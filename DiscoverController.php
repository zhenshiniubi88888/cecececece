<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Message;
use common\helper\SensitiveWord;
use common\models\Adv;
use common\models\AlyContentSecurity;
use common\models\Article;
use common\models\Goods;
use common\models\GoodsClass;
use common\models\Member;
use common\models\MemberComment;
use common\models\MemberPraise;
use common\models\Moments;
use common\models\Move;
use common\models\Notify;
use common\models\Orders;
use common\models\PBundling;
use common\models\ShareHits;
use common\models\Voucher;
use common\models\VoucherTemplate;
use frontend\service\DeleteMomentsService;

/**
 * DiscoverController
 */
class DiscoverController extends BaseController
{

    public function actionIndex()
    {
        $data = [];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 感动首页
     * @return mixed
     */
    public function actionMoveIndex()
    {
        $page = Request('page',1);
        $data = [];
        //banner
        $adv_model = new Adv();
        $data['member'] = [
            'member_id' => $this->member_id,
            'member_name' => $this->member_name
        ];
        $data['top_banner'] = $adv_model->getBanner(133);
        $data['move_title'] = '真情表白·感动';

        //查询自己已点赞
        $model_share_hits = new ShareHits();
        //分享点赞为0，花艺点赞为1,感动点赞的为2
        if($this->member_id){
            $condition = [
                'type' => 2,
                'member_id'=>$this->member_id
            ];
        }else{
            $condition = [
                'type' => 2,
                'sid'=>$this->sessionid
            ];
        }

        $share_hits = $model_share_hits->getShareHitsByMemberId($condition,'share_id');
        $share_hits_order_ids = array_column($share_hits, 'share_id');
        //感动列表
        $model_orders = new Orders();
        $data['move_list'] = $model_orders->getCardOrders($share_hits_order_ids, (int)$page);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 感动列表
     * @return mixed
     */
    public function actionMoveList($page = 1)
    {
        $post = \Yii::$app->request->post();
        $page = isset($post['page']) ? intval($post['page']) : $page;
        $data = [];
        //感动列表
        $model_move = new Move();
        $data['move_list'] = $model_move->getFriendlyMoveList($page, $this->member_id, $this->sessionid);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }


    /**
     * 删除感动
     * @return mixed
     */
    public function actionMoveRemove()
    {
        $post = \Yii::$app->request->post();
        $move_id = isset($post['move_id']) ? intval($post['move_id']) : 0;
        if (!$move_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $move = Move::findOne($move_id);
        if (!$move || $move->member_id != $this->member_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG.'1');
        }
        $move->is_delete = 1;
        $move->delete_time = TIMESTAMP;
        $result = $move->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG.'2');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }


    /**
     * 删除花艺
     * @return mixed
     */
    public function actionMomentRemove()
    {
        $post = \Yii::$app->request->post();
        $moment_id = isset($post['moment_id']) ? intval($post['moment_id']) : 0;
        if (!$moment_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $moment = Moments::findOne($moment_id);
        if (!$moment || $moment->member_id != $this->member_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG.'1');
        }
        $moment->is_delete = 1;
        $moment->update_time = TIMESTAMP;
        $result = $moment->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG.'2');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 感动点赞/取消点赞
     * @return mixed
     */
    public function actionMoveLike()
    {
        $post = \Yii::$app->request->post();
        $move_id = isset($post['move_id']) ? intval($post['move_id']) : 0;
        if (!$move_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $model_praise = new ShareHits();
        $result = $model_praise->addOrCancelPraise($move_id, $this->member_id, $this->sessionid);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 感动评价
     * @return mixed
     */
    public function actionMoveComment()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $move_id = isset($post['move_id']) ? intval($post['move_id']) : 0;
        //回复评价
        $comment_id = isset($post['comment_id']) ? intval($post['comment_id']) : 0;
        //评价内容或回复内容
        $comment_content = isset($post['comment_content']) ? trim($post['comment_content']) : '';
        if (!$move_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        if (mb_strlen($comment_content, 'UTF-8') < 1 || mb_strlen($comment_content, 'UTF-8') >= 200) {
            return $this->responseJson(Message::VALID_FAIL, '回复内容1~200字以内');
        }
        $model_comment = new MemberComment();
        $comment = [];
        $comment['comment_type'] = 1;
        $comment['object_id'] = $move_id;
        $comment['comment_content'] = $comment_content;
        $comment['member_id'] = $this->member_id;
        $comment['member_name'] = $this->member_name;
        $comment['reply_comment_id'] = $comment_id;
        $result = $model_comment->addComment($comment);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_comment->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 送花指南
     */
    public function actionGuideIndex()
    {
        $post = \Yii::$app->request->post();
        $page = isset($post['page']) ? intval($post['page']) : 0;
        $limit = 10;
        $data = [];
        $model_article = new Article();
        //$data['hot_rank'] = $model_article->getHotArticleList();
        //$data['hot_rank'] = $model_article->getDiscoverNav();
        $data['top_class'] = $model_article->getBaodianNav();
        $model_article = new Article();
        if(SITEID == 258){
            if(IS_TEST){
                $ac_id = [45,46,47,48,49,50];
            }else{
//                $ac_id = [63,65,67,69,66];
                $ac_id = [71,72,73,74,75,76];
            }
        }else{
           $ac_id = 14;
        }
        $offset = 0;
        $offset = $page > 0 ? ($page - 1) * $limit : $offset;
        $data['article_list'] = $model_article->getMoreArticleList(['ac_id' => $ac_id], $offset, $limit);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 宝典列表
     */
    public function actionGuideList()
    {
        $data = [];
        $post = \Yii::$app->request->post();
        $page = isset($post['page']) ? intval($post['page']) : 0;
        $offset = 0;
        $allow_ac = Article::instance()->allow_ac_id;
        $ac_id = isset($post['ac_id']) ? intval($post['ac_id']) : $allow_ac;//默认送花技巧
        if (!is_array($ac_id) && !in_array($ac_id, $allow_ac)) {
            $data['article_list'] = [];
        } else {
            $limit = 15;
            $offset = $page > 0 ? ($page - 1) * $limit : $offset;
            $model_article = new Article();
            $data['article_list'] = $model_article->getMoreArticleList(['ac_id' => $ac_id], $offset, $limit);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 文章详情
     */
    public function actionGuideDetail()
    {
        $post = \Yii::$app->request->post();
        $article_id = isset($post['article_id']) ? intval($post['article_id']) : 0;
        if (!$article_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $model_article = new Article();
        $field = "article_id,article_face,article_title,article_content,article_time,view_count,custom_view_count";
        $article_info = $model_article->getArticleById($article_id, $field);
        if (!$article_info) {
            return $this->responseJson(Message::EMPTY_CODE, '文章不存在或已删除');
        }
        //$article_info['article_face'] = getImgUrl($article_info['article_face'], ATTACH_ARTICLE);
        $article_info['article_face'] = $model_article->getArticleFace($article_info['article_content'], false);
        $article_info['article_time'] = getFriendlyTime($article_info['article_time'], 'Y-m-d');

        //更新阅读量
        $model_article->updateViewCount($article_info['article_id']);

        $data = [];
        $data['article_info'] = $article_info;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //创意礼物
    public function actionInspirePresent()
    {
        $page = \Yii::$app->request->post('page', 1);
        $model_goods = new Goods();
        $condition = [];
        $condition['gc_id'] = Goods::FLOWER_PRESENT;
        $condition['goods_state'] = 1;
        $field = 'goods_id,goods_image,goods_name,goods_material,goods_jingle,ahj_goods_price as goods_price,goods_costprice,goods_tag,sort_order';
        $goods_list = $model_goods->getGoodsList($condition, null, $field, 'sort_order desc,goods_salenum desc,goods_custom_salenum desc,goods_id desc', 10, ($page - 1) * 10);
        $goods_data = [];
        foreach ($goods_list as $key => $goods) {
            $data = [];
            $data['goods_id'] = $goods['goods_id'];
            $data['goods_type'] = GOODS_TYPE_FLOWER;
            $data['goods_image'] = thumbGoods($goods['goods_image'], 360);
            $data['goods_name'] = $goods['goods_name'] . ' ' . $goods['goods_jingle'];
            //$data['goods_jingle'] = $goods['goods_jingle'];
            //$data['goods_material'] = $goods['goods_material'];
            $data['goods_price'] = FinalPrice::S($goods['goods_price']);
            $data['goods_tag'] = $goods['goods_tag'] ? sprintf('#%s#', $goods['goods_tag']) : '';
            $data['praise_count'] = MemberPraise::getGoodsPraise($goods['goods_id'], $goods['sort_order']);
            $data['is_praised'] = 0;
            $goods_data[] = $data;
        }
        $data = [];
        $data['page'] = $page;
        $data['present_list'] = $goods_data;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 商品点赞/取消点赞
     * @return mixed
     */
    public function actionGoodsLike()
    {
        $post = \Yii::$app->request->post();
        $goods_id = isset($post['goods_id']) ? intval($post['goods_id']) : 0;
        if (!$goods_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }

        //添加点赞记录
        $model_praise = new MemberPraise();
        $result = $model_praise->addOrCancelPraise($model_praise::TYPE_GOODS, $goods_id, $this->member_id, $this->member_name, $this->sessionid);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }

        //更新商品缓存点赞数
        MemberPraise::updateGoodsPraise($goods_id, $result);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 分享花艺首页
     * @return mixed
     */
    public function actionMomentsIndex()
    {
        $user_id = (int)\Yii::$app->request->post('user', 0);
        $data = [];
        //banner
        $adv_model = new Adv();
        $data['member'] = [
            'member_id' => $this->member_id,
            'member_name' => $this->member_name
        ];
        //$data['top_banner'] = $adv_model->getBanner(148);
        //分享列表
        $model_moments = new Moments();
        $data['moments_list'] = $model_moments->getFriendlyMomentsList($user_id, 1, $this->member_id, $this->sessionid);
//        if(!empty($data['moments_list'])){
//            $data['hot_moment'] = mb_substr($data['moments_list'][0]['share_content'], 0, 2);
//        }else{
//            $data['hot_moment'] = '';
//        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 分享花艺发布
     */
    public function actionMomentsPush()
    {
        //需要登录
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $moments = new Moments();
        $result = $moments->pushOne($post, $this->member_info);
        if (!$result) {
            return $this->responseJson(4, $moments->getFirstError(Message::MODEL_ERROR));
        }
        cache(Moments::$historyKey,"");
//        if(SITEID == 258){
//            //领取    每日限领一张
//            $date = date('Ymd');
//            $key = "huayi_voucher_{$date}_{$this->member_id}";
//            if(cache($key)){
//                return $this->responseJson(3, '每日限领一张此优惠券');
//            }
//            $member = Member::findOne($this->member_id);
//            //添加一条优惠券记录
//            $voucher = Voucher::instance()->exchangeVoucher(HUADI_HUAYI_VOUCHER_ID, $member,"成功发布花艺");
//            cache($key,'1');
//            if(!$voucher){
//                return $this->responseJson(2, '发布成功，领取优惠券失败');
//            }
//        }
//        return $this->responseJson(2, '发布成功，平台审核通过后自动显示');
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 分享花艺列表
     */
    public function actionMomentsList($page = 1)
    {
        $page = (int)\Yii::$app->request->post('page', $page);
        $user_id = (int)\Yii::$app->request->post('user', 0);
        $keyword = \Yii::$app->request->post('keyword', '');
        $is_self = (int)\Yii::$app->request->post('is_self', 0);
        if($is_self){
            $user_id = $this->member_id;
        }
        $data = [];
        //分享列表
        $model_moments = new Moments();
        $data['moments_list'] = $model_moments->getFriendlyMomentsList($user_id, $page, $this->member_id, $this->sessionid, $keyword);
        //获取未读动态消息数量
        $data['unread_num'] = Notify::unDynamicReadNum($this->member_id);
        $data['hot_search'] = $keyword ? $keyword : '花递';
        //将当前用户加入到已读列表中
        $readList = cache(Moments::$historyKey);
        if (!$readList) {
            cache(Moments::$historyKey,$this->member_id ? $this->member_id : $this->sessionid);
        } else {
            $listArray = explode(',',$readList);
            array_push($listArray,$this->member_id ? $this->member_id : $this->sessionid);
            cache(Moments::$historyKey,implode(',',$listArray));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);

    }

    /**
     * 分享花艺列表-我赞过
     */
    public function actionMomentsPraiseList($page = 1)
//    public function actionMomentsList($page = 1)
    {
        $page = (int)\Yii::$app->request->post('page', $page);
        $data = [];
        //分享列表
        $model_moments = new Moments();
        $model_praise = new MemberPraise();
        $map = [];
        $map['is_delete'] = 0;
        $map['praise_type'] = $model_praise::TYPE_MOMENTS;
        if($this->member_id>0){
            $map['member_id'] = $this->member_id;
        }elseif ($this->sessionid !=''){
            $map['ssid'] = $this->sessionid;
        }else{
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }
        $praise_list = $model_praise->getPraiseList($map,'object_id');
        $moment_ids = array_column($praise_list,'object_id');
        if(!empty($moment_ids)){
            $data['moments_list'] = $model_moments->getMypraiseMomentsList($moment_ids, $page,$this->member_id,$this->sessionid);
        }else{
            $data = [];
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
    /**
     * 分享花艺点赞
     */
    public function actionMomentsPraise()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $mid = isset($post['mid']) ? intval($post['mid']) : 0;
        if (!$mid) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }

        //添加点赞记录
        $model_praise = new MemberPraise();
        $result = $model_praise->addOrCancelPraise($model_praise::TYPE_MOMENTS, $mid, $this->member_id, $this->member_name, $this->sessionid);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 分享花艺评论发布
     */
    public function actionMomentsComment()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $mid = isset($post['mid']) ? intval($post['mid']) : 0;
        //回复评价
        $comment_id = isset($post['comment_id']) ? intval($post['comment_id']) : 0;
        //评价内容或回复内容
        $comment_content = isset($post['comment_content']) ? trim($post['comment_content']) : '';
        if (!$mid) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        if (mb_strlen($comment_content, 'UTF-8') < 1 || mb_strlen($comment_content, 'UTF-8') >= 200) {
            return $this->responseJson(Message::VALID_FAIL, '回复内容1~200字以内');
        }
        if (preg_match('/[\xf0-\xf7].{3}/', $comment_content)) {
            return $this->responseJson(Message::VALID_FAIL, '回复内容暂不支持表情');
        }
        //验证内容
        $aly = new AlyContentSecurity();
        $result = $aly->detectionText($comment_content);
        $result = json_decode($result, true);
        if($result['code'] != 200){
            return $this->responseJson(Message::ERROR, $result['msg']);
        }
        $res = SensitiveWord::detectSensitiveWord($comment_content);
        if($res){
            return $this->responseJson(Message::ERROR, '检测到敏感词汇: ' . $res);
        }
        $model_comment = new MemberComment();
        $comment = [];
        $comment['comment_type'] = $model_comment::TYPE_MOMENTS;
        $comment['object_id'] = $mid;
        $comment['comment_content'] = $comment_content;
        $comment['member_id'] = $this->member_id;
        $comment['member_name'] = getMemberName($this->member_info);
        $comment['reply_comment_id'] = $comment_id;
        $result = $model_comment->addComment($comment);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_comment->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 分享花艺评论列表
     */
    public function actionMomentsCommentList()
    {
        $post = \Yii::$app->request->post();
        $mid = isset($post['mid']) ? intval($post['mid']) : 0;
        $page = isset($post['page']) ? intval($post['page']) : 1;
        $page_size = Request('page_size',10);
        if (!$mid) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $data = array();
        $data['comment_list'] = MemberComment::instance()->getTreeCommentList(MemberComment::TYPE_MOMENTS, $mid, $page,$page_size,true,$this->member_id,$this->sessionid);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 分享花艺评论点赞
     */
    public function actionMomentCommentPraise()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $comment_id = isset($post['comment_id']) ? intval($post['comment_id']) : 0;
        if (!$comment_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }

        //添加点赞记录
        $model_praise = new MemberPraise();
        $result = $model_praise->addOrCancelPraise($model_praise::TYPE_TEACH_COMMENT, $comment_id, $this->member_id, $this->member_name, $this->sessionid);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 花艺详情/增加花艺阅读量 1/次
     * @return mixed
     */
    public function actionFetchMomentsDetails()
    {
        $mid = (int)Request('mid',0);
        if (!$mid) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $moment = Moments::getDb()->cache(function ($db) use (&$mid) {
            return Moments::findOne($mid)->getAttributes(array('mid','member_id','share_type','share_content','share_cover','share_attach','goods_material','praise_num','custom_praise_num','view_num','add_time'));
        });
        if (!$moment) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        //获取用户头像 昵称
        $memberInfo = Member::getDb()->cache(function () use (&$moment) {
            return Member::findOne($moment['member_id'])->getAttributes(array('member_nickname','member_avatar'));
        });
        if (!$memberInfo) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        //组装数据
        $moment['share_cover']       = getImgUrl($moment['share_cover'],ATTACH_MOMENTS);
        $moment['add_time']          = getFriendlyTime($moment['add_time'], 'm-d H:i');
        $moment['member_name']       = $memberInfo['member_nickname'];
        $moment['member_avatar']     = getMemberAvatar($memberInfo['member_avatar']);

        $moment['share_attach']      = json_decode($moment['share_attach'],true);
        $share_cover_info = @getimagesize($moment['share_cover']);
        $moment['share_cover_width'] = isset($share_cover_info[0]) ? $share_cover_info[0] : '258';
        $moment['share_cover_heigh'] = isset($share_cover_info[1]) ? $share_cover_info[1] : '258';
        $moment['comment_count'] = MemberComment::instance()->getCommentCount(3,$mid);
        if ($moment['share_type'] == 'image') {
            $moment['share_images']  = array_map(function ($val) {
                return getImgUrl($val, ATTACH_MOMENTS);
            }, $moment['share_attach']['images']);
        }elseif ($moment['share_type'] == 'video'){
            if (is_array($moment['share_attach']['video'])) {
                $moment['share_video'] = array_map(function ($val) {
                    return getImgUrl($val, ATTACH_MEDIA);
                }, $moment['share_attach']['video']);
            } else {
                $moment['share_video'] = getImgUrl($moment['share_attach']['video'],ATTACH_MEDIA);
            }
        }
        unset($moment['share_attach']);

        //当前用户是否已经阅读过
        $key = $mid . "-" . $this->sessionid . "-" . $this->member_id ."-ReadMoment";
        if (!cache($key)) {
            (new Moments())->updateViewNum(Moments::findOne($mid));
            cache($key,1);
        }
        //获取当前用户是否点赞
        $praise_model = new MemberPraise();
        $moment['is_praised']   = $praise_model->isPraised(3,$mid,$this->member_id,$this->sessionid);
        $moment['praise_count'] = $moment['praise_num'] + $moment['custom_praise_num'];
        //$moment['praise_count'] = $praise_model->getPraiseCount(3,$mid);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $moment);
    }

    /**
     * 删除我的花艺内容
     * @return mixed
     */
    public function actionDeleteMoment()
    {
        $mid = (int)Request('mid',0);
        if (!$mid || !is_int($mid)) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        $instance = DeleteMomentsService::getInstance($mid,$this->member_id);
        $result   = $instance->delete();
        if (!$result) {
            return $this->responseJson(Message::MATCH_FAIL, Message::MATCH_FAIL_MSG);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
    }

    /**
     * 花艺评论1.1
     */
    public function actionMomentNewComment()
    {
        $post = \Yii::$app->request->post();
        $mid  = isset($post['mid']) ? intval($post['mid']) : 0;
        $page = isset($post['page']) ? intval($post['page']) : 1;
        $page_size = Request('page_size',50);
        $data = array();
        if (!$mid) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
       $data['comment_lists'] = MemberComment::instance()->getTopTreeCommentList(MemberComment::TYPE_MOMENTS, $mid, $page,$page_size,$this->member_id,$this->sessionid);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }
}
