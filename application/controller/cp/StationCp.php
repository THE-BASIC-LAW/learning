<?php
namespace app\controller\cp;

use app\controller\Cp;
use app\lib\Api;
use app\model\Station;
use app\third\alipay\AlipayAPI;
use app\third\swApi;
use think\Request;
use think\Session;


class StationCp extends Cp
{
    // 关联的Staion模型
    protected $station;

    /**
     * 构造函数
     * @access public
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->station = new Station();
    }

    // 商铺信息列表
    public function lists(){
        $access_shops  = null;
        $access_cities = null;
        extract(input());
        if (isset($do)) {
            if (!$this->auth->globalSearch && !$this->auth->checkStationIdIsAuthorized($station_id)) {
                Api::output([], 3, '您无权操作该站点');
            }
            switch ($do) {
                // 槽位操作
                case 'slotAction':
                    $slot_status    = $this->station->getSlotsStatus($station_id);
                    $slotscount     = count($slot_status);
                    $last_sync_time = [];
                    for ($i = 1; $i <= $slotscount; $i++) {
                        $rst = model('StationSlotLog')->getLastSyncTime($station_id, $i, 3);
                        if (empty($rst) || !isset($rst['last_sync_umbrella_time']) || !$rst['last_sync_umbrella_time']) {
                            $last_sync_time[$i] = '无';
                        } else {
                            $last_sync_time[$i] = date('Y-m-d H:i:s', $rst['last_sync_umbrella_time']);
                        }
                    }
                    $this->assign([
                        'slotscount'     => $slotscount,
                        'slot_status'    => $slot_status,
                        'last_sync_time' => $last_sync_time,
                    ]);
                    return $this->fetch('slotAction');

                case 'manuallyControl':
                    return $this->fetch('manuallyControl');

                case 'showQrcode':
                    if (!model('Station')->get($station_id)){
                        echo '站点不存在';
                        exit;
                    }
                    $wechat_qrcode_url = get_limit_qrcode_url($station_id, 60);
                    AlipayAPI::initialize();
                    $alipay_qrcode_url = AlipayAPI::createQrcode($station_id, 60);
                    $this->assign([
                        'wechat_qrcode_url' => $wechat_qrcode_url,
                        'alipay_qrcode_url' => $alipay_qrcode_url,
                    ]);
                    return $this->fetch('showQrcode');

                default:
                    if(!method_exists('app\third\swApi', $do)){
                        Api::fail(2, '不存在的指令');
                    } else {
                        swApi::$do($_GET);
                        $this->admin->$do($_GET);
                    }
            }
            Api::output();
        }
        $access_station = null;
        // 非全局搜索权限 @todo 等待打开权限验证
        if (!$this->auth->globalSearch) {
            $access_shops   = $this->auth->getAllAccessShops();
            $access_station = model('Shop')->getStationIdsByShopIds($access_shops);
            $access_station = array_filter($access_station);
        }
        //站点导出功能
        if (isset($_GET['export']) && Session::get('cdo')['export']) {
            //@todo 等待加入权限验证
            $stations         = $station->searchStation($_GET, '', '', $access_station);
            $stations['data'] = model('Station')::all();
            $stations['data'] = array_map(function ($a) use ($station) {
                $a['sync_time']      = date('Y-m-d H:i:s', $a['sync_time']);
                $a['network_status'] = $station->checkNetworkOnline($a['id']) ? '是' : '否';
                $a['maintain_name']  = '';
                $a['maintain_role']  = '';
                $a['phone']          = '';
                // @todo optimize
                $shopStation = model('ShopStation')->where('station_id', $a['id'])->find();
                if ($shopStation['shopid'])
                {
                    $admin_shop = model('AdminShop')
                        ->where(['shop_id' => $shopStation['shopid'], 'status'  => model('AdminShop')::STATUS_PASS])
                        ->find();
                    if ($admin_shop['admin_id'])
                    {
                        $admin              = model('Admin')->get($admin_shop['admin_id']);
                        $a['maintain_name'] = $admin['name'];
                        $a['maintain_role'] = model('AdminRole')->get($admin['role_id'])['role'];
                    }
                    $a['phone'] = model('Shop')->fetch($shopStation['shopid'])['phone'];
                }

                return $a;
            }, $stations['data']);
            $sheetarray[]     = create_excel_column($stations['data'], 'id', '站点ID');
            $sheetarray[]     = create_excel_column($stations['data'], 'title', '站点名称');
            $sheetarray[]     = create_excel_column($stations['data'], 'address', '具体地址');
            $sheetarray[]     = create_excel_column($stations['data'], 'phone', '联系电话');
            $sheetarray[]     = create_excel_column($stations['data'], 'network_status', '是否在线');
            $sheetarray[]     = create_excel_column($stations['data'], 'total', '雨伞总数');
            $sheetarray[]     = create_excel_column($stations['data'], 'usable', '可借数');
            $sheetarray[]     = create_excel_column($stations['data'], 'empty', '可还数');
            $sheetarray[]     = create_excel_column($stations['data'], 'voltage', '设备电压');
            $sheetarray[]     = create_excel_column($stations['data'], 'sync_time', '最后同步时间');
            $sheetarray[]     = create_excel_column($stations['data'], 'maintain_role', '维护者角色');
            $sheetarray[]     = create_excel_column($stations['data'], 'maintain_name', '维护人');
            $sheetarray       = transpose($sheetarray);
            export_excel($sheetarray, 'StationList_' . date("Ymd"));
            exit;
        }
        //@todo 等待加入权限验证
        $stations = $this->station->searchStation($_GET, RECORD_LIMIT_PER_PAGE, $access_station);
        if(!is_array($stations)){
            $pagehtm  = $stations->render();
            $stations = $stations->toArray()['data'];
        }

        foreach ($stations as $key => $station){
            // 雨伞同步判断 开启同步筛选的话所有值均为true
            $umbrella_outside_sync = isset($umbrella_outside_sync) ? $umbrella_outside_sync : '';
            $has_outside_sync_umbrella = $umbrella_outside_sync == 'on' ? true : $this->station->isStationHasumbrellaSync($stations[$key]['id']);
            $stations[$key]['has_outside_sync_umbrella'] = $has_outside_sync_umbrella;
            // 设备是否在线
            $stations[$key]['network_status'] = $this->station->checkNetworkOnline($stations[$key]['id']);
            // 所属商铺名称
            $stations[$key]['shopname'] = model('Shop')->getShopInfoByStationId($stations[$key]['id'])['name'];
            // 同步策略名称
            $stations[$key]['station_settings_name'] = model('Shop')->getStationSettingsNameByStationId($stations[$key]['id']) ?: '全局配置';
        }

        // 所有省份
        $provinces = array_map(function ($v) {
            return $v['province'];
        }, $GLOBALS['area_nav_tree']);
        $this->assign([
            'pagehtm'   => $pagehtm,
            'stations'  => $stations,
            'provinces' => $provinces,
        ]);
        return $this->fetch();
    }

    public function heartbeatLog(){
        extract(input());
        if (isset($station_id)) {
            $station_heartbeat_log       = model('StationHeartbeatLog');
            $begin_time                  = $sdate ? strtotime($sdate) : strtotime(date("Y-m-d"));
            $end_time                    = $edate ? strtotime($edate) : $begin_time + 86400;
            $times                       = model('StationHeartbeatLog')->getTimeList();
            $offline_info                = [];
            $offline_time                = 0;
            $offline_count               = 0;
            for ($i = 0; $i < count($times) - 1; $i++) {
                $delta = $times[$i] - $times[$i + 1];
                // 超过3个心跳记录认为机器掉线了
                if ($delta > STATION_HEARTBEAT * 3)
                {
                    $offline_time                 += $delta;
                    $offline_count                += 1;
                    $offline_info[$times[$i + 1]] = humanTime($delta);
                }
            }
            if (!$offline_time) {
                $alive_time = humanTime($end_time - $begin_time);
            } else {
                $alive_time = humanTime($end_time - $begin_time - $offline_time);
            }
            if (isset($export)) {
                $name = 'log_' . time();
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=$name.xls");
                echo "时间段：\t";
                echo "$sdate - $edate\t\n";
                echo "在线总时长：\t";
                echo "$alive_time\t\n";
                echo "离线次数：\t";
                echo "$offline_count\t\n";
                echo "\n";
                echo "离线时间\t";
                echo "离线时长\t\n";
                foreach ($offline_info as $k => $v)
                {
                    $k = date("Y-m-d H:i:s", $k);
                    echo "$k\t";
                    echo "$v\t\n";
                }
                exit;
            }
            $heartbeat_log = $station_heartbeat_log->findAllBySearch($begin_time, $end_time, $station_id, RECORD_LIMIT_PER_PAGE);
            $this->assign('heartbeat_log', $heartbeat_log);
        }
        return $this->fetch();
    }

    public function stationLog(){
        extract(input());
        if (isset($start_time)) {
            $pre   = date('Ymd', strtotime($start_time));
            $where = null;
            if ($province || $city || $area) {
                if (!checkProvenceCityAreaLegal($area_nav_tree, $province, $city, $area)) {
                    redirect('省市区不存在');
                }
                $city     && $search['city'] = $city;
                $area     && $search['area'] = $area;
                $province && $search['province'] = $province;
                $search_shop_ids         = model('Shop')->where($search)->column('id');
                $search_shop_station_ids = model('ShopStation')->where('shopid', 'in', $search_shop_ids)->column('station_id');
                $search_shop_station_ids = array_filter($search_shop_station_ids);
                if ($station_id) {
                    $search_shop_station_ids = in_array($station_id, $search_shop_station_ids) ? $station_id : [];
                }
                $where['station_id'] = ['in', $search_shop_station_ids];
            } else {
                $station_id && $where['station_id'] = $station_id;
            }
            // 使用stationlog里面的station id
            $where['left(id, 8)'] = $pre;
            $current_station_log = model('StationLog')
                ->where($where)
                ->paginate(RECORD_LIMIT_PER_PAGE, false, ['query'=>$_GET])
                ->each(function($item){
                    $item->new_station_id = substr($item->id, 8);
                });
            $pagehtm                         = $current_station_log->render();
            $current_station_log             = $current_station_log->toArray()['data'];
            $current_station_log_station_ids = array_column($current_station_log, 'new_station_id');
            $shop_station_info               = model('ShopStation')->where('station_id', 'in', $current_station_log_station_ids)->select();
            foreach ($shop_station_info as $v) {
                $new_shop_station_info[$v['station_id']] = $v;
            }

            $current_station_log = array_map(function ($a) use ($new_shop_station_info) {
                // 负责人
                if (key_exists($a['new_station_id'], $new_shop_station_info)) {
                    $a['shop_station_name'] = $new_shop_station_info[$a['new_station_id']]['title'];
                    // @todo optimize
                    if ($shop_id = $new_shop_station_info[$a['new_station_id']]['shopid']) {
                        $admin_shop = model('AdminShop')
                            ->where(['shop_id' => $shop_id, 'status' => model('AdminShop')::STATUS_PASS])
                            ->find();
                        if ($admin_shop['admin_id']) {
                            $admin              = model('Admin')->get($admin_shop['admin_id']);
                            $a['maintain_name'] = $admin['name'];
                            $a['maintain_role'] = model('AdminRole')->get($admin['role_id'])['role'];
                        }
                    }
                }
                $a['shop_station_name'] = $a['shop_station_name'] ? : '-';
                $a['maintain_role']     = $a['maintain_role'] ? : '-';
                $a['maintain_name']     = $a['maintain_name'] ? : '-';
                // 雨伞保有量等
                if ($a['rssi_info']) {
                    $a['rssi_info_desc'] = implode('/', json_decode($a['rssi_info'], true));
                } else {
                    $a['rssi_info_desc'] = '0/0/0/0/0';
                }
                if ($a['umbrella_from_station']) {
                    $a['umbrella_from_station_desc'] = implode('/', json_decode($a['umbrella_from_station'], true));
                } else {
                    $a['umbrella_from_station_desc'] = '0/0/0/0/0';
                }
                if ($a['slot_from_station']) {
                    $a['slot_from_station_desc'] = implode('/', json_decode($a['slot_from_station'], true));
                } else {
                    $a['slot_from_station_desc'] = '0/0/0/0/0';
                }

                return $a;
            }, $current_station_log);
            // 固定统计日期
            $hour_array            = [2, 7, 12, 17, 22];
            $all_station_log       = model('StationLog')->where($where)->select();
            // 雨伞保有量总数
            $all_station_log_umbrella_from_station = array_column($all_station_log, 'umbrella_from_station');
            $all_station_log_umbrella_from_station = array_filter($all_station_log_umbrella_from_station, function ($a) {
                if ($a !== '')
                    return true;
            });
            $total_umbrella_from_station         = [];
            foreach ($all_station_log_umbrella_from_station as $v) {
                $tmp = json_decode($v, true);
                foreach ($hour_array as $vv) {
                    $total_umbrella_from_station[$vv] += $tmp[$vv];
                }
            }
            $total_umbrella_from_station = implode('/', $total_umbrella_from_station);
            // 槽位总数
            $all_station_log_slot_from_station = array_column($all_station_log, 'slot_from_station');
            $all_station_log_slot_from_station = array_filter($all_station_log_slot_from_station, function ($a) {
                if ($a !== '')
                    return true;
            });
            $total_slot_from_station         = [];
            foreach ($all_station_log_slot_from_station as $v) {
                $tmp = json_decode($v, true);
                foreach ($hour_array as $vv)
                {
                    $total_slot_from_station[$vv] += $tmp[$vv];
                }
            }
            $total_slot_from_station = implode('/', $total_slot_from_station);
            // 导出功能
            if (isset($_GET['export'])) {
                // 需要重新整理数据
                $all_station_log = db('StationLog')
                    ->where($where)
                    ->exp('left(id, 8)', $pre)
                    ->select();
                $all_station_log         = array_map(function ($a) {
                    $a['new_station_id'] = substr($a['id'], 8);
                    return $a;
                }, $all_station_log);
                $all_station_log_station_id = array_column($all_station_log, 'new_station_id');
                $shop_station_info          = model('ShopStation')->where('station_id', 'in', $all_station_log_station_id)->select();
                unset($new_shop_station_info);
                foreach ($shop_station_info as $v) {
                    $new_shop_station_info[$v['station_id']] = $v;
                }
                $all_station_log = array_map(function ($a) use ($new_shop_station_info) {
                    // 负责人
                    if (key_exists($a['new_station_id'], $new_shop_station_info)){
                        $a['shop_station_name'] = $new_shop_station_info[$a['new_station_id']]['title'];
                        // @todo optimize
                        if ($shop_id = $new_shop_station_info[$a['new_station_id']]['shopid']) {
                            $admin_shop = model('AdminShop')
                                ->where(['shop_id' => $shop_id, 'status' => model('AdminShop')::STATUS_PASS])
                                ->find();
                            if ($admin_shop['admin_id']) {
                                $admin              = model('Admin')->get($admin_shop['admin_id']);
                                $a['maintain_name'] = $admin['name'];
                                $a['maintain_role'] = model('AdminRole')->get($admin['role_id'])['role'];
                            }
                        }
                    }
                    $a['shop_station_name'] = $a['shop_station_name'] ?: '';
                    $a['maintain_role']     = $a['maintain_role'] ?: '';
                    $a['maintain_name']     = $a['maintain_name'] ?: '';
                    // 雨伞保有量等
                    if ($a['rssi_info']) {
                        $a['rssi_info_desc'] = implode('/', json_decode($a['rssi_info'], true));
                    } else {
                        $a['rssi_info_desc'] = '0/0/0/0/0';
                    }
                    if ($a['umbrella_from_station']) {
                        $a['umbrella_from_station_desc'] = implode('/', json_decode($a['umbrella_from_station'], true));
                    } else {
                        $a['umbrella_from_station_desc'] = '0/0/0/0/0';
                    }
                    if ($a['slot_from_station']) {
                        $a['slot_from_station_desc'] = implode('/', json_decode($a['slot_from_station'], true));
                    } else {
                        $a['slot_from_station_desc'] = '0/0/0/0/0';
                    }

                    return $a;
                }, $all_station_log);
                $sheetarray[]  = create_excel_column($all_station_log, 'new_station_id', '站点ID');
                $sheetarray[]  = create_excel_column($all_station_log, 'shop_station_name', '商铺站点名称');
                $sheetarray[]  = create_excel_column($all_station_log, 'maintain_role', '归属角色');
                $sheetarray[]  = create_excel_column($all_station_log, 'maintain_name', '负责人');
                $sheetarray[]  = create_excel_column($all_station_log, 'umbrella_from_station_desc', '雨伞保有量(2/7/12/17/22)');
                $sheetarray[]  = create_excel_column($all_station_log, 'slot_from_station_desc', '雨伞槽位投放量(2/7/12/17/22)');
                $sheetarray[]  = create_excel_column($all_station_log, 'rssi_info_desc', '信号分布(2/7/12/17/22)');
                $sheetarray[]  = create_excel_column($all_station_log, 'max_umbrella_count', '最大雨伞数');
                $sheetarray[]  = create_excel_column($all_station_log, 'min_umbrella_count', '最小雨伞数');
                $sheetarray[]  = create_excel_column($all_station_log, 'online_time', '开机时长(分钟)');
                $sheetarray[]  = create_excel_column($all_station_log, 'login_count', '机器登录次数');
                $sheetarray    = transpose($sheetarray);
                array_unshift($sheetarray, [$pre . '站点统计日志', '', '', '总计', $total_umbrella_from_station, $total_slot_from_station]);
                export_excel($sheetarray, 'StationLogList_' . $pre);
                exit;
            }
            $this->assign([
                'pagehtm'                     => $pagehtm,
                'current_station_log'         => $current_station_log,
                'total_slot_from_station'     => $total_slot_from_station,
                'total_umbrella_from_station' => $total_umbrella_from_station,
            ]);
        }
        return $this->fetch();
    }

    public function batchImport(){
        extract(input());
        if (isset($path)) {
            $file = fopen($path, 'r');
            $data = [];
            while ($res = fgets($file))
            {
                $data[] = explode(',', str_replace("\n", '', $res));
            }
            $columns = ['id', 'title', 'mac'];
            for ($i = 0; $i < count($data); $i++)
            {
                foreach ($columns as $k => $v)
                {
                    $arr[$columns[$k]] = $data[$i][$k];
                }
                $this->station->insert($arr, 1); //重复就替换掉
            }
        }
        return $this->fetch();
    }

    public function umbrellaExport(){
        extract($_GET);
        $current_station     = model('CommonSetting')->get('umbreall_export_default_station') ?: 1080;
        $umbrella_counts     = model('station')->get($current_station)['usable'];
        // 返回的数据按照slot顺序 sync_time倒叙排列
        $umbrellas = model('umbrella')->getLimitedUmbrellas($current_station, $umbrella_counts);
        // 排序
        $umbrellas = multi_array_sort($umbrellas, 'slot');
        foreach ($umbrellas as &$u) {
            $u['sync_time']      = empty($u['sync_time']) ? '无' : date('Y-m-d H:i:s', $u['sync_time']);
            $u['exception_time'] = empty($u['exception_time']) ? '无' : date('Y-m-d H:i:s', $u['exception_time']);
            $u['heart_time']     = empty($u['heart_time']) ? '无' : date('Y-m-d H:i:s', $u['heart_time']);
        }
        if (isset($ajax)) {
            if (!ENV_DEV)
                return '';
            if (empty($umbrellas))
                return '';
            $umbrellas = array_map(function ($a) {
                $b[0] = $a['id'];
                $b[1] = $a['slot'];
                $b[2] = $a['sync_time'];

                return $b;
            }, $umbrellas);
            $excelObj  = new \PHPExcel();
            $excelObj->setActiveSheetIndex(0);
            $excelObj->getActiveSheet()
                ->setCellValue('A1', 'ID')
                ->setCellValue('B1', 'SLOT')
                ->setCellValue('C1', 'SYNC_TIME');
            $excelObj->getActiveSheet()->fromArray($umbrellas, null, 'A2');
            $fileName = $current_station . '_' . date('Y-m-d_H_i_s') . '.xls';
            // Redirect output to a client’s web browser (Excel5)
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename=' . $fileName);
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0
            $objWriter = \PHPExcel_IOFactory::createWriter($excelObj, 'Excel5');
            $objWriter->save('php://output');
            exit;
        }
        $this->assign([
            'umbrellas'       => $umbrellas,
            'current_station' => $current_station,
        ]);
        return $this->fetch();
    }

    public function umbrellaExport2(){
        $current_station     = model('CommonSetting')->get('umbreall_export_default_station_2') ?: 2004;
        $umbrella_counts = $this->station->get($current_station)['usable'];
        // 返回的数据按照slot顺序 sync_time倒叙排列
        $umbrellas = model('umbrella')->getLimitedUmbrellas($current_station, $umbrella_counts);
        // 排序
        $umbrellas = multi_array_sort($umbrellas, 'slot');
        foreach ($umbrellas as &$u) {
            $u['sync_time']      = empty($u['sync_time']) ? '无' : date('Y-m-d H:i:s', $u['sync_time']);
            $u['exception_time'] = empty($u['exception_time']) ? '无' : date('Y-m-d H:i:s', $u['exception_time']);
            $u['heart_time']     = empty($u['heart_time']) ? '无' : date('Y-m-d H:i:s', $u['heart_time']);
        }
        if (isset($ajax)) {
            if (!ENV_DEV)
                return '';
            if (empty($umbrellas))
                return '';
            $umbrellas = array_map(function ($a) {
                $b[0] = $a['id'];
                $b[1] = $a['slot'];
                $b[2] = $a['sync_time'];

                return $b;
            }, $umbrellas);
            $excelObj  = new \PHPExcel();
            $excelObj->setActiveSheetIndex(0);
            $excelObj->getActiveSheet()
                ->setCellValue('A1', 'ID')
                ->setCellValue('B1', 'SLOT')
                ->setCellValue('C1', 'SYNC_TIME');
            $excelObj->getActiveSheet()->fromArray($umbrellas, null, 'A2');
            $fileName = $current_station . '_' . date('Y-m-d_H_i_s') . '.xls';
            // Redirect output to a client’s web browser (Excel5)
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename=' . $fileName);
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0
            model('umbrella')->where('station_id', 'in', $current_station)->delete();
            $objWriter = \PHPExcel_IOFactory::createWriter($excelObj, 'Excel5');
            $objWriter->save('php://output');
            exit;
        }
        $this->assign([
            'umbrellas'       => $umbrellas,
            'current_station' => $current_station,
        ]);
        return $this->fetch();
    }

    public function umbrellaDetail(){
        extract($_GET);
        $umbrella_counts = $this->station->get($station_id)['usable'];
        // 返回的数据按照slot顺序 sync_time倒叙排列
        $umbrellas = model('umbrella')->getLimitedUmbrellas($station_id, $umbrella_counts);
        // 排序
        $umbrellas = multi_array_sort($umbrellas, 'slot');
        foreach ($umbrellas as &$u) {
            $u['sync_time']      = empty($u['sync_time']) ? '无' : date('Y-m-d H:i:s', $u['sync_time']);
            $u['exception_time'] = empty($u['exception_time']) ? '无' : date('Y-m-d H:i:s', $u['exception_time']);
            $u['heart_time']     = empty($u['heart_time']) ? '无' : date('Y-m-d H:i:s', $u['heart_time']);
        }
        $this->assign('umbrellas', $umbrellas);
        return $this->fetch();
    }

    public function settingStrategy(){
        extract(input());
        if (!$this->auth->globalSearch && !$this->auth->checkStationIdIsAuthorized($station_id)) {
            echo 'unauthorized station';
            exit;
        }
        if ($_POST) {
            $res = $this->admin->syncStrategy($this->station, $station_id, $strategy_id);
            if ($res) {
                Api::output([], 0, '更新成功');
            } else {
                Api::output([], 1, '更新失败');
            }
        }

        $settings               = model('stationSettings')->allSettings();
        $station_setting_id     = $this->station->getStationSettings($station_id);
        $this->assign([
            'settings'           => $settings,
            'station_setting_id' => $station_setting_id,
        ]);
        return $this->fetch();
    }

}