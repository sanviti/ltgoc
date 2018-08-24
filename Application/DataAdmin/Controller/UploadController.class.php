<?php
namespace DataAdmin\Controller;
use Think\Controller;
use Think;
class UploadController extends Controller {

    public function editor() {
        $upload = new \Think\Upload();// 实例化上传类
        $upload->maxSize   =     3145728 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型     
        $upload->savePath  = '/images/';
        $info=$upload->upload();
        echo json_encode( array( 
         'url'=>$info['upfile']['savepath'].'/'.$info['upfile']['savename'],                           
         'original'=>$info['upfile']['savepath'].'/'.$info['upfile']['savename'],       
         'state'=>'SUCCESS',       
        )); 
    }

    public function uploadImg() {
        $upload = new \Think\Upload();// 实例化上传类
        $key = array_shift(array_keys($_FILES));
        $upload->maxSize   =     2097152 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型     
        $upload->savePath = '/images/';
        $info=$upload->upload();
        if($upload->getError()){
            $this->error($upload->getError());
        }else{
            $this->success('上传成功','/Uploads'.$info[$key]['savepath'].$info[$key]['savename']); 
        }
    }
    
    public function uploadexcel() {
        $upload = new \Think\Upload();// 实例化上传类
        $upload->maxSize   =     1024*300 ;// 设置附件上传大小
        $upload->exts      =     array('xls', 'xlsx');// 设置附件上传类型
        $upload->savePath  = '/excel/';
        $info=$upload->upload();
        if(!$info){
            $this->ajaxReturn(array('state'=>0, 'msg' => $upload->getError()),'JSON');
        }else{
            $this->ajaxReturn(array('state'=>'SUCCESS', 'url' => $info['file']['savepath'].$info['file']['savename']),'JSON');
        }
    }
    
    /**
     * 批量处理提现
     */
    public function readexl() {
        $url = I("url");
        header("Content-Type:text/html;charset=utf-8");
        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
        $sheet=0;
        $filePath = "./Uploads".$url;
        if(empty($filePath) or !file_exists($filePath)){die('file not exists');}
        $PHPReader = new \PHPExcel_Reader_Excel2007();        //建立reader对象
        if(!$PHPReader->canRead($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if(!$PHPReader->canRead($filePath)){
                echo 'no Excel';
                return ;
            }
        }
        $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
        $currentSheet = $PHPExcel->getSheet($sheet);        //**读取excel文件中的指定工作表*/
        $allColumn = $currentSheet->getHighestColumn();        //**取得最大的列号*/
        $allRow = $currentSheet->getHighestRow();        //**取得一共有多少行*/
        $data = array();
        for($rowIndex=1;$rowIndex<=$allRow;$rowIndex++){        //循环读取每个单元格的内容。注意行从1开始，列从A开始
            for($colIndex='A';$colIndex<=$allColumn;$colIndex++){
                $addr = $colIndex.$rowIndex;
                $cell = $currentSheet->getCell($addr)->getValue();
                if($cell instanceof PHPExcel_RichText){ //富文本转换字符串
                    $cell = $cell->__toString();
                }
                $data[$rowIndex][$colIndex] = $cell;
            }
        }
        $result = true;
        M()->startTrans();
        foreach ($data as $key => $value) {
            if($key > 1) {
                if($value['A'] != NULL) {
                    if($info = M('applycash')->where(array('id'=>$value['A']))->field('state')->find()) {
                        if($info['state'] == 0) {
                            $da = array(
                                'state' => 1,
                                'ptime' => time(),
                                'mgrid' => $value['S'],
                            );
                            $result = $result && M('applycash')->where(array('id'=>$value['A']))->save($da);
                            if(!$result) {
                                break;
                            }
                        }
                    }
    
                }
            }
        }
    
        if($result) {
            M()->commit();
            succ("处理成功");
        } else {
            M()->rollback();
            err("处理失败");
        }
    }
	
	 /**
     * 批量充值goc
     */
    public function add_goc(){	
    	$url = I("url");
        header("Content-Type:text/html;charset=utf-8");
        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
        $sheet=0;
        $filePath = "./Uploads".$url;
        if(empty($filePath) or !file_exists($filePath)){die('file not exists');}
        $PHPReader = new \PHPExcel_Reader_Excel2007();        //建立reader对象
        if(!$PHPReader->canRead($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if(!$PHPReader->canRead($filePath)){
                echo 'no Excel';
                return ;
            }
        }
        $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
        $currentSheet = $PHPExcel->getSheet($sheet);        //**读取excel文件中的指定工作表*/
        $allColumn = $currentSheet->getHighestColumn();        //**取得最大的列号*/
        $allRow = $currentSheet->getHighestRow();        //**取得一共有多少行*/
        $data = array();
        for($rowIndex=1;$rowIndex<=$allRow;$rowIndex++){        //循环读取每个单元格的内容。注意行从1开始，列从A开始
            for($colIndex='A';$colIndex<=$allColumn;$colIndex++){
                $addr = $colIndex.$rowIndex;
                $cell = $currentSheet->getCell($addr)->getValue();
                if($cell instanceof PHPExcel_RichText){ //富文本转换字符串
                    $cell = $cell->__toString();
                }
                $data[$rowIndex][$colIndex] = $cell;
            }
        }
        $result = true;
        M()->startTrans();
        if(!empty($data)){
            //检查该社区用户是否存在
//            $vip_where['phone'] = $data[4][A];
//            $vip = D("Members")->normalMember($vip_where);
//            if(empty($vip)) err("充值失败，该社区经理人不存在！");
            //定义成功充值总数
            $succNum = 0;
            foreach ($data as $key => $value) {
                if($key > 5) {
                    if($value['A'] != NULL) {
                        //检查用户是否存在
                        $member_where['phone'] = $value['A'];
                        $member = D("Members")->normalMember($member_where);
                        if($member){
                            //1.加goc
                            $result = $result && D("Api/Members")->changeGoc($member,$value['B'],'in');                                                                                                                                                
                            //2.dep表加成本
                            $member_dep = M("members_dep")->field("buy_cny")->where(array("uid"=>$member['id']))->find();
                            $addNum = new_bcmul($value['B'],0.5);
                            $addCny['buy_cny'] = new_bcadd($member_dep['buy_cny'],$addNum);
                            $result = $result && M("members_dep")->where(array("uid"=>$member['id']))->save($addCny);
                            //3.加充值记录
                            $balance = new_bcadd($member['goc'],$value['B']);
                            $addLog = array(
                                'phone'  =>$value['A'],
                                'num'    =>$value['B'],
                                'balance'=>$balance,
                                'uid'    =>$member['id'],
                                'ctime'  =>time(),
                            );
                           $result = $result && M("recharge_list")->add($addLog);
                            //4.加充值日志
                            $logdata = array(
                                'uid' => $member['id'],
                                'changeform' => 'in',
                                'subtype' => 5,
                                'money' => $value['B'],
                                'ctime' => time(),
                                'describes' => '您认购的'.$value['B'].'GOC已到账',
                                'balance' => $balance,
                                'money_type'=>5
                            );
                            $result = $result && D("Api/MembersLog")->adds($logdata);
                            //5.买入表里加数据
                            $buyLog = array(
                                'transno'=>tradingOrderSN($data['order_type'] == 1 ? 'B' : 'S'),
                                'price'=>0.5,
                                'buy_num'=>$value['B'],
                                'sell_num'=>0,
                                'uid'=>$member['id'],
                                'status'=>0,
                            );
                           $result = $result && M("trading_order_sell")->add($buyLog);
                           //6.减系统goc，加用户goc
                           $gocInfo = M("goc")->where(array("id"=>1))->find();
                           $change = array(
                              'sys_goc'=>new_bcsub($gocInfo['sys_goc'],$value['B']),
                              'user_goc'=>new_bcadd($gocInfo['user_goc'],$value['B']),  
                              'last_time'=>time(),  
                           );
                           $result = $result && M("goc")->where(array("id"=>1))->save($change);
                           if(!$result) {
                               break;
                           }else{
                               $succNum+=$value['B'];
                           }
                        }//该会员存在
                    }//value A neq null
                }//key>4
            }//foreash
            //5.社区经理加链通积分
//			$chainNum = $succNum*0.5;
//			$addChain = new_bcmul($chainNum,0.2);
//			$vipInfo  = D("Members")->normalMember(array('phone'=>$data[4][A]));
//			$result = $result && D("Api/Members")->changeChainScore($vipInfo,$addChain,'in');
//			//6.加充值日志
//			$nowChain = new_bcadd($vip['chain_score'], $addChain);
//			$logdata2 = array(
//			    'uid' => $vipInfo['id'],
//			    'changeform' => 'in',
//			    'subtype' => 9,
//			    'money' => $addChain,
//			    'ctime' => time(),
//			    'describes' => '社区奖励',
//			    'balance' => $nowChain,
//			    'money_type'=>3
//			);
//			$result = $result && D("Api/MembersLog")->adds($logdata2);
        }
        if($result) {
            M()->commit();
            succ("处理成功");
        } else {
            M()->rollback();
            err("处理失败");
        }
    }
}
