<?php
namespace DataAdmin\Controller;
use Think\Controller;
/**
 * 好文推荐
 * @author xiaoqiang
 *
 */
class GoodsArtController extends BaseController{
    /**
     * 首页
     */
    public function index(){
        $type = I('type');
        $title = I('title');
        $page = I('get.p');
        
        if(!empty($type)){
            $where['type'] = $type;
        }
        if(!empty($title)){
            $where['title'] = array(
                'like',
                '%' . $title . '%'
            );
        }
        $count = M('data_goodsatr',null)->where($where)->count();
        $p = getpage($count, C('PAGE_SIZE'), $where);
        $show = $p->newshow();
        $list = M('data_goodsatr',null)->where($where)->page($page)->limit(C('PAGE_SIZE'))->order('ctime DESC')->select();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('p',$page);
        $this->display();
    }
    
    /**
     * 修改排序
     */
    public function editSort(){
        $id = I("id");
        $sort = I("sort");
        if(M('data_goodsatr',null)->where(array('id' => $id))->find()){
            if(M('data_goodsatr',null)->where(array('id'=> $id))->save(array("sort"=>$sort,"updatetime"=>time()))){
                succ("设置成功!");
            }else{
                err("设置失败!");
            }
        }else{
            err("设置失败！");
        }
    }   
    /**
     * 添加
     */
    public function edit(){
        $id = I('id');
        if ($id){
            $list = M('data_goodsatr',null)->where(array('id' => $id))->find();
            $list['content'] = unserialize($list['content']);
            $this->assign('list',$list);
            $this->assign('leixing',$list['leixing']);
        }        
        $this->display();
    }
    public function edit_to(){
        if (IS_POST) {
            $id = I('id');
            $type = I('type');
            $title = I('title');
            $tag = I('tag');
            $author = I('author');
            $video = I('video');
            $img = I('img');
            $sort = I('sort')?I('sort'):0;
            $click = I('click')?I('click'):0;
            $num = I('num')?I('num'):0;
            $content = I('content');
            $goodsid = I('goodsid');
            $leixing = I('leixing');
            $recommend = I('recommend');
            $data = array(
                'type'  => $type,
                'title' => $title,
                'tag'   => $tag,
                'author'=> $author,
                'video' => $video,
                'sort'  => intval($sort),
                'img'  =>  $img,
                'click'=>  intval($click),
                'num'=>    intval($num),
                'content'=>serialize($content),
                'goodsid'=>$goodsid,
                'addtime'=>strtotime(date("Y-m-d")),
                'leixing'=>$leixing,
                'recommend'=>$recommend
            );
            if (!$type){
                $this->success('类型必填');
                exit;
            }
            if($type!=3){
                if (!$title){
                    $this->success('标题不能为空');
                    exit;
                }
            }
            if($type!=3){
                if (!$tag){
                    $this->success('请填写标签');
                    exit;
                }
            }
            if($type!=3){
                if (!$author){
                    $this->success('作者不能为空');
                    exit;
                }
            }
            if($type==1 && $leixing==1){
                if (!$video){
                    $this->success('请上传视频');
                    exit;
                } 
            }
            if (!$img){
                $this->success('请填写缩略图');
                exit;
            }
            if(empty($id)){
                $data['ctime'] = time();
                $ret = M('data_goodsatr',null)->add($data);
            }else{
                if(M('data_goodsatr',null)->where(array('id' => $id))->find()){
                   $data['updatetime'] = time();
                   $ret = M('data_goodsatr',null)->where(array('id' => $id))->save($data);
                }
            }
            if ($ret){
                $this->success('保存成功');
            }else{
                $this->error('保存失败');
            }
        }
    } 
    /**
     * 删除文章
     */
    public function delArt(){
        $id = I('id');
        if(M('data_goodsatr',null)->where(array("id"=>$id))->find()){
           if(M('data_goodsatr',null)->where(array("id"=>$id))->delete()){
               succ("删除成功!");
           }else{
               err("删除失败!");
           } 
        }else{
            err("删除失败!");
        }
    }
    
    /**
     * 多删--删除文章
     */
    public function delAll(){
        $idStr = I('idStr');
        foreach ($idStr as $value) {
            $rtn = M('data_goodsatr',null)->where("id=" . $value)->delete();
        }
        if($rtn){
            succ("删除成功!");
        }else{
            err("删除失败!");
        }
    }
}
?>