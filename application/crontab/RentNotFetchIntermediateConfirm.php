<?php namespace app\crontab;

use app\logic\TplMsg;
use think\Log;


/**
 * 借出未拿走中间态确认 超时确认未拿走 每分钟执行一次
 *
 * Class RentNotFetchIntermediateConfirm
 *
 * @package app\crontab
 */
class RentNotFetchIntermediateConfirm implements CrontabInterface
{
    public function exec()
    {
        $where = [
            'status' => ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE,
            'lastupdate' => ['<', time()-60],
        ];
        $paidOrders = db('tradelog')->where($where)->select();
        foreach ($paidOrders as $order) {
            // 雨伞退款到账户余额
            // 更新订单状态, 借还均在原地
            $ret = db('tradelog')->update([
                'orderid'                => $order['orderid'],
                'return_station'         => $order['borrow_station'],
                'return_station_name'    => $order['borrow_station_name'],
                'return_shop_id'         => $order['borrow_shop_id'],
                'return_shop_station_id' => $order['borrow_shop_station_id'],
                'return_city'            => $order['borrow_city'],
                'return_device_ver'      => $order['borrow_device_ver'],
                'return_time'            => time(),
                'lastupdate'             => time(),
                'usefee'                 => 0,
                'status'                 => ORDER_STATUS_RENT_NOT_FETCH,
            ]);
            if ($ret) {
                Log::info('update order list success, orderid . ' . $order['orderid']);
                // 退还押金到账户余额
                if ($order['platform'] != PLATFORM_ZHIMA) {
                    if (model('User')->returnBack($order['uid'], $order['price'], $order['price'])) {
                        Log::info('success to return money to user account');
                    } else {
                        Log::error('fail to return money to user account');
                        continue; //当前订单更新失败，跳到下一个订单执行
                    }
                    $deposit = $order['price'];
                } else {
                    // 待撤销, 定时任务撤销该订单
                    db('trade_zhima')->update([
                        'orderid'     => $order['orderid'],
                        'status'      => ZHIMA_ORDER_CANCEL_WAIT,
                        'update_time' => time(),
                    ]);
                    Log::info('update zhima order waiting for cancel, orderid: ' . $order['orderid']);
                    $deposit = 0;
                }
                // 推送消息
                $msg = [
                    'openid' => $order['openid'],
                    'orderid' => $order['orderid'],
                    'refund' => $deposit,
                ];
                TplMsg::send(TplMsg::MSG_TYPE_REFUND_FEE, $msg);
            } else {
                Log::error('update  order list fail, orderid: ' . $order['orderid']);
            }
        }
    }
}