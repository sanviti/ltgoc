<?php
/**
 * 交易中心买入模型
 * 2017-12-08
 * lxy
 */
namespace Api\Model;
use Think\Model;
class TradingBuyModel extends Model {
	protected $tablename = 'trading_buy';

	/**
	 * 新增买入
	 * @param [type] $data [description]
	 */
	public function add($data){
		$data['price'] = floor3($data['price']);
		$data['transno'] = tradingOrderSN('B');
		$data['ctime'] = NOW_TIME;
		return parent::add($data);
	}

	/**
	 * 获取买入队列
	 * @return [type] [description]
	 */
	public function buyList(){
		$condi = array(
			'iscolse' => 0,
			'status' => 1,
			'ctime' => array('gt', NOW_TIME - 60*60*24)
		);
		return $this->field('id')->where($condi)->order('ctime ASC')->select();
	}

	/**
	 * 获取一条买入
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function findById($id){
		$condi = [
			'id' => $id,
			'iscolse' => 0,
			'status' => 1,
			'ctime' => array('gt', NOW_TIME - 60*60*24)
		];

		return $this->where($condi)->find();
	}

	/**
     * 更新数据
     */
    public function modify($id, $data){
        $condi = array('id' => $id);
        return $this->where($condi)->save($data);
    }
}