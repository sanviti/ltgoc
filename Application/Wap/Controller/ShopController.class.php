<?php
/**
 * 商城
 */
namespace Wap\Controller;
use Think\Controller;

class ShopController extends Controller{
	//首页
    public function index(){
        $this->assign('page_title', '商城首页');
        $this->display();
        
    }

    public function mill(){
        $type = I('type/d');
        empty($type) && exit();
        $this->assign('page_title', '光电机组详情');
        
        $name  = mill_name($type);
        $power = mill_power($type);
        $price = mill_price($type);
        $hour720 = number_format(mill_max_output($type), 2, '.', '');
        $hour72  = number_format($hour720 / 720 * 72, 2, '.', ''); 
        $hour12  = number_format($hour720 / 720 * 12, 2, '.', ''); 
        $this->assign('type', $type);
        $this->assign('power', $power);
        $this->assign('name', $name);
        $this->assign('hour12', $hour12);
        $this->assign('hour72', $hour72);
        $this->assign('hour720', $hour720);
        $this->assign('price', $price);
        $this->display();
    }
    
}