<?php

namespace frontend\controllers;

use common\components\HuawaApi;
use common\components\Log;
use common\components\Message;
use common\models\Address;
use common\models\Area;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * TraceController
 */
class TraceController extends BaseController
{
    public function actionIndex()
    {
        throw new HttpException(405);
    }

    public function actionError()
    {
        Log::writelog('trace', \Yii::$app->request->post());
    }
}
