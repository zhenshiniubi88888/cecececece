<?php


namespace frontend\service;


use common\models\Goods;
use common\models\GoodsClass;
use common\models\Member;
use common\models\Orders;
use common\models\Voucher;
use common\models\VoucherRestrict;
use common\models\VoucherTemplate;

class VoucherService
{
    /*代金券归属品牌*/
    const VOUCHER_TYPE = 4;
    /*花类型*/
    const VOUCHER_TYPE_BY = 1;//包月
    const VOUCHER_TYPE_LP = 2;//礼品
    const VOUCHER_TYPE_SH = 3;//生活
    const VOUCHER_TYPE_QT = 4;//其他

    const TYPE_NUM_ZERO  = 0;//缺少参数
    const TYPE_NUM_ONE   = 1;//领取失败
    const TYPE_NUM_TWO   = 2;//未绑定手机号
    const TYPE_NUM_THREE = 3;//已经是老用户
    const TYPE_NUM_FOUR  = 4;//订单状态满足
    const TYPE_NUM_FIVE  = 5;//当前用户已经领取
    const TYPE_NUM_SIX   = 6;//领取成功

    /*新人领取代金券模板ID*/
    //1985：全场无门槛（花店直卖的商品也可用）
    //1983：花递自营-蛋糕巧克力
    //1982：花递自营-创意礼品
    //1981：花递自营-包月花
    //1980：花递自营-生活花
    const NEW_VOUCHER_IDS = array(1985,1983,1982,1981,1980);//todo 这是线上配置
//    const NEW_VOUCHER_IDS = array(1937,1938,1939,1940,1941);//这是测试服配置

    /*新人的注册时间*/
    const START_TIME       = 1587744000; // 2020-04-25 00:00:00
    /*已完成的订单*/
    const SUCCESS_ORDER    = 2;

    /*是否已经领取*/
    private $type = true;

    /*错误信息*/
    private $error;

    private $code = self::TYPE_NUM_ZERO;

    const USER_TYPE_ERROR_CODE = 0; //不可以领取

    const USER_TYPE_SUCCESS_CODE = 1;//可以领取



    /**
     * 获取新人100代金券模板
     * @param int $member_id
     * @return array
     * @throws \Exception
     */
    public static function fetchVoucherTmp($member_id = 0)
    {
         $voucher_templates = [];
        if (!empty(self::NEW_VOUCHER_IDS)) {
            foreach (self::NEW_VOUCHER_IDS as $id) {
                $template = VoucherTemplate::instance()->getOneActiveTemplate($id);
                //查询分类规则
                if ($template) {
                    $template_resrict = VoucherRestrict::find()->where(['voucher_t_id' => $id, 'restrict_type' => 2])->select(['content'])->scalar();
                    $voucher_template['voucher_match']  = $template['voucher_t_limit'] > 0 ? sprintf('满%s使用', (int)$template['voucher_t_limit']) : '无门槛';
                    $voucher_template['voucher_t_limit'] = $template['voucher_t_limit'];
                    $voucher_template['voucher_t_price'] = $template['voucher_t_price'];
                    $voucher_template['is_get_voucher'] = Voucher::instance()->checkPicked($id, $member_id);       //验证是否已领取
                    switch ($template['voucher_url_type']) {
                        case self::VOUCHER_TYPE_BY:
                            $limit_text = '包月花';
                            $bg_color = '#ffa304';
                            $font_color = '#fff7bb';

                            break;
                        case self::VOUCHER_TYPE_LP:
                            $limit_text = '礼品花';
                            $bg_color = '#ffa304';
                            $font_color = '#fff7bb';
                            break;
                        case self::VOUCHER_TYPE_SH:
                            $limit_text = '生活花';
                            $bg_color = '#ffa304';
                            $font_color = '#fff7bb';
                            break;
                        case self::VOUCHER_TYPE_QT:
                            $limit_text = '全场';
                            $bg_color = '#fedd33';
                            $font_color = '#df7700';
                            break;
                        default:
                            $limit_text = '全场';
                            $bg_color = '#fedd33';
                            $font_color = '#df7700';
                    }
                    if($template_resrict) {
                        $gc_names = GoodsClass::find()->where(['gc_id' => explode(',', $template_resrict)])
                            ->select(['group_concat(gc_name) gc_names'])
                            ->asArray()->scalar();
                        if($gc_names){
                            $limit_text = $gc_names;
                            $bg_color = '#f75c20';
                            $font_color = '#ffe4d3';
                        }
                    }
                    $voucher_template['voucher_limit_text'] = $limit_text;
                    $voucher_template['font_color'] = $font_color;
                    $voucher_template['bg_color'] = $bg_color;
                    $voucher_template['effect_date'] = '优惠券领取后3个月内有效';//暂时写死
                    //$voucher_template['voucher_limit_type'] = $template['voucher_url_type'];
                    $voucher_templates[] = $voucher_template;
                }
            }
        }
        return $voucher_templates;
    }

    /**
     * 获取用户信息
     * @param $memberId
     * @return array|\yii\db\ActiveRecord|null
     */
    public function getMemberInfo($memberId)
    {
        $memberModel = new Member();
        return $memberModel->getMember(['member_id'=>$memberId],"*");
    }

    /**
     * 验证当前用户是否可以领取
     * @param $memberId
     * @return bool
     * @throws \Exception
     */
    public function checkUserType($memberId)
    {
        $memberInfo = $this->getMemberInfo($memberId);
        if (!$memberInfo) {
            $this->error = "未获取到用户信息!";
            return false;
        }
        //只针对新注册用户（此功能上线后注册的用户算新用户）
        if ($memberInfo->member_time < self::START_TIME) {
            $this->error = "您已经是老用户啦!";
            $this->code  = self::TYPE_NUM_THREE;
            return false;
        }
        //获取当前用户是否领取过
        foreach (self::NEW_VOUCHER_IDS as $voucher_id) {
            try {
                $result = Voucher::instance()->checkPicked($voucher_id, $memberId);
                if ($result) {
                    $this->type = false;
                    break;
                }
            } catch (\Exception $exception) {
                $this->type = false;
                break;
            }
        }
        if (!$this->type) {
            $this->error = "当前用户已经领取过!";
            $this->code  = self::TYPE_NUM_FIVE;
            return false;
        }
        //已完成的订单
        $successOrder = Orders::find()->where(['buyer_id'=>$memberId,'order_state'=>Orders::ORDER_STATE_SUCCESS])->count();
        //支付成功的订单
        $paySuccessOrder = Orders::find()->where(['buyer_id'=>$memberId,'order_state'=>Orders::ORDER_STATE_PAY])->count();
        //退款成功订单
        $refundTimeOrder = Orders::find()->where(['buyer_id'=>$memberId])->andWhere(['>','refund_time',0])->count();
        $successOrderNum = $paySuccessOrder - $refundTimeOrder;
        if ($successOrder > self::SUCCESS_ORDER || $successOrderNum > self::SUCCESS_ORDER) {
            $this->error = "当前用户没有领取资格~!";
            $this->code  = self::TYPE_NUM_FOUR;
            return false;
        }
        if (empty(self::NEW_VOUCHER_IDS)) {
            $this->error = "当前无可领优惠券~!";
            return false;
        }
        return true;
    }

    /**
     * 领取代金券
     * @param $memberId
     * @return bool
     * @throws \Exception
     */
    public function getVoucher($memberId)
    {
        //验证当前用户是否可以领取
        if (!$this->checkUserType($memberId)) {
            return  false;
        }
        $memberInfo = $this->getMemberInfo($memberId);
        if (!$memberInfo) {
            $this->error = "未获取到用户信息!";
            return false;
        }
        //点击领取时，须校验登录以及手机号是否绑定，先判断登录，未登录则调用手机号登录；未绑定手机的用户，先调用手机号一键登录，没有一键登录则必须要绑定手机号 并进入绑定手机号页面；绑定成功后还是回到首页；
        if (!$memberInfo->member_mobile) {
            $this->error = "当前用户未绑定手机号!";
            $this->code  = self::TYPE_NUM_TWO;
            return false;
        }
        //领取优惠券
        $voucherModel = new Voucher();
        $result = $voucherModel->exchangeVoucher(self::NEW_VOUCHER_IDS,$memberInfo,"新人领取100元优惠券");
        if (!$result) {
            $this->error = "领取失败";
            $this->code  = self::TYPE_NUM_ONE;
            return false;
        }
        return true;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getCode()
    {
        return $this->code;
    }
}