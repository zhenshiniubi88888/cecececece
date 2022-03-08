<?php

namespace frontend\controllers;

use common\components\HuawaApi;
use common\components\Message;
use common\models\Member;
use common\models\MemberComment;
use common\models\MemberNotifyStore;
use common\models\MemberPraise;
use common\models\Moments;
use common\models\NotifyRead;
use common\models\Relation;
use yii\web\HttpException;

/**
 */
class UserController extends BaseController
{
    //用户中心主页
    public function actionIndex()
    {
        $member_id = (int)\Yii::$app->request->post('user_id', 0);
        if ($member_id < 1) {
            $member_id = $this->member_id;
        }
        if ($member_id < 1) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $user = array();
        //获取用户信息
        $member_info = Member::instance()->getMemberInfoById($member_id, Member::getMemberBaseField());
        if (empty($member_info)) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $user['user_id'] = $member_info['member_id'];
        $user['user_avatar'] = getMemberAvatar($member_info['member_avatar']);
        $user['user_name'] = getMemberName($member_info);
        //获取关注信息
        $user['follow_status'] = Relation::instance()->getFollowStatus($this->member_id, $member_id);
        //获取统计信息
        $user['total_praise'] = MemberPraise::instance()->getMemberPraiseCount(['to_member_id' => $member_id, 'praise_type' => MemberPraise::TYPE_MOMENTS, 'is_delete' => 0]);
        $user['total_follow'] = Relation::instance()->getFollowCount($member_id);
        $user['total_fans'] = Relation::instance()->getFansCount($member_id);
        $user['is_self'] = $this->member_id == $member_id ? 1 : 0;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['user' => $user]);
    }

    //消息主页
    public function actionAlert()
    {
        $data = array();
        $model_read = new NotifyRead();
        //获取新增粉丝数量
        $data['new_fans'] = $model_read->getNewFansCount($this->member_id);
        //获取新增赞数量
        $data['new_praise'] = $model_read->getNewPraiseCount($this->member_id, MemberPraise::TYPE_MOMENTS);
        //新增评论数量
        $data['new_comment'] = $model_read->getNewCommentCount($this->member_id, MemberComment::TYPE_MOMENTS);
        //新增作品表彰数量
        $data['new_commend'] = $model_read->getNewCommendCount($this->member_id);
        //公告
        $data['notify_list'] = $this->getNotifyList();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取组装公告列表
     * @return array
     */
    private function getNotifyList()
    {
        $notify_list = array();
        //获取昨日最佳
        $map = array();
        $map['is_perfect'] = 1;
        $map['share_state'] = 1;
        $map['is_delete'] = 0;
        $map = ['AND', $map, ['between', 'commend_time', strtotime('yesterday'), strtotime('yesterday') + 86399]];
        $moment = Moments::instance()->getMomentOne($map, 'mid,member_id,commend_time,share_cover,share_content,share_type', 'total_score desc');
        if ($moment) {
            $member_info = Member::instance()->getMemberInfoById($moment->member_id, Member::getMemberBaseField());
            if (!empty($member_info)) {
                $last_read_time = NotifyRead::instance()->getLastTime($this->member_id, NotifyRead::TYPE_4, $moment->mid);
                array_push($notify_list, [
                    'mid' => $moment->mid,
                    'title' => sprintf('%s的作品被评选为昨日最佳', getMemberName($member_info)),
                    'timeline' => getFriendlyTime($moment->commend_time, 'm-d H:i'),
                    'share_cover' => getImgUrl($moment->share_cover, ATTACH_MOMENTS),
                    'share_content' => mobile_format($moment->share_content, true),
                    'is_new' => $last_read_time > $moment['commend_time'] ? 0 : 1,
                    'share_type' => $moment->share_type,
                ]);
            }
        }
        //有人评论
        $map = array();
        $map['comment_type'] = MemberComment::TYPE_MOMENTS;
        $map['to_member_id'] = $this->member_id;
        $map['comment_state'] = 2;
        $map['is_delete'] = 0;
        $comment = MemberComment::find()->select('object_id,count(comment_id) as total_count,add_time')->where($map)->groupBy('object_id')->orderBy('comment_id desc')->asArray()->one();
        if ($comment) {
            //查找分享信息
            $map = array();
            $map['mid'] = $comment['object_id'];
            $map['share_state'] = 1;
            $map['is_delete'] = 0;
            $moment = Moments::instance()->getMomentOne($map, 'mid,member_id,commend_time,share_cover,share_content,share_type');
            if (!empty($moment)) {
                //查找会员信息
                $member_info = Member::instance()->getMemberInfoById($moment->member_id, Member::getMemberBaseField());
                if (!empty($member_info)) {
                    $last_read_time = NotifyRead::instance()->getLastTime($this->member_id, NotifyRead::TYPE_3);
                    array_push($notify_list, [
                        'mid' => $moment->mid,
                        'title' => $comment['total_count'] == 1 ? sprintf('%s评价了你', getMemberName($member_info)) : sprintf('有%s等%s人评价了你', getMemberName($member_info), $comment['total_count']),
                        'timeline' => getFriendlyTime($comment['add_time'], 'm-d H:i'),
                        'share_cover' => getImgUrl($moment->share_cover, ATTACH_MOMENTS),
                        'share_content' => mobile_format($moment->share_content, true),
                        'is_new' => $last_read_time > $moment['add_time'] ? 0 : 1,
                        'share_type' => $moment->share_type,
                    ]);
                }
            }
        }
        //有人赞
        $map = array();
        $map['praise_type'] = MemberPraise::TYPE_MOMENTS;
        $map['to_member_id'] = $this->member_id;
        $map['is_delete'] = 0;
        $praise = MemberPraise::find()->select('object_id,count(praise_id) as total_count,add_time')->where($map)->groupBy('object_id')->orderBy('praise_id desc')->asArray()->one();
        if ($praise) {
            //查找分享信息
            $map = array();
            $map['mid'] = $praise['object_id'];
            $map['share_state'] = 1;
            $map['is_delete'] = 0;
            $moment = Moments::instance()->getMomentOne($map, 'mid,member_id,commend_time,share_cover,share_content,share_type');
            if (!empty($moment)) {
                //查找会员信息
                $member_info = Member::instance()->getMemberInfoById($moment->member_id, Member::getMemberBaseField());
                if (!empty($member_info)) {
                    $last_read_time = NotifyRead::instance()->getLastTime($this->member_id, NotifyRead::TYPE_2);
                    array_push($notify_list, [
                        'mid' => $moment->mid,
                        'title' => $praise['total_count'] == 1 ? sprintf('%s赞了你', getMemberName($member_info)) : sprintf('有%s等%s人赞了你', getMemberName($member_info), $praise['total_count']),
                        'timeline' => getFriendlyTime($praise['add_time'], 'm-d H:i'),
                        'share_cover' => getImgUrl($moment->share_cover, ATTACH_MOMENTS),
                        'share_content' => mobile_format($moment->share_content, true),
                        'is_new' => $last_read_time > $praise['add_time'] ? 0 : 1,
                        'share_type' => $moment->share_type,
                    ]);
                }
            }
        }

        //最新一个优秀作品
        $map = array();
        $map['is_perfect'] = 1;
        $map['share_state'] = 1;
        $map['is_delete'] = 0;
        $moment = Moments::instance()->getMomentOne($map, 'mid,member_id,commend_time,share_cover,share_content,share_type', 'commend_time desc');
        if ($moment) {
            $member_info = Member::instance()->getMemberInfoById($moment->member_id, Member::getMemberBaseField());
            if (!empty($member_info)) {
                $last_read_time = NotifyRead::instance()->getLastTime($this->member_id, NotifyRead::TYPE_4, $moment->mid);
                array_push($notify_list, [
                    'mid' => $moment->mid,
                    'title' => sprintf('%s的作品被评选为优秀作品', getMemberName($member_info)),
                    'timeline' => getFriendlyTime($moment->commend_time, 'm-d H:i'),
                    'share_cover' => getImgUrl($moment->share_cover, ATTACH_MOMENTS),
                    'share_content' => mobile_format($moment->share_content, true),
                    'is_new' => $last_read_time > $moment['commend_time'] ? 0 : 1,
                    'share_type' => $moment->share_type,
                ]);
            }
        }

        return $notify_list;
    }

    //获取消息数量
    public function actionAlertUnread()
    {
        $model_read = new NotifyRead();
        //获取新增粉丝数量
        $new_fans = $model_read->getNewFansCount($this->member_id);
        //获取新增赞数量
        $new_praise = $model_read->getNewPraiseCount($this->member_id, MemberPraise::TYPE_MOMENTS);
        //新增评论数量
        $new_comment = $model_read->getNewCommentCount($this->member_id, MemberComment::TYPE_MOMENTS);
        //新增作品表彰数量
        $new_commend = $model_read->getNewCommendCount($this->member_id);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, [
            'unread_count' => array_sum([$new_fans, $new_praise, $new_comment, $new_commend])
        ]);
    }

    //赞列表
    public function actionPraiseList()
    {
        $this->validLogin();
        $page = (int)\Yii::$app->request->post('page', 1);
        $data = [];
        $model_praise = new MemberPraise();
        $data['praise_list'] = $model_praise->getFriendlyPraiseList($this->member_id, MemberPraise::TYPE_MOMENTS, 0, $page);
        if ($page == 1 && $this->isLogin()) {
            //记录已读时间
            NotifyRead::instance()->writeLine($this->member_id, NotifyRead::TYPE_2);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //评论列表
    public function actionCommentList()
    {
        $this->validLogin();
        $page = (int)\Yii::$app->request->post('page', 1);
        $data = [];
        $model_comment = new MemberComment();
        $data['comment_list'] = $model_comment->getFriendlyCommentList($this->member_id, MemberComment::TYPE_MOMENTS, 0, $page);
        if ($page == 1 && $this->isLogin()) {
            //记录已读时间
            NotifyRead::instance()->writeLine($this->member_id, NotifyRead::TYPE_3);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //关注列表
    public function actionFollower()
    {
        $member_id = (int)\Yii::$app->request->post('who', 0);
        if ($member_id < 1) {
            $member_id = $this->member_id;
        }
        $page = (int)\Yii::$app->request->post('page', 1);
        return $this->_getRelation($member_id, 1, $page);
    }

    /**
     * 粉丝列表
     */
    public function actionFans()
    {
        $member_id = (int)\Yii::$app->request->post('who', 0);
        if ($member_id < 1) {
            $member_id = $this->member_id;
        }
        $page = (int)\Yii::$app->request->post('page', 1);
        return $this->_getRelation($member_id, 2, $page);
    }

    /**
     * 关注|粉丝列表
     * @param $member_id
     * @param $type
     */
    private function _getRelation($member_id, $type, $page)
    {
        $relate_list = Relation::instance()->getFriendlyList($member_id, $type, $page);
        //判断关注关系
        $user_list = Relation::instance()->getFollowStatusByList($this->member_id, $relate_list, $type);
        foreach ($user_list as &$user) {
            $user['remark'] = '';
            if ($type == 2 && $member_id == $this->member_id) {
                $user['remark'] = '关注了你';
            }
        }
        if ($page == 1 && $this->isLogin()) {
            //记录已读时间
            NotifyRead::instance()->writeLine($this->member_id, NotifyRead::TYPE_1);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['user_list' => $user_list]);
    }

    //关注|取关
    public function actionFollow()
    {
        $this->validLogin();
        $follow_member_id = (int)\Yii::$app->request->post('follow_user', 0);
        $follow_status = (int)\Yii::$app->request->post('follow_status', 0);
        if ($follow_member_id < 1) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $res = Relation::instance()->userFollow($this->member_id, $follow_member_id, $follow_status);
        if (false == $res) {
            $error = Message::getFirstMessage();
            return $this->responseJson(Message::ERROR, $error ? $error['message'] : Message::ERROR_MSG);
        }
        $follow_status = Relation::instance()->getFollowStatus($this->member_id, $follow_member_id);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['follow_status' => $follow_status]);
    }

    //作品表彰列表
    public function actionCommend()
    {
        $this->validLogin();
        $page = (int)\Yii::$app->request->post('page', 1);
        $data = [];
        $model_moments = new Moments();
        $data['commend_list'] = $model_moments->getFriendlyCommendList($this->member_id, $page);
        if ($page == 1 && $this->isLogin()) {
            //记录已读时间
            NotifyRead::instance()->writeLine($this->member_id, NotifyRead::TYPE_4);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //作品表彰详情
    public function actionCommendView()
    {
        $mid = (int)\Yii::$app->request->post('mid', 0);
        if ($mid < 1) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $data = [];
        $model_moments = new Moments();
        $data['commend_info'] = $model_moments->getFriendlyCommendInfo($this->member_id, $mid);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    //分享花生活详情
    public function actionMomentView()
    {
        $mid = (int)\Yii::$app->request->post('mid', 0);
        if ($mid < 1) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $data = [];
        $model_moments = new Moments();
        $data['moment_info'] = $model_moments->getFriendlyMomentInfo($mid,$this->member_id, $this->sessionid);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    // 花递向花店聊天，向花店-app 发送推送消息
    public function actionSendAppNotify()
    {
        $to_id = \Yii::$app->request->post('to_id', 0);
        $data = ['to_id' => $to_id, 'delivery_type' => 3];
        $result = HuawaApi::getInstance()->OC('huadi', 'index', $data);
        if ($result['status'] == 0) {
            $this->responseJson(Message::ERROR, '通知失败');
        } else {
            $this->responseJson(Message::SUCCESS, '通知成功');
        }
    }

    /**
     * 客户咨询时，商家超过5s未回复，发送短信给商家
     */
    public function actionNotifyStoreMsg()
    {
        $to_id = \Yii::$app->request->post('to_id', 0);
        $data = ['to_id' => $to_id, 'delivery_type' => 3];
        $result = HuawaApi::getInstance()->OC('huadi', 'send_mobile_msg', $data);
        if ($result['status'] == 0) {
            $this->responseJson(Message::ERROR, '短信通知失败');
        }
        $this->responseJson(Message::SUCCESS, '短信通知成功');
    }

    /**
     * 客户咨询，商家5秒未回复，每天有一次发送语音电话通知商家的机会
     */
    public function actionNotifyStoreVoice()
    {
        $to_id = \Yii::$app->request->post('to_id', 0);
        $member_id = \Yii::$app->request->post('member_id', 0);
        if($to_id == 0 || $member_id == 0){
            $this->responseJson(Message::ERROR, '用户id或者商家id错误');
        }
        $today = strtotime(date("Y-m-d",TIMESTAMP));
        $check = MemberNotifyStore::find()->where([
            'to_id' => $to_id,
            'member_id' => $member_id,
            'add_time' => $today
        ])->count();
        if($check > 0){
            $this->responseJson(Message::ERROR, '每天只能电话通知商家一次');
        }
        $data = ['to_id' => $to_id, 'delivery_type' => 3];
        $result = HuawaApi::getInstance()->OC('huadi', 'send_mobile_voice', $data);
        if ($result['status'] == 0) {
            $this->responseJson(Message::ERROR, '电话通知失败');
        }
        //添加通知记录
        $model = new MemberNotifyStore();
        $model->to_id = $to_id;
        $model->member_id = $member_id;
        $model->add_time = $today;
        $model->save();

        $this->responseJson(Message::SUCCESS, '电话通知成功');
    }
}
