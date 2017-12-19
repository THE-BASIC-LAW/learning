<?php
/**
 * Created by PhpStorm.
 * User: dlq
 * Date: 17-11-10
 * Time: 下午2:10
 */

namespace app\controller;


use app\common\controller\Base;
use think\Request;
use think\Log;

class Sync extends Base
{
    // 相关模型
    protected $shop;

    protected $station;

    protected $shop_station;

    protected $tradelog;

    protected $umbrella;

    protected $logName = 'sync';

    /**
     * 构造函数
     * @access public
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->shop             = model('Shop');
        $this->station          = model('Station');
        $this->shop_station     = model('ShopStation');
        $this->tradelog         = model('Tradelog');
        $this->umbrella         = model('Umbrella');
    }

    // 请求处理分发
    public function dispatch(){
        // 检查请求来源
        if (input('server.SERVER_ADDR') != SERVERIP) {
            return 'Illegal Access';
        }

        // 获取请求数据
        $input = file_get_contents("php://input");
        Log::info('get post data : ' . $input);
        $post = json_decode($input, 1);

        // 非登录请求进行预处理
        if($post['data']['ACT'] != 'login'){
            $handle_result = $this->handle($post['data']);
            if(isset($handle_result['errcode'])){
                return json($handle_result);
            }
            $post['data']['info'] = $handle_result;
        }

        // 调用请求中相应的处理方法
        $method = $this->convertUnderline($post['data']['ACT']);
        if(!method_exists($this, $method)){
            return json(make_error_data(ERR_PARAMS_INVALID, 'invalid parameter 1'));
        }
        return json($this->$method($post['data']));
    }

    // 函数名称处理
    private function convertUnderline ($str){
        $str = ucwords(str_replace('_', ' ', $str));
        $str = str_replace(' ','',lcfirst($str));
        return $str;
    }

    // 非登录请求的预处理
    private function handle($data){
        // station_id以data中的STATIONID为准
        $station_id = $data['STATIONID'];
        $station = $this->station->get($station_id);
        if(!$station) {
            Log::notice('station not exist');
            return make_error_data(ERR_STATION_NEED_LOGIN, 'station is gone, need login');
        }

        $shop_station = $this->shop_station->where('station_id', $station_id)->find();
        $shop = $this->shop->get($shop_station['shopid']);
        $terminal_time = null;
        isset($data['TIME']) && $terminal_time = $data['TIME'];
        return ['station_id' => $station_id, 'shop' => $shop, 'station' => $station, 'shop_station' => $shop_station, 'terminal_time' => $terminal_time];
    }

    # 设备登录
    private function login($data){
        Log::info('login handle');
        $mac = $data['MAC'];
        if (empty($mac)) {
            Log::info('mac empty, mac: ' . $mac);
            return make_error_data(ERR_NORMAL, 'mac not exit', 'login');
        }

        // 进行登录处理并且返回处理结果
        return $this->station->login($mac);
    }

    # 借出确认
    private function rentConfirm($data){
        return $this->tradelog->rentConfirm($data);
    }

    # 归还确认
    private function returnBack($data){
        extract($data['info']);
        // 归还分为订单归还和新伞归还
        Log::info('umbrellas return back');
        $umbrella_id = $data['ID'];
        $slot = $data['SLOT'] - UMBRELLA_SLOT_INTERVAL;
        $terminalTime = $data['TIME'];

        // 查umbrella表
        $umbrella = $this->umbrella->get($umbrella_id);
        if (!$umbrella) {
            // 新伞,执行入库操作即可
            $umbrella->newUmbrella($umbrella_id, $station_id, $slot);
            Log::info("get a new umbrella, umbrella_id: $umbrella_id , station_id: $station_id , slot: $slot");

            // 更新站点库存 @todo 待优化
            $station->updateInventory();
            Log::info('update station umbrellas numbers, station id: ' . $station_id . ' , usable:' . $_usable . ' empty: ' . $_empty);
        } else {
            // 订单归还
            if (time() - $terminal_time > 30) {
                Log::notice("this station maybe power down, stationid: $station_id , umbrella: $umbrella_id , terminal time: $terminal_time");
            }
            model('ReturnUmbrella', 'logic')
                ->exec($umbrella_id, $station_id, $slot, $terminal_time ? : time())
                ->updateStationStock();
        }
        return make_error_data(ERR_NORMAL, 'return umbrella back success', 'return_back', $umbrella_id);
    }

    # 同步配置
    private function syncSetting($data){
        Log::info('handle sync setting');
        return $this->station->syncSetting($data);
    }

    # 同步雨伞
    private function syncUmbrella($data){
        Log::info('handle sync umbrella');
        return $this->station->syncUmbrella($data);
    }

    # 心跳包
    private function heartbeat($data){
        Log::info('handle heartbeat, station_id: ' . $data["STATIONID"]);
        return $this->station->heartbeat($data);
    }

    # 升级请求
    private function upgradeRequestFile($data){
        Log::info('handle upgrade request file, station_id: ' . $data["STATIONID"]);
        return $this->station->upgradeRequestFile($data);
    }

    # 升级确认(步骤1)
    private function upgradeConfirm($data){
        Log::info('handle upgrade confirm');
        $status = $data['STATUS'];
        switch ($status) {
            // 目前就一个状态0
            case '0':
            default:
                $reply = make_error_data(ERR_NORMAL, 'success', 'upgrade_confirm');
        }
        return $reply;
    }

    # 升级请求(步骤2~n)
    private function upgradeRequest($data){
        Log::info('handle upgrade request');
        $station_id = $data['STATIONID'];
        $file_name = $data['FILE_NAME'];
        // 下面2个参数均为16进制的
        $index = $data['INDEX'];
        $byte_number  = $data['BYTE_NUMBER'];
        if (!$file_name) {
            $reply = [
                'ACK'     => 'upgrade_request',
                'ERRMSG'  => 'upgrade filename not existed',
                'ERRCODE' => ERR_STATION_UPGRADE_FILENAME_NOT_EXISTED,
            ];
            Log::info('upgrade filename not existed');
            return $reply;
        }
        $len   = hexdec($byte_number);
        $file  = SOFT_FILE_PATH . $file_name;
        $start = hexdec($index);
        if (!$start) {
            Log::info("station id $station_id upgrade file $file_name beginning");
        }
        if (!file_exists($file)) {
            $reply = [
                'ACK'     => 'upgrade_request',
                'ERRMSG'  => 'upgrade filename server file not existed',
                'ERRCODE' => ERR_STATION_UPGRADE_SERVER_FILE_NOT_EXISTED,
            ];
            Log::notice("station id $station_id server file not existed, file path: $file");
            return $reply;
        }
        if (!$len) {
            $reply = [
                'ACK'     => 'upgrade_request',
                'ERRMSG'  => 'upgrade filename byte number not existed',
                'ERRCODE' => ERR_STATION_UPGRADE_BYTE_NUMBER_NOT_EXISTED,
            ];
            Log::info("upgrade filename byte number not existed");
            return $reply;
        }
        $handle      = fopen($file, 'r');
        fseek($handle, $start);
        $file_content = fread($handle, $len);
        fclose($handle);
        // 16进制内容
        $content = bin2hex($file_content);
//        $content = $file_content;
        $reply = [
            'FILE_NAME'   => $file_name,
            'INDEX'       => $index,
            'BYTE_NUMBER' => $byte_number,
            'CONTENT'     => $content,
        ];
        Log::info("station id $station_id upgrade content success, index: $index , byte number: $byte_number , content: $content");
        return $reply;
    }

    # 升级结束确认(最后步骤)
    private function upgradeEnd($data){
        $station_id = $data['STATIONID'];
        Log::info('handle upgrade end');
        Log::info("station id $station_id upgrade file {$data['FILE_NAME']} end");
        return make_error_data(ERR_NORMAL, 'success', 'upgrade_end');
    }

    # 锁住槽位
    private function slotLock($data){
        $station_id = $data['STATIONID'];
        Log::info('handle slot lock');
        Log::info("slot lock info, stationid: $station_id , slot: {$data['SLOT']} result: {$data['STATUS']}");
        return make_error_data(ERR_NORMAL, 'success', 'slot_lock', $data['SLOT']);
    }

    # 解锁槽位
    private function slotUnlock($data){
        $station_id = $data['STATIONID'];
        Log::info('handle slot unlock');
        Log::info("slot unlock info, stationid: $station_id , slot: {$data['SLOT']} , result: {$data['STATUS']}");
        return make_error_data(ERR_NORMAL, 'success', 'slot_unlock', $data['SLOT']);
    }

    # 查询消息
    private function queryConfirm($data){
        $station_id = $data['STATIONID'];
        Log::info('handle query confirm');
        Log::info("slot info, stationid: $station_id , slot: {$data['SLOT']} , umbrella: {$data['ID']}");
        return make_error_data(ERR_NORMAL, 'success', 'query_confirm', $data['SLOT']);
    }

    # 人工借出
    private function popupConfirm($data){
        $station_id = $data['STATIONID'];
        Log::info('handle popup confirm');
        Log::info("popup info, stationid: $station_id , slot: {$data['SLOT']} , status: {$data['STATUS']}");
        return make_error_data(ERR_NORMAL, 'success', 'module_close', $data['SLOT']);
    }

    # 人工重启
    private function reboot($data){
        $station_id = $data['STATIONID'];
        Log::info('handle device reboot');
        Log::info("reboot info, stationid: $station_id , status: {$data['STATUS']} ");
        return make_error_data(ERR_NORMAL, 'success', 'reboot');
    }

    # 模组个数
    private function moduleSet($data){
        $station_id = $data['STATIONID'];
        Log::info('handle module set');
        Log::info("module info, stationid: $station_id , status: {$data['STATUS']}");
        return make_error_data(ERR_NORMAL, 'success', 'Module_Set');
    }

    # 雨伞数量更新
    private function syncCnt($data){
        $station_id = $data['STATIONID'];
        Log::info('handle sync umbrella count');
        Log::info("stationid: $station_id , usable: {$data['USABLE_UMBRELLA']} , empty: {$data['EMPTY_SLOT_COUNT']}");
        $this->station->save($station_id, [
            'empty'  => $data['EMPTY_SLOT_COUNT'],
            'total'  => $data['USABLE_UMBRELLA'] + $data['EMPTY_SLOT_COUNT'],
            'usable' => $data['USABLE_UMBRELLA'],
        ]);
        return make_error_data(ERR_NORMAL, 'success', 'sync_cnt');
    }

    # 初始化机器（清除槽位异常状态）
    private function initSet(){
        Log::info('handle init set');
        return make_error_data(ERR_NORMAL, 'success', 'INIT_SET');
    }

    # 机器相关功能开启
    private function moduleOpen($data){
        $station_id = $data['STATIONID'];
        Log::info('module open');
        if ($data['STATUS'] == 0) {
            Log::info("stationid: $station_id , module : {$data['MODULE']} , open success");
        } else {
            Log::notice("stationid: $station_id , module : {$data['MODULE']} , open fail");
        }
        return make_error_data(ERR_NORMAL, 'success', 'module_open');
    }

    # 机器相关功能关闭
    private function moduleClose($data){
        $station_id = $data['STATIONID'];
        Log::info('module close');
        if ($data['STATUS'] == 0) {
            Log::info("stationid: $station_id , module : {$data['MODULE']} , close success");
        } else {
            Log::notice("stationid: $station_id , module : {$data['MODULE']} , close fail");
        }
        return make_error_data(ERR_NORMAL, 'success', 'module_close');
    }

}