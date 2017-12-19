<?php namespace app\logic;

use app\third\alipay\AlipayAPI;
use app\third\wxServer;
use think\Log;

/**
 * 模板消息
 *
 * Class TplMsg
 *
 * @package app\logic
 */
class TplMsg
{
    /**
     * 平台类型 依次是：
     * 微信 支付宝 微信小程序 支付宝小程序
     */
    const TPL_PLATFORM_WECHAT  = 1;
    const TPL_PLATFORM_ALIPAY  = 2;
    const TPL_PLATFORM_WEAPP   = 3;
    const TPL_PLATFORM_ALIMINI = 4;

    /**
     * 模板消息类型 依次是：
     * 租借成功通知
     * 租借失败通知 (借伞失败通知，后面会改名)
     * 归还成功通知
     * 提现申请通知
     * 归还提醒
     * 遗失处理通知
     * 费用退还通知
     */
    const MSG_TYPE_BORROW_SUCCESS = 1;
    const MSG_TYPE_BORROW_FAIL    = 2;
    const MSG_TYPE_RETURN_SUCCESS = 3;
    const MSG_TYPE_WITHDRAW_APPLY = 4;
    const MSG_TYPE_RETURN_REMIND  = 5;
    const MSG_TYPE_LOSE_UMBRELLA  = 6;
    const MSG_TYPE_REFUND_FEE     = 7;


    const COLOR        = '#173177';
    const REMARK_COLOR = '#173177';

    protected static $platformType;
    protected static $platformStr;
    protected static $messageType;
    protected static $tplId;
    protected static $messageContent;
    protected static $originalData;

    private static function _getPlatformType($data)
    {
        self::$originalData = $data;
        if (substr($data['openid'], 0, 4) == '2088') {
            self::$platformType = self::TPL_PLATFORM_ALIPAY;
            self::$platformStr  = 'alipay';
        } else {
            self::$platformType = self::TPL_PLATFORM_WECHAT;
            self::$platformStr  = 'wechat';
        }
    }

    private static function _getMsgId($messageType)
    {
        switch ($messageType) {
            case self::MSG_TYPE_BORROW_SUCCESS:
                self::$tplId = env(self::$platformStr . '.template_borrow_success');
                break;
            case self::MSG_TYPE_BORROW_FAIL:
                self::$tplId = env(self::$platformStr . '.template_borrow_fail');
                break;
            case self::MSG_TYPE_RETURN_SUCCESS:
                self::$tplId = env(self::$platformStr . '.template_return_success');
                break;
            case self::MSG_TYPE_WITHDRAW_APPLY:
                self::$tplId = env(self::$platformStr . '.template_withdraw_apply');
                break;
            case self::MSG_TYPE_RETURN_REMIND:
                self::$tplId = env(self::$platformStr . '.template_return_remind');
                break;
            case self::MSG_TYPE_LOSE_UMBRELLA:
                self::$tplId = env(self::$platformStr . '.template_lose_umbrella');
                break;
            case self::MSG_TYPE_REFUND_FEE:
                self::$tplId = env(self::$platformStr . '.template_refund_fee');
                break;
            default:
                throw new \Exception('未定义的模板消息类型');
        }
        if (empty(self::$tplId)) {
            throw new \Exception('模板消息对应的模板ID不能为空');
        }
        self::$messageType = $messageType;
    }

    private static function _getWechatTplMsg()
    {
        switch (self::$messageType) {
            case self::MSG_TYPE_BORROW_SUCCESS:
                self::$messageContent = [
                    'touser'      => self::$originalData['openid'],
                    'template_id' => self::$tplId,
                    'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userRecord',
                    'data'        => [
                        'first'    => [
                            'value' => '你好，你已成功租借一把雨伞。',
                            'color' => self::COLOR,
                        ],
                        'keyword1' => [
                            'value' => self::$originalData['borrow_station_name'],
                            'color' => self::COLOR,
                        ],
                        'keyword2' => [
                            'value' => date('Y-m-d H:i:s', self::$originalData['borrow_time']),
                            'color' => self::COLOR,
                        ],
                        'keyword3' => [
                            'value' => self::$originalData['order_id'],
                            'color' => self::COLOR,
                        ],
                        'remark'   => [
                            'value' => '雨伞借出成功，感谢你的使用。',
                            'color' => self::REMARK_COLOR,
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_BORROW_FAIL:
                self::$messageContent = [
                    'touser'      => self::$originalData['openid'],
                    'template_id' => self::$tplId,
                    'url'         => 'http://' . SERVER_DOMAIN . '/user/pay#/oneKeyUse',
                    'data'        => [
                        'first'    => [
                            'value' => '你好，雨伞租借失败了。',
                            'color' => self::COLOR,
                        ],
                        'keyword1' => [
                            'value' => self::$originalData['borrow_station_name'],
                            'color' => self::COLOR,
                        ],
                        'keyword2' => [
                            'value' => date('Y-m-d H:i:s', self::$originalData['borrow_time']),
                            'color' => self::COLOR,
                        ],
                        'remark'   => [
                            'value' => '雨伞借出失败，点击详情重新借伞。',
                            'color' => self::REMARK_COLOR,
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_RETURN_SUCCESS:
                self::$messageContent = [
                    'touser'      => self::$originalData['openid'],
                    'template_id' => self::$tplId,
                    'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userWallet',
                    'data'        => [
                        'first'    => [
                            'value' => '你好，你已成功归还一把雨伞！',
                            'color' => self::COLOR,
                        ],
                        'keyword1' => [
                            'value' => self::$originalData['return_station_name'],
                            'color' => self::COLOR,
                        ],
                        'keyword2' => [
                            'value' => date('Y-m-d H:i:s', self::$originalData['return_time']),
                            'color' => self::COLOR,
                        ],
                        'keyword3' => [
                            'value' => humanTime(self::$originalData['used_time']),
                            'color' => self::COLOR,
                        ],
                        'keyword4' => [
                            'value' => self::$originalData['order_id'],
                            'color' => self::COLOR,
                        ],
                        'remark'   => [
                            'value' => '此次租借产生费用' . self::$originalData['price'] . '，点击详情提取剩余押金。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                            'color' => self::REMARK_COLOR,
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_WITHDRAW_APPLY:
                self::$messageContent = [
                    'touser'      => self::$originalData['openid'],
                    'template_id' => self::$tplId,
                    'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/walletDetail',
                    'data'        => [
                        'first'    => [
                            'value' => '你好，你已发起提现申请！',
                            'color' => self::COLOR,
                        ],
                        'keyword1' => [
                            'value' => self::$originalData['refund'] . '元',
                            'color' => self::COLOR,
                        ],
                        'keyword2' => [
                            'value' => date('Y-m-d H:i:s', self::$originalData['request_time']),
                            'color' => self::COLOR,
                        ],
                        'remark'   => [
                            'value' => '你好！发起提现后，款项将原路退回原支付账户。点击详情查看钱包明细。',
                            'color' => self::REMARK_COLOR,
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_RETURN_REMIND:
                self::$messageContent = [
                    'touser'      => self::$originalData['openid'],
                    'template_id' => self::$tplId,
                    'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userRecord',
                    'data'        => [
                        'first'    => [
                            'value' => '请尽快归还租借的雨伞。',
                            'color' => self::COLOR,
                        ],
                        'keyword1' => [
                            'value' => humanTime(self::$originalData['difftime']),
                            'color' => self::COLOR,
                        ],
                        'keyword2' => [
                            'value' => self::$originalData['usefee'] . '元',
                            'color' => self::COLOR,
                        ],
                        'remark'   => [
                            'value' => '你租借的雨伞已经产生了' . self::$originalData['usefee'] . '元的租借费用，点击详情查看租借记录。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                            'color' => self::REMARK_COLOR,
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_LOSE_UMBRELLA:
                self::$messageContent = [
                    'touser'      => self::$originalData['openid'],
                    'template_id' => self::$tplId,
                    'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userRecord',
                    'data'        => [
                        'first'    => [
                            'value' => '接收到您发起的登记遗失申请，已从押金中扣除费用' . self::$originalData['price'] . '元',
                            'color' => self::COLOR,
                        ],
                        'keyword1' => [
                            'value' => self::$originalData['borrow_station_name'],
                            'color' => self::COLOR,
                        ],
                        'keyword2' => [
                            'value' => self::$originalData['borrow_time'],
                            'color' => self::COLOR,
                        ],
                        'keyword3' => [
                            'value' => self::$originalData['handle_time'],
                            'color' => self::COLOR,
                        ],
                        'keyword4' => [
                            'value' => self::$originalData['order_id'],
                            'color' => self::COLOR,
                        ],
                        'remark'   => [
                            'value' => '感谢您对' . DEFAULT_TITLE . '的支持。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                            'color' => self::REMARK_COLOR,
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_REFUND_FEE:
                self::$messageContent = [
                    'touser'      => self::$originalData['openid'],
                    'template_id' => self::$tplId,
                    'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userWallet',
                    'data'        => [
                        'first'    => [
                            'value' => '你好，你有一笔费用退还信息。',
                            'color' => self::COLOR,
                        ],
                        'keyword1' => [
                            'value' => self::$originalData['orderid'],
                            'color' => self::COLOR,
                        ],
                        'keyword2' => [
                            'value' => self::$originalData['refund'] . '元',
                            'color' => self::COLOR,
                        ],
                        'remark'   => [
                            'value' => '费用已退还至用户中心，点击详情查看余额。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                            'color' => self::REMARK_COLOR,
                        ],
                    ],
                ];
                break;
        }
    }

    private static function _getAlipayTplMsg()
    {
        switch (self::$messageType) {
            case self::MSG_TYPE_BORROW_SUCCESS:
                self::$messageContent = [
                    'to_user_id' => self::$originalData['openid'],
                    'template'   => [
                        'template_id' => self::$tplId,
                        'context'     => [
                            'head_color'  => self::COLOR,
                            'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userRecord',
                            'action_name' => '查看详情',
                            'first'       => [
                                'value' => '你好，你已成功租借一把雨伞。',
                                'color' => self::COLOR,
                            ],
                            'keyword1'    => [
                                'value' => self::$originalData['borrow_station_name'],
                                'color' => self::COLOR,
                            ],
                            'keyword2'    => [
                                'value' => date('Y-m-d H:i:s', self::$originalData['borrow_time']),
                                'color' => self::COLOR,
                            ],
                            'keyword3'    => [
                                'value' => self::$originalData['order_id'],
                                'color' => self::COLOR,
                            ],
                            'remark'      => [
                                'value' => '雨伞借出成功，感谢你的使用。',
                                'color' => self::REMARK_COLOR,
                            ],
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_BORROW_FAIL:
                self::$messageContent = [
                    'to_user_id' => self::$originalData['openid'],
                    'template'   => [
                        'template_id' => self::$tplId,
                        'context'     => [
                            'head_color'  => self::COLOR,
                            'url'         => 'http://' . SERVER_DOMAIN . '/user/pay#/oneKeyUse',
                            'action_name' => '查看详情',
                            'first'       => [
                                'value' => '你好，雨伞租借失败了。',
                                'color' => self::COLOR,
                            ],
                            'keyword1'    => [
                                'value' => self::$originalData['borrow_station_name'],
                                'color' => self::COLOR,
                            ],
                            'keyword2'    => [
                                'value' => date('Y-m-d H:i:s', self::$originalData['borrow_time']),
                                'color' => self::COLOR,
                            ],
                            'remark'      => [
                                'value' => '雨伞借出失败，点击详情重新借伞。',
                                'color' => self::REMARK_COLOR,
                            ],
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_RETURN_SUCCESS:
                self::$messageContent = [
                    'to_user_id' => self::$originalData['openid'],
                    'template'   => [
                        'template_id' => self::$tplId,
                        'context'     => [
                            'head_color'  => self::COLOR,
                            'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userWallet',
                            'action_name' => '查看详情',
                            'first'       => [
                                'value' => '你好，你已成功归还一把雨伞！',
                                'color' => self::COLOR,
                            ],
                            'keyword1'    => [
                                'value' => self::$originalData['return_station_name'],
                                'color' => self::COLOR,
                            ],
                            'keyword2'    => [
                                'value' => date('Y-m-d H:i:s', self::$originalData['return_time']),
                                'color' => self::COLOR,
                            ],
                            'keyword3'    => [
                                'value' => humanTime(self::$originalData['used_time']),
                                'color' => self::COLOR,
                            ],
                            'keyword4'    => [
                                'value' => self::$originalData['order_id'],
                                'color' => self::COLOR,
                            ],
                            'remark'      => [
                                'value' => '此次租借产生费用' . self::$originalData['price'] . '，点击详情提取剩余押金。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                                'color' => self::REMARK_COLOR,
                            ],
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_WITHDRAW_APPLY:
                self::$messageContent = [
                    'to_user_id' => self::$originalData['openid'],
                    'template'   => [
                        'template_id' => self::$tplId,
                        'context'     => [
                            'head_color'  => self::COLOR,
                            'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/walletDetail',
                            'action_name' => '查看详情',
                            'first'       => [
                                'value' => '你好，你已发起提现申请！',
                                'color' => self::COLOR,
                            ],
                            'keyword1'    => [
                                'value' => self::$originalData['refund'] . '元',
                                'color' => self::COLOR,
                            ],
                            'keyword2'    => [
                                'value' => date('Y-m-d H:i:s', self::$originalData['request_time']),
                                'color' => self::COLOR,
                            ],
                            'remark'      => [
                                'value' => '你好！发起提现后，款项将原路退回原支付账户。点击详情查看钱包明细。',
                                'color' => self::REMARK_COLOR,
                            ],
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_RETURN_REMIND:
                self::$messageContent = [
                    'to_user_id' => self::$originalData['openid'],
                    'template'   => [
                        'template_id' => self::$tplId,
                        'context'     => [
                            'head_color'  => self::COLOR,
                            'url'         => 'http://' . SERVER_DOMAIN . '/user/center#/userRecord',
                            'action_name' => '查看详情',
                            'first'       => [
                                'value' => '请尽快归还租借的雨伞。',
                                'color' => self::COLOR,
                            ],
                            'keyword1'    => [
                                'value' => humanTime(self::$originalData['difftime']),
                                'color' => self::COLOR,
                            ],
                            'keyword2'    => [
                                'value' => self::$originalData['usefee'] . '元',
                                'color' => self::COLOR,
                            ],
                            'remark'      => [
                                'value' => '你租借的雨伞已经产生了' . self::$originalData['usefee'] . '元的租借费用，点击详情查看租借记录。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                                'color' => self::REMARK_COLOR,
                            ],
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_LOSE_UMBRELLA:
                self::$messageContent = [
                    'to_user_id' => self::$originalData['openid'],
                    'template'   => [
                        'template_id' => self::$tplId,
                        'context'     => [
                            'head_color'  => self::COLOR,
                            'url'         => 'http://' . SERVER_DOMAIN . '/user/center/#userRecord',
                            'action_name' => '查看详情',
                            'first'       => [
                                'value' => '接收到您发起的登记遗失申请，已从押金中扣除费用' . self::$originalData['price'] . '元',
                                'color' => self::COLOR,
                            ],
                            'keyword1'    => [
                                'value' => self::$originalData['borrow_station_name'],
                                'color' => self::COLOR,
                            ],
                            'keyword2'    => [
                                'value' => self::$originalData['borrow_time'],
                                'color' => self::COLOR,
                            ],
                            'keyword3'    => [
                                'value' => self::$originalData['handle_time'],
                                'color' => self::COLOR,
                            ],
                            'keyword4'    => [
                                'value' => self::$originalData['order_id'],
                                'color' => self::COLOR,
                            ],
                            'remark'      => [
                                'value' => '感谢您对' . DEFAULT_TITLE . '的支持。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                                'color' => self::REMARK_COLOR,
                            ],
                        ],
                    ],
                ];
                break;
            case self::MSG_TYPE_REFUND_FEE:
                self::$messageContent = [
                    'to_user_id' => self::$originalData['openid'],
                    'template'   => [
                        'template_id' => self::$tplId,
                        'context'     => [
                            'head_color'  => self::COLOR,
                            'url'         => 'http://' . SERVER_DOMAIN . '/user/center/#userWallet',
                            'action_name' => '查看详情',
                            'first'       => [
                                'value' => '你好，你有一笔费用退还信息。',
                                'color' => self::COLOR,
                            ],
                            'keyword1'    => [
                                'value' => self::$originalData['orderid'],
                                'color' => self::COLOR,
                            ],
                            'keyword2'    => [
                                'value' => self::$originalData['refund'] . '元',
                                'color' => self::COLOR,
                            ],
                            'remark'      => [
                                'value' => '费用已退还至用户中心，点击详情查看余额。如有疑问，请致电' . CUSTOMER_SERVICE_PHONE . '。',
                                'color' => self::REMARK_COLOR,
                            ],
                        ],
                    ],
                ];
                break;
        }
    }

    private static function _sendMessage()
    {
        switch (self::$platformType) {
            case self::TPL_PLATFORM_WECHAT:
                self::_getWechatTplMsg();
                $app = wxServer::instance();
                $rst = $app->notice->send(self::$messageContent);
                break;
            case self::TPL_PLATFORM_ALIPAY:
                self::_getAlipayTplMsg();
                AlipayAPI::initialize();
                $rst = AlipayAPI::sendTemplateMsg(self::$messageContent);
                break;
            default:
                throw new \Exception('未定义消息模板内容');
        }
        Log::info('template content : ' . print_r(self::$messageContent, 1) . ', result: ' . print_r($rst, 1));
    }

    public static function send($messageType, $data)
    {
        // 检查平台类型
        self::_getPlatformType($data);

        // 确定模板消息ID
        self::_getMsgId($messageType);

        // 发送模板消息
        self::_sendMessage();
    }
}