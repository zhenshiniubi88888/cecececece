<?php


namespace frontend\service;


use common\components\Log;

class BaiduService
{
    //爱花居  ahj
    //爱鲜花店 axh
    //52鲜花  waxh
    //花旺    hw
    private static $config_arr = [
        'ahj'   => [
            'appId'=>'16592049',
            'appKey'=>'7HnGsejajbwuoF940CG0QsGnRq0AkkQD',
            'appSecret'=>'QNFEUd1GGdTqq5tBZKGvfY8y0hMUe2VK',
        ],
        'axh'   => [
            'appId'=>'17726794',
            'appKey'=>'jZcYkmpKAGoOrS6AO7wHjU2SGWCtDKjt',
            'appSecret'=>'qH1kYBWSCTpvo19bIE6xEeLVQHkaTDvD',
        ],
        'waxh'   => [
            'appId'=>'17726723',
            'appKey'=>'XuaGC7ND0eVXMLGzO9DEgD3sanuBZbYC',
            'appSecret'=>'0EG5TQuwO81WCFAueBpy7c4FMfknZ9LD',
        ],
        'hw'   => [
            'appId'=>'17726660',
            'appKey'=>'5LUAteI6WclRG8NCPfkI17gnRAZMFtLj',
            'appSecret'=>'jmnt1RRevNaRuEnadstXVRAIZS3amxXY',
        ],
        'huadi' => [
            'appId'=>'21845389',
            'appKey'=>'OvErEDDBSdjODh1sgudAcqESyfTlhKHt',
            'appSecret'=>'ijr0ezD1Narzs7YymzBW5UZxuAwVdnNC',
        ]
    ];

    /**
     * 智能小程序在其服务端中发送POST请求到百度 OAuth2.0 授权服务地址，并带上对应的参数，便可获取到Session Key。
     * openid    用户身份标识，由 appid 和 uid 生成。
     * 不同用户登录同一个小程序获取到的 openid 不同，同一个用户使用登录不同一个小程序获取到的 openid 也不同。
     * session_key    用户的Session Key
     * @param $code
     * @return mixed
     */
    public static function getSessionKey($code,$type='ahj')
    {
        $gateway = "https://spapi.baidu.com/oauth/jscode2sessionkey";
        $data = array();
        $data['code'] = $code;//通过上面第一步所获得的Authorization Code
        $data['client_id'] = self::$config_arr[$type]['appKey'];//智能小程序的App Key
        $data['sk'] = self::$config_arr[$type]['appSecret'];//智能小程序的App Secret
        $bd_info = sendPost($gateway, $data);
        Log::writelog('huadi_login_fail',var_export($bd_info, true) . PHP_EOL);
        return json_decode($bd_info, true);
    }

    /**
     * 数据解密：低版本使用mcrypt库（PHP < 5.3.0），高版本使用openssl库（PHP >= 5.3.0）。
     *
     * @param string $ciphertext 待解密数据，返回的内容中的data字段
     * @param string $iv 加密向量，返回的内容中的iv字段
     * @param string $app_key 创建小程序时生成的app_key
     * @param string $session_key 登录的code换得的
     * @return string | false
     */
    public static function decrypt($ciphertext, $iv, $session_key)
    {
        $session_key = base64_decode($session_key);
        $iv = base64_decode($iv);
        $ciphertext = base64_decode($ciphertext);

        $plaintext = false;
        if (function_exists("openssl_decrypt")) {
            $plaintext = openssl_decrypt($ciphertext, "AES-192-CBC", $session_key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        } else {
            $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, null, MCRYPT_MODE_CBC, null);
            mcrypt_generic_init($td, $session_key, $iv);
            $plaintext = mdecrypt_generic($td, $ciphertext);
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        }
        if ($plaintext == false) {
            return false;
        }

        // trim pkcs#7 padding
        $pad = ord(substr($plaintext, -1));
        $pad = ($pad < 1 || $pad > 32) ? 0 : $pad;
        $plaintext = substr($plaintext, 0, strlen($plaintext) - $pad);

        // trim header
        $plaintext = substr($plaintext, 16);
        // get content length
        $unpack = unpack("Nlen/", substr($plaintext, 0, 4));
        // get content
        $content = substr($plaintext, 4, $unpack['len']);
        // get app_key
        $app_key_decode = substr($plaintext, $unpack['len'] + 4);

        return self::AppKey == $app_key_decode ? $content : false;
    }
}