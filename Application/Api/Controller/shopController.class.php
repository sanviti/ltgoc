<?php
/**
 * 商城
 */
namespace Api\Controller;
use Think\Log;
use Think\Controller;
use Think\Model;
use Common\Common\Lib\Constants;

class DealController extends BaseController{
    //商城首页
    public function index(){
        $member = D('members')->profiles($this->uid);
        $data['opc'] = $member['score'];
        $data['opcl'] = $member['score_lock'];
        $data['address'] = $member['token'];
        succ($data);
    }

    //购买矿机
    public function buyMill(){

    }
    
    //购买物品
    public function buyProduct(){

    }
}