<?php
use Common\Lib\Constants;

	/**
	 * 生成订单
	 * @return [type] [description]
	 */
	function getOrderSn(){
		$year_code = array('A','B','C','D','E','F','G','H','I','J');
		return $year_code[intval(date('Y'))-2010].strtoupper(dechex(date('m'))).date('d').substr(time(),-5).substr(microtime(),2,5).sprintf('d',rand(0,99));
	}

	/**
	 * 返回redis对象
	 * @return [type] [description]
	 */
	function ConnRedis(){
	    $redis = new \Redis();
	    $redis -> connect(C('REDIS_HOST'),C('REDIS_PORT'));
	    $redis -> auth(C('REDIS_AUTH'));
	    return $redis;
	}
	/**
	 * 生成订单号
	 * @param  string $prefix 前缀
	 * @return [type]         [description]
	 */
	function orderSN($prefix = ''){
		$charid = strtoupper(md5(uniqid(mt_rand(), true)));
		$uuid = $prefix.substr($charid, 0, 8).substr($charid, 8, 4).substr($charid,12, 4).substr($charid,16, 4).substr($charid,20,12);
		return $uuid;
	}
	/**
	 * 生成交易中心的订单号
	 * @param  string $prefix 前缀
	 * @return [type]         [description]
	 */
	function tradingOrderSN($prefix = ''){
		$charid = strtoupper(md5(uniqid(mt_rand(), true)));
		$uuid = $prefix.substr($charid, 0, 8).substr($charid, 8, 4).substr($charid,12, 4).substr($charid,16, 4).substr($charid,20,12);
		return $uuid;
	}
	/**
	 * 保留4位小数 不四舍五入
	 * @return [type] [description]
	 */
	function floor3($float){
		return floor($float * 1000) / 1000;
	}

	/**
	 * 交易锁
	 * @param str $key 锁键名
	 */
	function setLock($key, $act = 'add', $time = 0){
		$redis = ConnRedis();
		$time = $time == 0 ? 60 : $time;
		if($act == 'add'){
			$redis -> set($key, 1, $time);
		}else{
			$redis -> del($key);
		}
	}

	
	/**
	 * 检查锁
	 * @param str $key 锁键名
	 */
	function checkLock($key){
		$redis = ConnRedis();
		return $redis -> get($key) ? true : false;
	}

	/**
	 * 计划任务错误次数
	 * @param str $key 锁键名
	 * @param str $act 操作类型 get 获取次数 inc 递增1 reset 重置为0
	 */
	function planFail($key, $act = 'get'){
		$redis = ConnRedis();
		$num = $redis -> get($key);
		switch ($act) {
			case 'inc':
					$num += 1;
					$redis -> set($key, $num);
				break;
			case 'reset':
					$num = 0;
					$redis -> set($key, 0);
				break;
			case 'get':
			default:
					$num = $redis -> get($key);
				break;
		}
		return $num;
	}

	 /**
     * 图形验证码
     * @param  string $signcode 机器码
     * @return [type]           [description]
     */
    function valid_image_session(){
        $sessionid = session_id();
        $redis = ConnRedis();
        $vcode = create_code(4);
        $key  = 'img:'.md5($sessionid);
        $data = $redis -> set( $key , strval($vcode) , Constants::IMAGE_CODE_EXPIRE_TIME);
        getAuthImage($vcode);
    }

    /**
     * 验证图形
     * @param  string $validcode 验证码
     * @return [type]            [description]
     */
    function valid_check_session($validcode){
        $sessionid = session_id();
        $redis = ConnRedis();
        $key = 'img:'.md5($sessionid);
        $val = $redis -> get($key);
        if(strval($val) !== strval($validcode)){
            return false;
        }
        //验证过自动删除
        $redis -> del($key);
        return true;
    }


		/**
		* 图形验证码
		* @param  string $signcode 机器码
		* @return [type]           [description]
		*/
		function valid_image(){
		extract(require_check("signcode/s"));
		$redis = ConnRedis();
		$vcode = create_code(4);
		$key  = 'img:'.md5($signcode);
		$data = $redis -> set( $key , strval($vcode) , Constants::IMAGE_CODE_EXPIRE_TIME);
		getAuthImage($vcode);
		}

	/**
	 * 验证图形
	 * @param  string $validcode 验证码
	 * @return [type]            [description]
	 */
	function valid_check($validcode){
		extract(require_check_post("signcode/s"));
		$redis = ConnRedis();
		$key = 'img:'.md5($signcode);
		$val = $redis -> get($key);
		if(strval($val) !== strval($validcode)){
			err('图形验证码错误或已失效,请刷新重试');
		}
		$redis -> del($key);
		return true;
	}
?>