<?php
/**
 * 矿机
 */
namespace Api\Controller;
use Think\Log;
use Think\Controller;
use Think\Model;
use Common\Lib\Constants;

class MillsController extends BaseController{
    //矿机列表
    public function millList(){
        //状态
        $stype = I('stype/s') == 'stop' ? 'stop' : 'runing';
        if($stype == 'runing'){
            $condi['status']  = 1;
        }else{
            $condi['status']  = 0;
        }
        $condi['uid'] = $this->uid;
        //矿机类型
        $subtype = I('subtype/d');
        if($subtype){
            $condi['type'] = $subtype;
        }
        //时间范围
        $end_time = time();
        $end = I('end/s');
        if($end){
            $end_time = strtotime($end) + 3600 * 24;
        }
        $condi['create_time'] = array('ELT', $end_time);
        $begin = I('begin/s');
        if($begin){
            $begin = strtotime($begin);
            $condi['create_time'] =  array(array('egt', $begin), array('ELT', $end_time));
        }
        //页码
        $page = I('page/d');
        $millModel = M(get_mill_table($this->uid));
        $list = $millModel
                ->field('mill_sn,create_time,status,type,mill_value,last_time')
                ->where($condi)->page($page)->limit(20)->select();

        foreach($list as &$mill){
            $stop_time = $mill['create_time'] + 60*60*720;
            $mill['stop_time'] = $stop_time;
            $mill['output'] = mill_get_output($mill);
            $mill['type']   = mill_name($mill['type']);            
            $mill['status'] = mill_status($mill['status']);
            $mill['stop_time'] = date('Y-m-d H:i:s', $stop_time);
            $mill['create_time'] = date('Y-m-d H:i:s', $mill['create_time']);
            unset($mill['last_time']);
            unset($mill['mill_value']);
        }
        $data['page'] = $page+1;
        $data['list'] = empty($list) ? array() : $list;
        succ($data);
    }

    //购买矿机
    public function millGen(){
        require_check("mtype,paypwd");
        $mtype  = I('mtype');
        $price  = mill_price($mtype);
        $paypwd = input_str(I('paypwd'));

        if($price == '')
            err('商品不存在');

        $memberModel = D('members');
        if(!$memberModel->checkpaypwd($this->uid, $paypwd))
            err('交易密码错误');

        $logModel = M('userScoreLog');
        $millModel = M(get_mill_table($this->uid));

        $memberModel->startTrans();
        $member = $memberModel->lock(true)->where(array('id' => $this->uid))->find();
        if($member && $member['score'] >= $price){
            //创建矿机
            $mill_sn = gen_mill_sn($this->uid, $mtype);
            $data = array(
                'uid' => $this->uid,
                'type' => $mtype,
                'last_time'   => NOW_TIME,
                'create_time' => NOW_TIME,
                'status' => 1,
                'mill_value' => mill_max_output($mtype),
                'mill_sn' => $mill_sn,
                'get_way' => 'buy',
            );
            $result = $millModel->add($data);

            //用户扣币
            $result = $result && $memberModel->score_dec($member, $price);
            $newBalan1 = new_bcsub($member['score'], $price);

            //账户日志
            $log = array('uid' => $this->uid, 'changeform' => 'out', 'subtype' => 7, 'num' => $price, 'ctime' => NOW_TIME, 'balance' => $newBalan1, 'extends' => $mill_sn);
            $result = $result && $logModel->add($log);

            //团队算力
            $power = mill_power($mtype);
            $result = $result && $memberModel->upd_team_power($member['path'], $power, 'inc');
            //个人算力
            $result = $result && $memberModel->upd_power($this->uid, $power, 'inc');
            
            if($result){
                $memberModel->commit();
                succ('开通成功');
            }else{
                $memberModel->rollback();
                err('开通失败');
            }            
        }else{
            $memberModel->rollback();
            err('PEC数量不足');
        }
    }

    //矿机详情
    public function mill(){
        $mill_sn = I('post.mill_sn');
        $map_mill = array(
            'uid' => $this->uid,
            'mill_sn' => $mill_sn
        );
        $millModel = M(get_mill_table($this->uid));
        $mill_info = $millModel->field('type,create_time,last_time,status,mill_value')->where($map_mill)->find();
        //到期时间
        $stop_time = $mill_info['create_time'] + 60*60*720;
        $mill_info['stop_time'] = $stop_time;
        //已产出量
        $mill_info['output'] = new_bcsub(mill_max_output($mill_info['type']), $mill_info['mill_value']);
        
        //剩余量
        $mill_info['wait_out'] = mill_get_wait($mill_info);
        //矿机名称
        $mill_info['mill_name']   = mill_name($mill_info['type']);
        //矿机算力
        $mill_info['mill_power']   = mill_power($mill_info['type']);
        //矿机状态
        if($mill_info['create_time'] < time()-60*60*720) $mill_info['status'] = 0;
        $mill_info['status_html'] = mill_status($mill_info['status']);
        $mill_info['stop_time'] = date('Y-m-d H:i:s', $stop_time);
        $mill_info['create_time'] = date('Y-m-d H:i:s', $mill_info['create_time']);
        $mill_info['last_time'] = date('Y-m-d H:i:s', $mill_info['last_time']);
        // dump($mill_info);
        succ($mill_info);
    }

    //定时关闭矿机
    public function millClose(){
        //更新矿机状态
        
        //重算团队算力
    }

    //矿机收币
    public function getCoin(){
        require_check("millsn");
        $millsn = input_str(I('millsn'));

        $millModel = M(get_mill_table($this->uid));
        $memberModel = D('members');
        $logModel = M('userScoreLog');

        $millModel->startTrans();
        $mill = $millModel->lock(true)->where(array('mill_sn' => $millsn, 'uid' => $this->uid))->find();
        //验证
        if($mill && $mill['mill_value'] > 0){
            $member = $memberModel->lock(true)->where(array('id' => $this->uid))->find();
            if($member){
                $givenum = mill_get_wait($mill);
                if($givenum > 0){
                     //用户加币
                    $result = $memberModel->score_inc($member, $givenum);
                    $newBalan1 = new_bcadd($member['score'], $givenum);
                    
                    //用户日志
                    $log = array('uid' => $this->uid, 'changeform' => 'in', 'subtype' => 3, 'num' => $givenum, 'ctime' => NOW_TIME, 'balance' => $newBalan1, 'extends' => $millsn);
                    $result = $result && $logModel->add($log);

                    //矿机更新
                    $upd['mill_value'] = new_bcsub($mill['mill_value'], $givenum);
                    $upd['last_time']  = NOW_TIME;
                    $result = $result && $millModel->where(array('mill_sn' => $millsn, 'uid' => $this->uid))->save($upd);

                    //发放推荐奖
                    $result = $result && $memberModel->recReward($member, $givenum);

                    if($result){
                        $memberModel->commit();
                        succ('结算成功');
                    }else{
                        $memberModel->rollback();
                        err('结算失败');
                    }  
                }else{
                    $millModel->rollback();
                    err('还没有挖到新的PEC,请稍后重试');
                }
               
            }else{
                $millModel->rollback();
                err('结算失败[用户不存在]');
            }
        }else{
            $millModel->rollback();
            err('结算失败[矿机不存在]');
        }           
    }

    
}