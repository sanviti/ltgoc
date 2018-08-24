<?php 
namespace Api\Model;
use Think\Model;
class RechargeModel extends Model{
    protected $tableName = 'recharge';
    protected $tablePrefix = 'lt_';
    
    /**
     * 添加纪录
     */
    public function adds($data){
        return $this->add($data);
    }
    
    /**
     * 查找记录
     */
    public function findinfo($where){
        return $this->where($where)->find();
    }
    
    /**
     * 修改记录
     */
    public function modify($where,$data){
        return $this->where($where)->save($data);
    }
    
}
?>