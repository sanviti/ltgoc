<?php
/**
 * 新闻控制器
 */
namespace DataAdmin\Controller;
use Think\Controller;
class NewsController extends BaseController {

    /**
     * 新闻列表
     * @return [type] [description]
     */
    public function newsList()
    {
        //搜索
        $title = I('title', '', 'trim');
        $stype = I('stype', '', 'intval');
        $cate_id  = I('cate_id');
        $state    = I('state');
        $admin_id = I('admin_id');
        if($title){            
            if($stype == 0){
                $condi['news.title'] = $title;
            }else{
                $condi['news.title'] = array('like', '%'.$title.'%');
            }
            $params['title']   = $title;
        }

        if($cate_id !== ''){
            $condi['news.cate_id'] = intval($cate_id);
            $params['cate_id'] = $cate_id;
        }

        if(I('admin_id') !== ''){
            $condi['news.admin_id'] = intval($admin_id);
            $params['admin_id']   = $admin_id;
        }
        if($state !== ''){
            $condi['news.state'] = intval($state);
            $params['state']   = $state;
        }

        $page = IS_POST ? 1 : I('get.p'); //提交搜索的时候重置page参数
        
        $model = M('news');
        $count = $model -> alias('news')        
        ->join('LEFT JOIN __ADMIN__ adm ON adm.id = news.admin_id')
        ->join('LEFT JOIN __CATEGORY__ cate ON cate.cat_id = news.cate_id')
        ->where($condi) -> count();
        // dump($condi);
        // echo M()->getLastSql();
        // exit;
        $p = getpage($count, C('PAGE_SIZE'), $params);
        $show = $p -> show();
        $list = $model -> alias('news') 
        ->field('news.id,news.title,news.create_time,news.last_time,news.state,news.picurl,news.view,adm.username,cate.cat_name')
        ->join('LEFT JOIN __ADMIN__ adm ON adm.id = news.admin_id')
        ->join('LEFT JOIN __CATEGORY__ cate ON cate.cat_id = news.cate_id')
        ->where($condi) -> page($page) -> order('id desc') -> limit(C('PAGE_SIZE')) -> select();

        //分类
        if(S('category')){
            $category = S('category');
        }else{
            $category = M('category')->field('cat_name,cat_id')->where('parent_id = 0') -> order("`order` DESC") -> select();
            foreach($category as $k => $v){
                $category[$k]['child'] = M('category')->where("parent_id= %d", $v['cat_id'])->order("`order` DESC") -> select();
            }
            S('category',$category, 60*60*24);
        }
        $this -> assign('category', $category);

        //管理员
        if(S('adminList')){
            $adminList = S('adminList');
        }else{
            $adminList = M('admin')->field('username,id')->select();            
            S('adminList',$adminList, 60*60*24);
        }
        $this -> assign('adminList', $adminList);

        $this -> assign('page', $show);
        $this -> assign('list', $list);
        $this -> display();
    }

    /**
     * 添加新闻
     * @return [type] [description]
     */
    public function newsAdd()
    {

        if(IS_AJAX){
            if($err = admin_require_check('title,cate_id,content')) $this->error($err);
            $data['title']      = trim(I('post.title', '', 'htmlspecialchars,trim'));    
            $count = M('news')->where('title = "%s"', $data['$title'])->count();
            if($count) $this->error('新闻已存在');   
            $data['cate_id']    = trim(I('post.cate_id', '', 'intval'));
            $data['content']    = $_POST['content'];
            $data['create_ip']  = get_client_ip();
            $data['state']      = I('state') == 1 ? 1 : 0;
            $data['picurl']     = trim(I('post.PicUrl'));            
            $data['des']        = trim(I('post.des'));            
            $data['view']       = trim(I('post.view'));            
            $data['from']       = I('post.from', '', 'htmlspecialchars');
            $data['create_time']  = $data['last_time'] = NOW_TIME;
            $data['admin_id']   = $this->_admin['id']; 
            if(M('news')->add($data)){
                $this->success('保存成功');
            }else{
                $this->error('保存失败');
            }
        }

        $list = M('category')->field('cat_name,cat_id')->where('parent_id = 0') -> order("`order` DESC") -> select();
        foreach($list as $k => $v){
            $list[$k]['child'] = M('category')->where("parent_id= %d", $v['cat_id'])->order("`order` DESC") -> select();
        }
        $this->assign("list", $list);
        $this->display();
    }

    /**
     * 删除新闻
     * @return [type] [description]
     */
    public function newsDel()
    {
        $model = D('news');
        $id = I('get.id/d');
        if(empty($id))exit;
        $result = $model -> where('id = %d', $id) -> delete();
        if ($result) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    // 批量修改状态
    public function batchChangeState(){
        $model = D('news');
        $ids = array_unique(array_filter(I('ids')));
        $state = I('state') ? 1 : 0;
        $result = $model -> where(array('id' => array('in',$ids))) -> save(array('state' => $state));
        if ($result) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败');
        }
    }

    //批量删除
    public function BatchDelNews() {
        $model = D('news');
        $ids = array_unique(array_filter(I('ids')));
        $result = $model -> where(array('id' => array('in',$ids))) -> delete();
        if ($result) {
            $this -> success('删除成功');
        } else {
            $this -> error('删除失败');
        }
    }

    /**
     * 编辑新闻
     * @return [type] [description]
     */
    public function newsEdit()
    {
        $id = I('id/d', '', 'intval');
        if(!$id) $this->error('请选择要修改的新闻');

        $detail = M('news')->where('id = %d', $id)->find();
        if(!$detail) $this->error('新闻不存在');

        if(IS_AJAX){
            if($err = admin_require_check('title,cate_id,content')) $this->error($err);
            $data['title']      = trim(I('post.title', '', 'htmlspecialchars,trim'));  
            $count = M('news')->where('title = "%s" and id != "%s"', $data['$title'], $id)->count();
            if($count) $this->error('新闻已存在');   
            $data['state']      = I('state') == 1 ? 1 : 0;            
            $data['cate_id']    = trim(I('post.cate_id', '', 'intval'));
            $data['content']    = $_POST['content'];
            $data['picurl']     = trim(I('post.PicUrl'));            
            $data['des']        = trim(I('post.des'));            
            $data['view']       = trim(I('post.view'));            
            $data['from']       = I('post.from', '', 'htmlspecialchars');
            $data['last_time']  = NOW_TIME;
            $data['admin_id']   = $this->_admin['id']; 

            if(M('news')->where('id = %d', $id)->save($data)){
                $this->success('保存成功');
            }else{
                $this->error('保存失败');
            }
        }
        
        $this->assign('detail', $detail);
        $list = M('category')->field('cat_name,cat_id')->where('parent_id = 0') -> order("`order` DESC") -> select();
        foreach($list as $k => $v){
            $list[$k]['child'] = M('category')->where("parent_id= %d", $v['cat_id'])->order("`order` DESC") -> select();
        }
        $this->assign("list", $list);
        $this->display('newsAdd');
    }

    /**
     * 新闻分类
     * @return [type] [description]
     */
    public function category()
    {
        $model = M("category");
        $list = $model -> where('parent_id = 0') -> order("`order` DESC") -> select();
        foreach($list as $k => $v){
            $list[$k]['child'] = $model->where("parent_id=".$v['cat_id'])->order("`order` DESC") -> select();
        }
        $this->assign("list", $list);
        $this->display();
    }

    /**
     * 新闻分类添加
     * @return [type] [description]
     */
    public function categoryAdd()
    {
        $model = M('category');
        if(IS_POST){
            if($error = admin_require_check('cat_name')) $this->error($error);
            if(I("post.role")){
                $cat_role=I("post.role");
            }else{
                $cat_role=0;
            }
            $data = array(
                'cat_name' => I('post.cat_name/s'),
                'parent_id' => I('post.parent_id/d'),
                'order' => I('post.order/d'),
                'dateline' => NOW_TIME,
                'admin_id' => session('admin.id'),
            );
            if($model->add($data)){
                $this->success("添加成功");
            }else{
                $this->error("添加失败");
            }
        }
        $baseclass = $model -> where('parent_id = 0') -> order("`order` DESC") -> select();
        $this->assign("baseclass",$baseclass);
        $this->display();
    }

    /**
     * 分类编辑
     * @return [type] [description]
     */
    public function categoryEdit()
    {   
        $cat_id = I('cat_id/d', '', 'intval');
        if(!$cat_id) $this->error('分类不存在');        
        $model = M('category');
        if(IS_POST){
            if($error = admin_require_check('cat_name')) $this->error($error);
            if(I("post.role")){
                $cat_role=I("post.role");
            }else{
                $cat_role=0;
            }
            $data = array(
                'cat_name' => I('post.cat_name/s'),
                'parent_id' => I('post.parent_id/d'),
                'order' => I('post.order/d'),
                'update_time' => NOW_TIME,
                'admin_id' => session('admin.id'),
            );
            if($model->where('cat_id = %d', $cat_id)->save($data)){
                $this->success("修改成功");
            }else{
                $this->error("修改失败");
            }
        }
        $data = $model -> where('cat_id = %d', $cat_id)->find();
        $this->assign('data', $data);
        $baseclass = $model -> where('parent_id = 0') -> order("`order` DESC") -> select();
        $this->assign("baseclass",$baseclass);
        $this->display('categoryAdd');
    }

    /**
     * 分类删除
     * @return [type] [description]
     */
    public function categoryDel()
    {
        if($error = admin_require_check('get.cat_id')) $this->error($error);
        $cat_id   = I("get.cat_id/d");
        $cates = M('category')->field('cat_id')->where('cat_id = %d or parent_id = %d', $cat_id, $cat_id)->select();
        M()->startTrans();     

        //删除分类和子分类
        $result = M("category")->where('cat_id = %d or parent_id = %d', $cat_id, $cat_id)->delete();

        //删除新闻
        foreach ($cates as $item) {
            $newsNum = M('news')->where('cate_id = %d', $item['cat_id'])->count();
            if($newsNum){
                $result = $result && M('news')->where('cate_id = %d', $item['cat_id'])->delete();
            }
        }

        if($result){
            M()->commit();
            $this->success("删除成功");
        }else{
            M()->rollback();            
            $this->error("删除失败");
        }
    }



}