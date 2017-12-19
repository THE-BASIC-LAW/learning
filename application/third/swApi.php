<?php namespace app\third;

use think\Log;

class swApi {

    private static $server_ip;
    private static $port;
    private static $_receive = '';

    const SWOOLE_SLOT_INTERVAL = UMBRELLA_SLOT_INTERVAL; // 用于开关锁命令中, 因为终端是从4号开始的, 服务器是从1号开始的

    private static function _client($host, $port, $data, $recv = false) {
        $client = new \swoole_client( SWOOLE_SOCK_TCP );
        //连接到服务器
        if ( !$client->connect( $host, $port, 0.5 ) ) {
            Log::error("swoole client connect server failed: " .self::$server_ip . ":" . self::$port);
            return false;
        }
        //向服务器发送数据
        if ( !$client->send( $data . "\r\n" )) {
            Log::error("swoole client send data failed: " . $data);
            return false;
        }
        if ($recv) {
            self::$_receive = $client->recv();
            Log::info('swoole client recv: ' . self::$_receive);
        }
        $client->close();
    }

    private static function _init()
    {
        self::$server_ip = env('swoole.server_ip', '127.0.0.1');
        self::$port      = env('swoole.port', '8888');
    }

    private static function _send($data) {
        // 初始化
        self::_init();
        // 再发送数据
        Log::info('swoole client send data ' .print_r($data, 1));
        self::_client(self::$server_ip, self::$port, json_encode($data, true));
    }

    public static function isStationOnline($stationId) {
        if (empty($stationId)) return false;
        $data = 'isonline ' . $stationId;
        // 初始化
        self::_init();
        self::_client(self::$server_ip, self::$port, $data, true);
        // 在线返回1 不在线返回0
        $result = (self::$_receive == 1);
        Log::info("check station status, stationid: $stationId , online: " . ($result + 0));
        return $result;
    }

    public static function sendStatus($statusData)
    {
        self::_send($statusData);
    }

    public static function borrowUmbrella($stationId, $orderId){
        $data['stationid'] = $stationId;
        $content = [
            'EVENT_CODE' => 1,
            'ORDERID' => $orderId,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }

    public static function query($params){
        if(isset($params['all']) && $params['all']){
            for($i = $params['slot_num']; $i; $i--){
                $data['stationid'] = $params['station_id'];
                $content = [
                    'EVENT_CODE' => 52,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::_send($data);
                sleep(5);
            }
        }else{
            $data['stationid'] = $params['station_id'];
            $content = [
                'EVENT_CODE' => 52,
                'SLOT' => $params['slot_num'] + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::_send($data);
        }
    }

    public static function slotLock($params){
        if(isset($params['all']) && $params['all']){
            for($i = $params['slot_num']; $i; $i--){
                $data['stationid'] = $params['station_id'];
                $content = [
                    'EVENT_CODE' => 53,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::_send($data);
                sleep(5);
            }
        }else{
            $data['stationid'] = $params['station_id'];
            $content = [
                'EVENT_CODE' => 53,
                'SLOT' => $params['slot_num'] + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::_send($data);
        }
    }

    public static function slotUnlock($params){
        if(isset($params['all']) && $params['all']){
            for($i = $params['slot_num']; $i; $i--){
                $data['stationid'] = $params['station_id'];
                $content = [
                    'EVENT_CODE' => 54,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::_send($data);
                sleep(5);
            }
        }else{
            $data['stationid'] = $params['station_id'];
            $content = [
                'EVENT_CODE' => 54,
                'SLOT' => $params['slot_num'] + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::_send($data);
        }
    }

    // 人工借出
    public static function lend($params){
        if(isset($params['all']) && $params['all']){
            for($i = $params['slot_num']; $i; $i--){
                $data['stationid'] = $params['station_id'];
                $content = [
                    'EVENT_CODE' => 55,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::_send($data);
                sleep(7);
            }
        }else{
            $data['stationid'] = $params['station_id'];
            $content = [
                'EVENT_CODE' => 55,
                'SLOT' => $params['slot_num'] + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::_send($data);
        }
    }

    public static function reboot($params){
        $data['stationid'] = $params['station_id'];
        $content = [
            'EVENT_CODE' => 56,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }

    public static function upgrade($params){
        $data['stationid'] = $params['station_id'];
        $content = [
            'EVENT_CODE' => 57,
            'FILE_NAME' => $params['file_name'],
            'FILE_SIZE' => $params['file_size']
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }

    public static function syncUmbrella($params){
        $data['stationid'] = $params['station_id'];
        $content = [
            'EVENT_CODE' => 58,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }

    public static function moduleNum($params){
        $data['stationid'] = $params['station_id'];
        $content = [
            'EVENT_CODE' => 59,
            'MODULE' => $params['module_num'],
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }

    public static function initSet($params){
        $data['stationid'] = $params['station_id'];
        $content = [
            'EVENT_CODE' => 60,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }


    public static function moduleOpen($station_id, $moduleType){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 61,
            'MODULE' => $moduleType,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }


    public static function moduleClose($station_id, $moduleType){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 62,
            'MODULE' => $moduleType,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::_send($data);
    }

    // 单元模组启动
    public static function elementModuleOpen($params){
        self::moduleOpen($params['station_id'], 1);
    }

    // 单元模组休眠
    public static function elementModuleClose($params){
        self::moduleClose($params['station_id'], 1);
    }

    // 语音功能启动
    public static function voiceModuleOpen($params){
        self::moduleOpen($params['station_id'], 2);
    }

    // 语音功能休眠
    public static function voiceModuleClose($params){
        self::moduleClose($params['station_id'], 2);
    }


}