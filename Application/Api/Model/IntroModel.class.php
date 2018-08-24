<?php
/**
 * 文章及介绍
 * lxy
 */
namespace Api\Model;
use Think\Model;
class IntroModel extends Model{
    protected $tableName = 'news';
    protected $tablePrefix = 'lt_';
    
    /**
     * 说明
     */
    public function intro($field,$cateid){
        $where = array();
        $where['cate_id'] = $cateid;
        $where['state']  = 1;
        $data = $this->where($where)->field($field)->select();
        return $data;
    }
    
    /**
     * 详解
     */
    public function detail($field,$id){
        $where = array();
        $where['cate_id'] = $id;
        $info = $this->field($field)->where($where)->find();
        return $info;
    }
    
    /**
     * 查找文章id
     */
    public function get_noticeid($catid){
        return $this->field("id")->where(array('cate_id'=>$catid))->find();
    }
    
}