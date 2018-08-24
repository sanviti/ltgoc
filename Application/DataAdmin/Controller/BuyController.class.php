<?php
/**
 * 后台操盘，买入所有卖单
 */
namespace DataAdmin\Controller;
use Think\Controller;
use Think\Model;
use Think\Log;
use Common\Lib\Constants;

class BuyController extends BaseController{

    /**
     * 买入所有卖单-crontab
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
        $buyModel = D('TradingBuy');
        $buyList = $buyModel ->backStageBuyList();
        if(0 < count($buyList)){
                //买单锁
                TradLock(Constants::REDIS_BUYLOCK_PREFIX . $buyList['id']);
            Log::write('购买今日卖单--star--',Log::DEBUG);
                $result=$this->_cal_transact($buyList['id']);
            Log::write('购买今日卖单--end--',Log::DEBUG);
            //买单锁
            TradLock(Constants::REDIS_BUYLOCK_PREFIX . $buyList['id'], 'del');
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

            $this->error('【未挂买单，或者买单量已达到指标！】，请新增今日买入挂单数量后，继续尝试！');
        }
    }

    /**
     * 匹配交易
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    private function _cal_transact($id){
        Log::write('Info:处理-BuyID:'.$id,Log::DEBUG);
        $buyModel  = D('TradingBuy');
        $sellModel = D('TradingSell');
        $succModel = D('TradingSucc');
        $membersModel = D('members');
        $walletModel = D('Wallet');
        $logModel = D("MembersLog");
        $buy = $buyModel->findById($id);
        $succNum = $succModel -> buySuccNum($buy['transno']);
        //可买数量
        $buyNum = $buy['num'] - $succNum;
        if(0 >= $buyNum){
            Log::write('Error:可买数量错误（'. $buyNum .'）',Log::DEBUG);
            $errlog=array(
                'status'=>1,
                'msg'=>'挂单不足，请增加挂单量。'
            );
            return $errlog ;
        }
        $price=$buy['price'];
        //卖出队列
        $sellList = $sellModel -> sellList($price);
        if($sellList){
            $result = true;
            $membersModel->startTrans();
            foreach($sellList as $v){
                $sell = $sellModel -> findById($v['id']);
                Log::write('Info:匹配SellID:'.$sell['id'],Log::DEBUG);
                //已卖出
                $sellSuccNum = $succModel -> sellSuccNum($sell['transno']);
                //可卖数量
                $sellNum = $sell['num'] - $sellSuccNum;
                if(0 >= $sellNum){
                    Log::write('Error:可卖数量错误（'. $sellNum .'）',Log::DEBUG);
                    continue ;
                }

                //卖出锁
                TradLock(Constants::REDIS_SELLLOCK_PREFIX . $sell['id']);
                //成交数量
                $transactNum = 0;
                if($buyNum > $sellNum){
                    $transactNum = $sellNum;
                }elseif($buyNum < $sellNum){
                    $transactNum = $buyNum;
                }else{
                    $transactNum = $sellNum;
                }
                ###成交
                    //成交订单号
                $transno = tradingOrderSN('T');
                Log::write('Info:成交订单号（'. $transno .'）',Log::DEBUG);
                //卖出用户操作
                //冻结金链扣除
                $result=$result && $walletModel->WalletNumberLock($sell['uid'],$transactNum,'out');

                //KEY新增
                $total = floor3($sell['price'] * $transactNum);
                $sellFee=floor3($total * Constants::SCORE_SELL_FEE);
                $InBalance = floor3($total - $sellFee);
                $result = $result && $membersModel->balance($sell['uid'], $InBalance);
                //新增买卖手续费
                $result = $result && $membersModel->balance(2, $sellFee);
                //余额计算
                $sellBalance=$membersModel->profiles($sell['uid'],'balance,balance_lock');//KEY操作余额
                $sellWallet=$walletModel->WalletProfiles($sell['uid'],'wallet_number,wallet_lock');//钱包操作余额
                $walletBalance=bcadd($sellWallet['wallet_number'],$sellWallet['wallet_lock'],2);
                $balance=bcadd($sellBalance['balance'],$sellBalance['balance_lock'],3);
                    //KEY新增日志
                $log = [
                    'uid' => $sell['uid'],
                    'changeform' => 'in',
                    'subtype' => 9,
                    'money' => $InBalance,
                    'ctime' => NOW_TIME,
                    'balance' => $balance,
                    'extends' => $transno,
                    'money_type'=>2
                ];
                $result = $result && $logModel->add($log);
                //金链卖出日志
                $log = [
                    'uid' => $sell['uid'],
                    'changeform' => 'out',
                    'subtype' => 9,
                    'money' => $transactNum,
                    'ctime' => NOW_TIME,
                    'balance' => $walletBalance,
                    'extends' => $transno,
                    'money_type'=>1
                ];
                $result = $result && $logModel->add($log);


                //后台操盘用户添加金链以及买入手续费
                $result=$result && $walletModel->WalletNumber(1,$transactNum);
                $buyTotal = $sell['price'] * $transactNum;
                $buyFee=round($buyTotal * Constants::SCORE_BUY_FEE,3);
                //后台操盘日志日志
                $log = [
                    'uid' =>1,
                    'changeform' => 'in',
                    'subtype' => 9,
                    'money' => $transactNum,
                    'ctime' => NOW_TIME,
                    'extends' => $transno
                ];
                $result = $result && $logModel->add($log);


                //生成成交单
                $order = [
                    'num' => $transactNum,
                    'price' => $buy['price'],
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
                $buyNum -= $transactNum;

                //如果卖出数量为零标记卖出单 成功
                if(($sellNum - $transactNum) <= 0){
                    Log::write('Info:卖出单完成（SellID: '. $sell['id'] .', 数量：'. $transactNum .', 价格：'. $sell['price'] .'）',Log::DEBUG);
                    $upd = [
                        'status' => Constants::ORDER_SUCCESS,
                        'ptime' => NOW_TIME,
                        'isclose' => 1,
                    ];

                    $result = $result && $sellModel->modify($sell['id'], $upd);
                }

                //清除卖出锁
                TradLock(Constants::REDIS_SELLLOCK_PREFIX . $sell['id'], 'del');

                //买入数量为零标记买入单 成功
                if(0 >= $buyNum){
                    Log::write('Info:买入单完成（'. $buy['id'] .'）',Log::DEBUG);
                    $upd = [
                        'status' => Constants::ORDER_SUCCESS,
                        'ptime' => NOW_TIME,
                        'isclose' => 1,
                    ];

                    $result = $result && $buyModel->modify($buy['id'], $upd);
                    break; 
                }

            }
            if($result){
                Log::write('Info:匹配完成（'. $buy['id'] .'）',Log::DEBUG);
                $membersModel->commit();
                $succlog=array(
                    'status'=>0,
                    'msg'=>'本次匹配已完成'
                );
                return $succlog ;
            }else{
                Log::write('Info:匹配失败（'. $buy['id'] .'）',Log::DEBUG);
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
     * 自动撤销过期订单-crontab
     * @return [type] [description]
     */
    public function autoRecall(){
        //任务锁
        //撤单买入

        //撤单卖出
    }

    private function _recall(){

    }

}