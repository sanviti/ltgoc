<?php
/**
 * 交易中心
 */
namespace Api\Controller;
use Think\Log;
use Think\Controller;
use Think\Model;
use Common\Common\Lib\Constants;

class DealController extends Controller{
    //交易中心首页
    public function index(){
        //7天交易数据
        $start = mktime(0,0,0, 5, 5, 2018);
        $today = time();
        $currID = floor(($today-$start)/3600/24) + 1;

        $date_list = array();
        $price_list = array();
        for($i = 6; $i >= 0; $i--){
            $time = mktime(0,0,0, date('m'), date('d') - $i, date('Y'));
            $row = M('price')->where(array('id' => $currID - $i))->find();
            $row = $row == null ? array('price' => 0.00) : $row;
            array_push($price_list, $row['price']);
            array_push($date_list, date('m-d',$time));
            if($i == 0){
                $todayPrice = $row['price'];
            }
        }

        //今日
        $startTime = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $map = "(status = 5 OR status = 101) AND (confirm_time > {$today})";
        $liang = M('orders')->cache(true, 300)->where($map)->sum('opc');
        $liang = $liang ? $liang : 0;
        
        $fu = number_format((($todayPrice - $price_list[5]) / $price_list[5]) * 100, '0', '.', '');
        $fu = $fu > 0 ? '+'.$fu."%" : $fu . "%";
        $data = array(
            'date_list' => $date_list,
            'price_list' => $price_list,
            'price' => number_format($todayPrice, 2, '.', ''),
            'cny' => bcmul($todayPrice, 6.5, 2),
            'todayH' => number_format($todayPrice, 2, '.', ''),
            'todayL' => number_format($todayPrice, 2, '.', ''),
            'liang' => number_format($liang, 0, '.', ''),
            'fu' => $fu,
        );
        succ($data);
    }

    //订单
    public function orders(){
        $condi['od.status'] = array('in', '1,2');
        $order_type = I('order_type/d') == 1 ? 1 : 2;
        $condi['type'] = $order_type;
        $phone = I('phone/s');
        if($phone){
            $where['mb1.phone'] = $phone;
            $where['mb2.phone'] = $phone;
            $where['_logic'] = 'or';
            $condi['_complex'] = $where;
        }
        
        //页码
        $page = I('page/d');
        $orderModel = M('orders');
        $memberModel = D('members');

        $list = $orderModel->alias('od')
                ->field('od.id,od.type,cast(od.opc as decimal(9,2)) as opc,cast(od.price as decimal(9,3)) as price,cast(od.total_usd as decimal(9,3)) as total_usd,od.order_sn,od.buy_uid,od.sell_uid')
                ->join('LEFT JOIN data_members mb1 ON mb1.id = od.buy_uid')
                ->join('LEFT JOIN data_members mb2 ON mb2.id = od.sell_uid')
                ->where($condi)->page($page)->order('id DESC')->limit(5)->select();

        foreach($list as &$item){
            if($item['type'] == 1){
                $map = array('id' => $item['buy_uid']);
                $member = $memberModel->field('0 as chengjiao, 0 as haoping, nickname, headimg')->where($map)->find();
            }else{
                $map = array('id' => $item['sell_uid']);
                $member = $memberModel->field('0 as chengjiao, 0 as haoping, nickname, headimg')->where($map)->find();
            }
            $item['chengjiao'] = $member['chengjiao'];
            $item['haoping']  = $member['haoping'];
            $item['nickname'] = $member['nickname'];
            $item['total'] = 
            $item['headimg']  = $member['headimg'] ? $member['headimg'] : '/Public/Wap/img/headimg_default.jpg';
            unset($item['buy_uid']);
            unset($item['sell_uid']);
        }
        $data['list'] = empty($list) ? array() : $list;
        $data['page'] = $page + 1;
        succ($data);
    }
}