<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Goods;
use common\models\GoodsAttrIndex;
use common\models\HuadiYearCardHelpVoucher;
use common\models\HuadiYearCardOrders;
use common\models\Orders;
use common\models\Setting;
use common\models\Voucher;
use common\models\VoucherTemplate;
use foo\bar;
use yii\web\HttpException;

/**
 * OrderController
 */
class YearCardController extends BaseController
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
     * 省钱年卡首页
     * 优惠券模板状态   1 可领取  2 可使用  3 已使用  4 不可领取(未开通)
     */
    public function actionYearCardIndex(){
        $model_huadi_year_card_orders = new HuadiYearCardOrders();
        $year_card_member = $model_huadi_year_card_orders->getYearCardStateById($this->member_id);
        $voucher_template_ids = array_merge($model_huadi_year_card_orders->voucher_template_ids,$model_huadi_year_card_orders->day_voucher_template_ids,$model_huadi_year_card_orders->month_voucher_template_ids,$model_huadi_year_card_orders->large_voucher_template_ids,$model_huadi_year_card_orders->full_consumption_voucher_ids);
        $voucher_template_list = VoucherTemplate::instance()->getVoucherTemplateList(['voucher_t_id'=>$voucher_template_ids],'voucher_t_id,voucher_t_title,voucher_t_desc,voucher_term_of_validity,voucher_t_price,voucher_t_limit,voucher_url_type');
        $voucher_list = Voucher::instance()->getVoucherList(['voucher_t_id'=>$voucher_template_ids,'voucher_owner_id'=>$this->member_id],"voucher_id,voucher_t_id,voucher_start_date,voucher_end_date,voucher_active_date,voucher_state,voucher_url_type,voucher_is_ahj,voucher_url","voucher_active_date desc",0,5000);
        $open_t_vouchers = $day_t_vouchers = $month_t_vouchers = $large_t_vouchers = $full_consumption_t_vouchers = [];
        $day_start = strtotime(date('Y-m-d 00:00:00'));
        $day_end = strtotime(date('Y-m-d 23:59:59'));
        $month_start = strtotime(date('Y-m-01 00:00:00'));
        $month_end = strtotime(date("Y-m-01 00:00:00",strtotime("+1 month")));

        //获取用户累计消费金额
        $progressive_consumption_amount = Orders::instance()->getMemberPCAmountById($this->member_id);
        foreach ($voucher_template_list as $template_info){
            $template_info['v_id'] = 0;
            $template_info['v_url_type'] = 0;
            $template_info['v_url'] = '';
            if($template_info['voucher_t_id'] == 1810){
                $template_info['full_consumption_price'] = 699;
            }elseif($template_info['voucher_t_id'] == 1811){
                $template_info['full_consumption_price'] = 499;
            }elseif($template_info['voucher_t_id'] == 1812){
                $template_info['full_consumption_price'] = 399;
            }elseif($template_info['voucher_t_id'] == 1813){
                $template_info['full_consumption_price'] = 199;
            }
            if($year_card_member){
                foreach ($voucher_list as $voucher){
                    //开卡领券与消费满减券 都是与年卡时效期相同
                    if($template_info['voucher_t_id'] == $voucher['voucher_t_id']){
                        if(in_array($voucher['voucher_t_id'],array_merge($model_huadi_year_card_orders->voucher_template_ids,$model_huadi_year_card_orders->full_consumption_voucher_ids))){
                            if($voucher['voucher_start_date'] >= $year_card_member['payment_time'] && $voucher['voucher_end_date'] <= $year_card_member['expired_time']){
                                if($voucher['voucher_state'] == 1){
                                    $template_info['v_state'] = 2;
                                    $template_info['v_id'] = $voucher['voucher_id'];
                                    $template_info['v_url_type'] = $voucher['voucher_url_type'];
                                    $template_info['v_url'] = $voucher['voucher_url'];
                                }else{
                                    $template_info['v_state'] = 3;
                                }
                            }else{
                                //消费满减券 判断是否符合要求   符合则直接领取
                                if(in_array($voucher['voucher_t_id'],$model_huadi_year_card_orders->full_consumption_voucher_ids)){
                                    $result = $model_huadi_year_card_orders::instance()->getFullConsumptionVoucher($this->member_id,$voucher['voucher_t_id'],$progressive_consumption_amount,$year_card_member);
                                    if($result){
                                        $template_info['v_state'] = 2;
                                    }else{
                                        $template_info['v_state'] = 4;
                                    }
                                }else{
                                    $template_info['v_state'] = 1;
                                }
                            }
                        }

                        //每日领券 时效期  天
                        if(in_array($voucher['voucher_t_id'],$model_huadi_year_card_orders->day_voucher_template_ids)){
                            if($voucher['voucher_active_date'] >= $day_start && $voucher['voucher_active_date'] <= $day_end){
                                if($voucher['voucher_state'] == 1){
                                    $template_info['v_state'] = 2;
                                    $template_info['v_id'] = $voucher['voucher_id'];
                                    $template_info['v_url_type'] = $voucher['voucher_url_type'];
                                    $template_info['v_url'] = $voucher['voucher_url'];
                                }else{
                                    $template_info['v_state'] = 3;
                                }
                            }else{
                                $template_info['v_state'] = 1;
                            }
                        }

                        //每月领券与大额满减券 时效期相同  月
                        if(in_array($voucher['voucher_t_id'],array_merge($model_huadi_year_card_orders->month_voucher_template_ids,$model_huadi_year_card_orders->large_voucher_template_ids))){
                            if($voucher['voucher_active_date'] >= $month_start && $voucher['voucher_active_date'] <= $month_end){
                                if($voucher['voucher_state'] == 1){
                                    $template_info['v_state'] = 2;
                                    $template_info['v_id'] = $voucher['voucher_id'];
                                    $template_info['v_url_type'] = $voucher['voucher_url_type'];
                                    $template_info['v_url'] = $voucher['voucher_url'];
                                }else{
                                    $template_info['v_state'] = 3;
                                }
                                $template_info['v_month_number'] = intval(date('m',$voucher['voucher_active_date']));
                            }else{
                                $template_info['v_state'] = 1;
                            }
                        }
                        break;
                    }else{
                        $template_info['v_state'] = 1;
                    }
                }
            }else{
                $template_info['v_state'] = 4;
            }
            if(in_array($template_info['voucher_t_id'],$model_huadi_year_card_orders->voucher_template_ids)){
                $open_t_vouchers[] = $template_info;
            }
            if(in_array($template_info['voucher_t_id'],$model_huadi_year_card_orders->day_voucher_template_ids)){
                $day_t_vouchers[] = $template_info;
            }
            if(in_array($template_info['voucher_t_id'],$model_huadi_year_card_orders->month_voucher_template_ids)){
                $month_t_vouchers[] = $template_info;
            }
            if(in_array($template_info['voucher_t_id'],$model_huadi_year_card_orders->large_voucher_template_ids)){
                $large_t_vouchers[] = $template_info;
            }
            if(in_array($template_info['voucher_t_id'],$model_huadi_year_card_orders->full_consumption_voucher_ids)){
                $full_consumption_t_vouchers[] = $template_info;
            }
        }
        //默认底部年卡专享价商品列表  默认礼品花类
        $where = [];
        $config = Setting::instance()->getAll();
        $huadi_year_card_goods_info = $config['huadi_year_card'] ? unserialize($config['huadi_year_card']) : '';
        $where["goods.goods_id"] = array_keys($huadi_year_card_goods_info);
        $where["goods.gc_id_2"] = Goods::FLOWER_GIFT;
        $goods_list = $model_huadi_year_card_orders->getYearCardGoodsSort($where,$huadi_year_card_goods_info);
        $is_year_card = [
            'is_year_card' => 0,
            'carousel_data' => $model_huadi_year_card_orders->getCarouselData()
        ];

        $year_condition = [
            'member_id' => $this->member_id,
            'payment_state'       => 1
        ];
        $is_year_card_member = HuadiYearCardOrders::find()->where($year_condition)->orderBy('id desc')->asArray()->one();
        if($is_year_card_member){
            $is_year_card['is_year_card']  = 1;
            $is_year_card['expired_time'] = date('Y.m.d',$is_year_card_member['expired_time']);
            $is_year_card['expired_time_int'] = $is_year_card_member['expired_time'];
            $is_year_card['now_time_int'] = TIMESTAMP;
            $is_year_card['no_threshold_count'] = Voucher::instance()->getVoucherCount(['voucher_t_id'=>$model_huadi_year_card_orders->voucher_template_ids,'voucher_owner_id'=>$this->member_id]);
            $is_year_card['large_t_vouchers_count'] = 48;
            $is_year_card['full_consumption_t_vouchers_count'] = 4;
            $is_year_card['discount_count'] = intval(Voucher::find()->where(['voucher_t_id'=>$voucher_template_ids,'voucher_owner_id'=>$this->member_id,'voucher_state'=>2])->sum('voucher_price'));
        }
        $data = [
            "open_t_vouchers" => $open_t_vouchers,
            "day_t_vouchers" => $day_t_vouchers,
            "month_t_vouchers" => $month_t_vouchers,
            "large_t_vouchers" => $large_t_vouchers,
            "full_consumption_t_vouchers" => $full_consumption_t_vouchers,
            "goods_list" => $goods_list,
            "is_year_card"=>$is_year_card,
            "today_countdown_seconds" => $day_end - TIMESTAMP,
            "year_card_price" => HuadiYearCardOrders::YEAR_CARD_PRICE,
            "year_card_market_price" => HuadiYearCardOrders::YEAR_CARD_MARKET_PRICE,
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取年卡专享价商品
     */
    public function actionYearCardGoodsList(){
        $huadi_year_card_model = new HuadiYearCardOrders();
        $param = \Yii::$app->request->post();
        $special_type = isset($param['special_type']) ? (int)$param['special_type'] : 0;
        $cat_id = isset($param['cat_id']) ? (int)$param['cat_id'] : 0;
        if($cat_id == 671){
            $cat_id = 752;  //因为被删除  又不想改前端影响发版 故‘送长辈’强转
        }
        $page = isset($param['page']) ? (int)$param['page'] : 1;
        $pagesize = 10;
        $special_type = $special_type > 5 ? 0 : $special_type;
        $where = [];
        $config = Setting::instance()->getAll();
        $huadi_year_card_goods_info = $config['huadi_year_card'] ? unserialize($config['huadi_year_card']) : '';
        $where["goods.goods_id"] = array_keys($huadi_year_card_goods_info);
        if ($cat_id > 0) {
            $query = GoodsAttrIndex::find();
            $goods_ids = $query->select('goods_id')->where(array("attr_value_id" => $cat_id))->select("goods_id")->asArray()->all();
            if ($goods_ids) {
                $cat_goods_ids = array_column($goods_ids, 'goods_id');
                $where["goods.goods_id"] = array_intersect($huadi_year_card_goods_info,$cat_goods_ids);
            }
        }

        switch ($special_type){
            case 0:
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
            case 1:
                $where["goods.gc_id_2"] = Goods::FLOWER_GIFT;
                break;
            case 2:
                $where["goods.gc_id_2"] = Goods::FLOWER_HOME;
                break;
            case 3:
                $where["goods.gc_id_2"] = Goods::FLOWER_LVZHI;
                break;
            case 4:
                $where["goods.gc_id_2"] = Goods::FLOWER_BASKET;
                break;
            case 5:
                $where["goods.gc_id_2"] = Goods::FLOWER_MATERIAL;
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
        $data = $huadi_year_card_model->getYearCardGoodsSort($where,$huadi_year_card_goods_info,$page,$pagesize);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
    /**
     * 提交年卡订单
     */
    public function actionYearCardUnified()
    {
        $param = \Yii::$app->request->post();
        $order_param = $param['order_json'];
        //年卡下单下单
        $model_huadi_year_card_order = new HuadiYearCardOrders();
        $result = $model_huadi_year_card_order->yearCardUnifiedOrder($order_param['payment_code'],$this->member_id);
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
     * 领取大额满减券接口
     */
    public function actionGetLargeVoucher(){
        $model_huadi_year_card_orders = new HuadiYearCardOrders();
        $is_year_card_member = $model_huadi_year_card_orders->getYearCardStateById($this->member_id);
        if(!$is_year_card_member){
            return $this->responseJson(Message::ERROR, "您还不是年卡用户或年卡已过期，快去开通或续费吧");
        }
        $post = \Yii::$app->request->post();
        $t_id = isset($post['t_id']) && intval($post['t_id']) > 0 ? intval($post['t_id']) : 0;
        $result = $model_huadi_year_card_orders->getLargeCoupons($this->member_id,$t_id);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_huadi_year_card_orders->getFirstError(Message::MODEL_ERROR));
        }
        $help_info = HuadiYearCardHelpVoucher::instance()->getHelpInfo($result['help_id']);
        $voucher_template_info = VoucherTemplate::instance()->getCouponInfo($t_id,'voucher_t_id,voucher_t_title,voucher_t_desc,voucher_t_price,voucher_t_limit');
        $data = [
            'help_info'=>$help_info,
            'now_time' => TIMESTAMP,
            'voucher_template_info'=>$voucher_template_info
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 助力大额满减券接口
     */
    public function actionHelpLargeVoucher(){
        $post = \Yii::$app->request->post();
        $model_huadi_year_card_orders = new HuadiYearCardOrders();
        $model_huadi_year_card_help_voucher = new HuadiYearCardHelpVoucher();
        $help_id = isset($post['help_id']) && intval($post['help_id']) > 0 ? intval($post['help_id']) : 0;
        $help_info = $model_huadi_year_card_help_voucher->getHelpInfo($help_id);
        if(!$help_info){
            return $this->responseJson(Message::ERROR, $model_huadi_year_card_help_voucher->getFirstError(Message::MODEL_ERROR));
        }
        $result = $model_huadi_year_card_orders->getLargeCoupons($help_info['help_info']['for_member_id'],$help_info['help_info']['voucher_t_id']);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_huadi_year_card_orders->getFirstError(Message::MODEL_ERROR));
        }
        if(isset($result['code']) && $result['code'] == 2){
            return $this->responseJson(2, Message::SUCCESS_MSG, $help_info);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $help_info);
    }

    /**
     * 执行助力大额满减券接口
     */
    public function actionDoHelpLargeVoucher(){
        $post = \Yii::$app->request->post();
        $model_huadi_year_card_help_voucher = new HuadiYearCardHelpVoucher();
        $help_id = isset($post['help_id']) && intval($post['help_id']) > 0 ? intval($post['help_id']) : 0;
        $help_info = $model_huadi_year_card_help_voucher->getHelpInfo($help_id);
        if(!$help_info){
            return $this->responseJson(Message::ERROR, $model_huadi_year_card_help_voucher->getFirstError(Message::MODEL_ERROR));
        }
        $result = $model_huadi_year_card_help_voucher->helpLargeCoupons($this->member_id,$help_id);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_huadi_year_card_help_voucher->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 领取消费满减券接口
     */
    public function actionFullConsumptionVoucher(){
        $model_huadi_year_card_orders = new HuadiYearCardOrders();
        $year_card_member = $model_huadi_year_card_orders->getYearCardStateById($this->member_id);
        if(!$year_card_member){
            return $this->responseJson(Message::ERROR, "您还不是年卡用户或年卡已过期，快去开通或续费吧");
        }
        $post = \Yii::$app->request->post();
        $t_id = isset($post['t_id']) && intval($post['t_id']) > 0 ? intval($post['t_id']) : 0;
        //获取用户累计消费金额
        $progressive_consumption_amount = Orders::instance()->getMemberPCAmountById($this->member_id);
        $result = $model_huadi_year_card_orders->getFullConsumptionVoucher($this->member_id,$t_id,$progressive_consumption_amount,$year_card_member);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_huadi_year_card_orders->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $result);
    }

    /**
     * 领取日优惠券
     */
    public function actionDayVoucher(){
        //每日领券 时效期  天
        $model_huadi_year_card_orders = new HuadiYearCardOrders();
        $year_card_member = $model_huadi_year_card_orders->getYearCardStateById($this->member_id);
        if(!$year_card_member){
            return $this->responseJson(Message::ERROR, "您还不是年卡用户或年卡已过期，快去开通或续费吧");
        }
        $post = \Yii::$app->request->post();
        $t_id = isset($post['t_id']) && intval($post['t_id']) > 0 ? intval($post['t_id']) : 0;
        if(in_array($t_id,$model_huadi_year_card_orders->day_voucher_template_ids)){
            $voucher_list = $model_huadi_year_card_orders->haveDayCoupons($this->member_id,$t_id);
            if($voucher_list){
                return $this->responseJson(Message::ERROR, "今日已领取过该优惠券");
            }else{
                $result = Voucher::instance()->exchangeMember($t_id,$this->member_id,'花递年卡每日领券',$year_card_member);
                if(!$result){
                    return $this->responseJson(Message::ERROR, "领取失败");
                }
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $result);
            }
        }else{
            return $this->responseJson(Message::ERROR, "参数错误");
        }
    }
}
