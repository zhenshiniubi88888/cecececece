<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\WeixinHuadi;
use common\models\AddpriceLog;
use common\models\Attribute;
use common\models\Goods;
use common\models\GoodsAddprice;
use common\models\GoodsClass;
use common\models\Recommend;
use Yii;
use yii\base\InvalidParamException;
use common\components\Message;
use yii\db\Expression;

/**
 * WechatController
 */
class WechatController extends BaseController
{
    /**
     * 花递微信首页
     * @return mixed
     */
    public function actionIndex()
    {
        $index_key = md5("hua123_wechat_index2");
        $data = cache($index_key);
        if (!$data) {
            $data = [];
            $adv_model = new \common\models\Adv();
            //顶部banner
            $data['top_banner'] = $adv_model->getBanner(137);
            $data['month_banner'] = $adv_model->getBanner(138);
            $data['fitment_banner'] = $adv_model->getBanner(139);
            $data['present_banner'] = $adv_model->getBanner(140);
            $data['meaty_banner'] = $adv_model->getBanner(141);

            //热门分类
            $attr_model = new Attribute();
            $data['attribute_list'] = $attr_model->getHotAttribute(1);

            //精选推荐
            $rec_model = new Recommend();
            $data['fitment_list'] = $rec_model->getBestGoods(Goods::FLOWER_HOME, 4);
            $data['present_list'] = $rec_model->getBestGoods(Goods::FLOWER_GIFT, 4);
            $data['meaty_list'] = $rec_model->getBestGoods(Goods::FLOWER_DUOROU, 4);

            //分类筛选
            $data['category'] = GoodsClass::getAllClass();

            //缓存1小时
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 花递微信礼品花首页
     * @return mixed
     */
    public function actionGiftIndex()
    {
        //banner
        $index_key = md5("hua123_wechat_gift_index1");
        $data = cache($index_key);
        if (!$data || 1) {
            $data = [];
            $adv_model = new \common\models\Adv();
            //顶部banner
            $data['top_banner'] = $adv_model->getBanner(142);

            //热门分类
            $attr_model = new Attribute();
            $data['attribute_list'] = $attr_model->getHotAttribute(1);

            //缓存1小时
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 花递微信多肉首页
     * @return mixed
     */
    public function actionMeatyIndex()
    {
        //banner
        $index_key = md5("hua123_wechat_meaty_index");
        $data = cache($index_key);
        if (!$data) {
            $data = [];
            $adv_model = new \common\models\Adv();
            //顶部banner
            $data['top_banner'] = $adv_model->getBanner(143);

            //缓存1小时
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 买花送人
     * @section love.爱情鲜花|basket.开业鲜花|birthday.生日鲜花
     * floor style 列表样式:1 一张图展示 2.横向列表2D 3.横向列表3D效果
     * @return mixed
     */
    public function actionPresentFlower()
    {
        $allow = array('love', 'basket', 'birthday');
        $section = \Yii::$app->request->post('section', 'love');
        $section = in_array($section, $allow) ? $section : 'love';
        $index_key = md5("hua123_wechat_present_index" . $section);
        cache($index_key,null);
        $data = cache($index_key);
        if (!$data) {
            $data = [];
            switch ($section) {
                case 'love':
                    $ap_id = 155;
                    $floor = array(
                        array(
                            'title' => '表白求婚',
                            'subtitle' => '专属求爱神器',
                            'goods_list' => $this->getRecommendProduct(
                                ['goods_id' => Goods::instance()->getGoodsByAttrValueId(611)]
                                ,6
                            ),
                            'style' => 3,
                        ),
                        array(
                            'title' => '纪念日',
                            'subtitle' => '纪念属于我们的那个特殊日子',
                            'goods_list' => $this->getRecommendProduct(
                                ['goods_id' => Goods::instance()->getGoodsByAttrValueId(612)]
                                ,6
                            ),
                            'style' => 2,
                        ),
                    );
                    break;
                case 'basket':
                    $ap_id = 156;
                    $floor = array(
                        array(
                            'title' => '特别推荐',
                            'subtitle' => '福大志大，家大业大；财源滚滚，万事大吉',
                            'goods_list' => $this->getRecommendProduct(
                                ['goods_id' => Goods::instance()->getGoodsByAttrValueId(94)]
                                ,1
                            ),
                            'style' => 1,
                        ),
                    );
                    break;
                case 'birthday':
                    $ap_id = 157;
                    $floor = array(
                        array(
                            'title' => '特别推荐',
                            'subtitle' => '特别的生日鲜花给特别的你',
                            'goods_list' => $this->getRecommendProduct(
                                ['goods_id' => Goods::instance()->getGoodsByAttrValueId(613)]
                                ,6
                            ),
                            'style' => 3,
                        ),
                    );
                    break;
                default:
                    $ap_id = 155;
                    $floor = array();
                    break;
            }
            //顶部banner
            $adv_model = new \common\models\Adv();
            $data['top_banner'] = $adv_model->getBanner($ap_id);
            //Title
            $data['floor'] = $floor;

            //缓存1小时
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }


    /**
     * 获取推荐商品
     * @param array $condition
     * @param int $limit
     * @param string $order
     * @return array|\yii\db\ActiveRecord[]
     */
    private function getRecommendProduct($condition = array(), $limit = 1, $order = 'default_order desc,goods_click desc,goods_id desc')
    {
        $goods_model = new Goods();
        $map = [];
        $map['goods_state'] = 1;
        $map['goods_verify'] = 1;
        $map = ['AND', $map, $condition];
        $goods_list = $goods_model->getGoodsList($map, null, 'goods_id,goods_name,goods_jingle,goods_material,ahj_goods_price,goods_price,goods_image,gc_id_2,is_hot,is_best,is_desig,is_select,is_normal,is_disc,is_disc68,is_good,is_lowest', $order, $limit);
        $output = array();
        foreach ($goods_list as $k => $goods) {
            $item = array();
            $item['goods_id'] = $goods['goods_id'];
            $item['goods_name'] = $goods['goods_name'];
            $item['goods_jingle'] = $goods['goods_jingle'] ? $goods['goods_jingle'] : $goods['goods_material'];
            $item['goods_img'] = thumbGoods($goods['goods_image'], 260);
            $item['goods_type'] = Goods::instance()->getGoodsType($goods['gc_id_2']);
            $item['goods_price'] = FinalPrice::S($goods['goods_price']);
            $item['goods_tag'] = Goods::instance()->getGoodsTag($goods);
            array_push($output,$item);
        }
        return $output;
    }

    /**
     * 获取线上token
     */
    public function actionTest()
    {
        $secret = isset($_GET['secret']) ? $_GET['secret'] : '';
        if (!$secret || $secret != 'lFrHJydVohhARkoY') {
            echo "非法用户";die;
        }
        $access_token = WeixinHuadi::getInstance()->get_access_token();
        echo $access_token;die;
    }
}
