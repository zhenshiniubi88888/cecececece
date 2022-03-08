<?php
/**
 * 花递积分
 * User: gandalf
 * Date: 2019/11/7
 * Time: 21:37
 */

namespace frontend\controllers;


use common\components\Message;
use common\models\HuadiScoreLog;

class ScoreController extends BaseController
{
    /**
     * 积分明细，收入，支出
     * @return mixed
     */
    public function actionIndex()
    {
        $type = \Yii::$app->request->post('type', 1);  // 1：收入 2：支出
        $page = \Yii::$app->request->post('page', 1);
        if (!in_array($type, [1, 2])) {
            return $this->responseJson(Message::ERROR, '参数错误');
        }
        if ($page <= 0) {
            return $this->responseJson(Message::ERROR, '参数错误');
        }
        $condition = [
            'member_id' => $this->member_id,
            'type' => $type,
        ];
        $list = HuadiScoreLog::getList($condition, $page, 'type, score, created_time, describe, operate');
        if (empty($list)) {
            return $this->responseJson(Message::SUCCESS, '', $list);
        }
        $data = [];
        foreach ($list as $v) {
            $v['name'] = HuadiScoreLog::$operate_name[$v['operate']];
            if ($v['describe']) {
                $v['name'] = $v['name'] . '：' . $v['describe'];
            }
            $date = date('Y-m', $v['created_time']);
            $v['month'] = date('Ym', $v['created_time']);
            $data[$date][] = $v;
        }
        $data_new = [];
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                if (date('Y', strtotime($k)) == date('Y', time())) {
                    $month = date('m月', strtotime($k));
                } else {
                    $month = date('Y年m月', strtotime($k));
                }
                $data_new[] = [
                    'month' => $month,
                    'list' => $v,
                ];
            }
        }
        $res = [];
        $res['data'] = $data_new;
        return $this->responseJson(Message::SUCCESS, '', $res);
    }
}