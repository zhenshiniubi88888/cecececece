<?php


namespace frontend\service;


use common\models\Data;
use common\models\GoodsClass;

class MiniService
{
    /**
     * 小程序通用版本首页V1
     * @return array
     */
    public static function homeV1()
    {
        $data = array();

        //顶部BANNER
        $data['banner'] = array(
            array(
                'adv_pic' => 'http://i.ahj.cm/shop/textures/06099519996359553.jpg',
                'adv_url' => '/pages/goods-detail?id=13894&type=1',
            )
        );

        //顶部广告词
        $data['jingle'] = array(
            '• 超模冠军代言',
            '• 2小时送花',
            '• 1160家品牌连锁店',
            '• 超级女声赞助商',
        );

        //搜索广告词
        $data['search_placeholder'] = '输入商品名称';

        //导航
        $data_model = new \common\models\Data();
        $data['nav'] = $data_model->getMiniNav();

        //属性
        $class_model = new GoodsClass();
        $data['attr'] = $class_model->getMiniAttr();

        //中部banner
        $data['mid_banner'] = array(
            array(
                'adv_pic' => 'https://ahj.cm/data/upload/shop/textures/06259330640409650.jpg',
                'adv_url' => '/pages/goods-detail?id=12453&type=1',
            )
        );
        //底部鲜花资讯
        $_cache_name_tj = 'wap_tj_article';
        $results = cache($_cache_name_tj);
        if (empty($results)) {
            $url = "https://www.aihuaju.com/huahua/index.php?m=content&c=index&a=ahj_index_article";
            $results = file_get_contents($url);
            $results = json_decode(trim($results), true);
            cache($_cache_name_tj, $results, 7200);
        }
        $data['flower_news'] = $results;
        return $data;
    }


    /**
     * 更多鲜花资讯
     * @return mixed
     */
    public static function articleMore($curpage){
        $_cache_name_tj_more = 'wap_tj_article_more_'.$curpage;
        $results = cache($_cache_name_tj_more);
        if (empty($results)) {
            $url = 'https://www.aihuaju.com/huahua/index.php?m=content&c=index&a=get_article_list&catid=72&cur_page=' . ($curpage - 1) . '&r=' . TIMESTAMP;
            $results = file_get_contents($url);
            $results = json_decode(trim($results), true);
            cache($_cache_name_tj_more, $results, 7200);
        }
        $data['flower_news_more'] = $results;
        return $data;
    }


    /**
     * 鲜花资讯详情
     * @param $id
     * @return mixed
     */
    public static function articleInfo($id){
        $_cache_name_tj_info = 'wap_tj_article_info_'.$id;
        $results = cache($_cache_name_tj_info);
        if (empty($results)) {
            $url = "https://www.aihuaju.com/huahua/index.php?m=content&c=index&a=get_article_info&id=".$id;
            $results = file_get_contents($url);
            $results = json_decode(trim($results), true);
            cache($_cache_name_tj_info, $results, 3600);
        }
        $data['flower_news_info'] = $results;
        return $data;
    }

    /**
     * 小程序通用版本分类V1
     * @return array
     */
    public static function categoryV1()
    {
        $data = array();

        //获取所有分类
        $goods_class_model = new \common\models\GoodsClass();
        $data['catlist'] = $goods_class_model->getMiniCats();

        return $data;
    }
}