<?php
define("SERVERIP", "127.0.0.1");
define("SERVER_DOMAIN",  "ts.ddzh.com.cn");
define("WX_AUTH_DOMAIN", "ts.ddzh.com.cn");
define("ENV_DEV", true);

define("ROOT", "http://" . SERVER_DOMAIN . "/" );

define("DEFAULT_TITLE", "大地走红共享伞");
define("DEFAULT_STATION_NAME", DEFAULT_TITLE . "网点");

define( "DDZH", __DIR__.'/../public/'); 

//=========== 订单字段refundno的取值 ===========//
define("ORDER_NOT_REFUND", -1); //直接账户内支付,无法微信退款
define("ORDER_ALL_REFUNDED", -2); //订单已全部退完
define("ORDER_ZHIMA_NOT_REFUND", -3); //芝麻信用订单, 不能用于退款
//===========================================//

//==============以下状态均为支付状态==============//
define("ORDER_STATUS_WAIT_PAY", 0); //订单未支付
define("ORDER_STATUS_PAID", 1); //支付成功(微信,支付宝等)
define("ORDER_STATUS_RENT_CONFIRM", 2); //设备端确认借出
define("ORDER_STATUS_RETURN", 3); //归还状态
define("ORDER_STATUS_RENT_CONFIRM_FIRST", 5); //第一次确认，需等待第二次确认
define("ORDER_STATUS_RETURN_MANUALLY", 7);  // 借出失败, 管理员后台手动撤销订单退回押金

define("ORDER_STATUS_NETWORK_NO_RESPONSE", 64); //终端响应:网络超时
define("ORDER_STATUS_LAST_ORDER_UNFINISHED", 65); //上一个订单未完成
define("ORDER_STATUS_SYNC_TIME_FAIL", 66); //同步时间异常
define("ORDER_STATUS_POWER_LOW", 67); //终端电量不足
define("ORDER_STATUS_MOTOR_ERROR", 68); //终端借出时发生故障
define("ORDER_STATUS_NO_UMBRELLA", 70); //终端没有合适的雨伞(没有伞,或者伞所在槽位被锁)
define("ORDER_STATUS_TIMEOUT_NOT_RETURN", 92);// 租金已扣完, 用户没有归还
define("ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND", 93); // 归还失败, 管理员手动退押金
define("ORDER_STATUS_RETURN_EXCEPTION_SYS_REFUND", 94); // 归还失败, 雨伞状态异常(借出后同步), 系统自动归还退款
define("ORDER_STATUS_TIMEOUT_REFUND", 96); //超时自动退款
define("ORDER_STATUS_RENT_NOT_FETCH", 97); // 借出未拿走
define("ORDER_STATUS_TIMEOUT_CANT_RETURN", 98);// 租金已扣完, 用户已经归还
define("ORDER_STATUS_RENT_NOT_FETCH_INTERMEDIATE", 99); // 借出未拿走(中间态)
define("ORDER_STATUS_LOSS", 100); // 用户登记遗失

//===== 异常支付:支付金额+余额<应支付总额 ====//
define("ORDER_STATUS_PAID_NOT_ENOUGH_EXCEPTION", 102);

//======== 订单显示类型，仅作后台显示用途 =====//
define("ORDER_LIST_ALL_BORROW", 1); //借出(包括借出未归还和已归还)
define("ORDER_LIST_NOT_RETURN", 2); //借出未归还
define("ORDER_LIST_RETURNED", 3); //已归还
define("ORDER_LIST_EXCEPTION", 4); //异常
//=======================================//

// 退款记录状态
define("REFUND_STATUS_REQUEST", 1); //退款申请
define("REFUND_STATUS_DONE", 2); //退款完成

define("RECORD_LIMIT_PER_PAGE", 10);

// 客服电话
define('CUSTOMER_SERVICE_PHONE', '400-826-7388');



//=========== PLATFORM ==============//
define("PLATFORM_NO_SUPPORT", -1);
define("PLATFORM_WX", 0);
define("PLATFORM_ALIPAY", 1);
define("PLATFORM_ZHIMA", 2);
define("PLATFORM_WEAPP", 3);

// umbrella status for inside or outside of station
define("UMBRELLA_INSIDE", 0);
define("UMBRELLA_OUTSIDE", 1);
define("UMBRELLA_OUTSIDE_SYNC", 2); // 借出状态下却被同步, 属于异常状态但可归还
define("UMBRELLA_LOSS", 3); // 雨伞已遗失

// 芝麻信用订单状态
// 1. 先创建
// 2. 归还后进入结算
// 3. 结算后去查询扣款是否成功
// 4. 扣款结算成功, 订单完结
// 5. 等待取消
// 6. 取消成功
// 7. 芝麻订单结算扣款失败, 等待查询重试, 重试时间间隔较长
// 若出现负面记录需要撤销, 需联系芝麻信用小二处理
// 人工退款, 需要进入商户后台查询订单进行退款
define("ZHIMA_ORDER_CREATE", 1);
define("ZHIMA_ORDER_COMPLETE_WAIT", 2);
define("ZHIMA_ORDER_QUERY_WAIT", 3);
define("ZHIMA_ORDER_COMPLETE_SUCCESS", 4);
define("ZHIMA_ORDER_CANCEL_WAIT", 5);
define("ZHIMA_ORDER_CANCEL_SUCCESS", 6);
define("ZHIMA_ORDER_PAY_FAIL_QUERY_RETRY", 7);

define("ADMIN_SESSION_EXPIRED_TIME", 60*60);
define("ADMIN_LOGIN_ERROR_NUMBER", 10);
define("ADMIN_USER_STATUS_DELETED", -1);
define("ADMIN_USER_STATUS_APPLIED", 0);
define("ADMIN_USER_STATUS_NORMAL", 1);
define("ADMIN_USER_STATUS_LOCKED", 2);
define("ADMIN_USER_STATUS_REFUSE", 3);

define("ADMIN_CITY_STATUS_APPLIED", 0);
define("ADMIN_CITY_STATUS_NORMAL", 1);

define("DEFAULT_BIG_HEARTBEAT", 3600); // 心跳频率

define("UMBRELLA_DEPOSIT_DIFF", 10); // 押金差额, 最少的允许押金=默认押金-押金差额, 则若低于允许的最少押金, 需重新补足押金至默认押金


define("STATION_CHECK_UPDATE_DELAY", 60*60); // 默认机器检查更新时间
define("STATION_HEARTBEAT", 180); // 心跳频率, 单位秒
define("UMBRELLA_SYNC_TIME", 30*60); //机器同步雨伞默认时间
define("UMBRELLA_SLOT_INTERVAL", 3); //机器逻辑槽位与物理槽位的差值3, 物理1号槽位, 逻辑4号槽位


define("WALLET_TYPE_PREPAID", 1);           //钱包明细，充值
define("WALLET_TYPE_PAID", 2);              //钱包明细，支付
define("WALLET_TYPE_REQUEST", 3);           //钱包明细，提现申请
define("WALLET_TYPE_WITHDRAW", 4);          //钱包明细，提现到账
define("WALLET_TYPE_REFUND", 5);            //钱包明细，退款
define("WALLET_TYPE_ZHIMA_PAID", 6);        //钱包明细，芝麻支付
define("WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED", 7);    //钱包明细，芝麻支付待确认

// 后台登录相关
define("SUPER_ADMINISTRATOR_ROLE_ID", 1);	// 超级管理员角色ID

// 错误代码, 供返回接口使用
define(	"ERR_NORMAL", 0 );
define(	"ERR_PARAMS_INVALID", 1000 ); // 1001 弃用, 改为1000 表示参数错误
define(	"ERR_REQUEST_FAIL", 1002 ); // 请求其他服务器失败
define(	"ERR_SERVER_DB_FAIL", 1003 );

// 终端升级程序路径
define("SOFT_FILE_PATH", DDZH .  '../device_upgrade/');
// 本地图片储存位置
define('UPLOAD_IMAGE_ROOT_DIR', DDZH . 'images/upload/');
// 本地存储文件的相对位置(含域名的)
define('UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN', ROOT . 'images/upload');

// 机器同步使用的错误类型
define( "ERR_STATION_NEED_LOGIN", 6001 );  //站点未登录,需要重新登录
define( "ERR_STATION_NEED_SYNC_LOCAL_TIME", 6002 );  //终端需要校时
define( "ERR_STATION_UPGRADE_FILENAME_NOT_EXISTED", 6061 );  //升级软件时,请求缺少文件名或者文件名不存在
define( "ERR_STATION_UPGRADE_SERVER_FILE_NOT_EXISTED", 6062 );  //升级软件时,服务器上的文件不存在
define( "ERR_STATION_UPGRADE_BYTE_NUMBER_NOT_EXISTED", 6063 );  //升级软件时,缺少字节数量(长度)
define( "ERR_STATION_UPGRADE_SOFT_VERSION_MISMATCH", 6063 );  //升级软件时,软件版本不匹配
define( "ERR_STATION_UPGRADE_FILENAME_MISMATCH", 6064 );  //升级软件时,文件名不匹配

// 百度地图
define( "BAIDU_MAP_AK", 'RA2LUlc0LrFtm5SooIgGqh2bCTMGSmo0'); //服务器端
define( "BAIDU_MAP_JS_AK", '3hQER1VQK7rkHG80IZ5MYRaptLxuZRMx'); //浏览器端
define( "GEOTABLE_ID", 166391 );

// 数据统计模块
define("BORROW_SUCCESS_ORDER", 1);
define("RETURN_SUCCESS_ORDER", 2);
define("BORROW_SUCCESS_ORDER_RATE", 3);
define("RETURN_SUCCESS_ORDER_RATE", 4);
