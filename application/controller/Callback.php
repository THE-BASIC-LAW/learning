<?php namespace app\controller;

use app\common\controller\Base;
use app\third\alipay\AlipayAPI;
use app\third\wxServer;
use think\Log;


/**
 *
 * class Callback  处理支付回调
 * @package app\controller
 */

class Callback extends Base
{

    // 设置log目录
    protected $logName = 'callback';


    public function WechatPay()
    {
        Log::notice('wechat post: ' . print_r(input('post.'), true));
        $payment = wxServer::instance()->payment;
        try {
            $response = $payment->handleNotify(function($notify, $successful){

                $orderId = $notify['out_trade_no'];
                $paid = round($notify['total_fee']/100, 2);
                model('paid', 'logic')->handle($orderId, $paid);
                return true;
            });

            $response->send();
        } catch (\Exception $e) {
            Log::alert('handle exception: ' . $e->getMessage());
            exit;
        }

    }

    public function AlipayPay()
    {
        Log::notice('alipay post: ' . print_r(input('post.'), true));
        // 验证支付消息
        AlipayAPI::initialize();
        if (!AlipayAPI::verifyPayNotify()) {
            echo 'fail';
            exit;
        }

        // 处理消息
        // 说明：https://docs.open.alipay.com/203/105286/
        // TRADE_SUCCESS: 商户签约的产品支持退款功能的前提下，买家付款成功
        // TRADE_FINISHED: 商户签约的产品不支持退款功能的前提下，买家付款成功 或者
        // 商户签约的产品支持退款功能的前提下，交易已经成功并且已经超过可退款期限。
        if (in_array($this->request->get('trade_status'), ['TRADE_FINISHED', 'TRADE_SUCCESS'])) {
            $orderId = $this->request->get('out_trade_no');
            $paid = $this->request->get('total_amount');
            model('paid', 'logic')->handle($orderId, $paid);
        }

        echo 'success';
        exit;
    }

    public function ZhimaPay()
    {
        $biz_content = $this->request->get('biz_content');
        $result = json_decode($biz_content, true);

        header('location: /user/center?orderid='.$result['out_order_no'].'#/afterPay');
    }
}
