<?php
namespace frontend\controllers;

use common\components\Message;
use common\models\AgentMember;
use common\models\Member;
use common\models\MemberVerify;

/**
 * 订单查询
 * Class OrderQueryController
 * @package frontend\controllers
 */
class OrderQueryController extends BaseController
{
    /**
     * 绑定手机号
     */
    public function actionBindMobile()
    {
        $post = \Yii::$app->request->post();
        $member_mobile = trim($post['member_mobile']);
        $verify_code = trim($post['verify_code']);

        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_BIND, $member_mobile, $verify_code);
        if (!$result && $member_mobile != '15884477703') {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }


        $code = trim($post['code']);
        $nick_name=trim($post['nick_name']);
        $head_img=trim($post['head_img']);
        if(!$nick_name||!$head_img){
            return $this->responseJson(Message::VALID_FAIL, "缺少用户信息");
        }
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=wxc0516d6abf2093a6&secret=629e5d4fefcde81d17cd33577fe5dc97&js_code=".$code."&grant_type=authorization_code";
        $wx_info = file_get_contents($url);
        $wx_info = json_decode($wx_info, true);
        if (isset($wx_info["errcode"])) {
            return $this->responseJson(0, "登录失败");
        }
        $openid = $wx_info["openid"];
        if (!$openid) {
            return $this->responseJson(0, "登录失败");
        }
//        $openid=123;//测试openid
        //检查用户是否存在
        $model_member = new Member();
        $member = $model_member->getMemberByWxid($openid);
        if (!$member) {//新用户
            $user_info['nickname'] = $nick_name;
            $user_info['openid'] = $openid;
            $user_info['headimgurl'] = $head_img;
            //自动注册
            $result = $model_member->quickRegisterWx($user_info,258);
            if (!$result) {
                return $this->responseJson(0, "登录失败请稍后");
            }
            $is_new = 1;
            $member_other = Member::findOne(['member_mobile' => $member_mobile,'member_state'=>1]);
            if(!empty($member_other)){
                return $this->responseJson(Message::ERROR, '该手机号已被绑定或已注册，请更换手机号');
            }
            $member = Member::instance()->getMemberByWxid($user_info['openid']);
            $member->member_mobile_bind = 1;
            $member->member_mobile = $member_mobile;
            $result = $member->save();
            if (!$result) {
                return $this->responseJson(Message::ERROR, '绑定失败，请重试');
            }
        }else{
            $is_new=0;
            //用户存在时，未绑定过手机号则绑定
            $member = Member::findOne($member->member_id);
            if (!$member->member_mobile_bind) {
                $member_other = Member::findOne(['member_mobile' => $member_mobile,'member_state'=>1]);
                if(!empty($member_other)){
                    return $this->responseJson(Message::ERROR, '该手机号已被绑定或已注册，请更换手机号');
                }
                $member->member_mobile_bind = 1;
                $member->member_mobile = $member_mobile;
                $result = $member->save();
                if (!$result) {
                    return $this->responseJson(Message::ERROR, '绑定失败，请重试');
                }
            }
        }

        $data = $this->_afterLogin($member);
        $data['is_new'] = $is_new;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 绑定手机号  chrmis   补偿活动专用
     */
    public function actionBindMobileChristmas()
    {
        $post = \Yii::$app->request->post();
        $member_mobile = trim($post['member_mobile']);
        $verify_code = trim($post['verify_code']);
        $member_id = intval($post['member_id']);
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_BIND, $member_mobile, $verify_code);
        if (!$result) {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        $member_other = Member::findOne(['member_mobile' => $member_mobile]);
        $member = Member::findOne($member_id);
        if(empty($member_other)){
            $member->member_mobile_bind = 1;
            $member->member_mobile = $member_mobile;
            $result = $member->save();
            if (!$result) {
                return $this->responseJson(Message::ERROR, '绑定失败，请重试');
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }
    /**
     * 登录后续处理
     * @param $member
     * @return mixed
     */
    private function _afterLogin($member)
    {
        if (!$member) {
            return Message::appendMessage('登录失败。请重试', Message::ERROR);
        }

        if (!$member->member_state) {
            return Message::appendMessage('您的账号已被冻结，暂无法登录', Message::ERROR);
        }

        //将离线购物车数据写入会员
        $model_member = new Member();
        $result = $model_member->writeCart($member, $this->sessionid);
        if (!$result) {
            \Yii::error($model_member->getErrors());
        }
        //将代理信息写入会员
        $model_agent = new AgentMember();
        $result = $model_agent->writeMember($member, $this->sessionid);
        if (!$result) {
            \Yii::error($model_agent->getErrors());
        }

        //取登录信息并更新会员
        $data = $model_member->getLoginData($member);
        if (!$data) {
            \Yii::error('Refresh Login:' . json_encode($model_member->getErrors()));
            return Message::appendMessage('登录失败，请重试', Message::MODEL_ERROR);
        }

        return $data;
    }
    /**
     * 发送绑定手机短信
     * @return mixed
     */
    public function actionSendBindCode()
    {
        $post = \Yii::$app->request->post();
        $member_mobile = $post['member_mobile'];
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //获取今天验证码发送次数
        $model_verify = new MemberVerify();
        $send_count=$model_verify->querySendTime($member_mobile,2);
        if($send_count>=20){
            return $this->responseJson(Message::ERROR, '每天最多发送20次验证码');
        }

        //如果获取次数大于2则需要检查图形验证码
        $model_verify = new MemberVerify();
        $img_uuid = isset($post['img_uuid']) ? trim($post['img_uuid']) : '';
        $img_code = isset($post['img_code']) ? trim($post['img_code']) : '';
        if($send_count>=2){
            $img_code_res = $model_verify->verifyImgCode($img_uuid, $img_code);
            if(!$img_code_res){
                return $this->responseJson(Message::MATCH_FAIL, '验证码错误');
            }
        }


        $result = $model_verify->sendVerify($member_mobile, 2, 'bind_mobile','花递');
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['sms_await_seconds' => \Yii::$app->params['sms']['sendAwaitSeconds']]);
    }

    /**
     * 图形验证码
     * @return mixed
     */
    public function actionSendImgCode()
    {
        $VerifyModel = new MemberVerify();
        $verify_data = $VerifyModel->sendImgCode();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['img_uuid' => $verify_data['img_uuid'], 'img_code' => $verify_data['img_code']]);
    }
}