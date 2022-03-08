<?php


namespace frontend\service;


use linslin\yii2\curl\Curl;

class StoreService
{
    /**
     * 获取店铺的评分
     * @param $store_id
     * @return int|mixed
     */
    public function fetchStoreStar($store_id)
    {
        $store_stare_key = "store_stare_" . $store_id;
        $store_stare     = cache($store_stare_key);
        if ($store_stare) {
            return $store_stare;
        }
        $storeInfo = $this->fetchStoreInfo($store_id);
        if ($storeInfo) {
            $store_stare = $storeInfo['store_star'];
            cache($store_stare_key,$store_stare);
        }
        return $store_stare ? $store_stare : 0;
    }

    /**
     * 获取店铺评分
     * @param $store_id
     * @return array|mixed
     */
    private function fetchStoreInfo($store_id)
    {
        $curl = new Curl();
        $param = [
            "store_id" => $store_id,
        ];
        $url = MICRO_DOMAIN . "/api/storeinfo";
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            http_build_query($param)
        )->post($url);
        $response = json_decode($response, true);
        if ($response["status"]) {
            return $info = json_decode($response["data"], true);
        }
        return [];
    }
}