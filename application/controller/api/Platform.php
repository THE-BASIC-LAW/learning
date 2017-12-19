<?php

namespace app\controller\api;

use app\common\controller\Base;
use app\lib\Api;
use app\logic\Order;
use app\logic\Pay;
use app\logic\TplMsg;
use app\model\CommonSetting;
use app\model\Menu;
use app\model\Qrcode;
use app\model\Shop;
use app\model\ShopStation;
use app\model\ShopType;
use app\model\Station;
use app\model\Tradelog;
use app\model\TradeZhima;
use app\model\User;
use app\model\UserSession;
use app\model\WalletStatement;
use app\third\baiduLbs;
use app\third\swApi;
use app\third\wxServer;
use think\Db;
use think\Exception;
use think\Log;

/**
 * Class Platform
 * 微信公众号/支付宝生活号 API
 */
class Platform extends Base
{

    protected $logName = 'platform';

    /**
     * @var int 用户ID
     */
    protected $uid;

    private function _checkSession()
    {
        if (!$this->request->has('session', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $session = $this->request->post('session');
        $uid     = model('UserSession')->getUidBySession($session);
        if (empty($uid)) {
            Api::fail(Api::SESSION_EXPIRED);
        }

        $this->uid = $uid;
        // 更新状态时间
        model('UserSession')->updateSessionTime($session);

        Log::info("uid: $uid , session: $session");
    }

    public function _initialize()
    {
        // 开启Api的log打印功能
        Api::logOn();

        Log::info('api request: ' . json_encode(input('post.'), true));
        $requestMethod = $this->request->dispatch()['method'][1];
        if ($requestMethod != 'getOauthUrl') {
            $this->_checkSession();
        }
    }

    // 获取用户金额和未还订单数 installer
    public function userInfo()
    {
        $userInfo = db('user_info')->find($this->uid);
        $unReturn = (new Tradelog())->unReturn($this->uid);
        $user     = User::get($this->uid);
        $data     = [
            'nickname'   => $userInfo['nickname'] ? json_decode($userInfo['nickname'], true) : '',
            'headimgurl' => $userInfo['headimgurl'] ? : '', // 处理下，不然userinfo不存在时会返回给前端null
            'money'      => $user->usablemoney + $user->deposit,
            'unreturn'   => $unReturn,
        ];

        $cs = new CommonSetting();
        if ($cs->isMaintainUser($this->uid)) {
            $data['installer'] = 0;
        }
        if ($cs->isMaintainMan($this->uid)) {
            $data['installer'] = 1;
        }
        Api::output($data);
    }

    // 根据地理位置获取附近商铺信息
    public function getShops()
    {
        if (!$this->request->has('lng', 'post') || !$this->request->has('lat', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        if (empty($this->request->post('lng') || empty($this->request->post('lat')))) {
            Api::fail(3, '失败');
        }

        // 获取百度lbs中的sid
        $location = $this->request->post('lng') . ',' . $this->request->post('lat');
        $ret      = baiduLbs::searchNearbyPOI(['location' => $location]);
        if ($ret['status'] != 0) {
            Log::info('get shops fail because search nearby poi fail, ' . print_r($ret, 1));
            Api::fail(3, '失败');
        }
        foreach ($ret['contents'] as $k => $v) {
            $sids[] = $v['sid'];
        }
        if (empty($sid)) {
            Api::fail(2, '附近没有商铺');
        }
        $res = (new ShopStation())->getAllInfo($sids);
        if (empty($res)) {
            Api::fail(2, '附近没有商铺');
        }
        Api::output(array_values($res));
    }

    // 获取特定商铺详细信息
    public function shopDetail()
    {
        if (!$this->request->has('shop_station_id')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        if (empty($this->request->post('shop_station_id'))) {
            Api::fail(Api::NO_MUST_PARAM);
        }

        // 获取该商铺下面的所有商铺站点
        $shopStationInfo = ShopStation::get($this->request->post('shop_station_id'));
        $hasShopFlag     = false;
        if ($shopId = $shopStationInfo->shopid) {
            $shopStationInfo = ShopStation::all(['shopid' => $shopId]);
            $hasShopFlag     = true;
            $shopStationIds  = array_column($shopStationInfo, 'id');
        } else {
            $shopStationIds = $shopStationInfo->id;
        }

        // 显示规则
        // 有商铺时 商铺信息用商铺数据显示
        // 无商铺时 商铺信息用当前商铺站点数据显示

        if ($hasShopFlag) {
            $shopInfo             = Shop::get($shopId);
            $shopData['id']       = $shopInfo['id'];
            $shopData['name']     = $shopInfo['name'];
            $shopData['phone']    = $shopInfo['phone'];
            $shopData['stime']    = $shopInfo['stime'];
            $shopData['etime']    = $shopInfo['etime'];
            $shopData['carousel'] = json_decode($shopInfo['carousel'], true) ?: [];

            // 处理直辖市的特殊情况
            if ($shopInfo['province'] && ($shopInfo['province'] == $shopInfo['city'])) {
                $shopData['address'] = $shopInfo['province'] . $shopInfo['area'] . $shopInfo['locate'];
            } else {
                $shopData['address'] = $shopInfo['province'] . $shopInfo['city'] . $shopInfo['area'] . $shopInfo['locate'];
            }

            $stationIds   = array_column($shopStationInfo, 'station_id');
            $stations     = Station::where(['id' => ['in', $stationIds]])->column('id,usable,empty');
            $stationsDesc = ShopStation::where(['station_id' => ['in', $stationIds]])->column('station_id,desc');
            foreach ($stations as $k => &$v) {
                if (key_exists($k, $stationsDesc)) {
                    $v['desc'] = $stationsDesc[$k];
                }
            }
            $stations = array_values($stations);

            $ret = ['stations' => $stations, 'shop_info' => $shopData];
        } else {
            $stationInfo = Station::get($shopStationInfo['station_id']);
            $stations[]  = [
                'station_id' => $shopStationInfo['station_id'],
                'desc'       => $shopStationInfo['desc'],
                'empty'      => $stationInfo['empty'],
                'usable'     => $stationInfo['usable'],
            ];
            $shopInfo    = [
                'id'       => 0,
                'name'     => $shopStationInfo['title'],
                'address'  => $shopStationInfo['address'],
                'phone'    => '',
                'stime'    => '',
                'etime'    => '',
                'carousel' => [],
            ];

            $ret = ['stations' => $stations, 'shop_info' => $shopInfo];
        }

        Api::output();
    }

    // 根据关键字搜索商铺
    public function filter()
    {
        if (!$this->request->has('key_str', 'post') || $this->request->has('mark', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        if (empty($this->request->post('key_str'))) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $pageSize         = 30;
        $shopStation      = new ShopStation();
        $shopStationInfos = $shopStation->filterByKeyWords($this->request->post('key_str'));
        $shopStationIds   = array_column($shopStationInfos, 'id');
        $ret              = $shopStation->filter($shopStationIds, $this->request->post('mark'), $pageSize);
        $shops            = $ret['shops'];
        if (empty($shops)) {
            Api::fail(2, '没有搜索结果');
        }

        foreach ($shops as $k => $shop) {
            if (!$shop['shoplogo']) {
                $type_info        = ShopType::get($shop['type']);
                $shop['shoplogo'] = json_decode($type_info['logo'], true);
            }
        }
        $shops = array_values($shops);
        Api::output(['shop' => $shops, 'mark' => $ret['mark']]);
    }

    // 订单状态查询
    public function orderStatus()
    {
        if (!$this->request->has('order_id', 'post') || empty($this->request->post('order_id'))) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $orderId      = $this->request->post('order_id');
        $orderInfo = db('tradelog')->field('status,borrow_time,message')->where('uid', $this->uid)->find($orderId);
        // 订单不存在，订单已归还，前端跳走
        if (!$orderInfo || $orderInfo['status'] == ORDER_STATUS_RETURN) {
            Api::output(['status' => 7]);
        }
        // 借出时间超过2分钟，前端跳走
        if (time() - $orderInfo['borrow_time'] > 120) {
            Api::output(['status' => 7]);
        }

        switch ($orderInfo['status']) {

            # 订单未支付
            case ORDER_STATUS_WAIT_PAY:
                Api::output(['status' => 0]);
                break;
            # 订单已支付
            case ORDER_STATUS_PAID:
                Api::output(['status' => 1]);
                break;
            # 机器出伞中
            case ORDER_STATUS_RENT_CONFIRM_FIRST:
                $message = unserialize($orderInfo['message']);
                Api::output(['status' => 3, 'slot' => $message['slot']]);
                break;
            # 雨伞借出成功
            case ORDER_STATUS_RENT_CONFIRM:
                Api::output(['status' => 2]);
                break;
            # 用户未取走中间态
            case ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE:
            # 用户未取走
            case ORDER_STATUS_RENT_NOT_FETCH:
                Api::output(['status' => 4]);
                break;
            # 机器被人使用中导致借伞失败
            case ORDER_STATUS_LAST_ORDER_UNFINISHED:
                Api::output(['status' => 6]);
                break;
            default:
                Api::output(['status' => 5]);
                break;
        }
    }

    // 钱包信息
    public function wallet()
    {
        $user = User::get($this->uid);
        $menu = Menu::get(1);
        $ret  = [
            'usable'  => $user['usablemoney'],
            'deposit' => $user['deposit'],
            'price'   => $menu['price'],
        ];
        Api::output($ret);
    }

    // 钱包明细
    public function walletDetail()
    {
        if (!$this->request->has('page', 'post') || empty($this->request->post('page'))) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $page     = $this->request->post('page');
        $pageSize = 20;
        $ret      = (new WalletStatement())->getStatement($this->uid, max($page - 1, 0) * $pageSize, $pageSize);
        if ($ret) {
            // 状态为4(提现到账)时要判断是否超过2天，不超过2天变更状态为3(提现处理中)
            $ret = array_map(function ($a) {
                if (isset($a['type']) && $a['type'] == WALLET_TYPE_WITHDRAW) {
                    if (strtotime($a['time']) > time() - 2 * 24 * 3600) {
                        $a['type'] = WALLET_TYPE_REQUEST;
                    }
                }
                return $a;
            }, $ret);
            Api::output($ret);
        }
        Api::fail(2, '没有更多记录');
    }

    // 遗失处理
    public function lossHandle()
    {
        if (!$this->request->has('order_id', 'post') || empty($this->request->post('order_id'))) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $orderId  = $this->request->post('order_id');
        $tradeLog = new Tradelog();
        if (!$orderInfo = $tradeLog->canBeLostHandle($orderId, $this->uid)) {
            Api::fail(2, '操作失败');
        }

        $isZhimaOrder = $orderInfo['platform'] == PLATFORM_ZHIMA ? 1 : 0;

        $ret = $tradeLog->save([
            'status'      => ORDER_STATUS_LOSS,
            'lastupdate'  => time(),
            'return_time' => time(),
            'usefee'      => $orderInfo['price'],
        ], ['orderid' => $orderId, 'status' => ORDER_STATUS_RENT_CONFIRM]);
        if (!$ret) {
            Api::fail(2, '操作失败');
        }

        if ($isZhimaOrder) {
            // 芝麻订单
            db('trade_zhima')->save([
                'status'      => ZHIMA_ORDER_COMPLETE_WAIT,
                'update_time' => time(),
            ], ['orderid' => $orderId]);
            // 记录用户流水
            db('wallet_statement')->insert([
                'uid'        => $this->uid,
                'related_id' => $orderId,
                'type'       => WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED,
                'amount'     => $orderInfo['price'],
                'time'       => date('Y-m-d H:i:s'),
            ]);
        } else {
            // 非芝麻订单，扣除押金，可用余额不变
            (new User())->reduceDeposit($this->uid, $orderInfo['price']);
            // 记录用户流水
            db('wallet_statement')->insert([
                'uid'        => $this->uid,
                'related_id' => $orderId,
                'type'       => WALLET_TYPE_PAID,
                'amount'     => $orderInfo['price'],
                'time'       => date('Y-m-d H:i:s'),
            ]);
        }

        // 推送雨伞遗失处理信息
        $msg = [
            'openid'              => $orderInfo['openid'],
            'borrow_station_name' => $orderInfo['borrow_station_name'],
            'borrow_time'         => date('Y-m-d H:i:s', $orderInfo['borrow_time']),
            'handle_time'         => date('Y-m-d H:i:s'),
            'order_id'            => $orderId,
            'price'               => $orderInfo['price'],
        ];
        TplMsg::send(TplMsg::MSG_TYPE_LOSE_UMBRELLA, $msg);

        $ret = $tradeLog->getOrderDataForApi($orderId);
        Api::output($ret);
    }

    // 根据二维码返回特定站点信息
    public function getStationInfo()
    {
        if (!$this->request->has('qrcode', 'post') || empty($this->request->post('qrcode'))) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $qrcode   = urldecode($this->request->post('qrcode'));
        $userInfo = User::get($this->uid);

        // 获取对应stationId
        $stationId = model('Qrcode')->getStationId($qrcode, $userInfo->platform);
        if (!$stationId) {
            Api::output([], Api::ERROR_QR_CODE);
        }

        // 机器状态在线检查
        if (!swApi::isStationOnline($stationId)) {
            Api::fail(2, '机器离线');
        }

        // 可借雨伞数量检查
        $stationInfo = db('station')->where('id', $stationId)->select();
        $stationInfo = $stationInfo[0];
        if (!$stationId || !$stationInfo['usable']) {
            Api::fail(3, '无可借雨伞');
        }

        /**
         * 微信/支付宝返回站点相关信息
         * 如果是支付宝且没有未结束的芝麻订单，则返回芝麻订单相关链接
         */

        $isZhima = false;
        if ($userInfo->platform == PLATFORM_ALIPAY) {
            $isZhima = !(model('Tradelog')->hasUnFinishedZhimaOrder($this->uid));
        }

        $feeStrategyFee = model('ShopStation')->getFeeSettingsByStationId($stationId);
        // 收费策略--前端显示
        $feeStrForPlatform = makeFeeStr($feeStrategyFee);

        // 芝麻
        if ($isZhima) {
            // 收费策略--芝麻信用显示
            $feeInfo = getFeeInfoForZhima($feeStrategyFee, $stationInfo['price']);

            // 创建订单(芝麻)
            $orderInfo = model('Order', 'logic')->createNewOrder($stationId, $this->uid, PLATFORM_ZHIMA);

            // 添加芝麻信息
            $orderInfo['platform'] = PLATFORM_ZHIMA;
            $orderInfo['zhima']    = $feeInfo;

            $url = (new Pay())->getPayInfo($orderInfo);

            Api::output(['url' => $url, 'fee_strategy' => $feeStrForPlatform]);
        }

        // 非芝麻
        $menu = Menu::get(1);
        // 需要在线支付
        if (round($userInfo['usablemoney'], 2) >= round($menu['price'], 2)) {
            $need_pay = 0;
        } else {
            $need_pay = round($menu['price'] - $userInfo['usablemoney'], 2);
        }
        $data = [
            'sid'          => $stationId,
            'usable'       => $stationInfo['usable'],
            'deposit_need' => $menu['price'],
            'usable_money' => $userInfo['usablemoney'],
            'fee_strategy' => $feeStrForPlatform,
            'need_pay'     => $need_pay,
        ];

        Api::output($data);
    }

    // 提现申请
    public function refund()
    {
        $userInfo = User::get($this->uid);
        if (empty($userInfo['openid']) || $userInfo['usablemoney'] <= 0) {
            Api::fail(3, '提现失败');
        }

        // 事务处理提现申请
        Db::startTrans();
        try {
            $rst1 = db('refund_log')->insert([
                'uid'          => $userInfo['id'],
                'refund'       => $userInfo['usablemoney'],
                'status'       => REFUND_STATUS_REQUEST,
                'request_time' => time(),
                'detail'       => '',
            ], false, true);
            $rst2 = db('user')->where('usablemoney', '>=', $userInfo['usablemoney'])->where('id', $userInfo['id'])->dec('usablemoney', $userInfo['usablemoney'])->inc('refund', $userInfo['usablemoney'])->update();
            if ($rst1 && $rst2) {
                Db::commit();
                Log::info('user id: ' . $userInfo['id'] . ' usablemoney: ' . $userInfo['usablemoney'] . ' refund request success');
            } else {
                Db::rollback();
                Log::info('refund log result : ' . $rst1 . ', user update result: ' . $rst2);
                Log::notice('user id: ' . $userInfo['id'] . ' usablemoney: ' . $userInfo['usablemoney'] . ' refund request fail');
                Api::fail(3, '提现失败');
            }
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('refund fail. ' . $e->getMessage());
            Api::fail(3, '提现失败');
        }
        db('wallet_statement')->insert([
            'uid'        => $this->uid,
            'related_id' => $rst1,
            'type'       => WALLET_TYPE_REQUEST,
            'amount'     => $userInfo['usablemoney'],
            'time'       => date('Y-m-d H:i:s'),
        ]);

        $msg = [
            'openid'       => $userInfo['openid'],
            'refund'       => $userInfo['usablemoney'],
            'request_time' => time(),
        ];
        TplMsg::send(TplMsg::MSG_TYPE_WITHDRAW_APPLY, $msg);
        Log::info('refund request success');
        Api::output();
    }

    // 借还记录
    public function orders()
    {
        if (!$this->request->has('page', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $page     = $this->request->post('page');
        $pageSize = 20;
        $ret      = (new Tradelog())->getUserOrders($this->uid, $page, $pageSize);
        if ($ret) {
            Api::output($ret);
        }
        Api::fail(2, '没有记录');
    }

    // 角色切换, 管理人员使用
    public function switchRole()
    {
        $data = [];
        $rst  = (new CommonSetting())->changeInstallManRole($this->uid);
        if ($rst === false) {
            Api::output(2, '没有权限');
        } elseif ($rst === 0) {
            $data['installer'] = 0;
        } elseif ($rst === 1) {
            $data['installer'] = 1;
        }
        Api::output($data);
    }

    // 借伞及支付
    public function borrow()
    {
        if (!$this->request->has('stationid', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $stationId = $this->request->post('stationid');

        // 机器状态检查
        $tradelog = new Tradelog();
        if ($tradelog->hasBorrowingOrder($stationId)) {
            Api::fail(2, '上一单未完成');
        }

        // 可借雨伞数量检查
        $stationInfo = db('station')->where('id', $stationId)->select();
        $stationInfo = $stationInfo[0];
        if (!$stationId || !$stationInfo['usable']) {
            Api::fail(3, '无可借雨伞');
        }

        // 生成新订单相关
        $order     = new Order();
        $orderInfo = $order->createNewOrder($stationId, $this->uid);
        if (!$orderInfo) {
            Api::fail(4, '服务器内部错误');
        }

        // 支付押金
        Log::notice('begin to pay in user account');

        $user   = new User();
        $result = $user->payDeposit($this->uid, $orderInfo['price']);
        // 账户内押金支付
        if ($result) {
            db('tradelog')->update([
                'orderid'    => $orderInfo['orderid'],
                'status'     => ORDER_STATUS_PAID,
                'refundno'   => ORDER_NOT_REFUND,
                'lastupdate' => time(),
            ]);
            //出伞命令
            swApi::borrowUmbrella($stationId, $orderInfo['orderid']);
            Api::output(['paytype' => 1, 'orderid' => $orderInfo['orderid']]);
        }

        // 在线支付
        $usableMoney        = $user->find($this->uid)['usablemoney'];
        $needPayMoneyOnline = $orderInfo['price'] - $usableMoney;
        Log::info('uid: ' . $this->uid . ', usable money: ' . $usableMoney . ' need pay online: ' . $needPayMoneyOnline);

        $payInfo = [
            'subject'  => $orderInfo['subject'],
            'orderid'  => $orderInfo['orderid'],
            'openid'   => $orderInfo['openid'],
            'price'    => $needPayMoneyOnline,
            'platform' => $orderInfo['platform'],
        ];

        $pay  = new Pay();
        $data = $pay->getPayInfo($payInfo);
        // 微信返回数组，支付宝返回空数组，失败返回false
        if (false === $data) {
            Api::fail(4, '服务器内部错误');
        }
        $ret = [
            'paytype'         => 0,
            'orderid'         => $orderInfo['orderid'],
            'jsApiParameters' => $data,
        ];
        Api::output($ret);
    }

    // 获取授权地址
    public function getOauthUrl()
    {
        if (!$this->request->has('platform', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        if ($this->request->post('platform') == PLATFORM_WX) {
            $ret = ['url' => '/user/oauthWechat'];
        } else {
            $ret = ['url' => '/user/oauthAlipay'];
        }
        Api::output($ret);
    }

    // WechatJs配置
    public function getWechatJsapi()
    {
        if (!$this->request->has('url', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $userInfo = User::get($this->uid);
        if ($userInfo['platform'] != PLATFORM_WX) {
            // 非微信请求传空
            Api::output();
        }
        // 获取配置jssdk需要的url
        $url  = explode('#', $this->request->post('url'));
        $url  = $url[0];
        $js   = wxServer::instance()->js;
        $list = [
            'onMenuShareTimeline',
            'onMenuShareAppMessage',
            'onMenuShareQQ',
            'onMenuShareWeibo',
            'onMenuShareQZone',
            'getNetworkType',
            'openLocation',
            'getLocation',
            'closeWindow',
            'scanQRCode',
            'chooseWXPay',
        ];
        $js->setUrl($url);
        $ret = $js->config($list, false, false, false);
        // 前端不用jsApiList
        Api::output($ret);
    }

    // 转换百度经纬度为高德经纬度
    public function changeBaiduCoordinatesToGaode()
    {
        if (!$this->request->has('lng', 'post') || !$this->request->has('lat', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $lng      = $this->request->post('lng');
        $lat      = $this->request->post('lat');
        $location = $lng . ',' . $lat;
        if (!$ret = baiduLbs::aMapCoordinateConvert($location)) {
            Api::fail(1, '坐标转换失败');
        }
        Api::output($ret);
    }

    public function convertGpsToBaidu()
    {
        if (!$this->request->has('lng', 'post') || !$this->request->has('lat', 'post')) {
            Api::fail(Api::NO_MUST_PARAM);
        }
        $rst = baiduLbs::convertGps($this->request->post('lng') . ',' . $this->request->post('lat'));
        Log::info('convert gps to baidu: ' . print_r($rst, 1));
        if ($rst['status'] != 0) {
            Api::fail(1, '转换失败');
        }
        Api::output(['lng' => $rst['result'][0]['x'], 'lat' => $rst['result'][0]['y']]);
    }

    // @todo 好像没啥用
    public function _empty($name)
    {
        Log::notice('method not existed: ' . $name);
        Api::fail(Api::API_NOT_EXISTS);
    }
}