<?php
namespace DataAdmin\Controller;
use Think\Controller;
use Common\Lib\Constants;
use DataAdmin\Common\Lib\ActionLog;
class BaseController extends Controller {
	protected $_admin = array();
	protected $_adminid;
	protected $_allow_list = array('pwdEdit', 'userWindowLock', 'userWindowUnlock');

	protected function _initialize() {
		$this -> _admin = session('dataAdmin');
		//检测登录
		$this -> _check_login();

		//窗口锁定
		$this -> _window_lock();

		//权限验证
		$this -> _check_auth();

	}

	/**
	 * 检测登录
	 * @return [type] [description]
	 */
	private function _check_login() {
		//验证过期
		if (empty($this -> _admin)) {
			$this -> error('登录失效请重新登录', U('Login/index', array('url' => base64_encode(U(MODULE_NAME . '/' . CONTROLLER_NAME . '/' . ACTION_NAME, '', '')))));
		}
		//token失效
		$login_token = F('login_token_' . $this -> _admin['id']);
		if ($this->_admin['id'] != 53) {
    		if ($this -> _admin['login_token'] != $login_token) {
    			session('dataAdmin', null);
    			$this -> error('账号在其他设备登录，请重新登录', U('Login/index', array('url' => base64_encode(U(MODULE_NAME . '/' . CONTROLLER_NAME . '/' . ACTION_NAME, '', '')))));
    		}
		}

	}

	/**
	 * 窗口锁定
	 * @return [type] [description]
	 */
	private function _window_lock() {
		if (session('user_window_lock') == 1 && ACTION_NAME != 'userWindowUnlock') {
			if (IS_POST || IS_AJAX) {
				$this -> error('用户界面锁定中');
			} else {
				$this -> assign('user_window_lock', 1);
			}
		}
	}

	/**
	 * 权限验证
	 * @return [type] [description]
	 */
	private function _check_auth() {
		//权限处理
		if ($this -> _admin['role'] != 0) {
			$auth_lists = M("admin") -> where("role=" . $this -> _admin['role']) -> field('auth_list') -> find();
			$this -> _admin['menu_list'] = unserialize($auth_lists['auth_list']);

			$menu_action = strtolower(CONTROLLER_NAME . '/' . ACTION_NAME);
			$menu = D('Treenode') -> order('id asc') -> select();
			$menu_id = 0;

			foreach ($this->_admin['menu_list'] as $val) {
				foreach ($menu as $k => $v) {
					if ($v['id'] == $val) {
						if (strtolower($v['c'] . '/' . $v['a']) == strtolower($menu_action)) {
							$menu_id = (int)$k;
							break;
						}
					}
				}
			}
			if (empty($menu_id) || $menu_id == 0) {
				if ($menu_id == 0) {
					$this -> error('请联系管理员为您添加该模块权限 ' . $menu_action);
				} else {
					$this -> error('很抱歉您没有权限操作模块:' . $menu[$menu_id]['title']);
				}
			}
		}
		$this -> loadMenu();
		$nownav['m'] = strtolower(CONTROLLER_NAME);
		$nownav['a'] = strtolower(ACTION_NAME);
		$this -> assign('nownav', $nownav);
	}

	/**
	 * 获取列表
	 * @return [type] [description]
	 */
	private function _get_auth_list() {
		$auths = array();
		if (S('auths_' . $this -> _admin['id']) === false) {
			$admin = D('Admin') -> field('auth_list') -> where('id = %d', $this -> _admin['id']) -> find();
			trace($admin);
			$authList = implode(',', unserialize($admin['auth_list']));
			if ($authList) {
				$nodes = M('data_treenode', null) -> cache(true, Constants::PUB_CACHE_TIME) -> field("CONCAT(IFNULL(m,''),IFNULL(c,''),IFNULL(a,'')) AS mca") -> where('menu_class = 0 AND id IN(%s)', $authList) -> select();
				foreach ($nodes as $val) {
					$auths[] = strtolower($val['mca']);
				}
				S('auths_' . $this -> _admin['id'], $auths, 60 * 60);
			} else {
				S('auths_' . $this -> _admin['id'], 'null', 60 * 60);
			}
		} else {
			$auths = S('auths_' . $this -> _admin['id']);
		}
		return $auths;
	}

	/* public function loadMenu(){
	 $code = I('code/d');
	 $adminid = $_admin['id'];
	 if($code){

	 }
	 }*/
	private function loadMenu() {
		foreach ($this->_admin['menu_list'] as $v) {
			$node_id[] = $v;
		}
		//超级管理员
		if ($this -> _admin['role'] == 0) {
			$menu = M('treenode') -> where(array("menuflag" => 1)) -> order("id") -> select();
			//            dump($menu);DIE;
			$this -> assign('menu', $menu);
		} else {
			$menu = M('treenode') -> where(array("id" => array('IN', $node_id), "menuflag" => 1)) -> select();
			$this -> assign('menu', $menu);

		}
	}

	//用户窗口临时锁定
	public function userWindowLock() {
		if (IS_AJAX) {
			session('user_window_lock', 1);
			$this -> success();
		}
	}

	//用户窗口解除锁定
	public function userWindowUnlock() {
		if (IS_AJAX) {
			$pwd = I('pwd', '', 'trim,htmlspecialchars');
			$admin = D('Admin');
			$adminInfo = $admin -> field('password,salt') -> where('id = %d', $this -> _admin['id']) -> find();
			if ($adminInfo) {
				if ($adminInfo['password'] == md5(md5($pwd) . Constants::PUB_SALT . $adminInfo['salt'])) {
					session('user_window_lock', 0);
					$this -> success('解锁成功');
				} else {
					$this -> error('密码错误');
				}
			} else {
				$this -> error('解锁失败');
			}
		}
	}

	protected function adminid() {
		return $this -> _admin['id'];
	}

	protected function checkFields($data = array(), $fields = array()) {
		foreach ($data as $k => $val) {
			if (!in_array($k, $fields)) {
				unset($data[$k]);
			}
		}
		return $data;
	}

	/**
	 * 日志写入
	 * @return [type] [description]
	 */
	protected function _write_log($arg = '') {
		$callback = strtolower(CONTROLLER_NAME) . '_' . strtolower(ACTION_NAME);
		$logObj = new ActionLog();
		if (method_exists($logObj, $callback)) {
			$log = $logObj -> $callback($arg);
			$log['logtype'] = 2;
			$log['adminid'] = $this -> _admin['id'];
			$log['adminname'] = $this -> _admin['username'];
			$log['ip'] = get_clientIP();
			$log['ctime'] = NOW_TIME;
			return M('admin_act_logs') -> add($log);
		}
	}

}
