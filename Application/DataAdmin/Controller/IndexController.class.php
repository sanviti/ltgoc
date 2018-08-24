<?php
namespace DataAdmin\Controller;
use Think\Controller;
class IndexController extends BaseController {
    public function index(){
        $this->display();
    }
    
    public function test(){
        $n=array(array(10,23,11),array(13,23,21),array(11,3,5));
        echo count($n,1);
    }
}