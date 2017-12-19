<?php namespace app\logic;

use app\third\alipay\AlipayAPI;
use app\third\swApi;
use think\Log;


/**
 * 归还雨伞
 * 涉及站点信息更新、用户信息更新、消息推送、订单更新等。（包含芝麻订单更新）
 *
 *
 * 特别注意：
 * 1. 允许雨伞表中有同一个站点同一个槽位的多条记录存在
 * 2. 后台显示的时候以最近一条数据为准即可
 *
 *
 * Class ReturnUmbrella
 *
 * @package app\logic
 */
class ReturnUmbrella
{

    protected $umId;
    protected $stationId;
    protected $slot;
    protected $returnTime;

    public function exec($umId, $stationId, $slot, $returnTime, $isExceptionOrder = false)
    {
        // @todo 整个功能后续重写

        $this->umId       = $umId;
        $this->stationId  = $stationId;
        $this->slot       = $slot;
        $this->returnTime = $returnTime;

        $umInfo      = db('umbrella')->find($umId);
        $orderId     = $umInfo['order_id'];
        $stationInfo = db('station')->find($stationId);

        if ($orderId) {
            $orderInfo = db('tradelog')->find($orderId);
            // 订单不存在
            if (empty($orderInfo)) {
                Log::notice('umbrella id: ' . $umId . ' has not existed order id: ' . $orderId);
                return $this;
            }

            // 订单状态已完成
            // @todo 这个该移除了
            if ($orderInfo['status'] == ORDER_STATUS_RETURN) {
                Log::alert('duplicate return back, umbrella id: ' . $umId . ' order id: ' . $orderId);
                return $this;
            }

            // 幂等判断, 过滤重复并发请求
            if (!model('Tradelog')->idempotent($orderId)) {
                Log::alert('return back repeated request umbrella: ' . $umId . ' order id: ' . $orderId);
                return $this;
            }

            // 借出状态
            // @todo 借出第一次确认是否加入？？？
            if ($orderInfo['status'] == ORDER_STATUS_RENT_CONFIRM) {

                // 订单借出时间按服务器时间算,归还时间按终端算
                // 服务器时间和终端时间不同步,可能会造成借出时间比归还时间早的情况发生
                if ($returnTime < $orderInfo['borrow_time']) {
                    $returnTime = $orderInfo['borrow_time'];
                }

                // 使用时间
                $usedTime = $returnTime - $orderInfo['borrow_time'];
                $mark = $umInfo['mark'];

                // 判断是否是坏的雨伞
                // 5分钟归还记录一次。
                if ($usedTime < 300) {
                    $mark += 1;
                } else {
                    $mark = 0;
                }

                // 测试环境不启用这功能
                if (env('app.env') == 'development') {
                    $mark = 0;
                }

                // 锁伞并告知工作人员
                if ($mark >= 4) {
                    swApi::slotLock(['station_id' => $stationId, 'slot_num' => $slot]);
                    // @todo 记录下来
                }

                // 雨伞收费
                $fee = calc_fee($orderId, $orderInfo['borrow_time'], $returnTime);
                $fee = min($fee, $orderInfo['price']);
                Log::notice('order id: ' . $orderId . ' fee: ' . $fee);

                // 判断是否零收费人员判断
                $isZeroFeeUserOrder = false;
                $zeroFeeUserList = model('CommonSetting')->getZeroFeeUserList();
                if (in_array($orderInfo['openid'], $zeroFeeUserList)) {
                    $fee = 0;
                    $isZeroFeeUserOrder = true;
                    Log::notice('openid: ' . $orderInfo['openid'] . ' zero fee user');
                }

                // 获取站点相关信息名称
                $returnStationInfo = model('Order', 'logic')->getStationInfo($stationId);

                // message需要更新的内容
                $message = $orderInfo['message'] ? unserialize($orderInfo['message']) : [];
                if ($isZeroFeeUserOrder) {
                    $message['zero_fee_user'] = 1;
                }
                if ($orderInfo['platform'] == PLATFORM_ZHIMA) {
                    $message['refund_fee'] = 0;
                } else {
                    $message['refund_fee'] = round($orderInfo['price'] - $fee, 2);
                }
                $message['return_slot'] = $slot;

                // 更新订单
                db('tradelog')->update([
                    'orderid'                => $orderId,
                    'status'                 => $isExceptionOrder ? ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND : ORDER_STATUS_RETURN, //正常流程是3，借出后同步是94
                    'lastupdate'             => time(),
                    'return_station'         => $stationId,
                    'return_time'            => $returnTime,
                    'return_station_name'    => $returnStationInfo['station_name'],
                    'usefee'                 => $fee,
                    'return_shop_id'         => $returnStationInfo['shop_id'],
                    'return_shop_station_id' => $returnStationInfo['shop_station_id'],
                    'return_city'            => $returnStationInfo['city'],
                    'return_device_ver'      => $returnStationInfo['device_ver'],
                    'message'                => serialize($message),
                ]);
                Log::notice('update order id: ' . $orderId . ' to return back');

                // 更新雨伞信息
                db('umbrella')->update([
                    'id'             => $umId,
                    'station_id'     => $stationId,
                    'order_id'       => '',
                    'status'         => UMBRELLA_INSIDE,
                    'sync_time'      => time(),
                    'slot'           => $slot,
                    'exception_time' => 0,
                    'mark'           => $mark,
                ]);
                Log::notice('update umbrella: ' . $umId . ' to inside');

                // 判断是否芝麻订单
                if ($orderInfo['platform'] != PLATFORM_ZHIMA) {
                    model('User')->returnBack($orderInfo['uid'], $orderInfo['price'] - $fee, $orderInfo['price']);
                    Log::notice("update user: {$orderInfo['uid']}, deposit: {$orderInfo['price']}, fee: $fee");

                    // 记录用户流水
                    if ($fee > 0) {
                        db('wallet_statement')->insert([
                            'uid'        => $orderInfo['uid'],
                            'related_id' => $orderId,
                            'type'       => WALLET_TYPE_PAID,
                            'amount'     => $fee,
                            'time'       => date('Y-m-d H:i:s'),
                        ]);
                    }
                } else {

                    // 记录用户流水
                    if ($fee > 0) {
                        db('wallet_statement')->insert([
                            'uid'        => $orderInfo['uid'],
                            'related_id' => $orderId,
                            'type'       => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                            'amount'     => $fee,
                            'time'       => date('Y-m-d H:i:s'),
                        ]);
                    }

                    $zmOrder = db('trade_zhima')->where('orderid', $orderId)->value('zhima_order');
                    $params = [
                        'order_no' => $zmOrder,
                        'product_code'      => 'w1010100000000002858',
                        'restore_time'      => date('Y-m-d H:i:s', $returnTime),
                        'pay_amount_type'   => 'RENT',
                        'pay_amount'        => $fee,
                        'restore_shop_name' => $returnStationInfo['station_name'],
                    ];

                    $resp = AlipayAPI::zhimaOrderRentComplete($params);
                    // 值为空时，表示请求异常了（响应超时异常，状态码非200等）
                    if (empty($resp)) {
                        Log::error('zhima order complete fail, and send to crontab, orderid: ' . $orderId);
                        db('trade_zhima')->update([
                            'orderid' => $orderId,
                            'status' => ZHIMA_ORDER_COMPLETE_WAIT,
                            'update_time' => time()
                        ]);
                    } else {
                        Log::info('zhima complete result: ' . print_r($resp, true));
                        // 芝麻订单只能检查是否订单完成（不能检查是否扣款成功）
                        if (!empty($resp->code) && $resp->code == 10000) {
                            Log::info('zhima order complete success, orderid: ' . $orderId);
                            // 检查是否扣款成功，放到定时任务处理。
                            db('trade_zhima')->update([
                                'orderid'              => $orderId,
                                'status'               => ZHIMA_ORDER_QUERY_WAIT,
                                'alipay_fund_order_no' => $resp->alipay_fund_order_no,
                                'update_time'          => time(),
                            ]);
                            // 订单结束失败
                        } elseif (!empty($resp->code) && $resp->code == 40004 && $resp->sub_code == 'UNITRADE_WITHHOLDING_PAY_FAILED') {
                            Log::error('zhima order UNITRADE_WITHHOLDING_PAY_FAILED, orderid: ' . $orderId);
                            // 放到定时任务处理
                            db('trade_zhima')->update([
                                'orderid'     => $orderId,
                                'status'      => ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY,
                                'update_time' => time(),
                            ]);
                        } else {
                            Log::error('zhima order complete fail, orderid: ' . $orderId);
                            // 放到定时任务处理
                            db('trade_zhima')->update([
                                'orderid'     => $orderId,
                                'status'      => ZHIMA_ORDER_COMPLETE_WAIT,
                                'update_time' => time(),
                            ]);
                        }
                    }
                }

                $msg = [
                    'openid' => $orderInfo['openid'],
                    'order_id' => $orderId,
                    'return_time' => $returnTime,
                    'return_station_name' => $returnStationInfo['station_name'],
                    'used_time' => $usedTime,
                    'price' => $fee . '元'
                ];

                TplMsg::send(TplMsg::MSG_TYPE_RETURN_SUCCESS, $msg);
            } else {
                // 其他异常状态
                Log::notice("umbrella: $umId exception, " . print_r($orderInfo ,1));
            }

        } else {

            Log::alert('order not existed');
        }
        return $this;
    }

    public function updateStationStock()
    {
        db('station')
            ->where(['id' => $this->stationId, 'empty' => ['>', 0]])
            ->inc('usable')
            ->dec('empty')
            ->setField('sync_time', time());
        Log::notice('update station empty usable number');
    }


}