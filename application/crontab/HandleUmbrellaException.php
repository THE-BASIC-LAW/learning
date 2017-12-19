<?php namespace app\crontab;

use think\Log;


/**
 * 处理雨伞借出后同步异常
 *
 * 执行频率: 每半小时一次
 *
 * Class HandleUmbrellaException
 *
 * @package app\crontab
 */
class HandleUmbrellaException implements CrontabInterface
{
    public function exec()
    {
        $where = [
            'status' => UMBRELLA_OUTSIDE_SYNC,
            'exception_time' => ['<', time()-3600]
        ];
        $umbrellas = db('umbrella')->where($where)->select();
        if (empty($umbrellas)) {
            Log::info('no exception umbrella');
            return;
        }
        Log::notice('exception umbrella info: ' . print_r($umbrellas, 1));
        // 借出状态包含：借出确认，借出第一次确认
        $borrowStatus = [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST];
        foreach ($umbrellas as $umbrella) {
            if (empty($umbrella['order_id'])) {
                continue;
            }
            $orderInfo = db('tradelog')->find($umbrella['order_id']);
            if (in_array($orderInfo['status'], $borrowStatus)) {
                // 归还时间定义: 有异常时间以异常时间为准, 没有则以服务器时间为准
                if ($umbrella['exception_time']) {
                    $return_time = $umbrella['exception_time'];
                } else {
                    $return_time = time();
                }
                // 如果归还时间小于借出时间
                if ($return_time <= $orderInfo['borrow_time']) {
                    $return_time = $orderInfo['borrow_time'];
                }
                // 归还雨伞
                model('ReturnUmbrella', 'logic')->exec($umbrella['id'], $umbrella['station_id'], $umbrella['slot'], $return_time, true);
            } else {
                Log::info('umbrella is not rent status. order info: ' . print_r($orderInfo, 1));
                // 非借出状态，清楚异常记录
                db('umbrella')->update([
                    'id' => $umbrella['id'],
                    'order_id' => '',
                    'status' => UMBRELLA_INSIDE,
                    'exception' => 0
                ]);
                Log::info('set umbrella: ' . $umbrella['id'] . ' to normal');
            }
        }
    }
}