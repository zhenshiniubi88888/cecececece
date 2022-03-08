<?php
/**
 * 奇虎 360 - 服务
 * PHP version 7.2
 *
 * @category QihooService
 * @package  PHP_CodeSniffer
 * @author   wan.li <lswwyp@qq.com>
 * @license  https://spdx.org/licenses/Apache-2.0.html Apache-2.0
 * @version  GIT: 5.6.4
 */

namespace frontend\service;

use common\models\Member;

class QihooService
{
    public static $is_new = 0;

    private static $DebugTag = 'ByteDanceService';

    public static $message;
    /**
     * 获取360openid
     * @param $code
     * @param $versionType
     */
    public static function jscode2session($code, $type = 'huadi')
    {
        try {
            $gateway = 'https://mp.360.cn/miniplatform/open/oauth2/mp_session_key';
            $data = [
                'app_id' => \Yii::$app->params[$type]['360']['appid'],
                'auth_code' => $code,
            ];
            $requestInfo = sendGet($gateway . '?' . http_build_query($data));
            $requestInfoArr = json_decode($requestInfo, true);
            if(!isset($requestInfoArr['open_id'])){
                self::$message = self::$DebugTag . "->jscode2session() info " . $requestInfo;
                \Yii::error(self::$DebugTag . "->jscode2session() info " . $requestInfo);
                return false;
            }
            /*if (isset($requestInfoArr['errcode']) || (isset($requestInfoArr['error']) && $requestInfoArr['error'] > 0)) {
                self::$message = self::$DebugTag . "->jscode2session() info " . $requestInfo;
                \Yii::error(self::$DebugTag . "->jscode2session() info " . $requestInfo);
                return false;
            }
            if (!isset($requestInfoArr['open_id']) || !isset($requestInfo['session_key'])) {
                self::$message = self::$DebugTag . "->jscode2session() info 接口返回错误" . $requestInfo;
                \Yii::error(self::$DebugTag . "->jscode2session() info 接口返回错误" . $requestInfo);
                return false;
            }*/
            /*result "session_key": "ffaaed37bb05d096***","openid": "36d4bd3c8****", "anonymous_openid": ""*/
            return $requestInfoArr;
        } catch (\Exception $exception) {
            self::$message = self::$DebugTag . "->jscode2session() error " . $exception->getMessage();
            \Yii::error(self::$DebugTag . "->jscode2session() error " . $exception->getMessage());
            return false;
        }
//        $curl_result = send_post($url, json_encode($curl_data));
//        $openid = json_decode($curl_result)->open_id;
    }

    /**
     * 奇虎360小程序快速登录-注册
     * @param $openid
     * @param $userInfo
     * @return array|bool|\yii\db\ActiveRecord|null
     */
    public function autoLogin($openid, $userInfo)
    {
        $modelMember = new Member();
        $member = $modelMember->getMemberByQihoo360($openid);
        if ($member) {
            return $member;
        }
        if ($userInfo) {
            //自动注册
            $result = $modelMember->quickRegisterQihoo360([
                'nickname' => $userInfo['nickName'],
                'openid' => $openid,
                'headimgurl' => $userInfo['avatarUrl']
            ], SITEID);
            if (!$result) {
                return false;
            }
            self::$is_new = 1;
            $member = Member::instance()->getMemberByQihoo360($openid);
            return $member;
        } else {
            return false;
        }
    }
}