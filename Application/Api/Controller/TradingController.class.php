<?php
namespace Api\Controller;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class TradingController extends BaseController{

    /**
     * 买入排队
     * @return [type] [description]
     */
    public function buy(){
       require_check_api('num,paypwd,verify,signcode', $this->post);
        $date = mktime(0,0,0,date('m'),date('d'),date('Y'));
        if((time()<$date+Constants::ORDER_TRANDING_STIME || time()>$date+Constants::ORDER_TRANDING_ETIME) || (date('w')==6 || date('w') == 0)){
            err('非交易时间');
        }
        //数据检查
        $num   = floatval($this->post['num']);
        if($num < 1 || $num > 2000)err('单笔购买数量区间为1-2000枚');
        if (!preg_match('/^[0-9]+(.[0-9]{1,3})?$/', $num)) {
            err('买入数量最多为3位小数');
        }

        //价格
        $priceModel = D('TradingPrice');
        $TradingPrice = $priceModel->cache(true, 300)->getPrice();
        $price = $TradingPrice['price'];
        if($price <= 0)err('买入失败');
        $total = $price * $num * (1 + Constants::SCORE_BUY_FEE);

        //用户余额检查
        $members = D('members');
        $user = $members->normalMember($this->uid, 'usdc,usdc_lock');

         $verifyCode =$this->post['verify'];
         //图形验证码
        $signcode=$this->post['signcode'];
        $redis = ConnRedis();
        $key = 'img:'.md5($signcode);
        $val = $redis -> get($key);
        if(strval($val) !== strval($verifyCode)){
            err('图形验证码错误或已失效,请刷新重试');
        }

        if(empty($user)){
            err('账户已冻结,发布失败!');
        }
        if(!$members->checkpaypwd($this->uid,$this->post['paypwd'])){
            err('交易密码错误');
        }
        if($user['usdc'] < $total) err('账户余额不足');

        //排队
        $uid = $this->uid;
        $qname = 'buylist';
        $redis = ConnRedis();

        $queueLen = $redis->hlen($qname);
        if($queueLen > 500){
            err('业务繁忙，稍后重试');
        }

        $ists = $redis->hget($qname, $uid);
        if($ists){
            err('排队中，稍后重试');
        }

        $result = $redis->hset($qname, $uid, $num);
        if($result){
            succ('发布成功');
        }else{
            err('发布失败');
        }
    }

    /**
     * 发布卖出队列
     * @return [type] [description]
     */
    public function sell(){
        require_check_api('num,paypwd,verify,signcode', $this->post);

         $date = mktime(0,0,0,date('m'),date('d'),date('Y'));
        if((time()<$date+Constants::ORDER_TRANDING_STIME || time()>$date+Constants::ORDER_TRANDING_ETIME) || (date('w')==6 || date('w') == 0)){
            err('非交易时间');
        }

         $verifyCode =$this->post['verify'];
        //图形验证码
        $signcode=$this->post['signcode'];
        $redis = ConnRedis();
        $key = 'img:'.md5($signcode);
        $val = $redis -> get($key);
        if(strval($val) !== strval($verifyCode)){
            err('图形验证码错误或已失效,请刷新重试');
        }
        //数据检查
        $num   = floatval($this->post['num']);
        if($num < 1 || $num > 2000)err('单笔卖出数量区间为1-2000枚');
        if (!preg_match('/^[0-9]+(.[0-9]{1,3})?$/', $num)) {
            err('卖出数量最多为3位小数');
        }

        $priceModel=D('TradingPrice');
        $members = D('members');
        $user = $members->normalMember($this->uid,'goc,goc_lock');
        
        if(empty($user)){
            err('账户已冻结,发布失败!');
        }
        //验证当日价格
        $TradingPrice = $priceModel->cache(true, 60)->getPrice();
        $price = $TradingPrice['price'];
        if($price <= 0)err('卖出失败');

        //验证用户密码
        if(!$members->checkpaypwd($this->uid,$this->post['paypwd'])){
            err('交易密码错误');
        }
        //用户余额检查
        if($user['goc'] < $num) err('货币不足');

        //检查是否超出限额
//        $condi = [
//            'uid' => $this->uid,
//            'ctime' => array('gt', $date),
//            'order_type'=>2,
//            'status'=>array('NEQ',-1)
//        ];
//        $sellNum= D('TradingOrder')->where($condi)->sum('num');
//        $userGocTotal=bcadd($sellNum,$user['goc'],2);
//        if($sellNum+$num>$userGocTotal*0.5){
//            $canSell=$userGocTotal*0.5;
//            err('今日超出限额，今日可卖量：'.$canSell);
//        }


        //排队
        $uid = $this->uid;
        $qname = 'selllist';
        $redis = ConnRedis();

        $queueLen = $redis->hlen($qname);
        if($queueLen > 500){
            err('业务繁忙，稍后重试');
        }

        $ists = $redis->hget($qname, $uid);
        if($ists){
            err('排队中，稍后重试');
        }

        $result = $redis->hset($qname, $uid, $num);
        if($result){
            succ('发布成功');
        }else{
            err('发布失败');
        }
    }


    /**
     * 我的订单
     * @param  int $page    分页
     * @return [type]          [description]
     */
    public function order(){
        $page = intval($this->post['p']);
        $Model = D('TradingOrder');
        $condi = ['uid' => $this->uid,'status'=>0];
        $list = $Model -> where($condi) -> field('id,ptime,price,num,order_type,succ_num')
            -> page($page) -> limit(10) -> order('ctime DESC') -> select();
        foreach($list as  $key=>$value){
            $list[$key]['ptime'] = date("Y-m-d H:i",$value['ptime']);
        }
        $data['list'] = $list;
        succ($this->output($data));
    }

    /**
     * 手动撤单
     * @param  str $sn 订单号
     * @return [type]     [description]
     */
    public function recall(){
        require_check_api('id', $this->post);
        $id = intval($this->post['id']);
        $model = D('TradingOrder');
        $result = $model->cancel($id);
        if($result){
           succ('撤销成功');
        }else{
           err('撤销失败');
        }
    }



    ###########预约#############
    public function  movedUpBuy(){
        require_check_api('price,num,paypwd,verify,signcode', $this->post);
        //数据检查
        $num   = floatval($this->post['num']);
        if($num < 1 || $num > 2000)err('单笔卖出数量区间为1-2000枚');
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $num)) {
            err('买入数量最少为2位小数');
        }

        $verifyCode =$this->post['verify'];
        //图形验证码
        $signcode=$this->post['signcode'];
        $redis = ConnRedis();
        $key = 'img:'.md5($signcode);
        $val = $redis -> get($key);
        if(strval($val) !== strval($verifyCode)){
            err('图形验证码错误或已失效,请刷新重试');
        }


        //价格检查
        $price = floatval($this->post['price']);
        if($price <= 0)err('预约失败，价格不符合');
        $total = $price * $num * (1 + Constants::SCORE_BUY_FEE);
        $fee = $price * $num * Constants::SCORE_BUY_FEE;


        //用户余额检查
        $members = D('members');
        $user = $members->normalMember($this->uid, 'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign');

        if(empty($user)){
            err('账户已冻结,发布失败!');
        }
        if(!$members->checkpaypwd($this->uid,$this->post['paypwd'])){
            err('交易密码错误');
        }
        if($user['usdc']<$total){
            err('USDC不足');
        }
        M()->startTrans();
        //用户余额锁定
        $result = $members->changeUsdcAndlock($user, $total, 'out');
        //插入订单
        $data = array(
            'uid' => $this->uid,
            'num' => $num,
            'price' => $price,
            'order_type' => 1,
            'ctime'=>time(),
            'fee'=>$fee
        );
        $result = $result && D('TradingMoveup')->add($data);

        if($result){
            M()->commit();
            succ('预约成功');
        }else{
            M()->rollback();
            err('预约失败');
        }
    }


    public function  movedUpSell(){
        require_check_api('price,num,paypwd,verify,signcode', $this->post);
        //数据检查
        $num   = floatval($this->post['num']);
        if($num < 1 || $num > 2000)err('单笔卖出数量区间为1-2000枚');
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $num)) {
            err('买入数量最少为2位小数');
        }

        $verifyCode =$this->post['verify'];
        //图形验证码
        $signcode=$this->post['signcode'];
        $redis = ConnRedis();
        $key = 'img:'.md5($signcode);
        $val = $redis -> get($key);
        if(strval($val) !== strval($verifyCode)){
            err('图形验证码错误或已失效,请刷新重试');
        }


        //价格检查
        $price = floatval($this->post['price']);
        if($price <= 0)err('预约失败，价格不符合');

        //用户余额检查
        $members = D('members');
        $user = $members->normalMember($this->uid, 'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign');

        if(empty($user)){
            err('账户已冻结,发布失败!');
        }
        if(!$members->checkpaypwd($this->uid,$this->post['paypwd'])){
            err('交易密码错误');
        }
        if($user['goc']<$num){
            err('GOC数量不足');
        }
        M()->startTrans();
        //用户余额锁定
        $result = $members->changeGocAndlock($user, $num, 'out');
        //插入订单
        $data = array(
            'uid' => $this->uid,
            'num' => $num,
            'price' => $price,
            'order_type' => 2,
            'ctime'=>time()
        );
        $result = $result && D('TradingMoveup')->add($data);

        if($result){
            M()->commit();
            succ('预约成功');
        }else{
            M()->rollback();
            err('预约失败');
        }
    }



    public  function   movedUpOrder(){
        $page = intval($this->post['p']);
        if(empty($page)){
            $page=1;
        }
        $Model = D('TradingMoveup');
        $condi = ['uid' => $this->uid];
        $list = $Model ->lists($page,$condi,'id,price,num,ctime,status,order_type');
        foreach($list as  $key=>$value){
            $list[$key]['ctime'] = date("Y-m-d H:i",$value['ctime']);
        }
        $data['list'] = $list;
        succ($this->output($data));
    }


    public function  moveUpOrderRecall(){
        require_check_api('id', $this->post);
        $id = intval($this->post['id']);
        $model = D('TradingMoveup');
        $result = $model->cancel($id);
        if($result){
            succ('撤销成功');
        }else{
            err('撤销失败');
        }
    }





    public function p($str){
        echo '#############' . $str . '##############<br/>';
    }
}