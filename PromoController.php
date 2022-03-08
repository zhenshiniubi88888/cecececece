<?php

namespace frontend\controllers;

use common\components\Char;
use common\components\Message;
use common\models\Address;
use common\models\Area;
use common\models\Crontab;
use common\models\Member;
use common\models\MemberInvite;
use common\models\Voucher;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * 促销
 * PromoController
 */
class PromoController extends BaseController
{
    public function init()
    {
        parent::init();
        $this->validLogin();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 我的邀请码
     * @return mixed
     */
    public function actionMakeInvite()
    {
        $data = [];
        $data['share_code'] = Char::Base34($this->member_id);
        $member = MemberInvite::findOne(['invite_member_id' => $this->member_id]);
        $data['invite'] = [
            'has_invited' => $member ? 1 : 0,
            'invite_code' => $member ? $member->invite_code : '',
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**+
     * 提交优惠码
     * @return mixed
     */
    public function actionInvite()
    {
        $share_code = \Yii::$app->request->post('share_code', '');
        $invite_member_id = Char::Base34ToNum(strtoupper($share_code));
        if (!$invite_member_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $member = MemberInvite::findOne(['invite_member_id' => $this->member_id]);
            if (!empty($member)) {
                throw new \Exception('您已经提交过了');
            }
            $invite_member = Member::findOne($invite_member_id);
            if (!$invite_member) {
                throw new \Exception('优惠码不存在');
            }
            //给邀请人增加花金获得数
            $huajin = INVITE_HUAJIN;
            $invite_member->member_huajin += $huajin;
            $result = $invite_member->save();
            if (!$result) {
                throw new \Exception('验证失败，请重试');
            }
            //给邀请人添加一条邀请记录
            $invite = new MemberInvite();
            $invite->member_id = $invite_member_id;
            $invite->invite_code = $share_code;
            $invite->invite_member_id = $this->member_id;
            $invite->invite_huajin = $huajin;
            $invite->invite_time = TIMESTAMP;
            $invite->invite_from = MemberInvite::INVITE_FROM_CODE;
            $invite->insert();
            if (!$result) {
                throw new \Exception('验证失败，请重试');
            }
            //给被邀请人添加一条优惠券
            $voucher = Voucher::instance()->exchangeMember(INVITE_VOUCHER_TID, $this->member_id);
            if (!$voucher) {
                //优惠券被领完了或已过期了
                throw new \Exception('验证失败，请稍后再试');
            }
            $transaction->commit();
            return $this->actionMakeInvite();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }
    }


}
