<?php


namespace frontend\service;

use common\components\Log;

/**
 * 公众号数据埋点
 * Class DataBuryingPointService
 * @package frontend\service
 */


class DataBuryingPointService
{
    //数据埋点记录时间
    const START_TIME = 1588262400;//5.1

    const END_TIME   = 1589990399;//5.20
    /** 缓存KEY*/
    private $key;

    private $official_account_name;

    /** 公众号名称*/
    const AI_HUA_JU_OFFICIAL_NAME      = 'AiHuaJu';
    const HUA_DI_OFFICIAL_NAME         = 'HuaDi';
    const XIAN_HUA_SU_DI_OFFICIAL_NAME = 'XianHuaSuDi';


    public function __construct($official_account_name)
    {
        switch ($official_account_name) {
            case self::HUA_DI_OFFICIAL_NAME://花递
                $this->key = 'activity_follow_history_h_d';
                $this->official_account_name = '花递公众';
                break;
            case self::AI_HUA_JU_OFFICIAL_NAME://爱花居
                $this->key = 'activity_follow_history_a_h_j';
                $this->official_account_name = '爱花居鲜花店';
                break;
            case self::XIAN_HUA_SU_DI_OFFICIAL_NAME://鲜花速递
                $this->key = 'activity_follow_history_x_h_s_d';
                $this->official_account_name = '鲜花速递网上订花';
                break;
            default:
                $this->key = 'activity_follow_history';
        }
    }

    public function AddVisitData()
    {
        if (TIMESTAMP >= self::START_TIME && TIMESTAMP <= self::END_TIME) {//记录活动期间的公众号访问量
            $this->settingData($this->key);
            return true;
        }
        return false;
    }

    /**
     * 记录数据到文件
     * @param $key
     */
    private function settingData($key)
    {
        $followCount = cache($key);
        if ($followCount === false) {
            cache($key,1);
        } else {
            $followCount += 1;
            cache($key,$followCount);
        }
        Log::writelog($key, '当前时间:'.date("Y-m-d H:i:s").'----'.$this->official_account_name.'公众号关注量+1,当前活动期间新增关注总数:'.($followCount ? $followCount : 1));
    }
}