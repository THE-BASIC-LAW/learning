<?php namespace app\crontab;

use app\logic\TplMsg;
use think\Log;


/**
 * 已支付的订单,第一次借出确认   超时退款
 *
 * Class OrderConfirm
 *
 * @package app\crontab
 */
class OrderConfirm implements CrontabInterface
{
    public function exec()
    {
        $where      = [
            'status'     => ORDER_STATUS_PAID,
            'lastupdate' => ['<', time() - 60],
        ];
        $paidOrders = db('tradelog')->where($where)->select();

        if (empty($paidOrders)) {
            Log::info('not paid orders');
        } else {

            foreach ($paidOrders as $order) {
                // 雨伞退款到账户余额
                // 更新订单状态, 借还均在原地
                $rst = db('tradelog')->update([
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
                    'status'                 => ORDER_STATUS_TIMEOUT_REFUND,
                ]);
                if ($rst) {
                    Log::info('update order list success, order id: ' . $order['orderid']);
                    if ($order['platform'] != PLATFORM_ZHIMA) {
                        $deposit = $order['price'];
                        if (model('User')->returnBack($order['uid'], $deposit, $deposit)) {
                            Log::info('success to return money to user account');
                        } else {
                            Log::error('fail to return money to user account, uid: ' . $order['uid'] . ' deposit: ' . $deposit);
                            //当前订单更新失败，跳到下一个订单执行
                            break;
                        }
                    } else {
                        db('trade_zhima')->update([
                            'orderid'     => $order['orderid'],
                            'status'      => ZHIMA_ORDER_CANCEL_WAIT,
                            'update_time' => time(),
                        ]);
                        $deposit = 0;
                    }

                    $msg = [
                        'openid'  => $order['openid'],
                        'orderid' => $order['orderid'],
                        'refund'  => $deposit,
                    ];
                    TplMsg::send(TplMsg::MSG_TYPE_REFUND_FEE, $msg);
                } else {
                    Log::error('update order list fail, order id: ' . $order['orderid']);
                }
            }
        }
    }
}