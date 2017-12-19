<?php namespace app\crontab;

use app\third\wxServer;
use think\Log;

/**
 * 更新微信access_token
 *
 * Class UpdateWechatAccessToken
 *
 * @package app\crontab
 */
class UpdateWechatAccessToken implements CrontabInterface
{
    public function exec()
    {
        $accessToken = wxServer::instance()->access_token;
        $str = $accessToken->getToken(true);
        Log::info('new access token: ' . substr($str, 0, 10) . '*****' . substr($str, -10));
    }
}