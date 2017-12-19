<?php
namespace app\controller;

use app\common\controller\Base;

class Cp extends Base
{
    protected $admin = null;

    protected $auth  = null;

    public function _initialize()
    {
		// 判断登录
		$this->admin  = model('Admin', 'logic');
		$this->auth   = model('\app\lib\Auth');
		$this->assign(input());
		$GLOBALS = $GLOBALS + input();
        $GLOBALS['do'] = isset($GLOBALS['do']) ? $GLOBALS['do'] : '';
		if (!$this->admin->isLogin() && $GLOBALS['act'] != 'login') {
			return $this->redirect("/cp/login/index");
		}

		if (!$this->request->param('do')) {
			$GLOBALS['do'] = '';
		}
		$is_access_action = $this->auth->isAuthorizedUrl($GLOBALS['act'], $GLOBALS['opt'], $GLOBALS['do'], $GLOBALS['jjsan_nav_tree']);
		if (!$is_access_action) {
			return $this->redirect('/noAuth');
		}
    }
}
