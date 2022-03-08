<?php

namespace frontend\controllers;

use common\components\IntelligentAnalysisAddress;
use common\components\Message;
use common\models\Address;
use common\models\Area;
use common\models\OrderCommon;
use common\models\Orders;
use yii\db\Exception;
use yii\web\HttpException;
use Yii;

/**
 * AddressController
 */
class AddressController extends BaseController
{
    public function init()
    {
        parent::init();
    }

    public function actionIndex()
    {
        throw new HttpException(405);
    }

    /**
     * 地址列表
     * @return mixed
     */
    public function actionList()
    {
        $post = Yii::$app->request->post();
        $modifyAddressInfo = empty($post['modify_address_info']) ? '' : $post['modify_address_info'];
        $this->validLogin();
        $data = [];
        //查询地址列表
        $model_address = new Address();
        $data['address_list'] = $model_address->getAddressByUid($this->member_id);
        $data['tag_list'] = array_values($model_address->tag_list);
        if ($modifyAddressInfo != '') {
            //获取原地址经纬度
            $area = explode(',', $modifyAddressInfo);
            $modifyAddress = getAxis($area[0], $area[1]);
            foreach ($data['address_list'] as &$v) {
                //根据地址调取百度API反查经纬度
                $axis = getAxis($v['area_info'], $v['address']);

                //计算两地距离（单位：km）
                $distance = getDistance(
                    $modifyAddress['x_axis'],
                    $modifyAddress['y_axis'],
                    $axis['x_axis'],
                    $axis['y_axis']
                );

                //大于10km不可选
                if ($distance > 10) {
                    $v['choice'] = 0;
                } else {
                    $v['choice'] = 1;
                }
                $v['distance'] = $distance;
            }
            unset($v);
        }

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, $data);
    }

    /**
     * 历史订单中的收货地址列表
     */
    public function actionHistoryList(){
        $this->validLogin();
        $param = \Yii::$app->request->post();
        $page = (int)$param['page'] ? (int)$param['page'] : 1;
        $page_size=3;
        $field="address.address,address.address_id,address.area_id,address.area_info,address.city_id,address.show_tel,address.shou_name,address.member_id,address.provice_id,address.tag";
//        $field="address.*";
        $list_obj=Orders::find()
            ->alias("order")
            ->join("join","hua123_order_common o_common","o_common.order_id=order.order_id")
            ->join("join","hua123_address address","address.address_id=o_common.daddress_id")
            ->select($field)
            ->where(["order.buyer_id"=>$this->member_id])
            ->groupBy("address.address_id");

        $list=$list_obj->orderBy("order.order_id desc")
            ->offset(($page-1) * $page_size)
            ->limit($page_size)
            ->asArray()
            ->all();
        foreach ($list as &$item){
            $item['consignee_name']=$item['shou_name'];
            $item['consignee_mobile']=$item['show_tel'];
            $item['province_id']=$item['provice_id'];
            unset($item['shou_name'],$item['show_tel'],$item['provice_id']);
        }

        $data=[
//            "count"=>(int)$list_obj->count(),
            "curpage"=>$page,
            "pagesize"=>$page_size,
            "list"=>$list
        ];
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }
    /**
     * 地址详情
     * @return mixed
     */
    public function actionView()
    {
        $this->validLogin();
        $param = \Yii::$app->request->post();
        $address_id = (int)$param['address_id'];
        if (!$address_id) {
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $address = Address::findOne(['address_id' => $address_id, 'member_id' => $this->member_id, 'is_delete' => 0]);
        if (empty($address)) {
            return $this->responseJson(Message::EMPTY_CODE, Message::EMPTY_MSG);
        }
        $output = array();
        $output['address_id'] = $address->address_id;
        $output['province_id'] = $address->provice_id;
        $output['city_id'] = $address->city_id;
        $output['area_id'] = $address->area_id;
        $output['area_info'] = $address->area_info;
        $output['address'] = $address->address;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['address' => $output]);
    }

    /**
     * 保存地址（新增/编辑）
     * @return mixed
     */
    public function actionSave()
    {
        $this->validLogin();
        $param = \Yii::$app->request->post();
        $address_id = (int)$param['address_id'];
        if ($address_id) {
            $address = Address::findOne(['address_id' => $address_id, 'member_id' => $this->member_id, 'is_delete' => 0]);
            if (!$address) {
                return $this->responseJson(Message::ERROR, '地址不存在或已删除');
            }
            $address->setAttribute('update_time', TIMESTAMP);
        } else {
            $address = new Address();
            $address->setAttribute('add_time', TIMESTAMP);
        }
        $add_field = ['province_id', 'city_id', 'area_id', 'area_info', 'address', 'consignee_name', 'consignee_mobile', 'is_default', 'lng', 'lat','tag'];
        $address_data = [];
        $address_data['member_id'] = $this->member_id;
        foreach ($add_field as $key) {
            $val = isset($param[$key]) ? trim($param[$key]) : '';
            if (in_array($key, ['province_id', 'city_id', 'area_id'])) {
                $val = (int)$val;
                if ($val == 0 && $key != 'area_id') {
                    return $this->responseJson(Message::ERROR, '请选择所在地区');
                }
            }
            if ($key == 'address') {
                if (mb_strlen($val, 'UTF-8') < 4) {
                    return $this->responseJson(Message::ERROR, '详细地址至少4个字符');
                }
                if (mb_strlen($val, 'UTF-8') > 64) {
                    return $this->responseJson(Message::ERROR, '详细地址不能大于64个字符');
                }
            } elseif ($key == 'consignee_name') {
                if (!isUsername($val)) {
                    return $this->responseJson(Message::ERROR, '收货人姓名1-16个字符，不能含有特殊字符');
                }
            } elseif ($key == 'consignee_mobile') {
                if (!isMobile($val)) {
                    return $this->responseJson(Message::ERROR, '手机号码格式不正确');
                }
            } elseif ($key == 'is_default') {
                $val = $val == 1 ? 1 : 0;
            } elseif ($key == 'area_info') {
                $val = Area::getAreaText(['area_id' => [$param['province_id'], $param['city_id'], $param['area_id']]], ' ');
            } elseif ($key == 'lng') {
                $val = (float)$val;
                if($val > 180 || $val < -180){
                    return $this->responseJson(Message::ERROR, '经度格式不对');
                }
            } elseif ($key == 'lat') {
                $val = (float)$val;
                if($val > 90 || $val < -90){
                    return $this->responseJson(Message::ERROR, '纬度格式不对');
                }
            } elseif($key == 'tag'){
                $val = intval($val);
            }
            $address_data[$key] = $val;

        }

        $tmp_data = array();
        $tmp_data['true_name'] = "";
        $tmp_data['todate'] = "";
        $tmp_data['toshimite'] = "";
        $tmp_data['toshiduan'] = 0;
        $tmp_data['isup'] = 0;
        $tmp_data['deliver_type'] = 0;
        $tmp_data['deliver_store_name'] = "";
        $tmp_data['deliver_fee'] = 0;
        $tmp_data['isni'] = 0;

        $tmp_data['member_id'] = $address_data['member_id'];
        $tmp_data['provice_id'] = $address_data['province_id'];
        $tmp_data['city_id'] = $address_data['city_id'];
        $tmp_data['area_id'] = $address_data['area_id'];
        $tmp_data['area_info'] = $address_data['area_info'];
        $tmp_data['address'] = $address_data['address'];
        $tmp_data['mob_phone'] = $address_data['consignee_mobile'];
        $tmp_data['is_default'] = $address_data['is_default'];
        $tmp_data['shou_name'] = $address_data['consignee_name'];
        $tmp_data['show_tel'] = $address_data['consignee_mobile'];
        $tmp_data['tag'] = $address_data['tag'];
        /*$tmp_data['lng'] = $address_data['lng'];
        $tmp_data['lat'] = $address_data['lat'];*/
        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            //更新默认地址
            if ($tmp_data['is_default']) {
                $default_address = Address::findOne(['member_id' => $this->member_id, 'is_default' => 1, 'is_delete' => 0]);
                if ($default_address && $default_address->address_id != $address_id) {
                    $default_address->is_default = 0;
                    $default_address->update_time = TIMESTAMP;
                    $result = $default_address->save(false);
                    if (!$result) {
                        \Yii::error($default_address->getErrors());
                        throw new Exception('200');
                    }
                }
            }
            $address->setAttributes($tmp_data);
            $result = $address->save(false);
            if (!$result) {
                \Yii::error($address->getErrors());
                throw new Exception('100');
            }
            $address_id = $address->getAttribute('address_id');
            $transaction->commit();
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG, ['address_id' => $address_id]);
        } catch (Exception $e) {
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, $e->getMessage());
        }
    }

    /**
     * 重新拼装字母下面的城市，以及重新生成带筛选数据
     * @param $letter
     * @param $city_arr
     * @return array
     */
    private function letterSort($letter, $city_arr)
    {
        $letter_city = [];
        $new_city_arr = [];
        foreach ($city_arr as $city){
            if($city['area_tag'] == $letter){
                unset($city['area_tag']);
                $letter_city[] = $city;
            }else{
                $new_city_arr[] = $city;
            }
        }

        return ['letter_city' => $letter_city, 'city_arr' => $new_city_arr];
    }

    /**
     * 根据传入的地址id，获取所有上级地址信息
     */
    public function actionGetParents()
    {
        $area_id = \Yii::$app->request->post('area_id',0);
        $now_area_info = Area::find()->where(['area_id' => $area_id])->select("area_id, area_name, name, area_arrparentid")->asArray()->one();
        if(empty($now_area_info)){
            return $this->responseJson(Message::ERROR, Message::ERROR_MSG);
        }
        $parents = [];
        $parent_ids = isset($now_area_info['area_arrparentid']) ? explode(',',$now_area_info['area_arrparentid']) : [];
        if(!empty($parent_ids)){
            $parents = Area::find()->where(['in','area_id',$parent_ids])->select("area_id, area_name, name")->orderBy("area_id asc")->asArray()->all();
        }
        unset($now_area_info['area_arrparentid']);
        $parents[] = $now_area_info;
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$parents);
    }

    /**
     * 设置为默认地址
     * @return mixed
     */
    public function actionDefault()
    {
        $this->validLogin();
        $param = \Yii::$app->request->post();
        $address_id = (int)$param['address_id'];
        $address = Address::findOne(['address_id' => $address_id, 'member_id' => $this->member_id, 'is_delete' => 0]);
        if (!$address) {
            return $this->responseJson(Message::ERROR, '地址不存在或已删除');
        }
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $default_address = Address::findOne(['member_id' => $this->member_id, 'is_default' => 1, 'is_delete' => 0]);
            if ($default_address && $default_address->address_id != $address_id) {
                $default_address->is_default = 0;
                $default_address->update_time = TIMESTAMP;
                $result = $default_address->save(false);
                if (!$result) {
                    \Yii::error($default_address->getErrors());
                    throw new Exception('200');
                }
            }
            $address->is_default = 1;
            $address->update_time = TIMESTAMP;
            $result = $address->save(false);
            if (!$result) {
                \Yii::error($address->getErrors());
                throw new Exception('100');
            }
            $transaction->commit();
            return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
        } catch (\yii\base\Exception $e) {
            $transaction->rollBack();
            return $this->responseJson(Message::ERROR, '设置失败，请重试');
        }
    }

    /**
     * 删除地址
     * @return mixed
     */
    public function actionDel()
    {
        $this->validLogin();
        $param = \Yii::$app->request->post();
        $address_id = (int)$param['address_id'];
        $address = Address::findOne(['address_id' => $address_id, 'member_id' => $this->member_id, 'is_delete' => 0]);
        if (!$address) {
            return $this->responseJson(Message::ERROR, '地址不存在或已删除');
        }
        $address->is_delete = 1;
        $address->update_time = TIMESTAMP;
        $result = $address->save(false);
        if (!$result) {
            return $this->responseJson(Message::ERROR, '删除失败，请重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 快速编辑地址
     * @return mixed
     */
    public function actionQuickEdit()
    {
        $this->validLogin();
        $param = \Yii::$app->request->post();
        $address_id = (int)$param['address_id'];
        $address = Address::findOne(['address_id' => $address_id, 'member_id' => $this->member_id, 'is_delete' => 0]);
        if (!$address) {
            return $this->responseJson(Message::ERROR, '地址不存在或已删除');
        }

        $addressComponents = isset($param['addressComponents']) && is_array($param['addressComponents']) ? $param['addressComponents'] : array();
        if(empty($addressComponents) && $param['accurateAddress'] == ''){
            return $this->responseJson(Message::ERROR, '未获取到定位，请重试');
        }

        if($addressComponents['street'] == '' && $param['accurateAddress'] == ''){
            return $this->responseJson(Message::ERROR, '请填写详细地址');
        }

        //将原有地址的括号内容置空
        $address->address = str_replace(array('（','）'),array('(',')'),$address->address);
        $address->address = preg_replace('/\((.*?)\)/s','',$address->address);
        //只编辑详细地址
        if(!empty($addressComponents)){
            //验证是否出省
            $province = Area::find()->where(['area_name'=>$addressComponents['province'],'area_parent_id'=>0])->one();
            if(empty($province)){
                return $this->responseJson(Message::ERROR, '您选择的地址暂不支持自动校验');
            }
            if($addressComponents['province'] == $addressComponents['city']){
                //百度地图直辖市格式处理
                $addressComponents['city'] = $addressComponents['district'];
                $addressComponents['district'] = '';
            }
            $city = Area::find()->where(['area_name'=>$addressComponents['city'],'area_parent_id'=>$province->area_id])->one();
            if(empty($city)){
                return $this->responseJson(Message::ERROR, '您选择的地址暂不支持自动校验');
            }
            //必须有两级地址
            $area = null;
            if($addressComponents['district']){
                $area = Area::find()->where(['area_name'=>$addressComponents['district'],'area_parent_id'=>$city->area_id])->one();
            }

            //完成修改
            $address->provice_id = $province->area_id;
            $address->city_id = $city->area_id;
            $address->area_id = $area ? $area->area_id : 0;
            $address->area_info = implode(' ',array_filter([$province->area_name,$city->area_name,$area ? $area->area_name : '']));
            $address->address = sprintf("%s%s%s",$addressComponents['street'],$addressComponents['streetNumber'],$param['accurateAddress'] ? "(".$param['accurateAddress'].")" : '');
        }else{
            $address->address .= '('.$param['accurateAddress'].')';
        }

        if(mb_strlen($address->address,'UTF-8') > 200){
            return $this->responseJson(Message::ERROR, '详细地址200字以内');
        }
        $res = $address->save(false);
        if(!$res){
            return $this->responseJson(Message::ERROR, '保存地址失败，请稍后重试');
        }
        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG);
    }

    /**
     * 第三级地址排序
     */
    public function actionAddressSort(){
        $cache_name = "address_sort_letter_city1";
        $letter_city = cache($cache_name);
        if(!$letter_city || IS_TEST) {
            $city = Area::getAreaList([], 'area_id,area_name,name,area_tag', 'area_tag asc,area_id asc');
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y', 'Z'];
            $letter_city = [];//根据字母排序的城市
            foreach ($letters as $letter) {
                $now_city_data = $this->letterSort($letter, $city);
                $letter_city[] = [
                    'letter' => $letter,
                    'city' => $now_city_data['letter_city']
                ];
                $city = $now_city_data['city_arr'];
            }
            cache($cache_name,serialize($letter_city),3600*48);
        }else{
            $letter_city = unserialize($letter_city);
        }
        //热门城市
        $where = ['>','area_parent_id',0];
        $where=['and', $where, ['area_hot'=>1]];
        $hot_city = Area::find()->where($where)->select('area_id,area_name,name')->limit(12)->asArray()->all();
        //常用城市
//        $history_city = Orders::find()
//            ->alias("order")
//            ->join("join","hua123_order_common common","common.order_id = `order`.order_id")
//            ->join("join","hua123_area area","area.area_id = common.reciver_city_id")
//            ->where(['order.buyer_id'=>$this->member_id])
//            ->select("area.area_id,area.area_name,area.name")
//            ->groupBy("area.area_name")
//            ->limit(8)
//            ->asArray()
//            ->all();
        $data['letter_city'] = $letter_city;
        $data['hot_city'] = $hot_city;
//        $data['history_city'] = $history_city;

        return $this->responseJson(Message::SUCCESS, Message::SUCCESS_MSG,$data);
    }

    /**
     * 智能解析地址
     * @return mixed
     */
    public function actionIntelligentAnalysis()
    {
        $content = Yii::$app->request->post('content');
        if (empty($content)) {
            return $this->responseJson(Message::ERROR, '智能解析内容不能为空');
        }
        $result = IntelligentAnalysisAddress::smart($content);
        return $this->responseJson(Message::SUCCESS, '', $result);
    }
}
