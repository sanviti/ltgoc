<?php
/**
 * 交易中心接口
 */
namespace Api\Controller;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class TrandDataController extends Controller{

    /**
     * 首页
     * @return [type] [description]
     */
    public function index(){
        //当天成交量
        $trading_amountModel = M("trading_amount");
        $last_time = strtotime(date("Y-m-d"));
        $condi['ctime'] = array('egt',$last_time);
        $total = $trading_amountModel->field('amount')->where($condi)->find();
        if(!$total){
            $amount = 0.000;
        }else{
            $amount = $total['amount'];
        }

        //今日价格
        $priceModel=D('TradingPrice');
        $TradingPrice = $priceModel->getPrice();
        //昨日价格
        $TradingLastPrice = $priceModel->getLastPrice();
        $price=$TradingPrice['price'];
        $Lastprice=$TradingLastPrice['price'];
        $riseFallNum=bcsub($price,$Lastprice,2);
        $fu = number_format(($riseFallNum / $Lastprice) * 100, '0', '.', '');
        $riseFall= $fu > 0 ? '+'.$fu."%" : $fu . "%";


        //交易中心折线图
        $sdate = mktime(0,0,0,date('m'),date('d')-6,date('Y'));
        $edate = mktime(23,59,59,date('m'),date('d'),date('Y'));
        $condi['ctime']=array(array("EGT",$sdate),array("ELT",$edate));
        $priceList=$priceModel->field('price,FROM_UNIXTIME(ctime,"%m/%d") as ctime')->where($condi)->order('ctime asc ')->limit(7)->select();


        $data = array(
            'price' => number_format($price, 2, '.', ''),
            'total' => $amount,
            'riseFall'=>$riseFall,
            'priceList'=>$priceList,
            'buy_fee'=>Constants::SCORE_BUY_FEE,
            'sell_fee'=>Constants::SCORE_SELL_FEE
        );
        succ($data);
    }




}