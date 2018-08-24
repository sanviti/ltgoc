<?php
namespace DataAdmin\Controller;
use Think\Controller;
Use Think\Cache\Driver\Redis;
use Common\Lib\RestSms;
use Common\Lib\Constants;
class MillsController extends BaseController{
    //列表页
    public function index(){
        $list = array();

        for($i = 0; $i < 10; $i++){
            $model = M('mills_0' . $i);
            $mills = $model->field('type, count(id) as num')->where(array('status' => 1))->group('type')->select();
            array_push($list, $mills);
        }

        $this->assign('list', $list);
        $this->display('index');        
    }

}