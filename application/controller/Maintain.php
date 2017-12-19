<?php namespace app\controller;

use app\common\controller\Base;
use app\model\CommonSetting;
use app\model\Shop;
use app\model\ShopStation;
use app\model\ShopType;
use app\model\Station;
use app\third\baiduLbs;
use app\third\swApi;
use app\third\wxServer;
use app\lib\Api;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;

class Maintain extends Base
{

    protected $logName = 'maintain';
    protected $station;
    protected $uid;


    public function __construct(Request $request = null, $id)
    {
        parent::__construct($request);

        $station = Station::get($id);
        if (!$station) {
            $this->error('站点不存在', '/');
        }
        $this->station = $station;

        // 关闭模板布局
        $this->view->engine->layout(false);
    }

    protected function _initialize()
    {
        // 用户登录，使用PHP SESSION进行判断，与用户端区分开来。
        $uid = session('uid');
        if(empty($uid)) {
            $oauth = wxServer::instance()->oauth;
            $param = '?target_url='.$this->request->url(true);
            $oauth->setRedirectUrl($this->request->domain().'/maintain/oauth'.$param);
            return $oauth->redirect()->send();
        }
        $this->uid = $uid;

        // 判断是否有维护人员权限
        if (!$this->_authCheck()) {
            $this->error('未授权', '/');
        }

        // 过期判断
        if ($this->request->has('_t') && $this->request->param('_t') + 5*60 < time()) {
            $this->success('信息已过期，请重新扫码', '/');
        }
    }

    /**
     * @return bool true 是维护人员  false 非维护人员
     */
    private function _authCheck()
    {
        if ((new CommonSetting())->isMaintainMan($this->uid)) {
            return true;
        }
        return false;
    }

    public function init()
    {
        // 如果有绑定商铺，就跳转到管理页面
        if (ShopStation::get(['station_id' => $this->station->id, 'shopid' => ['gt', 0]])) {
            $this->redirect('/maintain/station/'.$this->station->id.'/manage');
        }
        $id = $this->station->id;
        $manageUrl = '/maintain/station/' . $id . '/manage';
        $baidu_map_web_ak = env('map.baidu_web_ak', 's3XlWEDIzNzkekllWj7ZLam03D98ByrP');
        $baidu_map_server_ak = env('map.baidu_server_ak', 'G9IOBvwnb7C2SLsCClEDdx6xV1igDjpz');
        $geotable_id = env('map.geotable_id', 179396);

        //return $this->display('platform/maintain/init')
        return view('platform/maintain/init', compact(
            'manageUrl',
            'id',
            'baidu_map_web_ak',
            'baidu_map_server_ak',
            'geotable_id')
        );
    }

    public function manage()
    {
        $id               = $this->station->id;
        $slotMgrUrl       = '/maintain/station/' . $id . '/slot_mgr';
        $initUrl          = '/maintain/station/' . $id . '/init';
        $replaceUrl       = '/maintain/station/' . $id . '/replace';
        $rmShopStationUrl = '/maintain/station/' . $id . '/remove';
        $hidden_button    = true;
        // 如果有绑定商铺，隐藏撤机按钮
        if (ShopStation::get(['station_id' => $this->station->id, 'shopid' => ['gt', 0]])) {
            $hidden_button = false;
        }
        return view('platform/maintain/manage', compact(
            'slotMgrUrl',
            'id',
            'initUrl',
            'replaceUrl',
            'hidden_button',
            'rmShopStationUrl'
        ));
    }

    public function addShop()
    {
        // 如果有绑定商铺，就禁止
        if (ShopStation::get(['station_id' => $this->station->id, 'shopid' => ['gt', 0]])) {
            Api::fail(1, '该机器已经绑定了商铺，请刷新当前页面');
        }

        // 验证post请求是否为空
        $checkRequest =
            $this->request->has('stationName', 'post', true) &&
            $this->request->has('stationProvince', 'post', true) &&
            $this->request->has('stationCity', 'post', true) &&
            $this->request->has('stationArea', 'post', true) &&
            $this->request->has('stationStreet', 'post', true) &&
            $this->request->has('stationDesc', 'post', true) &&
            $this->request->has('type', 'post', true) &&
            $this->request->has('phone', 'post', true) &&
            $this->request->has('stime', 'post', true) &&
            $this->request->has('etime', 'post', true) &&
            $this->request->has('longitude', 'post', true) &&
            $this->request->has('latitude', 'post', true);

        if (!$checkRequest) {
            Api::fail(1, '缺少参数');
        }

        // 验证shop type是否存在
        if (!ShopType::get($this->request->post('type'))) {
            Log::notice('shop type check fail');
            Api::fail(1, '缺少商铺类型');
        }

        extract(input('post.'));

        // 省市区是否合法
        include APP_PATH . 'area_tree.php';
        if (!checkProvenceCityAreaLegal(
            $area_nav_tree,
            $this->request->post('stationProvince'),
            $this->request->post('stationCity'),
            $this->request->post('stationArea'))
        ) {
            Log::notice('check province city area legal fail');
            Api::fail(1, '缺少省市区');
        }

        // 替换中文符号为英文符号
        $stationName = str_replace(['（', '）', '《', '》'], ['(', ')', '(', ')'], $stationName);
        $stationStreet = str_replace(['（', '）', '《', '》'], ['(', ')', '(', ')'], $stationStreet);
        $stationDesc = str_replace(['（', '）', '《', '》'], ['(', ')', '(', ')'], $stationDesc);

        $first_title = $stationName . ' A';

        $isMunicipality = false;
        if ($stationProvince && $stationProvince == $stationCity) {
            $stationCity = ''; //去掉直辖市重复的情况
            $isMunicipality = true;
        }

        $ret = baiduLbs::createPOI(
            $first_title,
            $longitude,
            $latitude,
            $stationProvince.$stationCity.$stationArea.$stationStreet
        );
        if ($ret['status'] != 0) {
            Api::fail(1, '地图描点失败');
        }

        $lbsId = $ret['id'];

        $shopData = [
            'name'     => $stationName,
            'province' => $stationProvince,
            'city'     => $isMunicipality ? $stationProvince : $stationCity, // 直辖市 city使用省份
            'area'     => $stationArea,
            'locate'   => $stationStreet,
            'cost'     => $cost,
            'phone'    => $phone,
            'stime'    => $stime,
            'etime'    => $etime,
            'type'     => $type,
            'logo'     => '',
            'carousel' => '',
            'status'   => 0
        ];
        $shop = new Shop();
        $shop->save($shopData);
        $newShopId = $shop->id;
        Log::info('new shop id: ' . $newShopId);

        // 更新shopstation里面station_id相同的设备
        ShopStation::update(['station_id' => 0], ['station_id' => $this->station->id]);

        // 增加新的shopStation
        $shopStationData = [
            'shopid'     => $newShopId,
            'station_id' => $this->station->id,
            'title'      => $first_title,
            'address'    => $stationProvince . $stationCity . $stationArea . $stationStreet,
            'desc'       => $stationDesc,
            'longitude'  => $longitude,
            'latitude'   => $latitude,
            'status'     => 1,
            'error_man'  => '',
            'lbsid'      => $ret['id'],
        ];
        $shopStation = new ShopStation();
        $shopStation->save($shopStationData);
        $newShopStationId = $shopStation->id;
        Log::info('new shop station id: ' . $newShopStationId);

        // 更新station表记录
        Station::update([
            'title' => $first_title,
            'address' => $stationProvince . $stationCity . $stationArea . $stationStreet
        ], ['id' => $this->station->id]);

        // 更新百度云检索(启用云检索)
        $ret = baiduLbs::updatePOI(['id' => $lbsId, 'sid' => $newShopStationId, 'enable' => baiduLbs::POI_ENABLE]);
        if ($ret['status'] != 0) {
            Log::notice('update baidu lbs fail, ' . print_r($ret ,1));
            Api::fail(1, '更新地图信息失败');
        }
        Log::notice('add shop success');
        Api::output();
    }

    public function slotMgr()
    {
        $stationInfo = $this->station;
        $slotsStatus = (new Station())->getSlotsStatus($this->station->id);
        $isStationOnline = swApi::isStationOnline($this->station->id);
        return view('platform/maintain/slot_mgr', compact(
            'isStationOnline',
            'stationInfo',
            'slotsStatus'
        ));
    }

    public function slotMgrHandle()
    {
        if ($this->request->isAjax()) {
            // 过滤slot
            $slot = $this->request->post('slot/a');
            if (empty($slot)) {
                Api::fail(1, '未选择槽位');
            }
            $slot = array_filter($slot);
            $slot = array_unique($slot);
            if (empty($slot)) {
                Api::fail(1, '未选择操作');
            }
            // @todo 过滤不存在的槽位

            switch ($this->request->post('type')) {
                // 解锁槽位
                case 'unlock':
                    foreach ($slot as $v) {
                        $v = (int)$v;
                        swApi::slotUnlock(['station_id' => $this->station->id, 'slot_num' => $v]);
                        sleep(7);
                    }
                    break;

                // 锁住槽位
                case 'lock':
                    foreach ($slot as $v) {
                        $v = (int)$v;
                        swApi::slotLock(['station_id' => $this->station->id, 'slot_num' => $v]);
                        sleep(7);
                    }
                    break;

                // 人工借出
                case 'manuallyLent':
                    foreach ($slot as $v) {
                        $v = (int)$v;
                        swApi::lend(['station_id' => $this->station->id, 'slot_num' => $v]);
                        sleep(7);
                    }
                    break;

                default:

            }
            Api::output();
        }
        Api::fail(1, 'error');
    }

    public function replace()
    {
        // 绑定了shopid的站点才能进行操作
        if (!ShopStation::get(['station_id' => $this->station->id, 'shopid' => ['gt', 0]])) {
            $this->redirect('/maintain/station/'.$this->station->id.'/manage');
        }
        $stationInfo = $this->station;
        $shopStationInfo = ShopStation::get(['station_id' => $this->station->id]);
        $shopInfo = Shop::get($shopStationInfo->shopid);

        return view('platform/maintain/replace', compact(
            'stationInfo',
            'shopStationInfo',
            'shopInfo'
        ));
    }

    public function replaceHandle()
    {
        if ($this->request->isAjax()) {
            $newStationId = $this->request->post('new_station_id');

            $station = db('station')->find($newStationId);
            if (!$station) {
                Api::fail(1, '新机器编号不存在');
            }
            $shopStation = db('shop_station')->where('station_id', $newStationId)->find();
            if ($shopStation) {
                Api::fail(1, '新机器编号已绑定其他商铺');
            }

            Db::startTrans();
            try {

                // 0.查询当前站点绑定的商铺站点信息
                // 1.把这个商铺站点绑定到新的机器上
                // 2.把新机器的信息（title,address）与这个商铺站点同步
                // 3.把原来的机器信息（title,address）置空

                $shopStation = ShopStation::get(['station_id' => $this->station->id, 'shopid' => ['gt', 0]]);
                if (!$shopStation) {
                    Api::fail(1, '更新新机器失败');
                }
                $rst1 = $shopStation->save(['station_id' => $newStationId]);
                $rst2 = db('station')->update([
                    'id' => $newStationId,
                    'title' => $shopStation['title'],
                    'address' => $shopStation['address']
                ]);
                $rst3 = db('station')->update([
                    'id' => $this->station->id,
                    'title' => '',
                    'address' => ''
                ]);

                if ($rst1 && $rst2 && $rst3) {
                    Db::commit();
                    Log::info('shop station replace success');
                    Api::output();
                } else {
                    Log::alert('shop station replace fail. rst1: ' . $rst1 . ', rst2: ' . $rst2 . ', rst3:' . $rst3);
                    Db::rollback();
                    Api::fail(1, '更新新机器失败');
                }
            } catch (Exception $e) {
                Log::error('shop station replace fail, exception: ' . $e->getMessage());
                Db::rollback();
                Api::fail(1, '更新新机器失败');
            }
        }
        Api::fail(1, 'error');
    }

    public function removeShopStation()
    {
        // 更新百度lbs 的enable = 0
        $shopStation = db('shop_station')->where('station_id', $this->station->id)->find();
        if (empty($shopStation)) {
            Api::fail(1, '撤机失败');
        }
        $lbsId = $shopStation['lbsid'];
        if (empty($lbsId)) {
            Api::fail(1, '撤机失败');
        }
        $result = baiduLbs::updatePOI(['id' => $lbsId, 'enable' => baiduLbs::POI_DISABLE]);
        if ($result['status'] != 0) {
            Log::notice('update poi result: ' . print_r($result, 1));
            Api::fail(1, '百度地图POI更新失败');
        }

        Db::startTrans();
        try {
            $rst1 = db('shop_station')->update([
                'id' => $shopStation['id'],
                'station_id' => 0,
                'status' => ShopStation::STATUS_DISABLE
            ]);
            $rst2 = db('station')->update([
                'id' => $this->station->id,
                'title' => '',
                'address' => ''
            ]);
            if ($rst1 && $rst2) {
                Db::commit();
                Log::notice('shop station remove success');
                Api::output();
            } else {
                Db::rollback();
                Log::alert('shop station remove fail');
                Api::fail(1, '撤机失败');
            }
        } catch (Exception $e) {
            Log::alert('shop station remove fail, exception: ' . $e->getMessage());
            Db::rollback();
        }

    }
}