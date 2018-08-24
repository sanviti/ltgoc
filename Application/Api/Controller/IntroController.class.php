<?php
/**
 * 介绍接口
 */
namespace Api\Controller;
use Think\Controller;
use Common\Lib\Constants;
class IntroController extends Controller{
    //资产说明
    public function asset_intro(){
        $field = array("title,content");
        $data = D("Intro")->intro($field,"63");
        foreach ($data as $k=>$v){
            $data[$k]['content'] = strip_tags(html_entity_decode($v['content']));
        }
        $array = array(
            'list'=>$data
        );
        succ($array);
    }
    
    //新手指南
    public function new_guide(){
        $field = array("id,title");
        $data = D("Intro")->intro($field,"62");
        foreach ($data as $k=>$v){
            $data[$k]['detail'] = Constants::BASE_URL.'/Api/Index/detail/id/'.$v['id'];
        }
        $array = array(
            'list'=>$data
        );
        succ($array);
    }
    

    // 关于我们
    public function aboutus(){
       $about = D("Intro")->get_noticeid("64");
       $array = array(
           'detail' => Constants::BASE_URL.'/Api/Index/detail/id/'.$about['id'],
       );
       succ($array);
    }
    
    //兑换规则 ---usdc兑换联通积分
    public function exchangeRule(){
        $field = array("title,content");
        $about = D("Intro")->detail($field,"65");
        succ($about);
    }
    
    //卖出规则
    public function sellRule(){
        $field = array("title,content");
        $about = D("Intro")->detail($field,"66");
        succ($about);
    }
    
    //充值说明
    public function rechargeRule(){
        $field = array("title,content");
        $about = D("Intro")->detail($field,"67");
        succ($about);
    }
    //兑换规则 ---链通积分兑换乐享积分
    public function exchangeRule2(){
        $field = array("title,content");
        $about = D("Intro")->detail($field,"68");
        succ($about);
    }
}
?>