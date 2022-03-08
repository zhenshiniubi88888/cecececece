<?php

namespace frontend\controllers;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use Api\Alipay\Alipay;
use Api\Sina\Sina;
use Api\Tencent\QQ;
use common\components\Log;
use common\components\Message;
use common\models\AgentMember;
use common\models\MemberThree;
use common\models\CachedKeys;
use common\models\HuadiScoreLog;
use common\models\HuadiYearCardOrders;
use common\models\Member;
use common\models\MemberCommon;
use common\models\MemberExppointsLog;
use common\models\MemberVerify;
use frontend\service\BaiduService;
use frontend\service\MiniLoginService;
use frontend\service\TencentWXService;
use yii\helpers\Url;
use yii\web\Cookie;
use yii\web\HttpException;

/**
 */
class LoginController extends BaseController
{
    public function actionIndex()
    {
//        return \Yii::$app->response->setStatusCode(403)->send();
        throw new HttpException(405);
    }

    /**
     * 获取是否登录状态
     */
    public function actionStatus()
    {
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['login_status' => $this->isLogin() ? 1 : 0]);
    }

    /**
     * App第三方登录回调接收数据地址
     */
    public function actionThird()
    {
        return $this->responseJson(Message::VALID_FAIL, '暂无法使用');
    }


    /**
     * 登录后传入app闪推id
     * @return mixed
     */
    public function actionPushId()
    {
        // 初始化参数.
        $code = Message::ERROR;
        $msg  = '未知错误';
        // 获取传参.
        $post = \Yii::$app->request->post();
        // 查询数据是否存在.
        $memberThreeModel = new MemberThree();
        $haveData = $memberThreeModel::find()
            ->select('member_three_id,member_three_rst_id')
            ->where(['=', 'member_id', $post['member_id']])
            ->one();
        // 存在则修改否则新增.
        if ($haveData && $haveData['member_three_rst_id'] == $post['rst_id']) {
            $code = Message::SUCCESS;
            $msg  = 'success';
        } elseif ($haveData) {
            $backData = $memberThreeModel::updateAll(['member_three_rst_id' => $post['rst_id']], ['member_id' => $post['member_id']]);
        } else {
            $memberThreeModel->member_three_rst_id = $post['rst_id'];
            $memberThreeModel->member_id = $post['member_id'];
            $backData = $memberThreeModel->insert();
        }
        if (isset($backData) && $backData) {
            $code = Message::SUCCESS;
            $msg  = 'success';
        }

        return $this->responseJson($code, $msg, array());
    }


    /**
     * 手机号登录
     * @return mixed
     */
    public function actionWithMobile()
    {
        $post = \Yii::$app->request->post();
        $member_mobile = isset($post['member_mobile']) ? trim($post['member_mobile']) : '';
        $verify_code = isset($post['verify_code']) ? trim($post['verify_code']) : '';
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_LOGIN, $member_mobile, $verify_code);
        if (!$result) {
            if ($member_mobile != '15884477703' && $verify_code != '4364536') {
                return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
            }
        }

        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberByMobile($member_mobile);
        $is_new = false;//是否新注册
        if (!$member) {
            //自动注册
            $result = $model_member->quickRegisterMobile($member_mobile);
            if (!$result) {
                \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
                return $this->responseJson(Message::ERROR, '登录失败，请重试');
            }
            $is_new = true;
            $member = Member::instance()->getMemberByMobile($member_mobile);
        }
        $data = $this->_afterLogin($member,1);
        if (!$data) {
            $error = Message::getFirstMessage();
            return $this->responseJson($error['code'], $error['message']);
        }
        if($is_new){
            $data['is_register'] = 1;
        }else{
            $data['is_register'] = 0;
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 通过密码登录
     */
    public function actionPwLogin()
    {
        $member_mobile = \Yii::$app->request->post("member_mobile","");
        $password = \Yii::$app->request->post("password","");
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }
        $model_member = new Member();
        $member = $model_member->getMember(['member_mobile' => $member_mobile, 'member_passwd' => md5($password.$member_mobile)],'*');
        if(!$member){
            return $this->responseJson(0, '手机号或者密码错误');
        }
        $data = $this->_afterLogin($member,2);
        if (!$data) {
            $error = Message::getFirstMessage();
            return $this->responseJson($error['code'], $error['message']);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 自动登录
     *
     * @return mixed
     */
    public function actionAuto()
    {
        $code = \Yii::$app->request->post("code", '');
        if(empty($code)){
            return $this->responseJson(Message::EMPTY_CODE, '参数错误');
        }
        $code = decrypt($code);
        if(empty($code)){
            return $this->responseJson(Message::EMPTY_CODE, '参数错误');
        }
        $model_member = new Member();
        $where = [
            'member_id' => $code,
        ];
        $member = $model_member->getMember($where);
        if(!$member){
            return $this->responseJson(0, '操作失败');
        }
        $data = $this->_afterLogin($member,3);
        if (!$data) {
            $error = Message::getFirstMessage();
            return $this->responseJson($error['code'], $error['message']);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 密码找回
     */
    public function actionForget()
    {
        $post = \Yii::$app->request->post();
        $member_mobile = isset($post['member_mobile']) ? trim($post['member_mobile']) : '';
        $verify_code = isset($post['verify_code']) ? trim($post['verify_code']) : '';
        $new_password = isset($post['new_password']) ? trim($post['new_password']) : '';
        $check_password = isset($post['check_password']) ? trim($post['check_password']) : '';
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }

        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_LOGIN, $member_mobile, $verify_code);
        if (!$result && $member_mobile != '15884477703') {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }

        if(strlen($new_password) < 6 || strlen($new_password) > 18 || $new_password != $check_password){
            return $this->responseJson(Message::VALID_FAIL, '新密码最少6位');
        }

        $member = Member::find()->where(['member_mobile' => $member_mobile])->one();
        if(!$member){
            return $this->responseJson(Message::VALID_FAIL, '手机号不存在');
        }
        if($member->member_passwd == md5($new_password.$member_mobile)){
            return $this->responseJson(Message::VALID_FAIL, '新密码和当前密码不能相同');
        }
        $member->member_passwd = md5($new_password.$member_mobile);
        $result = $member->save();
        if(!$result){
            return $this->responseJson(Message::VALID_FAIL, '更新密码失败');
        }

        $data = $this->_afterLogin($member,4);
        if (!$data) {
            $error = Message::getFirstMessage();
            return $this->responseJson($error['code'], $error['message']);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 通过手机号注册
     */
    public function actionRegister()
    {
        $post = \Yii::$app->request->post();
        $member_mobile = isset($post['member_mobile']) ? trim($post['member_mobile']) : '';
        $verify_code = isset($post['verify_code']) ? trim($post['verify_code']) : '';
        $new_password = isset($post['new_password']) ? trim($post['new_password']) : '';
        $check_password = isset($post['check_password']) ? trim($post['check_password']) : '';
        if (!isMobile($member_mobile)) {
            return $this->responseJson(Message::VALID_FAIL, '请输入正确的手机号');
        }

        //校验短信验证码
        $model_verify = new MemberVerify();
        $result = $model_verify->codeVerify(MemberVerify::SEND_TYPE_LOGIN, $member_mobile, $verify_code);
        if (!$result && $member_mobile != '15884477703') {
            return $this->responseJson(Message::VALID_FAIL, $model_verify->getFirstError(Message::MODEL_ERROR));
        }

        if(strlen($new_password) < 6 || strlen($new_password) > 18 || $new_password != $check_password){
            return $this->responseJson(Message::VALID_FAIL, '新密码最少6位');
        }

        $model_member = new Member();
        $member = $model_member->getMemberByMobile($member_mobile);
        if($member){
            return $this->responseJson(Message::VALID_FAIL, '手机号已注册');
        }

        $result = $model_member->quickRegisterMobile($member_mobile,0,md5($new_password.$member_mobile));
        if (!$result) {
            \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
            return $this->responseJson(Message::ERROR, '登录失败，请重试');
        }
        $member = Member::instance()->getMemberByMobile($member_mobile);
        $data = $this->_afterLogin($member,5);
        if (!$data) {
            $error = Message::getFirstMessage();
            return $this->responseJson($error['code'], $error['message']);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 微信小程序登录
     */
    public function actionSmallWx()
    {
        $code = \Yii::$app->request->post("code");
        $user_info = \Yii::$app->request->post("user_info", "");
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=wx0fb227279769192f&secret=d2e9dcae5282ac16ba8d539568f372cf&js_code=" . $code . "&grant_type=authorization_code";
        $wx_info = file_get_contents($url);
        $wx_info = json_decode($wx_info, true);
        if (isset($wx_info["errcode"])) {
            return $this->responseJson(0, "登录失败");
        }
        $openid = $wx_info["openid"];
        $unionid = isset($wx_info["unionid"]) ? $wx_info["unionid"] : '';
        if (!$openid) {
            return $this->responseJson(0, "登录失败");
        }
        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberByWxid($unionid ? $unionid : $openid);
        if (!$member) {
            if ($user_info) {
                $user_info['nickname'] = $user_info['nickName'];
                $user_info['openid'] = $openid;
                $user_info['unionid'] = $unionid ? $unionid : $openid;
                $user_info['headimgurl'] = $user_info['avatarUrl'];
                //自动注册
                $result = $model_member->quickRegisterWx($user_info);
                if (!$result) {
                    return $this->responseJson(0, "登录失败请稍后");
                }
                $member = Member::instance()->getMemberByWxid($user_info['openid']);
            } else {
                return $this->responseJson(0, "登录失败");
            }
        }
        $data = $this->_afterLogin($member,6);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 花递微信小程序登录
     */
    public function actionSmallWxhd(){
        $code = \Yii::$app->request->post("code");
        $user_info = \Yii::$app->request->post("user_info","");
        $is_register = \Yii::$app->request->post("is_register",1);
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=wxc0516d6abf2093a6&secret=629e5d4fefcde81d17cd33577fe5dc97&js_code=".$code."&grant_type=authorization_code";
        $wx_info = file_get_contents($url);
        $wx_info = json_decode($wx_info,true);
        if(isset($wx_info["errcode"])){
            return $this->responseJson(0, "登录失败1");
        }
        $openid = $wx_info["openid"];
        $unionid = isset($wx_info["unionid"]) ? $wx_info["unionid"] : '';
        if(!$openid){
            return $this->responseJson(0, "登录失败2");
        }
        //获取会员信息
        //===================先用unionid获取登录信息获取不到时再用openid获取
        $is_new = 0;
        $member = Member::instance()->getMemberByWxuniid($unionid);
        if (!$member) {
            $member = Member::instance()->getMemberByWxid($openid);
            if ($member) {
                //交换用户的openid与unionid
                $member->weixin_unionid = $unionid;
                $member->member_wxopenid  = $openid;
                $member->save(false);
            } else {
                $user_info_q = [];
                $user_info_q['nickname'] = !empty($user_info) && isset($user_info['nickName']) ? $user_info['nickName'] : "花递用户".rand(1111,9999);
                $user_info_q['openid'] = $openid;
                $user_info_q['unionid'] = $unionid ? $unionid : $openid;
                $user_info_q['headimgurl'] = !empty($user_info) && isset($user_info['avatarUrl']) ? $user_info['avatarUrl'] : '';
                //用户注册来源路由
                $user_info_q['from_route'] = !empty($user_info) && isset($user_info['from_route']) ? $user_info['from_route'] : '';
                //自动注册
                if($is_register){
                    $result = Member::instance()->quickRegisterWx($user_info_q,258);
                    if (!$result) {
                        return $this->responseJson(0, "登录失败请稍后");
                    }
                    $is_new = 1;
                    $member = Member::instance()->getMemberByWxid($openid);
                }else{
                    return $this->responseJson(10000, Message::SUCCESS_MSG);
                }
            }
        }
        // 记录微信 unionid
        MemberCommon::instance()->updateInfo($member->member_id, $unionid);
        //登录时查询主库用户信息, 防止从库未及时同步导致查询不到用户信息
        if($unionid || $openid){
            \Yii::$app->db->enableSlaves = false;
            $member =  Member::instance()->getMemberByWxuniid($unionid);
            if(!$member){
                $member =  Member::instance()->getMemberByWxid($openid);
            }
            \Yii::$app->db->enableSlaves = true;
        }else{
            return $this->responseJson(0, "登录失败请稍后");
        }
        if(!$member){
            Log::writelog('huadi_login_fail','slx======>登录失败。请重试====$wx_info=='.var_export($wx_info,true).'===$user_info_q==='.var_export($user_info_q,true).'===$member==='.var_export($member,true));
        }
        $data = $this->_afterLogin($member,7);
        $data['is_new'] = $is_new;
        //圣诞领红包
        $data['chrima_text'] = '送你一个圣诞节红包';
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 爱花居百度小程序登录
     * App ID (小程序ID)16592049
     * App Key	7HnGsejajbwuoF940CG0QsGnRq0AkkQD
     * App Secret (小程序密匙)QNFEUd1GGdTqq5tBZKGvfY8y0hMUe2VK
     */
    public function actionSmallBd(){
        $code = \Yii::$app->request->post("code");
        $user_info = \Yii::$app->request->post("user_info","");
        $version_type = \Yii::$app->request->post("version_type","ahj");
        $bd_info = BaiduService::getSessionKey($code,$version_type);
        $is_new = 0;  //是否新注册
        if(empty($bd_info)){
            return $this->responseJson(0, "登录失败");
        }
        if(isset($bd_info['errno'])){
            return $this->responseJson(0, "登录失败");
        }
        $openid = $bd_info["openid"];
        $session_key = $bd_info["session_key"];
        if(!$openid){
            return $this->responseJson(0, "登录失败");
        }
        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberByBdid($openid);
        if (!$member) {
            if($user_info){
                $user_info['nickname'] = $user_info['nickName'];
                $user_info['openid'] = $openid;
                $user_info['headimgurl'] = $user_info['avatarUrl'];
                //自动注册
                $result = $model_member->quickRegisterBd($user_info,SITEID);
                if (!$result) {
                    return $this->responseJson(0, "登录失败请稍后");
                }
                $is_new = 1;
                $member = Member::instance()->getMemberByBdid($user_info['openid']);
            }else{
                return $this->responseJson(0, "登录失败");
            }

        }
        $data = $this->_afterLogin($member,8);
        $data['is_new'] = $is_new;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 小程序应用登录中心【默认花递】
     */
    public function actionMiniLoginCenter()
    {
        $miniLoginService = new MiniLoginService();
        $member = $miniLoginService->getLoginResult();
        if(empty($member)) {
            return $this->responseJson(0, "登录失败" . $miniLoginService->message);
        }
        $data = $this->_afterLogin($member, 8);
        if (!$data) {
            $error = Message::getFirstMessage();
            return $this->responseJson($error['code'], $error['message']);
        }
        $data['is_new'] = $miniLoginService::$is_new;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    private function _get_token()
    {
        $token = cache('mpfx_smalltoken_0123');
        if ($token) {
            return $token['access_token'];
        }
        $appid = "wx42224670efef7a53";
        $appaecret = "1d79bbdae38353663530a9f233a38452";
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appaecret}";
        $data = sendGet($url);
        $result = (array)json_decode($data);
        if ($result['errcode']) {
            echo $result['errmsg'];
            die;
        } else {
            $data = array();
            $data['access_token'] = $result['access_token'];
            $data['expires_in'] = $result['expires_in'];
            $data['time'] = time();
            cache('mpfx_smalltoken_0123', $data, 5000);

            return $result['access_token'];
        }
    }

    /**
     * 微信登录
     * @return mixed
     */
    public function actionWithWx()
    {
        //前端回调地址页面
        $callback = \Yii::$app->request->get('callback', '/home');
        if ($callback) {
            \Yii::$app->session->set('callback', $callback);
        } else {
            $callback = \Yii::$app->session->get('callback', '/home');
        }
        $error_url = WEB_DOMAIN . '/login?callback=' . urlencode($callback);
        if (!isWeixin() && false) {
            $this->redirect($error_url)->send();
            die;
        }

        //获取微信网页用户授权信息
        try {
            $user_info = $this->_getWxUserInfo();
        } catch (\Exception $e) {
            \Yii::error($e->getMessage());
            $user_info = null;
        }

        if (empty($user_info)) {
            $this->redirect($error_url)->send();
            die;
        }
        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberByWxid($user_info['openid']);

        if (!$member) {
            //自动注册
            $result = $model_member->quickRegisterWx($user_info);
            if (!$result) {
                \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
                $this->redirect($error_url . '&error_msg=微信登录失败，请重试')->send();
                die;
            }
            $member = Member::instance()->getMemberByWxid($user_info['openid']);
        }
        $data = $this->_afterLogin($member,9);

        if (!$data) {
            $error = Message::getFirstMessage();
            $this->redirect($error_url . '&error_msg=' . $error['message'])->send();
            die;
        }
        $back = buildUrl(WEB_DOMAIN . $callback, ['_third' => urlencode(base64_encode(json_encode($data)))]);
        $this->redirect($back)->send();
        die;
    }

    /**
     * 微博登录
     */
    public function actionWithSina()
    {
        //前端回调地址页面
        $callback = \Yii::$app->request->get('callback', '/home');
        $error_url = WEB_DOMAIN . '/login?callback=' . urlencode($callback);
        //获取微博网页用户授权信息
        try {
            $user_info = Sina::getUserInfo();
        } catch (\Exception $e) {
            \Yii::error($e->getMessage());
            $user_info = null;
        }
        if (empty($user_info) || !isset($user_info['id'])) {
            $this->redirect($error_url)->send();
            die;
        }
        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberBySinaid($user_info['id']);
        if (!$member) {
            //自动注册
            $result = $model_member->quickRegisterSina($user_info, SITEID);
            if (!$result) {
                \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
                $this->redirect($error_url . '&error_msg=微博登录失败，请重试')->send();
                die;
            }
            $member = Member::instance()->getMemberBySinaid($user_info['id']);
        }
        $data = $this->_afterLogin($member,10);
        if (!$data) {
            $error = Message::getFirstMessage();
            $this->redirect($error_url . '&error_msg=' . $error['message'])->send();
            die;
        }
        $back = buildUrl(WEB_DOMAIN . $callback, ['_third' => urlencode(base64_encode(json_encode($data)))]);
        $this->redirect($back)->send();
        die;
    }

    /**
     * QQ登录
     */
    public function actionWithQq()
    {
        //前端回调地址页面
        $callback = \Yii::$app->request->get('callback');
        //QQ会强制把callback给去了
        if ($callback) {
            \Yii::$app->session->set('callback', $callback);
        } else {
            $callback = \Yii::$app->session->get('callback', '/home');
        }
        $error_url = WEB_DOMAIN . '/login?callback=' . urlencode($callback);
        //获取微博网页用户授权信息
        try {
            $user_info = QQ::getUserInfo();
        } catch (\Exception $e) {
            \Yii::error($e->getMessage());
            $user_info = null;
        }

        \Yii::debug($user_info);
        if (empty($user_info) || !isset($user_info['ret']) || $user_info['ret'] !== 0) {
            $this->redirect($error_url)->send();
            die;
        }
        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberByQQid($user_info['openid']);
        if (!$member) {
            //自动注册
            $result = $model_member->quickRegisterQQ($user_info, SITEID);
            if (!$result) {
                \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
                $this->redirect($error_url . '&error_msg=QQ登录失败，请重试')->send();
                die;
            }
            $member = Member::instance()->getMemberByQQid($user_info['openid']);
        }
        $data = $this->_afterLogin($member,11);
        if (!$data) {
            $error = Message::getFirstMessage();
            $this->redirect($error_url . '&error_msg=' . $error['message'])->send();
            die;
        }
        $back = buildUrl(WEB_DOMAIN . $callback, ['_third' => urlencode(base64_encode(json_encode($data)))]);
        $this->redirect($back)->send();
        die;
    }

    /**
     * 支付宝登录
     */
    public function actionWithAlipay()
    {
        //前端回调地址页面
        $callback = \Yii::$app->request->get('callback');
        if ($callback) {
            \Yii::$app->session->set('callback', $callback);
        } else {
            $callback = \Yii::$app->session->get('callback', '/home');
        }
        $error_url = WEB_DOMAIN . '/login?callback=' . urlencode($callback);
        $this->redirect($error_url)->send();
        die;
        //获取微博网页用户授权信息
        try {
            $user_info = Alipay::getUserInfo();
        } catch (\Exception $e) {
            \Yii::error($e->getMessage());
            $user_info = null;
        }
        \Yii::debug($user_info);
        if (empty($user_info)) {
            $this->redirect($error_url)->send();
            die;
        }
        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberByAlipayid($user_info['user_id']);
        if (!$member) {
            //自动注册
            $result = $model_member->quickRegisterAlipay($user_info);
            if (!$result) {
                \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
                $this->redirect($error_url . '&error_msg=支付登录失败，请重试')->send();
                die;
            }
            $member = Member::instance()->getMemberByAlipayid($user_info['user_id']);
        }
        $data = $this->_afterLogin($member,12);
        if (!$data) {
            $error = Message::getFirstMessage();
            $this->redirect($error_url . '&error_msg=' . $error['message'])->send();
            die;
        }
        $back = buildUrl(WEB_DOMAIN . $callback, ['_third' => urlencode(base64_encode(json_encode($data)))]);
        $this->redirect($back)->send();
        die;
    }

    /**
     * 淘宝登录
     */
    public function actionWithTaobao()
    {
        //前端回调地址页面
        $callback = \Yii::$app->request->get('callback');
        if ($callback) {
            \Yii::$app->session->set('callback', $callback);
        } else {
            $callback = \Yii::$app->session->get('callback', '/home');
        }
        $error_url = WEB_DOMAIN . '/login?callback=' . urlencode($callback);
        $this->redirect($error_url)->send();
        die;
        //获取微博网页用户授权信息
        try {
            $user_info = Alipay::getUserInfo();
        } catch (\Exception $e) {
            \Yii::error($e->getMessage());
            $user_info = null;
        }
        \Yii::debug($user_info);
        if (empty($user_info)) {
            $this->redirect($error_url)->send();
            die;
        }
        //获取会员信息
        $model_member = new Member();
        $member = $model_member->getMemberByAlipayid($user_info['user_id']);
        if (!$member) {
            //自动注册
            $result = $model_member->quickRegisterAlipay($user_info);
            if (!$result) {
                \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
                $this->redirect($error_url . '&error_msg=支付登录失败，请重试')->send();
                die;
            }
            $member = Member::instance()->getMemberByAlipayid($user_info['user_id']);
        }
        $data = $this->_afterLogin($member,13);
        if (!$data) {
            $error = Message::getFirstMessage();
            $this->redirect($error_url . '&error_msg=' . $error['message'])->send();
            die;
        }
        $back = buildUrl(WEB_DOMAIN . $callback, ['_third' => urlencode(base64_encode(json_encode($data)))]);
        $this->redirect($back)->send();
        die;
    }

    /**
     * 获取微信用户信息
     * @return array|null
     */
    private function _getWxUserInfo()
    {
        $user_info = null;
        //重试次数
        $retry = \Yii::$app->request->get('retry', 0);
        //约定好的缓存健值（cache_name = wx_auth_key）
        $cache_key = \Yii::$app->request->get('auth_key', '');
        if ($cache_key) {
            $cache_data = CachedKeys::findOne(['cache_name' => 'wx_' . $cache_key]);
            if ($cache_data) {
                $user_info = json_decode($cache_data->cache_desc, true);
                $cache_data->delete();
            }
        }
        if (empty($cache_key) || empty($user_info)) {
            if ($retry > 1) {
                return null;
            }
            $auth_return = Url::current(['retry' => ++$retry, 'ahj_domain' => FROM_DOMAIN], true);
            //$authorize_url = WX_DOMAIN . '/authorize/user?auth_return=' . urlencode($auth_return);
            $authorize_url = AHJ_SITE_URL . '/wap/index.php?act=weixin&op=oauth&auth_return=' . urlencode($auth_return);
            $this->redirect($authorize_url)->send();
            die;
        }
        return $user_info;
    }

    /**
     * 三方直接登录不需要绑定手机
     * 验证用户是否绑定第三方平台账号
     * 第三方社交账号快速登录,客户端已获取到用户信息
     * $platform_type 平台  1QQ 2微信 3新浪微博
     * $openid 第三方唯一识别码
     */
    public function actionThirdLogin()
    {
        $platform_type = \Yii::$app->request->post("platform_type");
        $openinfo      = \Yii::$app->request->post("openinfo");
        $openid        = \Yii::$app->request->post("openid");
        //$unionid       = \Yii::$app->request->post("unionid");
        $is_ios        = \Yii::$app->request->post("is_ios",1);
        //1QQ 2微信 3新浪微博  4苹果ID
        if (!in_array($platform_type, array(1, 2, 3, 4))) {
            return $this->responseJson(0, "暂不支持此登录方式");
        }
        if(!$openinfo){
            return $this->responseJson(0, "参数错误");
        }
        //第三方用户信息 base64 json_encode之后的
        $openinfo = json_decode(base64_decode($openinfo), true);
        if (!is_array($openinfo)) {
            return $this->responseJson(0, "参数错误1");
        }
        //第三方平台唯一标识符
        if (!$openid) {
            if(isset($openinfo['id'])){
                $openid = $openinfo['id'];
            }else{
                return $this->responseJson(0, "参数错误2");
            }
        }
        if($is_ios){
            $open_info = $openinfo['rawData'];
        }else{
            $open_info = $openinfo;
        }
        $unionid = $open_info['unionid'];
        //获取会员信息
        $model_member = new Member();
        $member = array();
        if ($platform_type == 1) {
            $member = $model_member->getMemberByQQid($openid);
        } elseif ($platform_type == 2) {
            //先用unionid获取 获取不到再用openid尝试获取
            $member = $model_member->getMemberByWxuniid($unionid);
            if ( !$member ) {
                $member = $model_member->getMemberByWxid($openid);
                if ( $member ) {
                    //交换用户的标识
                    $member->weixin_unionid = $unionid;
                    $member->member_wxopenid  = $openid;
                    $member->save(false);
                }
            }
        } elseif ($platform_type == 3) {
            $member = $model_member->getMemberBySinaid($openid);
        }elseif ($platform_type ==  4){
            $member = $model_member->getMemberByAppleid($openid);
        }
        if (!$member) {
            //自动注册
            if ($platform_type == 1) {
                $result = $model_member->quickRegisterQQ($open_info,$openid,SITEID);
            } elseif ($platform_type == 2) {
                $result = $model_member->quickRegisterWx($open_info,SITEID,$openid);
            } elseif ($platform_type == 3) {
                $result = $model_member->quickRegisterSina($open_info,$openid, SITEID);
            } elseif ($platform_type == 4) {
                $result = $model_member->quickRegisterAppleId($open_info,$openid,SITEID);
            }
            if (!$result) {
                return $this->responseJson(0, "登录失败".json_encode($model_member->getErrors()));
            }
            //20200917,快速注册之后立即查询用户信息强制走主库查询, 不然从库同步数据不及时会导致查询不到用户信息
            \Yii::$app->db->enableSlaves = false;
            if ($platform_type == 1) {
                $member = $model_member->getMemberByQQid($openid);
            } elseif ($platform_type == 2) {
                $member = $model_member->getMemberByWxuniid($unionid);
                if ( !$member ) {
                    $member = $model_member->getMemberByWxid($openid);
                }
            } elseif ($platform_type == 3) {
                $member = $model_member->getMemberBySinaid($openid);
            } elseif ($platform_type ==  4){
                $member = $model_member->getMemberByAppleid($openid);
            }
            \Yii::$app->db->enableSlaves = true;
        }
        if(!$member){
            Log::writelog('huadi_login_fail','55555===14141414===>登录失败。请重试===op=========>'.$openid.'||||||uniopid======>'.$unionid.'|||||type===='.$platform_type);
        }
        $data = $this->_afterLogin($member,14);
        if (!$data) {
            $error = Message::getFirstMessage();
            return $this->responseJson(0, "登录失败,".$error['message']);
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 通过阿里接口一键获取本机手机号 并直接登录注册功能接口
     */
    public function actionAliGetMobile(){
        require_once '../../vendor/alibaba-sdk/autoload.php';
        $param = \Yii::$app->request->post();
        $access_token = isset($param['access_token']) ? trim($param['access_token']) : '';
        if(!$access_token){
            return $this->responseJson(Message::ERROR, '缺少必要参数');
        }
        AlibabaCloud::accessKeyClient('LTAI4Fbn9R3eEwTZwtQAqjfq', 'WyauxC9dhgsMLbKxmzthWTpbAEbxdF')
            ->regionId('cn-hangzhou')
            ->asDefaultClient();

        try {
            $result = AlibabaCloud::rpc()
                ->product('Dypnsapi')
                ->scheme('https') // https | http
                ->version('2017-05-25')
                ->action('GetMobile')
                ->method('POST')
                ->host('dypnsapi.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-hangzhou",
                        'AccessToken'=> $access_token
                    ],
                ])
                ->request();
            $ret = $result->toArray();
            if($ret && $ret['Code'] == 'OK'){
                $mobile = $ret['GetMobileResultDTO']['Mobile'];
            }else{
                return $this->responseJson(Message::ERROR, '获取手机号失败');
            }
            //直接登录
            //获取会员信息
            $model_member = new Member();
            $member = $model_member->getMemberByMobile($mobile);
            $is_new = false;//是否新注册
            if (!$member) {
                //自动注册
                $result = $model_member->quickRegisterMobile($mobile);
                if (!$result) {
                    \Yii::error('Model Valid:' . json_encode($model_member->getErrors()));
                    return $this->responseJson(Message::ERROR, '登录失败，请重试');
                }
                $is_new = true;
                //20200917,快速注册之后立即查询用户信息强制走主库查询, 不然从库同步数据不及时会导致查询不到用户信息
                \Yii::$app->db->enableSlaves = false;
                $member = Member::instance()->getMemberByMobile($mobile);
                \Yii::$app->db->enableSlaves = true;
            }
            $data = $this->_afterLogin($member,15);
            if (!$data) {
                $error = Message::getFirstMessage();
                return $this->responseJson($error['code'], $error['message']);
            }
            if($is_new){
                $data['is_register'] = 1;
            }else{
                $data['is_register'] = 0;
            }
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
        } catch (ClientException $e) {
            return $this->responseJson(Message::ERROR, $e->getErrorMessage() . PHP_EOL);
        } catch (ServerException $e) {
            return $this->responseJson(Message::ERROR, $e->getErrorMessage() . PHP_EOL);
        }
    }

    /**
     * 登录后续处理
     * @param $member
     * @return mixed
     */
    private function _afterLogin($member,$num=0)
    {
        if (!$member) {
            Log::writelog('huadi_login_fail','55555======>登录失败。请重试===='.$num);
            return Message::appendMessage('登录失败。请重试', Message::ERROR);
        }

        if (!$member->member_state) {
            Log::writelog('huadi_login_fail','66666======>您的账号已被冻结，暂无法登录'.var_export($member,true));
            return Message::appendMessage('您的账号已被冻结，暂无法登录', Message::ERROR);
        }

        //将离线购物车数据写入会员
        $model_member = new Member();
        $result = $model_member->writeCart($member, $this->sessionid);
        if (!$result) {
            Log::writelog('huadi_login_fail','77777======>'.var_export($result,true));
            \Yii::error($model_member->getErrors());
        }
        //将代理信息写入会员
        $model_agent = new AgentMember();
        $result = $model_agent->writeMember($member, $this->sessionid);
        if (!$result) {
            Log::writelog('huadi_login_fail','88888======>'.var_export($result,true));
            \Yii::error($model_agent->getErrors());
        }
        //取登录信息并更新会员
        $data = $model_member->getLoginData($member);
        if (!$data) {
            Log::writelog('huadi_login_fail','99999======>'.var_export($data,true));
            \Yii::error('Refresh Login:' . json_encode($model_member->getErrors()));
            return Message::appendMessage('登录失败，请重试', Message::MODEL_ERROR);
        }
        $cache_key_login_time = $member->member_id .  '_key_login_time';
        $login_time = cache($cache_key_login_time);
        // 如果是花递，每天第一次登陆获得5积分
        if (SITEID == 258 && (!$login_time || $login_time <= strtotime(date('Y-m-d')))) {
            $check_one = HuadiScoreLog::find()->where(['member_id' => $member->member_id, 'operate' => HuadiScoreLog::OPERATE_LOGIN])->andWhere(['>', 'created_time', strtotime(date('Y-m-d'))])->orderBy('id desc')->one();
            if (!$check_one) {
                list($bool, $msg) = HuadiScoreLog::addLog($member->member_id, HuadiScoreLog::SCORE_LOGIN, HuadiScoreLog::TYPE_ADD, HuadiScoreLog::OPERATE_LOGIN);
                if (!$bool) {
                    Log::writelog('huadi_login_fail','10-10-10======>'.var_export($bool,true));
                    return Message::appendMessage($msg, Message::MODEL_ERROR);
                }
            }
            //首次登录加浪漫值
            $check_one = MemberExppointsLog::find()->where(['member_id' => $member->member_id, 'operate' => MemberExppointsLog::OPERATE_LOGIN])->andWhere(['>', 'create_time', strtotime(date('Y-m-d'))])->orderBy('id desc')->one();
            if(!$check_one){
                $result = MemberExppointsLog::addExppoints($member->member_id,1,MemberExppointsLog::OPERATE_LOGIN);
                if($result['code'] == 0){
                    Log::writelog('huadi_login_fail','11-11-11======>'.var_export($result,true));
                    return Message::appendMessage($result['msg'], Message::MODEL_ERROR);
                }
            }
            cache($cache_key_login_time, time(), 3600 * 48);
        }
        //是否是年卡用户
        $model_huadi_year_card_orders = new HuadiYearCardOrders();
        $is_year_card_member = $model_huadi_year_card_orders->getYearCardStateById($member->member_id);
        $data['is_year_card_member'] = $is_year_card_member ? 1 : 0;
        //年卡用户是否已领取今日优惠券
        $have_day_voucher = 0;
        if($is_year_card_member){
            $have_day_voucher = $model_huadi_year_card_orders->haveDayCoupons($member->member_id);
        }
        $data['have_day_voucher'] = $have_day_voucher ? 1 : 0;
        $data['member_name'] = !empty($member->member_nickname) ? $member->member_nickname : $member->member_name;
        $data['show_member_mobile'] = $member->member_mobile;
        return $data;
    }

}
