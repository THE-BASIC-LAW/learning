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

class Subscribe extends Base
{

    public function wechat()
    {
        // 关闭模板布局
        $this->view->engine->layout(false);
        return view('subscribe/wechat');
    }

    public function alipay()
    {
        // 关闭模板布局
        $this->view->engine->layout(false);
        return view('subscribe/alipay');
    }

}
