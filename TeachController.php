<?php

namespace frontend\controllers;

use common\models\Member;
use common\models\MemberComment;
use common\models\MemberPraise;
use common\models\Video;
use Yii;
use common\models\Course;
use common\components\Message;
use yii\web\HttpException;

/**
 * TeachController
 */
class TeachController extends BaseController
{
    /**
     * 天天学花艺首页
     * @return mixed
     */
    public function actionIndex()
    {
        $data = [];
        $adv_model = new \common\models\Adv();
        //顶部banner
        $data['top_banner'] = $adv_model->getBanner(158);

        $data['title'] = '精品课程';
        $data['subtitle'] = '从入门到精通';

        //精品课程
        $data['course_list'] = $this->actionCourseList();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 天天学花艺精选课程
     * @return mixed
     */
    public function actionCourse()
    {
        $page = \Yii::$app->request->post('page', 1);
        $data = [];
        //精品推荐
        $data['course_list'] = $this->actionCourseList($page);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 天天学花艺课程详情
     * @return mixed
     */
    public function actionCourseDetail()
    {
        $course_id = \Yii::$app->request->post('course_id', 0);
        if ($course_id < 1) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        //获取课程详情
        $course = Course::findOne(['course_id' => $course_id, 'is_delete' => 0]);
        if (empty($course)) {
            return $this->responseJson(Message::ERROR, '课程不存在或已删除');
        }

        $course_data = array();
        $course_data['course_id'] = $course->course_id;
        $course_data['course_title'] = $course->course_title;
        $course_data['course_face'] = getImgUrl($course->course_face, 'shop/teach', false);
        $course_data['course_desc'] = $course->course_desc;
        $course_data['course_brief'] = $course->course_brief;
        $course_data['course_price'] = $course->course_price > 0 ? $course->course_price : '免费';
        $course_data['view_count'] = sprintf("%s人学习", $course->view_count + $course->custom_count);
        $course_data['teacher_name'] = $course->teacher_name;
        //获取视频详情
        $video = Video::findOne(['v_id' => $course->video_id, 'is_delete' => 0]);
        $course_data['video_source'] = LOCAL_SITE_URL . DS . 'video/teach' . DS . $video->v_url;
        $data = [];
        //精品推荐
        $data['course'] = $course_data;
        //获取评论信息
        $data['comment_count'] = MemberComment::instance()->getCommentCount(MemberComment::TYPE_TEACH, $course->course_id);
        //获取点赞信息
        $data['praise_count'] = MemberPraise::instance()->getPraiseCount(MemberPraise::TYPE_TEACH, $course->course_id);
        //是否已点赞
        $data['is_praised'] = MemberPraise::instance()->isPraised(MemberPraise::TYPE_TEACH, $course->course_id, $this->member_id, $this->sessionid);

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取视频源 通过重写规则video/<source-id>
     */
    public function actionCourseSource()
    {
        $source_id = \Yii::$app->request->get('source-id');
        $video_id = Yii::$app->getSecurity()->decryptByPassword($source_id, SECURITY_KEY);
        if ($video_id < 1) {
            throw new HttpException(403);
        }
        //获取视频详情
        $video = Video::findOne(['v_id' => $video_id, 'is_delete' => 0]);
        if (empty($video)) {
            throw new HttpException(403);
        }
        $source_url = LOCAL_SITE_URL . DS . 'video' . $video->v_url;
        return $this->redirect($source_url);
    }


    /**
     * 天天学花艺精选课程列表
     * @return mixed
     */
    private function actionCourseList($page = 1)
    {
        $pagesize = 10;
        $course_list = Course::find()
            ->where([
                'is_delete' => 0,
                'course_show' => 1
            ])
            ->orderBy('course_sort desc,course_id desc')
            ->offset(($page - 1) * $pagesize)
            ->limit($pagesize)
            ->asArray()
            ->all();
        $course_data = array();
        foreach ($course_list as $course) {
            array_push($course_data, [
                'course_id' => $course['course_id'],
                'course_title' => $course['course_title'],
                'course_face' => getImgUrl($course['course_face'], 'shop/teach', false),
                'course_price' => $course['course_price'] > 0 ? $course['course_price'] : '免费',
                'course_desc' => $course['course_desc'],
                'course_tag' => $course['course_tag'],
                'view_count' => sprintf("%s人学习", $course['view_count'] + $course['custom_count']),
                'teacher_name' => $course['teacher_name'],
            ]);
        }
        return $course_data;
    }

    /**
     * 课程点赞
     * @return mixed
     */
    public function actionCoursePraise()
    {
        $post = \Yii::$app->request->post();
        $course_id = isset($post['course_id']) ? intval($post['course_id']) : 0;
        if (!$course_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }

        //添加点赞记录
        $model_praise = new MemberPraise();
        $result = $model_praise->addOrCancelPraise($model_praise::TYPE_TEACH, $course_id, $this->member_id, $this->member_name, $this->sessionid);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 评论点赞
     * @return mixed
     */
    public function actionCommentPraise()
    {
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
     * 课程评价
     * @return mixed
     */
    public function actionComment()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $course_id = isset($post['course_id']) ? intval($post['course_id']) : 0;
        //回复评价
        $comment_id = isset($post['comment_id']) ? intval($post['comment_id']) : 0;
        //评价内容或回复内容
        $comment_content = isset($post['comment_content']) ? trim($post['comment_content']) : '';
        if (!$course_id) {
            return $this->responseJson(Message::REQUIRE_PARAMETER_CODE, Message::REQUIRE_PARAMETER_MSG);
        }
        if (mb_strlen($comment_content, 'UTF-8') < 1 || mb_strlen($comment_content, 'UTF-8') >= 200) {
            return $this->responseJson(Message::VALID_FAIL, '回复内容1~200字以内');
        }
        if (preg_match('/[\xf0-\xf7].{3}/', $comment_content)) {
            return $this->responseJson(Message::VALID_FAIL, '回复内容暂不支持表情');
        }
        $model_comment = new MemberComment();
        $comment = [];
        $comment['comment_type'] = $model_comment::TYPE_TEACH;
        $comment['object_id'] = $course_id;
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
     * 获取评论列表
     * @return mixed
     */
    public function actionCourseComment()
    {
        $course_id = \Yii::$app->request->post('course_id', 0);
        $page = \Yii::$app->request->post('page', 1);
        if (!$course_id) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        $comment_list = MemberComment::instance()->getTreeCommentList(MemberComment::TYPE_TEACH, $course_id, $page, 10, true, $this->member_id, $this->sessionid);
        $data = array();
        $data['comment_list'] = $comment_list;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }

    /**
     * 查看全部回复
     * @return mixed
     */
    public function actionCommentMore()
    {
        $course_id = \Yii::$app->request->post('course_id', 0);
        $comment_id = \Yii::$app->request->post('comment_id', 0);
        if (!$comment_id) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        $comment_list = MemberComment::instance()->getReplyCommentList(MemberComment::TYPE_TEACH, $course_id,$comment_id);
        $data = array();
        $data['comment_list'] = $comment_list;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }

}
