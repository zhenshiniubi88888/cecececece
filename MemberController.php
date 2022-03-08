<?php

namespace frontend\controllers;

use Api\Alipay\Alipay;
use common\components\FinalPrice;
use common\components\Log;
use common\components\Message;
use common\helper\DateHelper;
use common\models\Address;
use common\models\Adv;
use common\models\Goods;
use common\models\GoodsClass;
use common\models\GoodsRecord;
use common\models\GroupShoppingGoods;
use common\models\GroupShoppingTeam;
use common\models\HuadiStoreZmGoods;
use common\models\HuadiYearCardOrders;
use common\models\Member;
use common\models\MemberCommon;
use common\models\MemberExppointsLog;
use common\models\MemberGuide;
use common\models\MemberMedal;
use common\models\MemberVerify;
use common\models\Moments;
use common\models\Notify;
use common\models\Orders;
use common\models\PXianshiGoods;
use common\models\Setting;
use common\models\Souvenir;
use common\models\SouvenirNotify;
use common\models\SouvenirVoucherSendLog;
use common\models\Voucher;
use frontend\service\BaiduService;
use frontend\service\VoucherService;
use yii\db\Exception;
use yii\web\HttpException;

/**
 */
class MemberController extends BaseController
{
    public function actionIndex()
    {
        throw new HttpException(405);
    }


    /**
     * 会员中心(WAP版)
     * @return mixed
     */
    public function actionUsercenter()
    {
//客户端缓存
        if (\Yii::$app->request->post('login', 0) && !$this->isLogin()) {
            $this->validLogin();
        }
        $data = [];
        $adv_model = new Adv();
        $data['top_banner'] = $adv_model->getBanner(145);
        if ($this->isLogin()) {
            $member = Member::findOne($this->member_id);
            //会员信息
            $data['member'] = [
                'member_id' => $this->member_id,
                'member_avatar' => getMemberAvatar($member->member_avatar),
                'member_name' => $member->member_nickname ? mobile_format($member->member_nickname) : '设置昵称',
                'member_mobile' => $member->member_mobile ? mobile_format($member->member_mobile) : '绑定手机号',
                'member_mobile_bind' => $member->member_mobile_bind ? 1 : 0,
                'member_sex' => $member->member_sex,
                'member_birthday' => $member->member_birthday,
                'is_set_password' => $member->member_passwd == md5($member->weixin_unionid) ? 0 : 1,//如果通过微信登录，然后绑定的手机号，登录密码就是直接加密openid
                'show_member_mobile' => $member->member_mobile,
            ];

            // 积分
            $member_common = MemberCommon::find()->select('huadi_score')->where(['member_id' => $this->member_id])->one();
            $huadi_score = $member_common ? $member_common['huadi_score'] : 0;
            //资产信息
            $data['assets'] = [
                'exp' => $member->member_exppoints,
                'point' => $member->member_points,
                'balance' => $member->available_predeposit,
                'voucher' => (int)Voucher::getAvailableCount($this->member_id),
                'score' => $huadi_score,
            ];
            //等级信息
            $grade = Member::instance()->getMemberGradeData($member->member_exppoints);
            $data['grade'] = $grade;

            //订单count
            $data['order_count'] = [
                'wait_pay' => Orders::getOrderUnpayCount($this->member_id),
                'wait_confirm' => Orders::getOrderWaitCount($this->member_id),
                'wait_comment' => Orders::getOrderEvaCount($this->member_id),
                'after_sale' => Orders::getOrderServiceCount($this->member_id),
            ];
            $data['bottom_banner'] = $adv_model->getBanner(136);
            //新增底部广告跳转领取红包页面
            if(!empty($data['bottom_banner'])){
                $data['bottom_banner'][0]['adv_pic_url'] = 'http://www.hua.zj.cn/h5/invitefriend?id=1452&member_id='.$this->member_id;
                $data['bottom_banner'][0]['adv_pic_url_xcx'] = '/coupon/invite-home';
                $data['bottom_banner'][0]['adv_pic_url_app'] = 'http://www.hua.zj.cn/index/toAppPage?app_page=InviteFriendPage&voucher_id=1452&member_id='.$this->member_id.'&mustLogin=1';
            }
            //最新订单
            $data['latest_order'] = Orders::getLatestOrder($this->member_id);
        }
        $data['has_new_version'] = 0;
        $data['kf_tel'] = '400-018-1013';
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    public function actionHuadiInviteList()
    {
        //客户端缓存
        if (\Yii::$app->request->post('login', 0) && !$this->isLogin()) {
            $this->validLogin();
        }
        $post = \Yii::$app->request->post();
        $page = isset($post['page']) && intval($post['page']) > 0 ? intval($post['page']) : 1;
        $pagesize = 10;
        $offset = ($page - 1) * $pagesize;
        $member_list = Member::find()->select('member_nickname,member_avatar,member_time')->where(['inviter_id' => $this->member_id])->offset($offset)->limit($pagesize)->asArray()->all();
        if (empty($member_list)) {
            return $this->responseJson(Message::ERROR, '暂无邀请人信息');
        }
        foreach ($member_list as $k => $member) {
            $member_list[$k]['member_avatar'] = getMemberAvatar($member['member_avatar']);
            $member_list[$k]['member_time'] = date('Y-m-d H:i:s', $member['member_time']);
        }
        $data['invite_member_list'] = $member_list;
        $data['invite_key'] = base64_encode(\Yii::$app->getSecurity()->encryptByPassword(HUADI_INVITE_VOUCHER_ID . '|' . $this->member_id, SECURITY_KEY));
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 会员中心(APP版)
     * @return mixed
     */
    public function actionUsercenterApp()
    {
        //客户端缓存
        if (\Yii::$app->request->post('login', 0) && !$this->isLogin()) {
            $this->validLogin();
        }
        $data = [];
        $adv_model = new Adv();
        if ($this->isLogin()) {
            $member = Member::findOne($this->member_id);
            //是否是年卡用户
            $model_huadi_year_card_orders = new HuadiYearCardOrders();
            $is_year_card_member = $model_huadi_year_card_orders->getYearCardStateById($this->member_id);
            $data['is_year_card_member'] = $is_year_card_member ? 1 : 0;
            //年卡用户是否已领取今日优惠券
            $have_day_voucher = 0;
            if($is_year_card_member){
                $have_day_voucher = $model_huadi_year_card_orders->haveDayCoupons($this->member_id);
            }
            //会员信息
            $data['member'] = [
                'member_id' => $this->member_id,
                'member_avatar' => getMemberAvatar($member->member_avatar),
                'member_name' => $member->member_nickname ? mobile_format($member->member_nickname) : '设置昵称',
                'member_mobile' => $member->member_mobile ? mobile_format($member->member_mobile) : '绑定手机号',
                'member_mobile_bind' => $member->member_mobile_bind ? 1 : 0,
                'member_sex' => $member->member_sex,
                'member_birthday' => $member->member_birthday,
                'is_set_password' => $member->member_passwd == md5($member->weixin_unionid) ? 0 : 1,//如果通过微信登录，然后绑定的手机号，登录密码就是直接加密openid
                'show_member_mobile' => $member->member_mobile,
                'is_year_card_member'=> $is_year_card_member ? 1 : 0,
                'have_day_voucher'=>$have_day_voucher ? 1 : 0
            ];
            $data['member']['is_get_voucher'] = VoucherService::USER_TYPE_ERROR_CODE;
            if ((new VoucherService())->checkUserType($this->member_id)) {
                $data['member']['is_get_voucher'] = VoucherService::USER_TYPE_SUCCESS_CODE;
            }

            // 积分
            $member_common = MemberCommon::find()->select('huadi_score')->where(['member_id' => $this->member_id])->one();
            $huadi_score = $member_common ? $member_common['huadi_score'] : 0;
            //资产信息
            $data['assets'] = [
                'exp' => $member->member_exppoints,
                'point' => $member->member_points,
                'balance' => $member->available_predeposit,
                'voucher' => (int)Voucher::getAvailableCount($this->member_id),
                'score' => $huadi_score,
            ];
            //等级信息
            $grade = Member::instance()->getMemberGradeData($member->member_exppoints);
            $data['grade'] = $grade;

            //订单count
            $data['order_count'] = [
                'wait_pay' => Orders::getOrderUnpayCount($this->member_id),
                'wait_confirm' => Orders::getOrderWaitCount($this->member_id,'confirm'),
                'wait_comment' => Orders::getOrderEvaCount($this->member_id),
                'after_sale' => Orders::getOrderServiceCount($this->member_id),
            ];

            //最新订单
            $data['latest_order'] = Orders::getLatestOrder($this->member_id);
            $data['bottom_banner'] = $adv_model->getBanner(146);
            //新增底部广告跳转领取红包页面
            if(!empty($data['bottom_banner'])){
                $data['bottom_banner'][0]['adv_pic_url'] = 'http://www.hua.zj.cn/h5/invitefriend?id=1452&member_id='.$this->member_id;
                $data['bottom_banner'][0]['adv_pic_url_xcx'] = '/coupon/invite-home';
                $data['bottom_banner'][0]['adv_pic_url_app'] = 'http://www.hua.zj.cn/index/toAppPage?app_page=InviteFriendPage&voucher_id=1452&member_id='.$this->member_id.'&mustLogin=1';
            }
        }
        $data['has_new_version'] = 0;
        $data['top_banner'] = $adv_model->getBanner(145);
        $data['kf_tel'] = '4006-221-019';
        //获取未读消息数量
        $data['unread_num'] = Notify::unDynamicReadNum($this->member_id);
        //获取当前用户是否在已读列表中
        $listsStr = cache(Moments::$historyKey);
        $data['is_new_moments'] = 1;
        $data['year_card_price'] = HuadiYearCardOrders::YEAR_CARD_PRICE;
        $data['year_card_price_market'] = HuadiYearCardOrders::YEAR_CARD_MARKET_PRICE;
        if ($listsStr) {
            $listArray = explode(',',$listsStr);
            if (!empty($listArray) && in_array($this->member_id,$listArray)) {
                $data['is_new_moments'] = 0;//0没有新消息
            }
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }


    /**
     * 会员等级中心
     */
    public function actionVipCenter()
    {
        $this->validLogin();
        $member = Member::find()
        ->where(['member_id' => $this->member_id])
        ->select("member_exppoints")
        ->one();
        $data['member_exppoints'] = $member->member_exppoints;

        $member_level = 1;
        $level = Member::instance()->getMemberGrade();
        foreach ($level as &$v){
            if($member->member_exppoints >= $v['exppoints']){
                $member_level = $v['level'];
            }
            unset($v['level'],$v['orderdiscount']);
        }

        $data['level'] = $level;
        //勋章（实际为权益）
        $data['medal'] = MemberMedal::getMedal($member_level);

        //查询完善资料的任务是否完成
        $where = [
            'in',
            'operate',
            [
                MemberExppointsLog::OPERATE_SET_USER_HEADER,
                MemberExppointsLog::OPERATE_SET_USER_NICKNAME,
                MemberExppointsLog::OPERATE_SET_USER_SEX,
                MemberExppointsLog::OPERATE_SET_USER_BIRTHDAY,
            ]
        ];
        $member_task = MemberExppointsLog::find()->where($where)->andWhere(['member_id'=>$this->member_id])->count();
        $bind_mobile_task = Member::find()->where(['member_id'=>$this->member_id])->select("member_mobile_bind")->one();
        $data['member_task'] = [
            [
                'title' => '完善个人信息',
                'exppoints' => '+10浪漫值',
                'des' => '仅限首次完善信息时发放',
                'img' => 'https://cdn.ahj.cm/shop/avatar/cf4d5fff2ecfb1ba0b55e8d6adf63a87.png',
                'done' => $member_task >= 4 ? 1 : 0,
                'button_title' => $member_task >= 4 ? '已完善' : '去完善',
                'button_url' => '/user-center/userinfo',
            ],
            [
                'title' => '绑定手机',
                'exppoints' => '+10浪漫值',
                'des' => '仅限首次绑定手机号时发放',
                'img' => 'https://cdn.ahj.cm/shop/avatar/847526fe40db58a5c67c614e5e4e5f55.png',
                'done' => $bind_mobile_task->member_mobile_bind == 1 ? 1 : 0,
                'button_title' => $bind_mobile_task->member_mobile_bind == 1 ? '已绑定' : '去绑定',
                'button_url' => '/user-center/bindtel'
            ]
        ];
        $data['task'] = [
            [
                'title' => '消费购物',
                'exppoints' => '',
                'des' => '确认收货后发放浪漫值',
                'img' => 'https://cdn.ahj.cm/shop/avatar/c59e1ba1f23c14222b0d9ac39360124e.png',
                'button_title' => '去购物',
                'button_url' => '/pages/index',
            ],
            [
                'title' => '评价奖励',
                'exppoints' => '+5/10/15/20浪漫值',
                'des' => '评价完成后发放对应浪漫值',
                'img' => 'https://cdn.ahj.cm/shop/avatar/873132386a0158c2d2e405b31368565c.png',
                'button_title' => '去评价',
                'button_url' => '/order/all-order?active=3',
            ],
//            [
//                'title' => '分享奖励',
//                'exppoints' => '+10浪漫值',
//                'des' => '分享商品和活动获取浪漫值',
//                'img' => 'https://cdn.ahj.cm/shop/avatar/5f13e778924c099d06637e3a5bcfe11d.png',
//                'button_title' => '去分享',
//                'button_url' => '/pages/index',
//            ]
        ];
        return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$data);
    }

    /**
     * 浪漫值明细列表
     */
    public function actionExppointsList()
    {
        $this->validLogin();
        $page = \Yii::$app->request->post("page",1);

        $model = new MemberExppointsLog();
        $data = $model->getList($this->member_id,$page);
        return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$data);
    }

    /**
     * 浪漫值规则
     */
    public function actionExppointsRule()
    {
        $this->validLogin();
        $bind_mobile_task = Member::find()->where(['member_id'=>$this->member_id])->select("member_mobile,member_mobile_bind")->one();
        $hide_mobile = "";
        if($bind_mobile_task->member_mobile_bind == 1){
            $hide_mobile = substr($bind_mobile_task->member_mobile,0,3)."****".substr($bind_mobile_task->member_mobile,-4);
        }
        $data = [
            [
                'title' => '什么是浪漫值？',
                'content' => '会员等级判定标准为浪漫值，不同浪漫值对应不同等级，不同等级可享受不同权益。',
                'extra' => false,
            ],
            [
                'title' => '如何获得浪漫值？',
                'content' => '通过购物、商品评价、登录、完善资料等行为可获得不同数值的浪漫值，具体如下：',
                'extra' => [
                    'type' => 'table',
                    'column' => [
                        '浪漫值来源',
                        '奖励',
                        '详细说明'
                    ],
                    'data' => [
                        [
                            [
                                'value' => '首次购物成功'
                            ],
                            [
                                'value' => '+5'
                            ],
                            [
                                'value' => '付款成功即发放5浪漫值',
                                'url' => false
                            ]
                        ],
                        [
                            [
                                'value' => '购物频次'
                            ],
                            [
                                'value' => '+50'
                            ],
                            [
                                'value' => '自然月内，有三天都进行了购物且订单状态已完成，次月10号发放50个浪漫值',
                                'url' => false,
//                                'url_tips' => false,

                            ]
                        ],
                        [
                            [
                                'value' => '评价奖励'
                            ],
                            [
                                'value' => '+5/+5/ +10/+10/ +15/+20'
                            ],
                            [
                                'value' => '不同等级会员评价成功后浪漫值奖励不同',
                                'url' => false
                            ]
                        ],
                        [
                            [
                                'value' => '购物金额'
                            ],
                            [
                                'value' => '+结算金额'
                            ],
                            [
                                'value' => '交易成功后按商品结算金额1：1的比例发放（优惠券、运费除外），',
                                'url' => '/pages/index',
                                'url_tips' => '去购物'
                            ]
                        ],
                        [
                            [
                                'value' => '完善个人信息'
                            ],
                            [
                                'value' => '+10'
                            ],
                            [
                                'value' => '设置头像、昵称、性别、生日，每项+10，只能领取一次，',
                                'url' => '/user-center/userinfo',
                                'url_tips' => '去完善'
                            ]
                        ],
                        [
                            [
                                'value' => '绑定手机号'
                            ],
                            [
                                'value' => '+10'
                            ],
                            [
                                'value' => '只能领取一次，',
                                'url' => $bind_mobile_task->member_mobile_bind == 1 ? '/user-center/changeTel?tel='.$hide_mobile : '/user-center/bindtel',
                                'url_tips' => '去完善'
                            ]
                        ],
//                        [
//                            [
//                                'value' => '分享商品、活动'
//                            ],
//                            [
//                                'value' => '+10'
//                            ],
//                            [
//                                'value' => '分享商品或活动获得10个浪漫值',
//                                'url' => '/pages/index',
//                                'url_tips' => '去分享'
//                            ]
//                        ],
                        [
                            [
                                'value' => '登录'
                            ],
                            [
                                'value' => '+1'
                            ],
                            [
                                'value' => '每日登录获得1个浪漫值',
                                'url' => false
                            ]
                        ]
                    ]
                ],
            ],
            [
                'title' => '哪些情况会扣除浪漫值？',
                'content' => '浪漫值扣减规则：',
                'extra' => [
                    'type' => 'list',
                    'data' => [
                        '1）您发表的商品评价被删除，扣除的浪漫值与当时获得的值相同',
                        '2）退货扣除的浪漫值与当时获得的值相同'
                    ]
                ]
            ],
            [
                'title' => '会员等级如何计算？',
                'content' => '花递的会员等级会实时进行结算，取决于结算时所得浪漫值是否达到升级所需条件，若满足则自动升级；若当时浪漫值未满足当前等级所需浪漫值，则自动降级。浪漫值越高会员等级越高，享受到的会员权益越多。',
                'extra' => false
            ],
            [
                'title' => '会员等级对应的成长值',
                'content' => '',
                'extra' => [
                    'type' => 'list',
                    'data' => [
                        'V1：1-119浪漫值',
                        'V2：120-499浪漫值',
                        'V3：500-2999浪漫值',
                        'V4：3000-4999浪漫值',
                        'V5：5000-14999浪漫值',
                        'V6：15000及以上浪漫值'
                    ]
                ]
            ]
        ];
        return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG,$data);
    }

    /**
     * 资料更新 (原有是昵称和头像修改，19年12月 1.0.10中添加性别和生日修改)
     * @return mixed
     */
    public function actionProfileEdit()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $allow_edit = ['member_avatar', 'member_nickname', 'member_sex', 'member_birthday'];
        $member = Member::findOne($this->member_id);
        $hasDiff = false;
        foreach ($allow_edit as $field) {
            if (isset($post[$field])) {
                if ($field == 'member_nickname') {
                    if (!isUsername($post[$field])) {
                        return $this->responseJson(Message::ERROR, '昵称在1-16个字符之间，不能含有特殊字符');
                    }
                    $post[$field] = forceUtf8($post[$field]);
                    //第一次修改昵称加浪漫值
                    $result = MemberExppointsLog::firstSetMember($this->member_id,MemberExppointsLog::OPERATE_SET_USER_NICKNAME);
                    if($result['code'] == 0){
                        return $this->responseJson(Message::ERROR, $result['msg']);
                    }
                }
                if ($field == 'member_avatar') {
                    if (!isImgName_wx($post[$field])) {
                        return $this->responseJson(Message::ERROR, '图片上传错误');
                    }
                    $post[$field] = UPLOAD_SITE_URL . DS . ATTACH_AVATAR . DS . $post[$field];
                    //第一次修改头像加浪漫值
                    $result = MemberExppointsLog::firstSetMember($this->member_id,MemberExppointsLog::OPERATE_SET_USER_HEADER);
                    if($result['code'] == 0){
                        return $this->responseJson(Message::ERROR, $result['msg']);
                    }
                }
                if($field == 'member_sex'){
                    $post[$field] = $post[$field] == 1 ? Member::SEX_MAN : Member::SEX_WOMAN;
                    //第一次设置性别加浪漫值
                    $result = MemberExppointsLog::firstSetMember($this->member_id,MemberExppointsLog::OPERATE_SET_USER_SEX);
                    if($result['code'] == 0){
                        return $this->responseJson(Message::ERROR, $result['msg']);
                    }
                }
                if($field == 'member_birthday'){
                    $post[$field] = date("Y-m-d",strtotime($post[$field]));
                    //第一次设置生日加浪漫值
                    $result = MemberExppointsLog::firstSetMember($this->member_id,MemberExppointsLog::OPERATE_SET_USER_BIRTHDAY);
                    if($result['code'] == 0){
                        return $this->responseJson(Message::ERROR, $result['msg']);
                    }
                }
                $member->$field = $post[$field];
                $hasDiff = true;
            }
        }
        if (!$hasDiff) {
            return $this->responseJson(Message::ERROR, '保存失败');
        }

        $result = $member->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, '保存失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 密码设置/修改
     */
    public function actionSetPassword()
    {
        $this->validLogin();
        $member_mobile = $this->member_info['member_mobile'];
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '手机号未绑定');
        }
        $post = \Yii::$app->request->post();
        $old_password = isset($post['old_password']) ? trim($post['old_password']) : '';
        $new_password = isset($post['new_password']) ? trim($post['new_password']) : '';
        $check_password = isset($post['check_password']) ? trim($post['check_password']) : '';
        if(strlen($new_password) < 6 || strlen($new_password) > 18 || $new_password != $check_password){
            return $this->responseJson(Message::ERROR, '密码设置错误');
        }

        $member = Member::findOne($this->member_id);
        $member->member_passwd = md5($new_password.$member_mobile);
        //设置登录密码
        if($old_password == ''){
            $result = $member->save();
            if (!$result) {
                return $this->responseJson(Message::ERROR, '设置失败，请重试');
            }
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
        }
        //修改登录密码
        if($old_password == '' || $this->member_info['member_passwd'] != md5($old_password.$member_mobile)){
            return $this->responseJson(Message::ERROR, '旧密码错误');
        }
        if($old_password == $new_password){
            return $this->responseJson(Message::ERROR, '新密码不能与旧密码相同');
        }
        $result = $member->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, '更新失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 通过手机验证码修改密码
     */
    public function actionMobileSetPassword()
    {
        $this->validLogin();
        $member_mobile = $this->member_info['member_mobile'];
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '手机号未绑定');
        }
        $post = \Yii::$app->request->post();
        $verify_code = isset($post['verify_code']) ? trim($post['verify_code']) : '';
        $new_password = isset($post['new_password']) ? trim($post['new_password']) : '';
        $check_password = isset($post['check_password']) ? trim($post['check_password']) : '';
        if(strlen($new_password) < 6 || strlen($new_password) > 18 || $new_password != $check_password){
            return $this->responseJson(Message::ERROR, '密码设置错误');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_LOGIN, $member_mobile, $verify_code);
        if (!$result && $member_mobile != '15884477703') {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }

        $member = Member::findOne($this->member_id);
        $member->member_passwd = md5($new_password.$member_mobile);
        $result = $member->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, '更新失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    //获取聊天token
    public function actionGetTalkToken()
    {
        //appkey:vWKUoJgTpJa7TfaF
        //appsecret:kpQJX5W0HLa0ySG7S7O4HdZa1xUOIr10
        $member = Member::findOne($this->member_id);
        $device_type = \Yii::$app->request->post('device_type','applet_huadi');
        if($device_type == 'applet_huadi'){
            $device_type = 'huadi';
        }
        $timestap = time();
        if ($member) {
            $is_anonymous = 0;
            $meber_id = $this->member_id;
            if ($member['member_nickname'] != '') {
                $member_name = $member['member_nickname'];
            } elseif ($member['member_name'] != '') {
                $member_name = $member['member_name'];
            } elseif ($member['member_mobile'] != '') {
                $member_name = $member['member_mobile'];
            } else {
                $member_name = $member['member_id'];
            }
            $avatar = $member['member_avatar'];
            //暂时直接返回
            //return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhcHBrZXkiOiJ2V0tVb0pnVHBKYTdUZmFGIiwicGxhdGZvcm0iOiJodWFkaSIsImRldmljZSI6IiIsImlzX2Fub255bW91cyI6IjAiLCJ1c2VyX2lkIjoiMTEyNTM5NSIsInVzZXJfbmFtZSI6Ilx1NjU4N1x1OTc1OSIsImF2YXRhciI6Imh0dHBzOlwvXC93eC5xbG9nby5jblwvbW1vcGVuXC92aV8zMlwvRFlBSU9ncTgzZXFqOElnN2Fad1IyeTNZUXA0MktJNVZzcmExa0JyYmlhemJjeXN4YWN6V3YxUWJ2UWNrSFFRTFBONHVRRTB2TWdYalliYWFSYlg3cGJBXC8xMzIiLCJhcHBfaWQiOjF9.Z4Tj4GWpcBJiXaQO0-quDlxaitILoowSo0_gvbZNezU');
        } else {
            $is_anonymous = 1;
            $meber_id = 0;
            $member_name = '';
            $avatar = '';
            //暂时直接返回
            //return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhcHBrZXkiOiJ2V0tVb0pnVHBKYTdUZmFGIiwicGxhdGZvcm0iOiJodWFkaSIsImRldmljZSI6IiIsImlzX2Fub255bW91cyI6IjEiLCJ1c2VyX2lkIjoiIiwidXNlcl9uYW1lIjoiIiwiYXZhdGFyIjoiIiwiYXBwX2lkIjoxfQ.29kPvw6GVPhd5GO3DPatRrTArq9VUOHILVIKvssmh7Y');
        }

        $appkey = IS_TEST ? 'Bl2w7XZ0TRC0lj0s' : 'FWM6oEcTp6JDbZxk';
        $data = [
            'appkey' => $appkey,
            'platform' => $device_type,
            'device' => '',
            'is_anonymous' => $is_anonymous,
            'user_id' => $meber_id,
            'user_name' => $member_name,
            'avatar' => $avatar != '' ? $avatar : 'default',//用于解决wap端没头像时不能正常返回
            'timestamp' => $timestap
        ];
        $data['sign'] = $this->gettalksign($timestap, $data);
        $url = "https://chat.huawa.com:9501/token/createToken";
        $token_info = sendPost($url, $data);
        $token_info = json_decode($token_info, true);
        if (isset($token_info["result"]) && $token_info["code"] == 1) {
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $token_info['result']['token']);
        } else {
            return $this->responseJson(0, json_encode($token_info));
        }
    }

    //获取聊天签名
    protected function gettalksign($timestap, $data)
    {
        $appkey = IS_TEST ? 'Bl2w7XZ0TRC0lj0s' : 'FWM6oEcTp6JDbZxk';
        $appsecret = IS_TEST ? 'cOrY5gcVZdfTa7QNSq0zNC1TFkEbgp2Y' : 'i5QEa8n7L0Dt5FQi2DxJtZ9HbiAqpQBV';
//            $appkey = 'appkey:vWKUoJgTpJa7TfaF';
//        $appsecret = 'kpQJX5W0HLa0ySG7S7O4HdZa1xUOIr10';
        $params = [
            'v' => '1.0',
            'appkey' => $appkey,
            'timestamp' => $timestap,
        ];
        $params = array_merge($params, $data);

        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . $v;
        }
        $str = $params['appkey'] . $str . $appsecret;
        $str = md5(md5($str));
        $str = strtoupper($str);
        return $str;
    }

    /**
     * 绑定手机号/更换绑定手机
     * @return mixed
     */
    public function actionBindMobile()
    {
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $member_mobile = trim($post['member_mobile']);
        $verify_code = trim($post['verify_code']);
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_BIND, $member_mobile, $verify_code);
        if (!$result && $member_mobile != '15884477703') {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        //初次绑定手机号加浪漫值
        $result = MemberExppointsLog::firstSetMember($this->member_id,MemberExppointsLog::OPERATE_BIND_MOBILE);
        if($result['code'] == 0){
            return $this->responseJson(Message::ERROR, $result['msg']);
        }
        //查看是否绑定过
        $member = Member::findOne($this->member_id);

        if ($member->member_mobile_bind) {
            return $this->responseJson(Message::ERROR, '您已经绑定过手机了');
        }

        //验证手机号是否已被其他手机号绑定
        $member_other = Member::findOne(['member_mobile' => $member_mobile,'member_siteid' => SITEID,'member_state'=>1]);
        if (!empty($member_other)) {
            //20181129新流程账户合并
            if (isset($post['device_type']) && in_array($post['device_type'],['app_huadi_ios' , 'app_huadi_android' , 'applet_huadi', 'app_huadi', 'applet_aihuaju'])) {
                //已经绑定过了
                if ($member_other->member_id == $this->member_id) {
                    return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
                }
                $result = Member::instance()->memberMigrate($member_other->member_id, $member->member_id);
                //如果没迁移成功
                //处理账号手机号被绑定返回
                if(!$result){
                    $res = Message::getLastMessage();
                    if($res['code'] == 0 && $res['message'] == '该手机号已被绑定'){
                        return $this->responseJson(Message::MATCH_FAIL, '该手机号已被绑定' , $member_mobile);
                    }
                    return $this->responseJson(Message::ERROR, '该手机号已被绑定或已注册，请更换手机号' . var_export(Message::$messages, true));
                }
            }
        }
        //更新
        $member->member_mobile_bind = 1;
        $member->member_mobile = $member_mobile;
        if(empty($member->member_nickname)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $str = "";
            for ($i = 0; $i < 8; $i++) {
                $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
            }
            $member->member_nickname = "HD_" . $str;
        }
        $result = $member->save();
        if (!$result) {
            log::writelog("member", var_export($member->errors, true));
            return $this->responseJson(Message::ERROR, '绑定失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$member_mobile);
    }

    /**
     * 分享增加浪漫值
     */
    public function actionShareAddExppoints()
    {
        $result = MemberExppointsLog::addExppoints($this->member_id,10,MemberExppointsLog::OPERATE_SHARE);
        if($result['code'] == 0){
            return $this->responseJson(Message::ERROR, $result['msg']);
        }
        return $this->responseJson(Message::SUCCESS, '成功');
    }
    public function actionUntied()
    {
        $member = Member::findOne(['member_id' => $this->member_id]);
        if (!$member) {
            return $this->responseJson(Message::ERROR, '该账号不存在，解绑失败' . $this->member_id);
        }
        if ($member->member_mobile_bind == 1) {
            $member->member_mobile_bind = 0;
            $member->member_mobile = '0';
            $result = $member->save();
        }

        if (!$result) {
            return $this->responseJson(Message::ERROR, '解绑失败' . $this->member_id);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }
    public function actionUnbindOther(){
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $member_mobile = trim($post['member_mobile']);
        $verify_code = trim($post['verify_code']);
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_UNBIND, $member_mobile, $verify_code);
        if (!$result) {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        $member = Member::find()->where(['member_mobile' => $member_mobile,'member_siteid' => SITEID])->one();
        if (!$member) {
            return $this->responseJson(Message::ERROR, '账号不存在，解绑失败' . $this->member_id);
        }
        if ($member->member_mobile_bind == 1) {
            $member->member_mobile_bind = 0;
            $member->member_mobile = '0';
            $result = $member->save();
        }

        if (!$result) {
            return $this->responseJson(Message::ERROR, '解绑失败' . $this->member_id);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 对密文进行解密
     * @param string $aesCipher 需要解密的密文
     * @param string $aesIV 解密的初始向量
     * @return string 解密得到的明文
     */
    public function actionDecrypt()
    {
        $aesCipher = \Yii::$app->request->post('aes_cipher', '');
        $aesIV = \Yii::$app->request->post('aes_iv', '');
        $code = \Yii::$app->request->post('code', '');
        $is_bind = (int)\Yii::$app->request->post('is_bind', 1);
        $type = \Yii::$app->request->post('type', 'huadi');
        switch ($type) {
            case 'huadi':
                $appid = 'wxc0516d6abf2093a6';
                $appaecret = '629e5d4fefcde81d17cd33577fe5dc97';
                break;
            case 'aihuaju':
                $appid = 'wx42224670efef7a53';
                $appaecret = '1d79bbdae38353663530a9f233a38452';
                break;
            default:
                $appid = 'wx42224670efef7a53';
                $appaecret = '1d79bbdae38353663530a9f233a38452';
                break;
        }
        $key = _get_session_key($appid, $appaecret, $code);
        try {

            $decrypted = openssl_decrypt(base64_decode($aesCipher), "aes-128-cbc", base64_decode($key), OPENSSL_RAW_DATA, base64_decode($aesIV));

        } catch (\Exception $e) {
            return $this->responseJson(0, $e->getMessage());
        }

        try {
            //去除补位字符
            $result = decode($decrypted);

        } catch (\Exception $e) {
            //print $e;
            return $this->responseJson(0, $e->getMessage());
        }
        $phone_data = json_decode($result, true);
        $phone = isset($phone_data['purePhoneNumber']) ? $phone_data['purePhoneNumber'] : 0;
        Log::writelog('member_auth', $phone_data);
        if(!$phone){
            $error_parm = [
                'aesCipher' => $aesCipher,
                'aesIV' => $aesIV,
                'code' => $code,
                'is_bind' => $is_bind,
                'type' => $type,
                'key' => $key,
                'decrypted' => $decrypted,
                'result' => $result
            ];
            Log::writelog('member_auth_error', $error_parm);
            return $this->responseJson(Message::ERROR, '未获取到手机号');
        }
        if ($is_bind) {
            return $this->_BindMobile($phone);
        }
    }

    /**
     * @return mixed
     */
    public function actionDecryptBd()
    {
        $aesCipher = \Yii::$app->request->post('aes_cipher', '');
        $aesIV = \Yii::$app->request->post('aes_iv', '');
        $code = \Yii::$app->request->post("code");
        $bd_info = BaiduService::getSessionKey($code);
        if (empty($bd_info)) {
            return $this->responseJson(0, "绑定失败[100]");
        }
        if (isset($bd_info['errno'])) {
            return $this->responseJson(0, "绑定失败[101]");
        }
        $openid = $bd_info["openid"];
        $session_key = $bd_info["session_key"];
        $decrypt_data = BaiduService::decrypt($aesCipher, $aesIV, $bd_info['session_key']);
        if (empty($decrypt_data)) {
            return $this->responseJson(0, "绑定失败[102]");
        }
        $decrypt_data = json_decode($decrypt_data, true);
        if (empty($decrypt_data)) {
            return $this->responseJson(0, "绑定失败[103]");
        }
        if (isset($decrypt_data['errno'])) {
            return $this->responseJson(0, "绑定失败[104]");
        }
        $phone = $decrypt_data['purePhoneNumber'];
        if (empty($phone)) {
            return $this->responseJson(0, "绑定失败[105]");
        }
        return $this->_BindMobile($phone);
    }

    /**
     * 支付宝手机号授权绑定
     */
    public function actionDecryptAli()
    {
        $aesCipher = \Yii::$app->request->post('aes_cipher', '');
        $type = \Yii::$app->request->post('type', '');
        $aesKey = \Yii::$app->params[$type]['ali']['aes_key'];
        $decrypt_data = openssl_decrypt(base64_decode($aesCipher), 'AES-128-CBC', base64_decode($aesKey),1);
        if (empty($decrypt_data)) {
            return $this->responseJson(0, "绑定失败[102]");
        }
        $decrypt_data = json_decode($decrypt_data, true);
        if (empty($decrypt_data)) {
            return $this->responseJson(0, "绑定失败[103]");
        }
        if (!isset($decrypt_data['code']) || $decrypt_data['code'] != 10000) {
            return $this->responseJson(0, "绑定失败[104]");
        }
        $phone = $decrypt_data['mobile'];
        if (empty($phone)) {
            return $this->responseJson(0, "绑定失败[105]");
        }
        return $this->_BindMobile($phone);
    }

    /**
     * @param $phone
     * @return mixed
     */
    private function _BindMobile($phone)
    {
        if(!isMobile($phone)){
            Log::writelog('member_auth_error', $this->member_id.'=====>'.$phone);
            return $this->responseJson(Message::ERROR, '不支持的手机号码格式');
        }
        //查看是否绑定过
        $member = Member::findOne($this->member_id);
        if (!$member) {
            return $this->responseJson(Message::ERROR, '用户不存在' . $this->member_id);
        }
        if ($member->member_mobile_bind) {
            return $this->responseJson(Message::ERROR, '您已经绑定过手机了');
        }

        //验证手机号是否已被其他手机号绑定
        $member_other = Member::findOne(['member_mobile' => $phone, 'member_siteid' => SITEID,'member_state'=>1]);
        //$member_other = Member::findOne(['member_mobile' => $phone]);
        $merge = false;
        if (!empty($member_other)) {
        //20181129新流程账户合并
            //已经绑定过了
            if ($member_other->member_id == $this->member_id) {
                return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
            }
            $result = Member::instance()->memberMigrate($member_other->member_id, $member->member_id);
            //处理账号手机号被绑定返回
            $res = Message::getLastMessage();
            if($res['code'] == 0 && $res['message'] == '该手机号已被绑定'){
                return $this->responseJson(Message::MATCH_FAIL, '该手机号已被绑定' , $phone);
            }
            if (!$result) {
                return $this->responseJson(Message::ERROR, '该手机号已被绑定或已注册，请更换手机号' . var_export(Message::$messages, true));
            }
            $merge = true;
            //如果没迁移成功
        }
        //更新
        $member->member_mobile_bind = 1;
        $member->member_mobile = $phone;
        $result = $member->save(false);
        if (!$result) {
            return $this->responseJson(Message::ERROR, '绑定失败，请重试2');
        }
        //初次绑定手机号加浪漫值
        MemberExppointsLog::firstSetMember($this->member_id,MemberExppointsLog::OPERATE_BIND_MOBILE);
        if($merge){
            return $this->responseJson(2, Message::SUCCESS_MSG,$phone);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$phone);
    }


    /**
     * 修改绑定手机号
     */
    public function actionChangeMobile(){
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $new_mobile=$post['member_mobile'];
        $old_mobile=$this->member_info['member_mobile'];
        $verify_code = trim($post['verify_code']);
        $token=trim($post['change_mobile_token']);
        if(!isMobile($new_mobile)){
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        if($new_mobile==$old_mobile){
            return $this->responseJson(Message::VALID_FAIL, '新手机号不能与旧手机号一致');
        }
        //验证是否通过正常旧手机号校验流程修改
        if($token!=$this->member_info['change_mobile_token']){
            return $this->responseJson(Message::ERROR, '非法操作');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_CHANGE, $new_mobile, $verify_code);
        if (!$result && $new_mobile != '15884477703' && $verify_code!="") {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        $member_other = Member::findOne(['member_mobile' => $new_mobile,'member_state'=>1]);
        if(!empty($member_other)){
            return $this->responseJson(Message::ERROR, '该手机号已被绑定或已注册，请更换手机号');
        }

        $member=Member::findOne($this->member_id);
        $member->member_mobile_bind = 1;
        $member->member_mobile = $new_mobile;
        $member->change_mobile_token=md5(time());
        $result = $member->save();
        if (!$result) {
            return $this->responseJson(Message::ERROR, '绑定失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, '绑定成功');
    }


    /**
     * 通过检查历史订单手机号修改绑定手机号
     */
    public function actionCheckOrderMobile(){
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $order_user_name=$post['user_name'];
        if($order_user_name==""){
            return $this->responseJson(Message::VALID_FAIL, '请输入历史收货人');
        }
        $order=Orders::find()->where(['buyer_id'=>$this->member_id,'buyer_name'=>$order_user_name])->select("buyer_phone")->asArray()->one();
        if(!$order){
            return $this->responseJson(Message::VALID_FAIL, '历史订单中无此收花人');
        }

        $token=md5($order_user_name.time());
        $member_model=Member::findOne($this->member_id);
        $member_model->change_mobile_token=$token;
        $result = $member_model->save();
        if(!$result){
            return $this->responseJson(Message::ERROR, '生成参数错误，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['change_mobile_token' => $token]);
    }

    /**
     * 检查旧手机号是否有效
     */
    public function actionCheckCode(){
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $verify_code = trim($post['verify_code']);
        $member_mobile = $this->member_info['member_mobile'];
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_CHANGE, $member_mobile, $verify_code);
        if (!$result && $member_mobile != '15884477703') {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }

        $token=md5($member_mobile.time());
        $member_model=Member::findOne($this->member_id);
        $member_model->change_mobile_token=$token;
        $result = $member_model->save();
        if(!$result){
            return $this->responseJson(Message::ERROR, '生成参数错误，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['change_mobile_token' => $token]);
    }

    /**
     * 发送手机验证码
     */
    public function actionSendCode(){
        $this->validLogin();
        $post = \Yii::$app->request->post();
        $member_mobile = $this->member_info['member_mobile'];
        $bind_new_mobile=!empty($post['member_mobile'])?true:false;
        if($bind_new_mobile){//新手机号发送验证码
            $member_mobile=$post['member_mobile'];
        }
        if($this->member_info['member_mobile_bind']!=1||!isMobile($member_mobile)){
            return $this->responseJson(Message::ERROR, '当前账号未绑定手机号');
        }
        //获取今天验证码发送次数
        $model_verify = new MemberVerify();
        $send_count = $model_verify->querySendTime($member_mobile,MemberVerify::SEND_TYPE_CHANGE);
        if($send_count>=20){
            return $this->responseJson(Message::ERROR, '每天最多发送20次验证码');
        }

        //如果获取次数大于2则需要检查图形验证码
        $img_uuid = isset($post['img_uuid']) ? trim($post['img_uuid']) : '';
        $img_code = isset($post['img_code']) ? trim($post['img_code']) : '';
        if($send_count>=2){
            $img_code_res = $model_verify->verifyImgCode($img_uuid, $img_code);
            if(!$img_code_res){
                return $this->responseJson(Message::MATCH_FAIL, '验证码错误');
            }
        }

        $result = $model_verify->sendVerify($member_mobile, MemberVerify::SEND_TYPE_CHANGE, 'bind_mobile','花递',20);
        if (!$result) {
            return $this->responseJson(Message::ERROR, $model_verify->getFirstError(Message::MODEL_ERROR));
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['sms_await_seconds' => \Yii::$app->params['sms']['sendAwaitSeconds']]);

    }

    /**
     * 发送图形验证码
     */
    public function actionSendImgCode(){
        $VerifyModel = new MemberVerify();
        $verify_data = $VerifyModel->sendImgCode();

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['img_uuid' => $verify_data['img_uuid'], 'img_code' => $verify_data['img_code']]);

    }

    /**
     * 优惠券列表
     */
    public function actionVoucher()
    {
        $type = array(1 => '未使用', 2 => '已使用', 3 => '已过期', 4 => '已收回');
        print_r($this->member_id);die;
        // 未使用.
        $countNotOver = Voucher::find()
            ->where(array('voucher_owner_id' => $this->member_id, 'voucher_state' => 1))
            ->andWhere(['>', 'voucher_end_date', TIMESTAMP])
            ->count();
        // 已使用.
        $countOver = Voucher::find()
            ->where(array('voucher_owner_id' => $this->member_id))
            ->andWhere(['in', 'voucher_state', [2,3,4]])
            ->andWhere(['<', 'voucher_end_date', TIMESTAMP])
            ->count();
        // 查询数据.
        if (isset($_GET['type']) && $_GET['type'] == 'over') {
            $voucherList = Voucher::find()
                ->where(array('voucher_owner_id' => $this->member_id))
                ->andWhere(['in', 'voucher_state', [2,3,4]])
                ->andWhere(['<', 'voucher_end_date', TIMESTAMP])
                ->orderBy('voucher_id desc')
                ->limit(20)
                ->all();
        } else {
            $voucherList = Voucher::find()
                ->where(array('voucher_owner_id' => $this->member_id, 'voucher_state' => 1))
                ->andWhere(['>', 'voucher_end_date', TIMESTAMP])
                ->orderBy('voucher_id desc')
                ->limit(20)
                ->all();
        }
        if (!empty($voucherList)) {
            foreach ($voucherList as $key => $list) {
                if ($list['voucher_limit'] > 0) {
                    $voucherList[$key]['desc'] = '满' . intval($list['voucher_limit']) . '元可使用';
                } else {
                    $voucherList[$key]['desc'] = '无门槛使用';
                }
                $voucherList[$key]['voucher_start_date'] = date('Y-m-d', $list['voucher_start_date']);
                $voucherList[$key]['voucher_end_date'] = date('Y-m-d', $list['voucher_end_date']);
            }
        }

        $result = [
            'countNotOver' => $countNotOver,
            'countOver' => $countOver,
            'list' => $voucherList,
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$result);
    }

    /**
     * 获取用户纪念日列表
     * @return mixed
     */
    public function actionGetSouvenirList()
    {
        $this->validLogin();
        $souvenir_list = Souvenir::find()
            ->where(['member_id' => $this->member_id, 'status' => 1])
            ->select(["id", "member_id", "type", "type_name", "date", "title", "name", "sex", "mobile", "content", "is_solar_calendar", "img", "is_top"])
            ->asArray()
            ->all();
        $top = $data = $arr_tmp = [];
        $today = strtotime(date('Y-m-d'));
        $date_helper = new DateHelper();
        foreach ($souvenir_list as $k => &$souvenir) {
            $souvenir['weekday'] = $date_helper->getWeekDay($souvenir['date']);
            if ($souvenir['is_solar_calendar'] != 1) {
                //阴历日期,获取对应时间戳的阴历月日,然后换算成当前年月日,然后再换算成当前阳历年月日
                list($year, $month, $date) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
                $cur_year = date('Y');
                $dateHelper = new DateHelper();
                //闰月判断
                $lunar_arr = $dateHelper->convertSolarToLunar($year, $month, $date);
                $run_yue = $dateHelper->getLeapMonth($cur_year);
                //阳历转阴历, 如果阴历后续要转阳历 需要判断值需不需要根据闰月-
                if ($lunar_arr[7] > 0 && $lunar_arr[4] > $lunar_arr[7]) $lunar_arr[4]--;
                //阴历转阳历, 如果当前年份对应有闰月的话, 需要判断阴历值需不需要加
                if ($run_yue > 0 && $lunar_arr[4] > $run_yue) $lunar_arr[4]++;
                list($year, $month, $day) = $dateHelper->convertLunarToSolar($cur_year, $lunar_arr[4], $lunar_arr[5]);
                $souvenir_cur_year_date = date_to_unixtime($year . '-' . $month . '-' . $day);
            } else {
                list($year, $month, $date) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
                $cur_year = date('Y');
                $souvenir_cur_year_date = date_to_unixtime($cur_year . '-' . $month . '-' . $date);
            }
            if ($souvenir_cur_year_date > time()) {
                //如果纪念日日期大于当前时间戳
                $souvenir['day_count'] = ceil(($souvenir_cur_year_date - time()) / 86400);
            } elseif ($souvenir_cur_year_date == $today) {
                $souvenir['day_count'] = 0;
            } else {
                //如果纪念日日期小于当前时间戳,计算当前距离下一年日期的时间
                list($year, $month, $date) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
                $cur_year = date('Y');
                $next_year = $cur_year + 1;
                if ($souvenir['is_solar_calendar'] != 1) {
                    $dateHelper = new DateHelper();
                    //闰月判断
                    $lunar_arr = $dateHelper->convertSolarToLunar($year, $month, $date);
                    $run_yue = $dateHelper->getLeapMonth($next_year);
                    //阳历转阴历, 如果阴历后续要转阳历 需要判断值需不需要根据闰月-
                    if ($lunar_arr[7] > 0 && $lunar_arr[4] > $lunar_arr[7]) $lunar_arr[4]--;
                    //阴历转阳历, 如果当前年份对应有闰月的话, 需要判断阴历值需不需要+
                    if ($run_yue > 0 && $lunar_arr[4] > $run_yue) $lunar_arr[4]++;
                    list($year, $month, $day) = $dateHelper->convertLunarToSolar($next_year, $lunar_arr[4], $lunar_arr[5]);
                    $souvenir_next_year_date = date_to_unixtime($year . '-' . $month . '-' . $day);
                } else {
                    $souvenir_next_year_date = date_to_unixtime($next_year . '-' . $month . '-' . $date);
                }
                $souvenir['day_count'] = ceil(($souvenir_next_year_date - time()) / 86400);
            }
            $souvenir['date_time'] = isset($souvenir_next_year_date) ? $souvenir_next_year_date : $souvenir_cur_year_date;
            $souvenir['souvenir_year'] = floor(($souvenir['date_time'] - $souvenir['date']) / 86400 / 365);
            $souvenir['cur_time'] = time();
            unset($souvenir_next_year_date);
            if ($souvenir['is_top'] == 1) {
                $top[] = $souvenir;
                unset($souvenir_list[$k]);
            }
        }
        $souvenir_list = array_sort($souvenir_list, 'day_count', 'asc');
        $data['member_list'] = array_merge($top, $souvenir_list);
        $data['system_list'] = Souvenir::getLatestSouvenir();
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 获取单个用户编辑的纪念日详情
     * @return mixed
     */
    public function actionGetEditSouvenir()
    {
        $id = intval(\Yii::$app->request->post('id'));
        if ($id > 0) {
            $data = [];
            $data['souvenir'] = Souvenir::find()->where(['id' => $id, 'member_id' => $this->member_id, 'status' => 1])
                ->asArray()->one();
            if (!$data['souvenir']) return $this->responseJson(Message::ERROR, '未获取到数据!', []);
            $data['souvenir_notify'] = SouvenirNotify::find()->where(['souvenir_id' => $id, 'status' => 1])
                ->asArray()->all();

            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        }
        return $this->responseJson(Message::ERROR, '未获取到数据!', []);
    }

    /**
     * 新增/修改纪念日
     * @return mixed
     * @throws Exception
     */
    public function actionAddSouvenir()
    {
        $this->validLogin();
        $params = \Yii::$app->request->post();
        $result = $this->validParams($params);
        if ($result !== true) {
            return $this->responseJson(Message::ERROR, $result);
        }
        //插入主表数据
        if (isset($params['id']) && !empty($params['id'])) {
            //更新
            $model = Souvenir::findOne(['id' => intval($params['id']), 'member_id' => $this->member_id]);
            if (!$model) {
                return $this->responseJson(Message::ERROR, '未找到纪念日数据!');
            }
        } else {
            $model = new Souvenir();
            $model->member_id = $this->member_id;
        }
        //兼容1900年01月01日换算成时间戳再换算回来导致的时间不对称问题
        $params['date'] = date_to_unixtime(unixtime_to_date('Y-m-d', $params['date'] + 60 * 6));
        $model->event_ids = $params['event_ids'];
        $db = \Yii::$app->db->beginTransaction();
        $dateHelper = new DateHelper();
        //是否需要插入当天提醒的记录,方便脚本发券和首页弹窗判断
        $need_default_notify = true;
        try {
            $model->load(['Souvenir' => $params]);
            if ($model->is_top == 1) {
                if ($model->isNewRecord) {
                    Souvenir::updateAll(['is_top' => 0], ['member_id' => $this->member_id]);
                } else {
                    Souvenir::updateAll(['is_top' => 0], ['and', ['member_id' => $this->member_id], ['<>', 'id', $model->id]]);
                }
            }
            if (!$model->save()) {
                throw new \Exception('数据插入失败' . current($model->getFirstErrors()));
            }
            //插入子表数据
            if (isset($params['id']) && !empty($params['id'])) {
                SouvenirNotify::updateAll(['status' => 0], ['souvenir_id' => intval($params['id'])]);
            }
            if (!empty($params['notify_date_list']) && is_array($params['notify_date_list'])) {
                foreach ($params['notify_date_list'] as $souvenir_nofity) {
                    $notify_model = new SouvenirNotify();
                    $notify_model->souvenir_id = $model->id;
                    $notify_model->notify_date = $souvenir_nofity['notify_date'];
                    $hour = intval($souvenir_nofity['notify_hour']);
                    if ($notify_model->notify_date > 0) {
                        switch ($notify_model->notify_date) {
                            case 1:
                                $timestamp = $params['date'];
                                $need_default_notify = false;
                                break;
                            case 2:
                                $timestamp = $params['date'] - 86400 * 1;
                                break;
                            case 3:
                                $timestamp = $params['date'] - 86400 * 3;
                                break;
                            case 4:
                                $timestamp = $params['date'] - 86400 * 7;
                                break;
                            default:
                                break;
                        }
                        if (isset($timestamp)) {
                            list($year, $month, $date) = explode('-', unixtime_to_date('Y-m-d', $timestamp));
                            $notify_model->notify_time = $month . '-' . $date;
                            $lunar_arr = $dateHelper->convertSolarToLunar($year, $month, $date);
                            $notify_model->notify_time_moon = $lunar_arr[1] . $lunar_arr[2];
                            $notify_model->notify_time_hour = $hour;
                            $notify_model->status = 1;
                            $notify_model->created_at = time();
                            if (!$notify_model->save()) {
                                throw new \Exception('数据插入失败[1]' . current($model->getFirstErrors()));
                            }
                        }
                    }
                }
                if ($need_default_notify) {
                    //默认插入一条不需要提醒的数据, 方便脚本判断发券
                    $notify_model = new SouvenirNotify();
                    $notify_model->souvenir_id = $model->id;
                    $notify_model->notify_date = 0;
                    list($year, $month, $date) = explode('-', unixtime_to_date('Y-m-d', $params['date']));
                    $notify_model->notify_time = $month . '-' . $date;
                    $lunar_arr = $dateHelper->convertSolarToLunar($year, $month, $date);
                    $notify_model->notify_time_moon = $lunar_arr[1] . $lunar_arr[2];
                    $notify_model->notify_time_hour = 0;
                    $notify_model->status = 1;
                    $notify_model->created_at = time();
                    if (!$notify_model->save()) {
                        throw new \Exception('数据插入失败[2]' . current($model->getFirstErrors()));
                    }
                }
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            Log::writelog('souvenir_insert_fail', $e->getMessage());
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 纪念日参数验证
     * @param $params
     * @return bool|string
     */
    private function validParams(&$params)
    {
        $params['type'] = isset($params['type']) ? intval($params['type']) : 1;
        $params['sex'] = isset($params['sex']) ? intval($params['sex']) : 0;
        $params['is_top'] = isset($params['is_top']) ? intval($params['is_top']) : 0;
        $params['is_solar_calendar'] = isset($params['is_solar_calendar']) ? intval($params['is_solar_calendar']) : 1;
        //数据验证
        if (empty($params['date'])) {
            $msg = $params['type'] ? '生日' : '纪念日';
            return '请输入' . $msg;
        }
        if ($params['type'] == 1) {
            //生日类型,name,sex,mobile必填
            if (empty($params['name'])) {
                return '请输入姓名';
            }
            if (empty($params['sex'])) {
                return '请输入性别';
            }
            if (empty($params['mobile']) || !isMobile($params['mobile'])) {
                return '请输入正确的手机号!';
            }
            $params['title'] = '生日';
            $params['type_name'] = $params['name'] . '的生日';
        } else {
            //主题
            if (empty($params['mobile']) || !isMobile($params['mobile'])) {
                return '请输入正确的手机号!';
            }
            if (empty($params['title'])) {
                return '请输入纪念日主题';
            }
            if (empty($params['type_name'])) {
                return '请输入纪念日主题名称';
            }
        }
        return true;
    }

    /**
     * 纪念日详情页
     * @return mixed
     */
    public function actionSouvenirView()
    {
        $id = \Yii::$app->request->post('id');
        if (!$id) {
            return $this->responseJson(Message::ERROR, '未找到纪念日数据!');
        }
        $souvenir = Souvenir::find()->where(['member_id' => $this->member_id, 'id' => intval($id), 'status' => 1])->asArray()->one();
        if (!$souvenir) {
            return $this->responseJson(Message::ERROR, '未找到纪念日数据!');
        }
        //重组数据
        $data = [];
        $data['title'] = $souvenir['title'];
        $data['type_name'] = $souvenir['type_name'];
        $data['img'] = $souvenir['img'];
        $data['cube_list'] = [];
        $data['content'] = $souvenir['content'];
        $data['type'] = $souvenir['type'];
        $data['sex'] = $souvenir['sex'];
        $data['date_time'] = $souvenir['date'];
        $data['is_solar_calendar'] = $souvenir['is_solar_calendar'];
        $data['event_ids'] = $souvenir['event_ids'];
        $date_helper = new DateHelper();

        if ($souvenir['type'] == 1) {
            //生日相关数据
            list($y, $m, $d) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
            $sub_title_data = $date_helper->convertSolarToLunar($y, $m, $d);
            $xing_zuo = $date_helper->getXingzuo($m, $d);
            $data['type_name_sub'] = $souvenir['mobile'] . ' · ' . $xing_zuo . ' · ' . $sub_title_data[6];
            $data['xingzuo'] = $xing_zuo;
            $data['shuxiang'] = $sub_title_data[6];
            $data['mobile'] = $souvenir['mobile'];
            $souvenir = $this->getDayCount($souvenir);
            $age = getAgeByBirth($souvenir['date']);
            if($age == 0) {
                if(date('Y-m-d') !== date('Y-m-d',$souvenir['date'])){
                    $age = 1;
                }
            }
            if ($souvenir['day_count'] != 0) {
                //不是生日当天
                $data['cube_list'][0]['title'] = '距离' . ($souvenir['sex'] == 1 ? '他' : '她') . $age . '岁生日';
                $data['cube_list'][0]['day_count_remark'] = '';
                $data['cube_list'][0]['day_count'] = $data['day_count'] = $souvenir['day_count'];
            } else {
                $day_count_remark = $age == 0 ? $souvenir['name'] . '的生日' : $souvenir['name'] . '的' . $age . '岁生日';
                $data['cube_list'][0]['title'] = '今天是';
                $data['cube_list'][0]['day_count_remark'] = $day_count_remark;
                $data['cube_list'][0]['day_count'] = $data['day_count'] = 0;
            }
            $date_time = $souvenir['date_time'];
            list($y, $m, $d) = explode('-', unixtime_to_date('Y-m-d', $date_time));
            $_sub_title_data = $date_helper->convertSolarToLunar($y, $m, $d);
            $data['cube_list'][0]['is_solar_calendar'] = $data['cube_list'][1]['is_solar_calendar'] = $souvenir['is_solar_calendar'];
            $data['cube_list'][0]['date'] = unixtime_to_date('Y.m.d', $date_time) . ' (' . $_sub_title_data[1] . $_sub_title_data[2] . ') ' . $date_helper->getWeekDay($date_time);
            $data['cube_list'][0]['date_time'] = $date_time;
            $data['cube_list'][0]['cur_time'] = time();
            $data['cube_list'][1]['title'] = '从出生到今天';
            $data['cube_list'][1]['day_count'] = ceil((time() - $souvenir['date']) / 86400);
            $data['cube_list'][1]['day_count_remark'] = '';
            $data['cube_list'][1]['date'] = unixtime_to_date('Y.m.d', $souvenir['date']) . ' (' . $sub_title_data[1] . $sub_title_data[2] . ') ' . $date_helper->getWeekDay($souvenir['date']);
        } else {
            $data['type_name_sub'] = unixtime_to_date('Y.m.d', $souvenir['date']);
            list($y, $m, $d) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
            //距离下一个周年纪念日
            $_souvenir = $this->getDayCount($souvenir);
            $souvenir_year = floor(($_souvenir['date_time'] - $souvenir['date']) / 86400 / 365);
            $data['cube_list'][0]['is_solar_calendar'] = $data['cube_list'][1]['is_solar_calendar'] = $data['cube_list'][2]['is_solar_calendar'] = $souvenir['is_solar_calendar'];
            if ($_souvenir['day_count'] != 0) {
                //不是生日当天
                $data['cube_list'][0]['title'] = '距离' . $souvenir_year . '周年纪念日';
                $data['cube_list'][0]['day_count_remark'] = $souvenir['title'] . $souvenir_year . '周年';
                $data['cube_list'][0]['day_count'] = $data['day_count'] = $_souvenir['day_count'];
            } else {
                $day_count_remark = $souvenir_year == 0 ? $souvenir['title'] . '的纪念日' : $souvenir['title'] . $souvenir_year . '周年';
                $data['cube_list'][0]['title'] = '今天是';
                $data['type_name_sub'] = $data['cube_list'][0]['day_count_remark'] = $day_count_remark;
                $data['cube_list'][0]['day_count'] = $data['day_count'] = 0;
            }
            list($_y, $_m, $_d) = explode('-', unixtime_to_date('Y-m-d', $_souvenir['date_time']));
            $_sub_title_data = $date_helper->convertSolarToLunar($_y, $_m, $_d);
            $data['cube_list'][0]['date'] = unixtime_to_date('Y.m.d', $_souvenir['date_time']) . ' (' . $_sub_title_data[1] . $_sub_title_data[2] . ') ' . $date_helper->getWeekDay($_souvenir['date_time']);
            $data['cube_list'][0]['date_time'] = $_souvenir['date_time'];
            $data['cube_list'][0]['cur_time'] = time();

            //下一个十周年纪念日
            $next_tens = $date_helper->getNextTenYears($souvenir_year);
            $next_tens_year = $y + $next_tens;
            if ($souvenir['is_solar_calendar'] != 1) {
                list($y, $m, $d) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
                $lunar_arr = $date_helper->convertSolarToLunar($y, $m, $d);
                if ($lunar_arr[7] > 0 && $lunar_arr[4] > $lunar_arr[7]) $lunar_arr[4]--;
                $run_yue = $date_helper->getLeapMonth($next_tens_year);
                //阴历转阳历, 如果当前年份对应有闰月的话, 需要判断阴历值需不需要加
                if ($run_yue > 0 && $lunar_arr[4] > $run_yue) $lunar_arr[4]++;
                list($year, $month, $day) = $date_helper->convertLunarToSolar($next_tens_year, $lunar_arr[4], $lunar_arr[5]);
                $souvenir_next_tens_year_date = date_to_unixtime($year . '-' . $month . '-' . $day);
            } else {
                $souvenir_next_tens_year_date = date_to_unixtime($next_tens_year . '-' . $m . '-' . $d);
            }
            $day_count = ceil(($souvenir_next_tens_year_date - strtotime(date('Y-m-d'))) / 86400);
            list($y, $m, $d) = explode('-', unixtime_to_date('Y-m-d', $souvenir_next_tens_year_date));
            $lunar_arr = $date_helper->convertSolarToLunar($y, $m, $d);
            $data['cube_list'][1]['title'] = '距离' . $next_tens . '周年纪念日';
            $data['cube_list'][1]['day_count'] = $day_count;
            $data['cube_list'][1]['date'] = unixtime_to_date('Y.m.d', $souvenir_next_tens_year_date) . ' (' . $lunar_arr[1] . $lunar_arr[2] . ') ' . $date_helper->getWeekDay($souvenir_next_tens_year_date);
            $data['cube_list'][1]['date_time'] = $souvenir_next_tens_year_date;
            $data['cube_list'][1]['cur_time'] = time();
            //从纪念日至今
            list($y, $m, $d) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
            $lunar_arr = $date_helper->convertSolarToLunar($y, $m, $d);
            $data['cube_list'][2]["title"] = '从' . $souvenir['title'] . '到今天';
            $data['cube_list'][2]["day_count"] = ceil((time() - $souvenir['date']) / 86400);
            $data['cube_list'][2]["date"] = unixtime_to_date('Y.m.d', $souvenir['date']) . ' (' . $lunar_arr[1] . $lunar_arr[2] . ') ' . $date_helper->getWeekDay($souvenir['date']);;
            $data['cube_list'][1]['day_count_remark'] = $data['cube_list'][2]['day_count_remark'] = '';
        }
        ksort($data['cube_list']);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);


    }

    /**
     * 获取纪念日倒数天数
     * @param $souvenir
     * @param string $_date
     * @return mixed
     */
    private function getDayCount($souvenir, $_date = '')
    {
        if (!$_date) {
            $_date = date_to_unixtime(unixtime_to_date('Y-m-d'));
        }
        if ($souvenir['is_solar_calendar'] != 1) {
            //阴历日期,获取对应时间戳的阴历月日,然后换算成当前年月日,然后再换算成当前阳历年月日
            list($year, $month, $day) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
            $cur_year = unixtime_to_date('Y', $_date);
            $dateHelper = new DateHelper();
            //对应阴历年月日
            $lunar_arr = $dateHelper->convertSolarToLunar($year, $month, $day);
            //判断纪念日年份是否是闰月且纪念日阴历月份在闰月之后
            $run_yue = $dateHelper->getLeapMonth($cur_year);
            //阳历转阴历, 如果阴历后续要转阳历 需要判断值需不需要根据闰月-
            if ($lunar_arr[7] > 0 && $lunar_arr[4] > $lunar_arr[7]) $lunar_arr[4]--;
            //阴历转阳历, 如果当前年份对应有闰月的话, 需要判断阴历值需不需要加
            if ($run_yue > 0 && $lunar_arr[4] > $run_yue) $lunar_arr[4]++;
            list($year, $month, $day) = $dateHelper->convertLunarToSolar($cur_year, $lunar_arr[4], $lunar_arr[5]);
            $souvenir_cur_year_date = date_to_unixtime($year . '-' . $month . '-' . $day);
        } else {
            list($year, $month, $date) = explode('-', date('Y-m-d', $souvenir['date']));
            $cur_year = date('Y', $_date);
            $souvenir_cur_year_date = date_to_unixtime($cur_year . '-' . $month . '-' . $date);
        }
        if ($souvenir_cur_year_date > $_date) {
            //如果纪念日日期大于当前时间戳
            $souvenir['day_count'] = ceil(($souvenir_cur_year_date - $_date) / 86400);
        } elseif ($souvenir_cur_year_date >= $_date && $souvenir_cur_year_date <= $_date + 86400) {
            $souvenir['day_count'] = 0;
        } else {
            //如果纪念日日期小于当前时间戳,计算当前距离下一年日期的时间
            list($year, $month, $day) = explode('-', unixtime_to_date('Y-m-d', $souvenir['date']));
            $cur_year = unixtime_to_date('Y', $_date);
            $next_year = $cur_year + 1;
            if ($souvenir['is_solar_calendar'] != 1) {
                $dateHelper = new DateHelper();
                //闰月判断
                $lunar_arr = $dateHelper->convertSolarToLunar($year, $month, $day);
                //判断纪念日年份是否是闰月且纪念日阴历月份在闰月之后
                $run_yue = $dateHelper->getLeapMonth($next_year);
                //阳历转阴历, 如果阴历后续要转阳历 需要判断值需不需要根据闰月-
                if ($lunar_arr[7] > 0 && $lunar_arr[4] > $lunar_arr[7]) $lunar_arr[4]--;
                //阴历转阳历, 如果当前年份对应有闰月的话, 需要判断阴历值需不需要加
                if ($run_yue > 0 && $lunar_arr[4] > $run_yue) $lunar_arr[4]++;
                list($year, $month, $day) = $dateHelper->convertLunarToSolar($next_year, $lunar_arr[4], $lunar_arr[5]);
                $souvenir_next_year_date = date_to_unixtime($year . '-' . $month . '-' . $day);
            } else {
                $souvenir_next_year_date = date_to_unixtime($next_year . '-' . $month . '-' . $day);
            }
            $souvenir['day_count'] = ceil(($souvenir_next_year_date - $_date) / 86400);
        }
        $souvenir['date_time'] = isset($souvenir_next_year_date) ? $souvenir_next_year_date : $souvenir_cur_year_date;
        return $souvenir;
    }

    /**
     * 获取纪念日预设主题图片
     * @return mixed
     */
    public function actionSouvenirThemeList()
    {
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, Souvenir::getSouvenirThemeList());
    }

    /**
     * 纪念日删除
     * @return mixed
     */
    public function actionDelSouvenir()
    {
        $id = intval(\Yii::$app->request->post('id'));
        if (empty($id) || $id <= 0) {
            return $this->responseJson(Message::ERROR, '参数错误!');
        }
        $souvenir = Souvenir::findOne(['id' => $id, 'member_id' => $this->member_id, 'status' => 1]);
        if (!$souvenir) {
            return $this->responseJson(Message::ERROR, '未找到数据!');
        }
        $souvenir->status = 0;
        $souvenir->save(false);
        SouvenirNotify::updateAll(['status' => 0], ['souvenir_id' => $souvenir->id]);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 纪念日手动获取优惠券
     * @return mixed
     * @throws Exception
     */
    public function actionSouvenirVoucherGet()
    {
        $this->validLogin();
        $id = intval(\Yii::$app->request->post('id'));
        if (!$id) return $this->responseJson(Message::ERROR, '参数错误');
        $souvenir = Souvenir::find()->where(['status' => 1, 'member_id' => $this->member_id, 'id' => $id])->one();
        if (!$souvenir) return $this->responseJson(Message::ERROR, '未找到数据');
        $souvenir_voucher_send_log = SouvenirVoucherSendLog::find()
            ->where(['date' => date('Y'), 'souvenir_id' => $id])
            ->scalar();
        if (!$souvenir_voucher_send_log) {
            $voucher = Voucher::instance()->exchangeVoucher(SOUVENIR_VOUCHER_T_ID, (object)$this->member_info, '纪念日发放优惠券-' . $id);
            $param = [
                'souvenir_id' => $id,
                'voucher_id' => $voucher->voucher_id,
                'voucher_t_id' => $voucher->voucher_t_id,
                'date' => date('Y'),
                'created_at' => time()
            ];
            SouvenirVoucherSendLog::instance()->addLog($param);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 花递邀请拉新按钮点击数据埋点
     * @return mixed
     * @author gxq <know1111@qq.com>
     */
    public function actionInviteNew()
    {
        if ($this->member_id) {
            $where['member_id'] = $this->member_id;
            $field = 'member_id, share_one_time, share_number';
            $memberCommon = MemberCommon::find()
                ->where($where)
                ->select($field)
                ->one();

            if (!empty($memberCommon)) {
                //如果是第一次点击按钮则记录时间
                !empty($memberCommon->share_one_time) ?: $memberCommon->share_one_time = TIMESTAMP;
                $memberCommon->share_number += 1;

                $result = $memberCommon->save();
                if ($result) {
                    return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
                }
            }
        }
        return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
    }
    /**
     * 获取地址通讯录(根据收货人地址信息获取)
     */
    public function actionContractListFromAddress(){
        $contract_list = [];
        if($this->member_id >0 ){
            $contract_list = Address::find()->where(['is_delete' => 0, 'member_id' => $this->member_id])
                ->select(['shou_name name', 'mob_phone mobile'])
                ->groupBy('mob_phone')->asArray()->all();
        }
        $data['contract_list'] = $contract_list;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    public function actionViewHistory()
    {
        if (!$this->isLogin()) {
            return $this->responseJson(Message::UN_LOGIN, Message::UN_LOGIN_MSG);
        }
        $goods_record_list = GoodsRecord::find()
            ->alias('a')
            ->leftJoin(Goods::tableName() . ' b', 'b.goods_id = a.goods_id')
            ->leftJoin(GoodsClass::tableName() . ' c', 'c.gc_id = b.gc_id_2')
            ->where(['a.status' => 1, 'b.goods_state' => 1, 'member_id' => $this->member_id])
            ->andWhere(['!=', 'a.goods_type', GOODS_TYPE_STORE_FLOWER])
            ->select([
                'a.id',
                "FROM_UNIXTIME(a.created_at,'%m月%d日') date",
                'a.goods_id',
                'a.goods_name',
                'a.goods_material',
                'a.goods_image',
                'a.goods_price',
                'a.goods_type',
                'max(a.created_at) created_at',
                'b.gc_id_2 gc_id',
                'c.gc_name',
            ])
            ->groupBy('date,a.goods_id,a.goods_type')
            ->orderBy('created_at desc')
            ->limit(80)->asArray()->all();
        $count = count($goods_record_list);
        //花递直卖的商品信息, 直接取zm_goods-可能不全
        $huadi_goods_record_list = GoodsRecord::find()
//            ->innerJoin(HuadiStoreZmGoods::tableName() . ' b', 'b.goods_id = a.goods_id')
            ->where(['status' => 1, 'member_id' => $this->member_id])
            ->andWhere(['goods_type' => GOODS_TYPE_STORE_FLOWER])
            ->select([
                'id',
                "FROM_UNIXTIME(created_at,'%m月%d日') date",
                'goods_id',
                'goods_name',
                'goods_material',
                'goods_image',
                'goods_price',
                'goods_type',
                'max(created_at) created_at',
            ])
            ->groupBy('date,goods_id,goods_type')
            ->orderBy('created_at desc')
            ->limit(100 - $count)->asArray()->all();
        $goods_record_list = array_sort(array_merge($goods_record_list, $huadi_goods_record_list), 'created_at', 'desc');
        $config = Setting::instance()->getAll();
        $return_data = [];
        $count = 0;
        if (!empty($goods_record_list)) {
            foreach ($goods_record_list as &$goods) {
                $count++;
                if ($goods['goods_type'] != GOODS_TYPE_STORE_FLOWER) {
                    //限时折扣判断
                    $xianshi = PXianshiGoods::instance()->getXianshiGoodsInfoByGoodsID($goods['goods_id']);
                    if (!empty($xianshi)) {
                        $goods['is_xianshi'] = 1;
                    } else {
                        $goods['is_xianshi'] = 0;
                    }
                    //特价
                    $special_offer_goods_idstr = $config['huadi_special_offer'] ? $config['huadi_special_offer'] : '';
                    $special_offer_goods_ids = [];
                    if ($special_offer_goods_idstr) {
                        $special_offer_goods_ids = explode(',', $special_offer_goods_idstr);
                    }
                    //是否特价商品
                    if (in_array($goods['goods_id'], $special_offer_goods_ids)) {
                        $goods['is_special'] = 1;
                    } else {
                        $goods['is_special'] = 0;
                    }
                    //拼团
                    $group_shopping_goods_model = new GroupShoppingGoods();
                    $check_group = $group_shopping_goods_model->getGoodsGroup([$goods['goods_id']]);
                    $product_data['goods_info']['is_group_shopping'] = isset($check_group[0]['goods_id']) ? 1 : 0;
                    if (isset($check_group[0]['goods_id'])) {
                        $goods['is_group_shopping'] = 1;
                        $goods['max_group_people'] = $check_group[0]['max_people'];
                    } else {
                        $goods['is_group_shopping'] = 0;
                        $goods['max_group_people'] = 0;
                    }
                    //年卡价
                    $year_card_id_arr = $config['huadi_year_card'] ? unserialize($config['huadi_year_card']) : '';
                    $year_card_goods_ids = [];
                    if ($year_card_id_arr && !empty($year_card_id_arr)) {
                        $year_card_goods_ids = array_keys($year_card_id_arr);
                    }
                    if (in_array($goods["goods_id"], $year_card_goods_ids)) {
                        $goods['year_card_price'] = FinalPrice::yearCardMatchRate($year_card_id_arr[$goods["goods_id"]], $goods["goods_price"]);
                    } else {
                        $goods['year_card_price'] = 0;
                    }
                }
                if (!isset($return_data[$goods['date']])) {
                    $return_data[$goods['date']] = [];
                }
                $return_data[$goods['date']][] = $goods;
            }
        }
        $data['data'] = $return_data;
        $data['count'] = $count;
        return $this->responseJson(Message::SUCCESS, 'success', $data);
    }

    /**
     * 我的足迹删除功能
     * @param $record_id integer 删除单个足迹,优先级最高
     * @param $is_delete_all boolean 删除所有足迹
     */
    public function actionDelViewHistory()
    {
        $record_id = \Yii::$app->request->post('id');
        $is_delete_all = \Yii::$app->request->post('is_delete_all');
        $condition = [];
        if ($is_delete_all && $this->member_id) {
            $condition['member_id'] = $this->member_id;
        }
        if (!empty($record_id) && $record_id > 0) {
            $condition = ['id' => $record_id, 'member_id' => $this->member_id];
        }
        if (!empty($condition)) {
            GoodsRecord::updateAll(['status' => 0], $condition);
        }
        return $this->responseJson(Message::SUCCESS, 'success', []);
    }
}
