<?php
/**
 * 交易中心预约模型
 */
namespace Api\Model;
use Think\Model;
use Common\Lib\Constants;
class TradingMoveupModel extends Model {
    protected $tablename = 'trading_moveup';

    /**
     * 获取队列
     * @return [type] [description]
     */
    public function lists($page,$where,$field='*'){
        return $this->field($field)->where($where) -> page($page)-> limit(10)-> order('ctime DESC') -> select();
    }

    /**
     * 更新数据
     */
    public function modify($id, $data){
        $condi = array('id' => $id);
        return $this->where($condi)->save($data);
    }


    //撤销预约订单
    public function cancel($id){
        $userModel = D('Members');
        $this->startTrans();
        $order = $this->lock(true)->where('id = %d AND status = 0', $id)->find();
        if($order){
            $user = $userModel->account($order['uid']);
            //标记订单状态
            $result = $this->where('id = %d AND status = 0', $id)->save(array('status' => -1, 'ctime' => NOW_TIME));
            $num = $order['num'];
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