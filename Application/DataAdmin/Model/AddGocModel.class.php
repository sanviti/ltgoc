<?php 
namespace DataAdmin\Model;
use Think\Model;
class AddGocModel extends Model{
    protected $tableName = 'recharge_list';
    protected $tablePrefix = 'lt_';
    
    public function adds($data){
        return $this->add($data);
    }
    
    public function lists($where,$page,$limit,$order){
        return $this->where($where)->alias("c")->page($page)->field("c.*,m.userid,m.rname")
               ->join("left join lt_members as m on c.uid=m.id")
               ->limit($limit)->order($order)->select();
    }
    /**
     * 总数
     * @param unknown $where
     * @return unknown
     */
    public function getcount($where){
        $num = $this->where($where)->alias("c")->join("lt_members as m on c.uid=m.id")->count();
        return $num;
    }

} 
 
?>