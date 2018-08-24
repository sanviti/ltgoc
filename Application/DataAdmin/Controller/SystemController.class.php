<?php
namespace DataAdmin\Controller;
use Think\Controller;
use Common\Lib\Constants;
class SystemController extends BaseController {
    //角色
    private $createrole_fields = array('name', 'status', 'remark', 'pid');
    private $editrole_fields = array('name', 'status', 'remark');
    //用户
    private $createuser_fields = array('user', 'password', 'username', 'userimg', 'role', 'note', 'state', 'add_time', 'last_logintime', 'last_loginip');
    private $edituser_fields = array('user', 'role', 'username', 'userimg', 'note', 'state', 'update_time');
    //系统消息
    private $createnotice_fields = array('title', 'info ', 'add_time', );
    private $editnotice_fields = array('title', 'info', 'update_time');
    public function index(){
        if(IS_AJAX){
            $this->save('site.php');
        }else{
            $this->display();
        }
    }

    private  function save($filename){	
        if($this->updateConfig(I(),$filename)){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    private function updateConfig($cfg,$filename) {
	    $files=CONF_PATH.$filename;
        if (is_file($files)&&is_writable($files)) {
            $config = require $files;
            $config = array_merge($config, $cfg);
            file_put_contents($files, "<?php \nreturn " . stripslashes(var_export($config, true)) . ";", LOCK_EX);          
            @unlink(RUNTIME_FILE);
            return true;
        } else {
            return false;
        }	
    }

    public function userList(){
        $user = I('user', '', 'trim');
        $username = I('username', '', 'trim');
        $state = I('state');
        
        if($state !== ''){
            $params['state'] = $state;
        }
        $condi = $params;
        if(I('user')){
            $condi['user'] = array('like', '%'.$user.'%');
            $params['user']   = $user;
        }
        if($username){
            $condi['username'] = array('like', '%'.$username.'%');
            $params['username']   = $username;
        }
        $page = IS_POST ? 1 : I('get.p');
        
        $admin = D('Admin');
        $count = $admin -> where($condi) -> count();

        $p = getpage($count, C('PAGE_SIZE'), $params);
        $show = $p -> newshow();
        $list = $admin -> where($condi) -> page($page) -> order('id desc') -> limit(C('PAGE_SIZE')) -> select();
        // dump($list);
        // exit;
        $this -> assign('page', $show);
        $this -> assign('list', $list);
        $this -> display();
    }

    public function userAdd() {
        if (IS_AJAX) {
            $data = $this -> adduserCheck();
            $obj = D('Admin');
            $result = $obj -> add($data);
            if ($result){
                $result = D('Admin') ->where('id = %d', $result) -> save(array('role'=>$result));
            }
            if ($result) {
                
                $this -> success("操作成功", U('System/userList'));
            } else {
                $this -> error('操作失败！');
            }
        }
        $auths = D('TreeNode')->field('id,title,pid,menuflag')->where('menu_class = 0')->select();
        $this -> assign('list', $auths);
        $this -> assign('auths', $auths);
        $this -> display();
    }

    private function addUserCheck() {
        if($err = admin_require_check('username,user,password')) $this->error($err);
        $add_time = time();
        $ip = get_client_ip();
        $data['user'] = trim(I('post.user', '', 'htmlspecialchars'));
        if ( D('Admin')->where("user = '%s'", $data['user'])->find()) {
            $this -> error('用户名已经存在');
        }
        $token = $this->_admin = session('dataAdmin');
        $salt = strtoupper(substr(md5(create_code(4)),0,6));
        $data['password'] = md5(I('password', '', 'trim,md5').Constants::PUB_SALT.$salt);
        $data['salt'] = $salt;
        $data['note'] = trim(I('post.note', '', 'htmlspecialchars'));
        $data['username'] = trim(I('post.username', '', 'htmlspecialchars'));
        $data['userimg'] = trim(I('post.userimg', '', 'htmlspecialchars'));
        $data['auth_list'] = serialize(I('post.rule'));
        $data['status'] = I('state') == 1? 1 : 0;
        $data['add_time'] = $add_time;
        $data['last_logintime'] = $add_time;
        $data['last_loginip'] = $ip;
        $data['update_time'] = 0;
        $data['login_token'] = $token['login_token'];
        
        return $data;
    }
    private function editUserCheck($admin) {
        if($err = admin_require_check('username,user')) $this->error($err);
        $password = I('password', '','trim');
        $ip = get_client_ip();
        $data['user'] = trim(I('post.user', '', 'htmlspecialchars'));
        if ( D('Admin')->where("user = '%s' and id <> %d", $data['user'],$admin['id'])->find()) {
            echo M()->getLastSql();
            $this -> error('用户名已经存在');
        }
        if ($admin['password'] == $password){
            $data['password'] = $password;
        }else{
            $data['password'] = md5(md5($password).Constants::PUB_SALT.$admin['salt']);
        }

        $data['note'] = trim(I('post.note', '', 'htmlspecialchars'));
        $data['username'] = trim(I('post.username', '', 'htmlspecialchars'));
        $data['userimg'] = trim(I('post.userimg', '', 'htmlspecialchars'));
        $role=I('post.rule');
        $roleStr=implode(',',$role);
         $topData=D('TreeNode')->where(array('id'=>array('in',$roleStr)))->field('pid')->select();
        $top=array();
        foreach($topData as $key=> $item){
            if(!in_array($item['pid'],$top)){
                $top[]=$item['pid'];
            }
        }
        $role=array_merge($role,$top);
        $role[]='2';
        $data['auth_list'] = serialize($role);
        $data['status'] = I('state') == 1 ? 1 : 0;
        $data['update_time'] = time();
        return $data;
    }

    public function editUser($id = 0) {
        $id = I('id/d');
        $admin = D('Admin')->where('id = %d', $id)->find();
        if(empty($id) || empty($admin)) $this->error('请选择要编辑的用户');
        if (IS_AJAX) {
            $data = $this -> editUserCheck($admin);
            $result = D('Admin') ->where('id = %d', $id) -> save($data);
            if ($result) {
                $this -> success("操作成功", U('System/userList'));
            } else {
                $this -> error('操作失败！');
            }
        }
        $auths = D('TreeNode')->field('id,title,pid,menuflag')->where('menu_class = 0')->select();
        $ids='#'. implode('#',unserialize($admin['auth_list'])) . '#';
        $this -> assign('list', $auths);
        $this -> assign('auths', $auths);
        $this -> assign('group', $ids);
        $this->assign('admin', $admin);
        $this->display('userAdd');
    }

    // 批量修改用户状态
    public function batchChangeUser(){
        $admin = D('Admin');
        $ids = array_unique(array_filter(I('ids')));
        $state = I('state') ? 1 : 0;
        $result = $admin -> where(array('id' => array('in',$ids))) -> save(array('state' => $state));
        if ($result) {
            $this -> success('修改成功');
        } else {
            $this -> error('修改失败');
        }
    }

    //批量删除用户
    public function batchDelUser() {
        $admin = D('Admin');
        $ids = array_unique(array_filter(I('ids')));
        $result = $admin -> where(array('id' => array('in',$ids))) -> delete();
        if ($result) {
            $this -> success('删除成功');
        } else {
            $this -> error('删除失败');
        }
    }

    //删除用户
    public function delUser() {
        $admin = D('Admin');
        $id = I('get.id/d');
        if(empty($id))exit;
        $result = $admin -> where('id = %d', $id) -> delete();
        if ($result) {
            $this -> success('删除成功', U('System/userList'));
        } else {
            $this -> error('删除失败');
        }
    }

    //修改密码
    public function pwdEdit(){

        if(IS_AJAX){
            $oldPwd = I('oldpwd','','trim');
            $newPwd = I('newpwd','','trim');
            $confirmPwd = I('cpwd','','trim');
            if(strlen($newPwd) < 6) $this->error('密码长度必须大于等于六位');
            if($newPwd != $confirmPwd) $this->error('两次密码不一致');
            $admin = D('Admin');
            $adminInfo = $admin->field('password,salt')->where('id = %d', $this->_admin['id'])->find();
            if($adminInfo['password'] != md5(md5($oldPwd).Constants::PUB_SALT.$adminInfo['salt'])){
                $this->error('原密码不正确');
            }

            $salt = strtoupper(substr(md5(create_code(4)),0,6));
            $data['password'] = md5(md5($newPwd).Constants::PUB_SALT.$salt);
            $data['salt'] = $salt;
            $result = $admin->where('id = %d', $this->_admin['id'])->save($data);
            if($result){
                $this->success('修改成功');
            }else{
                $this->error('修改失败');
            }

        
        }
        $this->display();
    }

    /**
     * 导航管理
     */
    public function nav(){
        if(IS_AJAX){
            $menu = array_filter(I("post.navList"));
            $newmenu = array();
            foreach($menu as $key => $v){
                $arr = array();
                if($v['name'] == "") continue;
                $arr['name'] = $v['name'];
                if($v['sub_button']){
                    foreach($v['sub_button'] as $sub_k => $sub_btn){
                        if($sub_btn['name'] == "") continue;
                        $arr_sub = array();
                        $arr_sub["name"] = $sub_btn['name'];
                        $arr_sub["url"] = $sub_btn['value'];
                        $arr['sub_button'][] = $arr_sub;
                    }

                }else{
                    $arr['url'] = $v['value'];
                }
                array_push($newmenu , $arr);
            }
            if(!count($newmenu)) err('菜单不能为空');
            $data = array('INDEX_MENU'=>$newmenu);
            if(update_config($data, 'index_menu.php')){
                $this->success('保存成功');
            }else{
                $this->error('保存失败');
            }

        }
        $data = C('INDEX_MENU');
        $this->assign('indexMenu',$data);
        $this->display();
    }

    /*
     * 系统公告
     */
    public function indexNotice() {
        $notice = D('notice');
        $count = $notice -> count();
        $p = getpage($count, C('PAGE_SIZE'));
        $show = $p -> show();
        $list = $notice -> page(I('get.p')) -> order('id desc') -> limit(C('PAGE_SIZE')) -> select();
        $this -> assign('page', $show);
        // 赋值分页输出
        $this -> assign('list', $list);
        $this -> display();
    }

    public function addNotice() {
        if (IS_POST) {
            $data = $this -> addCheck();
            $obj = D('notice');
            if ($obj -> add($data)) {
                $this -> success("保存成功", U('System/indexnotice'));
                return;
            } else {
                $this -> error('操作失败！');
                return;
            }
        }
        $this -> display();
    }
    private function addCheck() {
        $param = I('post.');

        $add_time = strtotime(date("Y-m-d H:i:s", time()));
        $data['title'] = trim(I('post.title', '', 'htmlspecialchars'));
        $data['dislog']=$param['dislog'];

//        if ( D('notice') -> getNoticeTitle($data['title'])) {
//            $this -> error('消息已经存在');
//            exit ;
//        }
        if (empty($data['title'])) {
            $this -> error('消息标题不能为空');
        }
        $data['info'] = $_POST['info'];
        if (empty($data['info'])) {
            $this -> error('消息内容不能为空');
        }
        $data['add_time'] = $add_time;
        return $data;
    }

    public function editNotice($id = 0) {
        if ($id = (int)$id) {
            $obj = D('notice');
            if (!$detail = $obj -> find($id)) {
                $this -> error('请选择要编辑的系统消息');
            }
            if (IS_POST) {
                $data=I();
                $data['update_time']=time();
                if ($obj -> save($data)) {
                    $this -> success('操作成功', U('System/indexnotice'));
                } else {
                    $this -> error('操作失败');
                }
            } else {
                $this -> assign('detail', $detail);
                $this -> display();
            }
        } else {
            $this -> error('请选择要编辑的系统消息');
        }
    }

    public function delNotice() {
        $notice = D('notice');
        $id = I('get.id');
        $result = $notice -> where(array("id"=>array("in",$id))) -> delete();
        if ($result) {
            $this -> success('删除成功', U('System/indexNotice'));
        } else {
            $this -> error('删除失败');
        }
    }
    private function editCheck() {
        $update_time = strtotime(date("Y-m-d H:i:s", time()));
        $data['title'] = trim(I('post.title', '', 'htmlspecialchars'));
        if (empty($data['title'])) {
            $this -> error('消息标题不能为空');
        }
        $data['info'] = $_POST['info'];
        if (empty($data['info'])) {
            $this -> error('消息内容不能为空');
        }
        $data['update_time'] = $update_time;
        $data['dialog'] = I('dialog');
        dump($data);die;
        return $data;
    }
}