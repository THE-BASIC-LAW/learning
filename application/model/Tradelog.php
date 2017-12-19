<?php

namespace app\model;

use app\logic\TplMsg;
use think\Model;
use think\Log;

class Tradelog extends Model
{
    // 租借成功订单
    public static $borrow_success_status = [
        ORDER_STATUS_RENT_CONFIRM,  // 2 借出
        ORDER_STATUS_RETURN,        // 3 归还
        ORDER_STATUS_TIMEOUT_NOT_RETURN,    // 92 未归还(押金扣完)
        ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,  // 93 借出成功,归还失败
        ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,   // 94 异常归还(借出后同步,系统自动归还)
        ORDER_STATUS_TIMEOUT_CANT_RETURN,   // 98 归还(押金扣完)
        ORDER_STATUS_LOSS, // 100 (用户登记遗失)
    ];

    // 归还成功订单
    public static $return_success_status = [
        ORDER_STATUS_RETURN,        // 3 归还
        ORDER_STATUS_TIMEOUT_CANT_RETURN,   // 98 归还(押金扣完)
    ];

    public function unReturn($uid)
    {
        return $this->where(['uid' => $uid, 'status' => ORDER_STATUS_RENT_CONFIRM])->count();
    }

    public function rentConfirm($data){
        Log::info('umbrella rent confirm');
        // 提取传入参数
        extract($data['info']);
        $slot        = isset($data['SLOT']) ? $data['SLOT'] - UMBRELLA_SLOT_INTERVAL : 0;
        $order_id    = $data['ORDERID'];
        $umbrella_id = isset($data['ID']) ? $data['ID'] : '';
        Log::info('order_id: ' . $order_id);
        $order = $this->get($order_id);
        $uid   = $order['uid'];
        $message = $order['message'] ? unserialize($order['message']) : [];

        // 非成功状态集合
        $STATUS = [
            '3'  => ORDER_STATUS_TIMEOUT_REFUND,
            '6'  => ORDER_STATUS_NO_UMBRELLA,
            '8'  => ORDER_STATUS_MOTOR_ERROR,
            '9'  => ORDER_STATUS_RENT_NOT_FETCH,
            '10' => ORDER_STATUS_POWER_LOW,
            '11' => ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE,
            '30' => ORDER_STATUS_SYNC_TIME_FAIL,
            '31' => ORDER_STATUS_LAST_ORDER_UNFINISHED,
            '32' => ORDER_STATUS_NETWORK_NO_RESPONSE,
        ];

        // status: 0:push后先回复雨伞ID信息， 1：代表确认用户拿走雨伞 2：代表借出失败(用户未拿走等)
        if($data['STATUS'] == 0) {
            // 雨伞出伞失败的话，可能会再次发送这个命令过来
            // 或者网络延时导致多次发送相同命令
            if ($order['status'] == ORDER_STATUS_RENT_CONFIRM_FIRST) {

                $message['slot'] = $slot;
                $order->save([
                    'message'     => serialize($message),
                    'lastupdate'  => time(),
                    'umbrella_id' => $umbrella_id,
                ]);
                Log::info('status 0 updated again');
                return make_error_data(ERR_NORMAL, 'msg received', 'rent_confirm', $order_id);
            }

            // 正常流程: 订单状态已支付
            if ($order['status'] == ORDER_STATUS_PAID) {
                // 第一次借出确认时雨伞ID存在
                $message['slot'] = $slot;

                $order->save([
                    'status'      => ORDER_STATUS_RENT_CONFIRM_FIRST,
                    'message'     => serialize($message),
                    'lastupdate'  => time(),
                    'borrow_time' => time(),
                    'umbrella_id' => $umbrella_id,
                ]);
                Log::info("order_id: $order_id umbrella_id: $umbrella_id slot: $slot , change status to order_status_confirm_first");

                // 通知终端服务器接收成功
                return make_error_data(ERR_NORMAL, 'msg received', 'rent_confirm', $order_id);
            }
            //异常订单状态直接回复
            Log::error("order_id $order_id has an exception status " . print_r($order, 1));
            return make_error_data(ERR_NORMAL, 'msg received', 'rent_confirm', $order_id);
        }elseif($data['STATUS'] == 1) {
            // 由于网络延时 需要判断是否已经确认过
            if ($order['status'] == ORDER_STATUS_RENT_CONFIRM) {
                Log::info('network problem, status 1 before status 0, it is ok');
                return make_error_data(ERR_NORMAL, 'confirm success', 'rent_confirm', $order_id);
            }

            // 确认借出成功(订单状态可能是借出第一次确认或者借出未取走中间态)
            if (in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM_FIRST, ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE])) {
                // 订单更新
                $umbrella = model('Umbrella')->get($umbrella_id);
                // 借出时间服务器为准
                $borrow_time = time();
                // 保存借出槽位信息
                $message['slot'] = $slot;
                // 更新相关order数据
                $order->save([
                    'status'      => ORDER_STATUS_RENT_CONFIRM,
                    'message'     => serialize($message),
                    'lastupdate'  => $borrow_time,
                    'umbrella_id' => $umbrella_id,
                    'borrow_time' => $borrow_time,
                ]);

                // 这里必须保留!!!!
                // 如果该雨伞id处于非在槽位状态且order_id不为空，则将上一单退还
                // 分2种情况, 借出状态的订单, 借出后同步的订单
                // 统一以order_id为准
                // @todo 这里的还伞和定时任务的还伞可能会触发多次还伞推送(用户账户余额不受影响)
                if ($umbrella['order_id']) {
                    // 借出后同步订单
                    if ($umbrella['status'] == UMBRELLA_OUTSIDE_SYNC && $umbrella['exception_time']) {
                        $exception_return_time = $umbrella['exception_time'];
                        // 借出状态订单
                    } elseif ($umbrella['status'] == UMBRELLA_OUTSIDE) {
                        $exception_return_time = time();
                    }
                    model('ReturnUmbrella', 'logic')->exec($umbrella_id, $station_id, $slot, $exception_return_time);
                }

                // 更新站点状态
                $usable = $station['usable'] - 1 > 0 ? $station['usable'] - 1 : 0;
                $empty  = $station['empty'] + 1;
                if ($station->save(['sync_time' => time(), 'usable' => $usable, 'empty' => $empty])) {
                    Log::info('success to update station umbrella numbers');
                } else {
                    Log::notice('failed to update station umbrella numbers');
                }
                // 更新雨伞表信息
                if ($umbrella->handleRent($umbrella_id, $order_id)) {
                    Log::info("success to update umbrella info, umbrella id: $umbrella_id , order_id: $order_id, station_id: $station_id ");
                } else {
                    return 'fail';
                    Log::info("fail to update umbrella info, umbrella id: $umbrella_id , order_id: $order_id, station_id: $station_id ");
                }

                $msg = [
                    'openid'              => $order['openid'],
                    'order_id'            => $order_id,
                    'borrow_time'         => $borrow_time,
                    'borrow_station_name' => $order['borrow_station_name'],
                ];

                if ($order['platform'] == PLATFORM_WEAPP) {
                    $form_ids = model('UserWeapp')->where('id', $uid)->value('form_ids');
                    $form_ids = json_decode($form_ids, 1);
                    while ($form_ids) {
                        $form_info = $form_ids[0];
                        $form_id = $form_info['form_id'];
                        if ($form_info['timestamp'] + 7 * 3600 * 24 < time()) {
                            array_shift($form_ids);
                            continue;
                        }
                        $msg['form_id'] = $form_id;
//                        addMsgToQueue(PLATFORM_WEAPP, TEMPLATE_TYPE_BORROW_UMBRELLA, $msg);
                        if ($form_info['count'] == 1) {
                            array_shift($form_ids);
                        } else {
                            $form_ids[0]['count']--;
                        }
                        $form_ids = json_encode($form_ids, 1);
                        model('UserWeapp')->get($uid)->save(['form_ids' => $form_ids]);
                        break;
                    }
                } else {
                    TplMsg::send(TplMsg::MSG_TYPE_BORROW_SUCCESS, $msg);
                }
                Log::info('order_id: ' . $order_id . ' rent success');
                return make_error_data(ERR_NORMAL, 'confirm success', 'rent_confirm', $order_id);
            }

            //异常订单状态直接回复
            Log::error("order_id $order_id has an exception status " . print_r($order, 1));
            return make_error_data(ERR_NORMAL, 'confirm success', 'rent_confirm', $order_id);
        }elseif(in_array($data['STATUS'], array_keys($STATUS))){
            $status = $STATUS[$data['STATUS']];
            // 中间态确认，只变更订单状态，其他不变
            if ($data['STATUS'] == 11) {
                $order->get($order_id)->save([
                    'status'     => $status,
                    'lastupdate' => time()
                ]);
                return make_error_data(ERR_NORMAL, 'msg received', 'rent_confirm', $order_id);
            }

            // 未借出未成功的, 推送租借失败通知
            $use_fee = 0;
            // message更新refund_fee, 退还押金为租借价格
            $message['refund_fee'] = $order['price'];
            // 更新订单状态, 统一0收费0借用时间
            $ret = $order->get($order_id)->save([
                'status'                 => $status,
                'usefee'                 => $use_fee,
                'message'                => serialize($message),
                'lastupdate'             => time(),
                'return_city'            => $order['borrow_city'],
                'return_time'            => $order['borrow_time'], // 相当于0借出时间
                'umbrella_id'            => $umbrella_id,
                'return_shop_id'         => $order['borrow_shop_id'],
                'return_station'         => $station_id,
                'return_device_ver'      => $order['borrow_device_ver'],
                'return_station_name'    => $order['borrow_station_name'],
                'return_shop_station_id' => $order['borrow_shop_station_id'],
            ]);
            if($ret) {
                Log::info('success to update to order status');
            } else {
                Log::error('fail to update to order status');
                return make_error_data(ERR_SERVER_DB_FAIL, 'order status update failed, db server fail', 'rent_confirm', $order_id);
            }


            // 芝麻订单:撤销订单
            if($order['platform'] == PLATFORM_ZHIMA) {
                // 调用撤销接口撤销该订单
                $zm_order = model('TradeZhima')->get($order_id);
                $params = [
                    'order_no'     => $zm_order['zhima_order'],
                    'product_code' => 'w1010100000000002858',
                ];
                $resp = AlipayAPI::zhimaOrderRentCancel($params);
                Log::info("zhima order: $order_id , cancel result: " . print_r($resp, true));
                if(! empty($resp->code) && $resp->code == 10000) {
                    Log::info('zhima order cancel success, orderid: ' . $order_id);
                    model('TradeZhima')->get($order_id)->save(
                        [
                            'status'      => ZHIMA_ORDER_CANCEL_SUCCESS,
                            'update_time' => time()
                        ]
                    );
                } elseif ($resp->code == 40004 && strtoupper($resp->sub_code) == 'ORDER_IS_CANCEL') {
                    // 已经撤销的订单更新芝麻订单状态为已撤销
                    Log::info("order id: $order_id has been cancel in zhima");
                    model('TradeZhima')->get($order_id)->save(
                        [
                            'status'      => ZHIMA_ORDER_CANCEL_SUCCESS,
                            'update_time' => time()
                        ]
                    );
                } else {
                    model('TradeZhima')->get($order_id)->save(
                        [
                            'status'      => ZHIMA_ORDER_CANCEL_WAIT,
                            'update_time' => time()
                        ]
                    );
                    Log::error('zhima order cancel fail, orderid: ' . $order_id);
                }
            }

            // 非芝麻订单:押金退回账户余额
            if ($order['platform'] != PLATFORM_ZHIMA) {
                if(model('User')->returnBack($uid, $order['price'], $order['price'])) {
                    Log::info('sucess to return money to user account');
                } else {
                    Log::error('fail to return money to user account');
                    return make_error_data(ERR_SERVER_DB_FAIL, 'user account update failed, db server fail', 'rent_confirm', $order_id);
                }
            }
            $msg = [
                'openid'              => $order['openid'],
                'borrow_station_name' => $order['borrow_station_name'],
                'borrow_time'         => $order['borrow_time'],
            ];
            if($order['platform'] == PLATFORM_WEAPP){
                $form_ids = model('UserWeapp')->where('id', $uid)->value('form_ids');
                $form_ids = json_decode($form_ids, 1);
                while($form_ids){
                    $form_info = $form_ids[0];
                    $form_id = $form_info['form_id'];
                    if($form_info['timestamp'] + 7 * 3600 * 24 < time()){
                        array_shift($form_ids);
                        continue;
                    }
                    $msg['form_id'] = $form_id;
//                    addMsgToQueue(PLATFORM_WEAPP, TEMPLATE_TYPE_BORROW_FAIL, $msg);
                    if($form_info['count'] == 1){
                        array_shift($form_ids);
                    } else {
                        $form_ids[0]['count']--;
                    }
                    $form_ids = json_encode($form_ids, 1);
                    model('UserWeapp')->get($uid)->save(['form_ids' => $form_ids]);
                    break;
                }
            } else {
                TplMsg::send(TplMsg::MSG_TYPE_BORROW_FAIL, $msg);
            }
            return make_error_data(ERR_NORMAL, 'exception handle success', 'rent_confirm', $order_id);
        }else{
            return make_error_data(ERR_PARAMS_INVALID, 'invalid status');
        }
    }

    /*
    订单更新幂等性检查, 保证多次请求的结果和一次请求的结果是一致的
    即短时间内并发多次更新, 只能有一次更新是有效的, 防止多次更新造成的一系列错误问题
    解决由于前端多次触发或由于网络重试导致的多次更新问题
    通过lastupdate的更新锁来实现, 3s内并发的请求均视为同一个请求
    可用于支付回调,借出确认,归还等等订单更新的场景
    返回是否合法, 即可是否可继续往下执行
    */
    public function idempotent($order_id)
    {
        return $this->get($order_id)->save(['lastupdate' => time()],['lastupdate' => ['<', time() - 3]]);
    }


    /**
     * 检查订单是否可以被遗失
     * @param $orderId
     * @param $uid
     * @return bool|object
     */
    public function canBeLostHandle($orderId, $uid)
    {
        // 应该避免借出第一次确认与借出确认这段时间内用户提交遗失请求
        // 简单一点的方法就是禁止遗失最后更新时间2分钟之内的订单
        $order = $this->get($orderId);
        if (!$order
            ||$order['uid'] != $uid
            || !in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])
            || $order['price'] <= 0
            || time() - $order['lastupdate'] < 120
        ) {
            return false;
        }
        return $order;
    }

    /**
     * 遗失订单操作
     * @param $orderId
     * @return bool
     */
    public function lostHandle($orderId)
    {
        return $this->save([
            'status' => ORDER_STATUS_LOSS,
            'lastupdate' => time(),
            'return_time' => time(),
            'usefee' => $orderId
        ], ['orderid' => $orderId, 'status' => ORDER_STATUS_RENT_CONFIRM]);
    }

    /**
     * 获取用户单条订单数据
     * @param string $orderId
     * @return array
     */
    public function getOrderDataForApi($orderId)
    {
        // 订单分类 1为使用中，2为已完成，3为已关闭
        $using = [
            ORDER_STATUS_RENT_CONFIRM,
            //ORDER_STATUS_PAID,
            //ORDER_STATUS_RENT_CONFIRM_FIRST 前端不展示未拿走的订单，所以支付完成和第一次确认不展示到前端
        ];
        $completed = [
            ORDER_STATUS_RETURN,
            ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
            ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,
            ORDER_STATUS_TIMEOUT_CANT_RETURN
        ];
        $closed = [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS];

        $order = $this->get($orderId);

        $return_time = $order['return_time'] ? date('Y-m-d H:i:s', $order['return_time']) : null;

        //租借时长
        $time2 = empty($order['return_time']) ? time() : $order['return_time'];
        $timediff = $time2 - $order['borrow_time'];

        //获取收费策略
        $fee_strategy = db('tradeinfo')->where('orderid', $order['orderid'])->find()['fee_strategy'];
        $usefee = $order['usefee'];

        $status = in_array($order['status'], $closed) ? 3 : (in_array($order['status'], $completed) ? 2 : 1);
        $data = [
            'orderid'        => $order['orderid'],
            'status'         => $status,
            'borrow_time'    => date('Y-m-d H:i:s', $order['borrow_time']),
            'borrow_station' => $order['borrow_station'],
            'last_time'      => humanTime($timediff),
            'return_time'    => $return_time,
            'borrow_name'    => $order['borrow_station_name'],
            'return_name'    => $order['return_station_name'],
            'use_fee'        => $usefee,
            'price'          => $order['price'],
            'fee_strategy'   => makeFeeStr($fee_strategy),
            'is_zhima'       => $order['platform'] == PLATFORM_ZHIMA ? 1 : 0,
        ];
        return $data;
    }


    /**
     * 用户订单列表（无需获取用户订单总数）
     * @param int $uid
     * @param int $page
     * @param int $page_size
     * @return array
     */
    public function getUserOrders($uid, $page, $page_size)
    {
        // 订单分类 1为使用中，2为已完成，3为已关闭
        $using = [
            ORDER_STATUS_RENT_CONFIRM,
            //ORDER_STATUS_PAID,
            //ORDER_STATUS_RENT_CONFIRM_FIRST 前端不展示未拿走的订单，所以支付完成和第一次确认不展示到前端
        ];
        $completed = [
            ORDER_STATUS_RETURN,
            ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
            ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND,
            ORDER_STATUS_TIMEOUT_CANT_RETURN
        ];
        $closed = [ORDER_STATUS_TIMEOUT_NOT_RETURN, ORDER_STATUS_LOSS];

        $condition = array_merge($using, $completed, $closed);

        $originalOrderData = $this->where('uid', $uid)
             ->where(['status' => ['in', $condition]])
             ->order('borrow_time', 'desc')
             ->limit(max($page-1, 0)*$page_size, $page_size)
             ->select();

        if ($originalOrderData) {

            // 获取收费策略
            $orderIds = array_column($originalOrderData, 'orderid');
            $tradeInfos = Tradeinfo::where(['orderid' => ['in', $orderIds]])->column('orderid,fee_strategy');

            foreach ($originalOrderData as $order) {
                $return_time = $order['return_time'] ? date('Y-m-d H:i:s', $order['return_time']) : null;

                //租借时长
                $time2 = empty($order['return_time']) ? time() : $order['return_time'];
                $timediff = $time2 - $order['borrow_time'];

                //获取收费策略
                $fee_strategy = $tradeInfos[$order['orderid']];
                $usefee = $order['usefee'];

                $status = in_array($order['status'], $using) ? 1 : (in_array($order['status'], $completed) ? 2 : 3);
                $orders[] = array(
                    'orderid'        => $order['orderid'],
                    'status'         => $status,
                    'borrow_time'    => date('Y-m-d H:i:s', $order['borrow_time']),
                    'borrow_station' => $order['borrow_station'],
                    'last_time'      => humanTime($timediff),
                    'return_time'    => $return_time,
                    'borrow_name'    => $order['borrow_station_name'],
                    'return_name'    => $order['return_station_name'],
                    'use_fee'        => $usefee,
                    'price'          => $order['price'],
                    'fee_strategy'   => makeFeeStr($fee_strategy),
                    'is_zhima'       => $order['platform'] == PLATFORM_ZHIMA ? 1 : 0,
                );
            }
        }

        return $orders;
    }

    /**
     * $conditions 数组详解
     *
     * city                 城市
     * status               订单状态
     * user_id              用户id
     * platform             订单来源
     * order_id             订单号id
     * err_status           错误状态
     * borrow_sid           借出机器id
     * filter_fee           过滤0费用
     * user_openid          openid模糊查询
     * umbrella_id          雨伞id
     * borrow_device_ver    借出机器版本
     * borrow_shop_sid      借出商铺站点id
     * return_device_ver    归还机器版本
     * return_shop_sid      归还商铺站点id
     * usefeeSituation      多种费用情况
     * borrow_start_time    借出日期起始
     * borrow_end_time      借出日期结束
     * return_start_time    归还日期起始
     * return_end_time      归还日期结束
     * return_station_id    归还机器id
     * borrow_shop_sid      借出商铺id
     * return_shop_sid      归还商铺id
     */

    public function searchOrder($conditions, $page_size, $access_cities, $access_shops){
        $city                      = '';
        $where                     = [];
        $orWhere                   = [];
        $user_id                   = '';
        $order_id                  = '';
        $umbrella_id               = '';
        $user_openid               = '';
        $borrow_end_time           = '';
        $return_end_time           = '';
        $borrow_start_time         = '';
        $return_start_time         = '';
        $borrow_station_id         = '';
        $return_station_id         = '';
        $borrow_shop_station_title = '';
        $return_shop_station_title = '';
        extract($conditions);

        // 授权城市 和 授权商铺 同时存在时使用orWhere
        if ($access_shops !== null || $access_cities !== null) {

            // 没有任何授权
            if (empty($access_shops) && empty($access_cities)) {
                return [];
            } else {
                // 授权的商铺id
                $access_shops  && $orWhere['borrow_shop_id'] = ['in', $access_shops];
                // 授权的区域下的所有商铺
                $access_cities && $orWhere['borrow_city'] = ['in', $access_cities];
            }
        }

        // 借出城市查询
        $city && $where['borrow_city'] = $city;

        // 借出机器版本
        if (isset($borrow_device_ver) && $borrow_device_ver != '-1') {
            $where['borrow_device_ver'] = $borrow_device_ver;
        }

        //　归还机器版本
        if (isset($return_device_ver) && $return_device_ver != '-1') {
            $where['return_device_ver'] = $return_device_ver;
        }

        //　订单号查询
        $order_id && $where['orderid'] = ['like', "%$order_id%"];

        // 雨伞id查询
        $umbrella_id && $where['umbrella_id'] = $umbrella_id;

        // 费用区间
        if (isset($usefee_situation) && $usefee_situation != '-1') {
            if ($usefee_situation == '=0') {
                $where['usefee'] = 0;
            } elseif ($usefee_situation == '>0') {
                $where['usefee'] = ['>', 0];
            } elseif ($usefee_situation == '>=4') {
                $where['usefee'] = ['>=', 4];
            } elseif ($usefee_situation == '>=10') {
                $where['usefee'] = ['>', 10];
            }
        }

        // 用户查询
        $user_id && $where['uid'] = $user_id;

        // 用户openid查询
        $user_openid && $where['openid'] = $user_openid;

        // 查询借出机器id
        $borrow_station_id && $where['borrow_station'] = $borrow_station_id;

        // 查询归还机器id
        $return_station_id && $where['return_station'] = $return_station_id;

        // 查询借出商铺站点名称
        $borrow_shop_station_title && $where['borrow_station_name'] = ['like', "%$borrow_shop_station_title%"];

        // 查询归还商铺站点名称
        $return_shop_station_title && $where['return_station_name'] = ['like', "%$return_shop_station_title%"];

        // 订单来源
        if (isset($platform) && $platform != '-1') {
            $where['platform'] = $platform;
        }

        // 订单状态
        if (isset($status) && $status != '-1') {
            switch ($status) {
                case ORDER_LIST_ALL_BORROW :
                    $where['status'] = ['in', [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RETURN ]];
                    break;

                case ORDER_LIST_EXCEPTION :
                    $where['status'] = ['not in', [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RETURN, ORDER_STATUS_RENT_CONFIRM_FIRST ]];
                    break;

                default :
                    $where['status'] = $status;
            }
        }

        // 订单错误状态
        if (isset($err_status) && $err_status != '-1') {
            $where['status'] = $err_status;
        }


        if (!empty($borrow_start_time && $borrow_end_time)) {
            $end_time   = strtotime($borrow_end_time);
            $start_time = strtotime($borrow_start_time);
            $where['borrow_time'] = ['between', [$start_time, $end_time]];
        } else {
            // 查询借出日期　(起始)
            if (!empty($borrow_start_time)) {
                $time = strtotime($borrow_start_time);
                $where['borrow_time'] = ['>=', $time];
            }

            // 查询借出日期 (结束)
            if (!empty($borrow_end_time)) {
                $time = strtotime($borrow_end_time);
                $where['borrow_time'] = ['<=', $time];
            }
        }

        if (!empty($return_start_time && $return_end_time)) {
            $start_time = strtotime($return_start_time);
            $end_time = strtotime($return_end_time);
            $where['return_time'] = ['between', [$start_time, $end_time]];
        } else {
            // 查询归还日期 (起始)
            if (!empty($return_start_time)) {
                $time = strtotime($return_start_time);
                $where['return_time'] = ['>=', $time];
            }

            // 查询归还日期 (结束)
            if (!empty($return_end_time)) {
                $time = strtotime($return_end_time);
                $where['return_time'] = ['<=', $time];
            }
        }

        return $this->where(function ($query) use ($where) {
            $query->where($where);
        })->where(function ($query) use ($orWhere){
            $query->whereOr($orWhere);
        })->order('borrow_time desc')->paginate($page_size, false, ['query'=>$conditions]);
    }


    /**
     * 判断该设备当前是否有正在借出
     * 1. 是否有订单处于已支付未借出
     * 2. 是否有订单处于准备借出中, 且准备借出中的时间距离现在不超过20s
     * 如果都没有以上两种状态, 则该设备无正在借出的订单, 可让后来用户使用
     *
     * @param $station_id
     * @return bool true 有正在借出的订单  false 无借出的订单
     */
    public function hasBorrowingOrder($station_id)
    {
        $count = $this->query('SELECT orderid FROM '.$this->getTable().
            ' WHERE borrow_station = ? AND 
            ((status = ?) OR (status = ? AND borrow_time > ?)) limit 1', [
            $station_id,
            ORDER_STATUS_PAID,
            ORDER_STATUS_RENT_CONFIRM_FIRST,
            time()-20
        ]);
        return (bool)$count;
    }


    /**
     *
     * 已支付订单，借出第一次确认，借出订单，借出未拿走中间态（后台还未取消订单）  全部返回true
     * 或者
     * 遗失订单（后台或用户手动）且更新时间在2分钟之内的订单（后台还未结算订单） 返回true
     *
     * 其他返回false
     *
     * @param $uid
     * @return bool
     *
     */
    public function hasUnFinishedZhimaOrder($uid)
    {
        // @todo 优化写法
        $count = $this->query('SELECT uid FROM '.$this->getTable().
            ' WHERE uid=? AND platform=? AND 
            ((status in (?,?,?,?) AND borrow_time>?) OR (status in (?,?) AND lastupdate>?)) limit 1', [
                $uid,
                PLATFORM_ZHIMA,
                ORDER_STATUS_PAID,
                ORDER_STATUS_RENT_CONFIRM_FIRST,
                ORDER_STATUS_RENT_CONFIRM,
                ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE,
                time() - 20*24*3600,
                ORDER_STATUS_TIMEOUT_NOT_RETURN,
                ORDER_STATUS_LOSS,
                time() - 120
        ]);

        return (bool)$count;
    }

    public function deleteNotPaidOrder()
    {
        return $this->execute('DELETE FROM ' . $this->getTable() . ' WHERE status = ? AND lastupdate < ?', [
            ORDER_STATUS_WAIT_PAY,
            time()-3600
        ]);
    }


    /**
     * 获取可以用于退款的订单
     * @param $uid
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrdersForRefund($uid)
    {
        // 选择订单顺序：先未退过的订单，再价格高的订单
        // refundno >= 0 过滤了芝麻订单
        // paid > 0 过滤了账户内支付
        $where = [
            'uid' => $uid,
            'status' => ['<>', ORDER_STATUS_WAIT_PAY],
            'paid' => ['>', 0],
            'refundno' => ['>=', 0]
        ];
        return $this->where($where)
            ->field('orderid, platform, price, refundno, paid, refunded')
            ->order('refunded asc, price desc')
            ->select();
    }
}
