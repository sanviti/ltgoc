<?php
/**
 * 轮播图
 */
namespace DataAdmin\Controller;
use Think\Controller;
class OtherController extends BaseController {
    public function bannerList(){
        $page = IS_POST ? 1 : I('get.p'); //提交搜索的时候重置page参数
        $model = M('banner');
        $count = $model-> count();
        $params['p']=I('get.p');
        $p = getpage($count, C('PAGE_SIZE'), $params);
        $show = $p -> show();
        $list = $model
        -> alias('banner')
        ->  page($page)
        ->  order('id desc') -> limit(C('PAGE_SIZE')) -> select();
        $this -> assign('page', $show);
        $this -> assign('list', $list);
        $this -> display();
    }

    public function bannerAdd(){
        if(IS_AJAX){
            if($err = admin_require_check('title,PicUrl')) $this->error($err);
            $data['title']      = trim(I('post.title', '', 'htmlspecialchars,trim'));    
            $count = M('banner')->where('title = "%s"', $data['$title'])->count();
            if($count) $this->error('轮播图已存在');   
            $data['sort']    = trim(I('post.sort', '', 'intval'));
            $data['pic_url'] = trim(I('post.PicUrl'));           
            $data['url']     = trim(I('post.url'));           
            $data['create_time']= $data['last_time'] = NOW_TIME;
            $data['admin_id']   = $this->_admin['id']; 
            if(M('banner')->add($data)){
                $this->success('保存成功');
            }else{
                $this->error('保存失败');
            }
        }
        $this->display();
    }

    public function bannerEdit(){
        $id = I('id/d', '', 'intval');
        if(!$id) $this->error('请选择要修改的轮播图');

        $detail = M('banner')->where('id = %d', $id)->find();
        if(!$detail) $this->error('轮播图不存在');

        if(IS_AJAX){
            if($err = admin_require_check('title,PicUrl')) $this->error($err);
            $data['title']      = trim(I('post.title', '', 'htmlspecialchars,trim'));    
            $count = M('banner')->where('title = "%s"', $data['$title'])->count();
            if($count) $this->error('轮播图已存在');   
            $data['sort']    = trim(I('post.sort', '', 'intval'));
            $data['pic_url'] = trim(I('post.PicUrl'));           
            $data['url']     = trim(I('post.url'));           
            $data['last_time']  = NOW_TIME;
            $data['admin_id']   = $this->_admin['id']; 
            if(M('banner')->where('id = %d', $id)->save($data)){
                $this->success('保存成功');
            }else{
                $this->error('保存失败');
            }
        }
        $this->assign('detail', $detail);
        $this->display('bannerAdd');
    }

    public function bannerDel(){
        $model = D('banner');
        $id = I('get.id/d');
        if(empty($id))exit;
        $result = $model -> where('id = %d', $id) -> delete();
        if ($result) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    //批量删除
    public function BatchDelBanner() {
        $model = D('banner');
        $ids = array_unique(array_filter(I('ids')));
        $result = $model -> where(array('id' => array('in',$ids))) -> delete();
        if ($result) {
            $this -> success('删除成功');
        } else {
            $this -> error('删除失败');
        }
    }
}