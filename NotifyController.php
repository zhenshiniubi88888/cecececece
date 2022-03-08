<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Notify;
use yii\web\HttpException;

/**
 * NotifyController
 */
class NotifyController extends BaseController
{
    public function init()
    {
        parent::init();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 获取未读消息数量
     * @return array
     */
    public function actionUnread()
    {
        $data = [];
        $data['unread_num'] = Notify::unReadNum($this->member_id);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取最新通知
     * @return mixed
     */
    public function actionLatest()
    {
        $data = [];

        $notify = [];
        $notify['has_msg'] = 0;
        $notify['is_new'] = 0;
        $notify['latest'] = [];
        if ($this->isLogin()) {
            //获取最新一条通知
            $latest = Notify::getLatestNotify($this->member_id);
            $notify['has_msg'] = Notify::unReadNum($this->member_id);
            if ($latest) {
                $notify['is_new'] = $latest['is_read'] ? 0 : 1;
                $notify['latest'] = [
                    'title' => $latest['notify_title'],
                    'content' => $latest['notify_content'],
                    'time' => getFriendlyTime($latest['add_time'])
                ];
            }
        }
        $data['notify'] = $notify;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取最新动态
     * @return mixed
     */
    public function actionDynamicLatest()
    {
        $data = [];

        $notify = [];
        $notify['has_msg'] = 0;
        $notify['is_new'] = 0;
        $notify['latest'] = [
            'title' => "",
            'content' => "",
            'time' => ""
        ];
        if ($this->isLogin()) {
            //获取最新一条通知
            $latest = Notify::getLatestDynamicNotify($this->member_id);
            $notify['has_msg'] = Notify::unDynamicReadNum($this->member_id);
            if ($latest) {
                $notify['is_new'] = $latest['is_read'] ? 0 : 1;
                $notify['latest'] = [
                    'title' => $latest['notify_title'],
                    'content' => $latest['notify_content'],
                    'time' => getFriendlyTime($latest['add_time'])
                ];
            }
        }

        $data['notify'] = $notify;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取消息通知
     * @param int $page
     * @return mixed
     */
    public function actionDynamicList()
    {
        $data = [];
        $data['notify_list'] = Notify::getDynamicFriendlyNotify($this->member_id, \Yii::$app->request->post('page', 1));
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取动态通知
     * @param int $page
     * @return mixed
     */
    public function actionList()
    {
        $data = [];
        $data['notify_list'] = Notify::getFriendlyNotify($this->member_id, \Yii::$app->request->post('page', 1));
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
}
