<?php
/**
 * 用户登录/注册
 */
namespace Wap\Controller;
use Think\Controller;

class MembersController extends Controller{
	//会员中心首页
    public function index(){
        $this->assign('page_title', '会员中心');
        $this->display();
    }
    //OPC光电链
    public function photoelectric(){
    	$this->assign('page_title', 'PEC光电链');
        $this->display();
    }
    //个人信息
    public function memberInfo(){
    	$this->assign('page_title', '个人信息');
        $this->display();
    }
    //我的工会
    public function laborUnion(){
    	$this->assign('page_title', '我的工会');
        $this->display();
    }
    //工会招募
    public function Unionrecruit(){
        $this->assign('page_title', '工会招募');
        $this->display();
    }
    //C1认证
    public function auth_c1(){
    	$this->assign('page_title', 'C1认证');
        $this->display();
    }
     //C2认证
    public function auth_c2(){
    	$this->assign('page_title', 'C2认证');
        $this->display();
    }
    //银行卡
    public function BankCard(){
    	$this->assign('page_title', '银行卡');
        $this->display();
    }
    //排行榜
    public function ranking(){
    	$this->assign('page_title', '排行榜');
        $this->display();
    }
    //新手入门
    public function newGuide(){
    	$this->assign('page_title', '新手入门');
        $this->display();
    }
    //新手入门详情
    public function article(){
    	$news_id = (int)I('get.news_id');
    	$this->assign('news_id', $news_id);
    	$this->assign('page_title', '新手入门');
        $this->display();
    }
    //客服中心
    public function support(){
    	$this->assign('page_title', '客服中心');
        $this->display();
    }
    //系统设置
    public function system(){
    	$this->assign('page_title', '系统设置');
        $this->display();
    }
    //我的邮件
    public function myMail(){
    	$this->assign('page_title', '我的邮件');
        $this->display();
    }
    //修改密码
    public function changePwd(){
    	$this->assign('page_title', '修改密码');
        $this->display();
    }
    //修改交易密码
    public function changePayPwd(){
    	$this->assign('page_title', '交易密码');
        $this->display();
    }
    //重置交易密码
    public function resetPayPwd(){
        $this->assign('page_title', '交易密码');
        $this->display();
    }
}