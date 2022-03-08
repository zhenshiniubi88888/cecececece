<?php


namespace frontend\service;


use common\models\GroupShopping;
use common\models\Setting;

class GoodsService
{
    private $_HuaDiSpecialOfferGoods = array();

    private $_YearCardGoods = array();

    public function __construct()
    {
        $query = Setting::find();
        $offer = $query->where(['name' => 'huadi_special_offer'])->asArray()->one();
        $card  = $query->where(['name' => 'year_card'])->asArray()->one();
        if ($offer && $offer['value']) {
            $this->_HuaDiSpecialOfferGoods = explode(',',$offer['value']);
        }
        if ($card && $card['value']) {
            $this->_YearCardGoods = explode(',',$card['value']);
        }
    }

    /**
     * 验证是否是特价商品
     * @param $goods_id
     * @return bool
     */
    public function checkGoodsIsHuaDiSpecialOffer($goods_id)
    {
        return in_array($goods_id,$this->_HuaDiSpecialOfferGoods);
    }

    /**
     * 获取当前商品是否在拼团活动中
     * @param $goods_id
     * @return bool
     */
    public function checkGoodsIsGroupGoods($goods_id)
    {
        $key = "group_goods_". $goods_id;
        $result = cache($key);
        if ($result) {
            return $result;
        }
        $getNewGroup = GroupShopping::getNewGroup();
        if ($getNewGroup) {
            $goodsList = $getNewGroup['goods'];
            if ($goodsList) {
                $result = in_array($goods_id,array_column($goodsList,'goods_id'));
                cache($key,$result);
                return $result;
            }
            return false;
        }
        return false;
    }

    /**
     * 验证商品是否是年卡商品
     * @param $goods_id
     * @return bool
     */
    public function checkGoodsIsYearCardGoods($goods_id)
    {
        return in_array($goods_id,$this->_YearCardGoods);
    }
}