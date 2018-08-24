<?php
/**
 * 验证码/已登陆
 */
namespace Api\Controller;
use Think\Log;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;
use Common\Lib\RestSms;

class VcodeLoginedController extends BaseController{

    /**
     * 申请提现验证码
     * @return [type] [description]
     */
    public function applycsh_sms(){
        $this->_send_sms(Constants::SMS_APPLYCASH_CODE, Constants::SMSTEMPLATE_APPLYCASH_VCODE);
    }
    /**
     * 重置交易密码
     * @return [type] [description]
     */
    public function payPwd_sms(){
        $this->_send_sms(Constants::SMS_RESETPAYPWD_CODE, Constants::SMSTEMPLATE_FINDPWD_VCODE);
    }

    /**
     * 发送短信验证码
     * @param  int $range  短信类型
     * @param  int $tempid 短信模板ID
     * @return [type]         [description]
     */
    private function _send_sms($range, $tempid){
        extract(require_check_post("validcode"));
        $model = D('members');
        $user = $model->field('phone')->where(array('id' => $this->uid))->find();
        $mobile = $user['phone'];
        //校验图形验证码
        if(!valid_check($validcode)){
            err('图形验证码错误或已失效,请刷新重试111');
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
        
        $datas = array($code, '5分钟');
        $rest  = new RestSms();
        $result = $rest->sendTemplateSMS($mobile, $datas, $tempid);
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
                err("发送频繁");
            }
        }
    }

    /**
     * 图形验证码
     * @param  string $signcode 机器码
     * @return [type]           [description]
     */
    public function image(){
        valid_image();
    }

    
    

}