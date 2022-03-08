<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Message;
use common\models\Address;
use common\models\Area;
use common\models\Card;
use common\models\Cart;
use common\models\Goods;
use common\models\GroupShoppingGoods;
use common\models\GroupShoppingMember;
use common\models\GroupShoppingTeam;
use common\models\HuadiYearCardOrders;
use common\models\OrderPay;
use common\models\Orders;
use common\models\Payment;
use common\models\PXianshiGoods;
use common\models\Setting;
use common\models\Voucher;
use common\models\VoucherTemplate;
use yii\web\HttpException;

/**
 * PreorderController
 */
class PreorderController extends BaseController
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
     * 通过购物车来确认订单
     * @return array
     */
    public function actionCheckout()
    {
        $param = \Yii::$app->request->post();
        //获取商品数据
        $cart_id = $param['cart_id'];
        $date = isset($param['date']) ? $param['date'] : date('Y-m-d');
        $is_group_shopping = isset($param['is_group_shopping'])&&$param['is_group_shopping']==1 ? true: false;//是否是拼团购
        $team_id = isset($param['team_id']) ? (int)$param['team_id'] : 0;//如果传入值，则是加入某个拼团
        if (!$cart_id) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        $cart_model = new Cart();
        /*
         * $map = ['AND',
            ['=','cart_id',$cart_id],
            ['OR',
                ['=','buyer_id',$this->member_id],
                ['=','ssid',$this->sessionid]
            ]
        ];*/

        //处理登录之后购物车数据未及时更新到会员身份
        $yearCardPrice=[];
        $cart_model->mergeCartBySession($this->sessionid,$this->member_id);
        $map = [];
        $map['buyer_id'] = $this->member_id;
        $map['cart_id'] = $cart_id;
        $cart_data = $cart_model->getOrderCart($map,$yearCardPrice, $date);
        //如果是年卡用户  要判断是否是年卡专享商品
        $year_card_member = 0;
        if($this->member_id){
            $model_huadi_year_card_orders = new HuadiYearCardOrders();
            $year_card_member = $model_huadi_year_card_orders->getYearCardStateById($this->member_id);
        }
        $config = Setting::instance()->getAll();
        //年卡专享价商品
        $year_card_id_arr = $config['huadi_year_card'] ? unserialize($config['huadi_year_card']) : '';
        $year_card_goods_ids = [];
        if($year_card_id_arr && !empty($year_card_id_arr)){
            $year_card_goods_ids = array_keys($year_card_id_arr);
        }
        //限时购个人是否可以购买检查
        if(SITEID == 258){
            foreach ($cart_data['cart_list'] as $k => $cart){
                $cart_info = Cart::find()->where(['cart_id'=>$cart['cart_id']])->select("goods_id, goods_num")->asArray()->one();
                $checkResult = (new PXianshiGoods())->checkXianshi($this->member_id,$cart_info['goods_id'],$cart_info['goods_num']);
                if($checkResult['code'] != 1){
                    return $this->responseJson($checkResult['code'], $checkResult['msg']);
                }
                $checkXianshiResult = (new PXianshiGoods())->getValidXianshiGoodsInfoByGoodsID($cart_info['goods_id']);
                if($checkXianshiResult){
                    $cart_data['cart_list'][$k]['is_xianshi'] = 1;
                }else{
                    $cart_data['cart_list'][$k]['is_xianshi'] = 0;
                }
            }
        }
        //检查购物车商品
        $year_card_total = 0;
        foreach($cart_data['cart_list'] as &$cart_goods){
            $cart_goods['title'] = Orders::replace_title($cart_goods['title']);
            $merch_flag = false;
            $merch_price = 0;
            if($cart_goods['merch_bill']){
                foreach($cart_goods['merch_bill'] as &$merch){
                    //年卡商品标签判断
                    if(in_array($merch['goods_id'],$year_card_goods_ids)){
                         $merch['is_year_card_goods'] = 1;
                         //单个商品的年卡单价
                         $merch['year_card_goods_price'] = $yearCardPrice[$merch['goods_id']];
//                         $merch['year_card_goods_price'] = FinalPrice::yearCardMatchRate($year_card_id_arr[$merch['goods_id']],$merch['goods_price']);
                         $merch_flag = true;
                         //单个购物车的年卡汇总价
                         $merch_price += $merch['year_card_goods_price']*$merch['goods_num'];
                    }else{
                        //不是年卡商品
                        $merch_price += $cart_goods['is_holiday'] ? $merch['cart_price_festival']*$merch['goods_num']: $merch['cart_price']*$merch['goods_num'] ;
                        $merch['is_year_card_goods'] = 0;
                        $merch['year_card_goods_price'] = $cart_goods['is_holiday'] ? $merch['cart_price_festival'] : $merch['cart_price'];
                    }

                    //是否年卡专享商品
                    if (($year_card_member||(!$year_card_member && $param['yearCardConfirm']==1) )&& in_array($merch['goods_id'], $year_card_goods_ids)) {
                        $cart_data['calc']['cart_price'] -= priceFormat(($merch['cart_price']-$year_card_id_arr[$merch['goods_id']])*$merch['goods_num']);
                    }

                }
                $cart_goods['is_year_card_goods'] = $merch_flag ? 1 : 0;
                $cart_goods['year_card_goods_total'] = $merch_price;
                $year_card_total += $merch_price;
            }else{
                //年卡商品标签判断
                if(in_array($cart_goods['goods_id'],$year_card_goods_ids)){
                    $cart_goods['is_year_card_goods'] = 1;
//                    $cart_goods['year_card_goods_price'] = FinalPrice::yearCardMatchRate($year_card_id_arr[$cart_goods['goods_id']],$cart_goods['goods_price']);
                    $cart_goods['year_card_goods_price'] = $yearCardPrice[$cart_goods['goods_id']];
//
                }else{
                    $cart_goods['is_year_card_goods'] = 0;
                    $cart_goods['year_card_goods_price'] = $cart_goods['is_holiday'] ? $cart_goods['cart_price_festival'] : $cart_goods['cart_price'];;
                }

                $cart_goods['year_card_goods_total'] = $cart_goods['year_card_goods_price'] * $cart_goods['goods_num'];
                $year_card_total += $cart_goods['year_card_goods_total'];

                //是否年卡专享商品
                if (($year_card_member||(!$year_card_member && $param['yearCardConfirm']==1) )&& in_array($cart_goods['goods_id'], $year_card_goods_ids)) {
                    $cart_data['calc']['cart_price'] -= priceFormat(($cart_goods['cart_price']-FinalPrice::yearCardMatchRate($year_card_id_arr[$cart_goods['goods_id']],$yearCardPrice[$cart_goods['goods_id']]))*$cart_goods['goods_num'],$date);
                }
            }
        }

        //是拼团购买
        if($is_group_shopping && SITEID==258){
            //普通花和花材cart_id来源不一样
            $cart_id = !empty($cart_data['cart_list'][0]['cart_id']) ? $cart_data['cart_list'][0]['cart_id'] : $cart_data['cart_list'][0]['merch_bill'][0]['cart_id'];
            $cart_info = Cart::find()->where(['cart_id'=>$cart_id])->select("goods_id, goods_num")->asArray()->one();
            $goods_id = $cart_info['goods_id'];
            $goods_num = $cart_info['goods_num'];
            $checkResult = (new GroupShoppingTeam())->checkGroup($team_id,$goods_id,$this->member_id,$goods_num);
            if($checkResult['code'] != 1){
                return $this->responseJson($checkResult['code'], $checkResult['msg']);
            }
            if(!empty($cart_data['cart_list'][0]['cart_id'])){
                $cart_data['cart_list'][0]['goods_price'] = FinalPrice::yearCardMatchRate($checkResult['group_price'],$checkResult['group_price'],2, $date);
                $cart_data['cart_list'][0]['cart_price'] = FinalPrice::yearCardMatchRate($checkResult['group_price'],$checkResult['group_price'],2, $date);
                $cart_data['cart_list'][0]['subtotal'] = FinalPrice::yearCardMatchRate($checkResult['group_price'],$checkResult['group_price'],2, $date);
                $cart_data['cart_list'][0]['subtotal_festival'] = FinalPrice::yearCardMatchRate($checkResult['group_price'],$checkResult['group_price'],2, $date);
            }else{
                $cart_data['cart_list'][0]['merch_bill'][0]['goods_price'] = FinalPrice::yearCardMatchRate($checkResult['group_price'],$checkResult['group_price'],2, $date);
                $cart_data['cart_list'][0]['subtotal'] = FinalPrice::yearCardMatchRate($checkResult['group_price'],$checkResult['group_price'],2, $date);
            }
            $cart_data['calc']['cart_price'] = $goods_num * FinalPrice::yearCardMatchRate($checkResult['group_price'],$checkResult['group_price'],2, $date);
            //修改此次购买不在购物车显示
            Cart::updateAll(['cart_show' => 0], ['cart_id'=> $cart_id]);
        }
        $cart_data['yearCardConfirm'] = $param['yearCardConfirm'];

        return $this->_checkConfirm($cart_data);
    }

    //花材确认订单
    public function actionMaterial()
    {
        $cart_model = new Cart();
        $map = [];
        $map['buyer_id'] = $this->member_id;
        $map['goods_type'] = GOODS_TYPE_MATERIAL_FLOWER;
        $cart_data = $cart_model->getOrderCart($map);
        return $this->_checkConfirm($cart_data);
    }

    //套餐确认订单
    public function actionPack()
    {
        $param = \Yii::$app->request->post();
        //获取商品数据
        $hash_id = $param['hash_id'];
        $cart_data['yearCardConfirm'] = $param['yearCardConfirm'];

        // todo 测试
        //$hash_id = '1fd0e69fe7ab929d512fbc809def83cb';
        //$param['hash_id_jg_zs'] = 'ee09fd199d32b991abc3378ddeab9254';

        // 包月花加购赠送
        $hash_id_jg_zs = isset($param['hash_id_jg_zs']) ? $param['hash_id_jg_zs'] : 0;
        if (!$hash_id) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        $hash_ids[] = $hash_id;
        if ($hash_id_jg_zs) {
            $hash_ids[] = $hash_id_jg_zs;
        }

        $cart_model = new Cart();
        $map = [];
        $map['buyer_id'] = $this->member_id;
        $map['hash_id'] = $hash_ids;
        $cart_data = $cart_model->getOrderCart($map);

        $cart_data['yearCardConfirm'] = $param['yearCardConfirm'];
        return $this->_checkConfirm($cart_data);
    }

    private function _checkConfirm($cart_data)
    {
        $param = \Yii::$app->request->post();
        $data = [];
        //获取默认或指定地址信息
        $address_model = new Address();
        $data['delivery_receiver'] = $address_model->getDefaultAddressByUid($this->member_id, isset($param['address_id']) ? $param['address_id'] : 0);

        //是否是送给本人
        $data['delivery_buyer'] = [
            'is_self' => 1,
            'buyer_name' => '',
            'buyer_mobile' => '',
        ];
        //购物车数据
        $data['cart_map'] = $cart_data['cart_list'];
        //加价购优惠券
        $data['jjg_voucher'] = [
            'active' => false,
            'selected' => false,
            'label'=>'领520红包',
            'title'=>'加1元，领50元优惠券',
            'subtitle'=>'520表白日满199可用',
            'money'=>'￥1.00',
            'increase'=>1.00,
        ];

        //周五会员日
        if(Cart::instance()->goodsExist($data['cart_map'],Goods::FLOWER_GIFT)){
            $data['jjg_voucher'] = [
                'active' => true,
                'selected' => false,
                'label'=>'甜蜜组合',
                'title'=>'加77元购德芙98克心形巧克力',
                'subtitle'=>'花+巧克力，让爱更甜蜜',
                'money'=>'￥77.00',
                'increase'=>77.00,
            ];
        }
        //商品金额
        $goods_amount = $cart_data['calc']['cart_price'];

        //商品运费
        $delivery_fee = $cart_data['calc']['delivery_fee'];

        //地区运费
        $delivery_base = Address::instance()->getOrderDeliveryFee($data['delivery_receiver'], $cart_data['cart_list']);

        //折扣金额
        $discount_price = 0;

        //获取优惠券
        $voucher_model = new Voucher();

        // 如果有加价购的商品，则是否有合适的优惠券，需要减去加价购的商品价格
        $tmp_goods_amount = $goods_amount;
        foreach ($data['cart_map'] as $k => $v) {
            if ($v['cart_type'] == GOODS_TYPE_JIA_GOU_ZENG_SONG) {
                $tmp_goods_amount = $tmp_goods_amount - $v['subtotal'];
            }
        }
        $available_data = $voucher_model->getActiveList($this->member_id, $tmp_goods_amount, $data['cart_map']);
        $select_item = $available_data['active']['list'] ? $available_data['active']['list'][0] : [];

        $yearUser = HuadiYearCardOrders::instance()->getYearCardStateById($this->member_id);
        $data['yearCard']['yearUserStatus'] = $yearUser == false ? 0 : 1;
        $data['yearCard']['yearCardConfirm'] = 0;

        if ($yearUser == false) {
            //不是年卡用户
            $voucher_template_ids = array_merge(
                HuadiYearCardOrders::instance()->voucher_template_ids,
                HuadiYearCardOrders::instance()->month_voucher_template_ids
            );
            $where = ['in', 'voucher_t_id', $voucher_template_ids];
            $result = $yearCardAll = VoucherTemplate::instance()
                ->getVoucherTemplateList($where);


            //$cart_map
            try {
                $data['cart_map'] = Cart::instance()->getCartForOrder($data['cart_map']);
            } catch (\Exception $e) {
                $data['cart_map'] = array();
            }
            //检查是否包含19年感恩节限时购商品
            //检查商品是否是快递商品
            $is_xianshi = false;
            foreach ($data['cart_map'] as &$item) {
                if(isset($item['data']) && is_array($item['data'])){
                    foreach ($item['data'] as &$datum) {
                        $datum['is_delivery_goods'] = $datum['goods_info']['huawa_store_id'] ? 1 : 0;
                        $check = PXianshiGoods::instance()->getXianshiGoodsInfoByGoodsID($datum['goods_id']);
                        if ($check) {
                            $is_xianshi = true;
                        }
                    }
                }
            }

            //去除不符合条件的年卡券模板
            foreach ($result as $k => $v) {
                /**
                 * 1.无门槛
                 * 2.订单金额 >= 满减限制金额
                 * 3.优惠金额 < 订单金额 (负数)
                 * 4.其他情况暂不考虑(店铺优惠券、类别优惠券..)
                 */
                if (($v['voucher_t_limit'] == 0 || $goods_amount >= $v['voucher_t_limit']) && $v['voucher_t_price'] < $goods_amount) {
                    //1.特殊产品不能使用
                    //2.订单类型(微花店)不能使用
                    //19年包含感恩节限时购活动中的商品时不能使用
                    //特价商品不能使用优惠券
                    if (
                        Voucher::matchUnableVoucher($data['cart_map']) ||
                        Voucher::matchUnableOrder($data['cart_map']) ||
                        Voucher::matchUnableRestrict($v['voucher_t_id'], $data['cart_map'], $this->member_id) ||
                        $is_xianshi
                    ) {
                        unset($result[$k]);
                    }
                } else {
                    unset($result[$k]);
                }
            }
            //以最高优惠排序
            $available_list = arraySort($result, 'voucher_t_price', SORT_DESC);
            $yearCardAll = arraySort($yearCardAll, 'voucher_t_price', SORT_DESC);
            $data['yearCard']['msg'] = '开通省钱年卡 ';
            if ($select_item) {
                if ($select_item['voucher_price'] >= $available_list[0]['voucher_t_price']) {
                    //优惠券抵扣金额 >= 年卡抵扣金额
                    $data['yearCard']['msg'] .= '开卡即送价值70元无门槛券';
                } else {
                    //优惠券抵扣金额 < 年卡抵扣金额
                    $data['yearCard']['msg'] .= '本单立减' . $available_list[0]['voucher_t_price'] . '元';
                }
            } else {
                if ($available_list){
                    $data['yearCard']['msg'] .= '本单立减' . $available_list[0]['voucher_t_price'] . '元';
                }else{
                    $data['yearCard']['msg'] .= '开卡即送价值70元无门槛券';
                }
            }
            $data['yearCard']['year_card_marker_price'] = HuadiYearCardOrders::YEAR_CARD_MARKET_PRICE;
            $data['yearCard']['year_card_price'] = HuadiYearCardOrders::YEAR_CARD_PRICE;
            $data['yearCard']['adv_msg'] = '开卡预计每年节省3000+元';

            //选中年卡
            if (!empty($cart_data['yearCardConfirm'])) {
                foreach ($yearCardAll as $k => $v) {
                    $arr = [];
                    $arr['voucher_id'] = -1;
                    $arr['voucher_t_id'] = $v['voucher_t_id'];
                    $arr['voucher_code'] = $v['voucher_t_id'] . '@yearCard';
                    $arr['voucher_price'] = $v['voucher_t_price'];
                    $arr['voucher_limit'] = $v['voucher_t_limit'];
                    $arr['voucher_url_type'] = $v['voucher_url_type'];
                    $arr['voucher_match'] = $arr['voucher_limit'] > 0 ?
                        sprintf('满%s使用', (int)$arr['voucher_limit']) :
                        '无门槛';
                    $arr['voucher_url'] = Voucher::setVoucherUrl($v);
                    $arr['voucher_desc'] = $v['voucher_t_desc'];
                    $arr['voucher_title'] = $v['voucher_t_title'];
                    $arr = array_merge($arr, Voucher::voucherInfoHandle($arr));
                    $v['voucher_start_date'] = TIMESTAMP;
                    $v['voucher_end_date'] = TIMESTAMP + 31536000;
                    $arr['voucher_date_string'] = sprintf(
                        '%s至%s',
                        date('Y-m-d', $v['voucher_start_date']),
                        date('Y-m-d', $v['voucher_end_date'])
                    );
                    $v['voucher_state'] = 1;
                    $arr['voucher_tag'] = Voucher::instance()->getVoucherTag($v);
                    $arr['is_select'] = 0;
                    if (in_array($v['voucher_t_id'], array_column($result, 'voucher_t_id'))) {
                        //可用
                        $available_data['active']['count']++;
                        $available_data['active']['list'][] = $arr;
                    } else {
                        //不可用
                        $available_data['disabled']['count']++;
                        $available_data['disabled']['list'][] = $arr;
                    }
                }
            }
            $available_data['active']['list'] = arraySort($available_data['active']['list'], 'voucher_price', SORT_DESC);
            $select_item=$available_data['active']['list'][0];
        }
        if ($select_item) {
            $discount_price = $select_item['voucher_price'];
            $available_data['active']['list'][0]['is_select'] = 1;
        }

        $data['voucher'] = [
            'status' => $select_item ? 1 : 0,
            'status_text' => $select_item ? $select_item['voucher_title'] : '暂无可用',
            'select_item' => $select_item,
            'available' => $available_data['active'],
            'unavailable' => $available_data['disabled'],
        ];
        //计算总额 总价 = (商品总价+配送费)-折扣
        $price = [];
        $price['goods'] = priceFormat($goods_amount);
        $price['delivery_base'] = priceFormat($delivery_base);
        if(isset($cart_data['cart_list'][0]['merch_bill']) && !empty($cart_data['cart_list'][0]['merch_bill'])){
            $count = count($cart_data['cart_list'][0]['merch_bill']);
            $delivery_base = $delivery_base * $count;
        }
        $price['delivery'] = priceFormat($delivery_fee + $delivery_base);
        $price['yearCard'] = empty($cart_data['yearCardConfirm'])?0:HuadiYearCardOrders::YEAR_CARD_PRICE;
        $price['timer'] = priceFormat(TIMING_DELIVERY_FEE);
        $price['discount'] = priceFormat($discount_price);
        $price['jjg_voucher'] = priceFormat($data['jjg_voucher']['increase']);
        $price['total'] = priceFormat(($goods_amount + $delivery_fee + $delivery_base + $price['yearCard']) - $discount_price);
        $data['price'] = $price;

        //贺卡留言
        $data['wish_cards'] = '';
        //订单备注
        $data['order_message'] = '';
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取推荐贺卡
     * @return mixed
     */
    public function actionRecommendCards()
    {
        $data = [];
        $model_card = new Card();
        $data['card_data'] = $model_card->getCardAll();
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取配送时段
     * @return mixed
     */
    public function actionPeriod()
    {
        if (true) {
            $data = [];
            //提前预定时间
            $data['ahead_time'] = FLOWER_AHEAD_TIME;
            //时段
            //节日时段临时添加功能
            if(in_array(date('Y-m-d'),['2020-05-21'])){
                $period_list = [
                    ['value' => 1, 'text' => '全天配送', 'expire' => getExpire(1)],
                    ['value' => 15, 'text' => '上午', 'expire' => getExpire(15)],
                    ['value' => 16, 'text' => '下午', 'expire' => getExpire(16)],
                    ['value' => 17, 'text' => '晚上', 'expire' => getExpire(17)]
                ];
            }else{
                $period = getPeriod();
                $period_list = [];
                foreach ($period as $k => $each) {
                    if ($k == 0) continue;
                    array_push($period_list, ['value' => $k, 'text' => $each, 'expire' => getExpire($k)]);
                }
            }
            $data['period'] = $period_list;
            //定时配送
            $hour = [];
            for ($i = 9; $i < 22; $i++) {
                $value = str_pad($i, 2, "0", STR_PAD_LEFT);
                array_push($hour, [
                    'value' => $value,
                    'text' => $value,
                ]);
            }
            $minutes = [];
            for ($i = 0; $i < 60; $i += 5) {
                $value = str_pad($i, 2, "0", STR_PAD_LEFT);
                array_push($minutes, [
                    'value' => $value,
                    'text' => $value,
                ]);
            }
            $data['timer'] = ['hour' => $hour, 'minutes' => $minutes];
        }

        $holiday_date = unserialize(HOLIDAY_TIME);

        //是否可以选择定时 1/0
        $data['is_timer'] = HOLIDAY_OPEN == 1 && date('Y-m-d') == end($holiday_date) ? 0 : 1;
        $data['timer_tips'] = sprintf('定时费用为%s元,定时服务允许误差时间为±%s分钟', TIMING_DELIVERY_FEE, 30);

        //节日时段控制选择
        $festival = [];
        $festival['open'] = HOLIDAY_OPEN == 1 ? 1 : 0;
        $festival['days'] = [];
        foreach ($holiday_date as $k => $date) {
            array_push($festival['days'], [
                'date' => trim($date),
                'text' => $k == count($holiday_date) - 1 ? HOLIDAY_NAME : '高峰期',
            ]);
        }
        //节日当天只允许当天配送
        $festival['allow_period'] = date('Y-m-d') == end($holiday_date) ? [1] : [1, 15, 16, 17];
//        $festival['allow_period'] = [1];
        $data['festival'] = $festival;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取配送时段-new
     * @return mixed
     */
    public function actionPeriodNew()
    {
        $param = \Yii::$app->request->post();
        $select_date = isset($param['select_date']) ? $param['select_date'] : date('Y-m-d');
        $today_date = date('Y-m-d');
        $now_time = strtotime($today_date);
        $select_time = strtotime($select_date);
        $select_time = $select_time >= $now_time ? $select_time : $now_time;
        $holiday_date = unserialize(HOLIDAY_TIME);
        if(in_array($select_date,$holiday_date)){
            if($select_date == end($holiday_date)){
                $period_list = [
                    ['value' => 1, 'text' => '全天配送', 'expire' => getExpire(1)],
//                    ['value' => 15, 'text' => '上午', 'expire' => getExpire(15)],
//                    ['value' => 16, 'text' => '下午', 'expire' => getExpire(16)],
//                    ['value' => 17, 'text' => '晚上', 'expire' => getExpire(17)]
                ];
            }else{
                if($select_time == $now_time){
                    $hour = intval(date("H"));
                    if( $hour < 12 ){
                        $period_list = [
                            ['value' => 1, 'text' => '全天配送', 'expire' => getExpire(1)],
                            ['value' => 15, 'text' => '上午', 'expire' => getExpire(15)],
                            ['value' => 16, 'text' => '下午', 'expire' => getExpire(16)],
                            ['value' => 17, 'text' => '晚上', 'expire' => getExpire(17)]
                        ];
                    }elseif($hour >= 12 && $hour < 18){
                        $period_list = [
                            ['value' => 1, 'text' => '全天配送', 'expire' => getExpire(1)],
                            ['value' => 16, 'text' => '下午', 'expire' => getExpire(16)],
                            ['value' => 17, 'text' => '晚上', 'expire' => getExpire(17)]
                        ];
                    }else{
                        $period_list = [
                            ['value' => 1, 'text' => '全天配送', 'expire' => getExpire(1)],
                            ['value' => 17, 'text' => '晚上', 'expire' => getExpire(17)]
                        ];
                    }
                }else{
                    $period_list = [
                        ['value' => 1, 'text' => '全天配送', 'expire' => getExpire(1)],
                        ['value' => 15, 'text' => '上午', 'expire' => getExpire(15)],
                        ['value' => 16, 'text' => '下午', 'expire' => getExpire(16)],
                        ['value' => 17, 'text' => '晚上', 'expire' => getExpire(17)]
                    ];
                }
            }
        }else{
            $period_list = [
                ['value' => 20, 'text' => '立即送', 'expire' => getExpire(20)],//0
                ['value' => 1, 'text' => '全天配送', 'expire' => getExpire(1)],//1
                ['value' => 15, 'text' => '上午', 'expire' => getExpire(15)],//2
                ['value' => 16, 'text' => '下午', 'expire' => getExpire(16)],//3
                ['value' => 17, 'text' => '晚上', 'expire' => getExpire(17)],//4
                ['value' => 2, 'text' => '08-10点', 'expire' => getExpire(2)],//5
                ['value' => 3, 'text' => '10-12点', 'expire' => getExpire(3)],//6
                ['value' => 4, 'text' => '12-14点', 'expire' => getExpire(4)],//7
                ['value' => 5, 'text' => '14-16点', 'expire' => getExpire(5)],//8
                ['value' => 6, 'text' => '16-18点', 'expire' => getExpire(6)],//9
                ['value' => 7, 'text' => '18-20点', 'expire' => getExpire(7)],//10
                ['value' => 8, 'text' => '20-22点', 'expire' => getExpire(8)],//11
            ];
            if($select_time == $now_time){
                $hour = intval(date("H"));
                //12点之后不展示上午,18点之后不展示下午,20点之后不展示晚上和立即送
                switch ($hour){
                    case 10 :
                    case 11 :
                        unset($period_list[5]);
                        break;
                    case 12:
                    case 13:
                        unset($period_list[2]);
                        unset($period_list[5]);
                        unset($period_list[6]);
                        break;
                    case 14:
                    case 15:
                        unset($period_list[2]);
                        unset($period_list[5]);
                        unset($period_list[6]);
                        unset($period_list[7]);
                        break;
                    case 16:
                    case 17:
                        unset($period_list[2]);
                        unset($period_list[5]);
                        unset($period_list[6]);
                        unset($period_list[7]);
                        unset($period_list[8]);
                        break;
                    case 18:
                    case 19:
                        unset($period_list[2]);
                        unset($period_list[3]);
                        unset($period_list[5]);
                        unset($period_list[6]);
                        unset($period_list[7]);
                        unset($period_list[8]);
                        unset($period_list[9]);
                        break;
                    case 20:
                    case 21:
                        unset($period_list[0]);
                        unset($period_list[2]);
                        unset($period_list[3]);
                        unset($period_list[5]);
                        unset($period_list[6]);
                        unset($period_list[7]);
                        unset($period_list[8]);
                        unset($period_list[9]);
                        unset($period_list[10]);
                        break;
                    case 22:
                    case 23:
                        unset($period_list[0]);
                        unset($period_list[2]);
                        unset($period_list[3]);
                        unset($period_list[5]);
                        unset($period_list[6]);
                        unset($period_list[7]);
                        unset($period_list[8]);
                        unset($period_list[9]);
                        unset($period_list[10]);
                        unset($period_list[11]);
                        break;
                }
            }else{
                unset($period_list[0]);//未选择当天配送,删除掉立即配送的按钮
            }
        }

        $data['period_list'] = array_values($period_list);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取支付方式列表
     * @return mixed
     */
    public function actionPayment()
    {
        $data = [];
        $data['countdown'] = ORDER_COUNTDOWN;
        $data['pay_amount'] = priceFormat(999999);
        $data['order_id'] = [];
        $pay_sn = Request('pay_sn');
        $order_sn = Request('order_sn');
        $map = [];
        $map['buyer_id'] = $this->member_id;
        if ($pay_sn) {
            $map['pay_sn'] = $pay_sn;
            $pay_info = OrderPay::findOne($map);
            if ($pay_info && TIMESTAMP < $pay_info->add_time + ORDER_COUNTDOWN) {
                $data['countdown'] = ORDER_COUNTDOWN - (TIMESTAMP - $pay_info->add_time);
                $data['pay_amount'] = $pay_info['total_amount'];
            }
            if ($pay_info) {
                $order_list = Orders::find()->where(['pay_sn' => $pay_sn])->select('order_id')->asArray()->all();
                $data['order_id'] = array_column($order_list, 'order_id');
            }
        } else if ($order_sn) {
            $map['order_sn'] = $order_sn;
            $order_info = Orders::find()->select('order_id,add_time,order_amount')->where($map)->one();
            if ($order_info && TIMESTAMP < $order_info->add_time + ORDER_COUNTDOWN) {
                $data['countdown'] = ORDER_COUNTDOWN - (TIMESTAMP - $order_info->add_time);
                $data['pay_amount'] = $order_info->order_amount;
            }
            if ($order_info) {
                array_push($data['order_id'], $order_info->order_id);
            }
        }
        $payment = new Payment();
        $payment_list = $payment->getOnlineList();
        foreach ($payment_list as $key => $payment) {
            $payment_list[$key]['is_select'] = 0;
            $payment_list[$key]['is_recommend'] = 0;
            if ($payment['payment_code'] == 'alipay') {
                if (isWeixin()) {
                    //微信浏览器不打开
                    unset($payment_list[$key]);
                    continue;
                }
                $payment_list[$key]['is_select'] = 1;
                $payment_list[$key]['is_recommend'] = 1;
            }
            if ($payment['payment_code'] == 'wxpay') {
                $payment_list[$key]['is_select'] = 1;
                $payment_list[$key]['is_recommend'] = 1;
                if (isApp() && getIp() != '110.185.172.204') {
//                    //中信商户已被冻结
                    unset($payment_list[$key]);
                    continue;
                }
            }
        }
        $data['payment_list'] = array_values($payment_list);
        $data['pay_sn'] = $pay_sn ? $pay_sn : $order_sn;
        if(SITEID == 258){
            $order_px = '花递直卖订单-';
        }else{
            $order_px = '爱花居订单-';
        }
        $data['pay_name'] = $order_px . $data['pay_sn'];
        $data['api_url'] = API_URL;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }


}
