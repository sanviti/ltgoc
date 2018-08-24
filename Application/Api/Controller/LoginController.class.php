<?php
/**
 * 用户登录/注册
 */
namespace Api\Controller;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class LoginController extends Controller{

    public function login(){
        require_check_post("mobile/s,password/s,validcode/s");
        $mobile   = addslashes(I('mobile/s'));
        $password = addslashes(I('password/s'));
        $validcode = addslashes(I('validcode/s'));
         if(!valid_check($validcode)){
             err('图形验证码错误或已失效,请刷新重试');
         }

        $member = D("Members");
        $member_dep = M('members_dep');
        $res = $member->checklogin($mobile,$password);

        switch ($res['code']) {
            case 0:
                $info = $res['info'];
                $map = array('uid' => $info['id']);
                $dep = $member_dep->field('auth_token')->where($map)->find();
                if($dep){
                    $authToken = $this->_auth_token($info['token'], $info['id'], $dep['auth_token']);
                    $member_dep->where($map)->save(array('auth_token' => $authToken));
                    succ("登陆成功",array('authtoken' => $authToken));
                }else{
                    err('附加信息创建失败');
                }                
                break;
            case -1:
                err("用户名不存在");
                break;
            case -2:
                err("密码错误");
                break;
            case -3:
                err("账号被禁用");
                break;
            default:
                err("登录失败");
                break;
        }
    }

    /**
     * 退出登录
     * @return [type] [description]
     */
    public function  loginout(){
        extract(require_check_post("authtoken/s"));
        $redis = ConnRedis();
        $redis -> del($authtoken);
        succ('退出成功');
    }

    /**
     * 创建redis session
     * @return [type] [description]
     */
    private function _auth_token($userToken, $uid, $oAuthToken){
        $redis = ConnRedis();
        $prefix = Constants::AUTH_TOKEN_PREFIX;
        $oldKey = $prefix . $oAuthToken;
        //干掉该账号其他token
        if($oAuthToken){
            $redis->delete($oldKey);
        }

        //新的token
        $authToken = sha1(time() . $userToken . Constants::PUB_SALT);
        $newKey =  $prefix . $authToken;
        $val = serialize( array('uid' => $uid, 'is_lock' => 0) );
        $redis -> set($newKey, $val, Constants::AUTH_TOKEN_TIME);
        return $authToken;
    }

    /**
     * 用户注册
     * @param  string $mobile   手机号
     * @param  string $password md5密码
     * @param  int    $lid      推荐人
     * @param  string $truename 真实姓名
     * @param  string $province    省
     * @param  string $city        市
     * @param  string $area        区
     * @param  string $smscode  手机验证码
     * @return [type]           [description]
     */
    public function reg(){
        require_check_post("mobile/s,password/s,pay_password/s,smscode/s");
        $model = D('Members');
        $phone = I('post.mobile/s');

        //手机号
        if($model->is_reg($phone)){
            err("手机号已经注册");
        }
        $smscode = I('smscode/s');
        //短信验证码
        $codeModel = D('Validcode');
        if($codeModel -> expires($phone,$smscode,Constants::SMS_REGISTER_CODE)){
            err('短信验证码错误或已失效');
        }
        $password = I('post.password/s');
        $leadToken=M('members')->where(array('recom_token'=>I('post.lid/s')))->field('id')->find();
        if($leadToken){
            $lid=$leadToken['id'];
        }else{
            err('未找到推荐人。');
        }
        $pay_password = I('post.pay_password/s');

        $data = array(
            'phone' => $phone,
            'pwd' => $password,
            'pay_pwd' => $pay_password,
            'leadid' => $lid
        );
        if($model->register($data)){
            succ('注册成功');
        }else{
            err('注册失败，稍后重试');
        }
    }

     /**
     * 找回密码
     * @return [type] [description]
     */
    public function findPwd(){
        require_check_post("mobile,password,password2,vcode");
        $mobile = I('mobile/s');
        $password = I('password/s');
        $password2 = I('password2/s');
        $vcode = I('vcode/s');
        $model  = D("Members");
        if($password2 != $password){
            err('两次密码不一致');
        }
        //用户名检测
        if(!$model->is_reg($mobile)){
            err("用户不存在");
        }
        //验证码检测
        $codeModel = D('Validcode');
        if($codeModel -> expires($mobile,$vcode,Constants::SMS_FINDPWD_CODE)){
            err('短信验证码错误或已失效');
        }

        //重置密码
        list($pwd, $salt) = md5password($password);
        $data['pwd'] = $pwd;
        $data['salt'] = $salt;
        $result = $model->where(array('phone' => $mobile))->save($data);
        if($result){
            succ('重置密码成功');
        }else{
            err('操作失败');
        }
    }
}