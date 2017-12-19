<?php namespace app\controller;

use app\common\controller\Base;
use think\Request;

class Platform extends Base
{

    protected $logName = 'platform';

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        // 关闭模板布局
        $this->view->engine->layout(false);
    }


    public function UserPay()
    {
        return view('platform/user/pay');
    }


    public function UserCenter()
    {
        return view('platform/user/center');
    }

    public function UserMap()
    {
        $gaode_web_key = env('map.gaode_web_key', '0cee743a9151aecfbf8b9c4b3149e10c');
        $baidu_web_ak = env('map.baidu_web_ak', 's3XlWEDIzNzkekllWj7ZLam03D98ByrP');
        return view('platform/user/map', compact(
            'gaode_web_key',
            'baidu_web_ak'
        ));
    }

}