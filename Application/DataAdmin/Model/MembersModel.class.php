<?php
/**
 * 后台用户
 * 2017-11-28 
 * lxy
 */
namespace DataAdmin\Model;
use Think\Model;
use Common\Lib\Constants;

class MembersModel extends Model {
	protected $tableName = 'members';
	/**
     * 查找正常用户
	 * @param $where 搜索条件
     */
    public function normalMember($condi, $fields = '*'){
    	$condi['is_lock'] = 0;
    	$condi['isfreeze'] = 0;
		$res = $this->field($fields)->where($condi)->find();
		return $res;
	}

	

    public function getCount($condi = array()){
        $map = array('userid'=>array('exp','IS NOT NULL'));
        $condi = array_merge($map, (array) $condi);
        return $this->where($condi)->count();
    }

	/**
     * 用户列表
	 * @param $where 搜索条件
	 * @param $page  当前页
     */
	public function getList($where, $page = 1){
        $map = array('userid'=>array('exp','IS NOT NULL'));
        $where = array_merge($map, (array)$where);
		$list = $this->field('id,userid,rname,phone,auth_c1,auth_c2,reg_time,login_time,is_lock,is_freeze,headimg,province,login_ip,city,goc,goc_lock,
		                      usdc,usdc_lock,balance,balance_lock,chain_score,share_score,vip_level')
					-> where($where)
					-> page($page)
					-> order('id desc')
					-> limit(C('PAGE_SIZE'))
					-> select();
		return $list;
	}

	public function getMember($id,$field = 'id,token,balance'){
		return $this->where(array('id' => $id))->find();
	}

    /**
     * 签名
     * @return [type] [description]
     */
    public function _sign($data){
        return sha1(Constants::SIGN_SALT . $data['token'] .$data['goc'].$data['goc_lock'].$data['usdc'] . $data['usdc_lock'] . $data['balance'] . $data['balance_lock'] . $data['chain_score'] . $data['share_score']);
    }

    /**
     * 验证签名
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function _verify_sign($data){
        $sign = sha1(Constants::SIGN_SALT . $data['token'] .$data['goc'].$data['goc_lock']. $data['usdc'] . $data['usdc_lock'] .$data['balance'] . $data['balance_lock'] . $data['chain_score'] . $data['share_score']);
        return $sign == $data['sign'] ? true : false;
    }
    #以下操作需要验证签名
    /**
     * 前台提现账户余额操作
     */
    public function ApplyBalance($upd,$num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['balance'] = new_bcsub($upd['balance'],$num);
        $upd['balance_lock'] = new_bcadd($upd['balance_lock'],$num);
        $upd['sign'] = $this->_sign($upd);
        $members['balance']=$upd['balance'];
        $members['balance_lock']=$upd['balance_lock'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 后台拒绝提现账户余额操作
     */
    public function AdminApplyBalance($upd,$num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['balance'] = new_bcadd($upd['balance'],$num);
        $upd['balance_lock'] = new_bcsub($upd['balance_lock'],$num);
        $upd['sign'] = $this->_sign($upd);
        $members['balance']=$upd['balance'];
        $members['balance_lock']=$upd['balance_lock'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }
    /**
     * 账户余额操作
     */
    public function changeBalance($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['balance'] = new_bcadd($upd['balance'], $num);
        }else{
            $upd['balance'] = new_bcsub($upd['balance'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['balance']=$upd['balance'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户锁定余额操作
     */
    public function changeBalanceLock($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['balance_lock'] = new_bcadd($upd['balance_lock'], $num);
        }else{
            $upd['balance_lock'] = new_bcsub($upd['balance_lock'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['balance_lock']=$upd['balance_lock'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }



    /**
     * 账户USDC操作
     */
    public function changeUsdc($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['usdc'] =  new_bcadd($upd['usdc'], $num);
        }else{
            $upd['usdc'] =  new_bcsub($upd['usdc'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['usdc']=$upd['usdc'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户锁定usdc操作
     */
    public function changeUsdcLock($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['usdc_lock'] = new_bcadd($upd['usdc_lock'], $num);
        }else{
            $upd['usdc_lock'] = new_bcsub($upd['usdc_lock'], $num);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['usdc_lock']=$upd['usdc_lock'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户usdc and 锁定操作
     */
    public function changeUsdcAndlock($upd,$num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['usdc'] = bcsub($upd['usdc'], $num,3);
        $upd['usdc_lock'] = bcadd($upd['usdc_lock'], $num,3);
        $upd['sign'] = $this->_sign($upd);
        $members['usdc']=$upd['usdc'];
        $members['usdc_lock']=$upd['usdc_lock'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }


    /**
     * 账户GOC操作
     */
    public function changeGoc($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['goc'] = bcadd($upd['goc'], $num,3);
        }else{
            $upd['goc'] = bcsub($upd['goc'], $num,3);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['goc']=$upd['goc'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户锁定GOC操作
     */
    public function changeGocLock($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['goc_lock'] = bcadd($upd['goc_lock'], $num,2);
        }else{
            $upd['goc_lock'] =bcsub($upd['goc_lock'], $num,2);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['goc_lock']=$upd['goc_lock'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户goc and 锁定操作
     */
    public function changeGocAndlock($upd,$num){
        if(!$this->_verify_sign($upd)) return false;
        $upd['goc'] = bcsub($upd['goc'], $num,2);
        $upd['goc_lock'] = bcadd($upd['goc_lock'], $num,2);
        $upd['sign'] = $this->_sign($upd);
        $members['goc']=$upd['goc'];
        $members['goc_lock']=$upd['goc_lock'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }

    /**
     * 账户chainsScore操作score
     */
    public function changeChainScore($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['chain_score'] = bcadd($upd['chain_score'], $num,3);
        }else{
            $upd['chain_score'] = bcsub($upd['chain_score'], $num,3);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['chain_score']=$upd['chain_score'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }


    /**
     * 账户chainsShareScore操作Share_score
     */
    public function chainsShareScore($upd,$num,$action='in'){
        if(!$this->_verify_sign($upd)) return false;
        if($action=="in"){
            $upd['share_score'] = bcadd($upd['share_score'], $num,3);
        }else{
            $upd['share_score'] = bcsub($upd['share_score'], $num,3);
        }
        $upd['sign'] = $this->_sign($upd);
        $members['share_score']=$upd['share_score'];
        $members['sign']= $upd['sign'];
        return $this->where(array('id' => $upd['id']))->save($members);
    }
    
    /**
     * 用户提现金额变动
     * @param  [type] $num [description]
     * @return [type]      [description]
     */
    public function buy_back($uid,$num,$act = 'in'){
        $condi = array('id' => $uid);
        if($act == 'in'){
            return $this->where($condi)->setInc("buy_back",$num);
        }else{
            return $this->where($condi)->setDec("buy_back",$num);
        }
    }
}