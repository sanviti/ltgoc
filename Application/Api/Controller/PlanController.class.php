<?php
/**
 * 计划任务
 * 2017-12-15
 * lxy
 */
namespace Api\Controller;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class PlanController extends Controller{

    /**
     * 会长评级 level_1 每天6-24点 每分钟1次
     * @return [type] [description]
     */
    public function memberLevel(){
        set_time_limit(0);
        $plan_name = Constants::PLAN_MEMBER_LEVEL;
        $transNum  = 100;
        //检查锁
        if(checkLock($plan_name)){
            die('runing');
        }
        //加锁
        setLock($plan_name, 'add');
        $lastid = getMemberLastID();
        //超出重置最后表ID
        if($lastid === false){
            $lastid = 0;
            setLastTableID(0);
        }


        $memberModel = D('members');
        $map = "((children_num >= 5 AND team_people_num >= 30 AND team_power >= 30) OR vip_level > 0) AND id > {$lastid}";

        $memberModel->startTrans();
        $list = $memberModel->lock(true)->field('id,children_num,team_people_num,team_power,vip_level,path')->where($map)->limit($transNum)->select();
        if($list){
            $result =true;
            foreach($list as $item){
                $lastid = $item['id'];
                $newlevel = 0;
                $star1 = $memberModel->where('leadid = ' . $item['id'] . ' AND vip_level >= 1')->count();
                $star2 = $memberModel->where('leadid = ' . $item['id'] . ' AND vip_level >= 2')->count();
                if($item['children_num'] >= 5 && $item['team_people_num'] >= 30 && $item['team_power'] >= 30){
                    $newlevel = 1;
                }
                if($star1 >= 3 && $item['team_power'] >= 100){
                    $newlevel = 2;
                }
                if($star2 >= 3 && $item['team_power'] >= 500){
                    $newlevel = 3;
                }
                
                if($item['vip_level'] != $newlevel){
                    $result = $result && $memberModel->where('id = ' . $item['id'])->save(array('vip_level' => $newlevel));
                    //赠送矿机
                    if($newlevel > 0){
                        switch ($newlevel) {
                            case '1':
                                $millType = 2; //赠送小型矿机
                                $get_way  = 'uplevel1';
                                break;
                            case '2':
                                $millType = 3; //赠送中型矿机
                                $get_way  = 'uplevel2';
                                break;
                            case '3':
                                $millType = 4; //赠送大型矿机
                                $get_way  = 'uplevel3';                            
                                break;
                        }
                        
                        $awradModel = M('awardMill');
                        $map = array('uid' => $item['id'], 'type' => $millType);
                        $is_give = $awradModel->where($map)->count();
                        if(!$is_give){
                            $millModel = M(get_mill_table($item['id']));
                            $messageModel = M('message');
                            #赠送矿机
                            $mill = array(
                                'uid' => $item['id'],
                                'type' => $millType,
                                'last_time' => NOW_TIME,
                                'create_time' => NOW_TIME,
                                'status' => 1,
                                'mill_value' => mill_max_output($millType),
                                'mill_sn' => gen_mill_sn($item['id'], $millType),
                                'get_way' => $get_way
                            );
                            $result = $result && $millModel->add($mill);
                            #团队算力
                            $power = mill_power($millType);
                            $result = $result && $memberModel->upd_team_power($item['path'], $power, 'inc');
                            //个人算力
                            $result = $result && $memberModel->upd_power($item['id'], $power, 'inc');
                            $result = $result && $awradModel->add(array('uid' => $item['id'], 'type' => $millType, 'rtime' => NOW_TIME));
                            #消息
                            $messages = array(
                                'title' => '光电机组开通成功',
                                'describe' => '恭喜您获得一台'.mill_name($millType).'!，来自系统赠送',
                                'type' => '1',
                                'ctime' => NOW_TIME,
                                'uid' => $item['id'],
                            );
                            $result = $result && $messageModel->add($messages);
                        }
                    }
                }
            }

            if($result){
                $memberModel->commit();
                $this->p('memberLevel-success:'. $lastid);
            }else{
                $memberModel->rollback();
                $this->p('memberLevel-fail:'. $lastid);
            }
        }else{
            $this->p('没有数据:' . $lastid);
            $memberModel->rollback();
        }


        //表ID递增
        if(count($list) < $transNum){
            setMemberLastID(0);
        }else{
            setMemberLastID($lastid);
        }
        //解锁
        setLock($plan_name, 'del');
    }



    /**
     * 社区分红奖励   每天0-3点  每分钟1次 每次500人
     * @return [type] [description]
     */
    public function awardTeam(){
        set_time_limit(0);
        $plan_name = Constants::PLAN_AWARD_TEAM;
        $transNum  = 500;
        //检查锁
        if(checkLock($plan_name)){
            $this->p('runing');
            exit;
        }
        //加锁
        setLock($plan_name, 'add');
        #################开始################

        $begin = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $end   = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $map1 = "order_type = 1 AND (ptime > {$begin} AND ptime < {$end})";
        $map2 = "order_type = 2 AND (ptime > {$begin} AND ptime < {$end})";
        $orderModel = M('tradingOrder');
        $buySuccUsdc = $orderModel->where($map1)->sum('succ_num * price');
        $sellSuccUsdc = $orderModel->where($map2)->sum('succ_num * price');

        $buyFee = new_bcmul($buySuccUsdc, Constants::SCORE_BUY_FEE);
        $sellFee = new_bcmul($sellSuccUsdc, Constants::SCORE_SELL_FEE);
        $fee_total = new_bcadd($buyFee, $sellFee);


        if($fee_total > 0){
            $memberModel = D('members');
            $logModel = D('membersLog');
            
            $map = "last_cal_time < {$begin} AND vip_level > 1";
            $lists = $memberModel->field('id')->lock(true)->where($map)->order('id')->limit($transNum)->select();

            // dump($lists);
            
            if($lists){
                $level_2_num = $memberModel->where('vip_level = 2')->count();
                $level_3_num = $memberModel->where('vip_level = 3')->count();

                $level_2_bal = new_bcdiv(new_bcmul($fee_total, Constants::AWARD_SMALL_TEAM), $level_2_num);
                $level_3_bal = new_bcdiv(new_bcmul($fee_total, Constants::AWARD_BIG_TEAM), $level_3_num);

                foreach($lists as $item){
                    $memberModel->startTrans();

                    $member = $memberModel->account($item['id'], 'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign,vip_level');
                    $givenum = 0;
                    switch ($member['vip_level']) {
                        case '2':
                            $givenum = $level_2_bal;
                            break;
                        case '3':
                            $givenum = $level_3_bal;
                            break;
                    }


                    $newBalan = new_bcadd(new_bcadd($member['balance'], $givenum), $member['balance_lock']);
                    $result = $memberModel->changeBalance($member, $givenum);
                    if($result == false){
                        $err = '签名失败';
                    }else{
                        $err = '';
                    }

                    $log = array('uid' => $member['id'], 'changeform' => 'in', 'subtype' => 16, 'money' => $givenum, 'ctime' => NOW_TIME, 'balance' => $newBalan, 'describes' => '社区分红' , 'money_type' => 1);
                    $result = $result && $logModel->add($log);

                    if($err == '' && $result == false){
                        $err = '插入日志失败';
                    }



                    if($result){
                        $memberModel->commit();
                        $this->p('分红奖励成功:' . $member['id']);
                    }else{
                        $memberModel->rollback();
                        $this->p('分红奖励失败' . $member['id'] . '[' . $err . ']');
                    } 

                    //更新最后分红时间
                    $memberModel->where(array('id' => $member['id']))->save(array('last_cal_time' => NOW_TIME));

                }

            }else{
                $this->p('分红奖励失败:用户为空');
            }

        }else{
            $this->p('分红奖励失败:奖励为空');
        }

        #################结束################
        
        //解锁
        setLock($plan_name, 'del');
    }

    /**
     * 清空一天前认证失败的资料 每天一次 6:00
     * @return [type] [description]
     */
    public function clearAuth(){
        $time = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $map = array('status' => '-1', 'ctime' => array('lt', $time));
        $result = M('auths')->where($map)->delete();
        if($result){
            echo 'clearAuth:Success';
        }else{
            echo 'clearAuth:Fail';
        }
    }

    /**
     * 处理买单
     * @return [type] [description]
     */
    public function insert_buy(){
        G('begin');
        $redis = ConnRedis();
        $qname = 'buylist';
        $data = $redis->hgetall($qname);
        $len = count($data);
        if($len > 0){
            $members = D('members');
            $orderModel = D('TradingOrder');
            $priceModel = D('TradingPrice');
            $TradingPrice = $priceModel->getPrice();
            $price = $TradingPrice['price'];
            foreach($data as $uid => $num){
                $members->startTrans();
                //用户余额检查
                $fee   = new_bcmul(new_bcmul($price, $num), Constants::SCORE_BUY_FEE);
                $total = new_bcmul(new_bcmul($price, $num), (1 + Constants::SCORE_BUY_FEE));
                $user  = $members->account($uid);
                if($user['usdc'] < $total){
                    $members->rollback();
                    continue;
                }

                //用户余额锁定
                $result = $members->changeUsdcAndlock($user, $total, 'out');
                //插入订单
                $data = array(
                    'uid' => $uid,
                    'num' => $num,
                    'price' => $price,
                    'order_type' => 1,
                    'fee' => $fee,
                );
                $result = $result && $orderModel->add($data);

                if($result){
                    $this->p("success[uid: {$uid}, num: {$num}, price: {$price}, total: {$total}]");
                    $members->commit();
                }else{
                    $this->p("fail[uid: {$uid}, num: {$num}, price: {$price}, total: {$total}]");
                    $members->rollback();
                }
            }

            //清空订单队列
            $redis->del($qname);
        }
        G('end');
        echo G('begin','end').'s<br/>';
    }



    /**
     * 处理卖单
     * @return [type] [description]
     */
    public function insert_sell(){
        G('begin');

        $redis = ConnRedis();
        $qname = 'selllist';
        $data = $redis->hgetall($qname);
        $len = count($data);
        if($len > 0){
            $members = D('members');
            $priceModel=D('TradingPrice');
            $orderModel = D('TradingOrder');
            $TradingPrice = $priceModel->getPrice();
            $price = $TradingPrice['price'];
            foreach($data as $uid => $num){
                M()->startTrans();
                //用户GOC余额检查
                $user  = $members->account($uid);
                if($user['goc'] < $num){
                    M()->rollback();
                    continue;
                }

                //用户GOC锁定
                $result= $members->changeGocAndlock($user, $num);
                //插入订单
                $data = array(
                    'uid' => $uid,
                    'num' => $num,
                    'price' => $price,
                    'order_type' => 2,
                );
                
                //发布订单
                $result = $result && $orderModel->add($data);

                if($result){
                    $this->p("success[uid: {$uid}, num: {$num}, price: {$price}]");
                    M()->commit();
                }else{
                    $this->p("fail[uid: {$uid}, num: {$num}, price: {$price}]");
                    M()->rollback();
                }
            }

            //清空订单队列
            $redis->del($qname);
        }
        G('end');
        echo G('begin','end').'s<br/>';
    }

    /**
     * 批量撤销过期订单 每天5点-6点 每分钟1次 每次500单
     * @return [type] [description]
     */
    public function cancel_order(){
        set_time_limit(0);
        $planName = 'PLAN_CANCEL_ORDERS';
        $transNum = 500;
        $orderModel = D('tradingOrder');
        //检查锁
        if(checkLock($planName))  die('runing');
        //加锁
        setLock($planName, 'add');
        $begin = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $map = 'ptime > ' . $begin . ' AND status = 0';
        $list = $orderModel->field('id')->where($map)->limit($transNum)->select();

        $this->p('start');
        foreach($list as $item){
            $res = $orderModel->cancel($item['id']);
            $this->p(($res === true ? 'TRUE' : 'FALSE') . '订单ID:' . $item['id']);
        }
        $this->p('end');

        setLock($planName, 'del');
    }


    /**
     * 处理预约单 每天0点  每分钟一次 每次500单
     * @return [type] [description]
     */

    public  function  insert_moveup(){

        set_time_limit(0);
        $planName = 'PLAN_MOVEUP_ORDERS';
        $transNum = 500;
        $orderModel =D('TradingMoveup');
        $tradingOrderModel = D('TradingOrder');
        $priceModel = D('TradingPrice');
        $TradingPrice = $priceModel->cache(true, 300)->getPrice();
        $price = $TradingPrice['price'];
        if($price<=0){
            err('价格错误');
        }

        //检查锁
        if(checkLock($planName))  die('runing');

        //加锁
        setLock($planName, 'add');
        $date = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $map="last_cal_time < {$date} AND status=0";
        $list = $orderModel->field('*')->where($map)->limit($transNum)->select();
        $this->p('start');
        if($list){
            foreach($list as $item){

                //预约买入
                if($item['order_type']==1){

                    if($item['price']>=$price){
                        M()->startTrans();
                        $result=true;
                        $data = array(
                            'uid' => $item['uid'],
                            'num' => $item['num'],
                            'price' => $item['price'],
                            'order_type' => 1,
                            'fee' => $item['fee'],
                        );
                        $result = $result && $tradingOrderModel->add($data);
                        $result = $result && $orderModel->where(array('id'=>$item['id'],'status'=>0))->save(array('status'=>1));

                        if($result){
                            M()->commit();
                        }else{
                            M()->rollback();
                        }

                    }
                }elseif($item['order_type']==2){
                    //预约卖出

                    if($item['price']<=$price){
                        M()->startTrans();
                        $result=true;
                        //插入订单
                        $data = array(
                            'uid' => $item['uid'],
                            'num' => $item['num'],
                            'price' => $item['price'],
                            'order_type' => 2,
                        );

                        //发布订单
                        $result = $result && $tradingOrderModel->add($data);
                        $result = $result && $orderModel->where(array('id'=>$item['id'],'status'=>0))->save(array('status'=>1));

                        if($result){
                            M()->commit();
                        }else{
                            M()->rollback();
                        }

                    }

                }

                //更新最后检测时间
                $orderModel->where(array('id' => $item['id']))->save(array('last_cal_time' => NOW_TIME));

            }
        }else{
            echo 'completed<br />';
        }

        $this->p('end');
        setLock($planName, 'del');
    }




    private function p($str){
        if($str == 'start'){
            echo "###################################### START #######################################\r\n";
        }elseif($str == 'end'){
            echo "###################################### END #######################################\r\n";
        }else{
            echo $str."\r\n";
        }
    }

}