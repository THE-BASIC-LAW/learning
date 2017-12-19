<?php namespace app\controller;

use app\common\controller\Base;
use app\model\User;
use app\model\UserInfo;
use app\third\alipay\AlipayAPI;
use app\third\swApi;
use think\Exception;
use think\exception\DbException;
use think\Log;


/**
 *
 * Class Alipay
 * @package app\controller
 */

class Alipay extends Base
{

    // 设置log目录
    protected $logName = 'alipay';

    protected function _initialize()
    {
        AlipayAPI::initialize();
    }

    /**
     * 支付宝网关地址
     * 接收支付宝的消息推送（包含芝麻信用订单创建，订单完结)
     */
    public function notice()
    {
        if ($this->request->isPost()) {

            Log::info(print_r($_POST, 1));

            // 处理芝麻信用通知
            if ($this->request->has('notify_type', 'post')) {
                if (!AlipayAPI::verifyPayNotify()) {
                    Log::info('zhima verify fail');
                    echo 'verify fail';
                    exit;
                }

                switch ($this->request->post('notify_type')) {

                    # 创建信用订单
                    case 'ORDER_CREATE_NOTIFY':
                        $trade = model('TradeZhima')->find($this->request->get('out_order_no'));
                        if ($trade) {
                            Log::info('zhima order has been created: ' . $this->request->get('out_order_no'));
                            echo 'success';
                            exit;
                        }
                        $result = model('TradeZhima')->insert([
                            'orderid' => $this->request->get('out_order_no'),
                            'zhima_order' => $this->request->get('order_no'),
                            'status' => ZHIMA_ORDER_CREATE,
                            'create_time' => time(),
                            'update_time' => time(),
                            'openid' => '',
                            'admit_state' => 0,
                            'pay_amount_type' => 'RENT',
                            'pay_time' => 0,
                            'alipay_fund_order_no' => 0
                        ]);
                        if (!$result) {
                            Log::error('insert zhima order error');
                            //必须要退出程序，不返回success，才能保证芝麻消息再次推送消息过来
                            exit;
                        }
                        // 支付完成处理
                        model('paid', 'logic')->handle($this->request->get('out_order_no'), 0);
                        Log::info('end of zhima borrow process');
                        break;

                    # 订单完成（扣款需要查询）
                    case 'ORDER_COMPLETE_NOTIFY':
                        db('trade_zhima')->where('orderid', $this->request->get('out_order_no'))
                            ->data(['status' => ZHIMA_ORDER_QUERY_WAIT, 'update_time' => time()])
                            ->update();
                        Log::info('update zhima query status: ' . $this->request->get('out_order_no'));
                        break;


                    default:
                }

                echo 'success';
                exit;
            }

            // 验证网关
            if ($this->request->post('service') == 'alipay.service.check') {
                AlipayAPI::verifyGateway();
            } else {
                // 验证数据
                if (AlipayAPI::verifyMessage()) {
                    Log::info('alipay message verify pass.');
                    // 解析数据
                    AlipayAPI::getMsg();
                    $openId = (string)AlipayAPI::$msgData['client'];
                    $type = (string)AlipayAPI::$msgData['type'];

                    // 处理业务逻辑
                    $user = new User();
                    try {
                        $userInfo = $user->where('openid', $openId)->find();
                        $userId = $userInfo['id'];
                    } catch (DbException $e) {
                        Log::error('db exception, ' . $e->getMessage());
                        return '';
                    } catch (Exception $e) {
                        Log::error('exception, ' . $e->getMessage());
                        return '';
                    }
                    if (!$userInfo) {
                        $userId = $user->save([
                            'openid' => $openId,
                            'platform' => PLATFORM_ALIPAY,
                            'create_time' => date('Y-m-d H:i:s'),
                            'unsubscribe' => 0, // 0关注 1未关注
                        ], [], true);
                        Log::info("new userInfo, id: $userId , openid: $openId");

                        // 新增用户信息
                        try {
                            $newUserInfo = [
                                'id' => $userId,
                                'openid' => $openId,
                                'subscribe_time' => time(),
                            ];
                            (new UserInfo())->addUser($newUserInfo);
                        } catch (\Exception $e) {
                            Log::error('save alipay userInfo info fail, exception message: ' . $e->getMessage());
                            return '';
                        }

                    }

                    switch ($type) {

                        # 文本消息
                        case 'text':
                            if (time() - $userInfo['reply_time'] < 6*3600) {
                                exit;
                            }
                            $msg = '';
                            $msg .= "<a href='http://" . SERVER_DOMAIN . "/user/center#/useFlow'>了解雨伞使用流程</a>\n\n";
                            $msg .= "常见问题：\n";
                            $msg .= "雨伞如何收费？\n";
                            $msg .= "扫码取伞时，伞没有弹出？\n";
                            $msg .= "归还时提示归还失败？\n";
                            $msg .= "伞柄卡入伞槽，没收到还伞成功提示？\n";
                            $msg .= "<a href='http://" . SERVER_DOMAIN . "/user/center#/userHelp'> >>使用帮助<< </a>";
                            AlipayAPI::replyTextMsg($msg);
                            $userInfo->save(['reply_time' => time()]);
                            break;

                        # 事件消息
                        case 'event':
                            switch (AlipayAPI::$msgData['event']) {

                                # 关注
                                case 'follow':
                                    db('user')->update(['id' => $userId, 'unsubscribe' => 0]);
                                    $msg  = "感谢关注JJ伞，我们致力于为您提供随街可借的雨伞！\n\n";
                                    $msg .= "<a href='http://" . SERVER_DOMAIN . "/user/center#/useFlow'>了解雨伞使用流程</a>";
                                    AlipayAPI::replyTextMsg($msg);
                                    break;

                                # 取消关注
                                case 'unfollow':
                                    db('user')->update(['id' => $userId, 'unsubscribe' => 1]);
                                    break;

                                # 进入界面事件
                                # 说明下: 支付宝和微信有些区别,微信有扫码事件, 支付宝就是进入事件(默认不订阅,需要设置)
                                case 'enter':
                                    if (!isset(AlipayApi::$msgData['eventkey'])) {
                                        break;
                                    }
                                    $stationId = (int)AlipayAPI::$msgData['eventkey'];
                                    // 场景id 1000以内待定, 1001以上绑定站点id
                                    if (!env('app.env') == 'development' && $stationId <= 1000) {
                                        AlipayAPI::replyTextMsg('场景id未设定');
                                        exit;
                                    }
                                    // 判断是否存在
                                    $station = db('station')->where(['id' => $stationId])->find();
                                    if (!$station) {
                                        // 回复默认信息
                                        AlipayAPI::replyTextMsg('设备未激活');
                                        exit;
                                    }
                                    // 设备是否在线
                                    if (!swApi::isStationOnline($stationId)) {
                                        AlipayAPI::replyTextMsg('非常抱歉，设备暂时无法连接网络，请稍后再试。或查看附近网点，前往附近的网点进行租借。');
                                        exit;
                                    }
                                    // 发送图文消息一键借伞
                                    $newsData[] = [
                                        'title' => '请点击“一键借伞”按钮',
                                        'description' => "",
                                        'picurl' => 'https://mmbiz.qpic.cn/mmbiz_png/0shRicALAmH0HjURf2SfyRRZMmAbibnvWV6xLCbrNgWiaEg14x3EA6DdXPic4CB9wFEHJuOSxnvUQ9JOVAXfKAhh4g/0?wx_fmt=png',
                                        'url' => "//" . SERVER_DOMAIN . "/rent/$stationId?_t=" . time(),
                                    ];
                                    AlipayAPI::replyPicTextMsg($newsData);
                                    break;


                                default:

                            }
                            break;

                        default:
                    }

                    // 发送回复
                    AlipayAPI::mkAckMsg();
                } else {
                    Log::info('verify message fail');
                    echo 'fail';
                }

            }
        }

    }

}
