<?php
use Common\Lib\Constants;

/**
 * JSON 返回失败数据
 */
function err($msg = ""){
    return_json($msg, "FAIL");
}
/**
 * JSON 返回成功数据
 * @param  string $param1    提示信息
 * @param  string $param2    返回数据
 */
function succ($param1 = "", $param2 = ""){
    if(is_array($param1)){
        return_json("", "SUCCESS", $param1);
    }else{
        return_json($param1, "SUCCESS", $param2);
    }
}
/**
 * JSON 返回数据
 * @param  string $msg       提示信息
 * @param  string $code      状态码
 * @param  array $result     返回数据
 */
function return_json($msg = '', $code = "SUCCESS", $result = ""){
    if(empty($msg)) $msg = $code == "SUCCESS" ? "返回成功" : "返回失败";
    $response = array(
        "returnMsg"   => $msg,
        "returnCode"  => $code,
    );
    $result && $response["result"] = $result;
    echo json_encode($response);
    exit();
}

function vip_name($vip_level){
    $lang_name = '';
    switch ($vip_level) {
        case 1:
            $lang_name = '普通会员';
            break;
        case 2:
            $lang_name = '小区';
            break;
        case 3:
            $lang_name = '大区';
            break;
        default:
            $lang_name = '普通会员';
            break;
    }
    return $lang_name;
}

/**
 * 获取推荐奖励比率 - 直推
 * @param  [type] $level [description]
 * @return [type]        [description]
 */
function get_reward_rate($level){
    $rate = 0;
    switch ($level) {
        case '1':
            $rate = 0.1;
            break;
        case '2':
            $rate = 0.05;
            break;
        case '3':
        default:
            $rate = 0.01;
            break;
    }
    return $rate;
}

/**
 * 获取社区推荐奖励比率-充值链通积分
 * @param  [type] $level [description]
 * @return [type]        [description]
 */
function get_team_rate($level){
    $rate = 0;
    switch ($level) {
        case '2':
            $rate = 0.02;
            break;
        case '3':
            $rate = 0.035;
            break;
    }
    return $rate;
}

/**
 * 获取社区推荐奖励比率-购买GOC
 * @return [type] [description]
 */
function get_team_rete_buygoc($level){
    $rate = 0;
    switch ($level) {
        case '2':
            $rate = 0.01;
            break;
        case '3':
            $rate = 0.015;
            break;
    }
    return $rate;
}

/**
 * 更新缓存文件
 * 2016-05-31
 * @param  array  $cfg       配置数据
 * @param  string $filename  配置文件名
 * @return boolean           操作结果
 */
function update_config($cfg,$filename) {
    $file = CONF_PATH . $filename;
    if (is_file($file) && is_writable($file)) {
        $config = require $file;
        $config = array_merge($config, $cfg);
        file_put_contents($file, "<?php \nreturn " . stripslashes(var_export($config, true)) . ";", LOCK_EX);
        @unlink(RUNTIME_FILE);
        return true;
    } else {
        return false;
    }
}

/**
 * 生成MD5密码
 * 2016-03-03
 * @param  string $password  密码
 * @param  string $salt      混淆字符串
 * @return array or string
 */
function md5password($password, $salt=''){
    if($salt == ''){
        $salt = strtoupper(substr(md5(create_code(4)),0,8));
        $password = md5(md5($password).$salt);
        return array($password,$salt);
    }else{
        return md5(md5($password).$salt);
    }
}

/**
 * 生成验证码
 *  2016-03-02
 * @param  integer $length    验证码长度
 * @return String             验证码
 */
function create_code($length = 6){
    $code = '';
    for($i = 0; $i < $length; $i++){
        $code .= mt_rand(0,9);
    }
    return $code;
}

/**
 * 必填项检测
 * 2016-03-03
 * @param  String $keys      "key,name,code"
 * @return Array
 */
function require_check($keys){
    $request = array();
    $keys = explode(",",$keys);
    foreach($keys as $key){
        $key = trim($key);
        $value = I($key);
        if(stripos($key,"/")) $key = substr($key,0,stripos($key,"/"));
        if(empty($value)){
            err("缺少必填项:".$key);
        }else{
            $request[$key] = $value;
        }
    }
    return $request;
}

/**
 * 必填项检测
 * 2017-11-27
 * @param  String $keys      "key,name,code"
 * @return Array
 */
function require_check_post($keys){
    $request = array();
    $keys = explode(",",$keys);
    foreach($keys as $key){
        $key = trim($key);
        $value = I("post.".$key);
        if(stripos($key,"/")) $key = substr($key,0,stripos($key,"/"));
        if(empty($value)){
            err("缺少必填项:".$key);
        }else{
            $request[$key] = $value;
        }
    }
    return $request;
}

/**
 * 必填项检测
 * 2017-11-27
 * @param  String $keys      "key,name,code"
 * @return Array
 */
function require_check_api($keys, $data){
    $keys = explode(",",$keys);
    foreach($keys as $key){
        $key = trim($key);
        $value = $data[$key];
        if(empty($value)){
            err("缺少必填项:".$key);
        }
    }
}

/**
 * 必填项检测
 * 2016-03-03
 * @param  String $keys      "key,name,code"
 * @return Array
 */
function admin_require_check($keys){
    $request = array();
    $keys = explode(",",$keys);
    foreach($keys as $key){
        $value = I($key);
        if(stripos($key,"/")) $key = substr($key,0,stripos($key,"/"));
        if(empty($value)){
            return $key."不能为空";
        }
    }
    return false;
}

/**
 * 格式化数字
 */
function input_numb($val, $dec = 2){
    return number_format($val, $dec, '.', '');
}

/**
 * 格式化字符串
 */
function input_str($val){
    return addslashes($val);
}

/**
 * 相乘
 */
function new_bcmul($n1, $n2){
    return bcmul($n1, $n2, 3);
}
/**
 * 相加
 */
function new_bcadd($n1, $n2){
    return bcadd($n1, $n2, 3);
}

/**
 * 相减
 */
function new_bcsub($n1, $n2){
    return bcsub($n1, $n2, 3);
}
/**
 * 相除
 */
function new_bcdiv($n1, $n2){
    return bcdiv($n1, $n2, 3);
}
/**
 * 获取有效期
 * 2016-04-13
 * @param  int $day  天
 */
function expires_in($day){
    return NOW_TIME + 60*60*24*$day;
}

/**
 * 生成订单号
 * 2016-04-13
 * @return [type] [description]
 */
function build_order_no(){
    return date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
}

/**
 * 关键词过滤
 * 2016-04-12
 * @param  str $text      原字符串
 * @param  str $rpStr     替换后的字符
 */
function wordFilter($text,$rpStr="*"){
    $l1 = explode("\r\n",file_get_contents("./Public/wordFilter/baidu_guolv.txt",true));
    $l2 = explode("\r\n",file_get_contents("./Public/wordFilter/baidu_mingan.txt",true));
    $arr = array_merge($l1,$l2);
    foreach($arr as $v){
        $v = trim($v);
        if($v == '') continue;
        $replace = '';
        $length = abslength($v);
        for($i=0;$i<$length;$i++){
            $replace .= $rpStr;
        }

        $text = str_replace($v,$replace,$text);
    }
    return $text;
}

/**
* 统计中文字符串长度
* @param $str 要计算长度的字符串
*/
function abslength($str)
{
    if(empty($str)){
        return 0;
    }
    if(function_exists('mb_strlen')){
        return mb_strlen($str,'utf-8');
    }
    else {
        preg_match_all("/./u", $str, $ar);
        return count($ar[0]);
    }
}

/**
 * 判断点是否多边形内部
 * @param  array  $polygon   多边形经纬数组
 * @param  array  $lnglat    点经纬度数组
 * @return boolean
 * $polygon = array(
        array(
            "lat" => 31.027666666667,
            "lng" => 121.42277777778
        ),
        array(
            "lat" => 31.016361111111,
            "lng" => 121.42797222222
        ),
        array(
            "lat" => 31.023666666667,
            "lng" => 121.45088888889
        ),
        array(
            "lat" => 31.035027777778,
            "lng" => 121.44575
        )
    );
    $lnglat = array(
        "lat" => 31.037666666667,
        "lng" => 121.43277777778
    );
 */
function isPointInPolygon($polygon,$lnglat){
    $count = count($polygon);
    $px = $lnglat['lat']; //纬度
    $py = $lnglat['lng']; //经度

    $flag = FALSE;

    for ($i = 0, $j = $count - 1; $i < $count; $j = $i, $i++) {
        $sy = $polygon[$i]['lng'];
        $sx = $polygon[$i]['lat'];
        $ty = $polygon[$j]['lng'];
        $tx = $polygon[$j]['lat'];

        if ($px == $sx && $py == $sy || $px == $tx && $py == $ty)
            return TRUE;

        if ($sy < $py && $ty >= $py || $sy >= $py && $ty < $py) {
            $x = $sx + ($py - $sy) * ($tx - $sx) / ($ty - $sy);
            if ($x == $px)
                return TRUE;
            if ($x > $px)
                $flag = !$flag;
        }
    }
    return $flag;
}

function getpage($count, $pagesize = 8, $urlParam = '') {
    $p = new Think\Page($count, $pagesize);
    if($urlParam){
        foreach($urlParam as $key=>$val) {
            if(strpos($key, '.')){
                $key = substr($key, strpos($key, '.') + 1);
            }
            $p->parameter[$key]   =   $val;
        }
    }
    $p -> setConfig('header', '<li class="rows">共<b>%TOTAL_ROW%</b>条记录&nbsp;第<b>%NOW_PAGE%</b>页/共<b>%TOTAL_PAGE%</b>页</li>');
    $p -> setConfig('prev', '上一页');
    $p -> setConfig('next', '下一页');
    $p -> setConfig('last', '末页');
    $p -> setConfig('first', '首页');
    $p -> setConfig('theme', '%FIRST%%UP_PAGE%%LINK_PAGE%%DOWN_PAGE%%END%%HEADER%');
    $p -> lastSuffix = false;
    return $p;
}

function getpage2($count, $pagesize = 8, $urlParam = '') {
    $p = new Think\Page($count, $pagesize);
    if($urlParam){
        foreach($urlParam as $key=>$val) {
            if(strpos($key, '.')){
                $key = substr($key, strpos($key, '.') + 1);
            }
            $p->parameter[$key]   =   $val;
        }
    }
    $p -> setConfig('header', '<span class="rows">共<b>%TOTAL_ROW%</b>条记录&nbsp;第<b>%NOW_PAGE%</b>页/共<b>%TOTAL_PAGE%</b>页</span>');
    $p -> setConfig('prev', '上一页');
    $p -> setConfig('next', '下一页');
    $p -> setConfig('last', '末页');
    $p -> setConfig('first', '首页');
    $p -> setConfig('theme', '%FIRST%%UP_PAGE%%LINK_PAGE%%DOWN_PAGE%%END%%HEADER%');
    $p -> lastSuffix = false;
    return $p;
}

function tranTime($time) {
    $rtime = date("Y-m-d H:i", $time);
    $htime = date("H:i", $time);
    $time = time() - $time;
    if ($time < 60) {
        $str = '刚刚';
    } elseif ($time < 60 * 60) {
        $min = floor($time / 60);
        $str = $min . '分钟前';
    } elseif ($time < 60 * 60 * 24) {
        $h = floor($time / (60 * 60));
        $str = $h . '小时前 ' . $htime;
    } elseif ($time < 60 * 60 * 24 * 3) {
        $d = floor($time / (60 * 60 * 24));
        if ($d == 1)
            $str = '昨天 ' . $htime;
        else
            $str = '前天 ' . $htime;
    }
    else {
        $str = $rtime;
    }
    return $str;
}

function ClientIp(){
    //strcasecmp 比较两个字符，不区分大小写。返回0，>0，<0。
    if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $res =  preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
    return $res;
}


// 获取操作系统
function getOS() {
    $os = '';
    $Agent = $_SERVER['HTTP_USER_AGENT'];
    if (eregi('win', $Agent) && strpos($Agent, '95')) {
        $os = 'Win 95';
    } elseif (eregi('win 9x', $Agent) && strpos($Agent, '4.90')) {
        $os = 'Win ME';
    } elseif (eregi('win', $Agent) && ereg('98', $Agent)) {
        $os = 'Win 98';
    } elseif (eregi('win', $Agent) && eregi('nt 5.0', $Agent)) {
        $os = 'Win 2000';
    } elseif (eregi('win', $Agent) && eregi('nt 6.0', $Agent)) {
        $os = 'Win Vista';
    } elseif (eregi('win', $Agent) && eregi('nt 6.1', $Agent)) {
        $os = 'Win 7';
    } elseif (eregi('win', $Agent) && eregi('nt 5.1', $Agent)) {
        $os = 'Win XP';
    } elseif (eregi('win', $Agent) && eregi('nt 6.2', $Agent)) {
        $os = 'Win 8';
    } elseif (eregi('win', $Agent) && eregi('nt 6.3', $Agent)) {
        $os = 'Win 8.1';
    } elseif (eregi('win', $Agent) && eregi('nt 10', $Agent)) {
        $os = 'Win 10';
    } elseif (eregi('win', $Agent) && eregi('nt', $Agent)) {
        $os = 'Win NT';
    } elseif (eregi('win', $Agent) && ereg('32', $Agent)) {
        $os = 'Win 32';
    } elseif (ereg('Mi', $Agent)) {
        $os = '小米';
    } elseif (eregi('Android', $Agent) && ereg('LG', $Agent)) {
        $os = 'LG';
    } elseif (eregi('Android', $Agent) && ereg('M1', $Agent)) {
        $os = '魅族';
    } elseif (eregi('Android', $Agent) && ereg('MX4', $Agent)) {
        $os = '魅族4';
    } elseif (eregi('Android', $Agent) && ereg('M3', $Agent)) {
        $os = '魅族';
    } elseif (eregi('Android', $Agent) && ereg('M4', $Agent)) {
        $os = '魅族';
    } elseif (eregi('Android', $Agent) && ereg('Huawei', $Agent)) {
        $os = '华为';
    } elseif (eregi('Android', $Agent) && ereg('HM201', $Agent)) {
        $os = '红米';
    } elseif (eregi('Android', $Agent) && ereg('KOT', $Agent)) {
        $os = '红米4G版';
    } elseif (eregi('Android', $Agent) && ereg('NX5', $Agent)) {
        $os = '努比亚';
    } elseif (eregi('Android', $Agent) && ereg('vivo', $Agent)) {
        $os = 'Vivo';
    } elseif (eregi('Android', $Agent)) {
        $os = 'Android';
    } elseif (eregi('linux', $Agent)) {
        $os = 'Linux';
    } elseif (eregi('unix', $Agent)) {
        $os = 'Unix';
    } elseif (eregi('iPhone', $Agent)) {
        $os = '苹果';
    } else if (eregi('sun', $Agent) && eregi('os', $Agent)) {
        $os = 'SunOS';
    } elseif (eregi('ibm', $Agent) && eregi('os', $Agent)) {
        $os = 'IBM OS/2';
    } elseif (eregi('Mac', $Agent) && eregi('PC', $Agent)) {
        $os = 'Macintosh';
    } elseif (eregi('PowerPC', $Agent)) {
        $os = 'PowerPC';
    } elseif (eregi('AIX', $Agent)) {
        $os = 'AIX';
    } elseif (eregi('HPUX', $Agent)) {
        $os = 'HPUX';
    } elseif (eregi('NetBSD', $Agent)) {
        $os = 'NetBSD';
    } elseif (eregi('BSD', $Agent)) {
        $os = 'BSD';
    } elseif (ereg('OSF1', $Agent)) {
        $os = 'OSF1';
    } elseif (ereg('IRIX', $Agent)) {
        $os = 'IRIX';
    } elseif (eregi('FreeBSD', $Agent)) {
        $os = 'FreeBSD';
    } elseif ($os == '') {
        $os = 'Unknown';
    }
    return $os;
}

/**
 * 手机号码
 */
function isPhone($val) {
    if (ereg("^1[1-9][0-9]{9}$", $val))
        return true;
    return false;
}

/*
 * 获取浏览器信息
 */
function getUserBrowser() {
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'Maxthon')) {
        $browser = 'Maxthon';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 12.0')) {
        $browser = 'IE12.0';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 11.0')) {
        $browser = 'IE11.0';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 10.0')) {
        $browser = 'IE10.0';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 9.0')) {
        $browser = 'IE9.0';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8.0')) {
        $browser = 'IE8.0';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 7.0')) {
        $browser = 'IE7.0';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 6.0')) {
        $browser = 'IE6.0';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'NetCaptor')) {
        $browser = 'NetCaptor';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Netscape')) {
        $browser = 'Netscape';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Lynx')) {
        $browser = 'Lynx';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera')) {
        $browser = 'Opera';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
        $browser = 'Chrome';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox')) {
        $browser = 'Firefox';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari')) {
        $browser = 'Safari';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'iphone') || strpos($_SERVER['HTTP_USER_AGENT'], 'ipod')) {
        $browser = 'iphone';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'ipad')) {
        $browser = 'iphone';
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'android')) {
        $browser = 'android';
    } else {
        $browser = 'other';
    }
    return $browser;
}

function is_ip($str) {
    $ip = explode('.', $str);
    for ($i = 0; $i < count($ip); $i++) {
        if ($ip[$i] > 255) {
            return false;
        }
    }
    return preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $str);
}

/**
 * 字符串截取，支持中文和其他编码
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = '...') {
    if (function_exists("mb_substr")){
        $slice = mb_substr($str, $start, $length, $charset);
    } else if (function_exists('iconv_substr')) {
        $slice = iconv_substr($str, $start, $length, $charset);
        if (false === $slice) {
            $slice = '';
        }
    } else {
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
    }
    if($slice == $str){
        return $slice;
    }else{
        return $suffix ? $slice . $suffix : $slice;
    }
}

/*
 * 删除缓存方法
 */
function delFileByDir($dir) {
    $dh = opendir($dir);
    while ($file = readdir($dh)) {
        if ($file != "." && $file != "..") {

            $fullpath = $dir . "/" . $file;
            if (is_dir($fullpath)) {
                delFileByDir($fullpath);
            } else {
                unlink($fullpath);
            }
        }
    }
    closedir($dh);
}

/**
 * 获取网站域名
 */
function getDomain(){
    $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
    $domain = empty($_SERVER['HTTP_HOST']) ? $scheme. '://' .$_SERVER['SERVER_NAME'] : $scheme. '://' .$_SERVER['HTTP_HOST'];
    return $domain;
}

/**
 * 直辖市
 * @return [type] [description]
 */
function zx_city($cityname){
    $citys = array('北京市','上海市','重庆市','天津市');
    return in_array($cityname, $citys);
}


/**
 * 发送HTTP请求方法，目前只支持CURL发送请求
 * @param  string $url    请求URL
 * @param  array  $param  GET参数数组
 * @param  array  $data   POST的数据，GET请求时该参数无效
 * @param  string $method 请求方法GET/POST
 * @return array          响应数据
 */
function http($url, $param, $data = '', $method = 'GET'){
    $opts = array(
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    );

    /* 根据请求类型设置特定参数 */
    $opts[CURLOPT_URL] = $url . '?' . http_build_query($param);
    if(strtoupper($method) == 'POST'){
        $opts[CURLOPT_POST] = 1;
        $opts[CURLOPT_POSTFIELDS] = $data;

        if(is_string($data)){ //发送JSON数据
            $opts[CURLOPT_HTTPHEADER] = array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data),
            );
        }
    }

    /* 初始化并执行curl请求 */
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $data  = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    //发生错误
    if($error) return $error;
    return  $data;
}

/**
 * 获取分表 表名
 * @param  [type] $table 表名
 * @param  [type] $id    ID
 */
function get_hash_table($table,$id) {
    $len = strlen($id);
    if($len == 1) {
        $t = '0'.substr($id,0,1);
    } else {
        $t = substr($id,$len-2,2);
    }
    return $table.'_'.$t;
}

//模版的截取字段省略显示
function subtext($text, $length)
{
    if(mb_strlen($text, 'utf8') > $length)
        return mb_substr($text, 0, $length, 'utf8').'...';
    return $text;
}


function getAuthImage($text) {
    $im_x = 160;
    $im_y = 40;
    $im = imagecreatetruecolor($im_x, $im_y);
    $text_c = ImageColorAllocate($im, mt_rand(0, 100), mt_rand(0, 100), mt_rand(0, 100));
    $tmpC0 = mt_rand(240, 255);
    $tmpC1 = mt_rand(240, 255);
    $tmpC2 = mt_rand(240, 255);

    $buttum_c = ImageColorAllocate($im, $tmpC0, $tmpC1, $tmpC2);
    imagefill($im, 16, 13, $buttum_c);

    $font = 'Public/Font/t1.ttf';

    for ($i = 0; $i < strlen($text); $i++) {
        $tmp = substr($text, $i, 1);
        $array = array(-1, 1);
        $p = array_rand($array);
        $an = $array[$p] * mt_rand(1, 10); //角度
        $size = 28;
        imagettftext($im, $size, $an, 15 + $i * $size, 35, $text_c, $font, $tmp);
    }


    $distortion_im = imagecreatetruecolor($im_x, $im_y);

    imagefill($distortion_im, 16, 13, $buttum_c);
    for ($i = 0; $i < $im_x; $i++) {
        for ($j = 0; $j < $im_y; $j++) {
            $rgb = imagecolorat($im, $i, $j);
            if ((int) ($i + 20 + sin($j / $im_y * 2 * M_PI) * 10) <= imagesx($distortion_im) && (int) ($i + 20 + sin($j / $im_y * 2 * M_PI) * 10) >= 0) {
                imagesetpixel($distortion_im, (int) ($i + 10 + sin($j / $im_y * 2 * M_PI - M_PI * 0.1) * 4), $j, $rgb);
            }
        }
    }
    //加入干扰象素;
    $count = 160; //干扰像素的数量
    for ($i = 0; $i < $count; $i++) {
        $randcolor = ImageColorallocate($distortion_im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        imagesetpixel($distortion_im, mt_rand() % $im_x, mt_rand() % $im_y, $randcolor);
    }
    $rand = mt_rand(5, 30);
    $rand1 = mt_rand(15, 25);
    $rand2 = mt_rand(5, 10);
    for ($yy = $rand; $yy <= +$rand + 2; $yy++) {
        for ($px = -80; $px <= 80; $px = $px + 0.1) {
            $x = $px / $rand1;
            if ($x != 0) {
                $y = sin($x);
            }
            $py = $y * $rand2;

            imagesetpixel($distortion_im, $px + 80, $py + $yy, $text_c);
        }
    }

    //设置文件头;
    Header("Content-type: image/JPEG");
    //以PNG格式将图像输出到浏览器或文件;
    ImagePNG($distortion_im);
    //销毁一图像,释放与image关联的内存;
    ImageDestroy($distortion_im);
    ImageDestroy($im);
}

function make_rand($length = "32") {//验证码文字生成函数
    $str = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $result = "";
    for ($i = 0; $i < $length; $i++) {
        $num[$i] = rand(0, 25);
        $result.=$str[$num[$i]];
    }
    return $result;
}

function post($url, $postfields = array(),$uploadFile = array()) {
    $http_info = array();
    $header = array(
        'Content-Type: multipart/form-data',
    );
    $ci = curl_init();
    curl_setopt($ci, CURLOPT_URL, $url);
    curl_setopt($ci, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ci, CURLOPT_BINARYTRANSFER,true);
    curl_custom_postfields($ci, $postfields, $uploadFile);
    curl_setopt($ci, CURLOPT_USERAGENT, "Yeepay ZGT PHPSDK v1.1x");
    curl_setopt($ci, CURLOPT_TIMEOUT, 30);
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ci, CURLOPT_HEADER, false);
    curl_setopt($ci, CURLOPT_POST, true);


    $response = curl_exec($ci);
    $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    $http_info = array_merge($http_info, curl_getinfo($ci));
    //print_r($http_info);
    //echo "<br/>";
    curl_close ($ci);
    return $response;
}
/**
 * 重写POST 参数body
 *
 * @param resource $ch cURL resource
 * @param array $assoc name => value
 * @param array $files name => path
 * @return bool
 */
function curl_custom_postfields($ch, array $assoc = array(), array $files = array()) {

    // invalid characters for "name" and "filename"
    static $disallow = array("\0", "\"", "\r", "\n");

    // build normal parameters
    foreach ($assoc as $k => $v) {
        $k = str_replace($disallow, "_", $k);
        $body[] = implode("\r\n", array(
            "Content-Disposition: form-data; name=\"{$k}\"",
            "",
            filter_var($v),
        ));
    }

    // build file parameters
    foreach ($files as $k => $v) {
        switch (true) {
            case false === $v = realpath(filter_var($v)):
            case !is_file($v):
            case !is_readable($v):
                continue; // or return false, throw new InvalidArgumentException
        }
        $data = file_get_contents($v);
        $v = call_user_func("end", explode(DIRECTORY_SEPARATOR, $v));
        $k = str_replace($disallow, "_", $k);
        $v = str_replace($disallow, "_", $v);
        $body[] = implode("\r\n", array(
            "Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$v}\"",
            "Content-Type: application/octet-stream",
            "",
            $data,
        ));
    }

    // generate safe boundary
    do {
        $boundary = "---------------------" . md5(mt_rand() . microtime());
    } while (preg_grep("/{$boundary}/", $body));

    // add boundary for each parameters
    array_walk($body, function (&$part) use ($boundary) {
        $part = "--{$boundary}\r\n{$part}";
    });

    // add final boundary
    $body[] = "--{$boundary}--";
    $body[] = "";

    // set options
    return @curl_setopt_array($ch, array(
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => implode("\r\n", $body),
        CURLOPT_HTTPHEADER => array(
            "Expect: 100-continue",
            "Content-Type: multipart/form-data; boundary={$boundary}", // change Content-Type
        ),
    ));
}

function encrpt($queryData){
   //请求明文参数，hmac需求参数数组
   $needRequestHmac=array(0 => "requestid", 1 => "amount", 2 => "assure", 3 => "productname", 4 => "productcat", 5 => "productdesc", 6 => "divideinfo", 7 => "callbackurl", 8 => "webcallbackurl", 9 => "period", 10 => "memo");
   $hmacData = array();
   $hmacData["customernumber"] ="10015469830";
   foreach ( $needRequestHmac as $hKey => $hValue ) {

       $v = "";
       //判断$queryData中是否存在此索引并且是否可访问
       if ( isViaArray($queryData, $hValue) && $queryData[$hValue] ) {

           $v = $queryData[$hValue];
       }

       //取得对应加密的明文的值
       $hmacData[$hValue] = $v;
   }
   $hmac = getHmac($hmacData, '8863566Po4z5m768FrF6BF1y1K43V1Gn6i8204173Cgj914cXqtxdx0Zd485');
   $needRequest=array(0 => "requestid", 1 => "amount", 3 => "productname", 4 => "productcat", 7 => "callbackurl", 8 => "webcallbackurl", 10 => "memo",11=>'payproducttype',12 =>"directcode");
   $dataMap["customernumber"] ="10015469830";
   foreach ( $needRequest as $rKey => $rValue ){
       $v = "";
       //判断$queryData中是否存在此索引并且是否可访问
       if ( isViaArray($queryData, $rValue) && $queryData[$rValue] ) {

           $v = $queryData[$rValue];
       }

       //取得对应加密的明文的值
       $dataMap[$rValue] = $v;
   }

   $dataMap["hmac"] = $hmac;
   $dataJsonString = cn_json_encode($dataMap);
   $data = strtoupper(bin2hex(openssl_encrypt($dataJsonString, "aes-128-ecb", '8863566Po4z5m768',OPENSSL_PKCS1_PADDING)));
   $postfields = array("customernumber" =>10015469830, "data" => $data);
   $requestURL="https://o2o.yeepay.com/zgt-api/api/pay";
   $data = post($requestURL,$postfields);
   $responseJsonArray = json_decode($data, true);
   $data=hex2bin($responseJsonArray["data"]);
   $responseData = openssl_decrypt($data,'aes-128-ecb','8863566Po4z5m768',OPENSSL_PKCS1_PADDING);
   return $responseData;
}

/**
 * @取得hmac签名
 * @$dataArray 明文数组或者字符串
 * @$key 密钥
 * @return string
 *
 */
function getHmac(array $dataArray, $key) {

    if ( !isViaArray($dataArray) ) {

        return null;
    }

    if ( !$key || empty($key) ) {

        return null;
    }

    if ( is_array($dataArray) ) {

        $data = implode("", $dataArray);
    } else {

        $data = strval($dataArray);
    }

    $b = 64; // byte length for md5
    if (strlen($key) > $b) {

        $key = pack("H*",md5($key));
    }

    $key = str_pad($key, $b, chr(0x00));
    $ipad = str_pad('', $b, chr(0x36));
    $opad = str_pad('', $b, chr(0x5c));
    $k_ipad = $key ^ $ipad ;
    $k_opad = $key ^ $opad;

    return md5($k_opad . pack("H*",md5($k_ipad . $data)));
}

/**
 *
 * @将数组转换为JSON字符串（兼容中文）
 * @$array 要转换的数组
 * @return string 转换得到的json字符串
 *
 */
function cn_json_encode($array) {
    $array = cn_url_encode($array);
    $json = json_encode($array);
    return urldecode($json);
}
/**
 *
 * @将数组统一进行urlencode（兼容中文）
 * @$array 要转换的数组
 * @return array 转换后的数组
 *
 */
function cn_url_encode($array) {
    arrayRecursive($array, "urlencode", true);
    return $array;
}

/**
 * @使用特定function对数组中所有元素做处理
 * @&$array 要处理的字符串
 * @$function 要执行的函数
 * @$apply_to_keys_also 是否也应用到key上
 *
 */
function arrayRecursive(&$array, $function, $apply_to_keys_also = false)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            arrayRecursive($array[$key], $function, $apply_to_keys_also);
        } else {
            $array[$key] = $function($value);
        }

        if ($apply_to_keys_also && is_string($key)) {
            $new_key = $function($key);
            if ($new_key != $key) {
                $array[$new_key] = $array[$key];
                unset($array[$key]);
            }
        }
    }
}
/**
 * @检查一个数组是否是有效的
 * @$checkArray 数组
 * @$arrayKey 数组索引
 * @return boolean
 * 如果$arrayKey传参，则不止检查数组，
 * 而且检查索引是否存在于数组中。
 *
 */
function isViaArray($checkArray, $arrayKey = null) {

    if ( !$checkArray || empty($checkArray) ) {

        return false;
    }

    if ( !$arrayKey ) {

        return true;
    }

    return array_key_exists($arrayKey, $checkArray);
}

//验证身份证
function validation_filter_id_card($id_card){
    if(strlen($id_card)==18){
        return idcard_checksum18($id_card);
    }elseif((strlen($id_card)==15)){
        $id_card=idcard_15to18($id_card);
        return idcard_checksum18($id_card);
    }else{
        return false;
    }
}
// 计算身份证校验码，根据国家标准GB 11643-1999
function idcard_verify_number($idcard_base){
    if(strlen($idcard_base)!=17){
        return false;
    }
    //加权因子
    $factor=array(7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2);
    //校验码对应值
    $verify_number_list=array('1','0','X','9','8','7','6','5','4','3','2');
    $checksum=0;
    for($i=0;$i<strlen($idcard_base);$i++){
        $checksum += substr($idcard_base,$i,1) * $factor[$i];
    }
    $mod=$checksum % 11;
    $verify_number=$verify_number_list[$mod];
    return $verify_number;
}
// 将15位身份证升级到18位
function idcard_15to18($idcard){
    if(strlen($idcard)!=15){
        return false;
    }else{
        // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
        if(array_search(substr($idcard,12,3),array('996','997','998','999')) !== false){
            $idcard=substr($idcard,0,6).'18'.substr($idcard,6,9);
        }else{
            $idcard=substr($idcard,0,6).'19'.substr($idcard,6,9);
        }
    }
    $idcard=$idcard.idcard_verify_number($idcard);
    return $idcard;
}
// 18位身份证校验码有效性检查
function idcard_checksum18($idcard){
    if(strlen($idcard)!=18){
        return false;
    }
    $idcard_base=substr($idcard,0,17);
    if(idcard_verify_number($idcard_base)!=strtoupper(substr($idcard,17,1))){
        return false;
    }else{
        return true;
    }
}

/**
 * [CompositeTxt 图片文字合成]
 * @param [type]  $bigImgPath [description]
 * @param [type]  $txt        [description]
 * @param string  $font       [description]
 * @param integer $fontSize   [description]
 * @param integer $left       [description]
 * @param integer $top        [description]
 * @param integer $circleSize [description]
 */
function CompositeTxt($bigImgPath, $txt, $fontSize = 55, $left = 330, $top = 240, $circleSize = 0, $font = 'Public/Font/Aachenn.ttf')
{
    $img = imagecreatefromstring(file_get_contents($bigImgPath));
    $black = imagecolorallocate($img, 80, 183, 225);//字体颜色 RGB
    imagefttext($img, $fontSize, $circleSize, $left, $top, $black, $font, $txt);
 
    list($bgWidth, $bgHight, $bgType) = getimagesize($bigImgPath);
    switch ($bgType) {
        case 1: //gif
            header('Content-Type:image/gif');
            imagegif($img, $bigImgPath);
            break;
        case 2: //jpg
            header('Content-Type:image/jpg');
            imagejpeg($img, $bigImgPath);
            break;
        case 3: //jpg
            header('Content-Type:image/png');
            imagepng($img, $bigImgPath);
            break;
        default:
            break;
    }
    imagedestroy($img);
}
/**
 * [CompositeQrcode description]
 * @param [type]  $srcImg      [description]
 * @param [type]  $waterImg    [description]
 * @param [type]  $savepath    [description]
 * @param [type]  $savename    [description]
 * @param integer $scale_width [description]
 * @param integer $positon_x   [description]
 * @param integer $positon_y   [description]
 */
function CompositeQrcode($srcImg, $waterImg, $savepath = null, $savename = null, $scale_width = 300, $positon_x = 170, $positon_y = 700){
    $temp = pathinfo($srcImg);
    $name = $temp['basename'];
    $path = $temp['dirname'];
    $exte = $temp['extension'];
    $savename = $savename ? $savename : $name;
    $savepath = $savepath ? $savepath : $path;
    $savefile = $savepath . $savename . '.' . $exte;
    $srcinfo = @getimagesize($srcImg);
    if (!$srcinfo) {
        return -1; //原文件不存在
    }
    $waterinfo = @getimagesize($waterImg);
    if (!$waterinfo) {
        return -2; //水印图片不存在
    }
    $srcImgObj = image_create_from_ext($srcImg);
    if (!$srcImgObj) {
        return -3; //原文件图像对象建立失败
    }
    $waterImgObj = image_create_from_ext($waterImg);
    if (!$waterImgObj) {
        return -4; //水印文件图像对象建立失败
    }
    $scale = $waterinfo[0]/$scale_width; 
    $scale_height = $waterinfo[1]/$scale;
    //imagecopymerge($srcImgObj, $waterImgObj, $x, $y, 0, 0, $waterinfo[0], $waterinfo[1], $alpha);
    imagecopyresampled($srcImgObj, $waterImgObj, $positon_x, $positon_y, 0, 0, $scale_width, $scale_height, $waterinfo[0], $waterinfo[1]); 
    switch ($srcinfo[2]) {
        case 1: imagegif($srcImgObj, $savefile); break;
        case 2: imagejpeg($srcImgObj, $savefile); break;
        case 3: imagepng($srcImgObj, $savefile); break;
        default: return -5; //保存失败
    }
    imagedestroy($srcImgObj);
    imagedestroy($waterImgObj);
    return $savefile;
}
function image_create_from_ext($imgfile){
    $info = getimagesize($imgfile);
    $im = null;
    switch ($info[2]) {
    case 1: $im=imagecreatefromgif($imgfile); break;
    case 2: $im=imagecreatefromjpeg($imgfile); break;
    case 3: $im=imagecreatefrompng($imgfile); break;
    }
    return $im;
}

function phoneaddstar($phone){
    return preg_replace('/(\d{3})\d{4}(\d{4})/', '$1****$2', $phone);
}

function nameaddstar($rname){
    $strlen    = mb_strlen($rname, 'utf-8');
    $firstStr  = mb_substr($rname, 0, 1, 'utf-8');
    $lastStr   = mb_substr($rname, -1, 1, 'utf-8');
    if($strlen == 2){
        return  $firstStr . str_repeat('*', mb_strlen($rname, 'utf-8') - 1);
    }else{
        return  $firstStr . str_repeat("*", $strlen - 2) . $lastStr;
    }
}

function idcardaddstar($idcard){
   return  substr_replace($idcard,str_repeat('*',6),6,7);
}
?>