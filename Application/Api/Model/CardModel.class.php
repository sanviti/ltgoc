<?php
/**
 * 债券金卡模型
 * 2017-11-30 
 * lxy
 */
namespace Api\Model;
use Think\Model;
class CardModel extends Model {

	/**
	 * 更改卡片状态
	 * @param  $id     卡片id
	 * @param  $status 状态码
	 * @return         [description]
	 */
	public function changeStatus($id, $status){
		$condi = array('id' => $id);
		return $this->where($condi)->save(array('to_use' => $status, 'last_time' => NOW_TIME, 'use_time' => NOW_TIME));
	}

	public function getCard($bind_mobile, $cardid){
		$condi = array(
			'bind_mobile' => $bind_mobile,
			'card_id' => strtoupper($cardid),
			'to_use' => 0,
		);
		$card = $this->where($condi)->find();
		return $card;
	}
	
}