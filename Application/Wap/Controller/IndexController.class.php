<?php
/**
 * 用户登录/注册
 */
namespace Wap\Controller;
use Think\Controller;

class IndexController extends Controller{
	//登录
    public function index(){
        $this->assign('page_title', '首页');
        $this->display();
    }
    public function notice(){
        $id = I('id/d');
        empty($id) && exit();
        $data = M('notice')->field('title, info as content')->where(array('id' => $id))->find();
        empty($data) && exit();
        $this->assign('category_name', '公告');        
        $this->assign('title', $data['title']);
        $this->assign('content', html_entity_decode($data['content']));
        $this->assign('page_title', '公告');        
        $this->display('detail');
    }
    public function detail(){
        $id = I('id/d');
        empty($id) && exit();
        $data = M('news')->field('title, content')->where(array('id' => $id))->find();
        empty($data) && exit();
        $this->assign('page_title', '最新资讯');     
        $this->assign('category_name', '最新资讯');
        $this->assign('title', $data['title']);
        $this->assign('content', $data['content']);
        $this->display('detail');
    }

    public function download(){
        $this->display();
    }
}