<?php namespace app\controller\api;

use app\common\controller\Base;
use app\lib\Api;
use app\model\ShopType;
use think\Request;

/**
 * Class Common
 * 没有权限控制的API都可以放在这里
 */

class Common extends Base
{

    public function getProvinceInfo()
    {
        $data = array_map(function($v){
            return $v['province'];
        }, $GLOBALS['area_nav_tree']);
        Api::output($data);
    }

    public function getCityInfo(Request $request)
    {
        $data = getCitiesByProvince($request->post('province'), $GLOBALS['area_nav_tree']);
        Api::output($data);
    }

    public function getAreaInfo(Request $request)
    {
        $data = getAreasByCity($request->post('province'), $request->post('city'), $GLOBALS['area_nav_tree']);
        Api::output($data);
    }

    public function getAllShopType()
    {
        $data = (new ShopType())->getIdAndType();
        Api::output($data);
    }

    public function getAllShopLocate()
    {

    }
}
