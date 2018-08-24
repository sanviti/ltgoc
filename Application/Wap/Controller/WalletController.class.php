<?php
/**
 * 我的钱包
 */
namespace Wap\Controller;
use Think\Controller;

class WalletController extends Controller{
	//会员中心首页
    public function index(){
        $this->assign('page_title', '钱包');
        $this->display();
    }
    //OPC账单
    public function Bill(){
    	$this->assign('page_title', '账单');
        $this->display();
    }
    //委托订单
    public function myOrder(){
    	$this->assign('page_title', '委托订单');
        $this->display();
    }
    //买入订单详情
    public function buy_order(){
    	$this->assign('page_title', '交易订单');
    	$this->assign('id', I('get.id', '', 'intval'));
        $this->display();
    }
    //上传凭证
    public function certificate(){
    	$this->assign('page_title', '上传凭证');
    	$this->assign('id', I('get.id', '', 'intval'));
        $this->display();
    }
    //订单申诉
    public function appeal(){
        $this->assign('page_title', '订单申诉');
        $this->assign('order_sn', I('get.order_sn'));
        $this->display();
    }
    //卖出订单详情
    public function sell_order(){
    	$this->assign('page_title', '交易订单');
    	$this->assign('id', I('get.id', '', 'intval'));
        $this->display();
    }
    //我的矿机
    public function millList(){
    	$this->assign('page_title', '我的机组');
        $this->display();
    }
    public function millView(){
        $mill_sn = I('get.mill_sn');
        $this->assign('mill_sn', $mill_sn);
        $this->assign('page_title', '我的机组');
        $this->display();
    }
    //区块浏览
    public function block_brows(){
    	$this->assign('page_title', '区块浏览');
        $this->display();
    }
}