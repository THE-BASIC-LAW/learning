<?php
namespace app\validate;

use think\Validate;

class Login extends Validate
{
    protected $rule = [
        'adminname'  => 'require|max:20',
        'password'   => 'require|max:20',
		'captcha|验证码'=>'require|captcha',
		'__token__'	=>	'require|token',
    ];

    protected $message = [
        'adminname.require'  =>  '用户名必填',
        'adminname.max'  =>  '用户名过长',
        'password.require' =>  '邮箱格式错误',
        'password.max' =>  '密码过长',
		'__token__.require' => '缺少关键参数',
    ];

    protected $scene = [
        'add'   =>  ['adminname','password', 'captcha'],
        'create'  =>  ['captcha'],
		'logout'=>  ['__token__'],
    ];
}
