<?php

namespace app\logic;

use think\Db;
use think\Loader;
use think\Model;
use think\Session;

class Shop extends Model
{
	private $shop;
	private $adminShop;

	public function __construct()
	{
		$this->shop = Loader::model('Shop');
		$this->adminShop = model('AdminShop');
	}

	public function searchShops($shopName, $province, $city, $area)
	{
		if (!empty($shopName)) $where['name'] = ['like', '%'.$shopName.'%'];
		if (!empty($province)) $where['province'] = $province;
		if (!empty($city)) $where['city'] = $city;
		if (!empty($area)) $where['area'] = $area;
		// 去除申请中或者已经申请的商铺
		$appliedShopIds = $this->adminShop->column('shop_id');
		if ($appliedShopIds) {
			$shops = $this->shop->where($where)->where('id', 'not in', $appliedShopIds)->paginate();
		}else {
			$shops = $this->shop->where($where)->paginate();
		}

		return $shops;
	}
}
