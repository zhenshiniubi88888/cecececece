<?php
/**
 * TencentQQService
 * PHP version 5.6
 *
 * @category TencentQQService
 * @package  PHP_CodeSniffer
 * @author   wan.li <lswwyp@qq.com>
 * @license  https://spdx.org/licenses/Apache-2.0.html Apache-2.0
 * @version  GIT: 5.6.4
 */

namespace frontend\service;

use common\models\Member;

/**
 * 腾讯QQ业务相关服务
 * Class TencentQQService
 * @package frontend\service
 */
class TencentQQService
{
    public static $is_new = 0;

    private static $DebugTag = 'TencentQQService';

    /**
     *  //获取QQ openid unionid
     * @param $jscode
     * @param string $type
     */
    public static function jscode2session($jscode, $type = 'huadi')
    {
        try {
            $gateway = 'https://api.q.qq.com/sns/jscode2session';
            $data = [
                'appid' => \Yii::$app->params[$type]['qq']['appid'],
                'secret' => \Yii::$app->params[$type]['qq']['secret'],
                'js_code' => $jscode,
                'grant_type' => 'authorization_code'
            ];
            $requestInfo = sendGet($gateway . '?' . http_build_query($data));
            $requestInfoArr = json_decode($requestInfo, true);
            if (isset($requestInfoArr['errcode']) && $requestInfoArr['errcode'] != 0) {
                \Yii::debug(self::$DebugTag . "->jscode2session() info " . $requestInfo);
                return false;
            }
            if (!isset($requestInfoArr['openid']) || !isset($requestInfoArr['session_key'])) {
                \Yii::debug(self::$DebugTag . "->jscode2session() info 接口返回错误" . $requestInfo);
                return false;
            }
            /*result "session_key": "ffaaed37bb05d096***","openid": "36d4bd3c8****", "anonymous_openid": ""*/
            return $requestInfoArr;
        } catch (\Exception $exception) {
            \Yii::debug(self::$DebugTag . "->jscode2session() error " . $exception->getMessage());
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
        $member = $modelMember->getMemberByQQid($openid);
        if ($member) {
            return $member;
        }
        if ($userInfo) {
            //自动注册
            $setUserInfo = [
                'nickname' => $userInfo['nickName'],
                'openid' => $openid,
                'figureurl_qq_2' => $userInfo['avatarUrl'],
            ];
            $result = $modelMember->quickRegisterQQ($setUserInfo, $openid, SITEID);
            if (!$result) {
                return false;
            }
            self::$is_new = 1;
            $member = Member::instance()->getMemberByQQid($openid);
            return $member;
        } else {
            return false;
        }
    }
}