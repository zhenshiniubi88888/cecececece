<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\Goods;
use common\models\Setting;

/**
 * SearchController
 */
class SearchController extends BaseController
{

    /**
     * 热门搜索
     * @return mixed
     */
    public function actionHot()
    {
        $data = [];
        $hot = [];
        array_push($hot, [
            'word' => '办公室',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 1,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        array_push($hot, [
            'word' => '茶几餐桌',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 2,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        array_push($hot, [
            'word' => '卫生间',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 3,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        array_push($hot, [
            'word' => '会议桌花',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 4,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        array_push($hot, [
            'word' => '绿植多肉',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 5,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        array_push($hot, [
            'word' => '散装花材',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 6,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        array_push($hot, [
            'word' => '包月花',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 7,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        array_push($hot, [
            'word' => '礼品花',
            'url' => '/list/家居花/' . Goods::FLOWER_HOME . '/0',
            'type'=> 8,
            'style' => [
                'color' => '#e30e27'
            ],
        ]);
        $data['hot'] = $hot;
        if(SITEID == 258){
            $huadi_keyword_system_set = Setting::C('huadi_keyword_system_set',true);
            $huadi_hotlist = [];
            if($huadi_keyword_system_set){
                $huadi_hotkeywords = Setting::C('huadi_hot_search_list',true);
                if($huadi_hotkeywords){
                    $huadi_hotlist = unserialize($huadi_hotkeywords);
                    array_multisort(array_column($huadi_hotlist,'sort'),SORT_DESC,$huadi_hotlist);
                }
            }
            $data['hot'] = $huadi_hotlist;
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

}
