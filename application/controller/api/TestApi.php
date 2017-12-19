<?php namespace app\controller\api;

use app\common\controller\Base;
use think\Request;

class TestApi extends Base
{
    public function index(Request $request)
    {
		return "this is test api";
    }
}
