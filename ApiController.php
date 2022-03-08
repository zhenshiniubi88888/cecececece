<?php

namespace frontend\controllers;

use Api\O2O;
use common\components\HuawaApi;
use common\components\Log;
use common\components\Message;
use common\components\WeixinHuadi;
use common\components\WeixinSubscribeMsg;
use common\models\Crontab;
use common\models\HuawaSend;
use common\models\Member;
use common\models\MemberExppointsLog;
use common\models\OrderGoods;
use common\models\Orders;
use common\models\RefundReturn;
use yii\web\Controller;
use yii\web\HttpException;

/**
 * ApiController
 */
class ApiController extends Controller
{

    public function init()
    {
        $this->enableCsrfValidation = false;
        parent::init();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    public function actionTest()
    {
//        $result = O2O::getInstance()->orderRefund(10301);
//        var_dump(Message::getFirstMessage());
//        var_dump($result);die;
    }

    /**
     * 接收花娃数据回调
     * @throws HttpException
     */
    public function actionHuawaNotify()
    {
        $get = \Yii::$app->request->get();

        //获取返回数据
        $data = HuawaApi::decryptData($get['data'], $get['key']);
        if (is_null($data)) {
            throw new HttpException(401);
        }
        Log::writelog('HuawaNotify', $get['order_state']);
        Log::writelog('HuawaNotify', $data);
        //订单状态
        $result = HuawaSend::instance()->apiCallback($get['order_state'], $data);
        exit($result ? '1' : '0');
    }

    /**
     * 提醒用户付款
     * @return bool
     * @throws HttpException
     */
    public function actionNoticeNoPaySendSms()
    {
        $get = \Yii::$app->request->get();
        //获取返回数据
        $data = HuawaApi::decryptData($get['data'], $get['key']);
        if (is_null($data)) {
            return false;
        }

        $order_id = isset($data['api_orderid']) ? $data['api_orderid'] : '';
        if (!$order_id || !is_numeric($order_id) || $order_id <= 0) {
            return false;
        }

        // 查询订单
        $order = Orders::find()->select('buyer_id')->where(['order_id' => $order_id])->asArray()->one();
        if (!$order_id) {
            return false;
        }

        // 订单商品
        $goods = OrderGoods::find()->select('goods_name')->where(['order_id' => $order_id])->asArray()->one();
        if (!$goods) {
            return false;
        }

        $member = Member::find()->select('member_mobile')->where(['member_id' => $order['buyer_id']])->asArray()->one();
        if (!$member || !$member['member_mobile']) {
            return false;
        }

        $msg = '嗨，您要购买的“'. $goods['goods_name'] .'”还没有付款额，未付款订单即将被取消，快去花递小程序中付款吧！';
        $res = sendSms($member['member_mobile'], $msg, 1, '花递');
        return $res;
    }

    /**
     * 花娃调取-修改花递花店订单修改未付款订单价格
     * @return bool
     */
    public function actionEditHuadiNoPayOrderPrice()
    {
        $get = \Yii::$app->request->get();
        //获取返回数据
        $data = HuawaApi::decryptData($get['data'], $get['key']);
        if (is_null($data)) {
            return false;
        }
        $order_id = isset($data['api_orderid']) ? $data['api_orderid'] : '';
        if (!$order_id || !is_numeric($order_id) || $order_id <= 0) {
            return false;
        }
        $price = isset($data['price']) ? $data['price'] : '';
        if (!$price || !is_numeric($price) || $price <= 0) {
            return false;
        }
        $order = Orders::find()->where(['order_id' => $order_id])->one();
        $price = sprintf("%.2f", $price);
        $order->total_amount = $price;
        $order->order_amount = $price;
        $res = $order->save();
        if (!$res) {
            return false;
        }
        return true;
    }

    /**
     * 发送微信订阅消息
     */
    public function actionSendWxMsg()
    {
        $order_id = \Yii::$app->request->post('order_id',0);
        $type = \Yii::$app->request->post('type','');
        if($order_id == 0 || $type == ''){
            echo json_encode(['code'=>Message::EMPTY_CODE,'msg'=>Message::EMPTY_MSG]);die();
        }
        $order_info = Orders::find()->alias("order")
            ->join("join","hua123_order_goods goods","`order`.order_id = `goods`.order_id")
            ->where(['order.order_id'=>$order_id])
            ->select("order.buyer_id, order.add_time, order.order_sn, order.delivery_store_name, order.total_amount, goods.goods_name")
            ->asArray()
            ->one();
//            ->createCommand()->getRawSql();
        $member_info =[];
        if(isset($order_info['buyer_id'])){
            $member_info = Member::find()->where(['member_id'=>$order_info['buyer_id']])->select("member_wxopenid")->asArray()->one();
        }
        if(!empty($order_info) && !empty($member_info)){
            $template_id = '';
            $data = [];
            switch ($type){
                case 'order_refund'://退款发送的消息
                    $data = [
                        'number1' => ['value' => $order_info['order_sn']],
                        'thing2' => ['value' => $order_info['goods_name']],
                        'amount3' => ['value' => $order_info['total_amount']]
                    ];
                    $template_id = WeixinSubscribeMsg::ORDER_REFUND;
                    break;
                case 'order_send'://订单配送发送订阅消息
                    $data = [
                        'thing1' => ['value' => $order_info['goods_name']],
                        'thing3' => ['value' => $order_info['delivery_store_name']],//配送门店
                        'phrase6' => ['value' => '配送中'],
                        'thing4' => ['value' => '您的订单已开始配送，邀请新朋友，你们同时获得订花优惠券，有情有义有花递>>']
                    ];
                    $template_id = WeixinSubscribeMsg::ORDER_SEND;
            }
            if($template_id != "" && !empty($data)){
//                $result = WeixinSubscribeMsg::sendMsg($data,$member_info['member_wxopenid'],WeixinSubscribeMsg::ORDER_SEND);
                $result = WeixinSubscribeMsg::sendMsg($data,$member_info['member_wxopenid'],$template_id);
                Log::writelog("hua123_send_wx_notify",$result);
                echo json_encode(['code'=>Message::SUCCESS,'msg'=>Message::SUCCESS_MSG,'data'=>$result]);die();
            }
        }
        echo json_encode(['code'=>Message::EMPTY_CODE,'msg'=>Message::EMPTY_MSG]);
    }

    /**
     * 用于花123订单配送时发送公众号客服消息
     */
    public function actionSendWxgzMsg()
    {
        $member_id = \Yii::$app->request->post('member_id',0);
        $msg = \Yii::$app->request->post('msg','');
        $member_info = Member::find()
            ->alias("member")
            ->join("join","hua123_member_common common","member.member_id = common.member_id")
            ->where(['member.member_id' => $member_id])->select("member.member_mobile,common.huadi_open_openid")->asArray()->one();
        if($msg == '' || empty($member_info['huadi_open_openid'])){
            echo json_encode(['code'=>Message::ERROR,'msg'=>'无效的用户']);die();
        }
        $this->sendGzMsg($member_info['huadi_open_openid'],$msg);
    }

    /**
     * 用于花娃商家通知没有及时回复消息的花递用户
     */
    public function actionSendSmsNotify()
    {
        $member_id = \Yii::$app->request->post('member_id',0);
        $member_info = Member::find()
            ->alias("member")
            ->join("join","hua123_member_common common","member.member_id = common.member_id")
            ->where(['member.member_id' => $member_id])->select("member.member_mobile,common.huadi_open_openid")->asArray()->one();
        if(!isset($member_info['member_mobile']) || !isMobile($member_info['member_mobile']) || empty($member_info['huadi_open_openid'])){
            echo json_encode(['code'=>Message::ERROR,'msg'=>'无效的用户']);die();
        }

        //短信发送
//        $result = sendSms($member_info['member_mobile'],'花店回复你的消息啦',1,'花递');
//        if($result){
//            echo json_encode(['code'=>Message::SUCCESS,'msg'=>Message::SUCCESS_MSG]);die();
//        }
//        echo json_encode(['code'=>Message::ERROR,'msg'=>'发送失败']);die();

        //客服消息发送
        $msg = '花店回复你的消息啦，<a data-miniprogram-appid="wxc0516d6abf2093a6" data-miniprogram-path="/pages/message" href="http://www.qq.com">快去查看！</a>';
        $this->sendGzMsg($member_info['huadi_open_openid'],$msg);
    }

    /**
     * 发送公众号消息
     * @param $openid string 用户在公众号里面的openid
     * @param $msg string 需要发送的消息内容
     */
    private function sendGzMsg($openid, $msg){
        $access_token = WeixinHuadi::getInstance()->get_access_token();
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$access_token}";
        $data = [
            'touser' => $openid,
            'msgtype' => 'text',
            'text' => [
                'content' => $msg
            ]
        ];
        $result = _post(json_encode($data,JSON_UNESCAPED_UNICODE),$url);
        $result = json_decode($result,true);
        if(isset($result['errmsg']) && $result['errmsg'] == 'ok'){
            echo json_encode(['code'=>Message::SUCCESS,'msg'=>Message::SUCCESS_MSG]);die();
        }
        echo json_encode(['code'=>Message::ERROR,'msg'=>'发送失败']);die();
    }

    /**
     * 花娃订单配送时间过期通知消息
     */
    public function actionHuawaOrderInvalidNotify()
    {
        $order_id = \Yii::$app->request->post('order_id',0);
        Log::writelog("hua123_send_order_invalid_notify",$order_id);
        $this->echoJson(1, '发送成功');
        exit;
        $info = OrderGoods::find()
            ->alias("goods")
            ->join("join","hua123_member member","goods.buyer_id = member.member_id")
            ->where(['goods.order_id'=>$order_id])->select("goods.goods_name,member.member_mobile")->asArray()->one();
        if(!empty($info['member_mobile']) && !empty($info['goods_name'])){
            $message = "您订购的“{$info['goods_name']}”已超过配送时间，暂未匹配到合适的商家进行配送，我们正在全力配送中，关注微xin公众号“花递”即可实时查看订单流程，退订回T";
            $result = sendSms($info['member_mobile'],$message,1,'【花递】');
            if($result){
                $this->echoJson(1, '发送成功');
            }
            $this->echoJson(0, '发送失败');
        }
        $this->echoJson(0,'参数错误');
    }

    /**
     * 花递 订单配送成功发送模板消息
     */
    public function actionSendWechatTemplateConfirmReceiveMsg()
    {
        $get = \Yii::$app->request->get();
        //获取返回数据
        $data = HuawaApi::decryptData($get['data'], $get['key']);
        if (is_null($data)) {
            $this->echoJson(0, '参数错误');
        }

        $member_id = isset($data['member_id']) ? $data['member_id'] : '';
        $order_id = isset($data['order_id']) ? $data['order_id'] : '';

        if ($member_id <= 0 || $order_id <= 0) {
            $this->echoJson(0, '参数错误');
        }

        $res = huadi_send_weixin_order_confrim_receive($member_id, $order_id);
        $status = $res ? 1 : 0;
        $msg = $res ? '发送成功' : '发送失败';
        $this->echoJson($status, $msg);
    }

    /**
     * 订单退款，统一减少浪漫值
     */
    public function actionReduceExppoints()
    {
        $order_id = \Yii::$app->request->post('order_id',0);
        $order_refund = RefundReturn::find()->where(['order_id'=>$order_id])->select("buyer_id,refund_amount")->asArray()->one();
        if(!empty($order_refund['buyer_id']) && !empty($order_refund['refund_amount'])){
            $result = MemberExppointsLog::addExppoints($order_refund['buyer_id'],intval($order_refund['refund_amount']),MemberExppointsLog::OPERATE_FINISH_ORDER,0,'订单退款',0);
            $result['order_id'] = $order_id;
            Log::writelog("reduce_exppoints",$result);
        }

    }

    public function echoJson($status, $msg = '', $data = [])
    {
        $res = [
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ];
        echo json_encode($res);die;
    }
    public function actionTestTwo(){
        $access_token = WeixinHuadi::getInstance()->get_access_token();
        Crontab::instance()->insertGzOpenid($access_token);
    }
}
