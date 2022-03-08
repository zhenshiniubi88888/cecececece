<?php
/**
 * 字节跳动小程序服务
 * PHP version 5.6
 *
 * @category ByteDanceService
 * @package  PHP_CodeSniffer
 * @author   wan.li <lswwyp@qq.com>
 * @license  https://spdx.org/licenses/Apache-2.0.html Apache-2.0
 * @version  GIT: 5.6.4
 * @link     http://www.xxx.com/api /(10)接口文档地址
 */

namespace frontend\service;

use common\components\Log;
use common\models\Member;

/**
 * Class ByteDanceService
 * @package frontend\service
 */
class ByteDanceService
{
    public static $message;
    public static $is_new = 0;
    private static $DebugTag = 'ByteDanceService';

    /**
     * 通过login接口获取到登录凭证后，开发者可以通过服务器发送请求的方式获取session_key和openId
     * @param $code string  登录凭证 code 只能使用一次
     * @param string $type
     * @return bool|mixed
     */
    public static function jscode2session($code, $type = 'huadi')
    {
        try {
            $gateway = 'https://developer.toutiao.com/api/apps/jscode2session';
            $data = [
                'appid' => \Yii::$app->params[$type]['byte_dance']['appid'],
                'secret' => \Yii::$app->params[$type]['byte_dance']['secret'],
                'code' => $code,
                'grant_type' => 'authorization_code'
            ];
            $requestInfo = sendGet($gateway . '?' . http_build_query($data));
            $requestInfoArr = json_decode($requestInfo, true);
            if (isset($requestInfoArr['errcode']) || (isset($requestInfoArr['error']) && $requestInfoArr['error'] > 0)) {
                self::$message = self::$DebugTag . "->jscode2session() info " . $requestInfo;
                Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() info " . $requestInfo);
                return false;
            }
            if (!isset($requestInfoArr['openid']) || !isset($requestInfoArr['session_key'])) {
                self::$message = self::$DebugTag . "->jscode2session() info 接口返回错误" . $requestInfo;
                Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() info 接口返回错误" . $requestInfo);
                return false;
            }
            Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() error " . json_encode($requestInfoArr));
            /*result "session_key": "ffaaed37bb05d096***","openid": "36d4bd3c8****", "anonymous_openid": ""*/
            return $requestInfoArr;
        } catch (\Exception $exception) {
            self::$message = self::$DebugTag . "->jscode2session() error " . $exception->getMessage();
            Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() error " . $exception->getMessage());
            return false;
        }
    }

    /**
     * @param $openid
     * @param $userInfo
     * @return array|bool|\yii\db\ActiveRecord|null
     */
    public function autoLogin($openid, $userInfo)
    {
        Log::writelog('huadi_login_fail',self::$DebugTag . "->jscode2session() error $openid autoLogin" );
        $modelMember = new Member();
        $member = $modelMember->getMemberByByteDance($openid);
        if ($member) {
            return $member;
        }
//        if ($userInfo) {
            //自动注册
            $result = $modelMember->quickRegisterByteDance([
                'nickname' => isset($userInfo['nickName']) ? $userInfo['nickName'] : '',
                'openid' => $openid,
                'headimgurl' => isset($userInfo['avatarUrl']) ? $userInfo['avatarUrl'] : ''
            ], SITEID);
            if (!$result) {
                return false;
            }
            self::$is_new = 1;
            $member = Member::instance()->getMemberByByteDance($openid);
            return $member;
//        } else {
//            return false;
//        }
    }

}