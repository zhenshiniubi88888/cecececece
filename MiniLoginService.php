<?php
/**
 * 小程序登录服务
 * PHP version5.6
 *
 * @category MiniLoginService.php
 * @package  PHP_CodeSniffer
 * @author   wan.li <lswwyp@qq.com>
 * @license  https://spdx.org/licenses/Apache-2.0.html Apache-2.0
 * @version  GIT: 5.6.4
 */

namespace frontend\service;

use common\models\Member;

/**
 * 小程序登录 - all
 * Class MiniLoginService
 * @package frontend\service
 */
class MiniLoginService
{
    // 是否新用户，初始为0
    private static $loginData = [];

    public $message = '';

    public static $is_new = 0;

    public function __construct()
    {
        // 小程序类型
        $applets_type = \Yii::$app->request->post('applets_type', "");
        $code = \Yii::$app->request->post("code");
        $userInfo = \Yii::$app->request->post("user_info", "");
        $versionType = \Yii::$app->request->post("version_type", "huadi");
        switch ($applets_type) {
            // 字节跳动小程序
            case \Yii::$app->params['applets_type']['byte_dance']:
                $byteDanceService = new ByteDanceService();
                $byteDanceUserInfo = $byteDanceService::jscode2session($code, $versionType);
                if (empty($byteDanceUserInfo)) {
                    self::$loginData = false;
                    break;
                }
                self::$loginData = $byteDanceService->autoLogin($byteDanceUserInfo['openid'], $userInfo);
                self::$is_new = $byteDanceService::$is_new;
                $this->message = $byteDanceService::$message;
                break;
            // 腾讯qq小程序
            case \Yii::$app->params['applets_type']['qq']:
                $tencentQQService = new TencentQQService();
                $tencentQQUserInfo = $tencentQQService::jscode2session($code, $versionType);
                if (empty($tencentQQUserInfo)) {
                    self::$loginData = false;
                    break;
                }
                self::$loginData = $tencentQQService->autoLogin($tencentQQUserInfo['openid'], $userInfo);
                self::$is_new = $tencentQQService::$is_new;
                break;
            // 腾讯微信小程序
            case \Yii::$app->params['applets_type']['wechat']:
                $tencentWXService = new TencentWXService();
                $tencentWXUserInfo = $tencentWXService::jscode2session($code, $versionType);
                if (empty($tencentWXUserInfo)) {
                    self::$loginData = false;
                    break;
                }
                self::$loginData = $tencentWXService->autoLogin($tencentWXUserInfo['openid'], $userInfo, $tencentWXUserInfo['unionid']);
                self::$is_new = $tencentWXService::$is_new;
                break;
            // 支付宝小程序
            case \Yii::$app->params['applets_type']['ali']:
                $aliService = new AliService();
                $user_id = $aliService::jscode2session($code, $versionType);
                if (empty($user_id)) {
                    self::$loginData = false;
                    break;
                }
                self::$loginData = $aliService->autoLogin($user_id, $userInfo);
                self::$is_new = $aliService::$is_new;
                break;
            // 360小程序
            case \Yii::$app->params['applets_type']['360']:
                $qihooService = new QihooService();
                $qihooUserInfo = $qihooService::jscode2session($code, $versionType);
                if (empty($qihooUserInfo)) {
                    self::$loginData = false;
                    break;
                }
                self::$loginData = $qihooService->autoLogin($qihooUserInfo['open_id'], $userInfo);
                self::$is_new = $qihooService::$is_new;
                break;
            // 百度小程序
            case \Yii::$app->params['applets_type']['baidu']:
                $bd_info = BaiduService::getSessionKey($code, $versionType);
                if (empty($bd_info) || isset($bd_info['errno']) || !isset($bd_info["openid"])) {
                    // return $this->responseJson(0, "登录失败");
                    self::$loginData = false;
                    break;
                }
                //获取会员信息
                $model_member = new Member();
                $member = $model_member->getMemberByBdid($bd_info["openid"]);
                if (!$member) {
                    if ($userInfo) {
                        $user_info['nickname'] = $userInfo['nickName'];
                        $user_info['openid'] = $bd_info["openid"];
                        $user_info['headimgurl'] = $user_info['avatarUrl'];
                        //自动注册
                        $result = $model_member->quickRegisterBd($user_info, SITEID);
                        if (!$result) {
                            self::$loginData = false;
                            break;
                        }
                        self::$is_new = 1;
                        $member = Member::instance()->getMemberByBdid($user_info['openid']);
                    }
                }else {
                    // 2020-09-09 之前（百度小程序还未正式上线）注册并有过绑定操作的账号 需要恢复state状态
                    if(!$member->member_state && $member->member_time < strtotime(date('2020-09-09'))) {
                        $member->member_state = 1;
                        $member->save();
                    }
                }
                self::$loginData = $member;
                break;
            default:
                self::$loginData = false;
                 $this->message = 'applets_type 不支持';
                break;
        }
    }

    public function getLoginResult()
    {
        return self::$loginData;
    }


}