<?php
namespace Api\Controller;
Use Think\Controller;
class UploadController extends Controller{

    /**
     * 批量上传图片  未完善
     * @return [type] [description]
     */
    public function imgs() {
        $upload = new \Think\Upload();// 实例化上传类
        $key = array_shift(array_keys($_FILES));
        $upload->maxSize   =     12582912 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型     
        $upload->savePath = '/images/';
        $info = $upload->upload();
        dump($info);
        $path = '/Uploads'.$info[$key]['savepath'].$info[$key]['savename'];
        if($upload->getError()){
            err($upload->getError());
        }else{
            $image = new \Think\Image();
            $image->open('.' . $path);
            $image->thumb($image->width(), $image->height(), 1)->save('.' . $path);
            $data = array(
                'path'=>$path,
            );
            succ($data);
        }
    }

    /**
     * 单图片上传
     * @return [type] [description]
     */
    public function img(){
        $upload = new \Think\Upload();// 实例化上传类
        $key = array_shift(array_keys($_FILES));
        $upload->maxSize   =     12582912 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型     
        $upload->savePath  = '/images/';
        $info   =  $upload->uploadOne($_FILES['img']);
        $path   = '/Uploads'.$info[$key]['savepath'].$info[$key]['savename'];
        if($upload->getError()){
            err($upload->getError());
        }else{
            $image = new \Think\Image();
            $image->open('.' . $path);
            $image->thumb($image->width(), $image->height(), 1)->save('.' . $path);
            $data = array(
                'path'=>$path,
            );
            succ($data);
        }
    }

    /**
     * 单图片上传
     * @return [type] [description]
     */
    public function uploadImg(){
        $upload = new \Think\Upload();// 实例化上传类
        $key = array_shift(array_keys($_FILES));
        $upload->maxSize   =     12582912 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
        $upload->savePath  = '/images/';
        $info   =  $upload->upload();
        $path   = '/Uploads'.$info[$key]['savepath'].$info[$key]['savename'];
        if($upload->getError()){
            err($upload->getError());
        }else{
            $image = new \Think\Image();
            $image->open('.' . $path);
            $image->thumb($image->width(), $image->height(), 1)->save('.' . $path);
            $data = array(
                'path'=>$path,
            );
            succ($data);
        }
    }
    
    /**
     * 会员头像
     * @Author 刘晓雨    2016-03-30
     * @param  file $headimg  头像文件['jpg', 'gif', 'png', 'jpeg']
     */
    public function headimg(){
        $filename = 'userHeadImg-'.strval($userid).create_code();
        $rootpath = './Uploads/headimg/';
        $saveExt  = "jpg";
        $upload = new \Think\Upload();
        $upload->maxSize   =     3145728 ;
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');
        $upload->saveExt   =     $saveExt;
//                $upload->autoSub   =     false;
        $upload->saveName  =     $filename;
        $upload->replace   =     true;
        $upload->rootPath  =     $rootpath;
        $info   =   $upload->uploadOne($_FILES['headimg']);
        if($info){
            $img = $upload->rootPath . $info['savepath'] . $info['savename'];
        }else{
            err($upload->getError());
        }
        $image = new \Think\Image();
        $image->open($img)->thumb(160, 160, 3)->save($img);                
        succ("上传成功", ltrim($img, "."));
    }
}

