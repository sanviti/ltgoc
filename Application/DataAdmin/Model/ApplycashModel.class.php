<?php
/**
 * 申请提现
 * 2018-03-16 
 * lxy
 */
namespace DataAdmin\Model;
use Think\Model;
class ApplycashModel extends Model {
	protected $tableName = 'applycash';
	protected $tablePrefix = 'lt_';

    /**
     * 获取总条数
     * @param  array  $condi [description]
     */
    public function getcount($where){
        $num = $this->where($where)->field("a.*,m.userid")->alias("a")->join("lt_members as m on a.uid=m.id")->count();
        return $num;
    }

	/**
     * 提现列表
     */
    public function lists($where,$page,$limit,$order){
        $lists = $this->where($where)->page($page)->alias("a")->field("a.*,m.userid")->join("lt_members as m on a.uid=m.id")
                 ->limit($limit)->order($order)->select();
        foreach ($lists as $k=>$v){
            $admin = M("admin")->field("username")->where(array("id"=>$v['mgrid']))->find();
            $lists[$k]['admin'] = $admin['username'];
        }
        return $lists;
    }
    
    /**
     * 提现明细
     */
    public function process($id){
        $process = $this->where(array("a.id"=>$id))->alias("a")
        ->field("a.*,m.userid,m.phone,m.rname,m.vip_level")->join("lt_members as m on a.uid=m.id")
        ->find();
        $admin = M("admin")->where(array("id"=>$process['mgrid']))->find();
        $process['mgrid'] = $admin['username'];
        return $process;
    }
    
    /**
     * 查询记录
     */
    public function getinfo($field,$where){
        $info = $this->where($where)->field($field)->find();
        return $info;
    }
    
    /**
     * 后台处理提现
     */
    public function to_apply($where,$data){
        return $this->where($where)->save($data);
    }

}