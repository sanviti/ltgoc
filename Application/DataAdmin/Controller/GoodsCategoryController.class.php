<?php
namespace DataAdmin\Controller;
use Think\Controller;
class GoodsCategoryController extends BaseController{
    public $catMoldel;
    public function categorys(){
        $catMoldel=D("GoodsCategory");
        $page = I('p');
        $data=$catMoldel->catInfo();
        $this->assign('data',$data);
        $this->display();
    }

    /*
     * 分类删除
     * @Author 王赛  2017-8-8
     * */
    public function catDelet(){
        $data=I();
        $catId=$data['cat_id'];
        $catMoldel=D("GoodsCategory");
        if($catMoldel->where(array("cat_id"=>$catId))->delete()){
            succ("删除成功");
        }else{
            err("删除失败");
        }
    }

    /*
    * 分类添加
    * @Author 王赛  2017-8-8
    * */

    public function catAdd(){
        $data=I();
        $catMoldel=D("GoodsCategory");
        $catInfo=$catMoldel->catInfo();
        $this->assign('catinfo',$catInfo);
        if(IS_AJAX){
            $quesInfo=I();
            $quesInfo['ctime']=time();
            if($quesInfo['cat_id']){
                if($catMoldel->where(array("cat_id"=>$quesInfo['cat_id']))->save($quesInfo)){
                    succ("修改成功");
                }else{
                    err("修改失败");
                }
            }else{
                if($catMoldel->add($quesInfo)){
                    succ("添加成功");
                }else{
                    err("添加失败");
                }
            }

        }
        if(isset($data['parent_id'])){
            $findInfo=$catMoldel->where(array("cat_id"=>$data['parent_id']))->find();
            $this->assign('findInfo',$findInfo);
        }
        if(isset($data['cat_id'])){
            $result=$catMoldel->where(array("cat_id"=>$data['cat_id']))->find();
            $data['cat_name']=$result['cat_name'];
            $data['cat_des']=$result['cat_des'];
            $data['img']=$result['img'];
            $data['pic']=$result['pic'];
            $data['parent_id']=$result['parent_id'];
            $this->assign('data',$data);
        }
        $this->display();
    }
    
    /*
     * 设置分类推荐
     */
    public function set_recommend(){
        $catid = I("catid");
        if(empty($catid)) err("请选择要推荐的分类！");
        $is_good = I("is_good");
        $catMoldel=M("category");
        $rtn = $catMoldel->where("cat_id=".$catid)->save(array("is_good"=>$is_good));
        if($rtn){
            succ("设置成功");
        }else{
            err("设置失败");
        }  
    }

    public function sort(){
        $catid = I("catid");
        $sort = I('sort');
        $catMoldel=M("category");
        $rtn = $catMoldel->where("cat_id=".$catid)->save(array("sort"=>$sort));
        if($rtn){
            succ("设置成功");
        }else{
            err("设置失败");
        }
    }
   
    /**
     * 设置搜索栏关键词
     */
    /* public function set_default(){
        $catid = I("catid");
        $is_def = I('is_def');
        $catMoldel=M("category");
        $rtn = $catMoldel->where("cat_id=".$catid)->save(array("is_def"=>$is_def,"set_deftime"=>time()));
        if($rtn){
            succ("设置成功");
        }else{
            err("设置失败");
        }
    } */
    
}
?>