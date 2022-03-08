<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Log;
use common\components\Message;
use common\components\MicroApi;
use common\components\QrCodeClient;
use common\models\Address;
use common\models\Cart;
use common\models\Data;
use common\models\EvaluateGoods;
use common\models\Florist;
use common\models\Goods;
use common\models\GoodsAttrIndex;
use common\models\GoodsImageLabel;
use common\models\GoodsImages;
use common\models\GoodsRecord;
use common\models\GroupShopping;
use common\models\GroupShoppingFalseMember;
use common\models\GroupShoppingGoods;
use common\models\GroupShoppingMember;
use common\models\GroupShoppingTeam;
use common\models\HuadiYearCardOrders;
use common\models\Member;
use common\models\OrderGoods;
use common\models\Orders;
use common\models\PBundling;
use common\models\PBundlingGoods;
use common\models\PXianshiGoods;
use common\models\PXianshiMemberGoods;
use common\models\Setting;
use common\models\SpecialOffer;
use common\models\SpecialOfferGoods;
use frontend\service\SpecialOfferCommendService;
use linslin\yii2\curl\Curl;
use Think\Upload;
use Yii;
use yii\db\Expression;
use yii\db\Query;

/**
 * ProductFlower controller
 */
class ProductController extends BaseController
{
    // [礼品鲜花，家居花，花材，店铺产品]
    private $product_type = [
        GOODS_TYPE_FLOWER,
        GOODS_TYPE_HOME_FLOWER,
        GOODS_TYPE_MATERIAL_FLOWER,
        GOODS_TYPE_STORE_FLOWER,
    ];

    /**
     * 拼团列表
     */
    public function actionList()
    {
        $param = \Yii::$app->request->post();
        $pagesize = 10;
        $page = isset($param['page']) ? (int)$param['page'] : 1;
        $goods_id = (int)$param['goods_id'];
        $group = GroupShopping::getNewGroup($page, $pagesize,true);
        if(!empty($goods_id) && isset($group['goods'])) {
            foreach ($group['goods'] as $index => $value) {
                if($value['goods_id'] == $goods_id && $index != 0){
                    array_unshift($group['goods'], $group['goods'][$index]);
                    unset($group['goods'][$index + 1]);
                }
            }
            $group['goods'] = array_values($group['goods']);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $group);
    }

    /**
     * 商品详情
     * @param array $post
     * @return mixed
     */
    public function actionIndex($post = array())
    {
        $param = empty($post)?\Yii::$app->request->post():$post;
        $goods_id = (int)$param['goods_id'];
        $goods_type = (int)$param['goods_type'];
        $goods_type = in_array($goods_type, $this->product_type) ? $goods_type : $this->product_type[0];
        $product_data = $this->getProductData($goods_id, $goods_type);
        if (empty($product_data)) {
            return $this->responseJson(Message::EMPTY_CODE, '您要找的商品不存在~');
        }
        $product_data['service_num'] = '4006-221-019';
        if ($this->isLogin()) {
            //登陆了相关
            $orders = new Orders();
            $address_info = $orders->getLatestOrderAddress($this->member_id);
            /*
                11=》登录了没有写过收货地址
                13=》登录了并且购买过商品
             */
            if (count($address_info) && $address_info['reciver_info']) {
                $address = explode(',',$address_info['reciver_info']);
                if (count($address) >= 2) {
                    list($area,$add_detail) = explode(',',$address_info['reciver_info']);
                    $address_info['reciver_axis'] = getAxis($area,$add_detail);
                    $address_info['reciver_area'] = $area;
                    $address_info['reciver_info'] =  explode(',',$address_info['reciver_info'])[1];
                }
                $product_data['old_order_address'] = $address_info;
                $product_data['old_order_address']['reciver_status'] = 13;
            } else {
                //20200810没有订单地址取用户默认地址
                $member_default_address = Address::instance()->getDefaultAddressByUid($this->member_id);
                if($member_default_address){
                    $product_data['old_order_address'] = [
                        'reciver_province_id' => $member_default_address['province_id'],
                        'reciver_city_id' => $member_default_address['city_id'],
                        'reciver_area_id' => $member_default_address['area_id'],
                        'reciver_info' => $member_default_address['address'],
                        'reciver_axis' => getAxis($member_default_address['area_info'],$member_default_address['address']),
                        'reciver_area' =>$member_default_address['area_info'],
                        'reciver_status' => 13
                    ];
                }else{
                    $product_data['old_order_address'] = [
                        'reciver_province_id' => 0,
                        'reciver_city_id' => 0,
                        'reciver_area_id' => 0,
                        'reciver_info' => '',
                        'reciver_axis' => [],
                        'reciver_area' => '',
                        'reciver_status' => 11
                    ];
                }
            }
            //登录记录浏览足迹
            GoodsRecord::addGoodsRecord($product_data,$goods_type,$this->member_id);

        } else {
            //10=》未登录
            $product_data['old_order_address']['reciver_province_id'] = 0;
            $product_data['old_order_address']['reciver_city_id'] = 0;
            $product_data['old_order_address']['reciver_area_id'] = 0;
            $product_data['old_order_address']['reciver_info'] = '';
            $product_data['old_order_address']['reciver_axis'] = [];
            $product_data['old_order_address']['reciver_area'] = '';
            $product_data['old_order_address']['reciver_status'] = 10;
        }
        //热门推荐商品
        $store_id = !empty($product_data['goods_info']['store_id']) ? $product_data['goods_info']['store_id'] : 0;
        list($hot_list,$api_url) = $this->getHotList($store_id);
        $product_data['hot_list'] = $hot_list;
        $product_data['hot_api_url'] = $api_url;

        //19年感恩节限时购当未开始时，需要返回的值
        if(empty($product_data['goods_info']['is_xianshi'])){
            $futureXianshi = (new PXianshiGoods())->getValidXianshiGoodsInfoByGoodsID($goods_id);
            if($futureXianshi){
                $product_data['goods_info']['is_xianshi'] = 1;
                $product_data['goods_info']['xianshi_info'] = [
                    'xianshi_title' => $futureXianshi['xianshi_name'],
                    'lower_limit' => $futureXianshi['lower_limit'],
                    'upper_limit' => $futureXianshi['upper_limit'],
                    'now_sale' => 0,
                    'user_upper_limit' => $futureXianshi['user_upper_limit'],
                    'start_time' => $futureXianshi['start_time'],
                    'end_time' => $futureXianshi['end_time'],
                    'xianshi_goods_price' => floatval($futureXianshi['xianshi_price'])
                ];
            }
        }

        //拼团信息
        $group_shopping_goods_model = new GroupShoppingGoods();
        $check_group = $group_shopping_goods_model->getGoodsGroup([$goods_id]);
        $product_data['goods_info']['is_group_shopping'] = isset($check_group[0]['goods_id']) ? 1 : 0;
        if(isset($check_group[0]['goods_id'])){
            $product_data['goods_info']['group_shopping'] = [
                'start_time' => $check_group[0]['start_time'],
                'end_time' => $check_group[0]['end_time'],
                'group_price' =>  FinalPrice::yearCardMatchRate($check_group[0]['group_price'],$check_group[0]['group_price'],2),
                'need_people' => $check_group[0]['max_people']
            ];

            //所有团队
            $product_data['goods_info']['group_shopping_team'] = (new GroupShoppingTeam())->getTeamList($goods_id,5);
        }
        //格式化价格
        if($goods_type !== GOODS_TYPE_STORE_FLOWER){
            $product_data['goods_info']['goods_price'] = FinalPrice::format($product_data['goods_info']['goods_price']);
        }
        //年卡价格
        $product_data['year_card_price'] = HuadiYearCardOrders::YEAR_CARD_PRICE;
        $product_data['year_card_marketprice'] = HuadiYearCardOrders::YEAR_CARD_MARKET_PRICE;
        //增加后台时间戳字段
        $product_data['current_time'] = time();
        //获取商品所有属性
        $goods_attrs = GoodsAttrIndex::getGoodsAttrs($goods_id,'attr_value_id');
        $goods_attrs = array_column($goods_attrs,'attr_value_id');
        //判断商品是否属于蓝色妖姬
        $flag = false;
        if(!in_array($goods_type,[GOODS_TYPE_STORE_FLOWER])){
            if(in_array(31,$goods_attrs) || in_array(660,$goods_attrs)){
                $flag = true;
            }
        }
        if(strpos($product_data['goods_info']['goods_name'],'蓝色妖姬') !== false ||
           strpos($product_data['goods_info']['goods_material'],'蓝色妖姬') !== false){
            $flag = true;
        }
        $product_data['blue_bird_tips'] = $flag ? '蓝色妖姬是用一种对人体无害的染色剂和助染剂调合成的蓝色玫瑰，请放心购买！' : '';
        //香皂花,永生花商品属性判断
        $product_data['is_special_flower_goods'] = 0;
        if(in_array(789,$goods_attrs)){
            $product_data['is_special_flower_goods'] = 1;//香皂花
        }
        if(in_array(788,$goods_attrs)){
            $product_data['is_special_flower_goods'] = 2;//永生花
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $product_data);
    }
    /**
     * 根据商品id判断商品是否包含蓝色妖姬的规格属性
     * @return boolean
     */
    private function isBluebirdGoods($goods_id){
        return GoodsAttrIndex::find()
            ->where(['goods_id' => $goods_id, 'attr_value_id' => [31,660]])->scalar();
    }

    /**
     * 花递wap获取历史收货地址接口
     */
    public function actionOldOrderAddress()
    {
        if ($this->isLogin()) {
            //登陆了相关
            $orders = new Orders();
            $address_info = $orders->getLatestOrderAddress($this->member_id);
            /*
                11=》登录了没有写过收货地址
                13=》登录了并且购买过商品
             */
            if (count($address_info) && $address_info['reciver_info']) {
                $data['old_order_address'] = $address_info;
                $data['old_order_address']['reciver_status'] = 13;
            } else {
                $data['old_order_address'] = [
                    'reciver_province_id' => 0,
                    'reciver_city_id' => 0,
                    'reciver_area_id' => 0,
                    'reciver_info' => '',
                    'reciver_status' => 11
                ];
            }
        } else {
            //10=》未登录
            $data['old_order_address']['reciver_province_id'] = 0;
            $data['old_order_address']['reciver_city_id'] = 0;
            $data['old_order_address']['reciver_area_id'] = 0;
            $data['old_order_address']['reciver_info'] = '';
            $data['old_order_address']['reciver_status'] = 10;
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 更多拼团
     */
    public function actionMoreTeam()
    {
        $param = \Yii::$app->request->post();
        $goods_id = (int)$param['goods_id'];
        $team = (new GroupShoppingTeam())->getTeamList($goods_id);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $team);
    }

    /**
     * 获取拼团id
     */
    public function actionGetTeamId(){
        $param = \Yii::$app->request->post();
        $pay_sn = (int)$param['pay_sn'];
//        if($this->isLogin()){
            $order = Orders::find()->where(['pay_sn'=>$pay_sn])->select("order_state,group_shopping_team_id")->one();
//        }
        $team_id = !empty($order['group_shopping_team_id']) && isset($order['order_state']) && $order['order_state'] >=20  ? $order['group_shopping_team_id'] : 0;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['team_id'=>$team_id]);
    }

    /**
     * 拼团信息
     */
    public function actionTeamInfo()
    {
        $param = \Yii::$app->request->post();
        $team_id = (int)$param['team_id'];
        $team_info = GroupShoppingTeam::find()->where(['team_id'=>$team_id])->asArray()->one();

        $group_member_model = new GroupShoppingMember();
        $members = $group_member_model->getMember($team_id);
        $true_member_num = count($members);
        $team_state = Orders::find()->where(['group_shopping_team_id'=>$team_id])->select("group_shopping_state")->asArray()->one();
        $false_member = GroupShoppingFalseMember::getFalseMember($team_id,$team_info['add_people'],$team_state['group_shopping_state']);
        //20200803拼团假人+真人数量超过限制处理
        if(count($members) < $team_info['max_people']){
            $need_false_member = $team_info['max_people'] - count($members);
            if($false_member){
                for($i = 0; $i < $need_false_member; $i++){
                    if(isset($false_member[$i])){
                        $members[] = $false_member[$i];
                    }
                }
            }
        }
        $data['join_member'] = $members;//已经加入的用户
        $need_member = $team_info['max_people']-count($members);
        $data['group_team'] = [//当前拼团信息
            'max_people' => $team_info['max_people'],
            'add_people' => $team_info['add_people'],
            'need_people' => $need_member >= 0 ? $need_member : 0,
            'end_time' => $team_info['group_end_time']
        ];

        //当前拼团的商品信息
        $goods_info = Goods::find()->alias("goods")
            ->join("left join","hua123_group_shopping_team team","team.goods_id = goods.goods_id")
            ->where(['goods.goods_id'=>$team_info['goods_id']])
            ->select("goods.goods_id,goods.gc_id_2,goods.goods_name,goods.goods_image,goods.goods_material,goods.goods_price,sum(team.max_people) as sum_num")
            ->groupBy("goods.goods_id")
            ->asArray()->one();
        $goods_info['group_price'] = $team_info['group_price'];
        $goods_info['goods_image'] = thumbGoods($goods_info['goods_image'], 260);
        $goods_model = new \common\models\Goods();
        $goods_info['goods_type'] = $goods_model->getGoodsType($goods_info['gc_id_2']);
        unset($goods_info['gc_id_2']);
        $data['goods_info'] = $goods_info;

        //如果用户已经登陆，则确定当前用户订单id
        $data['order_id'] = 0;
        if($this->isLogin()){
            foreach ($members as &$member){
                if(isset($member['member_id']) && $member['member_id'] == $this->member_id){
                    $data['order_id'] = $member['order_id'];
                }
                unset($member['member_id'],$member['order_id']);
            }
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 花递小程序圣诞专题生活花、礼品花商品信息接口（商品固定）
     * @return mixed
     */
    public function actionFixedGoodsList()
    {
        $life_goods_id = [15400, 15396, 14877, 14878, 14888, 14870];
        $gift_goods_id = [15468, 15469, 15508, 15467, 15481, 15478, 15476, 15474];
        $field = "goods.goods_id,goods.goods_name,goods.goods_image,goods.goods_material,goods.gc_id,goods_class.gc_name,goods.goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
        $goods_model = new Goods();
        $where = ['in','goods.goods_id',$life_goods_id];
        $life_goods = $goods_model->goodslist($field,1,10,$where);
        $data['life_goods'] = [];
        foreach ($life_goods_id as $k=>$id){
            $data['life_goods'][$k] = [];
            foreach ($life_goods as $life_good){
                if($id == $life_good['goods_id']){
                    $life_good['goods_name_copy'] = $life_good['goods_name'];
                    $life_good['goods_name'] = str_replace("【圣诞节】","",$life_good['goods_name']);
                    $data['life_goods'][$k] = $life_good;
                }
            }
        }
        $where = ['in','goods.goods_id',$gift_goods_id];
        $gift_goods = $goods_model->goodslist($field,1,10,$where);
        $data['gift_goods'] = [];
        foreach ($gift_goods_id as $k=>$id){
            $data['gift_goods'][$k] = [];
            foreach ($gift_goods as $gift_good){
                if($id == $gift_good['goods_id']){
                    $gift_good['goods_name_copy'] = $gift_good['goods_name'];
                    $gift_good['goods_name'] = str_replace("【圣诞节】","",$gift_good['goods_name']);
                    $data['gift_goods'][$k] = $gift_good;
                }
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //获取收花地址的花店数据统计(花店指数)
    public function actionAreaIndex()
    {
        $post = \Yii::$app->request->post();
        $province_id = isset($post['province_id']) && intval($post['province_id']) > 0 ? intval($post['province_id']) : 0;
        $city_id = isset($post['city_id']) && intval($post['city_id']) > 0 ? intval($post['city_id']) : 0;
        $area_id = isset($post['area_id']) && intval($post['area_id']) > 0 ? intval($post['area_id']) : 0;
        $area_info = isset($post['area_info']) && trim($post['area_info']) ? trim($post['area_info']) : '';
        if (!$province_id && !$city_id) {
            //判断是否登录，已登录用户获取最后一次收货地址
            if ($this->isLogin() && $address = Address::instance()->getOneByUid($this->member_id, 'provice_id as province_id,city_id,area_id,area_info')) {
                $province_id = $address->province_id;
                $city_id = $address->city_id;
                $area_id = $address->area_id;
                $area_info = $address->area_info;
            } else {
                //没传地址也没登录也没收货地址就IP定位
                $ip_data = Address::getIpLocation();
                $province_id = $ip_data['province_id'];
                $city_id = $ip_data['city_id'];
                $area_id = $ip_data['area_id'];
                $area_info = $ip_data['area_info'];
            }
        }
        //请求花店指数
        $api = new MicroApi();
        $param = [];
        $param['province_id'] = $province_id;
        $param['city_id'] = $city_id;
        $param['area_id'] = $area_id;
        $cache_name = md5(serialize($param) . '2');

        if (!($result = cache($cache_name))) {
            $result = $api->httpRequest('/api/getLocationStore', $param);
            if ($result && isset($result['store'])) {
                cache($cache_name, $result, 86400);
            }
        }
        if (!$result || !isset($result['store'])) {
            //获取接口失败了
            $store = [
                'store_count' => 0,
                'seven_order_count' => 0,
                'threemonth_order_count' => 0,
                'sixmonth_order_count' => 0,
                'thirty_order_count' => 0,
                'new_order' => [],
            ];
        } else {
            $store = $result['store'];
            if (isset($store['new_order']) && $store['new_order']) {
                foreach ($store['new_order'] as $k => $val) {
                    $store['new_order'][$k]['add_time'] = getFriendlyTime($val['add_time']);
                    $store['new_order'][$k]['receive_address'] = address_format(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", '', $val['receive_address']));
                    $store['new_order'][$k]['order_status'] = huawaState2($val['order_state']);
                    if (isset($val['order_id'])) unset($store['new_order'][$k]['order_id']);
                    if (isset($val['area_info'])) unset($store['new_order'][$k]['area_info']);
                    if (isset($val['order_state'])) unset($store['new_order'][$k]['order_state']);
                }
            }
        }
        $data = [];
        $area_arr = explode(' ', $area_info);
        $data['area'] = $area_arr ? end($area_arr) : 'Unknown';
        $data['index_title'] = $data['area'] . '内花店指数';
        $data['order_title'] = $data['area'] . '最新10笔订单信息';
        $data['store'] = $store;
        $data['location'] = [
            'province_id' => $province_id,
            'city_id' => $city_id,
            'area_id' => $area_id,
            'area_info' => $area_info,
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 根据类型获取商品详情
     * @param $goods_id
     * @param $type
     * @return array
     */
    private function getProductData($goods_id, $type)
    {
        if ($type == GOODS_TYPE_STORE_FLOWER) {
            return $this->getStoreFlowerInfo($goods_id);
        } else {
            return $this->getFlowerInfo($goods_id, $type);
        }
    }

    /**
     * 获取自营鲜花详情
     * @param $goods_id
     * @return array
     */
    private function getFlowerInfo($goods_id, $type)
    {
        $model_goods = new Goods();
        $goods_data = $model_goods->getGoodsDetail($goods_id,$type);
        $goods_info = $goods_data['goods_info'];
        if (empty($goods_info)) {
            return null;
        }
        $type = $model_goods->getGoodsType($goods_info['gc_id_2']);
        $goods = [];
        $goods['goods_id'] = $goods_id;
        $goods['goods_type'] = $type;
        $goods['goods_tag'] = $model_goods->getGoodsTag($goods_info, $type);
        $goods['goods_name'] = $goods_info['goods_name'];
        $goods['goods_material'] = $goods_info['goods_material'];
        $goods['goods_jingle'] = $goods_info['goods_jingle'];
        $goods['goods_price'] = FinalPrice::S($goods_info['goods_price']);
        $goods['goods_marketprice'] = FinalPrice::M($goods_info['goods_price'],$goods_info['goods_marketprice']);
        $goods['goods_salenum'] = lastGoodsSale($goods_info);
        $goods['is_flower'] = $type == GOODS_TYPE_FLOWER ? 1 : 0;
        $goods['is_home_flower'] = $type == GOODS_TYPE_HOME_FLOWER ? 1 : 0;
        $goods['is_material_flower'] = $type == GOODS_TYPE_MATERIAL_FLOWER ? 1 : 0;
        $goods['is_store_flower'] = $type == GOODS_TYPE_STORE_FLOWER ? 1 : 0;
        $goods['is_other_flower'] = $type == GOODS_TYPE_OTHER ? 1 : 0;
        $goods['is_unit'] = in_array($goods_info['gc_id'], array(Goods::FLOWER_BASKET)) ? 1 : 0;
        $goods['desc_video'] = $goods_info['goods_video'];
        $goods['desc_video_img'] = $goods_info['goods_video_img'];
        $goods['has_desc_video'] = $goods_info['has_video'];
        $goods['unit'] = '个';
        $goods['is_delivery_goods'] = $goods_info['huawa_store_id'] ? 1 : 0;
        $config = Setting::instance()->getAll();
        $special_offer_goods_idstr = $config['huadi_special_offer'] ? $config['huadi_special_offer'] : '';
        $special_offer_goods_ids = [];
        if($special_offer_goods_idstr){
            $special_offer_goods_ids = explode(',',$special_offer_goods_idstr);
        }
        //是否特价商品
        if(in_array($goods_id,$special_offer_goods_ids)){
            $goods['is_special'] = 1;
        }else{
            $goods['is_special'] = 0;
        }

        //是否年卡专享商品
        $year_card_id_arr = $config['huadi_year_card'] ? unserialize($config['huadi_year_card']) : '';
        $year_card_goods_ids = [];
        if($year_card_id_arr && !empty($year_card_id_arr)){
            $year_card_goods_ids = array_keys($year_card_id_arr);
        }
        if(in_array($goods_id,$year_card_goods_ids)){
            $goods['year_card_price'] = FinalPrice::yearCardMatchRate($year_card_id_arr[$goods_id],$goods_info['goods_price'],2);
        }else{
            $goods['year_card_price'] = 0;
        }
        //已加入购物车的数量
        $map = [];
        if ($this->isLogin()) {
            $map['buyer_id'] = $this->member_id;
        } else {
            $map['ssid'] = $this->sessionid;
        }
        $map['goods_id'] = $goods_id;
        $map['goods_type'] = $type;
        $cart = Cart::find()->select('goods_num')->where($map)->one();
        $goods['selected_num'] = $cart ? (int)$cart->goods_num : 0;
        $goods['quantity'] = [
            'show_quantity' => $model_goods->getShowQuantity($type),
            'default_quantity' => 1
        ];
        $goods['goods_attr'] = [
            'show_attr' => $model_goods->getShowAttr($type),
            'attr_list' => $model_goods->getAttrList($goods_info, $type)
        ];
        $goods['goods_body'] = [
            'show_type' => $model_goods->getShowBodyType($type),
            'show_title' => $model_goods->getShowBodyTitle($type),
            'body_content' => $model_goods->getShowBody($goods_info, $type, $goods_data['goods_image']),
        ];
        $goods['delivery_free'] = [
            'has_free' => $model_goods->getHasDeliveryFee($type),
            'free_line' => FLOWER_MATERIAL_DELIVERY_FREE_LINE,
        ];
        $goods['store'] = [
            'user_id' => 0,
            'store_id' => 0,
            'store_name' => '',
            'store_label' => '',
        ];
        if ($type == GOODS_TYPE_HOME_FLOWER) {
            //家居花非限定时间内购买价格上涨比例
            $unset_rate = ((HOME_FLOWER_UNSET_RATE - 1) * 100) . '%';
            $goods['goods_extra'] = [
                'unset_tips' => '*说明：39系列收取6元配送费，69、99系列免配送费',
                'is_open' => 0,
                'unset_rate' => $unset_rate,
                'unset_price' => priceFormat($goods['goods_price'] * HOME_FLOWER_UNSET_RATE),
            ];
        }

        //花递 19年感恩节限时购需求修改
        $goods['is_xianshi'] = 0;
        if(SITEID == 258 && isset($goods_info['promotion_type']) && $goods_info['promotion_type'] == 'xianshi'){
            //获取当前用户在活动期间购买的数量
            $now_sale = 0;
            if($this->isLogin()){
                $now_sale = PXianshiMemberGoods::getMemberBuyNum($this->member_id,$goods_id,$goods_info['xianshi_start_time'],$goods_info['xianshi_end_time']);
                $now_sale = isset($now_sale['sum']) ? $now_sale['sum'] : 0;
            }
            $goods['is_xianshi'] = 1;
            $goods['xianshi_info'] = [
                'xianshi_title' => $goods_info['xianshi_title'],
                'lower_limit' => $goods_info['lower_limit'],
                'upper_limit' => $goods_info['upper_limit'],
                'now_sale' => $now_sale,
                'user_upper_limit' => $goods_info['user_upper_limit'],
                'start_time' => $goods_info['xianshi_start_time'],
                'end_time' => $goods_info['xianshi_end_time']
            ];
        }
        //搭配购买
        $collocation_goods = (new Query())->from('hua123_goods_jg_list a')
            ->leftJoin('hua123_goods b','b.goods_id = a.bundling_goods_id')
            ->where(['b.goods_state' => 1,'a.goods_id' => $goods_id])
            ->select(['a.bundling_goods_id goods_id','b.goods_image','b.goods_type','b.goods_price','b.goods_marketprice','b.goods_name'])
            ->orderBy('a.sort desc')
            ->all();
        $dp_goods = [];
        foreach($collocation_goods as $k => $collocation){
            $dp_goods[$k] = [
                'dp_goods_id' => $collocation['goods_id'],
                'dp_goods_name' => $collocation['goods_name'],
                'dp_goods_price' => FinalPrice::S($collocation['goods_price']),
                'dp_goods_marketprice' => FinalPrice::format($collocation['goods_marketprice']),
                'dp_goods_image' => thumbGoods($collocation['goods_image']),
                'dp_goods_type' => $collocation['goods_type'],
            ];
            //是否特价商品
            if(in_array($collocation['goods_id'],$special_offer_goods_ids)){
                $dp_goods[$k]['is_special'] = 1;
            }else{
                $dp_goods[$k]['is_special'] = 0;
            }
            //是否年卡专享商品
            if(in_array($collocation['goods_id'],$year_card_goods_ids)){
                $dp_goods[$k]['year_card_price'] = FinalPrice::yearCardMatchRate($year_card_id_arr[$goods_id],$collocation['goods_price']);
            }else{
                $dp_goods[$k]['year_card_price'] = 0;
            }
        }

        return [
            'goods_banner' => $goods_data['goods_image'],
            'goods_banner_mini' => $goods_data['goods_image_mini'],
            'goods_info' => $goods,
            'associated_goods_list' => $goods_data['associated_goods_list'],
            'goods_services' => $model_goods->getGoodsServices(),
            'goods_new_services' => $model_goods->getGoodsNewServices($goods),
            'dp_goods_list' => $dp_goods,
            'florist' => Florist::getFriendlyFlorist($goods_info['florist_id'], $type),
            'sku' => $model_goods->getSpuItem($goods_info, $type),
            'banners' => GoodsImages::getGoodsBanner($goods_info),
            'festival' => $this->holidayShow($goods_info, $type),
        ];
    }


    /**
     * 获取店铺鲜花详情
     * @param $goods_id
     * @return array
     */
    private function getStoreFlowerInfo($goods_id, $type = GOODS_TYPE_STORE_FLOWER)
    {
        $api = new MicroApi();
        $goods_info = $api->httpRequest('api/getHuaGoods', ['goods_id' => $goods_id]);
        if (!$goods_info) {
            \Yii::error($api->getError());
            return null;
        }
        $model_goods = new Goods();
        $goods = [];
        $goods['goods_id'] = $goods_id;
        $goods['goods_type'] = $type;
        $goods['goods_tag'] = $model_goods->getGoodsTag($goods_info, $type);
        $goods['goods_name'] = $goods_info['goods_name'];
        $goods['goods_material'] = $goods_info['goods_introduce'];
        $goods['goods_jingle'] = '';
        //微花店的转单价就是成本价
        $goods['goods_price'] = priceFormat($goods_info['goods_price']);
        $goods['goods_marketprice'] = floatval(priceFormat($goods_info['market_price']));
        $goods['goods_salenum'] = (int)$goods_info['sell_num'];
        $goods['is_flower'] = 0;
        $goods['is_home_flower'] = 0;
        $goods['is_material_flower'] = 0;
        $goods['is_store_flower'] = 1;
        $goods['is_other_flower'] = 0;
        $goods['is_unit'] = 0;
        $goods['unit'] = '束';
        $goods['store_id'] = $goods_info['store_id'];//用于查询花店的猜你喜欢（即热销推荐）
        //已加入购物车的数量
        $map = [];
        if ($this->isLogin()) {
            $map['buyer_id'] = $this->member_id;
        } else {
            $map['ssid'] = $this->sessionid;
        }
        $map['goods_id'] = $goods_id;
        $map['goods_type'] = $type;
        $cart = Cart::find()->select('goods_num')->where($map)->one();
        $goods['selected_num'] = $cart ? (int)$cart->goods_num : 0;
        $goods['quantity'] = [
            'show_quantity' => $model_goods->getShowQuantity($type),
            'default_quantity' => 1
        ];
        $goods['goods_attr'] = [
            'show_attr' => $model_goods->getShowAttr($type),
            'attr_list' => $model_goods->getAttrList($goods_info, $type)
        ];
        //V1
        $goods_banner = [];
        //V2
        $banners = [];
        if (isset($goods_info['img_list']) && !empty($goods_info['img_list'])) {
            foreach ($goods_info['img_list'] as $img) {
                $goods_banner[] = $img['url'];
                $banners[] = [
                    'image' => $img['url'],
                    'stickers' => [
                        'floating' => [],
                        'version' => 1,
                    ],
                ];
            }
        }else{
            $goods_banner[] = $goods_info['goods_img'];
            $banners[] = [
                'image' => $goods_info['goods_img'],
                'stickers' => [
                    'floating' => [],
                    'version' => 1,
                ],
            ];
        }

        $goods['goods_body'] = [
            'show_type' => $model_goods->getShowBodyType($type),
            'show_title' => $model_goods->getShowBodyTitle($type),
            'body_content' => $model_goods->getShowBody($goods_info, $type, $goods_banner),
        ];
        $goods['delivery_free'] = [
            'has_free' => $model_goods->getHasDeliveryFee($type),
            'free_line' => FLOWER_MATERIAL_DELIVERY_FREE_LINE,
        ];
        $goods['store'] = [
            'user_id' => $goods_info['member_id'],
            'store_id' => $goods_info['store_id'],
            'store_name' => $goods_info['store_name'],
            'store_label' => $goods_info['store_label'],
            'store_mobile' => $goods_info['store_mobile'],
        ];

        return [
            'goods_banner' => $goods_banner,
            'goods_banner_mini' => $goods_info['img_mini'],
            'goods_info' => $goods,
            'associated_goods_data' => [],
            'dp_goods_list' => isset($goods_info['dp_goods_list']) ? $goods_info['dp_goods_list'] : [],
            'goods_services' => $model_goods->getGoodsServices(),
            'goods_new_services' => $model_goods->getGoodsNewServices(),
            'store_delivery' => $this->getDelivery($goods_info['store_id']),
            'florist' => Florist::getFriendlyFlorist(0, $type),
            'sku' => $model_goods->getSpuItem($goods_info, $type),
            'goods_label' => [],
            'banners' => $banners,
            'festival' => $this->holidayShow($goods_info, $type),
        ];
    }

    /**
     * 读取商品详情猜你喜欢
     * @param int $store_id
     * @return array
     */
    private function getHotList($store_id = 0)
    {
        $goods_list = [];
        $api_url = "";//刷新页面的地址
        if($store_id > 0){
            $url = MICRO_DOMAIN . "/api/recommend_goods";
            $curl = new Curl();
            $param = [
                "store_id" => $store_id
            ];

            $response = $curl->setOption(
                CURLOPT_POSTFIELDS,
                http_build_query($param)
            )->post($url);
            $response = json_decode($response, true);
            if ($response["status"]) {
                $info = json_decode($response["data"], true);
                $goods_list = $info["list"];
                foreach ($goods_list as $k => $goods) {
                    $goods_list[$k]["goods_type"] = GOODS_TYPE_STORE_FLOWER;
                    $goods_list[$k]["gc_id"] = $goods["cat_id"];
                    $goods_list[$k]["gc_name"] = $goods["cat_name"];
                    $goods_list[$k]["goods_image"] = $goods["goods_img"];
                    $goods_list[$k]["goods_salenum"] = $goods["sell_num"];
                    $goods_list[$k]["goods_price"] = floatval($goods_list[$k]["goods_price"]);
                }
                $api_url = "/micro/recommend-goods";
            }
        }
        if(empty($goods_list)){
            $where = [];
            $where["goods.gc_id_1"] = Goods::TOP_CLASS;
            $where["goods.goods_state"] = 1;
            $field = "goods.goods_id,goods.goods_name,goods.goods_image,goods.goods_material,goods.gc_id,goods_class.gc_name,goods.goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $goods_model = new \common\models\Goods();
            $goods_list = $goods_model->goodslist($field, 1, 6, $where);
            $api_url = "/flower/recommend-list";
        }
        return [$goods_list,$api_url];
    }
    /**
     * 获取微花店的配送范围
     * @param int $store_id
     * @return mixed|string
     */
    private function getDelivery($store_id = 0){
        $url = MICRO_DOMAIN . "/api/storedelivery";
        $curl = new Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query(["store_id" => $store_id])
        )->post($url);
        $response = json_decode($response, true);
        $data = isset($response['data']) && $response['data'] != "" ? json_decode($response['data'],true) : [];
        $delivery = isset($data['delivery']) && $data['delivery'] != "" ? $data['delivery'] : '';
        return $delivery;
    }
    /**
     * 节日显示
     * @param $goods_info
     * @param $goods_type
     */
    private function holidayShow($goods_info, $goods_type)
    {
        $data = [];
        $data['open'] = 0;
        if ($goods_type == GOODS_TYPE_STORE_FLOWER) {
            $goods_info['goods_marketprice'] = $goods_info['market_price'];
            //return $data;
        }
        if (!HOLIDAY_OPEN) {
            return $data;
        }
        list($smooth_name, $festival_name) = explode(',', HOLIDAY_TITLE);
        $smooth = [];
        $smooth['name'] = $smooth_name;
        $smooth['price'] = sprintf("%.2f", FinalPrice::smooth($goods_info['goods_price']));
        $smooth['discount'] = [
            'on' => HOLIDAY_DISCOUNT_ON ? 1 : 0,
            'original' => sprintf("%.2f", FinalPrice::smoothMarket($goods_info['goods_marketprice'])),
            'rate' => intval($goods_info['goods_marketprice'])>0 ? sprintf("%s折", sprintf("%.1f", $goods_info['goods_price'] / $goods_info['goods_marketprice'] * 10)) : 0,
        ];
        $festival = [];
        $festival['name'] = $festival_name;
        $festival['price'] = sprintf("%.2f", FinalPrice::festival($goods_info['goods_price']));
        $festival['discount'] = [
            'on' => HOLIDAY_DISCOUNT_ON ? 1 : 0,
            'original' => sprintf("%.2f", FinalPrice::festivalMarket($goods_info['goods_price'], $goods_info['goods_marketprice'])),
            'rate' => intval($goods_info['goods_marketprice'])>0 ? sprintf("%s折", sprintf("%.1f", $goods_info['goods_price'] / $goods_info['goods_marketprice'] * 10)) : 0,
        ];
        $data['open'] = 1;
        $data['desc'] = HOLIDAY_DESC;
        $data['only'] = HOLIDAY_ONLY;
        $data['smooth'] = $smooth;
        $data['shake'] = $festival;
        return $data;
    }

    /**
     * 生成二维码
     * @return mixed
     */
    public function actionGetQrcode()
    {
        return $this->actionCreateQrcode();

        $url = \Yii::$app->request->get('url', '');
        if (!$url) {
            return $this->responseJson(Message::ERROR, '参数错误');
        }
        QrCodeClient::png($url);
//        ob_start();
//        QrCodeClient::png($url);
//        $data = ob_get_contents();
//        ob_end_clean();
//        $data = base64_encode($data);
//        return $this->responseJson(Message::SUCCESS, $data);
    }


    /**
     * 生成二维码 wap端 base64版
     * @return mixed
     */
    public function actionGetQrcodeWap()
    {
        //获取二维码图片
        $img = $this->actionCreateQrcode();
        if ($img->data['code'] != 1) {
            return $this->responseJson(Message::ERROR, '获取二维码信息失败');
        }
        $img = $img->data['content'];

        //获取文件内容
        $file_content = file_get_contents($img);
        if ($file_content === false) {
            return $this->responseJson(Message::ERROR, '获取二维码信息失败');
        }
        $imageInfo = getimagesize($img);
        $prefiex = 'data:' . $imageInfo['mime'] . ';base64,';
        $base64 = $prefiex . chunk_split(base64_encode($file_content));
        $base64 = base64_encode($base64);
        return $this->responseJson(Message::SUCCESS, '获取二维码信息成功',$base64);

    }



    /***
     * 创建微信小程序二维码
     */
    public function actionCreateQrcode()
    {
        $url = Request('url','');
        $type = Request('type',1);
        if($type == 2){
            $url = '/user-center/vipCard?help_id='.intval($url);
        }
        if (!$url) {
            return $this->responseJson(Message::ERROR, '参数错误');
        }
        $url = urldecode($url);
        $result = $this->get_qrcode($url);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $result);
    }
    /***
     * 创建微信小程序码
     */
    public function actionCreateSpcode()
    {
        //是否刷新缓存, 可能没获取到正确的小程序码
        $refresh = Request('refresh','');
        $path = Request('path','');
        //scene 调用微信时不能传空,给个默认值
        $scene = Request('scene','test');
        //跳转携带参数处理,拼接为'&'连接的值构成的字符串
        $scene_combine_str = '';
        if(!empty($scene)){
            if(is_array($scene)){
                $scene_combine_arr = [];
                foreach($scene as $k => $v){
                    $scene_combine_arr[] = $k .'='. $v;
                }
                $scene_combine_str = implode('&',$scene_combine_arr);
            }elseif(is_string($scene)){
                $scene_combine_str = $scene;
            }
        }
        $result = $this->get_spcode($path,$scene_combine_str,$refresh);
        $debug_arr = [
            'post_path' => $path,
            'post_scene' => $scene,
            'conbine_scene_str' => $scene_combine_str,
            'member_id' => $this->member_id,
            'return_result' => $result
        ];
        Log::writelog('huadi_spcode_debug',json_encode($debug_arr));

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $result);
    }
    function get_spcode($url,$scene,$refresh = false)
    {
        $_name = 'new_huadi_get_spcode_2020_1' . base64_encode(json_encode([$url,$scene]));
        $do_refresh = false;
        if($refresh){
            $_refresh_num = 'new_huadi_get_spcode_refresh_num' . base64_encode(json_encode([$url,$scene]));
            $refresh_num = cache($_refresh_num);
            if($refresh_num < 5){
                $do_refresh = true;
                cache($_refresh_num,$refresh_num+1, 6000);
            }else{
                $do_refresh = false;
            }
        }
        $res = cache($_name);
        if ($res && !$do_refresh) {
            return $res;
        }
        $access_token = \common\components\WeixinHuadi::getInstance()->get_token();
        $_url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$access_token}";
        $param = array();
        $param['page'] = $url;
        $param['scene'] = $scene;
        $param['width'] = '430px';
        $param = json_encode($param);
        $data = $this->https_request($_url, $param);
        $img = base64_encode($data);
        $upload = new Upload();
        $upload->autoSub = true;
        $upload->subName = array('date', 'Y/m/d');
        $upload->saveName = ['md5', [microtime(true) . rand(1, 99999)]];
        $upload->savePath = ATTACH_ORDER;
        $result = $upload->binary($img);
        if ($result) {
            $url = 'https://cdn.ahj.cm/' . $result[0]['savepath'] . $result[0]['savename'];
            cache($_name, $url,6000);
        }
        return $url;
    }

    function get_qrcode($url)
    {
        $_name = 'new_huadi_get_qrcode_2020_1' . base64_encode($url);
        $res = cache($_name);
        if ($res) {
            return $res;
        }
        $access_token = \common\components\WeixinHuadi::getInstance()->get_token();
        $_url = "https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token={$access_token}";
        $param = array();
        $param['path'] = $url;
        $param['width'] = '430px';
        $param = json_encode($param);
        $data = $this->https_request($_url, $param);
        $img = base64_encode($data);

        $upload = new Upload();
        $upload->autoSub = true;
        $upload->subName = array('date', 'Y/m/d');
        $upload->saveName = ['md5', [microtime(true) . rand(1, 99999)]];
        $upload->savePath = ATTACH_ORDER;
        $result = $upload->binary($img);
        if ($result) {
            $url = 'https://cdn.ahj.cm/' . $result[0]['savepath'] . $result[0]['savename'];
            cache($_name, $url,6000);
        }
        return $url;
    }

    function https_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    function getname()
    {
        return date('Ymdhis') . rand(100, 999) . '.jpg';
    }

    /**
     * 花递1.0.14特价顶部分类数据
     */
    public function actionSpecialTopCategory()
    {
        $data_model = new \common\models\Data();
        $top_category = $data_model -> getSpecialTopCategory();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $top_category);
    }

    /**
     * 花递特价模块精选接口
     */
    public function actionSpecialOfferCommend(){
        $special_offer_model = new SpecialOffer();
        $special_offer_goods_model = new SpecialOfferGoods();
        $settings = \common\models\Setting::instance()->getValue("huadi_icon_setting",true);
        $huadi_icon_setting = unserialize($settings);
        //1爆款推荐 2礼品花推荐 3生活花推荐 4花篮推荐 5绿植推荐
        $special_offer_arr = [
            [
                'type' => 1,
                'key'  => 'explosion',
                'limit'=> 4,
            ],
            [
                'type' => 2,
                'key'  => 'gift_flower',
                'limit'=> 3,
            ],
            //20200818,隐藏生活花
            [
                'type' => 3,
                'key'  => 'live_flower',
                'limit'=> 3,
            ],
            [
                'type' => 4,
                'key'  => 'basket_flower',
                'limit'=> 3,
            ],
            [
                'type' => 5,
                'key'  => 'green_plant',
                'limit'=> 3,
            ]
        ];
        $data = [];
        $special_offer_goods_order = $this->specialOfferGoodsSort();
        foreach ($special_offer_arr as $k=>$v){
            if($huadi_icon_setting['daily_flower_tag'] == 0 && $v['type'] === 3) {
                // 隐藏生活花
                continue;
            }
            $special_offer = $special_offer_model->getOnlineSpecialOfferByType($v['type']);
            if($special_offer && !empty($special_offer)){
                $condition = [];
                $condition['huadi_special_offer_goods.at_id'] = $special_offer['at_id'];
                $data[$v['key']] = $special_offer_goods_model->getSpecialOfferGoods($condition,$v['limit']);
            }else{
                $where = [];
                if($special_offer_goods_order){
                    $where['goods.goods_id'] = $special_offer_goods_order;
                }else{
                    $data[$v['key']] = [];
                    continue;
                }
                switch ($v['type']){
                    case 1:
                    case 6:
                        $where["goods.gc_id_2"] = [
                            Goods::FLOWER_GIFT,
                            Goods::FLOWER_MATERIAL,
                            Goods::FLOWER_HOME,
                            Goods::FLOWER_LVZHI,
                            Goods::FLOWER_BASKET,
                            Goods::FLOWER_PRESENT,
                            Goods::FLOWER_CHOC,
                            Goods::FLOWER_CAKE,
                            Goods::FLOWER_ASSORT,
                            Goods::FLOWER_DUOROU,
                        ];
                        break;
                    case 2:
                        $where["goods.gc_id_2"] = Goods::FLOWER_GIFT;
                        break;
                    case 3:
                        $where["goods.gc_id_2"] = Goods::FLOWER_HOME;
                        break;
                    case 4:
                        $where["goods.gc_id_2"] = Goods::FLOWER_BASKET;
                        break;
                    case 5:
                        $where["goods.gc_id_2"] = Goods::FLOWER_LVZHI;
                        break;
                    default:
                        $where["goods.gc_id_2"] = [
                            Goods::FLOWER_GIFT,
                            Goods::FLOWER_MATERIAL,
                            Goods::FLOWER_HOME,
                            Goods::FLOWER_LVZHI,
                            Goods::FLOWER_BASKET,
                            Goods::FLOWER_PRESENT,
                            Goods::FLOWER_CHOC,
                            Goods::FLOWER_CAKE,
                            Goods::FLOWER_ASSORT,
                            Goods::FLOWER_DUOROU,
                        ];
                }
                $order = "field(goods.goods_id,".implode(',',$special_offer_goods_order).")";
                $data[$v['key']] = $special_offer_goods_model->getSpecialOfferGoodsSort($where,$order,1,$v['limit']);
            }
        }
        $data['wordContent'] = SpecialOfferCommendService::wordContent();
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    public function actionSpecialOfferGoodsList(){
        $special_offer_goods_model = new SpecialOfferGoods();
        $param = \Yii::$app->request->post();
        $special_type = isset($param['special_type']) ? (int)$param['special_type'] : 0;
        $cat_id = isset($param['cat_id']) ? (int)$param['cat_id'] : 0;
        if($cat_id == 671){
            $cat_id = 752;  //因为被删除  又不想改前端影响发版 故‘送长辈’强转
        }
        $page = isset($param['page']) ? (int)$param['page'] : 1;
        $pagesize = 10;
        $special_type = $special_type > 5 ? 0 : $special_type;
        $special_offer_goods_order = $this->specialOfferGoodsSort();
        $where = [];
        $where["goods.goods_id"] = $special_offer_goods_order;
        if ($cat_id > 0) {
            $query = GoodsAttrIndex::find();
            $goods_ids = $query->select('goods_id')->where(array("attr_value_id" => $cat_id))->select("goods_id")->asArray()->all();
            if ($goods_ids) {
                $cat_goods_ids = array_column($goods_ids, 'goods_id');
                $where["goods.goods_id"] = array_intersect($special_offer_goods_order,$cat_goods_ids);
            }
        }

        switch ($special_type){
            case 1:
                $where["goods.gc_id_2"] = [
                    Goods::FLOWER_GIFT,
                    Goods::FLOWER_MATERIAL,
                    Goods::FLOWER_HOME,
                    Goods::FLOWER_LVZHI,
                    Goods::FLOWER_BASKET,
                    Goods::FLOWER_PRESENT,
                    Goods::FLOWER_CHOC,
                    Goods::FLOWER_CAKE,
                    Goods::FLOWER_ASSORT,
                    Goods::FLOWER_DUOROU,
                ];
                break;
            case 2:
                $where["goods.gc_id_2"] = Goods::FLOWER_GIFT;
                break;
            case 3:
                $where["goods.gc_id_2"] = Goods::FLOWER_HOME;
                break;
            case 4:
                $where["goods.gc_id_2"] = Goods::FLOWER_BASKET;
                break;
            case 5:
                $where["goods.gc_id_2"] = Goods::FLOWER_LVZHI;
                break;
            default:
                $where["goods.gc_id_2"] = [
                    Goods::FLOWER_GIFT,
                    Goods::FLOWER_MATERIAL,
                    Goods::FLOWER_HOME,
                    Goods::FLOWER_LVZHI,
                    Goods::FLOWER_BASKET,
                    Goods::FLOWER_PRESENT,
                    Goods::FLOWER_CHOC,
                    Goods::FLOWER_CAKE,
                    Goods::FLOWER_ASSORT,
                    Goods::FLOWER_DUOROU,
                ];
        }
        $where['goods.goods_state'] = 1;//商品状态正常
        $order = "field(goods.goods_id,".implode(',',$special_offer_goods_order).")";
        $data = $special_offer_goods_model->getSpecialOfferGoodsSort($where,$order,$page,$pagesize);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }


    //花递特价包月花接口
    public function actionSpecialPbundlingList(){
        $config = Setting::instance()->getAll();
        $huadi_special_offer_bundling_idstr = $config['huadi_special_offer_bundling_ids'] ? $config['huadi_special_offer_bundling_ids'] : '';
        if($huadi_special_offer_bundling_idstr){
            $huadi_special_offer_bundling_ids = explode(',',$huadi_special_offer_bundling_idstr);
            $page = Yii::$app->request->post("page", 0);
            if ($page < 0 || !is_numeric($page)) {
                return $this->responseJson(1, "参数错误");
            }
            $pagesize = 10;

            $PBund_model = new \common\models\PBundling();
            $condition = [];
            $condition['bl_type'] = $PBund_model::BUNDLING_2;
            $condition['bl_id'] = $huadi_special_offer_bundling_ids;
            $condition['bl_state'] = 1;
            $field = 'bl_id, bl_name, bl_sub_name, bl_discount_price, norms_info';
            $data = $PBund_model->getBundlingJxList($condition, $page, $pagesize, $field);
            return $this->responseJson(1, "success", $data);
        }else{
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }
    }

    private function specialOfferGoodsSort(){
        /**
         * 1、真实销售量   15%
        0-5：1分；5-10：2分；10-20：3分；20-50-4分；50以上：5分；
         * 4、评论数量  15%
        0-5：1分；5-10：2分；10-20：3分；20-50-4分；50以上：5分；
         * 5、好评率  15%
        0-60%：1分；60%-70%-10：2分；70–80%：3分；80-90%：4分；90%以上：5分；
         * 6、加购次数  10%
        0-5：1分；5-10：2分；10-20：3分；20-50-4分；50以上：5分；
         * 8、利润率 10%
        0-10%：1分；10%-20%：2分；20-30%：3分；30-50%：4分；50%以上：5分；
         */
        $config = Setting::instance()->getAll();
        $goods_model = new Goods();
        $special_offer_goods_idstr = $config['huadi_special_offer'] ? $config['huadi_special_offer'] : '';
        $special_offer_goods_order = [];
        $cache_name = md5($special_offer_goods_idstr);
        $data = cache($cache_name);
        if(empty($data)){
            if($special_offer_goods_idstr){
                $special_offer_goods_ids = explode(',',$special_offer_goods_idstr);
                //真实销量 利润率
                $so_sale_profit = $goods_model->getGoodsInfo(['goods_id'=>$special_offer_goods_ids],'goods_id,goods_salenum,((goods_price - goods_costprice)/goods_price) as profit_rate');
                $so_sale_profit = array_column($so_sale_profit, null, 'goods_id');
                //评论数量
                $so_commen = EvaluateGoods::find()->where(['geval_goodsid'=>$special_offer_goods_ids])->select('geval_goodsid as goods_id,count(*) as comment_count')->groupBy('goods_id')->asArray()->all();
                $so_commen = array_column($so_commen, null, 'goods_id');
                //好评数量
                $so_good_commen = EvaluateGoods::find()->where(['geval_goodsid'=>$special_offer_goods_ids,'geval_level'=>1])->select('geval_goodsid as goods_id,count(*) as goods_comment_count')->groupBy('goods_id')->asArray()->all();
                $so_good_commen = array_column($so_good_commen, null, 'goods_id');
                //加入购物车次数
                $cart_count = Cart::find()->where(['goods_id'=>$special_offer_goods_ids])->select('goods_id,count(*) as cart_count')->groupBy('goods_id')->asArray()->all();
                $cart_count = array_column($cart_count, null, 'goods_id');
                $goods_score_arr = [];
                foreach ($special_offer_goods_ids as $val){
                    $sale_score = isset($so_sale_profit[$val]) ? $this->quota_score_final($so_sale_profit[$val]['goods_salenum'],'sale_count') : 0;
                    $profit_rate_score = isset($so_sale_profit[$val]) ? $this->quota_score_final(round($so_sale_profit[$val]['profit_rate'] * 100,2),'profit_rate') : 0;
                    $commen_score = isset($so_commen[$val]) ? $this->quota_score_final($so_commen[$val]['comment_count'],'comment_count') : 0;
                    $good_commen_score = isset($so_good_commen[$val]) && isset($so_commen[$val]) ? $this->quota_score_final(round(($so_good_commen[$val]['goods_comment_count']/$so_commen[$val]['comment_count']) * 100,2),'good_comment_rate') : 0;
                    $cart_score = isset($cart_count[$val]) ? $this->quota_score_final($cart_count[$val]['cart_count'],'cart_count') : 0;
                    $goods_score_arr[$val] = $sale_score + $profit_rate_score + $commen_score + $good_commen_score + $cart_score;
                }
                arsort($goods_score_arr);
                $special_offer_goods_order = array_keys($goods_score_arr);
                cache($cache_name, $special_offer_goods_order, 7200);
            }
        }else{
            return $data;
        }
        return $special_offer_goods_order;
    }

    /**
     * 根据评级评分规则获取最终得分
     * @param $val
     * @param $quota_type 指标类型
     * @return int|mixed
     */
    private function quota_score_final($val, $quota_type)
    {
        $score_final = 0;
        //指标类型 对应实际得分规则
        switch ($quota_type) {
            case 'sale_count':
            case 'comment_count':
            case 'cart_count':
                if ($val < 5) {
                    $score_final = 1;
                } elseif ($val >= 5 && $val < 10) {
                    $score_final = 2;
                } elseif ($val >= 10 && $val < 20) {
                    $score_final = 3;
                } elseif($val >= 20 && $val < 50) {
                    $score_final = 4;
                }else{
                    $score_final = 5;
                }
                break;
            case 'good_comment_rate':
                if ($val < 60) {
                    $score_final = 1;
                } elseif ($val >= 60 && $val < 70) {
                    $score_final = 2;
                } elseif ($val >= 70 && $val < 80) {
                    $score_final = 3;
                } elseif($val >= 80 && $val < 90) {
                    $score_final = 4;
                }else{
                    $score_final = 5;
                }
                break;
            case 'profit_rate':
                if ($val < 10) {
                    $score_final = 1;
                } elseif ($val >= 10 && $val < 20) {
                    $score_final = 2;
                } elseif ($val >= 20 && $val < 30) {
                    $score_final = 3;
                } elseif($val >= 30 && $val < 50) {
                    $score_final = 4;
                }else {
                    $score_final = 5;
                }
                break;
        }
        return $score_final;
    }
    public function actionGetPromotionBundling(){
        $goods_id = intval(Yii::$app->request->post('goods_id',0));
        $page = intval(Yii::$app->request->post('page',0));
        if(!$page){
            $page = 1;
            $pagesize = 2;//默认值展示2条数据
        }else{
            $pagesize = 10;
        }
        if (!$goods_id) {
            return $this->responseJson(Message::ERROR, Message::EMPTY_MSG, []);
        }
        //查询优惠套装is_ahj=0 && bl_type=1,的套装数据
        $promotion_bundling_data = PBundlingGoods::find()->alias('a')
            ->leftJoin(PBundling::tableName() . ' b','b.bl_id = a.bl_id')
            ->where(['a.goods_id' => $goods_id, 'b.bl_state' => 1, 'b.is_ahj' => 0, 'b.bl_type' => 1])
            ->select(['a.bl_id'])->groupBy('a.bl_id')
            ->orderBy('b.add_time desc')
            ->offset(($page-1)*$pagesize)
            ->limit($pagesize)
            ->asArray()->column();
        if(!$promotion_bundling_data) {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }
        $data = [];
        $goods_model = new Goods();
        foreach ($promotion_bundling_data as $bl_id){
            $_data = [];
            $_data['bl_id'] = $bl_id;
            $promotion_goods_data = PBundlingGoods::find()->alias('a')
                ->leftJoin(Goods::tableName() . ' b','b.goods_id = a.goods_id')
                ->where(['a.bl_id' => $bl_id])
                ->select(['b.gc_id_2','a.goods_name','a.goods_id','a.goods_image','a.bl_goods_price','b.ahj_goods_price'])
                ->asArray()->all();
            if(!$promotion_goods_data) continue;
            $market_amount = $bundling_amount = 0;
            foreach ($promotion_goods_data as $promotion_goods){
                $goods = [
                    'goods_id' => $promotion_goods['goods_id'],
                    'goods_name' => $promotion_goods['goods_name'],
                    'goods_image' => thumbGoods($promotion_goods['goods_image'],320),
                    'goods_type' => $goods_model->getGoodsType($promotion_goods['gc_id_2']),
                    'goods_price' => FinalPrice::yearCardMatchRate($promotion_goods['bl_goods_price'], $promotion_goods['bl_goods_price'],2),
                    'goods_market_price' => FinalPrice::S($promotion_goods['ahj_goods_price']),
                ];
                $market_amount += $goods['goods_market_price'];
                $bundling_amount += $goods['goods_price'];
                $_data['goods_info'][] = $goods;
            }
            $_data['amount_total'] = $bundling_amount;
            $_data['amount_discount'] =  $market_amount - $bundling_amount;
            //多少人已购买, 前期用缓存假数据, 定时更新真数据
            $cache_key = 'bl_id_'.$bl_id;
            $buy_num = cache($cache_key);
            if(!$buy_num){
                //随机生成一个200-500的数字并缓存起来
                $buy_num = rand(200,500);
                cache($cache_key,$buy_num);
            }
            $_data['buy_num'] = $buy_num;
            $data[] = $_data;
        }
        return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$data);
    }

    /**
     * @param  $goods_id integer|array
     * @param  $bl_id integer
     * @return mixed
     */
    public function actionGoodsEval(){
        $params = Yii::$app->request->post();
        $goods_id = $params['goods_id'];
        $bl_id = isset($params['bl_id']) ? $params['bl_id'] : 0;
        if($bl_id > 0) {
            $condition_goods = ['bl_id' => $bl_id, 'is_delete' => 0];
            $model_goods = new PBundlingGoods();
            $bundling_goods = $model_goods->getBundlingGoodsList($condition_goods, "goods_id");
            if($bundling_goods){
                $goods_id = array_column($bundling_goods,'goods_id');
            }
        }
        $type = intval($params['type']);//评价好评类型 1:送货快;2鲜花质量好;3服务态度好
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 10;
        //处理标签统计/总评论/好评率
        $static_data = $this->getEvalStatic($goods_id);
        //评论列表数据
        if($type){//根据标签筛选不同的评论
            switch($type){
                case 1 :
                    $_where = ['>=', 'geval_scores_fw', 4];
                    break;
                case 2 :
                    $_where = ['>=', 'geval_scores_zl', 4];
                    break;
                case 3 :
                    $_where = ['>=', 'geval_scores_sd', 4];
                    break;
                case 5 :
                case 6 :
                case 7 :
                case 8 :
                case 9 :
                    $tag_id = $type-4;
                    $_where = new Expression('FIND_IN_SET('. $tag_id .',geval_tags)');
                    break;
            }
        }
        $condition = [];
        $condition['geval_goodsid'] = $goods_id;
//        $condition['geval_type'] = 0;
        $condition['geval_is_show'] = 1;
        if(isset($_where) && !empty($_where)){
            $condition = ['and',$condition,$_where];
        }
        $geval_list = (new EvaluateGoods)->getEvaluateList($condition, $field = '*','geval_id desc',$page,$pagesize);
        $member_ids = array_column($geval_list,'geval_frommemberid');
        $member_avatars = Member::find()->where(['member_id' => $member_ids])
            ->select(['member_avatar','member_id', 'member_mobile', 'member_nickname'])
            ->indexBy('member_id')->asArray()->all();
        $comment_list = [];
        if ($geval_list) {
            foreach ($geval_list as $k => $eva) {
                $data = [];
                $data['comment_level'] = isset(EvaluateGoods::getEvalLevel()[$eva['geval_level']]) ? EvaluateGoods::getEvalLevel()[$eva['geval_level']] : '好评';
                $data['comment_content'] = $eva['geval_content'];
                $data['comment_id'] = $eva['geval_id'];
                $data['comment_time'] = getFriendlyTime($eva['geval_addtime'],'Y年m月d日');
                $data['comment_reply'] = $eva['reply_content'];
                if (empty($eva['reply_content']) && $eva['geval_level'] == 1 && preg_match('/(like|love|好|美|不错|快|喜欢|棒|漂亮|开心|贴心|新鲜|爱|赞|热情|满意|及时)/is', $eva['geval_content'])) {
                    $data['comment_reply'] = '非常感谢亲的信任、支持和鼓励，感谢亲的满分好评。祝亲生活愉快，天天开心哦。';
                }
                if($eva['geval_image']){
                    $images = explode(',', $eva['geval_image']);
                    foreach($images as $image){
                        $data['comment_imgs'][] =getImgUrl($image, ATTACH_COMMENT);
                    }
                }else{
                    $data['comment_imgs'] = [];
                }
                $member_avatar = isset($member_avatars[$eva['geval_frommemberid']]) ? $member_avatars[$eva['geval_frommemberid']]['member_avatar'] : '';
                if($eva['geval_frommembername'] == ''){
                    //如果评价人无名称, 填充手机号并脱敏
                    $eva['geval_frommembername'] = isset($member_avatars[$eva['geval_frommemberid']]) ? $member_avatars[$eva['geval_frommemberid']]['member_mobile'] : '';
                    $eva['geval_frommembername'] = name_format($eva['geval_frommembername']);
                }
                if($eva['geval_isanonymous']) {
                    if(mb_strlen($eva['geval_frommembername'], 'utf-8') < 2){
                        $nickname = '匿名';
                    }else{
                        $nickname = name_format($eva['geval_frommembername']);
                    }
                }else{
                    $nickname = $eva['geval_frommembername'];
                }
                $data['member'] = [
                    'id' => 0,
                    'avatar' => getMemberAvatar($member_avatar),
                    'nickname' => $nickname,
                ];
                $comment_list[] = $data;
            }
        }
        $data = [
            'static_data' => $static_data,
            'eval_list' => $comment_list
        ];
        return $this->responseJson(Message::SUCCESS,'success',$data);
    }
    protected function getEvalStatic($goods_id){
        $tags = EvaluateGoods::find()
            ->where(['geval_goodsid' => $goods_id, 'geval_is_show' => 1])
            ->select([
                'count(*) eval_num',//评论总数
                'sum(case when geval_level=1 then 1 else null end) good_eval_num',//好评数量
                'sum(case when geval_scores_fw >= 4 then 1 else null end) service_goods_num',//服务态度好
                'sum(case when geval_scores_zl >= 4 then 1 else null end) quality_goods_num',//质量好
                'sum(case when geval_scores_sd >= 4 then 1 else null end) deli_goods_num',//送货快
//                'sum(case when geval_scores_bz >= 4 then 1 else null end) cover_goods_num',//包装好
                'sum(case when FIND_IN_SET(1,geval_tags) then 1 else null end) flower_fresh_num',//花材新鲜
                'sum(case when FIND_IN_SET(2,geval_tags) then 1 else null end) flower_big_num',//花朵大
                'sum(case when FIND_IN_SET(3,geval_tags) then 1 else null end) cover_good_num',//包装好看
                'sum(case when FIND_IN_SET(4,geval_tags) then 1 else null end) dp_good_num',//搭配好看
                'sum(case when FIND_IN_SET(5,geval_tags) then 1 else null end) price_good_num',//性价比高
            ])->asArray()->one();
        $data = [];
        $data['eval_count']['eval_num'] = $tags['eval_num'];
        $data['eval_count']['good_eval_ratio'] = $tags['eval_num'] >0 ? sprintf('%.2f',($tags['good_eval_num']/$tags['eval_num'])*100) . '%' : '0.00%';
        $data['eval_tags'] = [];
        if($tags['service_goods_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '服务态度好('. $tags['service_goods_num'] .')',
                'type' => 1,
            ];
        }
        if($tags['quality_goods_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '质量好('. $tags['quality_goods_num'] .')',
                'type' => 2,
            ];
        }if($tags['deli_goods_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '送货快('. $tags['deli_goods_num'] .')',
                'type' => 3,
            ];
        }
        if($tags['flower_fresh_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '花材新鲜('. $tags['flower_fresh_num'] .')',
                'type' => 5,
            ];
        }if($tags['flower_big_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '花朵大('. $tags['flower_big_num'] .')',
                'type' => 6,
            ];
        }if($tags['cover_good_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '包装好看('. $tags['cover_good_num'] .')',
                'type' => 7,
            ];
        }if($tags['dp_good_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '搭配好看('. $tags['dp_good_num'] .')',
                'type' => 8,
            ];
        }if($tags['price_good_num'] > 0 ){
            $data['eval_tags'][] = [
                'title' => '性价比高('. $tags['price_good_num'] .')',
                'type' => 9,
            ];
        }
        return $data;
    }
}

