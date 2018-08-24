<?php 
namespace DataAdmin\Controller;
use Think\Controller;
class KeySetController extends BaseController{
    //价格列表
    public function addCoinPrice() {
        $page = I("p");
        $KeySetPriceModel = D("Price");
        $count = $KeySetPriceModel->getPriceCount(); // 查询满足要求的总记录数
        $list = $KeySetPriceModel->getPriceList($page,array("id,price,ctime"));

        $p = getpage($count, C('PAGE_SIZE'));;
        $show = $p->newshow();

        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }
    //设置今日价格
    public function savePrice(){
		$price = trim(I('coinPrice'));
		if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $price)) {
			$this->error('价格最多为2位小数');
		}
		
		$start_time = strtotime(date("Y-m-d"));
		$end_time = strtotime(date("Y-m-d"))+3600*24;
		$condi['ctime'] = array(array("GT",$start_time),array("LT",$end_time));
		$count =  D("Price")->getPriceCount($condi);
		
		$coinData = array(
		    'price'=>$price,
		    'ctime'=>time()
		);
		if($count==0){//每天只能添加一次
			if($price > 0){
			    D("Price")->setPrice($coinData);  
			}
		}else{
			$this->error('每天价格只能设置一次');
		}
        $this->redirect('KeySet/addCoinPrice');
    }
	//设置明日价格
    public function save_next_price(){
		$price = trim(I('coinPrice'));
		$next_start_time = strtotime(date("Y-m-d"))+3600*24;
		$next_end_time = strtotime(date("Y-m-d"))+3600*24*2;
		if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $price)) {
			$this->error('价格最多为2位小数');
		}
        $condi['ctime'] = array(array("EGT",$next_start_time),array("ELT",$next_end_time));
		$count =  D("Price")->getPriceCount($condi);
		$coinData = array(
		    'price'=>$price,
		    'ctime'=>$next_start_time
		);
		if($count==0){//每天只能价格一次
			if(trim(I('coinPrice')) > 0){
			  D("Price")->setPrice($coinData);  
			}
		}else{
			$this->error('每天价格只能设置一次');
		}
        $this->redirect('KeySet/addCoinPrice');
    }
    //修改价格
    public function editprice(){
        $editprice = trim(I('editprice'));
        $priceid = trim(I('priceid'));
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $editprice)) {
            err('价格最多为2位小数');
        }
        $condi['id'] = $priceid;
        $find = D("Price")->findPriceInfo($condi);
        $end_time = strtotime(date("Y-m-d"))+3600*24;
        $time = time();
        if($find){
            if($end_time== $find['ctime']){
                $time = $find['ctime'];
            }
        }
        $data = array(
            'price'=>$editprice,
            'ctime'=>$time
        );
        if($editprice > 0){
            $res = D("Price")->editPrice($condi,$data);
        }
        if($res){
            succ("修改成功");
        }else{
            err("修改失败");
        }
    }
    
	//添加累计交易量
    public function add_coin_amount() {
        $page = I("p");
        $trading_amountModel = D("Amount");
        $count = $trading_amountModel->getCount(array()); 
        $list = $trading_amountModel->getList($page);

        $p = getpage($count, C('PAGE_SIZE'));;
        $show = $p->newshow();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }
    //保存累计交易量
    public function save_trading_amount(){
		$amount = trim(I('amount'));
		$trading_amountModel = D("Amount");
		if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $amount)) {
			$this->error('价格最多为2位小数');
		}
		$last_time = strtotime(date("Y-m-d"));
		$cond['ctime'] = array('gt',$last_time-1);
		$count = $trading_amountModel->getCount($cond);
		
		if($count==0){
			if($last_time<time()){
				$last_time+3600*24;
			}
			$firstdata = array(
				'amount'=>$amount,
				'ctime'=>$last_time
			);
			D("Amount")->addAmount($firstdata);
			$this->redirect('KeySet/add_coin_amount');
		}else{
			$where['ctime'] = array('lt',$last_time+3600*24); 
			$find = $trading_amountModel->findInfo($where);
			$total = floatval($amount) + $find['amount'];

			$coinData = array(
				'amount'=>$total,
				'ctime'=>time()
			);
			if(trim(I('amount')) > 0){
			  $trading_amountModel->editAmount(array("id"=>$find['id']),$coinData);  
			}
		}
        $this->redirect('KeySet/add_coin_amount');
    }
	
}
?>