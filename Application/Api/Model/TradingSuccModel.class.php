<?php
/**
 * 交易中心成交模型
 * 2017-12-08
 * lxy
 */
namespace Api\Model;
use Think\Model;
class TradingSuccModel extends Model {
	protected $tablename = 'trading_succ';

	/**
	 * 新增成交订单
	 * @param [type] $data [description]
	 */
	public function add($data){
		$data['price'] = floor4($data['price']);
		$data['transno'] = tradingOrderSN('B');
		$data['ctime'] = NOW_TIME;
		return parent::add($data);
	}

	/**
	 * 获取成交列表
	 * @param  [type]  $page  [description]
	 * @param  integer $limit [description]
	 * @return [type]         [description]
	 */
	public function getList($page, $where = array(), $limit = 10){
		return  $this->where($where)->page($page)->limit($limit)->select();
	}

	/**
	 * 买入订单下已成交总额
	 * @param  str $sn 订单号
	 * @return float   总额
	 */
	public function buySuccNum($transno){
		$condi = array(
			'transno_buy' => $transno
		);
		return $this->where($condi)->sum('num');
	}

	/**
	 * 卖出订单下已成交总额
	 * @param  str $sn 订单号
	 * @return float   总额
	 */
	public function sellSuccNum($transno){
		$condi = array(
			'transno_sell' => $transno
		);
		return $this->where($condi)->sum('num');
	}

}