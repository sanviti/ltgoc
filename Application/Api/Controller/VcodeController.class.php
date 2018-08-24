<?php
/**
 * 验证码
 */
namespace Api\Controller;
use Think\Log;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;
use Common\Lib\RestSms;
class VcodeController extends Controller{

    /**
     * 注册验证码
     * @return [type] [description]
     */
    public function reg_sms(){
        $mobile = I('mobile', '', 'addslashes');
        $is_reg = D('Members')->is_reg($mobile);
        if($is_reg){
            err("发送失败,手机号已注册");
        }
        $this->_send_sms(Constants::SMS_REGISTER_CODE, Constants::SMSTEMPLATE_REG_VCODE);
    }

    /**
     * 找回密码验证码
     * @return [type] [description]
     */
    public function findpwd_sms(){
        $mobile = I('mobile', '', 'addslashes');
        $model = D('members');
        if(!$model->is_reg($mobile)){
            err("该手机号还未注册！");
        }
        $this->_send_sms(Constants::SMS_FINDPWD_CODE, Constants::SMSTEMPLATE_FINDPWD_VCODE);
    }

    /**
     * 发送短信验证码
     * @return [type] [description]
     */
    private function _send_sms($range, $tempid){
        extract(require_check_post("mobile/s,validcode"));
        //校验图形验证码
        if(!valid_check($validcode)){
            err('图形验证码错误或已失效,请刷新重试');
        }

        $this->_check_mobile($mobile, $range);
        $model = D('Validcode');
        $code = create_code(4);
        $ip = ip2long(ClientIp());
        $expires_in = NOW_TIME + Constants::SMS_EXPIRE_TIME; //过期时间(5分钟)

        $data = array(
            "mobile" => $mobile,
            "code"   =>  $code,
            "range"  =>  $range,
            "expires_in" => $expires_in,
            'ip' => $ip
        );
        
        $rest = new RestSms();
        $datas = array($code,'5');
        $result = $rest->sendTemplateSMS($mobile,$datas,$tempid);
        if($model->add($data)){
            succ("发送成功", array('out_time' => Constants::SMS_INTERVAL_TIME));
        }else{
            err("发送失败");
        }
    }

    /**
     * 验证手机号
     * @param  [type] $mobile [description]
     * @param  [type] $range  [description]
     * @return [type]         [description]
     */
    private function _check_mobile($mobile, $range){
        if(!isPhone($mobile)){
            err("请输入有效手机号");
        }
        $model = D('Validcode');
        //同一手机号限制80s内不允许发送
        $last = $model->lastSMS($mobile, $range);
        if($last) {
            if( ( NOW_TIME - ($last['expires_in'] - Constants::SMS_EXPIRE_TIME) ) < Constants::SMS_INTERVAL_TIME ) {
                err("发送频繁,稍后重试");
            }
        }
    }


    /**
     * 提现验证码
     * @return [type] [description]
     */
    public function apply_sms(){
        $mobile = I('mobile', '', 'addslashes');
        $validcode = I('validcode', '', 'addslashes');
        $i = I('mobile', '', 'addslashes');
        $is_reg = D('Members')->is_reg($mobile);

        $this->_send_sms(Constants::SMS_APPLYCASH_CODE, Constants::SMSTEMPLATE_REG_VCODE);
    }
    
    /**
     * 修改登录密码验证码
     * @return [type] [description]
     */
    public function upd_loginpwd(){
        $mobile = I('mobile', '', 'addslashes');
        $validcode = I('validcode', '', 'addslashes');
        $is_reg = D('Members')->is_reg($mobile);
    
        $this->_send_sms(Constants::SMS_UPDLOGIN_CODE, Constants::SMSTEMPLATE_REG_VCODE);
    }
    
    /**
     * 修改交易密码验证码
     * @return [type] [description]
     */
    public function upd_paypwd(){
        $mobile = I('mobile', '', 'addslashes');
        $validcode = I('validcode', '', 'addslashes');
        $is_reg = D('Members')->is_reg($mobile);
    
        $this->_send_sms(Constants::SMS_UPDPAY_CODE, Constants::SMSTEMPLATE_REG_VCODE);
    }
    
    /**
     * 生成图形验证码
     */
    public function image(){
        valid_image();
    }

}