<?php


namespace frontend\service;


use common\models\Adv;

class HomeImageServices
{
    public static function getImgUri()
    {
        if (TIMESTAMP <= 1592496000) {
            //显示618图片
            return AHJ_SITE_URL . '/h5/huadi_mp_img/618_adv.gif';
        } else {
            return AHJ_SITE_URL . '/h5/huadi_mp_img/home_welfare.png';
        }
    }

    /**
     * @return array|bool|\yii\db\ActiveRecord|null
     * 获取花递中部广告banner
     */
    public static function getHuaDiCenterBanner()
    {
        $advModel = new Adv();
        $adv = $advModel->getAdvById(1576);
        return $adv;
    }
}