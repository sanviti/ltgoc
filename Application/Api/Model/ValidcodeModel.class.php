<?php
namespace Api\Model;
use Think\Model;
use Think\Log;
class ValidcodeModel extends Model{
    protected $pk   = 'codeid';
    protected $tableName =  'validcode';
    protected $token = 'code';

    /**
     * 手机号最后一条信息
     * @param  [type] $mobile [description]
     * @param  [type] $range  [description]
     * @return [type]         [description]
     */
    public function lastSMS($mobile, $range){
       return $this->where(array('mobile' => $mobile, 'range' => $range))->order("$this->pk DESC")->find();
    }

    /**
     * 检测验证码是否过期
     */
    public function expires($mobile,$code,$range){
        $condition['mobile'] = $mobile;
        $condition['code']   = $code;
        $condition['range']   = $range;
        $result = $this->field('expires_in')->where($condition)->order("codeid DESC")->find(); 
        return empty($result) || $result['expires_in'] < NOW_TIME ? true : false;
    }


}



