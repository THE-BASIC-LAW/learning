<?php namespace app\controller;

use app\common\controller\Base;
use think\Request;

class Rent extends Base
{

    protected $logName = 'rent';

    public function index($id)
    {
        if ($this->request->get('_t') && $this->request->get('_t') < time() - 300) {
            $this->redirect('/user/pay#/oneKeyUse');
        }
        $platform = getPlatform() ? 'alipay' : 'wx';
        $qrcode = db('qrcode')->where('id', $id)->value($platform);
        if (empty($qrcode)) {
            $this->redirect('/user/pay#/oneKeyUse');
        }
        $this->redirect("/user/pay?flag=1&qrcode=$qrcode#/oneKeyUse");
    }
}
