<?php
/**
 * 兑换USDC
 */
namespace Api\Controller;
use Think\Controller;
use Common\Lib\Constants;
class ExchangeController extends BaseController{

    /**
     * [index 兑换订单]
     * @return JSON
     */
    public  function  index(){
        $exchangeList=M('exchange')->field('id,usdc,ctime,sort')->where(array('uid'=>$this->uid,'status'=>0))->select();
        foreach($exchangeList as $key=>$value){
            $count=M('exchange')->where(array('sort'=>array('lt',$value['sort']),'status'=>0))->count();
            $code=mt_rand(0,9);
            $count=bcmul($count,10)-$code;
            if($count<0){
                $count=0;
            }
            $exchangeList[$key]['rank']=$count;
            $exchangeList[$key]['ctime']=date("Y-m-d H:i",$value['ctime']);;
            unset($exchangeList[$key]['sort']);
        }
        $members = D('members');
        $user  = $members->profiles($this->uid,'share_score');
        $data=array(
            'list'=>$exchangeList,
            'share_score'=>$user['share_score']
        );
        succ($this->output($data));
    }

    /**
     * [exchange 兑换]
     * @return JSON
     */
    public  function  exchange(){
        require_check_api('num,paypwd', $this->post);
        //数据检查
        $num   = floatval($this->post['num']);
        if($num < 0 || $num > 50000)err('单笔订单最多兑换50000个USDC');

        //用户余额检查
        $members = D('members');
        $user  = $members->profiles($this->uid);
        if(!$members->checkpaypwd($this->uid,$this->post['paypwd'])){
            err('交易密码错误');
        }
        if($user['chain_score'] < $num) err('链通积分不足');

        $members->startTrans();
        //用户链通积分扣除
        $result = $members->changeChainScore($user,$num,'out');
        $newChainScore=bcsub($user['chain_score'],$num,3);
        $log=array(
            'uid' => $this->uid,
            'changeform' => 'out',
            'subtype' => 3,
            'money' =>$num,
            'ctime' => time(),
            'describes' => '兑换USDC',
            'balance' => $newChainScore,
            'money_type' =>3 //余额
        );
       $LogModel=D('MembersLog');
       $result = $result &&  $LogModel->adds($log);

        //插入订单
        $data = array(
            'uid' => $this->uid,
            'usdc' => $num,
            'chain_score' => $num,
            'ctime'=>NOW_TIME,
        );
        $result = $result && M('exchange')->add($data);
        if($result){
            M()->commit();
            succ('提交成功');
        }else{
            M()->rollback();
            err('网络错误，提交失败');
        }

    }


    /**
     * [exchange 兑换撤回]
     * @return JSON
     */
    public  function  exchangeRecall(){
        require_check_api('id,paypwd', $this->post);

        //用户余额检查
        $members = D('members');
        $user  = $members->profiles($this->uid);
        if(!$members->checkpaypwd($this->uid,$this->post['paypwd'])){
            err('交易密码错误');
        }


        $members->startTrans();
        //获取兑换信息
        $id=$this->post['id'];
        $upd=array('uid'=>$this->uid,'id'=>$id,'status'=>0);
        $exchangeInfo= M('exchange')->where($upd)->find();
        if(empty($exchangeInfo)){
            err('撤回操作失败');
        }

        $result = $members->changeChainScore($user,$exchangeInfo['chain_score'],'in');
        $newChainScore=bcadd($user['chain_score'],$exchangeInfo['chain_score'],3);
        $log=array(
            'uid' => $this->uid,
            'changeform' => 'in',
            'subtype' => 6,
            'money' =>$exchangeInfo['chain_score'],
            'ctime' => time(),
            'describes' => '兑换USDC撤回',
            'balance' => $newChainScore,
            'money_type' =>3 //链通积分
        );
        $LogModel=D('MembersLog');
        $result = $result &&  $LogModel->adds($log);

        $result = $result && M('exchange')->where($upd)->save(array('status'=>-1,'ptime'=>time()));
        if($result){
            M()->commit();
            succ('撤销成功');
        }else{
            M()->rollback();
            err('网络错误，撤销失败');
        }

    }





    public  function  top(){
        require_check_api('id',$this->post);
        $id=$this->post['id'];
        //用户余额检查
        $members = D('members');
        $user  = $members->profiles($this->uid);
        if($user['share_score']<Constants::SHARE_SCORE_EXPEND){
            err('乐享积分不足，每次耗费:'.Constants::SHARE_SCORE_EXPEND.' 颗。');
        }
        M()->startTrans();
        $result=$members->changeShareScore($user,Constants::SHARE_SCORE_EXPEND,'out');
        $newShareScore=bcsub($user['share_score'],Constants::SHARE_SCORE_EXPEND,3);
        $log=array(
            'uid' => $this->uid,
            'changeform' => 'out',
            'subtype' => 4,
            'money' =>Constants::SHARE_SCORE_EXPEND,
            'ctime' => time(),
            'describes' => '顶一顶',
            'balance' => $newShareScore,
            'money_type' =>4 //乐享积分余额
        );
        $LogModel=D('MembersLog');
        $result = $result &&  $LogModel->adds($log);
        $result = $result && M('exchange')->where(array('id'=>$id,'uid'=>$this->uid,'status'=>0))->setInc('sort',1);
        if($result){
            M()->commit();
            succ('顶一顶，成功');
        }else{
            M()->rollback();
            err('网络错误，稍后再试');
        }
    }

    /**
     * 链通积分兑换乐享积分
     * @return [num] [description]兑换乐享积分个数
     */

    public  function exchangeShare(){
        require_check_api('num,paypwd',$this->post);
        //用户余额检查
        $members = D('members');
        $user  = $members->profiles($this->uid);
        if(!$members->checkpaypwd($this->uid,$this->post['paypwd'])){
            err('交易密码错误');
        }
        $num=$this->post['num'];
        $total=round($num/Constants::CHAIN_SCORE_EXPEND,3);
        if($user['chain_score']<$total){
            err('链积分不足');
        }
        M()->startTrans();
        $result=$members->changeChainScore($user,$total,'out');
        $newChainScore=bcsub($user['chain_score'],$total,3);
        $log=array(
            'uid' => $this->uid,
            'changeform' => 'out',
            'subtype' =>8,
            'money' =>$total,
            'ctime' => time(),
            'describes' => '兑入乐享积分',
            'balance' => $newChainScore,
            'money_type' =>3 //链通积分
        );
        $LogModel=D('MembersLog');
        $result = $result &&  $LogModel->adds($log);

        //增加乐享积分
        $userTwo= $members->profiles($this->uid);
        $result= $result &&  $members->changeShareScore($userTwo,$num,'in');
        $newShareScore=bcadd($user['share_score'],$num,3);
        $log=array(
            'uid' => $this->uid,
            'changeform' => 'in',
            'subtype' => 8,
            'money' =>$num,
            'ctime' => time(),
            'describes' => '兑入乐享积分',
            'balance' => $newShareScore,
            'money_type' =>4 //乐享积分余额
        );
        $result = $result &&  $LogModel->adds($log);
        if($result){
            M()->commit();
            succ('兑入成功');
        }else{
            M()->rollback();
            succ('兑入失败');
        }
    }

    /**
     * 余额充值
     * @return [num] [description]兑换链通个数
     */

      public  function recharge(){
          require_check_api('phone,num',$this->post);
          $model=D('members');
          $phone=$this->post['phone'];
          $num=$this->post['num'];
          $touser=$model->where(array('phone'=>$phone))->field('id,rname')->find();
          $total=round($num/Constants::BALANCE_TO_SCORE,3);
          $user=$model->profiles($this->uid);
          if($user['balance']<$total){
              err('余额不足，充值失败');
          }
          if(empty($touser)){
              err('用户未找到');
          }
          $data=array(
              'phone'=>phoneaddstar($phone),
              'rname'=>strval($touser['rname']),
              'num'=>$num
          );
         succ($this->output($data));
      }


        public function recharge_confirm(){
            require_check_api('phone,num,paypwd',$this->post);
            $model=D('members');
            $phone=$this->post['phone'];
            $num=$this->post['num'];
            $touser=$model->where(array('phone'=>$phone))->field('id')->find();
            if(empty($touser)){
                err('用户未找到');
            }
            $total=round($num/Constants::BALANCE_TO_SCORE,3);
            $user=$model->profiles($this->uid);
            if(!$model->checkpaypwd($this->uid,$this->post['paypwd'])){
                err('交易密码错误');
            }
            if($user['balance']<$total){
                err('余额不足，充值失败');
            }

            M()->startTrans();
            $result=true;
            $result= $result &&  $model->changeBalance($user,$total,'out');
            $newBalance=bcsub($user['balance'],$total,3);
            $log=array(
                'uid' => $this->uid,
                'changeform' => 'out',
                'subtype' => 7,
                'money' =>$total,
                'ctime' => time(),
                'describes' => '余额充值链通积分',
                'balance' => $newBalance,
                'extends'=> $touser['id'],
                'money_type' =>1//余额
            );
            $LogModel=D('MembersLog');
            $result = $result &&  $LogModel->adds($log);

            //受赠用户账户数据
            $touser=$model->profiles($touser['id']);
            $result= $result &&  $model->changeChainScore($touser,$num,'in');
            $newToUserChainScore=bcadd($touser['chain_score'],$num,3);
            $log=array(
                'uid' => $touser['id'],
                'changeform' => 'in',
                'subtype' => 7,
                'money' =>$num,
                'ctime' => time(),
                'describes' => '余额充值链通积分',
                'balance' => $newToUserChainScore,
                'extends'=>$this->uid,
                'money_type' =>3//余额
            );
            $result = $result &&  $LogModel->adds($log);
            if($result){
                M()->commit();
                succ('充值成功');
            }else{
                M()->rollback();
                err('充值失败');
            }
        }











}
?>