<?php

namespace frontend\controllers;

use common\components\Message;
use common\models\EvaluateGoods;
use common\models\Goods;
use common\models\GoodsClass;
use common\models\Member;
use common\models\PBundling;

/**
 * CommentController
 */
class CommentController extends BaseController
{
    /**
     * 评价列表
     * @return mixed
     */
    public function actionIndex()
    {
        $post = \Yii::$app->request->post();
        $page = isset($post['page']) && intval($post['page']) > 0 ? intval($post['page']) : 1;
        $goods_id = isset($post['goods_id']) ? intval($post['goods_id']) : 0;
        $store_id = isset($post['store_id']) ? intval($post['store_id']) : 0;
        $type = isset($post['type']) ? intval($post['type']) : 1;//评价类型 1.普通商品 2花材 3.店铺花
        $goods = Goods::findOne($goods_id);
        if ($type == 1 && in_array($goods->gc_id,array(Goods::FLOWER_GIFT,Goods::FLOWER_BASKET,Goods::FLOWER_HOME,Goods::FLOWER_LVZHI))) {
            return $this->_getGoodsRandComment();
        }
        $filter = isset($post['filter']) ? trim($post['filter']) : 'all';
        if ((!$goods_id && !$store_id) && in_array($type, [1, 3])) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $pagesize = 10;
        $model_comment = new EvaluateGoods();
        $map = [];
        if ($type == 2) {
            $map['geval_type'] = 2;
        } elseif ($type == 3) {
            $map['geval_type'] = 3;
            if ($goods_id) {
                $map['geval_goodsid'] = $goods_id;
            }
            if ($store_id) {
                $map['geval_storeid'] = $store_id;
            }
        } else {
            $map['geval_type'] = 1;
            $map['geval_goodsid'] = $goods_id;
        }
        if (in_array($filter, ['perfect', 'good', 'bad'])) {
            $map['geval_level'] = str_replace(['perfect', 'good', 'bad'], [1, 2, 3], $filter);
        } elseif ($filter == 'image') {
            $map = ['and', ['<>', 'geval_image', ''], $map];
        }
        $field = 'geval_image,geval_scores_bz,geval_scores_zl,geval_scores_fw,geval_content,geval_addtime,geval_explain,geval_frommemberid';
        $comment_list = $model_comment->getEvaluateList($map, $field, 'geval_id desc', $page, $pagesize);
        $comment_data = $member_list = [];
        if ($comment_list) {
            $member_list = Member::find()->select('member_id,member_avatar,member_nickname')->where(['member_id' => array_column($comment_list, 'geval_frommemberid')])->all();
        }
        foreach ($comment_list as $comment) {
            $data = [];
            $images = [];
            if ($comment['geval_image']) {
                $img = explode(',', $comment['geval_image']);
                foreach ($img as $g) {
                    if ($g) {
                        $images[] = getImgUrl($g, ATTACH_COMMENT);
                    }
                }
            }
            $data['comment_score'] = ceil(($comment['geval_scores_bz'] + $comment['geval_scores_zl'] + $comment['geval_scores_fw']) / 3);
            $data['comment_content'] = $comment['geval_content'];
            $data['comment_time'] = getFriendlyTime($comment['geval_addtime']);
            $data['comment_reply'] = $comment['geval_explain'];
            $data['comment_images'] = $images;
            $avatar = $nickname = '';
            foreach ($member_list as $member) {
                if ($member->member_id == $comment['geval_frommemberid']) {
                    $avatar = getMemberAvatar($member->member_avatar);
                    $nickname = $member->member_nickname;
                    break;
                }
            }
            $data['member'] = [
                'id' => $comment['geval_frommemberid'],
                'avatar' => $avatar,
                'nickname' => $nickname,
            ];
            $comment_data[] = $data;
        }
        $data = [];
        $data['page'] = $page;
        $data['pagesize'] = $pagesize;
        $data['comment_list'] = $comment_data;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 评价综合分析
     */
    public function actionAnalysis()
    {
        $post = \Yii::$app->request->post();
        $goods_id = isset($post['goods_id']) ? intval($post['goods_id']) : 0;
        $store_id = isset($post['store_id']) ? intval($post['store_id']) : 0;
        $type = isset($post['type']) ? intval($post['type']) : 1;//评价类型 1.普通商品 2花材 3.店铺花
        if ((!$goods_id && !$store_id) && in_array($type, [1, 3])) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $model_comment = new EvaluateGoods();
        $map = [];
        if ($type == 2) {
            $map['geval_type'] = 2;
        } elseif ($type == 3) {
            $map['geval_type'] = 3;
            if ($goods_id) {
                $map['geval_goodsid'] = $goods_id;
            }
            if ($store_id) {
                $map['geval_storeid'] = $store_id;
            }
        } else {
            $map['geval_type'] = 1;
            $map['geval_goodsid'] = $goods_id;
        }
        $perfect = $model_comment->getEvaluateCount(['and', $map, ['geval_level' => 1]]);
        $good = $model_comment->getEvaluateCount(['and', $map, ['geval_level' => 2]]);
        $bad = $model_comment->getEvaluateCount(['and', $map, ['geval_level' => 3]]);
        $image = $model_comment->getEvaluateCount(['and', $map, ['<>', 'geval_image', '']]);
        $zl = EvaluateGoods::find()->where($map)->average('geval_scores_zl');
        $sd = EvaluateGoods::find()->where($map)->average('geval_scores_sd');
        $fw = EvaluateGoods::find()->where($map)->average('geval_scores_fw');
        $all = $perfect + $good + $bad;
        $data = [];
        $data['commentCount'] = [
            'all' => $all,
            'perfect' => (int)$perfect,
            'good' => (int)$good,
            'bad' => (int)$bad,
            'image' => (int)$image,
        ];
        $data['commentRate'] = [
            'perfect' => $all == 0 ? 100 : sprintf("%.1f", ($perfect / $all) * 100),
            'zl' => $zl == 0 ? 5 : ceil($zl),
            'sd' => $sd == 0 ? 5 : ceil($sd),
            'fw' => $fw == 0 ? 5 : ceil($fw),
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }


    /**
     * 获取商品随机评论
     * @return mixed
     */
    private function _getGoodsRandComment()
    {
        $post = \Yii::$app->request->post();
        $page = isset($post['page']) && intval($post['page']) > 0 ? intval($post['page']) : 1;
        $goods_id = isset($post['goods_id']) ? intval($post['goods_id']) : 0;
        $page_size = EvaluateGoods::PAGE_SIZE;
        $data = [];
        $data['page'] = $page;
        $data['pagesize'] = $page_size;
        $eva = new EvaluateGoods();
        $data['comment_list'] = $eva->getGoodsComment($goods_id, $page, $page_size);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
    /**
     * 获取单挑评论信息
     * @return mixed
     */
    public function actionGetOneComment(){
        $comment_id = intval(\Yii::$app->request->post('comment_id'));
        $goods_id = intval(\Yii::$app->request->post('goods_id'));
        if(!$comment_id || !$goods_id){
            return $this->responseJson(Message::ERROR,'fail','未找到评论信息!');
        }
        $eval_data = EvaluateGoods::find()
            ->alias('a')
            ->leftJoin(Member::tableName(). ' b','b.member_id = a.geval_frommemberid')
            ->where(['geval_id' => $comment_id, 'geval_goodsid' => $goods_id, 'geval_type' => 0, 'geval_is_show' => 1])
            ->select(['a.*','b.member_avatar'])->asArray()->one();
        var_dump($eval_data);exit;
    }
}
