<?php 
namespace Api\Model;
use Think\Model;
class BankModel extends Model{
    protected $tableName = 'bankcard';
    protected $tablePrefix = 'lt_';
    //银行卡默认背景
    private $bankBackgroudDefault = '/Public/images/bankicos/bg_qita.png';
    
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
     * 我的银行卡列表
     */
    public function lists($condi){
        $list = $this->field("cardid,bankname,card,truename,branchname")->where($condi)->order("cardid DESC")->select();
        $list = $list ? $list : array();
        foreach($list as $k => $v){
            $lists[$k]['ico'] = $this->_bankIco(stripslashes($v['bankname']));
            $lists[$k]['cardid'] = $v['cardid'];
            $lists[$k]['card'] = $v['card'];
            $lists[$k]['branchname'] = $v['branchname'];
            $lists[$k]['truename'] = $v['truename'];
            //过滤银行卡号
            $lists[$k]['cooked_card'] = $this->_handlCard(stripslashes($v['card']));
            $lists[$k]['backgroud'] = $this->_bankBackgroud(stripslashes($v['bankname']));
            $lists[$k]['bankname'] = stripslashes($v['bankname']);
        }
        return $lists;
    }
    
    /**
     * 添加银行卡
     */
    public function add_banks($data){
        return $this->add($data);
    }
    
    /**
     * 修改银行卡
     */
    public function edit_banks($condi,$data){
        return $this->where($condi)->save($data);
    }
    
    /**
     * 删除银行卡
     */
    public function del_bank($condi){
        return $this->where($condi)->delete();
    }
    
    /**
     * 设置默认银行卡
     */
    public function setdef($condi){
        return $this->where($condi)->save(array("def"=>1)); 
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
     *银行卡号加*
     */
    private function _handlCard($card){
        $newcard = substr($card,-4);
        $newcard = "**** **** **** ".$newcard;
        return $newcard;
    }

    /**
     * 银行图标
     */
    private function _bankBackgroud($bankname){
        $backgroud = '';
        foreach($this->bankIcos as $bank){
            if($bankname == $bank['bankname']){
                $backgroud = $bank['backgroud'];
                break;
            }
        }
        if(empty($backgroud)){
            $backgroud = $this->bankBackgroudDefault;
        }
        return $backgroud;
    }
    
    /**
     * 银行卡
     */
    public function banklist(){
        $arr = $this->bankIcos;
        return $arr;
    }
    
    /**
     * 查询某个银行卡信息
     */
    public function get_bankinfo($condi){
        return $this->where($condi)->find();
    }
    
    
    
}

?>