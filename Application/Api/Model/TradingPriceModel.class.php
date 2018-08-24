<?php
/**
 * 交易中心价格K线及精密价值
 * 2017-4-20
 * ws
 */
namespace Api\Model;
use Think\Model;
class TradingPriceModel extends Model {
    protected $tablename = 'trading_price';

    /**
     * 价格获取
     * @param [type] $data [description]
     */
    public function getPrice(){
        $sdate = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $edate = mktime(23,59,59,date('m'),date('d'),date('Y'));
        $condi['ctime']=array(array("EGT",$sdate),array("ELT",$edate));
        return  $this->order('id desc')->where($condi)->field('price')->find();
    }

    /**
     * 获取前一天价格
     * @param [type] $data [description]
     */
    public function getLastPrice(){
        $sdate = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $edate = mktime(23,59,59,date('m'),date('d')-1,date('Y'));
        $condi['ctime']=array(array("EGT",$sdate),array("ELT",$edate));
        return  $this->order('id desc')->where($condi)->field('price')->find();
    }

}