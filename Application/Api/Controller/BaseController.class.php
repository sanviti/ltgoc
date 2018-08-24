<?php
/**
 * @Author: lxy
 * @Date:   2017-11-28
 */
namespace Api\Controller;
Use Think\Controller;
Use Think\Model;
Use Think\Log;
Use Common\Lib\Safety;
use Common\Lib\Constants;

class BaseController extends Controller{
    protected $userToken;//用户token
    protected $authToken;//登录Token
    protected $post;
    protected $uid;
    protected $clientSys; //客户端系统类型
    public function _initialize(){
        $authToken = $_SERVER['HTTP_AUTHTOKEN']; //登录Token
        $timestamp = $_SERVER['HTTP_TIMESTAMP']; //请求时间
        $signtrue  = $_SERVER['HTTP_SIGNTRUE']; //签名
        $this->clientSys = $_SERVER['HTTP_CLIENT']; //客户端系统 Android ios
        $res = $this->_checkAuthToken($authToken);
        // authtoken失效
        /* if(!$res){
            err(Constants::ERRCODE_AUTHTOKEN_VOID);
        }else{
            $this->authToken = $authToken;
            $this->uid = $res['uid'];
        } 
        //用户锁定
        if($res['is_lock'] !== 0){
            err(Constants::ERRCODE_MEMBER_LOCK);
        } */
        //$this->authToken = $authToken;
        $this->uid = 108;
        $entext = I('post.'. Constants::ENCRYP_TEXT_KEYNAME);
        if($entext){
            $safe = new Safety($authToken);
            //验证签名
            if(!$safe -> auth($entext, $timestamp, $signtrue)){
                err(Constants::ERRCODE_SIGNTRUE_VOID);
            }
            //解析密文
            $this->post = $safe -> decrypt(I(Constants::ENCRYP_TEXT_KEYNAME), $timestamp, $this->clientSys);
            //Log::write(var_export($this->post,true));
        }else{
            if(PHP_OS == 'WINNT'){
                $this->post = I('post.');
            }
        }
        // log::write(var_export($this->post,true),"WARW");
    }

    /**
     * 验证authToken有效性
     * @param  [type] $token [description]
     * @return [type]        [description]
     */
    private function _checkAuthToken($authToken){
        $res   = false;
        $redis = ConnRedis();
        $key  = Constants::AUTH_TOKEN_PREFIX . $authToken;
        $val  = $redis->get($key);
        if($val){
          $redis->set($key, $val, Constants::AUTH_TOKEN_TIME);
          $res = unserialize($val);
        }
        return $res;
    }

    /**
     * 返回密文
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    protected function output($data){
        $safe = new Safety($this->authToken);
        $rand = mt_rand(0,99999999999);
        $entext = $safe -> encrypt($data, $rand, $this->clientSys);
        $signtrue = $safe -> sign($entext, $rand);
        // $this->ajaxReturn($data);
        // exit;
        $outdata = array(
            'signtrue' => $signtrue,
            'entext' => $entext,
            'rand' => $rand,
        );

        return $outdata;
    }

    public function allkeys(){
        $redis = ConnRedis();
        $keys = $redis->keys('*');
        dump($keys);
        echo sha1(substr(md5('9183bb7f73d9fc648f2e1ceff8f3d8ec37fdd251'), 0, 6) . '0');
    }

    public function test_client_entext(){
        $timestamp = time();
        $authToken = $this->authToken;
        $data = array(
            'price' => '1',
            'num' => 100
        );

        $safe = new Safety($authToken);
        $key  = sha1(substr(md5($authToken), 0, 6) . $timestamp);
        $entext = $safe->authcode($data, 'EN', $key);

        echo $timestamp."<br/>";
        echo $entext."<br/>";
        $sign = sha1(substr($authToken, 0, 4) . $timestamp . substr($entext, 0, 6));
        echo $sign."<br/>";

    }
}