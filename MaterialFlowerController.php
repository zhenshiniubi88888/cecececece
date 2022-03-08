<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Message;
use common\models\Cart;
use common\models\EvaluateGoods;
use common\models\Goods;
use common\models\GoodsClass;
use common\models\PBundling;
use common\models\PBundlingGoods;

/**
 * MaterialFlowerController
 */
class MaterialFlowerController extends BaseController
{

    public function actionIndex()
    {
        //获取当前用户已加入购物车的数据
        $cart_data = $this->actionTrade(true);
        $data = [];
        //获取套餐推荐
        $data['bundling_list'] = $this->getMaterialBundling($cart_data);
        //获取评价信息
        $data['comment'] = $this->getMaterialComment();
        //获取花材分类
        $data['category_list'] = $this->getMaterialCategory();
        //获取花材列表
        $data['material_list'] = $this->getMaterialList($cart_data);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /***
     * 获取套餐推荐
     * @return array
     */
    private function getMaterialBundling($cart_data = [])
    {
        $model_bundling = new PBundling();
        $bundling_list = $model_bundling->getMaterialBundlingList(true);
        $bundling_data = [];
        foreach ($bundling_list as $bundling) {
            $selected_num = 0;
            foreach ($cart_data as $cart) {
                if (isset($cart['bl_id']) && $cart['bl_id'] == $bundling['bl_id']) {
                    $selected_num = $cart['selected_num'];
                    break;
                }
            }
            $bundling_data[] = [
                'bl_id' => $bundling['bl_id'],
                'bundling_img' => thumbGoods($bundling['goods_list'][0]['goods_image'], 260),//取第一张
                'bundling_name' => $bundling['bl_name'],
                'discount_price' => $bundling['bl_discount_price'],
                'selected_num' => $selected_num,
            ];
        }
        return $bundling_data;
    }

    /**
     * 获取店铺评价
     * @return array
     */
    private function getMaterialComment()
    {
        $model_comment = new EvaluateGoods();
        return [
            'comment_num' => (int)$model_comment->getEvaluateCount(['geval_type' => 2])
        ];
    }

    /**
     * 获取花材分类
     * @return array
     */
    private function getMaterialCategory()
    {
        $class_model = new GoodsClass();
        $hot_class = [['gc_id' => "0", 'gc_name' => '热门推荐']];
        $goods_class = $class_model->getGoodsClass(['gc_parent_id' => Goods::FLOWER_MATERIAL], 'gc_id,gc_name');
        return array_merge($hot_class, $goods_class);
    }

    /**
     * 获取花材列表
     * @return array
     */
    private function getMaterialList($cart_data = [])
    {
        $goods_model = new Goods();
        $field = 'goods.goods_id,goods.gc_id,goods.goods_image,goods.goods_name,goods.goods_salenum,goods.goods_custom_salenum,goods.ahj_goods_price as goods_price,goods.goods_costprice,goods.goods_marketprice,goods_class.gc_name,goods.goods_commend';
        $goods_list = $goods_model->getGoodsList(['goods.gc_id_2' => Goods::FLOWER_MATERIAL,'goods_state'=>Goods::GOODS_STATE_OK], 'goods_class', $field);
        $goods_data = [];
        foreach ($goods_list as $key => $goods) {
            $goods['goods_image'] = thumbGoods($goods['goods_image']);
            $goods['goods_price'] = FinalPrice::S($goods['goods_price']);
            unset($goods_list[$key]['goods_costprice']);
            $goods['goods_salenum'] = lastGoodsSale($goods);
            $goods['goods_type'] = GOODS_TYPE_MATERIAL_FLOWER;
            $goods['selected_num'] = 0;
            foreach ($cart_data as $cart) {
                if (isset($cart['goods_id']) && $cart['goods_id'] == $goods['goods_id']) {
                    $goods['selected_num'] = $cart['selected_num'];
                    break;
                }
            }
            unset($goods['goods_custom_salenum']);
            $goods_data[$goods['gc_id']]['gc_id'] = $goods['gc_id'];
            $goods_data[$goods['gc_id']]['gc_name'] = $goods['gc_name'];
            $goods_data[$goods['gc_id']]['goods_list'][] = $goods;
            //抽出热门推荐并保留拥有商品
            if ($goods['goods_commend']) {
                $goods_data['0']['gc_id'] = '0';
                $goods_data['0']['gc_name'] = '热门推荐';
                $goods_data['0']['goods_list'][] = $goods;
            }
        }
        ksort($goods_data);
        return array_values($goods_data);
    }

    /**
     * 获取购物车信息
     * @param bool $return_map 返回购物车数据供内部调用
     * @return mixed
     */
    public function actionTrade($return_map = false)
    {
        $map = [];
        if ($this->isLogin()) {
            $map['buyer_id'] = $this->member_id;
        } else {
            $map['ssid'] = $this->sessionid;
        }
        $map['goods_type'] = GOODS_TYPE_MATERIAL_FLOWER;
        $cart_list = Cart::instance()->getCartList($map);
        //购物车中的套餐
        $bl_ids = [];
        //以组分类
        $group_cart = [];
        foreach ($cart_list as $cart) {
            $group_cart[$cart['bl_id']][] = $cart;
            if ($cart['bl_id']) {
                array_push($bl_ids, $cart['bl_id']);
            }
        }
        //查找所有套餐信息
        $bl_all_list = $bl_all_goods_list = [];
        if ($bl_ids) {
            $bl_all_list = PBundling::instance()->getBundlingList(['bl_id' => $bl_ids, 'bl_state' => 1, 'is_delete' => 0], 'bl_id,bl_name,bl_discount_price');
            $bl_all_goods_list = PBundlingGoods::instance()->getBundlingGoodsList(['bl_id' => $bl_ids, 'is_delete' => 0], 'bl_id,goods_id,goods_num,bl_goods_price');
        }
        //单行信息
        $cart_data = [];
        foreach ($group_cart as $bl_id => $group) {
            if ($bl_id) {
                //获取套餐产品
                $bl_goods_list = [];
                foreach ($bl_all_goods_list as $bl_goods) {
                    if ($bl_goods['bl_id'] == $bl_id) {
                        $bl_goods_list[] = $bl_goods;
                    }
                }
                $bl_info = [];
                foreach ($bl_all_list as $bl_item) {
                    if ($bl_id == $bl_item['bl_id']) {
                        $bl_info = $bl_item;
                        break;
                    }
                }
                if(empty($bl_goods_list) || empty($bl_info)){
                    //套餐过期
                    continue;
                }
                //套餐汇总
                $bl_data = [];
                $bl_data['bl_id'] = $bl_id;
                $bl_data['name'] = $bl_info['bl_name'];
                $bl_data['price'] = priceFormat($bl_info['bl_discount_price'] * $group[0]['goods_num']);
                $bl_data['selected_num'] = $group[0]['goods_num'];
                //原价 = 每个商品的原价 * 套餐商品的数量  * 购物车下单的数量
                $bl_data['org_price'] = priceFormat(array_sum(array_map(function ($val1, $val2) {
                        if ($val1['goods_id'] == $val2['goods_id']) {
                            return ($val1['goods_price'] * $val2['goods_num']);
                        }
                        return 0;
                    }, $group, $bl_goods_list)) * $bl_data['num']);
                array_push($cart_data, $bl_data);
            } else {
                //单件汇总
                foreach ($group as $cart) {
                    array_push($cart_data, [
                        'goods_id' => $cart['goods_id'],
                        'name' => $cart['goods_name'],
                        'price' => priceFormat($cart['cart_price'] * $cart['goods_num']),
                        'selected_num' => $cart['goods_num'],
                        'org_price' => priceFormat($cart['goods_price'] * $cart['goods_num']),
                    ]);
                }
            }
        }
        if ($return_map) {
            return $cart_data;
        }
        /**
         * 总价格/总数量/总原价
         */
        $cart_price = $cart_num = $cart_org_price = 0;
        foreach ($cart_data as $cart) {
            $cart_price += $cart['price'];
            $cart_num += $cart['selected_num'];
            $cart_org_price += $cart['org_price'];
        }

        $agent_fee_tip = "";

        //查找运费
        if (FLOWER_MATERIAL_DELIVERY_FREE && FLOWER_MATERIAL_DELIVERY_FEE > 0) {
            //$cart_price += FLOWER_MATERIAL_DELIVERY_FEE;
            $agent_fee_tip = sprintf("另需配送费%s元", FLOWER_MATERIAL_DELIVERY_FEE);
        }

        //查找满减
        if (FLOWER_MATERIAL_DELIVERY_FREE > 0) {
            if (FLOWER_MATERIAL_DELIVERY_FEE > 0) {
                $agent_fee_tip = sprintf("另需配送费%s元，满%s包邮", FLOWER_MATERIAL_DELIVERY_FEE, FLOWER_MATERIAL_DELIVERY_FREE_LINE);
            } else {
                $agent_fee_tip = sprintf("满%s包邮", FLOWER_MATERIAL_DELIVERY_FREE_LINE);
            }
            //满减达标
            if ($cart_price >= FLOWER_MATERIAL_DELIVERY_FREE_LINE) {
                $agent_fee_tip = sprintf("已满%s元，免配送费", FLOWER_MATERIAL_DELIVERY_FREE_LINE);
                //$cart_price = $cart_price - FLOWER_MATERIAL_DELIVERY_FEE;
            }
        }

        //是否满足起送条件
        $limit_map = $cart_price >= FLOWER_MATERIAL_PRICE_LIMIT ? 1 : 0;

        $data = [];
        $data['alert_msg'] = "";
        $data['show_toast'] = "";
        $data['broadcast'] = [];
        $data['cart'] = [
            'agent_fee_tip' => $agent_fee_tip,
            'deliver_amount' => priceFormat($cart_org_price),
            'discount_amount' => priceFormat($cart_org_price - $cart_price),
            'total_price' => priceFormat($cart_price),
            'total_num' => $cart_num,
            'map' => $cart_data,
        ];
        $data['checkout_button'] = [
            'action' => '',
            'is_click' => $limit_map && count($cart_data) > 0 ? 1 : 0,
            'text' => $limit_map ? '去结算' : sprintf('¥%s起送', FLOWER_MATERIAL_PRICE_LIMIT)
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

}
