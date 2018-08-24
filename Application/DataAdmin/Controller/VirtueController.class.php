<?php
namespace DataAdmin\Controller;
class VirtueController extends BaseController{
    //充值金链列表
    public function rechargeLists(){
        $userid = I("userid");
        $rname = I("rname");
        $btime = I("btime");
        $etime = I("etime");
        $condi = array();
        if($userid!=""){
            $condi['m.userid'] = $userid;
        }
        if($rname!=""){
            $condi['m.rname'] = $rname;
        }
        if($btime!=""){
            $condi['ctime'] = array("EGT",strtotime($btime));
        }
        if($etime!=""){
            $condi['ctime'] = array("ELT",strtotime($etime));
        }
        if($btime!="" && $etime!=""){
            $condi['ctime'] = array(array("EGT",strtotime($btime)),array("ELT",strtotime($etime)));
        }
        $page = I("p");
        $count = D("AddGoc")->getcount($condi);
        $p = getpage($count, C('PAGE_SIZE'),$condi);
        $show = $p->newshow();
        $data = D("AddGoc")->lists($condi,$page,C('PAGE_SIZE'),'id desc');
        $this->assign("list",$data);
        $this->assign("page",$show);
        $this->display();
    }

    //充值金链
    public function recharge(){
        $this->display();
    }

    //执行充值
    public function adds(){

        $data['phone'] = trim(I('phone', '', 'htmlspecialchars'));
        $data['num'] =trim(I('num', '', 'trim'));
        $data['note'] = trim(I('note', '', 'htmlspecialchars'));
        if (empty($data['phone'])) {
            err('手机号不能为空');
        }
        if (empty($data['num'])) {
            err('充值数量不能为空');
        }
        $memberModel = D('Members');
        $where['phone'] = $data['phone'];
        $info = $memberModel->normalMember($where);
        if(empty($info)) err("该账户不存在");
        if(empty($info['rname'])){
            $info['rname']='';
        }
        $content = array(
            "phone"=>$data['phone'],
            "num"=>$data['num'],
            "rname"=>$info['rname'],
        );
        succ($content);
    }

    public function add_goc(){
        // err('关闭');
        $data['phone'] = trim(I('phone', '', 'htmlspecialchars'));
        $data['num'] = I('num');
        $data['ctime'] = time();
        if (empty($data['phone'])) {
            err('手机号不能为空');
        }
        if (empty($data['num'])) {
            err('充值数量不能为空');
        }
        if($data['num']<0){
            err('充值数量错误');
        }
        M()->startTrans();
        $pass = true;
        $memberModel = D('Members');
        $where['phone'] = $data['phone'];
        $info = $memberModel->normalMember($where);

        M()->startTrans();
        $result= D("Members")->changeGoc($info,$data['num'],'in');
        $nowGoc =bcadd($info['goc'],$data['num'],3);
        //记录表
        $chainArr = array(
            "phone"=>$data['phone'],
            "num"=>$data['num'],
            "uid"=>$info['id'],
            "ctime"=>time(),
            "adminid"=>session("dataAdmin.id"),
            "balance"=>$nowGoc,
        );
        $result = $result && D("AddGoc")->adds($chainArr);
        //日志表
        $logdata = array(
            'uid' => $info['id'],
            'changeform' => 'in',
            'subtype' => 5,
            'money' => $data['num'],
            'ctime' => time(),
            'describes' => '您认购的'.$data['num'].'GOC已到账',
            'balance' => $nowGoc,
            'money_type'=>5
        );
        $result = $result && D("Api/MembersLog")->adds($logdata);

        if($result){
            M()->commit();
            succ("GOC充值成功");
        }else{
            M()->rollback();
            err("GOC充值失败");
        }
    }


    /**
     * 版本管理
     */
    public function version_index(){
        $verModel = M('version');
        //分页
        $pageSize = C('PAGE_SIZE');
        $count = $verModel->count();
        $p = getpage($count,$pageSize);
        $show = $p->show();
        $list = $verModel->alias('v')->field("v.*,adm.username AS managername")->join("LEFT JOIN __ADMIN__ adm ON adm.id = v.managerid")->page(I('p'))->limit($pageSize)->order('v.code DESC')->select();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->display();
    }
    /**
     * 版本添加
     */
    public function version_add(){
        if(IS_AJAX){
            $verModel = M('version');
            $verModel->create();
            $verModel->dateline = NOW_TIME;
            $verModel->managerid = $this->adminid();
            if($verModel->add()){
                $this->success('添加成功');
            }else{
                $this->success('添加失败');
            }
        }
        $this->display();
    }
    /**
     * 版本修改
     */
    public function version_edit(){
        if($error = admin_require_check('code')) $this->error($error);
        $verModel = M('version');
        $condi['code'] = I('code');
        if(IS_AJAX){
            $verModel->create();
            $verModel->dateline = NOW_TIME;
            $verModel->managerid = $this->adminid();
            if($verModel->where($condi)->save()){
                $this->success('修改成功');
            }else{
                $this->success('修改失败');
            }
            exit;
        }
        $data = $verModel->where($condi)->find();
        $this->assign('data',$data);
        $this->display('version_add');
    }

    /**
     * 版本删除
     */
    public function version_del(){
        if($error = admin_require_check('code')) $this->error($error);
        $verModel = M('version');
        $condi['code'] = I('code');
        if($verModel->where($condi)->delete()){
            succ("删除成功");
        }else{
            err("删除失败");
        }
    }
	
    
    /**
     * 导表充值goc
     */
	public function export_addgoc(){
    	$this->display();
    }
    
    /**
     * 充值链通积分及余额
     */
    public function recharge_score(){
        $this->display();
    }
    
    //执行充值链通积分及余额
    public function add_require(){
        $data['phone'] = trim(I('phone', '', 'htmlspecialchars'));
        $data['num'] =trim(I('num', '', 'trim'));
        $data['type'] = trim(I('type', '', 'htmlspecialchars'));
        if (empty($data['phone'])) {
            err('手机号不能为空');
        }
        if (empty($data['num'])) {
            err('充值数量不能为空');
        }
        if (empty($data['type'])) {
            err('充值类型不能为空');
        }
        $memberModel = D('Members');
        $where['phone'] = $data['phone'];
        $info = $memberModel->normalMember($where);
        if(empty($info)) err("该账户不存在");
        if(empty($info['rname'])){
            $info['rname']='未实名';
        }
        $content = array(
            "phone"=>$data['phone'],
            "num"=>$data['num'],
            "rname"=>$info['rname'],
            "type"=>$data['type'],
        );
        succ($content);
    }
    
    public function add_exe(){
        $data['phone'] = trim(I('phone', '', 'htmlspecialchars'));
        $data['num'] = I('num');
        $data['type'] = I('type');
        $data['ctime'] = time();
        if (empty($data['phone'])) {
            err('手机号不能为空');
        }
        if (empty($data['num'])) {
            err('充值数量不能为空');
        }
        if($data['num']<0){
            err('充值数量错误');
        }
        if (empty($data['type'])) {
            err('充值类型不能为空');
        }
        M()->startTrans();
        $pass = true;
        $memberModel = D('Members');
        $where['phone'] = $data['phone'];
        $info = $memberModel->normalMember($where);
    
        $types = $data['type'];
        if($types==1){ //链通积分
            $result= D("Members")->changeChainScore($info,$data['num'],'in');
            $nowMoney =bcadd($info['chain_score'],$data['num'],3);
            $des = '链通积分';
            $money_type = 3;
        }elseif ($types==2){ //余额
            $result= D("Members")->changeBalance($info,$data['num'],'in');
            $nowMoney =bcadd($info['balance'],$data['num'],3);
            $des = '余额';
            $money_type = 1;
        }
        
        //记录表
        $chainArr = array(
            "phone"=>$data['phone'],
            "num"=>$data['num'],
            "type"=>$data['type'],
            "uid"=>$info['id'],
            "ctime"=>time(),
            "adminid"=>session("dataAdmin.id"),
            "balance"=>$nowMoney,
        );
        // dump($chainArr);
        $result = $result && M("rechargeList")->add($chainArr);
        // echo M()->getLastSql();
        //日志表
        $logdata = array(
            'uid' => $info['id'],
            'changeform' => 'in',
            'subtype' => 5,
            'money' => $data['num'],
            'ctime' => time(),
            'describes' => '您充值的'.$data['num'].$des.'已到账',
            'balance' => $nowMoney,
            'money_type'=>$money_type
        );
        $result = $result && D("Api/MembersLog")->adds($logdata);
    
        if($result){
            M()->commit();
            succ("充值成功");
        }else{
            M()->rollback();
            err("充值失败");
        }
    }
}
?>