<?php
/**
 * 交易中心买入模型
 * 2017-12-08
 * lxy
 */
namespace Api\Model;
use Think\Model;
use Common\Lib\Constants;
class TradingOrderModel extends Model {
	protected $tablename = 'trading_Order';

	/**
	 * 新增订单
	 * @param [type] $data [description]
	 */
	public function add($data){
		$data['price'] = $data['price'];
		$data['transno'] = tradingOrderSN($data['order_type'] == 1 ? 'B' : 'S');
		$data['ptime'] = NOW_TIME;
		$data['status'] = 0;
		return parent::add($data);
	}

	/**
	 * 获取队列
	 * @return [type] [description]
	 */
	public function lists(){
		$condi = array(
			'iscolse' => 0,
			'status' => 1,
			'ctime' => array('gt', NOW_TIME - 60*60*24)
		);
		return $this->field('id')->where($condi)->order('ctime ASC')->select();
	}

	/**
	 * 获取订单
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

    //撤销订单
    public function cancel($id){
    	$userModel = D('Api/Members');
    	$this->startTrans();
    	$order = $this->lock(true)->where('id = %d AND status = 0', $id)->find();
    	if($order){
    		$user = $userModel->account($order['uid']);
    		//标记订单状态
    		$result = $this->where('id = %d AND status = 0', $id)->save(array('status' => -1, 'ctime' => NOW_TIME));
    		$num = new_bcsub($order['num'], $order['succ_num']);
    		//判断定单类型
    		if($order['order_type'] == 1){
    			#TODO:返还USDC
    			$amount = new_bcmul($order['price'], $num);
    			$fee = new_bcmul($amount, Constants::SCORE_BUY_FEE);
    			$total = new_bcadd($amount, $fee);
    			$result = $result && $userModel->unlockUsdc($user, $total);
    		}else{
    			//返还GOC
    			$result = $result && $userModel->unlockGoc($user, $num);
    		}
    		if($result){
    			$this->commit();
    			return true;
    		}else{
    			$this->rollback();
    			return false;
    		}
    	}else{
    		$this->rollback();
    		return false;
    	}
    }
}