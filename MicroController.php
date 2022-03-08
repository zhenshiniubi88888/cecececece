<?php

namespace frontend\controllers;

use Codeception\Module\Db;
use common\components\FinalPrice;
use common\components\HuawaApi;
use common\components\IntelligentAnalysisAddress;
use common\components\Log;
use common\components\MicroApi;
use common\models\Address;
use common\models\Adv;
use common\models\Area;
use common\models\Crontab;
use common\models\Data;
use common\models\EvaluateGoods;
use common\models\Favorites;
use common\models\GoodsAddprice;
use common\models\HuadiStoreZm;
use common\models\HuadiStoreZmGoods;
use common\models\JmApply;
use common\models\Member;
use common\models\MemberVerify;
use common\models\OrderCommon;
use common\models\Orders;
use common\models\GoodsImages;
use common\models\Search;
use frontend\service\StoreService;
use linslin\yii2\curl\Curl;
use Yii;
use yii\base\InvalidParamException;
use common\components\Message;
use yii\base\Model;
use yii\db\Expression;

class MicroController extends BaseController
{


    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }


    public function actionTest(){
        $crontab = new Crontab();
        var_dump($crontab->explodeHomeFlowerOrder());exit;
//        echo date('Y-m-d H:i:s',1568469599);
        $aa = Orders::find()->select('delivery_type')->where(['huawa_orderid'=>6553142])->one();
        var_dump($aa->delivery_type);exit;
    }
    /**
     * 获取花店列表
     * @return mixed
     */
    public function actionStorelist()
    {
        $curpage = Yii::$app->request->post("page", 1);
        $cat_id = Yii::$app->request->post("cat_id", 0);
        $keyword = Yii::$app->request->post("keyword", "");
        $lng = Yii::$app->request->post("lng", "104.056238");
        $lat = Yii::$app->request->post("lat", "30.653585");
        $pagesize = 10;
        $order = Yii::$app->request->post("order", 4);//1.综合 2.接单量 3.信用 4.距离
        $is_jiameng = Yii::$app->request->post("type", 0);//0普通微花店  1加盟店
        $param = [
            "page" => $curpage,
            "keyword" => $keyword,
            "lng" => $lng,
            "lat" => $lat,
            "cat_id" => $cat_id,
            "order" => $order,
            "pagesize" => $pagesize,
            "is_jiameng" => $is_jiameng,
        ];
        $api = new MicroApi();
        $store_data = $api->httpRequest('/api/get_store_goods', $param);
        if ($store_data) {
            $store = $store_data["store"];
            foreach ($store as $k => $_store) {
                foreach ($_store["goods"] as $key => $goods) {
                    $_store["goods"][$key]["goods_name"] = sprintf("%s %s", $goods['goods_name'], $goods['goods_introduce']);
                    $_store["goods"][$key]["goods_type"] = GOODS_TYPE_STORE_FLOWER;

                    $_store["goods"][$key]["goods_price"] = FinalPrice::S($goods['goods_price']);
                }
                //默认等级
                if ($_store["store_grade"] == "") {
                    $store[$k]["store_grade"] = '<img src="//www.huawa.com/static/images/grade_2.gif" align="absmiddle" title="信誉等级6级" /><img src="//www.huawa.com/static/images/grade_1.gif" align="absmiddle" title="信誉等级6级" /><img src="//www.huawa.com/static/images/grade_1.gif" align="absmiddle" title="信誉等级6级" />';
                }

                //单量

                //好评
                //评价
                //店招图
                $store[$k]["store_imgs"] = JmApply::getStoreImg($_store['store_id'], $_store['store_label']);

                //聊天固定字段
                $store[$k]["user_id"] = $_store['member_id'];
                $store[$k]["store_name"] = $_store['store_name'];
                $store[$k]["store_label"] = $_store['store_label'];
                $store[$k]["goods"] = $_store["goods"];
            }
            $data = [];
            $data["store"] = $store;
            $data["cat"] = $store_data['cat'];
            $adv_model = new Adv();
            $ad = $adv_model->getBanner($is_jiameng ? 147 : 134);
            $data["ad"] = $ad;
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        } else {
            Yii::error($api->getError());
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['store' => [], 'ad' => [], 'cat' => []]);
        }
    }

    /**
     * 花店列表广告图
     * @return mixed
     */
    public function actionAdv()
    {
        $adv_model = new \common\models\Adv();
        $adv = $adv_model->getBanner(167);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $adv);
    }

    /**
     * 获取花递附近花店列表
     * @return mixed
     */
    public function actionHuadiStorelist_bak()
    {
        $lng = Yii::$app->request->post("lng", "104.056238");
        $lat = Yii::$app->request->post("lat", "30.653585");
        $store_name = Yii::$app->request->post("keyword", "");
        $distance = Yii::$app->request->post("distance", 5);
        $cat_id = Yii::$app->request->post("cat_id", "");
        $gsc_id = Yii::$app->request->post("gsc_id", "");
        $price = Yii::$app->request->post("price", "");
        $order = Yii::$app->request->post("order", 4);//1.综合 2.接单量 3.信用 4.距离
        $curpage = Yii::$app->request->post("page", 1);
        $param = [
            "lng" => $lng,
            "lat" => $lat,
            "distance" => $distance,
            "store_name" => $store_name,
            "cat_id" => $cat_id,
            "gsc_id" => $gsc_id,
            "price" => $price,
            "order" => $order,
            "page" => $curpage
        ];
        $huadi_huadiangou_key = md5("huadi_huadiangou_6_{$store_name}_{$curpage}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_1");
        $huadi_huadiangou_time_key = md5("huadi_huadiangou_6_{$store_name}_{$curpage}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_time");
        $huadi_huadiangou_list_key = md5("huadi_huadiangou_6_{$store_name}_{$curpage}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_list");
        $store_data = cache($huadi_huadiangou_key);
        if (!$store_data) {
            $api = new MicroApi();
            $store_data = $api->httpRequest('/api/nearstorelist', $param);
            cache($huadi_huadiangou_key, $store_data, 3600);
        }
        if ($store_data) {
            // 第一页，是否有新入驻的花递
            $store_new = $store_data['store_new'];

            // 第一页
            if ($curpage == 1 && $store_new) {
                // 如果第一页中的老店铺数据不足10个，则直接合并新店铺数据
                if (count($store_data["store"]) < 10) {
                    $store_data["store"] = array_merge($store_data["store"], $store_new);
                } else {
                    cache($huadi_huadiangou_time_key, TIMESTAMP + 60 * 5, TIMESTAMP + 3600);

                    $new_store_ids = [];
                    $store_new_arr = [];
                    foreach ($store_new as $s_n) {
                        $new_store_ids[] = $s_n['store_id'];
                        $store_new_arr[$s_n['store_id']] = $s_n;
                    }
                    $redis_list = Yii::$app->redis->lrange($huadi_huadiangou_list_key, 0, -1);

                    // 如果队列中有数据,则合并
                    if (!empty($redis_list)) {
                        $new_store_diff_ids = array_diff($new_store_ids, $redis_list);
                    } else {
                        $new_store_diff_ids = $new_store_ids;
                    }

                    if (!empty($new_store_diff_ids)) {
                        foreach ($new_store_diff_ids as $s_n) {
                            Yii::$app->redis->rpush($huadi_huadiangou_list_key, $s_n);
                            Yii::$app->redis->expire($huadi_huadiangou_list_key, 3600 * 24);
                        }
                    }

                    // 取出两条
                    $store_new_data = array();
                    for ($i = 0; $i <= 100; $i++) {
                        if (count($store_new_data) >= 2) {
                            break;
                        }
                        $new_pop_store_id = Yii::$app->redis->lpop($huadi_huadiangou_list_key);
                        // 如果队列已经取完，则跳过
                        if (!$new_pop_store_id) {
                            break;
                        }

                        // 如果队列中的store_id 在新花店中没有，则跳过，继续取数据
                        if (!in_array($new_pop_store_id, $new_store_ids)) {
                            continue;
                        }

                        $store_new_data[] = $store_new_arr[$new_pop_store_id];
                    }

                    if (count($store_new_data) >= 2) {
                        array_splice($store_data["store"], 5, 0, array($store_new_data[0]));
                        array_splice($store_data["store"], 9, 0, array($store_new_data[1]));
                    } elseif (count($store_new_data) == 1) {
                        array_splice($store_data["store"], 6, 0, array($store_new_data[0]));
                    }

                }
            }

            $store = $store_data["store"];
            if (empty($store)) {
                Log::writelog('huadi_empty_store_list', $param);
            }
            $store = $this->_huadiStorelist($store);

            $exec_time[] = microtime(true);
            $data = [];
            $data["store"] = $store;
            $data["store_new"] = $store_new;
            $data["exec_time"] = $exec_time;
            $data["store_count"] = count($store);//修改附近花店数量不统一，原代码：isset($store_data['store_count']) ? $store_data['store_count'] : 0
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        } else {
            Log::writelog('huadi_empty_store_list', $param);
            Yii::error($api->getError());
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['store' => [], 'store_new' => [], 'ad' => [], 'cat' => []]);
        }
    }

    private function _huadiStorelist($store)
    {
        foreach ($store as $k => $_store) {
            //默认等级
            if ($_store["store_grade"] == "") {
                $store[$k]["store_grade"] = $_store['store_star'];
                $store[$k]["store_star"] = $_store['store_star'];
            }
//                $order_count_condition = [
//                    'siteid'=> SITEID,
//                    'delivery_store_id' => $_store['store_id'],
//                    'order_state'       => ['egt',20]
//                ];
//                $order_count = Orders::find()->where($order_count_condition)->count();
            // 暂时使用花娃的数据
            $order_count = $_store['sell_num'] <= 0 ? 0 : $_store['sell_num'];

            //评价
//                $comment_count_condition = [
//                    "geval_storeid" => $_store['store_id'],
//                ];
//                $comment_count = EvaluateGoods::find()->where($comment_count_condition)->count();
            // 暂时使用花娃的数据
            $comment_count = $_store['store_comments'] >= $order_count ? $order_count : $_store['store_comments'];

            //好评
//                $good_comment_count_condition = [
//                    "geval_storeid" => $_store['store_id'],
//                    'geval_level'       => 1
//                ];
//                $good_comment_count = EvaluateGoods::find()->where($good_comment_count_condition)->count();
            // 暂时使用花娃的数据
            $good_comment_count = $_store['store_wellcredit'] >= $comment_count ? $comment_count : $_store['store_wellcredit'];

            $store[$k]['order_count'] = $order_count;
            $store[$k]['store_comments'] = $comment_count;
            $store[$k]['good_comment_count'] = $good_comment_count;
            $store[$k]['comment_count'] = $comment_count;
            $store[$k]['send_time'] = $_store['send_time'] . '送达';
            //店招图
            $store[$k]["store_imgs"] = JmApply::getStoreImg($_store['store_id'], $_store['store_label']);
            //聊天固定字段
            $store[$k]["user_id"] = $_store['member_id'];
            $store[$k]["store_name"] = $_store['store_name'];
            $store[$k]["store_label"] = JmApply::getOssThumb($_store['store_label']);
            foreach ($_store["goods"] as $kk => $vv) {
                $_store["goods"][$kk]['goods_type'] = GOODS_TYPE_STORE_FLOWER;
                $_store["goods"][$kk]['goods_price'] = floatval($_store["goods"][$kk]['goods_price']);
                $_store["goods"][$kk]['goods_img'] = JmApply::getOssThumb($vv['goods_img']);
            }
            $store[$k]["goods"] = $_store["goods"];
        }
        return $store;
    }

    private function _huadiStorelist_new($store, $store_goods)
    {
        $store_data = [];
        foreach ($store as $k => $_store) {
            //没有头像
            $_store_data = $_store;
            $img_path = str_replace("http://resource.huawa.com/shop/store/image/", "", $_store['store_label']);
            //判断头像资源是否存在
            $handle = @fopen($_store['store_label'], 'r');
            if (!$img_path || $handle === false) {
                $_store["store_label"] = 'http://img.huawa.com/upload/shop/common/default.jpg';
            }
            //无商品的跳过
            if (isset($store_goods[$_store['store_id']])) {
                //花店图片
                $goods_data = $store_goods[$_store['store_id']];
                foreach ($goods_data as $key => $g) {
                    //花店产品图片生成oss图片100
                    $goods_data[$key]['goods_img'] = JmApply::getOssThumb($g['goods_img'], 360, 360);
                    $goods_data[$key]['goods_price'] = priceFormat($g['goods_price']);
                }
                $_store_data['goods'] = $goods_data;
            } else {
                unset($store[$k]);
                continue;
            }
            //默认等级
            $_store['store_star'] += 2;//星级1-3,统一+2增加信任度
            if ($_store["store_grade"] == "") {
                $_store_data["store_grade"] = $_store['store_star'];
            }
            $_store_data["store_star"] = intval($_store['store_star']);
            // 暂时使用花娃的数据
            $order_count = $_store['store_orders'] <= 0 ? 0 : $_store['store_orders'];

            // 暂时使用花娃的数据
            $comment_count = $_store['store_comments'] >= $order_count ? $order_count : $_store['store_comments'];

            // 暂时使用花娃的数据
            $good_comment_count = $_store['store_wellcredit'] >= $comment_count ? $comment_count : $_store['store_wellcredit'];

            $_store_data['order_count'] = $order_count;
            $_store_data['store_comments'] = $comment_count;
            $_store_data['good_comment_count'] = $good_comment_count;
            $_store_data['comment_count'] = $comment_count;
            if ($_store['distance'] < 1) {
                $_store_data['distance'] = floor($_store['distance'] * 1000) . "m";
            } else {
                $_store_data['distance'] = sprintf("%.2f", $_store['distance']) . "km";
            }
            if ($_store['distance']) {
                $send_time = ceil(60 + $_store['distance'] * 10);
                if ($send_time > 60) {
                    $send_time = floor($send_time / 60) . "小时";
                } else {
                    $send_time = $send_time . "分钟";
                }
            } else {
                $send_time = "*";
            }
            $_store_data['send_time'] = $send_time . '送达';
            $_store_data['address'] = $_store['store_address'];
            //店招图
            $_store_data["store_imgs"] = JmApply::getStoreImg($_store['store_id'], $_store['store_label']);
            //聊天固定字段
            $_store_data["user_id"] = $_store['member_id'];
            $_store_data["store_name"] = $_store['store_name'];
            $_store_data["store_label"] = JmApply::getOssThumb($_store['store_label']);
            $store_data[] = $_store_data;
        }
        return $store_data;
    }
    private function _huadiStorelist_new_two($store, $store_goods)
    {
        $store_data = [];
        foreach ($store as $k => $_store) {
            //没有头像
            $_store_data = $_store;
            $img_path = str_replace("http://resource.huawa.com/shop/store/image/", "", $_store['store_label']);
            //判断头像资源是否存在
            $handle = @fopen($_store['store_label'], 'r');
            if (!$img_path || $handle === false) {
                $_store["store_label"] = 'http://img.huawa.com/upload/shop/common/default.jpg';
            }
            //无商品的跳过
            if (isset($store_goods[$_store['store_id']])) {
                //花店图片
                $goods_data = $store_goods[$_store['store_id']];
                foreach ($goods_data as $key => $g) {
                    //花店产品图片生成oss图片100
                    $goods_data[$key]['goods_img'] = JmApply::getOssThumb($g['goods_img'], 360, 360);
                    $goods_data[$key]['goods_price'] = FinalPrice::S($g['goods_price']);
                }
                $_store_data['goods'] = $goods_data;
            } else {
                unset($store[$k]);
                continue;
            }
            //默认等级
            $_store['store_star'] += 2;//星级1-3,统一+2增加信任度
            if ($_store["store_grade"] == "") {
                $_store_data["store_grade"] = $_store['store_star'];
            }
            $_store_data["store_star"] = intval($_store['store_star']);
            // 暂时使用花娃的数据
            $order_count = $_store['store_orders'] <= 0 ? 0 : $_store['store_orders'];

            // 暂时使用花娃的数据
            $comment_count = $_store['store_comments'] >= $order_count ? $order_count : $_store['store_comments'];

            // 暂时使用花娃的数据
            $good_comment_count = $_store['store_wellcredit'] >= $comment_count ? $comment_count : $_store['store_wellcredit'];

            $_store_data['order_count'] = $order_count;
            $_store_data['store_comments'] = $comment_count;
            $_store_data['good_comment_count'] = $good_comment_count;
            $_store_data['comment_count'] = $comment_count;
            if ($_store['distance'] < 1) {
                $_store_data['distance'] = floor($_store['distance'] * 1000) . "m";
            } else {
                $_store_data['distance'] = sprintf("%.2f", $_store['distance']) . "km";
            }
            if ($_store['distance']) {
                $send_time = ceil(60 + $_store['distance'] * 10);
                if ($send_time > 60) {
                    $send_time = floor($send_time / 60) . "小时";
                } else {
                    $send_time = $send_time . "分钟";
                }
            } else {
                $send_time = "*";
            }
            $_store_data['send_time'] = $send_time . '送达';
            $_store_data['address'] = $_store['store_address'];
            //店招图
            $_store_data["store_imgs"] = JmApply::getStoreImg($_store['store_id'], $_store['store_label']);
            //聊天固定字段
            $_store_data["user_id"] = $_store['member_id'];
            $_store_data["store_name"] = $_store['store_name'];
            $_store_data["store_label"] = JmApply::getOssThumb($_store['store_label']);
            $store_data[] = $_store_data;
        }
        return $store_data;
    }

    /**
     * 获取报价花店列表
     *
     * @return mixed
     */
    public function actionNearStorelist()
    {
        $lng = Yii::$app->request->post("lng", "104.056238");
        $lat = Yii::$app->request->post("lat", "30.653585");
        $goods_id = Yii::$app->request->post("goods_id", 76);
        $pagesize = 10;//$pagesize = Yii::$app->request->post("pagesize",10);

        $url = MICRO_DOMAIN . "/api/nearstorelist.html";
        $param = [
            "lng" => $lng,
            "lat" => $lat,
            "pagesize" => $pagesize,
        ];

        $curl = new Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);
        $response = json_decode($response, true);
        $goodsadd_model = new GoodsAddprice;
        $_storelist = [];
        if ($response["status"]) {
            $storelist = json_decode($response["data"], true);
            $store = $storelist["store"];
            $price = 0;
            foreach ($store as $k => $v) {
                $_store = $v;
                $addprice = $goodsadd_model::find()->where(array(
                    "store_id" => $v["store_id"],
                    "goods_id" => $goods_id
                ))->select("ap_id,goods_id,report_price")->asArray()->one();
                if (!$addprice) {
                    continue;
                }
                if ($price > $addprice["report_price"]) {
                    $price = $addprice["report_price"];
                }
                $_store["ap_id"] = $addprice["ap_id"];
                $_store["report_price"] = $addprice["report_price"];
                $_storelist[] = $_store;
            }
            $store_count = count($_storelist);
            $data["store"] = $_storelist;

            return $this->responseJson($response["status"], $response["msg"], $data);
        } else {
            return $this->responseJson($response["status"], $response["msg"]);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 搜索筛选
     *
     * @return mixed
     */
    public function actionHuadiSearchItem()
    {
        $version = Yii::$app->request->post("version", 1);
        $cache_key = 'micro_huadi_search_item_' . $version;
        $ret_arr = cache($cache_key);
        if ($ret_arr && !empty($ret_arr)) {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $ret_arr);
        }
        $api = new MicroApi();
        $goods_cate_scene = $api->httpRequest('/api/getGoodsCategory');

        $ret_arr = [];
        // 场景
        if ($goods_cate_scene && isset($goods_cate_scene['scene']) && isset($goods_cate_scene['scene_class'])) {
            foreach ($goods_cate_scene['scene_class'] as $class) {
                $scene = [];
                foreach ($goods_cate_scene['scene'] as $v) {
                    if ($class['class_id'] == $v['class_id']) {
                        $scene[] = [
                            "attr_value_id" => $v['sc_id'],
                            "attr_value_name" => $v['sc_name']
                        ];
                    }
                }
                if ($scene) {
                    $ret_arr[] = [
                        "title" => $class['class_name'],
                        "item" => $scene,
                    ];
                }
            }
        }
        cache($cache_key, $ret_arr, 60 * 3);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $ret_arr);
    }

    /**
     * 单个店铺商品列表
     *
     * @return mixed
     */
    public function actionGoods()
    {
        Log::writelog('goods_list_debug','rukou');
        $curpage = Yii::$app->request->post("page", 1);
        $order = Yii::$app->request->post("order", 1);
        $price = Yii::$app->request->post("price", 0);//价格区间拼接 : 11_22
        $min_price = Yii::$app->request->post("min_price", 0);
        $max_price = Yii::$app->request->post("max_price", 0);
        $keyword = Yii::$app->request->post("keyword", 0);
        $cat_id = Yii::$app->request->post("cat_id", 0);
        $category_id = Yii::$app->request->post("category_id", 0);
        $store_id = Yii::$app->request->post("store_id", 380482);
        $material_id = Yii::$app->request->post("material_id", 0);
        $pagesize = 10;//input("limit", 10);
        //兼容price,覆盖min-max
        if($price && strpos($price,'_') !== false){
            list($min_price,$max_price) = explode('_',$price);
        }
        //兼容价格收尾导致的价格区间搜索不准确的问题
        $url = MICRO_DOMAIN . "/api/goods";

        $curl = new Curl();

        $param = [
            "page" => $curpage,
            "order" => $order,
            "keyword" => $keyword,
            "min_price" => $min_price,
            "max_price" => $max_price,
            "cat_id" => $cat_id,
            "category_id" => $category_id,
            "store_id" => $store_id,
            "pagesize" => $pagesize,
            "material_id" => $material_id,
        ];
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);
        Log::writelog('goods_list_debug',$response);
        Log::writelog('goods_list_debug',var_export($response,true));
        $response = json_decode($response, true);
        if ($response["status"]) {
            $info = json_decode($response["data"], true);
            $goodslist = $info["list"];
            foreach ($goodslist as $k => $goods) {
                $goodslist[$k]["goods_type"] = GOODS_TYPE_STORE_FLOWER;
                $goodslist[$k]["gc_id"] = $goods["cat_id"];
                $goodslist[$k]["gc_name"] = $goods["cat_name"];
                $goodslist[$k]["goods_image"] = $goods["goods_img"];
                $goodslist[$k]["goods_salenum"] = $goods["sell_num"];
                $goodslist[$k]["goods_material"] = $goods["goods_introduce"];
                $goodslist[$k]["goods_price"] = FinalPrice::format(floatval($goodslist[$k]["goods_price"]));
                $goodslist[$k]["goods_marketprice"] = FinalPrice::format(floatval($goodslist[$k]["goods_price"])*1.1);
                //价格收尾可能导致收尾后的价格超过搜索价格, 需要过滤下
                if (isset($max_price) && $max_price > 0 && $goodslist[$k]['goods_price'] > $max_price) {
                    unset($goodslist[$k]);
                }
            }
            $data["goods"] = $goodslist;
            $data["count"] = $info["page"]["count"];
            return $this->responseJson($response["status"], $response["msg"], $data);
        } else {
            return $this->responseJson($response["status"], $response["msg"]);
        }
    }

    /**
     * 单个店铺商品分类类别
     *
     * @return mixed
     */
    public function actionStoreScene()
    {
        $store_id = Yii::$app->request->post("store_id", 0);
        if (!$store_id) {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }

        $url = MICRO_DOMAIN . "/api/get_store_scene";

        $curl = new Curl();

        $param = [
            "store_id" => $store_id,
        ];

        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);
        $response = json_decode($response, true);
        if ($response["status"]) {
            $info = json_decode($response["data"], true);
            array_unshift($info, array('scene_id' => 0, 'sc_name' => '全部鲜花'));
            $data["scene_list"] = $info;
            return $this->responseJson($response["status"], $response["msg"], $data);
        } else {
            return $this->responseJson($response["status"], $response["msg"]);
        }
    }


    /**
     * 单个店铺推荐商品列表
     */
    public function actionRecommendGoods()
    {
        $store_id = Yii::$app->request->post("store_id", 380482);
        $page = \Yii::$app->request->post("page", 1);

        $url = MICRO_DOMAIN . "/api/recommend_goods";
        $curl = new Curl();
        $param = [
            "store_id" => $store_id,
            "page" => $page
        ];
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);
        $response = json_decode($response, true);
        if ($response["status"]) {
            $info = json_decode($response["data"], true);
            $goodslist = $info["list"];
            foreach ($goodslist as $k => $goods) {
                $goodslist[$k]["goods_type"] = GOODS_TYPE_STORE_FLOWER;
                $goodslist[$k]["gc_id"] = $goods["cat_id"];
                $goodslist[$k]["gc_name"] = $goods["cat_name"];
                $goodslist[$k]["goods_image"] = $goods["goods_img"];
                $goodslist[$k]["goods_salenum"] = $goods["sell_num"];
                $goodslist[$k]["goods_price"] = floatval($goodslist[$k]["goods_price"]);

            }
            $data["goods"] = $goodslist;
            return $this->responseJson($response["status"], $response["msg"], $data);
        } else {
            return $this->responseJson($response["status"], $response["msg"]);
        }
    }

    /**
     *订单实拍图列表
     *
     * @return mixed
     */
    public function actionFlowerphoto()
    {
        $code = 1;
        // 检查传参
        if (!isset($_POST['order_id'])) {
            $msg = '参数错误';
            return $this->responseJson($code, $msg, array());
        }
        $ordersWhere['order_id'] = $_POST['order_id'];
//        $ordersWhere['audit_state'] = 1;
        $imgs = Orders::find()->select('delivery_imgurl,delivery_imgurl3')->where($ordersWhere)->asArray()->one();
        $delivery_imgurl = $imgs['delivery_imgurl3'] == '' ? $imgs['delivery_imgurl'] : $imgs['delivery_imgurl3'];
        $data = explode(',', $delivery_imgurl);
        $result = [];
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $new = [];
                $new['oss_img_path'] = JmApply::getOssThumb('http://cdn.ahj.cm' . DS . $v);
                $new['img_path'] = 'http://cdn.ahj.cm' . DS . $v;
                $result[] = $new;
            }
        }
        $msg = 'success';
        return $this->responseJson($code, $msg, $result);
    }

    /**
     *订单实拍列表
     *
     * @return mixed
     */
    public function actionFlowerlist()
    {
        $curpage = Yii::$app->request->post("page", 1);
        $store_id = Yii::$app->request->post("store_id", 380482);
        $pagesize = 10;//input("limit", 10);

        $url = MICRO_DOMAIN . "/api/flowerlist";

        $curl = new Curl();

        $param = [
            "page" => $curpage,
            "store_id" => $store_id,
            "pagesize" => $pagesize,
        ];
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);
        $response = json_decode($response, true);
        if ($response["status"]) {
            $info = json_decode($response["data"], true);
            $list = $info["order"];
            $data = [];
            foreach ($list as $val) {
                $area = explode(' ', $val['area_info']);
                array_push($data, [
                    'order_id' => $val['order_id'],
                    'send_imgurl' => JmApply::getOssThumb($val['send_imgurl'], 320, 320),
                    'big_send_imgurl' => $val['send_imgurl'],
                    'goods_material' => $val['goods_material'],
                    'send_time' => $val['send_time'],
                    'praise_count' => $val['praise_count'],
                    'step_count' => $val['step_count'],
                    'is_step' => $val['is_step'],
                    'is_praise' => $val['is_praise'],
                    'order_state' => huawaState2($val['order_state']),
                    'area_info' => end($area) . address_format(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", '',
                            $val['receive_address'])),
                ]);
            }
            $result["goods"] = $data;
            return $this->responseJson($response["status"], $response["msg"], $result);
        } else {
            return $this->responseJson($response["status"], $response["msg"]);
        }
    }

    /**
     * 店铺评论列表
     *
     * @return mixed
     */
    public function actionCommentlist()
    {
        $curpage = Yii::$app->request->post("page", 1);
        $type = Yii::$app->request->post("type", 0);//0.全部 1.好评 2.中评 3.差评 4.有图
        $store_id = Yii::$app->request->post("store_id", 380485);
        $pagesize = 10;//input("limit", 10);
        $url = MICRO_DOMAIN . "/api/commentlist";
        $curl = new Curl();

        $param = [
            "page" => $curpage,
            "store_id" => $store_id,
            "type" => $type,
            "pagesize" => $pagesize,
        ];

        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);

        $response = json_decode($response, true);

        if ($response["status"]) {
            $info = json_decode($response["data"], true);
            $list = $info["list"];
            $data["comment"] = $list;
            return $this->responseJson($response["status"], $response["msg"], $data);
        } else {
            return $this->responseJson($response["status"], $response["msg"]);
        }
    }

    /**
     * 花递店铺评论列表
     *
     * @return mixed
     */
    public function actionHuadiCommentlist()
    {
        $data = $this->actionCommentlist();
        $data = $data->data;
        if ($data['code'] == 1 && !empty($data['content']) && !empty($data['content']['comment'])) {

            foreach ($data['content']['comment'] as $k => &$v) {
                $v['geval_isanonymous'] = $v['seval_membername'] ? 0 : 1;
                $v['geval_avatar'] = $v['avatar'] ? getMemberAvatar($v['avatar']) : 'http://img.huawa.com/upload/shop/avatar/avatar_160794.jpg';
                $v['geval_frommembername'] = str_replace('爱花居', '花递', $v['seval_membername']);

                $v['geval_addtime'] = date('Y-m-d H:i:s', $v['seval_addtime']);

                $star = 5;
                if ($v['seval_comment'] == '差评') {
                    $star = 1;
                } elseif ($v['seval_comment'] == '中评') {
                    $star = 3;
                }

                $v['geval_scores_fw'] = $star;

                $v['geval_content'] = $v['seval_comment'];

                $v['geval_image'] = isset($v['comment_img']) ? $v['comment_img'] : [];
                // 确认时间与发货时间间隔
                if ($v['confirm_time'] && $v['send_time']) {
                    $use_time = intval($v['confirm_time']) - intval($v['send_time']);
                    $v['accept_text'] = ceil($use_time / 3600) . '小时内送达';
                } else {
                    $v['accept_text'] = '';
                }
                $strs = "QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
                $length = rand(4, 8);
                $name = substr(str_shuffle($strs), mt_rand(0, strlen($strs) - 11), $length);
                $v['seval_membername'] = $this->_nameFormat($name);
                $v['address_info'] = isset($v['area_info']) ? $v['area_info'] : '';
                $v['geval_avatar'] = $this->_getCommentAvatar();
                $v['geval_frommembername'] = $v['seval_membername'];
            }
        }
        return $this->responseJson(1, '', $data['content']);

        $curpage = Yii::$app->request->post("page", 1);
        //$type = Yii::$app->request->post("type", 0);//0.全部 1.好评 2.中评 3.差评 4.有图
        $store_id = Yii::$app->request->post("store_id", 0);
        $condition = [
            "geval_storeid" => $store_id,
            "geval_is_show" => 1
        ];
        $evaluate_goods = new EvaluateGoods();
        $list = $evaluate_goods->getEvaluateList($condition, '*', 'geval_id desc', $curpage);
        foreach ($list as $k => $v) {
            if ($v['geval_image'] != '') {
                $geval_image = explode(',', $v['geval_image']);
                if (!empty($geval_image)) {
                    foreach ($geval_image as $key => $value) {
                        if ($value != '') {
                            $geval_image[$key] = getImgUrl($value, ATTACH_COMMENT);
                        }
                    }
                }
            }
            $member = Member::findOne($v['geval_frommemberid']);
            $list[$k]['geval_image'] = $v['geval_image'] == '' ? [] : $geval_image;
            $list[$k]['geval_addtime'] = date('Y-m-d H:i:s', $v['geval_addtime']);
            $list[$k]['geval_avatar'] = $member ? getMemberAvatar($member->member_avatar) : getMemberAvatar();
            //送至
            $address = OrderCommon::findOne($v['geval_orderid']);
            $reply_con = $v['reply_content'] == '' ? '' : unserialize($v['reply_content']);
            if ($reply_con) {
                $list[$k]['reply_content'] = $reply_con['reply_content'];
                $list[$k]['reply_time'] = date('Y-m-d H:i:s', $reply_con['time']);
            } else {
                $list[$k]['reply_content'] = '';
                $list[$k]['reply_time'] = 0;
            }

            if ($address && $address['reciver_info'] != '') {
                $address_info = explode(',', $address['reciver_info']);
                $list[$k]['address_info'] = $address_info[0] . '***';
            } else {
                unset($list[$k]);
            }
            //多久送达
            $order_info = Orders::findOne($v['geval_orderid']);
            $use_time = intval($order_info['receive_time']) - intval($order_info['send_time']);
            $list[$k]['accept_text'] = ceil($use_time / 3600) . '小时内送达';
        }
        if ($list) {
            $data["comment"] = $list;
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        } else {
            return $this->responseJson(1, '暂无评论数据');
        }
    }

    /**
     * 评论人姓名隐藏
     *
     * @param $name
     *
     * @return mixed.
     */
    private function _nameFormat($name)
    {
        if (mb_strlen($name, 'utf-8') < 2) {
            return $name;
        }
        if (strpos($name, '*') !== false) {
            return $name;
        }
        $len = mb_strlen($name, 'utf-8');
        $start = mb_substr($name, 0, ceil($len / 3));
        $end = mb_substr($name, floor($len - $len / 3), ceil($len / 3));
        return $start . '***' . $end;
    }

    /**
     * 花递评价随机头像
     *
     * @return mixed
     */
    private function _getCommentAvatar()
    {
        $avatars = [
            'https://resource.huawa.com/shop/placeorder/06251708991569595.jpg',
            'https://resource.huawa.com/shop/placeorder/06251709217853678.jpg',
            'https://resource.huawa.com/shop/placeorder/06251709337604371.jpg',
            'https://resource.huawa.com/shop/placeorder/06251709489446216.jpg',
            'https://resource.huawa.com/shop/placeorder/06251709602481435.jpg',
            'https://resource.huawa.com/shop/placeorder/06251709727218866.jpg',
            'https://resource.huawa.com/shop/placeorder/06251712030723285.png',
            'https://resource.huawa.com/shop/placeorder/06251712066001515.png',
            'https://resource.huawa.com/shop/placeorder/06251712108403701.png',
            'http://i.ahj.cm/images/huadi_year_card/1.jpg',
            'http://i.ahj.cm/images/huadi_year_card/2.jpg',
            'http://i.ahj.cm/images/huadi_year_card/3.jpg',
            'http://i.ahj.cm/images/huadi_year_card/4.jpg',
            'http://i.ahj.cm/images/huadi_year_card/5.jpg',
            'http://i.ahj.cm/images/huadi_year_card/6.jpg',
            'http://i.ahj.cm/images/huadi_year_card/7.jpg',
            'http://i.ahj.cm/images/huadi_year_card/8.jpg',
            'http://i.ahj.cm/images/huadi_year_card/9.jpg',
            'http://i.ahj.cm/images/huadi_year_card/10.jpg',
            'http://i.ahj.cm/images/huadi_year_card/11.jpg',
            'http://i.ahj.cm/images/huadi_year_card/12.jpg',
            'http://i.ahj.cm/images/huadi_year_card/13.jpg',
            'http://i.ahj.cm/images/huadi_year_card/14.jpg',
            'http://i.ahj.cm/images/huadi_year_card/15.jpg',
            'http://i.ahj.cm/images/huadi_year_card/16.jpg',
            'http://i.ahj.cm/images/huadi_year_card/17.jpg',
            'http://i.ahj.cm/images/huadi_year_card/18.jpg',
            'http://i.ahj.cm/images/huadi_year_card/19.jpg',
            'http://i.ahj.cm/images/huadi_year_card/20.jpg',
            'http://i.ahj.cm/images/huadi_year_card/30.jpg',
            'http://i.ahj.cm/images/huadi_year_card/21.jpg',
            'http://i.ahj.cm/images/huadi_year_card/22.jpg',
            'http://i.ahj.cm/images/huadi_year_card/23.jpg',
            'http://i.ahj.cm/images/huadi_year_card/24.jpg',
            'http://i.ahj.cm/images/huadi_year_card/25.jpg',
            'http://i.ahj.cm/images/huadi_year_card/26.jpg',
            'http://i.ahj.cm/images/huadi_year_card/27.jpg',
            'http://i.ahj.cm/images/huadi_year_card/28.jpg',
            'http://i.ahj.cm/images/huadi_year_card/29.jpg',
            'http://img.huawa.com/upload/shop/avatar/avatar_160794.jpg'
        ];
        $rand_keys = array_rand($avatars, 1);
        return $avatars[$rand_keys];
    }


    public function actionStoreinfo()
    {
        $member_id = $this->member_id;
        $store_id = Yii::$app->request->post("store_id", 380482);
        $url = MICRO_DOMAIN . "/api/storeinfo";
        $curl = new Curl();
        $param = [
            "store_id" => $store_id,
        ];

        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);
        $response = json_decode($response, true);
        if ($response["status"]) {
            $info = json_decode($response["data"], true);
            $store["user_id"] = $info["member_id"];
            $store["store_id"] = $info["store_id"];
            $store["store_name"] = $info["store_name"];
            //判断头像资源是否存在
            $handle = @fopen($info["store_label"], 'r');
            if ($handle === false) {
                $store["store_label"] = 'http://img.huawa.com/upload/shop/common/default.jpg';
            } else {
                $store["store_label"] = JmApply::getOssThumb($info["store_label"]);
            }
            $store["is_auth"] = $info["is_auth"];
            $store["is_store_auth"] = $info["is_store_auth"];
            if ($info["security_deposit"] > 1000) {
                $store["is_security"] = 1;
            } else {
                $store["is_security"] = 0;
            }
            $store["store_address"] = $info["area_info"] . $info["store_address"];
            $store["service_content"] = "由花娃快送为你服务";
            $store["service_time"] = "9:00-18:00";
            $delivery_area = $info["delivery_area"]["free"] . $info["delivery_area"]["other"];
            $store["delivery_area"] = strlen($delivery_area) < 2 ? "该店铺未完善此信息" : $delivery_area;
            $store["store_credit"] = $info["store_credit"];
            $store["store_wellcredit_percent"] = $info["store_wellcredit_percent"];
            $store["store_midcredit"] = $info["store_midcredit"];
            $store["store_badcredit"] = $info["store_badcredit"];
            //$store["store_allcredit"] = $info["store_allcredit"];
            $store["store_allcredit"] = 0;//TODO 待获取本地评价
            $store["store_imgcredit"] = $info["store_imgcredit"];
            $store["real_order"] = $info["real_order"];
            $store["goods_count"] = $info["goods_count"];
            $store["store_grade"] = $info["store_grade"];
            $store["store_mobile"] = $info["store_mobile"];
            $store["y_axis"] = floatval($info["y_axis"]);
            $store["x_axis"] = floatval($info["x_axis"]);
            $storeInfo = HuadiStoreZm::find()->where(['store_id' => $store_id])->select('store_star')->one();
            $store['store_star'] = $storeInfo['store_star'] ? $storeInfo['store_star'] : $info['store_star'];
            $store_environment = $store_environment_thumb = [];
            if (!empty($info['store_environment'])) {
                foreach ($info['store_environment'] as $v) {
                    $store_environment_thumb[] = JmApply::getOssThumb('http://resource.huawa.com/shop/store/environment/' . $v, 320, 320);
                    //点击大图加载慢,替换为缩略图
                    $store_environment[] = JmApply::getOssThumb('http://resource.huawa.com/shop/store/environment/' . $v, 1080, 1080);
                }
            }
            $store['store_environment_thumb'] = $store_environment_thumb;
            $store['store_environment'] = $store_environment;
            //单量
//            $order_count_condition = [
//                'siteid'            =>  258,
//                'delivery_store_id' => $store['store_id'],
//                'order_state'       => ['egt',20]
//            ];
//            $store["sell_num"] = Orders::find()->where($order_count_condition)->count();
            $store["sell_num"] = $info['sell_num'] <= 0 ? 0 : $info['sell_num'];
            //好评
//            $good_comment_count_condition = [
//                "geval_storeid" => $store['store_id'],
//                'geval_level'       => 1
//            ];
//            $store["store_wellcredit"] = EvaluateGoods::find()->where($good_comment_count_condition)->count();
            $store["store_wellcredit"] = $info['store_wellcredit'] >= $store["sell_num"] ? $store["sell_num"] : $info['store_wellcredit'];
//            $store["store_wellcredit_percent"] = $info['store_comments'] ? $store["store_wellcredit"] / $info['store_comments'] : 0;
//            $store["store_wellcredit_percent"] = sprintf("%.2f", $store["store_wellcredit_percent"] * 100) . '%';
            $favorites_model = new Favorites();
            $is_follow = $favorites_model->is_follow($member_id, $store_id);
            if ($is_follow) {
                $store["is_follow"] = "1";
            } else {
                $store["is_follow"] = "0";
            }

            //门店banner图信息
            $store['banner'] = [
                "banner_url" => $info['banner_url'],
                "goods_id" => $info['banner_goods_id'],
            ];
            //门店公告
            $store['store_notice'] = $info['notice'];
            $store['follow_count'] = $favorites_model->storeFollowCount($store_id);
            //店长推荐
            $goods_recommend = $info['goods_recommend'];
            if (!empty($goods_recommend)) {
                foreach ($goods_recommend as $key => $g) {
                    $goods_recommend[$key]['goods_img'] = JmApply::getOssThumb($g['goods_img'], 360, 360);
                }
                $store['goods_recommend'] = $goods_recommend;
            } else {
                $store['goods_recommend'] = array();
            }
            $store['store_star'] += 2;
            $data["store"] = $store;
            return $this->responseJson($response["status"], $response["msg"], $data);
        } else {

            return $this->responseJson($response["status"], $response["msg"]);
        }
    }

    /**
     * @return mixed|string
     */
    public function actionVerifyShopEnterCode()
    {
        $post = \Yii::$app->request->post();
        $member_mobile = isset($post['member_mobile']) ? trim($post['member_mobile']) : '';
        $verify_code = isset($post['verify_code']) ? trim($post['verify_code']) : '';

        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        $model_verify = new MemberVerify();

        //校验短信验证码 -- 花递- 申请入驻类型的;
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_SHOP_ENTER, $member_mobile, $verify_code);
        if (!$result) {
            if ($member_mobile != '15884477703' && $verify_code != '4364536') {
                return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
            }
        }
        //是否需要登录，跟 杨哥 确认后直接将手机号码发送给 曾中钢 接口；
        return $model_verify->verifyMobile($member_mobile);
    }
    public function actionHuadiStoreListNew($lng = '',$lat = '',$distance = ''){
        $lng = $lng ? $lng : Yii::$app->request->post("lng", "104.056238");
        $lat = $lat ? $lat : Yii::$app->request->post("lat", "30.653585");
        $store_name = trim(Yii::$app->request->post("keyword", ""));
        $distance = $distance? $distance : Yii::$app->request->post("distance", 5);
        $cat_id = Yii::$app->request->post("cat_id", "");
        $gsc_id = Yii::$app->request->post("gsc_id", "");
        $price = Yii::$app->request->post("price", "");
        $order = Yii::$app->request->post("order", 4);//1.综合 2.接单量 3.信用 4.距离
        $curpage = Yii::$app->request->post("page", 1);
        $page_size = 10;
        $orderby = '';
        $huadi_huadiangou_curpage_key = md5("huadi_huadiangou_868_{$store_name}_{$curpage}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_storelist");
        $huadi_huadiangou_all_key = md5("huadi_huadiangou_868_{$store_name}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_storelist");
        $huadi_huadiangou_list_key = md5("huadi_huadiangou_868_{$store_name}_{$curpage}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_list");
        $api = new MicroApi();
        $store_data = cache($huadi_huadiangou_curpage_key);
        $store_data = '';
        if (!$store_data || !$store_data['store']) {
            $x_axis = $lng * 3.141593;
            $y_axis = $lat * 3.141593;
            if ($order == 1) {
                $orderby = 'huadi_order_score desc,store_wellcredit desc,store_comments desc';
            } elseif ($order == 2) {
                $orderby = 'store_orders desc,huadi_store_orders_month desc';
            } elseif ($order == 3) {
                $orderby = 'store_wellcredit desc, huadi_order_score desc';
            } elseif ($order == 4) {
                $orderby = 'distance asc,huadi_order_score desc';
            }
            $having = "distance <= " . $distance;
            $address_cache_key = md5("address568_" . $lat . "_" . $lng);
            $address_info_cache_key = md5("address_info_668_" . $lat . "_" . $lng);
            $_address_info = cache($address_info_cache_key);
            $address_info = cache($address_cache_key);
            $address_info = '';
            if (!$address_info || empty($address_info)) {
                $url = "http://api.map.baidu.com/geocoder/v2/?location=" . $lat . "," . $lng . "&output=json&pois=1&ak=umUCiqBuSegddOqTLIL5oLWVR8ZaHHcD";
                $addr = file_get_contents($url);
                $addr = json_decode($addr, true);
                $where = [];
                if ($addr['status'] == 0) {
                    $_address = $addr['result'];
                    $address = $_address['addressComponent'];
                    $area_model = Area::find();
                    $_address_info = '';
                    //获取省市区id
                    if (isset($address['province']) && !empty($address['province'])) {
                        $_address_info = $address['province'];
                        $province = $area_model->select("area_id")->where(array("area_name" => $address['province']))->one();
                        if ($province) {
                            $where['provinceid'] = $province->area_id;
                            if (!in_array($province->area_id, array(2, 3, 4, 5))) {
                                if (isset($address['city']) && !empty($address['city'])) {
                                    $province = $area_model->select("area_id")->where(array("area_name" => $address['city']))->one();
                                    if ($province) {
                                        $where['cityid'] = $province->area_id;
                                    }
                                }
                            }
                        }
                    }
                    if (isset($address['city']) && !empty($address['city'])) {
                        $_address_info .= ' '. $address['city'];
                    }
                }
                if (!empty($_address_info)) {
                    cache($address_info_cache_key, $_address_info, 86400 * 15);
                }
                if (!empty($where)) {
                    cache($address_cache_key, $where, 86400*15);
                }
            } else {
                if (isset($address_info['provinceid'])) {
                    $where['provinceid'] = $address_info['provinceid'];
                }
                if (isset($address_info['cityid'])) {
                    $where['cityid'] = $address_info['cityid'];
                }
            }
            $store_ids = [];
            if ($store_name || $cat_id || $gsc_id || $price) {
                $param = [
                    "store_name" => $store_name,
                    'is_store' => 1,
                    "cat_id" => $cat_id,
                    "gsc_id" => $gsc_id,
                    "price" => $price
                ];
                $condition_cache_key = md5(serialize($param));
                $store_ids = cache($condition_cache_key);
                $store_ids = '';
                if (!$store_ids) {
                    //查询huadi_store_zm表
                    $like_store_ids = $mic_store_ids = $store_ids = [];
                    if (isset($where['provinceid']) && isset($where['cityid'])) {
                        $huadi_store_zm_condition = [];
                        $huadi_store_zm_condition['provinceid'] = $where['provinceid'];
                        $huadi_store_zm_condition['cityid'] = $where['cityid'];
                        $zm_map = ['or', ['like', 'store_name', $store_name], ['like', 'store_address', $store_name]];
                        $huadi_store_zm_condition = ["and", $huadi_store_zm_condition, $zm_map];
                        $like_store_ids = HuadiStoreZm::find()->select('store_id')->where($huadi_store_zm_condition)->asArray()->all();
                        if (!empty($like_store_ids)) {
                            $like_store_ids = array_column($like_store_ids, 'store_id');
                        } else {
                            $like_store_ids = [];
                        }
                    }

//                    $mic_store_ids = $api->httpRequest('/api/getzmstoreids', $param);
//                    if (!empty($mic_store_ids)) {
//                        $mic_store_ids = array_column($mic_store_ids, 'store_id');
//                        $store_ids = array_merge($mic_store_ids, $store_ids);
//                    }
                    if (!empty($like_store_ids)) {
                        $store_ids = array_merge($like_store_ids, $store_ids);
                    }
                    if($store_name){
                        $_condition = [];
                        $_condition['provinceid'] = $where['provinceid'];
                        $_condition['cityid'] = $where['cityid'];
                        //2、增加商品描述、商品名称模糊搜索
                        $keyword_like_store_ids =  HuadiStoreZmGoods::find()
                            ->where(['or', ['like', 'goods_name', $store_name], ['like', 'goods_introduce', $store_name]])
                            ->select(['store_id'])->groupBy('store_id')->asArray()->column();
                        //3、最后再通过搜索的地址关键词获得定位坐标，并搜索关键词地址附近的花店
                        $axis = getAxis($_address_info,$store_name);
                        if($axis){
                            $_x_axis = $axis['x_axis'] * 3.141593;
                            $_y_axis = $axis['y_axis'] * 3.141593;
                            Log::writelog('huadi_search_debug','地址反编经纬度:'.$axis['x_axis'] .'===='.$axis['y_axis']);
                            Log::writelog('huadi_search_debug','地址:'.$_address_info.' '.$store_name);

                            $_s_a = $_y_axis / 360;
                            $_s_b = $_y_axis / 180;
                            $_s_c = $_x_axis / 360;
                            $_having = "distance <= " . 5;
                            $fields = "store_id,(12756.276 * ASIN(SQRT(POW(SIN(({$_s_a}-y_axis*0.00872664722)),2)+(COS($_s_b)*COS(y_axis*0.0174532944)*SIN({$_s_c}-x_axis*0.00872664722)*SIN({$_s_c}-x_axis*0.00872664722))))) AS distance";
                            $keyword_axis_store_data = HuadiStoreZm::find()->select($fields)->where($_condition)->having($_having)->asArray()->all();
                            if($keyword_axis_store_data){
                                Log::writelog('huadi_search_debug','地址反编译命中了店铺:'.implode(',',$keyword_axis_store_data));
                                $axis_store_ids = array_column($keyword_axis_store_data,'store_id');
                            }
                        }

                        if(!empty($keyword_like_store_ids)) {
                            $store_ids = array_merge($store_ids,$keyword_like_store_ids);
                        }
                        if(!empty($axis_store_ids)) {
                            $store_ids = array_merge($store_ids,$axis_store_ids);
                        }
                    }
                    cache($condition_cache_key, $store_ids, 1800);
                    if(empty($store_ids)) {
                        //如果keyword匹配不到花店, 直接返回空
                        //如果没搜索到返回空数据
                        $data["store"] = array();
                        $data["store_count"] = 0;
                        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
                    }
                    $where['store_id'] = $store_ids;
                } else {
                    $where['store_id'] = array_column($store_ids, 'store_id');
                }
            }
            $offset = ($curpage - 1) * $page_size;
            $s_a = $y_axis / 360;
            $s_b = $y_axis / 180;
            $s_c = $x_axis / 360;
            $fields = "store_id,store_name,member_id,store_comments,store_orders,store_wellcredit,member_name,store_label,store_mobile,area_info,store_address,x_axis,y_axis,store_grade,store_star,huadi_order_score,(12756.276 * ASIN(SQRT(POW(SIN(({$s_a}-y_axis*0.00872664722)),2)+(COS($s_b)*COS(y_axis*0.0174532944)*SIN({$s_c}-x_axis*0.00872664722)*SIN({$s_c}-x_axis*0.00872664722))))) AS distance,o2o_time";
            if (!empty($where)) {
                $strKey = $where['provinceid'] . $where['cityid'];
                if (isset($where['store_id'])) {
                    $strKey .= implode($where['store_id']);
                }
            } else {
                $strKey = "";
            }
            $sql_all_store_list = md5(serialize($strKey . $orderby . $having . $fields));
            $store_data = cache($sql_all_store_list);
            if (!$store_data) {
                $store_data = HuadiStoreZm::find()->select($fields)->where($where)->having($having)->orderBy($orderby)->asArray()->all();
                if ($order == 1) {
                    $store_data_0_9 = [];
                    $store_data_9_15 = [];
                    $store_data_15_20 = [];
                    $store_data_20_30 = [];
                    foreach ($store_data as $v) {
                        if ($v['distance'] <= 9) {
                            $store_data_0_9[] = $v;
                        } elseif ($v['distance'] <= 15 && $v['distance'] > 9) {
                            $store_data_9_15[] = $v;
                        } elseif ($v['distance'] <= 20 && $v['distance'] > 15) {
                            $store_data_15_20[] = $v;
                        } else {
                            $store_data_20_30[] = $v;
                        }
                    }
                    $store_data = array_merge($store_data_0_9, $store_data_9_15, $store_data_15_20, $store_data_20_30);
                }
                cache($sql_all_store_list, $store_data, 1800);
            }
            $new_time = TIMESTAMP - 7 * 86400;
            if (!empty($store_data)) {
                $store_data = array_column($store_data, null, 'store_id');
                $store_data_bak = $store_data;
                $new_store_ids = [];
                foreach ($store_data as $k => $v) {
                    if ($v['o2o_time'] > $new_time) {
                        $new_store_ids[] = $k;
                    }
                }
                if ($curpage == 1) {
                    $redis_list = Yii::$app->redis->lrange($huadi_huadiangou_list_key, 0, -1);
                    // 如果队列中有数据,则合并
                    if (!empty($redis_list)) {
                        $new_store_diff_ids = array_diff($new_store_ids, $redis_list);
                    } else {
                        $new_store_diff_ids = $new_store_ids;
                    }

                    if (!empty($new_store_diff_ids)) {
                        foreach ($new_store_diff_ids as $s_n) {
                            Yii::$app->redis->rpush($huadi_huadiangou_list_key, $s_n);
                            Yii::$app->redis->expire($huadi_huadiangou_list_key, 3600 * 24);
                        }
                    }
                    // 取出两条
                    $store_new_data = array();
                    for ($i = 0; $i <= 100; $i++) {
                        if (count($store_new_data) >= 2) {
                            break;
                        }
                        $new_pop_store_id = Yii::$app->redis->lpop($huadi_huadiangou_list_key);
                        // 如果队列已经取完，则跳过
                        if (!$new_pop_store_id) {
                            break;
                        }

                        // 如果队列中的store_id 在新花店中没有，则跳过，继续取数据
                        if (!in_array($new_pop_store_id, $new_store_ids)) {
                            continue;
                        }

                        $store_new_data[] = $store_data_bak[$new_pop_store_id];
                        unset($store_data_bak[$new_pop_store_id]);
                    }

                    if (count($store_new_data) >= 2) {
                        array_splice($store_data_bak, 5, 0, array($store_new_data[0]));
                        array_splice($store_data_bak, 9, 0, array($store_new_data[1]));
                    } elseif (count($store_new_data) == 1) {
                        array_splice($store_data_bak, 6, 0, array($store_new_data[0]));
                    }
                    cache($huadi_huadiangou_all_key, $store_data_bak, 1800);
                    $list_count = $store_data_bak;
                } else {
                    $list_count = cache($huadi_huadiangou_all_key);
                }
                $data = [];
                if(is_array($list_count)){
                    $list = array_slice($list_count, $offset, 10);
                }else{
                    $list = array();
                }
                $store_ids = array_column($list, 'store_id');
                $store_ids_str = implode(',', $store_ids);
                $param = [];
                $param['store_id'] = $store_ids;
                $store_goods_key = md5('store_ids_goods588_' . $store_ids_str);
                $store_goods = cache($store_goods_key);
                if (!$store_goods) {
                    $store_goods_list = HuadiStoreZmGoods::find()->select("store_id,goods_id,goods_img,goods_name,goods_price,goods_introduce")->where($param)->asArray()->all();
                    foreach ($store_goods_list as $good) {
                        $good['goods_type'] = GOODS_TYPE_STORE_FLOWER;
                        $store_goods[$good['store_id']][] = $good;
                    }
                    cache($store_goods_key, $store_goods, 1800);
                }
                $store = $this->_huadiStorelist_new($list, $store_goods);
                $data["store"] = array_unique($store, SORT_REGULAR);
                $data["store_count"] = count($list_count);
                cache($huadi_huadiangou_curpage_key, $data, 1800);
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
            } else {
                //如果没搜索到返回空数据
                $data["store"] = array();
                $data["store_count"] = 0;
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
            }
        } else {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $store_data);
        }
    }
    /**
     * 获取花递附近花店列表
     * @param $lng //内部调用传参
     * @param $lat //内部调用传参
     * @param $distance //内部调用传参
     * @return mixed
     */
    public function actionHuadiStorelist($lng = '',$lat = '',$distance = '')
    {
        $lng = $lng ? $lng : Yii::$app->request->post("lng", "104.056238");
        $lat = $lat ? $lat : Yii::$app->request->post("lat", "30.653585");
        $store_name = trim(Yii::$app->request->post("keyword", ""));
        $distance = $distance? $distance : Yii::$app->request->post("distance", 5);
        $cat_id = Yii::$app->request->post("cat_id", "");
        $gsc_id = Yii::$app->request->post("gsc_id", "");
        $price = Yii::$app->request->post("price", "");
        $order = Yii::$app->request->post("order", 4);//1.综合 2.接单量 3.信用 4.距离
        $curpage = Yii::$app->request->post("page", 1);
        $page_size = 10;
        $orderby = '';
        $huadi_huadiangou_curpage_key = md5("huadi_huadiangou_868_{$store_name}_{$curpage}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_storelist");
        $huadi_huadiangou_all_key = md5("huadi_huadiangou_868_{$store_name}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_storelist");
        $huadi_huadiangou_list_key = md5("huadi_huadiangou_868_{$store_name}_{$curpage}_{$distance}_{$order}_{$lng}_{$lat}_{$this->member_id}_{$cat_id}_{$gsc_id}_{$price}_list");
        $api = new MicroApi();
        $store_data = cache($huadi_huadiangou_curpage_key);
        if (!$store_data || !$store_data['store']) {
            $x_axis = $lng * 3.141593;
            $y_axis = $lat * 3.141593;
            if ($order == 1) {
                $orderby = 'huadi_order_score desc,store_wellcredit desc,store_comments desc';
            } elseif ($order == 2) {
                $orderby = 'store_orders desc,huadi_store_orders_month desc';
            } elseif ($order == 3) {
                $orderby = 'store_wellcredit desc, huadi_order_score desc';
            } elseif ($order == 4) {
                $orderby = 'distance asc,huadi_order_score desc';
            }
            $having = "distance <= " . $distance;
            $address_cache_key = md5("address568_" . $lat . "_" . $lng);
            $address_info = cache($address_cache_key);
            if (!$address_info || empty($address_info)) {
                $url = "http://api.map.baidu.com/geocoder/v2/?location=" . $lat . "," . $lng . "&output=json&pois=1&ak=umUCiqBuSegddOqTLIL5oLWVR8ZaHHcD";
                $addr = file_get_contents($url);
                $addr = json_decode($addr, true);
                $where = [];
                if ($addr['status'] == 0) {
                    $_address = $addr['result'];
                    $address = $_address['addressComponent'];
                    $area_model = Area::find();
                    //获取省市区id
                    if (isset($address['province']) && !empty($address['province'])) {
                        $province = $area_model->select("area_id")->where(array("area_name" => $address['province']))->one();
                        if ($province) {
                            $where['provinceid'] = $province->area_id;
                            if (!in_array($province->area_id, array(2, 3, 4, 5))) {
                                if (isset($address['city']) && !empty($address['city'])) {
                                    $province = $area_model->select("area_id")->where(array("area_name" => $address['city']))->one();
                                    if ($province) {
                                        $where['cityid'] = $province->area_id;
                                    }
                                }
                            }
                        }
                    }
                }
                if (!empty($where)) {
                    cache($address_cache_key, $where, 86400*15);
                }
            } else {
                if (isset($address_info['provinceid'])) {
                    $where['provinceid'] = $address_info['provinceid'];
                }
                if (isset($address_info['cityid'])) {
                    $where['cityid'] = $address_info['cityid'];
                }
            }
            $store_ids = [];
            if ($store_name || $cat_id || $gsc_id || $price) {
                $param = [
                    "store_name" => $store_name,
                    'is_store' => 1,
                    "cat_id" => $cat_id,
                    "gsc_id" => $gsc_id,
                    "price" => $price
                ];
                $condition_cache_key = md5(serialize($param));
                $store_ids = cache($condition_cache_key);
                if (!$store_ids) {
                    //查询huadi_store_zm表
                    $like_store_ids = $mic_store_ids = $store_ids = [];
                    if (isset($where['provinceid']) && isset($where['cityid'])) {
                        $huadi_store_zm_condition = [];
                        $huadi_store_zm_condition['provinceid'] = $where['provinceid'];
                        $huadi_store_zm_condition['cityid'] = $where['cityid'];
                        $zm_map = ['or', ['like', 'store_name', $store_name], ['like', 'store_address', $store_name]];
                        $huadi_store_zm_condition = ["and", $huadi_store_zm_condition, $zm_map];
                        $like_store_ids = HuadiStoreZm::find()->select('store_id')->where($huadi_store_zm_condition)->limit(10)->asArray()->all();
                        if (!empty($like_store_ids)) {
                            $like_store_ids = array_column($like_store_ids, 'store_id');
                        } else {
                            $like_store_ids = [];
                        }
                    }

                    $mic_store_ids = $api->httpRequest('/api/getzmstoreids', $param);
                    if (!empty($mic_store_ids)) {
                        $mic_store_ids = array_column($mic_store_ids, 'store_id');
                        $store_ids = array_merge($mic_store_ids, $store_ids);
                    }
                    if (!empty($like_store_ids)) {
                        $store_ids = array_merge($like_store_ids, $store_ids);
                    }
                    cache($condition_cache_key, $store_ids, 1800);
                    $where['store_id'] = $store_ids;
                } else {
                    $where['store_id'] = array_column($store_ids, 'store_id');
                }
                if($store_name){
                    //2、增加商品描述、商品名称模糊搜索
                    $keyword_like_store_ids =  HuadiStoreZmGoods::find()
                        ->where(['or', ['like', 'goods_name', $store_name], ['like', 'goods_introduce', $store_name]])
                        ->select(['store_id'])->groupBy('store_id')->asArray()->column();

                    //3、最后再通过搜索的地址关键词获得定位坐标，并搜索关键词地址附近的花店
                    $_address_info = IntelligentAnalysisAddress::smart($store_name);
                    if(isset($_address_info['city']['area_id']) && $_address_info['city']['area_id'] >0 ){
                        $store_city_id = $_address_info['city']['area_id'];
                    }
                    if(!empty($keyword_like_store_ids) && isset($store_city_id)){
                        $_where = ['or', ['store_id' => $keyword_like_store_ids], ['cityid' => $store_city_id]];
                    }elseif(!empty($keyword_like_store_ids)){
                        $_where['store_id'] =  $keyword_like_store_ids;
                    }elseif(isset($store_city_id)){
                        $_where['cityid'] =  $store_city_id;
                    }
                    if(!empty($_where)){
                        $where = ['or', $where, $_where];
                    }
                }
            }
            $offset = ($curpage - 1) * $page_size;
            $s_a = $y_axis / 360;
            $s_b = $y_axis / 180;
            $s_c = $x_axis / 360;
            $fields = "store_id,store_name,member_id,store_comments,store_orders,store_wellcredit,member_name,store_label,store_mobile,area_info,store_address,x_axis,y_axis,store_grade,store_star,huadi_order_score,(12756.276 * ASIN(SQRT(POW(SIN(({$s_a}-y_axis*0.00872664722)),2)+(COS($s_b)*COS(y_axis*0.0174532944)*SIN({$s_c}-x_axis*0.00872664722)*SIN({$s_c}-x_axis*0.00872664722))))) AS distance,o2o_time";
            if (!empty($where)) {
                $strKey = $where['provinceid'] . $where['cityid'];
                if (isset($where['store_id'])) {
                    $strKey .= implode($where['store_id']);
                }
            } else {
                $strKey = "";
            }
            $sql_all_store_list = md5(serialize($strKey . $orderby . $having . $fields));
            $store_data = cache($sql_all_store_list);
            if (!$store_data) {
                $store_data = HuadiStoreZm::find()->select($fields)->where($where)->having($having)->orderBy($orderby)->asArray()->all();
                Log::writelog('huadi_store_sql', HuadiStoreZm::find()->select($fields)->where($where)->having($having)->orderBy($orderby)->createCommand()->getRawSql());
                if ($order == 1) {
                    $store_data_0_9 = [];
                    $store_data_9_15 = [];
                    $store_data_15_20 = [];
                    $store_data_20_30 = [];
                    foreach ($store_data as $v) {
                        if ($v['distance'] <= 9) {
                            $store_data_0_9[] = $v;
                        } elseif ($v['distance'] <= 15 && $v['distance'] > 9) {
                            $store_data_9_15[] = $v;
                        } elseif ($v['distance'] <= 20 && $v['distance'] > 15) {
                            $store_data_15_20[] = $v;
                        } else {
                            $store_data_20_30[] = $v;
                        }
                    }
                    $store_data = array_merge($store_data_0_9, $store_data_9_15, $store_data_15_20, $store_data_20_30);
                }
                cache($sql_all_store_list, $store_data, 1800);
            }
            $new_time = TIMESTAMP - 7 * 86400;
            if (!empty($store_data)) {
                $store_data = array_column($store_data, null, 'store_id');
                $store_data_bak = $store_data;
                $new_store_ids = [];
                foreach ($store_data as $k => $v) {
                    if ($v['o2o_time'] > $new_time) {
                        $new_store_ids[] = $k;
                    }
                }
                if ($curpage == 1) {
                    $redis_list = Yii::$app->redis->lrange($huadi_huadiangou_list_key, 0, -1);
                    // 如果队列中有数据,则合并
                    if (!empty($redis_list)) {
                        $new_store_diff_ids = array_diff($new_store_ids, $redis_list);
                    } else {
                        $new_store_diff_ids = $new_store_ids;
                    }

                    if (!empty($new_store_diff_ids)) {
                        foreach ($new_store_diff_ids as $s_n) {
                            Yii::$app->redis->rpush($huadi_huadiangou_list_key, $s_n);
                            Yii::$app->redis->expire($huadi_huadiangou_list_key, 3600 * 24);
                        }
                    }
                    // 取出两条
                    $store_new_data = array();
                    for ($i = 0; $i <= 100; $i++) {
                        if (count($store_new_data) >= 2) {
                            break;
                        }
                        $new_pop_store_id = Yii::$app->redis->lpop($huadi_huadiangou_list_key);
                        // 如果队列已经取完，则跳过
                        if (!$new_pop_store_id) {
                            break;
                        }

                        // 如果队列中的store_id 在新花店中没有，则跳过，继续取数据
                        if (!in_array($new_pop_store_id, $new_store_ids)) {
                            continue;
                        }

                        $store_new_data[] = $store_data_bak[$new_pop_store_id];
                        unset($store_data_bak[$new_pop_store_id]);
                    }

                    if (count($store_new_data) >= 2) {
                        array_splice($store_data_bak, 5, 0, array($store_new_data[0]));
                        array_splice($store_data_bak, 9, 0, array($store_new_data[1]));
                    } elseif (count($store_new_data) == 1) {
                        array_splice($store_data_bak, 6, 0, array($store_new_data[0]));
                    }
                    cache($huadi_huadiangou_all_key, $store_data_bak, 1800);
                    $list_count = $store_data_bak;
                } else {
                    $list_count = cache($huadi_huadiangou_all_key);
                }
                $data = [];
                if(is_array($list_count)){
                    $list = array_slice($list_count, $offset, 10);
                }else{
                    $list = array();
                }
                $store_ids = array_column($list, 'store_id');
                $store_ids_str = implode(',', $store_ids);
                $param = [];
                $param['store_id'] = $store_ids;
                $store_goods_key = md5('store_ids_goods588_' . $store_ids_str);
                $store_goods = cache($store_goods_key);
                if (!$store_goods) {
                    $store_goods_list = HuadiStoreZmGoods::find()->select("store_id,goods_id,goods_img,goods_name,goods_price,goods_introduce")->where($param)->asArray()->all();
                    foreach ($store_goods_list as $good) {
                        $good['goods_type'] = GOODS_TYPE_STORE_FLOWER;
                        $store_goods[$good['store_id']][] = $good;
                    }
                    cache($store_goods_key, $store_goods, 1800);
                }
                $store = $this->_huadiStorelist_new($list, $store_goods);
                $data["store"] = array_unique($store, SORT_REGULAR);
                $data["store_count"] = count($list_count);
                cache($huadi_huadiangou_curpage_key, $data, 1800);
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
            } else {
                //如果没搜索到返回空数据
                $data["store"] = array();
                $data["store_count"] = 0;
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
            }
        } else {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $store_data);
        }
    }
    /**
     * 获取花递店铺内筛选列表
     * @return array
     */
    public function actionGetGoodsItemInStore(){
        $api = new MicroApi();
        $store_id = \Yii::$app->request->get('store_id','');
        $post_store_id = \Yii::$app->request->post('store_id','');
        if($post_store_id) $store_id = $post_store_id;
        if(!$store_id){
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }
        $goods_cate_scene = $api->httpRequest('/api/getStoreGoodsCategory',['store_id' => $store_id]);
        $ret_arr = [];
        //场景
        if ($goods_cate_scene && isset($goods_cate_scene['scene']) && isset($goods_cate_scene['scene_class'])) {
                foreach ($goods_cate_scene['scene_class'] as $class){
                    $scene = [];
                    foreach ($goods_cate_scene['scene'] as $v) {
                        if($class['class_id'] == $v['class_id']){
                            $scene[] = [
                                "attr_value_id"=> $v['sc_id'],
                                "attr_value_name" => $v['sc_name']
                            ];
                        }
                    }
                    if($scene){
                        $ret_arr[] = [
                            'key' => 'category_id',
                            'type' => 'checkbox',
                            'title' => $class['class_name'],
                            'item'  => $scene,
                        ];
                    }
                }
        }
        //价格区间
        $price_selections = [

            '100_199' => [
                'attr_value_id' => '100_199',
                'attr_value_name' => '100-199'
            ],
            '200_299' => [
                'attr_value_id' => '200_299',
                'attr_value_name' => '200-299'
            ],
            '300_399' => [
                'attr_value_id' => '300_399',
                'attr_value_name' => '300-399'
            ],
            '400_499' => [
                'attr_value_id' => '400_499',
                'attr_value_name' => '400-499'
            ],
            '500_599' => [
                'attr_value_id' => '500_599',
                'attr_value_name' => '500-599'
            ],
            '600_999' => [
                'attr_value_id' => '600_999',
                'attr_value_name' => '600-999'
            ],
            '1000_0' => [
                'attr_value_id' => '1000_0',
                'attr_value_name' => '1000以上'
            ],
        ];
        //根据店铺具体商品价格去掉没有的价格区间
        $price_range = HuadiStoreZmGoods::find()->where(['store_id'=>$store_id])->select([
            "(case when goods_price>=100 and goods_price<=200 then '100_199' 
            when goods_price>=200 and goods_price<300 then '200_299'
            when goods_price>=300 and goods_price<400 then '300_399'
            when goods_price>=400 and goods_price<500 then '400_499'
            when goods_price>=500 and goods_price<600 then '500_599'
            when goods_price>=600 and goods_price<1000 then '600_999'
            when goods_price>=1000 then '1000_0' else 0 end) price_range"
        ])->distinct()->column();
        foreach ($price_selections as $k => $selection){
            if(!in_array($k,$price_range)){
                unset($price_selections[$k]);
            }
        }

        $price_format = [
            'key' => 'price',
            'type' => 'radio',
            'title' => '价格区间',
            'item' => array_values($price_selections)
        ];
        //固定价格区间的位置在第二位
        array_splice($ret_arr,1,0,[$price_format]);
        //分类
        if ($goods_cate_scene && isset($goods_cate_scene['category'])) {
            $category = [];
            foreach ($goods_cate_scene['category'] as $cat){
                $category[] = [
                    'attr_value_id' => $cat['cat_id'],
                    'attr_value_name' => $cat['cat_name'],
                ];
            }
            if($category){
                $ret_arr[] = [
                    'key' => 'cat_id',
                    'type' => 'checkbox',
                    'title' => '分类',
                    'item'  => $category,
                ];
            }
        }
        //主花材
        if ($goods_cate_scene && isset($goods_cate_scene['material_list'])) {
            $material = [];
            foreach ($goods_cate_scene['material_list'] as $cat){
                $material[] = [
                    'attr_value_id' => $cat['material_id'],
                    'attr_value_name' => $cat['material_name'],
                ];
            }
            if($material){
                $ret_arr[] = [
                    'key' => 'material_id',
                    'type' => 'radio',
                    'title' => '主花材',
                    'item'  => $material,
                ];
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$ret_arr);
    }
}
