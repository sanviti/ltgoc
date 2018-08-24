<?php 
namespace Api\Controller;
class BankController extends BaseController{
    /**
     * 银行卡列表
     */
    public function banklist(){
        $bankCardModel = D("bank");
        $arr = $bankCardModel->banklist();
        succ($this->output($arr));
    } 
      
     /**
     * 我的银行卡
     */
    public function bankcard(){
       $bankCardModel = D("bank");
       $condi['uid'] = $this->uid;
       $list = $bankCardModel->lists($condi);
       if(empty($list)){
           $list = array();
       }
       $data = array(
           'list'=>$list
       ); 
       succ($this->output($data));
   }
    /**
     * 添加银行卡
     */
    public function addBankCard(){
        require_check_api("truename,bankname,card,branchname", $this->post);
        $Model = D("bank");
        $data['uid'] = $this->uid;
        $data['truename'] = addslashes($this->post['truename']);
        $data['card'] = addslashes($this->post['card']);
        $data['bankname'] = addslashes($this->post['bankname']);
        $data['branchname'] = addslashes($this->post['branchname']);
        $data['dateline'] = NOW_TIME;
        if($Model->add_banks($data)){
            succ("添加成功");
        }else{
            err("添加失败");
        }
    }
    
    /**
     * 修改银行卡
     */
    public function editBankCard(){
        require_check_api("cardid,truename,bankname,card,branchname", $this->post);
        $Model = D("bank");
        $condi['cardid'] = intval($this->post['cardid']);
        $condi['uid'] = $this->uid;
        $data['truename'] = addslashes($this->post['truename']);
        $data['card'] = addslashes($this->post['card']);
        $data['bankname'] = addslashes($this->post['bankname']);
        $data['branchname'] = addslashes($this->post['branchname']);
        $data['dateline'] = NOW_TIME;
        if($Model->edit_banks($condi,$data)){
            succ("修改成功");
        }else{
            err("修改失败");
        }
   }
   
   /**
    * 删除银行卡
    */
   public function delBankCard(){
       require_check_api("cardid", $this->post);
       $condi ['uid']    = $this->uid;
       $condi ['cardid'] = addslashes($this->post['cardid']);
       $bankCardModel = D("bank");
       if($bankCardModel->del_bank($condi)){
           succ("删除成功");
       }else{
           err("删除失败");
       }
   }
   
   /**
    * 设置默认银行卡
    * @param $cardid  记录ID
    */
   public function defBankCard(){
       require_check_api("cardid", $this->post);
       $condi['uid'] = $this->uid;
       $bankCardModel = D("bank");
       $bankCardModel->where($condi)->save(array('def'=>0));
       $condi ['cardid'] = intval($this->post['cardid']);
       if($bankCardModel->setdef($condi)){
           succ("设置成功");
       }else{
           err("设置失败");
       }
   }
}

?>