<?php

namespace frontend\controllers;

require_once __DIR__.'/../../vendor/jpush/autoload.php';
use JPush\Client;
use JPush;
use yii\web\Controller;


/**
 * 推送类
 */
class JPushController extends Controller
{
    const app_key       = '36113c371c22764519bac8a2';
    const master_secret = '9e8e03db9200ada59726c053';

    public function init()
    {
        $this->enableCsrfValidation = false;
        parent::init();
    }

    public function pushMessage($registrationId, $content)
    {
        $code = -1;
        // 实例化client.php中的client类.
        $client = new Client(self::app_key, self::master_secret);
        // 发送消息.
        $push_payload = $client->push()
            ->setPlatform(array('ios', 'android'))
            ->addRegistrationId($registrationId)
            ->setNotificationAlert($content);
        // 标签推送.
//        $this->client->push()
//            ->setPlatform(array('ios', 'android'))
//            ->addTag($tags)                          //标签
//            ->setNotificationAlert($alert)           //内容
//            ->send();
        // 别名推送.
//        $this->client->push()
//            ->setPlatform(array('ios', 'android'))
//            ->addAlias($alias)                      //别名
//            ->setNotificationAlert($alert)          //内容
//            ->send();
        try {
            // 执行推送
            $push_payload->send();
            $code = 1;
        } catch (JPush\Exceptions\APIConnectionException $e) {
            // 请求异常
        } catch (JPush\Exceptions\APIRequestException $e) {
            // 回复异常
        }
        return $code;
    }


}
