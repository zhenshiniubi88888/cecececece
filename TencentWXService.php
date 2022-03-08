<?php
/**
 * 腾讯微信小程序服务
 * PHP version 7.2
 *
 * @category TencentWXService
 * @package  PHP_CodeSniffer
 * @author   wan.li <lswwyp@qq.com>
 * @license  https://spdx.org/licenses/Apache-2.0.html Apache-2.0
 * @version  GIT: 5.6.4
 */

namespace frontend\service;

use common\components\Log;
use common\models\Member;

class TencentWXService
{
    public static $is_new = 0;

    private static $DebugTag = 'TencentWXService';


    /**
     *  //获取openid unionid
     * @param $jscode
     * @param string $type
     */
    public static function jscode2session($jscode, $type = 'huadi')
    {
        try {
            $gateway = 'https://api.weixin.qq.com/sns/jscode2session';
            $data = [
                'appid' => \Yii::$app->params[$type]['wechat']['appid'],
                'secret' => \Yii::$app->params[$type]['wechat']['secret'],
                'js_code' => $jscode,
                'grant_type' => 'authorization_code'
            ];
            $requestInfo = sendGet($gateway . '?' . http_build_query($data));

            $requestInfoArr = json_decode($requestInfo, true);
            if (isset($requestInfoArr['errcode']) && $requestInfoArr['errcode'] != 0) {
                Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() info " . $requestInfo);
                return false;
            }
            if (!isset($requestInfoArr['openid']) || !isset($requestInfoArr['session_key'])) {
                Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() info 接口返回错误" . $requestInfo);
                return false;
            }
            /*result "session_key": "ffaaed37bb05d096***","openid": "36d4bd3c8****", "anonymous_openid": ""*/
            return $requestInfoArr;
        } catch (\Exception $exception) {
            Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() error " . $exception->getMessage());
            return false;
        }
    }

    /**
     * @param $openid
     * @param $userInfo
     * @return array|bool|\yii\db\ActiveRecord|null
     */
    public function autoLogin($openid, $userInfo, $unionid = false)
    {
        $modelMember = new Member();
        $member = $modelMember->getMemberByWxid($openid);
        if ($member) {
            return $member;
        }
        if ($userInfo) {
            //自动注册
            $setUserInfo = [
                'nickname' => $userInfo['nickName'],
                'openid' => $openid,
                'unionid' => $unionid ? $unionid : $openid,
                'headimgurl' => $userInfo['avatarUrl'],
            ];
            $result = $modelMember->quickRegisterWx($setUserInfo, SITEID);
            if (!$result) {
                return false;
            }
            self::$is_new = 1;
            $member = Member::instance()->getMemberByWxid($openid);
            return $member;
        } else {
            return false;
        }
    }

    public function payment($payment_code, $pay_sn, $order_sn, $payment_amount)
    {

    }
}