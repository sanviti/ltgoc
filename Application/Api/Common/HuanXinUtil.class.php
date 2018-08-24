<?php
namespace Api\Common;
/**
 * Description of HuanXinUtil
 * @author cpx
 */
class HuanXinUtil {
    const client_id = 'YXA69a2J0OBOEeW36Qno2iS74A';
    const client_secret = 'YXA61wQh4pLaXrw1ZZnfiBA86r9vONI';
    const address = 'https://a1.easemob.com/lanbaoapp/dihejiuzhou/';
    private $token;
    private $validtime = 0;
    private $application;
    public function token(){
        if(empty($this->token) || ($this->validtime)<time()){
            $data = array(
                'grant_type'=>'client_credentials',
                'client_id'=> self::client_id,
                'client_secret'=>  self::client_secret,
                );
            $result = self::send($data,self::address.'token');
            $this->token = $result['access_token'];
            //echo $this->token.'tttt';
            $this->validtime = time()+$result['expires_in']-1000;
            $this->application = $result['application'];
        }
    }

    public function register($mobile,$pwd){
        $this->token();
        $data = array(
           'username'=>$mobile,
           'password'=>$pwd,
        );
        $this->send($data, self::address.'users');
    }
    
    public function send($data,$url){
        $params = json_encode($data);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE ); 
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_POST, true ); // post传输数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params );// post传输数据
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->token,)
        );
        $responseText = curl_exec($curl);
        return json_decode($responseText,true);
    }
}
