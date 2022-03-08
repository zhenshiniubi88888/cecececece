<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Address;
use common\models\Area;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * AreaController
 */
class AreaController extends BaseController
{

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 匹配滴滴select插件字典
     * @return mixed
     */
    public function actionLatest()
    {
        $data = [];
        $data['dictionary'] = Area::getAreaTree();
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 重新拼装字母下面的城市，以及重新生成带筛选数据
     * @param $letter
     * @param $city_arr
     * @return array
     */
    private function letterSort($letter, $city_arr)
    {
        $letter_city = [];
        $new_city_arr = [];
        foreach ($city_arr as $city){
            if($city['area_tag'] == $letter){
                unset($city['area_tag']);
                $letter_city[] = $city;
            }else{
                $new_city_arr[] = $city;
            }
        }

        return ['letter_city' => $letter_city, 'city_arr' => $new_city_arr];
    }

    /**
     * 第三级地址排序
     */
    public function actionAddressSort(){
        $cache_name = "address_sort_letter_city1";
        $letter_city = cache($cache_name);
        if(!$letter_city || IS_TEST) {
            $city = Area::getAreaList([], 'area_id,area_name,name,area_tag', 'area_tag asc,area_id asc');
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y', 'Z'];
            $letter_city = [];//根据字母排序的城市
            foreach ($letters as $letter) {
                $now_city_data = $this->letterSort($letter, $city);
                $letter_city[] = [
                    'letter' => $letter,
                    'city' => $now_city_data['letter_city']
                ];
                $city = $now_city_data['city_arr'];
            }
            cache($cache_name,serialize($letter_city),3600*48);
        }else{
            $letter_city = unserialize($letter_city);
        }
        //热门城市
        $where = ['>','area_parent_id',0];
        $where=['and', $where, ['area_hot'=>1]];
        $hot_city = Area::find()->where($where)->select('area_id,area_name,name')->limit(12)->asArray()->all();
        //常用城市
//        $history_city = Orders::find()
//            ->alias("order")
//            ->join("join","hua123_order_common common","common.order_id = `order`.order_id")
//            ->join("join","hua123_area area","area.area_id = common.reciver_city_id")
//            ->where(['order.buyer_id'=>$this->member_id])
//            ->select("area.area_id,area.area_name,area.name")
//            ->groupBy("area.area_name")
//            ->limit(8)
//            ->asArray()
//            ->all();
        $data['letter_city'] = $letter_city;
        $data['hot_city'] = $hot_city;
//        $data['history_city'] = $history_city;

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }
}
