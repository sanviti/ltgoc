<?php
/**
 * 用户登录/注册
 */
namespace Wap\Controller;
use Think\Controller;

class LoginController extends Controller{
	//登录
    public function index(){
        $this->assign('page_title', '登录');
        $this->display();
    }
    //注册
    public function register(){
        $this->assign('page_title', '注册');
        $this->assign('lid', I('lid'));
        $this->display();
    }
    //找回密码
    public function findpwd(){
        $this->assign('page_title', '找回密码');
        $this->display();
    }

    public function reg(){
        $this->assign('page_title', '注册');
        $this->assign('lid', I('lid'));
        $this->display();
    }
}