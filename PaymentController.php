<?php

namespace frontend\controllers;

use common\components\Log;
use common\components\Message;
use common\models\Agent;
use common\models\AgentOrder;
use common\models\AgentRecharge;
use common\models\GoodsClass;
use common\models\GroupShoppingTeam;
use common\models\HuadiYearCardOrders;
use common\models\Member;
use common\models\OrderGoods;
use common\models\OrderPay;
use common\models\Orders;
use common\models\PBundling;
use common\models\PBundlingGoods;
use common\models\PdRecharge;
use common\models\PXianshiGoods;
use Payment\Adapter\TtPay\HdAppletPay;
use Payment\Payment;
use Payment\wxpay\WxPayApi;
use Payment\wxpay\WxPayNotifyReply;
use Payment\wxpay\WxPayDataBase;
use yii\web\HttpException;


/**
 * PaymentController
 */
class PaymentController extends BaseController
{
    /**
     * 合并支付号
     * @var string 32
     */
    private $pay_sn = '';
    /**
     * 订单号
     * @var string 32
     */
    private $order_sn = '';
    /**
     * 支付类型 (real|virtual|recharge)
     * @var string
     */
    private $payment_type = '';
    /**
     * 支付方式 (alipay|wxpay)
     * wxpay = h5 ? zxh5pay : zxpay
     * @var string
     */
    private $payment_code = '';
    /**
     * 支付金额
     * @var string
     */
    private $payment_amount = '';
    /**
     *微信OPENID
     * @var string
     */
    private $wx_openid = '';
    /**
     * 支付前台回调URL
     * @var string
     */
    private $return_url = '';

    /**
     * 初始化参数
     */
    public function init()
    {
        parent::init();
        //批量支付单号
        if (Request('pay_sn')) {
            $this->pay_sn = Request('pay_sn');
        }
        //单个支付单号
        if (Request('order_sn')) {
            $this->order_sn = Request('order_sn');
        }
        //支付方式
        $this->payment_code = Request('payment_code');
        //支付金额
        $this->payment_amount = (float)Request('payment_amount');

        //兼容
        if(isWeixin() && \Yii::$app->request->isPost && !in_array(Request('payment_code'), [Payment::PAY_CODE_WX_SMALL_PROGRAM,Payment::PAY_CODE_WX_APPLET,Payment::PAY_CODE_WX_APPLET_AHJ])){
            $this->payment_code = Payment::PAY_CODE_WX_NATIVE;
        }
        //微信支付
        if ($this->payment_code == Payment::PAY_CODE_WX_NATIVE ) {
            if (isWeixin()) {
//                $this->payment_code = Payment::PAY_CODE_WX_ZX_JSAPI;
                $this->payment_code = Payment::PAY_CODE_WX_JSAPI;
            } elseif (isApp()) {
                //原生APP支付
                $this->payment_code = Payment::PAY_CODE_WX_APP;
                //中信APP支付
//                $this->payment_code = Payment::PAY_CODE_WX_ZX_APP;
            } else {
                //微信原生H5
                $this->payment_code = Payment::PAY_CODE_WX_H5;
                //中信H5
                //$this->payment_code = Payment::PAY_CODE_WX_ZX_H53;
            }
            //微信支付WX_OPENID
            if($this->member_id == 1567403){
                Log::writelog("payment_debug", 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $_SERVER['QUERY_STRING'] . " ------   " . json_encode($_POST));
            }
            if (in_array($this->payment_code, [Payment::PAY_CODE_WX_ZX_JSAPI,Payment::PAY_CODE_WX_JSAPI])) {
                $return_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                //微信支付需提权获取authcode,先跳出去再回来
                if (Request('_continue_pay') != 1) {
                    $query = [];
                    $query['ssid'] = $this->sessionid;
                    $query['token'] = $this->token;
                    if ($this->pay_sn) {
                        $query['pay_sn'] = $this->pay_sn;
                    }
                    if ($this->order_sn) {
                        $query['order_sn'] = $this->order_sn;
                    }
                    $query['payment_code'] = 'wxpay';
                    $query['payment_amount'] = $this->payment_amount;
                    $query['ahj_domain'] = FROM_DOMAIN;
                    $query['_continue_pay'] = 1;
                    $return_url .= '?' . http_build_query($query);
                    $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
                        'payment_code' => $this->payment_code,
                        'payment_data' => [
                            'url' => $return_url
                        ],
                    ]);
                    \Yii::$app->response->send();
                    die;
                }
                //创建支付对象设置APPID
                $payment = Payment::create($this->payment_code);
                $payment->setConfig();
                $js_api_pay = new \Payment\wxpay\JsApiPay();
                $js_api_pay->return_url = urlencode($return_url);
                $wx_openId = $js_api_pay->GetOpenid();
                if (!$wx_openId) {
                    //没提取到openid
                }
                $this->wx_openid = $wx_openId;
            }
        } elseif ($this->payment_code == Payment::PAY_CODE_ALI_WAP) {
            if (isApp()) {
                $this->payment_code = Payment::PAY_CODE_ALI_APP;
            }
        } elseif (in_array($this->payment_code, [Payment::PAY_CODE_WX_SMALL_PROGRAM,Payment::PAY_CODE_WX_APPLET,Payment::PAY_CODE_WX_APPLET_AHJ])) {
            //微信支付WX_OPENID
                $return_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                //微信支付需提权获取authcode,先跳出去再回来
                if (Request('_continue_pay') != 1) {
                    $query = [];
                    $query['ssid'] = $this->sessionid;
                    $query['token'] = $this->token;
                    if ($this->pay_sn) {
                        $query['pay_sn'] = $this->pay_sn;
                    }
                    if ($this->order_sn) {
                        $query['order_sn'] = $this->order_sn;
                    }
                    $query['payment_code'] = 'wxpay';
                    $query['payment_amount'] = $this->payment_amount;
                    $query['_continue_pay'] = 1;
                    $return_url .= '?' . http_build_query($query);
                    $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
                        'payment_code' => $this->payment_code,
                        'payment_data' => [
                            'url' => $return_url
                        ],
                    ]);
                    \Yii::$app->response->send();
                    die;
                }
                //创建支付对象设置APPID
                Payment::create($this->payment_code);
                $js_api_pay = new \Payment\wxpay\JsApiPay();
                $js_api_pay->return_url = urlencode($return_url);
                $wx_openId = $js_api_pay->GetOpenid_applet($this->payment_code);
                if (!$wx_openId) {
                    //没提取到openid
                    return $this->responseJson(0, "获取openid失败，请确认是否传入正确的code");
                }
                $this->wx_openid = $wx_openId;
        }
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 实物订单支付
     */
    public function actionRealOrder()
    {
        $this->payment_type = 'real';
        log::writelog('hdPay', 'actionRealOrder');
        return $this->_doApiPay();
    }

    /**
     * 年卡订单支付
     */
    public function actionYearCardOrder(){
        $this->payment_type = 'year_card';
        return $this->_doApiPay(false,0);
    }

    /**
     * 虚拟订单支付
     */
    public function actionVirtualOrder()
    {
        $this->payment_type = 'virtual';
        return $this->_doApiPay();
    }

    /**
     * 充值订单支付
     */
    public function actionRechargeOrder()
    {
        $this->payment_type = 'recharge';
        return $this->_doApiPay();
    }

    /**
     * 字节跳动获取支付状态
     */
    public function actionGetOrderStatus()
    {
        $pay_type = \Yii::$app->request->post('pay_type');
        $pay_sn = \Yii::$app->request->post('pay_sn');
        $result = array();
        switch ($pay_type) {
            case 'wx_pay':
                // todo appid mch_id 更换为花递的
                $HdAppletPay = new HdAppletPay();
                $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
                $mch_id = '1238965602';
                $data['out_trade_no'] = $pay_sn;
                $data['appid'] = 'wx21a546d18a238c98';
                $data['mch_id'] = $mch_id;//微信支付分配的商户号
                $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                $str = "";
                for ($i = 0; $i < 32; $i++) {
                    $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
                }
                $data['nonce_str'] =$str;//随机字符串，不长于32位
                $data['sign'] = $HdAppletPay->getSign($data);//签名
                $xml = $HdAppletPay->arrayToXml($data);//将数组转xml
                $res = $HdAppletPay->postXmlCurl($xml, $url);//调用请求
                $res = $HdAppletPay->xmlToArray($res);//将xml转数组
                if ($res['return_code'] != 'SUCCESS' && $res['result_code'] != 'SUCCESS' && $res['trade_state'] != 'SUCCESS') {
                    return $this->responseJson(0, "调起微信支付状态查询失败");
                } else {
                    switch ($res['trade_state']) {
                        case 'SUCCESS' :
                            $code = 0;
                            break;
                        case 'REFUND' :
                            $code = 2;
                            break;
                        case 'NOTPAY' :
                            $code = 9;
                            break;
                        case 'CLOSED' :
                            $code = 3;
                            break;
                        case 'REVOKED' :
                            $code = 2;
                            break;
                        case 'USERPAYING' :
                            $code = 9;
                            break;
                        case 'PAYERROR' :
                            $code = 3;
                            break;
                        default;
                            return $this->responseJson(0, "调起微信支付状态查询失败");
                    }
                    $result['code'] = $code;
                    break;
                }
        }
        //AJAX返回
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $result);
    }
    /**
     * 分销支付
     */
    public function actionAgentOrder()
    {
        // $server_name = \Yii::$app->request->post("server_name","");
        //  Log::writelog("fenxiao",$server_name);
        $this->payment_type = 'real';
        // $this->return_url = "http://".$server_name . '/member/orderlist?group=confirm';

        if($this->order_sn){
            $order = Orders::find()->where(["order_sn" => $this->order_sn])->asArray()->one();
            if(!$order){
                return $this->responseJson(0, "订单不存在或已被删除");
            }
            $realorder = $order;
        }else{
            $order = OrderPay::find()->where(['pay_sn' => $this->pay_sn, 'is_delete' => 0])->asArray()->one();
            if(!$order || $order["api_pay_state"] == 1){
                return $this->responseJson(0,'订单已支付，请返回订单页查看');
            }
            $realorder = Orders::find()->where(["pay_sn" => $this->pay_sn])->asArray()->one();
            if(!$realorder){
                return $this->responseJson(0, "订单不存在或已被删除4");
            }
        }
        $agentOrder = AgentOrder::find()->where(["order_id" => $realorder["order_id"]])->asArray()->one();
        if(!$agentOrder){
            return $this->responseJson(0, "订单不存在或已被删除2");
        }

        $agent = Agent::find()->where(["agent_id" => $agentOrder["agent_id"]])->asArray()->one();
        if(!$agent){
            return $this->responseJson(0, "订单不存在或已被删除3");

        }

        $this->return_url = "http://".$agent["agent_name"].".".FX_DOMAIN_NAME."/member/orderlist?group=confirm";

        $this->member_id = $order["buyer_id"];
        $member = new Member();
        $this->member_info = $member->getMemberById($this->member_id);

        return $this->_doApiPay(false);
    }
    /**
     * 分销充值
     */
    public function actionAgentRecharge()
    {
        $this->payment_type = 'agent_recharge';
        $this->return_url = FENXIAO_DOMAIN . '/finance/index';
        return $this->_doApiPay(false);
    }

    /**
     * 分销充值
     */
    public function actionPlatformRecharge()
    {
        $this->payment_type = 'platform_recharge';
        $this->return_url = FENXIAO_DOMAIN . '/finance/index';
        return $this->_doApiPay(false);
    }
    public function actionWeixinPlatformrecharge()
    {
        $this->payment_type = 'weixin_platform_recharge';
        $this->return_url = FENXIAO_DOMAIN . '/finance/index';
        return $this->_doApiPay(false);
    }
    /**
     * 发起支付
     * @return mixed
     */
    private function _doApiPay($need_login = false,$no_year_card = 1)
    {
        log::writelog('hdPay', '_doApiPay');
        if ($need_login) {
            //需要登录
            $this->validLogin();
        }
        //获取支付信息
        $pay_data = $this->_beforeSubmit();
        log::writelog('hdPay', var_export($pay_data, true));
        $pay_data['data']['order']['wx_openId'] = $this->wx_openid;//有时wx_openId在传值时会莫名其妙的丢失  原因暂未知  所以这里重新赋值一下暂时
        if (!$pay_data['state']) {
            return $this->responseJson(Message::ERROR, $pay_data['msg']);
        }
        $pay_data['data']['order']['pay_attach'] = $this->payment_type;
        $pay_sn = explode('_',$pay_data['data']['order']['pay_sn']);
        //拼团支付检查
        if(SITEID == 258 && $no_year_card){
            $order_info = Orders::find()
                ->alias("order")
                ->join("join","hua123_order_goods goods","goods.order_id = order.order_id")
                ->where(['pay_sn'=>$pay_sn[1]])
                ->select("order.group_shopping_team_id, order.group_shopping_state, goods.goods_id, goods.goods_num")
                ->asArray()
                ->one();
            if($order_info['group_shopping_state'] == 1){
                $checkResult = (new GroupShoppingTeam())->checkGroup($order_info['group_shopping_team_id'],$order_info['goods_id'],$this->member_id,$order_info['goods_num']);
                if($checkResult['code'] != 1){
                    return $this->responseJson($checkResult['code'], $checkResult['msg']);
                }
            }
        }
        //19年感恩节限时购活动中购买资格检查
        if(SITEID == 258 && $no_year_card){
            $order_infos = Orders::find()
                ->alias("order")
                ->join("join","hua123_order_goods goods","goods.order_id = order.order_id")
                ->where(['pay_sn'=>$pay_sn[1]])
                ->select("goods.goods_id, goods.goods_num")
                ->asArray()
                ->all();
            foreach ($order_infos as $info){
                $checkResult = (new PXianshiGoods())->checkXianshi($this->member_id,$info['goods_id'],$info['goods_num']);
                if($checkResult['code'] != 1){
                    return $this->responseJson($checkResult['code'], $checkResult['msg']);
                }
            }
        }

        //创建支付对象;
        $payment = Payment::create($this->payment_code);
        if ($payment == false || !method_exists($payment, 'payment')) {
            return $this->responseJson(Message::ERROR, '0x6000');
        }
        //设置前台回调地址
        if ($this->return_url) {
            $payment->setReturnUrl($this->return_url);
        }
        //发起支付
        if(request('service')){
            $pay_data['data']['order']['service'] = request('service');
        }
        $data = $payment->payment($pay_data['data']['order'], $pay_data['data']['config']);
        $error = Message::getFirstMessage();
        if (in_array($this->payment_code,[Payment::PAY_CODE_WX_ZX_JSAPI, Payment::PAY_CODE_WX_JSAPI])) {
            if(!$data){
                exit($error['message']);
            }
            $data['return_url'] = $payment->return_url . '?ahj_domain='.$_GET['ahj_domain'] . '&pay_sn=' . $this->pay_sn;
            \common\components\Log::writelog('jsapi_pay_enter','======>member_id: '.$this->member_id);
//            var_dump($data['return_url']);exit;
            //中信微信支付需要进行二次跳转
            if($this->member_id == 1567403){//20200925,jsapi调起支付失败调试
                return $this->renderPartial('zxwxpay_debug', ['data' => $data]);
            }
            return $this->renderPartial('zxwxpay', ['data' => $data]);
        } else {
            if (!$data) {
                return $this->responseJson(Message::ERROR, isset($error['message']) ? $error['message'] : '初始化支付失败');
            }
            //AJAX返回
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
                'payment_code' => $this->payment_code,
                'payment_data' => $data,
            ]);
        }
    }

    /**
     * 获取支付信息
     * @return array
     */
    private function _beforeSubmit()
    {
        if ($this->payment_type == 'real') {
            return $this->_getRealOrderBefore();
        } elseif ($this->payment_type == 'agent_recharge') {
            return $this->_getAgentRechargeBefore();
        }elseif ($this->payment_type == 'platform_recharge') {
            return $this->_getPlatformRechargeBefore();
        }elseif ($this->payment_type == 'weixin_platform_recharge'){
            return $this->_getWeixinPlatformRechargeBefore();
        }elseif ($this->payment_type == 'year_card'){
            return $this->_getYearCardBefore();
        }
    }

    private function _getPlatformRechargeBefore()
    {
        //支付方式验证
        $payment_config = \common\models\Payment::getPaymentOnlineConfig($this->payment_code);
        if (empty($payment_config)) {
            //暂不支持此支付方式
            return arrayBack('初始化支付失败，请稍后再试');
        }
        //TODO 待完成代码
        if (!$this->pay_sn) {
            return arrayBack('未找到订单信息，请重试');
        }
        $where["pdr_sn"] = $this->pay_sn;
        $recharge = PdRecharge::find()->where($where)->one();
        if (!$recharge) {
            return arrayBack('未找到订单信息，请重试[2]');
        }

        if ($recharge->pdr_amount != $this->payment_amount) {
            return arrayBack('支付金额错误');
        }
        if ($recharge->pdr_payment_state) {
            return arrayBack('订单已充值成功');
        }

        $order = [];
        $order['pay_name'] = "平台充值";
        $order['pay_sn'] = $recharge->pdr_sn;
        $order['pay_amount'] = $recharge->pdr_amount;
        $order['pay_attach'] = $this->payment_type; //固定，不要改
        $order['wx_openId'] = $this->wx_openid; //固定，不要改
        $order['buyer_id'] = $this->member_info['alipay_userid'];
        return arrayBack('ok', true, [
            'order' => $order,
            'config' => $payment_config
        ]);
        //需返回以下字段
//        $order = [];
//        $order['pay_name'] = $pay->pay_name;
//        $order['pay_sn'] = $pay->pay_order_sn;
//        $order['pay_amount'] = $pay->total_amount;
//        $order['pay_attach'] = $this->payment_type; //固定，不要改
//        $order['wx_openId'] = $this->wx_openid; //固定，不要改
//        return arrayBack('ok', true, [
//            'order' => $order,
//            'config' => $payment_config
//        ]);
    }
    private function _getWeixinPlatformRechargeBefore()
    {
        //支付方式验证
        $payment_config = \common\models\Payment::getPaymentOnlineConfig($this->payment_code);
        if (empty($payment_config)) {
            //暂不支持此支付方式
            return arrayBack('初始化支付失败，请稍后再试');
        }
        //TODO 待完成代码
        if (!$this->pay_sn) {
            return arrayBack('未找到订单信息，请重试');
        }
        $where["pdr_sn"] = $this->pay_sn;
        $recharge = PdRecharge::find()->where($where)->one();
        if (!$recharge) {
            return arrayBack('未找到订单信息，请重试[2]');
        }

        if ($recharge->pdr_amount != $this->payment_amount) {
            return arrayBack('支付金额错误');
        }
        if ($recharge->pdr_payment_state) {
            return arrayBack('订单已充值成功');
        }

        $order = [];
        $order['pay_name'] = "平台充值";
        $order['pay_sn'] = $recharge->pdr_sn;
        $order['pay_amount'] = $recharge->pdr_amount;
        $order['pay_attach'] = $this->payment_type; //固定，不要改
        $order['wx_openId'] = $this->wx_openid; //固定，不要改
        $order['buyer_id'] = $this->member_info['alipay_userid'];
        return arrayBack('ok', true, [
            'order' => $order,
            'config' => $payment_config
        ]);
        //需返回以下字段
//        $order = [];
//        $order['pay_name'] = $pay->pay_name;
//        $order['pay_sn'] = $pay->pay_order_sn;
//        $order['pay_amount'] = $pay->total_amount;
//        $order['pay_attach'] = $this->payment_type; //固定，不要改
//        $order['wx_openId'] = $this->wx_openid; //固定，不要改
//        return arrayBack('ok', true, [
//            'order' => $order,
//            'config' => $payment_config
//        ]);
    }
    private function _getRealOrderBefore()
    {
        //支付方式验证
        $payment_config = \common\models\Payment::getPaymentOnlineConfig($this->payment_code);
        if (empty($payment_config) && !in_array($this->payment_code,[
                Payment::PAY_CODE_WX_APPLET,
                Payment::PAY_CODE_WX_APPLET_AHJ,
                Payment::PAY_CODE_BD_APPLET_AHJ,
                Payment::PAY_CODE_HD_WX_H5,
                Payment::PAY_CODE_HDALI_WAP,
                Payment::PAY_CODE_HD_ALI_APP,
                Payment::PAY_CODE_HD_WX_APP,
                Payment::PAY_CODE_WX_JSAPI,
                Payment::PAY_CODE_BD_APPLET,
                Payment::PAY_CODE_TT_APPLET,
                Payment::PAY_CODE_ALI_APPLET
            ])
        ) {
            //暂不支持此支付方式
            return arrayBack('初始化支付失败，请稍后再试[100]');
        }
        //获取支付信息
        if ($this->order_sn) {
            try {
                //单订单支付重新生成pay_sn并删除合并支付的信息
                $order_info = Orders::find()->where(['order_sn' => $this->order_sn, 'buyer_id' => $this->member_id, 'delete_state' => 0])->asArray()->one();
                if (!$order_info) {
                    throw new \Exception('0x7001');
                }
                if($order_info['payment_state'] == 1){
                    return arrayBack('订单已支付，请返回订单页查看');
                }
                //判断包月花套餐是否过期
                if(SITEID == 258 && ($order_info['order_type'] == 99 || $order_info['order_type'] == 6 || $order_info['order_type'] == 23)){
                    $ordergoods_info = OrderGoods::find()->where(['order_id' => $order_info['order_id']])->asArray()->one();
                    if($ordergoods_info['promotions_id']){
                        $condition = ['bl_id' => $ordergoods_info['promotions_id'], 'is_delete' => 0];
                        $p_bunding_goods_count = PBundlingGoods::find()->where($condition)->count();

                        $pbund_info = PBundling::find()->where(['bl_id' => $ordergoods_info['promotions_id'],'bl_state'=>1,'is_delete'=>0])->asArray()->one();
                        if(!$pbund_info){
                            return arrayBack('抱歉，此套餐不存在或已下架！');
                        }

                        $goods_count = OrderGoods::find()->where(['order_id' => $order_info['order_id'], 'goods_type' => 1])->count();

                        if ($pbund_info['norms_info']) {
                            $norms_info = unserialize($pbund_info['norms_info']);
                            if ($norms_info && !empty($norms_info)) {
                                $is_bool = false;
                                foreach ($norms_info as $no) {
                                    // 判断待付款订单中的规格是否是现有的规格
                                    if ($no['n_month'] * $p_bunding_goods_count == $goods_count) {
                                        $is_bool = true;
                                    }
                                }
                                if (!$is_bool) {
                                    return arrayBack('抱歉，当前规格不存在或已下架！');
                                }
                            }
                        }
                    }
                }

                $pay = new OrderPay();
                $pay = $pay->createSinglePay($order_info, $this->member_info);
            } catch (\Exception $e) {
                \Yii::error($e->getMessage());
                return arrayBack('初始化支付失败，请重试');
            }
        } else {
            $pay = OrderPay::findOne(['pay_sn' => $this->pay_sn, 'buyer_id' => $this->member_id, 'is_delete' => 0]);
            if(!$pay){
                return arrayBack('支付订单号不存在，请返回订单页查看');
            }
            if($pay->api_pay_state == 1){
                return arrayBack('订单已支付，请返回订单页查看');
            }
            $pay->last_pay_time = TIMESTAMP;
            $result = $pay->save();
            if (!$result) {
                return arrayBack('初始化订单支付失败，请重试');
            }
        }
        if (!$pay) {
            return arrayBack('未找到支付信息，请重试');
        }
        if ($pay->total_amount != $this->payment_amount) {
            return arrayBack('支付金额错误');
        }
        if ($pay->api_pay_state) {
            $r =  Member::updateAll(['draw_number' => ['draw_number' => 1]],['member_id' => $this->member_id]);
            return arrayBack('订单已支付成功');
        }
        //需返回以下字段
        $order = [];
        $order['pay_name'] = $pay->pay_name;
        $order['pay_sn'] = $pay->pay_order_sn;
        $order['pay_amount'] = $pay->total_amount;
        $order['add_time'] = $pay->add_time;
        $order['buyer_id'] = $this->member_info['alipay_userid'];
        $order['pay_attach'] = $this->payment_type; //固定，不要改
        $order['wx_openId'] = $this->wx_openid; //固定，不要改
        return arrayBack('ok', true, [
            'order' => $order,
            'config' => $payment_config
        ]);
    }

    private function _getAgentRechargeBefore()
    {
        //支付方式验证
        $payment_config = \common\models\Payment::getPaymentOnlineConfig($this->payment_code);
        if (empty($payment_config)) {
            //暂不支持此支付方式
            return arrayBack('初始化支付失败，请稍后再试');
        }
        //TODO 待完成代码
        if (!$this->pay_sn) {
            return arrayBack('未找到订单信息，请重试');
        }
        $where["order_sn"] = $this->pay_sn;
        $recharge = AgentRecharge::find()->where($where)->one();
        if (!$recharge) {
            return arrayBack('未找到订单信息，请重试[2]');
        }

        if ($recharge->order_amount != $this->payment_amount) {
            return arrayBack('支付金额错误');
        }
        if ($recharge->pay_status) {
            return arrayBack('订单已充值成功');
        }

        $order = [];
        $order['pay_name'] = "花递代理充值";
        $order['pay_sn'] = $recharge->order_sn;
        $order['pay_amount'] = $recharge->order_amount;
        $order['pay_attach'] = $this->payment_type; //固定，不要改
        $order['wx_openId'] = $this->wx_openid; //固定，不要改
        $order['buyer_id'] = $this->member_info['alipay_userid'];
        return arrayBack('ok', true, [
            'order' => $order,
            'config' => $payment_config
        ]);
        //需返回以下字段
//        $order = [];
//        $order['pay_name'] = $pay->pay_name;
//        $order['pay_sn'] = $pay->pay_order_sn;
//        $order['pay_amount'] = $pay->total_amount;
//        $order['pay_attach'] = $this->payment_type; //固定，不要改
//        $order['wx_openId'] = $this->wx_openid; //固定，不要改
//        return arrayBack('ok', true, [
//            'order' => $order,
//            'config' => $payment_config
//        ]);
    }

    private function _getYearCardBefore()
    {
        //支付方式验证
        if (empty($payment_config) && !in_array($this->payment_code,[
                Payment::PAY_CODE_WX_APPLET,
                Payment::PAY_CODE_WX_APPLET_AHJ,
                Payment::PAY_CODE_BD_APPLET_AHJ,
                Payment::PAY_CODE_HD_WX_H5,
                Payment::PAY_CODE_HDALI_WAP,
                Payment::PAY_CODE_HD_ALI_APP,
                Payment::PAY_CODE_HD_WX_APP,
                Payment::PAY_CODE_WX_JSAPI,
                Payment::PAY_CODE_BD_APPLET,
                Payment::PAY_CODE_TT_APPLET,
                Payment::PAY_CODE_ALI_APPLET
            ])
        ) {
            //暂不支持此支付方式
            return arrayBack('初始化支付失败，请稍后再试[100]');
        }
        $pay_sn = \Yii::$app->request->post('pay_sn');
        if(!empty($pay_sn) && empty($this->pay_sn)) {
            $this->pay_sn = $pay_sn;
        }
        if (!$this->pay_sn) {
            return arrayBack('未找到订单信息，请重试');
        }
        $where["pay_sn"] = $this->pay_sn;
        $yearcard = HuadiYearCardOrders::find()->where($where)->one();
        if (!$yearcard) {
            return arrayBack('未找到订单信息，请重试[2]');
        }
        $payment_amount = \Yii::$app->request->post('payment_amount');
        if(!empty($payment_amount) && empty($this->payment_amount)) {
            $this->payment_amount = $payment_amount;
        }
        if ($yearcard->order_amount != $this->payment_amount) {
            return arrayBack('支付金额错误');
        }
        if ($yearcard->payment_state) {
            return arrayBack('订单已支付成功');
        }

        $order = [];
        $order['pay_name'] = "花递年卡购买";
        $order['pay_sn'] = $yearcard->pay_sn;
        $order['order_sn'] = $yearcard->order_sn;
        $order['pay_amount'] = $yearcard->order_amount;
        $order['pay_attach'] = $this->payment_type; //固定，不要改
        $order['wx_openId'] = $this->wx_openid; //固定，不要改
        $order['buyer_id'] = $this->member_info['alipay_userid'];
        return arrayBack('ok', true, [
            'order' => $order,
            'config' => []
        ]);
    }
    /**
     *回调设置支付code
     */
    private function _setNotifyPaymentCode($payment_code = '')
    {
        $raw = file_get_contents('php://input');
        $get = \Yii::$app->request->get();
        $post = \Yii::$app->request->post();

        Log::writelog('Notify', $raw);
        Log::writelog('Notify', $get);
        Log::writelog('Notify', $post);

        $this->payment_code = $payment_code ? $payment_code : Request('code');
    }

    /**
     * 统一回调
     */
    public function actionNotify()
    {
        $this->_setNotifyPaymentCode();
        $this->_notify();
    }

    /**
     * 支付宝APP回调
     */
    public function actionNotifyAlipay()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_ALI_APP);
        $this->_notify();
    }

    /**
     * 花递支付宝APP回调
     */
    public function actionNotifyAlipayApp()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_HD_ALI_APP);
        $this->_notify();
    }

    /**
     * 支付宝WAP回调
     */
    public function actionNotifyAliwap()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_ALI_WAP);
        $this->_notify();
    }

    /**
     * 花递WAP 支付宝WAP回调
     */
    public function actionNotifyHdaliwap()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_HDALI_WAP);
        $this->_notify();
    }
    /**
     * 花递小程序 支付宝WAP回调
     */
    public function actionNotifyHdaliapplet()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_ALI_APPLET);
        $this->_notify();
    }
    /**
     * 花递小程序 百度小程序支付回调
     */
    public function actionNotifyAppletbdpay()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_BD_APPLET);
        $this->_notify();
    }
    /**
     * 花递小程序 头条小程序-微信支付回调
     */
    public function actionNotifyhdappletttpay()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_TT_APPLET);
        $this->_notify();
    }
    /**
     * 微信H5回调
     */
    public function actionNotifywxh5()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_WX_H5);
        $this->_notify();
    }

    /**
     * 花递WAP 微信H5回调
     */
    public function actionHdnotifywxh5()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_HD_WX_H5);
        $this->_notify();
    }
    /**
     * 花递WAP 微信内JSAPI回调
     */
    public function actionHdnotifywxjsapi()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_WX_JSAPI);
        $this->_notify();
    }

    /**
     * 微信App回调
     */
    public function actionNotifywxapp()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_WX_APP);

        $this->_notify();
    }

    /**
     * 花递App微信支付回调
     */
    public function actionHdnotifywxapp()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_HD_WX_APP);

        $this->_notify();
    }

    /**
     * 微信小程序扫码支付回调
     */
    public function actionsNotifywxsp()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_WX_SMALL_PROGRAM);
        $this->_notify();
    }

    /**
     * 花递微信小程序支付回调
     */
    public function actionNotifywxapplet()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_WX_APPLET);
        $this->_notify();
    }

    /**
     * 爱花居微信小程序支付回调
     */
    public function actionNotifywxappletahj()
    {
        $this->_setNotifyPaymentCode(Payment::PAY_CODE_WX_APPLET_AHJ);
        $this->_notify();
    }

    /**
     * 回调处理
     */
    private function _notify()
    {
        //创建支付对象;
        $payment = Payment::create($this->payment_code);

        if ($payment == false || !method_exists($payment, 'notify')) {
            $this->_notifyOutput(false, "0x9000");
        }

        //回调验证及后续处理
        $result = $payment->notify();
        $this->_notifyOutput($result['state'], $result['msg']);
    }

    /**
     * 统一回调第三方
     * @param bool $success
     * @param string $msg
     */
    private function _notifyOutput($success = false, $msg = "")
    {
        if ($msg) {
            \Yii::error($this->payment_code . ':' . $msg);
            Log::writelog('NotifyOutput', $this->payment_code . ':' . $msg);
        }
        switch ($this->payment_code) {
            case Payment::PAY_CODE_ALI_WAP:
            case Payment::PAY_CODE_ALI_APP:
                $message = $success ? "success" : "failure";
                exit($message);
                break;
            case Payment::PAY_CODE_WX_ZX:
            case Payment::PAY_CODE_WX_ZX_H5:
            case Payment::PAY_CODE_WX_ZX_H53:
            case Payment::PAY_CODE_WX_ZX_APP:
            case Payment::PAY_CODE_WX_H5:
            case Payment::PAY_CODE_WX_APP:
            case Payment::PAY_CODE_WX_JSAPI:
                $reply = new WxPayNotifyReply();
                if ($success) {
                    $reply->SetReturn_code("SUCCESS");
                    $reply->SetReturn_msg("OK");
                } else {
                    $reply->SetReturn_code("FAIL");
                    $reply->SetReturn_msg("FAIL");
                }
                WxPayApi::replyNotify($reply->ToXml());
                die;
                break;
            case Payment::PAY_CODE_WX_SMALL_PROGRAM:
                $reply = new WxPayNotifyReply();
                if ($success) {
                    $reply->SetReturn_code("SUCCESS");
                    $reply->SetReturn_msg("OK");
                } else {
                    $reply->SetReturn_code("FAIL");
                    $reply->SetReturn_msg("FAIL");
                }
                WxPayApi::replyNotify($reply->ToXml());
                die;
                break;
            case Payment::PAY_CODE_WX_APPLET:
                $reply = new WxPayNotifyReply();
                if ($success) {
                    $reply->SetReturn_code("SUCCESS");
                    $reply->SetReturn_msg("OK");
                } else {
                    $reply->SetReturn_code("FAIL");
                    $reply->SetReturn_msg("FAIL");
                }
                WxPayApi::replyNotify($reply->ToXml());
                die;
                break;
            case Payment::PAY_CODE_WX_APPLET_AHJ:
                $reply = new WxPayNotifyReply();
                if ($success) {
                    $reply->SetReturn_code("SUCCESS");
                    $reply->SetReturn_msg("OK");
                } else {
                    $reply->SetReturn_code("FAIL");
                    $reply->SetReturn_msg("FAIL");
                }
                WxPayApi::replyNotify($reply->ToXml());
                die;
                break;
            case Payment::PAY_CODE_WX_ZX_JSAPI:
                $message = $success ? "success" : "fail";
                exit($message);
                break;
            case Payment::PAY_CODE_HD_WX_APP:
                $reply = new WxPayNotifyReply();
                if ($success) {
                    $reply->SetReturn_code("SUCCESS");
                    $reply->SetReturn_msg("OK");
                } else {
                    $reply->SetReturn_code("FAIL");
                    $reply->SetReturn_msg("FAIL");
                }
                WxPayApi::replyNotify($reply->ToXml());
                die;
                break;
            case Payment::PAY_CODE_HD_ALI_APP:
                $reply = new WxPayNotifyReply();
                if ($success) {
                    $reply->SetReturn_code("SUCCESS");
                    $reply->SetReturn_msg("OK");
                } else {
                    $reply->SetReturn_code("FAIL");
                    $reply->SetReturn_msg("FAIL");
                }
                WxPayApi::replyNotify($reply->ToXml());
                die;
                break;
        }
        die;
    }

    /**
     * 统一回调第三方测试
     */
    public function actionNotifyTest()
    {
        $this->payment_code = 'zxh5pay';
        $this->_notifyOutput(false, 'd');
    }

    /**
     * 第三方前台回调
     * @return \yii\web\Response
     */
    public function actionReturn()
    {
        sleep(1);//延迟一下
        //'buyer_id' => $this->member_id, TODO

        if(is_numeric($this->pay_sn)){
            //数字类型的pay_sn
            $where['pay_sn'] = $this->pay_sn;
        }else{
            $where['pay_order_sn'] = $this->pay_sn;
        }
        $pay = OrderPay::find()->where($where)
            ->andWhere(['is_delete' => 0])->one();
        $order = $pay ? Orders::findOne(['pay_sn' => $pay->pay_sn]) : false;
        $query = [];
        $query['success'] = $pay && $pay->api_pay_state ? 1 : 0;
        $query['pay_sn'] = $pay ? $pay->pay_sn : '';
        $query['pay_amount'] = $pay ? $pay->total_amount : 0;
        $query['order_id'] = $order ? $order->order_id : 0;
        $query['is_group_shopping'] = $order ? $order->group_shopping_state : 0;
        $query['team_id'] = $order ? $order->group_shopping_team_id : 0;
        //&order_ids=${this.$route.query.order_ids}&is_group_shopping=1&team_id=${this.$route.query.team_id}
        if(WEB_DOMAIN == 'http://huadi.aihuaju.com' || WEB_DOMAIN == 'https://www.huadi01.cn'|| WEB_DOMAIN == 'http://www.hua.zj.cn'){
            return $this->redirect(WEB_DOMAIN . '/h5/pay/result?' . http_build_query($query), 301);
        }
        return $this->redirect(WEB_DOMAIN . '/pay/result?' . http_build_query($query), 301);
    }


}
