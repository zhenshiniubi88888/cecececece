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
 * HomepageController
 */
class HomepageController extends BaseController
{
    /**
     * 小程序首页V1版本
     * @return mixed
     */
    public function actionMiniV1()
    {
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, MiniService::homeV1());
    }

    /**
     * 百度小程序首页 更多鲜花资讯
     * @return mixed
     */
    public function actionArticleMore()
    {
        $post = \Yii::$app->request->post();
        $curpage = isset($post['curpage']) ? intval($post['curpage']) : 1;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, MiniService::articleMore($curpage));
    }

    /**
     * 百度小程序首页 鲜花资讯详情
     * @return mixed
     */
    public function actionArticleInfo()
    {
        $post = \Yii::$app->request->post();
        $news_id = isset($post['news_id']) ? intval($post['news_id']) : 0;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, MiniService::articleInfo($news_id));
    }
}
