<?php

use common\components\HuawaApi;
use common\components\WeixinHuadi;
use common\models\EvaluateGoods;
use common\models\Member;
use common\models\MemberCommon;
use common\models\OrderGoods;
use common\models\Orders;

/**
 * 获取请求参数
 * @param $name
 * @param $default
 * @return string
 */
function Request($name, $default = '')
{
    static $request_params = null;
    if (is_null($request_params)) {
        $get = Yii::$app->request->get();
        $post = Yii::$app->request->post();
        $request_params = array_merge($get, $post);
    }
    return isset($request_params[$name]) ? $request_params[$name] : $default;
}



/**
 * 手机号验证
 * @param $mobile
 * @return int
 */
function isMobile($mobile)
{
    return preg_match('/^1[3-9][0-9]{9}$/', $mobile);
}

/**
 * 用户名检测
 * @param $name
 * @return int
 */
function isUsername($name)
{
    return preg_match("/^(\w|[\x{4e00}-\x{9fa5}]){1,16}$/u", $name);
}
/**
 * 邮箱检测
 * @param $email
 * @return int
 */
function isEmail($email)
{
    return strlen($email) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $email);
}

/**
 * 图片名检测
 * @param $name
 * @return int
 */
function isImgName($name)
{
    return preg_match("/^(\w|\/)+\.(jpg|png|gif|jpeg|bmp)$/u", $name);
}

/**
 * 图片名检测  只验证类型  微信传图片可能会有afad.afdasf.asd.png 这种格式的 则 isImgName不适用
 * @param $name
 * @return int
 */
function isImgName_wx($name)
{
    return preg_match("/^(.*)\.(jpg|png|jpeg)$/u", $name);
}
/**
 * 验证UUID
 * @param $uuid
 * @return int
 */
function isUuid($uuid)
{
    return preg_match("/^[0-9A-Z]{8}-[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{12}$/i", $uuid);
}

/**
 * 手机号格式化
 * @param type $mobile
 */
if (!function_exists('mobile_format')) {
    function mobile_format($mobile, $str = false)
    {
        if ($str) {
            //替换文本中的手机号
            return preg_replace('/([0-9]{11,})|([0-9]{3,4}-[0-9]{7,10})|([0-9]{3,4}-[0-9]{2,5}-[0-9]{2,5})/', '', $mobile);
        } else {
            if (!isMobile($mobile)) return $mobile;
            return substr_replace($mobile, '****', 3, 4);
        }
    }
}
/**
 * 姓名隐藏
 * @param $name
 * @return mixed.
 */
function name_format($name)
{
    if (mb_strlen($name, 'utf-8') < 2) {
        return $name;
    }
    if (strpos($name, '*') !== false) {
        return $name;
    }
    $len = mb_strlen($name, 'utf-8');
    $start = mb_substr($name, 0, ceil($len / 3));
    $end = mb_substr($name, floor($len - $len / 3), ceil($len / 3));
    return $start . '**' . $end;
}

function replaceSpecialChar($strParam){
    $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
    return filterEmoji(preg_replace($regex,"",$strParam));
}

function is_utf8($word)
{
    if (preg_match("/^([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}/", $word) == true || preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}$/", $word) == true || preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){2,}/", $word) == true) {
        return true;
    } else {
        return false;
    }
}

/**
 * 地址隐藏
 * @param $name
 * @return mixed
 */
function address_format($address)
{
    if (mb_strlen($address, 'utf-8') < 2) {
        return $address;
    }
    $address = mb_substr($address, 0, floor(mb_strlen($address, 'utf-8') / 3) * 2, 'utf-8') . '***';
//    $address = preg_replace('/(\d)/', '*', $address);
    return $address;
}

if (!function_exists('cache')) {
    /**
     * 缓存管理
     * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
     * @param mixed $value 缓存值
     * @param mixed $options 缓存参数
     * @return mixed
     */
    function cache($name, $value = '', $options = null)
    {
        $name = API_DOMAIN . $name;
        static $cache_store;
        $cache = Yii::$app->cache;
        if ('' === $value) {
            if (isset($cache_store[$name])) return $cache_store[$name];
            // 获取缓存
            return $cache->get($name);
        } elseif (is_null($value)) {
            if (isset($cache_store[$name])) unset($cache_store[$name]);
            // 删除缓存
            return $cache->delete($name);
        } else {
            // 缓存数据
            if (is_array($options)) {
                $expire = isset($options['expire']) ? $options['expire'] : null; //修复查询缓存无法设置过期时间
            } else {
                $expire = is_numeric($options) ? $options : null; //默认快捷缓存设置过期时间
            }
            $cache_store[$name] = $value;
            return $cache->set($name, $value, $expire);
        }
    }
}
if (!function_exists('getImgUrl')) {
    /**
     * 获取图片绝对路径
     * @param $filename
     * @param string $dir
     * @return string
     */
    function getImgUrl($filename, $dir = ATTACH_PATH, $oss = true)
    {
        if (!preg_match('/^http/', $filename)) {
            if ($oss) {
                $filename = UPLOAD_SITE_URL . DS . $dir . DS . $filename;
            } else {
                $filename = LOCAL_SITE_URL . DS . $dir . DS . $filename;
            }
        }
        return $filename;
    }
}
if (!function_exists('array_sort')) {
    function array_sort($arr,$keys,$type = 'asc'){
        $key_value = $new_array = array();
        foreach($arr as $k => $v){
            $key_value[$k] = $v[$keys];
        }
        if($type == 'asc'){
            asort($key_value);
        }else{
            arsort($key_value);
        }
        reset($key_value);
        foreach ($key_value as $k => $v){
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }
}
if (!function_exists('array_column')) {
    function array_column($array, $columnKey, $indexKey = null)
    {
        $result = array();
        foreach ($array as $subArray) {
            if (is_null($indexKey) && array_key_exists($columnKey, $subArray)) {
                $result[] = is_object($subArray) ? $subArray->$columnKey : $subArray[$columnKey];
            } elseif (array_key_exists($indexKey, $subArray)) {
                if (is_null($columnKey)) {
                    $index = is_object($subArray) ? $subArray->$indexKey : $subArray[$indexKey];
                    $result[$index] = $subArray;
                } elseif (array_key_exists($columnKey, $subArray)) {
                    $index = is_object($subArray) ? $subArray->$indexKey : $subArray[$indexKey];
                    $result[$index] = is_object($subArray) ? $subArray->$columnKey : $subArray[$columnKey];
                }
            }
        }
        return $result;
    }
}
if (!function_exists('thumbGoods')) {
    /**
     * 取得商品缩略图的完整URL路径，接收商品信息数组，返回所需的商品缩略图的完整URL
     *
     * @param array $goods 商品信息数组
     * @param string $type 缩略图类型  值为60,240,360,1280
     * @return string
     */
    function thumbGoods($file = '', $type = '')
    {
        if (preg_match('/^http/', $file)) return $file;

        $type_array = explode(',_', ltrim(GOODS_IMAGES_EXT, '_'));
        if (!in_array($type, $type_array)) {
            $type = '320';
        }
        if (empty($file)) {
            return defaultGoodsImage($type);
        }
        return getImgUrl(str_ireplace('.', '_' . $type . '.', $file), ATTACH_GOODS, false);
    }
}
function https_request($url, $data = null){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if (!empty($data)){
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($curl);
    curl_close($curl);
    return $output;
}

// 记录和统计时间（微秒）
function addUpTime($start, $end = '', $dec = 3)
{
    static $_info = array();
    if (!empty($end)) { // 统计时间
        if (!isset($_info[$end])) {
            $_info[$end] = microtime(TRUE);
        }
        return number_format(($_info[$end] - $_info[$start]), $dec);
    } else { // 记录时间
        $_info[$start] = microtime(TRUE);
    }
}

if (!function_exists('thumbRealPic')) {
    /**
     * 获取实拍图片
     * @param $images
     * @param int $width
     * @param int $height
     * @return array
     */
    function thumbRealPic($images, $width = 60, $height = 60)
    {
        if ($width == 60 && $height == 60) {
            $width = 120;
            $height = 120;
        }
        $tmp = array();
        foreach ($images as $image) {
            if (preg_match('/^http/', $image)) {
                $tmp[] = $image;
                continue;
            }
            $imagename = basename($image);//201803012351087693.png
            $tmp_arr = explode(".", $imagename);
            $filename = $tmp_arr[0];//201803012351087693
            $filename = str_replace('huawa_', '', $filename);
            $ext = $tmp_arr[1];//.png
            $thumb_name = 'thumb';
            if ($width == 450 && $height = 450) {
                $thumb_name = 'a';
            }
            $oss_thumb_path = ATTACH_STORE . DS . $thumb_name . DS . $filename . "_{$width}_{$height}." . $ext;
            $tmp[] = UPLOAD_SITE_URL . DS . $oss_thumb_path;
        }
        return $tmp;
    }
}
if (!function_exists('defaultGoodsImage')) {
    /**
     * 取得商品默认大小图片
     *
     * @param string $key 图片大小 small tiny
     * @return string
     */
    function defaultGoodsImage($key)
    {
        $file = str_ireplace('.', '_' . $key . '.', DEFAULT_GOODS_IMAGE);
        return getImgUrl($file, ATTACH_COMMON);
    }
}
if (!function_exists('lastGoodsPrice')) {
    /**
     * 计算最终商品价格
     * @param $goods
     * @return mixed
     */
    function finalGoodsPrice($goods)
    {
        return $goods['goods_price'];
    }
}
if (!function_exists('lastGoodsSale')) {
    /**
     * 获取商品的销量
     * @param $goods
     * @return mixed
     */
    function lastGoodsSale($goods)
    {
       // return $goods['goods_custom_salenum'];
        return $goods['goods_salenum'] + $goods['goods_custom_salenum'];
    }
}
if (!function_exists('getMemberAvatar')) {
    /**
     * 获取用户头像
     * @param $member_id
     * @return string
     */
    function getMemberAvatar($member_avatar = '',$is_fenxiao = 0)
    {
        if ($member_avatar == ''){
            if($is_fenxiao == 1){
                return 'http://q.00f.cn/images/nick_header.jpg';
            }elseif(SITEID == 258){
//                return 'http://i.ahj.cm/images/default_huadi.jpg';
                return 'http://i.ahj.cm/images/default_huadi_new.png';
            }else{
                return 'http://i.ahj.cm/images/default_x80.jpg';
            }
        }
        return getImgUrl($member_avatar, ATTACH_AVATAR, false);
    }
}
if (!function_exists('getMemberName')) {
    /**
     * 获取用户名称
     * @param $member_id
     * @return string
     */
    function getMemberName($member_info)
    {
        if (isset($member_info['member_nickname']) && trim($member_info['member_nickname'])) {
            return mobile_format($member_info['member_nickname']);
        }
        if (isset($member_info['member_name']) && trim($member_info['member_name'])) {
            return mobile_format($member_info['member_name']);
        }
        if (isset($member_info['member_mobile']) && trim($member_info['member_mobile'])) {
            return mobile_format($member_info['member_mobile']);
        }
        return isset($member_info['member_id']) ? '用户' . $member_info['member_id'] : '匿名';
    }
}

if (!function_exists('arraySort')) {
    /**
     * @param $arr
     * @param $keys
     * @param int $orderby
     * @param string $key
     * @return array
     */
    function arraySort($arr, $keys, $orderby = SORT_ASC, $key = 'no')
    {
        $keysvalue = $new_array = array();
        foreach ($arr as $k => $v) {
            $keysvalue[$k] = $v[$keys];
        }
        if ($orderby == SORT_ASC) {
            asort($keysvalue);
        } else {
            arsort($keysvalue);
        }
        reset($keysvalue);
        foreach ($keysvalue as $k => $v) {
            if ($key == 'yes') {
                $new_array[$k] = $arr[$k];
            } else {
                $new_array[] = $arr[$k];
            }
        }
        return $new_array;
    }
}
if (!function_exists('dump')) {
    /**
     * 浏览器友好的变量输出
     * @access public
     * @param  mixed $var 变量
     * @param  boolean $echo 是否输出(默认为 true，为 false 则返回输出字符串)
     * @param  string|null $label 标签(默认为空)
     * @param  integer $flags htmlspecialchars 的标志
     * @return null|string
     */
    function dump($var, $echo = true, $label = null, $flags = ENT_SUBSTITUTE)
    {
        $label = (null === $label) ? '' : rtrim($label) . ':';

        ob_start();
        var_dump($var);
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', ob_get_clean());

        if (IS_CLI) {
            $output = PHP_EOL . $label . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, $flags);
            }

            $output = '<pre>' . $label . $output . '</pre>';
        }

        if ($echo) {
            echo($output);
            return;
        }

        return $output;
    }
}
if (!function_exists('getWeekText')) {
    /**
     * 获取星期
     * @param $time
     * @param string $format
     * @return string
     */
    function getWeekText($time, $format = '周')
    {
        $week = ['日', '一', '二', '三', '四', '五', '六'];
        return sprintf("%s%s", $format, $week[date('w', $time)]);
    }
}
if (!function_exists('getFriendlyTime')) {
    /**
     * 格式化时间输出
     * @param $timestamp
     * @return string
     */
    function getFriendlyTime($timestamp, $format = 'Y-m-d H:i:s')
    {
        $dur = TIMESTAMP - $timestamp;
        if ($dur < 0 || $dur > 31536000) {
            return date($format, $timestamp);
        } else {
            if ($dur < 30) {
                return '刚刚';
            } elseif ($dur < 60) {
                return $dur . '秒前';
            } elseif ($dur < 3600) {
                return floor($dur / 60) . '分钟前';
            } elseif ($dur < 86400) {
                return floor($dur / 3600) . '小时前';
            } elseif ($dur < 259200) {
                return floor($dur / 86400) . '天前';
            } else {
                return date($format, $timestamp);
            }
        }
    }
}

/**
 * 变量替换
 * @param $message
 * @param $param
 * @return bool|mixed
 */
function replaceText($message, $param)
{
    if (!is_array($param)) return false;
    foreach ($param as $k => $v) {
        $message = str_replace('{$' . $k . '}', $v, $message);
    }
    return $message;
}


/**
 * 发送短信验证码
 * @param $mobile
 * @param $message
 * @return bool
 */
function sendSmsCode($mobile, $message, $sign = '')
{
    return sendSms($mobile, $message, 2, $sign);
}

/**
 *
 * 发送短信
 */
function sendSms($mobile, $message, $type = 1, $sign = '')
{
    if(!$sign) $sign = '爱花居鲜花店';

    if ($type === 1) {
        $CorpID = 'CDJS008666';//营销
        $pwd = 'zm0513@';
    } else {
        $CorpID = 'CDJS010384';//验证码
        $pwd = 'zm0513@';
    }

    $message = str_replace('视频', 'SHI频', $message);
    $message = str_replace('贺卡', 'HE卡', $message);
    $message = str_replace('微信', 'WEI信', $message);

    $message = $message . '【'.$sign.'】';

    $param = array();
    $param['CorpID'] = $CorpID;
    $param['Pwd'] = $pwd;
    $param['Mobile'] = $mobile;
    $param['Content'] = get_utf8_to_gb($message);
    $param['Cell'] = '666';
    $param['SendTime'] = '';
    $result = _post($param, 'https://sdk2.028lk.com/sdk2/BatchSend2.aspx');
    \common\components\Log::writelog('sendSmsHuadi', $mobile . '|' . $message . '===' . $result. '===' . json_encode($param));
    if (intval($result) < 0) {
        return false;
    }
    return true;
}

function _post($curlPost, $url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_TIMEOUT, 15);
    if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
    $return_str = curl_exec($curl);
    curl_close($curl);
    return $return_str;
}


function get_utf8_to_gb($value)
{
    $value_1 = $value;
    $value_2 = @iconv("utf-8", "gb2312//IGNORE", $value_1);//使用@抵制错误，如果转换字符串中，某一个字符在目标字符集里没有对应字符，那么，这个字符之后的部分就被忽略掉了；即结果字符串内容不完整，此时要使用//IGNORE
    return $value_2;
}


/**
 * 发送短信
 * @param $mobile
 * @param $message
 * @param int $type
 * @return bool
 */
function sendSmsOld($mobile, $message, $type = 1,$sign = '')
{
    if(!$sign) $sign = '爱花居鲜花店';
    // 发送短信地址，以下为示例地址，具体地址询问网关获取
    //$url_send_sms = "http://47.93.40.213:8860/sendSms";
    $url_send_sms = "http://123.58.248.206:8860/sendSms";
    if ($type == 1) {
        //及时短信
        // 用户账号，必填
        $cust_code = "100159";
        // 用户密码，必填
        $cust_pwd = "YDNEWA2B52";
    } else {
        // 用户账号，必填
        $cust_code = "100160";
        // 用户密码，必填
        $cust_pwd = "8V8E2HULXU";
    }
    $message = str_replace('视频', 'SHI频', $message);
    $message = str_replace('贺卡', 'HE卡', $message);
    $message = str_replace('微信', 'WEI信', $message);
    $message = $message . '【'.$sign.'】';
    $sign = $message . $cust_pwd;
    $sign = md5($sign);
    // 长号码，选填
    $sp_code = "";
    // 发送短信
    // 业务标识，选填，由客户自行填写不超过20位的数字
    $uid = "";
    // 是否需要状态报告
    $need_report = "yes";
    $data = array('cust_code' => $cust_code, 'sp_code' => $sp_code, 'content' => $message, 'destMobiles' => $mobile, 'uid' => $uid, 'need_report' => $need_report, 'sign' => $sign);
    $json_data = json_encode($data);
    $resp_data = sendPost($url_send_sms, $json_data);
    $result = json_decode($resp_data);
    \common\components\Log::writelog('sendSms', $mobile . '|' . $message . '===' . $resp_data);
    if (null == $result) return false;
    if ($result->status == 'success') {
        return true;
    } else {
        return false;
    }
}

/**
 * 发送post请求
 * @param $url
 * @param null $data
 * @param array $headers
 * @return mixed
 */
function sendPost($url, $data = null, $headers = [])
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if (!empty($data)) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    $output = curl_exec($curl);
    curl_close($curl);
    return $output;
}

/**
 * 发送get请求
 * @param $url
 * @param array $headers
 * @return mixed
 */
function sendGet($url, $headers = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}

/**
 * 生成短信验证码
 * @param int $len
 * @return string
 */
function createVerifyCode($len = 6)
{
    $code = [];
    $digital = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
    for ($i = 0; $i < $len; $i++) {
        $code[] = $digital[array_rand($digital)];
    }
    return implode('', $code);
}

/**
 * 对需要加密的明文进行填充补位
 * @param $text 需要进行填充补位操作的明文
 * @return 补齐明文字符串
 */
function encode($text)
{
    $block_size = 32;
    $text_length = strlen($text);
    //计算需要填充的位数
    $amount_to_pad = $block_size - ($text_length % $block_size);
    if ( $amount_to_pad == 0 )
        $amount_to_pad = $block_size;
    //获得补位所用的字符
    $pad_chr = chr($amount_to_pad);
    $tmp = "";
    for ( $index = 0; $index < $amount_to_pad; $index++ )
        $tmp .= $pad_chr;
    return $text . $tmp;
}

/**
 * 对解密后的明文进行补位删除
 * @param decrypted 解密后的明文
 * @return 删除填充补位后的明文
 */
function decode($text)
{
    $pad = ord(substr($text, -1));
    if ($pad < 1 || $pad > 32)
        $pad = 0;
    return substr($text, 0, (strlen($text) - $pad));
}
/**
 * 获取ip
 * @return mixed|string
 */
function getIp()
{
    if (isset($_SERVER['HTTP_ALI_CDN_REAL_IP']) && $_SERVER['HTTP_ALI_CDN_REAL_IP'] && strcasecmp($_SERVER["HTTP_ALI_CDN_REAL_IP"], "unknown")) {
        $ip = $_SERVER["HTTP_ALI_CDN_REAL_IP"];
    } else if (isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] && strcasecmp($_SERVER["HTTP_CLIENT_IP"], "unknown")) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER["HTTP_X_FORWARDED_FOR"] && strcasecmp($_SERVER["HTTP_X_FORWARDED_FOR"], "unknown")) {
        $ip = current(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]));
    } else if ($_SERVER["REMOTE_ADDR"] && strcasecmp($_SERVER["REMOTE_ADDR"], "unknown")) {
        $ip = $_SERVER["REMOTE_ADDR"];
    } else {
        $ip = "127.0.0.1";
    }
    return $ip;
}

/**
 * 价格格式化 19年12月需求取消多余零显示
 * @param int $price
 * @return string    $price_format
 * @return int    $tail
 */
function priceFormat($price, $tail = 2)
{
    //针对双11价格为11.11的商品做特殊返回处理
    if(time() >= strtotime('20201030') && time() <= strtotime('20201112') && $price == 11.11){
        return $price;
    }
    $price_format = number_format($price, $tail, '.', '');
//    $price_format = floatval($price_format);
    $price_format = intval($price_format);
    return $price_format;
}

/**
 * 是否是微信浏览器
 * @return bool
 */
function isWeixin()
{
    if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'micromessenger') !== false) {
        return true;
    } else {
        return false;
    }
}

/**
 * 是否是App
 * @return bool
 */
function isApp()
{
    return preg_match('/(iOS_APP|ANDROID_APP)/i', $_SERVER['HTTP_USER_AGENT']);
}

/**
 * 是否是iOS
 * @return bool
 */
function isIOS()
{
    return preg_match('/(iOS_APP)/i', $_SERVER['HTTP_USER_AGENT']);
}

/**
 * 是否是Android
 * @return bool
 */
function isAndroid()
{
    return preg_match('/(ANDROID_APP)/i', $_SERVER['HTTP_USER_AGENT']);
}

/**
 * 获取session_key
 * @param $appid
 * @param $appaecret
 * @param $code
 * @return array
 */
function _get_session_key($appid,$appaecret,$code)
{
    $url = "https://api.weixin.qq.com/sns/jscode2session?appid=".$appid."&secret=".$appaecret."&js_code=".$code."&grant_type=authorization_code";
    $data = sendGet($url);
    $result = (array)json_decode($data);
    if(isset($result['session_key'])){
        return $result['session_key'];
    }
    return $result;
}

function isWap()
{
    if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])) {
        return true;
    } elseif (isset ($_SERVER['HTTP_VIA'])) {
        return true;
    } elseif (preg_match('/(nokia|sony|ericsson|mot|samsung|htc|sgh|lg|sharp|sie-|philips|panasonic|alcatel|lenovo|iphone|ipod|blackberry|meizu|android|netfront|symbian|ucweb|windowsce|palm|operamini|operamobi|openwave|nexusone|cldc|midp|wap|mobile|mi|huawei)/i', $_SERVER['HTTP_USER_AGENT'])) {
        return true;
    }
    return false;

}

/**
 * 来源客户端
 * @return int
 */
function fromAgent()
{
    if (isWeixin()) {
        return 4;
    } elseif (isApp()) {
        return 3;
    } elseif (isWap()) {
        return 2;
    } else {
        return 1;
    }
}

/**
 * 数字转中文¬
 * @param $num
 * @return mixed|string
 */
function numToWord($num)
{
    $chiNum = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
    $chiUni = array('', '十', '百', '千', '万', '亿', '十', '百', '千');
    $chiStr = '';
    $num_str = (string)$num;
    $count = strlen($num_str);
    $last_flag = true; //上一个 是否为0
    $zero_flag = true; //是否第一个
    $temp_num = null; //临时数字

    $chiStr = '';//拼接结果
    if ($count == 2) {//两位数
        $temp_num = $num_str[0];
        $chiStr = $temp_num == 1 ? $chiUni[1] : $chiNum[$temp_num] . $chiUni[1];
        $temp_num = $num_str[1];
        $chiStr .= $temp_num == 0 ? '' : $chiNum[$temp_num];
    } else if ($count > 2) {
        $index = 0;
        for ($i = $count - 1; $i >= 0; $i--) {
            $temp_num = $num_str[$i];
            if ($temp_num == 0) {
                if (!$zero_flag && !$last_flag) {
                    $chiStr = $chiNum[$temp_num] . $chiStr;
                    $last_flag = true;
                }
            } else {
                $chiStr = $chiNum[$temp_num] . $chiUni[$index % 9] . $chiStr;

                $zero_flag = false;
                $last_flag = false;
            }
            $index++;
        }
    } else {
        $chiStr = $chiNum[$num_str[0]];
    }
    return $chiStr;
}

/**
 * 获取配送时段
 * @param string $select
 * @return array|mixed
 */
function getPeriod($select = '', $isup = false, $minutes = 0)
{
    if ($isup) {
        return $select . ':' . $minutes;
    }
    $period = [
        "20" => '立即送',
        "0" => '全天配送',
        "1" => '全天配送',
        "2" => '08-10点',
        "3" => '10-12点',
        "4" => '12-14点',
        "5" => '14-16点',
        "6" => '16-18点',
        "7" => '18-20点',
        "8" => '20-22点',
        "15" => '上午',
        "16" => '下午',
        "17" => '晚上'
    ];

    return isset($period[$select]) ? $period[$select] : $period;
}

/**
 * 获取当天过期时间
 * @param int $period
 * @param int $isup
 * @param int $minutes
 * @return array|int|mixed
 */
function getExpire($period = 1, $isup = 0, $minutes = 0)
{
    $delivery_date = strtotime(date('Y-m-d 00:00:00'));
    if ($isup) {
        $minutes = floor($minutes / 5);
        $minutes = $minutes * 60 * 5;
        $expired_time = $delivery_date + ($period * 3600) + $minutes - 1;
    } else {
        switch ($period) {
            case '2':
                $expired_time = $delivery_date + 10 * 3600 - 1;
                break;
            case '3':
                $expired_time = $delivery_date + 12 * 3600 - 1;
                break;
            case '4':
                $expired_time = $delivery_date + 14 * 3600 - 1;
                break;
            case '5':
                $expired_time = $delivery_date + 16 * 3600 - 1;
                break;
            case '6':
                $expired_time = $delivery_date + 18 * 3600 - 1;
                break;
            case '7':
                $expired_time = $delivery_date + 20 * 3600 - 1;
                break;
            case '8':
                $expired_time = $delivery_date + 22 * 3600 - 1;
                break;
            case '15':
                $expired_time = $delivery_date + 12 * 3600 - 1;
                break;
            case '16':
                $expired_time = $delivery_date + 18 * 3600 - 1;
                break;
            case '17':
                $expired_time = $delivery_date + 22 * 3600 - 1;
                break;
            case '20':
                $expired_time = TIMESTAMP + 3600;
                break;
            default:
                //默认全天配送
                $expired_time = $delivery_date + 22 * 3600 - 1;
                break;
        }
    }
    return $expired_time - $delivery_date;
}

/**
 * 是否过期
 * @param int $period
 *
 * @return bool
 */
function isExpireTime($period = 1){
    $delivery_date = strtotime(date('Y-m-d 00:00:00'));
    switch ($period) {
        case '2':
            $expired_time = $delivery_date + 10 * 3600 - 1;
            break;
        case '3':
            $expired_time = $delivery_date + 12 * 3600 - 1;
            break;
        case '4':
            $expired_time = $delivery_date + 14 * 3600 - 1;
            break;
        case '5':
            $expired_time = $delivery_date + 16 * 3600 - 1;
            break;
        case '6':
            $expired_time = $delivery_date + 18 * 3600 - 1;
            break;
        case '7':
            $expired_time = $delivery_date + 20 * 3600 - 1;
            break;
        case '8':
            $expired_time = $delivery_date + 22 * 3600 - 1;
            break;
        case '15':
            $expired_time = $delivery_date + 12 * 3600 - 1;
            break;
        case '16':
            $expired_time = $delivery_date + 18 * 3600 - 1;
            break;
        case '17':
            $expired_time = $delivery_date + 22 * 3600 - 1;
            break;
        case '20':
            $expired_time = TIMESTAMP + 3600;
            break;
        default:
            //默认全天配送
            $expired_time = $delivery_date + 22 * 3600 - 1;
            break;
    }
    if($expired_time > TIMESTAMP){
        return true;
    }else{
        return false;
    }
}
/**
 * 获取配送过期时间
 * @param $delivery_date
 * @param $period
 * @param int $isup
 * @param int $minutes
 * @return false|int
 */
function getExpireTime($delivery_date, $period = 1, $isup = 0, $minutes = 0)
{
    date_default_timezone_set('PRC');
    $delivery_date = strtotime(trim($delivery_date));
    if ($isup) {
        $minutes = floor($minutes / 5);
        $minutes = $minutes * 60 * 5;
        $expired_time = $delivery_date + ($period * 3600) + $minutes - 1;
    } else {
        switch ($period) {
            case '2':
                $expired_time = $delivery_date + 10 * 3600 - 1;
                break;
            case '3':
                $expired_time = $delivery_date + 12 * 3600 - 1;
                break;
            case '4':
                $expired_time = $delivery_date + 14 * 3600 - 1;
                break;
            case '5':
                $expired_time = $delivery_date + 16 * 3600 - 1;
                break;
            case '6':
                $expired_time = $delivery_date + 18 * 3600 - 1;
                break;
            case '7':
                $expired_time = $delivery_date + 20 * 3600 - 1;
                break;
            case '8':
                $expired_time = $delivery_date + 22 * 3600 - 1;
                break;
            case '15':
                $expired_time = $delivery_date + 12 * 3600 - 1;
                break;
            case '16':
                $expired_time = $delivery_date + 18 * 3600 - 1;
                break;
            case '17':
                $expired_time = $delivery_date + 22 * 3600 - 1;
                break;
            case '20':
                $expired_time = TIMESTAMP + 3600;
                break;
            default:
                //默认全天配送
                $expired_time = $delivery_date + 22 * 3600 - 1;
                break;
        }
    }
    return $expired_time;
}

/**
 * 获取配送时间
 * @param $date
 * @param int $period
 * @param int $isup
 * @param int $minutes
 * @return string
 */
function getDeliveryTime($date, $period = 1, $isup = 0, $minutes = 0)
{
    return $date . ' ' . ($isup ? $period . ':' . $minutes . '(定时配送)' : getPeriod($period));
}

/**
 * 内部数据调用
 * @param string $msg
 * @param bool $state
 * @param array $data
 * @return array
 */
function arrayBack($msg, $state = false, $data = [])
{
    return ['msg' => $msg, 'state' => $state, 'data' => $data];
}

/**
 * 获取支付名称
 * @param $code
 * @return mixed
 */
function paymentName($code)
{
    return str_replace(
        ['qywxpay','wxapppay', 'zxappwxpay', 'zxwxpay', 'wxpay', 'zxpay', 'zxh5pay3', 'zxh5pay2', 'zxh5pay1', 'zxh5pay', 'wxh5pay', 'zxalipay', 'appalipay', 'alipay', 'predeposit','appletwxpay','ahjappletwxpay','ahjappletbdpay'],
        ['企业微信支付','APP微信支付', 'APP微信支付', '公众号微信支付', '公众号微信支付', '公众号微信支付', 'WAP微信支付', 'WAP微信支付', 'WAP微信支付', 'WAP微信支付', 'WAP微信支付', 'WAP支付宝', 'APP支付宝', 'WAP支付宝', '余额支付','微信小程序支付','微信小程序支付','百度小程序支付']
        , $code);
}

/**
 * @param $day
 * @return mixed|string
 */
function weekEn($day)
{
    $week = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    return array_key_exists($day, $week) ? $week[$day] : '';
}

/**
 * @param $day
 * @param string $prefix
 * @return string
 */
function weekZh($day, $prefix = '周')
{
    $week = ['日', '一', '二', '三', '四', '五', '六'];
    return array_key_exists($day, $week) ? $prefix . $week[$day] : '';
}

/**
 * 阿拉伯数字转中文
 * @param $day
 * @param string $prefix
 * @return string
 */
function numToChr($num, $mode = true)
{
    $char = array("零", "一", "二", "三", "四", "五", "六", "七", "八", "九");
    $dw = array("", "十", "百", "千", "", "万", "亿", "兆");
    $dec = "点";
    $retval = "";
    if ($mode)
        preg_match_all("/^0*(\d*)\.?(\d*)/", $num, $ar);
    else
        preg_match_all("/(\d*)\.?(\d*)/", $num, $ar);
    if ($ar[2][0] != "")
        $retval = $dec . numToChr($ar[2][0], false); //如果有小数，先递归处理小数
    if ($ar[1][0] != "") {
        $str = strrev($ar[1][0]);
        for ($i = 0; $i < strlen($str); $i++) {
            $out[$i] = $char[$str[$i]];
            if ($mode) {
                $out[$i] .= $str[$i] != "0" ? $dw[$i % 4] : "";
                if($i>0){
                    if ($str[$i] + $str[$i - 1] == 0)
                        $out[$i] = "";
                }
                if ($i % 4 == 0)
                    $out[$i] .= $dw[4 + floor($i / 4)];
            }
        }
        $retval = join("", array_reverse($out)) . $retval;
    }
    return $retval;
}

/**
 * 取出禁止配送的日期
 * @param array $able_day
 * @return array
 */
function getReverseDay($able_day = [])
{
    $week = [0, 1, 2, 3, 4, 5, 6];
    return array_values(array_diff($week, $able_day));
}

/**
 * 获取首次送达日期
 * HOME_FLOWER_DELIVER_TIME 允许配送的时间
 * HOME_FLOWER_DELIVER_AHEAD_DAYS 需提前预定的时间
 * @return false|string
 */
function getNextFirstTime()
{
    $allow = explode(',', HOME_FLOWER_DELIVER_TIME);
    sort($allow);
    $current = date('w', TIMESTAMP);
//    $next = 0;
//    foreach ($allow as $key => $each) {
//        if ($current >= $each) {
//            $next = $allow[$key];
//            break;
//        }
//    }
    if(in_array($current,[5,6])){
        return date('Y-m-d', strtotime('+1 week last ' . weekEn(1)));
    }elseif(in_array($current,[1,2,3,4])){
        return date('Y-m-d', strtotime(weekEn(6)));
    }elseif(in_array($current,[0])){
        return date('Y-m-d', strtotime('+1 week last ' . weekEn(6)));
    }
}

/**
 * 获取包月鲜花日期
 * @param $first_date
 * @return array
 */
function getNextDeliveryDays($first_date, $max_num = 4)
{
    $next_days = [];
    $current = date('w', strtotime($first_date));
    for ($i = 0; $i < $max_num; $i++) {
        array_push($next_days, strtotime(sprintf("+%s week last " . weekEn($current), $i + 1), strtotime($first_date) - 7 * 86400));
    }
    return $next_days;
}

function urlsafe_b64encode($string)
{
    $data = base64_encode($string);
    $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
    return $data;
}

function urlsafe_b64decode($string)
{
    $data = str_replace(array('-', '_'), array('+', '/'), $string);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    return base64_decode($data);
}


function getWebKey()
{
    return "GLKmS-PqnvpJ684GRU4GxC_sIR-B7U78";
}

/**
 * 获取用户唯一标识
 * @return string
 */
function getWebToken()
{
    if (Yii::$app->user->id) {
        $token = Yii::$app->getSecurity()->encryptByPassword(Yii::$app->user->id, getWebKey());
        $token = urlsafe_b64encode($token);
    } else {
        $token = "";
    }
    return $token;
}


/**
 * 验证身份证号码是否正确
 * @param $id
 * @return bool
 */
function is_idcard($id = "")
{
    $id = strtoupper($id);
    $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
    $arr_split = array();
    if (!preg_match($regx, $id)) {
        return false;
    }
    if (15 == strlen($id)) {//检查15位
        $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";
        @preg_match($regx, $id, $arr_split);
        //检查生日日期是否正确
        $dtm_birth = "19" . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
        if (!strtotime($dtm_birth)) {
            return false;
        } else {
            return true;
        }
    } else {//检查18位
        $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
        @preg_match($regx, $id, $arr_split);
        $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
        if (!strtotime($dtm_birth)) {//检查生日日期是否正确
            return false;
        } else {
            //检验18位身份证的校验码是否正确。
            //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
            $arr_int = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
            $arr_ch = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
            $sign = 0;
            for ($i = 0; $i < 17; $i++) {
                $b = (int)$id{$i};
                $w = $arr_int[$i];
                $sign += $b * $w;
            }
            $n = $sign % 11;
            $val_num = $arr_ch[$n];
            if ($val_num != substr($id, 17, 1)) {
                return false;
            } else {
                return true;
            }
        }
    }
}

/**
 * @param $url
 * @param array $param
 * @return string
 */
function buildUrl($url, $param = [])
{
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($param);
}

/**
 * 字符串强转UTF-8
 * @param $string
 * @return mixed|string
 */
function forceUtf8($string)
{
    //表情等特殊字符
    $string = json_encode($string);
    $string = preg_replace("/(\\\ud[0-9a-f]{3})|(\\\ue[0-9a-f]{3})/is", "#", $string);
    $string = json_decode($string);
    $type = mb_detect_encoding($string, array("ASCII", 'UTF-8', 'GBK', 'LATIN1', "GB2312", 'BIG5'));
    return $type != 'UTF-8' ? mb_convert_encoding($string, "UTF-8", $type) : $string;
}

/**
 * 返回银行卡列表
 * @param int $bank_id
 * @return array|mixed
 */
function bankList($bank_id = 2013)
{
    $bank = [
        "2001" => [
            "code" => "2001",
            "name" => "工商银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/gs.png",
        ],
        "2002" => [
            "code" => "2002",
            "name" => "光大银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/gd.png",
        ],
        "2003" => [
            "code" => "2003",
            "name" => "广发银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/gf.png",
        ],
        "2004" => [
            "code" => "2004",
            "name" => "华夏银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/hx.png",
        ],
        "2005" => [
            "code" => "2005",
            "name" => "建设银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/js.png",
        ],
        "2006" => [
            "code" => "2006",
            "name" => "交通银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/jt.png",
        ],
        "2007" => [
            "code" => "2007",
            "name" => "民生银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/ms.png",
        ],
        "2008" => [
            "code" => "2008",
            "name" => "农村商业银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/nc.png",
        ],
        "2009" => [
            "code" => "2009",
            "name" => "农村信用合作社",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/ncxyc.png",
        ],
        "2010" => [
            "code" => "2010",
            "name" => "农业银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/ny.png",
        ],
        "2011" => [
            "code" => "2011",
            "name" => "浦发银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/pf.png",
        ],
        "2012" => [
            "code" => "2012",
            "name" => "兴业银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/xy.png",
        ],
        "2013" => [
            "code" => "2013",
            "name" => "招商银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/zs.png",
        ],
        "2014" => [
            "code" => "2014",
            "name" => "中国银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/zg.png",
        ],
        "2015" => [
            "code" => "2015",
            "name" => "中信银行",
            "logo" => FENXIAO_DOMAIN . "/images/bankLogo/zx.png",
        ],
    ];
    if ($bank_id > 2000 && $bank_id < 2016) {
        return $bank[$bank_id];
    }
    return $bank;
}

/**
 * 获取银行尾号
 * @param string $bank_card
 * @return bool|string
 */
function get_bank_last_num($bank_card = "")
{
    if (!$bank_card) {
        return "";
    }
    $card = substr($bank_card, -3);
    return $card;
}

/**
 * 爱花居短链接
 * @param $url
 */
function shortUrlaihuaju($url)
{
    $aihuaju_url = 'https://www.aihuaju.com/index.php?act=api&op=get_mshort';
    $param = array(
        'url' => $url
    );
    $result = _post($param, $aihuaju_url);
    //{"error_code":1,"error_msg":"success!","url":"1.00f.cn\/uiXPwQ"}
    $result = json_decode($result, true);
    if ($result['error_code']) {
        return $result['url'];
    }
    return $url;
}

/**
 * 新浪短网址生成
 * @param $url
 * @return string
 */
function shortUrlSina($url)
{
    return shortUrlaihuaju($url);
    $header = array(
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Origin: http://suo.im',
        'Referer: http://suo.im/',
        'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36'
    );
    $data["urlListStr"] = urlencode($url);
    $data["domain"] = "suo.im";
    $data["expireType"] = 5;
    $data["key"] = "0@null";
    $data["mark"] = "3f01799496a2d1a1ee434977b50c0132";
    $data["random"] = "1568178199128";
    $dr = http_build_query($data);
    //   $dr = 'urlListStr=http%253A%252F%252Fwww.huawa.com&domain=suo.im&expireType=5&key=0%40null&mark=3f01799496a2d1a1ee434977b50c0132&random=1568178199128';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "http://create.suo.im/pageHome/createByMulti.htm");
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // 对认证证书来源的检查
    // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $dr);
    $return_str = curl_exec($curl);
    $return_str = json_decode($return_str,true);
    // $curl_no = curl_errno($curl);
    $short_url = "";
    try{
        if($return_str["rtnFlag"] == true){
            $short_url = $return_str["data"][0];
        }
    }catch (\Exception $e){
        $short_url = $url;
    }

    return $short_url;

    // 'Accept: application/json, text/javascript, */*; q=0.01',
   // return $url;
   /* $header = array(

        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Cache-Control: no-cache',
        'Connection: keep-alive',
        'Cookie: Hm_lvt_dccc0c79dec4ca8b022b2809427e6c4d=1568170863; Hm_lpvt_dccc0c79dec4ca8b022b2809427e6c4d=1568170863; BDUSS=5LsYpxDWqhS6Qa9MBVaEd40XcsYs0MzqyMgSwV0hMoQ4iNi/hnhY1oQycjmgSCz1Vsszn4VEFT9CA6GGDNIv9iZNBMbCCl3mNJudw2h9n6FCYmER79EevLRhYtvB1zRLAcv5/+QthTw/weBtYeTVHu1SPll2Wmkq7VI+WXZaaSrtUj5ZdlppKu1SPll2Wmkq7VI+WXZaaSrtUj5ZdlppKu1SPll2Wmkq7VI+WXZaaSp0lWdAHGDIO6azJUGbar3S; Hm_lvt_21aa50f32ac4ec2548e78c74744a61e6=1568170937; Hm_lpvt_21aa50f32ac4ec2548e78c74744a61e6=1568170937',
        'Host: dwz.cn',
        'Origin: https://dwz.cn',
        'Pragma: no-cache',
        'Referer: https://dwz.cn/',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-origin',
        'Token: 29b667610116552be56c94fabe2286be',
        'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36',
    );
    $data["TermOfValidity"] = "long-term";
    $data["Url"] = $url;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "https://dwz.cn/admin/v2/create");
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $return_str = curl_exec($curl);
    $return_str = json_decode($return_str,true);


    // $curl_no = curl_errno($curl);
    $short_url = "";
    try{
        if($return_str["Code"] == 0){
            $short_url = $return_str["ShortUrl"];
        }

    }catch (\Exception $e){
        $short_url = $url;
    }
    return $short_url;*/


    //  return $url;
    //  $result = json_decode(sendGet('http://api.t.sina.com.cn/short_url/shorten.json?source=700135530&url_long=' . $url));
    //  $result = $result && isset($result[0]) ? $result[0] : null;
    //  return isset($result->url_short) ? $result->url_short : '';
}

/**
 * @param $huawa_state
 * @return string
 */
function huawaState($huawa_state)
{
    $state = 'Unknown';
    switch ($huawa_state) {
        case HUAWA_PLACE_ORDER_CANCEL:
            $state = '已取消';
            break;
        case HUAWA_ACCEPT_ORDER_EXPIRE:
            $state = '已取消';
            break;
        case HUAWA_ORDER_EXPIRE:
            $state = '已过期';
            break;
        case HUAWA_ORDER_UNPUBLISHED_UNSPECIFIE:
            $state = '已接单';
            break;
        case HUAWA_ORDER_UNPUBLISHED:
            $state = '指定待接收';
            break;
        case HUAWA_ORDER_MAKING:
            $state = '制作中';
            break;
        case HUAWA_ORDER_DELIVERYING:
            $state = '配送中';
            break;
        case HUAWA_ORDER_STATUS_CONFIRM:
            $state = '确认送达';
            break;
        case HUAWA_ORDER_STATUS_SUCCESS:
            $state = '已完成';
            break;
        default:
            break;
    }
    return $state;
}

/**
 * @param $huawa_state
 * @return string
 */
function huawaState2($huawa_state)
{
    $state = 'Unknown';
    switch ($huawa_state) {
        case HUAWA_PLACE_ORDER_CANCEL:
            $state = '已受理';
            break;
        case HUAWA_ACCEPT_ORDER_EXPIRE:
            $state = '已受理';
            break;
        case HUAWA_ORDER_EXPIRE:
            $state = '已受理';
            break;
        case HUAWA_ORDER_UNPUBLISHED_UNSPECIFIE:
            $state = '已受理';
            break;
        case HUAWA_ORDER_UNPUBLISHED:
            $state = '已受理';
            break;
        case HUAWA_ORDER_MAKING:
            $state = '已受理';
            break;
        case HUAWA_ORDER_DELIVERYING:
            $state = '正在配送';
            break;
        case HUAWA_ORDER_STATUS_CONFIRM:
            $state = '确认送达';
            break;
        case HUAWA_ORDER_STATUS_SUCCESS:
            $state = '订单完成';
            break;
        default:
            break;
    }
    return $state;
}

//判断是不是星期五
function isFriday()
{
    if (HOLIDAY_OPEN) {
        return false;
    }
    if (intval(\common\models\Setting::C('promotion_member_start_date')) && intval(\common\models\Setting::C('promotion_member_end_date'))) {
        if (time() > intval(\common\models\Setting::C('promotion_member_start_date')) and time() < intval(\common\models\Setting::C('promotion_member_end_date'))) {
            define('ANNIVERSARY', 1);//开启16周年日
            return true;
        } else {
            return false;
        }
    } else {
        define('ANNIVERSARY', 0);//开启16周年日
        $wk_day = date('w');
        if (ANNIVERSARY) {
            if ($wk_day == intval(\common\models\Setting::C('promotion_member_day'))) {
                return false;
            } else {
                return true;
            }
        } else {
            if ($wk_day == intval(\common\models\Setting::C('promotion_member_day'))) {
                return true;
            } else {
                return false;
            }
        }
    }
}

/**
 * 获取代理商等级及成本比例
 * @param $price
 * @return array
 */
function getAgentBasePoint2($price)
{
    \common\models\Setting::instance()->getValue('agent_cost_rate',true);
    $agent = [
        "level" => 1,
        "point" => 2,
    ];
    if ($price >= 0 && $price <= 1000) {
        $agent = [
            "level" => 1,
            "point" => 2,
        ];
    } elseif ($price > 1000 && $price <= 2000) {
        $agent = [
            "level" => 2,
            "point" => 2,
        ];
    } elseif ($price > 2000 && $price <= 3000) {
        $agent = [
            "level" => 3,
            "point" => 2,
        ];
    } elseif ($price > 3000 && $price <= 4000) {
        $agent = [
            "level" => 4,
            "point" => 2,
        ];
    } elseif ($price > 4000) {
        $agent = [
            "level" => 5,
            "point" => 2,
        ];
    }
    return $agent;
}

/**
 * 获取平台自定义等级
 * @param int $custom_level
 * @return array|bool|mixed
 */
function getAgentCustomPoint($custom_level = 0,$agent_id = 0){
    $settings = \common\models\Setting::instance()->getValue("agent_cost_rate",true);
    $settings = unserialize($settings);
    if (!$settings) {
        return false;
    }
    $agent_cost_rate = array();
    foreach ($settings as $k => $v) {
        $level_info = explode("|", $v);
        if (count($level_info) != 3) {
            continue;
        }
        if (!$level_info[0] || !$level_info[1] || !$level_info[2]) {
            continue;
        }

        $agent_cost_rate[$level_info[0]] = array(
            'level' => $level_info[0],
            'point' => $level_info[2],
        );
    }
    return $custom_level ? $agent_cost_rate[$custom_level] : $agent_cost_rate;
}

function getAgentBasePoint($price,$custom_level = 0,$agent_id=0)
{
    if($custom_level > 0){
        $base_setting = getAgentCustomPoint($custom_level,$agent_id);
        if($base_setting){
            return $base_setting;
        }
    }
    if(in_array($agent_id,array(10072))){
        $base_setting = getAgentCustomPoint();
        return end($base_setting);
    }

    $base_setting = [
        "level" => 1,
        "point" => 1,
    ];
    $settings = \common\models\Setting::instance()->getValue("agent_cost_rate",true);
    $settings = unserialize($settings);

    if (!$settings) {
        return $base_setting;
    }

    foreach ($settings as $k => $v) {
        $level_info = explode("|", $v);
        if (count($level_info) != 3) {
            break;
        }
        if (!$level_info[0] || !$level_info[1] || !$level_info[2]) {
            break;
        }
        $prices = explode("-", $level_info[1]);
        if (count($prices) != 2) {
            continue;
        }
        if ($prices[0] == 0) {
            if ($price < $prices[1]) {
                $base_setting["level"] = $level_info[0];
                $base_setting["point"] = $level_info[2];
                break;
            }
        } elseif ($prices[0] > 0 && $prices[1] > 0) {
            if ($price < $prices[1] && $price >= $prices[0]) {
                $base_setting["level"] = $level_info[0];
                $base_setting["point"] = $level_info[2];
                break;
            }
        } elseif ($prices[1] == 0) {
            if ($price > $prices[0]) {
                $base_setting["level"] = $level_info[0];
                $base_setting["point"] = $level_info[2];
                break;
            }
        }
    }

    return $base_setting;
}

/**
 * 获取用户分销商等级
 * @param int $level_id
 * @return array|mixed
 */
function getAgentLevel($level_id = 1){
    $level = [
        "1" => [
            "key" => "LV0",
            "name" => "普通代理",
            "low" => "0",
            "high" => "10",
        ],
        "2" => [
            "key" => "LV1",
            "name" => "白银代理",
            "low" => "10",
            "high" => "50",
        ],
        "3" => [
            "key" => "LV2",
            "name" => "黄金代理",
            "low" => "50",
            "high" => "200",
        ],
        "4" => [
            "key" => "LV3",
            "name" => "铂金代理",
            "low" => "200",
            "high" => "500",
        ],
        "5" => [
            "key" => "LV4",
            "name" => "钻石代理",
            "low" => "500",
            "high" => "1000",
        ],
        "6" => [
            "key" => "LV5",
            "name" => "黑金代理",
            "low" => "1000",
            "high" => "10000",
        ],
    ];
    if(isset($level[$level_id])){
        return $level[$level_id];
    }
    return $level;
}

/**
 * 获取代理等级最新信息
 * @param int $level_id
 * @return array|mixed
 */
function getNewAgentLevel($level_id = 1){
    $levels = getAgentLevel(0);
    $settings = \common\models\Setting::instance()->getValue("agent_cost_rate",true);
    $settings = unserialize($settings);
    foreach ($settings as $k => $v) {
        $level_info = explode("|", $v);
        $prices = explode("-", $level_info[1]);
        $levels[$level_info[0]]["point"] = $level_info[2] * 10;
        $levels[$level_info[0]]["low"] = $prices[0];
        $levels[$level_info[0]]["high"] = $prices[1];
    }
    if(isset($levels[$level_id])){
        return $levels[$level_id];
    }
    return $levels;
}

/**
 * 获取代理商商品售价
 * @param $goods_price
 * @param int $agent_id
 * @return float|int|string
 */
function getAgentGoodsPrice($goods_price, $agent_id = 0, $goods_id = 0,$change_holiday = 0)
{
    $agent = new \common\models\Agent();
    $goods_price = $agent->getGoodsPrice($goods_price, $agent_id, $goods_id,$change_holiday);
    return $goods_price;
}

/**
 * 根据ip地址获取用户位置
 * @param string $ip
 * @return array
 */
function getCityByIp($ip = "")
{
    $info = [
        "province" => "",
        "city" => "",
    ];
    if (!$ip) {
        return $info;
    }
    $url = "http://api.map.baidu.com/location/ip?ip=" . $ip . "&ak=umUCiqBuSegddOqTLIL5oLWVR8ZaHHcD&coor=bd09ll";

    $ip = json_decode(file_get_contents($url), true);
    if ($ip["status"] !== 0) {
        return $info;
    }
    $city = $ip["content"]["address_detail"]["city"];
    $province = $ip["content"]["address_detail"]["province"];

    $info["province"] = $province;
    $info["city"] = $city;
    return $info;
}

/**
 * 远程图片下载到本地
 * @param $url
 * @param $new_file
 * @return string
 */
function downloadImage($url, $new_file)
{
    if (file_exists($new_file)) {
        unlink($new_file);
    }
    if (!is_dir($dir = dirname($new_file))) {
        if (false == mkdir($dir, 0777, true)) {
            return false;
        }
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $file = curl_exec($ch);
    curl_close($ch);
    $resource = fopen($new_file, 'w');
    fwrite($resource, $file);
    fclose($resource);
    if (!chmod($new_file, 777)) {
        return false;
    }
    return true;

}

/**
 * 根据URL下载到本地生成略缩图上传到OSS
 * @param $url
 * @param int $max_width
 * @param int $max_height
 * @param int $quality
 * @param string $new_name
 */
function thumbnailByUrl($url, $max_width = 80, $max_height = 80, $quality = 80, $new_name = '')
{
    //每次请求最多执行的次数
    static $times = 0;

    if ($times >= 5) {
        return $url;
    }

    $tmp = explode('.', basename($url));
    $file_name = $new_name ? $new_name : md5(serialize(func_get_args())) . '.' . end($tmp);
    $object = ATTACH_THUMB . DS . $file_name;


    try {
        //OSS存在则直接返回
        if (\huadi\oss\AliOss::getObjectMeta(Yii::$app->params['upload']['driverConfig']['bucket'], $object)) {
            return getImgUrl($file_name, ATTACH_THUMB);
        }

        $local_dir = UPLOAD_PATH . DS . ATTACH_TMP;
        $local_path = $local_dir . DS . $file_name;
        $res = downloadImage($url, $local_path);
        if (false == $res) {
            throw new Exception('file not exists');
        }
        $thumb = \yii\imagine\Image::thumbnail($local_path, $max_width, $max_height);
        $thumb_path = $local_dir . DS . 'tb_' . $file_name;
        $thumb->save($thumb_path, ['quality' => $quality]);
        if (!file_exists($thumb_path)) {
            throw new Exception('file not exists');
        }
        if (!\huadi\oss\AliOss::uploadFile(Yii::$app->params['upload']['driverConfig']['bucket'], $object, $thumb_path)) {
            throw new Exception('upload oss fail');
        }

        if (true) {
            unlink($local_path);//本地原图
            unlink($thumb_path);//本地略缩图
        }

    } catch (Exception $e) {
        return false;
    }

    $times++;

    return getImgUrl($file_name, ATTACH_THUMB);
}

/**
 * 是否开启节日模式
 * @param int $agent_id
 * @return int
 */
function is_holiday($agent_id = 0){
    return 1;
    if(in_array($agent_id,array(10013))){//开启节日模式10292
        return 1;
    }
    return 0;
}
/**
 * 是否显示节日价格
 * @param int $agent_id
 * @return int
 */
function is_holiday_display($agent_id = 0){
    return 1;
    if(in_array($agent_id,array(10010,10013))){//开启节日模式10292
        return 1;
    }
    return 0;
}

/**
 * 下单成功给分销商发送短信通知
 *
 * @param int $order_id
 *
 * @return bool
 */
function send_agent_sms($order_id = 0)
{
    try {
        $agent_order = \common\models\AgentOrder::findOne(["order_id" => $order_id]);
        if (!$agent_order) {
            throw new Exception("4");
        }
        $agent = \common\models\Agent::findOne(["agent_id" => $agent_order->agent_id]);
        if (!$agent) {
            throw new Exception("5");
        }
        $mobile = isset($agent['agent_mobile']) ? trim($agent['agent_mobile']) : '';
        $message = '恭喜又卖出了一笔，获得利润' . $agent_order->agent_amount . '元，加油，离财务自由更进了一步！';
        if (!empty($mobile)) {
            sendSmsOld($mobile, $message, 1, '爱花居分销');
        }
    } catch (Exception $e) {
        \common\components\Log::writeLog(__FUNCTION__, $order_id . "||" . ($e->getMessage()));
    }
    return false;
}

/**
 * 微信发送订单信息
 * @param string $open_id
 * @param array $order
 * @return bool
 */
function send_weixin_order($order_id = 0){
    try{
        $template_id = "P4ye16u-ljcxHvmO9YSj6fzunhGnqvV-uQoIoEUn7sA";
        if($order_id <= 0){
            throw new Exception("1");
        }
        $orders = \common\models\Orders::findOne(["order_id" => $order_id]);
        if(!$orders){
            throw new Exception("2");
        }
        $orderGoods = \common\models\OrderGoods::findOne(["order_id" => $order_id]);
        if(!$orderGoods){
            throw new Exception("3");
        }
        if($orderGoods->goods_id > 0){
            $mgoods =  \common\models\Goods::findOne($orderGoods->goods_id);
            $mgoods->goods_salenum = $mgoods->goods_salenum + $orderGoods->goods_num;
            $mgoods->save();
        }

        $agent_order = \common\models\AgentOrder::findOne(["order_id" => $order_id]);
        if(!$agent_order){
            throw new Exception("4");
        }

        $agent = \common\models\Agent::findOne(["agent_id" => $agent_order->agent_id]);
        if(!$agent){
            throw new Exception("5");
        }

        $member = \common\models\Member::findOne(["member_id" => $orders->buyer_id]);
        if(!$member){
            throw new Exception("6");
        }

        if(!$agent->wx_openid){
            throw new Exception("7");
        }
        $open_id = $agent->wx_openid;

        $order = [];
        $order["goods_name"] = $orderGoods->goods_name;
        $order["goods_price"] = $orders->total_amount;
        $order["agent_price"] = $agent_order->agent_amount;
        $order["order_time"] = $orders->add_time?date("Y-m-d H:i:s",$orders->add_time):"";
        $order["wx_name"] = $member->member_nickname?$member->member_nickname:$member->member_mobile;
        if(!$order["wx_name"]){
            $order["wx_name"] = "用户".$member->member_id;
        }

        $where = [];
        $where["agent_id"] = $agent_order->agent_id;
        $where["account_status"] = [1,2];
        $count = \common\models\AgentAccount::find()->where($where)->count();
        $count = $count>0?$count:0;

        $level = getAgentBasePoint($count,$agent->custom_level,$agent_order->agent_id);
        $limit_count = 0;
        $levelinfo = getNewAgentLevel($level["level"]);
        $next_level = $level["level"]+1;
        if($next_level > 6){
            $levelinfo1 = $levelinfo;
        }else{
            $levelinfo1 = getNewAgentLevel($level["level"]+1);
            $limit_count = $levelinfo1["low"] - $count - 1;
            $limit_count = $limit_count>0?$limit_count:0;
        }
        $order["limit_count"] = $limit_count;
        $order["next_level"] = $levelinfo1["name"];
        $order["next_point"] = $levelinfo1["point"];
        $data = array(
            'first' => array('value' => "您的好友【".$order["wx_name"]."】刚刚在您的店下了一个新订单 \n", 'color' => '#ff0000'),
            'keyword1' => array('value' => $order["goods_name"]. " \n", 'color' => '#333'),
            'keyword2' => array('value' => $order["goods_price"]. "元 \n", 'color' => '#e30e27'),
            'keyword3' => array('value' => $order["agent_price"] . "元 \n", 'color' => '#00b97e'),
            'keyword4' => array('value' => $order["order_time"]. " \n", 'color' => '#333'),
        );
        if($next_level > 6){
            $data["remark"] = array('value' => '您已是'.$order["next_level"].', 优享'.$order["next_point"]."折扣", 'color' => '#ff0000');
        }else{
            $data["remark"] = array('value' => '您还差'.$order["limit_count"]."笔，将升级为".$order["next_level"].", 优享".$order["next_point"]."折扣", 'color' => '#ff0000');
        }
        $web_url = 'http://'.FX_DOMAIN_NAME.'/order/orderlist';
        $access_token = \common\components\Weixin::getInstance()->get_token();
        $template = array(
            'touser' => $open_id,
            'template_id' => $template_id,
            'url' => $web_url,
            'topcolor' => "#7B68EE",
            'data' => $data,
        );

        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $curl = new \linslin\yii2\curl\Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            $json_template
        )->post($url);
        $result = json_decode($response, true);
        if($result["errcode"] == 0 && $result["errmsg"] == "ok"){
            \common\components\Log::writeLog(__FUNCTION__,$order_id."||".var_export($template,true));
            return true;
        }else{
            throw new Exception($result["errmsg"]);
        }
    }catch (Exception $e){
        \common\components\Log::writeLog(__FUNCTION__,$order_id."||".($e->getMessage()));
    }

    return false;
}

/**
 * 微信发送提现信息
 * @param string $cash_id
 * @return bool
 */
function send_weixin_cash($cash_id = 0){
    try{
        $template_id = "e2wuju5KL0cFx8maoMzlKRH5JvJoF-AssR0esu6upnk";
        if($cash_id <= 0){
            throw new Exception("1");
        }
        $cashinfo = \common\models\AgentCash::findOne(["cash_id" => $cash_id]);
        if(!$cashinfo){
            throw new Exception("2");
        }

        $other_cashinfo = \common\models\ShareCash::findOne(["id" => $cashinfo->share_cash_id]);
        if(!$other_cashinfo){
            throw new Exception("3");
        }

        $agent = \common\models\Agent::findOne(["agent_id" => $cashinfo->agent_id]);
        if(!$agent){
            throw new Exception("5");
        }

        if(!$agent->wx_openid){
            throw new Exception("7");
        }
        $open_id = $agent->wx_openid;//oLBrp1OlvxgBtiUElDsdBdxJtPRU;

        $cash = [];
        $cash["wx_name"] = $other_cashinfo->wx_name;
        $cash["agent_name"] = $other_cashinfo->bank_user;
        $cash["cash_time"] = $other_cashinfo->add_time?date("Y-m-d H:i:s",$other_cashinfo->add_time):"";
        $cash["cash_money"] = $other_cashinfo->amount;


       /* $where = [];
        $where["agent_id"] = $cashinfo->agent_id;
        $where["account_status"] = [1,2];
        $count = \common\models\AgentAccount::find()->where($where)->count();
        $count = $count>0?$count:0;

        $level = getAgentBasePoint($count,$agent->custom_level,$cashinfo->agent_id);
        $limit_count = 0;
        $levelinfo = getNewAgentLevel($level["level"]);
        $next_level = $level["level"]+1;
        if($next_level > 6){
            $levelinfo1 = $levelinfo;
        }else{
            $levelinfo1 = getNewAgentLevel($level["level"]+1);
            $limit_count = $levelinfo1["low"] - $count - 1;
            $limit_count = $limit_count>0?$limit_count:0;
        }*/

        $where = [];
        $where["agent_id"] = $cashinfo->agent_id;
        $where["account_status"] = 1;
        $query = \common\models\AgentAccount::find();
        $wait_amount = $query->where($where)->sum("agent_amount");
        $wait_amount = $wait_amount>0?$wait_amount:0;
        $wait_amount = sprintf("%.2f",$wait_amount);



        $where = [];
        $where["agent_id"] = $cashinfo->agent_id;
        $map = ["<>","cash_status",3];
        $where = ["and",$where,$map];
        $query = \common\models\AgentCash::find();
        $cash_amount = $query->where($where)->sum("cash_amount");
        $cash_amount = $cash_amount>0?$cash_amount:0;
        $cash_amount = sprintf("%.2f",$cash_amount);



        $order = [];
        $order["wait_amount"] = $wait_amount;
        $order["cash_amount"] = $cash_amount;
        $data = array(
            'first' => array('value' => $cash["wx_name"].",您有一笔提现已经审核成功，预计1-3个工作日到账。 \n", 'color' => '#ff0000'),
            'keyword1' => array('value' => $cash["agent_name"]. " \n", 'color' => '#333'),
            'keyword2' => array('value' => $cash["cash_time"]. " \n", 'color' => '#e30e27'),
            'keyword3' => array('value' => $cash["cash_money"] . "元 \n", 'color' => '#00b97e'),
        );

        $data["remark"] = array('value' => "您当前的已提现金额为".$cash_amount."元，待结算金额为".$wait_amount."元", 'color' => '#ff0000');

        $web_url = 'http://'.FX_DOMAIN_NAME.'/finance/costlist';
        $access_token = \common\components\Weixin::getInstance()->get_token();
        $template = array(
            'touser' => $open_id,
            'template_id' => $template_id,
            'url' => $web_url,
            'topcolor' => "#7B68EE",
            'data' => $data,
        );

        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $curl = new \linslin\yii2\curl\Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            $json_template
        )->post($url);
        $result = json_decode($response, true);

        if($result["errcode"] == 0 && $result["errmsg"] == "ok"){
            \common\components\Log::writeLog(__FUNCTION__,$cash_id."||".var_export($template,true));
            return true;
        }else{
            throw new Exception($result["errmsg"]);
        }
    }catch (Exception $e){
        \common\components\Log::writeLog(__FUNCTION__,$cash_id."||".($e->getMessage()));
    }

    return false;
}

/**
 * 微信发送二级分销注册成功
 * @param string $open_id
 * @param array $order
 * @return bool
 */
function send_weixin_agent_reg($agent_data = []){
    try{
        $template_id = "aIuHmM1meXyDK9nIwvjSv1B1nqI9x_q5jZ0VJyWOKwk";
        if(empty($agent_data)){
            throw new Exception("1");
        }
        $agent_parent = \common\models\Agent::findOne(["agent_id" => $agent_data['parent_id']]);
        if(!$agent_parent){
            throw new Exception("2");
        }
        if(!$agent_parent->wx_openid){
            throw new Exception("3");
        }
        $open_id = $agent_parent->wx_openid;
        $access_token = \common\components\Weixin::getInstance()->get_token();
        $web_url = 'http://'.FX_DOMAIN_NAME.'/member/agentlist';
        $data = array(
            'first' => array('value' => $agent_data["agent_name"].",已经成为您的下级分销商 \n", 'color' => '#ff0000'),
            'keyword1' => array('value' => $agent_data["agent_mobile"]. " \n", 'color' => '#00b97e'),
            'keyword2' => array('value' => date('Y-m-d H:i:s',$agent_data["register_time"]). " \n", 'color' => '#e30e27'),
            'keyword3' => array('value' => $agent_data["agent_name"]. " \n", 'color' => '#333')

        );

        $data["remark"] = array('value' => "良好的管理您的分销商并设置对应的等级能有效提升您账号下分销商的销售热情，快去联系他吧!", 'color' => '#ff0000');
        $template = array(
            'touser' => $open_id,
            'template_id' => $template_id,
            'url' => $web_url,
            'topcolor' => "#7B68EE",
            'data' => $data,
        );

        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $curl = new \linslin\yii2\curl\Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            $json_template
        )->post($url);
        $result = json_decode($response, true);
        if($result["errcode"] == 0 && $result["errmsg"] == "ok"){
            \common\components\Log::writeLog(__FUNCTION__,json_encode($agent_data)."||".var_export($template,true));
            return true;
        }else{
            throw new Exception($result["errmsg"]);
        }
    }catch (Exception $e){
        \common\components\Log::writeLog(__FUNCTION__,json_encode($agent_data)."||".($e->getMessage()));
    }

    return false;
}
/**
 * 获取当前用户的父级分销比例
 * @param int $agent_id
 * @param int $parent_id
 * @param int $parent_order_num
 * @param int $parent_point
 * @return int|mixed
 */
function get_parent_point($agent_id = 0,$parent_id = 0,$parent_order_num = 0,$parent_point = 0){
    if($parent_id <= 0){
        if($parent_point < 1){
            $parent_point = 1.2;
        }
        return $parent_point;
    }
    if($agent_id <= 0){
        if($parent_point < 1){
            $parent_point = 1.2;
        }
        return $parent_point;
    }

    $where = [];
    $where["agent_id"] = $agent_id;
    $where["parent_id"] = $parent_id;
    $where["level_type"] = 2;
    $agent_childlevel_special = \common\models\AgentChildlevel::find()->where($where)->one();
    if($agent_childlevel_special && $agent_childlevel_special->agent_level_info >= 1){
        $parent_point = $agent_childlevel_special->agent_level_info;
        return $parent_point;
    }

    $where = [];
    $where["parent_id"] = $parent_id;
    $where["level_type"] = 1;
    $agent_childlevel = \common\models\AgentChildlevel::find()->where($where)->asArray()->one();
    if($agent_childlevel){
        $agent_level_info = $agent_childlevel["agent_level_info"];
        $agent_level_info = json_decode($agent_level_info,true);
        krsort($agent_level_info);
        foreach($agent_level_info as $k=>$v){
            if($parent_order_num >= $v["num"] && $v["point"] >= 1){
                $parent_point = $v["point"];
                break;
            }
        }
        return $parent_point;
    }
    if($parent_point < 1){
        $parent_point = 1.2;
    }
    return $parent_point;
}

/**
 * 获取节日成本价格
 * @param int $goods_base_price
 * @return int
 */
function get_festival_base_price($goods_base_price = 0){
    $festival_base_price_point = \common\models\Setting::instance()->getValue('agent_cost_festival',true);
    if(!$festival_base_price_point){
        return $goods_base_price;
    }
    $festival_base_price_point = unserialize($festival_base_price_point);
    foreach($festival_base_price_point as $k=>$v){
        $info = explode("|",$v);
        if(count($info) < 2){
            continue;
        }
        $prices = explode("-",$info[0]);
        if(count($prices) < 2){
            continue;
        }
        $point = $info[1];
        if($goods_base_price > $prices[0] && $goods_base_price <= $prices[1]){
            $goods_base_price = $goods_base_price*$point;
            break;
        }
    }
    return $goods_base_price;
}

/**
 *1.所有参数按键值降序排序
 *2.拼接参数字符串A（key=value）（a=1b=2）
 *3.字符串添加商户store_key和store_identity生成字符串B：A+store_key+store_identity
 *4.将sign作为参数：signature = sign
 * @param $request
 * @param $store_key
 * @param $store_identity
 * @return bool
 * @author Red
 * @date
 */
function check_sign($request, $store_key, $store_identity)
{
    $params = $request;
    $signature = $request["signature"];
    $app_sttr = $store_key . $store_identity;
    unset($params["signature"]);
    if ($signature != get_sign($params, $app_sttr)) {
        return false;
    }
    return true;
}
function get_sign(array $param, $app_str)
{
    ksort($param);
    $string = "";
    foreach ($param as $key => $value) {
        $string .= $key . "=" . $value;
    }
    $splice = $string . $app_str;
    $md5 = strtoupper(md5($splice));
    return $md5;
}

/**
 * 退款通知
 * 1.花递用户取消订单和申请退款 2，指定单花娃花店拒接、退单、超过配送时间未接3.平台流程超时未配送出去
 */
function send_weixin_huadi_refund($order_id, $desc = ''){
    try{
        $template_id = '5JLoVo19abn5RiYbernlfxIKo25dcxJHHxWspFq0UHg';

        if (!$order_id) {
            throw new Exception('参数错误');
        }

        $order = Orders::find()->where(['order_id' => $order_id])->asArray()->one();
        if (!$order) {
            throw new Exception('订单不存在');
        }
        $member = Member::find()->where(['member_id' =>$order['buyer_id']])->asArray()->one();
        if (!$member) {
            throw new Exception('会员不存在');
        }

        $open_id = $member['member_wxopenid'];

        $access_token = \common\components\WeixinHuadi::getInstance()->get_token();

        $data = array(
            'keyword1' => array('value' => $order['order_sn'], 'color' => '#00b97e'),    // 订单编号
            'keyword2' => array('value' => $order['order_amount'], 'color' => '#e30e27'),    // 退款金额
            'keyword3' => array('value' => $desc, 'color' => '#e30e27'),    // 退款原因
            'keyword4' => array('value' => '微信钱包', 'color' => '#e30e27'),    // 退款方式
            'keyword5' => array('value' => date('Y-m-d H:i:s'), 'color' => '#e30e27'),    // 退款时间
        );
        $form_id = getWxFormId($order['buyer_id'], $order_id);
        if (!$form_id) {
            return false;
        }

        $template = array(
            'touser' => $open_id,
            'template_id' => $template_id,
            'data' => $data,
            'form_id' => $form_id
        );
        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $curl = new \linslin\yii2\curl\Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            $json_template
        )->post($url);
        $result = json_decode($response, true);
        if($result["errcode"] == 0 && $result["errmsg"] == "ok"){
            \common\components\Log::writeLog(__FUNCTION__,json_encode($order)."||".var_export($template,true));
            return true;
        }else{
            throw new Exception($result["errmsg"]);
        }
    }catch (Exception $e){
        \common\components\Log::writeLog(__FUNCTION__,json_encode($order)."||".($e->getMessage()));
    }

    return false;
}


/**
 * 取消订单
 * 1. 用户超时未付款
 */
function send_weixin_huadi_cacel_order($order_id, $desc = ''){
    try{
        $template_id = 'hi-PyfUV8dnptT8fNugIQN-qdhUx_8hphR3jFVdLa_c';

        if (!$order_id) {
            throw new Exception('参数错误');
        }

        $order = Orders::find()->where(['order_id' => $order_id])->asArray()->one();
        if (!$order) {
            throw new Exception('订单不存在');
        }
        $member = Member::find()->where(['member_id' =>$order['buyer_id']])->asArray()->one();
        if (!$member) {
            throw new Exception('会员不存在');
        }

        $goods = OrderGoods::find()->select('goods_name')->where(['order_id' => $order_id])->asArray()->one();

        $open_id = $member['member_wxopenid'];

        $access_token = \common\components\WeixinHuadi::getInstance()->get_token();

        $data = array(
            'keyword1' => array('value' => '花递直卖', 'color' => '#00b97e'),    // 商户名称
            'keyword2' => array('value' => $order['order_sn'], 'color' => '#e30e27'),    // 订单编号
            'keyword3' => array('value' => $goods['goods_name'], 'color' => '#e30e27'),    // 商品详情
            'keyword4' => array('value' => date('Y-m-d H:i:s', $order['add_time'])),    // 下单时间
            'keyword5' => array('value' => $desc, 'color' => '#e30e27'),    // 取消原因
        );
        $form_id = getWxFormId($order['buyer_id'], $order_id);
        if (!$form_id) {
            return false;
        }

        $template = array(
            'touser' => $open_id,
            'template_id' => $template_id,
            'data' => $data,
            'form_id' => $form_id
        );
        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $curl = new \linslin\yii2\curl\Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            $json_template
        )->post($url);
        $result = json_decode($response, true);
        if($result["errcode"] == 0 && $result["errmsg"] == "ok"){
            \common\components\Log::writeLog(__FUNCTION__,json_encode($order)."||".var_export($template,true));
            return true;
        }else{
            throw new Exception($result["errmsg"]);
        }
    }catch (Exception $e){
        \common\components\Log::writeLog(__FUNCTION__,json_encode($order)."||".($e->getMessage()));
    }
    return false;
}

/**
 * 发送微信模板消息获取 form_id
 * @param $member_id
 * @param $order_id
 * @return bool|mixed
 */
function getWxFormId($member_id, $order_id)
{
    $key_cache = getWxFormIdCachekey($member_id, $order_id);
    $form_id = cache($key_cache);
    if (!$form_id) {
        return false;
    }
    return $form_id;
}

/**
 * 发送微信模板消息获取 form_id
 * 缓存KEY
 * @param $member_id
 * @param $order_id
 * @return string
 */
function getWxFormIdCachekey($member_id, $order_id)
{
    $key_cache = $member_id .  '_' . $order_id . 'form_id';
    return $key_cache;
}

/**
 * 修改花递花店排序分数
 * @param $store_id
 * @param $type
 * @param $score
 * @return HuawaApi
 */
function updateStoreOrderScore($store_id, $type, $score, $is_update_order_sale = false, $order_sale_count = 0)
{
//    $data = [
//        'store_id' => $store_id,
//        'type' => $type,
//        'score' => $score,
//        'delivery_type' => 3,
//        'is_update_order_sale' => $is_update_order_sale,
//        'order_sale_count' => $order_sale_count,
//    ];

    $scores[] = [
        'type' => $type,
        'score' => $score,
    ];
    $data = [
        'store_id' => $store_id,
        'delivery_type' => 3,
        'scores' => json_encode($scores),
    ];

    $res = HuawaApi::getInstance()->OC('huadi', 'update_store_order_score', $data);
    return $res;
}

/**
 * 根据好评完成修改花递花店排序分数
 * 好评度，交易额
 * @param $store_id
 * @param $type
 * @param $score
 * @return HuawaApi
 */
function updateStoreOrderScoreByEva($store_id)
{
    $model_evaluate_goods = new EvaluateGoods();
    $score = $model_evaluate_goods->getHpRateScore($store_id);

    $scores = [];
    $scores[] = [
        'type' => 1,
        'score' => $score,
    ];
    $condition = [
        'siteid' => 258,
        'delivery_store_id' => $store_id,
        'evaluation_state' => 1,
        'order_state' => ORDER_STATE_EVA,
        'receive_time' => ['<', strtotime('-30 day')],
    ];
    $orders = Orders::find()->select('sum(order_amount) as amount')->where($condition)->asArray()->one();
    $amount = $orders ? $orders['amount'] : 0;
    $score = 0;
    if ($amount >= 100000) {
        $score = 25;
    } elseif ($amount >= 50000 && $amount <= 99999) {
        $score = 20;
    } elseif ($amount >= 10000 && $amount <= 49999) {
        $score = 15;
    } elseif ($amount >= 5000 && $amount <= 9999) {
        $score = 12;
    } elseif ($amount >= 2000 && $amount <= 4999) {
        $score = 10;
    } elseif ($amount >= 1000 && $amount <= 1999) {
        $score = 5;
    } elseif ($amount >= 1 && $amount <= 999) {
        $score = 2;
    }

    // 近30天交易完成订单量
    $condition = [
        'siteid' => 258,
        'delivery_store_id' => $store_id,
        'evaluation_state' => 1,
        'order_state' => ORDER_STATE_EVA,
        'receive_time' => ['<', strtotime('-30 day')],
    ];
    $count = Orders::find()->where($condition)->count();

    $scores[] = [
        'type' => 3, // 近30天交易额
        'score' => $score,
    ];
    $data = [
        'store_id' => $store_id,
        'delivery_type' => 3,
        'scores' => json_encode($scores),
        'is_update_order_sale' => true,
        'order_sale_count' => $count,
    ];
    $res = HuawaApi::getInstance()->OC('huadi', 'update_store_order_score', $data);
    return $res;
}


/**
 * 花递微信发送模板消息，已送达
 * @param string $member_id
 * @param string $order_id
 * @return bool
 */
function huadi_send_weixin_order_confrim_receive($member_id, $order_id){
    try{
        $template_id = "-F6FrlkL9zQDo43oVaKYrSUswrYv8xOLJo_N4tvwd6Q";
        if($member_id <= 0){
            throw new Exception("会员id不能为空");
        }

        if ($order_id <= 0) {
            throw new Exception("订单编号不能为空");
        }
        $member_common = MemberCommon::find()->select('huadi_open_openid')->where(['member_id' => $member_id])->asArray()->one();
        if (!$member_common || !$member_common['huadi_open_openid']) {
            throw new Exception("该用户未关注公众号");
        }

        $order = Orders::find()->select('order_id, order_sn, buyer_name')->where(['order_id' => $order_id])->asArray()->one();
        if (!$order) {
            throw new Exception("该订单不存在");
        }

        $open_id = $member_common['huadi_open_openid'];

        $time = date('Y年m月m日 H:i');
        $data = array(
            'first' => array('value' => "嗨，". $order['buyer_name'] ."，您的花递订单已送达 \n", 'color' => '#ff0000'),
            'keyword1' => array('value' => $order["order_sn"]. " \n", 'color' => '#333'),
            'keyword2' => array('value' => $time. " \n", 'color' => '#333'),
            'remark' => array('value' => "轻奢幸福生活，从花递开始❀
99元/4束包月鲜花，送TA浪漫与惊喜，让爱恒久持续

每天仅3元，点击订阅>>（跳转到生活花） \n", 'color' => '#f30f30'),
        );

        $access_token = WeixinHuadi::getInstance()->get_access_token();
        $template = array(
            'touser' => $open_id,
            'template_id' => $template_id,
            'topcolor' => "#7B68EE",
            'url' => 'http://mp.weixin.qq.com',
            'data' => $data,
            "miniprogram" => [
                "appid" => "wxc0516d6abf2093a6",
                "pagepath" => "/month-flower/monthly-flower",
           ],
        );

        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $curl = new \linslin\yii2\curl\Curl();
        $response = $curl->setOption(
            CURLOPT_POSTFIELDS,
            $json_template
        )->post($url);
        $result = json_decode($response, true);
        if($result["errcode"] == 0 && $result["errmsg"] == "ok"){
            \common\components\Log::writeLog(__FUNCTION__,$order_id."||".var_export($template,true));
            return true;
        }else{
            throw new Exception($result["errmsg"]);
        }
    }catch (Exception $e){
        \common\components\Log::writeLog(__FUNCTION__,$order_id."||".($e->getMessage()));
    }

    return false;
}

/**
 * 替换名称中间字符串
 * @param $string string 需要替换的字符串
 * @param string $replaceStr string 替换后显示的字符
 * @return string
 */
function replaceStr($string, $replaceStr = "*")
{
    $strLen     = mb_strlen($string, 'utf-8');
    if ($strLen < 2) {
        return $string;
    }
    $firstStr   = mb_substr($string, 0, 1, 'utf-8');
    $lastStr    = mb_substr($string, -1, 1, 'utf-8');
    return $strLen == 2 ? $firstStr . str_repeat($replaceStr, mb_strlen($string, 'utf-8') - 1) : $firstStr . str_repeat($replaceStr, $strLen - 2) . $lastStr;
}

/**
 * 获取OSS视频缩略图
 * @param $url
 * @return string
 */
function getVideoThumb($url)
{
        //获取视频第几秒截图
        $video_thumb_time = 1;
        //宽高为0自动计算
        $video_thumb_width = 0;
        $video_thumb_height = 0;
        return $url . "?x-oss-process=video/snapshot,t_{$video_thumb_time},f_jpg,w_{$video_thumb_width},h_{$video_thumb_height},m_fast";
}

defined('MD5_KEY') or define('MD5_KEY', 'qazwsxedcrfvtgbyhnujmiksd1254332424');

/**
 * 加密函数
 *
 * @param string $txt 需要加密的字符串
 * @param string $key 密钥
 * @return string 返回加密结果
 */
function encrypt($txt, $key = '')
{
    if (empty($txt)) return $txt;
    if (empty($key)) $key = md5(MD5_KEY);
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
    $ikey = "-x6g6ZWm2G9g_vr0Bo.pOq3kRIxsZ6rm";
    $nh1 = rand(0, 64);
    $nh2 = rand(0, 64);
    $nh3 = rand(0, 64);
    $ch1 = $chars{$nh1};
    $ch2 = $chars{$nh2};
    $ch3 = $chars{$nh3};
    $nhnum = $nh1 + $nh2 + $nh3;
    $knum = 0;
    $i = 0;
    while (isset($key{$i})) $knum += ord($key{$i++});
    $mdKey = substr(md5(md5(md5($key . $ch1) . $ch2 . $ikey) . $ch3), $nhnum % 8, $knum % 8 + 16);
    $txt = base64_encode(time() . '_' . $txt);
    $txt = str_replace(array('+', '/', '='), array('-', '_', '.'), $txt);
    $tmp = '';
    $j = 0;
    $k = 0;
    $tlen = strlen($txt);
    $klen = strlen($mdKey);
    for ($i = 0; $i < $tlen; $i++) {
        $k = $k == $klen ? 0 : $k;
        $j = ($nhnum + strpos($chars, $txt{$i}) + ord($mdKey{$k++})) % 64;
        $tmp .= $chars{$j};
    }
    $tmplen = strlen($tmp);
    $tmp = substr_replace($tmp, $ch3, $nh2 % ++$tmplen, 0);
    $tmp = substr_replace($tmp, $ch2, $nh1 % ++$tmplen, 0);
    $tmp = substr_replace($tmp, $ch1, $knum % ++$tmplen, 0);
    return $tmp;
}

/**
 * 解密函数
 *
 * @param string $txt 需要解密的字符串
 * @param string $key 密匙
 * @return string 字符串类型的返回结果
 */
function decrypt($txt, $key = '', $ttl = 0)
{
    if (empty($txt)) return $txt;
    if (empty($key)) $key = md5(MD5_KEY);

    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
    $ikey = "-x6g6ZWm2G9g_vr0Bo.pOq3kRIxsZ6rm";
    $knum = 0;
    $i = 0;
    $tlen = @strlen($txt);
    while (isset($key{$i})) $knum += ord($key{$i++});
    $ch1 = @$txt{$knum % $tlen};
    $nh1 = strpos($chars, $ch1);
    $txt = @substr_replace($txt, '', $knum % $tlen--, 1);
    $ch2 = @$txt{$nh1 % $tlen};
    $nh2 = @strpos($chars, $ch2);
    $txt = @substr_replace($txt, '', $nh1 % $tlen--, 1);
    $ch3 = @$txt{$nh2 % $tlen};
    $nh3 = @strpos($chars, $ch3);
    $txt = @substr_replace($txt, '', $nh2 % $tlen--, 1);
    $nhnum = $nh1 + $nh2 + $nh3;
    $mdKey = substr(md5(md5(md5($key . $ch1) . $ch2 . $ikey) . $ch3), $nhnum % 8, $knum % 8 + 16);
    $tmp = '';
    $j = 0;
    $k = 0;
    $tlen = @strlen($txt);
    $klen = @strlen($mdKey);
    for ($i = 0; $i < $tlen; $i++) {
        $k = $k == $klen ? 0 : $k;
        $j = strpos($chars, $txt{$i}) - $nhnum - ord($mdKey{$k++});
        while ($j < 0) $j += 64;
        $tmp .= $chars{$j};
    }
    $tmp = str_replace(array('-', '_', '.'), array('+', '/', '='), $tmp);
    $tmp = trim(base64_decode($tmp));

    if (preg_match("/\d{10}_/s", substr($tmp, 0, 11))) {
        if ($ttl > 0 && (time() - substr($tmp, 0, 11) > $ttl)) {
            $tmp = null;
        } else {
            $tmp = substr($tmp, 11);
        }
    }
    return $tmp;
}

//跟据地址获取经伟度
function getAxis($area_info, $address)
{
    $url = 'http://api.map.baidu.com/geocoder/v2/?';
    $param = array('output' => 'json', 'ak' => 'jP6FTWz2i9hEyQZbximzFShL');
    if ($area_info) {
        $area_info = str_replace("\t", ' ', $area_info);
        $tmp_arr = explode(' ', $area_info);
        //$param['province'] = $tmp_arr[0];
        //$param['city'] = $tmp_arr[1];
    }
    $param['address'] = $area_info . $address;
    //去除地址信息中的中/英文括号中的内容
    $param['address'] = str_replace(array('（', '）'), array('(', ')'), $param['address']);
    $param['address'] = preg_replace('/(\(.*?\))/s', '', $param['address']);
    $url .= http_build_query($param);
    $result = @file_get_contents($url);
    $data = array();
    if ($result) {
        $result = json_decode($result);
        if ($result->status == 0) {
            $x_axis = $result->result->location->lng;
            $y_axis = $result->result->location->lat;
            $data = array('x_axis' => $x_axis, 'y_axis' => $y_axis);
        }
    }
    return $data;
}

/**
 * 计算两点之间的距离
 * @param $lng1 经度1
 * @param $lat1 纬度1
 * @param $lng2 经度2
 * @param $lat2 纬度2
 * @param int $unit m，km
 * @param int $decimal 位数
 * @return float
 */
function getDistance($lng1, $lat1, $lng2, $lat2, $unit = 2, $decimal = 2)
{

    $EARTH_RADIUS = 6370.996; // 地球半径系数
    $PI           = 3.1415926535898;

    $radLat1 = $lat1 * $PI / 180.0;
    $radLat2 = $lat2 * $PI / 180.0;

    $radLng1 = $lng1 * $PI / 180.0;
    $radLng2 = $lng2 * $PI / 180.0;

    $a = $radLat1 - $radLat2;
    $b = $radLng1 - $radLng2;

    $distance = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
    $distance = $distance * $EARTH_RADIUS * 1000;

    if ($unit === 2) {
        $distance /= 1000;
    }

    return round($distance, $decimal);
}

function batchUpdate($table, $columns, $rows, $keyPrimaryArrs, $keyPrimaryColumn)
{

    $sql = '';
    $sql .= 'UPDATE ' . $table . ' SET ';
    $rowsCount = count($rows);
    $columnsCount = count($columns);
    $columnName = '';
    $rowFang = '';

    for ($i = 0; $i < $columnsCount; $i++) {

        $columnName = isset($columns[$i]) ? $columns[$i] : $columnName;
        $sql .= $columnName . ' = CASE ' . $keyPrimaryColumn;

        for ($j = 0; $j < $rowsCount; $j++) {
            $rowFang = isset($rows[$j][$i]) ? $rows[$j][$i] : $rowFang;
            $keyPrimary = isset($keyPrimaryArrs[$j]) ? $keyPrimaryArrs[$j] : $keyPrimary;
            if (gettype($rowFang) == 'integer') {
                $sql .= ' WHEN \'' . $keyPrimary . '\' THEN ' . $rowFang;
            } else {
                $sql .= ' WHEN \'' . $keyPrimary . '\' THEN \'' . $rowFang . '\'';
            }
        }

        $end = ' END, ';
//        if ($i === $rowsCount) {
//            $end = ' END ';
//        } else {
//            $end = ' END, ';
//        }
        $sql .= $end;
    }

    $conditions = '(\'' . implode('\',\'', $keyPrimaryArrs) . '\')';

    $sql .= ' WHERE ' . $keyPrimaryColumn . ' IN ' . $conditions;
    $sql = str_replace('END,  WHERE', 'END  WHERE', $sql);

    return $sql;
}
if (!function_exists('filterEmoji')) {
    /**
     * 过滤字符串中的emoji表情
     *
     * @param string $str
     * @return string
     */
    function filterEmoji($str)
    {
        $str = preg_replace_callback( '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);
        return $str;
    }
}
if (!function_exists('unixtime_to_date')) {

    function unixtime_to_date($format = 'Y-m-d H:i:s',$unix_time = '',$timezone = 'PRC')
    {
        $unix_time = $unix_time ? $unix_time : time();
        $datetime = new DateTime("@$unix_time");
        $datetime->setTimezone(new DateTimeZone($timezone));
        return  $datetime->format($format);
    }
}
if (!function_exists('date_to_unixtime')) {

    function date_to_unixtime($date = '2038-01-19 11:14:07', $timezone = 'PRC')
    {
        $datetime = new DateTime($date, new DateTimeZone($timezone));
        return  $datetime->format("U");
    }
}
/**
 * 包含特殊字符的连接转义
 */
if(!function_exists('link_url_encode')) {
    function link_url_encode($url){
        $return_url = '';
        $cs = unpack('C*', $url);
        $len = count($cs);
        for($i=1; $i<=$len; $i++) {
            $return_url .= $cs[$i] > 127 ? '%'.strtoupper(dechex($cs[$i])) : $url{$i-1};
        }
        return $return_url;
    }
}
/**
 * 获取周岁
 */
if(!function_exists('getAgeByBirth')) {
    function getAgeByBirth($date)
    {
        list($birthYear, $birthMonth, $birthDay) = explode('-', date('Y-m-d', $date));
        list($currentYear, $currentMonth, $currentDay) = explode('-', date('Y-m-d'));
        $age = $currentYear - $birthYear - 1;
        if ($currentMonth > $birthMonth || $currentMonth == $birthMonth && $currentDay >= $birthDay) $age++;
        return $age;
    }
}
