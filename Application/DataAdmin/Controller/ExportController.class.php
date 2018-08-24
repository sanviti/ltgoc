<?php 
namespace DataAdmin\Controller;
header("Content-Type:text/html;charset=utf-8");
class ExportController extends BaseController{
    //提现
    public function apply_export(){
        $condi = array();
        $rname = I("rname");//开户人
        if($rname){
            $condi['rname']=$rname;
        }
        $sTime = I("btime");//申请时间
        $eTime = I("etime");
        if($sTime && $eTime){
            $condi['ctime'] = array(array("EGT",strtotime($sTime)),array("ELT",strtotime($eTime)));
            
        }elseif($sTime){
            $condi['ctime'] = array("EGT",strtotime($sTime));
            
        }elseif($eTime){
            $condi['ctime'] = array("ELT",strtotime($eTime));
        }

        $state  = I("state");
        if($state != ''){
            $condi['state'] = $state;
        }
        //操作时间
        $odate = I('ctime');
        if($odate){
            $condi['ptime'] = array(array("EGT",strtotime($odate)),array("ELT",strtotime($odate.' 23:59:59')));
        }
        //用户ID
        $userid  = I("userid");
        if($userid != ''){
            $condi['userid'] = $userid;
        }
        
        $data = M("applycash")->alias("a")->where($condi)
                ->field("a.id,a.money,a.account,a.disburse,
                         a.cardid,a.rname,m.rname as names,a.mgrid,m.userid,a.ctime,a.state,a.ptime")
                ->join("left join lt_members as m on a.uid=m.id")
                ->select();
        foreach ($data as $k=>$v){
            $admin = M("admin")->field("username")->where(array("id"=>$v['mgrid']))->find();
            $data[$k]['mgrid'] = $admin['username'];
            if($v['state']==0){
                $data[$k]['state']="未处理";
            }elseif ($v['state']==1){
                $data[$k]['state']="已打款";
            }elseif ($v['state']==-1){
                $data[$k]['state']="已拒绝";
            }
            $data[$k]['ctime'] = date("Y-m-d H:i:s",$v['ctime']);
            $data[$k]['ptime'] = date("Y-m-d H:i:s",$v['ptime']);
        }
        $head = array(
            '编号', 
            '提现金额',
            '手续费',
            '支给金额',
            '提现卡号',
            '开户名',
            '用户名',
            '操作人',
            '用户编号',
            '申请时间',
            '处理状态',
            '处理时间',
        );
        $filename = "申请提现";
        $this->_ToExcel($filename,$head,$data);
    }
    /**
     * 普通导出
     */
    public function applycash(){
        $startid = intval(I('startid'));
        $endid   = intval(I('endid'));
        $sendTime = I('subtime');
        empty($startid) && exit;
        empty($endid) && exit;      
        $data = array();
        $where = 'ap.state = 0 AND ap.id >= '. $startid .' AND ap.id <= '. $endid;
        //申请时间
        if(!empty($sendTime)){
            $send_start = strtotime($sendTime.' 00:00:00');
            $send_end = strtotime($sendTime.' 23:59:59');
            $where.=' AND ctime BETWEEN '.$send_start.' and  '.$send_end;
        }
        //,adm.username as adminname,adm.user as admin
        $rows = M("applycash")->alias("ap")
                ->field('ap.*,m.userid,m.phone,m.rname')
                ->join("left join lt_members as m on m.id = ap.uid")
                ->where($where)->select();
        $lastrows = end($rows);
        $endid = $lastrows['id'];
        $filename = "申请提现表ID[{$startid}-{$endid}]".date('H_i_s');
        $head = array(
            '编号',
            '付方账号',
            '金额上限',
            '生效日期',
            '失效日期',
            '支票权限',
            '授权使用人',
            '收方信息填写类型',
            '收方账号',
            '收方户名',
            '用户姓名/名称',
            '汇路类型',
            '收方行名称',
            '收方行行号',
            '收方行地址',
            '附言',
            /* '收款人手机号码', */
            '操作人',
            '用户编号',
            '手续费',
            '申请时间',
        );
        
        foreach($rows as $k=>$vo){
            $admin = M("admin")->where(array("id"=>$vo['mgrid']))->find();
            $rows[$k]['adminname'] = $admin['username'];
            $rows[$k]['admin'] = $admin['user'];
            switch ($vo['bank']) {
                case '招商银行':
                    $way = '招商银行';
                    break;
                case '中国建设银行':
                case '中国工商银行':
                case '中国农业银行':
                case '中国银行':
                    $way = '他行实时';
                    break;
                default:
                    $way = '他行普通';
                    break;
            }
            $adminname = $vo['adminname'];
            if (empty($adminname)){
                $adminname = '无';
            }
        
            $temp = array(
                $vo['id'],
                "110929631010901 ",
                $vo['disburse'],
                date('Ymd'),
                date('Ymd',strtotime('+1 month')),
                '可支付、不可转让',
                '百壹齐领军',
                '预先录入(支付时不可修改)',
                $vo['cardid']." ",
                $vo['rname'],
                $vo['name'],
                $role,
                $way,
                $vo['bank'],
                '',
                $vo['subbranch'],
                '代付',
                /* $vo['phone']." ", */
                $adminname,
                $vo['userid'],
                $vo['account']." ",
                date('Y-m-d H:i:s',$vo['ctime'])." ",
            );
            array_push($data,$temp);
            unset($temp);
        
        }
        $this->_ToExcel($filename,$head,$data);
    }
    /**
     * nc导出
     */
    public function applycash_nc(){
        $startid = intval(I('startid'));
        $endid   = intval(I('endid'));
        empty($startid) && exit;
        empty($endid) && exit;
        $where = 'ap.state = 0 AND ap.id >= '. $startid .' AND ap.id <= '. $endid;
        $rows = M('applycash')->alias('ap')
                ->field('ap.*,m.phone')
                ->join('LEFT JOIN lt_members m ON m.id = ap.uid')
                ->where($where)->select();
        $lastrows = end($rows);
        $endid = $lastrows['id'];
        $filename = "NC系统-申请提现表ID[{$startid}-{$endid}]".date('H_i_s');
        $head = array(
            '员工编码',
            '员工身份证号码',
            '员工账户名',
            '收款账户',
            '收款银行名称',
            '币种(可空）',
            '工资金额(原币)',
            '摘要',
            '用途',
            );

        $data = array();
        foreach($rows as $vo){
            $temp = array(
                " ".$vo['uid'],
                '123456789108888888 ',
                $vo['rname'],
                $vo['cardid']." ",
                $vo['bank'],
                '',
                $vo['disburse'],
                '代付',
            );
            array_push($data,$temp);
            unset($temp);
        }

        $this->_ToExcel($filename,$head,$data);
    }
    //导出方法
    private	function _ToExcel($name,$hd,$data){
        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
        //对数据进行检验
        if(empty($data) || !is_array($data)){
            die("data must be a array");
        }
        //检查文件名
        if(empty($name)){
            exit;
        }
    
        $date = date("Y_m_d",time());
        $name .= "_{$date}.xls";
    
        //创建PHPExcel对象，注意，不能少了\
        $objPHPExcel = new \PHPExcel();
        $objProps = $objPHPExcel->getProperties();
    
        //设置表头
        $key = ord("A");
        foreach($hd as $v){
            $colum = chr($key);
            $objPHPExcel->setActiveSheetIndex(0) ->setCellValue($colum.'1', $v);
            $key += 1;
        }
    
        $column = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
        foreach($data as $key => $rows){ //行写入
            $span = ord("A");
            foreach($rows as $keyName=>$value){// 列写入
                $j = chr($span);
                $objActSheet->setCellValue($j.$column, $value);
                $span++;
            }
            $column++;
        }
    
        $name = iconv("utf-8", "gb2312", $name);
        //重命名表
        // $objPHPExcel->getActiveSheet()->setTitle('test');
        //设置活动单指数到第一个表,所以Excel打开这是第一个表
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$name\"");
        header('Cache-Control: max-age=0');
    
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output'); //文件通过浏览器下载
        exit;
    }
}

?>