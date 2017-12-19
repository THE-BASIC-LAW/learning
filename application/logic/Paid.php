<?php namespace app\logic;

use app\third\swApi;
use think\Log;

class Paid
{

    public function handle($orderId, $paid)
    {
        Log::info('orderid: ' . $orderId . ' paid: ' . $paid);
        $order = model('Tradelog')->find($orderId);
        $status = $order->status;
        $uid = $order->uid;

        switch ($status) {

            case ORDER_STATUS_WAIT_PAY:
                $price = round($order->price, 2);
                $needPayMore = 0;

                // 非芝麻订单
                if ($order->platform != PLATFORM_ZHIMA) {

                    // 订单中有部分或者全部是押金支付的情况, refunded指的是已退款的额度
                    if ($paid < $price) {
                        // 验证支付的金额和用户余额是否大于订单需要支付的押金
                        $user = model('User')->find($uid);
                        if (round($user->usablemoney + $paid, 2) < $price) {
                            Log::error('usable money not enough, please check. usable money: ' . $user->usablemoeny . ', paid: ' . $paid);
                            $order->status = ORDER_STATUS_PAID_NOT_ENOUGH_EXCEPTION;
                            $order->lastupdate = time();
                            $order->save();
                            return false;
                        }
                        Log::info('need pay with usable money: ' . $needPayMore);
                        //需要账户余额支付的钱
                        $needPayMore = $price - $paid;

                    }
                    // 更新用户账户 余额和押金 (第二参数是使用余额付款的钱, 第三个参数是增加的押金的钱)
                    $ret = model('User')->payMore($uid, $needPayMore, $order->price);
                    if (!$ret) {
                        Log::error('user need pay with usable money fail: ' . $orderId);
                        // @todo 更新用户余额
                        return false;
                    }

                    // 更新钱包明细
                    model('WalletStatement')->insert([
                        'uid' => $uid,
                        'related_id' => $orderId,
                        'type' => WALLET_TYPE_PREPAID,
                        'amount' => $paid,
                        'time' => date('Y-m-d H:i:s'),
                    ]);
                }

                // 更新订单
                $order->status = ORDER_STATUS_PAID;
                $order->refunded = $needPayMore;
                $order->lastupdate = time();
                $order->paid = $paid;
                $order->save();
                Log::info('update order id: ' . $orderId . ' status to paid');

                // 出伞命令
                swApi::borrowUmbrella($order->borrow_station, $orderId);
                break;

            default:
                Log::error('paid not handle. order id: ' . $orderId . ' paid: ' . $paid);
        }

    }


    public function wechat($orderId, $paid)
    {

    }

    public function alipay($orderId, $paid)
    {

    }

    public function zhima($orderId)
    {

    }
}