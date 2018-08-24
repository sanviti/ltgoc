<?php
/**
 * 首页接口
 */
namespace Api\Controller;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class IndexController extends Controller{

    /**
     * 首页
     * @return [type] [description]
     */
    public function index(){
        //banner
        $banner = M('banner')->cache(true, 300)->field('pic_url,url')->order('sort DESC')->select();

        //当天成交量
        $trading_amountModel = M("trading_amount");
        $last_time = strtotime(date("Y-m-d"));
        $condi['ctime'] = array('egt',$last_time);
        $total = $trading_amountModel->field('amount')->where($condi)->find();
        if(!$total){
            $amount = 0;
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
        //infos
        $infos = M('news')->field('id,picurl,title,des')->where('cate_id = 39 AND picurl <> ""')->order('sort desc,id DESC')->limit(10)->select();
        foreach ($infos as $k=>$v){
            $infos[$k]['detail'] =  Constants::BASE_URL.'/Api/Index/detail/id/'.$v['id'];
        }
        //交易中心折线图
//        $sdate = mktime(0,0,0,date('m'),date('d')-6,date('Y'));
//        $edate = mktime(23,59,59,date('m'),date('d'),date('Y'));
//        $condi['ctime']=array(array("EGT",$sdate),array("ELT",$edate));
//        $priceList=$priceModel->field('price,FROM_UNIXTIME(ctime,"%m/%d") as ctime')->where($condi)->order('ctime asc ')->limit(7)->select();


        $data = array(
            'banner' => $banner,
            'price' => number_format($price, 2, '.', ''),
            'amount'=>$amount,
            'total' => bcmul($amount, $price, 2),
            'riseFall'=>$riseFall,
            'infos' => $infos
//            'priceList'=>$priceList,
//            'buy_fee'=>Constants::SCORE_BUY_FEE,
//            'sell_fee'=>Constants::SCORE_SELL_FEE
        );
        succ($data);
    }


    /**
     * 新闻详情
     * @return [type] [description]
     */
    /* public function detail(){
        $id = I('id/d');
        empty($id) && exit();
        $data = M('news')->field('title, content')->cache(true, 300)->where(array('id' => $id))->find();
        empty($data) && exit();
        foreach($data as $key=>$value){
            $data[$key]['content']=htmlentities($value);
        }
        succ($data);
    } */
    public function detail(){
        $id = I('id/d');
        empty($id) && exit();
        $data = M('news')->field('title, content')->where(array('id' => $id))->find();
        empty($data) && exit();
        $this->assign('page_title',$data['title']);
        $this->assign('category_name', '最新资讯');
        $this->assign('title', $data['title']);
        $this->assign('content', $data['content']);
        $this->display();
    }


    /**
     * 软件版本号
     * @param sys 操作平台 android ios
     * @return
     * code  版本号
     * describe 更新内容
     * download 下载地址
     * force    强制更新
     * dateline 时间
     */
    public function version(){
        $system = I('system/s');
        $data = M('version')->field('version as code,name as version,describe,download,force,dateline,system')->where(array('system'=>$system))->order('dateline DESC')->find();
        $data['download'] = $data['download'];
        succ($data);
    }


    public  function  reset(){
        $phone=I('phone');
        $members=D('members')->where(array('phone'=>$phone))->find();
        if(empty($members)){
            err('账户未找到');
        }
        $sign=D('members')->_sign($members);
        $result=D('members')->where(array('phone'=>$phone))->save(array('sign'=>$sign));
        if($result){
            succ('ok');
        }else{
            err('no');
        }
    }


    public  function  chong(){
        $phone=I('phone');
        $members=D('members')->where(array('phone'=>$phone))->find();
        if(empty($members)){
            err('账户未找到');
        }
        $moneyfield=I('moneyfield');
        if(empty($moneyfield)){
            err('未填写货币类型');
        }
        $money=I('money');
        if(empty($money)){
            err('未填写充值数量');
        }
        $sql = "UPDATE lt_members SET " . $moneyfield . " = " . $moneyfield . " + " . $money . " WHERE phone = " .$phone;
        M()->execute($sql);
        $Newmembers=D('members')->where(array('phone'=>$phone))->find();
        $sign=D('members')->_sign($Newmembers);
        $result=D('members')->where(array('phone'=>$phone))->save(array('sign'=>$sign));
        if($result){
            succ('ok');
        }else{
            err('no');
        }
    }


}