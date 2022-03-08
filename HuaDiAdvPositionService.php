<?php

/**
 * 花递广告服务
 */

namespace frontend\service;


use common\models\HuadiAdvPosition;

class HuaDiAdvPositionService
{
    const POSITION_HOME   = 1;//首页广告位
    const POSITION_GIF_FLOWER   = 2;//礼品花
    const POSITION_LIFE_FLOWER = 3;//生活花
    const POSITION_FLOWER_SHOP = 4;//花店直卖
    const POSITION_MY_INFO  = 5;//我的页面
    const POSITION_ORDER   = 6;//6全部订单
    const POSITION_CATE = 7;//分类页面

    //分类页面下的子分类
    const POSITION_TYPE_XH = 1;//1鲜花
    const POSITION_TYPE_HL = 2;//花篮
    const POSITION_TYPE_DR = 3;//绿植多肉
    const POSITION_TYPE_JJ = 4;//家居花
    const POSITION_TYPE_HC = 5;//散装花材

    private $error;

    public function getAdvListService($position, $position_type = 0, $return = array())
    {
        if ($position == self::POSITION_CATE) {
            $_adv_position = HuadiAdvPosition::find()
                ->where(['position'=>$position,'position_type'=>$position_type,'is_use'=>1])
                ->select('ap_id,color_value as background_color')
                ->asArray()
                ->one();
        } else {
            $_adv_position = HuadiAdvPosition::find()
                ->where(['position'=>$position,'is_use'=>1])
                ->select('ap_id,color_value as background_color')
                ->asArray()
                ->one();
        }
        if (!$_adv_position) {
            return $return;
        }
        //获取当前广告位下开启的广告
        $return = HuadiAdvPosition::getHdAdv($_adv_position['ap_id']);
        foreach ($return as &$value) {
            $value['background_color'] = $_adv_position['background_color'];
        }
        return $return;
    }


    public function getErr()
    {
        return $this->error;
    }

}