<?php 
namespace Api\Controller;
use Common\Lib\Constants;
class ApplyController extends BaseController{
    /**
     * 卖出(提现)
     * money  卖出数量
     * cardid 银行卡id
     * cid    支给商户id
     * 
     */
    public function applycash(){
        require_check_api('money,cardid,pwd,vcode',$this->post);
        $date = mktime(0,0,0,date('m'),date('d'),date('Y'));
        if(time()<$date+Constants::C2C_TRANDING_STIME || time()>$date+Constants::C2C_TRANDING_ETIME){
           err('非C2C余额交易时间');
        }
        $money = number_format($this->post['money'], 0, '','');
        $cardid = intval($this->post['cardid']);
        $pwd = $this->post['pwd'];
        if($money > 50000 || $money < 100)err('金额错误');
        if($money % 100 > 0) err('卖出数量要是 100 的倍数');
        $memberModel = D('Members');
        $member = $memberModel->normalMember($this->uid);
        
        if(!$member) err('操作失败');
        if($member['is_freeze'] == 1) err('抱歉，您的账户资金已被冻结！');
        if($member['is_lock'] == 1)   err('抱歉，您的账户资金已被锁定！');
        //余额
        if($member['balance']<$money) err('余额不足'); 
        
        //银行卡
        $where = array();
        $where['uid'] = $this->uid;
        $where['cardid'] = $cardid;
        $bankinfo = D('bank')->where($where)->find();
        if(!$bankinfo)err('银行卡不存在');
        if($bankinfo['truename'] != $member['rname']) {
            err('卖出失败，您的银行卡开户名与您的账户姓名不一致');
        }  
        //二级密码
        if($member['pay_pwd'] == null) err('请设置交易密码');
        if($member['pay_pwd'] != md5password($pwd,$member['pay_salt'])) err('交易密码错误');

        //判断短信验证码
        $codeModel = D('Validcode');
        if($codeModel -> expires($member['phone'],$this->post['vcode'],Constants::SMS_APPLYCASH_CODE)){
            err('短信验证码错误或已失效');
        }

        $newBalance = new_bcsub($member['balance'],$money);
        //日志
        $log = array(
             'uid' => $member['id'], 
             'changeform' => 'out',
             'subtype' => 1,
             'money_type' =>1, //余额
             'money' => $money,
             'ctime' => time(),
             'balance' => $newBalance,
             'describes' => 'c2c卖出',
        );
        //提现申请
        $disburse = new_bcmul($money,(1-Constants::SCORE_APPLY_FEE)); //到账金额
        $account = new_bcmul($money,Constants::SCORE_APPLY_FEE); //手续费
        $apply = array(
             'uid' => $member['id'],
             'money' => $money,
             'disburse' => $disburse,
             'cardid' => $bankinfo['card'],
             'rname' => $bankinfo['truename'],
             'bank' => $bankinfo['bankname'],
             'subbranch' => $bankinfo['branchname'], 
             'ctime' => time(),
             'account'=>$account,
             'ordersn'=>orderSN('c'),
        );
        
        $logModel = D("MembersLog");
        $applyModel = D("Apply");
        //开启事物
        M()->startTrans();
        $result = true;
        //验证签名
        $data = D("Members")->profiles($this->uid);
        $result = $result &&  $memberModel->ApplyBalance($data,$money);
        $result = $result && $logModel->adds($log);
        $result = $result && $applyModel->adds($apply);
        if($result){
            M()->commit();
            succ('操作成功');
        }else{
            M()->rollback();
            err('操作失败'); 
        }
    }
    
   /**
    * c2c订单记录
    */
    public function applyLog(){
        $page = intval($this->post['p']);
        //提现中，提现失败
        $condi = array();
        $uid = $this->uid;
        $condi['uid'] = $uid;
        $field = array("id,money,ctime,state");
        $log = D("Apply")->applyLog($page,$field,$condi,C('PAGE_SIZE'));
        $array = array(
            'list'=>$log,
        );
        succ($this->output($array));
       // succ($array);
    }
    
  /**
   * c2c订单详情
   */
    public function apply_detail(){
        require_check_api('orderid',$this->post);
        $orderid = intval($this->post['orderid']);
        $info = D("Apply")->applydetail($orderid);
        //succ($info);
        succ($this->output($info));
    }
}

?>