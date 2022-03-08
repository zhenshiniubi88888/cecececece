<?php
/**
 * 花递专题页面接口
 * User: 赵兴浪
 * Date: 2020/1/14
 * Time: 16:59
 */
namespace frontend\controllers;


use common\components\Message;
use common\models\CmsConstel;
use common\models\CmsConstelGoods;
use common\models\CmsConstelType;
use common\models\Goods;

class SpecialTopicController extends BaseController
{
    private $cache_base_name = 'SpecialTopic_';

    /**
     * 通用专题商品接口
     */
    public function actionIndex()
    {
        $topic_id = \Yii::$app->request->post('topic_id',0);
        $refresh_cache = \Yii::$app->request->post('cache_version',0);//用于刷新缓存
        $topic_id = (int)$topic_id;
        $topic_info = CmsConstel::find()->where(['id'=>$topic_id])->select("start_time,end_time")->asArray()->one();
        if(!$topic_info){
            return $this->responseJson(Message::ERROR, "不存在的专题");
        }
        if(time() < $topic_info['start_time'] || $topic_info['end_time'] < time()){
            return $this->responseJson(Message::ERROR, "专题已过期");
        }
        $topic_types = CmsConstelType::find()->where(['type_id'=>$topic_id])->select("id,title,data")->asArray()->all();
        if(!$topic_types){
            return $this->responseJson(Message::ERROR, "不存在的专题");
        }

        $cache_name = $this->cache_base_name.'index2020_'.$topic_id.json_encode(array_column($topic_types,"id")).$refresh_cache;
        $cache_data = cache($cache_name);
        if($cache_data){
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,unserialize($cache_data));
        }
        $all_goods_infos = CmsConstelGoods::find()->where(['constel_id'=>$topic_id])->select("id,goods_id,cat_id")->orderBy("id asc")->asArray()->all();
        foreach ($topic_types as &$topic_type){
            $topic_type['data'] = unserialize($topic_type['data']);
            $goods_ids = [];
            foreach ($all_goods_infos as $goods_info){
                if($goods_info['cat_id'] == $topic_type['id']){
                    $goods_ids[] = $goods_info['goods_id'];
                }
            }
            $condition['goods.goods_id'] = $goods_ids;
            $goods_model = new Goods();
            $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
            $goods_list = $goods_model->goodslist($field,1,188,$condition,'',1,[],[],[],0,320);
            //重新按照后台设置的顺序排序
            $goods_new = [];
            foreach ($goods_ids as $goods_id){
                foreach ($goods_list as $item){
                    if($goods_id == $item['goods_id']){
                        $goods_new[] = $item;
                    }
                }
            }
            $topic_type['goods'] = $goods_new;
        }
        cache($cache_name,serialize($topic_types),3600*24);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$topic_types);
    }

    /**
     * 2020年年货节
     */
    public function actionYearGoods()
    {
        $where = $one = [15192,14688,14888,15754,15756,15758];
        $two = [15185,15012,15755,15757,15759];
        $where = array_merge($where,$two);
        $three = [15626];
        $where = array_merge($where,$three);
        $four = [14701,14619,15027,14820,15078,14658,14727,14806,14714,15018,14870,14660];
        $condition['goods.goods_id'] = array_merge($where,$four);
        $goods_model = new Goods();
        $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
        $list = $goods_model->goodslist($field, 1, 30, $condition);
        $one_goods = $two_goods = $three_goods = $four_goods = [];
        foreach ($one as $o){
            foreach ($list as $v1){
                if($v1['goods_id'] == $o){
                    $v1['goods_name'] = str_replace('【年货节】','',$v1['goods_name']);
                    $one_goods[] = $v1;
                }
            }
        }
        foreach ($two as $t){
            foreach ($list as $v2){
                if($v2['goods_id'] == $t){
                    $v2['goods_name'] = str_replace('【年货节】','',$v2['goods_name']);
                    $two_goods[] = $v2;
                }
            }
        }

        foreach ($list as $v3){
            if($three[0] == $v3['goods_id']){
                $v3['goods_name'] = str_replace('【年货节】','',$v3['goods_name']);
                $three_goods = $v3;
            }
        }

        foreach ($four as $f){
            foreach ($list as $v4){
                if($v4['goods_id'] == $f){
                    $v4['goods_name'] = str_replace('【年货节】','',$v4['goods_name']);
                    $four_goods[] = $v4;
                }
            }
        }

        $data = [
            'one_goods' => $one_goods,
            'two_goods' => $two_goods,
            'three_goods' => $three_goods,
            'four_goods' => $four_goods,
        ];

        return $this->responseJson(1, "success", $data);
    }

    /**
     * 2020年专题指定包月花商品详情接口
     */
    public function actionYearLifeGoods()
    {
        return $this->responseJson(1, "success", $this->getPBundling([19,14,20,18]));
    }

    /**
     * 2020年2月专题
     */
    public function actionOne()
    {
        $pbdundling_id_str = \Yii::$app->request->post('pbdundling_ids','');
        if($pbdundling_id_str){
            $pbdundling_ids = explode(',',$pbdundling_id_str);
        }else{
            $pbdundling_ids = [11,15,12,14];
        }
        return $this->responseJson(1, "success", $this->getPBundling($pbdundling_ids));
    }

    /**
     * 获取专题包月花商品
     * @param array $bl_ids
     * @return array
     */
    private function getPBundling($bl_ids = [])
    {
        if(empty($bl_ids)){
            return [];
        }
        $PBund_model = new \common\models\PBundling();
        $where = [];
//        $where['is_ahj'] = 0;
        $where['is_delete'] = 0;
        $where['bl_state'] = 1;
        $where['bl_type'] = 2;
        $where['bl_id'] = $bl_ids;
        $bdling = $PBund_model->getBundlingInfoList($where, 1, 100, 'bl_id,bl_name,bl_sub_name,bl_discount_price');
        $goods = [];
        foreach ($where['bl_id'] as $item){
            foreach ($bdling as $value){
                if($item == $value['bl_id']){
                    $goods[] = $value;
                }
            }
        }
        return $goods;
    }

    /**
     * 生日专题换一换专用商品接口
     */
    public function actionBirthChange()
    {
        $topic_id = 188;
        $cat_id = \Yii::$app->request->post('cat_id',0);
        $page = \Yii::$app->request->post('page',0);
        $limit = \Yii::$app->request->post('limit',0);
        if(!$cat_id || !$limit || !$page){
            return $this->responseJson(Message::ERROR, "参数错误,cat_id:{$cat_id},page:{$page},limit:{$limit}");
        }
        $offset = ($page - 1) * $limit;
        $count = CmsConstelGoods::find()->where(['constel_id'=>$topic_id,'cat_id'=>$cat_id])->select("id,goods_id,cat_id")->count();
        $all_goods_infos = CmsConstelGoods::find()->where(['constel_id'=>$topic_id,'cat_id'=>$cat_id])->select("id,goods_id,cat_id")->orderBy("id asc")->offset($offset)->limit($limit)->asArray()->all();
        $goods_ids = array_column($all_goods_infos,'goods_id');
        $condition['goods.goods_id'] = $goods_ids;
        $goods_model = new Goods();
        $field = "goods.goods_id,goods.goods_name,goods.goods_material,goods.goods_image,goods.goods_addtime,goods.goods_material,goods.goods_jingle,goods.gc_id,goods_class.gc_name,goods.ahj_goods_price as goods_price,goods.goods_salenum,goods.goods_custom_salenum,(goods.goods_salenum + goods.goods_custom_salenum) as goods_salenums";
        $goods_list = $goods_model->goodslist($field,1,30,$condition,"",1,[],[],[],0,320);
        //重新按照后台设置的顺序排序
        $goods_new = [];
        foreach ($goods_ids as $goods_id){
            foreach ($goods_list as $item){
                if($goods_id == $item['goods_id']){
                    $goods_new[] = $item;
                }
            }
        }
        $data = [
            'count' => $count,
            'page'  => $page,
            'list'  => $goods_new
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }
}