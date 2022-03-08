<?php

namespace frontend\controllers;

use Codeception\Module\Db;
use common\components\FinalPrice;
use common\components\HuawaApi;
use common\components\Message;
use common\models\Adv;
use common\models\Attribute;
use common\models\AttributeValue;
use common\models\CmsConstel;
use common\models\CmsConstelGoods;
use common\models\CmsConstelType;
use common\models\Goods;
use common\models\GoodsClass;
use common\models\GroupShopping;
use common\models\Member;
use common\models\Orders;
use common\models\PXianshi;
use common\models\PXianshiGoods;
use common\models\Setting;
use common\models\Voucher;
use common\models\VoucherTemplate;
use frontend\service\HomeImageServices;
use frontend\service\VoucherService;
use yii\helpers\ArrayHelper;

/**
 * HomeFlower controller
 */
class HomeFlowerController extends BaseController
{
    public function actionTest()
    {
        $settings = \common\models\Setting::instance()->getValue("huadi_icon_setting",true);
        $huadi_icon_setting = unserialize($settings);
        var_dump($huadi_icon_setting);
        exit;
    }
    public function actionIndex()
    {
        $data = [];
        //banner
        $adv_model = new Adv();
        $data['top_banner'] = $adv_model->getBanner(130);

        //为你甄选
        $goods_model = new Goods();
        $map = [];
        $map['goods_state'] = 1;
        $map['goods_verify'] = 1;
        $map['gc_id'] = Goods::FLOWER_HOME;
        $order = "sort_order desc,is_best desc,is_hot desc";
        $goods_list = $goods_model->getGoodsList($map, null, 'goods_id,goods_name,ahj_goods_price as goods_price,goods_costprice,goods_image', $order, 3);
        foreach ($goods_list as $k => $goods) {
            $goods_list[$k]['goods_price'] = FinalPrice::S($goods['goods_price']);
            unset($goods_list[$k]['goods_costprice']);
            $goods_list[$k]['goods_img'] = thumbGoods($goods['goods_image'], 150);
            $goods_list[$k]['goods_type'] = GOODS_TYPE_HOME_FLOWER;
        }
        $data['trends'] = [
            'title'        => '为你甄选',
            'sub_title'    => 'TRENDS',
            'product_list' => $goods_list,
        ];

        //办公室鲜花
        $data['office_flower'] = [
            'name'  => '办公室鲜花',
            'value' => $adv_model->getBanner(131)
        ];
        //家居鲜花
        $data['home_flower'] = [
            'name'  => '家居鲜花',
            'value' => $adv_model->getBanner(132)
        ];

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 花递1.0.14首页顶部分类数据
     */
    public function actionTopCategoryData()
    {
        $data_model = new \common\models\Data();
        $top_category = $data_model -> getTopCategory();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $top_category);
    }

    /**
     * 花递1.0.14版本新首页
     */
    public function actionV1014HomeIndex()
    {
        //热门搜索标签
        $huadi_keyword_system_set = Setting::C('huadi_keyword_system_set');
        $huadi_hotlist = [];
        if ($huadi_keyword_system_set) {
            $huadi_hotkeywords = Setting::C('huadi_hot_search_list');
            if ($huadi_hotkeywords) {
                $huadi_hotlist = unserialize($huadi_hotkeywords);
                array_multisort(array_column($huadi_hotlist, 'sort'), SORT_DESC, $huadi_hotlist);
            }
        }
        $data['hot'] = $huadi_hotlist;
        $data_model = new \common\models\Data();
        $data_model::$member_id = $this->member_id;
        //banner
        $adv_model = new Adv();
        $data['top_banner'] = $adv_model->getBanner(163);
        //app顶部banner临时处理
        if (\Yii::$app->request->post('device_type', 'applet_huadi') == 'app_huadi' && !empty($data['top_banner'])) {
            $data['top_banner'] = $this->appHideBanner($data['top_banner']);
        }
        //福利社
        $data['welfare_banner'] = $adv_model->getBanner(165);
        //最新的限时团购
        $data['group'] = GroupShopping::getNewGroup(1,10,true);
        //新品推荐
        $settings = \common\models\Setting::instance()->getValue("huadi_icon_setting", true);
        $huadi_icon_setting = unserialize($settings);
        if (!empty($huadi_icon_setting) && $huadi_icon_setting['new_goods_product'] != 0) {
            $goods_model = new \common\models\Goods();
            $where["goods.gc_id_2"] = [
                Goods::FLOWER_HOME,
                Goods::FLOWER_MATERIAL,
                Goods::FLOWER_GIFT,
            ];
            $where["goods.is_new"] = 1;
            $where["goods.goods_state"] = 1;
            $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $new_goods = $goods_model->goodslist($field, 1, 6, $where, "goods.is_new desc,goods.goods_edittime desc", 1, [], [], [], 0, 320);
            $data['new_goods'] = $new_goods;
        }

        // 顶部导航
        $data['nav'] = $data_model->getNav($this->version);
        // 分类
        $data['category_list'] = $data_model->getCategoryList($this->version);
        // 送礼鲜花分类
        $data['gift_category_list'] = $data_model->getGiftCategoryList($this->version);
        // banner 广告
        $data['ad_info'] = $data_model->getBannerAdInfo();
        // 今日特价
        $data['today_specials'] = $data_model->getTodaySpecials();
        // 首页公告
        $data['tips'] = $data_model->getTips($this->version);
        //生活花分类
        $data['life_class'] = [
            [
                "gc_id" => "591",
                "gc_name" => "办公室"
            ],
            [
                "gc_id" => "592",
                "gc_name" => "茶几餐桌"
            ],
            [
                "gc_id" => "593",
                "gc_name" => "卫生间"
            ],
        ];
        //礼品花分类
        $data['gift_class'] = [
            [
                "keyword" => "送恋人",
                'icon' => 'https://ahj.cm/data/images/special/huadi/index_2.0/lianren.png'
            ],
            [
                "keyword" => "送老婆",
                'icon' => 'https://ahj.cm/data/images/special/huadi/index_2.0/laopo.png'
            ],
            [
                "keyword" => "送父母",
                'icon' => 'https://ahj.cm/data/images/special/huadi/index_2.0/fumu.png'
            ],
            [
                "keyword" => "送长辈",
                'icon' => 'https://ahj.cm/data/images/special/huadi/index_2.0/zhangbei.png'
            ],
            [
                "keyword" => "送客户",
                'icon' => 'https://ahj.cm/data/images/special/huadi/index_2.0/kehu.png'
            ],
            [
                "keyword" => "送朋友",
                'icon' => 'https://ahj.cm/data/images/special/huadi/index_2.0/pengyou.png'
            ],
        ];
        //代金券
        // todo 单独提出-开新接口
        $data['voucher'] = VoucherService::fetchVoucherTmp($this->member_id);
        //首页领券图片
        $data['voucher_cover'] = HomeImageServices::getImgUri();
        $data['center_banner'] = HomeImageServices::getHuaDiCenterBanner();
        $data['center_banner']['adv_pic_url_app'] = htmlspecialchars_decode($data['center_banner']['adv_pic_url_app']);

        // 获取首页文字.
        $indexWords = Setting::C('huadi_index_words');
        $data['indexWords'] = unserialize($indexWords);
        $huadi_icon_setting = unserialize(Setting::C('huadi_icon_setting'));
        $data['daily_folwer']  = isset($huadi_icon_setting['daily_flower_tag']) ? $huadi_icon_setting['daily_flower_tag'] : 0;
        $data['monthly_flower']  = isset($huadi_icon_setting['monthly_flower_tag']) ? $huadi_icon_setting['monthly_flower_tag'] : 0;
        //生活花|包月花入口
        $data['pages_entrance_list'] =$data_model->getLiveAndMonthEntrance($this->version,$data);
//        $data['rank_list'] = $data_model->getGoodsRankList($this->version,'page_index');
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
    /**
     * 花递1.0.11版本新首页
     */
    public function actionHomeIndex()
    {
        //热门搜索标签
        $huadi_keyword_system_set = Setting::C('huadi_keyword_system_set',true);
        $huadi_hotlist = [];
        if($huadi_keyword_system_set){
            $huadi_hotkeywords = Setting::C('huadi_hot_search_list',true);
            if($huadi_hotkeywords){
                $huadi_hotlist = unserialize($huadi_hotkeywords);
                array_multisort(array_column($huadi_hotlist,'sort'),SORT_DESC,$huadi_hotlist);
            }
        }
        $data['hot'] = $huadi_hotlist;
        //banner
        $adv_model = new Adv();
        $data['top_banner'] = $adv_model->getBanner(163);
        //app顶部banner临时处理
        if(\Yii::$app->request->post('device_type','applet_huadi') == 'app_huadi' && !empty($data['top_banner'])){
            $data['top_banner'] = $this->appHideBanner($data['top_banner']);
        }
        //福利社
        $data['welfare_banner'] = $adv_model->getBanner(165);
        //最新的限时团购
        $data['group'] = GroupShopping::getNewGroup();
        //新品推荐
        $goods_model = new \common\models\Goods();
        $where["goods.gc_id_2"] = [
            Goods::FLOWER_HOME,
            Goods::FLOWER_MATERIAL,
            Goods::FLOWER_GIFT,
        ];
        $where["goods.is_new"] = 1;
        $where["goods.goods_state"] = 1;
        $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
        $new_goods = $goods_model->goodslist($field, 1, 6, $where, "goods.is_new desc,goods.goods_edittime desc",1,[],[],[],0,320);
        $data['new_goods'] = $new_goods;
        //生活花分类
        $data['life_class'] = [
            [
                "gc_id"   => "591",
                "gc_name" => "办公室"
            ],
            [
                "gc_id"   => "592",
                "gc_name" => "茶几餐桌"
            ],
            [
                "gc_id"   => "593",
                "gc_name" => "卫生间"
            ],
        ];
        //礼品花分类
        $data['gift_class'] = [
            [
                "keyword" => "送恋人",
                'icon'=>'https://ahj.cm/data/images/special/huadi/index_2.0/lianren.png'
            ],
            [
                "keyword" => "送老婆",
                'icon'=>'https://ahj.cm/data/images/special/huadi/index_2.0/laopo.png'
            ],
            [
                "keyword" => "送父母",
                'icon'=>'https://ahj.cm/data/images/special/huadi/index_2.0/fumu.png'
            ],
            [
                "keyword" => "送长辈",
                'icon'=>'https://ahj.cm/data/images/special/huadi/index_2.0/zhangbei.png'
            ],
            [
                "keyword" => "送客户",
                'icon'=>'https://ahj.cm/data/images/special/huadi/index_2.0/kehu.png'
            ],
            [
                "keyword" => "送朋友",
                'icon'=>'https://ahj.cm/data/images/special/huadi/index_2.0/pengyou.png'
            ],
        ];
        //代金券
        $data['voucher'] = VoucherService::fetchVoucherTmp($this->member_id);
        //首页领券图片
        $data['voucher_cover'] = HomeImageServices::getImgUri();
        $data['center_banner'] = HomeImageServices::getHuaDiCenterBanner();
        $data['center_banner']['adv_pic_url_app'] = htmlspecialchars_decode($data['center_banner']['adv_pic_url_app']);
        // 获取首页文字.
        $query = Setting::find();
        $indexWords = $query->where(array('name'=>'huadi_index_words'))->asArray()->one();
        $data['indexWords'] = unserialize($indexWords['value']);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * app顶部banner临时隐藏涉及有商品跳转的专题
     * @param $banner
     * @return array
     */
    private function appHideBanner($banner)
    {
        $new = [];
        foreach ($banner as $v){
            if(strstr($v['adv_pic_url'],'214a') || strstr($v['adv_pic_url'],'320')){
                continue;
            }
            $new[] = $v;
        }
        return $new;
    }

    /**
     * 花递1.0.11版本首页限时购
     */
    public function actionHomeXianshi()
    {
        $next = \Yii::$app->request->post("next", false);//是否是下一场
        if(!$next){//本场特惠
            $where['state'] = 1;
            $where = ['and', $where, ['>', 'end_time', TIMESTAMP]];
            $where = ['and', $where, ['<', 'start_time', TIMESTAMP]];
            $xianshi = PXianshi::find()->where($where)->select("xianshi_id, start_time, end_time")
                ->orderBy("xianshi_sort desc, xianshi_id desc")
//                ->createCommand()->getSql();
                ->asArray()->one();
        }else{//下场预告
            $where['state'] = 1;
            $where = ['and', $where, ['>', 'start_time', TIMESTAMP]];
            $xianshi = PXianshi::find()->where($where)->select("xianshi_id, start_time, end_time")
                ->orderBy("xianshi_sort desc, xianshi_id desc")
//                ->createCommand()->getSql();
                ->asArray()->one();
        }
        if(!isset($xianshi['xianshi_id'])){
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }
        $xianshi['now_time'] = time();
        $data['xianshi'] = $xianshi;
        $where = [];
        $param = \Yii::$app->request->post();
        $page = isset($param['page']) ? (int)$param['page'] : 1;
        $pagesize = isset($param['pagesize']) ? (int)$param['pagesize'] : 6;
        $goods_id = (int)$param['goods_id'];//用于置顶,和判断是否首页请求(首页请求不会传这个参数)
        $where['xianshi_id'] = $xianshi['xianshi_id'];
        $where['state'] = PXianshiGoods::XIANSHI_GOODS_STATE_NORMAL;
        $where['site_goods_price'] = 1;
        $goods_xianshi = PXianshiGoods::find()->where($where)->select("goods_id,index_img")->orderBy("gc_sort desc")->asArray()->all();
        $goods_ids = array_column($goods_xianshi,"goods_id");
        $goods_index_imgs = ArrayHelper::map($goods_xianshi,'goods_id','index_img');
        if(!$goods_ids){
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }
        $condition['goods.goods_id'] = $goods_ids;
        $condition['goods.goods_state'] = 1;
        $goods_model = new \common\models\Goods();
        $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
        $data['goods'] = $goods_model->goodslist($field, $page, $pagesize, $condition, ['field(goods.goods_id, '.implode(',', $condition["goods.goods_id"] ).')' => true],1,[],[],[],0,320);
        if(!empty($data['goods'])){
            foreach ($data['goods'] as $index => $value) {
                if(!empty($goods_id)){
                    if($value['goods_id'] == $goods_id && $index != 0){
                        array_unshift($data['goods'], $data['goods'][$index]);
                        unset($data['goods'][$index + 1]);
                    }
                }else{
                    if(isset($goods_index_imgs[$value['goods_id']]) && !empty($goods_index_imgs[$value['goods_id']])){
                        $data['goods'][$index]['goods_image'] = $goods_index_imgs[$value['goods_id']];
                    }
                }

            }
            $data['goods'] = array_values($data['goods']);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 1.0.11 新版全部分类侧边栏
     */
    public function actionHomeClass()
    {
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $this->homeClass());
    }

    /**
     * 新版全部分类侧边栏
     */
    private function homeClass()
    {
        $data = GoodsClass::getSqlAllClass();
        array_unshift($data,['type' => 0, 'title' => '包月花']);
        list($data[1], $data[0]) = [$data[0], $data[1]];
//        $adv_model = new \common\models\Adv();
//        $data_ret['class_arr'] = $data;
//        $data_ret['adv'] = $adv_model->getBanner(178);
//        return $data_ret;
        $settings = \common\models\Setting::instance()->getValue("huadi_icon_setting",true);
        $huadi_icon_setting = unserialize($settings);

        if($huadi_icon_setting['daily_flower_tag'] == 0 || $huadi_icon_setting['monthly_flower_tag'] == 0 || $huadi_icon_setting['flower_material'] == 0) {
            $data = array_filter($data, function ($arr) use ($huadi_icon_setting){
                if($arr['title'] == '包月花' && $huadi_icon_setting['monthly_flower_tag'] == 0) {
                    return false;
                }
                if($arr['title'] == '家居花' && $huadi_icon_setting['daily_flower_tag'] == 0) {
                    return false;
                }
                if($arr['title'] == '生活花' && $huadi_icon_setting['daily_flower_tag'] == 0) {
                    return false;
                }
                if($arr['title'] == '花材' && $huadi_icon_setting['flower_material'] == 0) {
                    return false;
                }
                return true;
            });
            $data = array_values($data);
        }
        return $data;
    }

    /**
     * 1.0.11 新版全部分类具体内容
     */
    public function actionHomeClassContent()
    {
        $type = \Yii::$app->request->post('type',1);
        $ret_data = $this->homeClass();
//        if(!in_array($type,array_column($ret_data['class_arr'],'type'))){
//            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
//        }
        if(!in_array($type,array_column($this->homeClass(),'type'))){
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        //包月花直接跳转地址
        if($type == 0){
            $data = ['content_type'=>0];
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
        }

        $data = ['content_type'=>0];
        $goodsClassInfo = GoodsClass::find()->where(['gc_id'=>$type])->select("type_id")->asArray()->one();
        $attrList = Attribute::find()
            ->where(['type_id'=>$goodsClassInfo['type_id'],'attr_show'=>1])
            ->select("attr_id, attr_name")
            ->orderBy("attr_sort desc")
            ->asArray()
            ->all();
        if($attrList){
            $attrValueList = AttributeValue::find()
                ->where([
                    'type_id' => $goodsClassInfo['type_id'],
                    'attr_id' => array_column($attrList,"attr_id")
                ])
                ->select("attr_value_id, attr_value_name, attr_id")
                ->orderBy("attr_value_sort desc")
                ->asArray()
                ->all();
            foreach ($attrList as &$attr){
                foreach ($attrValueList as $attrValue){
                    if($attr['attr_id'] == $attrValue['attr_id']){
                        $attr['value'][] = $attrValue;
                    }
                }
            }

            $goods_ids = Goods::find()->where(['gc_id_2'=>$type])->select("goods_id")->limit(3)->asArray()->all();
            $goods_ids = array_column($goods_ids,"goods_id");
            $condition['goods.goods_id'] = $goods_ids;
            $goods_model = new \common\models\Goods();
            $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $goods_list = $goods_model->goodslist($field, 1, 3, $condition, '',1,[],[],[],0,320);
            $data['content_type'] = 1;
            $data['goods_list'] = $goods_list;
            $data['attr_list'] = $attrList;
        }
        $adv_model = new \common\models\Adv();
        $data['adv'] = $adv_model->getBanner(166);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }

    /**
     * 1.0.13版首页弹框
     */
    public function actionHomeAlert()
    {
        $data = [
            "is_show" => 0
        ];
//        $get_is_show = Setting::find()->where(['name'=>'huadi_home_alert'])->one();//后期需改为可后台设置是否显示
        if(true){//!empty($get_is_show->value)
            $data['is_show'] = 1;
            //背景图
            $data['bg_url'] = 'https://cdn.ahj.cm/romantic_equity/2020/2/12/seE5bFC4cJ.png';
            if(TIMESTAMP < strtotime("2020-02-14 23:59:59")){//214之前显示情人节图片
                $data['bg_url'] = 'https://cdn.ahj.cm/romantic_equity/2020/2/12/i2mSJDJC7w.png';
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }

    /**
     * 1.0.13版首页弹窗获取是否绑定手机号以及是否领取
     */
    public function actionHomeAlertVoucher()
    {
        $this->validLogin();
        $data = [
            "bind_mobile" => 0,
        ];
        if($this->member_info['member_mobile_bind'] == 1){
            $data['bind_mobile'] = 1;
        }
        $voucher_id = 1650;
        $data['topic_id'] = 143;
        $data['is_get'] = Voucher::instance()->checkPicked($voucher_id, $this->member_id);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }
    /**
     * 花递小程序家居花首页
     * @return mixed
     */
    public function actionAppletsindex()
    {
        $data = [];
        //banner
        $adv_model = new Adv();
        $data['top_banner'] = $adv_model->getBanner(159);
        $goods_model = new GoodsClass();
        $class_list = $goods_model->getCatChildListById(Goods::FLOWER_HOME);
        if (empty($class_list)) {
            $class_list = [
                [
                    "gc_id"   => "591",
                    "gc_name" => "办公室"
                ],
                [
                    "gc_id"   => "592",
                    "gc_name" => "茶几餐桌"
                ],
                /*  [
                      "gc_id" =>  "403",
                      "gc_name" => "康乃馨"
                  ],*/
                [
                    "gc_id"   => "593",
                    "gc_name" => "卫生间"
                ],
                [
                    "gc_id"   => "594",
                    "gc_name" => "会议桌花"
                ],
                [
                    "gc_id"   => "642",
                    "gc_name" => "养花器具"
                ],
                [
                    "gc_id"   => "641",
                    "gc_name" => "绿植多肉"
                ],
                [
                    "gc_id"   => "638",
                    "gc_name" => "散装花材"
                ],
                [
                    "gc_id"   => "406",
                    "gc_name" => "包月花"
                ]
            ];
        }
        $data['class_list'] = $class_list;

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 花递小程序礼品花轮播图
     */
    public function actionAppletsGiftBanner()
    {
        $adv_model = new Adv();
        $data['top_banner'] = $adv_model->getBanner(162);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 调用花娃接口获取门店总数
     * @return mixed
     */
    public function actionGetStoreCount()
    {
        $data = HuawaApi::getInstance()->OC("huadi_store", "get_store_count", ['delivery_type' => 3]);
        if ($data['status'] != 1 || empty($data['data'])) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data['data']);
    }

    /**
     * 获取首次送花时间及禁止配送的工作日
     * @return mixed
     */
    public function actionFirstTime()
    {
        $data = [];
        $param = \Yii::$app->request->post();
        $first_date = isset($param['first_date']) ? (string)$param['first_date'] : '';
        if ($first_date) {
            if (Orders::checkFirstTime($first_date) == false) {
                //return $this->responseJson(Message::ERROR, Orders::instance()->getFirstError(Message::MODEL_ERROR));
                $first_date = getNextFirstTime();
            }
        } else {
            //默认下一个可以配送的时间
            $first_date = getNextFirstTime();
        }
        //计算接下来一个月可以配送的时间段
        $data['next_days'] = array_map(function ($val) {
            return [
                'text'  => date('m-d', $val) . ',' . getWeekText($val),
                'value' => date('Y-m-d', $val)
            ];
        }, getNextDeliveryDays($first_date));
        //禁止选择的星期
        $data['disable_days'] = getReverseDay(explode(',', HOME_FLOWER_DELIVER_TIME));

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 爱花居专题接口-超模
     * @throws Exception
     */
    public function actionSpecial_156()
    {
        $topic_id = 156;
        $cms_constel = CmsConstel::find()->where(['id' => $topic_id])->one();
        if (empty($cms_constel)) {
            return $this->responseJson(0, '信息不存在');
        }
        $start_time = intval($cms_constel['start_time']);
        $end_time = intval($cms_constel['end_time']);
        $site_goods_price = isset($_GET['site_goods_price']) ? $_GET['site_goods_price'] : 1;
        if (TIMESTAMP > $start_time && TIMESTAMP < $end_time) {
            $cms_constel_type = CmsConstelType::find()->where(['type_id' => $topic_id])->asArray()->all();
            $cms_constel_data = [];
            foreach ($cms_constel_type as $key => $type) {
                $constel_data = [];
                $constel_data['cat_title'] = $type['title'];
                $constel_data['data'] = isset($type['data']) && $type['data'] ? unserialize($type['data']) : '';
                $cms_constel_goods = CmsConstelGoods::find()->where(['constel_id' => $topic_id, 'cat_id' => $type['id']])->asArray()->all();
                foreach ($cms_constel_goods as $k => $goods) {
                    $goods = Goods::find()->select('goods_id,goods_name,goods_price,goods_marketprice,ahj_goods_price,ahj_goods_marketprice,goods_salenum,goods_custom_salenum,goods_image,goods_material,store_id,goods_material,goods_jingle')->where(['goods_id' => $goods['goods_id']])->asArray()->one();
                    if (empty($goods)) continue;
                    $goods['url'] = $goods['goods_id'];
                    $goods['img'] = $goods['goods_image'];
                    $p_xianshi_goods = PXianshiGoods::find()->where(['goods_id' => $goods['goods_id'], 'site_goods_price' => $site_goods_price])->asArray()->one();
                    if ($site_goods_price != 1) {
                        $goods['goods_price'] = FinalPrice::S($goods['goods_price']);
                        $goods['goods_marketprice'] = FinalPrice::M($goods['goods_price'],$goods['goods_marketprice']);
                    }
                    if (!empty($p_xianshi_goods)) {
                        $goods['goods_price'] = intval($p_xianshi_goods['xianshi_price']);
                        $goods['goods_marketprice'] = $p_xianshi_goods['goods_price'];
                    }
                    $goods['cha'] = intval($goods['goods_marketprice'] - $goods['goods_price']);
                    $goods['goods_salenum'] = $goods['goods_salenum'] + $goods['goods_custom_salenum'];
                    $constel_data['goods'][$k] = $goods;
                }
                $cms_constel_data[$key] = $constel_data;
            }
        } else {
            return $this->responseJson(0, '专题已过期');
        }
        cache('api/special_' . $topic_id, $cms_constel_data, 1);
        $cms_constel_data['site_id'] = isset($_GET['site_id']) ? $_GET['site_id'] : 193;
        $v_ten = \Yii::$app->getSecurity()->encryptByPassword('1352|' . TIMESTAMP, SECURITY_KEY);
        $v_thirty = \Yii::$app->getSecurity()->encryptByPassword('1353|' . TIMESTAMP, SECURITY_KEY);
        $v_fifty = \Yii::$app->getSecurity()->encryptByPassword('1355|' . TIMESTAMP, SECURITY_KEY);
        $cms_constel_data['voucher_list'] = [
            'v_ten'    => base64_encode($v_ten),
            'v_thirty' => base64_encode($v_thirty),
            'v_fifty'  => base64_encode($v_fifty),
        ];

        return $this->responseJson(1, '', $cms_constel_data);
    }

    public function actionSpecial_156_luck()
    {
        if (!$this->member_id) {
            return $this->responseJson(0, '请登录后再试');
        }
        if (isset($_GET['m_code'])) {
            $code = trim($_GET['m_code']);
            $m_code = $this->m_crypt($code, 'DECODE', 'chaomoaihuaju_slx888');
            $cache_name = 'member_draw_topic_' . $m_code . '_' . $this->member_id;
            $_d = cache($cache_name);
            if (empty($_d)) {
                $r = Member::findOne(['member_id' => $m_code])->updateCounters(['draw_number' => 1]);
                if ($r) {
                    cache($cache_name, 1, 86400);
                }
            }
        }
        $topic_id = 156;
        $site_goods_price = isset($_GET['site_goods_price']) ? $_GET['site_goods_price'] : 1;
        $cms_constel = CmsConstel::find()->where(['id' => $topic_id])->one();
        if (empty($cms_constel)) {
            return $this->responseJson(0, '活动不存在');
        }
        $start_time = intval($cms_constel['start_time']);
        $end_time = intval($cms_constel['end_time']);
        if (time() > $start_time && time() < $end_time) {
            $cms_constel_type = CmsConstelType::find()->where(['type_id' => $topic_id])->asArray()->all();
            $cms_constel_data = [];
            foreach ($cms_constel_type as $key => $type) {
                $constel_data = [];
                $constel_data['cat_title'] = $type['title'];
                $constel_data['data'] = unserialize($type['data']);
                $cms_constel_goods = CmsConstelGoods::find()->where(['constel_id' => $topic_id, 'cat_id' => $type['id']])->asArray()->all();
                foreach ($cms_constel_goods as $k => $goods) {
                    $goods = Goods::find()->select('goods_id,goods_name,goods_price,goods_marketprice,ahj_goods_price,ahj_goods_marketprice,goods_salenum,goods_custom_salenum,goods_image,goods_material,store_id,goods_material,goods_jingle')->where(['goods_id' => $goods['goods_id']])->asArray()->one();
                    if (empty($goods)) continue;

                    $goods['url'] = $goods['goods_id'];
                    $goods['img'] = $goods['goods_image'];
                    $p_xianshi_goods = PXianshiGoods::find()->where(['goods_id' => $goods['goods_id'], 'site_goods_price' => $site_goods_price])->asArray()->one();
                    if ($site_goods_price != 1) {
                        $goods['goods_price'] = FinalPrice::S($goods['goods_price']);
                        $goods['goods_marketprice'] = FinalPrice::M($goods['goods_price'],$goods['goods_marketprice']);
                    }
                    if (!empty($p_xianshi_goods)) {
                        $goods['goods_price'] = $p_xianshi_goods['xianshi_price'];
                        $goods['goods_marketprice'] = $p_xianshi_goods['goods_price'];
                    }
                    $goods['cha'] = intval(FinalPrice::smoothMarket($goods['goods_marketprice']) - FinalPrice::smooth($goods['goods_price']));
                    $goods['goods_salenum'] = $goods['goods_salenum'] + $goods['goods_custom_salenum'];
                    $constel_data['goods'][$k] = $goods;
                }
                $cms_constel_data[$key] = $constel_data;
            }
        } else {
            return $this->responseJson(0, '活动已过期');
        }
        if (FROM_DOMAIN != 'www.tflove.com') {
            cache('index/special_' . $topic_id, $cms_constel, 86400);
        }
        $rate['cms_constel'] = $cms_constel_data;
        $member_info = Member::find()->where(array('member_id' => $this->member_id))->one();
        if (empty($member_info)) {
            return $this->responseJson(0, '请登录后再试');
        }
        $draw_number = $member_info['draw_number'];
        $buy_data = cache('topic_luck');
        $rate['draw_number'] = $draw_number;

        if (empty($buy_data)) {
            $jg_p = array(0 => '199元现金红包', 1 => '11朵玫瑰花束', 2 => '20元无门槛优惠券', 3 => '30元无门槛优惠券', 4 => '50元无门槛优惠券');
            $sql_str = "SELECT c.`buyer_phone`,c.`add_time`,g.`goods_name` FROM `hua123_orders` as c,`hua123_order_goods` as g WHERE g.`order_id`= c.`order_id` ORDER BY c.`order_id` desc  LIMIT 100";
            $command = \Yii::$app->db->createCommand($sql_str);
            $buy_data = $command->queryAll();
            $now_s = 1;
            foreach ($buy_data as $k => $data) {
                if (!$data['buyer_phone']) {
                    unset($buy_data[$k]);
                }
                $buy_data[$k]['buyer_phone'] = mobile_format($data['buyer_phone']);
                $s = rand(10, 99);
                if ($s < 50) {
                    $now_s++;
                }
                $buy_data[$k]['now_s'] = date('H:i:s', time() - $now_s * 50);
                $y = rand(0, 4);
                $buy_data[$k]['now_y'] = $jg_p[$y];
                $buy_data[$k]['today'] = date('m-d');
            }
            cache('topic_luck', $buy_data, 60);
        }
        $rate['buy_data'] = $buy_data;

        $rate['rate_data'] = [
            '30' => '0.001',
            '20' => '0.001',
            '0'  => '0.803',
        ];
        $rate_30 = Voucher::find()->where(['voucher_t_id' => '1385', 'voucher_owner_id' => $this->member_id])->count();
        if ($rate_30 > 0) {
            $rate['rate_data']['30'] = 0;
        }
        $rate_20 = Voucher::find()->where(['voucher_t_id' => '1384', 'voucher_owner_id' => $this->member_id])->count();
        if ($rate_20 > 0) {
            $rate['rate_data']['20'] = 0;
        }
        $v_ten = \Yii::$app->getSecurity()->encryptByPassword('1352|' . TIMESTAMP, SECURITY_KEY);
        $v_thirty = \Yii::$app->getSecurity()->encryptByPassword('1353|' . TIMESTAMP, SECURITY_KEY);
        $v_fifty = \Yii::$app->getSecurity()->encryptByPassword('1355|' . TIMESTAMP, SECURITY_KEY);
        $rate['voucher_list'] = [
            'v_ten'    => base64_encode($v_ten),
            'v_thirty' => base64_encode($v_thirty),
            'v_fifty'  => base64_encode($v_fifty),
        ];
        //加密链接
        $my_m_code = $this->m_crypt($this->member_id, 'ENCODE', 'chaomoaihuaju_slx888');
        $rate['enscry_url'] = 'webapp.aihuaju.com/activity/luck?m_code=' . $my_m_code;
        $rate['my_m_code'] = $my_m_code;

        return $this->responseJson(1, '成功', $rate);
    }

    public function actionLuck_draw()
    {
        $r = Member::findOne(['member_id' => $this->member_id])->updateCounters(['draw_number' => -1]);
        if ($r) {
            return $this->responseJson(1, '成功');
        } else {
            return $this->responseJson(0, '您暂无抽奖次数，请完成任务获得抽奖次数后，再次继续抽奖！');
        }
    }

    public function actionLuck_draw_add()
    {
        if (isset($_GET['m_code']) && $_GET['m_code'] != '') {
            $member_id = $this->m_crypt($_GET['m_code'], 'DECODE', 'chaomoaihuaju_slx888');
            $r = Member::findOne(['member_id' => $member_id])->updateCounters(['draw_number' => 1]);
        } else {
            $r = Member::findOne(['member_id' => $this->member_id])->updateCounters(['draw_number' => 1]);
        }
        if ($r) {
            return $this->responseJson(1, '成功');
        } else {
            return $this->responseJson(0, '操作失败');
        }
    }

    private function m_crypt($txt, $operation = 'ENCODE', $key = '')
    {
        $key = md5($key);
        $txt = $operation == 'ENCODE' ? (string)$txt : base64_decode($txt);
        $len = strlen($key);
        $code = '';
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $i % $len;
            $code .= $txt[$i] ^ $key[$k];
        }
        $code = $operation == 'DECODE' ? $code : base64_encode($code);

        return $code;
    }
}
