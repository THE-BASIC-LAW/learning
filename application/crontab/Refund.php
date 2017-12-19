<?php namespace app\crontab;

use think\Log;


/**
 * 处理提现申请
 *
 * Class Refund
 *
 * @package app\crontab
 */
class Refund implements CrontabInterface
{
    public function exec()
    {
        Log::log('start to refund');
        $refundLogs = db('refund_log')->where('status', REFUND_STATUS_REQUEST)->select();
        if(empty($refundLogs)) {
            Log::info('empty, finish refund task');
            return;
        }
        foreach($refundLogs as $refundLog) {
            Log::info('refund log id:' . $refundLog['id'] . ', refund:' . $refundLog['refund'] . ', refunded:' . $refundLog['refunded'] . ', user id :' . $refundLog['uid']);
            // 异常判断：已退款，未更新状态
            if(round($refundLog['refund'], 2) == round($refundLog['refunded'], 2)) {
                db('refund_log')->update([
                    'id' => $refundLog['id'],
                    'status'=>REFUND_STATUS_DONE
                ]);
                Log::info('update status, not need refund');
                model('WalletStatement')->updateTypeByRelatedId($refundLog['id'], WALLET_TYPE_WITHDRAW);
                continue;
            }
            // 可能是部分退款，所以需要传申请提现与实际提现金额
            $ret = model('Refund', 'logic')->exec($refundLog['uid'], $refundLog['refund']-$refundLog['refunded']);
            if ($ret['refunded']) {
                $refundedNow = round($ret['refunded']+$refundLog['refunded'], 2);
                $refundTotal = round($refundLog['refund'], 2);
                $status = $refundedNow >= $refundTotal? REFUND_STATUS_DONE : REFUND_STATUS_REQUEST;
                $detail = empty($refundLog['detail']) ? $ret['detail'] : array_merge(json_decode($refundLog['detail'], true), $ret['detail']);
                $detail = json_encode($detail);
                $updateResult = db('refund_log')->update([
                    'id'          => $refundLog['id'],
                    'status'      => $status,
                    'refunded'    => $refundedNow,
                    'detail'      => $detail,
                    'refund_time' => time(),
                ]);
                if ($updateResult) {
                    if ($status == REFUND_STATUS_DONE) {
                        Log::info('refund over, uid: ' . $refundLog['uid']);
                        model('WalletStatement')->updateTypeByRelatedId($refundLog['id'], WALLET_TYPE_WITHDRAW);
                    }
                } else {
                    Log::error('update refund log status failed');
                }
            } else {
                Log::error('refund error, log id:' . $refundLog['id']);
            }
        }
        Log::log('finish to refund');
    }
}