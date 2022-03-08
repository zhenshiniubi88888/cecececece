<?php

namespace frontend\controllers;

use Api\Tencent\Wechat;
use common\components\Message;
use yii\web\HttpException;

/**
 * ShareController
 */
class ShareController extends BaseController
{
    public function init()
    {
        parent::init();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    public function actionWechat()
    {
        $current_url = urldecode(\Yii::$app->request->post('current_url'));
        $data = [];
        $data['sign_package'] = Wechat::GetSignPackage($current_url);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
    public function actionMshortUrl(){
        $url = Request('url','');
        if($url){
//            $api_url = 'http://hua123.test.tfxk.org/index.php?act=api&op=get_mshort';//dev
            $api_url = 'https://www.aihuaju.com/index.php?act=api&op=get_mshort';
            $res = sendPost($api_url,['url' => $url]);
            $res = json_decode($res,true);
            if(isset($res['error_code']) && $res['error_code'] == 1){
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['short_url' => $res['url']]);
            }
        }
        return $this->responseJson(Message::ERROR, '生成短链失败','');
    }


}
