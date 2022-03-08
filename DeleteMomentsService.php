<?php


namespace frontend\service;


use common\components\Message;
use common\models\MemberComment;
use common\models\MemberPraise;
use common\models\Moments;

class DeleteMomentsService
{
    /*操作的对象ID*/
    private $mid;
    /*当前操作人ID*/
    private $memberId;

    /*错误信息*/
    private $err;

    static private $instance;

    private function __construct($objectId, $memberId)
    {
        $this->mid      = $objectId;
        $this->memberId = $memberId;
    }

    private function __clone(){}

    static public function getInstance($objectId, $memberId)
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($objectId, $memberId);
        }
        return self::$instance;
    }

    public function delete()
    {
        //查询花艺是否存在
        $moment = Moments::findOne($this->mid);
        if (!$moment || $this->memberId != $moment->member_id) {
            $this->err = "您不能对当前数据进行操作";
            return false;
        }
        $comment = MemberComment::find()->where([
            'object_id' => $this->mid,
            'comment_type' => MemberComment::TYPE_MOMENTS
        ])->select('comment_id')->asArray()->all();
        if ($comment) {
            MemberComment::deleteAll(['comment_id'=>array_column($comment,'comment_id')]);
        }
        $praise = MemberPraise::find()->where([
            'praise_type'=>MemberPraise::TYPE_MOMENTS,'object_id' => $this->mid
        ])->select('praise_id')->asArray()->all();
        if ($praise) {
            MemberPraise::deleteAll(['praise_id'=>array_column($praise,'praise_id')]);
        }
        Moments::deleteAll(['mid'=>$this->mid]);
        return true;
    }

    public function error()
    {
        return $this->err;
    }



}