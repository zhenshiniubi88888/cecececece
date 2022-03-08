<?php

namespace frontend\controllers;

use common\components\Log;
use Payment\Payment;
use yii\web\HttpException;

/**
 * BaiduController
 */
class BaiduController extends BaseController
{

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 回调处理（支付|退款）
     * @return mixed
     */
    public function actionPay()
    {
        Log::writelog('baiduPay',\Yii::$app->request->post());
        //创建支付对象;
        $payment = Payment::create(Payment::PAY_CODE_BD_APPLET_AHJ);
        if ($payment == false || !method_exists($payment, 'payment')) {
            throw new HttpException(500);
        }
        $event = \Yii::$app->request->get('event');
        $params = \Yii::$app->request->post();
        switch ($event) {
            //支付回调
            case 'payNotify';
                $result = $payment->notify($params);
                break;
            //退款回调
            case 'refundNotify';
                $result = $payment->refund_notify($params);
                break;
            //退款审核
            case 'refundCheck';
                $result = $payment->refund_check($params);
                break;
            //待补充
            default;
                $result = array();
                break;
        }
        return $this->asJson($result);
    }

    /**
     * 回调处理（支付|退款）
     * @return mixed
     */
    public function actionHdPay()
    {
        Log::writelog('hdbaiduPay', var_export(\Yii::$app->request->get(), true));
        Log::writelog('hdbaiduPay', var_export(\Yii::$app->request->post(), true));
        //创建支付对象;
        $payment = Payment::create(Payment::PAY_CODE_BD_APPLET);
        if ($payment == false || !method_exists($payment, 'payment')) {
            throw new HttpException(500);
        }
        $event = \Yii::$app->request->get('event');
        $params = \Yii::$app->request->post();
        switch ($event) {
            //支付回调
            case 'payNotify';
                $result = $payment->notify($params);
                break;
            //退款回调
            case 'refundNotify';
                $result = $payment->refund_notify($params);
                break;
            //退款审核
            case 'refundCheck';
                $result = $payment->refund_check($params);
                break;
            //待补充
            default;
                $result = array();
                break;
        }
        return $this->asJson($result);
    }


}
