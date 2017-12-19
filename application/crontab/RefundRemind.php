<?php namespace app\crontab;

use app\logic\TplMsg;


/**
 * 提醒用户归还
 *
 * Class RefundRemind
 *
 * @package app\crontab
 */
class RefundRemind implements CrontabInterface
{
    public function exec()
    {
        $where = [
            'status' => ['in', [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST]],
            'remind' => 0
        ];
        $orders = db('tradelog')->column('orderid,openid,borrow_time,price,platform')->where($where)->select();
        if ($orders) {
            $curTime = time();
            foreach ($orders as $order) {
                $remindPrice = $order['price'] * 0.6; // 6层提醒

                $useFee = calc_fee($order['orderid'], $order['borrow_time'], $curTime);

                if ($useFee < $remindPrice) {
                    continue;
                }
                if ($useFee > $order['price']) {
                    $useFee = $order['price'];
                }
                $msg = [
                    'openid'   => $order['openid'],
                    'orderid'  => $order['orderid'],
                    'difftime' => $curTime - $order['borrow_time'],
                    'usefee'   => $useFee,
                ];
                TplMsg::send(TplMsg::MSG_TYPE_RETURN_REMIND, $msg);
                db('tradelog')->update([
                    'orderid'    => $order['orderid'],
                    'remind'     => 1,
                    'lastupdate' => time(),
                ]);
            }
        }
    }
}