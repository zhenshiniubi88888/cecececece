<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Message;
use common\models\Attribute;
use common\models\Goods;
use common\models\GoodsClass;
use common\models\PBundling;

/**
 * GiftFlowerController
 */
class GiftFlowerController extends BaseController
{

    public function actionIndex()
    {
        $data = [];
        //获取礼品花顶部商品Banner列表
        $data['goods_banner'] = $this->getGiftFlowerBanner();
        //获取礼品花今日推荐
        $data['goods_recommend'] = $this->getGiftFlowerRecommend();
        //获取礼品花热门分类
        $data['attribute_list'] = $this->getGiftFlowerHotAttribute();
        //获取礼品花热卖榜
        $data['goods_hot'] = $this->getGiftFlowerHotGoods();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //获取礼品花顶部商品Banner列表
    private function getGiftFlowerBanner()
    {
        $model_goods = new Goods();
        $condition = [];
        $condition['gc_id'] = Goods::FLOWER_GIFT;
        $condition['goods_state'] = 1;
        $field = 'goods_id,goods_image,goods_name,goods_material,ahj_goods_price as goods_price,goods_costprice';
        $goods_list = $model_goods->getGoodsList($condition, null, $field, 'sort_order desc', 10);
        $goods_data = [];
        foreach ($goods_list as $goods) {
            $data = [];
            $data['goods_id'] = $goods['goods_id'];
            $data['goods_type'] = GOODS_TYPE_FLOWER;
            $data['goods_image'] = thumbGoods($goods['goods_image'], 360);
            $data['goods_name'] = $goods['goods_name'];
            $data['goods_material'] = $goods['goods_material'];
            $data['goods_price'] = FinalPrice::S($goods['goods_price']);
            $goods_data[] = $data;
        }
        return $goods_data;
    }

    //获取礼品花今日推荐
    private function getGiftFlowerRecommend()
    {
        $model_goods = new Goods();
        $condition = [];
        $condition['gc_id'] = Goods::FLOWER_GIFT;
        $condition['goods_state'] = 1;
        $condition['goods_commend'] = 1;
        $field = 'goods_id,goods_image,goods_name,ahj_goods_price as goods_price,goods_costprice';
        $goods_list = $model_goods->getGoodsList($condition, null, $field, 'sort_order desc', 3);
        $goods_data = [];
        foreach ($goods_list as $goods) {
            $data = [];
            $data['goods_id'] = $goods['goods_id'];
            $data['goods_type'] = GOODS_TYPE_FLOWER;
            $data['goods_image'] = thumbGoods($goods['goods_image'], 260);
            $data['goods_name'] = $goods['goods_name'];
            $data['goods_price'] = FinalPrice::S($goods['goods_price']);
            $goods_data[] = $data;
        }
        return $goods_data;
    }

    //获取礼品花热门分类
    private function getGiftFlowerHotAttribute()
    {
        $model_attr = new Attribute();
        return $model_attr->getHotAttribute(1);
    }

    //获取礼品花热卖榜
    private function getGiftFlowerHotGoods()
    {
        $model_goods = new Goods();
        $condition = [];
        $condition['gc_id'] = Goods::FLOWER_GIFT;
        $condition['goods_state'] = 1;
        //$condition['is_hot'] = 1;
        $field = 'goods_id,goods_image,goods_name,goods_material,goods_jingle,ahj_goods_price as goods_price,goods_costprice';
        $goods_list = $model_goods->getGoodsList($condition, null, $field, 'sort_order desc,goods_salenum desc,goods_custom_salenum desc,goods_id desc', 10);
        $goods_data = [];
        foreach ($goods_list as $key => $goods) {
            $data = [];
            $data['goods_rank'] = $key + 1;
            $data['goods_id'] = $goods['goods_id'];
            $data['goods_type'] = GOODS_TYPE_FLOWER;
            $data['goods_image'] = thumbGoods($goods['goods_image'], 360);
            $data['goods_name'] = $goods['goods_name'];
            $data['goods_jingle'] = $goods['goods_jingle'];
            $data['goods_material'] = $goods['goods_material'];
            $data['goods_price'] = FinalPrice::S($goods['goods_price']);
            $goods_data[] = $data;
        }
        return $goods_data;
    }


}
