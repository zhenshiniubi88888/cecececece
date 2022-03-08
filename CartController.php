<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Log;
use common\components\Message;
use common\models\Cart;
use common\models\FlowerArtVideo;
use common\models\Goods;
use common\models\GoodsAddprice;
use common\models\HuadiYearCardOrders;
use common\models\Orders;
use common\models\PBundlingSkuGoods;
use common\models\PBundling;
use common\models\PBundlingGoods;
use common\models\PXianshiGoods;
use common\models\Setting;
use linslin\yii2\curl\Curl;

/**
 * CartController
 */
class CartController extends BaseController
{

    public function actionIndex()
    {
        return $this->actionList();
    }

    /**
     * 购物车查询
     * @return mixed
     */
    public function actionList()
    {
        $this->_syncCartGoodsPrice();
        $yearCardPrice=[];
        $model_cart = new Cart();
        // 获取用户购物车
        if ($this->isLogin()) {
            $cart_list = $model_cart->getCartByMember($this->member_id);
        } else {
            $cart_list = $model_cart->getCartBySsid($this->sessionid);
        }
        // 获取购物车商品最新信息
        $cart_list = $model_cart->_getOnlineCartList($cart_list,$yearCardPrice);
        // 分离已失效产品并将正常状态的产品分类汇总
        $cart_list = $model_cart->_groupOnlineCartList($cart_list);

        $data = [];
        //得到购物车产品
        $member_id = $this->isLogin() ? $this->member_id : 0;
        $data['cart_list'] = $model_cart->_getFriendlyCartList($cart_list,$member_id,$yearCardPrice);
        //修改title
        foreach($data['cart_list']['active_cart'] as &$cart){
            $cart['gc_name'] = Orders::replace_title($cart['gc_name']);
        }
        //得到购物车汇总
        $data['cart_total'] = $model_cart->_getCartTotal($cart_list);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 单品：单个或批量添加购物车(仅限单品) 礼品花/花篮/绿植/单束包月花
     * cart_json[0]['good_id'] = 29; //商品编号 (必须)
     * cart_json[0]['goods_type'] = 1; //商品类型 参照GOODS_TYPE (必须)
     * cart_json[0]['quantity'] = 29; //商品数量 (必须)
     * cart_json[0]['offer_id'] = 29; //报价ID 礼品花才传
     * cart_json[0]['first_date'] = 29; //首次送达日期 包月花单束才传
     * cart_json[0]['is_direct'] = 1; //立即购买
     */
    public function actionAdd()
    {
        $param = \Yii::$app->request->post();

        if (isset($param['cart_json'])&& !empty($param['cart_json'])) {
            $cart_param = $param['cart_json'];
        }else{
            return $this->responseJson(Message::ERROR, '请选择产品');
        }
        $batch_max = \Yii::$app->params['cart']['batchInsertMax'];
        if (count($cart_param) > $batch_max) {
            return $this->responseJson(Message::ERROR, sprintf('购物车一次性最多只能加入%s个产品', $batch_max));
        }
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
        $goods_model = new Goods();
        $cart_id = [];
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            //批量添加
            foreach ($cart_param as $cart) {

                //查找商品INFO
                $goods_info = $goods_model->getPlatGoods($cart['goods_id'], $cart['goods_type']);

                if (empty($goods_info)) {
                    //TODO 待验证
                    return $this->responseJson(Message::ERROR, sprintf('很抱歉，您所购买的商品[%s]已下架', $cart['goods_id']));
                }

                $quantity = (int)$cart['quantity'];
                //报价ID
                $offer_id = 0;
                //店铺ID
                $store_id = 0;
                //店铺名
                $store_name = '';
                //报价INFO
                $offer_info = [];
                //首次送达日期
                $delivery_time = 0;
                //商品价格
                $goods_price = 0;
                //验证
                if ($quantity < 1) {
                    $quantity = 1;
                }
                //新版已取消店铺选择20181112
                if ($cart['goods_type'] == GOODS_TYPE_FLOWER && false) {
                    $offer_id = 1;
                    $store_id = (int)$cart['store_id'];
                    if (!$store_id) {
                        throw new \Exception('参数错误');
                    }
                    $store_name = (string)$cart['store_name'];
                    if (!$store_name) {
                        throw new \Exception('请选择报价');
                    }
                    $addprice = new GoodsAddprice();
                    $goods_price = $addprice->getAddPrice($cart['goods_id'], $cart['store_id']);
                    if (!$goods_price) {
                        throw new \Exception($addprice->getFirstError('error'));
                    }
                } elseif ($cart['goods_type'] == GOODS_TYPE_HOME_FLOWER && false) {//20190418已取消提前时间选择
                    $first_date = (string)$cart['first_date'];
                    if (!Orders::checkTime($first_date)) {
                        throw new \Exception(Orders::instance()->getFirstError(Message::MODEL_ERROR));
                    }
                    $goods_price = Orders::checkUnset($first_date) ? $goods_info['goods_price'] : priceFormat($goods_info['goods_price'] * HOME_FLOWER_UNSET_RATE);
                    $delivery_time = strtotime($first_date);
                }
                //TODO 库存验证

                $data = [];
                //花递指定店铺下单 20190828
                if(isset($param['device_type']) && in_array($param['device_type'],array('applet_huadi','app_huadi','app_huadi_android','app_huadi_ios')) && isset($cart['store_id'])){

                    $storeid = (int)$cart['store_id'];
                    if (!$storeid) {
                        throw new \Exception('店铺ID参数错误');
                    }
                    $storename = (string)$cart['store_name'];
                    if (!$storename) {
                        throw new \Exception('店铺名称参数错误');
                    }
                    if(isset($cart['store_tel'])){
                        $storetel = $cart['store_tel'];
                    }else{
                        $url = MICRO_DOMAIN . "/api/storeinfo";
                        $curl = new Curl();
                        $param = [
                            "store_id" => $storeid,
                        ];
                        $response = $curl->setOption(
                            CURLOPT_POSTFIELDS,
                            http_build_query($param)
                        )->post($url);
                        $response = json_decode($response, true);
                        if ($response["status"]) {
                            $info = json_decode($response["data"], true);
                            $storetel = $info["store_mobile"];
                        }
                    }
                    $data['store_id'] = $storeid;
                    $data['store_name'] = $storename;
                    $data['store_tel'] = $storetel;
                }
                if ($this->isLogin()) {
                    $data['buyer_id'] = $this->member_id;
                    $data['ssid'] = "";
                } else {
                    $data['ssid'] = $this->sessionid;
                }

                $data['cart_show'] = 1;
                $data['is_micro'] = $cart['goods_type'] == GOODS_TYPE_STORE_FLOWER ? 1 : 0;
                if ($cart['goods_type'] == GOODS_TYPE_STORE_FLOWER) {
                    $data['store_id'] = (int)$goods_info['store_id'];
                    $data['store_name'] = (string)$goods_info['store_name'];
                }
                if ($offer_id) {
                    $data['offer_id'] = $offer_id;
                    //查找报价信息
                    $data['store_id'] = (int)$store_id;
                    $data['store_name'] = (string)$store_name;
                }
                $data['gc_id'] = $goods_info['gc_id'];
                $data['gc_name'] = $goods_info['gc_name'];
                $data['goods_type'] = $cart['goods_type'];
                $data['goods_id'] = $goods_info['goods_id'];
                $data['goods_name'] = $goods_info['goods_name'];
                $data['goods_material'] = $goods_info['goods_material'];
                //是否年卡专享商品
                if($year_card_member && in_array($goods_info['goods_id'],$year_card_goods_ids)){
                    $data['goods_price'] = $year_card_id_arr[$goods_info['goods_id']];
                    $data['cart_price'] = $year_card_id_arr[$goods_info['goods_id']];
                }else{
                    $data['goods_price'] = FinalPrice::format($goods_info['ahj_goods_price']);
                    $data['cart_price'] = $goods_price ? $goods_price : FinalPrice::format($goods_info['ahj_goods_price']);
                }
                $data['goods_image'] = $goods_info['goods_image'];
                $data['bl_id'] = 0;
                $data['combin_id'] = 0;
                $data['extension_id'] = 0;
                $data['extension_type'] = 0;
                $data['delivery_time'] = $delivery_time;
                $data['time'] = time();
                $data['extra'] = "";
                $data['is_holiday'] = isset($cart['is_holiday'])&&$cart['is_holiday'] == 1 ? 1 : 0;
                $data['time'] = TIMESTAMP;
                //查找是否已加入购物车
                $condition = [];
                if ($this->isLogin()) {
                    $condition['buyer_id'] = $this->member_id;
                } else {
                    $condition['ssid'] = $this->sessionid;
                }
                $condition['goods_id'] = $cart['goods_id'];
                $condition['goods_type'] = $cart['goods_type'];
                $condition['bl_id'] = 0;
                if ($store_id) {
                    //同一商品多店铺下单支持
                    $condition['store_id'] = $store_id;
                }

                //19年感恩节限时购加购物车添加检查
                if(SITEID == 258){
                    $member_id = $this->isLogin() ? $this->member_id : 0;
                    $checkResult = (new PXianshiGoods())->checkXianshi($member_id,$cart['goods_id'],$quantity);
                    if($checkResult['code'] != 1){
                        return $this->responseJson($checkResult['code'], $checkResult['msg']);
                    }
                }

                $original = Cart::findOne($condition);
                if ($original) {
                    $original->setAttributes($data);
                    //是否是直接购买
                    if(isset($cart['is_direct'])){
                        if(SITEID == 258 && $cart['goods_type'] == GOODS_TYPE_MATERIAL_FLOWER){
                            $original->goods_num = $quantity > 4 ? $quantity : 5;
                        }else{
                            $original->goods_num = $quantity;
                        }
                    }else{
                        $original->goods_num += $quantity;
                    }
                    $result = $original->save();
                    if (!$result) {
                        \Yii::error($original->getErrors());
                        throw new \Exception('加入失败');
                    }
                    $cart_id[] = $_cart_id= $original->cart_id;
                } else {
                    //新增记录
                    if(SITEID == 258 && $cart['goods_type'] == GOODS_TYPE_MATERIAL_FLOWER){
                        $data['goods_num'] = $quantity > 4 ? $quantity : 5;
                    }else{
                        $data['goods_num'] = $quantity;
                    }
                    $data['goods_image'] = !empty($data['goods_image']) ? $data['goods_image'] : 'null_image';//临时解决商品无图片加入失败
                    $new_cart = new Cart();
                    $new_cart->setAttributes($data);
                    $result = $new_cart->save();
                    if (!$result) {
                        \Yii::error($new_cart->getErrors());
                        throw new \Exception('加入失败');
                    }
                    $cart_id[] = $_cart_id = $new_cart->cart_id;
                }
                //判断商品是否来自于花艺视频推荐, 并记录缓存数据, 统计视频销量
                if(isset($cart['video_id']) && $cart['video_id'] > 0){
                    $video_model = FlowerArtVideo::find()
                        ->where(['id' => $cart['video_id'],'upload_status' => 1, 'video_status' => 1])
                        ->andWhere('find_in_set('.$cart['goods_id'].',related_good)')
                        ->one();
                    if($video_model){
                        $cache_name = 'huadi_video_related_cart_' . $_cart_id;
                        cache($cache_name, $cart['video_id'], 86400);
                    }

                }
            }

            $transaction->commit();
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['cart_id' => $cart_id]);
        } catch (\Exception $e) {
            \Yii::error($e->getMessage());
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }
    }

    /**
     * 自选套餐
     */
    public function actionMonthPack()
    {
        $param = \Yii::$app->request->post();
        //首次送达日期
        $first_date = (string)$param['first_date'];

        // 日期重定向
        if ($first_date == '2019-09-28') {
            $first_date = '2019-09-30';
        } elseif ($first_date == '2019-09-29') {
            $first_date = '2019-10-05';
        }

        if (Orders::checkFirstTime($first_date) == false) {
            return $this->responseJson(Message::ERROR, Orders::instance()->getFirstError(Message::MODEL_ERROR));
        }
        //4个自选商品
        $all_goods_id = (array)$param['goods_id'];
        $goods_model = new Goods();
        $map['goods_id'] = $all_goods_id;
        $map['gc_id_2'] = Goods::FLOWER_HOME;
        $map['goods_state'] = Goods::GOODS_STATE_OK;
        $all_goods_list = $goods_model->getGoodsList($map);
        $goods_list = [];
        //1.兼容4个产品会出现相同的情况
        //2.以用户选择的先后顺序进行下单
        foreach ($all_goods_id as $goods_id) {
            foreach ($all_goods_list as $goods) {
                if ($goods_id == $goods['goods_id']) {
                    $goods_list[] = $goods;
                    break;
                }
            }
        }
        if (count($goods_list) != 4) {
            return $this->responseJson(Message::ERROR, '套餐已失效，暂无法购买');
        }
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            //获取4次送达时间
            $next_days = getNextDeliveryDays($first_date);
            //生成一个临时套餐ID
            $hash_id = md5(uniqid($this->sessionid, true));
            foreach ($goods_list as $i => $goods) {
                $data = [];
                $data['hash_id'] = $hash_id;
                $data['cart_show'] = 0;
                $data['cart_price'] = $goods['goods_monthprice'];
                if ($this->isLogin()) {
                    $data['buyer_id'] = $this->member_id;
                } else {
                    $data['ssid'] = $this->sessionid;
                }
                $data['gc_id'] = $goods['gc_id'];
                $data['goods_type'] = GOODS_TYPE_HOME_FLOWER;
                $data['gc_name'] = isset($goods['gc_name'])?$goods['gc_name']:'';
                $data['goods_id'] = $goods['goods_id'];
                $data['goods_name'] = isset($goods['goods_name'])?$goods['goods_name']:'';
                $data['goods_material'] = $goods['goods_material'];
                $data['goods_price'] = $goods['ahj_goods_price'];
                $data['goods_num'] = 1;
                $data['goods_image'] = isset($goods['goods_image'])?$goods['goods_image']:'';
                $data['delivery_time'] = $next_days[$i];
                $data['time'] = TIMESTAMP;
                $cart_model = new Cart();
                $cart_model->setAttributes($data);
                $result = $cart_model->insert(false);
                if (!$result) {
                    throw new \Exception('下单失败，请重试');
                }
            }
            $transaction->commit();
            $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['hash_id' => $hash_id]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }
    }

    /**
     * 包月组合套餐
     */
    public function actionMonthCombo()
    {
        $param = \Yii::$app->request->post();
        //首次送达日期
        $first_date = (string)$param['first_date'];
        if (Orders::checkFirstTime($first_date) == false) {
            return $this->responseJson(Message::ERROR, Orders::instance()->getFirstError(Message::MODEL_ERROR));
        }
        //套餐ID
        $bl_id = (int)$param['bl_id'];

        // 规格月份
        $month = isset($param['month']) ? (int)$param['month'] : 1;
        // 套餐加购商品id
        $sku_goods_id_jg = isset($param['goods_id_jg']) && $param['goods_id_jg'] ? explode(',',$param['goods_id_jg']) : 0;
        // 套餐赠送商品id
        $sku_goods_id_zs = isset($param['goods_id_zs']) && $param['goods_id_zs'] ? explode(',',$param['goods_id_zs']) : 0;

        $pb_model = new PBundling();
        $pb_goods = new PBundlingGoods();
        $goods_model = new Goods();
        $map = [];
        $map['bl_id'] = $bl_id;
        $map['bl_type'] = PBundling::BUNDLING_2;
        $map['bl_state'] = PBundling::P_STATE_OK;
        $map['is_delete'] = 0;
        $p_info = $pb_model->getBundlingInfo($map);
        if (!$p_info) {
            return $this->responseJson(Message::ERROR, '套餐已过期或下架');
        }
        $map = [];
        $map['bl_id'] = $bl_id;
        $map['is_delete'] = 0;
        $p_goods_list = $pb_goods->getBundlingGoodsList($map);
        if (!$p_goods_list) {
            return $this->responseJson(Message::ERROR, '套餐已过期或下架(0x1100)');
        }

        // 验证加购和赠送商品
        if (SITEID == 258) {
            $res_sku = $this->_checkMonthComboSkuGoods($bl_id, $month, $sku_goods_id_jg, $sku_goods_id_zs);
            if (empty($res_sku)) {
                return $this->responseJson(Message::ERROR, '选择的加购和赠送的商品错误，暂无法下单');
            }
        }

        $map = [];
        $map['goods_id'] = array_column($p_goods_list, 'goods_id');
        $map['goods_state'] = Goods::GOODS_STATE_OK;
        $map['goods_verify'] = Goods::GOODS_VERIFY_OK;
        $goods_list = $goods_model->getGoodsList($map);
        if (count($p_goods_list) != count($goods_list)) {
            return $this->responseJson(Message::ERROR, '套餐中有产品已过期，暂无法下单');
        }
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            //获取4次送达时间
            $next_days = getNextDeliveryDays($first_date, 100);
            //生成一个临时套餐ID
            $hash_id = md5(uniqid($this->sessionid, true));
            //为了不打乱包月花送花顺序
            // 包月花有规格，月份
            $n = 0;
            for ($j = 1 ; $j <= $month; $j++) {
                foreach ($p_goods_list as $i => $pgoods) {
                    $data = [];
                    $data['hash_id'] = $hash_id;
                    $data['cart_show'] = 0;
                    if ($this->isLogin()) {
                        $data['buyer_id'] = $this->member_id;
                    } else {
                        $data['ssid'] = $this->sessionid;
                    }
                    $data['goods_type'] = GOODS_TYPE_HOME_FLOWER;
                    foreach ($goods_list as $goods) {
                        if ($goods['goods_id'] == $pgoods['goods_id']) {
                            $data['cart_price'] = $pgoods['bl_goods_price'];
                            $data['goods_num'] = 1;
                            $data['gc_id'] = $goods['gc_id'];
                            $data['gc_name'] = '包月鲜花';
                            $data['goods_id'] = $goods['goods_id'];
                            $data['goods_name'] = $goods['goods_name'];
                            $data['goods_material'] = $goods['goods_material'];
                            $data['goods_price'] = $goods['ahj_goods_price'];
                            $data['goods_image'] = $goods['goods_image'];
                            break;
                        }
                    }
                    $data['delivery_time'] = $next_days[$n];
                    $data['bl_id'] = $bl_id;
                    $data['time'] = TIMESTAMP;
                    $cart_model = new Cart();
                    $cart_model->setAttributes($data);
                    $result = $cart_model->insert(false);
                    if (!$result) {
                        throw new \Exception('下单失败，请重试');
                    }
                    $n++;
                }
            }

            // 包月花有加购和赠送，将加购和赠送订单信息提交到chart中
            $hash_id_jg_zs = '';
            if (SITEID == 258) {
                $hash_id_jg_zs = md5(uniqid($this->sessionid, true));
                $this->_insertMonthComboSkuCart($bl_id, $hash_id_jg_zs, $month, $sku_goods_id_jg, GOODS_TYPE_JIA_GOU);
                $this->_insertMonthComboSkuCart($bl_id, $hash_id_jg_zs, $month, $sku_goods_id_zs, GOODS_TYPE_ZENG_SONG);
            }

            $transaction->commit();
            $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['hash_id' => $hash_id, 'hash_id_jg_zs' => $hash_id_jg_zs]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }
    }
    /**
     * 优惠组合套餐(普通组合)
     */
    public function actionPbundlingCombo()
    {
        $param = \Yii::$app->request->post();
        //套餐ID
        $bl_id = (int)$param['bl_id'];
        $pb_model = new PBundling();
        $pb_goods = new PBundlingGoods();
        $goods_model = new Goods();
        $map = [];
        $map['bl_id'] = $bl_id;
        $map['bl_type'] = PBundling::BUNDLING_1;
        $map['bl_state'] = PBundling::P_STATE_OK;
        $map['is_delete'] = 0;
        $p_info = $pb_model->getBundlingInfo($map);
        if (!$p_info) {
            return $this->responseJson(Message::ERROR, '套餐已过期或下架');
        }
        $map = [];
        $map['bl_id'] = $bl_id;
        $map['is_delete'] = 0;
        $p_goods_list = $pb_goods->getBundlingGoodsList($map);
        if (!$p_goods_list) {
            return $this->responseJson(Message::ERROR, '套餐已过期或下架(0x1100)');
        }
        $map = [];
        $map['goods_id'] = array_column($p_goods_list, 'goods_id');
        $map['goods_state'] = Goods::GOODS_STATE_OK;
        $map['goods_verify'] = Goods::GOODS_VERIFY_OK;
        $goods_list = $goods_model->getGoodsList($map);
        if (count($p_goods_list) != count($goods_list)) {
            return $this->responseJson(Message::ERROR, '套餐中有产品已过期，暂无法下单');
        }
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            //生成一个临时套餐ID
            $hash_id = md5(uniqid($this->sessionid, true));
            $return_cart_ids = [];
            foreach ($p_goods_list as $i => $pgoods) {
                $data = [];
                $data['hash_id'] = $hash_id;
                $data['cart_show'] = 0;
                if ($this->isLogin()) {
                    $data['buyer_id'] = $this->member_id;
                } else {
                    $data['ssid'] = $this->sessionid;
                }
                $data['goods_type'] = GOODS_TYPE_BUNDLING_FLOWER;
                foreach ($goods_list as $goods) {
                    if ($goods['goods_id'] == $pgoods['goods_id']) {
                        $data['cart_price'] = $pgoods['bl_goods_price'];
                        $data['goods_num'] = 1;
                        $data['gc_id'] = $goods['gc_id'];
                        $data['gc_name'] = '优惠套装';
                        $data['goods_id'] = $goods['goods_id'];
                        $data['goods_name'] = $goods['goods_name'];
                        $data['goods_material'] = $goods['goods_material'];
                        $data['goods_price'] = $goods['ahj_goods_price'];
                        $data['goods_image'] = $goods['goods_image'];
                        break;
                    }
                }
                $data['bl_id'] = $bl_id;
                $data['time'] = TIMESTAMP;
                $cart_model = new Cart();
                $cart_model->setAttributes($data);
                $result = $cart_model->insert(false);
                if (!$result) {
                    throw new \Exception('下单失败，请重试');
                }
                $return_cart_ids[] = $cart_model->cart_id;
            }
            $transaction->commit();
            $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['cart_id' => $return_cart_ids]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }
    }

    /**
     * 验证加购和赠送商品是否正确
     * @param $bl_id
     * @param $month
     * @param $sku_goods_id_jg
     * @param $sku_goods_id_zs
     * @return array
     */
    private function _checkMonthComboSkuGoods($bl_id, $month, $sku_goods_id_jg, $sku_goods_id_zs)
    {
        $res = ['goods_jg' => [], 'goods_zs' => []];
        if (!$sku_goods_id_jg && !$sku_goods_id_zs) {
            return true;
        }
        if ($sku_goods_id_jg) {
            foreach ($sku_goods_id_jg as $goods_id){
                // 验证加购商品
                $goods = PBundlingSkuGoods::getGoodsByMonth($bl_id, $month, PBundlingSkuGoods::TYPE_JG, $goods_id);
                if (!$goods) {
                    return [];
                }
                $res['goods_jg'][] = $goods;
            }

        }

        if ($sku_goods_id_zs) {
            foreach ($sku_goods_id_zs as $goods_id) {
                // 验证赠送商品
                $goods = PBundlingSkuGoods::getGoodsByMonth($bl_id, $month, PBundlingSkuGoods::TYPE_ZS, $goods_id);
                if (!$goods) {
                    return [];
                }
                $res['goods_zs'][] = $goods;
            }
        }
        return $res;
    }

    private function _insertMonthComboSkuCart($bl_id, $hash_id_jg_zs, $month, $goods_id, $type)
    {
        if (!$goods_id) {
            return '';
        }
        //生成一个临时套餐ID
        $hash_id = $hash_id_jg_zs;
        //为了不打乱包月花送花顺序
        if ($type == GOODS_TYPE_JIA_GOU) {
            $gc_name = '包月鲜花加购商品';
        } else {
            $gc_name = '包月鲜花赠送商品';
        }

        $goods_model = new Goods();
        $map = [];
        $map['goods_state'] = Goods::GOODS_STATE_OK;
        $map['goods_verify'] = Goods::GOODS_VERIFY_OK;
        $where = ["in", "goods_id", $goods_id];
        $map = ["and", $map, $where];
        $goods_list = $goods_model->getGoodsList($map);
        foreach ($goods_list as $goods) {
            $data = [];
            $data['hash_id'] = $hash_id;
            $data['cart_show'] = 0;
            if ($this->isLogin()) {
                $data['buyer_id'] = $this->member_id;
            } else {
                $data['ssid'] = $this->sessionid;
            }
            $data['goods_type'] = $type;
            $data['goods_num'] = 1;
            $data['gc_id'] = $goods['gc_id'];
            $data['gc_name'] = $gc_name;
            $data['goods_id'] = $goods['goods_id'];
            $data['goods_name'] = $goods['goods_name'];
            $data['goods_material'] = $goods['goods_material'];
            $data['goods_image'] = $goods['goods_image'];
            if ($type == GOODS_TYPE_JIA_GOU) {
                $data['cart_price'] = $goods['goods_price'];
                $data['goods_price'] = $goods['goods_price'];
            } else {    // 赠送商品价格为0
                $data['cart_price'] = 0;
                $data['goods_price'] = 0;
            }
            $data['delivery_time'] = 0;
            $data['bl_id'] = $bl_id;
            $data['extension_id'] = $month;
            $data['time'] = TIMESTAMP;

            $cart_model = new Cart();
            $cart_model->setAttributes($data);
            $result = $cart_model->insert(false);
            if (!$result) {
                throw new \Exception('下单失败，请重试');
            }
        }
        return $hash_id;
    }

    /**
     * 花材加入购物车
     */
    public function actionMaterial()
    {
        $param = \Yii::$app->request->post();
        $bl_id = (int)$param['bl_id'];
        $goods_id = (int)$param['goods_id'];
        $quantity = (int)$param['quantity'];
        if ((!$bl_id && !$goods_id)) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        $goods_model = new Goods();
        $goods_list = $p_goods_list = [];
        if ($bl_id) {
            $pb_model = new PBundling();
            $pb_goods = new PBundlingGoods();
            $map = [];
            $map['bl_id'] = $bl_id;
            $map['bl_state'] = PBundling::P_STATE_OK;
            $map['is_delete'] = 0;
            $p_info = $pb_model->getBundlingInfo($map);
            if (!$p_info) {
                return $this->responseJson(Message::ERROR, '套餐已过期或下架');
            }
            $map = [];
            $map['bl_id'] = $bl_id;
            $map['is_delete'] = 0;
            $p_goods_list = $pb_goods->getBundlingGoodsList($map);
            if (!$p_goods_list) {
                return $this->responseJson(Message::ERROR, '套餐已过期或下架(0x1100)');
            }
            $map = [];
            $map['goods_id'] = array_column($p_goods_list, 'goods_id');
            $map['gc_id_2'] = Goods::FLOWER_MATERIAL;
            $map['goods_state'] = Goods::GOODS_STATE_OK;
            $map['goods_verify'] = Goods::GOODS_VERIFY_OK;
            $goods_list = $goods_model->getGoodsList($map);
            if (count($p_goods_list) != count($goods_list)) {
                return $this->responseJson(Message::ERROR, '套餐中有产品已过期，暂无法下单');
            }
        } else {
            $map = [];
            $map['goods_id'] = $goods_id;
            $map['gc_id_2'] = Goods::FLOWER_MATERIAL;
            $map['goods_state'] = Goods::GOODS_STATE_OK;
            $map['goods_verify'] = Goods::GOODS_VERIFY_OK;
            $goods_info = $goods_model->getGoodsInfo($map);
            if (!$goods_info) {
                return $this->responseJson(Message::ERROR, '很抱歉，您所购买的商品已下架');
            }
            $goods_list[] = $goods_info;
            $p_goods_list[] = [
                'goods_id' => $goods_info['goods_id'],
                'bl_goods_price' => $goods_info['ahj_goods_price'],
                'goods_num' => 1,
            ];
        }
        $hash_id = '';
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $map = [];
            if ($this->isLogin()) {
                $map['buyer_id'] = $this->member_id;
            } else {
                $map['ssid'] = $this->sessionid;
            }
            $map['goods_type'] = GOODS_TYPE_MATERIAL_FLOWER;
            //验证是否已加入过
            $delete_map = $map;
            $delete_map['goods_id'] = $goods_id;
            $delete_map['bl_id'] = $bl_id;
            if ($bl_id) {
                unset($delete_map['goods_id']);
            }
            if ($quantity == 0 || Cart::find()->where($delete_map)->count()) {
                $result = Cart::deleteAll($delete_map);
                if (!$result) {
                    throw new \Exception($quantity ? '删除失败' : '加入失败');
                }
            }
            if ($quantity > 0) {
                foreach ($goods_list as $i => $goods) {
                    $data = [];
                    $data['cart_show'] = 0;
                    foreach ($p_goods_list as $pgoods) {
                        if ($goods['goods_id'] == $pgoods['goods_id']) {
                            $data['cart_price'] = $pgoods['bl_goods_price'];
                        }
                    }
                    $data['goods_num'] = $quantity;
                    if ($this->isLogin()) {
                        $data['buyer_id'] = $this->member_id;
                    } else {
                        $data['ssid'] = $this->sessionid;
                    }
                    $data['gc_id'] = $goods['gc_id'];
                    $data['goods_type'] = GOODS_TYPE_MATERIAL_FLOWER;
                    $data['gc_name'] = '花材';
                    $data['goods_id'] = $goods['goods_id'];
                    $data['goods_name'] = $goods['goods_name'];
                    $data['goods_material'] = $goods['goods_material'];
                    $data['goods_price'] = $goods['ahj_goods_price'];
                    $data['goods_image'] = $goods['goods_image'];
                    $data['bl_id'] = $bl_id;
                    $data['time'] = TIMESTAMP;
                    $cart_model = new Cart();
                    $cart_model->setAttributes($data);
                    $result = $cart_model->insert(false);
                    if (!$result) {
                        throw new \Exception('加入购物车失败，请重试');
                    }
                }
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }

        //实时计算最新数据给前端
        return \Yii::$app->runAction('material-flower/trade');
    }

    /**
     * 花材购物车清空
     */
    public function actionMaterialClear()
    {
        $map = [];
        if ($this->isLogin()) {
            $map['buyer_id'] = $this->member_id;
        } else {
            $map['ssid'] = $this->sessionid;
        }
        $map['goods_type'] = GOODS_TYPE_MATERIAL_FLOWER;
        if (Cart::find()->where($map)->count()) {
            $result = Cart::deleteAll($map);
            if (!$result) {
                $this->responseJson(Message::ERROR, '清空失败');
            }
        }
        //实时计算最新数据给前端
        return \Yii::$app->runAction('material-flower/trade');
    }

    /**
     * 编辑购物车
     */
    public function actionEdit()
    {
        $param = \Yii::$app->request->post();
        $cart_id = isset($param['cart_id'])?(int)$param['cart_id']:0;
        $quantity = isset($param['quantity'])?(int)$param['quantity']:0;
        if (!$cart_id || !$quantity) {
            return $this->responseJson(Message::EMPTY_MSG, Message::ERROR_MSG);
        }
        $cart = Cart::findOne($cart_id);
        if (empty($cart)) {
            return $this->responseJson(Message::ERROR, '购物车不存在或已删除');
        }
        if ($this->isLogin() && $cart->buyer_id != $this->member_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        if (!$this->isLogin() && $cart->ssid != $this->sessionid) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        //19年感恩节限时购加购物车添加检查
        if(SITEID == 258){
            $checkResult = (new PXianshiGoods())->checkXianshi($this->member_id,$cart->goods_id,$quantity);
            if($checkResult['code'] != 1){
                return $this->responseJson($checkResult['code'], $checkResult['msg']);
            }
        }
        $cart->goods_num = $quantity;
        $result = $cart->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->actionList();
    }

    /**
     * 删除购物车
     */
    public function actionDel()
    {
        $param = \Yii::$app->request->post();
        $cart_id = $param['cart_ids'];
        if (!$cart_id) {
            return $this->responseJson(Message::EMPTY_MSG, Message::ERROR_MSG);
        }
        $condition = [];
        $condition['cart_id'] = $cart_id;
        if ($this->isLogin()) {
            $condition['buyer_id'] = $this->member_id;
        } else {
            $condition['ssid'] = $this->sessionid;
        }
        $result = Cart::deleteAll($condition);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->actionList();
    }

    /**
     * 购物车统计
     * @return mixed
     */
    public function actionCalc()
    {
        $model_cart = new Cart();
        // 获取用户购物车
        if ($this->isLogin()) {
            $cart_list = $model_cart->getCartByMember($this->member_id);
        } else {
            $cart_list = $model_cart->getCartBySsid($this->sessionid);
        }
        // 获取购物车商品最新信息
        $cart_list = $model_cart->_getOnlineCartList($cart_list);
        // 分离已失效产品并将正常状态的产品分类汇总
        $cart_list = $model_cart->_groupOnlineCartList($cart_list);

        //得到购物车汇总
        $data['cart_total'] = $model_cart->_getCartTotal($cart_list);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 购物车选择首次送达时间
     * @return mixed
     */
    public function actionEditTime()
    {
        $param = \Yii::$app->request->post();
        $cart_id = (int)$param['cart_id'];
        $first_date = (string)$param['first_date'];
        if (!$cart_id) {
            return $this->responseJson(Message::EMPTY_MSG, Message::ERROR_MSG);
        }
        if (!Orders::checkTime($first_date)) {
            return $this->responseJson(Message::ERROR, Orders::instance()->getFirstError(Message::MODEL_ERROR));
        }
        $cart = Cart::findOne($cart_id);
        if (empty($cart)) {
            return $this->responseJson(Message::ERROR, '购物车不存在或已删除');
        }
        if ($this->isLogin() && $cart->buyer_id != $this->member_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        if (!$this->isLogin() && $cart->ssid != $this->sessionid) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        if ($cart->goods_type != GOODS_TYPE_HOME_FLOWER) {
            return $this->responseJson(Message::ERROR, '非家居花产品不能修改送达时间');
        }
        $cart->cart_price = Orders::checkUnset($first_date) ? $cart->goods_price : priceFormat($cart->goods_price * HOME_FLOWER_UNSET_RATE);
        $cart->delivery_time = strtotime($first_date);

        $result = $cart->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->actionList();
    }

    /**
     * 购物车修改选择花店
     * @return mixed
     */
    public function actionEditStore()
    {
        $param = \Yii::$app->request->post();
        $cart_id = (int)$param['cart_id'];
        $store_id = (int)$param['store_id'];
        $store_name = (string)$param['store_name'];
        if (!$cart_id || !$store_id || !$store_name) {
            return $this->responseJson(Message::EMPTY_MSG, Message::ERROR_MSG);
        }
        $cart = Cart::findOne($cart_id);
        if (empty($cart)) {
            return $this->responseJson(Message::ERROR, '购物车不存在或已删除');
        }
        if ($this->isLogin() && $cart->buyer_id != $this->member_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        if (!$this->isLogin() && $cart->ssid != $this->sessionid) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        if ($cart->goods_type != GOODS_TYPE_FLOWER) {
            return $this->responseJson(Message::ERROR, '非礼品花产品不能修改报价');
        }
        $addprice = new GoodsAddprice();
        $goods_price = $addprice->getAddPrice($cart->goods_id, $store_id);
        if (!$goods_price) {
            return $this->responseJson(Message::ERROR, $addprice->getFirstError('error'));
        }
        $cart->cart_price = $goods_price;
        $cart->store_id = $store_id;
        $cart->store_name = $store_name;
        $result = $cart->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->actionList();
    }
    private function _syncCartGoodsPrice(){
        //缓存控制最短五分钟同步一次
        $cache_key = 'cart_sync_timeout_' . $this->sessionid;
        $timeout = cache($cache_key);
        if(!$timeout){
//        if(true){
            // 获取用户购物车
            $model_cart = new Cart();
            if ($this->isLogin()) {
                $cart_list = $model_cart->getCartByMember($this->member_id);
            } else {
                $cart_list = $model_cart->getCartBySsid($this->sessionid);
            }
            //是否年卡专享商品
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
            //20200927,购物车列表同步只刷新同步商品原价
            foreach($cart_list as $cart){
                $goods_info = Goods::findOne(['goods_id' => $cart['goods_id']]);
                if($goods_info){
                    if($year_card_member && in_array($goods_info['goods_id'],$year_card_goods_ids)){
                        $data['goods_price'] = $year_card_id_arr[$goods_info['goods_id']];
                        $data['cart_price'] = $year_card_id_arr[$goods_info['goods_id']];
                    }else{
                        $data['goods_price'] = FinalPrice::format($goods_info['ahj_goods_price']);
                        $data['cart_price'] =  FinalPrice::format($goods_info['ahj_goods_price']);
                    }
                    Cart::updateAll($data,['cart_id' => $cart['cart_id']]);
                }
            }

            cache($cache_key,true,600);
        }

        return true;

    }
}
