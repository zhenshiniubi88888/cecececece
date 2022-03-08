<?php

namespace frontend\controllers;

use common\components\Message;
use common\components\MicroApi;
use common\models\Address;
use common\models\HuadiYearCardOrders;
use common\models\MemberVerify;
use common\models\Orders;
use common\models\Voucher;
use Yii;
use yii\web\HttpException;

/**
 * OrderController
 */
class OrderController extends BaseController
{
    public function init()
    {
        parent::init();
        $this->validLogin();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 提交订单
     */
    public function actionUnified()
    {
        $param = \Yii::$app->request->post();
        $order_param = $param['order_json'];
        $is_new_json = isset($param['is_new_json']) ?  $param['is_new_json'] : 0;
        if($is_new_json){
            $order_param = json_decode($order_param, true);
        }
        if (!$order_param) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }

        //收货地址
        $address_model = new Address();
        $address_id = isset($order_param['delivery_receiver'])&&isset($order_param['delivery_receiver']['address_id'])?$order_param['delivery_receiver']['address_id']:0;
        $address_info = $address_model->getDefaultAddressByUid($this->member_id, $address_id);
        if (empty($address_info)) {
            return $this->responseJson(Message::ERROR, '请填写收货地址');
        }
        $order_param['delivery_receiver'] = $address_info;

        //送自己，收花人就是订花人
        if ($order_param['delivery_buyer']['is_self'] == 1) {
            $order_param['delivery_buyer']['buyer_name'] = $order_param['delivery_receiver']['consignee_name'];
            $order_param['delivery_buyer']['buyer_mobile'] = $order_param['delivery_receiver']['consignee_mobile'];
        } else {
            //订花人验证
            if(isset($order_param['delivery_buyer']['buyer_name']) && !empty($order_param['delivery_buyer']['buyer_name'])){
                if (!isUsername($order_param['delivery_buyer']['buyer_name'])) {
                    return $this->responseJson(Message::ERROR, '订花人姓名1-16个字符，不能含有特殊符号');
                }
            }
            if (!isMobile($order_param['delivery_buyer']['buyer_mobile'])) {
                return $this->responseJson(Message::ERROR, '订花人手机号格式不正确');
            }
        }
        //未绑定手机号直接赋值订花人信息
        if ($this->member_info['member_mobile_bind'] != 1) {
            $this->member_info['member_mobile_bind'] = 1;
            $this->member_info['member_mobile'] = $order_param['delivery_buyer']['buyer_mobile'];
        }
        //过滤特殊字符
        $order_param['wish_cards'] = isset($order_param['wish_cards'])?replaceSpecialChar($order_param['wish_cards']):'';
        $order_param['order_message'] = isset($order_param['order_message'])?replaceSpecialChar($order_param['order_message']):'';
        //贺卡留言验证
        if (isset($order_param['wish_cards']) && mb_strlen($order_param['wish_cards'], 'UTF-8') > 200) {
            return $this->responseJson(Message::ERROR, '贺卡留言最多200字');
        }
//
//        if($order_param['wish_cards'] && !is_utf8($order_param['wish_cards'])){
//            return $this->responseJson(Message::ERROR, '贺卡留言暂不支持填写特殊字符');
//        }

        //备注验证
        if (isset($order_param['order_message']) && mb_strlen($order_param['order_message'], 'UTF-8') > 300) {
            return $this->responseJson(Message::ERROR, '备注最多300字');
        }
//        if($order_param['order_message'] && !is_utf8($order_param['order_message'])){
//            return $this->responseJson(Message::ERROR, '备注暂不支持填写特殊字符');
//        }

        //配送时间与地址验证
        $error = $this->_validDelivery($order_param);
        if ($error) {
            return $this->responseJson(Message::ERROR, $error);
        }
        //统一下单
        $model_order = new Orders();
        $result = $model_order->UnifiedOrder($order_param, $this->member_info);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_order->getFirstError(Message::MODEL_ERROR));
        }
        //返回支付信息
        $data = [];
        $data['pay_sn'] = $result['pay_sn'];
        $data['total_amount'] = $result['total_amount'];
        $data['order_list'] = $result['order_list'];

        // 保存form_id
        if (isset($order_param['formId']) && $order_param['formId']) {
            if (isset($result['order_list'][0]) && $result['order_list'][0]) {
                $key_cache = getWxFormIdCachekey($this->member_id, $result['order_list'][0]);
                cache($key_cache, $order_param['formId'], 3600 * 24 * 5);
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 提交年卡订单
     */
    public function actionYearCardUnified()
    {
        $param = \Yii::$app->request->post();
        $order_param = $param['order_json'];
        if (!$order_param) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        //年卡下单下单
        $model_huadi_year_card_order = new HuadiYearCardOrders();
        $result = $model_huadi_year_card_order->YearCardUnifiedOrder($order_param['payment_code'],$this->member_id);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_huadi_year_card_order->getFirstError(Message::MODEL_ERROR));
        }

        // 保存form_id
        if (isset($order_param['formId']) && $order_param['formId']) {
            if (isset($result['order_list'][0]) && $result['order_list'][0]) {
                $key_cache = getWxFormIdCachekey($this->member_id, $result['order_list'][0]);
                cache($key_cache, $order_param['formId'], 3600 * 24 * 5);
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $result);
    }
    /**
     * 配送地区与时间验证
     * @param $order_param
     * @return string
     */
    private function _validDelivery($order_param)
    {
        //配送时间验证
        foreach ($order_param['cart_map'] as $cart) {
            $delivery_time = $cart['delivery_time'];
            if ($delivery_time['is_select']) {
                if (!$delivery_time['has_select']) {
                    return '请选择配送时间';
                }
                if (!$delivery_time['date']) {
                    return '请选择配送日期';
                }
            }
        }

        //配送区域验证
        $delivery_receiver = $order_param['delivery_receiver'];
        $api = new MicroApi();
        //详细地址
        $area_info = explode(" ", $delivery_receiver['area_info']);
        //所在城市
        $city = isset($area_info[1]) ? $area_info[1] : '';
        $address = preg_replace('/\s+/', '', $delivery_receiver['area_info'] . $delivery_receiver['address']);
        $address = str_replace(array('（','）'),array('(',')'),$address);
        $address = preg_replace('/\(.*?\)/s','',$address);
        $data = $api->httpRequest('/api/isLocationStore', ['address' => $address, 'city' => $city]);
        if ($data && $data['count'] == 0) {
            return '您所选择的地址暂不支持配送，请确认收货地址是否正确';
        }

    }

    /**
     * 花递-每5秒钟获取订单是否接单的状态
     * @return mixed
     */
    public function actionGetOrderState()
    {
        $order_ids = \Yii::$app->request->post('order_ids', '');
        if (!$order_ids || empty($order_ids)) {
            return $this->responseJson(Message::ERROR, '参数错误');
        }
        $ids = [];
        $data = [];
        foreach ($order_ids as $k => $v) {
            if (!$v || !is_numeric($v) || $v <= 0) {
                continue;
            }
            $ids[] = $v;
            $data[$v] = $v;
        }
        $orders = Orders::find()->select('order_id, huawa_state')->where(['in', 'order_id', $ids])->asArray()->all();

        foreach ($orders as $v) {
            if ($v['huawa_state'] <= HUAWA_ORDER_MAKING) {
                $data[$v['order_id']] = false;
            } else {
                $data[$v['order_id']] = true;
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 校验短信验证码
     */
    public function actionVerifyPhoneCode(){
        $param = Yii::$app->request->post();
        $phone = isset($param['phone']) ? $param['phone'] : '';
        if(!$phone){
            return $this->responseJson(Message::ERROR, Message::EMPTY_MSG);
        }
        $verify_code = isset($param['code']) ? $param['code'] : '';
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_BIND, $phone, $verify_code);
        if (!$result) {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }


    /**
     * 订单查询功能接口
     */
    public function actionOrderTracking(){
        $memberInfo = $this->member_info;
        $param = Yii::$app->request->post();
        $page = isset($param['page']) ? (int)$param['page'] : 1;
//        $phone = isset($param['phone']) ? $param['phone'] : '';
        $phone = $memberInfo['member_mobile'];
        if(!$phone){
            return $this->responseJson(Message::ERROR, Message::EMPTY_MSG);
        }
        $map = [];
        $map['orders.delete_state'] = 0;
        if (isset($param['order_sn']) && strlen($param['order_sn'])) {
            $orderSn = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","", $param['order_sn']);
            $map['orders.order_sn'] = $orderSn;
        } else {
            $map['orders.buyer_phone'] = $phone;
        }
        $order = new Orders();
        $order_data = $order->getOrderTracking($map, $page);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $order_data);
    }

    /**
     * 订单支付状态查询（用于wap端微信支付时的支付状态检查）
     */
    public function actionOrderPayState()
    {
        $pay_sn = Yii::$app->request->post("pay_sn",0);
        $order_sn = Yii::$app->request->post("order_sn",0);
        $is_year_order = Yii::$app->request->post('is_year_order', 0);
        if(!$pay_sn && !$order_sn){
            return $this->responseJson(Message::ERROR, '参数错误');
        }
        $member_column = $is_year_order ? 'member_id' : 'buyer_id';
        if($pay_sn) {
            // pay_sn 查询需要去除前缀hd_
            if(strpos($pay_sn, 'hd_') === 0) {
                $pay_sn = substr($pay_sn, 3);
            }
            $where = [
                $member_column => $this->member_id,
                'pay_sn' => $pay_sn,
                'payment_state' => 1
            ];
        }else{
            $where = [
                $member_column => $this->member_id,
                'order_sn' => $order_sn,
                'payment_state' => 1
            ];
        }
        $pay_state = 0;
        if($is_year_order) {
            $order_info = HuadiYearCardOrders::find()->where($where)->select('payment_state')->asArray()->one();
            if(isset($order_info['payment_state'])){
                $pay_state = $order_info['payment_state'];
            }
        }else{
            $order_info = Orders::find()->where($where)->select("order_state")->asArray()->one();
            if(isset($order_info['order_state']) && $order_info['order_state'] >= ORDER_STATE_PAY){
                $pay_state = 1;
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,['pay_state'=>$pay_state]);
    }
}
