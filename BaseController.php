<?php

namespace frontend\controllers;

use common\components\FinalPrice;
use common\components\Message;
use common\models\Member;
use common\models\Site;
use Faker\Factory;
use Yii;
use yii\web\Controller;

class BaseController extends Controller
{
    //当前会话ID
    protected $sessionid;
    //当前用户TOKEN
    protected $token = '';
    //当前用户ID
    protected $member_id = 0;
    //当前用户名
    protected $member_name = '';
    //用户资料
    protected $member_info = [];
    //当前代理商ID
    protected $agent_id = 0;
    //当前请求版本
    protected $version = '';

    public function init()
    {

        $this->enableCsrfValidation = false;

        //创建全局统一会话ID
        $this->setSsid();

        //设置全局会员信息
        $this->setIdentity();

        //设置全局站点信息
        $this->setSite();
        //设置全局站点信息
        $this->setVersion();
        //设置代理商信息
        //$this->setAgent();
        /*header('Content-Type: text/html;charset=utf-8');
        header('Access-Control-Allow-Origin:*'); // *代表允许任何网址请求
        header('Access-Control-Allow-Methods:POST,GET,OPTIONS,PUT'); // 允许请求的类型
        header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
        header('Access-Control-Allow-Headers: Content-Type,Content-Length,usertoken,Accept-Encoding,Accept,X-Requested-with, Origin'); // 设置允许自定义请求头的字段*/
    }
    /**
     * 设置版本信息
     */
    protected function setVersion(){
        if($base_version = Request('api_version')){
            $this->version = intval($base_version);
        }
    }
    /**
     * 创建统一会话ID
     * @param string $ssid
     */
    protected function setSsid($ssid = '')
    {
        //从系统中设置
        if ($ssid) return $this->sessionid = $ssid;

        //从请求中设置
        if (isUuid(Request('ssid'))) {
            $this->sessionid = Request('ssid');
        } else {
            $faker = Factory::create('zh_CN');
            $this->sessionid = $faker->uuid;
        }
    }

    /**
     * 设置全局会员身份
     * @param array $member_info
     */
    protected function setIdentity($member_info = [])
    {

        if ($member_info) return $this->setMember($member_info);
        if (preg_match("/^[\w+]{32}$/", Request('token'))) {
            $this->token = Request('token');
            $member = new Member();
            $member_info = $member->getMemberByToken(Request('token'));
            if ($member_info) {
                if ($member_info['member_token_expire'] > TIMESTAMP) {
                    $this->setMember($member_info);
                }
            }
        }

    }

    /**
     * 设置全局会员信息
     * @param $member_info
     */
    protected function setMember($member_info)
    {
        $this->member_info = $member_info;
        $this->member_id = $member_info['member_id'];
        $this->member_name = mobile_format($member_info['member_nickname']);
    }

    /**
     * 检查用户是否登录
     * @return bool
     */
    protected function isLogin()
    {
        return !empty($this->member_info);
    }

    /**
     * 检查用户是否登录未登录直接抛出
     * @return bool
     */
    protected function validLogin()
    {
        if (!$this->isLogin()) {
            if (Yii::$app->request->isPost) {
                $this->responseJson(Message::UN_LOGIN, Message::UN_LOGIN_MSG);
                Yii::$app->response->send();
            } else {
                //直接打开的页面
                header('Location:' . WEB_DOMAIN);
            }
            die;
        }
        return true;
    }

    /**
     * 设置全局站点信息
     * @param int $siteid
     */
    protected function setSite($siteid = 0)
    {
        $from_site = in_array(FROM_DOMAIN,array('wx1.aihuaju.cn','wx2.aihuaju.cn')) ? FROM_DOMAIN : 'web.aihuaju.com';
        if (false == $site = cache('api_site_' . $siteid . $from_site)) {
            $site = Site::findOne($siteid > 0 ? $siteid : ['site_host'=>$from_site]);
            cache('api_site_' . $siteid . $from_site, $site, 86400);
        }
        $this->setParams($site->getAttributes());

        //初始化全局与价格相关的变量
        FinalPrice::initialize($this->sessionid, $this->member_id, Request('agent_id', 0));
    }

    /**
     * 设置全局代理商信息
     */
    protected function setAgent()
    {
        //即使不传入agent_id也要通过会员查询代理信息
        $this->agent_id = FinalPrice::getAgentId();
    }

    /**
     * 设置Yii全局参数
     * @param $params
     */
    protected function setParams($params)
    {
        Yii::$app->params = array_merge(Yii::$app->params, $params);
    }

    /**
     * api返回json信息
     * @param int $code
     * @param string $msg
     * @param string $content
     * @return mixed
     */
    public function responseJson($code = 0, $msg = "", $content = [])
    {
        if (Yii::$app->request->isGet && false) {
            return $this->redirect(WEB_DOMAIN . '?' . http_build_query(['err_code' => $code, 'err_msg' => $msg]));
        } else {
            return $this->asJson([
                'code' => $code,
                'msg' => $msg,
                'content' => $content,
                'ssid' => $this->sessionid,
            ]);
        }
    }

    /**
     *    作用：格式化参数，签名过程需要使用
     */
    public function formatBizQueryParaMap($paraMap, $urlencode = 0)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = "";
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }
}
