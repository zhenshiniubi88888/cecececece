<?php

/**
 * 新人领券
 */

namespace frontend\controllers;


use common\components\Log;
use common\components\Message;
use frontend\service\VoucherService;

class NewVoucherController extends BaseController
{

    /**
     * 领取新人100元代金券
     * @return mixed
     */
    public function actionGetVoucher()
    {
        if ($this->isLogin()) {
            $service = new VoucherService();
            $result  = $service->getVoucher($this->member_id);
            if (!$result) {
                $return['code']    = $service->getCode();
                $return['message'] = $service->getError();
                Log::writelog('voucher_get_debug',$service->getError());
                return $this->responseJson(Message::SUCCESS,Message::SUCCESS_MSG, $return);
            }
            $return['code']    = VoucherService::TYPE_NUM_SIX;
            $return['message'] = "领取成功";
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$return);
        }
        return $this->responseJson(Message::ERROR, Message::UN_LOGIN_MSG, []);
    }

}