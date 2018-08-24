<?php
/**
 * 用户账户签名
 * 2018-3-3
 * lxy
 */
namespace Api\Model;
use Think\Model;
class MembersSignModel extends Model {
	protected $tableName = 'members_sign';

	/**
	 * 获取最后一条签名
	 * @param  [type] $token [description]
	 * @return [type]        [description]
	 */
	public function getLastSign($token){
		$condi = [
			'user_token' => $token,
		];
		$last = $this->where($condi)->find();
		return $last;
	}

	/**
     * 更新数据 token
     */
    public function modify($token, $sign){
        $condi = array('user_token' => $token);
        return $this->where($condi)->save(array('last_sign' => $sign));
    }

}