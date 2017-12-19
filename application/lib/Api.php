<?php
namespace app\lib;

class Api
{
    /**
     *  通用错误代码
     *  依次是：
     *      -1          接口不不存在
     *      0           请求成功
     *      1           缺少必要参数
     *      5           状态过期
     *      404         未知错误
     *      9999        请求失败
     *
     */
    const SUCCESS = 0;
    const NO_MUST_PARAM = 1;
    const API_NOT_EXISTS = -1;
    const ERROR_UNKNOWN = 404;
    const SESSION_EXPIRED = 5;
    const ERROR = 9999;


    const OPERATION_FAIL = 2;
    const CODE_INVALID = 3;
    const ENCRYPTED_DATA_INVALID  = 4;
    const ERROR_QR_CODE = 555;

    public static $msg = [
        self::SUCCESS 					=> '成功',
        self::NO_MUST_PARAM 			=> '缺少必要的参数',
        self::API_NOT_EXISTS 			=> '该api不存在',
        self::OPERATION_FAIL 			=> '操作失败',
    	self::ERROR 					=> '接口调用失败',
        self::ERROR_UNKNOWN 			=> '未知错误',
        self::ERROR_QR_CODE				=> '错误二维码',
        self::SESSION_EXPIRED           => '状态过期', // session过期

	];

    private static $logOn = false;
    private static $logObj = null;

    private static $logStr = [];


    /**
     * 开启log打印
     * @param $logObj string 全局log命名空间
     */
    public static function logOn($logObj = '\think\Log')
    {
        self::$logOn = true;
        self::$logObj = $logObj;
    }

    /**
     * 关闭log打印
     */
    public static function logOff()
    {
        self::$logOn = false;
        self::$logObj = null;
    }

    /**
     * 如果 第三个参数 有设置的化 直接 使用 $msg
     * 如果设置了 $msg 的话 ，返回说明 为 错误码对应的说明
     * 如果没有设置 $msg 或者 $msg['code'] 不存在 则使用 $msg
     *
     * @param array  $data
     * @param int    $code
     * @param string $msg
     */
    public static function output(array $data = [] , $code = self::SUCCESS, $msg = '')
    {
        $str['data'] = $data;
        $str['code'] = $code;
        if(isset(self::$msg[$code])){
            $str['msg'] = self::$msg[$code];
        }
        if($msg){
            $str['msg'] = $msg;
        }
        self::$logStr = $str;
        self::outputJSON($str);
        if (self::$logOn) {
            self::$logObj->info('api response: ' . print_r(self::$logStr, 1));
        }
        exit;
    }

    public static function outputJSON(array $data = []) {
        header('Content-type: application/json');
        echo json_encode($data);
    }

    public static function fail($code = self::ERROR, $m = '')
    {
        self::output([], $code, $m);
    }
}
