<?php
/**
 * 后台操盘，卖给所有买单
 */
namespace DataAdmin\Controller;
use Think\Controller;
use Think\Model;
use Think\Log;
use Common\Lib\Constants;

class SellController extends BaseController{

    /**
     * 卖出所有买单-crontab
     * @return [type] [description]
     */
    public function autoTransact(){
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);

        //任务锁
        if(TradLockCheck(Constants::REDIS_PLANLOCK_NAME)){
            $fail = PlanFail(Constants::REDIS_PLANFAIL_NAME);
            if($fail > 5 && ($fail % 10 == 0)){
                $this->error('【请求次数过于频繁】，如非上述原因，请联系技术人员。');
            }
            PlanFail(Constants::REDIS_PLANLOCK_NAME,'inc');
            $this->error('【in execute】，交易匹配中，稍后重试。');
        }

        TradLock(Constants::REDIS_PLANLOCK_NAME, 'add', Constants::REDIS_PLANLOCK_TIME);
        $sellModel = D('TradingSell');
        $sellList = $sellModel ->backStageSellList();
        if(0 < count($sellList)){
                //卖单锁
            TradLock(Constants::REDIS_BUYLOCK_PREFIX . $sellList['id']);
            Log::write('购买今日买单--star--',Log::DEBUG);
                $result=$this->_cal_transact($sellList['id']);
            Log::write('购买今日买单--end--',Log::DEBUG);

//            //卖单锁
            TradLock(Constants::REDIS_BUYLOCK_PREFIX . $sellList['id'], 'del');
            //任务锁
            TradLock(Constants::REDIS_PLANLOCK_NAME, 'del');
            //重置错误次数
            PlanFail(Constants::REDIS_PLANFAIL_NAME,'reset');
            if($result['status']=0){
                $this->success('买入完成');
            }else{
                $this->success($result['msg']);
            }
        }else{
            //任务锁
            TradLock(Constants::REDIS_PLANLOCK_NAME, 'del');
            //重置错误次数
            PlanFail(Constants::REDIS_PLANFAIL_NAME,'reset');

            $this->error('【未挂卖单，或者卖单量已达到指标！】，请新增今日卖出挂单数量后，继续尝试！');
        }
    }

    /**
     * 匹配交易
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    private function _cal_transact($id){
        Log::write('Info:处理-SellID:'.$id,Log::DEBUG);
        $sellModel = D('TradingSell');
        $buyModel  = D('TradingBuy');
        $succModel = D('TradingSucc');
        $membersModel = D('members');
        $walletModel = D('Wallet');
        $logModel = D("MembersLog");
        $sell = $sellModel->findById($id);
        $succNum = $succModel -> sellSuccNum($sell['transno']);
        //可卖数量
        $sellNum = $sell['num'] - $succNum;
        if(0 >= $sellNum){
            Log::write('Error:可卖数量错误（'. $sellNum .'）',Log::DEBUG);
            $errlog=array(
                'status'=>1,
                'msg'=>'挂单不足，请增加挂单量。'
            );
            return $errlog ;
        }
        $price=$sell['price'];
        //买入队列
        $buyList = $buyModel -> buyList($price);
        if($buyList){
            $result = true;
            $membersModel->startTrans();
            foreach($buyList as $v){
                $buy = $buyModel -> findById($v['id']);
                Log::write('Info:匹配BuyID:'.$buy['id'],Log::DEBUG);
                //已买入
                $buySuccNum = $succModel -> buySuccNum($buy['transno']);
                //可买数量
                $buyNum = $buy['num'] - $buySuccNum;
                if(0 >= $buyNum){
                    Log::write('Error:可买数量错误（'. $buyNum .'）',Log::DEBUG);
                    continue ;
                }

//                //买入锁
                TradLock(Constants::REDIS_SELLLOCK_PREFIX . $buy['id']);
                //成交数量
                $transactNum = 0;
                if($sellNum > $buyNum){
                    $transactNum = $buyNum;
                }elseif($sellNum < $buyNum){
                    $transactNum = $sellNum;
                }else{
                    $transactNum = $sellNum;
                }
                ###成交
                    //成交订单号
                $transno = tradingOrderSN('T');
                Log::write('Info:成交订单号（'. $transno .'）',Log::DEBUG);

                //买入用户操作

                $outBalance = $price * $transactNum*(1+Constants::SCORE_BUY_FEE);//扣除总额
                //判断冻结资金是否足够
                $balance_lock=$membersModel->profiles($buy['uid'],'balance_lock');
                $outBalance=floor3($outBalance);
                if($balance_lock['balance_lock'] < $outBalance){
                    Log::write('Error:冻结数量错误（'. $outBalance .'）'.'Info:匹配BuyID:'.$buy['id'],Log::DEBUG);
                    continue ;
                }
                $buyFee=floor3($price * $transactNum*Constants::SCORE_BUY_FEE);//手续费
                //买入用户冻结KEY扣除
                $result = $result && $membersModel->balance_lock($buy['uid'], $outBalance,'out');
                //买入用户新增金链
                $result=$result && $walletModel->walletNumber($buy['uid'],$transactNum,'in');
                //买入用户直推推荐人奖励
                $leadInfo=$membersModel->profiles($buy['uid'],'leadid,teamid');
                if($leadInfo['leadid']){
                    $leadid=$leadInfo['leadid'];
                    $leadIsHave=$membersModel->profiles($leadid);
                    if($leadIsHave){
                        $leadBalance=floor3($price * $transactNum*Constants::PROFIT_PERSONAL);
                        if($leadBalance>=0.001){
                            $result=$result && $membersModel->balance($leadid,$leadBalance);
                            //个人推荐余额计算
                            $leadMembers=$membersModel->profiles($leadid,'balance,balance_lock');//KEY操作余额
                            $leadHandleBalance=bcsub($leadMembers['balance'],$leadMembers['balance_lock'],3);
                            //个人推荐费 subtype 11
                            $log = [
                                'uid' => $leadid,
                                'changeform' => 'in',
                                'subtype' => 11,
                                'money' => $leadBalance,
                                'ctime' => NOW_TIME,
                                'describes'=>'个人推荐费',
                                'balance' => $leadHandleBalance,
                                'extends' => $transno,
                                'money_type'=>2
                            ];
                            $result = $result && $logModel->add($log);

                            //个人推荐总收益
                            $result=$result && $membersModel->where(array('id'=>$leadid))->setInc('pgain',$leadBalance);
                            //平台统计个人收益总计
                            M('profit')->where(array('id'=>1))->setInc('profit_personal',$leadBalance);
                        }
                    }
                }
                //买入用户上级社区
                if($leadInfo['teamid']){
                    $teamlid=$leadInfo['teamid'];
                    $teamlInfo=$membersModel->profiles($teamlid,'is_pass');
                    if($teamlInfo['is_pass']){
                        $teamlBalance=floor3($price * $transactNum*Constants::PROFIT_TEAMl);
                        if($teamlBalance>=0.001){
                            $result=$result && $membersModel->balance($teamlid,$teamlBalance);

                            //个人推荐余额计算
                            $teamlMembers=$membersModel->profiles($teamlid,'balance,balance_lock');//KEY操作余额
                            $teamlHandleBalance=bcsub($teamlMembers['balance'],$teamlMembers['balance_lock'],3);
                            //初级推荐费 subtype 12
                            $log = [
                                'uid' => $teamlid,
                                'changeform' => 'in',
                                'subtype' => 12,
                                'money' => $teamlBalance,
                                'ctime' => NOW_TIME,
                                'describes'=>'初级社区推荐费',
                                'balance' => $teamlHandleBalance,
                                'extends' => $transno,
                                'money_type'=>2
                            ];
                            $result = $result && $logModel->add($log);

                            //团队推荐总收益
                            $result=$result && $membersModel->where(array('id'=>$teamlid))->setInc('cgain',$teamlBalance);
                            //平台统计初级团队收益总计
                            M('profit')->where(array('id'=>1))->setInc('profit_teaml',$teamlBalance);
                        }
                    }
                }
                //新增买手续费
                $result = $result && $membersModel->balance(2, $buyFee);
                //余额计算
                $buyBalance=$membersModel->profiles($buy['uid'],'balance,balance_lock');//KEY操作余额
                $buyWallet=$walletModel->WalletProfiles($buy['uid'],'wallet_number,wallet_lock');//钱包操作余额
                $balance=bcadd($buyBalance['balance'],$buyBalance['balance_lock'],3);
                $walletBalance=bcadd($buyWallet['wallet_number'],$buyWallet['wallet_lock'],2);
                    //买入用户 KEY新增日志
                $log = [
                    'uid' => $buy['uid'],
                    'changeform' => 'out',
                    'subtype' => 9,
                    'money' => $outBalance,
                    'ctime' => NOW_TIME,
                    'balance' => $balance,
                    'extends' => $transno,
                    'money_type'=>2
                ];
                $result = $result && $logModel->add($log);
                //金链买入日志
                $log = [
                    'uid' => $buy['uid'],
                    'changeform' => 'in',
                    'subtype' => 9,
                    'money' => $transactNum,
                    'ctime' => NOW_TIME,
                    'balance' => $walletBalance,
                    'extends' => $transno,
                    'money_type'=>1
                ];
                $result = $result && $logModel->add($log);


                //后台操盘用户扣除金链以及卖出手续费
                $result=$result && $walletModel->WalletNumber(1,$transactNum,'out');
                $sellTotal = $sell['price'] * $transactNum;
                $sellFee=round($sellTotal * Constants::SCORE_SELL_FEE,3);
                //后台操盘日志日志
                $log = [
                    'uid' =>1,
                    'changeform' => 'out',
                    'subtype' => 9,
                    'money' => $transactNum,
                    'ctime' => NOW_TIME,
                    'extends' => $transno,
                    'money_type'=>1
                ];
                $result = $result && $logModel->add($log);


                //生成成交单
                $order = [
                    'num' => $transactNum,
                    'price' => $sell['price'],
                    'ctime' => NOW_TIME,
                    'transno_sell' => $sell['transno'],
                    'transno_buy' => $buy['transno'],
                    'buy_uid' => $buy['uid'],
                    'sell_uid' => $sell['uid'],
                    'sell_fee' => $sellFee,
                    'buy_fee' => $buyFee,
                    'transno' => $transno,
                ];
                $result = $result && $succModel->add($order);
            

                //买入数量递减
                $sellNum -= $transactNum;
                //如果卖出数量为零标记卖出单 成功
                if(($buyNum - $transactNum) <= 0){
                    Log::write('Info:买单完成（BuyID: '. $buy['id'] .', 数量：'. $transactNum .', 价格：'. $buy['price'] .'）',Log::DEBUG);
                    $upd = [
                        'status' => Constants::ORDER_SUCCESS,
                        'ptime' => NOW_TIME,
                        'isclose' => 1,
                    ];

                    $result = $result && $buyModel->modify($buy['id'], $upd);
                }

                //清除买入锁
                TradLock(Constants::REDIS_SELLLOCK_PREFIX . $buy['id'], 'del');

                //卖出数量为零标记卖出单 成功
                if(0 >= $sellNum){
                    Log::write('Info:卖出单完成（'. $sell['id'] .'）',Log::DEBUG);
                    $upd = [
                        'status' => Constants::ORDER_SUCCESS,
                        'ptime' => NOW_TIME,
                        'isclose' => 1,
                    ];

                    $result = $result && $sellModel->modify($sell['id'], $upd);
                    break; 
                }

            }
            if($result){
                Log::write('Info:匹配完成（'. $sell['id'] .'）',Log::DEBUG);
                $membersModel->commit();
                $succlog=array(
                    'status'=>0,
                    'msg'=>'本次匹配已完成'
                );
                return $succlog ;
            }else{
                Log::write('Info:匹配失败（'. $sell['id'] .'）',Log::DEBUG);
                $membersModel->rollback();
                $errlog=array(
                    'status'=>1,
                    'msg'=>'本次匹配失败'
                );
                return $errlog ;
            }
            

        }else{
            $errlog=array(
                'status'=>1,
                'msg'=>'无挂卖订单'
            );
            return $errlog ;
        }

    }

    /**
     * 卖出所有买单-crontab
     * @return [type] [description]
     */
    public function RemainautoTransact(){
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);

        //任务锁
        if(TradLockCheck(Constants::REDIS_PLANLOCK_NAME)){
            $fail = PlanFail(Constants::REDIS_PLANFAIL_NAME);
            if($fail > 5 && ($fail % 10 == 0)){
                $this->error('【请求次数过于频繁】，如非上述原因，请联系技术人员。');
            }
            PlanFail(Constants::REDIS_PLANLOCK_NAME,'inc');
            $this->error('【in execute】，交易匹配中，稍后重试。');
        }

        TradLock(Constants::REDIS_PLANLOCK_NAME, 'add', Constants::REDIS_PLANLOCK_TIME);
        $sellModel = D('TradingSell');
        $sellList = $sellModel ->backStageSellList();
        if(0 < count($sellList)){
            $per=I('per');
            if(!$per || $per>0.5){
                TradLock(Constants::REDIS_PLANLOCK_NAME, 'del');
                //重置错误次数
                PlanFail(Constants::REDIS_PLANFAIL_NAME,'reset');
                $this->error('未设置比例,或比例设置过大');
            }
            //卖单锁
            TradLock(Constants::REDIS_BUYLOCK_PREFIX . $sellList['id']);
            Log::write('购买今日买单--star--',Log::DEBUG);
            $result=$this->_cal_remain__transact($sellList['id'],$per);
            Log::write('购买今日买单--end--',Log::DEBUG);

//            //卖单锁
            TradLock(Constants::REDIS_BUYLOCK_PREFIX . $sellList['id'], 'del');
            //任务锁
            TradLock(Constants::REDIS_PLANLOCK_NAME, 'del');
            //重置错误次数
            PlanFail(Constants::REDIS_PLANFAIL_NAME,'reset');
            if($result['status']=0){
                $this->success('买入完成');
            }else{
                $this->success($result['msg']);
            }
        }else{
            //任务锁
            TradLock(Constants::REDIS_PLANLOCK_NAME, 'del');
//            //重置错误次数
            PlanFail(Constants::REDIS_PLANFAIL_NAME,'reset');

            $this->error('【未挂卖单，或者卖单量已达到指标！】，请新增今日卖出挂单数量后，继续尝试！');
        }
    }

    /**
     * 匹配交易
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    private function _cal_remain__transact($id,$per){
        Log::write('Info:处理-SellID:'.$id,Log::DEBUG);
        $sellModel = D('TradingSell');
        $buyModel  = D('TradingBuy');
        $succModel = D('TradingSucc');
        $membersModel = D('members');
        $walletModel = D('Wallet');
        $logModel = D("MembersLog");
        $sell = $sellModel->findById($id);
        $succNum = $succModel -> sellSuccNum($sell['transno']);
        //可卖数量
        $sellNum = $sell['num'] - $succNum;
        if(0 >= $sellNum){
            Log::write('Error:可卖数量错误（'. $sellNum .'）',Log::DEBUG);
            $errlog=array(
                'status'=>1,
                'msg'=>'挂单不足，请增加挂单量。'
            );
            return $errlog ;
        }
        $price=$sell['price'];
        //买入队列
        $buyList = $buyModel -> buyList($price);
        $date = mktime(0,0,0,date('m'),date('d'),date('Y'));
        if($buyList){
            $roundTimes=0;
            $result = true;
            $membersModel->startTrans();
            foreach($buyList as $v){
                $buy = $buyModel -> findById($v['id']);
                Log::write('Info:匹配BuyID:'.$buy['id'],Log::DEBUG);
                //已买入
                $buySuccNum = $succModel -> buySuccNum($buy['transno']);
                //是否已成交过
               if($buySuccNum){
                   continue;
               }
                //可买数量
                $buyNum = $buy['num'] - $buySuccNum;
                if(0 >= $buyNum){
                    Log::write('Error:可买数量错误（'. $buyNum .'）',Log::DEBUG);
                    continue ;
                }

//                //买入锁
                TradLock(Constants::REDIS_SELLLOCK_PREFIX . $buy['id']);
                //当笔订单买入量
                $orderBuyNum = $buyNum;
                $buyNum=round($buyNum*$per,2);
                //成交数量
                $transactNum = 0;
                if($sellNum > $buyNum){
                    $transactNum = $buyNum;
                }elseif($sellNum < $buyNum){
                    $transactNum = $sellNum;
                }else{
                    $transactNum = $sellNum;
                }

                ###成交
                //成交订单号
                $transno = tradingOrderSN('T');
                Log::write('Info:成交订单号（'. $transno .'）',Log::DEBUG);

                //买入用户操作

                $outBalance = $price * $transactNum*(1+Constants::SCORE_BUY_FEE);//扣除总额
                //判断冻结资金是否足够
                $balance_lock=$membersModel->profiles($buy['uid'],'balance_lock');
                $outBalance=floor3($outBalance);
                if($balance_lock['balance_lock'] < $outBalance){
                    Log::write('Error:冻结数量错误（'. $outBalance .'）'.'Info:匹配BuyID:'.$buy['id'],Log::DEBUG);
                    continue ;
                }
                $buyFee=floor3($price * $transactNum*Constants::SCORE_BUY_FEE);//手续费
                //买入用户冻结KEY扣除
                $result = $result && $membersModel->balance_lock($buy['uid'], $outBalance,'out');
                //买入用户新增金链
                $result=$result && $walletModel->walletNumber($buy['uid'],$transactNum,'in');
                //买入用户直推推荐人奖励
                $leadInfo=$membersModel->profiles($buy['uid'],'leadid,teamid');
                if($leadInfo['leadid']){
                    $leadid=$leadInfo['leadid'];
                    $leadIsHave=$membersModel->profiles($leadid);
                    if($leadIsHave){
                        $leadBalance=floor3($price * $transactNum*Constants::PROFIT_PERSONAL);
                        if($leadBalance>=0.001){
                            $result=$result && $membersModel->balance($leadid,$leadBalance);
                            //个人推荐余额计算
                            $leadMembers=$membersModel->profiles($leadid,'balance,balance_lock');//KEY操作余额
                            $leadHandleBalance=bcsub($leadMembers['balance'],$leadMembers['balance_lock'],3);
                            //个人推荐费 subtype 11
                            $log = [
                                'uid' => $leadid,
                                'changeform' => 'in',
                                'subtype' => 11,
                                'money' => $leadBalance,
                                'ctime' => NOW_TIME,
                                'describes'=>'个人推荐费',
                                'balance' => $leadHandleBalance,
                                'extends' => $transno,
                                'money_type'=>2
                            ];
                            $result = $result && $logModel->add($log);

                            //个人推荐总收益
                            $result=$result && $membersModel->where(array('id'=>$leadid))->setInc('pgain',$leadBalance);
                            //平台统计个人收益总计
                            M('profit')->where(array('id'=>1))->setInc('profit_personal',$leadBalance);
                        }
                    }
                }
                //买入用户上级社区
                if($leadInfo['teamid']){
                    $teamlid=$leadInfo['teamid'];
                    $teamlInfo=$membersModel->profiles($teamlid,'is_pass');
                    if($teamlInfo['is_pass']){
                        $teamlBalance=floor3($price * $transactNum*Constants::PROFIT_TEAMl);
                        if($teamlBalance>=0.001){
                            $result=$result && $membersModel->balance($teamlid,$teamlBalance);

                            //个人推荐余额计算
                            $teamlMembers=$membersModel->profiles($teamlid,'balance,balance_lock');//KEY操作余额
                            $teamlHandleBalance=bcsub($teamlMembers['balance'],$teamlMembers['balance_lock'],3);
                            //初级推荐费 subtype 12
                            $log = [
                                'uid' => $teamlid,
                                'changeform' => 'in',
                                'subtype' => 12,
                                'money' => $teamlBalance,
                                'ctime' => NOW_TIME,
                                'describes'=>'初级社区推荐费',
                                'balance' => $teamlHandleBalance,
                                'extends' => $transno,
                                'money_type'=>2
                            ];
                            $result = $result && $logModel->add($log);

                            //团队推荐总收益
                            $result=$result && $membersModel->where(array('id'=>$teamlid))->setInc('cgain',$teamlBalance);
                            //平台统计初级团队收益总计
                            M('profit')->where(array('id'=>1))->setInc('profit_teaml',$teamlBalance);
                        }
                    }
                }
                //新增买手续费
                $result = $result && $membersModel->balance(2, $buyFee);
                //余额计算
                $buyBalance=$membersModel->profiles($buy['uid'],'balance,balance_lock');//KEY操作余额
                $buyWallet=$walletModel->WalletProfiles($buy['uid'],'wallet_number,wallet_lock');//钱包操作余额
                $balance=bcadd($buyBalance['balance'],$buyBalance['balance_lock'],3);
                $walletBalance=bcadd($buyWallet['wallet_number'],$buyWallet['wallet_lock'],2);
                //买入用户 KEY新增日志
                $log = [
                    'uid' => $buy['uid'],
                    'changeform' => 'out',
                    'subtype' => 9,
                    'money' => $outBalance,
                    'ctime' => NOW_TIME,
                    'balance' => $balance,
                    'extends' => $transno,
                    'money_type'=>2
                ];
                $result = $result && $logModel->add($log);
                //金链买入日志
                $log = [
                    'uid' => $buy['uid'],
                    'changeform' => 'in',
                    'subtype' => 9,
                    'money' => $transactNum,
                    'ctime' => NOW_TIME,
                    'balance' => $walletBalance,
                    'extends' => $transno,
                    'money_type'=>1
                ];
                $result = $result && $logModel->add($log);


                //后台操盘用户扣除金链以及卖出手续费
                $result=$result && $walletModel->WalletNumber(1,$transactNum,'out');
                $sellTotal = $sell['price'] * $transactNum;
                $sellFee=round($sellTotal * Constants::SCORE_SELL_FEE,3);
                //后台操盘日志日志
                $log = [
                    'uid' =>1,
                    'changeform' => 'out',
                    'subtype' => 9,
                    'money' => $transactNum,
                    'ctime' => NOW_TIME,
                    'extends' => $transno,
                    'money_type'=>1
                ];
                $result = $result && $logModel->add($log);


                //生成成交单
                $order = [
                    'num' => $transactNum,
                    'price' => $sell['price'],
                    'ctime' => NOW_TIME,
                    'transno_sell' => $sell['transno'],
                    'transno_buy' => $buy['transno'],
                    'buy_uid' => $buy['uid'],
                    'sell_uid' => $sell['uid'],
                    'sell_fee' => $sellFee,
                    'buy_fee' => $buyFee,
                    'transno' => $transno,
                ];
                $result = $result && $succModel->add($order);


                //买入数量递减
                $sellNum -= $transactNum;
                //如果卖出数量为零标记卖出单 成功
                if(($orderBuyNum - $transactNum) <= 0){
                    Log::write('Info:买单完成（BuyID: '. $buy['id'] .', 数量：'. $transactNum .', 价格：'. $buy['price'] .'）',Log::DEBUG);
                    $upd = [
                        'status' => Constants::ORDER_SUCCESS,
                        'ptime' => NOW_TIME,
                        'isclose' => 1,
                    ];

                    $result = $result && $buyModel->modify($buy['id'], $upd);
                }

                //清除买入锁
                TradLock(Constants::REDIS_SELLLOCK_PREFIX . $buy['id'], 'del');

                //卖出数量为零标记卖出单 成功
                if(0 >= $sellNum){
                    Log::write('Info:卖出单完成（'. $sell['id'] .'）',Log::DEBUG);
                    $upd = [
                        'status' => Constants::ORDER_SUCCESS,
                        'ptime' => NOW_TIME,
                        'isclose' => 1,
                    ];

                    $result = $result && $sellModel->modify($sell['id'], $upd);
                    break;
                }

            }
            if($result){
                Log::write('Info:匹配完成（'. $sell['id'] .'）',Log::DEBUG);
                $membersModel->commit();
                $succlog=array(
                    'status'=>0,
                    'msg'=>'本次匹配已完成'
                );
                return $succlog ;
            }else{
                Log::write('Info:匹配失败（'. $sell['id'] .'）',Log::DEBUG);
                $membersModel->rollback();
                $errlog=array(
                    'status'=>1,
                    'msg'=>'本次匹配失败'
                );
                return $errlog ;
            }


        }else{
            $errlog=array(
                'status'=>1,
                'msg'=>'无挂卖订单'
            );
            return $errlog ;
        }

    }

}