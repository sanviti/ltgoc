<?php 
namespace Api\Model;
use Think\Model;
class AuthsModel extends Model{
    protected $tableName = 'auths';
    protected $tablePrefix = 'lt_';
    
    //查询用户是否进行实名认证
    public function isauth($uid){
        return $this->where(array("uid"=>$uid))->field("rname")->find();
    }
    
    //查询该身份证号是否已认证
    public function ishave($idcard){
        return $this->where(array("idcard"=>$idcard))->find();
    }
}