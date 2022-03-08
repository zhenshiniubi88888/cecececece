<?php

namespace frontend\controllers;

use common\components\Message;
use common\components\MicroApi;
use common\models\Address;
use common\models\Area;
use frontend\service\MiniService;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * CategoryController
 */
class CategoryController extends BaseController
{
    /**
     * 小程序首页V1版本
     * @return mixed
     */
    public function actionMiniV1()
    {
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, MiniService::categoryV1());
    }
}
