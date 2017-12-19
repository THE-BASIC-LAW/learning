<?php

namespace app\model;

use think\Model;

class ShopStation extends Model
{

    // status 0 不启用 1 启用
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    public function getFeeSettings($id, $type = 'array'){
        $result = $this->get($id);
        if (empty($result['fee_settings'])) {
            $fee_settings = model('CommonSetting')->get('fee_settings');
            if ($type == 'array') {
                return json_decode($fee_settings['svalue'], 1);
            } elseif ($type == 'json') {
                return $fee_settings['svalue'];
            }
        }
        $ret = model('FeeStrategy')->get($result['fee_settings']);
        if ($type == 'array') {
            return json_decode($ret['fee'], 1);
        } elseif ($type == 'json') {
            return $ret['fee'];
        }
    }

    public function getFeeSettingsByStationId($stationId, $type = 'array'){
        $result = $this->where('station_id', $stationId)->find();
        if (empty($result) || empty($result['fee_settings'])) {
            $fee_settings = model('CommonSetting')->get('fee_settings');
            if ($type == 'array') {
                return json_decode($fee_settings['svalue'], 1);
            } elseif ($type == 'json') {
                return $fee_settings['svalue'];
            }
        }
        $ret = model('FeeStrategy')->get($result['fee_settings']);
        if ($type == 'array') {
            return json_decode($ret['fee'], 1);
        } elseif ($type == 'json') {
            return $ret['fee'];
        }
    }

    /**
     * @param array $shopStationIds 商铺站点ID集合
     * @param bool  $isMerge 是否合并同商铺下站点信息
     * @param bool  $province 是否显示省市
     * @return array
     */
    public function getAllInfo(array $shopStationIds, $isMerge = false, $province = false){
        $ret = [];
        foreach ($shopStationIds as $id) {
            if ($info = $this->getInfo($id, $province)) {
                $ret[$id] = $info;
            }
        }

        // shop_type logo 后续

        if (!$isMerge) {
            return $ret;
        }

        // 合并shopid相同的商铺站点雨伞数量
        $shopIds       = array_column($ret, 'shopid');
        $uniqueShopIds = array_unique($shopIds);
        $repeatShopIds = array_diff_assoc($shopIds, $uniqueShopIds);
        // 过滤shopid为0
        $repeatShopIds = array_filter($repeatShopIds);


        $singleInfos = []; // 使用id为key
        $repeatInfos = []; // 使用shopid为key，便于雨伞求和
        foreach ($ret as &$v) {
            if (in_array($v['shopid'], $repeatShopIds)) {
                if (key_exists($v['shopid'], $repeatInfos)) {
                    $repeatInfos[$v['shopid']]['usable'] += $v['usable'];
                    $repeatInfos[$v['shopid']]['empty']  += $v['empty'];
                } else {
                    $repeatInfos[$v['shopid']] = $v;
                }
                $repeatInfos[$v['shopid']]['more'] = 1; // 标记为多商铺站点网点
            } else {
                $singleInfos[$v['id']] = $v;
            }
        }

        $newRepeatInfos = []; //使用id为key
        foreach ($repeatInfos as $vv) {
            $newRepeatInfos[$vv['id']] = $vv;
        }

        return array_merge($singleInfos, $newRepeatInfos);
    }


    /**
     * 显示商铺站点信息（整合station，shop信息）
     * @param  int $shopStationId
     * @param bool $province 是否显示省市
     * @return array|bool
     */
    public function getInfo($shopStationId, $province = false){
        $shopStationInfo = $this
            ->where('id', $shopStationId)
            ->column('`id`, `shopid`, `station_id`, `lbsid`, `title`, `address`, `desc`, `longitude`, `latitude`');
        $shopStationInfo = $shopStationInfo[$shopStationId];
        if (empty($shopStationInfo)) return false;

        $stationInfo = Station::get($shopStationInfo['station_id']);
        if (empty($stationInfo)) return false;

        // 整合station信息到shopStation里面
        $shopStationInfo['empty'] = $stationInfo['empty'];
        $shopStationInfo['usable'] = $stationInfo['usable'];

        // 整合shop信息到shopStation里面
        if (empty($shopStationInfo['shopid'])) {
            $shopStationInfo['shop_name'] = '';
        } else {
            $shopInfo =  Shop::get($shopStationInfo['shopid']);
            $shopStationInfo['title'] = $shopInfo['name'];
            // 显示到  区 + 地址
            if(!$province){
                $shopStationInfo['address'] = $shopInfo['area'] . $shopInfo['locate'];
            }
            $shopStationInfo['shop_type'] = $shopInfo['type'];
            $shopStationInfo['shoplogo'] = $shopInfo['logo'] ? json_decode($shopInfo['logo'], true)[0] : '#';
            $shopStationInfo['shopcarousel'] = json_decode($shopInfo['carousel'], true);
        }
        return $shopStationInfo;
    }

    public function filterByKeyWords($keyWords){
        $ret = $this->cache(300)->where('status', self::STATUS_ENABLE)
             ->where('address|title', 'like', '%'.$keyWords.'%')
            ->column('id,shopid,longitude,latitude');
        return $ret;
    }

    public function filter($shopStationIds, $mark = 0, $page_size){
        $shops = [];
        $count = 0;
        if($mark){
            $shopStationIds = array_slice($shopStationIds, $mark);
        }
        foreach($shopStationIds as $id){
            if ($info =  $this->getInfo($id, true)) {
                if (key_exists($info['shopid'], $shops)) {
                    $shops[$info['shopid']]['usable'] += $info['usable'];
                    $shops[$info['shopid']]['empty'] += $info['empty'];
                    $shops[$info['shopid']]['more'] = 1;
                }else{
                    $count ++;
                    if($count == ($page_size + 1)){
                        $shops = array_values($shops);
                        return ['shops' => $shops, 'mark' => $mark];
                    }
                    $shops[$info['shopid']] = $info;
                    $shops[$info['shopid']]['lng'] = $info['longitude'];
                    $shops[$info['shopid']]['lat'] = $info['latitude'];
                }
            }
            $mark ++;
        }

        return ['shops' => $shops, 'mark' => $mark];
    }

    public function getErrorMans($station_id = 0) {
        if ( !empty($station_id) && is_int($station_id) ) {
            return self::where('station_id', $station_id)->value('error_man');
        } else {
            return self::where('error_man', '<>' , '')->column('error_man');
        }
    }

    public function bindShop($shop_station_id, $shop_id){
        return $this->get($shop_station_id)->save(['shopid' => $shop_id]);
    }

    public function unBindShop($shop_station_id){
        return $this->get($shop_station_id)->save(['shopid' => 0]);
    }

    public function searchShopStation($conditions, $page_size, $access_cities = null, $access_shops = null){
        $city       = '';
        $area       = '';
        $where      = [];
        $orWhere    = [];
        $keyword    = '';
        $province   = '';
        $station_id = '';
        extract($conditions);

        if (isset($status) && $status != -1) {
            $where['status'] = $status;
        }

        if ($province || $city || $area) {
            $where['address'] = ['like',  $province . $city . $area .'%'];
        }

        $keyword && $where['title'] = ['like', '%' . $keyword . '%'];

        $station_id && $where['station_id'] = $station_id;

        // 授权城市,授权商铺, 用 or 连接
        if ($access_cities !== null && $access_shops !== null) {
            // 城市和商铺都为空时
            if (empty($access_cities) && empty($access_shops)) return [];

            // 城市为空, 商铺不为空
            empty($access_cities) && $access_shops && $orWhere[] = ['shopid' => $access_shops];
            // 商铺为空, 城市不为空
            // 城市传进来的是数组, 需要处理下
            if ($access_cities && empty($access_shops)) {
                foreach ($access_cities as $v) {
                    $orWhere[] = ['address' => ['like', '%' . $v . '%']];
                }
            }
            // 二者都不为空时
            if ($access_cities && $access_shops) {
                //城市传进来的是数组, 需要处理下
                $orWhere[] = ['shopid' => $access_shops];
                foreach ($access_cities as $v) {
                    $orWhere[] = ['address' => ['like', '%' . $v . '%']];
                }
            }
        }

        return $this->where(function ($query) use ($where) {
            $query->where($where);
        })->where(function ($query) use ($orWhere){
            $query->whereOr($orWhere);
        })->order('id desc')->paginate($page_size, false, ['query'=>$conditions]);
    }
}
