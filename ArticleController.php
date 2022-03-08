<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Address;
use common\models\Area;
use common\models\Article;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * ArticleController
 */
class ArticleController extends BaseController
{

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 用户协议
     * @return mixed
     */
    public function actionProtocol()
    {
        $data = [];
        $article = new Article();
        $article_info = $article->getArticleById(49);
        $data['article_info'] = [
            'article_title' => $article_info['article_title'],
            'article_description' => $article_info['article_description'],
            'article_content' => $article_info['article_content'],
            'article_time' => date('Y-m-d', $article_info['article_time'])
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }


}
