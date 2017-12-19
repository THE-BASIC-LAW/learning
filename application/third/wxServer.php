<?php namespace app\third;

use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Foundation\Application as wxApp;
use Predis;

class wxServer
{

    protected static $ins = null;

    public static function instance()
    {
        // @todo 日志冲突

        if (self::$ins == null) {
            // 创建redis实例
            $redis       = new Predis\Client([
                'scheme' => env('redis.scheme', 'tcp'),
                'host'   => env('redis.host', '127.0.0.1'),
                'port'   => env('redis.port', '6379'),
            ]);
            $cacheDriver = new PredisCache($redis);
            // 初始化easywechat对象
            $options = [
                'debug'   => env('wechat.debug', false),
                'app_id'  => env('wechat.app_id', 'wxcad5914060366ead'),
                'secret'  => env('wechat.secret', '361ae0c57dae29e38a7d31ac82621572'),
                'token'   => env('wechat.token', '7Z9zBrPG8'),
                'aes_key' => env('wechat.aes_key', 'vjpCgBLjDpzp7N790SJ53ulPthYca9NTaJGR96oEa0O'),
                'cache'   => $cacheDriver,
                'oauth'   => [
                    'scopes'   => ['snsapi_userinfo'],
                    'callback' => '/user/oauthWechat',
                ],

                'log' => [
                    'level' => 'info',
                    'file'  => ROOT_PATH . 'logs/wechat/' . date('Y-m-d') . '.log',
                ],

                // 支付
                'payment' => [
                    'merchant_id' => env('wechat.mch_id', '1489614952'),
                    'key'         => env('wechat.pay_key', 'asdfgfghjjkkttjjkvgghfjujkjkhjut'),
                    'cert_path'   => ROOT_PATH . env('wechat.cert_path', 'application/cert/development/wechat/apiclient_cert.pem'),
                    'key_path'    => ROOT_PATH . env('wechat.key_path', 'application/cert/development/wechat/apiclient_key.pem'),
                    'notify_url'  => 'http://' . SERVER_DOMAIN . '/callback/wechatPay',
                ]
            ];

            $app = new wxApp($options);

            return self::$ins = $app;
        }
        return self::$ins;
    }
}