<?php namespace app\controller;

use app\common\controller\Base;
use app\model\CommonSetting;
use app\model\User;
use app\model\UserInfo;
use app\third\scanQrcode;
use app\third\wxServer;
use EasyWeChat\Core\Exceptions\FaultException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;


/**
 *
 * EasyWechat
 * 文档地址：https://easywechat.org/zh-cn/docs/
 *
 *
 * Class Wechat
 * @package app\controller
 */

class Wechat extends Base
{

    // 微信公众号配置
    protected $options;
    protected $appServer;

    // 设置log目录
    protected $logName = 'wechat';

    protected function _initialize()
    {
        $this->appServer = wxServer::instance()->server;
    }

    public function notice()
    {
        // get请求为验证接口
        if ($this->request->isGet()) {
            if ($this->request->get('echostr')) {
                try {
                    $this->appServer->serve()->send();
                    exit;
                } catch (FaultException $e) {
                    //
                }

            }
            return '';
        }

        // post请求事件消息推送
        if ($this->request->isPost()) {
            $this->appServer->setMessageHandler(function($message){
                Log::info(file_get_contents("php://input"));
                Log::info('message content: ' . print_r($this->appServer->getMessage(), 1));
                switch ($message->MsgType) {

                    // 接收事件推送
                    // https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140454
                    case 'event':
                        return $this->handleEventMessage($message);


                    // 接收普通消息
                    // https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140453
                    // 文本
                    case 'text':
                        return 'text';
                    // 图片
                    case 'image':
                        return 'image';
                    // 语音
                    case 'voice':
                        return 'voice';
                    // 视频
                    case 'video':
                    // 小视频
                    case 'shortvideo':
                    // 地理位置
                    case 'location':
                    // 链接
                    case 'link':
                    default:
                }
            });
            $response = $this->appServer->serve();
            $response->send();
        }
    }

    // 处理事件推送逻辑
    protected function handleEventMessage($message)
    {
        $openId = $message->FromUserName;
        try {
            $user = db('user')->where('openid', $openId)->find();
            $userId = $user['id'];
        } catch (DbException $e) {
            Log::error('db exception, ' . $e->getMessage());
            return '';
        } catch (Exception $e) {
            Log::error('exception, ' . $e->getMessage());
            return '';
        }
        if (!$user) {
            $userModel = new User;
            $userModel->openid = $openId;
            $userModel->platform = PLATFORM_WX;
            $userModel->create_time = date('Y-m-d H:i:s');
            $userModel->unsubscribe = 0;
            $userModel->reply_time = 0;
            $userModel->save();
            $userId = $userModel->id;
            Log::info("new user, id: $userId , openid: $openId");

            // 获取用户信息
            try {
                $wxUser = wxServer::instance()->user->get($openId);
                $wxUser->id = $userId;
            } catch (\Exception $e) {
                Log::error('get wechat user info fail, exception message: ' . $e->getMessage());
                return '';
            }

            // 新增用户信息
            try {
                model("UserInfo")->addUser($wxUser);
            } catch (\Exception $e) {
                Log::error('save wechat user info fail, exception message: ' . $e->getMessage());
                return '';
            }

        }
        switch ($message->Event) {
            case 'subscribe':
                if (substr($message->EventKey, 0, 8) == 'qrscene_') {
                    // 未关注的用户扫了添加维护人员的二维码
                    if (!is_numeric(substr($message->EventKey, 8))) {
                        $msg  = "感谢关注".DEFAULT_TITLE."\n";
                        $msg .= "<a href='http://" . SERVER_DOMAIN . "/user/center#/useFlow'>了解".DEFAULT_TITLE."使用流程</a>";
                        return $msg;
                    }
                    $sceneId = substr($message->EventKey, 8) + 0;
                    return scanQrcode::replyMessage($sceneId);
                } else {
                    $msg  = "感谢关注".DEFAULT_TITLE."\n";
                    $msg .= "<a href='http://" . SERVER_DOMAIN . "/user/center#/useFlow'>了解".DEFAULT_TITLE."使用流程</a>";
                    return $msg;
                }

            case 'unsubscribe':
                Log::info('unsubscribe event, openid: ' . $openId);
                db('user')->update(['id' => $userId, 'unsubscribe' => 1]);
                exit;

            case 'SCAN':
                /**
                 * 先判断$sceneId是否其他业务
                 */
                if (isApplyInstallManSceneId($message->EventKey)) {
                    $this->_applyInstallMan($userId);
                    return '已申请维护人员权限';
                }
                // 是否维护人员
                $isMaintainUser = (new CommonSetting())->isMaintainMan($userId);
                return scanQrcode::replyMessage($message->EventKey, $isMaintainUser);

            case 'TEMPLATESENDJOBFINISH':
                Log::info("template id: {$message->MsgID} , result: {$message->Status}");
                break;

            case 'LOCATION':
                break;
            default:
                # code...
                break;
        }

    }


    // 申请维护人员
    private function _applyInstallMan($uid)
    {
        (new CommonSetting())->applyInstallMan(UserInfo::get($uid));
    }
}
