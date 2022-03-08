<?php

namespace frontend\controllers;

use common\components\HuawaApi;
use common\components\Log;
use common\components\Message;
use common\components\WeixinHuadi;
use common\components\WeixinSubscribeMsg;
use common\models\HuawaSend;
use common\models\Member;
use common\models\MemberExppointsLog;
use common\models\OrderGoods;
use common\models\Orders;
use common\models\AppMessage;
use common\models\RefundReturn;
use yii\base\Model;
use yii\web\Controller;
use yii\web\HttpException;

/**
 * ApiController
 */
class PushMessageController extends BaseController
{

    public function init()
    {
        $this->enableCsrfValidation = false;
        parent::init();
    }

    // 新增app推送消息
    public function actionAdd()
    {
        $code = Message::ERROR;
        $msg  = '未知错误';

        // 新增数据.
        $appMessageModel = new AppMessage();
        $appMessageModel->app_message_content = $_POST['content'];
        $appMessageModel->app_message_rst_id = $_POST['rst_id'];
        $appMessageModel->app_message_add_time = time();
        $result = $appMessageModel->insert();
        // 判断返回.
        if ($result) {
            $code = Message::SUCCESS;
            $msg  = 'success';
        }

        return $this->responseJson($code, $msg, array());

    }


}
