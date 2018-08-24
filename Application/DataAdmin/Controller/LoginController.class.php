<?php
namespace DataAdmin\Controller;
use Think\Controller;
use Common\Lib\Constants;
class LoginController extends Controller {

    public function index(){
        if(session('dataAdmin')){
            header("Location: " . U('Index/index'));
            die;
        }
        $url = I('url');
        $this->assign('url', $url);
        $this->display();
    }

    public function dologin(){
        $username = I('post.user', '', 'trim');
        $password = I('post.password', '', 'trim,md5');
        $vcode = I('post.vcode', '', 'trim');
        $verify = new \Think\Verify();
        if(!$verify->check($vcode)){
            $this->error('验证码错误');
        }
        if(empty($username)||empty($password)){
            $this->error('请输入帐号密码');
        }

        $ip = get_clientIP();
        $map = array();
        $map['user'] = $username;
        $map['state'] = 1;
        $admin = D('Admin');
        $adminInfo = $admin->field('password,salt,id,userimg,note,user,username,role')->where($map)->find();

        if($adminInfo){
            // $this->ajaxReturn(array('11'=>md5($password.Constants::PUB_SALT.$adminInfo['salt'])));
            if($adminInfo['password'] != md5($password.Constants::PUB_SALT.$adminInfo['salt'])){
                //登录日志
                $logs = array(
                    'logtype' => 1, 'subtype' => 'login_fail', 'adminname' => $username,
                    'ip' => $ip, 'ctime' => NOW_TIME,  'memo' => '密码输入错误：尝试密码 *'. I('post.password') .'*',

                );
                
                M('admin_act_logs')->add($logs);
                $this->error('帐号密码不正确');
            }

            $login_token = md5($ip.time());
            session('dataAdmin.id', $adminInfo['id']);
            session('dataAdmin.user', $adminInfo['user']);
            session('dataAdmin.username', $adminInfo['username']);
            session('dataAdmin.note', $adminInfo['note']);
            session('dataAdmin.last_loginip', $ip);
            session('dataAdmin.last_logintime',date('Y-m-d H:i:s'));
            session('dataAdmin.login_token', $login_token);
            session('dataAdmin.role', $adminInfo['role']);
            $data = array();
            $data['last_logintime'] = time();
            $data['last_loginip']   = $ip;
            $admin->where(array('id'=>$adminInfo['id']))->save($data);

            //登录日志
            $logs = array(
                'logtype' => 1, 'subtype' => 'login_success', 'adminname' => $username,
                'ip' => $ip, 'ctime' => NOW_TIME,  'memo' => '登录成功', 'adminid' => $adminInfo['id']
            );
            D('ActionLog')->add($logs);

            //更新login_token缓存
            F('login_token_'.$adminInfo['id'], $login_token);
            //清除掉权限缓存
            S('auths_'.$adminInfo['id'], null);

            $url = I('url','','trim,base64_decode');
            $url = $url ? $url : U('Index/index');
            $this->success('登录成功',$url);

        }else{
            $this->error('帐号不存在或者被禁用');
        }
    }
    
    public function loginout(){
        S('auths_'.session('dataAdmin.id'), null);
        session('dataAdmin',null);
        $this->success('退出成功',U('Login/index'));
    }

    public function vcode(){
        $config =    array(
            'fontSize'    =>    25,    // 验证码字体大小
            'length'      =>    4,     // 验证码位数
            'useNoise'    =>    true, // 关闭验证码杂点
            'imageW'      =>    356,
            'imageH'      =>    50,
            'bg'          =>    array(248,248,248),
            'useZh'       =>    false,
            'useCurve'    =>    true,
        );
        $Verify = new \Think\Verify($config);
        $Verify->codeSet = '0123456789'; 
        $Verify->entry();
    }

}