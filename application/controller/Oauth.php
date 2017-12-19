<?php namespace app\controller;

use app\common\controller\Base;
use app\model\User;
use app\third\alipay\AlipayAPI;
use app\third\wxServer;

class Oauth extends Base
{

    protected $logName = 'oauth';

    // 微信公众号网页登录授权
    public function wechat()
    {
        $session = cookie('session');
        $uid = model('UserSession')->getUidBySession($session);
        // uid为空说明状态过期
        if (empty($uid)) {
            $wxApp = wxServer::instance();
            $oauth = $wxApp->oauth;
            //url没有带参数时
            if ($this->request->url() == $this->request->baseUrl()) {
                return $oauth->redirect()->send();
            } else {
                //url有带参数时
                $user = $oauth->user();
                $userInfo = User::get(['openid' => $user->id]);
                if (empty($userInfo) || $userInfo->unsubscribe == 1) {
                    // 未关注过的用户，或者取消关注的用户
                    header('Location: /subscribe/wechat');
                    exit;
                }
                $newSession = model('UserSession')->addSession($userInfo->id);
                cookie('session', $newSession, ['path' => '/user', 'expire' => 3600]);
            }

        }
        header('Location:'.cookie('target_url'));
        exit;
    }

    // 支付宝生活号网页登录授权
    public function alipay()
    {
        $session = cookie('session');
        $uid = model('UserSession')->getUidBySession($session);
        // uid为空说明状态过期
        if (empty($uid)) {
            AlipayAPI::initialize();
            $response = AlipayAPI::getOAuthToken('auth_user');
            if ($openid = $response->user_id) {
                $userInfo = User::get(['openid' => $openid]);
                if (empty($userInfo)) {
                    // 不存在的用户
                    header('Location: /subscribe/alipay');
                    exit;
                }
                $newSession = model('UserSession')->addSession($userInfo->id);
                cookie('session', $newSession, ['path' => '/user', 'expire' => 3600]);
            }
        }
        header('Location:'.cookie('target_url'));
        exit;
    }

    // 这个是给维护人员登录授权的
    public function wechatForMaintain()
    {
        $uid = session('uid');
        if (empty($uid)) {
            $wxApp = wxServer::instance();
            $oauth = $wxApp->oauth;
            //url有带参数时
            $user = $oauth->user();
            $userInfo = User::get(['openid' => $user->id]);
            if (empty($userInfo) || $userInfo->unsubscribe == 1) {
                // 未关注过的用户，或者取消关注的用户
                header('Location: /subscribe/wechat');
                exit;
            }
            session('uid', $userInfo['id']);
        }
        header('Location: ' . $this->request->get('target_url'));
        exit;
    }
}
