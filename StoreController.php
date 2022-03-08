<?php

namespace frontend\controllers;

use common\components\Alipush;
use common\components\Message;
use common\models\Adv;
use common\models\Article;
use common\models\Favorites;
use common\models\Goods;
use linslin\yii2\curl\Curl;
use Yii;
use yii\base\InvalidParamException;
use yii\db\Expression;
use yii\imagine\Image;
use yii\web\BadRequestHttpException;
use frontend\controllers\BaseController;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * Store controller
 */
class StoreController extends BaseController
{

    public function init()
    {
        parent::init();
        $this->validLogin();
    }

    /**
     * 添加店铺关注
     * @return mixed
     */
    public function actionFollow()
    {
        $type = Yii::$app->request->post("type", 1);
        $store_id = Yii::$app->request->post("store_id", 0);
        $store_name = Yii::$app->request->post("store_name", "");
        if (empty($store_id) || empty($store_name)) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $favorites_model = new Favorites();
        $result = $favorites_model->addFollow($this->member_id, $this->member_name, $store_id, $store_name, $type);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $favorites_model->getErrors()["error"][0]);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    public function actionTest()
    {

        //  $push = Yii::$app->get("alipush");
//        $result = Alipush::push('送东京', '你有一个新订单', ["41E75B4296CAA12AB42C8DDBFD4820D7"], [], 'newOrder.mp3', '+1');
//        print_r($result);
//        die;
//        $query = Goods::find();
//        $result = $query->count();
//        print_r($query->createCommand()->getRawSql());
//        die;
    }


}
