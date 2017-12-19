<?php

namespace app\lib;

use think\Db;
use think\Session;

class Auth
{
	const COMPANY_YCB = 1;
    const COMPANY_KEK = 2;
    const COMPANY_HSX = 3;

	public static $companyArray = [
        self::COMPANY_YCB => '深圳市街借伞科技有限公司',
        self::COMPANY_KEK => '深圳市卡儿酷软件技术有限公司',
        self::COMPANY_HSX => '深圳市华思旭科技有限公司',
    ];

	public  $adminId;
    public  $globalSearch = 0; //默认不支持全局搜索
    private $admin;
    private $currentAdmin;
    private $adminRole;
    private $currentAdminRole;
    private $adminCity;
    private $currentAdminCity;
    private $adminShop;
    private $currentAdminShop;

	public function __construct()
    {
		$aid = Session::get('aid');

        $this->adminId = $aid;

        $this->admin       = Db::name('admin');
        $this->adminRole   = Db::name('admin_role');
        $this->adminCity   = Db::name('admin_city');
        $this->adminShop   = model('AdminShop');
        $this->currentAdmin     = $this->admin->where(['id' => $aid])->find();
        $this->currentAdminRole = $this->adminRole->where(['id' => $this->currentAdmin['role_id']])->find();
        $this->currentAdminCity = $this->adminCity->where(['admin_id' => $aid])->find();
        $this->currentAdminShop = $this->adminShop->where(['admin_id' => $aid])->select();

        $this->globalSearch = $this->currentAdminRole['global_search'];
    }

	/**
	*	获得可注册的角色
	*/
	public function allCanRegisterRoles()
    {
        return $this->adminRole->where('id', 'NOT IN', SUPER_ADMINISTRATOR_ROLE_ID)->select();
    }

	public function getCompany()
	{
		return self::$companyArray;
	}

	/**
	*	获得当前角色角色名成
	*/
	public function getCurrentRoleName()
    {
         return $this->currentAdminRole['role'];
    }

	/**
	*	获得当前角色所有权限
	*/
	public function getAuth()
    {
        return json_decode($this->currentAdminRole['access'], 1);
    }

	/**
	*	获得当前角色菜单栏入口
	*/
	public function getNavAccessTree($navTree)
    {
        $access = $this->getAuth();
        $tmp = [];
        foreach($navTree as $k => $v) {
            if(in_array($k, $access)) {
                $tmp[$k]['text'] = $v['text'];
            }
            foreach($v['sub_nav'] as $kk => $vv) {
                if(in_array($k.'/'.$kk , $access)) {
                    $tmp[$k]['sub_nav'][$kk]['opt'] = $vv['opt'];
                }
                if (isset($vv['do']) && $vv['do']) {
                    foreach($vv['do'] as $kkk => $vvv) {
                        if(in_array($k.'/'.$kk.'/'.$kkk, $access)) {
                            $tmp[$k]['sub_nav'][$kk]['do'][$kkk] = $vvv;
                        }

                    }
                }
            }
        }
        return $tmp;
    }

	/**
	*	获得所有角色
	*	@return arr 角色列表
	*/
	public function getAllRoles($needPaginate = false)
    {
		if ($needPaginate) {
			return $this->adminRole->paginate();
		}

        return $this->adminRole->select();
    }

	/**
	*	获得角色信息
	*	@return arr 角色信息
	*/
	public function getRoleInfo($role_id)
    {
        return $this->adminRole->where(['id' => $role_id])->find();
    }

	public function isAuthorizedAction($access_array, $access_tree)
	{
		if (!is_array($access_array)) {
			return false;
		}
		// act/opt/do 有子级必有父级
		foreach($access_array as $k => $v) {
			$cnt = substr_count($v, '/');
			if ($cnt == 0) {
				continue;
			} elseif ($cnt == 1) {
				if (!in_array(substr($v, 0, strpos($v, '/')), $access_array)) return false;
			} elseif ($cnt == 2) {
				if (!in_array(substr($v, 0, strrpos($v, '/')), $access_array)) return false;
			} else {
				return false;
			}
		}

		// act/opt/do 必须在tree里面
		foreach($access_array as $v) {
			$cnt = substr_count($v, '/');
			if ($cnt == 0) {
				if (!key_exists($v, $access_tree)) return false;
			} elseif ($cnt == 1) {
				list($act, $opt) = explode('/', $v);
				if (empty($access_tree[$act]['sub_nav'][$opt])) return false;
			} elseif ($cnt == 2) {
				list($act, $opt, $do) = explode('/', $v);
				if (empty($access_tree[$act]['sub_nav'][$opt]['do'][$do])) return false;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	*	新建后台角色
	*/
	public function createNewRole($role, $access, $global_search)
    {
        if(empty($role) || empty($access) || !is_string($role) || !is_array($access)) return false;
        if(count($this->adminRole->where(['role' => $role])->select())) return false;
        return $this->adminRole->insert([
            'role'          => $role,
            'access'        => json_encode($access, JSON_UNESCAPED_UNICODE),
            'global_search' => $global_search && 1,
            'create_time'   => date('Y-m-d H:i:s')
        ]);
    }

	/**
	*	修改用户权限
	*/
	public function updateRoleAccess($role_id, $role, $access, $global_search)
    {
        if (empty($role)) return false;
        return $this->adminRole->update([
			'id'			=> $role_id,
            'role'          => $role,
            'access'        => json_encode($access, JSON_UNESCAPED_UNICODE),
            'global_search' => $global_search && 1,
        ]);
    }

	/**
	*	判断url访问权限
	*/
	public function isAuthorizedUrl($act, $opt, $do = '', $tree = [])
    {
		if ($act != 'login') {
			if(array_key_exists($opt, $tree[$act]['sub_nav'])){
	            if(!in_array($act.'/'.$opt, $this->getAuth())) {
	                return false;
	            }elseif($do && !in_array($act.'/'.$opt.'/'.$do, $this->getAuth())) {
	                return false;
	            }
	        // 不在权限控制内的 url 直接给过
	        }
		}

        return true;
    }

    public function getAccess()
    {
        return json_decode($this->currentAdminRole['access'], 1);
    }

    public function getCity()
    {
        return json_decode($this->currentAdminCity['city'], 1);
    }

	public function getCurrentApplyCites()
    {
        return $this->currentAdminShop;
    }

    public function getAccessCities()
    {
        return model('AdminCity')->getAccessCities($this->adminId);
    }

    public function getAccessShops()
    {
        return model('AdminShop')->getAccessShops($this->adminId);
    }

    public function getAccessCitiesByAdminId()
    {
        return $this->adminCity->getAccessCities($this->adminId);
    }

    public function getAccessShopsByAdminId($adminId)
    {
        return $this->adminShop->getAccessShops($adminId);
    }


    /**
     * 获取所有的授权商铺id (包含授权的商铺id 和 授权的城市所在的商铺)
     */
    public function getAllAccessShops()
    {
        // 授权的商铺id
        $ids = $this->getAccessShops();

        // 授权的城市 所在的商铺
        $cities = $this->getAccessCities();
        $newIds = model('Shop')->getShopIdsByCities($cities);
        $ids = array_merge($ids, (array) $newIds);
        return array_unique($ids);
    }

    /**
     * 获取所有的授权商铺id (包含授权的商铺id 和 授权的城市所在的商铺)
     */
    public function getAllAccessShopsByAdminId($adminId)
    {
        // 授权的商铺id
        $ids = $this->getAccessShopsByAdminId($adminId);
        // 授权的城市 所在的商铺
        $cities = $this->getAccessCitiesByAdminId($adminId);
        $newIds = model('Shop')->getShopIdsByCities($cities);
        $ids = array_merge($ids, (array) $newIds);
        return array_unique($ids);
    }

    /**
     * 检查是够有该城市的权限
     */
    public function checkShopIdIsInAuthorizedCity($shop_id)
    {
        $shop_info = model('Shop')->get($shop_id);
        if (!$shop_info) return false;
        if (!$this->checkCityIsAuthorized($shop_info['city'])) return false;
        return true;
    }

    public function checkCityIsAuthorized($city)
    {
        $access_cities = $this->getAccessCities();
        if ($access_cities) {
            return in_array($city, $access_cities);
        }
    }

	/**
	 * 获取城市权限状态
	 */
	public function checkCityStatus()
    {
        if(!$this->currentAdminCity) return null;
        if($this->currentAdminCity['status'] == ADMIN_CITY_STATUS_APPLIED) return false;
        if($this->currentAdminCity['status'] == ADMIN_CITY_STATUS_NORMAL) return true;
    }

	public function deleteShopAccess($adminShopId)
    {
        $rst = $this->adminShop->where(['admin_id' => $this->adminId, 'id' => $adminShopId])->find();
        if(!$rst) return false;
        return $this->adminShop->delete($adminShopId);
    }

	/**
	* 获得已申请和申请中的商铺
	*/
	public function getShopsApply(){
		$shop = model('Shop');
		$shopsApply = $this->getCurrentApplyCites();
		$shop_applys_key = [];
		foreach ($shopsApply as &$shop_apply) {
            $shop_applys_key[] = $shop_apply['shop_id'];
            $c = $shop->where('id', $shop_apply['shop_id'])->find();
            $shop_apply['shop_name'] = $c['name'];
			$shop_apply['status'] = $shop::$STATUS[$shop_apply['status']];
            $shop_apply['shop_locate'] =
                $c['province'] .
                $c['city'] .
                $c['area'] .
                $c['locate'];
		};

		return $shopsApply;
	}

	/**
	* 添加商铺权限
	* @param arr, int 商铺id
	*/
	public function addShopAccess($shopId)
    {
        if (empty($shopId)) return false;
		if (!is_array($shopId)) {
			$shopId = [$shopId];
		}
		$shop = model('Shop');
		// 去重
        $shopId = array_unique($shopId);
        // 去零，去负数，去非整型
        $shopId = array_filter($shopId, function($a){
           return is_numeric($a) && $a > 0;
        });
        if (empty($shopId)) return false;
		// 验证shop_id是否合法
		if (count($shopId) != count($shop->where(['id'=>['in', $shopId]])->select())) return false;
        $data = [
            'admin_id'      => $this->adminId,
            'shop_id'       => 0,
            'status'        => $shop::STATUS_APPLY,
            'create_time'   => date('Y-m-d H:i:s'),
        ];
        // 批量插入
        foreach ($shopId as $v) {
			if ($this->adminShop->where([
					'admin_id'	=> $this->adminId,
					'shop_id'	=> $v,
				])->find()) {
				continue;
			}
            $data['shop_id'] = $v;
            $datas[] = $data;

        }
        return $this->adminShop->insertAll($datas);
    }

	/**
	*	删除当前管理员城市权限
	*/
	public function deleteCurrentCitiesAccess()
    {
        if(!$this->currentAdminCity) return false;
        return $this->adminCity->delete($this->currentAdminCity['id']);
    }

	/**
	* 检查城市是否合法
	* @param arr, 申请城市
	* @param arr，城市数列
	*/
	public function isAuthorizedCity($cities, $citiesTree)
	{
        // 省市验证: 有城市必有省份
        $province = array_map(function($v){
            if(strpos($v, '/') == 0) {
                return $v;
            }
        }, $cities);
        foreach($cities as $v) {
            if(strpos($v, '/')) {
                $p = substr($v, 0, strpos($v, '/'));
                if(!in_array($p, $province)) {
                    return false;
                }
            }
        }

        $tmp = [];
        foreach($citiesTree as $v) {
            $tmp[] = $v['province'];
            foreach($v['city'] as $vv) {
                $tmp[] = $v['province'].'/'.$vv['name'];
            }
        }
        //求2个数组的并集,并集数组的个数等于待验证的数组个数时,验证通过.
        return count(array_intersect($cities, $tmp)) == count($cities);
    }

	/**
	* 新增城市权限
	* @param arr 城市
	*/
	public function addCityAccess($cities)
    {
		// 转一维数组为二维数组 省份为key， 城市为value(数组）
		$newCities = $this->cityiesTransform($cities);

        // 没有申请过的可以申请
        if(!$this->currentAdminCity) {
            return $this->adminCity->insert([
                'admin_id'  => $this->adminId,
                'city'      => json_encode($newCities, JSON_UNESCAPED_UNICODE),
                'status'    => ADMIN_CITY_STATUS_APPLIED,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        }
        return false;
    }

    /**
     * 双重检查
     * 1. 检查shopid是否在授权的商铺下面
     * 2. 检查shopid是否在授权的城市下面
     */
    public function checkShopIdIsAuthorized($shop_id)
    {
        $access_shops = $this->getAccessShops();
        if (in_array($shop_id, $access_shops) || $this->checkShopIdIsInAuthorizedCity($shop_id)) return true;
        return false;

    }

    public function checkStationIdIsAuthorized($stationId)
    {
        $shop_station = model('ShopStation')->where('station_id', $stationId)->find();
        if (!$shop_station['shopid']) return false;
        return $this->checkShopIdIsAuthorized($shop_station['shopid']);
    }

    // 检查操作者是否用着对该商铺站点的权限
    public function checkShopStationIdIsAuthorized($shop_station_id)
    {
        $shop_station = model('ShopStation')->get($shop_station_id);
        return $shop_station['shopid'] ? $this->checkShopIdIsAuthorized($shop_station['shopid']) : false;
    }

	/**
	* 修改城市权限
	*/
	public function modifyCityAccess($cities)
    {
		// 转一维数组为二维数组 省份为key， 城市为value(数组）
		$newCities = $this->cityiesTransform($cities);

        $data = $this->adminCity->where(['admin_id' => $this->adminId])->find();
        // 有申请记录的可以修改
        if (count($data)!==0) {
            return $this->adminCity->where('id', $data['id'])->update([
                'city' => json_encode($newCities, JSON_UNESCAPED_UNICODE),
                'status' => ADMIN_CITY_STATUS_APPLIED,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        }
        return false;
    }

	/**
	* 转一维数组为二维数组 省份为key， 城市为value(数组）
	*/
	public function cityiesTransform($cities)
	{
		foreach ($cities as $v) {
			$tmp = explode('/', $v);
			if (!isset($tmp[1])) {
				$provinces[]= $v;
			}
		}
		foreach($provinces as $v) {
			foreach($cities as $v1) {
				$tmp = explode('/', $v1);
				if ($tmp[0] == $v && isset($tmp[1])) {
					$newCities[$v][] = $tmp[1];
				}
			}
		}

		return $newCities;
	}

	/**
	* 获取城市权限申请列表
	*/
	public function applyCitesInfo($needPaginate = false)
    {
		// 获取城市权限申请数据列表
		$data = $this->adminCity
					->alias('ac')
					->join('__ADMIN__ a', 'ac.admin_id = a.id')
					->join('__ADMIN_ROLE__ ar', 'a.role_id = ar.id')
					->order('ac.status asc')
					->column('
						ac.id as id,
						a.id as admin_id,
						ac.city as city,
						ac.status as status,
						a.name as name,
						ac.create_time as create_time,
						a.company as company,
						ar.role as role
					', 'ac.id');
		$data = array_map(function($v){
			$v['city'] = json_decode($v['city'], 1);
			return $v;
		}, $data);

		// 若分页则进行分页处理
		if ($needPaginate) {

			$info = $this->adminCity
						->order('status asc')
						->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>request()->param()])
						->each(function($item, $key)use($data){
							$item = $data[$item['id']] + $item;
							return $item;
						});

			return $info;
		}

		// 不分页则直接返回 data
        return $data;
    }

	/**
	*	城市申请处理
	*	@param adminId
	*	@param 行为
	*/
	public function handleCityApplyUsers($id, $action)
    {
        switch ($action) {
            case 'pass':
                $before = ADMIN_CITY_STATUS_APPLIED;
                $after  = ADMIN_CITY_STATUS_NORMAL;
                break;

            default:
                return false;
        }
        return $this->adminCity
				->where([
					'admin_id'	=>	$id,
					'status'	=>	$before,
				])
				->update([
					'status'	=>	$after,
				]);
    }

	/**
	*	城市申请处理
	*	@param admin_shop id
	*	@param 行为
	*/
	public function handleShopApplyUsers($adminShopId, $action)
    {
        switch ($action) {
            case 'pass':
                $before = $this->adminShop->STATUS_APPLY;
                $after  = $this->adminShop->STATUS_PASS;

                break;

            default:
                return false;
        }
        return $this->adminShop
				->where('id', 'in', $adminShopId)
				->where([
					'status'	=>	$before,
				])
				->update([
					'status'	=>	$after,
				]);
    }

	/**
	*	获取商铺权限申请列表
	*/
	public function applyShopsInfo()
    {
		$shop = model('Shop');
        $adminShops =  $this->adminShop
				->order('status asc, id desc')
				->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>request()->param()])
				->each(function($v, $key)use($shop){
					$adminRes = $this->admin -> where('id',$v['admin_id'])->find();
		            $v['status_text'] = $shop::$STATUS[$v['status']];
		            $v['name'] = $adminRes['name'];
		            $v['company'] = $adminRes['company'];
		            $v['role'] = $this->adminRole -> where('id', $adminRes['role_id'])->find()['role'];
					$shopInfo = $shop->get($v['shop_id']);
		            $v['shop_name'] = $shopInfo['name'];
		            if ($shopInfo['province'] == $shopInfo['city']) {
		                $shopInfo['city'] = '';
		            }
		            $v['shop_address'] = $shopInfo['province'].$shopInfo['city'].$shopInfo['area'].$shopInfo['locate'];
				});

		return $adminShops;
    }

}
