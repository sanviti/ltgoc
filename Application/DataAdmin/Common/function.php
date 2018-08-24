<?php
/**
 * 获取客户端IP地址
 * @return [type] [description]
 */
function get_clientIP()
{
    if (getenv("HTTP_CLIENT_IP")){
        $ip = getenv("HTTP_CLIENT_IP");
    }
    else if(getenv("HTTP_X_FORWARDED_FOR")){
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    }
    else if(getenv("REMOTE_ADDR")){
        $ip = getenv("REMOTE_ADDR");
    }else{
        $ip = "Unknow";
    }
    return $ip;
}
/**
 * 二位数组去重
 * @param  [type] $arr     
 * @param  [type] $newKey  0 原始键名 1新键名
 * @return [type] array    
 */
function array_unique_re($arr, $newKey = 0){
    $temp = array();
    $key  = 0;
    foreach ($arr as $oldkey => $value) {
        if(!in_array($value, $temp)){
            if($newKey){
                $key = $key == 0 ? 0 : $key++;
            }else{
                $key = $oldkey;
            }
            $temp[$key] = $value;
        }
    }
    return $temp;
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
     * 生成交易中心的订单号
     * @param  string $prefix 前缀
     * @return [type]         [description]
     */
    function tradingOrderSN($prefix = ''){
        $charid = strtoupper(md5(uniqid(mt_rand(), true)));
        $uuid = $prefix.substr($charid, 0, 8).substr($charid, 8, 4).substr($charid,12, 4).substr($charid,16, 4).substr($charid,20,12);
        return $uuid;
    }
?>