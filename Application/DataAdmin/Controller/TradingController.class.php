<?php
/**
 * 订单管理
 */
namespace DataAdmin\Controller;
use Think\Controller;
Use Think\Cache\Driver\Redis;
use Common\Lib\RestSms;
use Common\Lib\Constants;
class TradingController extends BaseController{

    //首页
    public function index(){
        $this -> display();
    }

    //最新数据
    public function refresh(){
        $begin = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $map = 'ptime > ' . $begin . ' AND order_type = 1 AND status IN (0,1)';
        $orderModel = M('tradingOrder');
        $buy_sum = $orderModel->where($map)->sum('num');

        $map2 = 'ptime > ' . $begin . ' AND order_type = 2 AND status IN (0,1)';
        $sell_sum = $orderModel->where($map2)->sum('num');

        $map3 = 'ptime > ' . $begin . ' AND order_type = 1';
        $buy_succ_sum = $orderModel->where($map3)->sum('succ_num');

        $map4 = 'ptime > ' . $begin . ' AND order_type = 2';
        $sell_succ_sum = $orderModel->where($map4)->sum('succ_num');

        //平台持有
        $gocModel  = M('goc');
        $lastData  = $gocModel->where('id = 1')->find();
        $sys_goc   = $lastData['sys_goc'];
        $user_goc  = $lastData['user_goc'];
        $total_goc = $sys_goc + $user_goc;
        $user_goc_db  = M('members')->sum('goc+goc_lock');
        if($total_goc != 80000000 || $user_goc_db != $user_goc){
            $sys_goc  = '数据异常';
            $user_goc = '数据异常';
        }

        $data = array(
            'buy_sum' => number_format($buy_sum, 2),
            'sell_sum' =>number_format($sell_sum, 2),
            'buy_succ_sum' => number_format($buy_succ_sum, 2),
            'sell_succ_sum' => number_format($sell_succ_sum, 2),
            'sys_goc' => $sys_goc,
            'user_goc' => $user_goc,
            );
        $this->ajaxReturn($data);
    }

    //成本明细
    public function priceGroup(){
        $model = M('tradingOrderSell');
        $list = $model->field('sum(buy_num - sell_num) as num, price')->where('status = 0')->group('price')->select();
        $this->ajaxReturn($list);
    }

    //强制卖出
    public function compelSell(){
        set_time_limit(0);
        $buyPrice = input_numb(I('price'), 3);
        $model = M('tradingOrderSell');
        $priceModel = D('Api/TradingPrice');
        $orderModel = M('tradingOrder');
        $userModel = D('Api/Members');
        $logModel = M('userLog');

        $list = $model->field('uid')->where('status = 0 AND price = %s', $buyPrice)->group('uid')->select();
        $TradingPrice = $priceModel->getPrice();
        $price = $TradingPrice['price'];
        if($price <= 0)$this->error('卖出失败');

        if($list){
            foreach($list as $vo){
                $model->startTrans();
                $user = $userModel->account($vo['uid']);

                $num = $model->where('uid = %d AND price = %s AND status = 0', $vo['uid'], $buyPrice)->sum('buy_num - sell_num');

                //用户扣除GOC、余额增加
                $addBal = new_bcmul($price, $num); //收益余额
                $fee = new_bcmul($addBal, Constants::SCORE_SELL_FEE);
                $insbal = new_bcsub($addBal, $fee);//实际增加余额
                // dump($insbal);
                $newBal = new_bcadd($user['balance_lock'], new_bcadd($user['balance'], $insbal));
                $gocBal = new_bcadd($user['goc'], new_bcsub($user['goc_lock'], $num));

                $result = $userModel->compelSellGoc($user, $num, $insbal);
                // dump($result);
                //用户GOC日志
                $log1 = array(
                    'uid' => $vo['uid'],
                    'changeform' => 'out',
                    'money_type' => '5',
                    'subtype' => '52',
                    'money' => $num,
                    'ctime' => NOW_TIME,
                    'balance' => $gocBal,
                );
                $result = $result && $logModel->add($log1);
                // dump($result);

                //用户余额日志
                $log2 = array(
                    'uid' => $vo['uid'],
                    'changeform' => 'in',
                    'money_type' => '1',
                    'subtype' => '11',
                    'money' => $insbal,
                    'ctime' => NOW_TIME,
                    'balance' => $newBal,
                );

                $result = $result && $logModel->add($log2);
                // dump($result);

                //插入订单
                $order = array(
                    'uid' => $vo['uid'],
                    'num' => $num,
                    'succ_num' => $num,
                    'price' => $price,
                    'order_type' => 2,
                    'fee' => $fee,
                    'transno' => tradingOrderSN('S'),
                    'ptime' => NOW_TIME,
                    'stime' => NOW_TIME,
                    'status' => 1,
                );
               
                $result = $result && $orderModel->add($order);
                // echo M()->getLastSql();
                // dump($result);

                //更新用户买入日志
                $buyLogModel = M('tradingOrderSell');
                $buylist = $buyLogModel->where('uid = %d AND status = 0 AND price = %s', $vo['uid'], $buyPrice)->order('id ASC')->select();
                $useNum = $num;

                foreach($buylist as $item){
                    $itemNum = new_bcsub($item['buy_num'], $item['sell_num']);
                    if($itemNum >= $useNum){
                        $currNum = $useNum;
                    }else{
                        $currNum = $itemNum;
                    }

                    $useNum -= $currNum;
                    $newSellNum = new_bcadd($item['sell_num'], $currNum);
                    $buyUpd = array('sell_num' => $newSellNum);
                    if($newSellNum == $item['buy_num']){
                        $buyUpd['status'] = 1;
                    }
                    $result = $result && $buyLogModel->where('id = %d', $item['id'])->save($buyUpd);
                    // dump($useNum);
                    // dump(M()->getLastSql());
                    if($useNum == 0){
                        break;
                    }

                }

                //用户成本重新计算
                $depModel = M('membersDep');
                $userDep = $depModel->field('sell_cny')->where('uid = %d', $vo['uid'])->find(); 
                $sellCny = new_bcadd($userDep['sell_cny'], $addBal);
                $result = $result && $depModel->where('uid = %d', $vo['uid'])->save(array('sell_cny' => $sellCny));
                // dump($result);
                
                //平台持有增加
                $gocModel = M('goc');
                $lastData = $gocModel->where('id = 1')->find();
                $sysGoc = new_bcadd($lastData['sys_goc'], $num);
                $userGoc = new_bcsub($lastData['user_goc'], $num);
                $data = array(
                    'sys_goc' => $sysGoc,
                    'user_goc' => $userGoc,
                    'last_time' => NOW_TIME,
                );
                $result = $result && $gocModel->where('id = 1')->save($data);
                // dump($result);
                
                if($result){
                    $orderModel->commit();
                }else{
                    $orderModel->rollback();
                }

                $this->success('操作成功');

            }
            
        }else{
            $this->error('没有订单');
        }


    }
    //订单列表
    public function orderlist(){
        $type = intval(I('type')); //1买入  2卖出
        $limit = intval(I('limit'));
        $offset = intval(I('offset'));

        $orderModel = M('tradingOrder');
        $begin = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $map = 'ptime > ' . $begin . ' AND order_type = ' . $type;
        $search = intval(str_ireplace('U', '', I('search')));
        if($search){
            $map .= ' AND uid = ' . $search;
        }
        $count = $orderModel->where($map)->count();
        $list = $orderModel->where($map)->order('id ASC')->limit($offset, $limit)->select();
        $rows = array();
        foreach($list as $key => $item){
            $temp['id'] = $item['id'];
            $temp['num'] = $item['num'];
            $temp['succ_num'] = $item['succ_num'];
            $temp['user'] = $item['uid'];
            $temp['time'] = date('H:i:s', $item['ptime']);
            $temp['status'] = $item['status'];
            array_push($rows, $temp);
        }
        $data['total'] = $count;
        $data['rows'] = $rows;
        $this->ajaxReturn($data);
    }

    //批量成交
    public function batch_succ(){
        set_time_limit(0);
        $type = intval(I('get.type'));
        $total = I('total');
        if($type != 1 && $type != 2){
            $this->error('请选择成交类型');
        }

        if($type == 1){
            $total = input_numb(I('buy_total'));
            $rate = input_numb(I('buy_rate'));
            $match_type = I('buy_match_type');
        }else{
            $total = input_numb(I('sell_total'));
            $rate = input_numb(I('sell_rate'));
            $match_type = I('sell_match_type');
        }

        if($match_type == 2){
            if(! ($rate > 0 && $rate < 100) ){
                $this->error('请设置百分比');
            }
        }

        if($total < 0){
            $this->error('成交数量未设置');
        }

        $msg = '';
        $planName = 'batch_succ';
        $failName = 'batch_fail';

        //任务锁
        if(checkLock($planName)){
            $fail = PlanFail($failName);
            if($fail > 5 && ($fail % 10 == 0)){
                $this->error('【请求次数过于频繁】，如非上述原因，请联系技术人员。');
            }
            PlanFail($failName, 'inc');
            $this->error('【in execute】，交易匹配中，稍后重试。');
        }

        setLock($planName, 'add', 120);

        $orderModel = M('tradingOrder');
        $begin = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $map = 'ptime > ' . $begin . ' AND order_type = '. $type .' AND status = 0';
        $list = $orderModel -> field('id') -> where($map) -> select();
        if(0 < count($list)){
            // $this->p('start');

            foreach($list as $item){
                $res = $this->_succ($item['id'], $total, $match_type, $rate);
                // dump($res);
                if($res['status'] === false){
                    continue;
                }
                if($res['succ_num'] === 0){
                    break;
                }
                $total -= $res['succ_num'];

            }
            // $this->p('end');
            $msg = '处理完成';
        }else{
            $msg = '没有订单';
        }
        //删除任务锁
        setLock($planName, 'del');
        //重置错误次数
        PlanFail($failName, 'reset');

        $this->success($msg);
    }

    //单笔成交
    private function _succ($orderid, $total, $match_type, $rate){
        $res['status'] = false;
        $res['succ_num'] = 0;
        $orderModel = M('tradingOrder');
        $userModel = D('Api/Members');
        $logModel = M('userLog');

        $orderModel->startTrans();
        $order = $orderModel->lock(true)->where('id = %d AND status = 0', $orderid)->find();
        if($match_type == 1){ //全量成交
            $num = new_bcsub($order['num'], $order['succ_num']);
        }else{ //百分比成交
            $num = new_bcsub($order['num'], $order['succ_num']);
            if($num == $order['num']){
                $num = new_bcmul($order['num'], new_bcdiv($rate, 100));
            }else{
                $ratenum = new_bcmul($order['num'], new_bcdiv($rate, 100));
                if($num > $ratenum){
                    $num = $ratenum;
                }
            }
        }
        // dump($order);

        if($order && $total >= $num){
            $user = $userModel->account($order['uid'], 'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign,path');
            if($order['order_type'] == 1){
                //用户扣除冻结usdc 增加GOC
                $usdc = new_bcmul($order['price'], $num);
                $fee  = new_bcmul($usdc, Constants::SCORE_BUY_FEE);
                $usdc = new_bcadd($usdc, $fee);

                $result = $userModel->buyGoc($user, $num, $usdc);
                $usdcBal = new_bcadd($user['usdc'], new_bcsub($user['usdc_lock'], $usdc));
                //用户日志
                $log1 = array(
                    'uid' => $order['uid'],
                    'changeform' => 'out',
                    'money_type' => '2',
                    'subtype' => '22',
                    'money' => $usdc,
                    'ctime' => NOW_TIME,
                    'balance' => $usdcBal,
                );
                $result = $result && $logModel->add($log1);
                // dump($result);
                //用户增加GOC
                $gocBal = new_bcadd($user['goc'], new_bcadd($user['goc_lock'], $num));

                //用户GOC日志
                $log2 = array(
                    'uid' => $order['uid'],
                    'changeform' => 'in',
                    'money_type' => '5',
                    'subtype' => '51',
                    'money' => $num,
                    'ctime' => NOW_TIME,
                    'balance' => $gocBal,
                );
                $result = $result && $logModel->add($log2);
                // dump($result);

                //社区奖励
                $totle = new_bcmul($order['price'], $num);
                $nPath = substr($user['path'], 0, strripos($user['path'], ','));
                // dump($nPath);
                if($nPath){
                    //第一个社区
                    $team1 = $userModel->where('id in (%s) AND vip_level > 1', $nPath)->order(' id DESC')->find();
                    if($team1){
                        $rate = get_team_rete_buygoc($team1['vip_level']);
                        $rewardNum  = new_bcmul($totle, $rate);
                        // dump($rate);
                        $newBalan = new_bcadd(new_bcadd($team1['balance'], $rewardNum), $team1['balance_lock']);
                        $result = $result && $userModel->changeBalance($team1, $rewardNum);
                // dump($result);

                        $log = array('uid' => $team1['id'], 'changeform' => 'in', 'subtype' => 15, 'money' => $rewardNum, 'ctime' => NOW_TIME, 'balance' => $newBalan, 'extends' => $user['id'], 'money_type' => 1);
                        $result = $result && $logModel->add($log);
                // dump($result);

                    }
                    //第二个社区
                    if($team1 && $team1['vip_level'] < 3){
                        $team2 = $userModel->where('id in (%s) AND vip_level = 3', $nPath)->order(' id DESC')->find();
                        if($team2){
                            $rate = get_team_rete_buygoc($team2['vip_level']);
                            $rewardNum  = new_bcmul($totle, $rate);
                            $newBalan = new_bcadd(new_bcadd($team2['balance'], $rewardNum), $team2['balance_lock']);
                            $result = $result && $userModel->changeBalance($team2, $rewardNum);
                            $log = array('uid' => $team2['id'], 'changeform' => 'in', 'subtype' => 15, 'money' => $rewardNum, 'ctime' => NOW_TIME, 'balance' => $newBalan, 'extends' => $user['id'], 'money_type' => 1);
                            $result = $result && $logModel->add($log);
                        }
                    }
                }
                // echo 'shequ';
                // dump($result);

                
                //订单修改
                $ordSuccNum = new_bcadd($order['succ_num'], $num);
                $ordUpd = array('succ_num' => $ordSuccNum);
                if($ordSuccNum == $order['num']){
                    $ordUpd['status'] = 1;
                    $ordUpd['stime'] = NOW_TIME;
                }
                $result = $result && $orderModel->where('id = %d', $order['id'])->save($ordUpd);
                // dump($result);

                //增加用户买入日志
                $insertBuy = array(
                    'transno' => $order['transno'],
                    'price' => $order['price'],
                    'buy_num' => $num,
                    'sell_num' => 0,
                    'uid' => $order['uid'],
                    'status' => 0,
                );
                $result = $result && M('tradingOrderSell')->add($insertBuy);
                // dump($result);


                //用户成本重新计算
                $depModel = M('membersDep');
                $userDep = $depModel->field('buy_cny')->where('uid = %d', $order['uid'])->find(); 
                $buyCny = new_bcadd($userDep['buy_cny'], $usdc);
                $result = $result && $depModel->where('uid = %d', $order['uid'])->save(array('buy_cny' => $buyCny));
                // dump($result);

                //平台持有减少
                $gocModel = M('goc');
                $lastData = $gocModel->where('id = 1')->find();
                $sysGoc = new_bcsub($lastData['sys_goc'], $num);
                $userGoc = new_bcadd($lastData['user_goc'], $num);
                $data = array(
                    'sys_goc' => $sysGoc,
                    'user_goc' => $userGoc,
                    'last_time' => NOW_TIME,
                );
                $result = $result && $gocModel->where('id = 1')->save($data);
                // dump($result);
                
                if($result){
                    $res['status'] = true;
                    $res['succ_num'] = $num;
                    $orderModel->commit();
                }else{
                    $orderModel->rollback();
                }

            }else{
                // dump($order);

                //用户扣除GOC、余额增加
                $addBal = new_bcmul($order['price'], $num); //收益余额
                $fee = new_bcmul($addBal, Constants::SCORE_SELL_FEE);
                $insbal = new_bcsub($addBal, $fee);//实际增加余额

                $newBal = new_bcadd($user['balance_lock'], new_bcadd($user['balance'], $insbal));
                $gocBal = new_bcadd($user['goc'], new_bcsub($user['goc_lock'], $num));

                $result = $userModel->sellGoc($user, $num, $insbal);
                // dump($result);
                //用户GOC日志
                $log1 = array(
                    'uid' => $order['uid'],
                    'changeform' => 'out',
                    'money_type' => '5',
                    'subtype' => '52',
                    'money' => $num,
                    'ctime' => NOW_TIME,
                    'balance' => $gocBal,
                );
                $result = $result && $logModel->add($log1);
                // dump($result);

                //用户余额日志
                $log2 = array(
                    'uid' => $order['uid'],
                    'changeform' => 'in',
                    'money_type' => '1',
                    'subtype' => '11',
                    'money' => $insbal,
                    'ctime' => NOW_TIME,
                    'balance' => $newBal,
                );

                $result = $result && $logModel->add($log2);
                // dump($result);

                //订单修改
                $ordSuccNum = new_bcadd($order['succ_num'], $num);
                $ordUpd = array('succ_num' => $ordSuccNum);
                if($ordSuccNum == $order['num']){
                    $ordUpd['status'] = 1;
                    $ordUpd['stime'] = NOW_TIME;
                }
                $result = $result && $orderModel->where('id = %d', $order['id'])->save($ordUpd);
                // echo M()->getLastSql();
                // dump($result);

                //更新用户买入日志
                $buyLogModel = M('tradingOrderSell');
                $buylist = $buyLogModel->where('uid = %d AND status = 0', $order['uid'])->order('id ASC')->select();
                $useNum = $num;

                foreach($buylist as $item){
                    $itemNum = new_bcsub($item['buy_num'], $item['sell_num']);
                    if($itemNum >= $useNum){
                        $currNum = $useNum;
                    }else{
                        $currNum = $itemNum;
                    }

                    $useNum -= $currNum;
                    $newSellNum = new_bcadd($item['sell_num'], $currNum);
                    $buyUpd = array('sell_num' => $newSellNum);
                    if($newSellNum == $item['buy_num']){
                        $buyUpd['status'] = 1;
                    }
                    $result = $result && $buyLogModel->where('id = %d', $item['id'])->save($buyUpd);
                    // dump($useNum);
                    // dump(M()->getLastSql());
                    if($useNum == 0){
                        break;
                    }

                }
                // dump($result);

                //用户成本重新计算
                $depModel = M('membersDep');
                $userDep = $depModel->field('sell_cny')->where('uid = %d', $order['uid'])->find(); 
                $sellCny = new_bcadd($userDep['sell_cny'], $addBal);
                $result = $result && $depModel->where('uid = %d', $order['uid'])->save(array('sell_cny' => $sellCny));
                // dump($result);
                
                //平台持有增加
                $gocModel = M('goc');
                $lastData = $gocModel->where('id = 1')->find();
                $sysGoc = new_bcadd($lastData['sys_goc'], $num);
                $userGoc = new_bcsub($lastData['user_goc'], $num);
                $data = array(
                    'sys_goc' => $sysGoc,
                    'user_goc' => $userGoc,
                    'last_time' => NOW_TIME,
                );
                $result = $result && $gocModel->where('id = 1')->save($data);
                // dump($result);
                
                if($result){
                    $res['status'] = true;
                    $res['succ_num'] = $num;
                    $orderModel->commit();
                }else{
                    $orderModel->rollback();
                }
            }
        }

        return $res;
    }

    /**
     * 单笔全量成交
     * @return [type] [description]
     */
    public function oneOrderSucc(){
        $id = I('id/d');
        $res = $this->_succ($id, 2000, 1, 0);
        if($res['status'] === true){
            $this->success('处理成功【成交：'.$res['succ_num'].'】');
        }else{
            $this->error('处理失败');
        }
    }

    /**
     * 撤销订单
     * @return [type] [description]
     */
    public function cancel_order(){
        $id = I('id/d');
        $model = D('Api/TradingOrder');
        $result = $model->cancel($id);
        if($result){
            $this->success('撤销成功');
        }else{
            $this->error('撤销失败');
        }
    }

     private function p($str){
        if($str == 'start'){
            echo '###################################### START #######################################<br/>';
        }elseif($str == 'end'){
            echo '###################################### END #######################################<br/>';
        }else{
            echo $str."<br/>";
        }
    }
}