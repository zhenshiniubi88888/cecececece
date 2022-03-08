<?php
/**
 * 阿里（支付宝）相关服务
 * PHP version 5.6
 *
 * @category AliService
 * @package  PHP_CodeSniffer
 * @author   wan.li <lswwyp@qq.com>
 * @license  https://spdx.org/licenses/Apache-2.0.html Apache-2.0
 * @version  GIT: 5.6.4
 */

namespace frontend\service;

use AlipaySystemOauthTokenRequest;
use Api\Alipay\Alipay;
use common\components\Log;
use common\models\Member;
use yii\db\Exception;

class AliService
{
    public static $is_new = 0;

    private static $DebugTag = 'AliService';

    /**
     * @var
     */
    private static $userInfo = [];

    public function __construct()
    {
    }

    /**
     * 通过code 换取userid
     * @param $code
     * @param string $type
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public static function jscode2session($code, $type = 'huadi')
    {
        try {
            $client = new Alipay();
            $client->aop->appId = \Yii::$app->params[$type]['ali']['appid'];
            $client->aop->signType = \Yii::$app->params[$type]['ali']['sign_type'];
            $client->aop->rsaPrivateKey = \Yii::$app->params[$type]['ali']['private_key'];
            $client->aop->alipayrsaPublicKey = \Yii::$app->params[$type]['ali']['alipay_public_key'];
            $request = new AlipaySystemOauthTokenRequest();
            $request->setCode($code);
            $request->setGrantType("authorization_code");
            $result = $client->aop->execute($request);
            log::writelog('ali-HdAppletPay',  var_export(\Yii::$app->params[$type]['ali'], true) . PHP_EOL);
            log::writelog('ali-HdAppletPay',  var_export($result, true) . PHP_EOL);
            return $result->alipay_system_oauth_token_response->user_id;
        }catch (Exception $exception){
            return  false;
        }
    }

    /**
     * @param $openid
     * @return array|bool|\yii\db\ActiveRecord|null
     */
    public function autoLogin($userid, $userInfo = [])
    {
        $modelMember = new Member();
        $member = $modelMember->getMemberByAlipayid($userid);
        if ($member) {
            return $member;
        }
        //自动注册
        $result = $modelMember->quickRegisterAlipay(
            [
                'nick_name' => $userInfo['nickName'],
                'user_id' => $userid,
                'avatar' => $userInfo['avatarUrl']
            ], SITEID);
        if (!$result) {
            return false;
        }
        self::$is_new = 1;
        $member = Member::instance()->getMemberByAlipayid($userid);
        return $member;
    }
}