<?php


namespace frontend\service;


use common\models\Setting;

class SpecialOfferCommendService
{
    /**
     * 特价文字内容
     * @return array
     */
    public static function wordContent()
    {
        $result = Setting::find()->where(array('name'=>"huadi_app_word_content"))->asArray()->one();
        if ($result) {
            $result['value'] = unserialize($result['value']);
            if (is_array($result['value'])) {
                return $result['value'];
            }
        }
        return [
            'title'     => '今日特价',
            'explosion' => [
                'title' => '爆款推荐',
                'subheading' => '省钱省心限时购'
            ],
            'gift_flower' => [
                'title'   => '礼品花',
                'subheading' => '精选爆卖款 补贴抄底价'
            ],
            'live_flower'    => [
                'title'      => '生活花',
                'subheading' => '精选爆卖款 补贴抄底价'
            ],
            'basket_flower'  => [
                'title'      => '花篮',
                'subheading' => '精选爆卖款 补贴抄底价'
            ],
            'green_plant'    => [
                'title'      => '绿植',
                'subheading' => '精选爆卖款 补贴抄底价'
            ],
        ];
    }
}