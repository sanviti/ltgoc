<?php 
/**
 * 兑换USDC
*/
namespace Api\Controller;
use Think\Controller;
use Common\Lib\Constants;
class RechargeController extends BaseController{
    /**
     * 转账充值链通积分
     */
    public function transfer(){
        require_check_api('name,cardid,num', $this->post);
        $num = number_format($this->post['num'], 0, '','');
        $username = $this->post['name'];
        $cardid = $this->post['cardid'];
        $comBank = D("Combank")->combank();
        if($num < 100)err('最低充值100');
        if($num % 100 > 0) err('充值数量要是 100 的倍数');
        $ordersn=str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT).substr(time(),-4);;
        $array = array(
            'num'=>$num,
            'username'=>$username,
            'cardid'=>$cardid,
            'comcard'=>$comBank['card'],
            'combank'=>$comBank['bank'],
            'subbank'=>$comBank['subbank'],
            'truename'=>$comBank['truename'],
            'ordersn'=>$ordersn,
        );
        succ($this->output($array));
        //succ($array);
    }
    
    /**
     * 执行转账充值链通积分
     */
    public function to_transfer(){
        require_check_api('num,username,cardid,ordersn', $this->post);
        $num = number_format($this->post['num'], 0, '','');
        $username = $this->post['username']; 
        $cardid = $this->post['cardid'];
        $ordern = $this->post['ordersn'];
        
        if($num < 100)err('最低充值100');
        if($num % 100 > 0) err('充值数量要是 100 的倍数');
        
        $userbank = D("Bank")->get_bankinfo(array("cardid"=>$cardid));
        $array = array(
            'uid'=>$this->uid,
            'username'=>$username,
            'cardid'=>$userbank['card'],
            'bankname'=>$userbank['bankname'],
            'type'=>1,
            'ordersn'=>$ordern,
            'ctime'=>time(),
            'num'=>$num
        ); 
        $rtn = D("Recharge")->adds($array);
        if($rtn){
            succ("提交成功");
        }else{
            err("提交失败");
        }  
    }
    
    
    /**
     * 转账充值订单
     */
    public function transfer_order(){
        $data = M("recharge")->field("num,status,ctime,type")->where(array("uid"=>$this->uid))->select();
        foreach ($data as $k=>$v){
            $data[$k]['ctime'] = date("Y-m-d H:i:s",$v['ctime']);
            $data[$k]['action'] = "本机充值";
        }
        
        $array = array(
            'list'=>$data
        );
        succ($this->output($array));
        //succ($array);
        
    }
}
?>