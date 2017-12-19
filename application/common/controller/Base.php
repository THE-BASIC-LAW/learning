<?php

namespace app\common\controller;

use think\Controller;
use think\Log;
use think\Request;

class Base extends Controller
{

    /**
     * @var string 设置记录log的目录
     */
    protected $logName = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        // log初始化 根目录下logs目录
        $logName = $this->logName ? : 'index';
        Log::init(['path' => ROOT_PATH . '/logs/' . $logName . '/']);
    }
}
