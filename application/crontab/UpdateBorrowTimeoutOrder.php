<?php namespace app\crontab;

use think\Db;
use think\Log;


/**
 * 更新超时未归还的订单
 *
 * Class UpdateBorrowTimeoutOrder
 *
 * @package app\crontab
 */
class UpdateBorrowTimeoutOrder implements CrontabInterface
{
    public function exec()
    {
        $orders = db('tradelog')
            ->where('status', 'in', [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])
            ->select();
        Log::info('total count: ' . count($orders));
        $returnTime = time();
        foreach ($orders as $v) {
            if ($v['borrow_time'] == 0) {
                LOG::ERROR("abnormal order: " . print_r($v, 1));
                continue;
            }
            $usefee = calc_fee($v['orderid'], $v['borrow_time'], $returnTime);
            // 订单费用大于订单价格时
            if ($usefee >= $v['price']) {
                // 租金扣完，更新订单状态
                // $user['usefee'] = $user['deposit'] = 0;
                $orderMsg = unserialize($v['message']);
                $orderMsg['refund_fee'] = 0;
                Db::startTrans();
                $tradelogResult = db('tradelog')->update([
                    'orderid' => $v['orderid'],
                    'status' => ORDER_STATUS_TIMEOUT_NOT_RETURN,
                    'usefee' => $v['price'],
                    'message' => serialize($orderMsg),
                    'return_time' => time(),
                    'lastupdate' => time()
                ]);
                // 如果是芝麻信用订单, 则直接更新芝麻信用订单状态, 调用芝麻信用接口结算订单
                if($v['platform'] != PLATFORM_ZHIMA) {
                    $otherResult = model('User')->reduceDeposit($v['uid'], $v['price']);
                } else {
                    Log::log('zhima order');
                    // 记录用户流水
                    $walletResult = db('wallet_statement')->insert([
                        'uid'        => $v['uid'],
                        'related_id' => $v['orderid'],
                        'type'       => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                        'amount'     => $v['price'],
                        'time'       => date('Y-m-d H:i:s'),
                    ]);
                    $otherResult  = db('trade_zhima')->update([
                        'orderid'     => $v['orderid'],
                        'status'      => ZHIMA_ORDER_COMPLETE_WAIT,
                        'update_time' => time(),
                    ]);
                }
                if ($tradelogResult && $otherResult) {
                    Db::commit();
                    Log::info("update orderid: {$v['orderid']} success");
                } else {
                    Db::rollback();
                    Log::alert("update orderid: {$v['orderid']} fail, tradelogResult: $tradelogResult , otherResult: $otherResult");
                }
            }
        }
    }
}