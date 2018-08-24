<?php
/**
 * 提现控制器
 */
namespace DataAdmin\Controller;
use Think\Controller;
use Common\Lib\Constants;
class ApplycashController extends BaseController {
    /**
     * 提现列表
     */
    public function lists(){
        $userid = I("userid");
        $rname = I("rname");
        $state = I("state");
        $btime = I("btime");
        $etime = I("etime");
        $ctime = I("ctime");
        $condi = array();
        if($userid!=""){
            $condi['m.userid'] = $userid;
            $param['userid'] = $userid;
        }
        if($rname!=""){
            $condi['m.rname'] = $rname;
            $param['rname'] = $rname;
        }
        if($state!=""){
            $condi['a.state'] = $state;
            $param['state'] = $state;
        }
        if($btime!=""){
            $condi['a.ctime'] = array("EGT",strtotime($btime));
            $param['btime'] = $btime;
        }
        if($etime!=""){
            $condi['a.ctime'] = array("ELT",strtotime($etime));
            $param['etime'] = $etime;
        }
        if($btime!="" && $etime!=""){
            $condi['a.ctime'] = array(array("EGT",strtotime($btime)),array("ELT",strtotime($etime)));
            $param['btime'] = $btime;
            $param['etime'] = $etime;
        }
        if($ctime!=""){
            $condi['a.ptime'] = array("EGT",strtotime($ctime));
            $param['ctime'] = $ctime;
        }
        $page = I("p");
        $lists = D("Applycash")->lists($condi,$page,'10','a.ctime DESC');
        $count = D("Applycash")->getcount($condi);
        $p = getpage($count,10,$param);
        $show = $p->newshow();
        $this->assign("list",$lists);
        $this->assign("page",$show);
        $this->display();
    }
    
    /**
     * 提现明细
     */
    public function process(){
        if($err = admin_require_check('id')) $this->error($err);
        $id = I("id");
        //查找该记录
        $info = D("Applycash")->process($id);
        if(IS_POST){
            //查询该记录
            $upd_condi['id'] = $id;
            $field = array('money,uid,ctime,account');
            if(!$applycashData = D("Applycash")->getinfo($field,$upd_condi)) {
                $this->error("操作失败[不存在的记录]");
            }
            //开启日志
            M()->startTrans();
            $result = true;
            //处理记录
            $state = I('state');
            $data['mgrid'] = session("dataAdmin.id");
            $data['ptime'] = time();
            $data['pname'] = '平台处理';
            $data['state'] = $state;
            $data['remark'] = I('remark');
            //修改记录
            $result = $result && D("Applycash")->to_apply($upd_condi,$data);
            //查询用户
            $member = D("Api/Members")->profiles($info['uid']);
            if($state == 1) {
                //标识已打款
                //操作日志
                $buy_back = $info['money'];
                $result = $result && D('Members')->buy_back($info['uid'],$buy_back);
                //扣除冻结余额
                $memberedit = array(
                    'token'=>$member['token'],
                    'goc'=>$member['goc'],
                    'goc_lock'=>$member['goc_lock'],
                    'usdc'=>$member['usdc'],
                    'usdc_lock'=>$member['usdc_lock'],
                    'balance'=>$member['balance'],
                    'balance_lock'=>$member['balance_lock'],
                    'chain_score'=>$member['chain_score'],
                    'share_score'=>$member['share_score'],
                    'id'=>$member['id'],
                    'sign'=>$member['sign']
                );
                $result = $result && D("Api/Members")->changeBalanceLock($memberedit,$info['money'],'out');
                            
                /* //给系统提现账户加手续费
                $appmember = D("Api/Members")->profiles(Constants::APPLY_UID);
                $accout = new_bcmul($info['money'],Constants::SCORE_APPLY_FEE);
                $adminedit = array(
                    'token'=>$appmember['token'],
                    'goc'=>$appmember['goc'],
                    'goc_lock'=>$appmember['goc_lock'],
                    'usdc'=>$appmember['usdc'],
                    'usdc_lock'=>$appmember['usdc_lock'],
                    'balance'=>$appmember['balance'],
                    'balance_lock'=>$appmember['balance_lock'],
                    'chain_score'=>$appmember['chain_score'],
                    'share_score'=>$appmember['share_score'],
                    'id'=>Constants::APPLY_UID,
                    'sign'=>$appmember['sign']
                );
                $result = $result && D("Api/Members")->changeBalance($adminedit,$accout,'in'); */
            }elseif($state==-1) {
                //拒绝
                $merber = D("Api/Members")->profiles($info['uid']);
                //检测冻结余额钱是否大于提现金额
                if($member['balance_lock']<$info['money']) err("拒绝失败");
                //退款金额
                $nowUserMoney = new_bcadd($merber['balance'],$info['money']);
                //回购失败日志
                $logdata = array(
                    'uid' => $info['uid'],
                    'changeform' => 'in',
                    'subtype' => 2,
                    'money' => $info['money'],
                    'ctime' => time(),
                    'describes' => 'c2c交易失败,已退回到您的账户,申请交易数量'.$info['money'],
                    'balance' => $nowUserMoney,
                    'money_type'=>1,
                );
                $result = $result && D("Api/MembersLog")->adds($logdata);
                //当前用户冻结消除
                $memberedit = array(
                    'token'=>$member['token'],
                    'goc'=>$member['goc'],
                    'goc_lock'=>$member['goc_lock'],
                    'usdc'=>$member['usdc'],
                    'usdc_lock'=>$member['usdc_lock'],
                    'balance'=>$member['balance'],
                    'balance_lock'=>$member['balance_lock'],
                    'chain_score'=>$member['chain_score'],
                    'share_score'=>$member['share_score'],
                    'id'=>$member['id'],
                    'sign'=>$member['sign']
                );
                $result = $result && D("Api/Members")->AdminApplyBalance($memberedit,$info['money'],'out');
            }
            if($result){
                M()->commit();
                $this->success("操作成功");
            }else{
                M()->rollback();
                $this->error("操作失败");
            }
            exit;
        }
        $this->assign("data",$info);
        $this->display();
    }
    
    /**
     * 充值链通积分列表
     */
    public function  chainLists(){
        $userid = I("userid");
        $btime = I("btime");
        $etime = I("etime");
        $name = I("name");
        $status = I("status");
        $ordersn = I("ordersn");
        $condi = array();
        if($userid!=""){
            $condi['m.userid'] = $userid;
            $param['userid'] = $userid;
        }
        if($ordersn!=""){
            $condi['r.ordersn'] = $ordersn;
            $param['ordersn'] = $ordersn;
        }
        if($name!=""){
            $condi['r.username'] = $name;
            $param['name'] = $name;
        }
        if($status!=""){
            $condi['r.status'] = $status;
            $param['status'] = $status;
        }
        if($btime!=""){
            $condi['ctime'] = array("EGT",strtotime($btime));
            $param['btime'] = $btime;
        }
        if($etime!=""){
            $condi['ctime'] = array("ELT",strtotime($etime));
            $param['etime'] = $etime;
        }
        if($btime!="" && $etime!=""){
            $condi['ctime'] = array(array("EGT",strtotime($btime)),array("ELT",strtotime($etime)));
            $param['btime'] = $btime;
            $param['etime'] = $etime;
        }
        $page = I("p");
        $count = M("recharge")->alias("r")->where($condi)->count();
        $p = getpage($count, C('PAGE_SIZE'),$param);
        $show = $p->newshow();
        $data = M("recharge")->alias("r")->where($condi)->page($page)->order("id desc")->limit(C('PAGE_SIZE'))->select();
        foreach ($data as $k=>$v){
            $info = D("Api/Members")->normalMember($v['uid'],'userid');
            $data[$k]['userid'] = $info['userid'];
        }
        $this->assign("list",$data);
        $this->assign("page",$show);
        $this->display();
    }
    
    /**
     * 充值链通积分  
     */
    public function add_chainscore(){
        $orderid = I("orderid");
        $recModel = D("Api/Recharge");
        $memberModel = D("Api/Members");
        $logModel = D("Api/MembersLog");

        $order = $recModel->findinfo(array("id"=>$orderid, "status"=>0));
        if(empty($order)) err("充值失败");
        //开启日志
        $memberModel->startTrans();
        $member = $memberModel->account($order['uid'], 'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign,path');

        $result = $memberModel->changeChainScore($member, $order['num']);

        $nowUserMoney = new_bcadd($member['chain_score'], $order['num']);
        $logdata = array(
            'uid' => $order['uid'],
            'changeform' => 'in',
            'subtype' => 7,
            'money' => $order['num'],
            'ctime' => NOW_TIME,
            'describes' => '转账充值链通积分'.$order['num'].'到账',
            'balance' => $nowUserMoney,
            'money_type'=>3,
        );
        $result = $result && $logModel->adds($logdata);
        $result = $result && $recModel->modify(array("id" => $orderid), array("status" => 1, "ptime" =>NOW_TIME));

        //推荐人奖励 2级 发放成余额
        $rewardLevel = 2;
        $path = explode(',', $member['path']);
        $path = array_reverse($path);
        for($i = 1; $i <= $rewardLevel; $i++){
            if($path[$i]){
                $rate = get_reward_rate($i);
                $num  = new_bcmul($order['num'], $rate);
                $leader = $memberModel->account($path[$i]);
                if($leader){
                    $newBalan = new_bcadd(new_bcadd($leader['balance'], $num), $leader['balance_lock']);
                    $result = $result && $memberModel->changeBalance($leader, $num);
                    $log = array('uid' => $leader['id'], 'changeform' => 'in', 'subtype' => 14, 'money' => $num, 'ctime' => NOW_TIME, 'balance' => $newBalan, 'extends' => $member['id'], 'money_type' => 1);
                    $result = $result && $logModel->add($log);
                }
            }else{
                break;
            }
        }

        //社区奖励
        $nPath = substr($member['path'], 0, strripos($member['path'], ','));
        if($nPath){
            //第一个社区
            $team1 = $memberModel->where('id in (%s) AND vip_level > 1', $nPath)->order(' id DESC')->find();
            if($team1){
                $rate = get_team_rate($team1['vip_level']);
                $num  = new_bcmul($order['num'], $rate);
                $newBalan = new_bcadd(new_bcadd($team1['balance'], $num), $team1['balance_lock']);
                $result = $result && $memberModel->changeBalance($team1, $num);
                $log = array('uid' => $team1['id'], 'changeform' => 'in', 'subtype' => 15, 'money' => $num, 'ctime' => NOW_TIME, 'balance' => $newBalan, 'extends' => $member['id'], 'money_type' => 1);
                $result = $result && $logModel->add($log);
            }
            //第二个社区
            if($team1 && $team1['vip_level'] < 3){
                $team2 = $memberModel->where('id in (%s) AND vip_level = 3', $nPath)->order(' id DESC')->find();
                if($team2){
                    $rate = get_team_rate($team2['vip_level']);
                    $num  = new_bcmul($order['num'], $rate);
                    $newBalan = new_bcadd(new_bcadd($team2['balance'], $num), $team2['balance_lock']);
                    $result = $result && $memberModel->changeBalance($team2, $num);
                    $log = array('uid' => $team2['id'], 'changeform' => 'in', 'subtype' => 15, 'money' => $num, 'ctime' => NOW_TIME, 'balance' => $newBalan, 'extends' => $member['id'], 'money_type' => 1);
                    $result = $result && $logModel->add($log);
                }
            }
        }



        //直推奖励 200乐享积分
        $isRecharge=$recModel->where(array('uid'=>$order['uid'],'status'=>1))->count();
        if($isRecharge==1){
            $spath = explode(',', $member['path']);
            $spath = array_reverse($spath);
            if($spath[1]){
                $rewardShare =Constants::REGISTER_RECOMMEND;
                $leader = $memberModel->account($path[1]);
                if($leader){
                    $newShare =new_bcadd($leader['share_score'], $rewardShare);
                    $result = $result && $memberModel->changeShareScore($leader, $rewardShare);
                    $log = array('uid' => $leader['id'], 'changeform' => 'in', 'subtype' => 9, 'money' => $rewardShare, 'ctime' => NOW_TIME,'describes'=>'直推首充奖励', 'balance' => $newShare, 'extends' => $member['id'], 'money_type' => 4);
                    $result = $result && $logModel->add($log);
                }
            }
        }


        if($result){
            $memberModel->commit();
            succ("充值成功");
        }else{
            $memberModel->rollback();
            err("充值失败");
        } 
    }
}