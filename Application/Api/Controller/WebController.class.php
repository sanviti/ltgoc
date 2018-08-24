<?php
/**
 * 用户登录/注册
 */
namespace Api\Controller;
use Think\Log;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class WebController extends Controller{
    public function register(){
        $lid = I("lid");
        $this->assign("lid",$lid);
        $this->display();
    }
    
    public function download(){
        $baseUrl=Constants::BASE_URL;
        $this->assign('baseurl',$baseUrl);
        $this->display();
    }
    
}