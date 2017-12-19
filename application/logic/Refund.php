<?php namespace app\logic;


use app\third\alipay\AlipayAPI;
use app\third\wxServer;
use think\Log;

class Refund
{
    /**
     * 退款
     *
     * @param $uid
     * @param $refund
     * @return array|bool
     * uid 和 refund 不符合要求时，返回false
     * 实际退款金额由返回值refunded确定。detail为退款关联的订单号
     */
    public function exec($uid, $refund)
    {
        // 总共需要退款金额
        $totalRefund = $refund;
        Log::notice("refund request, uid: $uid , total need refund money: $refund");
        // 四舍五入，避免太长精度
        $refund = round($refund, 2);
        if (empty($uid) || empty($refund) || !is_numeric($refund)) {
            Log::notice('refund not permission');
            return false;
        }
        // 获取可供退款的订单
        $orders = model('Tradelog')->getOrdersForRefund($uid);

        if (empty($orders)) {
            Log::alert('no order could be refund');
            return ['refunded' => 0, 'detail' => []];
        }

        foreach ($orders as $order) {
            $orderId = $order['orderid'];
            $paid = $order['paid'];

            /**
             * tradelog表字段说明
             * message  里面的refund_fee指的是单个订单已退款的（只用在后台退款，后台退款时会调整为该值）
             * usefee   指的是已向用户收取的费用（后台手动退款时会调整该值）
             * refunded 指的是已退款的金额（该字段用在提现业务中）
             * price    指的是用户支付的押金（雨伞押金）
             * paid     指用户在线支付的金额（芝麻信用/账户内支付为0，全款在线支付为30元）
             */

            // 需优先选择refunded最小的, 即已退过最小的, 为了让所有refunded具有可比性,
            // 所以退款时判断还剩多少可退, 应该用 order['paid'] - order['refunded']

            // 检查订单是否已经全额退款，如果是就更新此订单refundno为已全额退款
            $refundable = round($order['paid'] - $order['refunded'], 2);
            if ($refundable <= 0) {
                db('tradelog')->update([
                    'orderid'    => $orderId,
                    'refundno'   => ORDER_ALL_REFUNDED,
                    'lastupdate' => time(),
                ]);
                Log::notice('update order status to all refunded, orderid: ' . $orderId);
                continue;
            }

            // 对比请求的退款金额与此订单可退款余额，取二者最小值为退款金额
            $refundFee = $refund > $refundable ? $refundable : $refund;
            $refundFee = round($refundFee, 2);

            $refundResult = false;
            $refundDetail = [];

            switch ($order['platform']) {

                # 微信退款
                case PLATFORM_WX:
                    $payment = wxServer::instance()->payment;
                    $result = $payment->refund(
                        $orderId,
                        $this->_getRefundNo($orderId),
                        $order['price']*100,
                        $refundFee*100,
                        $this->_getRefundOperator()
                    );
                    Log::notice(print_r($result->toArray(), 1));
                    if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS') {
                        $refundResult = true;
                        Log::notice('wxpay refund success');
                    } elseif ($result->err_code == 'NOTENOUGH' || $result->err_code == 'SYSTEMERROR') {
                        // 以下策略保证尽可能的少分单退款
                        // 若微信支付账户 未结算金额不足,则暂停此次退款,等到账户余额充足再自动退款
                        // 若微信返回系统错误, 则等待下一轮退款再重试
                        Log::error('try again next time, err_code: ' .$result->err_code);
                        break 2;
                    } elseif ($result->err_code = 'REFUND_FEE_MISMATCH') {
                        // 若出现订单金额不一致的问题, 则代表前一次提交的一次退款失败了, 然后紧接着分单尝试退了部分
                        // 等到下一轮尝试退款时,这个订单该退的金额和之前不一致了(别的单退了部分)
                        // 则这次采用同样的退款编号但金额不同的退款会失败, 需要将退款编号更新一下再次尝试退款
                        Log::error('REFUND_FEE_MISMATCH, increment refundno, and try again next time');
                        db('tradelog')->update([
                            'orderid'    => $orderId,
                            'refundno'   => $order['refundno'] + 1,
                            'lastupdate' => time(),
                        ]);
                        break 2;
                    } else {
                        $refundResult = false;
                    }
                    break;

                # 支付宝退款
                case PLATFORM_ALIPAY:
                    $params = [
                        'out_trade_no'   => $orderId,
                        'out_request_no' => $this->_getRefundNo($orderId),
                        'refund_amount'  => $refundFee,
                        'operator_id'    => $this->_getRefundOperator(),
                        'refund_reason'  => DEFAULT_TITLE . '押金退款',
                    ];
                    AlipayAPI::initialize();
                    $result = AlipayAPI::refund($params);
                    if ($result->code == 10000) {
                        $refundResult = true;
                        Log::notice('alipay refund success');
                    } else {
                        $refundResult = false;
                    }
                    break;

                default:
                    // 其他平台暂不支持
                    continue 2;
            }

            // 成功请求退款
            if ($refundResult) {
                Log::info('refund success, orderid: ' . $orderId . ', refund: ' . $refundFee);
                // 检查退款数与此订单在线支付金额是否一致
                // 如果一致，refundno 变为 已全额退款
                // 如果不一致，refundno 加1，说明退过1次款
                $order['refunded'] += $refundFee;
                $refundno          = $order['paid'] == $order['refunded'] ? ORDER_ALL_REFUNDED : $order['refundno'] + 1;
                $refundDetail[] = [$orderId, $refundFee];

                $ret = db('tradelog')->update([
                    'orderid'    => $orderId,
                    'refundno'   => $refundno,
                    'refunded'   => $order['refunded'],
                    'lastupdate' => time(),
                ]);
                if (!$ret) {
                    Log::error('update order refund no fail');
                }
            } else {
                Log::error("refund fail, order id: $orderId , refund : $refundFee , result: " . print_r($result, 1));
                Log::error('continue next order');
                continue;
            }

            // 检查还有多少未退款
            $refund -= $refundFee;
            $refund = round($refund, 2);
            Log::notice("left: $refund");
            if ($refund <= 0) {
                // 退款完成
                break;
            }
        }

        if ($refund > 0) {
            Log::error('refund no complete, and left: ' . $refund);
        } else {
            Log::info('refund all success');
        }

        $refund = $totalRefund - $refund;
        $refund = round($refund, 2);

        $ret = true;
        if ($refund > 0) {
            $ret = model('User')->refund($uid, $refund);
        }
        if($ret) {
            Log::info('update user account money success');
        } else {
            Log::error('update user account money fail');
        }
        return ['refunded' => $refund, 'detail' => $refundDetail];

    }

    /**
     * 获取退款订单号
     * @param $orderId
     * @return string
     */
    private function _getRefundNo($orderId)
    {
        return $orderId.'-REFUND-'.date('YmdHis');
    }

    private function _getRefundOperator()
    {
        return 'REFUND-ROBOT';
    }
}