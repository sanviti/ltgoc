<?php
namespace DataAdmin\Common\Lib;
/**
 * 管理员操作日志
 */
class ActionLog{

    //修改用户备注
    public function members_editmemo($id){
        return array(
            'subtype' => 'edit',
            'memo' => "修改用户[ID:{$id}]备注。",
        );
    }

    //账户充值
    public function members_charge($arg){
        return array(
            'subtype' => 'charge',
            'memo' => "预付费充值，用户[ID:{$arg['id']}]，金额[ {$arg['money']} 元]。",
        );
    }

    //账户充值-订单确认
    public function members_charge_confirm($arg){
        return array(
            'subtype' => 'charge_confirm',
            'memo' => "预付费充值订单确认，订单号[{$arg['ordersn']}]，用户[ID:{$arg['id']}]，金额[ {$arg['money']} 元]。",
        );
    }
	
	//财务-批量确认
	public function finance_batchconfirm($arg){
		return array(
            'subtype' => 'batch_confirm',
            'memo' => "批量确认付款，处理ID[{$arg['ids']}]。",
        );
	}
}