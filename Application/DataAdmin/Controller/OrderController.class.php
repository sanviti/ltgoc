<?php
/**
 * 订单管理
 */
namespace DataAdmin\Controller;
use Think\Controller;
Use Think\Cache\Driver\Redis;

class OrderController extends BaseController {
    
    public function index(){
        $status  = I('status');
        $ordersn = I('ordersn');
        $shop_id = I('shop_id');
        $userid = I('userid');
        $rname   = I('rname');
        $page = I('get.p');

        $parm = "";
        if($status!=""){
            $where['od.status'] = $status;
            $parm['status'] = $status;
        }
        if($ordersn!=""){
            $where['od.ordersn'] = $ordersn;
            $parm['ordersn'] = $ordersn;
        }
        if($shop_id!=""){
            $shop = M("data_shop",null)->field("id")->where(array("shopid"=>$shop_id))->find();
            $where['od.shop_id'] = $shop['id'];
            $parm['shop_id'] = $shop_id; 
        }
        if($userid!=""){
            $user = M("app_user",null)->field("id")->where(array("userid"=>$userid))->find();
            $where['od.uid'] = $user['id'];
            $parm['uid'] = $userid;
        }
        if($rname!=""){
            $where['od.rname']   = $rname;
            $parm['rname'] = $rname;
        }
        
        if (empty($page))
            $page = 1;
        $count = M('data_order',null)->alias("od")->where($where)->count();
        $p = getpage($count, C("PAGE_SIZE"),$parm);
        $show = $p->newshow();

        $data = M('data_order',null)->alias("od")
            ->field("
    od.orderid,od.ordersn,od.shop_id,od.uid,od.amount,od.express_fee,od.rname,od.mobile,od.ctime,od.status,g.goods_name,g.goods_img
                ")
            ->join("LEFT JOIN data_order_goods g ON g.orderid = od.orderid")
            ->page(I('get.p'))
            ->where($where)
            ->order("ctime DESC")
            ->limit(10)
            ->select();
        
        foreach ($data as $k => $v){
            $data[$k]['status'] = $this->status($v['status']);
        }

        
        $this->assign('page', $show);
        $this->assign('data',$data);
        $this->display();
    }
    
    //返回状态值
    private function status($data){

        if ($data == 0){
            $status = '待付款';
        }elseif ($data == 1){
            $status = '待发货';
        }elseif ($data == 2){
            $status = '待收货';
        }elseif ($data == 4){
            $status = '已完成';
        }elseif ($data == 3){
            $status = '待评价';
        }elseif ($data == -1){
            $status = '已取消';
        }elseif ($data == -2){
            $status = '交易关闭';
        }elseif ($data == -3){
            $status = '已删除';
        }elseif ($data == -10){
            $status = '退款中';
        }elseif ($data == -11){
            $status = '退款失败';
        }elseif ($data == -12){
            $status = '退款成功';
        }elseif ($data == -20){
            $status = '退货退款中';
        }elseif ($data == -21){
            $status = '退货退款失败';
        }elseif ($data == -22){
            $status = '退货退款成功';
        }
        
        return $status;
    }

    //详情
    public function orderprocess(){
        $orderid = I('orderid');
        
        $where['orderid'] = $orderid;
        $data  = M('data_order',null)->alias('od')->where($where)->find();

        $goods = M('data_order_goods',null)->alias('g')
        ->field('od.orderid,od.ordersn,od.shop_id,od.uid,od.amount,od.express_fee,od.rname,od.mobile,od.ctime,od.status,g.goods_name,g.goods_img,g.num,g.flag,g.price')
            ->join('LEFT JOIN data_order AS od ON od.orderid = g.orderid')
            ->where("od.orderid = '%s'",$data['orderid'])
            ->select();

        $this->assign('data',$data);
        $this->assign('goods',$goods);
        $this->display("process");
    }
    
    //取消
    public function cancel(){
        $orderid = I('orderid');
        if (!$orderid){
            err('请选择要取消的订单');
        }
        $order = M('data_order',null)->where(array('orderid' => $orderid,'status' => 0))->find();
        if (!$order){
            err('该订单不能取消');
        }
        $data['status'] = -1;
        $save = M('data_order',null)->where(array('orderid' => $orderid))->save($data);
        if ($save){
            succ('取消成功');
        }
    }
    
    
    
}