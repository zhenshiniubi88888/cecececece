<?php
namespace frontend\controllers;

use common\models\AddpriceLog;
use common\models\Goods;
use common\models\GoodsAddprice;
use Yii;
use yii\base\InvalidParamException;
use common\components\Message;
use yii\db\Expression;
/**
 * Flower controller
 */
class FlowerapiController extends BaseController
{



    /**
     * {@inheritdoc}t
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
     * 获取礼品花列表
     * @return mixed
     */
    public function actionGoodslist()
    {
        $page = Yii::$app->request->post("page",1);
        $addprice = Yii::$app->request->post("addprice",0);
        $member_id = Yii::$app->request->post("member_id",25679);
        $pagesize = 10;
        if($member_id <= 0){
            return $this->responseJson(Message::ERROR, "会员id不能为空");
        }
        $nocount = $this->getNoGoodscount($member_id);
        $count = $this->getGoodscount();

        $data = [];
        //获取礼品花列表
        $query = GoodsAddprice::find();
        $goods = $this->getGoodslist($page,$pagesize,$addprice,$member_id);
        foreach ($goods as $k => $v) {
            $gds = $query->where(array("member_id" => $member_id,"goods_id" => $v["goods_id"]))->select("*")->asArray()->one();
            if($gds){
                $goods[$k]["goods_costprice"] = $gds["report_price"];
            }
            $goods[$k]["shop_price"] = $v["goods_costprice"];
        }
        $data['goods'] = $goods;
        $data["nocount"] = $nocount;
        $data["count"] = ($count - $nocount) > 0 ? ($count - $nocount)."": 0;

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 批量添加报价
     * @return mixed
     */
    public function actionBatchAddPrice(){
        $member_id = Yii::$app->request->post("member_id",62219);
        $store_id = Yii::$app->request->post("store_id",380482);
        $goods = Yii::$app->request->post("goods","76,77");
        $type = Yii::$app->request->post("batch_type",1);
        $price = Yii::$app->request->post("price",100);
        if($price >= 100){
            $batch_type = 1;
        }else{
            $batch_type = 2;
        }
        if($type == 3){
            $batch_type = 3;
        }
        $price = $price/100;
        $addprice_model = new GoodsAddprice();
        $result = $addprice_model->batchAddPrice($member_id,$store_id,$goods,$batch_type,$price);
        if(!$result){
            return $this->responseJson(Message::ERROR, $addprice_model->getErrors()["error"][0]);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 添加报价
     * @return mixed
     */
    public function actionAddPrice(){
        $member_id = Yii::$app->request->post("member_id",0);
        $store_id = Yii::$app->request->post("store_id",0);
        $goods = Yii::$app->request->post("goods","");
        $price = Yii::$app->request->post("price","");
        $addprice_model = new GoodsAddprice();
        $result = $addprice_model->addPrice($member_id,$store_id,$goods,$price);
        if(!$result){
            return $this->responseJson(Message::ERROR, $addprice_model->getErrors()["error"][0]);
        }
        $data["price"] = $price;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }

    /**
     * 花店报价日志
     * @return mixed
     */
    public function actionAddPricelist(){
        $page = Yii::$app->request->post("page",1);
        $batch_type = Yii::$app->request->post("batch_type",0);
        $member_id = Yii::$app->request->post("member_id",0);

        $pagesize = 10;
        $offset = ($page - 1) * $pagesize;
        $condition = [];
        $condition['member_id'] = $member_id;
        if($batch_type > 0){
            $condition['log_type'] = $batch_type;
        }
        $query = AddpriceLog::find();
        $list = $query->where($condition)->orderBy("add_time DESC")->limit($pagesize)->offset($offset)->select("*")->asArray()->all();
        foreach ($list as $k => $v){
            $list[$k]["add_time"] = date("Y-m-d H:i:s",$v["add_time"]);
            if($v["log_type"] == 1){
                $list[$k]["log_price"] = "上涨".($v["log_price"] * 100)."%";
            }elseif($v["log_type"] == 2){
                $list[$k]["log_price"] = "下降".($v["log_price"] * 100)."%";
            }else{
                $list[$k]["log_price"] = "";
            }

        }

        $data["loglist"] = $list;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //获取礼品花列表
    private function getGoodslist($page=1,$pagesize=10,$addprice = 1,$member_id=0)
    {
        $offset = ($page - 1) * $pagesize;
        if($addprice == 0){
            $sql = "select goods_id,goods_image,goods_name,goods_costprice,goods_material,goods_jingle from hua123_goods where gc_id = 13 and goods_state = 1 and goods_id not in (select goods_id from hua123_goods_addprice where member_id = ".$member_id.") order by sort_order desc limit $offset,$pagesize";

        }else{
            $sql = "select goods_id,goods_image,goods_name,goods_costprice,goods_material,goods_jingle from hua123_goods where gc_id = 13 and goods_state = 1 and goods_id in (select goods_id from hua123_goods_addprice where member_id = ".$member_id.") order by sort_order desc limit $offset,$pagesize";

        }
        try{
            $goods_list = Goods::findBySql($sql)->asArray()->all();

            $goods_data = [];
            foreach ($goods_list as $key => $goods) {
                $data = [];
                // $data['goods_rank'] = $key + 1;
                $data['goods_id'] = $goods['goods_id'];
                //   $data['goods_type'] = GOODS_TYPE_FLOWER;
                $data['goods_image'] = thumbGoods($goods['goods_image'], 150);
                $data['goods_name'] = $goods['goods_name'];
                $data['goods_costprice'] = $goods['goods_costprice'];

                // $data['goods_jingle'] = $goods['goods_jingle'];
                $data['goods_material'] = $goods['goods_material'];
                $data['addprice'] = $addprice."";

                $goods_data[] = $data;
            }
            return $goods_data;
        }catch (\Exception $e){
            return [];
        }
    }

    /**
     * 获取商品总数量
     * @return int|string
     */
    private function getGoodscount(){
        $condition = [];
        $condition['gc_id'] = 13;
        $condition['goods_state'] = 1;
        $model_goods = new Goods();
        $count = $model_goods::find()->where($condition)->count();
        $count = $count>0?$count:0;
        return $count."";
    }

    /**
     * 获取没有设置接单价的商品数量
     * @param int $member_id
     * @throws \yii\db\Exception
     */
    public function getNoGoodscount($member_id = 0){

        $sql = "select count(*) as num from hua123_goods where gc_id = 13 and goods_state = 1 and goods_id not in (select goods_id from hua123_goods_addprice where member_id = ".$member_id.")";
        try{
            $res = Goods::findBySql($sql)->asArray()->one();
            $count = $res["num"] > 0 ? $res["num"] : 0;
            return $count;
        }catch (\Exception $e){
            return 0;
        }
    }

    public function actionTest(){
        $addprice_model = new GoodsAddprice();
        $result = $addprice_model->addPrice(62219,380482,"77",1);
        print_r($addprice_model->getErrors());die;
    }

    public function actionGoodsnum(){
        $member_id = Yii::$app->request->post("member_id",0);
        if($member_id <= 0){
            return $this->responseJson(Message::ERROR, "会员id不能为空");
        }
        $nocount = $this->getNoGoodscount($member_id);
        $count = $this->getGoodscount();
        $data["nocount"] = $nocount;
        $data["count"] = $count;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

}
