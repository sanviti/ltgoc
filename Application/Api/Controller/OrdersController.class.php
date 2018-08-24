<?php
/**
 * 订单
 */
namespace Api\Controller;
use Think\Log;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class OrdersController extends BaseController{
    //我的订单
    public function orders(){
        //状态
        $status = I('status/s');
        $condi['status'] = $status == 'end' ? array('in','5,-1,-2,101,102') : array('in', '1,2,3,4');
        //买入卖出
        $stype = I('stype/d');
        if ($stype == 1) {
            $condi['buy_uid']  = $this->uid;
            $condi['type'] = $stype;
        } elseif ($stype == 2) {
            $condi['sell_uid']  = $this->uid;
            $condi['type'] = $stype;
        } else {
            $condi['_query'] = 'buy_uid=' . $this->uid . '&sell_uid=' . $this->uid . '&_logic=or';
        }
        
        //时间范围
        $end_time = time();
        $end = I('end/s');
        if($end){
            $end_time = strtotime($end) + 3600 * 24;
        }
        $condi['create_time'] = array('ELT', $end_time);
        $begin = I('begin/s');
        if($begin){
            $begin = strtotime($begin);
            $condi['create_time'] =  array(array('egt', $begin), array('ELT', $end_time));
        }
        //页码
        $page = I('page/d');
        $orderModel = M('orders');
        $list = $orderModel
                ->field('
                    id,status,order_sn,
                    IF(buy_uid = '. $this->uid .', 1, 2) as type,
                    cast(opc AS decimal(10,2)) AS opc,price,
                    FROM_UNIXTIME(create_time, "%Y/%m/%d %T") AS create_time
                    
                ')
                ->where($condi)->page($page)->limit(20)->order('id DESC')->select();
        if (!$list) {
            err();
        }
        $data['list'] = $list;
        $data['page'] = $page + 1;
        succ($data);
    }

    private function _todayPrice(){
        $start = mktime(0,0,0, 5, 5, 2018);
        $today = time();
        $currID = floor(($today-$start)/3600/24) + 1;
        $row = M('price')->where(array('id' => $currID))->find();
        $row = $row == null ? array('price' => 0.00) : $row;
        return $row['price'];
    }

    //交易检查
    private function _post_check(){
        //交易时段
        // $start = mktime(10, 30, 0, date('m'), date('d'), date('Y'));
        // $end = mktime(19, 0, 0, date('m'), date('d'), date('Y'));
        // if(NOW_TIME < $start || NOW_TIME > $end){
        //     err('非交易时段');
        // }
        //c2认证
        $member = D('members')->field('auth_c2')->where(array('id' => $this->uid))->find();
        if(empty($member['auth_c2'])){
            err('请完成c2认证');
        }
    }
    //发布买入
    public function submitBuy(){
        $this->_post_check();
        require_check("buy_price,buy_num,buy_password");
        $price  = input_numb(I('buy_price'), 2);
        $num    = input_numb(I('buy_num'), 8);
        $paypwd = input_str(I('buy_password'));

        if($price <= 0 || $price != $this->_todayPrice())
            err('价格错误');

        if($num <= 0)
            err('数量错误');

        $memberModel = D('members');
        if(!$memberModel->checkpaypwd($this->uid, $paypwd))
            err('交易密码错误');

        $orderModel = M('orders');
        $ordersn = build_order_no();
        $total_usd = new_bcmul($num, $price);
        $total_cny = new_bcmul($total_usd, 6.5);
        $order = array(
            'type' => 1,
            'opc' => $num,
            'price' => $price,
            'total_usd' => $total_usd,
            'total_cny' => $total_cny,
            'buy_uid' => $this->uid,
            'status' => 1,
            'create_time' => NOW_TIME,
            'order_sn' => $ordersn,
        );

        if($orderModel->add($order)){
            succ('发布成功');
        }else{
            err('发布失败');
        }
    }

    //发布卖出
    public function submitSell(){
        $this->_post_check();        
        require_check("sell_price,sell_num,sell_password");
        $price  = input_numb(I('sell_price'), 2);
        $num    = input_numb(I('sell_num'), 8);
        $paypwd = input_str(I('sell_password'));

        if($price <= 0 || $price != $this->_todayPrice())
            err('价格错误');

        if($num <= 0)
            err('数量错误');

        $memberModel = D('members');
        if(!$memberModel->checkpaypwd($this->uid, $paypwd))
            err('交易密码错误');

        $memberModel = D('members');
        $orderModel  = M('orders');

        $memberModel->startTrans();
        $member = $memberModel->lock(true)->where(array('id' => $this->uid))->find();
        if($member && $member['is_lock'] == 0){
            //手续费
            $fee = new_bcmul($num, Constants::DEAL_FEE);
            $lockNum = new_bcadd($num, $fee);
            if($member['score'] >= $lockNum){
                //用户锁定opc
                $result = $memberModel->score2lock($member, $lockNum);
                //生成订单
                $ordersn = build_order_no();
                $total_usd = new_bcmul($num, $price);
                $total_cny = new_bcmul($total_usd, 6.5);
                $order = array(
                    'type' => 2,
                    'opc' => $num,
                    'price' => $price,
                    'total_usd' => $total_usd,
                    'total_cny' => $total_cny,
                    'sell_uid' => $this->uid,
                    'status' => 2,
                    'create_time' => NOW_TIME,
                    'order_sn' => $ordersn,
                );
                $result = $result && $orderModel->add($order);
                if($result){
                    $memberModel->commit();
                    succ('发布成功');
                }else{
                    $memberModel->rollback();
                    err('发布失败');
                }
            }else{
                $memberModel->rollback();
                err('pec数量不足');
            }
        }else{
            $memberModel->rollback();
            err('用户不存在');
        }
    }

    //买入
    public function buy(){
        $this->_post_check();
        require_check("ordersn,paypwd");
        $ordersn = input_str(I('ordersn'));
        $paypwd  = input_str(I('paypwd'));

        $orderModel = M('orders');
        //验证交易密码
        $memberModel = D('members');
        if(!$memberModel->checkpaypwd($this->uid, $paypwd))
            err('交易密码错误');
        //更改订单状态
        $orderModel->startTrans();
        $map = array('order_sn' => $ordersn, 'status' => 2);
        $order = $orderModel->lock(true)->where($map)->find();
        if($order && $order['sell_uid'] != $this->uid){
            $upd = array('match_time' => NOW_TIME, 'buy_uid' => $this->uid, 'status' => 3);
            $result = $orderModel->where($map)->save($upd);
            if($result){
                $orderModel->commit();

                //短信通知卖家
                //{sms}
                
                succ('下单成功');
            }else{
                $orderModel->rollback();
                err('下单失败');
            }

        }else{
            $orderModel->rollback();
            err('订单不存在');
        }
    }

    //卖出
    public function sell(){
        $this->_post_check();
        require_check("ordersn,paypwd");
        $ordersn = input_str(I('ordersn'));
        $paypwd  = input_str(I('paypwd'));        
        $orderModel = M('orders');
        //验证交易密码
        $memberModel = D('members');
        if(!$memberModel->checkpaypwd($this->uid, $paypwd))
            err('交易密码错误');

        $orderModel->startTrans();
        $map = array('order_sn' => $ordersn, 'status' => 1);
        $order = $orderModel->lock(true)->where($map)->find();
        if($order && $order['buy_uid'] != $this->uid){
            $sell_mem = $memberModel->lock(true)->where(array('id' => $this->uid))->find();

            //手续费
            $fee = new_bcmul($order['opc'], Constants::DEAL_FEE);
            $lockNum = new_bcadd($order['opc'], $fee);
            //校验数量
            if($sell_mem && $sell_mem['score'] >= $lockNum && $sell_mem['is_lock'] == 0){
                //更改订单状态
                $upd = array('match_time' => NOW_TIME, 'status' => 3, 'sell_uid' => $this->uid);
                $result = $orderModel->where($map)->save($upd);

                //用户锁定opc
                $result = $result && $memberModel->score2lock($sell_mem, $lockNum);

                if($result){
                    $orderModel->commit();

                    //短信通知买家
                    //{sms}
                    
                    succ('操作成功');
                }else{
                    $orderModel->commit();
                    err('操作失败');
                }
            }else{
                $orderModel->rollback();
                err('ope数量不足');
            }
        }else{
            $orderModel->rollback();
            err('订单不存在');
        }
    }

    //标记付款
    public function confirm_pay(){
        require_check("ordersn,photo");
        $ordersn = input_str(I('ordersn'));   

        $orderModel = M('orders');
        $memberModel = D('members');

        //更改订单状态
        $orderModel->startTrans();
        $map = array('order_sn' => $ordersn, 'status' => 3, 'buy_uid' => $this->uid);
        $order = $orderModel->lock(true)->where($map)->find();
        if($order){
            $upd = array(
                'pay_time' => NOW_TIME,
                'status' => 4,
                'message' => input_str(I('message')),
                'cert_img' => input_str(I('photo'))
            );
            $result = $orderModel->where($map)->save($upd);
            if($result){
                $orderModel->commit();

                //短信通知卖家
                //{sms}
                
                succ('操作成功');
            }else{
                $orderModel->rollback();
                err('操作失败');
            }

        }else{
            $orderModel->rollback();
            err('订单已经标记付款或者订单不存在');
        }
    }

    //标记收款
    public function confirm_money(){
        require_check("ordersn,paypwd");
        $ordersn = input_str(I('ordersn'));
        $paypwd  = input_str(I('paypwd'));        
        $orderModel = M('orders');
        //验证交易密码
        $memberModel = D('members');
        if(!$memberModel->checkpaypwd($this->uid, $paypwd))
            err('交易密码错误');

        $logModel = M('userScoreLog');
        //更改订单状态
        $orderModel->startTrans();
        $map = array('order_sn' => $ordersn, 'status' => 4, 'sell_uid' => $this->uid);
        $order = $orderModel->lock(true)->where($map)->find();
        if($order){
            $sell_mem = $memberModel->lock(true)->where(array('id' => $this->uid))->find();
            $buy_mem  = $memberModel->lock(true)->where(array('id' => $order['buy_uid']))->find();
            if($sell_mem && $buy_mem){
                $upd = array('confirm_time' => NOW_TIME, 'status' => 5);
                $result = $orderModel->where($map)->save($upd);

                //扣币
                $fee = new_bcmul($order['opc'], Constants::DEAL_FEE);
                $decnum = new_bcadd($order['opc'], $fee);
                $result = $result && $memberModel->score_lock_dec($sell_mem, $decnum);

            
                //加币
                $incnum = $order['opc'];                
                $result = $result && $memberModel->score_inc($buy_mem, $incnum);


                $newBalan1 = new_bcsub(new_bcadd($sell_mem['score'], $sell_mem['score_lock']), $decnum);
                $newBalan2 = new_bcadd(new_bcadd($buy_mem['score'], $buy_mem['score_lock']), $incnum);

                //日志
                $log = array(
                    array('uid' => $this->uid, 'changeform' => 'out', 'subtype' => 2, 'num' => $order['opc'], 'ctime' => NOW_TIME, 'balance' => $newBalan1, 'extends' => $order['order_sn']),
                    array('uid' => $order['buy_uid'], 'changeform' => 'in', 'subtype' => 1, 'num' => $incnum, 'ctime' => NOW_TIME, 'balance' => $newBalan2, 'extends' => $order['order_sn'])
                    );
                $result = $result && $logModel->addAll($log);

                if($result){
                    $orderModel->commit();                    
                    succ('操作成功');
                }else{
                    $orderModel->rollback();
                    err('操作失败11');
                }
            }else{
                $orderModel->rollback();
                err('用户不存在');
            }
        }else{
            $orderModel->rollback();
            err('订单不存在');
        }
    }

    //卖家撤单
    public function cancel_sell(){
        require_check("ordersn");
        $ordersn = input_str(I('ordersn'));
        $orderModel = M('orders');
        $memberModel = D('members');
        
        //更改订单状态
        $orderModel->startTrans();
        $map = array('order_sn' => $ordersn, 'sell_uid' => $this->uid, 'status' => array('in', '2,3,4'));
        $order = $orderModel->lock(true)->where($map)->find();
        if($order){
            //未匹配成功前
            $condi1 = ($order['status'] == 2);  
            $hour = floor((NOW_TIME - $order['match_time'])/(60*60));
            //匹配成功 6 小时后 没有交易成功 没有被申诉的订单 可以撤单
            $condi2 = (($order['status'] == 3 || $order['status'] == 4) && ($hour >= 6) && $order['appeal'] == 0);
            if($condi1 || $condi2){
                $sell_mem = $memberModel->lock(true)->where(array('id' => $this->uid))->find();
                if($sell_mem){
                    //更改订单状态
                    $upd = array('cancel_time' => NOW_TIME, 'status' => -1);
                    $result = $orderModel->where($map)->save($upd);
                    //手续费
                    $fee = new_bcmul($order['opc'], Constants::DEAL_FEE);
                    $unlockNum = new_bcadd($order['opc'], $fee);
                    //解冻opc
                    $result = $result && $memberModel->lock2score($sell_mem, $unlockNum);

                    if($result){
                        $orderModel->commit();                        
                        succ('操作成功');
                    }else{
                        $orderModel->rollback();
                        err('操作失败');
                    }

                }else{
                    $orderModel->rollback();
                    err('用户存在');
                }
            }else{
                $orderModel->rollback();
                err('6小时后可撤单');
            }
        }else{
            $orderModel->rollback();
            err('订单不存在');
        }
    }

    //买家撤单
    public function calcel_buy(){
        require_check("ordersn");
        $ordersn = input_str(I('ordersn'));
        $orderModel = M('orders');
        $memberModel = D('members');

        $orderModel->startTrans();
        $map = array('order_sn' => $ordersn, 'buy_uid' => $this->uid, 'status' => array('in', '1,3,4'));
        $order = $orderModel->lock(true)->where($map)->find();
        if($order){
            //更改订单状态
            $upd = array('cancel_time' => NOW_TIME, 'status' => -2);
            $result = $orderModel->where($map)->save($upd);

            //用户解冻opc
            if($order['status'] == 3 || $order['status'] == 4){
                $sell_mem = $memberModel->lock(true)->where(array('id' => $order['sell_uid']))->find();
                if($sell_mem){
                    //解冻opc
                    $fee = new_bcmul($order['opc'], Constants::DEAL_FEE);//手续费
                    $unlockNum = new_bcadd($order['opc'], $fee);
                    $result = $result && $memberModel->lock2score($sell_mem, $unlockNum);
                }else{
                    $orderModel->rollback();
                    err('用户存在');
                }
            }

            if($result){
                $orderModel->commit();                        
                succ('操作成功');
            }else{
                $orderModel->rollback();
                err('操作失败');
            }

        }else{
            $orderModel->rollback();
            err('订单不存在');
        }
    }

    //查看买入单详情
    public function buy_order(){
        $map_order = array(
            'id' => (int)I('post.id'),
            'buy_uid' => $this->uid,
        );
        $orderModel = M('orders')->alias('orders');
        $order = $orderModel
        ->field('id,status,opc,price,total_usd,total_cny,sell_uid,match_time,sell_zfb,sell_wx,sell_bank,sell_other,order_sn')
        // ->join('__MEMBERS__ member ON member.id = sell_uid')
        ->where($map_order)
        ->find();

        if(!$order) err('订单不存在');

        $order['sell_zfb'] = empty($order['sell_zfb']) ? '请联系卖家开启' : $order['sell_zfb'];
        $order['sell_wx'] = empty($order['sell_wx']) ? '请联系卖家开启' : $order['sell_wx'];
        if (empty($order['sell_bank'])) {
            $order['bank_name'] = '请联系卖家开启';
            $order['card_number'] = '请联系卖家开启';
            $order['card_name'] = '请联系卖家开启';
        } else {
            $sell_bank = explode(',', $order['sell_bank']);
            $order['bank_name'] = $sell_bank[0];
            $order['card_number'] = $sell_bank[1];
            $order['card_name'] = $sell_bank[2];
        }
        $order['sell_other'] = empty($order['sell_other']) ? '请联系卖家开启' : $order['sell_other'];
        $seller = D('members')->profiles($order['sell_uid'],'nickname AS buy_rname,phone AS buy_phone');
        if (!$seller) {
            $seller = array(
                'buy_rname' => '',
                'buy_phone' => ''
            );
        }
        $data = array_merge($order, $seller);
        succ($data);
    }

    //查看卖出单详情
    public function sell_order(){
        $map_order = array(
            'id' => (int)I('post.id'),
            'sell_uid' => $this->uid,
        );
        $orderModel = M('orders');
        $order = $orderModel->alias('orders')
        ->field('id,type,status,opc,price,total_usd,total_cny,buy_uid,match_time,sell_zfb,sell_wx,sell_bank,sell_other,order_sn,message,cert_img')
        // ->join('__MEMBERS__ member ON member.id = buy_uid')
        ->where($map_order)
        ->find();
        if(!$order) err('订单不存在');

        $order['sell_zfb'] = empty($order['sell_zfb']) ? '点击开启' : $order['sell_zfb'];
        $order['sell_wx'] = empty($order['sell_wx']) ? '点击开启' : $order['sell_wx'];
        if (empty($order['sell_bank'])) {
            $order['bank_name'] = '点击开启';
            $order['card_number'] = '点击开启';
            $order['card_name'] = '点击开启';
        } else {
            $sell_bank = explode(',', $order['sell_bank']);
            $order['bank_name'] = $sell_bank[0];
            $order['card_number'] = $sell_bank[1];
            $order['card_name'] = $sell_bank[2];
        }
        $order['sell_other'] = empty($order['sell_other']) ? '点击开启' : $order['sell_other'];
        $buyer = D('members')->profiles($order['buy_uid'],'nickname AS buy_rname,phone AS buy_phone');
        if (!$buyer) {
            $buyer = array(
                'buy_rname' => '',
                'buy_phone' => ''
            );
        }
        $data = array_merge($order, $buyer);
        succ($data);
    }

    //开启支付方式
    public function open_payway(){
        $order_sn = I('order_sn/s');
        if (empty($order_sn)) {
            err('请求发生错误！');
        }
        $map_order = array(
            'order_sn' => $order_sn,
            'sell_uid' => $this->uid
        );
        $order = M('orders')->where($map_order)->find();
        if (!$order) {
            err('订单不存在！');
        }
        $member_dep = D('members')->member_dep($order['sell_uid'],'bank_name,card_number,card_name,alipay_account,weixin_account');
        $payway = I('payway/d');
        $upd_data = array();
        switch ($payway) {
            case 1:
                $upd_data['sell_zfb'] = $member_dep['alipay_account'];
                break;
            case 2:
                $upd_data['sell_wx'] = $member_dep['weixin_account'];
                break;
            case 3:
                $upd_data['sell_bank'] = $member_dep['bank_name'] . ',' . $member_dep['card_number'] . ',' . $member_dep['card_name'];
                break;
            default:
                $other = I('other/s');
                if (empty($other)) {
                    err('请填写其它支付方式！');
                }
                $upd_data['sell_other'] = $other;
                $member_dep['sell_other'] = $other;
                break;
        }
        M('orders')->where($map_order)->save($upd_data);
        succ('开启成功', $member_dep);
    }

    //发起申诉
    public function appeal(){
        require_check_post("title/s,content/s,order_sn/s");
        $title   = addslashes(I('title/s'));
        $content = addslashes(I('content/s'));
        $order_sn = addslashes(I('order_sn/s'));
        $screen_img = I('photo');
        $map_order = array(
            'order_sn' => $order_sn
        );
        $order = M('orders')->where($map_order)->find();
        if (!$order) {
            err('订单异常!');
        }
        if ($order['status'] != 5) {
           err('该订单不能申诉!');
        }
        $map_appeal = array(
            'uid' => $this->uid,
            'order_id' => $order['id']
        );
        $appeal = M('orders_appeal')->where($map_appeal)->find();
        if ($appeal) {
             err('该订单已经提交申诉，请勿重复提交!');
        }
        $appeal_data = array(
            'order_id' => $order['id'],
            'uid' => $this->uid,
            'title' => $title,
            'content' => $content,
            'imgs' => implode(',', $screen_img),
            'add_time' => time()
        );
        if (M('orders_appeal')->add($appeal_data)) {
            succ('提交成功，工作人员会及时处理！');
        }
        err('网络繁忙，请稍后再试！');
    }

}