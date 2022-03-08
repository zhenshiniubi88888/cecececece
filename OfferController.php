<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Goods;
use common\models\GoodsAddprice;
use linslin\yii2\curl\Curl;
use yii\web\HttpException;
use Faker;

/**
 */
class OfferController extends BaseController
{
    public function actionIndex()
    {
        throw new HttpException(405);
    }

    public function actionSearch()
    {
        $param = \Yii::$app->request->post();
        $goods_id = (int)$param['goods_id'];
        $lat = \Yii::$app->request->post("lat", "30.653585");
        $lng = \Yii::$app->request->post("lng", "104.056238");
        $order = \Yii::$app->request->post("order", 1);//1.综合 2.接单量 3.价格升序 4.价格降序 5.距离
        if (!$goods_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $pagesize = 10;//$pagesize = Yii::$app->request->post("pagesize",10);
        $cache_name = 'api_offer_search_' . $goods_id . $lat . $lng . $pagesize;
        if (false == $data = cache($cache_name)) {
            $data = [];
            $offer_list = [];
            $url = MICRO_DOMAIN . "/api/nearstorelist.html";
            $param = [
                "lng" => $lng,
                "lat" => $lat,
                "order" => $order,
            ];

            $curl = new Curl();
            $response_data = $curl->setOption(
                CURLOPT_POSTFIELDS,
                http_build_query($param)
            )->post($url);

            $response = json_decode($response_data, true);
            if (!$response["status"]) {
                \Yii::error($response_data);
                return $this->responseJson(Message::ERROR, '获取报价失败，请重试');
            }
            $goodsadd_model = new GoodsAddprice();
            $goods_model = new Goods();
            $_storelist = [];
            $storelist = json_decode($response["data"], true);
            $store = $storelist["store"];
            $price = 0;
            $well = 0;
            foreach ($store as $k => $v) {
                if ($well < $v["good_comment"]) {
                    $well = $v["good_comment"];
                }
                $_store["store_id"] = $v["store_id"];
                $_store["store_name"] = $v["store_name"];
                $_store["store_label"] = $v["store_label"];
                $_store["store_star"] = priceFormat($v["store_point"], 1);
                $_store["store_sale"] = $v["sell_num"];
                $_store["store_rate"] = $v["good_comment"];
                $_store["store_distance"] = $v["distance"];
                $_store["tag"] = "";
                $addprice = $goodsadd_model::find()->where(array("store_id" => $v["store_id"], "goods_id" => $goods_id))->select("ap_id,goods_id,report_price")->asArray()->one();
                if (!$addprice) {
                    $goods = $goods_model->getGoodsInfoById($goods_id, "goods_costprice");
                    if (!$goods) {
                        continue;
                    }
                    $price = $goods["goods_costprice"];
                    if ($price > $goods["goods_costprice"]) {
                        $price = $goods["goods_costprice"];
                    }
                    $_store["offer_price"] = $goods["goods_costprice"];
                } else {
                    $price = $addprice["report_price"];

                    if ($price > $addprice["report_price"]) {
                        $price = $addprice["report_price"];
                    }
                    //$_store["ap_id"] = $addprice["ap_id"];
                    $_store["offer_price"] = $addprice["report_price"];
                }

                $_storelist[] = $_store;
            }

            $store_count = count($_storelist);

            foreach ($_storelist as $k => $v) {
                if ($price >= $v["offer_price"]) {
                    $_storelist[$k]["tag"] = "价格最低";
                    break;
                }
            }
            foreach ($_storelist as $k => $v) {
                if ($well <= $v["store_rate"]) {
                    $_storelist[$k]["tag"] = "评价最优";
                    break;
                }
            }

            $data["offer_list"] = $_storelist;
            $data["offer"]["count"] = $store_count;
            $data["offer"]["cheapness_price"] = $price;
            cache($cache_name, $data, 86400);
        }

        $_storelist = $data['offer_list'];
        if($order == 1){//综合排序
            /* for($i=0;$i<$store_count-1;$i++){
                 for($j=$i+1;$j<$store_count;$j++){
                     if($_storelist[$i]["store_wellcredit"] < $_storelist[$j]["store_wellcredit"]){
                         $temp = $_storelist[$j];
                         $_storelist[$i] = $temp;
                         $_storelist[$j] = $_storelist[$i];
                     }
                 }
             }*/
            $_storelist = arraySort($_storelist,"store_wellcredit",SORT_DESC);
        }elseif($order == 2){//销量
            /* for($i=0;$i<$store_count-1;$i++){
                 for($j=$i+1;$j<$store_count;$j++){
                     if($_storelist[$i]["store_orders"] < $_storelist[$j]["store_orders"]){
                         $temp = $_storelist[$j];
                         $_storelist[$i] = $temp;
                         $_storelist[$j] = $_storelist[$i];
                     }
                 }
             }*/
            $_storelist = arraySort($_storelist,"store_sale",SORT_DESC);
        }elseif($order == 3){//价格升序
            /*for($i=0;$i<$store_count-1;$i++){
                for($j=$i+1;$j<$store_count;$j++){
                    if($_storelist[$i]["offer_price"] > $_storelist[$j]["offer_price"]){
                        $temp = $_storelist[$j];
                        $_storelist[$i] = $temp;
                        $_storelist[$j] = $_storelist[$i];
                    }
                }
            }*/
            $_storelist = arraySort($_storelist,"offer_price",SORT_ASC);
        }elseif($order == 4){//价格降序
            /*for($i=0;$i<$store_count-1;$i++){
                for($j=$i+1;$j<$store_count;$j++){
                    if($_storelist[$i]["offer_price"] < $_storelist[$j]["offer_price"]){
                        $temp = $_storelist[$j];
                        $_storelist[$i] = $temp;
                        $_storelist[$j] = $_storelist[$i];
                    }
                }
            }*/
            $_storelist = arraySort($_storelist,"offer_price",SORT_DESC);
        }

        $data['offer_list'] = $_storelist;

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);

    }

}
