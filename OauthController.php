<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Address;
use common\models\Area;
use common\models\Favorites;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * OauthController
 */
class OauthController extends BaseController
{
    public function init()
    {
        parent::init();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    public function actionCallback(){
        \Yii::info(\Yii::$app->request);
    }

    public function actionCancel(){
        \Yii::info(\Yii::$app->request);
    }

}
