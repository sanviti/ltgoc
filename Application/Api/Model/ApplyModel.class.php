<?php 
namespace Api\Model;
use Think\Model;
class ApplyModel extends Model{
    protected $tableName = 'applycash';
    protected $tablePrefix = 'lt_';
    //回购银行卡添加类型
    private $bankIcos = array(
        array("bankname"=>"中国工商银行","ico"=>"/Public/images/bankicos/gongshang.png",'backgroud'=>'/Public/images/bankicos/bg_gongshang.png'),
        array("bankname"=>"中国农业银行","ico"=>"/Public/images/bankicos/nongye.png",'backgroud'=>'/Public/images/bankicos/bg_nongye.png'),
        array("bankname"=>"中国建设银行","ico"=>"/Public/images/bankicos/jianshe.png",'backgroud'=>'/Public/images/bankicos/bg_jianshe.png'),
        array("bankname"=>"中国银行","ico"=>"/Public/images/bankicos/zhongguoyinhang.png",'backgroud'=>'/Public/images/bankicos/bg_zhonghang.png'),
        array("bankname"=>"中国交通银行","ico"=>"/Public/images/bankicos/jiaotong.png",'backgroud'=>'/Public/images/bankicos/bg_jiaotong.png'),
        array("bankname"=>"中信银行","ico"=>"/Public/images/bankicos/zhongxin.png",'backgroud'=>'/Public/images/bankicos/bg_zhongxin.png'),
        array("bankname"=>"中国光大银行","ico"=>"/Public/images/bankicos/guangda.png",'backgroud'=>'/Public/images/bankicos/bg_guangda.png'),
        array("bankname"=>"华夏银行","ico"=>"/Public/images/bankicos/huaxia.png",'backgroud'=>'/Public/images/bankicos/bg_huaxia.png'),
        array("bankname"=>"招商银行","ico"=>"/Public/images/bankicos/zhaoshang.png",'backgroud'=>'/Public/images/bankicos/bg_zhaoshang.png'),
        array("bankname"=>"兴业银行","ico"=>"/Public/images/bankicos/xingye.png",'backgroud'=>'/Public/images/bankicos/bg_xingye.png'),
        array("bankname"=>"广发银行","ico"=>"/Public/images/bankicos/guangfa.png",'backgroud'=>'/Public/images/bankicos/bg_guangfa.png'),
        array("bankname"=>"平安银行","ico"=>"/Public/images/bankicos/pingan.png",'backgroud'=>'/Public/images/bankicos/bg_pingan.png'),
        array("bankname"=>"北京银行","ico"=>"/Public/images/bankicos/beijingyinhang.png",'backgroud'=>'/Public/images/bankicos/bg_beijing.png'),
        array("bankname"=>"上海浦东发展银行","ico"=>"/Public/images/bankicos/pufa.png",'backgroud'=>'/Public/images/bankicos/bg_pufa.png'),
        array("bankname"=>"中国邮政储蓄银行","ico"=>"/Public/images/bankicos/youzheng.png",'backgroud'=>'/Public/images/bankicos/bg_youzheng.png'),
        array("bankname"=>"北京农商银行","ico"=>"/Public/images/bankicos/nongshang.png",'backgroud'=>'/Public/images/bankicos/bg_nongshang.png'),
        array("bankname"=>"中山农商银行","ico"=>"/Public/images/bankicos/zsns.png",'backgroud'=>'/Public/images/bankicos/bg_zsns.png'),
        array("bankname"=>"东莞银行","ico"=>"/Public/images/bankicos/dg.png",'backgroud'=>'/Public/images/bankicos/bg_dg.png'),
    );
    
    /**
     * 提现
     */
    public function adds($data){
        return $this->add($data);
    }
    
    /**
     * 提现记录
     */
    public function applyLog($page,$field,$where,$limit){
        $list =  $this->where($where)->field($field)->page($page)->limit($limit)->select();
        foreach ($list as $k=>$v){
            $list[$k]['ctime'] = date("Y-m-d H:i:s",$v['ctime']);
        }
        if(empty($list)){
            $list = array();
        }
        return $list;    
    }
    
    /**
     * 提现总额
     */
    public function apply_succ($uid){
        $where['uid'] = $uid;
        $where['state'] = 1;
        return $this->where($where)->sum("money");
    }
    

    /**
     * 银行图标
     * @Author 刘晓雨    2017-12-05
     * @param  str $bankname  银行名称
     */
    private function _bankIco($bankname){
        $ico = '';
        foreach($this->bankIcos as $bank){
            if($bankname == $bank['bankname']){
                $ico = $bank['ico'];
                break;
            }
        }
        return $ico;
    }
    
    /**
     * c2c订单详情
     */
    public function applydetail($orderid){
        return $this->field("ordersn,money,state,remark")->where(array("id"=>$orderid))->find();
    }
}

?>