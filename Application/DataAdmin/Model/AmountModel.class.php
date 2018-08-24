<?php
namespace DataAdmin\Model;
use Think\Model;
class AmountModel extends Model {
    protected $tableName = 'trading_amount';
    
    public function getCount($where){
        return $this->where($where)->count();
    }
    
    public function getList($page){
        return $this->field('id,amount,ctime')->page($page)->order('ctime desc')->limit(C('PAGE_SIZE'))->select();
    }
    
    public function addAmount($data){
        return $this->add($data);
    }
    
    public function findInfo($where){
        return $this->where($where)->order('ctime desc')->find();
    }
    
    public function editAmount($where,$data){
        return $this->where($where)->save($data);
    }
    
}    
?>