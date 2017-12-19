<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\Route;

Route::get('/', function(){
		if(PHP_SAPI == 'cli'){
			//cli 模式直接返回
			return ;
		}else{
			return 'index page';
		}
	});


// 消息推送相关(芝麻信用也走这里)
Route::rule('wechat/notice', 'app\controller\Wechat@notice', 'GET|POST');
Route::rule('alipay/notice', 'app\controller\Alipay@notice', 'GET|POST');

// 支付回调相关
Route::rule('callback/wechatPay', 'app\controller\Callback@WechatPay', 'POST');
Route::rule('callback/alipayPay', 'app\controller\Callback@AlipayPay', 'POST');

// 借伞
Route::get('rent/:id', 'app\controller\Rent@index');

// 用户端授权页面
Route::get('user/oauthWechat', 'app\controller\Oauth@wechat');
Route::get('user/oauthAlipay', 'app\controller\Oauth@alipay');
// 维护人员授权
Route::get('maintain/oauth', 'app\controller\Oauth@wechatForMaintain');

// 提示关注微信公众号页面，生活号页面
Route::get('subscribe/wechat', 'app\controller\Subscribe@wechat');
Route::get('subscribe/alipay', 'app\controller\Subscribe@alipay');

// 通信
Route::post('sync', 'Sync/dispatch');

// 描点维护
Route::get('maintain/station/:id/init', 'app\controller\Maintain@init');
Route::get('maintain/station/:id/manage', 'app\controller\Maintain@manage');
Route::get('maintain/station/:id/slot_mgr', 'app\controller\Maintain@slotMgr');
Route::get('maintain/station/:id/replace', 'app\controller\Maintain@replace');
Route::post('maintain/station/:id/add_shop', 'app\controller\Maintain@addShop');
Route::post('maintain/station/:id/slot_mgr', 'app\controller\Maintain@slotMgrHandle');
Route::post('maintain/station/:id/replace', 'app\controller\Maintain@replaceHandle');
Route::post('maintain/station/:id/remove', 'app\controller\Maintain@removeShopStation');

// API相关
Route::post('api/common/get_province_info', 'app\controller\api\Common@getProvinceInfo');
Route::post('api/common/get_city_info', 'app\controller\api\Common@getCityInfo');
Route::post('api/common/get_area_info', 'app\controller\api\Common@getAreaInfo');
Route::post('api/common/get_all_shop_type', 'app\controller\api\Common@getAllShopType');
Route::post('api/common/get_all_shop_locate', 'app\controller\api\Common@getAllShopLocate');

Route::post('api/maintain/query', 'app\controller\api\Maintain@query');

// 前端页面
Route::get('user/pay', 'app\controller\Platform@UserPay');
Route::get('user/map', 'app\controller\Platform@UserMap');
Route::get('user/center', 'app\controller\Platform@UserCenter');

// 前端API
Route::post('api/platform/user_info', 'app\controller\api\Platform@userInfo');
Route::post('api/platform/get_shops', 'app\controller\api\Platform@getShops');
Route::post('api/platform/filter', 'app\controller\api\Platform@filter');
Route::post('api/platform/order_data', 'app\controller\api\Platform@orderData');
Route::post('api/platform/wallet', 'app\controller\api\Platform@wallet');
Route::post('api/platform/wallet_detail', 'app\controller\api\Platform@walletDetail');
Route::post('api/platform/loss_handle', 'app\controller\api\Platform@lossHandle');
Route::post('api/platform/refund', 'app\controller\api\Platform@refund');
Route::post('api/platform/get_station_info', 'app\controller\api\Platform@getStationInfo');
Route::post('api/platform/orders', 'app\controller\api\Platform@orders');
Route::post('api/platform/order_status', 'app\controller\api\Platform@orderStatus');
Route::post('api/platform/switch_role', 'app\controller\api\Platform@switchRole');
Route::post('api/platform/get_oauth_url', 'app\controller\api\Platform@getOauthUrl');
Route::post('api/platform/borrow', 'app\controller\api\Platform@borrow');
Route::post('api/platform/get_wechat_jsapi', 'app\controller\api\Platform@getWechatJsapi');
Route::post('api/platform/convert_gps_to_baidu', 'app\controller\api\Platform@convertGpsToBaidu');
Route::post('api/platform/change_baidu_coordinates_to_gaode', 'app\controller\api\Platform@changeBaiduCoordinatesToGaode');

// 测试环境功能代码
Route::controller('test', 'test');

// 后台相关
Route::rule(':mod/:act/:opt', ':mod.:act_:mod/:opt');
Route::rule('/noAuth', function(){return view('common/noAuth');});// 无权限页

Route::get('cp', function(){
    return redirect('cp/admin/index');
});
