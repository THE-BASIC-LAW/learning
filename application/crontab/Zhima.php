<?php namespace app\crontab;

use app\third\alipay\AlipayAPI;
use think\Log;


/**
 * 芝麻订单
 *
 * Class Zhima
 *
 * @package app\crontab
 */
class Zhima implements CrontabInterface
{
    public function exec()
    {
        $status = [
            ZHIMA_ORDER_COMPLETE_WAIT, //后台手动退全款，后台手动退部分款，雨伞归还时芝麻信用接口响应失败
            ZHIMA_ORDER_QUERY_WAIT, //芝麻订单回调，雨伞归还
            ZHIMA_ORDER_CANCEL_WAIT //支付超时定时任务，后台手动撤销，状态同步借出失败
        ];
        $zmOrders = db('trade_zhima')->where('status', 'in', $status)->select();

        foreach ($zmOrders as $zmOrder) {
            $orderId = $zmOrder['orderid'];
            Log::log('zhima order id: ' . $orderId . ', status: ' . $zmOrder['status']);

            switch ($zmOrder['status']) {
                # 确认订单完成状态
                case ZHIMA_ORDER_COMPLETE_WAIT:
                    // 调用结算接口, 成功后更新为 查询状态
                    $order = db('tradelog')->find($orderId);
                    // 未结束的订单不能扣款
                    if(in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                        continue 2;
                    }
                    $returnStation = $order['return_station_name'] ? : '街借伞网点';
                    $params = [
                        'order_no'          => $zmOrder['zhima_order'],
                        'product_code'      => 'w1010100000000002858',
                        'restore_time'      => date('Y-m-d H:i:s', $order['return_time']? : time()),
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
                            'status'               => ZHIMA_ORDER_QUERY_WAIT,
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
                    } else if(! empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'ORDER_GOODS_IS_RESTORED') {
                        Log::alert('zhima order ORDER_GOODS_IS_RESTORED, orderid: ' . $orderId);
                        db('trade_zhima')->update([
                            'orderid'     => $orderId,
                            'status'      => ZHIMA_ORDER_QUERY_WAIT,
                            'update_time' => time(),
                        ]);
                    } else {
                        Log::alert('zhima order complete fail, orderid: ' . $orderId);
                    }

                    // 记录用户流水
                    $walletStatementId = db('wallet_statement')->where('related_id', $orderId)->value('id');
                    $amount = db('tradelog')->where('orderid', $orderId)->value('usefee');
                    if($walletStatementId){
                        db('wallet_statement')->update([
                            'id'     => $walletStatementId,
                            'type'   => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                            'amount' => $amount,
                            'time'   => date('Y-m-d H:i:s'),
                        ]);
                        Log::log('wallet statement update success');
                    } else {
                        $uid = db('tradelog')->where('orderid', $orderId)->value('uid');
                        db('wallet_statement')->insert([
                            'uid'        => $uid,
                            'related_id' => $orderId,
                            'type'       => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                            'amount'     => $amount,
                            'time'       => date('Y-m-d H:i:s'),
                        ]);
                        Log::log('wallet statement add success');
                    }
                    break;

                # 确认订单扣款完成状态
                case ZHIMA_ORDER_QUERY_WAIT:
                    // 调用查询接口, 如果扣款成功, 则更新成 结算成功状态, 如果扣款失败, 下个周期继续查询
                    $params = [
                        'out_order_no' => $orderId,
                        'product_code' => 'w1010100000000002858',
                    ];
                    $resp = AlipayAPI::zhimaOrderRentQuery($params);
                    Log::info('query result: ' . print_r($resp, true));
                    if(! empty($resp->code)
                        && $resp->code == 10000
                        && !empty($resp->pay_status)
                        && $resp->pay_status == 'PAY_SUCCESS'
                    ) {
                        Log::info('zhima order query success, orderid: ' . $orderId);
                        db('trade_zhima')->update([
                            'orderid'              => $orderId,
                            'status'               => ZHIMA_ORDER_COMPLETE_SUCCESS,
                            'pay_amount_type'      => $resp->pay_amount_type,
                            'pay_amount'           => $resp->pay_amount,
                            'pay_time'             => $resp->pay_time,
                            'alipay_fund_order_no' => $resp->alipay_fund_order_no,
                            'admit_state'          => $resp->admit_state == 'Y' ? 1 : 0,
                            'openid'               => $resp->user_id,
                            'update_time'          => time(),
                        ]);
                        // 记录用户流水
                        $walletStatementId = db('wallet_statement')->where('related_id', $orderId)->value('id');
                        if($walletStatementId){
                            db('wallet_statement')->update([
                                'id'   => $walletStatementId,
                                'type' => WALLET_TYPE_ZHIMA_PAID,
                                'time' => date('Y-m-d H:i:s'),
                            ]);
                        } else {
                            $uid = db('user')->where('openid', $resp->user_id)->value('id');
                            db('wallet_statement')->insert([
                                'uid'        => $uid,
                                'related_id' => $orderId,
                                'type'       => WALLET_TYPE_ZHIMA_PAID,
                                'amount'     => $resp->pay_amount,
                                'time'       => date('Y-m-d H:i:s'),
                            ]);
                        }

                        // 如果这个订单是用户支付赔偿金的, 即用户没有归还, 只是在支付宝上进行了赔偿, 需更新订单状态
                        if($resp->pay_amount_type == 'DAMAGE') {
                            if(db('tradelog')->update([
                                'orderid'     => $orderId,
                                'status'      => ORDER_STATUS_TIMEOUT_NOT_RETURN,
                                'usefee'      => $resp->pay_amount,
                                'return_time' => time(),
                                'lastupdate'  => time(),
                            ])) {
                                Log::alert('damage, update order success ' . $orderId);
                            } else {
                                Log::error('damage, update order fail ' . $orderId);
                            }
                        }
                        Log::log('zhima order finish success, orderid: ' . $orderId);
                    } else if(! empty($resp->code)
                        && $resp->code == 10000
                        && !empty($resp->pay_status)
                        && $resp->pay_status == 'PAY_FAILED'
                    ) {
                        Log::error('zhima order pay fail, orderid: ' . $orderId);
                        db('trade_zhima')->update([
                            'orderid'     => $orderId,
                            'status'      => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY,
                            'update_time' => time(),
                        ]);
                    } else {
                        Log::error('zhima order finish fail, orderid: ' . $orderId);
                    }
                    break;

                # 确认订单取消状态
                case ZHIMA_ORDER_CANCEL_WAIT:
                    // 调用撤销接口, 需增加管理员手动撤销入口
                    $params = [
                        'order_no' => $zmOrder['zhima_order'],
                        'product_code' => 'w1010100000000002858',
                    ];
                    $resp = AlipayAPI::zhimaOrderRentCancel($params);
                    Log::info('cancel result: ' . print_r($resp, true));
                    if(! empty($resp->code) && $resp->code == 10000) {
                        Log::info('zhima order cancel success, orderid: ' . $orderId);
                        db('trade_zhima')->update([
                            'orderid'     => $orderId,
                            'status'      => ZHIMA_ORDER_CANCEL_SUCCESS,
                            'update_time' => time(),
                        ]);
                    } elseif ($resp->code == 40004 && strtoupper($resp->sub_code) == 'ORDER_IS_CANCEL') {
                        // 已经撤销的订单更新芝麻订单状态为已撤销
                        LOG::INFO("order id: $orderId has been cancel in zhima");
                        db('trade_zhima')->update([
                            'orderid'     => $orderId,
                            'status'      => ZHIMA_ORDER_CANCEL_SUCCESS,
                            'update_time' => time(),
                        ]);
                    } else {
                        LOG::ERROR('zhima order cancel fail, orderid: ' . $orderId);
                    }
                    break;
                default:
                    Log::error('error status');
                    break;
            }

        }
    }
}