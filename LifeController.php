<?php

namespace frontend\controllers;

use Api\Alipay\Alipay;
use Api\Sina\Sina;
use Api\Tencent\QQ;
use common\components\Log;
use common\components\Message;
use common\models\AgentMember;
use common\models\CachedKeys;
use common\models\Goods;
use common\models\Member;
use common\models\MemberVerify;
use yii\helpers\Url;
use yii\web\Cookie;
use yii\web\HttpException;

/**
 * 生活花
 */
class LifeController extends BaseController
{
    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 生活花首页
     */
    public function actionHome()
    {
        $index_key = md5("hua123_life_home1");
        $data = cache($index_key);
        if (!$data) {
            $data = [];
            $adv_model = new \common\models\Adv();
            //顶部banner
            $data['top_banner'] = $adv_model->getBanner(149);
            $data['share_banner'] = $adv_model->getBanner(150);

            //每周新品
            $data['weekly_product'] = $this->getWeeklyProduct();
            //39体验款
            $data['try_39'] = $this->getExperienceProduct(39, 3);
            //69体验款
            $data['try_69'] = $this->getExperienceProduct(69, 6);
            //99体验款
            $data['try_99'] = $this->getExperienceProduct(99, 6);
            //精品推荐
            $data['recommend'] = $this->getRecommendProduct();
            //缓存1小时
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 精品推荐
     * @return mixed
     */
    public function actionRecommend()
    {
        $page = \Yii::$app->request->post('page', 1);
        $allow_during = array(39, 69, 99);
        $price = \Yii::$app->request->post('section', 0);
        $price = in_array((int)$price, $allow_during) ? (int)$price : 0;
        $data = [];
        //精品推荐
        $data['recommend'] = $this->getRecommendProduct($page, $price);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 每周新品
     * @return mixed
     */
    public function actionWeek()
    {
        $index_key = md5("hua123_life_week");
        $data = cache($index_key);
        if (!$data) {
            $data = [];
            $adv_model = new \common\models\Adv();
            //顶部banner
            $data['top_banner'] = $adv_model->getBanner(151);

            //每周新品
            $data['newly_product'] = $this->getWeeklyProduct();
            //历史
            $data['history_product'] = $this->getHistoryProduct(count($data['newly_product']) + 1);

            //缓存1小时
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 体验款
     * @return mixed
     */
    public function actionExperience()
    {
        $allow_during = array(39, 69, 99);
        $price = \Yii::$app->request->post('section', 39);
        $price = in_array((int)$price, $allow_during) ? (int)$price : 39;
        $index_key = md5("hua123_life_experience_5" . $price);
        $data = cache($index_key);
        if (!$data) {
            $data = [];
            $adv_model = new \common\models\Adv();
            //顶部banner
            $ap_id = 152;
            switch ($price) {
                case 39:
                    $ap_id = 152;
                    break;
                case 69:
                    $ap_id = 153;
                    break;
                case 99:
                    $ap_id = 154;
                    break;
            }
            $data['top_banner'] = $adv_model->getBanner($ap_id);
            //今日推荐
            $data['daily_product'] = $this->getWeeklyProduct($price, 3);
            //为您精选
            $data['recommend'] = $this->getRecommendProduct(1, $price);
            //周边
            $data['assort'] = $this->getAssortProduct($price);
            //缓存1小时
            cache($index_key, $data, 3600);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 每周新品
     * @param int $price
     * @param int $limit
     * @return array|\yii\db\ActiveRecord[]
     */
    private function getWeeklyProduct($price = 0, $limit = 3)
    {
        return $this->getLifeProduct($price, 1, $limit, 'goods_addtime desc');
    }

    /**
     * 获取体验款
     * @param $price
     * @param int $limit
     * @return array|\yii\db\ActiveRecord[]
     */
    private function getExperienceProduct($price, $limit = 3)
    {
        return $this->getLifeProduct($price, 1, $limit, 'sort_order desc,is_best desc,is_hot desc,goods.goods_id desc');
    }

    /**
     * 推荐
     * @param int $page
     * @param int $price
     * @return array|\yii\db\ActiveRecord[]
     */
    private function getRecommendProduct($page = 1, $price = 0)
    {
        return $this->getLifeProduct($price, $page, 10, 'sort_order desc,goods_click desc,is_best desc,is_hot desc,goods_id desc');
    }

    /**
     * 获取历史上新
     * @param int $offset 排除本周上新的产品数
     * @return array|\yii\db\ActiveRecord[]
     */
    private function getHistoryProduct($offset = 0)
    {
        //显示近两个月的产品
        $goods_model = new Goods();
        $map = [];
        $map['goods_state'] = 1;
        $map['goods_verify'] = 1;
        $map['gc_id'] = Goods::FLOWER_HOME;
        $map = ['AND', $map, ['>', 'goods_addtime', strtotime('-2 month')]];
        $goods_list = $goods_model->getGoodsList($map, null, 'goods_id,goods_name,goods_material as goods_jingle,ahj_goods_price,goods_price,goods_image,goods_addtime', 'goods_addtime desc', '', $offset);
        //按天分类
        $day_list = array();
        foreach ($goods_list as $k => $goods) {
            $goods['goods_img'] = thumbGoods($goods['goods_image'], 260);
            $goods['goods_type'] = GOODS_TYPE_HOME_FLOWER;
            $goods['goods_price'] = (int)$goods['goods_price'];
            $day_list[date('Y-m-d', $goods['goods_addtime'])][] = $goods;
        }
        //整理
        $output = array();
        foreach ($day_list as $date => $goods_list) {
            $data = array();
            $early = end($goods_list);
            $data['date'] = date('Y') == date('Y', $early['goods_addtime']) ? date('m月d日 H:i', $early['goods_addtime']) : date('Y年m月d日 H:i', $early['goods_addtime']);
            $data['goods_list'] = array_slice($goods_list, 0, 6);
            array_push($output, $data);
        }
        return $output;
    }

    /**
     * 获取生活花产品
     * @param $price
     * @param int $page
     * @param int $limit
     * @param string $order
     * @return array|\yii\db\ActiveRecord[]
     */
    private function getLifeProduct($price, $page = 1, $limit = 3, $order = 'sort_order desc')
    {
        $goods_model = new Goods();
        $map = [];
        $map['goods_state'] = 1;
        $map['goods_verify'] = 1;
        $map['gc_id'] = Goods::FLOWER_HOME;
        if ($price > 0) {
            $map['ahj_goods_price'] = priceFormat($price);
        }
        $offset = ($page - 1) * $limit;
        $goods_list = $goods_model->getGoodsList($map, null, 'goods_id,goods_name,goods_material as goods_jingle,ahj_goods_price,goods_price,goods_image', $order, $limit, $offset);
        foreach ($goods_list as $k => $goods) {
            $goods_list[$k]['goods_img'] = thumbGoods($goods['goods_image'], 260);
            $goods_list[$k]['goods_type'] = GOODS_TYPE_HOME_FLOWER;
            $goods_list[$k]['goods_price'] = (int)$goods['goods_price'];
        }
        return $goods_list;
    }

    /**
     * 获取周边推荐
     * @param $price
     * @return array
     */
    private function getAssortProduct($price)
    {
        $assort_data = array();
        switch ($price) {
            default:
                array_push($assort_data, [
                    'goods_id' => 13821,
                    'goods_type' => GOODS_TYPE_OTHER,
                    'goods_name' => '彩色玻璃花瓶',
                    'goods_jingle' => '简约清新，明净自然',
                    'goods_img' => 'http://i.ahj.cm/g/1/2019/04/1_06083063665001436_320.jpg',
                ]);
                array_push($assort_data, [
                    'goods_id' => 13822,
                    'goods_type' => GOODS_TYPE_OTHER,
                    'goods_name' => '宽头家用花剪',
                    'goods_jingle' => '锋锐润滑',
                    'goods_img' => 'http://i.ahj.cm/g/1/2019/04/1_06083067491807047_320.jpg',
                ]);
                array_push($assort_data, [
                    'goods_id' => 13823,
                    'goods_type' => GOODS_TYPE_OTHER,
                    'goods_name' => '荷兰可利鲜',
                    'goods_jingle' => '净化水质,延长花期',
                    'goods_img' => 'http://i.ahj.cm/g/1/2019/04/1_06083070699587941_320.jpg',
                ]);
                break;
        }
        return $assort_data;
    }

}
