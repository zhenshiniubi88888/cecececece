<?php

namespace frontend\controllers;

use common\components\Message;
use common\components\MicroApi;
use common\models\Address;
use common\models\Area;
use common\models\EvaluateGoods;
use common\models\Favorites;
use common\models\HuadiStoreZm;
use common\models\Orders;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * FavoritesController
 */
class FavoritesController extends BaseController
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
     * 店铺关注
     * @param int $page
     * @return mixed
     */
    public function actionStore($page = 1)
    {
        $data = [];
        $fav = new Favorites();
        $data['fav_list'] = $fav->getFavStore($this->member_id, $page);
        //如果未关注店铺,就返回推荐店铺列表
        if(empty($data['fav_list'])){
            $lng = Yii::$app->request->post("lng");
            $lat = Yii::$app->request->post("lat");
            $distance = Yii::$app->request->post("distance", "30");
            $obj = Yii::$app->runAction('micro/huadi-storelist',['lng' => $lng, 'lat' => $lat, 'distance' => $distance]);
            $_data = $obj->data;
            if(isset($_data['code']) && $_data['code'] == 1){
                $_store_list = array();
                if($_data['content']['store']){
                    $zm_store_data = array_column($_data['content']['store'],null,'store_id');
                    $api = new MicroApi();
                    $store_data = $api->httpRequest('/api/search_storelist', ['store' => implode(',', array_column($_data['content']['store'], 'store_id'))]);
                    if ($store_data) {
                        $store_list = $store_data['store'];
                        $store_list = array_unique($store_list, SORT_REGULAR);;
                        foreach($store_list as $store){
                            $_store['store_label'] = $store['store_label'];
                            $_store['store_name'] = $store['store_name'];
                            $_store['store_id'] = $store['store_id'];
                            $storeInfo = HuadiStoreZm::find()->where(['store_id'=>$store['store_id']])->select('store_star')->one();
                            $_store['store_star'] = $storeInfo['store_star'] ? $storeInfo['store_star'] : $store['store_star'];
                            $_store['store_sale'] = $store['sell_num'];
                            $_store['store_rate'] = $store['good_comment'];
                            $_store['order_count'] = $store['real_order'];
                            $_store['distance'] = isset($zm_store_data[$store['store_id']]) ? $zm_store_data[$store['store_id']]['distance'] : '1km';
                            $_store['send_time'] = isset($zm_store_data[$store['store_id']]) ? $zm_store_data[$store['store_id']]['send_time'] : '10分钟';
                            $_store['address'] = isset($zm_store_data[$store['store_id']]) ? $zm_store_data[$store['store_id']]['address'] : '';
                            if(SITEID == 258){
                                //单量
                                $order_count_condition = [
                                    'siteid' => SITEID,
                                    'delivery_store_id' => $store['store_id'],
                                    'order_state'       => ['egt',20]
                                ];
                                $_store['order_count'] = Orders::find()->where($order_count_condition)->count();
                                //评价
                                $comment_count_condition = [
                                    "geval_storeid" => $store['store_id']
                                ];
                                $_store['comment_count'] = EvaluateGoods::find()->where($comment_count_condition)->count();
                                //好评
                                $good_comment_count_condition = [
                                    "geval_storeid" => $store['store_id'],
                                    'geval_level'       => 1
                                ];
                                $_store['good_comment_count'] = EvaluateGoods::find()->where($good_comment_count_condition)->count();
                            }
                            $_store_list[] = $_store;
                        }
                    }

                    $data['recommend_store_list'] = $_store_list;
                }

            }

        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 批量取消店铺关注
     */
    public function actionCancelfollows()
    {
        $store_ids = Yii::$app->request->post("store_ids", "");
        if (empty($store_ids)) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $favorites_model = new Favorites();
        $result = $favorites_model->cancelFollows($this->member_id, $store_ids);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $favorites_model->getErrors()["error"][0]);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

}
