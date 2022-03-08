<?php

namespace frontend\controllers;
use common\components\FinalPrice;
use common\components\HuawaApi;
use common\components\KuaisongApi;
use common\components\Log;
use common\components\Message;
use common\components\Weixin;
use common\components\WeixinHuadi;
use common\components\WeixinSubscribeMsg;
use common\helper\DateHelper;
use common\helper\SensitiveWord;
use common\models\AlyContentSecurity;
use common\models\Crontab;
use common\models\Goods;
use common\models\HuadiStoreZm;
use common\models\HuadiYearCardHelpVoucher;
use common\models\HuadiYearCardOrders;
use common\models\Member;
use common\models\OrderCommon;
use common\models\OrderGoods;
use common\models\OrderLog;
use common\models\Orders;
use common\models\Setting;
use common\models\Sms;
use common\models\Voucher;
use console\controllers\MinutesController;
use foo\bar;
use yii\web\Controller;

class TestController extends Controller
{
    public function actionIndex()
    {
        $condition = [];
        $condition['payment_state'] = 1;//已付款订单
        $order_list = HuadiYearCardOrders::find()->select('distinct(member_id)')->where($condition)->andWhere(['>=', 'expired_time', TIMESTAMP])->orderBy('id asc')->limit(100)->asArray()->all();
        if (empty($order_list)) {
            return true;
        }
        //已领取过月券用户
        //本月初时间
        $m_start_time = strtotime(date('Y-m-01 00:00:00'));
        $m_end_time = strtotime(date("Y-m-01 00:00:00",strtotime("+1 month")));
        $month_voucher_condition = [];
        $month_voucher_condition['voucher_t_id'] = HuadiYearCardOrders::instance()->month_voucher_template_ids;
        $voucher = Voucher::find()->where($month_voucher_condition)->andWhere(['between', 'voucher_active_date', $m_start_time, $m_end_time])->select('voucher_owner_id')->asArray()->all();
        $al_voucher_memberids = array_column($voucher,'voucher_owner_id');
        foreach ($order_list as $order) {
            if(in_array($order['member_id'],$al_voucher_memberids)){
                continue;
            }
            //发放月券
            $result = Voucher::instance()->exchangeMember(HuadiYearCardOrders::instance()->month_voucher_template_ids,$order['member_id'],'花递年卡每月送券');
            if(!$result){
                echo "发放失败";
                continue;
            }
            $member_info = Member::find()->where(['member_id'=>$order['member_id']])->select("member_mobile")->asArray()->one();
            if(isset($member_info['member_mobile']) && isMobile($member_info['member_mobile'])){
                sendSms($member_info['member_mobile'], "省钱年卡每月送您10元无门槛现金券已到账，本月内有效。快去花递省钱年卡立即领取使用吧，退订回T",1,'花递');
            }
            echo "发放成功";
        }
        return true;
    }

    public function actionCeshi()
    {
        require_once __DIR__.'/../../vendor/jpush/autoload.php';
        $app_key = 'dd1066407b044738b6479275';
        $master_secret = 'e8cc9a76d5b7a580859bcfa';
        $client = new \JPush\Client($app_key, $master_secret);
        var_dump($client);die;
        $push_payload = $client->push()
            ->setPlatform(array('ios', 'android'))
            ->addAlias('alias1')
            ->addTag(array('tag1', 'tag2'))
            ->setNotificationAlert('Hi, JPush')
            ->addAndroidNotification('Hi, android notification', 'notification title', 1, array("key1"=>"value1", "key2"=>"value2"))
            ->addIosNotification("Hi, iOS notification", 'iOS sound', 0x10000, true, 'iOS category', array("key1"=>"value1", "key2"=>"value2"))
            ->setMessage("msg content", 'msg title', 'type', array("key1"=>"value1", "key2"=>"value2"))
            ->setOptions(100000, 3600, null, false)
            ->send();
    }

    public function actionSendMonthFlower(){
        $map = [];
        $map['order_type'] = Orders::ORDER_TYPE_WAIT;
        $map['order_state'] = ORDER_STATE_PAY;
        $map['payment_state'] = 1;
        $map['siteid'] = 258;
        $map['delete_state'] = 0;
        $home_flower_order = Orders::findOne($map);
        Log::writelog('explodeHomeFlowerOrder',$home_flower_order ? $home_flower_order->order_id : 'ok');
        if (!$home_flower_order) {
            //已拆完
            return true;
        }
        date_default_timezone_set("PRC");
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $order_common = OrderCommon::findOne(['order_id' => $home_flower_order->order_id]);
            $order_goods = OrderGoods::findAll(['order_id' => $home_flower_order->order_id]);
            $voucher = Voucher::findOne(['voucher_order_id' => $home_flower_order->order_id]);
            //价格算法
            //

            // 加购的价格
            $price_jia_gou = 0;
            $goods_id_jg_zs = [];
            $goods_jg = [];
            $goods_zs = [];
            $order_goods_count = count($order_goods);
            foreach ($order_goods as $k => $goods) {
                if ($goods['goods_type'] == GOODS_ORDER_TYPE_JIA_GOU ) {
                    $price_jia_gou += $goods['cart_price'];
                }

                if ($goods['goods_type'] == GOODS_ORDER_TYPE_JIA_GOU) {
                    $goods_id_jg_zs[] = $goods['goods_id'];
                    $goods_jg[] = $goods;
                    unset($order_goods[$k]);
                } elseif ($goods['goods_type'] == GOODS_ORDER_TYPE_ZENG_SONG) {
                    $goods_id_jg_zs[] = $goods['goods_id'];
                    $goods_zs[] = $goods;
                    unset($order_goods[$k]);
                }
            }

            // 如果有加购和赠送的商品，则需要再新建一个订单
            if (!empty($goods_id_jg_zs)) {
                $cb_amount = $goods_amount = $jm_amount = $gj_amount = $total_amount = 0;
                $delivery_time = 0;
                // 加购商品
                if (!empty($goods_jg)) {
                    foreach ($goods_jg as $jg) {
                        $goods_original = Goods::findOne(['goods_id' => $jg['goods_id']]);
                        $goods_amount += $goods_original->ahj_goods_price;
                        $cb_amount += $goods_original->goods_costprice * $jg->goods_num;
                        $jm_amount += $goods_original->goods_jm_costprice * $jg->goods_num;
                        $gj_amount += $goods_original->goods_gj_costprice * $jg->goods_num;
                        $total_amount += $jg->goods_pay_price;
                        $delivery_time = $jg->delivery_time;
                    }

                }
                // 赠送商品
                if (!empty($goods_zs)) {
                    foreach ($goods_zs as $zs) {
                        $goods_original = Goods::findOne(['goods_id' => $zs['goods_id']]);
                        $goods_amount += $goods_original->ahj_goods_price;
                        $cb_amount += $goods_original->goods_costprice * $zs->goods_num;
                        $jm_amount += $goods_original->goods_jm_costprice * $zs->goods_num;
                        $gj_amount += $goods_original->goods_gj_costprice * $zs->goods_num;
                        $total_amount += $zs->goods_pay_price;
                        $delivery_time = $zs->delivery_time;
                    }
                }

                //新增新订单
                $new_order = new Orders();
                $new_order->setAttributes($home_flower_order->toArray());
                $new_order->setAttribute('order_id', null);
                $new_order->order_type = GOODS_TYPE_JIA_GOU_ZENG_SONG;
                $new_order->order_type_title = '加购赠送商品';
                $new_order->order_sn = Orders::makeSn($home_flower_order->buyer_id);
                $new_order->todate = date('Y-m-d', $delivery_time);
                $new_order->toshiduan = '1'; //不限时段
                $new_order->expired_time = getExpireTime($new_order->todate);
                //平分价格
                $new_order->goods_amount = $goods_amount;
                $new_order->total_amount = $total_amount;
                $new_order->order_amount = $total_amount;

                $new_order->order_base_amount = $total_amount;
                $new_order->cb_amount = $cb_amount;
                $new_order->jm_amount = $jm_amount;
                $new_order->gj_amount = $gj_amount;
                $new_order->base_cb_amount = $new_order->cb_amount;
                $new_order->shipping_fee = 0;
                $new_order->delivery_fee = 0;
                $new_order->isNewRecord = true;
                $new_order->delivery_type = 1;
                $new_order->delivery_store_id = JIA_GOU_STORE_ID;
                $result = $new_order->save();
                if (!$result) {
                    throw new \Exception($new_order->getErrors());
                }

                //新增新的common记录
                $new_common = $order_common;
                $new_common->setAttribute('order_id', $new_order->getAttribute('order_id'));
                $new_common->isNewRecord = true;
                $result = $order_common->save();
                if (!$result) {
                    throw new \Exception($order_common->getErrors());
                }

                // 加购商品
                if (!empty($goods_jg)) {
                    //修改order_id
                    foreach ($goods_jg as $jg) {
                        $jg->setAttribute('order_id', $new_order->getAttribute('order_id'));
                        $result = $jg->save();
                        if (!$result) {
                            throw new \Exception($jg->getErrors());
                        }
                    }
                }

                // 赠送商品
                if (!empty($goods_zs)) {
                    //修改order_id
                    foreach ($goods_zs as $zs) {
                        $zs->setAttribute('order_id', $new_order->getAttribute('order_id'));
                        $result = $zs->save();
                        if (!$result) {
                            throw new \Exception($zs->getErrors());
                        }
                    }
                }

                //添加order_log
                $result = OrderLog::addBuyerLog($new_order, '订单提交成功');
                if (!$result) {
                    throw new \Exception('0x2004', API_CODE);
                }
                $log = new OrderLog();
                $log->order_id = $new_order->order_id;
                $log->log_msg = '等待花店接单';
                $log->log_time = TIMESTAMP + 1;
                $log->log_role = 'user';
                $log->log_user = $new_order->buyer_name;
                $log->log_orderstate = $new_order->order_state <= 40 ? (string)$new_order->order_state : '0';
                $result = $log->insert();
                if (!$result) {
                    throw new \Exception('0x2004', API_CODE);
                }
            }

            // 订单价格需要减去加购价格
            $home_flower_order->order_amount = $home_flower_order->order_amount - $price_jia_gou;
            $base_voucher_price = $key_max = 0;
            if($voucher){
                $base_voucher_price = $voucher->voucher_price;
                $key_max = $order_goods_count - 1;
            }
            $mod = $delivery_fee_avg = 0;
            //如果有配送费
            if($home_flower_order->shipping_fee || $home_flower_order->delivery_fee){
                $delivery_fee_total = $home_flower_order->shipping_fee > 0 ? $home_flower_order->shipping_fee : $home_flower_order->delivery_fee;
                $mod = $delivery_fee_total % $order_goods_count;
                $delivery_fee_avg = ($delivery_fee_total - $mod) / $order_goods_count;
            }

            foreach ($order_goods as $k => $goods) {
                //获取最新商品信息
                $goods_original = Goods::findOne(['goods_id'=>$goods->goods_id]);
                //新增新订单
                $new_order = new Orders();
                $new_order->setAttributes($home_flower_order->toArray());
                $new_order->setAttribute('order_id', null);
                $new_order->order_type = Orders::ORDER_TYPE_MONTH;
                $new_order->order_type_title = '包月鲜花';
                $new_order->order_sn = Orders::makeSn($home_flower_order->buyer_id);
                $new_order->todate = date('Y-m-d', $goods->delivery_time);
                $new_order->toshiduan = '1'; //不限时段
                $new_order->expired_time = getExpireTime($new_order->todate);
                //平分价格
                $new_order->goods_amount = $goods_original->ahj_goods_price;
                $new_order->total_amount = $goods->goods_pay_price;
                //花递订单包月花使用优惠卷时 每笔订单的实际售价 - (优惠券金额/未优惠前的实际订单金额*未优惠前的单笔订单金额)
                if($voucher){
                    $discount = $voucher->voucher_price / $home_flower_order->order_amount * $goods->goods_pay_price;
                    $discount_int = intval($discount);
                    if($k == $key_max ){
                        $order_amount = $goods->goods_pay_price - $base_voucher_price;
                    }else{
                        $order_amount = $goods->goods_pay_price - $discount_int;
                        $base_voucher_price -= $discount_int;
                    }
                    $new_order->order_amount = sprintf("%.2f",$order_amount);

                }else{
                    $new_order->order_amount = $goods->goods_pay_price;
                }

                if($home_flower_order->shipping_fee || $home_flower_order->delivery_fee){
                    if($k == 0){
                        $new_order->order_amount = $new_order->order_amount + $delivery_fee_avg + $mod;
                    }else{
                        $new_order->order_amount = $new_order->order_amount + $delivery_fee_avg;
                    }
                }
                $new_order->order_base_amount = $goods->goods_pay_price;
                $new_order->cb_amount = $goods_original->goods_costprice * $goods->goods_num;
                $new_order->jm_amount = $goods_original->goods_jm_costprice * $goods->goods_num;
                $new_order->gj_amount = $goods_original->goods_gj_costprice * $goods->goods_num;
                $new_order->base_cb_amount = $new_order->cb_amount;
                $new_order->shipping_fee = 0;
                $new_order->delivery_fee = 0;
                $new_order->isNewRecord = true;
                $result = $new_order->save();
                if (!$result) {
                    throw new \Exception($new_order->getErrors());
                }
                //新增新的common记录
                $new_common = $order_common;
                $new_common->setAttribute('order_id', $new_order->getAttribute('order_id'));
                $new_common->isNewRecord = true;
                $result = $order_common->save();
                if (!$result) {
                    throw new \Exception($order_common->getErrors());
                }
                //修改order_id
                $goods->setAttribute('order_id', $new_order->getAttribute('order_id'));
                $result = $goods->save();
                if (!$result) {
                    throw new \Exception($goods->getErrors());
                }
                //添加order_log
                $result = OrderLog::addBuyerLog($new_order, '订单提交成功');
                if (!$result) {
                    throw new \Exception('0x2004', API_CODE);
                }
                $log = new OrderLog();
                $log->order_id = $new_order->order_id;
                $log->log_msg = '等待花店接单';
                $log->log_time = TIMESTAMP + 1;
                $log->log_role = 'user';
                $log->log_user = $new_order->buyer_name;
                $log->log_orderstate = $new_order->order_state <= 40 ? (string)$new_order->order_state : '0';
                $result = $log->insert();
                if (!$result) {
                    throw new \Exception('0x2004', API_CODE);
                }
            }
            $home_flower_order->order_type = Orders::ORDER_TYPE_DECLARE;
            $home_flower_order->order_state = 0;
            $home_flower_order->payment_state = 0;
            $home_flower_order->delete_state = 1;
            $result = $home_flower_order->save();
            if (!$result) {
                throw new \Exception($home_flower_order->getErrors());
            }
            $transaction->commit();
        } catch (\Exception $e) {
            \Yii::error($e->getMessage());
            Log::writelog('explodeHomeFlowerOrder',$home_flower_order->order_id.$e->getMessage());
            $transaction->rollBack();
            return false;
        }
        return true;
    }
    public function actionTest(){
        echo TIMESTAMP;
        echo PHP_EOL;
        echo time();
        echo PHP_EOL;
        echo microtime();
        echo PHP_EOL;
        echo date('Y-m-d H:i:s');
    }

    /**
     * 刷新setting缓存数据
     * @return bool
     */
    public function actionRefreshSettingCache(){
        Setting::instance()->getAll(true);
        return true;
    }

    public function actionGetVoucherKey($voucher_t_id,$time){
        return $voucher_template['voucher_key'] = base64_encode(\Yii::$app->getSecurity()->encryptByPassword($voucher_t_id . '|' . $time, SECURITY_KEY));
    }
    public function actionFilterEmoji($str){
        echo $str;
        echo PHP_EOL;
        echo filterEmoji($str);
    }
    public function actionSouvenirNotify(){
        return (new Crontab())->souvenirNotify();
    }
    public function actionHuadiOpenid(){
        set_time_limit(0);
//        return (new Crontab())->insertGzOpenid();
        return (new Crontab())->insertGzOpenidBy100();
    }
    public function actionLunar(){
        $date = \Yii::$app->request->get('date');
        $toyear = \Yii::$app->request->get('toyear');
        list($y,$m,$d) = explode('-',$date);
        $lunar = (new DateHelper())->convertSolarToLunar($y,$m,$d);
        //阳历转阴历, 如果阴历后续要转阳历 需要判断值需不需要根据闰月-
        if($lunar[7] >0 && $lunar[4] > $lunar[7]) $lunar[4]--;
        $run_yue = (new DateHelper())->getLeapMonth($toyear);
        //阴历转阳历, 如果当前年份对应有闰月的话, 需要判断阴历值需不需要加
       if($run_yue>0 && $lunar[4] > $run_yue) $lunar[4]++;
    }
    public function actionSyncFakeGroupMember()
    {
        (new Crontab())->insertFakeGroupMember();
    }
    public function actionShare(){
        $url = \Yii::$app->request->get('url');
        $data = WeixinHuadi::getInstance()->getSignPackage($url);
        return json_encode([
            'code' => 200,
            'msg' => 'success',
            'data' => $data,
        ]);
    }
    public function actionContentFilter(){
        $content = \Yii::$app->request->post('content');
        $aly = new AlyContentSecurity();
        $text_res = $aly->detectionText($content);
        $text_res = json_decode($text_res, true);
        if ($text_res['code'] != 200) {
            return $text_res['msg'];
        }
    }
    public function actionGetCache(){
        $video_id = \Yii::$app->request->post('video_id');
        var_dump( cache('huadi_video_related_cart_' . $video_id));
    }
    public function actionMin(){
        $content = \Yii::$app->request->post('content');
        $res = SensitiveWord::detectSensitiveWord($content);
        if($res){
            var_dump('检测到敏感词汇:'.$res);
        }
    }
    public function actionFullUpdateZmGoods(){
        $store_ids = HuadiStoreZm::find()->select('store_id')->asArray()->all();
        \Yii::$app->db->createCommand()->batchInsert('hua123_huadi_upd_store', ['store_id'], $store_ids)->execute();
    }
}
