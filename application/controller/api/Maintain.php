<?php namespace app\controller\api;

use app\common\controller\Base;
use app\lib\Api;
use app\model\CommonSetting;

class Maintain extends Base
{
    public function query()
    {

        $this->_checkAuth();

        switch ($this->request->post('type')) {
            case 'search_shop':
                $shopName = $this->request->post('shop_name');
                break;

            default:
        }

        Api::fail(1, '没有结果');

    }

    private function _checkAuth()
    {
        if (!(new CommonSetting())->isMaintainMan(session('uid'))) {
            Api::fail(1, '未授权');
        }
    }
}