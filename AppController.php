<?php
namespace frontend\controllers;

use common\components\Log;
use common\components\Message;
use common\helper\DateHelper;
use common\models\Adv;
use common\models\HuadiApp;
use common\models\MemberEquipmentComment;
use common\models\Setting;
use common\models\Souvenir;
use common\models\SouvenirNotify;

class AppController extends BaseController
{

    /**
     * app检测更新
     */
    public function actionUpdate()
    {
        $type = \Yii::$app->request->post("type",0);
        $type = $type == 1 ? 1 : 2;
        $new_version = HuadiApp::find()->where(['version_mobile_type'=>$type,'version_type_id' => 1, 'version_delete' => 2])->orderBy("version_code desc")->asArray()->one();
        $data = [];
        if($new_version){
            $data = [
                'versionname' => $new_version['version_name'],
                'versioncode' => $new_version['version_code'],
                'isforce' => $new_version['version_force'] == 1 ? '1' : '0',
                'apkurl' => 'https://cdn.ahj.cm/' . $new_version['version_file'],
                'des' => $new_version['version_log'],
            ];
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    public function actionIosVersionReviewing()
    {
        $version_code = \Yii::$app->request->post("version_code",0);
        $is_ios_version_reviewing = 0;
        if($version_code == '1.1.10'){
            $is_ios_version_reviewing = 1;  //苹果版本正在审核中
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $is_ios_version_reviewing);
    }
    /**
     * app的启动配置
     */
    public function actionStart()
    {
        //启动图（暂用广告图）
        $adv_model = new Adv();
        $data['start_img'] = $adv_model->getBanner(170);
        $huadi_icon_setting = unserialize(Setting::C('huadi_icon_setting',true));
        $data['daily_flower']  = isset($huadi_icon_setting['daily_flower_tag']) ? $huadi_icon_setting['daily_flower_tag'] : 0;
        $version_code = \Yii::$app->request->post("version_code",0);
        $is_ios_version_reviewing = 0;
        if($version_code == '1.1.10'){
            $is_ios_version_reviewing = 1;  //苹果版本正在审核中
        }
        $data['is_ios_version_reviewing'] = $is_ios_version_reviewing;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 进入app的广告或者活动弹窗
     */
    public function actionAdv()
    {
        //20200918, 纪念日弹窗
        if($this->member_id > 0){
            $already_notify = cache('souvenir_notify_'.$this->member_id);
            if(!$already_notify){
//            if(true){
                list($y,$m,$d) = explode('-',date('Y-m-d'));
                $lunar = (new DateHelper())->convertSolarToLunar($y,$m,$d);
                $date_lunar = $lunar[1] . $lunar[2];
                $date_solar = date('m-d');
                $souvenir_notify_model = SouvenirNotify::find()
                    ->alias('a')
                    ->leftJoin(Souvenir::tableName().' b','b.id = a.souvenir_id')
                    ->select(['a.*','b.*'])
                    ->where(['and',
                    ['a.status' => 1],
                    ['b.status' => 1],
                    ['b.member_id' => $this->member_id],
                    ['notify_date' => [0,1]],
                    ['or',['notify_time_moon' => $date_lunar],['notify_time' => $date_solar]]
                ]);
                $souvenir_notify_models = $souvenir_notify_model->asArray()->all();
                foreach ($souvenir_notify_models as $souvenir_notify_model){
                    if($souvenir_notify_model){
                        if(
                            ($souvenir_notify_model['is_solar_calendar'] == 1 && $date_solar == $souvenir_notify_model['notify_time']) ||
                            ($souvenir_notify_model['is_solar_calendar'] != 1 && $date_lunar == $souvenir_notify_model['notify_time_moon'])
                        ){
                            $years = round((time() - $souvenir_notify_model['date'])/86400/365);
                            $title = $souvenir_notify_model['type'] == 1 ? $souvenir_notify_model['name'] : $souvenir_notify_model['type_name'];
                            $data['souvenir_notify'] = [
                                'type' => $souvenir_notify_model['title'],
                                'title' => $title,
                                'count' => $years,
                                'souvenir_id' => $souvenir_notify_model['id']
                            ];
                        }
                    }
                }
            }
        }
        cache('souvenir_notify_'.$this->member_id,true,strtotime(date('Y-m-d 23:59:59') - time()));
        $adv_model = new Adv();
        $data['adv'] = $adv_model->getBanner(172);
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 记录设备型号信息
     * @return mixed
     */
    public function actionMobileInfo()
    {
        $imei = \Yii::$app->request->post('imei','');
        $type = \Yii::$app->request->post('type',0);
        $mobile_type = \Yii::$app->request->post('mobile_type','');
        $version = \Yii::$app->request->post('version','');
        $app_type = \Yii::$app->request->post('app_type',0);
        if (!$imei) {
            return $this->responseJson(Message::ERROR, Message::EMPTY_MSG, []);
        }
        $model = new MemberEquipmentComment();
        $result= $model->getCount(['imei'=>htmlentities($imei)]);
        if (!$result) {
            $model->setAttribute('imei',htmlentities($imei));
            $model->setAttribute('type',$type);
            $model->setAttribute('mobile_type',htmlentities($mobile_type));
            $model->setAttribute('version',$version);
            $model->setAttribute('app_type',$app_type);
            $model->setAttribute('create_at',time());
            if (!$model->save()) {
                return $this->responseJson(Message::ERROR, Message::MODEL_ERROR_MSG, []);
            }
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, []);
        }

    }
}