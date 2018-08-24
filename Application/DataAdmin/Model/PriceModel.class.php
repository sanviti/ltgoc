<?php
namespace DataAdmin\Model;
use Think\Model;
class PriceModel extends Model {
    protected $tableName = 'trading_price';
    
    /**
     * 获取价格表里数量
     */
    public function getPriceCount($where){
        return $this->where($where)->count();
    }
    
    /**
     * 获取价格记录
     */
    public function getPriceList($page,$field='*',$where){
        $list = $this->page($page)->field($field)->where($where)->limit(C('PAGE_SIZE'))->order('ctime desc')->select();
        $zero_time = strtotime(date("Y-m-d"))+3600*24;
        for($i=0;$i<count($list);$i++){
            $list[$i]['price'] =round($list[$i]['price'],2);
            $list[$i]['editable'] =0;
            if($list[$i]['ctime']>=$zero_time){
                $list[$i]['editable'] = 1;
            }
        }
        return $list;
    }
    
    /**
     * 设置价格
     */
    public function setPrice($data){
        return $this->add($data);
    }
    
    /**
     * 获取某条价格记录
     */
    public function findPriceInfo($where){
        return $this->where($where)->find();
    }
    
    /**
     * 修改价格
     */
    public function editPrice($where,$data){
        return $this->where($where)->save($data);
    }
}