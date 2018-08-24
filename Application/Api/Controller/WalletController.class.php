<?php
/**
 * 我的资产
 */
namespace Api\Controller;
use Think\Controller;
use Think\Model;

class WalletController extends BaseController{

    //钱包首页
    public function index(){
        $member = D('members')->profiles($this->uid,'goc,usdc,balance,chain_score,share_score');
        $depInfo = M('membersDep')->field('buy_cny,sell_cny')->where('uid = %d', $this->uid)->find(); ;

        //成本
        $member['cost']=$depInfo['buy_cny'];
        //今日价格 //市值
        $priceModel=D('TradingPrice');
        $TradingPrice = $priceModel->getPrice();
        $member['markeyVa']=bcmul($member['goc'],$TradingPrice['price'],3);
        //盈亏
        $total=bcadd($depInfo['sell_cny'],$member['markeyVa'],3);
        $member['gain']=bcsub($total,$depInfo['buy_cny'],3);
        succ($this->output($member));
    }

    /**
     * 账单明细
     *1余额 2usdc 3链通积分 4乐享积分
     */
    public function bill(){
        //积分类型
        require_check_api('money_type',$this->post);
        $money_type = $this->post['money_type'];
        $condi['money_type'] = $money_type;
        $condi['uid']  = $this->uid;
        $page = I('p/d');
        $page=empty($page)?1:$page;
        $list = M('userLog')
                ->field('money,balance,changeform,FROM_UNIXTIME(ctime, "%Y-%m-%d %H:%i") AS ctime,
                    case subtype 
                    when 1 then "C2C卖出"
                    when 2 then "C2C申请驳回"
                    when 3 then "兑换USDC"
                    when 4 then "跳一跳"
                    when 5 then "GOC认购充值"
                    when 6 then "兑换USDC撤回"
                    when 7 then "充值链通积分"
                    when 8 then "兑入乐享积分"
                    when 9 then "推荐奖励"
                    when 11 then "交易"
                    when 14 then "推荐奖励"
                    when 16 then "社区分红"
                    when 22 then "交易"
                    when 51 then "交易"
                    when 52 then "交易"
                    else "其他" end AS subtype'
                    )
                ->where($condi)->page($page)->order('ctime DESC')->limit(10)->select();
        foreach($list as $key=>$value){
            if($value['changeform']=="in"){
                $list[$key]['num'] = "+".$value['money'];
            }else{
                $list[$key]['num'] = "-".$value['money'];
            }
            unset($list[$key]['money']);
            unset($list[$key]['changeform']);
        }
       $data=array(
           'list'=>$list
       );
        succ($this->output($data));
    }

    

}