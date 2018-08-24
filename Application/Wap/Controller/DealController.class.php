<?php
/**
 * 交易中心
 */
namespace Wap\Controller;
use Think\Controller;

class DealController extends Controller{
	//登录
    public function index(){
        $this->assign('page_title', '交易中心');
        $this->display();
    }
}