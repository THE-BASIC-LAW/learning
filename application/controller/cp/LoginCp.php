<?php namespace app\controller\cp;

use app\controller\Cp;
use think\Session;

class LoginCp extends Cp
{
    /**
     * 登录默认入口
     *
     * @return \think\Response
     */
    public function index()
    {
		if ($this->admin->isLogin()) {
			$this->success('您已登录', '/cp/admin/index');
		}

        return $this->fetch();
    }

	/**
     * 管理员注册页面及注册处理.
     *
     * @return \think\Response
     */
    public function create()
    {

		$data = $this->request->except(['mod', 'act', 'opt']);	//获取请求数据
		if ($data) {
			// 验证验证码
			$result = $this->validate(
				[
					'captcha'=> $this->request->post('captcha'),
				],
				'Login.create'
			);
			if(true !== $result){
				// 验证失败 输出错误信息
				$this->error($result);
			}

			if ($this->admin->register($data)) {
				$this->success('注册成功，请耐心等待', '/cp/login/index');
			}else {
				$this->error('注册失败', $_SERVER['HTTP_REFERER']);
			}
		}
        $this->assign([
            'roles'         => $this->auth->allCanRegisterRoles(),
            'companyArray' => $this->auth->getCompany()
        ]);
		return $this->fetch();
    }

	/**
     * 对登陆进行验证和处理
     *
     * @return \think\Response
     */
    public function save()
    {
		if ($this->admin->isLogin()) {
			$this->success('您已登录', '/cp/admin/index');
		}else {
			$name = $this->request->post('adminname');
			$pwd = $this->request->post('password');

			// 验证用户名和密码格式
			$result = $this->validate(
				$data=[
						'adminname'  => $name,
						'password' => $pwd,
						'captcha'=> $this->request->post('captcha'),
					],
				'Login.add'
			);
			if(true !== $result){
			    // 验证失败 输出错误信息
				$this->error($result);
			}

			if ($this->admin->login($data)) {
			    $this->redirect('cp/admin/index');
			}

	        $this->error('登录失败');
		}
    }

	/**
     * 退出登录
     */
    public function destroy()
    {
		$result = $this->validate(
			$data=[
					'__token__'=> $this->request->post('__token__'),
				],
			'Login.logout'
		);

		if(true !== $result){
			// 验证失败 输出错误信息
			$this->error($result);
		}

		if ($this->request->isPost()) {
			if ($this->admin->isLogin()) {
				Session::clear();
				$this->success('退出成功', '/cp/login/index');
			}else {
				$this->success('您未登录', '/cp/login/index');
			}
		}else {
			$this->error('操作失败');
		}


    }

}
