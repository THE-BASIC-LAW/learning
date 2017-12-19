<?php
namespace app\third;

use GuzzleHttp\Client;
use think\Log;

class baiduLbs {

    const COORD_TYPE  = 3;
    const POI_ENABLE  = 1;  // 描点启用
    const POI_DISABLE = 0;  // 描点不启用


    protected static $baseUri = 'http://api.map.baidu.com/';

    protected static $updatePOIUri       = 'geodata/v3/poi/update';
    protected static $searchLocalPOIUri  = 'geosearch/v3/local'; // 本地检索
    protected static $searchNearbyPOIUri = 'geosearch/v3/nearby'; // 周边检索
    protected static $createPOIUri       = 'geodata/v3/poi/create';
    protected static $geoConvertUri      = 'geoconv/v1/'; // 结尾必须带'/'


    protected static $client;
    protected static $ak;
    protected static $geotable_id;

    private static function _init()
    {
        self::$client = new Client(['base_uri' => self::$baseUri]);
        self::$geotable_id = env('map.baidu_geotable_id', '179396');
        self::$ak = env('map.baidu_server_ak', 'G9IOBvwnb7C2SLsCClEDdx6xV1igDjpz');
    }

    public static function updatePOI(array $data) {
        if (!$data || !is_array($data)) return false;

        self::_init();
        // $data中需带有唯一索引key
        $data['geotable_id'] = self::$geotable_id;
        $data['ak']          = self::$ak;

        $response = self::$client->request('POST', self::$updatePOIUri, ['form_params' => $data])->getBody();
        $ret      = json_decode($response, true);
        if($ret['status'] != 0) {
            Log::error('baidu lbs update poi fail, ret:' . print_r($ret, true));
            usleep(50000);
            $i = 1;
            do{
                Log::notice("update POI-----try $i times-----");
                $response = self::$client->request('POST', self::$updatePOIUri, ['form_params' => $data])->getBody();
                $ret = json_decode($response, true);
                if ($ret['status'] == 0) {
                    Log::info("update POI success");
                    break;
                } else {
                    Log::info('baidu lbs update poi fail, ret:' . print_r($ret, true));
                }
                $i++;
                usleep(50000);
            }while($i < 4);
        }
        return $ret;
    }

    // 文档地址 本地检索http://lbsyun.baidu.com/index.php?title=lbscloud/api/geosearch
    public static function searchLocalPOI(array $param) {
        self::_init();

        $data['geotable_id'] = self::$geotable_id;
        $data['ak']          = self::$ak;
        $data['filter']      = "enable:1"; //已经启用


        // q 检索关键字
        // region 检索区域名称
        // page_index 分页索引
        // page_size 分页数量
        foreach ($param as $k => $v) {
            $data[$k] = $v;
        }

        $response = self::$client->request('GET', self::$searchLocalPOIUri, ['query' => $data])->getBody();
        $ret = json_decode($response, true);
        if($ret['status'] != 0) {
            Log::error('baidu lbs search local poi fail, ret:' . print_r($ret, true));
            usleep(50000);
            $i = 1;
            do{
                Log::notice("search local POI-----try $i times-----");
                $response = self::$client->request('GET', self::$searchLocalPOIUri, ['query' => $data])->getBody();
                $ret = json_decode($response, true);
                if ($ret['status'] == 0) {
                    Log::info("search local POI success");
                    break;
                } else {
                    Log::info('baidu lbs search local poi fail, ret:' . print_r($ret, true));
                }
                $i++;
                usleep(50000);
            }while($i < 4);
        }
        return $ret;
    }

    // 文档地址 周边检索http://lbsyun.baidu.com/index.php?title=lbscloud/api/geosearch
    public static function searchNearbyPOI(array $param) {
        self::_init();

        $data['geotable_id'] = self::$geotable_id;
        $data['ak']          = self::$ak;
        $data['filter']      = "enable:1"; //已经启用
        $data['radius']      = 5000; //搜索半径，单位米
        $data['page_index']  = 0;
        $data['page_size']   = 50;


        // q 检索关键字
        // location 检索的中心点
        // page_index 分页索引
        // page_size 分页数量
        foreach ($param as $k => $v) {
            $data[$k] = $v;
        }

        $response = self::$client->request('GET', self::$searchNearbyPOIUri, ['query' => $data])->getBody();
        $ret      = json_decode($response, true);
        if ($ret['status'] != 0) {
            Log::error('baidu lbs search nearby poi fail, ret:' . print_r($ret, true));
            usleep(50000);
            $i = 1;
            do {
                Log::notice("search nearby POI-----try $i times-----");
                $response = self::$client->request('GET', self::$searchNearbyPOIUri, ['query' => $data])->getBody();
                $ret      = json_decode($response, true);
                if ($ret['status'] == 0) {
                    Log::info("search nearby POI success");
                    break;
                } else {
                    Log::info('baidu lbs search nearby poi fail, ret:' . print_r($ret, true));
                }
                $i++;
                usleep(50000);
            } while ($i < 4);
        }
        return $ret;
    }

    public static function searchPOIBy($keyword, $city, $pageIndex, $pageSize) {
        self::_init();
        $data = [
            'q'           => $keyword, // 检索关键字
            'page_index'  => $pageIndex, // 页码
            'page_size'   => $pageSize,
            'region'      => $city, // 城市名
            'geotable_id' => self::$geotable_id,
            'ak'          => self::$ak, // 用户ak
            'filter'      => "enable:1", //已经启用的
        ];

        $api = "http://api.map.baidu.com/geosearch/v3/local";
        // $data中需带有唯一索引key
        $curl = new sCurl( $api, 'GET', $data );
        $ret = json_decode( $curl->sendRequest(), true );
        if($ret['status'] != 0) {
            LOG::ERROR('baidu lbs search poi fail, ret:' . print_r($ret, true));
            $i = 1;
            do{
                LOG::WARN("search POI-----try $i times-----");
                $curl = new sCurl( $api, 'POST', $data );
                $ret = json_decode( $curl->sendRequest(), true );
                if ($ret['status'] == 0) {
                    LOG::INFO("search POI success");
                    break;
                } else {
                    LOG::INFO('baidu lbs search poi fail, ret:' . print_r($ret, true));
                }
                $i++;
            }while($i < 4);
        }
        return $ret;
    }

    public static function searchAllPOI($keyword, $city, $pageIndex, $pageSize) {
        self::_init();
        $data = [
            'q'           => $keyword, // 检索关键字
            'page_index'  => $pageIndex, // 页码
            'page_size'   => $pageSize,
            'region'      => $city, // 城市名
            'geotable_id' => self::$geotable_id,
            'ak'          => self::$ak, // 用户ak
        ];

        $api = "http://api.map.baidu.com/geosearch/v3/local";
        // $data中需带有唯一索引key
        $curl = new sCurl( $api, 'GET', $data );
        $ret = json_decode( $curl->sendRequest(), true );
        if($ret['status'] != 0) {
            LOG::ERROR('baidu lbs search poi fail, ret:' . print_r($ret, true));
            $i = 1;
            do{
                LOG::WARN("search all POI-----try $i times-----");
                $curl = new sCurl( $api, 'POST', $data );
                $ret = json_decode( $curl->sendRequest(), true );
                if ($ret['status'] == 0) {
                    LOG::INFO("search all POI success");
                    break;
                } else {
                    LOG::INFO('baidu lbs search all poi fail, ret:' . print_r($ret, true));
                }
                $i++;
            }while($i < 4);
        }
        return $ret;
    }

    public static function createPOI($name, $latitude, $longitude, $address, $enable = self::POI_ENABLE)
    {
        self::_init();
        Log::info("create poi data: name->$name, latitude->$latitude, longitude->$longitude, address->$address");
        $data = [
            'title'       => $name,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'coord_type'  => self::COORD_TYPE,
            'address'     => $address,
            'enable'      => $enable,
            'geotable_id' => self::$geotable_id,
            'ak'          => self::$ak,
        ];
        Log::info(print_r($data, 1));
        $response = self::$client->request('POST', self::$createPOIUri, ['form_params' => $data])->getBody();
        $ret = json_decode($response, true);
        Log::info('create poi response:'.print_r($ret, 1));
        if($ret['status'] != 0) {
            usleep(50000);
            Log::error('baidu lbs create poi fail, ret:' . print_r($ret, true));
            $i = 1;
            do{
                Log::info("create POI-----try $i times-----");
                $response = self::$client->request('POST', self::$createPOIUri, ['form_params' => $data])->getBody();
                $ret = json_decode($response, true);
                if ($ret['status'] == 0) {
                    Log::info("create POI success");
                    break;
                } else {
                    Log::info('baidu lbs create poi fail, ret:' . print_r($ret, true));
                }
                $i++;
                usleep(50000);
            }while($i < 4);
        }
        return $ret;
    }

    // 坐标转换
    public static function aMapCoordinateConvert($baiduCoordinates)
    {
        $url               = 'http://restapi.amap.com/v3/assistant/coordinate/convert';
        $data['key']       = env('map.gaode_server_key', '261ed9998ca0263337a856303d40d9bf');
        $data['locations'] = $baiduCoordinates;
        $data['coordsys']  = 'baidu';

        $client = new Client();
        $res = $client->request('GET', $url, ['query' => $data])->getBody();
        $res = json_decode($res, true);
        if ($res['status'] != 1) {
            Log::info('change coordinates fail');
            Log::info(print_r($res, 1));
            return false;
        }
        list($newLng, $newLat) = explode(',', $res['locations']);
        return ['lng' => $newLng, 'lat' => $newLat];

    }

    /**
     * @param $location string 'lng,lat'
     * @return mixed
     */
    public static function convertGps($location)
    {
        self::_init();

        $data['ak']          = self::$ak;
        $data['coords']      = $location;

        $response = self::$client->request('GET', self::$geoConvertUri, ['query' => $data])->getBody();
        return json_decode($response, true);
    }
}