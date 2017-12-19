<?php namespace app\crontab;

use app\third\alipay\AlipayAPI;
use think\Log;


/**
 * 芝麻订单扣款失败查询（独立出来便于控制请求次数）
 *
 * Class ZhimaPayFailRetryQuery
 *
 * @package app\crontab
 */
class ZhimaPayFailRetryQuery implements CrontabInterface
{
    public function exec()
    {
        $zmOrders = db('trade_zhima')->where('status', ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY)->select();
        foreach ($zmOrders as $zmOrder) {
            $orderId = $zmOrder['orderid'];
            $order = db('tradelog')->find($orderId);
            $returnStation = $order['return_station_name'] ?: DEFAULT_STATION_NAME;
            $params = [
                'order_no'          => $zmOrder['zhima_order'],
                'product_code'      => 'w1010100000000002858',
                'restore_time'      => date('Y-m-d H:i:s', $order['return_time'] ?: time()),
                'pay_amount_type'   => 'RENT',
                'pay_amount'        => $order['usefee'],
                'restore_shop_name' => $returnStation,
            ];
            $resp = AlipayAPI::zhimaOrderRentComplete($params);
            Log::info('complete result: ' . print_r($resp, true));
            if(! empty($resp->code) && $resp->code == 10000) {
                Log::info('zhima order complete success, orderid: ' . $orderId);
                db('trade_zhima')->update([
                    'orderid'              => $orderId,
                    'status'               => ZHIMA_ORDER_QUERY_WAIT, //丢到上面的定时任务去
                    'alipay_fund_order_no' => $resp->alipay_fund_order_no,
                    'update_time'          => time(),
                ]);
            } else if(! empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'UNITRADE_WITHHOLDING_PAY_FAILED') {
                Log::alert('zhima order UNITRADE_WITHHOLDING_PAY_FAILED, orderid: ' . $orderId);
                db('trade_zhima')->update([
                    'orderid'     => $orderId,
                    'status'      => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY,
                    'update_time' => time(),
                ]);
                // 更新用户流水
                $walletStatementId = db('wallet_statement')->where('related_id', $orderId)->value('id');
                db('wallet_statement')->update([
                    'id' => $walletStatementId,
                    'time' => date('Y-m-d H:i:s')
                ]);
            }  else if(! empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'ORDER_GOODS_IS_RESTORED') {
                Log::error('zhima order ORDER_GOODS_IS_RESTORED, orderid: ' . $orderId);
                db('trade_zhima')->update([
                    'orderid'     => $orderId,
                    'status'      => ZHIMA_ORDER_QUERY_WAIT, // 已完成的订单丢到上面的定时任务去
                    'update_time' => time(),
                ]);
            } else {
                Log::error('zhima order complete fail, orderid: ' . $orderId);
            }

        }
    }
}