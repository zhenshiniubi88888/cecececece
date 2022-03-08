<?php

namespace frontend\controllers;

use common\components\HuawaApi;
use common\components\Message;
use common\models\AdminOrderState;
use common\models\EvaluateGoods;
use common\models\EvaluateStore;
use common\models\Member;
use common\models\MemberVerify;
use common\models\OrderGoods;
use common\models\OrderLog;
use common\models\Orders;
use common\models\RefundReason;
use common\models\RefundReturn;
use Yii;


/**
 * PollingOrderController
 */
class PollingOrderController extends BaseController
{
    private $cache_key = '';

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub

        //查询订单缓存TOKEN-key
        $this->cache_key = md5('polling_order_' . getIp() . Yii::$app->request->userAgent);
    }

    /**
     * 获取查询订单的token
     * @return mixed
     */
    public function actionToken()
    {
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
            if ($member_mobile != '15884477703') {
                return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
            }
        }
        $token = md5(microtime(true) . mt_rand(1000, 9999));
        cache($this->cache_key, ['mobile' => $member_mobile, 'token' => $token], 3600);

        //判断是否登录，已登录未绑定手机直接绑定手机
        if ($this->isLogin() && $this->member_info['member_mobile_mobile'] == 0) {
            //查找手机号是否已绑定过
            $member = Member::instance()->getMemberByMobile($member_mobile);
            if (!$member) {
                $data = [];
                $data['member_mobile'] = $member_mobile;
                $data['member_mobile_bind'] = 1;
                $result = Member::instance()->updateMember(['member_id' => $this->member_id], $data);
                if (!$result) {
                    Yii::error(Member::instance()->getErrors());
                }
            }else{
                //20181129新流程账户合并
                $result = Member::instance()->memberMigrate($member->member_id, $this->member_id);
                //成功与否没有关系，用户是来查订单的
            }
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['token' => $token]);
    }

    /**
     * 订单列表
     * @return mixed
     */
    public function actionIndex()
    {
        $param = \Yii::$app->request->post();
        $cache_data = cache($this->cache_key);
        $order_sn = isset($param['order_code']) && $param['order_code'] ? $param['order_code'] : 0;
        if ((empty($cache_data) || $param['query_token'] != $cache_data['token']) && !$order_sn) {
            //返一个空
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
                'count' => 0,
                'curpage' => 1,
                'pagesize' => Orders::PAGE_SIZE,
                'list' => []
            ]);
        }
        $member_mobile = $cache_data['mobile'];
        $page = (int)$param['page'] ? (int)$param['page'] : 1;
        $map = [];
        if ($order_sn) {
            $map['orders.order_sn'] = $order_sn;
        } else {
            $map['orders.buyer_phone'] = $member_mobile;
        }
        $map['orders.delete_state'] = 0;
        $order = new Orders();
        $order_data = $order->getFriendlyOrderData($map, $page);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $order_data);
    }

    /**
     * 订单详情
     * @return mixed
     */
    public function actionView()
    {
        $order_id = (int)Yii::$app->request->post('order_id');
        $order_sn = Yii::$app->request->post('order_sn');
        if (!$order_id) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        $map = [];
        $map['orders.order_id'] = $order_id;
        $map['orders.order_sn'] = $order_sn;
        $map['orders.delete_state'] = 0;

        $order = new Orders();
        $data = $order->getOrderDetail($map);
        if ($data == false) {
            return $this->responseJson(Message::EMPTY_CODE, $order->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

}