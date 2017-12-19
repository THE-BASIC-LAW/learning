<?php namespace app\controller;

use app\common\controller\Base;
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
use app\model\User;
use app\model\UserInfo;
use app\model\WalletStatement;
use app\third\baiduLbs;
use app\third\wxServer;
use think\Controller;
use think\Db;
use think\Log;
use think\Request;

/**
 * !!!这里只接受测试服务器上运行的代码
 * !!!这里只接受测试服务器上运行的代码
 * !!!这里只接受测试服务器上运行的代码
 */
class Test extends Base
{

    public function _initialize()
    {
        if (env('app.env') != 'development') {
            echo 'need development environment';
            exit;
        }
    }

    // 空操作重定向到首页
    public function _empty()
    {
        $this->redirect('/');
    }

    // 添加session
    // url: /test/addsession?uid=2
    public function getAddSession()
    {
        $uid = $this->request->get('uid') ? : 1;
        session('uid', $uid);
        return "用户uid:$uid session添加成功";
    }

    // 添加维护人员权限
    // url: /test/addmaintain?uid=2
    public function getAddMaintain()
    {
        $uid = $this->request->get('uid') ? : 1;
        $user = UserInfo::get($uid);
        if (empty($user)) {
            return 'uid: ' . $uid . ' 没有用户信息';
        }
        (new CommonSetting())->addInstallMan($user);

        (new CommonSetting())->isMaintainMan($uid);
        return '添加维护人员uid: ' . $user->id;
    }



    public function getWechatUrl()
    {
        $qrcode = wxServer::instance()->qrcode;
        $result = $qrcode->temporary($this->request->get('id') ? : 1001 , 6*24*3600);
        return $result->url;
    }

    public function getBaiduLbs()
    {
        $ret = baiduLbs::searchNearbyPOI(['location' => '116.4321,38.76623']);
        dump($ret);
    }

    public function getTest()
    {
    }
}
