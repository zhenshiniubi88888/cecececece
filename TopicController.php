<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Message;
use common\components\MicroApi;
use common\models\Address;
use common\models\Cart;
use common\models\CmsConstel;
use common\models\CmsConstelGoods;
use common\models\Data;
use common\models\Florist;
use common\models\Goods;
use common\models\GoodsImageLabel;
use common\models\GoodsImages;
use common\models\Orders;
use Faker\Test\Provider\Collection;
use yii\db\Expression;

/**
 * TopicController controller
 */
class TopicController extends BaseController
{
    public function actionData()
    {
        $topic_id = (int)\Yii::$app->request->post('topic_id', 0);
        if ($topic_id < 1) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $topic_cache_name = md5('topic_3' . $topic_id);
        if ($data = cache($topic_cache_name)) {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        }


        //获取专题详情，暂不判断时效
        $cms_constel = CmsConstel::findOne($topic_id);
        if (empty($cms_constel)) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }


        //获取专题关联商品
        $cms_constel_goods = CmsConstelGoods::find()->select('goods_id')->where(['constel_id' => $topic_id])->asArray()->all();

        $field = 'goods_id,goods_name,goods_jingle,goods_image,goods_material,goods_price,ahj_goods_price,goods_marketprice';
        $goods_list = Goods::instance()->getGoodsList(['goods_id' => array_column($cms_constel_goods, 'goods_id')], '', $field);

        //排序重组(非常重要)
        $temp_goods = array();
        foreach ($cms_constel_goods as $item) {
            foreach ($goods_list as $goods) {
                if ($item['goods_id'] == $goods['goods_id']) {
                    array_push($temp_goods, $goods);
                    break;
                }
            }
        }
        $goods_data = array();
        foreach ($temp_goods as $goods) {
            array_push($goods_data, [
                'goods_id' => $goods['goods_id'],
                'goods_name' => $goods['goods_name'],
                'goods_jingle' => $goods['goods_jingle'],
                'goods_material' => $goods['goods_material'],
                'goods_image' => thumbGoods($goods['goods_image'], 320),
                'goods_price' =>  FinalPrice::S($goods['goods_price']),
                'goods_marketprice' => FinalPrice::M($goods['goods_price'],$goods['goods_marketprice']),
            ]);
        }


        $data = array();
        $data['goods_list'] = $goods_data;

        //生成公告
        if (in_array($topic_id, array(134))) {
            $condition = ['<', 'payment_time', strtotime(date('Y-m-d'))];
            $condition = ['AND', ['payment_state' => 1], $condition];
            $order_model = Orders::find();
            $order = $order_model->select('payment_time,buyer_name,buyer_phone,goods_amount')->where($condition)->orderBy('order_id desc')->limit(1)->one();
            if ($order) {
                $data['order_free'] = sprintf("%s免单：%s %s 订单金额%s元"
                    , date('m-d', $order->payment_time)
                    , mb_substr($order->buyer_name, 0, 1, 'UTF-8') . '先生'
                    , mobile_format($order->buyer_phone)
                    , $order->goods_amount
                );
            }
        }

        //生成优惠券
        if (in_array($topic_id, array(134))) {
            $voucher_id = [181, 182, 183, 184];
            $data['voucher_key'] = array_map(function ($voucher_id) {
                return base64_encode(\Yii::$app->getSecurity()->encryptByPassword($voucher_id . '|' . TIMESTAMP, SECURITY_KEY));
            }, $voucher_id);
        }
        if (in_array($topic_id, array(138))) {
            $voucher_id = [203,200, 201];
            $data['voucher_key'] = array_map(function ($voucher_id) {
                return base64_encode(\Yii::$app->getSecurity()->encryptByPassword($voucher_id . '|' . TIMESTAMP, SECURITY_KEY));
            }, $voucher_id);
        }

        //每日更新
        cache($topic_cache_name, $data, 86400);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);

    }

}
