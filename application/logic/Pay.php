<?php namespace app\logic;

use app\third\alipay\AlipayAPI;
use app\third\wxServer;
use EasyWeChat\Payment\Order;
use think\Log;

class Pay
{
    public function getPayInfo(array $orderInfo)
    {
        switch ($orderInfo['platform']) {

            case PLATFORM_WX:
                $payment = wxServer::instance()->payment;
                $attr = [
                    'trade_type' => 'JSAPI',
                    'body' => $orderInfo['subject'],
                    'attach' => 'Attach',
                    'out_trade_no' => $orderInfo['orderid'],
                    'total_fee' => round($orderInfo['price']*100),
                    'time_start'   => date("YmdHis"),
                    'time_expire'  => date("YmdHis", time() + 600),
                    'goods_tag'    => "NOTAG",
                    'openid'       => $orderInfo['openid'],
                ];
                $order = new Order($attr);
                $result = $payment->prepare($order);
                if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS') {
                    $prepayId = $result->prepay_id;
                    $params = $payment->configForJSSDKPayment($prepayId);
                    return $params;
                } else {
                    Log::error('wechat payment fail, result:' . print_r($result, 1));
                }
                break;


            case PLATFORM_ALIPAY:
                $request = [
                    'subject' => $orderInfo['subject'],
                    'orderid' => $orderInfo['orderid'],
                    'price' => $orderInfo['price'],
                    'return_url' => 'http://'.SERVER_DOMAIN.'/user/pay?orderid='.$orderInfo['orderid'].'#/afterPay',
                    'notify_url' => 'http://'.SERVER_DOMAIN.'/alipay/pay',
                ];
                AlipayAPI::initialize();
                return AlipayAPI::buildAlipaySubmitForm($request);
                break;


            case PLATFORM_ZHIMA:
                $params = [
                    "invoke_type"       => 'WINDOWS',
                    "invoke_return_url" => "http://" . SERVER_DOMAIN . '/zhima/pay',
                    "out_order_no"      => $orderInfo['orderid'],
                    "product_code"      => "w1010100000000002858", // 信用借还产品码（固定值）
                    "goods_name"        => $orderInfo['subject'],
                    "rent_info"         => $orderInfo['zhima'][2],
                    "rent_unit"         => $orderInfo['zhima'][1],
                    "rent_amount"       => $orderInfo['zhima'][0] ? $orderInfo['zhima'][0] : 0, // 单位元, 精确到小数点后两位
                    "deposit_amount"    => $orderInfo['price'], // 单位元, 精确到小数点后两位
                    "deposit_state"     => 'Y', // 单位元, 精确到小数点后两位
                    "expiry_time"       => $orderInfo['zhima'][3],
                    "borrow_shop_name"  => $orderInfo['borrow_shop_name'] ? : DEFAULT_STATION_NAME,
                ];
                AlipayAPI::initialize();
                return AlipayAPI::getZhimaRentOrderUrl($params);
                break;

            default:

        }
        return false;
    }
}