<?php
/**
 * 手机端用户模型
 */
namespace Api\Model;
use Think\Model;
use Common\Lib\Constants;
class MembersModel extends Model{
    protected $tableName = 'members';
    protected $userToken = '';
    private $retain = array(8,88,888,88888,888888,1,9,99,999,999999,99999,6,66,666,66666,666666,6888,100,110);
    public function register($data){
        //生成新的密码和salt
        list($pwd, $salt) = md5password($data['pwd']);
        $data['pwd'] = $pwd;
        $data['salt'] = $salt;

        //交易密码
        list($pay_pwd, $pay_salt) = md5password($data['pay_pwd']);
        $data['pay_pwd'] = $pay_pwd;
        $data['pay_salt'] = $pay_salt;


        //推荐关系

        $data['leadid'] = $data['leadid'] > 0 ? $data['leadid'] : 1;
        if($data['leadid']){
            $lead = $this->profiles($data['leadid'],'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign,path');
            $data['path'] = empty($lead['path']) ? '' : $lead['path'];
        }else{
            $data['path'] = '';
        }
        $data['reg_time'] = NOW_TIME;
        $this->startTrans();
        $result = $userid = $this->add($data);
        //保留账号
        if(in_array( $userid, $this->retain )){
            $this->where(array('id' => $userid))->save(array('phone'=> ''));
            $result = $result && $userid = $this->add($data);
        }

        $upd['token']  = $this->_create_token();
        $upd['recom_token']  = $this->_create_recom_token();
        $upd['userid'] = 'U'.sprintf('%06d',$userid);
        $upd['path'] = trim($data['path'] . ',' . $userid , ',');
        //创建sign
        $defInfo= array(
            'token'=> $upd['token'] ,
            'goc'=>'0.000',
            'goc_lock'=>'0.000',
            'usdc'=>'0.000',
            'usdc_lock'=>'0.000',
            'balance'=>'0.000',
            'balance_lock'=>'0.000',
            'chain_score'=>'0.000',
            'share_score'=>'0.000',
            );
        $sign = $this->_sign($defInfo);
        $upd['sign'] = $sign;
        //增加团队人数
        $result = $result && $this->upd_team_people_num($upd['path'], 1, 'inc');
        //更新推荐人直推人数
        if(!empty($lead)){
            $result = $result && $this->where(array('id' => $data['leadid']))->setInc('children_num', 1);
//            $result = $result && $this->where(array('id' => $data['leadid']))->setInc('recomend_reward', Constants::REGISTER_RECOMMEND);
//            $result = $result && $this->changeShareScore($lead,Constants::REGISTER_RECOMMEND,'in');
//            $newShareScore=bcadd($lead['share_score'],Constants::REGISTER_RECOMMEND,3);
//            $log=array(
//                'uid' => $data['leadid'],
//                'changeform' => 'in',
//                'subtype' => 9,
//                'money' =>Constants::REGISTER_RECOMMEND,
//                'ctime' => time(),
//                'describes' => '推荐注册',
//                'balance' => $newShareScore,
//                'money_type' =>4, //乐享积分余额
//                'extends'=>$userid
//            );
//            $LogModel=D('MembersLog');
//            $result = $result &&  $LogModel->adds($log);
        }
        $result = $result && $this->where(array('id' => $userid))->save($upd);
        //添加会员副表
        $members_dep = array(
            'uid' => $userid
        );
        $result = $result &&  M('members_dep')->add($members_dep);
        if($result){
            $this->commit();
            return true;
        }else{
            $this->rollback();
            return false;
        }
    }


    /**
     * 登录
     * @param  [type] $phone    [description]
     * @param  [type] $password [description]
     * @return [type]           [description]
     */
    public function checklogin($phone, $password){
        $code = 0;
        $condi['phone'] = $phone;
        $field = "id, pwd, is_lock, salt, pay_pwd, token";
        $member = $this->field($field)->where($condi)->find();
        $result = array();
        //用户名不存在
        if($member == NULL){
            $code = -1;
        }
        //密码错误
        elseif(md5password($password,$member['salt']) != $member['pwd']){
            $code = -2;
        }
        //账号锁定
        elseif($member['is_lock'] != 0){
            $code = -3;
        }
        //登录成功
        elseif($code == 0){
            $this->where($condi)->save(array('login_time' => NOW_TIME, 'login_ip' => ClientIp()));
            $result['info'] = array('token' => $member['token'], 'id' => $member['id']);
        }
        
        $result['code'] = $code;
        return $result;
    }

    /**
     * 推荐奖励
     * @return [type] [description]
     */
    public function recReward($member, $givenum){
        $logModel = D("MembersLog");
        $path = explode(',', $member['path']);
        $path = array_reverse($path);
        $result = true;
        for($i = 1; $i <= 1; $i++){
            if($path[$i]){
                $rate = get_reward_rate($i);
                $num  = new_bcmul($givenum, $rate);
                $leader = $this->lock(true)->where(array('id' => $path[$i]))->find();
                if($leader){
                    //推荐人算力 小于 被推荐人 推荐奖 50%
                    if($leader['power'] < $member['power']){
                        $num = new_bcmul($num, 0.5);
                    }
                    $newBalan = new_bcadd($leader['score'], $num);
                    $result = $result && $this->score_inc($leader, $num);
                    $log = array('uid' => $leader['id'], 'changeform' => 'in', 'subtype' => 5, 'num' => $num, 'ctime' => NOW_TIME, 'balance' => $newBalan, 'extends' => $member['id']);
                    $result = $result && $logModel->add($log);
                }
            }else{
                break;
            }
        }
        return $result;
    }

    /**
     * 检查支付密码
     * @param  [type] $uid    [description]
     * @param  [type] $paypwd [description]
     * @return [type]         [description]
     */
    public function checkpaypwd($uid, $paypwd){
        $member = $this->field('pay_pwd,pay_salt')->where(array('id' => $uid))->find();
        if(md5password($paypwd,$member['pay_salt']) != $member['pay_pwd']){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 创建token
     * @return [type] [description]
     */
    private function _create_token(){
        $status = 0;
        while($status == 0){
            $token = $this->_str_rand();
            $count = $this->where(array('token' => $token))->count();
            if($count == 0){
                $status = 1;
            }
        }
        return $token;
    }

    /*
     * 生成随机字符串
     * @param int $length 生成随机字符串的长度
     * @param string $char 组成随机字符串的字符串
     * @return string $string 生成的随机字符串
     */
    private function _str_rand($length = 32, $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        if(!is_int($length) || $length < 0) {
            return false;
        }
        $string = '';
        for($i = $length; $i > 0; $i--) {
            $string .= $char[mt_rand(0, strlen($char) - 1)];
        }
        return '0x'. substr(sha1($string), 0, 40);
    }



    /**
     * 创建推荐token
     * @return [type] [description]
     */
    private function _create_recom_token(){
        $status = 0;
        while($status == 0){
            $token = $this->_recom_rand();
            $count = $this->where(array('recom_token' => $token))->count();
            if($count == 0){
                $status = 1;
            }
        }
        return $token;
    }
    /*
     * 生成随机推荐码
     * @param int $length 生成随机字符串的长度
     * @param string $char 组成随机字符串的字符串
     * @return string $string 生成的随机字符串
     */
    private function _recom_rand($length = 8, $char = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        if(!is_int($length) || $length < 0) {
            return false;
        }
        $string = '';
        for($i = $length; $i > 0; $i--) {
            $string .= $char[mt_rand(0, strlen($char) - 1)];
        }
        return  $string;
    }

    /**
     * 账户注册检测
     * @param  string  $phone 手机号
     * @return boolean        [description]
     */
    public function is_reg($phone){
        $condi = array('phone' => $phone);
        $count = $this->where($condi)->count();
        return $count ? true : false;
    }

    /**
     * 用户信息 id
     * @param  [type] $id [description]
     * @param  string $field [description]
     * @return [type]        [description]
     */
    public function profiles($uid, $field = 'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign'){
        return $this->field($field)->where(array('id' => $uid))->find();
    }

    /**
     * 用户账户 id
     * @param  [type] $id [description]
     * @param  string $field [description]
     * @return [type]        [description]
     */
    public function account($uid, $field = 'id,token,goc,goc_lock,usdc,usdc_lock,balance,balance_lock,chain_score,share_score,sign'){
        return $this->lock(true)->field($field)->where(array('id' => $uid))->find();
    }
    /**
     * base获取用户信息 id
     * @param  [type] $id [description]
     * @param  string $field [description]
     * @return [type]        [description]
     */
    public function profilesToken($token, $field = 'id,is_lock'){
        return $this->field($field)->where(array('token' => $token))->find();
    }
    

    /**
     * 更新用户等级
     * @param  [type] $uid   [description]
     * @param  [type] $level [description]
     * @return [type]        [description]
     */
    public function memberLevel($uid, $level){
         return $this->where(array('id' => $uid))->save(array('vip_level'=>$level));
    }

    /**
     * 查找正常用户
     * @param $where 搜索条件
     */
    public function normalMember($uid, $fields = '*'){
        $condi['id'] = $uid;
        $condi['is_lock'] = 0;
        $condi['is_freeze'] = 0;
        $res = $this->field($fields)->where($condi)->find();
        return $res;
    }

    /**
     * 修改用户数据 token
     * @param  [type] $token [description]
     * @param  [type] $data  [description]
     * @return [type]        [description]
     */
    public function modify($uid, $data){
        $condi = array('id' => $uid);
        return $this->where($condi)->save($data);
    }

    /**
     * [member_dep 获取会员副表信息]
     * @param  integer $uid    会员ID
     * @param  string  $fields 读取字段
     * @return array           会员副表信息
     */
    public function member_dep($uid, $fields = '*'){
        $map = array('uid' => $uid);
        $member_depInfo = M('members_dep')->where($map)->find();
        return $member_depInfo;
    }

    /**
     * [modify_memberdep 修改会员副表]
     * @param  integer $uid  会员ID
     * @param  array   $data [description]
     * @return [type]        [description]
     */
    public function modify_memberdep($uid, $data = array()){
        $condi = array('uid' => $uid);
        return M('members_dep')->where($condi)->save($data);
    }

    /**
     * 更新团队人数
     * @param  [type] $path [description]
     * @param  [type] $num  [description]
     * @param  string $act  [description]
     * @return [type]       [description]
     */
    public function upd_team_people_num($path, $num, $act = 'inc'){
        if($path){
            $this->lock(true)->where(array('id' => array('in', $path)))->select();
            if($act == 'inc'){
                $this->where(array('id' => array('in', $path)))->setInc('team_people_num', $num);               
            }else{
                $this->where(array('id' => array('in', $path)))->setDec('team_people_num', $num);    
            }
        }
        return true;
    }




    /**
     * 签名
     * @return [type] [description]
     */
    public function _sign($data){
        return sha1(Constants::SIGN_SALT . $data['token'] . $data['goc'] . $data['goc_lock'] . $data['usdc'] . $data['usdc_lock'] . $data['balance'] . $data['balance_lock'] . $data['chain_score'] . $data['share_score']);
    }

    /**
     * 验证签名
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function _verify_sign($data){
        // dump($data);
        // dump($data['sign']);
        $sign = sha1(Constants::SIGN_SALT . $data['token'] . $data['goc'] . $data['goc_lock'] . $data['usdc'] . $data['usdc_lock'] . $data['balance'] . $data['balance_lock'] . $data['chain_score'] . $data['share_score']);
        // dump($sign);

        return $sign == $data['sign'] ? true : false;
    }



    #提示：以下操作需要验证签名
    
    /**
     * 解冻USDC
     */
    public function unlockUsdc($user, $num){
        if(!$this->_verify_sign($user)) return false;
        $user['usdc'] =  new_bcadd($user['usdc'], $num);
        $user['usdc_lock'] =  new_bcsub($user['usdc_lock'], $num);
        $upd['sign'] = $this->_sign($user);
        $upd['usdc']      = $user['usdc'];
        $upd['usdc_lock'] = $user['usdc_lock'];
        return $this->where(array('id' => $user['id']))->save($upd);
    }
    
    /**
     * 解冻GOC
     */
    public function unlockGoc($user, $num){
        if(!$this->_verify_sign($user)) return false;
        $user['goc'] =  new_bcadd($user['goc'], $num);
        $user['goc_lock'] =  new_bcsub($user['goc_lock'], $num);        
        $upd['sign'] = $this->_sign($user);
        $upd['goc']      = $user['goc'];
        $upd['goc_lock'] = $user['goc_lock'];
        return $this->where(array('id' => $user['id']))->save($upd);
    }

    /**
     * 買入goc
     */
    public function buyGoc($user, $goc, $usdc){
        if(!$this->_verify_sign($user)) return false;
        $user['goc']          = new_bcadd($user['goc'], $goc);
        $user['usdc_lock']    = new_bcsub($user['usdc_lock'], $usdc);
        $user['sign']         = $this->_sign($user);
        $upd['goc']           = $user['goc'];
        $upd['usdc_lock']     = $user['usdc_lock'];
        $upd['sign']          = $user['sign'];
        return $this->where(array('id' => $user['id']))->save($upd);
    }
    /**
     * 賣出goc
     */
    public function sellGoc($user, $goc, $balance){
        if(!$this->_verify_sign($user)) return false;
        $user['goc_lock']     = new_bcsub($user['goc_lock'], $goc);
        $user['balance']      = new_bcadd($user['balance'], $balance);
        $user['sign']         = $this->_sign($user);
        $upd['goc_lock']      = $user['goc_lock'];
        $upd['balance']       = $user['balance'];
        $upd['sign']          = $user['sign'];
        return $this->where(array('id' => $user['id']))->save($upd);
    }

    /**
     * 强制賣出goc
     */
    public function compelSellGoc($user, $goc, $balance){
        if(!$this->_verify_sign($user)) return false;
        $user['goc']          = new_bcsub($user['goc'], $goc);
        $user['balance']      = new_bcadd($user['balance'], $balance);
        $user['sign']         = $this->_sign($user);
        $upd['goc']           = $user['goc'];
        $upd['balance']       = $user['balance'];
        $upd['sign']          = $user['sign'];
        return $this->where(array('id' => $user['id']))->save($upd);
    }

    /**
     * 前台提现账户余额操作
     */
    public function ApplyBalance($upd, $num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['balance']      = new_bcsub($upd['balance'],$num);
        $upd['balance_lock'] = new_bcadd($upd['balance_lock'],$num);
        $upd['sign']         = $this->_sign($upd);
        $members['balance']  = $upd['balance'];
        $members['balance_lock'] = $upd['balance_lock'];
        $members['sign']     = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }
    
    /**
     * 后台拒绝提现账户余额操作
     */
    public function AdminApplyBalance($upd, $num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['balance']      = new_bcadd($upd['balance'],$num);
        $upd['balance_lock'] = new_bcsub($upd['balance_lock'],$num);
        $upd['sign']         = $this->_sign($upd);
        $members['balance']  = $upd['balance'];
        $members['balance_lock'] = $upd['balance_lock'];
        $members['sign']     = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户余额操作
     */
    public function changeBalance($upd, $num, $action = 'in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action == "in"){
            $upd['balance'] = new_bcadd($upd['balance'], $num);
        }else{
            $upd['balance'] = new_bcsub($upd['balance'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['balance'] = $upd['balance'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }
    
    /**
     * 账户锁定余额操作
     */
    public function changeBalanceLock($upd, $num, $action = 'in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action == "in"){
            $upd['balance_lock'] = new_bcadd($upd['balance_lock'], $num);
        }else{
            $upd['balance_lock'] = new_bcsub($upd['balance_lock'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['balance_lock'] = $upd['balance_lock'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }



    /**
     * 账户USDC操作
     */
    public function changeUsdc($upd, $num, $action = 'in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action == "in"){
            $upd['usdc'] =  new_bcadd($upd['usdc'], $num);
        }else{
            $upd['usdc'] =  new_bcsub($upd['usdc'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['usdc'] = $upd['usdc'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
    * 账户锁定usdc操作
    */
    public function changeUsdcLock($upd, $num, $action = 'in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action == "in"){
            $upd['usdc_lock'] = new_bcadd($upd['usdc_lock'], $num);
        }else{
            $upd['usdc_lock'] = new_bcsub($upd['usdc_lock'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['usdc_lock'] = $upd['usdc_lock'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户usdc and 锁定操作
     */
    public function changeUsdcAndlock($upd, $num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['usdc'] = new_bcsub($upd['usdc'], $num, 3);
        $upd['usdc_lock'] = new_bcadd($upd['usdc_lock'], $num, 3);
        $upd['sign'] = $this->_sign($upd);
        $members['usdc'] = $upd['usdc'];
        $members['usdc_lock'] = $upd['usdc_lock'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }


    /**
     * 账户GOC操作
     */
    public function changeGoc($upd, $num, $action = 'in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action == "in"){
            $upd['goc'] = new_bcadd($upd['goc'], $num, 3);
        }else{
            $upd['goc'] = new_bcsub($upd['goc'], $num, 3);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['goc'] = $upd['goc'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户锁定GOC操作
     */
    public function changeGocLock($upd, $num, $action = 'in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action == "in"){
            $upd['goc_lock'] = new_bcadd($upd['goc_lock'], $num, 3);
        }else{
            $upd['goc_lock'] = new_bcsub($upd['goc_lock'], $num, 3);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['goc_lock'] = $upd['goc_lock'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户goc and 锁定操作
     */
    public function changeGocAndlock($upd, $num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['goc'] = new_bcsub($upd['goc'], $num, 3);
        $upd['goc_lock'] = new_bcadd($upd['goc_lock'], $num, 3);
        $upd['sign'] = $this->_sign($upd);
        $members['goc'] = $upd['goc'];
        $members['goc_lock'] = $upd['goc_lock'];
        $members['sign'] = $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户chainsScore操作score
     */
    public function changeChainScore($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['chain_score'] = new_bcadd($upd['chain_score'], $num, 3);
        }else{
            $upd['chain_score'] = new_bcsub($upd['chain_score'], $num, 3);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['chain_score']=$upd['chain_score'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }


    /**
     * 账户chainsShareScore操作Share_score
     */
    public function changeShareScore($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['share_score'] = new_bcadd($upd['share_score'], $num, 3);
        }else{
            $upd['share_score'] = new_bcsub($upd['share_score'], $num, 3);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['share_score']=$upd['share_score'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

}