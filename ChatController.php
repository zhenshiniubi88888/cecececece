<?php

namespace frontend\controllers;

use common\components\Message;
use common\components\MicroApi;
use common\models\Address;
use common\models\Area;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * ChatController
 */
class ChatController extends BaseController
{
    /**
     * 创建聊天所需用户数据
     * 登录后传花递信息进行注册
     * 未登录传ssid
     */
    public function actionCreate()
    {
        $api = new MicroApi();
        $param = [];
        $param['usercode'] = $this->sessionid;
        $param['platform_from'] = 1;
        $param['platform_userid'] = $this->member_id;
        $response_data = $api->httpRequest('/api/getImUserId', $param);
        if (false == $response_data) {
            \Yii::error($api->getError());
            \Yii::error($api->getResponseBody());
            return $this->responseJson(Message::ERROR, '初始化聊天失败');
        }
        $data = [];
        $user = [
            'userid' => $response_data['userid'],
            'name' => "花递-" . ($this->member_name ? $this->member_name : '用户' . sprintf("%u", ip2long(getIp()))),
            'avatar' => isset($this->member_info['member_avatar']) ? getMemberAvatar($this->member_info['member_avatar']) : 'http://i.huadi01.com/shop/avatar/2fec351909e88cf890525b872603b170.png',
            'tokens' => md5(uniqid()),
        ];
        $data['user'] = $user;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
}
