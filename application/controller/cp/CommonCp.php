<?php namespace app\controller\cp;

use app\controller\Cp;
use app\lib\Api;
use app\lib\Auth;

class CommonCp extends Cp
{
    // 省市区
    public function getAreaInfo(){
        $ajax     = $_POST['ajax'];
        $city     = isset($_POST['city']) ? $_POST['city'] : '';
        $province = isset($_POST['province']) ? $_POST['province'] : '';
        // 全局搜索
        $auth = new Auth();
        $auth->globalSearch = 1;
        if ($auth->globalSearch) {
            if ($ajax == 1) {
                if($province) {
                    if($city) {
                        Api::output(getAreasByCity($province, $city, $GLOBALS['area_nav_tree']));
                    } else {
                        Api::output(getCitiesByProvince($province, $GLOBALS['area_nav_tree']));
                    }
                }
            }

            if ($ajax == 2) {
                // 所有省份
                $provinces = array_map(function($v){
                    return $v['province'];
                }, $GLOBALS['area_nav_tree']);
                Api::output($provinces);
            }
        }

        // 非全局搜索(仅授权的省市区)
        if (!$auth->globalSearch) {
            $adminId = $this->admin->adminInfo['id'];
            // 通过授权的城市获取相应的shop id
            $cities = model('AdminCity')->getAccessCities($adminId);
            $cShops = model('Shop')->where('city', 'in', $cities)->select();
            $cShops = array_map(function($a){
                return $a['id'];
            }, $cShops);

            // 授权的shop id
            $shops = model('AdminShop')->where(['admin_id' => $adminId, 'status' => model('AdminShop')::STATUS_PASS])->select();
            $shops = array_map(function($a){
                return $a['shop_id'];
            }, $shops);

            // 合并去重
            $shopIds = array_merge($cShops, $shops);
            $shopIds = array_unique($shopIds);

            // 组合省市区,去重
            $infos = model('Shop')->field('province, city, area')->where('id', 'in', $shopIds)->select();
            $infos = array_map(function($a){
                return join(',', $a);
            }, $infos);
            $infos = array_unique($infos);
            foreach ($infos as $v) {
                $tmp[] = explode(',', $v);
            }
            $infos = $tmp;

            if ($ajax == 1) {
                if($province) {
                    if($city) {
                        // 省市下面的区
                        $areas = array_map(function($a) use ($province, $city) {
                            if ($a[0] == $province && $a[1] == $city) {
                                return $a[2];
                            }
                            return '';
                        }, $infos);
                        $areas = array_filter($areas);
                        $areas = array_unique($areas);
                        Api::output($areas);
                    } else {
                        // 省下面的市
                        $cities = array_map(function($a) use ($province) {
                            if ($a[0] == $province) {
                                return $a[1];
                            }
                            return '';
                        }, $infos);
                        $cities = array_filter($cities);
                        $cities = array_unique($cities);
                        Api::output($cities);
                    }
                }
            }

            if ($ajax == 2) {
                // 所有省
                $provinces = array_map(function($a){
                    return $a[0];
                }, $infos);
                $provinces = array_unique($provinces);
                Api::output($provinces);
            }
        }
    }
}