<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Log;
use common\components\Message;
use common\components\MicroApi;
use common\components\WeixinSubscribeMsg;
use common\helper\SensitiveWord;
use common\models\Adv;
use common\models\AlyContentSecurity;
use common\models\Article;
use common\models\Attribute;
use common\models\AttributeValue;
use common\models\Cart;
use common\models\CommentStar;
use common\models\FlowerArtVideo;
use common\models\Goods;
use common\models\GoodsAttrIndex;
use common\models\GoodsClass;
use common\models\GoodsCommon;
use common\models\GroupShoppingGoods;
use common\models\HuadiYearCardOrders;
use common\models\Notify;
use common\models\PXianshiGoods;
use common\models\Search;
use common\models\Setting;
use common\models\VideoComment;
use common\models\VideoStar;
use Yii;
use yii\base\InvalidParamException;
use yii\db\Exception;
use yii\db\Expression;
use yii\imagine\Image;
use yii\web\BadRequestHttpException;
use frontend\controllers\BaseController;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * Flower controller
 */
class FlowerController extends BaseController
{

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

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

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionUpload()
    {
        $img = base64_encode(file_get_contents("http://img.huawa.com/upload/shop/placeorder/05600943053780904.png"));
        $result = \huadi\Upload::upload($img);
        print_r($result);
        die;
        /*     $alipay = Yii::$app->get("alipay");
             print_r($alipay->pay());die;

             Image::frame("G:/www/huadi/static/upload/lession/201807/11a3fb2b2949aa121c4836624ba68210.png")->save("G:/www/huadi/static/upload/lession/201807/2.png");
     die;
             Yii::$app->cache->set('huada', 'huadi..',30);
            // echo Yii::$app->cache->get('huada'), "\n";*/

        // exit;
        $model = new \common\models\Member;

        $result = \common\models\Member::find()->asArray()->all();
        print_r($result);
        die;

        $data["mobile"] = mobile_format(15228331200);
        return $this->responseJson(1002, "??????????????????", $data);
        $img = base64_encode(file_get_contents("http://img.huawa.com/upload/shop/placeorder/05600943053780904.png"));
        $result = \huadi\Upload::upload($img);
        print_r($result);
        die;
        $result = \huadi\oss\AliOss::listBuckets();
        print_r($result);
        die;
    }


    public function actionSign()
    {
        return $this->render('sign');
    }

    public function actionCheck()
    {
        $adv_model = new \common\models\Adv;
        $adv = $adv_model->getBanner(1051);

        //Yii::$app->getSession()->setFlash("error","??????????????????");
        echo Yii::$app->getSession()->getFlash("error");
    }

    public function actionIndex()
    {
        //banner
        $index_key = md5("hua123_index1");
        $data = cache($index_key);
        if (!$data) {
            $adv_model = new \common\models\Adv();
            $banner = $adv_model->getBanner(123);

            //??????????????????
            $data_model = new \common\models\Data();
            $nav = $data_model->getIndexNav();

            //????????????
            $cat_model = new \common\models\GoodsClass();
            $cat = $cat_model->getIndexCat();

            //????????????
            $attr = $cat_model->getIndexAttr();

            //??????????????????
            $article_model = new \common\models\Article();
            $recommend_article = $article_model->getRecommendArticle();
            //????????????
            $_house["name"] = "??????????????????";
            $_house["value"] = $adv_model->getBanner(125);

            //???????????????
            $_book["name"] = "???????????????";
            $_book["value"] = $adv_model->getBanner(126);

            //??????
            $_girl["name"] = "???????????????????????????";
            $_girl["value"] = $adv_model->getBanner(127);

            //??????
            $_bless["name"] = "????????????????????????";
            $_bless["value"] = $adv_model->getBanner(128);

            //??????
            $_flower["name"] = "?????????????????????";
            $_flower["value"] = $adv_model->getBanner(129);

            $data = [];
            $data["nav"] = $nav;
            $data["banner"] = $banner;
            $data["cat"] = $cat;
            $data["attr"] = $attr;
            $data["recommend_article"] = $recommend_article;
            $data["house"] = $_house;
            $data["book"] = $_book;
            $data["girl"] = $_girl;
            $data["bless"] = $_bless;
            $data["flower"] = $_flower;


            $data['category'] = GoodsClass::getAllClass();
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(1, "success", $data);
    }

    /**
     * ???????????????????????????????????????
     * @param array $params ??????????????????
     * @return mixed
     *
     */
    public function actionGoodslist(array $params = [])
    {
        $page = Yii::$app->request->post("page", 1);
        $pagesize = 10;
        $_order = Yii::$app->request->post("order", 1);
        $type = Yii::$app->request->post("type", 4);
        $gc_id = Yii::$app->request->post("gc_id", 0);
        $cat_id = Yii::$app->request->post("cat_id", 0);
        $keyword = Yii::$app->request->post("keyword", "");
        $price = Yii::$app->request->post("price", "");
        $is_hot = Yii::$app->request->post("is_hot", 0);
        $is_new = Yii::$app->request->post("is_new",0);  //????????????
        $is_live = Yii::$app->request->post("is_live",0); //???????????????
        $device_type = Yii::$app->request->post("device_type",''); //???????????????
        $post_attr_value_id = Yii::$app->request->post("attr_value_id",0);//?????????id
        $post_attr_value_id = (int)$post_attr_value_id;

        if ($params){
            extract($params);
        }
        if ($type == 1) {
            //??????
            $order = "goods.default_order desc,goods.goods_addtime ASC,goods.goods_custom_salenum DESC";
        } elseif ($type == 2) {
            //??????
            if ($_order == 1) {
                $order = "goods.goods_custom_salenum ASC";
            } else {
                $order = "goods.goods_custom_salenum DESC";
            }
        } elseif ($type == 3) {
            //??????
            if ($_order == 1) {
                $order = "goods.ahj_goods_price ASC";
            } else {
                $order = "goods.ahj_goods_price DESC";
            }
        }else{
            if(SITEID == 258){
                $order = "goods.goods_custom_salenum desc, goods_salenum DESC";
            }else{
                $order = "goods.goods_salenum DESC";
            }
        }
        $goods_key = md5('v_511' . $page . $pagesize . $_order . $type . $gc_id . $keyword . $order . $cat_id . $price);
        $data = cache($goods_key);
        $data = [];
        if (!$data) {
            $where = [];
            $where["goods.goods_state"] = 1;
            //$where["goods.gc_id_1"] = Goods::TOP_CLASS;
            if(SITEID == 258 && $is_live){
                $where["goods.gc_id_2"] = [
                    Goods::FLOWER_HOME,
                    Goods::FLOWER_MATERIAL,
                ];
            }else{
                $where["goods.gc_id_2"] = [
                    Goods::FLOWER_GIFT,
                    /*Goods::FLOWER_MATERIAL,
                    Goods::FLOWER_HOME,
                    Goods::FLOWER_LVZHI,
                    Goods::FLOWER_BASKET,
                    Goods::FLOWER_PRESENT,
                    Goods::FLOWER_CHOC,
                    Goods::FLOWER_CAKE,
                    Goods::FLOWER_ASSORT,
                    Goods::FLOWER_DUOROU,*/
                ];
            }
            if(in_array($gc_id,array(600,601,604,605,20))){

                if($gc_id == 605){
                    $cat_id = 3;
                    $keyword = "?????????";
                    $is_live = 0;
                    $gc_id = 0;
                }elseif($gc_id == 601){
                    $cat_id = 2;
                    $keyword = "?????????";
                    $is_live = 0;
                    $gc_id = 0;
                }elseif($gc_id == 600){
                    $cat_id = 1;
                    $keyword = "?????????";
                    $is_live = 0;
                    $gc_id = 0;
                }elseif($gc_id == 604){
                    $cat_id = 3;
                    $keyword = "?????????";
                    $is_live = 0;
                    $gc_id = 0;
                } elseif($gc_id == 20){

                    $gc_id = 72;
                }
               // $cat_id = $gc_id;
             //   $gc_id = 0;
            }
            if ($gc_id > 0) {
                if($gc_id == 20 && GOODS_FLOWER_GIFT != $gc_id){
                    $gc_id = GOODS_FLOWER_GIFT;
                }
                $where["goods.gc_id"] = $gc_id;
            }
            if ($cat_id > 0) {
                if(SITEID == 258 && $cat_id == 638){
                    $where["goods.gc_id_2"] = GOODS_FLOWER_MATERIAL;
                }else{
                    $query = GoodsAttrIndex::find();
                    $goods_ids = $query->select('goods_id')->where(array("attr_value_id" => $cat_id))->select("goods_id")->asArray()->all();
                    if ($goods_ids) {
                        $where["goods.goods_id"] = array_column($goods_ids, 'goods_id');
                    }
                }
            }
            if ($is_hot) {
                $where["goods.is_hot"] = 1;
            }
            if ($is_new) {
                $where["goods.is_new"] = 1;
                $order = "goods.goods_custom_salenum desc,goods_price asc,goods.goods_addtime desc";
            }
            $cart_goods_ids = [];
            $cart_goods_ids_kv = [];
            $cart_ids_kv = [];
            if($is_live){
                $model_cart = new Cart();
                //???????????????????????????ID
                if ($this->isLogin()) {
                    $cart_list = $model_cart->getCartByMember($this->member_id);
                } else {
                    $cart_list = $model_cart->getCartBySsid($this->sessionid);
                }
                $cart_goods_ids = array_column($cart_list,'goods_id');
                $cart_goods_ids_kv = array_column($cart_list,'goods_num','goods_id');
                $cart_ids_kv = array_column($cart_list,'cart_id','goods_id');
            }
            //??????????????????????????????????????????
            if ($gc_id == 0 && $cat_id == 0 && $keyword) {
                $attr_arr = [
                    "?????????"=> 579,
                    "????????????"=> 576,
                    "??????"=>576,
                    "????????????"=> 574,
                    "????????????"=> 580,
                    "??????"=> 580,
                    "?????????"=> 580,
                    "????????????"=> 584,
                    "????????????"=> 586,
                    "????????????"=> 577,
                    "????????????"=> 584,
                    "????????????"=> 578,
                    "?????????"=> 578,
                    "?????????"=> 581,
                    "?????????"=> 752,
                    "??????"=> 574,
                    "??????"=> 580,
                    "?????????"=> 576,
                    "??????"=> 584,
                    "??????"=> 578,
                    "??????"=> 586,
                    "??????"=>586,
                    "????????????"=> 595,
                ];
                $query = GoodsAttrIndex::find();
                if(isset($attr_arr[$keyword]) || $post_attr_value_id > 0){
                    $attr_value_id = isset($attr_arr[$keyword]) ? $attr_arr[$keyword] : $post_attr_value_id;
                    $goods_ids = $query->where(array("attr_value_id" => $attr_value_id))->select("goods_id")->asArray()->all();
                    if ($goods_ids) {
                        $where["goods.goods_id"] = array_column($goods_ids, 'goods_id');
                    }
                    //??????1.0.11??????????????????????????????????????????????????????
                    if($attr_value_id == 595){
                        $where["goods.gc_id_2"] = 87;
                    }
                }else{
                    $attr_value_id = Search::instance()->getAnalysisResult($keyword);
                    if ($attr_value_id) {
                        $attr_value_id = explode('_', $attr_value_id);
                        $goods_ids = $query->where(array("attr_value_id" => $attr_value_id))->select("goods_id")->asArray()->all();
                        if ($goods_ids) {
                            $where["goods.goods_id"] = array_column($goods_ids, 'goods_id');
                        }
                    } else {
                        $_where = ["or", ["like", "goods.goods_name", $keyword], ["like", "goods.goods_material", $keyword]];
                        $where = ["and", $where, $_where];
                    }
                }
            }
            if ($price) {
                $wh = $this->conditionPrice($price,$device_type);
                $where = ["and", $where, $wh];
            }
            $goods_model = new \common\models\Goods();
            $count = Goods::find()->alias('goods')->where($where)->count();
            $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $member_id = $this->isLogin() ? $this->member_id : 0;
            $list = $goods_model->goodslist($field, $page, $pagesize, $where, $order,1,$cart_goods_ids,$cart_goods_ids_kv,$cart_ids_kv,$member_id,320);
            $goods_class_model = new \common\models\GoodsClass();
            $nab = $goods_class_model->getNab();

            //19????????????????????????????????????????????????????????????????????????????????????
            if($is_new && SITEID == 258){
                $sort_arr = [];
                foreach ($list as $item){
                    $sort_arr[] = $item['is_xianshi'];
                }
                array_multisort($sort_arr,SORT_DESC,$list);
            }

            $data["count"] = $count;
            $data["goods"] = $list;
            $data["nab"] = $nab;

            cache($goods_key, $data, 3600);
        }

        //?????????????????? ????????????????????????
        $search = new Search();
        $search->member_id = $this->member_id;
        $search->member_name = $this->member_name;
        $search->ssid = $this->sessionid;
        $search->gc_id = $gc_id;
        $search->attr_id = $cat_id;
        $search->keyword = $keyword;
        $search->price = $price;
        $search->ordertype = $type;
        $search->orderby = $_order;
        $search->rows = $data["count"];
        $search->ip = getIp();
        $search->user_agent = mb_strcut(Yii::$app->request->userAgent, 0, 300, 'UTF-8');
        $search->add_time = TIMESTAMP;
        $search->insert(false);

        //????????????????????????
        $goods_ids = isset($data['goods']) && is_array($data['goods']) ? array_column($data['goods'],'goods_id') : [];
        $group_goods = (new GroupShoppingGoods())->getGoodsGroup($goods_ids);
        foreach ($data['goods'] as &$val){
            $is_join_group = false;
            $group_price = 0;
            foreach ($group_goods as $group_good){
                if($val['goods_id'] == $group_good['goods_id']){
                    $is_join_group = true;
                    $group_price = $group_good['group_price'];
                }
            }
            $val['is_group_shopping'] = $is_join_group ? 1 : 0;
            if($is_join_group){
                $val['group_price'] = $group_price;
            }
        }
        if($params) return $data;
        return $this->responseJson(1, "success", $data);
    }

    /**
     * ????????????????????????????????????
     * @var string[]
     */
    private $huadi_index_cat = array(
        '1' => '?????????',
        '2' => '?????????',
        '3' => '?????????',
        '4' => '?????????',
        '5' => '?????????',
        '6' => '?????????',
        '7' => '????????????',
    );

    /**
     * ??????????????????????????????????????? ------????????????????????????
     * @return mixed
     */
    public function actionHuadiGoodslist()
    {
        $page = Yii::$app->request->post("page", 0);
        $pagesize = 10;
        $_order = Yii::$app->request->post("order", 1);
        $type = Yii::$app->request->post("type", 1);
        $gc_id = Yii::$app->request->post("gc_id", 0);
        $cat_id = Yii::$app->request->post("cat_id", 0);
        $keyword = Yii::$app->request->post("keyword", "");
        $is_new = Yii::$app->request->post("is_new",0);  //????????????
        $price = Yii::$app->request->post("price", "");
        $spray_number = Yii::$app->request->post("spray_number", "");
        // ??????????????????
        $homeRecommendCat = Yii::$app->request->post("home_recommend_cat", "");
        $where = array();
        $where["goods.goods_state"] = 1;
        if (!empty($homeRecommendCat)) {
            $settings = \common\models\Setting::instance()->getValue("huadi_index_cat_goods_list", true);
            $huadi_icon_setting = unserialize($settings);
            $huadi_index_cat = array_flip($this->huadi_index_cat);
            if (!empty($huadi_index_cat) && isset($huadi_index_cat[$homeRecommendCat])
                && !empty($huadi_icon_setting) && isset($huadi_icon_setting[$huadi_index_cat[$homeRecommendCat]])
            ) {
                $goods_id = $huadi_icon_setting[$huadi_index_cat[$homeRecommendCat]];
                if ($page == 1) {
                    $goods_model = new \common\models\Goods();
                    $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
                    $recommendList = $goods_model->goodslist($field, 1, 10,
                        array('goods.goods_id' => $goods_id),
                        array("field(goods.goods_id, " . implode(',', $goods_id) . ") " => true),
                        1, [], [], [], 0, 320);
                    $pagesize = $pagesize - count($recommendList);
                } else {
                    $goodsIdNotIn = $goods_id;
                }
            }
        }
        // ??????????????????
        $is_lph = Yii::$app->request->post("lph_index", "");
        $post_attr_value_id = Yii::$app->request->post("attr_value_id",[]);//?????????id
        //??????attr_value_id??????
        if($post_attr_value_id){
            if(!is_array($post_attr_value_id)){
                $post_attr_value_id = explode(',',$post_attr_value_id);
            }
        }
//        $post_attr_value_id = (int)$post_attr_value_id;
        if($is_new){
            $is_new = true;
        }else{
            $is_new = false;
        }
        if ($type == 4) {
            $type = 1;
            $is_new = true;
        }

        if ($type == 1) {
            //??????
            $order = "goods.default_order desc,goods.goods_custom_salenum DESC,goods.goods_addtime DESC";
        } elseif ($type == 2) {
            //??????
            if ($_order == 1) {
                $order = "goods.goods_custom_salenum ASC";
            } else {
                $order = "goods.goods_custom_salenum DESC";
            }
        } elseif ($type == 3) {
            //??????
            if ($_order == 1) {
                $order = "goods.ahj_goods_price ASC";
            } else {
                $order = "goods.ahj_goods_price DESC";
            }
        } else{
            $order = "goods_salenums DESC";
        }



        $data = [];
        if (!$data) {
            $def_gc_id_2 = [
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
                Goods::FLOWER_TEA,
                Goods::MOON_CAKE,
                Goods::CHOCOLATE,
            ];
            if($gc_id){
//                $gc_id = explode(',',$gc_id);
//                $where["goods.gc_id_2"] = $gc_id;
            }elseif ($is_lph) {
                $where["goods.gc_id_2"] = [
                    Goods::FLOWER_GIFT,
                    Goods::FLOWER_BASKET,
                ];
            } else {
                $where["goods.gc_id_2"] = $def_gc_id_2;
            }
            if ($cat_id) {
                $cat_ids = explode(',',$cat_id);
                $goods_ids_arr = [];

                $attr_value_list = AttributeValue::find()->select('attr_value_name')->where(['in', 'attr_value_id', $cat_ids])->asArray()->all();
                if ($attr_value_list) {
                    $attr_name = [];
                    foreach ($attr_value_list as $a_v) {
                        $attr_name[] = $a_v['attr_value_name'];
                    }

                    // ?????????????????????????????? attr_value_id
                    $attr_value_list = AttributeValue::find()->select('attr_value_id')->where(['in', 'attr_value_name', $attr_name])->asArray()->all();
                    $attr_value_ids = [];
                    foreach ($attr_value_list as $a_v_l) {
                        $attr_value_ids[] = $a_v_l['attr_value_id'];
                    }

                    // ????????????????????????????????????ID
                    $goods_ids = GoodsAttrIndex::find()->select('goods_id')->where(['in', 'attr_value_id', $attr_value_ids])->select("goods_id")->asArray()->all();
                    if ($goods_ids) {
                        $goods_ids = array_column($goods_ids, 'goods_id');
                        $goods_ids_arr = array_unique($goods_ids);
                    }
                }
                if ($goods_ids_arr) {
                    $where["goods.goods_id"] = $goods_ids_arr;
                }
            }
            //??????????????????????????????????????????
            if ($gc_id == 0 && $cat_id == 0 && $keyword) {
                //?????????????????????
                $replace_keyword = [
                    '??????' => '????????????'
                ];
                if(isset($replace_keyword[$keyword])){
                    $keyword = $replace_keyword[$keyword];
                }
                /**
                 * 20200819,????????????????????????????????????????????????????????????ids:
                 *
                 **/
                $replace_attr_value_ids = [
                    //?????????585????????????786???????????????749???????????????750
                    '?????????' => [585,786,749,750],
                ];
                /**
                 * 20200817,????????????????????????:
                 * 1.??????????????????keyword ??? attr_value_id(??????????????????????????????????????????), ????????????attr_value_id, ????????????;
                 * 2.????????????????????????keyword, ??? attr_value_id(????????????????????????), ????
                 *
                 **/
                if(isset($replace_attr_value_ids[$keyword])){
                    $post_attr_value_id = array_merge($post_attr_value_id,$replace_attr_value_ids[$keyword]);
                }
                $query = GoodsAttrIndex::find();
                if($post_attr_value_id){
                    $search_goods_ids = $query->where(array("attr_value_id" => $post_attr_value_id))->select("goods_id")->asArray()->column();
                    $_where = ['in','goods.goods_id',$search_goods_ids];
                }else{
                    //??????????????????????????????  ????????????????????????  todo
                    $attr_value_id = Search::instance()->getAnalysisResult($keyword);
                    if ($attr_value_id) {
                        $attr_value_id = explode('_', $attr_value_id);
                        $search_goods_ids = $query->where(array("attr_value_id" => $attr_value_id))->select("goods_id")->asArray()->column();
                    }
                    if (!empty($search_goods_ids)) {
                        $_where = ["or", ["like", "goods.goods_name", $keyword], ["like", "goods_common.goods_material", $keyword],["like", "goods_common.goods_jingle", $keyword],["like", "goods_common.goods_tag", $keyword],["like", "goods_class.gc_name", $keyword],["like", "goods_common.goods_attr", $keyword],['in','goods.goods_id',$search_goods_ids]];
                    }else{
                        $_where = ["or", ["like", "goods.goods_name", $keyword], ["like", "goods_common.goods_material", $keyword],["like", "goods_common.goods_jingle", $keyword],["like", "goods_common.goods_tag", $keyword],["like", "goods_class.gc_name", $keyword],["like", "goods_common.goods_attr", $keyword]];
                    }
                }
                if(isset($_where)){
                    $where = ["and", $where, $_where];
                }
            } elseif ($keyword) {
                $_where = ["or", ["like", "goods.goods_name", $keyword], ["like", "goods_common.goods_material", $keyword],["like", "goods_common.goods_jingle", $keyword],["like", "goods_common.goods_tag", $keyword]];
                $where = ["and", $where, $_where];
            } elseif (!$keyword && $post_attr_value_id){
                //???????????????????????????????????????id?????????
                $query = GoodsAttrIndex::find();
                $search_goods_ids = $query->where(array("attr_value_id" => $post_attr_value_id))->select("goods_id")->asArray()->column();
                if($search_goods_ids){
                    $_where = ['in','goods.goods_id',$search_goods_ids];
                    $where = ["and", $where, $_where];
                }
            }
            if ($price) {
                //??????????????????????????????
                $price = FinalPrice::getSearchPrice($price);
                $wh = $this->conditionPrice($price,'applet_huadi');
                $where = ["and", $where, $wh];
            }
            if ($is_new) {
                //????????????????????????????????????????????????
                //$where["goods.is_new"] = 1;
                $order = "goods.goods_addtime DESC";
            }
            if ($spray_number) {
                $wh = $this->conditionPrice($spray_number,'spray_number');
                $where = ["and", $where, $wh];
            }
            if($gc_id){
                $_where = ["or", ["in", "goods.gc_id_2", $gc_id], ["in", "goods.gc_id_3", $gc_id]];
                $where = ["and", $where, $_where];
            }
            $goods_model = new \common\models\Goods();
            if(!empty($goodsIdNotIn)){
                $where = ['and', $where, ['not in', 'goods.goods_id', $goodsIdNotIn]];
            }
            $count = GoodsCommon::find()
                ->alias('goods_common')
                ->leftJoin('hua123_goods goods','goods.goods_commonid = goods_common.goods_commonid')
                ->leftJoin('hua123_goods_class goods_class', 'goods.gc_id=goods_class.gc_id')
                ->where($where)->count();
            $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $list = $goods_model->goodslist($field, $page, $pagesize, $where, $order,1,[],[],[],$this->member_id,320);

//            var_dump($list);exit;
            $data["count"] = $count;
            if(isset($recommendList)){
                for($i = count($recommendList) - 1;$i >= 0; $i--){
                    array_unshift($list, $recommendList[$i]);
                }
            }
            $data["goods"] = $list;
        }
        //?????????????????? ????????????????????????
        $search = new Search();
        $search->member_id = $this->member_id;
        $search->member_name = $this->member_name;
        $search->ssid = $this->sessionid;
        $search->gc_id = $gc_id;
        $search->attr_id = $cat_id;
        $search->keyword = $keyword;
        $search->price = $price;
        $search->ordertype = $type;
        $search->orderby = $_order;
        $search->rows = $data["count"];
        $search->ip = getIp();
        $search->user_agent = mb_strcut(Yii::$app->request->userAgent, 0, 300, 'UTF-8');
        $search->add_time = TIMESTAMP;
        $search->insert(false);

        //????????????????????????
        $goods_ids = isset($data['goods']) && is_array($data['goods']) ? array_column($data['goods'],'goods_id') : [];
        $group_goods = (new GroupShoppingGoods())->getGoodsGroup($goods_ids);
        foreach ($data['goods'] as &$val){
            $is_join_group = false;
            $group_price = 0;
            foreach ($group_goods as $group_good){
                if($val['goods_id'] == $group_good['goods_id']){
                    $is_join_group = true;
                    $group_price = $group_good['group_price'];
                }
            }
            $val['is_group_shopping'] = $is_join_group ? 1 : 0;
            if($is_join_group){
                $val['group_price'] = $group_price;
            }
        }
        return $this->responseJson(1, "success", $data);
    }

    /**
     * wap????????????????????????????????????????????????????????????
     */
    public function actionHotGoods()
    {
        $type = Yii::$app->request->post("type", 1);
        $cat_id = Yii::$app->request->post("cat_id", "");

        $where["goods.is_hot"] = 1;

        $goods_ids_arr = [];
        $attr_value_list = AttributeValue::find()->select('attr_value_name')->where(['=', 'attr_value_id', $cat_id])->asArray()->all();
        if ($attr_value_list) {
            $attr_name = [];
            foreach ($attr_value_list as $a_v) {
                $attr_name[] = $a_v['attr_value_name'];
            }

            // ?????????????????????????????? attr_value_id
            $attr_value_list = AttributeValue::find()->select('attr_value_id')->where(['in', 'attr_value_name', $attr_name])->asArray()->all();
            $attr_value_ids = [];
            foreach ($attr_value_list as $a_v_l) {
                $attr_value_ids[] = $a_v_l['attr_value_id'];
            }

            // ????????????????????????????????????ID
            $goods_ids = GoodsAttrIndex::find()->select('goods_id')->where(['in', 'attr_value_id', $attr_value_ids])->select("goods_id")->asArray()->all();
            if ($goods_ids) {
                $goods_ids = array_column($goods_ids, 'goods_id');
                $goods_ids_arr = array_unique($goods_ids);
            }
        }
        if ($goods_ids_arr) {
            $where["goods.goods_id"] = $goods_ids_arr;
        }

        $order = "goods.goods_custom_salenum desc";
        $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
        $goods_model = new \common\models\Goods();
        $list = $goods_model->goodslist($field, 1, 4, $where, $order);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $list);
    }

    /**
     * ??????????????????????????????
     * @return mixed
     */
    public function actionFreelist()
    {
        $page = Yii::$app->request->post("page", 0);
        $pagesize = 10;
        $order = "goods.goods_addtime DESC";

        $goods_key = md5($page . $pagesize . $order . "free3");
        $data = cache($goods_key);
        if (!$data || 1) {
            $where = [];
            $where["goods.goods_state"] = 1;
            $where["goods.gc_id"] = Goods::FLOWER_HOME;
            $filter = ['>', 'goods.goods_monthprice', 0];
            $where = ['and', $where, $filter];
            $field = "goods.goods_id,goods.goods_name,goods.goods_image,goods.goods_material,goods.gc_id,goods_class.gc_name,goods.goods_price,goods.goods_monthprice,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $goods_model = new \common\models\Goods();
            $list = $goods_model->goodslist($field, $page, $pagesize, $where, $order, GOODS_TYPE_HOME_FLOWER,[],[],[],0,640);
            $data["goods"] = $list;
            cache($goods_key, $data, 3600);
        }
        return $this->responseJson(1, "success", $data);
    }

    /**
     * ????????????????????????
     * @return mixed
     */
    public function actionCombination()
    {

        $bl_id = Yii::$app->request->post("bl_id", 0);
        $type = Yii::$app->request->post("type", 1);

        $PBund_model = new \common\models\PBundling();

        $bdling = $PBund_model->getSingleBundling($bl_id, $type);
        if (!$bdling) {
            return $this->responseJson(0, "??????????????????");
        }

        $data["bdling"] = $bdling;
        return $this->responseJson(1, "success", $data);
    }

    /**
     * ????????????????????????
     * @return mixed
     */
    public function actionCombinationnew()
    {
        $PBund_model = new \common\models\PBundling();
        $where = [];
        $where['is_ahj'] = 0;
        $where['is_delete'] = 0;
        $where['bl_state'] = 1;
        $where['bl_type'] = 2;
        $bdling = $PBund_model->getBundlingInfoList($where, 1, 1000, 'bl_id,bl_name,bl_discount_price');
        if (!$bdling) {
            return $this->responseJson(0, "??????????????????");
        }

        $data["bdling"] = $bdling;
        return $this->responseJson(1, "success", $data);
    }

    /**
     * ????????????
     * @return mixed
     */
    public function actionCombinationJx()
    {
        $page = Yii::$app->request->post("page", 0);
        if ($page < 0 || !is_numeric($page)) {
            return $this->responseJson(1, "????????????");
        }
        $pagesize = 10;

        $PBund_model = new \common\models\PBundling();
        $condition = [];
        $condition['bl_type'] = $PBund_model::BUNDLING_2;
        $condition['bl_state'] = 1;
        $condition['is_delete'] = 0;
        $field = 'bl_id, bl_name, bl_sub_name, bl_discount_price, norms_info';
        $data = $PBund_model->getBundlingJxList($condition, $page, $pagesize, $field);
        return $this->responseJson(1, "success", $data);
    }

    public function actionCombinationJxDetail()
    {
        $id = Yii::$app->request->post("id", 9);
        if (!$id || $id <= 0) {
            return $this->responseJson(0, "???????????????");
        }
        $model_bundling = new \common\models\PBundling();
        $bunding_field = 'bl_id, bl_name, bl_sub_name, bl_discount_price, bl_freight_choose, bl_freight, mobile_body, norms_info';
        $data = $model_bundling->getBundlingDetail($id, $bunding_field);
        if (!$data || empty($data)) {
            return $this->responseJson(0, "???????????????");
        }
        $model_goods = new Goods();
        $data['goods_new_services'] = $model_goods->getGoodsNewServices();
        //????????????
        $data['year_card_price'] = HuadiYearCardOrders::YEAR_CARD_PRICE;
        $data['year_card_marketprice'] = HuadiYearCardOrders::YEAR_CARD_MARKET_PRICE;
        return $this->responseJson(1, "success", $data);
    }

    //???????????????????????????
    public function actionHuadiHotSearchList(){
        $huadi_keyword_system_set = Setting::C('huadi_keyword_system_set',true);
        $huadi_hotlist = [];
        if($huadi_keyword_system_set){
            $huadi_hotkeywords = Setting::C('huadi_hot_search_list',true);
            if($huadi_hotkeywords){
                $huadi_hotlist = unserialize($huadi_hotkeywords);
                array_multisort(array_column($huadi_hotlist,'sort'),SORT_DESC,$huadi_hotlist);
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$huadi_hotlist);
    }

    public function actionGetHuadiSearchInputPh(){
        $huadi_keyword_placeholder = Setting::C('huadi_keyword_placeholder',true);
        $data['content'] = $huadi_keyword_placeholder && $huadi_keyword_placeholder!='' ? $huadi_keyword_placeholder : '???????????????????????????';
        $data['weixin_msg_template_id'] = WeixinSubscribeMsg::getTemplateId();
//        $data['cornor_logo'] = 'http://i.ahj.cm/images/huadi_qixi_logo.png?re';
        $data['cornor_logo'] = '';
        $data['holiday_list'] = explode(',', Setting::C('huadi_holiday_time'));
        if(Setting::C('huadi_holiday_open') && Setting::C('huadi_holiday_open_web')) {
            $data['is_holiday_open'] = true;
        }else{
            $data['is_holiday_open'] = false;
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }

    /**
     * ????????????????????????
     * @return mixed
     */
    public function actionHuadiSearchItem(){
        $attr_model = new Attribute();
        $type = Yii::$app->request->post("type", 1); //1 ??????  2??????
        $cache_key = 'huadi_search_item_' . $type;
        $ret_arr = cache($cache_key);
        if ($ret_arr && !empty($ret_arr)) {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$ret_arr);
        }
        if($type == 1){//20200710??????????????????items
            $goodsClassInfo = GoodsClass::find()->where(['gc_id'=> 82])->select("type_id")->asArray()->one();
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
                $data['attr_list'] = $attrList;
            }
            $field = 'attribute.attr_id as attr_id,attribute_value.attr_value_id as attr_value_id, attribute_value.attr_value_name as attr_value_name';
            $attr_arr = [
                88=>'??????', 99=>'??????',64=>'??????','??????', 63=>'??????',3=>'??????',7=>'??????',
            ];
            //??????????????????????????????
//            $class_info = [
//                [
//                    'attr_id' => 'home_flower',
//                    'attr_value_id' => GOODS_FLOWER_HOME,
//                    'attr_value_name' => '?????????'
//                ],
//                [
//                    'attr_id' => 'duorou_flower',
//                    'attr_value_id' => GOODS_FLOWER_DUOROU,
//                    'attr_value_name' => '??????'
//                ],
//                [
//                    'attr_id' => 'gift_flower',
//                    'attr_value_id' => GOODS_FLOWER_GIFT,
//                    'attr_value_name' => '?????????'
//                ],
//                [
//                    'attr_id' => 'lvzhi_flower',
//                    'attr_value_id' => GOODS_FLOWER_LVZHI,
//                    'attr_value_name' => '??????'
//                ],
//                [
//                    'attr_id' => 'baseket_flower',
//                    'attr_value_id' => GOODS_FLOWER_BASKET,
//                    'attr_value_name' => '??????'
//                ]
//            ];
            $gc_id = [GOODS_FLOWER_GIFT, GOODS_FLOWER_BASKET];
            $goods_class = GoodsClass::find()->where(['in', 'gc_parent_id', $gc_id])->asArray()->all();
            $class_info = [];
            foreach ($goods_class as $v) {
                if ($v['gc_parent_id'] == GOODS_FLOWER_GIFT) {
                    $attr_id = 'gift_flower';
                } else {
                    $attr_id = 'baseket_flower';
                }
                $class_info[] = [
                    'attr_id' => $attr_id,
                    'attr_value_id' => $v['gc_id'],
                    'attr_value_name' => $v['gc_name'],
                ];
            }
            $ret_arr = [];
            foreach ($attr_arr as $k=>$v){
                $item_info = [
                    "parent_key" => $k,
                    "title" => $v,
                ];
                if(in_array($v,['??????','??????'])){
                    $item_info['item'] = [];
                }elseif(in_array($v,['??????'])){
                    $item = $class_info;
                } elseif ($v == '??????') {
                    $item = Attribute::getHuadiGoodsByAttrName(HUADI_GOODS_SEARCH_DX, $field);;
                }
                elseif ($v == '??????') {
                    $item = Attribute::getHuadiGoodsByAttrName(HUADI_GOODS_SEARCH_YT, $field);
                } elseif ($v == '??????') {
                    $item = Attribute::getHuadiGoodsByAttrName(HUADI_GOODS_SEARCH_HC, $field);
                } elseif ($v == '??????') {
                    $item = Attribute::getHuadiGoodsByAttrName(HUADI_GOODS_SEARCH_LB, $field);
                }
                else{
                    $item = $attr_model->typeRelatedJoinList($k,$field);
                }

                if (!in_array($v,['??????','??????'])) {
                    if (isset($item) && $item) {
                        $item_info['item'] = $item;
                    } else{
                        unset($attr_arr[$k]);
                        continue;
                    }
                }
                array_push($ret_arr,$item_info);
            }
        }else{   //???????????????????????????????????????????????????????????????????????????????????????????????????
            $api = new MicroApi();
            $goods_cate_scene = $api->httpRequest('/api/getGoodsCategory');

            $ret_arr = [];
            // ??????
            if ($goods_cate_scene && isset($goods_cate_scene['scene']) && isset($goods_cate_scene['scene_class'])) {
                $k = 1;
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
                            "parent_key" => $k,
                            "title" => $class['class_name'],
                            "item"  => $scene,
                        ];
                        $k++;
                    }
                }
            }
            $ret_arr[] = [
                "parent_key" => count($ret_arr)+1,
                "title" => "??????",
                "item"  => []
            ];

            // ??????
            if ($goods_cate_scene && isset($goods_cate_scene['category'])) {
                $cate = [];
                foreach ($goods_cate_scene['category'] as $k => $v) {
                    $cate[] = [
                        "attr_value_id"=> $v['cat_id'],
                        "attr_value_name" => $v['cat_name']
                    ];
                }
                $ret_arr[] = [
                    "parent_key" => count($ret_arr)+1,
                    "title" => "??????",
                    "item"  => $cate,
                ];
            }
        }
        cache($cache_key, $ret_arr, 60*3);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$ret_arr);
    }
    /**
     * ????????????????????????new
     * @return mixed
     */
    public function actionHuadiSearchItemNew(){
        $type = Yii::$app->request->post("type", 1); //1 ??????  2??????
        $gc_id = Yii::$app->request->post('gc_id', '');
        $cache_key = 'huadi_search_item_new_' . $type . '_' . $gc_id;
        $ret_arr = cache($cache_key);
        if ($ret_arr && !empty($ret_arr)) {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$ret_arr);
        }
        if($type == 1){
            $ret_arr = [];
            //??????
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
                    'attr_value_name' => '1000??????'
                ],
                '0_100' => [
                    'attr_value_id' => '0_100',
                    'attr_value_name' => '100??????'
                ],
            ];
            $ret_arr[] = [
                'key' => 'price',
                'type' => 'radio',
                'title' => '????????????',
                'item' => array_values($price_selections)
            ];
            if($gc_id){
                $goodsClassInfo = GoodsClass::find()->where(['gc_id'=> $gc_id])->select("type_id")->asArray()->one();
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
                    foreach ($attrList as $attr){
                        $ret = [
                            'key' => 'attr_value_id',
                            'type' => 'checkbox',
                            'title' => $attr['attr_name'],
                            'item' => []
                        ];
                        $items = [];
                        foreach($attrValueList as $v){
                            if($v['attr_id'] == $attr['attr_id']){
                                $items[] = [
                                    "attr_value_id"=> $v['attr_value_id'],
                                    "attr_value_name" => $v['attr_value_name']
                                ];
                            }
                        }
                        $ret['item'] = $items;
                        $ret_arr[] = $ret;
                    }
                }
                $data = GoodsClass::getSqlAllClass();
                $filter = ['?????????', '????????????', '?????????'];
                $ret = [];
                foreach($data as $k => $v){
                    if(in_array($v['title'], $filter)){
                        unset($data[$k]);continue;
                    };
                    $ret[] = [
                        'attr_value_id' => $v['type'],
                        'attr_value_name' => $v['title'],
                    ];
                }
                $ret_arr[] = [
                    'key' => 'gc_id',
                    'type' => 'checkbox',
                    'title' => '??????',
                    'item' => $ret
                ];
            }
        }else{   //???????????????????????????????????????????????????????????????????????????????????????????????????
            $api = new MicroApi();
            $goods_cate_scene = $api->httpRequest('/api/getGoodsCategory');

            $ret_arr = [];
            // ??????
            if ($goods_cate_scene && isset($goods_cate_scene['scene']) && isset($goods_cate_scene['scene_class'])) {
                $k = 1;
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
                            "parent_key" => $k,
                            "title" => $class['class_name'],
                            "item"  => $scene,
                        ];
                        $k++;
                    }
                }
            }
            $ret_arr[] = [
                "parent_key" => count($ret_arr)+1,
                "title" => "??????",
                "item"  => []
            ];

            // ??????
            if ($goods_cate_scene && isset($goods_cate_scene['category'])) {
                $cate = [];
                foreach ($goods_cate_scene['category'] as $k => $v) {
                    $cate[] = [
                        "attr_value_id"=> $v['cat_id'],
                        "attr_value_name" => $v['cat_name']
                    ];
                }
                $ret_arr[] = [
                    "parent_key" => count($ret_arr)+1,
                    "title" => "??????",
                    "item"  => $cate,
                ];
            }
        }
        cache($cache_key, $ret_arr, 60*3);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$ret_arr);
    }
    /**
     * ??????????????????
     * @return mixed
     */
    public function actionRecommendList()
    {
        $page = Yii::$app->request->post("page", 0);
        $pagesize = 10;

        $goods_key = md5($page . $pagesize . "recommend_goodslist8");

        $data = cache($goods_key);
        if (!$data) {
            $where = [];
            $where["goods.gc_id_1"] = Goods::TOP_CLASS;
            $where["goods.goods_state"] = 1;
            $field = "goods.goods_id,goods.goods_name,goods.goods_image,goods.goods_material,goods.gc_id,goods_class.gc_name,goods.goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $goods_model = new \common\models\Goods();
            $list = $goods_model->goodslist($field, $page, $pagesize, $where,"goods_salenums desc",1,[],[],[],0,320);
            $goods_class_model = new \common\models\GoodsClass();
            $nab = $goods_class_model->getNab();

            $data["goods"] = $list;
            $data["nab"] = $nab;
            cache($goods_key, $data, 3600);
        }
        return $this->responseJson(1, "success", $data);
    }


    /**
     * ????????????
     * @return mixed
     */
    public function actionCatlist()
    {
        return $this->_recommendClass();
        $cat_key = md5("catlists");
        $data = cache($cat_key);
        if (!$data) {
            $goods_class_model = new \common\models\GoodsClass();
            $catlist = $goods_class_model->getCats();
            $data = [];
            $data["catlist"] = $catlist;
            cache($cat_key, $data, 3600);
        }
        return $this->responseJson(1, "success", $data);
    }


    /**
     * ??????????????????
     * @return mixed
     */
    private function _recommendClass()
    {
        $cat_key = md5("catlists_v10");
        $data = cache($cat_key);
        if (!$data) {
            $goods_class_model = new \common\models\GoodsClass();
            $catlist = $goods_class_model->getAllCats();
            $data = [];
            $data["catlist"] = $catlist;
            cache($cat_key, $data, 3600);
        }
        return $this->responseJson(1, "success", $data);
    }

    /**
     * ??????????????????????????????
     * @param string $price
     * @return array
     */
    private function conditionPrice($price = "",$type = "")
    {
        $where = [];
        if (!$price) {
            return $where;
        }
        $price = explode('_', $price);
        if (count($price) != 2) {
            return $where;
        }
        if($type && in_array($type,['applet_huadi','app_huadi','applet_aihuaju'])){
            $field_name = '`goods`.`ahj_goods_price`';
        }elseif($type && in_array($type,['spray_number'])){  //??????
            $field_name = '`goods`.`goods_number`';
        }else{
            $field_name = '`goods`.goods_price';
        }
        if (intval($price[0]) && intval($price[1])) {
            //????????????
            $where = ["between", $field_name, intval($price[0]), intval($price[1])];
        } elseif (intval($price[0]) && !intval($price[1])) {
            //????????????
            $where = [">=", $field_name, intval($price[0])];
        } elseif (!intval($price[0]) && intval($price[1])) {
            //????????????
            $where = ["<=", $field_name, intval($price[1])];
        } else {
            $where = [];
        }
        return $where;
    }


    /**
     * ??????????????????
     * @param string $price
     * @return array
     */
    public function actionActivityrecommend()
    {
        // ??????????????????????????????????????????id.
        $settingData = Setting::find()->select('value')->where(array('name' => 'activity_recommend'))->asArray()->one();
        if ($settingData) {
            // ????????????.
            $activityInfo = unserialize($settingData['value']);
            $data = $activityInfo;
            // ??????????????????.
            $ordersWhere = ['in', 'goods_id', $activityInfo['list']];
            // ??????????????????.
            $goods_model = new \common\models\Goods();
            $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $goodsInfo = $goods_model->goodslist($field, 1, 6, $ordersWhere, '',1,[],[],[],0,320);
            $data['list'] = $goodsInfo;
        } else {
            $data = array();
        }

        $code = 1;
        $msg = 'success';
        return $this->responseJson($code, $msg, $data);

    }
    /**
     * ?????????????????????20201103, ?????????4936
     * live-flower-new-index
     */
    public function actionLiveFlowerNewIndex(){
        //??????????????????????????????
        $adv_model = new Adv;
        $data['top_adv'] = $adv_model->getBanner(163);//??????????????????????????????id;
        //???????????????????????????
        $data['cat_list'] = AttributeValue::getLiveAttrList();
        //??????????????????
        $new_goods_data = $this->actionGoodslist(['_order' => 0, 'is_new' => 1, 'is_live' => 1, 'type'=>1, 'pagesize' => 12]);
        $data['new_goods'] = $new_goods_data['goods'];
        //??????????????????
        $data['recommend_video_list'] = [
            'title' => '????????????',
            'sub_title' => '?????????????????????????????????',
            'list' => FlowerArtVideo::getRecommendVideoList()
        ];
        //??????????????????????????????
        $recommend_goods_ids = Setting::C('live_flower_recommend_list',true);
        if($recommend_goods_ids) $recommend_goods_ids = json_decode($recommend_goods_ids,true);
        $goods_model = new Goods();
        $where = [];
        $where['goods.goods_state'] = 1;
        //todo ???????????????
//        $where["goods.gc_id_2"] = [
//            Goods::FLOWER_HOME,
//            Goods::FLOWER_MATERIAL,
//        ];
        $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
        //???????????????
        $list = [];
        $cat_id = 632;
        if(isset($recommend_goods_ids['pch']) && !empty($recommend_goods_ids['pch'])){
            $order = ["field(goods.goods_id," . implode(',', $recommend_goods_ids['pch']) . ")" => true];
            $recommend_ids = $where['goods.goods_id'] = $recommend_goods_ids['pch'];
            $list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
            if(count($list) < 4){
               $limit = 4 - count($list);
            }
        }else{
            $limit = 4;
            $recommend_ids = [];
        }
        if( $limit > 0 ){
            $goods_ids = GoodsAttrIndex::find()->alias('a')
                ->leftJoin(Goods::tableName().' b','b.goods_id=a.goods_id')
                ->where(['and',['b.goods_state' => 1],['a.attr_value_id' => $cat_id],['not in','a.goods_id',$recommend_ids]])
                ->orderBy('b.goods_addtime desc')->limit($limit)->select(['a.goods_id'])->asArray()->column();
            if($goods_ids){
                $where['goods.goods_id'] = $goods_ids;
                $order = ["field(goods.goods_id," . implode(',', $goods_ids) . ")" => true];
                $_list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
                $list = array_merge($list,$_list);
            }
        }
        $data['recommend_list'][] = [
            'header' => 'http://i.ahj.cm/images/huadi_live/huadi_live_pch_recommend.png',
            'list' => $list,
            "url"  => "http://www.hua.zj.cn/index/toAppPage?app_page=LifeListPage&cat_id={$cat_id}&is_live=1",
//            "url_xcx" => "/pages/new-searchList?cat_id={$cat_id}&is_live=1",
            "url_xcx" => "/life/life-flowerlist?cat_id={$cat_id}&is_live=1"
        ];
        //????????????
        $list = [];
        $cat_id = 641;
        if(isset($recommend_goods_ids['lz']) && !empty($recommend_goods_ids['lz'])){
            $order = ["field(goods.goods_id," . implode(',', $recommend_goods_ids['lz']) . ")" => true];
            $recommend_ids = $where['goods.goods_id'] = $recommend_goods_ids['lz'];
            $list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
            if(count($list) < 4){
                $limit = 4 - count($list);
            }
        }else{
            $limit = 4;
            $recommend_ids = [];
        }
        if( $limit > 0 ){
            $goods_ids = GoodsAttrIndex::find()->alias('a')
                ->leftJoin(Goods::tableName().' b','b.goods_id=a.goods_id')
                ->where(['and',['b.goods_state' => 1],['a.attr_value_id' => $cat_id],['not in','a.goods_id',$recommend_ids]])
                ->orderBy('b.goods_addtime desc')->limit($limit)->select(['a.goods_id'])->asArray()->column();
            if($goods_ids){
                $where['goods.goods_id'] = $goods_ids;
                $order = ["field(goods.goods_id," . implode(',', $goods_ids) . ")" => true];
                $_list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
                $list = array_merge($list,$_list);
            }
        }
        $data['recommend_list'][] = [
            'header' => 'http://i.ahj.cm/images/huadi_live/huadi_live_lvzhi_recommend.png',
            'list' => $list,
            "url"  => "http://www.hua.zj.cn/index/toAppPage?app_page=LifeListPage&cat_id={$cat_id}&is_live=1",
            "url_xcx" => "/life/life-flowerlist?cat_id={$cat_id}&is_live=1"
        ];
        //??????????????????
        $list = [];
        $cat_id = 641;
        if(isset($recommend_goods_ids['dr']) && !empty($recommend_goods_ids['dr'])){
            $order = ["field(goods.goods_id," . implode(',', $recommend_goods_ids['dr']) . ")" => true];
            $recommend_ids = $where['goods.goods_id'] = $recommend_goods_ids['dr'];
            $list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
            if(count($list) < 4){
                $limit = 4 - count($list);
            }
        }else{
            $limit = 4;
            $recommend_ids = [];
        }
        if( $limit > 0 ){
            $goods_ids = GoodsAttrIndex::find()->alias('a')
                ->leftJoin(Goods::tableName().' b','b.goods_id=a.goods_id')
                ->where(['and',['b.goods_state' => 1],['a.attr_value_id' => $cat_id],['not in','a.goods_id',$recommend_ids]])
                ->orderBy('b.goods_addtime desc')->limit($limit)->select(['a.goods_id'])->asArray()->column();
            if($goods_ids){
                $where['goods.goods_id'] = $goods_ids;
                $order = ["field(goods.goods_id," . implode(',', $goods_ids) . ")" => true];
                $_list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
                $list = array_merge($list,$_list);
            }
        }
        $data['recommend_list'][] = [
            'header' => 'http://i.ahj.cm/images/huadi_live/huadi_live_duorou_recommend.png',
            'list' => $list,
            "url"  => "http://www.hua.zj.cn/index/toAppPage?app_page=LifeListPage&cat_id={$cat_id}&is_live=1",
            "url_xcx" => "/life/life-flowerlist?cat_id={$cat_id}&is_live=1"
        ];
        //??????????????????
        $list = [];
        $cat_id = 642;
        if(isset($recommend_goods_ids['yhqj']) && !empty($recommend_goods_ids['yhqj'])){
            $order = ["field(goods.goods_id," . implode(',', $recommend_goods_ids['yhqj']) . ")" => true];
            $recommend_ids = $where['goods.goods_id'] = $recommend_goods_ids['yhqj'];
            $list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
            if(count($list) < 4){
                $limit = 4 - count($list);
            }
        }else{
            $limit = 4;
            $recommend_ids = [];
        }
        if( $limit > 0 ){
            $goods_ids = GoodsAttrIndex::find()->alias('a')
                ->leftJoin(Goods::tableName().' b','b.goods_id=a.goods_id')
                ->where(['and',['b.goods_state' => 1],['a.attr_value_id' => $cat_id],['not in','a.goods_id',$recommend_ids]])
                ->orderBy('b.goods_addtime desc')->limit($limit)->select(['a.goods_id'])->asArray()->column();
            if($goods_ids){
                $where['goods.goods_id'] = $goods_ids;
                $order = ["field(goods.goods_id," . implode(',', $goods_ids) . ")" => true];
                $_list = $goods_model->goodslist($field, 1, 4, $where, $order,2,[],[],[],$this->member_id,320);
                $list = array_merge($list,$_list);
            }
        }
        $data['recommend_list'][] = [
            'header' => 'http://i.ahj.cm/images/huadi_live/huadi_live_can_recommend.png',
            'list' => $list,
            "url"  => "http://www.hua.zj.cn/index/toAppPage?app_page=LifeListPage&cat_id={$cat_id}&is_live=1",
            "url_xcx" => "/life/life-flowerlist?cat_id={$cat_id}&is_live=1"
        ];
        return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$data);
    }

    /**
     * ???????????????????????????????????????
     * get-live-sub-cat
     */
    public function actionGetLiveSubCat(){
        $cat_list = AttributeValue::getLiveAttrList(true);
        return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$cat_list);
    }
    public function actionAddVideoPlayNum(){
        $video_id = Yii::$app->request->post('video_id',0);
        if( !$video_id ) return $this->responseJson(Message::ERROR,'????????????');
        $video_model = FlowerArtVideo::find()
            ->where(['video_status' => 1, 'upload_status' => 1,'id' => $video_id])->one();
        if( !$video_model ) return $this->responseJson(Message::ERROR,'???????????????!');
        $video_model->playback_num += 1;
        $video_model->save(false);
        $this->responseJson(Message::SUCCESS,'success');
    }
    /**
     * ?????????????????????
     * flower-video-list
     */
    public function actionFlowerVideoList(){
        $pagesize = Yii::$app->request->post('pagesize',10);
        $id = Yii::$app->request->post('video_id',0); //??????????????????
        $is_next_page = Yii::$app->request->post('next',0); //???????????????
        $data = [
            'banner' => 'http://i.ahj.cm/images/huadi_live/huadi_live_video_banner.png',
            'list' => []
        ];
        //todo ??????????????????????????????
//        $cache_key = md5('flower_')
        $video_sort_list = FlowerArtVideo::getVideoSortList();
        if($id == 0){
            $video_ids = array_splice($video_sort_list,0,$pagesize);
        }else{
            $_video_sort_list = $video_sort_list;
            $offset = current(array_keys($_video_sort_list,$id,false));
            if($is_next_page) $offset = isset($video_sort_list[$offset+1]) ? $offset+1 : '';
            if($offset === ''){
                //?????????
                return $this->responseJson(Message::SUCCESS,'success',['list' => []]);
            }
            $video_ids = array_splice($video_sort_list,$offset,$pagesize);
        }
        $video_list = FlowerArtVideo::find()
            ->where(['video_status' => 1, 'upload_status' => 1])
            ->andWhere(['id' => $video_ids ])
            ->orderBy('is_recommend desc, add_time desc')
            ->select(['id','video_name as video_title','supporting_text as desc','member_avatar','member_name','video_url','video_cover_img','heart_num','comment_num','related_good','update_time'])
            ->asArray()->all();
        $goods_model = new Goods();
        foreach ($video_list as &$video){
            $video['video_cover_img'] = 'https://hua123-static.oss-cn-hangzhou.aliyuncs.com/huadi_floral/img/' . $video['video_cover_img'];
            $video['video_url'] = 'https://hua123-static.oss-cn-hangzhou.aliyuncs.com/huadi_floral/video/' . $video['video_url'];
            $video['member_avatar'] = 'http://i.ahj.cm/images/huadi_live/huayi-master.png?v1';
            $video['member_name'] = '????????????';
            if($video['related_good']) {
                $related_goods_ids = explode(',', $video['related_good']);
                $where = [];
                $where['goods.goods_state'] = 1;
                $where['goods.goods_id'] = $related_goods_ids;
                $order = ["field(goods.goods_id,{$video['related_good']})" => true];
                $field = "goods.goods_id,goods.goods_name,goods.goods_image,goods.goods_material,goods.gc_id,goods_class.gc_name,goods.goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
                $goods_list = $goods_model->goodslist($field, 1, 100, $where, $order,1,[],[],[],0,320);
                //????????????????????????
                $group_goods = (new GroupShoppingGoods())->getGoodsGroup($related_goods_ids);
                foreach ($goods_list as &$val){
                    $is_join_group = false;
                    $group_max_people = 0;
                    foreach ($group_goods as $group_good){
                        if($val['goods_id'] == $group_good['goods_id']){
                            $is_join_group = true;
                            $group_max_people = $group_good['max_people'];
                        }
                    }
                    $val['is_group_shopping'] = $is_join_group ? 1 : 0;
                    if($is_join_group){
                        $val['group_max_people'] = $group_max_people;
                    }
                }
                $video['related_goods_list'] = $goods_list;
            }
            $video['offset'] = $offset;
            $offset++;
            unset($video['related_good']);
        }
        if(!$video_list) $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$data);
        $video_ids = array_column($video_list,'id');
        //??????????????????
        if($this->member_id){
            $star_videos = VideoStar::find()
                ->where(['member_id' => $this->member_id,'star_status' => 1, 'video_id' => $video_ids])
                ->select(['video_id'])->column();
        }
        foreach($video_list as &$video){
            $video['is_star'] = 0;
            if(isset($star_videos) && !empty($star_videos) && in_array($video['id'], $star_videos)){
                $video['is_star'] = 1;
            }
        }
        $data['list'] = $video_list;
        return $this->responseJson(Message::SUCCESS,'success',$data);
    }
    /**
     * ??????????????????/????????????
     * flower-video-star
     */
    public function actionFlowerVideoStar(){
        $video_id = Yii::$app->request->post('video_id', 0);
        if(!$video_id) return $this->responseJson(Message::ERROR,'????????????!');
        if(!$this->member_id) return $this->responseJson(Message::UN_LOGIN,Message::UN_LOGIN_MSG);
        $db = Yii::$app->db->beginTransaction();
        try{
            $model = FlowerArtVideo::findOne(['id' => intval($video_id)]);
            if(!$model) {
                return $this->responseJson(Message::ERROR,'???????????????!');
            }
            $model_star = VideoStar::findOne(['video_id' => $video_id, 'member_id' => $this->member_id]);
            if( !$model_star ) {
                //??????
                $model->heart_num += 1;
                $model_star = new VideoStar();
                $model_star->video_id = $video_id;
                $model_star->member_id = $this->member_id;
                $model_star->star_status = 1;
            }else{
                $add_or_reduce = $model_star->star_status == 1 ? -1 : 1;
                $model->heart_num += $add_or_reduce;
                $model_star->star_status = $model_star->star_status ? 0 : 1;
            }
            $model_star->add_time = time();
            $res = $model_star->save(false);
            $res1 = $model->save(false);
            if( !$res || !$res1){
                Log::writelog('huadi_live_debug',current($model_star->getFirstErrors()) . current($model->getFirstErrors()));
                throw new \Exception('????????????!');
            }
            $db->commit();
            return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->responseJson(Message::ERROR,$e->getMessage());
        }
    }

    /**
     * ??????????????????/????????????
     * video-comment-star
     */
    public function actionVideoCommentStar(){
        $comment_id = Yii::$app->request->post('comment_id', 0);
        if(!$comment_id) return $this->responseJson(Message::ERROR,'????????????!');
        if(!$this->member_id) return $this->responseJson(Message::UN_LOGIN,Message::UN_LOGIN_MSG);
        $db = Yii::$app->db->beginTransaction();
        try{
            $model = VideoComment::findOne(['id' => intval($comment_id)]);
            if(!$model) {
                return $this->responseJson(Message::ERROR,'???????????????!');
            }
            $model_star = CommentStar::findOne(['comment_id' => $comment_id, 'member_id' => $this->member_id]);
            if( !$model_star ) {
                //??????
                $model->heart_num += 1;
                $model_star = new CommentStar();
                $model_star->comment_id = $comment_id;
                $model_star->member_id = $this->member_id;
                $model_star->star_status = 1;
            }else{
                $add_or_reduce = $model_star->star_status == 1 ? -1 : 1;
                $model->heart_num += $add_or_reduce;
                if($model->heart_num < 0) $model->heart_num = 0;
                $model_star->star_status = $model_star->star_status ? 0 : 1;
            }
            $model_star->add_time = time();
            $res = $model_star->save(false);
            $res1 = $model->save(false);
            if( !$res || !$res1){
                Log::writelog('huadi_live_debug',current($model_star->getFirstErrors()) . current($model->getFirstErrors()));
                throw new \Exception('????????????!');
            }
            //?????????????????????????????????????????????
            $notify = Notify::instance();
            $notify_data = [];
            $video_model = FlowerArtVideo::findOne($model->video_id);
            $notify_data['member_id'] = $this->member_id;
            $notify_data['member_name'] = $this->member_name;
            $notify_data['extra_id'] = $model->member_id;
            $notify_data['notify_type'] = $notify::TYPE_HUAYI_VIDEO_PRAISE;
            $notify_data['notify_title'] = "????????????";
            $notify_data['notify_icon']  = 'https://hua123-static.oss-cn-hangzhou.aliyuncs.com/huadi_floral/img/'.$video_model->video_cover_img;
            $notify_data['notify_content'] = "{$this->member_info['member_nickname']}?????????????????????";
            $notify_data['add_time'] = TIMESTAMP;
            $notify_data['object_id'] = $video_model->id;
            $ree = $notify->addNotify($notify_data);
            if(!$ree){
                throw new \Exception('????????????????????????[605]');
            }
            $db->commit();
            return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->responseJson(Message::ERROR,$e->getMessage());
        }
    }
    /**
     * ????????????????????????
     * add-video-comment
     */
    public function actionAddVideoComment(){
        $params = Yii::$app->request->post();
        $video_id = intval($params['video_id']);
        $content = trim($params['content']);
        $comment_id = intval($params['comment_id']);
        if(!$video_id) return $this->responseJson(Message::ERROR,'????????????!');
        $video_model = FlowerArtVideo::findOne(['id'=> $video_id,'video_status' => 1]);
        if(!$video_model) return $this->responseJson(Message::ERROR,'?????????????????????!');
        if(mb_strlen($content) > 128) return $this->responseJson(Message::ERROR, '??????????????????128???');
        //??????????????????
        $aly = new AlyContentSecurity();
        $text_res = $aly->detectionText($content);
        $text_res = json_decode($text_res, true);
        if ($text_res['code'] != 200) {
            return $this->responseJson(Message::ERROR, $text_res['msg']);
        }
        $res = SensitiveWord::detectSensitiveWord($content);
        if($res){
            return $this->responseJson(Message::ERROR, '?????????????????????: ' . $res);
        }
        $db = Yii::$app->db->beginTransaction();
        try{
            $model = new VideoComment();
            $model->member_id = $this->member_id;
            $model->video_id = $video_id;
            $model->member_name = $this->member_info['member_nickname'];
            $model->member_avatar = $this->member_info['member_avatar'];
            $model->add_time = time();
            $model->content = $content;
            $model->to_member_name = '';
            if($comment_id > 0){
                $reply_comment = VideoComment::findOne(['id' => $comment_id,'video_id' => $video_id]);
                if(!$reply_comment) throw new \Exception('???????????????????????????!');
                if($reply_comment->pid == 0){
                    //??????????????????????????????, ??????????????????????????????
                    $model->pid = $comment_id;
                }else{
                    $model->pid = $reply_comment->pid;
                    $model->to_member_name = $reply_comment->member_name;
                }
            }else{
                $model->pid = 0;
            }
            $res = $model->save(false);
            $video_model->comment_num += 1;
            $res1 = $video_model->save(false);
            if(!$res || !$res1) throw new \Exception('????????????');
            //????????????????????????
            $return_data = $model->toArray();
            if(isset($reply_comment)){
                //?????????????????????????????????????????????
                $notify = Notify::instance();
                $notify_data = [];
                $notify_data['member_id'] = $return_data['member_id'];
                $notify_data['member_name'] = $return_data['member_name'];
                $notify_data['extra_id'] = $reply_comment->member_id;
                $notify_data['notify_type'] = $notify::TYPE_HUAYI_VIDEO_COMMENT;
                $notify_data['notify_title'] = "????????????";
                $notify_data['notify_icon']  = 'https://hua123-static.oss-cn-hangzhou.aliyuncs.com/huadi_floral/img/'.$video_model->video_cover_img;
                $notify_data['notify_content'] = "{$return_data['member_name']}?????????????????????";
                $notify_data['add_time'] = TIMESTAMP;
                $notify_data['object_id'] = $video_model->id;
                $ree = $notify->addNotify($notify_data);
                if(!$ree){
                    throw new \Exception('????????????????????????[605]');
                }
            }
            $db->commit();
            $return_data['is_star'] = 0;
            $return_data['heart_num'] = 0;
            return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$return_data);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->responseJson(Message::ERROR,$e->getMessage());
        }
    }
    /**
     * ????????????????????????
     * video-comment-list
     */
    public function actionVideoCommentList(){
        $id = Yii::$app->request->post('id','');//??????????????????id,????????????,????????????????????????
        $video_id = Yii::$app->request->post('video_id',0);
        $pagesize = 10;
        if(!$video_id) return $this->responseJson(Message::ERROR,'????????????!');
        $video_model = FlowerArtVideo::findOne(['id'=> $video_id,'video_status' => 1]);
        if(!$video_model) return $this->responseJson(Message::ERROR,'?????????????????????!');
        $comment_list = VideoComment::find()->alias('a')
            ->leftJoin(CommentStar::tableName().' b','a.id=b.comment_id and b.member_id=' . $this->member_id)
            ->where(['a.pid' => 0,'a.comment_status' => 1, 'video_id' => $video_id])
            ->andFilterWhere(['<','a.id',$id])
            ->select(['(case when b.star_status = 1 then 1 else 0 end) is_star','a.id','a.add_time','a.member_id','a.member_name','a.member_avatar','a.content','a.heart_num','a.author'])
            ->orderBy('a.add_time desc')
            ->limit($pagesize)
            ->asArray()->all();
        if(!$comment_list) return $this->responseJson(Message::SUCCESS,'success!',[]);
        foreach($comment_list as &$top){
            $top['member_avatar'] = getMemberAvatar($top['member_avatar']);
            $sub_comment_list = VideoComment::find()->alias('a')
                ->leftJoin(CommentStar::tableName().' b','a.id=b.comment_id and b.member_id=' . $this->member_id)
                ->where(['a.comment_status' => 1, 'a.pid' => $top['id'], 'video_id' => $video_id])
                ->select(['(case when b.star_status=1 then 1 else 0 end) is_star', 'a.id','a.add_time','a.member_id','a.member_name','a.member_avatar','a.to_member_name','a.content','a.heart_num','a.author'])
                ->orderBy('add_time desc')
                ->limit(2)->asArray()->all();
            if($sub_comment_list){
                foreach ($sub_comment_list as &$sub_comment){
                    $sub_comment['member_avatar'] = getMemberAvatar($sub_comment['member_avatar']);
                    $top['sub_comment_list'][] = $sub_comment;
                }
            }else{
                $top['sub_comment_list'] = [];
            }
        }
        return $this->responseJson(Message::SUCCESS,'success!',$comment_list);
    }
    /**
     * ??????????????????????????????
     * get-comment-detail
     */
    public function actionGetCommentDetail(){
        $top_comment_id = Yii::$app->request->post('top_comment_id', 0);//????????????id
        $cur_comment_id = Yii::$app->request->post('cur_comment_id', 0);//????????????id
        $video_id = Yii::$app->request->post('video_id', 0);//??????id
        if(!$top_comment_id || !$cur_comment_id || !$video_id) return $this->responseJson(Message::ERROR,'????????????!');
        $comment_list = VideoComment::find()->alias('a')
            ->leftJoin(CommentStar::tableName().' b','a.id=b.comment_id and b.member_id=' . $this->member_id)
            ->where(['a.pid' => $top_comment_id,'a.comment_status' => 1, 'video_id' => $video_id])
            ->andFilterWhere(['<','a.id',$cur_comment_id])
            ->select(['(case when b.star_status = 1 then 1 else 0 end) is_star','a.id','a.add_time','a.member_id','a.member_name','a.member_avatar','a.content','to_member_name','a.heart_num','a.author'])
            ->orderBy('a.add_time desc')
            ->limit(10)
            ->asArray()->all();
        return $this->responseJson(Message::SUCCESS,'success!',$comment_list);
    }
    /**
     * ??????????????????
     * get-comment-detail
     */
    public function actionGetVideoStatus(){
        $video_id = Yii::$app->request->post('video_id', 0);//??????id
        $video_status = FlowerArtVideo::find()->where(['id' => intval($video_id)])->select(['video_status'])->scalar();
        if($video_status){
            $data['status'] = 1;
        }else{
            $data['status'] = 0;
        }
        return $this->responseJson(Message::SUCCESS,'success!',$data);
    }
}
