<?php
namespace Api\Common;
/**
 * Description of MsgSendUtil
 * @author cpx
 */
use Think\Log;
class MsgSendUtil {
    private static $username = 'vip-dhtz';
    static $password = 'Dhjz147147';
    private static $address = 'http://222.73.117.156/msg/HttpBatchSendSM';
    //短信发送成功表示
    public static $MSG_SEND_SUCCESS_STATUS = 0;
    
    public static function send($mobile,$txt){
        $data = array(
            'account'=>  self::$username,
            'pswd'=> self::$password,
            'mobile'=>$mobile,
            'msg'=>$txt,
            );
        $postdata = http_build_query($data);
        $options = array(
            'http'=>array(
                'method'=>'POST',
                'header'=>'Content-type:application/x-www-form-urlencoded',
                'content'=>$postdata,
                'timeout'=>15*60,
            ),
        );
        $context = stream_context_create($options);
        $result = file_get_contents(self::$address,false,$context);
        //echo $result;//20150814105830,0
        Log::write("send short message to [$mobile] result:$result");
        $rstag = explode(',',$result);
        return $rstag[1];
    }
}
