<?php
/**
 * 个人中心控制器
 * 217-11-26
 * lxy
 */
namespace Api\Controller;
Use Think\Controller;
Use Think\Model;
Use Think\Log;
Use Api\Common\MsgSendUtil;
use Common\Lib\Constants;

class MembersController extends BaseController{

    /**
     * 账户管理 (个人中心首页数据)
     * 
     * */
    public  function  account(){
        $member  = D("Members");
        $acctInfo=$member->profiles($this->uid,'recom_token as userid,vip_level,phone,goc,usdc,balance,chain_score,share_score');
        succ($this->output($acctInfo));
    }
    /**
     * 个人资料 (个人中心)
     *
     * */
    public  function  personal_data(){
        $member  = D("Members");
        $acctInfo=$member->profiles($this->uid,'phone');
        $array = array();
        $auth = D("Auths")->isauth($this->uid);
        if(empty($auth)) {
            $rname = "未认证";
        }elseif ($auth['status']==0){
            $rname = "认证中";
        }elseif ($auth['status']==1){
            $rname = "已认证";
        }elseif($auth['status']==-1){
            $rname = "认证失败";
        }
        $array = array(
            'phone'=>phoneaddstar($acctInfo['phone']),
            'rname'=>$rname
        );
        succ($this->output($array));
    }
    /**
     * [myDirect 推荐列表]
     * @return JSON
     */
    public function myDirect(){
        $map = array(
            'leadid' => $this->uid
        );
        $page=intval($this->post['p']);
        if(empty($page)){
            $page=1;
        }
        $mod_member = D('members');
        $member_list = $mod_member
        ->field('rname,FROM_UNIXTIME(reg_time, "%Y-%m-%d %H:%i:%s") AS reg_time,phone')
        ->where($map)
        ->order('reg_time DESC')
        ->page($page)
        ->limit(10)
        ->select();
        foreach($member_list as $key=>$value){
            $member_list[$key]['phone']=phoneaddstar($value['phone']);
            $member_list[$key]['rname']=strval($value['rname']);
        }
        $recomend=$mod_member->profiles($this->uid,'children_num,recomend_reward');
        $result = array(
            'list' => $member_list,
            'recomend'=>$recomend,
        );
        succ($this->output($result));
    }


    /**
     * 更多订单
     * @param  int $page    分页
     * @param  str $orderType 请求类型 0 交易中 1卖出
     * @return [type]          [description]
     */
    public function order(){
        $page = intval($this->post['p']);
        $orderType = intval($this->post['orderType']);
        if($orderType==0){
            $condi = array(
                'uid'=>$this->uid,
                'status'=>0
            );
        }else{
            $condi = array(
                'uid'=>$this->uid,
                'status'=>array('neq',0)
            );
        }
        $Model = D('TradingOrder');

        $list = $Model -> where($condi) -> field('id,ptime,price,num,order_type,succ_num,status')
            -> page($page) -> limit(10) -> order('ctime DESC') -> select();
        foreach($list as  $key=>$value){
            $list[$key]['ptime'] = date("Y-m-d H:i:s",$value['ptime']);
        }
        $data['list'] = $list;
        succ($this->output($data));
    }
    /**
     * 分享二维码
     * @return [type] [description]
     */
    public function share(){
        $memberModel = D('members');
        $member = $memberModel->profiles($this->uid, 'recom_token');
        if($member){
            $member_dep = $memberModel->member_dep($this->uid,'qrcode_rec');
            $shareUrl = Constants::BASE_URL . '/Api/Web/register/lid/' . $member['recom_token'] . '.html';
            //生成二维码
            if($member_dep['qrcode_rec'] == '' || !is_file('.' . $member_dep['qrcode_rec'])){
                $qr = $this->create_qrcode($member['userid'], $shareUrl);
                $memberModel->modify_memberdep($this->uid, array('qrcode_rec' => $qr));
            }else{
                $qr = $member_dep['qrcode_rec'];
            }
            $data = array(
                'url' => $shareUrl,
                'shareid' => $member['recom_token'],
                'qrcode' => $qr,
            );
            succ($data);
        }
    }

    /**
     * 生成推荐二位码
     * @param $id
     * @return string
     */
    private function create_qrcode($userid, $val){
        $filename = md5(rand(0,9999).microtime()).'.png';
        $path = './Uploads/qrcode/'.date('Y-m-d').'/';
        if(!is_dir($path)){
            mkdir($path);
        }
        import('Vendor.phpqrcode.phpqrcode','','.php');
        $errorCorrectionLevel = 'L';//容错级别
        $matrixPointSize = 6;//生成图片大小
        \QRcode::png($val, $path.$filename, $errorCorrectionLevel, $matrixPointSize,1,0);
        return ltrim($path.$filename,'.');
    }
    /**
     * [实名认证]
     * @return JSON
     */
    public function auth(){
        $map_c1 = array(
            'uid' => $this->uid
        );
        $mod_auths = D('Auths');
        $mod_auths->startTrans();
        $auth_info = $mod_auths->lock(true)->where($map_c1)->find();
        if ($auth_info) {
            if ($auth_info['status'] == 0) {
                $mod_auths->rollback();
                err('认证审核中，请勿重复提交！');
            }
            if ($auth_info['status'] == 1) {
                $mod_auths->rollback();
                err('已认证成功，请勿重复提交！');
            }
            if ($auth_info['status'] == -1) {
                $mod_auths->lock(true)->where($map_c1)->delete();
            } 
        }
        require_check_api("rname,idcard,frontimg,backimg,photo", $this->post);
        $rname   = addslashes($this->post['rname']);
        $idcard = addslashes($this->post['idcard']);
        $frontimg = addslashes($this->post['frontimg']);
        $backimg = addslashes($this->post['backimg']);
        $photo = addslashes($this->post['photo']);
        if (!validation_filter_id_card($idcard)) {
            $mod_auths->rollback();
            err('身份证号码不正确！');
        }

        if (empty($frontimg)) {
            
            err('请上传正面身份证照片！');
        }
        if (empty($backimg)) {

            err('请上传背面身份证照片！');
        }
        if (empty($photo)) {

            err('请上传手持身份证照片！');
        }
        //判断该身份证号是否已认证
        $have = D("Auths")->ishave($idcard);
        if($have) err("该身份证号已认证");
        $auth_data = array(
            'uid' => $this->uid,
            'idcard' => $idcard,
            'rname' => $rname,
            'frontimg' => $frontimg,
            'backimg' => $backimg,
            'photo' => $photo,
            'status' => 0,
            'ctime' => time()
        );
        if ($mod_auths->add($auth_data)) {
            $mod_auths->commit();
            succ('提交成功，请等待审核！');
        } else {
            $mod_auths->rollback();
            err('网络繁忙，请稍后再试！');
        }
    }

    /**
     * 实名认证结果
     */
    public function auth_result(){
        $uid = $this->uid;
        $info = D("Auths")->field("status,remark,rname,idcard")->where(array("uid"=>$uid))->find();
        $array = array();
        if(empty($info)){
            $array = array(
                'msg'=>'',
                'iscertify'=>-2
            );
        }else{
            if($info['status']==0){
                $array = array(
                    'msg'=>'',
                    'iscertify'=>$info['status']
                );
            }elseif ($info['status']==1){
                $array = array(
                    'msg'=>'',
                    'iscertify'=>$info['status']
                );
            }elseif ($info['status']==-1){
                $array = array(
                    'msg'=>$info['remark'],
                    'iscertify'=>$info['status']
                );
            }
        }
        
        //succ($array);
        succ($this->output($array));
    }
    /**
     * 修改登录密码
     * @param  str $opwd 旧密码
     * @param  str $npwd 新密码
     */
    public function editpwd(){
        require_check_api("npwd,npwd2,vcode", $this->post);
        $npwd = addslashes($this->post['npwd']);
        $npwd2 = addslashes($this->post['npwd2']);
        if ($npwd != $npwd2) {
            err('新密码与确认密码不一致');
        }
        $memberModel = D('members');
        $user = $memberModel->normalMember($this->uid, 'phone,salt,pwd');
        //判断短信验证码
        $codeModel = D('Validcode');
        if($codeModel -> expires($user['phone'],$this->post['vcode'],Constants::SMS_UPDLOGIN_CODE)){
            err('短信验证码错误或已失效');
        } 
        list($pwd,$salt) = md5password($npwd);
        $data = array('pwd' => $pwd,'salt' => $salt);
        if($memberModel->modify($this->uid, $data)){
            succ("操作成功");
        }else{
            err("操作失败");
        }
    }
    /**
     * 修改支付密码
     * @param  str $opwd 旧密码
     * @param  str $npwd 新密码
     */
    public function edipaytpwd(){
        require_check_api("npwd,npwd2,vcode", $this->post);
        $npwd = addslashes($this->post['npwd']);
        $npwd2 = addslashes($this->post['npwd2']);
        if ($npwd != $npwd2) {
            err('新密码与确认密码不一致');
        }
        $memberModel = D('members');
        $user = $memberModel -> normalMember($this->uid, 'pay_salt, pay_pwd,phone');
        //判断短信验证码
        $codeModel = D('Validcode');
        if($codeModel -> expires($user['phone'],$this->post['vcode'],Constants::SMS_UPDPAY_CODE)){
            err('短信验证码错误或已失效');
        }
        list($pay_pwd,$pay_salt) = md5password($npwd);
        $data = array('pay_pwd' => $pay_pwd, 'pay_salt' => $pay_salt);
        if($memberModel->modify($this->uid, $data)){
            succ("操作成功");
        }else{
            err("操作失败");
        }
    }
    public function resetpayPwd(){
        require_check_post("smscode/s,npwd/s,npwd2/s");
        $smscode   = addslashes(I('smscode/s'));
        $npwd = addslashes(I('npwd/s'));
        $npwd2 = addslashes(I('npwd2/s'));
        if ($npwd != $npwd2) {
            err('新密码与确认密码不一致');
        }
        $memberModel = D('members');
        $user = $memberModel -> normalMember($this->uid, 'phone');
        //验证码检测
        $codeModel = D('Validcode');
        if($codeModel -> expires($user['phone'],$smscode,Constants::SMS_RESETPAYPWD_CODE)){
            err('短信验证码错误或已失效');
        }
        list($pay_pwd,$pay_salt) = md5password($npwd);
        $data = array('pay_pwd' => $pay_pwd, 'pay_salt' => $pay_salt);
        if($memberModel->modify($this->uid, $data)){
            succ("操作成功");
        }else{
            err("操作失败");
        }
    }
    /**
     * [myMail 我的邮件]
     * @return JSON
     */
    public function myMail(){
        $page =intval($this->post['p']);
        $page= empty($page)? 1:$page;
        $perpage = 10;
        $map_mail = array(
            'uid' => $this->uid
        );
        $mail_list = M('message')->field('title,describe,ctime')
                    ->where($map_mail)
                    ->order('ctime DESC')
                    -> page($page)
                    ->limit($perpage)
                    ->select();
        if (!$mail_list) {
            $mail_list = array();
        }
        foreach ($mail_list as &$mail) {
            $mail['ctime'] = date('Y-m-d', $mail['ctime']);
        }
        $result = array(
            'list' => $mail_list
        );
        succ($this->output($result));
       // succ($result);
    }

}

