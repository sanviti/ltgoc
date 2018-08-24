<?php
/**
 * Created by PhpStorm.
 * User: jing
 * Date: 2017/3/31
 * Time: 11:37
 */
namespace Api\Model;
use Think\Model;
class AdvertModel extends Model{

    protected $tableName = 'advert';

    public function getAdvList(){
        $where=array(
            'state'=>1,
            'page'=>array('in',array(2,5)),
        );
        $list = $this->field('title,imageurl,value as url,page as type')->where($where)->order('id desc')->select();

        return $list;

    }

}