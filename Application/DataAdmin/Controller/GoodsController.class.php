<?php
namespace DataAdmin\Controller;

use Think\Controller;

class GoodsController extends BaseController
{

    public function index()
    {
        $tag = I("tag");    
        if($tag=="cuxiao"){
            $condi['g.is_hot'] = 1;
        }
        if($tag=="tuijian"){
            $condi['g.is_recommend'] = 1;
        }
        if($tag=="eight"){
            $condi['g.is_eight'] = 1;
        }
        if($tag=="manjian"){
            $condi['g.is_manjian'] = 1;
        }
        if($tag=="maizeng"){
             $condi['g.is_maizeng'] = 1;
        }
        if($tag=="zhekou"){
            $condi['g.is_zhekou'] = 1;
        }
        $params['tag'] = $tag;
        $name = I('name', '', 'trim');
        $page = I('get.p');
        $state = I('get.state');
        $shopid = I('shopid');
        $cid=I("cid");
        $is_pinpai = I('is_pinpai');
        $is_haowu = I('is_haowu');

        $paixu = I('paixu');
        if($paixu!=""){
            if($paixu=="up"){
               $order = "click_num ASC,id DESC";
            }elseif($paixu=="down"){
               $order = "click_num DESC,id DESC";
            }elseif($paixu=="csortdown"){
               $order = "csort DESC,id DESC";
            }elseif($paixu=="csortup"){
               $order = "csort ASC,id DESC";
            }elseif ($paixu=="id"){
               $order = "id DESC";
            }
        }else{
            $order = "csort DESC,id DESC";
        }
        if (! empty($name)) {
            $condi['g.name'] = array(
                'like',
                '%' . $name . '%'
            );
            $params['name'] = $name;
        }
        if ($state != '') {
            $condi['g.status'] = $state;
            $params['state'] = $state;
        }
        if ($is_pinpai != '') {
            $condi['g.is_pinpai'] = $is_pinpai;
            $params['is_pinpai'] = $is_pinpai;
        }
        
        if ($is_haowu != '') {
            $condi['g.is_haowu'] = $is_haowu;
            $params['is_haowu'] = $is_haowu;
        }
        if ($cid) {
            $condi['g.category_id'] = $cid;
            $params['category_id'] =$cid;
        }
        if ($shopid){
            $shop = M('data_shop',null)->field('id')->where(array('shopid' => $shopid))->find();

            $condi['g.shop_id'] = $shop['id'];
            $params['shop_id'] = $shopid;
        }
        if (empty($page))
            $page = 1;
        $count = D('Goods')->getGoodsCount($condi);
        $p = getpage($count, C('PAGE_SIZE'), $params);
        $show = $p->newshow();
        $field = 'g.*,s.name as s_name';
        $list = D('Goods')->getGoodsList($condi, $page, $field,$order);

        foreach ($list as $k=>$v){
            $recommend = M("data_goods_recommend",NULL)->where(array("type"=>1,"goodsid"=>$v['id']))->find();
            if(empty($recommend)){
                $list[$k]['is_r'] = 0;
            }else{
                $list[$k]['is_r'] = 1;
            }
        }
        $catInfo= D("GoodsCategory")->catInfo();
        //品牌
        $brand = M("data_brand",null)->where(array('type'=>0))->select();
        //好物
        $found = M("data_brand",null)->where(array('type'=>1))->select();
        $this->assign("found",$found);
        $this->assign("brand",$brand);
        $this->assign('catInfo',$catInfo);
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('p',$page);
        $this->display();
    }
    // 添加
    public function add()
    {
        // 得到分类列表
        $list = D('Category')->getList('');
        $this->assign('list', $list);
        $this->display();
    }
    // 修改
    public function edit1()
    {
        $id = I('get.id');
        // 得到分类列表
        $clist = D('Category')->getList('');
        // 得到信息
        $goodsData = M('goods')->field('g.*,shopid')
            ->alias('g')
            ->join('inner join data_shop as s on s.id = g.shop_id')
            ->where("g.id=" . $id)
            ->find();

        $goodsData['goods_pics'] = unserialize($goodsData['pic']);
        $goodsData['arguments'] = unserialize($goodsData['arguments']);

        if($goodsData['extended_attributes'] == 1){
            $goodsData['attribute'] = unserialize($goodsData['attribute']);

            $stock = M('data_goods_attr',null)->field('flag,price,stock,img')->where('goods_id = %s',$id)->select();
            if($stock) $this->assign('stock',$stock);
        }

        $this->assign('clist', $clist);
        $this->assign('list', $goodsData);
        $this->display();
    }

    public function edit(){
        $goods_id = I('id');
        if ($goods_id){
            $goods = M('data_goods',null)->field('mid,status')->where(array('id' => $goods_id))->find();
            $mid = $goods['mid'];
        }
        $status = M('data_shop',null)->field("id,status,is_money,shopid")->where(array('mid' => $mid))->find();
        if ($status['status'] != 1){
            $this->error('该账户未通过审核，暂时无法上传商品');
        }
        if ($status['is_money'] != 1){
            $this->error('未缴纳保证金,无法上传商品');
        }

        if(IS_POST){
            if (!I('category1')){
                err('请选择分类');
            }
            if (!I('price')){
                err('请输入商品价格');
            }
            if (!I('goods_img')){
                err('请添加商品主图');
            }
            if (!I('goods_pics')){
                err('请添加轮播图');
            }
            $marketprice=I('marketprice')?I('marketprice'):0;
            $price=I('price')?I('price'):0;
            $goods_pics = I('goods_pics') ? serialize(explode(',',I('goods_pics'))) : ''; //商品展示图片
            $goods_type = I('category2') ? I('category2') : I('category1'); //商品分类
            $attribute  = ''; //商品规格
            $stock_info = I('stock');
            $arguments = is_array(I('arguments')) ? serialize(I('arguments')) : '';
            //商品库存
            if($extended_attributes = I('extended_attributes')){
                $goods_stock = $this->_getStockSum($stock_info);
                $attribute   = serialize(I('attribute'));
            }else{
                $goods_stock = I('goods_stock/d');
            }

            $goodsData = array(
                'extended_attributes' => I('extended_attributes'),
                'promote_start_date'  => I('promote_start_date') ? strtotime(I('promote_start_date')) : '',
                'promote_end_date'    => I('promote_end_date') ? strtotime(I('promote_end_date')) : '',
                'dec_stock_type'      => I('dec_stock_type'),
                'promote_open' => I('promote_open'),
                'details' => I('goods_detail'),
                'price1' => $marketprice,
                'postage'  => I('express_fee'),//快递费
                'stock'  => $goods_stock,
                'status'   => $goods['status'],//进入待发布状态
                'shop_id'  => $status['id'],
                'category_id' => $goods_type,
                'pic' => $goods_pics,
                'name' => I('goods_name'),
                'attribute'  => $attribute,
                'arguments'  => $arguments,
                'img'  => I('goods_img'),
                'subtitle'   => I('subtitle'),
                'price2'      => $price,
                'unit'       => I('unit'),
                'mid'=>$mid,
                'is_import'=>I('is_import'),
                'ctime' => time(),
                'ret_money' => 20//让利配比默认20%
            );
            if($goods_id){
                $goodsData['last_update'] = NOW_TIME;

                $result = M('data_goods',null)->where('id = %s',$goods_id)->save($goodsData);

                if($result){

                    M('data_goods_attr',NULL)->where('goods_id = %s',$goods_id)->delete();

                    if(is_array($stock_info) && $extended_attributes == 1){
                        foreach($stock_info as $k => $item){
                            $stock_info[$k]['goods_id'] = $goods_id;
                        }
                        M('data_goods_attr',null)->addAll($stock_info);
                    }
                    succ('商品修改成功');
                }else{
                    err('修改失败稍后重试');
                }
            }else{
                $goodsData['dateline'] = NOW_TIME;
                $result_goods_id = M('data_goods',null)->add($goodsData);

                if($result_goods_id){
                    if(is_array($stock_info) && $extended_attributes == 1){
                        foreach($stock_info as $k => $item){
                            $stock_info[$k]['goods_id'] = $result_goods_id;
                        }
                        M('data_goods_attr',null)->addAll($stock_info);
                    }
                    succ('商品添加成功');
                }else{
                    err('添加失败稍后重试');
                }
            }
            exit;
        }

        if($goods_id){
            $goodsData = M('data_goods',null)->find($goods_id);
            $goodsData['goods_pics'] = unserialize($goodsData['pic']);
            $goodsData['arguments'] = unserialize($goodsData['arguments']);
            $goodsData['shopid'] = $status['shopid'];

            if($goodsData['extended_attributes'] == 1){
                $goodsData['attribute'] = unserialize($goodsData['attribute']);

                $stock = M('data_goods_attr',null)->field('flag,price,stock,img')->where('goods_id = %s',$goods_id)->select();
                if($stock) $this->assign('stock',$stock);
            }
            //            dump($goodsData);die;
            $this->assign('data',$goodsData);
        }
        //计量单位
        //        $units = M('shopGoodsUnits',null)->cache('SHOP_GOODS_UNITS')->select();
        //        $this->assign('units',$units);
        //商品分类
        $goodsCateModel=new \NewMerchant\Model\GoodsCategoryModel();
        $cate=$goodsCateModel->catInfoNoPage();
        // $cate   = D('GoodsCategory')->field('cat_id,cat_name,parent_id')->where(array("cat_role"=>0))->select();
        //供应商

        $this->assign('category',$cate);

        $this->assign('active',$goodsData['active']);
        $this->assign('goods_id',$goods_id);
        $this->display();
    }



    public function act_do()
    {
        $id = I('id');
        $data['name'] = I('name');
        $m_num = I('m_num');
        $data['category_id'] = I('category');
        $data['price1'] = I('price1');
        $data['price2'] = I('price2');
        $userimg = I('userimg');
        $data['status'] = $_POST['status'];
        $data['intro'] = I('intro');
        $data['details'] = I('details');
        $data['stock'] = I('stock');
        if (empty($data['status']))
            $data['status'] = 0;
        if (empty($userimg)) {
            $this->error('图片至少上传一张');
        }
        if ($data['stock'] == '') {
            $this->error('库存不能为空');
        } else
            if (! is_numeric($data['stock'])) {
                $this->error('库存应该是数字');
            }
        if (empty($data['price1'])) {
            $this->error('原价不能为空');
        }
        if (empty($data['price2'])) {
            $this->error('市场价不能为空');
        }
        // 处理图片
        $picData = explode('||', $userimg);
        $data['pic'] = $picData[0];
        if (empty($id)) {
            if (empty($m_num)) {
                $this->error('商家编号不能为空');
            }
            $data['uptidme'] = time();
            $data['ctime'] = time();
            // 根据商家编号得打商家ID和店铺ID
            $mList = M('shop')->field('id,mid')
                ->where("shopid='" . $m_num . "' and status=1")
                ->find();
            if (empty($mList)) {
                $this->error('店铺编号不存在');
            }
            $data['mid'] = $mList['mid'];
            $data['shop_id'] = $mList['id'];
            $result = M('goods')->add($data);
            if ($result) {
                // 处理图片
                $this->addPic($picData, $result);
                $this->success('添加成功',U('Goods/index'));
            } else {
                $this->error('添加失败');
            }
        } else {
            $result = M('goods')->where("id=" . $id)->save($data);
            if ($result !== false) {
                // 处理图片
                $this->delPic($id);
                $this->addPic($picData, $id);
                $this->success('添加成功',U('Goods/index'));
            } else {
                $this->error('添加失败');
            }
        }
    }
    // 添加图片
    private function addPic($picData, $id)
    {
        foreach ($picData as $pic) {
            $data['pic_url'] = $pic;
            $data['goods_id'] = $id;
            M('goods_pic')->add($data);
        }
    }
    // 删除图片
    private function delPic($id)
    {
        M('goods_pic')->where("goods_id=" . $id)->delete();
    }
    // 展示设置规格
    public function setattribute()
    {
        $id = I('get.id');
        $list = M('goods')->alias('attribute')
            ->where("id=" . $id)
            ->find();
        $stock = M('goods_attr')->field('flag,price,stock,img')
            ->where(' goods_id = %s', $id)
            ->select();
        if ($stock)
            $this->assign('stock', $stock);
        $this->assign('id', $id);
        $this->assign('stock', $stock);
        $this->assign('attribute', unserialize($list['attribute']));
        $this->display();
    }
    // 设置规格操作
    public function setattribute_do()
    {
        $id = I('post.id');
        $attribute = I('post.attribute');
        $stock_info = I('stock');
        if (! empty($attribute)) {
            $data['attribute'] = serialize($attribute);
            $goods_stock = $this->getStockSum($stock_info);
            $data['stock'] = $goods_stock;
            M('goods')->where("id=" . $id)->save($data);
        }

        if (is_array($stock_info)) {
            // 先删除所有规格
            M('goods_attr')->where("goods_id=" . $id)->delete();
            foreach ($stock_info as $k => $item) {
                $stock_info[$k]['goods_id'] = $id;
            }
            M('goods_attr')->addAll($stock_info);
        }
        $this->success('设置成功', U('Goods/index'));
    }

    /**
     * 计算商品库存
     */
    private function getStockSum($stock)
    {
        $sum = 0;
        foreach ($stock as $s) {
            $sum += $s['stock'];
        }
        return $sum;
    }

    /**
     * 计算商品库存
     */
    private function _getStockSum($stock){
        $sum = 0;
        foreach($stock as $s){
            $sum += $s['stock'];
        }
        return $sum;
    }

    public function delGoods()
    {
        $id = I('post.id');
        $result = true;
        M()->startTrans();
        $result = $result && M('goods')->where("id=" . $id)->delete();
        $result2 = M('goods_pic')->where("goods_id=" . $id)->delete();
        $result3 = M('goods_attr')->where("goods_id=" . $id)->delete();
        if ($result && $result2 !== false && $result3 !== false) {
            M()->commit();
            $data['code'] = 'S';
        } else {
            $data['code'] = 'E';
            M()->rollback();
        }
        echo json_encode($data);
    }
    // 批量设置状态
    public function setGoodsStatus()
    {
        $type = I('post.type');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
                'status' => $type,
                'uptidme' => time()
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    // 批量设置促销
    public function setGoodsHot()
    {
        $type = I('post.type');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
                'is_hot' => $type
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    // 批量设置推荐
    public function setGoodsRecommed()
    {
        $type = I('post.type');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
                'is_recommend' => $type,
                'recom_time' =>time()
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }

    // 批量设置80%激励
    public function setEight()
    {
        $type = I('post.type');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_eight' => $type,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }

    // 批量设置满减
    public function setManjian()
    {
        $type = I('post.type');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_manjian' => $type,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    // 批量设置买赠
    public function setMaizeng()
    {
        $type = I('post.type');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_maizeng' => $type,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    // 批量设置品牌
    public function setPinpai()
    {
        $type = I('post.is_pinpai');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_pinpai' => $type,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    // 批量取消设置品牌
    public function unsetPinpai()
    {
        $type = I('post.is_pinpai');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_pinpai' => 0,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    // 批量设置品牌
    public function setZhekou()
    {
        $type = I('post.type');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_zhekou' => $type,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    //批量设置好物
    public function setFound()
    {
        $type = I('post.is_haowu');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_haowu' => $type,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }
    // 批量取消设置品牌
    public function unsetFound()
    {
        $type = I('post.is_haowu');
        $idStr = $_POST['idStr'];
        foreach ($idStr as $value) {
            M('goods')->where("id=" . $value)->save(array(
            'is_haowu' => 0,
            ));
        }
        $data['code'] = 'S';
        echo json_encode($data);
    }

    /**
     * App首页推荐推荐
     */
    public function goods_recommend()
    {
        $lid = I('lid', '', 'trim');
        $goodsid = I('goodsid','', 'trim');
        $type = I("type");
        $condi = "";
        if (! empty($lid)) {
            $condi['lid'] = $lid;
        }
        if ($goodsid != '') {
            $condi['goodsid'] = $goodsid;
        }
        if ($type != '') {
            $condi['type'] = $type;
        }
        $page = I('get.p');
        $count = M('data_goods_recommend', NULL)->where($condi)->count();
        $p = getpage($count, C('PAGE_SIZE'),$condi);
        $show = $p->newshow();
        $list = M('data_goods_recommend', NULL)->where($condi)->page($page)->limit(C('PAGE_SIZE'))->select();
        $now = time();
        foreach ($list as $k=>$v){
            if($v['etime']<$now){
                $list[$k]['status'] = 0;
            }else{
                $list[$k]['status'] = 1;
            }
            $brand = M("data_brand",null)->field("name")->where(array("id"=>$v['brand_id']))->find();
            $list[$k]['brand_id'] = $brand['name'];
        }
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('p',$page);
        $this->display();
    }
   /**
    * 详情
    */
    public function recommendDetail()
    {
        $id = I('id');
        $p=I("p");
        $data = M('data_goods_recommend', NULL)->where('id='.$id)->find();
        $catMoldel=D("GoodsCategory");
        $cdata=$catMoldel->catInfo();
        $this->assign('cdata',$cdata);
        $this->assign('id', $id);
        $this->assign('p',$p);
        $this->assign('data', $data);
        $brand = M("data_brand",null)->where(array("type"=>0))->select();
        $found = M("data_brand",null)->where(array("type"=>1))->select();
        $this->assign("found",$found);
        $this->assign("brand",$brand);
        $this->display();
    }

    /**
     * 修改App首页推荐
     */
    public function recommend_to()
    {
        $id = I('id');
        $p=I('p');
        if (IS_POST) {
            $info = M('data_goods_recommend', NULL)->where('id=%d', $id)->find();
             $arr=I();
            if($arr['etime']){
                $arr['etime']=strtotime($arr['etime']);
            }else{
                $arr['etime']= time()+31536000;
            }
           if($arr['type']==3){
               if($arr['cid']==0){
                   $this->error("请选择分类");
               }
           }
            if ($info) {
                $rtn = M('data_goods_recommend', NULL)->where('id=%d', $id)->save($arr);
                if ($rtn) {
                    $this->success("操作成功!",U('DataAdmin/Goods/goods_recommend',array('p'=>$p)));
                } else {
                    $this->error("操作失败!");
                }
            }
        }
    }

    //添加首页推荐
    public function addRecommend(){
        $goodsid = I('goodsid');
        $catMoldel=D("GoodsCategory");
        $data=$catMoldel->catInfo();
        $this->assign('data',$data);
        $this->assign('goodsid',$goodsid);
        $brand = M("data_brand",null)->where(array("type"=>0))->select();
        $found = M("data_brand",null)->where(array("type"=>1))->select();
        $this->assign("found",$found);
        $this->assign("brand",$brand);
        $this->display();
    }

    public function  addRe_to(){

        if (IS_POST) {
           $arr=I();
            if($arr['etime']){
                $arr['etime']=strtotime($arr['etime']);
            }else{
                $arr['etime']= time()+31536000;
            }
            if($arr['type']==3){
                if($arr['cid']==0){
                    $this->error("请选择分类");
                }
            }
            if($arr['lid']==32){
                if($arr['brand_id']==""){
                    $this->error("请选择品牌名称");
                }
            }
            if($arr['lid']==33){
                if($arr['found_id']==""){
                    $this->error("请选择好物名称");
                }
            }
            $rtn = M('data_goods_recommend', NULL)->add($arr);
            if ($rtn) {
                $this->success("添加成功!",U('goods_recommend'));
            } else {
                $this->error("添加失败!",U('goods_recommend'));
            }
        }

    }

    //取消首页推荐
    public function delRecommend(){
        $goodsid = I("goodsid");
        $where['goodsid'] = $goodsid;
        $where['type'] = 1;
        if(M("data_goods_recommend",null)->where($where)->find()){
            if(M("data_goods_recommend",null)->where($where)->delete()){
                succ("取消成功！");
            }else{
                err("取消失败！");
            }
        }else{
            err("取消失败！");
        }
    }
    // 修改让利比列
    public function ret_money()
    {
        extract(require_check("id,ret_money"));
        if (!$id) {
            err('请选择操作数据');
        }
        if (!$ret_money) {
            err('请选择比例');
        }
        $data = array(
            'ret_money' => $ret_money
        );
        $result = M('data_goods', null)->where(array(
            'id' => $id
        ))->save($data);

        succ($result);
    }

    //修改状态
    public function status()
    {
        extract(require_check("id"));
        $status = I('status');
        if (!$id) {
            err('请选择操作数据');
        }
        $data = array(
            'status' => $status
        );
        $result = M('data_goods', null)->where(array(
            'id' => $id
        ))->save($data);

        succ($result);
    }

    //拒绝商品上架
    public function  set_refuse(){
        $id = I('id');
        $cause = I('cause');
        $status = 4;
        $info = M("data_goods",NULL)->where(array('id'=>$id))->find();
        if($info){
            if($info['status']==1){
                err("该商品已发布!");
            }else{
                if(M("data_goods",NULL)->where("id=".$id)->save(array("cause"=>$cause,'status'=>$status))){
                    succ("设置成功!");
                }else{
                    err("设置失败!");
                }
            }
        }else{
            err("设置失败!");
        }
    }

    //设置商品参考价
    public function set_lookprice(){
        $goodsid = I("goodsid");
        $look_price = I("look");
        $info = M("data_goods",NULL)->where(array('id'=>$goodsid))->find();
        if($info){
            if(M("data_goods",NULL)->where(array('id'=>$goodsid))->save(array("look_price"=>$look_price))){
                succ("设置成功!");
            }else{
                err("设置失败!");
            }
        }else{
            err("设置失败!");
        }
    }

    //设置分类列表商品排序
    public function set_csort(){
        $goodsid = I("goodsid");
        $num = I("num");
        if($num<0){
            err("设置参数不正确，请填写大于0的");
        }
        $info = M("data_goods",NULL)->where(array('id'=>$goodsid))->find();
        if($info){
            if(M("data_goods",NULL)->where(array('id'=>$goodsid))->save(array("csort"=>$num))){
                succ("设置成功!");
            }else{
                err("设置失败!");
            }
        }else{
            err("设置失败!");
        }
    }

    /**
     * App闪屏列表
     */
    public function screen_list(){
        $page = I('get.p');
        $condi['type'] = 5;
        $count = M('data_goods_recommend', NULL)->where($condi)->count();
        $p = getpage($count, C('PAGE_SIZE'),$condi);
        $show = $p->newshow();
        $list = M('data_goods_recommend', NULL)->where($condi)->page($page)->limit(C('PAGE_SIZE'))->select();
        $now = time();
        foreach ($list as $k=>$v){
            if($v['etime']<$now){
                $list[$k]['status'] = 1;
            }else{
                $list[$k]['status'] = 0;
            }
        }
        $this->assign("list",$list);
        $this->assign('page', $show);
        $this->assign('p',$page);
        $this->display();
    }

    /**
     * 添加APP闪屏
     */
    public function add_screen(){
        $id = I("id");
        $info = M('data_goods_recommend', NULL)->where(array("id"=>$id))->find();
        $this->assign("id",$id);
        $this->assign("info",$info);
        $this->display();
    }

    public function addscreen_to(){
        if (IS_POST) {
            $id = I("id");
            $title = I('title');
            $link = I('link');
            $linktype = I('link_type');
            $etime = I('etime');
            $pic = I('pic');
            $arr = array(
                'title'=>$title,
                'link' => $link,
                'link_type' => $linktype,
                'etime'=>strtotime($etime),
                'pic'=>$pic,
                'type'=>5
            );
            if($id!=""){
                $rtn = M('data_goods_recommend', NULL)->where(array("id"=>$id))->save($arr);
            }else{
                $rtn = M('data_goods_recommend', NULL)->add($arr);
            }
            if ($rtn) {
                $this->success("设置成功!",U('screen_list'));
            } else {
                $this->error("设置失败!",U('screen_list'));
            }
        }
    }

    /**
     * 删除闪屏
     */
    public function del_screen(){
        $id = I("id");
        $info = M('data_goods_recommend', NULL)->where(array("id"=>$id))->find();
        if($info){
            $rtn = M('data_goods_recommend', NULL)->where(array("id"=>$id))->delete();
            if ($rtn) {
                $this->success("删除成功!",U('screen_list'));
            } else {
                $this->error("删除失败!",U('screen_list'));
            }
        }
    }
    
    /**
     * 品牌列表
     */
    public function big_brand(){
        $p = I("p");
        $count = M("data_brand",null)->where(array("type"=>0))->count();
        $p = getpage($count, C('PAGE_SIZE'));
        $show = $p->newshow();
        $list = M("data_brand",null)->where(array("type"=>0))->page($p)->limit(C('PAGE_SIZE'))->select();
        $this->assign("page",$show);
        $this->assign("list",$list);
        $this->assign("p",$p);
        $this->display();
    }
    
    /**
     * 添加品牌
     */
    public function addBrand(){
        $id = I("id");
        $this->assign("id",$id);
        $info = M("data_brand",null)->where(array("id"=>$id,"type"=>0))->find();
        $this->assign("info",$info);
        $this->display();
    }
    
    /**
     * 执行添加品牌名称
     */
    public function to_addbrand(){
        $id = I("id");
        $name = I('name','','trim');
        $info = M("data_brand",null)->where(array("id"=>$id,"type"=>0))->find();
        if(empty($info)){
            $rtn = M("data_brand",null)->add(array("name"=>$name,"type"=>0));
            if($rtn){
                $this->success("添加成功");
            }else{
                $this->error("添加失败");
            }
        }else{
            $rtn = M("data_brand",null)->where(array("id"=>$id))->save(array("name"=>$name));
            if($rtn){
                $this->success("修改成功");
            }else{
                $this->error("修改失败");
            }
        }
    }

    /**
     * 发现好物
     * @Author   xiaoqiang
     * @DateTime 2017年12月25日
     */
    public function FoundGoods(){
        $p = I("p");
        $count = M("data_brand",null)->where(array('type'=>1))->count();
        $p = getpage($count, C('PAGE_SIZE'));
        $show = $p->newshow();
        $list = M("data_brand",null)->where(array('type'=>1))->page($p)->limit(C('PAGE_SIZE'))->select();
        $this->assign("page",$show);
        $this->assign("list",$list);
        $this->assign("p",$p);
        $this->display();
    }
    
    //添加好物
    public function addFound(){
        $id = I("id");
        $this->assign("id",$id);
        $info = M("data_brand",null)->where(array("id"=>$id,'type'=>1))->find();
        $this->assign("info",$info);
        $this->display();
    }
    
    //好物提交
    public function to_addFound(){
        $id = I("id");
        $name = I('name','','trim');
        $info = M("data_brand",null)->where(array("id"=>$id,'type'=>1))->find();
        if(empty($info)){
            $rtn = M("data_brand",null)->add(array("name"=>$name,'type'=>1));
            if($rtn){
                $this->success("添加成功");
            }else{
                $this->error("添加失败");
            }
        }else{
            $rtn = M("data_brand",null)->where(array("id"=>$id,'type'=>1))->save(array("name"=>$name));
            if($rtn){
                $this->success("修改成功");
            }else{
                $this->error("修改失败");
            }
        }
    }
    
    //搜索栏关键字列表
    public function search_list(){
        $page = I("p");
        $count = M("data_defsearch",null)->count();
        $list = M("data_defsearch",null)->page($page)->limit(10)->select();
        $p = getpage($count, C('PAGE_SIZE'));
        $show = $p->newshow();
        $this->assign("list",$list);
        $this->assign("page",$show);
        $this->display();
    }
    public function edit_search(){
        $id = I("id");
        $info = M("data_defsearch",null)->where(array("id"=>$id))->find();
        $this->assign("info",$info);
        $this->display();
    }
    public function search_to(){
        $id = I("id");
        $name = I("name");
        $type = I("type");
        if(IS_POST){
            $array = array(
                'name'=>$name,
                'type'=>$type,
                'ctime'=>time()
            );
            if(empty($id)){
                $rtn = M("data_defsearch",null)->add($array);
            }else{
                $rtn = M("data_defsearch",null)->where(array("id"=>$id))->save($array);
            }
            if ($rtn) {
                $this->success("设置成功!",U('search_list'));
            } else {
                $this->error("设置失败!",U('edit_search'));
            }
        }
    }
    
    public function delsearch(){
        $id = I("id");
        $info = M("data_defsearch",null)->where(array("id"=>$id))->find();
        if($info){
            $rtn = M("data_defsearch",null)->where(array("id"=>$id))->delete();
            if($rtn){
                succ("删除成功");
            }else{
                err("删除失败");
            }
        }
    }
}
