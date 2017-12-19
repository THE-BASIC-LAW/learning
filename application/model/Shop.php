<?php

namespace app\model;

use think\Model;

class Shop extends Model
{
	const STATUS_APPLY = 0; // 申请中
    const STATUS_PASS = 1;

    public static $STATUS = [
        self::STATUS_APPLY => '申请中',
        self::STATUS_PASS  => '通过',
    ];

    // 相关模型
    protected $shop_type;

    public function __construct($data = []){
        parent::__construct($data);
        $this->shop_type = model('ShopType');
    }

    public function getShops($page, $page_size){
        $shops = $this->where('id', '>', '0')->page($page, $page_size)->select();
        // 获取商铺类型
        $shop_types = $this->shop_type->select();
        foreach ($shop_types as $type) {
            $shopTypes[$type['id']] = $type['type'];
        }

        $shops = array_map(function($shop) use ($shopTypes) {
            $shop['logo'] = json_decode($shop['logo']);
            if(!$shop['logo']){
                $shop['default'] = true;
            } else {
                $shop['default'] = false;
            }
            $shop['carousel'] = json_decode($shop['carousel']);
            $shop['shoptype'] = $shopTypes[$shop['type']] ? : '无';
            return $shop;
        }, $shops);
        $count = $this->count();
        return ['shops' => $shops, 'count' => $count];
    }

    public function getShopIdsByCities($cities){
        return self::where('city', 'in', $cities)->column('id') ? : [];
    }

    /**
     * 不允许通过station_id来更新收费策略等信息，商铺中更新收费策略等信息应该是更新此商铺下所有shop_station的信息
     */

    public function getStationIdsByShopIds($shop_ids){
        return model('ShopStation')->where('shopid', 'in', $shop_ids)->column('station_id');
    }

    public function getShopInfoByStationId($station_id){
        $shop_station = model('ShopStation')->where('station_id', $station_id)->find();
        if (!$shop_station) return false;
        $shop = $this->get($shop_station['shopid']);
        return $shop;
    }

    public function getStationSettingsNameByStationId($station_id){
        $station = model('Station')->get($station_id);
        if (!$station) return false;
        $feeSettings = model('StationSettings')->get($station['station_setting_id']);
        return $feeSettings['name'];
    }

    public function searchShop($conditions, $page_size, $access_cities = null, $access_shops = null){
        extract($conditions);
        $where    = [];
        $orWhere  = [];

        if (isset($province) && !empty($province)) {
            $where['province'] = $province;
        }
        if (isset($city) && !empty($city)) {
            $where['city'] = $city;
        }
        if (isset($area) && !empty($area)) {
            $where['area'] = $area;
        }
        if (isset($keyword) && !empty($keyword)) {
            $where['name'] = ['like',  '%' . $keyword . '%'];
        }

        // 授权的商铺id
        $access_shops !== null && $orWhere['id'] = ['in', $access_shops];
        // 授权的区域下的所有商铺
        $access_cities !== null && $orWhere['city'] = ['in', $access_cities];

        return $this->where(function ($query) use ($where) {
            $query->where($where);
        })->where(function ($query) use ($orWhere){
            $query->whereOr($orWhere);
        })->order('id desc')->paginate($page_size, false, ['query'=>$conditions]);
    }

}
