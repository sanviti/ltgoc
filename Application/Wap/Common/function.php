<?php 
function valid_image(){
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
function valid_check($validcode){
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
?>