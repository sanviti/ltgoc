<?php
/**
 * 提现控制器
 */
namespace DataAdmin\Controller;
use Think\Controller;
use Common\Lib\Constants;
class StatisticsController extends BaseController {
    public function index(){
        //goc总数
        $goc = D("Members")->sum("goc");
        $this->assign("goc",$goc);
        
        //冻结goc总数
        $goc_lock = D("Members")->sum("goc_lock");
        $this->assign("goc_lock",$goc_lock);
        
        //usdc总数
        $usdc = D("Members")->sum("usdc");
        $this->assign("usdc",$usdc);
        
        //冻结USDC总数
        $usdc_lock = D("Members")->sum("usdc_lock");
        $this->assign("usdc_lock",$usdc_lock);
        
        //余额总数
        $balance = D("Members")->sum("balance");
        $this->assign("balance",$balance);
        
        //冻结余额总数
        $balance_lock = D("Members")->sum("balance_lock");
        $this->assign("balance_lock",$balance_lock);
        
        //链通积分总数
        $chain_score = D("Members")->sum("chain_score");
        $this->assign("chain_score",$chain_score);
        
        //链通积分总数
        $share_score = D("Members")->sum("share_score");
        $this->assign("share_score",$share_score);
        
        //平台现有用户
        $members = D("Members")->count();
        $this->assign('memcount',$members);
        
        //提现已打款
        $applyout = D("Applycash")->where(array("state"=>1))->sum("money");
        $this->assign("applyout",$applyout);
        
        //提现未打款
        $applying = D("Applycash")->where(array("state"=>0))->sum("money");
        $this->assign("applying",$applying);
        
        $start = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $end = mktime(24,0,0,date('m'),date('d'),date('Y'));
        //今日提现打款
        $todaywhere['ptime'] = array(array("EGT",$start),array("ELT",$end));
        $todaywhere['state'] = array("eq",1);
        $todayApply = D("Applycash")->where($todaywhere)->sum("money");
        $this->assign("todayApply",$todayApply);
        
        //今日提现中
        $todaywhere2['ctime'] = array(array("EGT",$start),array("ELT",$end));
        $todaywhere2['state'] = array("eq",0);
        $todayApplying = D("Applycash")->where($todaywhere2)->sum("money");
        $this->assign("todayApplying",$todayApplying);


        //买入预约统计
        $moveBuyList=M('trading_moveup')->group('price')->where(array('order_type'=>1,'status'=>0))->field('price,sum(num) as num')->select();
        $this->assign('moveBuyList',$moveBuyList);
        //卖出预约统计
        $moveSellList=M('trading_moveup')->group('price')->where(array('order_type'=>2,'status'=>0))->field('price,sum(num) as num')->select();
        $this->assign('moveSellList',$moveSellList);
        $this->display();
    }
}
?>