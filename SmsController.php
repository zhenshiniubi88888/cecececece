<?php

namespace frontend\controllers;

use common\components\Message;
use common\helper\CaptchaHelper;
use common\models\MemberVerify;
use common\models\MsgTemlates;
use yii\web\HttpException;

/**
 */
class SmsController extends BaseController
{
    public function actionIndex()
    {
//        return \Yii::$app->response->setStatusCode(403)->send();
        throw new HttpException(405);
    }


    /**
     * 发送短信
     * @return mixed
     */
    public function _sendCode($send_type, $send_code)
    {
        $post = \Yii::$app->request->post();
        $member_mobile = $post['member_mobile'];
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        $model_verify = new MemberVerify();
        $sign = '';
        if(SITEID == 258){
            $sign = '花递';
        }
        $result = $model_verify->sendVerify($member_mobile, $send_type, $send_code,$sign);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['sms_await_seconds' => \Yii::$app->params['sms']['sendAwaitSeconds']]);
    }

    /**
     * @param $send_type
     * @param $send_code
     * @return mixed
     */
    public function _sendVerifyCode($send_type, $send_code)
    {
        $model_verify = new MemberVerify();
        $post = \Yii::$app->request->post();
        $member_mobile = $post['member_mobile'];
        //获取 曾 接口返回信息
        $mobileVerify = $model_verify->verifyMobile($member_mobile, 1);
        $json_res = json_decode($mobileVerify, true);
        if ($json_res['status']) {
            return $this->responseJson(2, '您已是花递直卖的商家，请前往下载商家端“花娃”！', []);
        }
        $img_uuid = isset($post['img_uuid']) ? trim($post['img_uuid']) : '';
        $img_code = isset($post['img_code']) ? trim($post['img_code']) : '';
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        $img_codes = $model_verify->sendImgCode();
        //统计该号码的发送次数；
        $times = $model_verify->querySendTime($member_mobile, $send_type);
        if ($times >= 2) {
            //发送图片验证码
            if (strlen($img_uuid) && strlen($img_code)) {
                //验证之后再发送
                $img_code_res = $model_verify->verifyImgCode($img_uuid, $img_code);
                if ($img_code_res) {
                    $result = $model_verify->sendVerify($member_mobile, $send_type, $send_code);
                    if (!$result) {
                        return $this->responseJson(Message::ERROR, $model_verify->getFirstError(Message::MODEL_ERROR));
                    }
                    return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
                        'sms_await_seconds' => \Yii::$app->params['sms']['sendAwaitSeconds'],
                        'show_img_code' => 1,
                        'img_uuid' => $img_codes['img_uuid'],
                        'img_code' => $img_codes['img_code'],
                        'img_verify' => 1,
                    ]);
                } else {
                    return $this->responseJson(Message::VALID_FAIL, '图片验证码错误或过期');
                }
            } else {
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
                    'sms_await_seconds' => \Yii::$app->params['sms']['sendAwaitSeconds'],
                    'show_img_code' => 1,
                    'img_uuid' => $img_codes['img_uuid'],
                    'img_code' => $img_codes['img_code'],
                    'img_verify' => 0,
                ]);
            }
        } else {
            $result = $model_verify->sendVerify($member_mobile, $send_type, $send_code);
            if (!$result) {
                return $this->responseJson(Message::ERROR, $model_verify->getFirstError(Message::MODEL_ERROR));
            }
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
                'sms_await_seconds' => \Yii::$app->params['sms']['sendAwaitSeconds'],
                'show_img_code' => 0,
                'img_uuid' => $img_codes['img_uuid'],
                'img_code' => $img_codes['img_code'],
                'img_verify' => 0,
            ]);
        }
        $result = $model_verify->sendVerify($member_mobile, $send_type, $send_code);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
            'sms_await_seconds' => \Yii::$app->params['sms']['sendAwaitSeconds'],
            'show_img_code' => 0,
            'img_uuid' => $img_codes['img_uuid'],
            'img_code' => $img_codes['img_code'],
        ]);
    }

    /**
     * 发送登录短信
     * @return mixed
     */
    public function actionSendLoginCode()
    {
        return $this->_sendCode(MemberVerify::SEND_TYPE_LOGIN, 'smslogin');
    }


    /**
     * 发送绑定手机短信
     * @return mixed
     */
    public function actionSendBindCode()
    {
        return $this->_sendCode(MemberVerify::SEND_TYPE_BIND, 'bind_mobile');
    }


    /**
     * 发送账户安全认证短信
     * @return mixed
     */
    public function actionSendAuthCode()
    {
        return $this->_sendCode(MemberVerify::SEND_TYPE_AUTH, 'authenticate');
    }
    /**
     * 发送手机号解绑短信
     * @return mixed
     */
    public function actionSendUnbindSms()
    {

        return $this->_sendCode(MemberVerify::SEND_TYPE_UNBIND, 'smsunbind');
    }

    /**
     * @return mixed
     */
    public function actionSendImgCode()
    {
        $VerifyModel = new MemberVerify();
        $verify_data = $VerifyModel->sendImgCode();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['img_uuid' => $verify_data['img_uuid'], 'img_code' => $verify_data['img_code']]);
    }

    /**
     * 发送花递-开店入驻 短信验证码
     * @return mixed
     */
    public function actionSendShopEnterCode()
    {
        return $this->_sendVerifyCode(MemberVerify::SEND_TYPE_SHOP_ENTER, 'smslogin');
    }

}
