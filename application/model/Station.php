<?php

namespace app\model;

use think\Model;
use think\Log;

class Station extends Model
{
    // 相关模型
    protected $station_log;

    protected $station_slot_log;

    protected $station_settings;

    protected $station_heartbeat_log;

    const STATION_HEARTBEAT_MISSING_NUMBER = 5; //连续5个心跳包没有判断站点离线



    public function __construct($data = []){
        parent::__construct($data);
        $this->station_log               = model('StationLog');
        $this->station_slot_log          = model('StationSlotLog');
        $this->station_settings          = model('StationSettings');
        $this->station_heartbeat_log     = model('StationHeartbeatLog');
    }

    public function login($mac){
        // 根据mac获取相关站点信息
        $station = $this->where('mac', $mac)->find();

        // 站点不存在则返回错误提示 存在则在station_log表中记录相关登录信息
        if(!$station){
            return make_error_data(ERR_NORMAL, 'mac not exit', 'login');
        } else {
            // 更新站点同步时间
            $station->save(['sync_time' => time()]);
            $station_log_id   = date('Ymd').$station['id'];
            $station_log_info = $this->station_log->get($station_log_id);
            // 若是当天第一次登录则新增一条记录 否则login_count加1
            if(!$station_log_info){
                $data = [
                    'id'          => $station_log_id,
                    'station_id'  => $station['id'],
                    'created_at'  => time(),
                    'login_count' => 1,
                ];
                $this->station_log->save($data);
            } else {
                $this->station_log->updateLoginCount($station_log_id);
            }

            $result = [
                'bindaddress' => 1,
                'stationid'   => $station['id'],
            ];
            Log::info('login success, station_id: ' .$station['id'] . ' mac: ' . $mac);
            return $result;
        }
    }

    public function updateInventory(){
        $usable = $this['usable'] + 1;
        $empty  = $this['empty'] - 1 > 0 ? $this['empty'] - 1 : 0;
        $this->save([
            'sync_time' => time(),
            'usable'    => $usable,
            'empty'     => $empty,
        ]);
    }

    public function syncSetting($data){
        extract($data['info']);
        $soft_ver   = $data['SOFT_VER'];
        $device_ver = $data['DEVICE_VER'];

        // 更新硬件和软件版本号，由于与login命令时间上是相差很近，有时会更新失败。
        $this->get($station_id)->save([
            'soft_ver'   => $soft_ver,
            'sync_time'  => time(),
            'device_ver' => $device_ver,
        ]);
        // 返回站点的配置信息 为空返回默认配置 有值返回设定配置
        $settings = $this->station_settings->getUsingSetting($station['station_setting_id']);

        $reply['TIME']             = time();
        $reply['IP']               = $settings['ip'];
        $reply['DOMAIN']           = $settings['domain'];
        $reply['PORT']             = $settings['port'];
        $reply['HEARTBEAT']        = $settings['heartbeat'];
        $reply['CHECKUPDATEDELAY'] = $settings['checkupdatedelay'];
        isset($settings['soft_ver']) && !empty($settings['soft_ver']) && $reply['SOFT_VER'] = $settings['soft_ver'];
        isset($settings['file_name']) && !empty($settings['file_name']) && $reply['FILE_NAME'] = $settings['file_name'];

        Log::info('sync setting success,  station id: '. $station_id);
        return $reply;
    }

    public function syncUmbrella($data){
        extract($data['info']);
        $last_sync_umbrella_time = $data['LASTTIME']; //最近一次同步雨伞的时间

        // 空槽
        if (isset($data['EMPTY_SLOT_COUNT'])) {
            $content['empty'] = $data['EMPTY_SLOT_COUNT'];
        }

        // 可接伞数量
        if (isset($data['USABLE_UMBRELLA'])) {
            $content['usable'] = $data['USABLE_UMBRELLA'];
        }

        // 槽位数量
        $content['total'] = $content['empty'] + $content['usable'];

        $content['sync_time'] = time();

        Log::info('sync umbrella station_id: ' . $station_id);
        $slotstatus = $station['slotstatus'];
        $slotstatus = substr($slotstatus, 0, $content['total']);
        $need_clean_log_slots = [];
        // 处理雨伞信息
        foreach ($data as $k => $uminfo) {
            if(strpos( $k, 'UM') !== 0) continue;
            $cmp        = '0000000000';
            $umid       = substr($uminfo, 0, 10);
            $slot       = (int)(substr( $k, 2, 2)) - UMBRELLA_SLOT_INTERVAL;
            $status     = hexdec(substr($uminfo, 10)); //16进制转10进制
            $slotstatus = substr_replace($slotstatus, $status, $slot -1 , 1);
            if($umid == $cmp) {
                if ($status == 3) {
                    // 刷新记录
                    $this->station_slot_log->deleteStationSlotLog($station_id, $slot);
                    $this->station_slot_log->save([
                        'slot'                    => $slot,
                        'type'                    => 3,
                        'station_id'              => $station_id,
                        'create_time'             => time(),
                        'last_sync_umbrella_time' => $last_sync_umbrella_time,
                    ]);
                }
                continue;
            }

            //通信中断
            $status != 4 && $need_clean_log_slots[] = $slot;

            $umbrella = model('Umbrella')->get($umid);
            if($umbrella){
                // 判断雨伞当前状态并做出相应处理
                // 雨伞借出状态且order_id存在时,为借出后同步

                // 区分断电和正常网络延时 小于30s为网络延时, 超过30s为断电

                // 断电处理:最后一次同步雨伞后产生的订单0元处理, 之前同步产生的订单归还时间为最后一次同步时间
                // 非断电情况:归还时间为当前时间
                // @todo 订单0元处理还没做

                if($umbrella['status'] == UMBRELLA_OUTSIDE && !empty($umbrella['order_id'])) {
                    // 存在这样一种可能性
                    // 借出雨伞的过程中同步了雨伞
                    // 目前终端状态0,1之间的最长时间大约12s

                    // 12s内认为是借伞过程中同步了雨伞
                    if (time() - $umbrella['sync_time'] < 12) {
                        // 雨伞状态不做更新
                        Log::info("sync umbrella in borrowing umbrella case, don't update this umbrella sync time");
                        Log::info("umbrella info, " . print_r($umbrella, 1));
                    } else {

                        // 服务器时间
                        $serverTime = time();
                        // 雨伞同步固定时间
                        $umbrellaSyncTime = UMBRELLA_SYNC_TIME;
                        // 网络延时时间
                        $networkDelayTime = 20;
                        // 终端与服务器同步时间差
                        $diffTime = 10;

                        // 判断是否断电:距离上次同步雨伞超过1830秒为断电
                        if ($serverTime > $last_sync_umbrella_time + $umbrellaSyncTime + $networkDelayTime + $diffTime) {
                            // 断电:异常时间以上次同步雨伞时间为准
                            $exception_time = $last_sync_umbrella_time ? : $serverTime;
                            Log::info('Power down case !!!');
                        } else {
                            // 其他情况:以服务器当前时间为准
                            $exception_time = $serverTime;
                        }

                        model('Umbrella')->get($umid)->save([
                            'slot'           => $slot,
                            'status'         => UMBRELLA_OUTSIDE_SYNC,
                            'sync_time'      => time(),
                            'station_id'     => $station_id,
                            'exception_time' => $exception_time,
                        ]);
                        $order = model('Tradelog')->get($umbrella['order_id']);
                        Log::info("Exception umbrella outside sync, id: {$umbrella['id']} , orderid: {$umbrella['order_id']} , order status: {$order['status']}");
                    }

                } else {

                    // 区分雨伞是否已经有记录异常时间
                    // 有过异常记录的只更新同步时间
                    if ($umbrella['status'] == UMBRELLA_OUTSIDE_SYNC && $umbrella['exception_time'] && $umbrella['order_id']) {
                        Log::info("An exception umbrella hasn't been handled, umbrella info: " . print_r($umbrella, 1));
                        model('Umbrella')->get($umid)->save([
                            'sync_time' => time()
                        ]);
                    } else {
                        model('Umbrella')->get($umid)->save([
                            'slot'       => $slot,
                            'status'     => UMBRELLA_INSIDE,
                            'order_id'   => '',
                            'sync_time'  => time(),
                            'station_id' => $station_id,
                        ]);
                    }
                }
            } else {
                // 不存在的umid 插入新表
                model('Umbrella')->insert([
                    'id'         => $umid,
                    'slot'       => $slot,
                    'sync_time'  => time(),
                    'station_id' => $station_id,
                ], 1);
                Log::info('new umbrella id, id: ' . $umid . ' from station: ' . $station_id . ' slot: ' . $slot);

            }
        }

        // 清除station_slot_log里面的异常记录
        $this->station_slot_log->deleteStationSlotLog($station_id, $need_clean_log_slots);

        $content['slotstatus'] = $slotstatus;

        $station->get($station_id)->save($content);
        Log::info('umbrellas sync success, station id: '. $station_id);
        return make_error_data(ERR_NORMAL, 'success', 'sync_umbrella');
    }

    public function heartbeat($data){
        // 添加心跳log记录
        extract($data['info']);
        $this->station_heartbeat_log->heartbeat($station_id);

        // 更新站点同步时间
        $this->get($station_id)->save([
            'rssi'      => $data['2G_RSSI'],
            'status'    => $data['STATUS'],
            'voltage'   => $data['VOLTAGE'],
            'isdamage'  => $data['ISDAMAGE'],
            'drivemsg'  => $data['DRIVEMSG'],
            'sync_time' => time(),
        ]);

        // 更新统计表登录次数
        $station_log_id = date('Ymd').$station_id;
        $station_log = $this->station_log->get($station_log_id);
        if (!$station_log) {
            $this->station_log->save(['id' => $station_log_id, 'heartbeat_count' => 1, 'online_time' => 1,'created_at' => time()]);
        } else {
            $this->station_log->updateHeartbeatCount($station_log_id);
        }

        // 检查终端是否需要校时
        // 终端比服务器快或者终端比服务器慢25秒以上时，重新校时
        if ($terminal_time - time() > 0 || time() - $terminal_time >= 25) {
            Log::info('station local time need update, the difference: ' . (time() - $terminal_time));
            return make_error_data(ERR_STATION_NEED_SYNC_LOCAL_TIME, 'station local time need update', 'heartbeat');
        }

        Log::info('station heartbeat updated');
        return make_error_data(ERR_NORMAL, 'success', 'heartbeat');
    }

    public function upgradeRequestFile($data){
        $station   = $data['info']['station'];
        $soft_ver  = $data['SOFT_VER'];
        $file_name = $data['FILE_NAME'];
        // 检查站点配置中soft_ver和file_name和请求的是否一致
        $station_setting = model('StationSettings')->getUsingSetting($station['station_setting_id']);
        if (!$soft_ver || $soft_ver != $station_setting['soft_ver']) {
            $reply = [
                'ACK'     => 'upgrade_request_file',
                'ERRMSG'  => 'upgrade soft version mismatch',
                'ERRCODE' => ERR_STATION_UPGRADE_SOFT_VERSION_MISMATCH,
            ];
            Log::info("upgrade soft version mismatch, local: {$station_setting['soft_ver']} , request: $soft_ver");
            return $reply;
        }
        if (!$file_name || $file_name != $station_setting['file_name']) {
            $reply = [
                'ACK'     => 'upgrade_request_file',
                'ERRMSG'  => 'upgrade filename mismatch',
                'ERRCODE' => ERR_STATION_UPGRADE_FILENAME_MISMATCH,
            ];
            Log::info("upgrade filename mismatch, local: {$station_setting['file_name']} , request: $file_name");
            return $reply;
        }
        if (!file_exists(SOFT_FILE_PATH . $file_name)) {
            $reply = [
                'ACK'     => 'upgrade_request_file',
                'ERRMSG'  => 'upgrade filename server file not existed',
                'ERRCODE' => ERR_STATION_UPGRADE_SERVER_FILE_NOT_EXISTED,
            ];
            Log::info("upgrade filename server file not existed, file path: " . SOFT_FILE_PATH . "$file_name");
            return $reply;
        }
        $reply = [
            'SOFT_VER'  => $soft_ver,
            'FILE_NAME' => $file_name,
            'FILE_SIZE' => dechex(filesize(SOFT_FILE_PATH . $file_name))
        ];
        Log::info("upgrade_request_file success, " . print_r($reply, 1));
        return $reply;
    }
    
    public function getSlotsStatus($id){
        $ret = $this->get($id);
        $slotStatus = str_split($ret['slotstatus']);
        $rst = [];
        for ($i = 0; $i < $ret['total']; $i++) {
            $rst[$i] = $slotStatus[$i];
        }
        return $rst;
    }

    public function searchStation($conditions, $page_size, $access_station = null){
        $city       = '';
        $area       = '';
        $where      = [];
        $orderby    = '';
        $orderBy    = '';
        $keyword    = '';
        $province   = '';
        $station_id = '';
        extract($conditions);

        // 权限条件用 and 连接
        if ($access_station !== null) {
            if (empty($access_station)) return [];
            if($station_id && in_array($station_id, $access_station)){
                $where['id'] = $station_id;
            } else {
                $where['id'] = ['in', $access_station];
            }
        } else {
            $station_id && $where['id'] = $station_id;
        }

        // 网络状态 0断线 1在线
        if (isset($status) && $status != -1) {
            // 获取判断不在线的时间值
            $intervalTime = time() - $this->getNetworkCheckInterval();
            if ($status == 0) {
                $where['sync_time'] = ['<', $intervalTime];
            } else {
                $where['sync_time'] = ['>', $intervalTime];
            }
        }

        // 省市区
        if ($province || $city || $area) {
            //直辖市去掉省份
            if ($province == $city) $province = '';
            $where['address'] = ['like', $province . $city . $area .'%'];
        }

        // 站点名称
        if ($keyword) {
            $where['title'] = ['like', '%' . $keyword . '%'];
        }

        // 机器硬件版本
        if (isset($device_ver) && !empty($device_ver)) {
            $where['device_ver'] = $device_ver;
        }

        // 机器软件版本
        if (isset($soft_ver) && !empty($soft_ver)) {
            $where['soft_ver'] = $soft_ver;
        }

        // 雨伞模组状态
        if(isset($slotstatus) && $slotstatus != -1){
            if ($slotstatus == 0) $where['slotstatus'] = 0;
            if ($slotstatus == 1) $where['slotstatus'] = ['>', '0'];
        }

        // 电池状态
        if(isset($isdamage) && $isdamage != -1){
            if ($isdamage == 0) $where['isdamage'] = 0;
            if ($isdamage == 1) $where['isdamage'] = 1;
            if ($isdamage == 2) $where['isdamage'] = 2;
            if ($isdamage == 3) $where['isdamage'] = 3;
        }

        // 排序
        if ($orderby) {
            if ($orderby == 1) $orderBy = 'heartbeat_rate';
            if ($orderby == 2) $orderBy = 'power_on_time';
        }
        if ($orderBy) {
            if ($order_desc == 1) {
                $orderBy .= ' DESC';
            } else {
                $orderBy .= ' ASC';
            }
        }

        // 借出后同步雨伞 需要关联umbrella表
        // 1. 在umbrella表中获取有同步雨伞的stationId
        // 2. 在station表中查询时只在这些stationId查询
        if (isset($umbrella_outside_sync) && $umbrella_outside_sync == 'on') {
            $station_ids = model('Umbrella')->getAllStationIdsWithUmbrellaSync();
            // 如果为空 说明没有站点异常
            if (empty($station_ids)) return [];
            if ($where['id']) {
                // 如果搜索条件中含有stationId 且 该id不在$station_ids中, 说明没有站点异常
                if (!in_array($where['id'], $station_ids)) return [];
                // 该id在$station_ids中:$where['id'] = $sid;
            } else {
                $where['id'] = $station_ids;
            }
        }

        return $this->where($where)->order($orderBy)->paginate($page_size, false, ['query'=>$conditions]);
    }

    public function checkNetworkOnline($station_id) {
        $station = $this->get($station_id);
        $settings = $this->station_settings->getUsingSetting($station['station_setting_id']);
        if (time() - $station['sync_time'] > $settings['heartbeat'] * self::STATION_HEARTBEAT_MISSING_NUMBER) {
            return false;
        } else {
            return true;
        }
    }

    public function isStationHasumbrellaSync($station_id)
    {
        $rst = model('Umbrella')->where(['station_id' => $station_id, 'status' => UMBRELLA_OUTSIDE_SYNC])->count();
        return $rst['status'] ? true: false;
    }

    public function getStationCity($station_id = 0){
        if ($station_id) {
            $station = self::get($station_id);
            return substr( $station['address'], 0, strpos($station['address'] , '市')+3);
        } else {
            $ret = self::where('address', '<>', '')->column('address');
            foreach ($ret as $value) {
                $city_list[] = substr($value, 0, strpos($value, '市')+3);
            }
            return array_unique($city_list);
        }
    }

    public function getStationSettings($station_id){
        return $this->get($station_id)->value('station_setting_id');
    }

    public function setStationSettings($station_id, $strategy_id){
        return $this->get($station_id)->save(['station_setting_id' => $strategy_id]);
    }

    public function getShopStationId($station_id){
        return $this->get($station_id)->value('shop_station_id');
    }


    public function getNetworkCheckInterval(){
        return self::STATION_HEARTBEAT_MISSING_NUMBER * STATION_HEARTBEAT;
    }

}
