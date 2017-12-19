<?php namespace app\controller;

use app\common\controller\Base;

class Api extends Base
{
    public function index()
    {
		// 判断登录
		$GLOBALS['act'] = $this->request->param('act');
		$GLOBALS['opt'] = $this->request->param('opt');

		$event = controller($GLOBALS['act'] . 'Api', "controller\api");
		return $event->$GLOBALS['opt']($this->request);
    }
}
