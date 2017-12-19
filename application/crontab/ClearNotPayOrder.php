<?php namespace app\crontab;

use think\Log;


/**
 * 处理过期未支付的订单
 *
 * Class ClearNotPayOrder
 *
 * @package app\crontab
 */
class ClearNotPayOrder implements CrontabInterface
{
    public function exec()
    {
        $rst = model('Tradelog')->deleteNotPaidOrder();
        if (!$rst) {
            Log::info('delete nothing');
        } else {
            Log::info('delete count: ' . $rst);
        }
    }
}