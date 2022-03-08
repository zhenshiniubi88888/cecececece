<?php

namespace frontend\controllers;

use common\components\Char;
use common\components\HuawaApi;
use common\components\Log;
use common\components\Message;
use common\models\Agent;
use common\models\AgentApply;
use common\models\AgentMember;
use common\models\HuawaSend;
use common\models\MemberVerify;
use yii\web\Controller;
use yii\web\HttpException;

/**
 * AgentController
 */
class AgentController extends BaseController
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
     * 提交代理申请1
     * @return mixed
     */
    public function actionApply1()
    {
        $this->validLogin();

        $post = \Yii::$app->request->post();
        $member_mobile = trim($post['member_mobile']);
        $verify_code = trim($post['verify_code']);
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_AUTH, $member_mobile, $verify_code);
        if (!$result) {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }

        $apply_name = trim($post['apply_name']);
        $company_name = trim($post['company_name']);
        $area_info = trim($post['area_info']);
        if (!$apply_name) {
            return $this->responseJson(Message::VALID_FAIL, '请填写姓名');
        }
        if (!$company_name) {
            return $this->responseJson(Message::VALID_FAIL, '请填写公司名称');
        }
        if (!$area_info) {
            return $this->responseJson(Message::VALID_FAIL, '请选择代理城市');
        }

        $apply = new AgentApply();
        $apply->member_id = $this->member_id;
        $apply->apply_name = trim($post['apply_name']);
        $apply->company_name = trim($post['company_name']);
        $apply->province_id = (int)$post['province_id'];
        $apply->city_id = (int)$post['city_id'];
        $apply->area_id = (int)$post['area_id'];
        $apply->area_info = $post['area_info'];
        $apply->apply_mobile = $member_mobile;
        $apply->apply_time = TIMESTAMP;
        $apply->apply_from = AgentApply::APPLY_FROM_WEB;
        $apply->ip = getIp();
        $apply->apply_status = AgentApply::APPLY_STATUS_NEW;
        $result = $apply->insert();
        if (!$result) {
            return $this->responseJson(Message::ERROR, '提交申请失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['apply_info' => $apply->toArray()]);
    }

    public function actionApply()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $member_mobile = trim($post['member_mobile']);
        $verify_code = trim($post['verify_code']);
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_AUTH, $member_mobile, $verify_code);
        if (!$result) {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        $version = $post['verison'] == 'new'?'new':'old';
        $apply = new AgentApply();
        $apply->member_id = $this->member_id;
        $apply->apply_mobile = $member_mobile;
        $apply->apply_time = TIMESTAMP;
        $apply->apply_from = AgentApply::APPLY_FROM_WEB;
        $apply->ip = getIp();
        $apply->apply_status = AgentApply::APPLY_STATUS_NEW;
        if($version == 'new'){
            $add_field = ['apply_name', 'area_info', 'login_pwd', 'idcard_code', 'idcard_positive', 'idcard_obverse', 'idcard_handheld', 'shop_name','shop_introduction','shop_images','company_buslicense','company_usic','province_id','city_id','area_id'];
            $add_data = [];
            $err_msg = [
                'apply_name'=>'请填写姓名',
                'area_info'=>'请选择代理城市',
                'login_pwd'=>'请填写登录密码',
                'idcard_code'=>'请填写正确的身份证号',
                'idcard_positive'=>'请上传正确的身份证正面照',
                'idcard_obverse'=>'请上传正确的身份证反面照',
                'idcard_handheld'=>'请上传正确的身份证手持照',
                'shop_name'=>'请填写店铺名称',
                'shop_introduction'=>'请填写店铺简介',
                'shop_images'=>'请上传店铺图片，不少于四张',
                'company_buslicense'=>'请上传正确的营业执照',
                'company_usic'=>'请填写公司统一社会信息代码'
            ];
            foreach ($add_field as $key) {
                $val = isset($post[$key]) ? trim($post[$key]) : '';
                if(!$val){
                    return $this->responseJson(Message::ERROR, $err_msg[$key]);
                }
                if($key == 'idcard_code' && !is_idcard($val)){
                    return $this->responseJson(Message::ERROR, $err_msg[$key]);
                }
                if (in_array($key, ['province_id', 'city_id', 'area_id'])) {
                    $val = (int)$val;
                    if ($val == 0 && $key != 'area_id') {
                        return $this->responseJson(Message::ERROR, '请选择所在地区');
                    }
                }
                if(in_array($key,['idcard_positive','idcard_obverse','idcard_handheld','company_buslicense'])){
                    if(!isImgName_wx($val)){
                        return $this->responseJson(Message::ERROR, $err_msg[$key]);
                    }
                }
                $add_data[$key] = $val;
            }
            $apply->apply_name = $add_data['apply_name'];
            $apply->login_pwd = $add_data['login_pwd'];
            $apply->idcard_code = $add_data['idcard_code'];
            $apply->idcard_positive = $add_data['idcard_positive'];
            $apply->idcard_obverse = $add_data['idcard_obverse'];
            $apply->idcard_handheld = $add_data['idcard_handheld'];
            $apply->shop_name = $add_data['shop_name'];
            $apply->shop_introduction = $add_data['shop_introduction'];
            $apply->shop_images = $add_data['shop_images'];
            $apply->company_buslicense = $add_data['company_buslicense'];
            $apply->company_usic = $add_data['company_usic'];
            $apply->area_info = $add_data['area_info'];
            $apply->province_id = $add_data['province_id'];
            $apply->city_id = $add_data['city_id'];
            $apply->area_id = $add_data['area_id'];
        }else{
            $apply_name = trim($post['apply_name']);
            $company_name = trim($post['company_name']);
            $area_info = trim($post['area_info']);
            if (!$apply_name) {
                return $this->responseJson(Message::VALID_FAIL, '请填写姓名');
            }
            if (!$company_name) {
                return $this->responseJson(Message::VALID_FAIL, '请填写公司名称');
            }
            if (!$area_info) {
                return $this->responseJson(Message::VALID_FAIL, '请选择代理城市');
            }

            $apply->apply_name = trim($post['apply_name']);
            $apply->company_name = trim($post['company_name']);
            $apply->province_id = (int)$post['province_id'];
            $apply->city_id = (int)$post['city_id'];
            $apply->area_id = (int)$post['area_id'];
            $apply->area_info = $post['area_info'];
        }
        $result = $apply->insert();
        if (!$result) {
            return $this->responseJson(Message::ERROR, '提交申请失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['apply_info' => $apply->toArray()]);
    }

    /**
     * @return mixed
     * 注册成代理商会员
     */
    public function actionRegister()
    {
        $agent_id = (int)\Yii::$app->request->post('from_agent_id');
        if ($agent_id < 1) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        //查询代理商信息
        $agent = Agent::findOne($agent_id);
        if (!$agent) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        //查询有没有在这个代理商注册过
        $map = [];
        $map['parent_id'] = $agent_id;
        if ($this->isLogin()) {
            $map['member_id'] = $this->member_id;
        } else {
            $map['ssid'] = $this->sessionid;
        }
        $agent_member = AgentMember::findOne($map);
        if ($agent_member) {
            return $this->actionParent($agent_id);
        }
        //注册为会员
        $agent_member = new AgentMember();
        $agent_member->ssid = $this->sessionid;
        //如果会员未登录，需在登录后关联member_id,Login->AfterLogin
        $agent_member->member_id = $this->member_id;
        $agent_member->buy_num = 0;
        $agent_member->buy_amount = 0;
        $agent_member->parent_id = $agent_id;
        $agent_member->add_time = TIMESTAMP;
        $agent_member->dateline = TIMESTAMP;
        $result = $agent_member->insert(false);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->actionParent($agent_id);
    }

    /**
     * 获取上级代理商信息
     * @param int $agent_id
     */
    public function actionParent($agent_id = 0)
    {
        $agent_id = $agent_id ? $agent_id : \Yii::$app->request->post('parent_agent_id');
        $agent = Agent::findOne($agent_id);
        if (!$agent) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        //
        $data = [];
        $data['parent_agent'] = [];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

}
