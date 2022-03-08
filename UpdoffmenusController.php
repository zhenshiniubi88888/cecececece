<?php

namespace frontend\controllers;


use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use common\components\Log;
use common\components\Message;
use common\models\EvaluateGoods;
use common\models\Feedback;
use common\models\FeedbackType;
use common\models\Goods;
use common\models\Member;
use common\models\OrderGoods;
use common\models\Orders;
use frontend\service\DataBuryingPointService;
use Yii;

class UpdoffmenusController extends BaseController
{
    private $fromUsername;
    private $toUsername;
    private $times;
    private $keyword;

    public function actionValid()
    {
        if (isset($_GET['echostr']) && $this->checkSignature()) {
            echo $_GET['echostr'];
            exit;
        }
//        //$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//返回回复数据
        $postStr = file_get_contents("php://input");
        //$postStr = isset($GLOBALS["HTTP_RAW_POST_DATA"])?$GLOBALS["HTTP_RAW_POST_DATA"]:'';//返回回复数据
        if (!empty($postStr)) {
            $access_token = $this->get_access_token();//获取access_token
//            $this->createmenu($access_token);//创建菜单
            //$this->delmenu($access_token);//删除菜单
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            Log::writelog('wx_code', var_export($postObj, true));
            $this->fromUsername = $postObj->FromUserName;//发送消息方ID
            $this->toUsername = $postObj->ToUserName;//接收消息方ID
            $this->keyword = trim($postObj->Content);//用户发送的消息
            $this->times = time();//发送时间
            $MsgType = $postObj->MsgType;//消息类型
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$this->fromUsername}&lang=zh_CN";
            //<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='/pages/goods-detail?id=15471&type=2' href='http://www.qq.com'>只需29元,感受生活中的小确幸>></a>
            //<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='/pages/goods-detail?id=14850&type=2' href='http://www.qq.com'>👉39元升级版，底价拼团限量抢>></a>
            //<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='/pages/goods-detail?id=15470&type=2' href='http://www.qq.com'>👉59元高级版,重拾生活的仪式感>></a>
            $data = json_decode(file_get_contents($url), true);
            if ($MsgType == 'event') {
                $MsgEvent = $postObj->Event;//获取事件类型
                $EventKey = $postObj->EventKey;
                $order_id = intval(str_replace('qrscene_', '', $EventKey));
                if ($MsgEvent == 'subscribe') {//订阅事件
                    if (substr($order_id, -5) == 58888) {
                        $arr[] = "11枝红玫瑰今日仅售99元
不能错过的钜惠<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/goods-detail?id=15967&type=1' href='http://www.qq.com'>马上下单></a>
";
                    } elseif (substr($order_id, -5) == 58898) {
                        $arr[] = "嗨！ 恭喜你发现了新鲜又低价的水果
今日特价： 不知火（丑柑）6斤精品装，<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/goods-detail?id=16265&type=1' href='http://www.qq.com'>点击购买></a>
海南贵妃芒 5斤精品装，<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/goods-detail?id=16267&type=1' href='http://www.qq.com'>点击购买></a>
关注我们，每周三都带给您优质商品~
";
                        echo $this->make_xml("text", $arr);
                        exit;
                    } else {
                        if(TIMESTAMP > 1592496000 && TIMESTAMP <= 1592755200){
                            $service = new DataBuryingPointService('HuaDi');
                            $service->AddVisitData();
                            $arr[] = "HI，等你很久了❤~
——欢迎进入“花递直卖”平台!

👉查物流、商家电话<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='question/order-select' href='http://www.qq.com'> 点>></a>
👉身边的花店<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/near-shop' href='http://www.qq.com'>点>></a>

621父亲节，为爸爸准备一份心意礼,<a href='https://www.aihuaju.com/h5/hd/2020/hd_father_day/'>点>></a>

";
                        }else {
                            $service = new DataBuryingPointService('HuaDi');
                            $service->AddVisitData();
                            $arr[] = "嗨，你终于来了，等你很久喽❤~
——欢迎进入【花店直卖】新时代！超10w+线下花店，可送全国实体花店直营，不转单，不推责没有中间商赚差价，价格直降30%

👉查实拍，物流，商家电话，<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='question/order-select' href='http://www.qq.com'> 点击>></a>
👉找收花人附近实体花店，<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/near-shop' href='http://www.qq.com'>进入>></a>

<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/life-flower' href='http://www.qq.com'>轻奢生活，居家/办公鲜花>></a>
<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='month-flower/monthly-flower' href='http://www.qq.com'>每周一束，最低99元 包月花>></a>
";
                        }
                    }
                    echo $this->make_xml("text", $arr);
                    exit;
                } elseif ($MsgEvent == 'SCAN') {//图片关键词事件
                    if (substr($order_id, -5) == 58888) {
                        $arr[] = "11枝红玫瑰今日仅售99元
不能错过的钜惠<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/goods-detail?id=15967&type=1' href='http://www.qq.com'>马上下单></a>
";
                        echo $this->make_xml("text", $arr);
                        exit;
                    }
                    if (substr($order_id, -5) == 58898) {
                        $arr[] = "嗨！ 恭喜你发现了新鲜又低价的水果
今日特价： 不知火（丑柑）6斤精品装，<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/goods-detail?id=16265&type=1' href='http://www.qq.com'>点击购买></a>
海南贵妃芒 5斤精品装，<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/goods-detail?id=16267&type=1' href='http://www.qq.com'>点击购买></a>
关注我们，每周三都带给您优质商品~
";
                        echo $this->make_xml("text", $arr);
                        exit;
                    }
                } elseif ($MsgEvent == 'CLICK') {//点击事件
                    $message = $this->getEventVal($EventKey);
                    $arr[] = $message;
                    echo $this->make_xml("text", $arr);
                    exit;
                }
            } elseif ($MsgType == 'text') {
                if ((strpos($this->keyword, '查询订单') !== false) || (strpos($this->keyword, '查单') !== false) || (strpos($this->keyword, '配送进度') !== false) || (strpos($this->keyword, '订单') !== false) || (strpos($this->keyword, '帮我查单') !== false)) {
                    $arr[] = "亲，您的订单可以在这里查询哟~<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='question/order-select' href='http://www.qq.com'> 点击查询>></a>";
                    echo $this->make_xml("text", $arr);
                }elseif (strpos($this->keyword, 'test') !== false) {
                    $arr[] = $this->fromUsername;
                    echo $this->make_xml("text", $arr);
                }elseif ((strpos($this->keyword, '你好') !== false) || (strpos($this->keyword, '在吗') !== false) || (strpos($this->keyword, '客服') !== false) || (strpos($this->keyword, '人呢') !== false)) {
                    $arr[] = "亲亲，欢迎光临本店
爆款鲜花限时特惠中<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/index-new' href='http://www.qq.com'>去看看></a>";
                    echo $this->make_xml("text", $arr);
                }elseif ((strpos($this->keyword, '订花') !== false) || (strpos($this->keyword, '送花') !== false)) {
                    $arr[] = "亲亲，欢迎光临本店
要订花<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/index-new' href='http://www.qq.com'> 点此链接></a>";
                    echo $this->make_xml("text", $arr);
                }elseif ((strpos($this->keyword, '店在哪里') !== false) || (strpos($this->keyword, '能送吗') !== false) || (strpos($this->keyword, '有花店没') !== false)) {
                    $arr[] = "您好，我们是全国连锁，每个城市都有花店，您可放心下单<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/index-new' href='http://www.qq.com'> 点击购买></a>";
                    echo $this->make_xml("text", $arr);
                }elseif ((strpos($this->keyword, '送女朋友') !== false) || (strpos($this->keyword, '送老婆') !== false)) {
                    $arr[] = "亲，这些花花很适合送她哦<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/index-new' href='http://www.qq.com'> 点击购买></a>";
                    echo $this->make_xml("text", $arr);
                }elseif ($this->keyword == '会员福利') {
                    $arr[] = "新用户领取100元红包：<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='pages/index-new' href='http://www.qq.com'>点击领取></a>";
                    echo $this->make_xml("text", $arr);
                    exit;
                } else {
//                    $arr[] = "花递—花店直卖
//没有中间商赚差价
//
//<a data-miniprogram-appid='wxc0516d6abf2093a6' data-miniprogram-path='order/all-order' href='http://www.qq.com'>订单查询</a>
//
//<a href='http://storein.huadi01.cn'>入驻花递</a>
//
//加客服进优惠群：huadi01
//
//客服工作时间9点~18点
//";
//                    echo $this->make_xml("text", $arr);
                }

                exit;
            }
        } else {
            echo "没有数据";
            exit;
        }
    }

    /**
     * 运行程序
     *
     * @param string $value [description]
     */
    public function actionRun()
    {
        $this->responseMsg();
        $arr[] = "您好，这是自动回复，我现在不在，有事请留言，我会尽快回复你的^_^";
        echo $this->make_xml("text", $arr);
    }

    /**
     * 获取素材列表media_id
     */
    public function actionGetmedias()
    {
        $access_token = $this->get_access_token();
        $url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token={$access_token}";
        $arr = [
            'type' => 'image',
            'offset' => 0,
            'count' => 20
        ];
        $jsondata = urldecode(json_encode($arr));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsondata);
        $ret = curl_exec($ch);
        curl_close($ch);
        print_r($ret);
    }

    public function actionGettoken()
    {
        $token = cache('menus_accesstokens');
        $data_arr = [
            'code' => 1,
            'msg' => 'SUCCESS'
        ];
        if (!$token) {
            $token = $this->get_access_token();
        }
        $data_arr['access_token'] = $token;
        return json_encode($data_arr);
    }

    public function actionUpdmenus()
    {
        $access_token = $this->get_access_token();//获取access_token

        $this->createmenu($access_token);//创建菜单
        exit;
        $this->responseMsg();
        $arr[] = "你好，欢迎关注花递！~";
        echo $this->make_xml("text", $arr);
    }

    public function actionQrcodes()
    {
        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$this->get_access_token()}";
        $param = array();
        $param['action_name'] = 'QR_LIMIT_SCENE';
        $param['action_info'] = array('scene' => array('scene_id' => 58898));
        $param = json_encode($param);
        //print_r($param);die;
        $data = https_request($url, $param);
        $result = (array)json_decode($data);
        if (isset($result['errcode'])) {
            //echo $result['errmsg'];die;
            echo "0";
            die;
        } else {
            $ticket = $result['ticket'];
        }
        $url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . urlencode($ticket);
        $data = https_request($url);
        header('Content-type: image/jpg');
        echo $data;
    }


    protected function getEventVal($EventKey)
    {
        switch ($EventKey) {
            case 'STORE_DOWNING':
                $message = '功能内测中，敬请期待';
                break;
            case 'STORE_BUY':
                $message = '功能内测中，敬请期待';
                break;
            case 'LEARN_FLORAL':
                $message = '功能内测中，敬请期待';
                break;
            case 'FLORAL_PK':
                $message = '功能内测中，敬请期待';
                break;
            case 'TOUCH_MSG':
                $message = '功能内测中，敬请期待';
                break;
            case 'FLOWER_KNOWLEDGE':
                $message = '功能内测中，敬请期待';
                break;
            case 'MEMBER_CENT_ORDERS':
                $message = '功能内测中，敬请期待';
                break;
            case 'COMPLAINT_QUESTION':
                $message = '功能内测中，敬请期待';
                break;
            case 'DISTRIBUTOR':
                $message = '功能内测中，敬请期待';
                break;
            case 'FLOWER_SHOP':
                $message = '功能内测中，敬请期待';
                break;
            default:
                $message = '功能内测中，敬请期待';
        }
        return $message;
    }

    /**
     * 获取access_token
     */
    private function get_access_token()
    {
        if ($_GET['ylbug']) {
            //花递
            $appid = 'wx5dea2142adb7a1f0';
            $secret = 'cd7dca13582b9d2071e7c3889305ff04';
        } else {
            //花递直卖
            $appid = 'wxdd58ad88c4ac1921';
            $secret = '3324336f4c9664d6c7e2f77f307f1e2a';
        }
        $access_token = cache('menus_accesstoken3' . $_GET['ylbug']);
        if (empty($access_token)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
            $data = json_decode(file_get_contents($url), true);
            if (isset($data['access_token'])) {
                cache('menus_accesstoken3' . $_GET['ylbug'], $data['access_token'], 7000);
                return $data['access_token'];
            } else {
                return "获取access_token错误";
            }
        } else {
            return $access_token;
        }
    }

    /**
     * 生成带参数小程序二维码
     */
    public function actionGetAppletAqrcode()
    {
        $post = \Yii::$app->request->post();
        $help_id = isset($post['help_id']) && intval($post['help_id']) > 0 ? intval($post['help_id']) : 0;
        //构建请求二维码参数
        //path是扫描二维码跳转的小程序路径，可以带参数?id=xxx
        //width是二维码宽度
        $access_token = \common\components\WeixinHuadi::getInstance()->get_token();
        $qcode = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$access_token}";
        $path = ["page" => "pages/index-new", "width" => 400, "scene" => "$help_id"];
        $param = json_encode($path);

        //POST参数
        $result = https_request($qcode, $param);
        //生成二维码
        file_put_contents("qrcode.png", $result);
        $data['base64_image'] = "data:image/jpeg;base64," . base64_encode($result);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * @return mixed
     */
    public function actionFeedbacksubmit()
    {
        $param = \Yii::$app->request->post();
        $t_id = isset($param['t_id']) ? intval($param['t_id']) : 0;
        $type_info = FeedbackType::getTypeById($t_id);
        if (!$type_info) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $f_content = isset($param['f_content']) ? trim($param['f_content']) : '';
        if ($f_content == '' || mb_strlen($f_content, 'UTF-8') > 200 || mb_strlen($f_content, 'UTF-8') < 10) {
            return $this->responseJson(Message::ERROR, '请输入10-200字的描述');
        }
        $pic = isset($param['f_pic']) && $param['f_pic'] != '' ? explode(',', $param['f_pic']) : '';
        if ($pic != '' && count($pic) > 3) {
            return $this->responseJson(Message::ERROR, '最多上传三张图片');
        } else {
            foreach ($pic as $p) {
                if (!isImgName_wx($p)) {
                    return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
                }
            }
        }
        $feedback = new Feedback();
        $feedback->t_id = $type_info['t_id'];
        $feedback->t_content = $type_info['t_content'];
        $feedback->f_content = $f_content;
        $feedback->f_pic = isset($param['f_pic']) ? $param['f_pic'] : '';
        $feedback->user_agent = isset($param['user_agent']) ? $param['user_agent'] : \Yii::$app->request->userAgent;
        $feedback->app_version = isset($param['app_version']) ? $param['app_version'] : $_SERVER['API_VERSION'];
        $feedback->ip = getIp();
        if ($this->isLogin()) {
            $feedback->member_id = $this->member_id;
            $feedback->member_name = $this->member_name;
        } elseif (isset($param['member_id'])) {
            $feedback->member_id = intval($param['member_id']);
            $feedback->member_name = isset($param['name']) ? $param['name'] : "";
            $feedback->member_mobile = isset($param['mobile']) ? intval($param['mobile']) : "";
        } else {
            $feedback->is_anonymous = 1;
        }
        $feedback->f_state = 0;
        $feedback->device_type = isset($param['device_type']) ? $param['device_type'] : 'wap';
        $feedback->add_time = TIMESTAMP;
        $result = $feedback->insert(false);
        if (!$result) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 商品详情
     *
     * @return mixed
     */
    public function actionGoodsinfo()
    {
        $param = \Yii::$app->request->post();
        $goods_id = (int)$param['goods_id'];
        $model_goods = new Goods();
        $goods_data = $model_goods->getGoodsDetail($goods_id, 1);
        $goods_info = $goods_data['goods_info'];
        if (empty($goods_info)) {
            return $this->responseJson(Message::EMPTY_CODE, '您要找的商品不存在~');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $goods_info);
    }

    public function actionHuadistorecommentslist()
    {
        $store_id = Yii::$app->request->get("store_id", 0);
        $page = Yii::$app->request->get("page", 1);
        $order_count_condition = [
            'siteid' => 258,
            'delivery_store_id' => $store_id,
            'delete_state' => 0
        ];
        //echo Orders::find()->select('order_id')->where($order_count_condition)->andWhere(['>=', 'order_state', 20])->createCommand()->getRawSql();die;
        $orders = Orders::find()->select('order_id, send_time')->where($order_count_condition)->andWhere([
            '>=',
            'order_state',
            20
        ])->asArray()->all();
        $order_arr = [];
        if ($orders && !empty($orders)) {
            foreach ($orders as $v) {
                $order_arr[$v['order_id']] = $v;
            }
        }
        $order_ids = array_column($orders, 'order_id');
        $condition = [
            "geval_storeid" => $store_id,
            "geval_is_show" => 1
        ];
        $where = ["in", "geval_orderid", $order_ids];
        $map = ["and", $condition, $where];
        $evaluate_goods = new EvaluateGoods();
        $orders_model = new Orders();
        $list = $evaluate_goods->getEvaluateList($map, '*', 'geval_id desc', $page);
        foreach ($list as $k => $v) {
            if ($v['geval_image'] != '') {
                $geval_image = explode(',', $v['geval_image']);
                if (!empty($geval_image)) {
                    foreach ($geval_image as $key => $value) {
                        if ($value != '') {
                            $geval_image[$key] = getImgUrl($value, ATTACH_COMMENT);
                        }
                    }
                }
            }
            $member = Member::findOne($v['geval_frommemberid']);
            $list[$k]['geval_image'] = $v['geval_image'] == '' ? [] : $geval_image;
            $list[$k]['geval_addtime'] = date('Y-m-d H:i:s', $v['geval_addtime']);
            $list[$k]['geval_avatar'] = $member ? getMemberAvatar($member->member_avatar) : getMemberAvatar();
            $order_info = $orders_model->getOrderInfo(['=', 'order_id', $v['geval_orderid']], ['order_goods']);
            $list[$k]['orders_info'] = $order_info;

            // 商品数量
            $order_goods = OrderGoods::find()->select('sum(goods_num) as goods_num')->where(['order_id' => $v['geval_orderid']])->asArray()->one();
            $list[$k]['goods_num'] = $order_goods ? $order_goods['goods_num'] : 1;

            // 送花时间
            $list[$k]['send_time'] = isset($order_arr[$v['geval_orderid']]) ? $order_arr[$v['geval_orderid']]['send_time'] : 0;

            // 评论回复内容
            $list[$k]['reply_content'] = $v['reply_content'] ? unserialize($v['reply_content']) : '';
            if ($list[$k]['reply_content'] && !empty($list[$k]['reply_content'])) {
                $list[$k]['reply_content']['reply_content'] = @base64_decode($list[$k]['reply_content']['reply_content']);
            }

        }
        if ($list) {
            $data["comment"] = $list;
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        } else {
            return $this->responseJson(1, '暂无评论数据');
        }
    }

    public function actionReplyhuadicomment()
    {
        $geval_id = Yii::$app->request->post("geval_id", 0);
        $reply_content = Yii::$app->request->post("reply_content", '');
        if (intval($geval_id) && $reply_content) {
            $reply_content = base64_encode($reply_content);
            $geval = EvaluateGoods::findOne($geval_id);
            if (empty($geval)) {
                return $this->responseJson(0, '评论不存在或已删除');
            } else {
                $upd_reply = [
                    'time' => TIMESTAMP,
                    'reply_content' => $reply_content
                ];
                $upd_reply = serialize($upd_reply);
                $geval->setAttribute('reply_content', $upd_reply);
                $result = $geval->save(false);
                if (!$result) {
                    return $this->responseJson(0, '回复失败');
                } else {
                    return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
                }
            }
        }
    }

    /**
     * 创建菜单
     *
     * @param $access_token 已获取的ACCESS_TOKEN
     */
    protected function createmenu($access_token)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=" . $access_token;
        if (TIMESTAMP > 1592496000 && TIMESTAMP <= 1592755200) {
            $arr = array(
                'button' => array(
                    array(
                        'name' => urlencode("活动专区🔥"),
                        'sub_button' => array(
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("鲜花商城"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/pages/index-new'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("9.9元|抢年卡"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => "/user-center/vipCard"
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("69元|生活花特惠"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => "/pages/goods-detail?id=16578&type=2"
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("99元|抢11枝玫瑰"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/pages/goods-detail?id=16592&type=1'
                            ),
                        )
                    ),
                    array(
                        'type' => 'miniprogram',
                        'name' => urlencode("621父亲节"),
                        'url' => 'http://mp.weixin.qq.com',
                        'appid' => 'wxc0516d6abf2093a6',
                        'pagepath' => '/pages/active?src=https://www.aihuaju.com/h5/hd/2020/hd_father_day/'
                    ),
                    array(
                        'name' => urlencode("会员服务"),
                        'sub_button' => array(
                            array(
                                'type' => 'view',
                                'name' => urlencode("APP领100红包"),
                                'url' => 'http://www.hua.zj.cn/index/download',
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("查看订单进度"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/question/order-select'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("我的红包"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/coupon/coupon-list'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("个人中心"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/pages/my?isPublicNumber=1'
                            ),
                        )
                    )
                )
            );
        } else {
            $arr = array(
                'button' => array(
                    array(
                        'name' => urlencode("立即订花🌹"),
                        'sub_button' => array(
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("鲜花商城"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/pages/index-new'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("送爱人♡"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => "/pages/search?kw=" . urlencode('送老婆') . "&isCategory=1"
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("送朋友送长辈"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => "/pages/search?kw=" . urlencode('送长辈') . "&isCategory=1"
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("家居鲜花"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/pages/life-flower'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("包月|每周1束"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/month-flower/monthly-flower'
                            ),
                        )
                    ),
                    array(
                        'type' => 'miniprogram',
                        'name' => urlencode("花店直卖"),
                        'url' => 'http://mp.weixin.qq.com',
                        'appid' => 'wxc0516d6abf2093a6',
                        'pagepath' => '/pages/near-shop'
                    ),
                    array(
                        'name' => urlencode("会员服务"),
                        'sub_button' => array(
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("订单查询"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/question/order-select'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("会员中心"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/pages/my?isPublicNumber=1'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("我的红包"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/coupon/index'
                            ),
                            array(
                                'type' => 'miniprogram',
                                'name' => urlencode("帮助中心"),
                                'url' => 'http://mp.weixin.qq.com',
                                'appid' => 'wxc0516d6abf2093a6',
                                'pagepath' => '/question/saled'
                            ),
                        )
                    )
                )
            );
        }
        $jsondata = urldecode(json_encode($arr));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsondata);
        $ret = curl_exec($ch);
        curl_close($ch);
        print_r(json_encode($ret));
    }

    /**
     * 查询菜单
     *
     * @param $access_token 已获取的ACCESS_TOKEN
     */

    private function getmenu($access_token)
    {
        # code...
        $url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token=" . $access_token;
        $data = file_get_contents($url);
        return $data;
    }

    /**
     * 删除菜单
     *
     * @param $access_token 已获取的ACCESS_TOKEN
     */

    private function delmenu($access_token)
    {
        # code...
        $url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=" . $access_token;
        $data = json_decode(file_get_contents($url), true);
        if ($data['errcode'] == 0) {
            # code...
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param type: text 文本类型, news 图文类型
     * @param value_arr array(内容),array(ID)
     * @param o_arr array(array(标题,介绍,图片,超链接),...小于10条),array(条数,ID)
     */

    private function make_xml($type, $value_arr, $o_arr = array(0))
    {
        //=================xml header============
        $con = "<xml> 
                    <ToUserName><![CDATA[{$this->fromUsername}]]></ToUserName> 
                    <FromUserName><![CDATA[{$this->toUsername}]]></FromUserName> 
                    <CreateTime>{$this->times}</CreateTime> 
                    <MsgType><![CDATA[{$type}]]></MsgType>";

        //=================type content============
        switch ($type) {

            case "text" :
                $con .= "<Content><![CDATA[{$value_arr[0]}]]></Content> 
                    <FuncFlag>{$o_arr}</FuncFlag>";
                break;

            case "news" :
                $con .= "<ArticleCount>{$o_arr[0]}</ArticleCount> 
                     <Articles>";
                foreach ($value_arr as $id => $v) {
                    if ($id >= $o_arr[0]) {
                        break;
                    } else {
                        null;
                    } //判断数组数不超过设置数
                    $con .= "<item> 
                         <Title><![CDATA[{$v[0]}]]></Title>  
                         <Description><![CDATA[{$v[1]}]]></Description> 
                         <PicUrl><![CDATA[{$v[2]}]]></PicUrl> 
                         <Url><![CDATA[{$v[3]}]]></Url> 
                         </item>";
                }
                $con .= "</Articles> 
                     <FuncFlag>{$o_arr[1]}</FuncFlag>";
                break;
            case "image":
                $con .= "    <Image>
                                <MediaId><![CDATA[{$value_arr['media_id']}]]></MediaId>
                            </Image>";
                break;
        } //end switch
        //=================end return============
        $con .= "</xml>";
        return $con;
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = 'sunlixiang1';
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    public function actionCccgetcallrecord()
    {
        require_once '../../vendor/alibaba-sdk/autoload.php';
        $page = 1;
        $data = array();
        $stop_time = (TIMESTAMP - 600) * 1000;
        $start_time = (TIMESTAMP - 7200) * 1000;
        AlibabaCloud::accessKeyClient('LTAI4FcGMVnDfzvDcpyrTXbt', 'KS6QbB1PgyTBI7mgMoD7ERiUraEIxp')
            ->regionId('cn-shanghai')
            ->asDefaultClient();
        try {
            $result = AlibabaCloud::rpc()
                ->product('CCC')
                // ->scheme('https') // https | http
                ->version('2017-07-05')
                ->action('ListCallDetailRecords')
                ->method('POST')
                ->host('ccc.cn-shanghai.aliyuncs.com')
                ->options(array(
                    'query' => array(
                        'RegionId' => "cn-shanghai",
                        'StartTime' => $start_time,
                        'StopTime' => $stop_time,
                        'InstanceId' => "1b20a035-b35e-46aa-8c34-644572e8d615",
                        'PageNumber' => $page,
                        'PageSize' => "100",
                        'WithRecording' => false
                    ),
                ))
                ->request();
            $result = $result->toArray();
            if (isset($result['CallDetailRecords']['TotalCount'])) {
                $total = $result['CallDetailRecords']['TotalCount'];
                $page_max = ceil($total / 100);
                $data = $result['CallDetailRecords']['List']['CallDetailRecord'];
                while ($page < $page_max) {
                    $page++;
                    $result = AlibabaCloud::rpc()
                        ->product('CCC')
                        // ->scheme('https') // https | http
                        ->version('2017-07-05')
                        ->action('ListCallDetailRecords')
                        ->method('POST')
                        ->host('ccc.cn-shanghai.aliyuncs.com')
                        ->options(array(
                            'query' => array(
                                'RegionId' => "cn-shanghai",
                                'StartTime' => $start_time,
                                'StopTime' => $stop_time,
                                'InstanceId' => "1b20a035-b35e-46aa-8c34-644572e8d615",
                                'PageNumber' => $page,
                                'PageSize' => "100",
                                'WithRecording' => false
                            ),
                        ))
                        ->request();
                    $result = $result->toArray();
                    if (isset($result['CallDetailRecords']['TotalCount'])) {
                        $data = array_merge($data, $result['CallDetailRecords']['List']['CallDetailRecord']);
                    } else {
                        break;
                    }
                }
            }
            return json_encode(array('code' => 200, 'data' => $data));
        } catch (ClientException $e) {
            return json_encode(array('code' => 200, 'msg' => $e->getErrorMessage() . PHP_EOL));
        } catch (ServerException $e) {
            return json_encode(array('code' => 200, 'msg' => $e->getErrorMessage() . PHP_EOL));
        }
    }


    public function actionImgbase64()
    {
        $img_url = isset($_POST['img_url']) && $_POST['img_url'] ? $_POST['img_url'] : '';
        if ($img_url) {
            $imageInfo = getimagesize($img_url);
            return 'data:' . $imageInfo['mime'] . ';base64,' . chunk_split(base64_encode(file_get_contents($img_url)));
        }
        return 1;
    }

    /**
     * 记录其他公众号的访问人数
     */
    public function actionTest()
    {
        $official_account_name = Request('official_account_name', 'HuaDi');
        $service = new DataBuryingPointService($official_account_name);
        if ($service->AddVisitData()) {
            $this->responseJson(0, 'success');
        } else {
            $this->responseJson(1, '不在活动期');
        }

    }

    public function actionWeixinPush()
    {
        $access_token = $this->get_access_token();
        //echo '暂不开发';die;
        $wx_name = $this->get_weixin_name(trim('oFWeWjoHTNT8hzpi6qNh6ZyEIDDA'));
        $data = array(
            'first' => array('value' => urlencode('亲爱的'.$wx_name.'，恭喜您升级为花递VIP\n'), 'color' => '#ff0000'),
            'keyword1' => array('value' => urlencode(($wx_name ? $wx_name  : 'hi')), 'color' => '#03037c'),
            'keyword2' => array('value' => urlencode('花递VIP\n'), 'color' => '#03037c'),
            'keyword3' => array('value' => urlencode('2020年8月24日\n'), 'color' => '#03037c'),
            'remark' => array('value' => urlencode('明天七夕情人节，你订花，我送券，最高立减30元\n别让她的期待变成了等待和伤心，立即订花→'), 'color' => '#ff0000'),
        );
        $web_url = '/pages/active?src=https://www.aihuaju.com/h5/hd/2020/qixitest/index.html';
        $result = $this->doSend($access_token, 'oFWeWjoHTNT8hzpi6qNh6ZyEIDDA', 'oSS_3U3Xk_1b3i_KUcyAxoTZZnOHo6AmwmFDilAaXGs', $web_url, $data,1);
        echo $result;
        die;
    }

    public function actionWeixinPushOne(){
        $access_token = $this->get_access_token();
        $page = intval($_GET['page']);
        $page_size = 20;
        if ($page > 800) {
            echo '发送完成，共发送了 <font color="red">' . ($page * $page_size) . '</font> 个用户';
            die;
        }
        $data = $this->getFileLines(dirname(dirname(__FILE__)).'/tests/caches_weixin/app_id.txt', ($page * $page_size), (($page + 1) * $page_size));
        if (empty($data)) {
            echo '发送完成，共发送了 <font color="red">' . ($page * $page_size) . '</font> 个用户';
            die;
        }
        $web_url = 'pages/active?src=https://www.aihuaju.com/h5/hd/2020/520lph/index.html';
        foreach ($data as $k=>$d) {
            $d = str_replace(array("\n", "\r"), "", $d);
            $_tmp = file_get_contents(dirname(dirname(__FILE__)).'/tests/caches_weixin/yitui.txt');
            if ($_tmp) {
                $_tmp = json_decode($_tmp, true);
                if (in_array($d, $_tmp)) {
                    continue;
                }
            } else {
                $_tmp = array();
            }
            $wx_name = $this->get_weixin_name(trim($d));
            $data = array(
                'first' => array('value' => urlencode('恭喜您，已成功升级为黄金会员\n'), 'color' => '#ff0000'),
                'keyword1' => array('value' => urlencode(($wx_name ? $wx_name  : 'hi')), 'color' => '#03037c'),
                'keyword2' => array('value' => urlencode('黄金会员\n'), 'color' => '#03037c'),
                'keyword3' => array('value' => urlencode('2020年5月20日\n'), 'color' => '#03037c'),
                'remark' => array('value' => urlencode('今天520情人节，再犹豫花儿都没啦！\n趁现在还有花材，赶快下单\n晒幸福的时刻，别让她羡慕别的女人>>'), 'color' => '#ff0000'),
            );
            $result = $this->doSend($access_token, trim($d), 'oSS_3U3Xk_1b3i_KUcyAxoTZZnOHo6AmwmFDilAaXGs', $web_url, $data,1);
            if (!$result) {
                echo '错误';
                die;
            }
            $_tmp[] = trim($d);
            file_put_contents(dirname(dirname(__FILE__)).'/tests/caches_weixin/yitui.txt', json_encode($_tmp));
        }
        $page++;
        echo '已发送 <font color="red">' . ($page * $page_size) . '</font> 个用户';
        echo "<script>window.location.href='http://api.test.aihuaju.com/updoffmenus/weixin-push-one?page=" . $page . "';</script>";
    }

    public function actionWeixinPushTwo(){
        $access_token = $this->get_access_token();
        $page = intval($_GET['page']);
        $page = $page < 800 ? 801 : $page;
        $page_size = 20;
        if ($page > 1600) {
            echo '发送完成，共发送了 <font color="red">' . ($page * $page_size) . '</font> 个用户';
            die;
        }
        $data = $this->getFileLines(dirname(dirname(__FILE__)).'/tests/caches_weixin/app_id.txt', ($page * $page_size), (($page + 1) * $page_size));
        if (empty($data)) {
            echo '发送完成，共发送了 <font color="red">' . ($page * $page_size) . '</font> 个用户';
            die;
        }
        $web_url = 'pages/active?src=https://www.aihuaju.com/h5/hd/2020/520lph/index.html';
        foreach ($data as $k=>$d) {
            $d = str_replace(array("\n", "\r"), "", $d);
            $_tmp = file_get_contents(dirname(dirname(__FILE__)).'/tests/caches_weixin/yitui.txt');
            if ($_tmp) {
                $_tmp = json_decode($_tmp, true);
                if (in_array($d, $_tmp)) {
                    continue;
                }
            } else {
                $_tmp = array();
            }
            $wx_name = $this->get_weixin_name(trim($d));
            $data = array(
                'first' => array('value' => urlencode('恭喜您，已成功升级为黄金会员\n'), 'color' => '#ff0000'),
                'keyword1' => array('value' => urlencode(($wx_name ? $wx_name  : 'hi')), 'color' => '#03037c'),
                'keyword2' => array('value' => urlencode('黄金会员\n'), 'color' => '#03037c'),
                'keyword3' => array('value' => urlencode('2020年5月20日\n'), 'color' => '#03037c'),
                'remark' => array('value' => urlencode('今天520情人节，再犹豫花儿都没啦！\n趁现在还有花材，赶快下单\n晒幸福的时刻，别让她羡慕别的女人>>'), 'color' => '#ff0000'),
            );
            $result = $this->doSend($access_token, trim($d), 'oSS_3U3Xk_1b3i_KUcyAxoTZZnOHo6AmwmFDilAaXGs', $web_url, $data,1);
            if (!$result) {
                echo '错误';
                die;
            }
            $_tmp[] = trim($d);
            file_put_contents(dirname(dirname(__FILE__)).'/tests/caches_weixin/yitui.txt', json_encode($_tmp));
        }
        $page++;
        echo '已发送 <font color="red">' . ($page * $page_size) . '</font> 个用户';
        echo "<script>window.location.href='http://api.test.aihuaju.com/updoffmenus/weixin-push-two?page=" . $page . "';</script>";
    }

    public function actionWeixinPushThree(){
        $access_token = $this->get_access_token();
        $page = intval($_GET['page']);
        $page = $page < 1600 ? 1601 : $page;
        $page_size = 20;
        if ($page > 10000) {
            echo '发送完成，共发送了 <font color="red">' . ($page * $page_size) . '</font> 个用户';
            die;
        }
        $data = $this->getFileLines(dirname(dirname(__FILE__)).'/tests/caches_weixin/app_id.txt', ($page * $page_size), (($page + 1) * $page_size));
        if (empty($data)) {
            echo '发送完成，共发送了 <font color="red">' . ($page * $page_size) . '</font> 个用户';
            die;
        }
        $web_url = 'pages/active?src=https://www.aihuaju.com/h5/hd/2020/520lph/index.html';
        foreach ($data as $k=>$d) {
            $d = str_replace(array("\n", "\r"), "", $d);
            $_tmp = file_get_contents(dirname(dirname(__FILE__)).'/tests/caches_weixin/yitui.txt');
            if ($_tmp) {
                $_tmp = json_decode($_tmp, true);
                if (in_array($d, $_tmp)) {
                    continue;
                }
            } else {
                $_tmp = array();
            }
            $wx_name = $this->get_weixin_name(trim($d));
            $data = array(
                'first' => array('value' => urlencode('恭喜您，已成功升级为黄金会员\n'), 'color' => '#ff0000'),
                'keyword1' => array('value' => urlencode(($wx_name ? $wx_name  : 'hi')), 'color' => '#03037c'),
                'keyword2' => array('value' => urlencode('黄金会员\n'), 'color' => '#03037c'),
                'keyword3' => array('value' => urlencode('2020年5月20日\n'), 'color' => '#03037c'),
                'remark' => array('value' => urlencode('今天520情人节，再犹豫花儿都没啦！\n趁现在还有花材，赶快下单\n晒幸福的时刻，别让她羡慕别的女人>>'), 'color' => '#ff0000'),
            );
            $result = $this->doSend($access_token, trim($d), 'oSS_3U3Xk_1b3i_KUcyAxoTZZnOHo6AmwmFDilAaXGs', $web_url, $data,1);
            if (!$result) {
                echo '错误';
                die;
            }
            $_tmp[] = trim($d);
            file_put_contents(dirname(dirname(__FILE__)).'/tests/caches_weixin/yitui.txt', json_encode($_tmp));
        }
        $page++;
        echo '已发送 <font color="red">' . ($page * $page_size) . '</font> 个用户';
        echo "<script>window.location.href='http://api.test.aihuaju.com/updoffmenus/weixin-push-three?page=" . $page . "';</script>";
    }
    //推送微信模板信息
    public function doSend($access_token, $touser, $template_id, $url, $data, $is_mini = 0,$topcolor = '#7B68EE')
    {
        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'url' => $url,
            'topcolor' => $topcolor,
            'data' => $data
        );
        //是否跳转小程序
        if($is_mini && $url){
            $template['miniprogram'] = [
                'appid'=>'wxc0516d6abf2093a6',
                'pagepath'=> $url
            ];
            unset($template['url']);
        }
        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $dataRes = https_request($url, urldecode($json_template));
        if ($_GET['debug']) {
            echo $dataRes;
            die;
        }
        if ($dataRes['errcode'] == 0) {
            return true;
        } else {
            return false;
        }
    }

    private function get_weixin_name($oppid = '')
    {
        if (!$oppid) {
            return '';
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $this->get_access_token() . '&openid=' . $oppid . '&lang=zh_CN';
        $data = https_request($url);
        $data = json_decode($data, true);
        return $data['nickname'];
    }

    //获取所有关注用户appid
    public function actionGetWxinfo()
    {
        //echo '暂不开发';die;
        if ($_GET['page'] && !$_GET['next_openid']) {
            echo '完成';
            die;
        }
        $access_token = $this->get_access_token();
        $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token={$access_token}";
        if ($_GET['page']) {
            $url .= "&next_openid={$_GET['next_openid']}";
        } else {
            unlink("caches/caches_weixin/app_id.txt");
        }
        $result = https_request($url);
        $jsoninfo = json_decode($result, true);
        //print_r($jsoninfo);die;
        $this->fopen_data($jsoninfo['data']['openid']);
        $next_openid = $jsoninfo['next_openid'];
        $page = intval($_GET['page']) + 1;
        echo "<script>window.location.href='http://api.test.aihuaju.com/updoffmenus/get-wxinfo?page=" . $page . "&next_openid=" . $next_openid . "';</script>";
    }

    //写入文件
    private function fopen_data($data = array())
    {
        foreach ($data as $d) {
            $result = file_put_contents(dirname(dirname(__FILE__)).'/tests/caches_weixin/app_id.txt', $d . "\n", FILE_APPEND);
        }
    }

    /** 返回文件从X行到Y行的内容(支持php5、php4)
     * @param string $filename 文件名
     * @param int $startLine 开始的行数
     * @param int $endLine 结束的行数
     * @return string
     */
    public function getFileLines($filename, $startLine = 1, $endLine = 50, $method = 'rb')
    {
        $content = array();
        $count = $endLine - $startLine;
        $fp = fopen($filename, $method);
        if (!$fp)
            return 'error:can not read file';
        for ($i = 1; $i < $startLine; ++$i) {// 跳过前$startLine行
            fgets($fp);
        }
        for ($i; $i <= $endLine; ++$i) {
            $content[] = fgets($fp); // 读取文件行内容
        }
        fclose($fp);
        // }
        return array_filter($content); // array_filter过滤：false,null,''
    }
    public function actionWeixinSouvenirPush($data,$member)
    {
        $access_token = $this->get_access_token();
        $web_url = '/pages/index-new';
        return $this->doSend($access_token, $member, 'etVixhHZrfpbDi6SmkUpmknXZG6mk_cWgvaljC_LDVg' , $web_url, $data,1);
    }
}