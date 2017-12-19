<?php namespace app\logic;

use app\model\FeeStrategy;
use think\Exception;
use think\Log;

class Order
{


    /**
     * @var string 订单前缀
     */
    private $orderPre = 'DDZH';

    /**
     * 生成新订单(包含收费策略保存)
     * @param $stationId integer
     * @param $uid integer
     * @param $platform integer
     * @return bool | array 失败 返回false 成功 返回订单详情（含有更多数据）
     */
    public function createNewOrder($stationId, $uid, $platform = PLATFORM_WX)
    {
        $orderId = $this->_generateOrderId();
        $borrowStationInfo = $this->_getStationInfoForOrder($stationId);
        $user = db('user')->cache()->find($uid);

        $menu = db('menu')->cache()->find(1);

        $data = [
            'orderid' => $orderId,
            'price' => $menu['price'],
            'baseprice' => 0,
            'uid' => $uid,
            'openid' => $user['openid'],
            'status' => ORDER_STATUS_WAIT_PAY,
            'message' => '',
            'lastupdate' => time(),
            'borrow_station' => $borrowStationInfo['station'],
            'borrow_station_name' => $borrowStationInfo['station_name'],
            'borrow_shop_id' => $borrowStationInfo['shop_id'],
            'borrow_city' => $borrowStationInfo['city'],
            'borrow_device_ver' => $borrowStationInfo['device_ver'],
            'borrow_shop_station_id' => $borrowStationInfo['shop_station_id'],
            'shop_type' => $borrowStationInfo['shop_type'],
            'seller_mode' => 0, //直营或者代理 0直营 1代理
            'refundno' => $platform == PLATFORM_ZHIMA ? ORDER_ZHIMA_NOT_REFUND : 0, // 芝麻信用不能用于退款
            'platform' => $platform,
            // 借出时间是从终端获取的，这里写入时间只是为了方便一些异常订单显示页面中有时间这个参数
            'borrow_time' => time()

        ];

        try {
            db('tradelog')->insert($data);
        } catch (Exception $e) {
            Log::error('add new order fail, Exception message: ' . $e->getMessage());
            return false;
        }

        $this->_saveFeeStrategy($orderId, $borrowStationInfo['fee_settings']);

        // 返回更多数据
        $data['subject'] = $menu['subject'];

        return $data;
    }




    /**
     * 生成订单ID
     * @return string
     */
    private function _generateOrderId()
    {
        $d  = getdate();
        $id = sprintf('-%u%02u%02u-%02u%02u%02u-%05u',
            $d['year'],
            $d['mon'],
            $d['mday'],
            $d['hours'],
            $d['minutes'],
            $d['seconds'],
            rand(1, 99999)
        );
        return $this->orderPre . $id;
    }


    /**
     * 获取订单需要使用的站点相关信息
     * 优先级： 商铺名称->商铺站点名称->站点ID
     *
     * @param $stationId
     * @return array
     */
    private function _getStationInfoForOrder($stationId)
    {
        $data['station'] = $stationId;

        $station = db('station')->cache()->where('id', $stationId)->find();

        $shopStation = db('shop_station')->cache()->where('station_id', $stationId)->find();
        if ($shopStation['shopid']) {
            $shop = db('shop')->cache()->where('id', $shopStation['shopid'])->find();
        }

        if (isset($shop) && isset($shop['name']) && $shop['name']) {
            $data['station_name'] = $shop['name'];
        } elseif (isset($shopStation['title']) && $shopStation['title']) {
            $data['station_name'] = $shopStation['title'];
        } else {
            $data['station_name'] = $stationId;
        }

        $data['shop_id'] = isset($shop) ? $shop['id'] + 0 : 0;
        $data['shop_station_id'] = $shopStation['id'] + 0;
        $data['device_ver'] = $station['device_ver'];
        $data['city'] =  isset($shop) && $shop['city'] ? $shop['city'] : '';
        $data['shop_type'] = isset($shop) && $shop['type'] ? $shop['type'] + 0 : 0;
        $data['fee_settings'] = $shopStation['fee_settings'];


        return $data;
    }


    /**
     * @param $orderId       string  订单ID
     * @param $feeStrategyId integer 收费策略ID
     */
    private function _saveFeeStrategy($orderId, $feeStrategyId)
    {
        $feeStrategyArr = (new FeeStrategy())->getStrategySettings($feeStrategyId);
        db('tradeinfo')->insert([
            'orderid' => $orderId,
            'fee_strategy' => json_encode($feeStrategyArr)
        ]);
        Log::info('fee strategy save : order id-> ' .$orderId. ' , fee strategy-> ' . print_r($feeStrategyArr, 1));
    }

    public function getStationInfo($stationId)
    {
        return $this->_getStationInfoForOrder($stationId);
    }
}