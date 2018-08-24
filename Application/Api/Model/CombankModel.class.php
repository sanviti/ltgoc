<?php
/**
 * 手机端用户模型
 */
namespace Api\Model;
use Think\Model;
use Common\Lib\Constants;
class CombankModel extends Model{
    protected $tableName = 'combank';
    
    public function combank(){
        return $this->find();
    }
}
?>