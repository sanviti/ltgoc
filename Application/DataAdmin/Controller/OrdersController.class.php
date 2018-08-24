<?php
/**
 * 订单管理
 */
namespace DataAdmin\Controller;
use Think\Controller;
Use Think\Cache\Driver\Redis;
use Common\Lib\RestSms;
use Common\Lib\Constants;
class OrdersController extends BaseController{

    //兑换列表
    public function exchange(){       
        $uid = I('uid');
        $phone = I("phone");
        if($uid){
            $params['uid'] = $uid;
            $condi['m.userid'] = $uid;
        }
        if($phone){
            $params['phone'] = $phone;
            $condi['m.phone'] = $phone;
        }
        $status = I('status');
        if($status !== ''){
            $params['status'] = $status;
            $condi['e.status'] = $status;
        }
        $btime = I("btime");
        $etime = I("etime");
        $ctime = I("ctime");
        if($btime!=""){
            $condi['e.ctime'] = array("EGT",strtotime($btime));
            $param['btime'] = $btime;
        }
        if($etime!=""){
            $condi['e.ctime'] = array("ELT",strtotime($etime));
            $param['etime'] = $etime;
        }
        if($btime!="" && $etime!=""){
            $condi['e.ctime'] = array(array("EGT",strtotime($btime)),array("ELT",strtotime($etime)));
            $param['btime'] = $btime;
            $param['etime'] = $etime;
        }
        if($ctime!=""){
            $condi['e.ptime'] = array("EGT",strtotime($ctime));
            $param['ctime'] = $ctime;
        }
        $page = IS_POST ? 1 : I('get.p');
        $model = M('exchange');
        $count = $model->alias('e')-> where($condi)->join('LEFT JOIN lt_members AS m ON m.id = e.uid')->count();
        $p = getpage($count, C('PAGE_SIZE'), $params);
        $show = $p -> newshow($params);
        $list = $model->alias('e')
        ->field('e.*, m.userid, m.rname,m.phone')
        ->join('LEFT JOIN lt_members AS m ON m.id = e.uid')
        -> where($condi) -> page($page) -> limit(C('PAGE_SIZE')) -> order('sort DESC,id asc') -> select();
       
        $this -> assign('page', $show);
        $this -> assign('list', $list);
        $this -> display();
    }


    /**
     * 兑换USDC审核通过
     */
    public function confirm(){
        if(IS_AJAX){
            if($err = admin_require_check('ids,action')) $this->error($err);
            $ids = I('ids/s');
            $ids = array_filter(explode(',', $ids));
            $action = I('action/s');
            $exchangeModel = M('exchange');
            $memberModel = D('Api/members');
            $logModel = D("Api/MembersLog");
            $list = $exchangeModel->where(array('id' => array('in', $ids), 'status' => 0))->order('sort desc,id asc')->limit(10)->select();
            //开始事物
            $exchangeModel -> startTrans();
            $result = true;
            foreach($list as $item){
                if($action == 'pass'){
                    //更改审核状态
                    $exchangUpd = array('status' => 1,'admin' => $this->adminid(), 'ptime' => NOW_TIME);
                    $result = $result && $exchangeModel->where(array('id'=>$item['id']))->save($exchangUpd);
                    //更改用户状态
                    $user=$memberModel->profiles($item['uid']);
                    $result = $result && $memberModel->changeUsdc($user,$item['usdc'],'in');
                    $newusdc=bcadd($user['usdc_lock'],bcadd($user['usdc'],$item['usdc'],3),3);
                    $log=array(
                        'uid' => $user['id'],
                        'changeform' => 'in',
                        'subtype' => 3,
                        'money' =>$item['usdc'],
                        'ctime' => time(),
                        'describes' => '兑换USDC',
                        'balance' => $newusdc,
                        'money_type' =>2 //usdc
                    );
                    $LogModel=D('Api/MembersLog');
                    $result = $result &&  $LogModel->adds($log);
                }else{
                    $this->error('操作失败');
                }
            }
            if($result){
                $exchangeModel->commit();
                $this->success('操作成功');
            }else{
                $exchangeModel->rollback();
                $this->error('操作失败');
            }

        }
    }
}