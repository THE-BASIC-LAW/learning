<?php
/**
 * Created by PhpStorm.
 * User: dlq
 * Date: 17-12-4
 * Time: 下午4:29
 */

namespace app\controller\cp;


use app\controller\Cp;
use app\lib\Api;
use think\Log;
use think\Request;

//use page\page;

class OrderCp extends Cp
{
    // 关联的Tradelog模型
    protected $shop_station = null;

    public function __construct(Request $request = null){
        parent::__construct($request);
        $this->trade_log = model('Tradelog');
    }

    public function lists(){
        $access_shops  = null;
        $access_cities = null;
        $borrow_shop_station_infos = [];
        $return_shop_station_infos = [];

        if (!$this->auth->globalSearch) {
            $access_cities = $this->auth->getAccessCities();
            $access_shops = $this->auth->getAccessShops();
        }

        // 转换商铺名称为商铺id
        if (isset($borrow_shop_station_title)) {
            $borrow_shop_station_infos = model('ShopStation')
                ->where('title', 'like', '%'.$borrow_shop_station_title.'%')
                ->select();
            $_GET['borrow_shop_sid'] = array_column($borrow_shop_station_infos, 'id');
        }
        if (isset($return_shop_station_title)) {
            $return_shop_station_infos = model('ShopStation')
                ->where('title', 'like', '%'.$return_shop_station_title.'%')
                ->select();
            $_GET['return_shop_sid'] = array_column($return_shop_station_infos, 'id');
        }


        $orders = model('Tradelog')->searchOrder($_GET, RECORD_LIMIT_PER_PAGE, $access_cities, $access_shops);

        $params = [
            'orders'  => $orders,
            'borrow_shop_station_info' => $borrow_shop_station_infos,
            'return_shop_station_info' => $return_shop_station_infos,
        ];
        !isset($status) && $params['status'] = '';
        !isset($user_id) && $params['user_id'] = '';
        !isset($order_id) && $params['order_id'] = '';
        !isset($platform) && $params['platform'] = '';
        !isset($err_status) && $params['err_status'] = '';
        !isset($user_openid) && $params['user_openid'] = '';
        !isset($umbrella_id) && $params['umbrella_id'] = '';
        !isset($borrow_end_time) && $params['borrow_end_time'] = '';
        !isset($return_end_time) && $params['return_end_time'] = '';
        !isset($usefee_situation) && $params['usefee_situation'] = '';
        !isset($borrow_start_time) && $params['borrow_start_time'] = '';
        !isset($return_start_time) && $params['return_start_time'] = '';
        !isset($borrow_station_id) && $params['borrow_station_id'] = '';
        !isset($return_station_id) && $params['return_station_id'] = '';
        !isset($borrow_shop_station_title) && $params['borrow_shop_station_title'] = '';
        !isset($return_shop_station_title) && $params['return_shop_station_title'] = '';
        $this->assign($params);
        return $this->fetch();
    }

    public function orderDetail(){
        $order_id = $_GET['order_id'];
        $order    = $this->trade_log->get($order_id);
        $zhima_order = [];

        $order['lastupdate'] = date('Y-m-d H:i:s', $order['lastupdate']);
        $message = unserialize($order['message']) ? unserialize($order['message']) : [];

        $manually_return_time = isset($message['manually_return_time']) ? $message['manually_return_time'] : NULL;
        $message['manually_return_time'] = date("Y-m-d H:i:s" , $manually_return_time);

        if($order['platform'] == PLATFORM_ZHIMA) {
            $zhima_order = model('TradeZhima')->get($order_id);
        }
        $this->assign([
            'order'       => $order,
            'message'     => $message,
            'zhima_order' => $zhima_order,
        ]);
        return $this->fetch();
    }

    public function buyerDetail(){
        $uid = $_GET['uid'];
        $user = model('User')->get($uid);
        $user['headimg'] = model('UserInfo')->get($uid)['headimgurl'];
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function returnDeposit(){
        extract(input());
        if(isset($submit)){
            $res = $this->admin->returnDeposit(input());
            if(!$res){
                Api::output([], 1, '订单号错误或非可退款订单');
            }else{
                Api::output([], $res['errcode'], $res['errmsg']);
            }
        }
        $order = $this->trade_log->get($order_id);
        $stations = db('Station')->field('id, title')->select();
        $borrow_station = $order['borrow_station'];
        $order['message'] = unserialize($order['message']);
        $this->assign([
            'order'          => $order,
            'stations'       => $stations,
            'borrow_station' => $borrow_station,
        ]);
        return $this->fetch();
    }

    public function lostOrderFinish(){
        $order_id = $_POST['order_id'];
        Log::info('order id is : ' . $order_id);
        $res = $this->admin->lostOrderFinish($order_id);
        Api::output([], $res['errcode'], $res['errmsg']);
    }
}