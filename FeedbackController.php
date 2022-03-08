<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Address;
use common\models\Area;
use common\models\Feedback;
use common\models\FeedbackType;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * FeedbackController
 */
class FeedbackController extends BaseController
{

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * @return mixed
     */
    public function actionType()
    {
        $data = [];
        $type = new FeedbackType();
        $data['type_list'] = $type->getOnlineType();
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * @return mixed
     */
    public function actionSubmit()
    {
        if(!$this->isLogin() || empty($this->member_info['member_mobile'])){
            return $this->responseJson(Message::ERROR, '未绑定手机号');
        }
        $param = \Yii::$app->request->post();
        $t_id = (int)$param['t_id'];
        $type_info = FeedbackType::getTypeById($t_id);
        if (!$type_info) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $f_content = trim($param['f_content']);
        if (mb_strlen($f_content, 'UTF-8') > 200 || mb_strlen($f_content, 'UTF-8') < 10) {
            return $this->responseJson(Message::ERROR, '请输入10-200字的描述');
        }
        $pic = explode(',', $param['f_pic']);
        if (count($pic) > 3) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        foreach ($pic as $p) {
            if ($p) {
                if (!isImgName_wx($p)) {
                    return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
                }
            }
        }
        $feedback = new Feedback();
        $feedback->t_id = $type_info['t_id'];
        $feedback->t_content = $type_info['t_content'];
        $feedback->f_content = $f_content;
        $feedback->f_pic = $param['f_pic'];
        $feedback->user_agent = isset($param['user_agent']) ? $param['user_agent'] : \Yii::$app->request->userAgent;
        $feedback->app_version = isset($param['app_version']) ? $param['app_version'] : $_SERVER['API_VERSION'];
        $feedback->ip = getIp();
        if ($this->isLogin()) {
            $feedback->member_id = $this->member_id;
            $feedback->member_name = $this->member_name;
        } else {
            $feedback->is_anonymous = 1;
        }
        $feedback->f_state = 0;
        $feedback->device_type=isset($param['device_type'])?$param['device_type']:'wap';
        $feedback->add_time = TIMESTAMP;
        $result = $feedback->insert(false);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }


}
