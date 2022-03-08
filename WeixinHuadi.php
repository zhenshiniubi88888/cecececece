<?php

namespace common\components;

use common\models\Agent;
use linslin\yii2\curl\Curl;
use yii\base\Component;

/**
 * @author gandalf
 * Class HuawaApi
 * @package common\components
 */
class WeixinHuadi extends Component
{
    public $cache_key = 'huadi_token_2020';
    /**
     * 单例对象
     */
    private static $instance = null;
    private $access_token = null;

    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function get_token()
    {
        $token = cache($this->cache_key);
        if (empty($token)) {
            $access_token = $this->save_token();
        } else {
            $access_token = $token['access_token'];
        }
        $this->access_token = $access_token;
        return $access_token;
    }

    /**
     * 保存token
     * @return mixed
     */
    function save_token()
    {
        $tokens = $this->_get_token();
        $data = array();
        $data['access_token'] = $tokens['access_token'];
        $data['expires_in'] = $tokens['expires_in'];
        $data['time'] = time();
        cache($this->cache_key, $data, 6000);
        return $tokens['access_token'];
    }

    /**
     * 通过接口获取token
     * @return array
     */
    function _get_token()
    {
        $appid = HUADI_APPLET_APPID;
        $appaecret = HUADI_APPLET_APPSECRET;
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appaecret}";
        $data = sendGet($url);
        $result = (array)json_decode($data);
        if (isset($result['errcode'])) {
            echo $result['errmsg'];
            die;
        } else {
            return $result;
        }
    }

    /**
     * 获取access_token
     */
    public function get_access_token()
    {
        // todo 测试服不能获取 token，会与线上的冲突，请从线上拷贝token
        if (IS_TEST) {
            //return '28_Mo1ILAO8eyw0NOpxAEjjItpp5FaFztXDc7JzqZrWFbuLfTigjKHY0O5Mya9mienmNW7hi0q3FYWXgGnBGXuffBRpRIsBBYxlyypG-bhOUMbP5AxXv4OrPP8EyiJ-bMvbDHA1lJgfsCz-ueliHCWdABAAVP';
            return false;
        }

        $access_token = cache('menus_accesstoken');
        if (empty($access_token)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=wxdd58ad88c4ac1921&secret=3324336f4c9664d6c7e2f77f307f1e2a";
            $data = $_data = sendGet($url);
            $data = (array)json_decode($data);
            if(isset($data['access_token'])){
                cache('menus_accesstoken', $data['access_token'], 7000);
                return $data['access_token'];
            }else{
                return "获取access_token错误";
            }
        } else {
            return $access_token;
        }
    }
    public function getSignPackage($current_url = '')
    {
        $jsapiTicket = $this->getJsApiTicket();

        // 注意 URL 一定要动态获取，不能 hardcode.
//        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
//        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $url = $current_url;

        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
            "debug" => true,
            "appId" => 'wxdd58ad88c4ac1921',
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            //"rawString" => $string
        );
        return $signPackage;
    }

    private function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getJsApiTicket()
    {
        // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
            $accessToken = $this->get_access_token();
            // 如果是企业号用以下 URL 获取 ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
            $res = json_decode(sendGet($url));
            $res->ticket;

        return $res->ticket;
    }
}