<?php

namespace frontend\controllers;

use common\components\HuawaApi;
use common\components\Message;
use common\models\UploadForm;
use huadi\oss\AliOss;
use OSS\OssClient;
use Think\Upload;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 */
class UploadController extends BaseController
{
    /**
     * 图片统一上传处理中心
     * @return mixed
     */
    public function actionIndex()
    {
        header('Content-Type: multipart/form-data;charset=utf-8');
        $post = \Yii::$app->request->post();
        $upload = new Upload();
        $upload->autoSub = false;
        $upload->saveName = ['md5', [microtime(true) . rand(1, 99999)]];
        $config = $this->getUploadConfig($post['type']);
        foreach ($config as $key => $val) {
            $upload->$key = $val;
        }
        //$_FILES['file']
        $result = $upload->upload('');
        if (!$result) {
            return $this->responseJson(Message::ERROR, $upload->getError());
        }
        $data = [];
        foreach ($result as $key => $img) {
            $data = [
                'filename' => $img['subpath'] . $img['savename'],
                'url' => UPLOAD_SITE_URL . DS . $img['savepath'] . $img['savename']
            ];
            //只取一张
            break;
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 图片base64上传
     */
    public function actionBase64()
    {
        $post = \Yii::$app->request->post();
        $type = isset($post['type']) ? $post['type'] : '';
        $img_base64 = isset($post['img_base64']) ? $post['img_base64'] : '';
        if($type == '' || $img_base64 == ''){
            return $this->responseJson(Message::ERROR, Message::REQUIRE_PARAMETER_MSG);
        }
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $img_base64, $result)){
            $type = $result[2];//从base64中提取出的文件类型
            $fileName = md5(microtime(true) . rand(1, 99999)).".".$type;
            $filePath = "images/{$fileName}";
            $newFileBase64 = str_replace($result[1], '', $img_base64);//将原base64内容中的形如《data:image/jpg;base64》替换掉
            $base64decode = base64_decode($newFileBase64);//将新的base64解码
            $save_local = file_put_contents($filePath, $base64decode);
            if(!$save_local){
                return $this->responseJson(Message::ERROR, Message::ERROR_MSG.'保存本地失败');
            }

            $dir_path = $this->getUploadConfig($post['type']);
            $oss_path = $dir_path['savePath'].$fileName;
            $config = \Yii::$app->params['upload']['driverConfig'];
            $result = AliOss::uploadFile($config['bucket'], $oss_path, $filePath);
            if($result){
                @unlink($filePath);
                $data = [
                    'filename' => $fileName,
                    'url' => UPLOAD_SITE_URL . DS . $oss_path
                ];
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
            }
        }
        return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
    }

    /**
     * 图片二进制上传
     * @return mixed
     */
    public function actionBinary($type = '', $binary = '')
    {
        $post = \Yii::$app->request->post();
        $upload = new Upload();
        $upload->autoSub = true;
        $upload->subName = array('date', 'Y/m/d');
        $upload->saveName = ['md5', [microtime(true) . rand(1, 99999)]];

        $config = $this->getUploadConfig($type ? $type : $post['type']);
        foreach ($config as $key => $val) {
            $upload->$key = $val;
        }
        $result = $upload->binary($binary ? $binary : $post['file'],$post['file_name']);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $upload->getError());
        }
        $data = [];
        foreach ($result as $key => $img) {
            $data = [
                'filename' => $img['subpath'] . $img['savename'],
                'url' => UPLOAD_SITE_URL . DS . $img['savepath'] . $img['savename']
            ];
            //只取一张
            break;
        }
        if ($type && $binary) {
            return $data;
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 视频上传
     * @return mixed
     */
    public function actionMedia()
    {
        $upload = new Upload();
        $upload->autoSub = true;
        $upload->subName = array('date', 'Y/m/d');
        $upload->saveName = ['md5', [microtime(true) . rand(1, 99999)]];
        $upload->maxSize = 1024 * 1024 * 20;
        $upload->exts = ['mp4','mov'];
        $upload->savePath = ATTACH_MEDIA;
        $result = $upload->upload();
        if (!$result) {
            return $this->responseJson(Message::ERROR, $upload->getError());
        }
        $data = [];
        foreach ($result as $key => $img) {
            $data = [
                'filename'     => $img['subpath'] . $img['savename'],
                'url'          => UPLOAD_SITE_URL . DS . $img['savepath'] . $img['savename'],
                'share_images' => $img['subpath'] . $img['savename'],
            ];
            //只取一张
            break;
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取不同类型的配置信息
     * @param $type
     * @return array
     */
    private function getUploadConfig($type)
    {
        $config = [];
        $config['maxSize'] = 1024 * 1024 * 2;
        $config['exts'] = ['png', 'jpg', 'jpeg'];
        $config['savePath'] = ATTACH_TMP;
        switch ($type) {
            // 头像上传
            case 'avatar':
                $config['savePath'] = ATTACH_AVATAR;
                break;
            // 头像上传
            case 'wximg':
                $config['savePath'] = "wximg";
                break;
            // 分销头像上传
            case 'share_avatar':
                $config['savePath'] = 'share_avatar';
                break;
            // 意见反馈
            case 'feedback':
                $config['savePath'] = ATTACH_FEEDBACK;
                break;
            // 申请售后
            case 'service':
                $config['savePath'] = ATTACH_SERVICE;
                break;
            // 评价
            case 'comment':
                $config['savePath'] = ATTACH_COMMENT;
                break;
            // 消息图片
            case 'message':
                $config['savePath'] = ATTACH_MESSAGE;
                break;
            // 消息图片
            case 'real_pic':
                $config['savePath'] = ATTACH_ORDER;
                break;
            // 分享生活
            case 'moments':
                $config['savePath'] = ATTACH_MOMENTS;
                break;
            // 分享生活
            case 'qrcode':
                $config['savePath'] = ATTACH_QRCODE;
                break;
            // 入驻申请资料
            case 'apply':
                $config['savePath'] = ATTACH_APPLY;
                break;
            // 纪念日上传主题图片
            case 'souvenir':
                $config['savePath'] = ATTACH_SOUVENIR;
                break;
        }
        $config['savePath'] .= DS;
        return $config;
    }


    /**
     * 头像上传
     * @return mixed
     */
    public function actionAvatar()
    {
        return 1;
        $upload = new Upload();
        $upload->maxSize = 1024 * 1024 * 2;
        $upload->exts = ['png', 'jpg', 'jpeg'];
        $upload->autoSub = false;
        $upload->savePath = ATTACH_AVATAR . DS;
        $upload->saveName = ['md5', [microtime(true)]];
        $result = $upload->upload($_FILES['file']);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $upload->getError());
        }
        $filename = $result['file']['savename'];
        $data = [
            'filename' => $filename,
            'url' => UPLOAD_SITE_URL . DS . ATTACH_PATH . DS . $filename];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    public function actionTest11111rrrr()
    {
        return 1;
        $model = new UploadForm();
        if (\Yii::$app->request->isPost) {
            $upload = new Upload();
            $upload->maxSize = 1024 * 1024 * 2;
            $upload->exts = ['png', 'jpg', 'jpeg'];
            $upload->autoSub = false;
            $upload->savePath = ATTACH_AVATAR . DS;
            $upload->saveName = ['md5', [microtime(true) . '_1111']];
            $result = $upload->upload();
            if (!$result) {
                var_dump($upload->getError());
                die;
            }
            var_dump($result);
            $url = UPLOAD_SITE_URL . DS . ATTACH_AVATAR . $result['file']['savename'];
            var_dump($url);
        }
        $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data', 'action' => '/upload/avatar']]);
        $html = <<<HTML
<input type="file" name="file">
HTML;
        echo $html;
        echo Html::submitButton('提交');
        ActiveForm::end();
    }

    public function actionTest23112(){
//        $image_file = "http://resource.huawa.com/shop/placeorder/05951535252915882.jpg";
//        if ($image_file) {
//            $image_content = base64_encode(file_get_contents($image_file));
//            if ($image_content) {
//                $upload = new Upload();
//                $upload->autoSub = true;
//                $upload->subName = array('date', 'Y/m/d');
//                $upload->saveName = ['md5', [microtime(true) . rand(1, 99999)]];
//                $upload->savePath = ATTACH_ORDER;
//                $result = $upload->binary($image_content);
//                var_dump($result);
//                if ($result) {
//                    var_dump($result[0]['subpath'] . $result[0]['savename']);
//                }
//            }
//        }
//        die;


//        $ahj_data = [];
//        $ahj_data['three_shop_name'] = "爱花居（纬一路1518店）";
//        $ahj_data['order_from'] = 7;
//        $ahj_data['province_id'] = 17;
//        $ahj_data['city_id'] = 261;
//        $ahj_data['area_id'] = 1708;
//        $ahj_data = base64_encode(json_encode($ahj_data));
//        $keycode = HUAWA_KEY;
//        $keycode = md5(md5($ahj_data . $keycode));
//        $res = sendPost("http://www.aihuaju.com/huadi_api/get_hezuo_store_id", ['data' => $ahj_data, 'key' => $keycode]); //10.30.201.53
//        $response = json_decode(trim($res), true);
//        if ($response && isset($response['code']) && $response['code']) {
//            $is_hezuo = isset($response['data']['is_hezuo']) ? $response['data']['is_hezuo'] : 0;
//            $data['store_id'] = isset($response['data']['store_id']) ? $response['data']['store_id'] : 0;
//        }
//        var_dump($is_hezuo);
//        var_dump($data);die;
    }
}
