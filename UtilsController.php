<?php

namespace frontend\controllers;

use common\components\Log;
use common\components\Message;
use common\models\Address;
use common\models\Area;
use common\models\Member;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * UtilsController
 */
class UtilsController extends BaseController
{
    public function init()
    {
        parent::init();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    public function actionTest(){
        Log::writelog('dsds22','222');
        var_dump(Log::getLogs());die;
    }

    /**
     * http://lbsyun.baidu.com/index.php?title=webapi/guide/changeposition
     * 坐标转换
     * @return mixed
     */
    public function actionGeoconv()
    {
        $post = \Yii::$app->request->post();
        $latitude = isset($post['latitude']) ? $post['latitude'] : '';
        $longitude = isset($post['longitude']) ? $post['longitude'] : '';
        if (!$latitude || !$longitude) {
            return $this->responseJson(Message::ERROR, '获取定位失败');
        }
        $ak = BAIDU_KEY_AK;
        $api = "http://api.map.baidu.com/geoconv/v1/?coords={$longitude},{$latitude}&from=1&to=5&ak={$ak}";
        $result = json_decode(sendGet($api), true);
        if($result && $result['status'] === 0){
            $data = [];
            $data['latitude'] = $result && isset($result["result"][0]["y"]) ? $result["result"][0]["y"] : '';
            $data['longitude'] = $result && isset($result["result"][0]["x"]) ? $result["result"][0]["x"] : '';
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        }
        \Yii::error($result);
        return $this->responseJson(Message::ERROR, '获取定位失败');
    }

}
