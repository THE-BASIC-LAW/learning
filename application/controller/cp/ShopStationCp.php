<?php namespace app\controller\cp;


use app\controller\Cp;
use app\lib\Api;
use think\Request;

class ShopStationCp extends Cp
{
    // 关联的ShopStation模型
    protected $shop_station = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->shop_station = model('ShopStation');
    }

    public function lists(){
        $access_shops  = null;
        $access_cities = null;
        extract(input());

        // 非全局搜索
        if (!$this->auth->globalSearch) {
            $access_cities = $this->auth->getAccessCities();
            $access_shops  = $this->auth->getAccessShops();
        }

        $shop_stations = $this->shop_station->searchShopStation($_GET, 0, $access_cities, $access_shops);
        echo count($shop_stations->toArray()['data']);
        exit;
        if(!is_array($shop_stations)){
            $pagehtm = $shop_stations->render();
            $shop_stations = $shop_stations->toArray()['data'];
        }

        // 显示商铺名称
        $shop_ids = array_map(function($a){
            return $a['shopid'];
        }, $shop_stations);
        $shop_infos = model('Shop')->where('id', 'in', $shop_ids)->select();  //不要用get, 因为shopIds是数组
        foreach ($shop_infos as $v) {
            $new_shop_infos[$v['id']] = $v;
        }

        // @todo optimize sql
        foreach ($shop_stations as $key => $shop_station){
            $shop_name = isset($new_shop_infos[$shop_stations[$key]['shopid']]['name']) ? $new_shop_infos[$shop_stations[$key]['shopid']]['name'] : '';
            $shop_stations[$key]['shopname'] = $shop_name;
            $shop_stations[$key]['desc'] = $shop_stations[$key]['desc'] ?  : '未设置';
            $shop_stations[$key]['fee_setting_name'] = $shop_stations[$key]['fee_settings'] ? model('FeeStrategy')->get($shop_stations[$key]['fee_settings'])['name'] : '全局配置';
            $admin = model('Admin')->get($shop_stations[$key]['seller_id']);
            $admin_role = model('AdminRole')->get($admin['role_id']);
            $shop_stations[$key]['seller_name'] = $shop_stations[$key]['seller_id'] ? $admin['name'] : '无';
            $shop_stations[$key]['seller_role_name'] = $admin_role ? $admin_role['role'] : '无';
        }

        $this->assign([
            'pagehtm'       => $pagehtm,
            'shop_stations' => $shop_stations,
        ]);
        return $this->fetch();
    }

    public function setting(){
        extract(input());
        if (isset($do)) {
            switch ($do) {
                // 绑定商铺
                case 'bind':
                    $shops = model('Shop')->paginate(RECORD_LIMIT_PER_PAGE, false, ['query'=>$_GET]);
                    $pagehtm = $shops->render();
                    $this->assign([
                        'shops'   => $shops,
                        'pagehtm' => $pagehtm,
                    ]);
                    return $this->fetch('shopSet');

                //　站点解绑商铺
                case 'unbind':
                    if (!$this->auth->globalSearch && !$this->auth->checkShopStationIdIsAuthorized($shop_station_id)) {
                        echo 'unauthorized station';
                        exit;
                    }
                    $this->admin->cpShopUnbind($shop_station_id);
                    $this->redirect("{$_SERVER['HTTP_REFERER']}");
                    break;


                    break;

                default:
            }
            exit;
        }
    }

    public function ajaxShopSet(){
        if (!$this->auth->globalSearch && !$this->auth->checkShopStationIdIsAuthorized($shop_station_id)) {
            echo 'unauthorized station';
            exit;
        }
        extract(input());
        $this->admin->cpShopBind($shop_station_id, $shop_id);
        $this->redirect("/$mod/$act/setting?page=$page");
    }

    public function shopStationRemove(){
        $res = $this->admin->cpRemove('', $_POST['shop_station_id']);
        return $res ? ['errcode'=>0, 'errmsg'=>'撤机成功'] : ['errcode'=>1, 'errmsg'=>'撤机失败'];
    }

    public function showShopStationReplace(){
        $shop_station = $this->shop_station->get(input()['shop_station_id']);
        $this->assign('shop_station', $shop_station);
        return $this->fetch();
    }

    public function shopStationReplace(){
        // 换机操作：针对已有绑定机器的商铺站点进行更换机器的操作
        extract(input());
        $new_station_id          = $station_id;
        $origin_station_id       = $this->shop_station->get($shop_station_id)['station_id'];
        $this_station_id_existed = model('Station')->get($new_station_id);
        if (!$this_station_id_existed) {
            return ['errcode'=> 2, 'errmsg'=>'不存在该机器'];
        }

        $this_station_id_binded = $this->shop_station->where('station_id', $new_station_id)->find();
        if ($this_station_id_binded) {
            return ['errcode'=> 3, 'errmsg'=>'该机器已绑定其他商铺站点'];
        }
        // 1.把这个商铺站点绑定到新的机器上
        // 2.把新机器的信息（title,address）与这个商铺站点同步
        // 3.把原来的机器信息（title,address）置空
        $res = $this->admin->cpReplace($shop_station_id, $new_station_id, $origin_station_id);
        return $res ? ['errcode'=> 0, 'errmsg'=>'换机成功'] : ['errcode'=> 1, 'errmsg'=>'换机失败'];
    }

    public function showShopStationGoUp(){
        return $this->fetch();
    }

    public function shopStationGoUp(){
        // 上机操作：在没有绑定机器的时候绑定一台新的机器
        extract(input());
        $new_station_id = $station_id;
        $this_station_id_existed = model('Station')->get($new_station_id);
        if (!$this_station_id_existed) {
            return ['errcode'=> 3, 'errmsg'=>'不存在该机器'];
        }

        $this_station_id_binded = $this->shop_station->where('station_id', $new_station_id)->find();
        if ($this_station_id_binded) {
            return ['errcode'=> 2, 'errmsg'=>'该机器已绑定其他商铺站点'];
        }

        $res = $this->admin->cpGoUp($shop_station_id, $new_station_id);

        return $res ? ['errcode'=>0, 'errmsg'=>'上机成功'] : ['errcode'=>1, 'errmsg'=>'上机失败'];
    }

    public function settingStrategy(){
        extract(input());
        $shop_station = $this->shop_station->get($shop_station_id);
        // 设置策略
        if (!$this->auth->globalSearch && !$this->auth->checkShopStationIdIsAuthorized($shop_station_id)) {
            return 'unauthorized station';
        }

        if($_POST) {
            if($this->shop_station->get($shop_station_id)->value('station_id') == 0 && $status == 1){
                Api::output([], 1, '未绑定站点，无法启用');
                exit;
            }
            if ($seller_id) {
                $sellerInfo = model('Admin')->get($seller_id);
                if (!$sellerInfo || $sellerInfo['status'] != ADMIN_USER_STATUS_NORMAL) {
                    Api::output([], 1, '归属负责人不存在或者非正常状态');
                    exit;
                }
            }
            $update_shop_station_fields = [
                'desc'             => $desc,
                'title'            => $title,
                'status'           => $status,
                'address'          => $address,
                'seller_id'        => $seller_id,
                'fee_settings'     => $fee_id,
                'pictext_settings' => $pic_id,
            ];
            $new_lbs_data = [
                'desc'    => $desc,
                'title'   => $title,
                'enable'  => $status,
                'address' => $address,
            ];
            $origin_lbs_data = [
                'desc'    => $shop_station['desc'],
                'title'   => $shop_station['title'],
                'enable'  => $shop_station['status'],
                'address' => $shop_station['address'],
            ];
            if ($origin_lbs_data != $new_lbs_data) {
                $ret = update_station_to_lbs($shop_station['lbsid'], $new_lbs_data);
                if ($ret['errcode'] == 0) {
                    $update_shop_station_fields['status'] = $status;
                }
            }
            $res = $this->admin->shopStationSettings($shop_station_id, $update_shop_station_fields);
            if ($station_id = $shop_station['station_id'] && $res) {
                model('Station')->get($station_id)->save(['title' => $title, 'address' => $address]);
            }
            if ($res) {
                Api::output([], 0, '商铺站点设置更新成功');
            } else {
                Api::output([], 1, '商铺站点设置更新失败');
            }
            exit;
        }

        $fees         = model('FeeStrategy')::all();
        $pictexts     = model('PictextSettings')::all();
        $feeSetting   = $shop_station['fee_settings'];
        $picSetting   = $shop_station['pictext_settings'];
        $seller_id    = $shop_station['seller_id'];
        $all_sellers  = model('Admin')->where('status', ADMIN_USER_STATUS_NORMAL)->find();
        $this->assign([
            'fees'         => $fees,
            'pictexts'     => $pictexts,
            'feeSetting'   => $feeSetting,
            'picSetting'   => $picSetting,
            'seller_id'    => $seller_id,
            'all_sellers'  => $all_sellers,
            'shop_station' => $shop_station,
        ]);
        return $this->fetch();
    }
}