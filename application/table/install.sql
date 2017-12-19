DROP TABLE IF EXISTS `pre_ddzh_admin`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_admin` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `adminname` varchar(255) NOT NULL COMMENT '登录名',
 `name` varchar(255) NOT NULL COMMENT '真实姓名',
 `pwd` varchar(255) NOT NULL COMMENT '加密后密码',
 `salt` char(8) NOT NULL COMMENT '加密随机盐值',
 `email` varchar(255) NOT NULL COMMENT '公司邮箱',
 `company` varchar(255) NOT NULL COMMENT '公司名称',
 `role_id` int(11) NOT NULL COMMENT '用户角色',
 `login_error` tinyint(1) NOT NULL COMMENT '登录错误次数 (超过失败次数锁定账户，成功就刷新为0)',
 `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
 `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
 `status` int(11) NOT NULL COMMENT '状态(-1删除，0申请，1申请通过,2账户被锁定,3申请被拒绝)',
 PRIMARY KEY (`id`),
 UNIQUE KEY `adminname` (`adminname`),
 UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='权限控制权限表';

DROP TABLE IF EXISTS `pre_ddzh_admin_log`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_admin_log` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `uid` int(11) NOT NULL COMMENT '管理员id',
 `type` int(11) NOT NULL COMMENT '事件类型',
 `detail` varchar(10000) NOT NULL COMMENT '事件详情记录',
 `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '事件发生时间',
 PRIMARY KEY (`id`),
 KEY `uid` (`uid`),
 KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='管理员事件记录表';

DROP TABLE IF EXISTS `pre_ddzh_admin_session`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_admin_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `session` varchar(255) NOT NULL,
  `update_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间(每次访问操作均更新)',
  `create_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='权限控制权限表';

DROP TABLE IF EXISTS `pre_ddzh_admin_city`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_admin_city` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL COMMENT '管理员id',
  `city` text CHARACTER SET utf8mb4 NOT NULL COMMENT '管理员所负责的城市集合',
  `status` int(11) NOT NULL COMMENT '状态(0 申请中，1通过)',
  `update_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
  `create_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='管理员所负责的城市表' AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `pre_ddzh_admin_role`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_admin_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `role` varchar(255) CHARACTER SET utf8mb4 NOT NULL COMMENT '管理员角色',
  `access` text CHARACTER SET utf8mb4 NOT NULL COMMENT '用户权限json字符串',
  `global_search` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '搜索功能全局权限标志位',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '角色创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='管理员角色表' AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `pre_ddzh_admin_shop`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_admin_shop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL COMMENT '管理员id',
  `shop_id` int(11) NOT NULL COMMENT '管理员负责的商铺id',
  `status` int(11) NOT NULL COMMENT '状态',
  `update_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
  `create_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='管理员所负责的商铺表' AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `pre_ddzh_user`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `openid` varchar(50) NOT NULL comment '用户openid(微信,支付宝等其他平台)',
  `platform` tinyint(1) unsigned NOT NULL DEFAULT '0' comment '0:微信, 1:支付宝',
  `usablemoney` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' comment '账户余额',
  `deposit` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' comment '押金',
  `refund` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' comment '待退款数目',
  `up` int(11) NOT NULL DEFAULT '0' comment '上级id',
  `credit` mediumint(8) unsigned NOT NULL DEFAULT '0' comment '积分',
  `unsubscribe` tinyint(1) NOT NULL COMMENT '该用户是否已经取消关注 1 为取消 0为未取消',
  `eventkey` tinyint(1) NOT NULL DEFAULT '0' comment '判断是否是push引起的scan',
  `create_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '用户注册时间',
  `reply_time` int(11) unsigned DEFAULT 0 NOT NULL COMMENT '最后一次自动回复时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='用户表';

DROP TABLE IF EXISTS `pre_ddzh_user_info`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_user_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(255) NOT NULL COMMENT '微信用户openid',
  `nickname` varchar(255) NOT NULL COMMENT '微信用户昵称',
  `sex` tinyint(1) NOT NULL COMMENT '性别 男1 女2 未知0',
  `city` varchar(255) NOT NULL COMMENT '用户所在城市',
  `province` varchar(255) NOT NULL COMMENT '省份',
  `country` varchar(255) NOT NULL COMMENT '国家',
  `headimgurl` varchar(255) NOT NULL COMMENT '微信用户头像',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `language` varchar(255) NOT NULL COMMENT '用户的语言',
  `subscribe_time` int(11) NOT NULL COMMENT '用户关注时间，为时间戳。如果用户曾多次关注，则取最后关注时间',
  `unionid` varchar(255) NOT NULL COMMENT '只有在用户将公众号绑定到微信开放平台帐号后，才会出现该字段',
  `remark` varchar(255) NOT NULL COMMENT '公众号运营者对粉丝的备注',
  `groupid` varchar(255) NOT NULL COMMENT '用户所在的分组ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='用户信息表';

DROP TABLE IF EXISTS `pre_ddzh_user_log`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_user_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL COMMENT '用户id',
  `type` int(11) NOT NULL COMMENT '事件类型',
  `detail` varchar(10000) NOT NULL COMMENT '事件详情记录',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '事件发生时间',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='用户事件记录表';

DROP TABLE IF EXISTS `pre_ddzh_station`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_station` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '站点id, 对应微信永久二维码对应的场景id,sceneid,范围1-100000,预留1000以内他用,站点绑定从1001开始',
  `title` varchar(30) NOT NULL DEFAULT '' COMMENT '大地走红伞名称',
  `address` varchar(100) NOT NULL DEFAULT '' COMMENT '大地走红伞地址',
  `mac` varchar(20) NOT NULL  DEFAULT '' COMMENT '机器物理地址 标识一台机器',
  `total` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '总数',
  `usable` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '大地走红伞可借数量',
  `empty` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '大地走红伞可返还数量',
  `slotstatus` char(90) NOT NULL DEFAULT '0' COMMENT '长度为槽位数, 每个槽位一个字符，每位代表卡槽，0表示卡槽正常,其它表示异常',
  `sportstatus` varchar(8) NOT NULL DEFAULT '0' COMMENT '8位数字，每位表示机器的一个地方是否正常',
  `sensor_status_1` varchar(1000) NOT NULL DEFAULT '0' COMMENT '每个槽位两个字符用16进制表示代表8位整数, 槽位间用-相连',
  `sensor_status_2` varchar(1000) NOT NULL DEFAULT '0' COMMENT '每个槽位两个字符用16进制表示代表8位整数, 槽位间用-相连',
  `colorcount` varchar(200) NOT NULL DEFAULT '' COMMENT '大地走红伞里含有颜色－机器个数集合',
  `machine` tinyint(1) NOT NULL DEFAULT '0' COMMENT '机器锁定状态',
  `rssi` tinyint(1) NOT NULL DEFAULT '0' COMMENT '网络型号强度',
  `maincontrol` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '主控板状态 0正常 其它不正常',
  `sync_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '同步时间',
  `bgimg` varchar(100) NOT NULL DEFAULT '' COMMENT '背景图',
  `device_ver` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '机器硬件版本 ',
  `soft_ver` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '机器软件版本',
  `station_setting_id` int(11) unsigned not null default '0' comment '配置策略id',
  `status` varchar(10) NOT NULL DEFAULT '0' COMMENT '这台机器的状况 暂时4位储存,每位对应一种状态: 右通道 左通道 RFID 断电',
  `error_man` text COMMENT '这台机器的维护人员信息',
  `heartbeat_rate` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '心跳存活率, 百分比',
  `power_on_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '今天开机累计时长, 单位秒',
  `voltage` SMALLINT unsigned NOT NULL DEFAULT '0' COMMENT '设备电压值，低于1650时只可归还不可借出',
  `isdamage` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '雨伞状态，0表示电量满，1表示充电中，2表示读不到电量，3表示当前未连接电源',
  `drivemsg` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '模组状态 0正常 1语音休眠 2模组休眠',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mac` (`mac`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='机器站点表' AUTO_INCREMENT=1001;


DROP TABLE IF EXISTS `pre_ddzh_user_role`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_user_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(255) NOT NULL COMMENT '角色名字',
  `status` int(11) NOT NULL COMMENT '该条记录状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户角色表' AUTO_INCREMENT=1 ;


DROP TABLE IF EXISTS `pre_ddzh_shop`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_shop` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '商铺id',
  `name` varchar(255)  NOT NULL COMMENT '商铺名称',
  `province` varchar(128)  NOT NULL COMMENT '所在省份',
  `city` varchar(128)  NOT NULL COMMENT '所在城市',
  `area` varchar(128)  NOT NULL COMMENT '所在区域',
  `locate` varchar(255)  NOT NULL COMMENT '商铺位置',
  `cost` int(11) NOT NULL COMMENT '人均花费',
  `phone` char(20) NOT NULL COMMENT '电话号码',
  `stime` varchar(255) NOT NULL COMMENT '商铺开始营业时间',
  `etime` varchar(255)  NOT NULL COMMENT '商铺结束营业时间',
  `logo` varchar(1000)  NOT NULL COMMENT '商铺logo',
  `carousel` varchar(1000)  NOT NULL COMMENT '商铺轮播图',
  `type` tinyint(4) NOT NULL COMMENT '商铺类型',
  `status` int(11) NOT NULL COMMENT '标识这条记录',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8  COMMENT='商铺表' AUTO_INCREMENT=1;


DROP TABLE IF EXISTS `pre_ddzh_shop_station`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_shop_station` (
 `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
 `shopid` int(11) NOT NULL COMMENT '所属商铺id',
 `station_id` int(11) NOT NULL COMMENT '绑定设备id',
 `lbsid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '百度地图云检索id',
 `title` varchar(30) NOT NULL DEFAULT '' COMMENT '大地走红伞名称',
 `desc` varchar(100) NOT NULL DEFAULT '' COMMENT '描述',
 `address` varchar(100) NOT NULL DEFAULT '' COMMENT '大地走红伞地址',
 `longitude` varchar(15) NOT NULL DEFAULT '' COMMENT '纬度',
 `latitude` varchar(15) NOT NULL DEFAULT '' COMMENT '经度',
 `fee_settings` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '收费策略id',
 `pictext_settings` int unsigned NOT NULL DEFAULT '0' COMMENT '图文推送配置id',
 `seller_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '绑定商户的id',
 `error_man` text NOT NULL COMMENT '这台机器的维护人员信息',
 `status` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '0: 未启用 1: 启用',
 PRIMARY KEY (`id`),
 KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `pre_ddzh_shop_type`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_shop_type` (
 `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '商铺类型id',
 `type` varchar(255) NOT NULL COMMENT '商铺类型名字',
 `logo` varchar(1000) NOT NULL COMMENT '默认商铺logo',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='商铺类型表' AUTO_INCREMENT=1;

DROP TABLE IF EXISTS `pre_ddzh_tradelog`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_tradelog` (
  `orderid` varchar(32) NOT NULL COMMENT '订单id',
  `price` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `platform` tinyint(1) unsigned NOT NULL DEFAULT '0' comment '0:微信, 1:支付宝',
  `openid` varchar(50) NOT NULL COMMENT '微信用户openid',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单状态 cfg.inc.php 定义 1 已支付 2 借出 3 归还 4 提现 5 第一次确认 6 提醒归还 7 手动退押金 10 充电头/线借出 84 异常断电 85 机械手报警 86 雨伞卡住 87 四轴无响应 88 机器工作中 89 主控无确认 90 主控无应答 95 充电头槽口有问题 96 超时自动退款 97 借出失败 98 租金已扣完 100 管理员待支付 101 管理员直接支付 102 异常支付',
  `lastupdate` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后更新时间',
  `baseprice` decimal(8,2) NOT NULL COMMENT '省下来的钱',
  `message` text NOT NULL COMMENT '订单详情',
  `borrow_station` int(32) unsigned NOT NULL DEFAULT '0' COMMENT '借出该雨伞的站点',
  `borrow_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '借出的时间',
  `return_station` int(32) unsigned NOT NULL DEFAULT '0' COMMENT '归还的大地走红伞',
  `return_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '归还的时间',
  `umbrella_id` varchar(32) NOT NULL DEFAULT '0' COMMENT  '雨伞原始ID, 16进制',
  `borrow_station_name` varchar(30) NOT NULL DEFAULT '' COMMENT '借出的大地走红伞名称',
  `return_station_name` varchar(30) NOT NULL DEFAULT '' COMMENT '归还的大地走红伞名称',
  `borrow_shop_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '借出的商铺ID',
  `return_shop_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '归还的商铺ID',
  `borrow_shop_station_id` mediumint(8) unsigned NOT NULL  DEFAULT '0' COMMENT '借出的商铺站点ID',
  `return_shop_station_id` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '归还的商铺站点ID',
  `borrow_city` varchar(20) NOT NULL DEFAULT '' COMMENT '借出时所在的城市',
  `return_city` varchar(20) NOT NULL DEFAULT '' COMMENT '归还时所在的城市',
  `borrow_device_ver` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '借出的机器版本',
  `return_device_ver` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '归还的机器版本',
  `shop_type` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '业态',
  `seller_mode` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '直营/代理',
  `seller_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '代理id',
  `paid` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '用户支付金额',
  `refundno` tinyint(1) NOT NULL DEFAULT '0' COMMENT '这个订单提现的状态 -1 账户余额,无法微信退款 -2退款完毕',
  `refunded` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '退款金额',
  `usefee` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '用户使用完后需要支付的金额',
  `tag` varchar(20) NOT NULL DEFAULT '' COMMENT '商品标签',
  `remind` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '该订单是否执行过提醒用户归还，0表示未提醒，1表示已提醒',
  UNIQUE KEY `orderid` (`orderid`),
  KEY `status` (`status`),
  KEY `uid` (`uid`),
  KEY `platform` (`platform`),
  KEY `borrow_station` (`borrow_station`),
  KEY `borrow_time` (`borrow_time`),
  KEY `return_station` (`return_station`),
  KEY `return_time` (`return_time`),
  KEY `borrow_shop_station_id` (`borrow_shop_station_id`),
  KEY `return_shop_station_id` (`return_shop_station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单详情表';

DROP TABLE IF EXISTS `pre_ddzh_user_statistics_cache`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_user_statistics_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` varchar(255) NOT NULL,
  `platform` int(11) NOT NULL,
  `subscribe_user_count` int(11) NOT NULL,
  `unsubscribe_user_count` int(11) NOT NULL,
  `pay_button_user_count` int(11) NOT NULL,
  `shop_page_user_count` int(11) NOT NULL,
  `user_scan_user_count` int(11) NOT NULL,
  `top_up_success_count` int(11) NOT NULL,
  `top_up_user_count` int(11) NOT NULL,
  `refund_count` int(11) NOT NULL,
  `success_user_count` int(11) NOT NULL,
  `order_count` int(11) NOT NULL,
  `success_fee_user_count` int(11) NOT NULL,
  `success_fee_user_accumulated` int(11) NOT NULL,
  `user_accumulated_old` int(11) NOT NULL,
  `success_user_count_old` int(11) NOT NULL,
  `success_fee_user_count_old` int(11) NOT NULL,
  `subscribe_user_count_new` int(11) NOT NULL,
  `unsubscribe_user_count_new` int(11) NOT NULL,
  `pay_button_user_count_new` int(11) NOT NULL,
  `shop_page_user_count_new` int(11) NOT NULL,
  `user_scan_user_count_new` int(11) NOT NULL,
  `top_up_user_count_new` int(11) NOT NULL,
  `increse_order_user_new` int(11) NOT NULL,
  `success_user_count_new` int(11) NOT NULL,
  `success_user_order_count_new` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='用户统计数据的缓存表';

DROP TABLE IF EXISTS `pre_ddzh_menu`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_menu` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(80) NOT NULL DEFAULT '' COMMENT '商品名称',
  `content` text NOT NULL COMMENT '商品内容, 保存商品图片信息等',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '商品描述',
  `costprice` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '成本价,进货价',
  `discount` decimal(8,2) NOT NULL DEFAULT '1.00' COMMENT '折扣,默认为1,不打折',
  `price` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '售价,租赁物品指的是押金',
  `tag` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '标示位, 雨伞为1',
  `invisible` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态位, 0正常 1软删除',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT '商品表';

DROP TABLE IF EXISTS `pre_ddzh_fee_strategy`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_fee_strategy` (
  `id` int unsigned NOT NULL auto_increment,
  `name` varchar(20) NOT NULL DEFAULT '' COMMENT '收费策略名称',
  `fee` varchar(200) NOT NULL DEFAULT '' COMMENT '收费策略详情 fixed_time 固定收费时间 fixed_unit固定收费时间单位 fixed 固定时间费用 fee 超时计费费用 fee_unit超时计费单位 max_fee_time 最高收费时间 max_fee_unit 最高收费单位 max_fee 最高收费费用 free_time 意外借出免费时间 free_unit 意外借出免费单位',
  primary key (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='收费策略表' AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `pre_ddzh_station_settings`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_station_settings` (
 `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '策略id',
 `name` varchar(255) NOT NULL COMMENT '策略名称',
 `settings` varchar(10000) NOT NULL COMMENT '策略详情',
 `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '策略更新时间',
 `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '本条纪录状态 -1 为删除',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='大地走红伞设置同步策略表';

DROP TABLE IF EXISTS `pre_ddzh_tradeinfo`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_tradeinfo` (
  `orderid` varchar(32) NOT NULL COMMENT '订单id',
  `fee_strategy` varchar(200) NOT NULL DEFAULT '' COMMENT '收费策略详情',
  UNIQUE KEY `orderid` (`orderid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='已有订单收费策略详情表';

DROP TABLE IF EXISTS `pre_ddzh_station_heartbeat_log`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_station_heartbeat_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station_id` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '站点机器id',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '心跳时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='设备心跳记录';

DROP TABLE IF EXISTS `pre_ddzh_umbrella`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_umbrella` (
 `id` varchar(32) NOT NULL DEFAULT '0' COMMENT '雨伞原始ID, 16进制',
 `station_id` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '雨伞所在站点的id',
 `order_id` varchar(32) NOT NULL DEFAULT '' COMMENT '雨伞上一次订单 id',
 `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '状态 0可借出 1已借出 2 借出后同步',
 `sync_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最近同步的时间',
 `slot` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '雨伞所在槽口',
 `exception_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '异常时间点, 跟status匹配, 即出现status异常状态的时间点, 目前仅用到 2 借出后同步',
 `mark` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '借出5分钟内归还标记',
 PRIMARY KEY (`id`),
 KEY `station_id` (`station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='雨伞表';

DROP TABLE IF EXISTS `pre_ddzh_refund_log`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_refund_log` (
  `id` bigint(11) unsigned NOT NULL auto_increment,
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `refund` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '申请提现金额',
  `refunded` decimal(8,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '实际提现金额',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1申请 2退款完成',
  `request_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '申请时间',
  `refund_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '退款时间',
  `detail` text NOT NULL COMMENT '退款订单详情',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='提现记录表';

DROP TABLE IF EXISTS `pre_ddzh_qrcode`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_qrcode` (
  `id` int(11) NOT NULL,
  `wx` varchar(1000) NOT NULL COMMENT '微信二维码',
  `alipay` varchar(1000) NOT NULL COMMENT '支付宝二维码',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT ='二维码表';

DROP TABLE IF EXISTS `pre_ddzh_user_location_log`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_user_location_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL COMMENT '用户id',
  `lng` double NOT NULL COMMENT '经度坐标',
  `lat` double NOT NULL COMMENT '维度坐标',
  `js_date` varchar(255) CHARACTER SET latin1 NOT NULL COMMENT '客户端日期',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '这条记录创建的时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='用户地理位置表' AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `pre_ddzh_trade_zhima`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_trade_zhima` (
  `orderid` varchar(32) NOT NULL COMMENT '订单id',
  `zhima_order` varchar(255) NOT NULL COMMENT '芝麻信用订单号',
  `openid` varchar(50) NOT NULL COMMENT '支付宝用户ID',
  `admit_state` tinyint(1) unsigned NOT NULL COMMENT '信用是否准入, 0: 不准入, 1: 准入',
  `status` int(11) NOT NULL COMMENT '交易状态, 1: 下单成功, 2: 等待结算, 3: 等待查询是否结算, 4: 结算成功, 5: 等待撤销, 6: 撤销成功',
  `pay_amount_type` varchar(10) NOT NULL COMMENT 'RENT 租金, DAMAGE 赔偿金',
  `pay_amount` decimal(8,2) NOT NULL DEFAULT '0' COMMENT '支付金额',
  `pay_time` varchar(20) NOT NULL COMMENT '支付时间',
  `alipay_fund_order_no` varchar(32) NOT NULL COMMENT '支付宝资金流水号',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最近更新时间',
  PRIMARY KEY (`orderid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='芝麻信用交易表' ;

DROP TABLE IF EXISTS `pre_ddzh_pictext_settings`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_pictext_settings` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `name` varchar(20) NOT NULL DEFAULT '' COMMENT '图文设置名称',
 `pictext` text NOT NULL COMMENT '图文设置详情 title 图文标题 description 图文描述 picurl 图片链接 url 跳转链接',
 `stime` int(11) unsigned NOT NULL COMMENT '配置启用时间',
 `etime` int(11) unsigned NOT NULL COMMENT '配置到期时间',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='图文消息设置表';

DROP TABLE IF EXISTS `pre_ddzh_alipay_bill`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_alipay_bill` (
  `orderid` varchar(32) NOT NULL COMMENT '订单id',
  `zhima_order` varchar(50) NOT NULL COMMENT '芝麻信用订单号',
  `openid` varchar(50) NOT NULL COMMENT '支付宝用户ID',
  `pass_name` varchar(50) NOT NULL COMMENT '优惠券名称',
  `pass_amount` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '优惠券金额',
  `order_amount` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `pay_amount` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '支付金额',
  `discount_amount` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `alipay_profit` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '支付宝分润金额',
  `alipay_fund_order_no` varchar(32) NOT NULL COMMENT '支付宝资金流水号',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单创建时间',
  `finish_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单完成时间',
  PRIMARY KEY (`orderid`),
  KEY `alipay_fund_order_no` (`alipay_fund_order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付宝交易信息表' ;

DROP TABLE IF EXISTS `pre_ddzh_station_slot_log`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_station_slot_log` (
 `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
 `station_id` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '站点id',
 `slot` SMALLINT unsigned NOT NULL DEFAULT 0 COMMENT '槽位编号',
 `type` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '类型 3读取不对雨伞id',
 `last_sync_umbrella_time` int unsigned NOT NULL DEFAULT 0 COMMENT '最近一次同步雨伞时间',
 `create_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
 PRIMARY KEY (`id`),
 KEY `sation_id` (`station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='机器槽位日志表';

DROP TABLE IF EXISTS `pre_ddzh_station_log`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_station_log` (
  `id` bigint unsigned NOT NULL COMMENT '年月日+机器id，保证唯一性',
  `station_id` int unsigned NOT NULL DEFAULT '0' COMMENT '机器id',
  `shop_station_id` int unsigned NOT NULL DEFAULT '0' COMMENT '商铺站点id',
  `login_count` SMALLINT unsigned NOT NULL DEFAULT '0' COMMENT '当天登录次数',
  `heartbeat_count` SMALLINT unsigned NOT NULL DEFAULT '0' COMMENT '当天心跳次数',
  `rssi_info` varchar(200) NOT NULL DEFAULT '' COMMENT '机器信号信息',
  `umbrella_from_station` varchar(200) NOT NULL DEFAULT '' COMMENT '期初雨伞数量，包含现有数量和借出数量',
  `slot_from_station` varchar(200) NOT NULL DEFAULT '' COMMENT '机器槽位数量',
  `max_umbrella_count` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '当天最大雨伞数量',
  `min_umbrella_count` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '当天最小雨伞数量',
  `online_time` SMALLINT unsigned NOT NULL DEFAULT '0' COMMENT '当天在线时长（单位分钟）',
  `created_at` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updated_at` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `station_id` (`station_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='站点每日记录表' AUTO_INCREMENT=1;

DROP TABLE IF EXISTS `pre_ddzh_common_setting`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_common_setting` (
 `skey` VARCHAR(200) NOT NULL,
 `svalue` text NOT NULL,
 PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='常用参数表';

DROP TABLE IF EXISTS `pre_ddzh_order_analysis`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_order_analysis` (
  `shop_station_id` mediumint(8) unsigned NOT NULL COMMENT '商铺站点id',
  `borrow_success_order` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租借成功订单数',
  `return_success_order` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '归还成功订单数',
  `borrow_try_user` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '尝试租借用户数',
  `borrow_success_user` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '借成功用户数',
  `return_success_user` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '还成功用户数',
  `borrow_try_order` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '尝试租借订单数',
  `return_try_order` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '尝试归还订单数',
  `renting_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '总租借时间(s)',
  `return_all_order` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '总归还订单数（包括订单金额大于0与归还成功的订单）',
  `charge_order` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '盈利订单数',
  `total_usefee` double(10,2) unsigned NOT NULL DEFAULT '0' COMMENT '盈利总额',
  `seller_usefee` double(10,2) unsigned NOT NULL DEFAULT '0' COMMENT '盈利总额（扣除电池成本85元）',
  `finish_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单完成日期，精确到天',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`shop_station_id`,`finish_time`),
  KEY `shop_station_id` (`shop_station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='每日订单分析表' ;

DROP TABLE IF EXISTS `pre_ddzh_user_session`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_user_session` (
 `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
 `uid` int(11) unsigned NOT NULL,
 `session` char(32) NOT NULL,
 `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
 `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间(每次访问操作均更新)',
 PRIMARY KEY (`id`),
 KEY `uid` (`uid`),
 UNIQUE KEY `session` (`session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户会话记录表';

DROP TABLE IF EXISTS `pre_ddzh_wallet_statement`;
CREATE TABLE IF NOT EXISTS `pre_ddzh_wallet_statement` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `uid` int(10) unsigned NOT NULL COMMENT '用户ID',
 `related_id` varchar(32) NOT NULL COMMENT '关联id（提现相关为提现记录id，其他为订单id）',
 `type` tinyint(1) unsigned NOT NULL COMMENT '操作类型 cfg.inc.php 定义 1 充值 2 支付 3 提现申请 4 提现到账 5 退款 6 芝麻支付 7 芝麻超时未归还',
 `amount` decimal(8,2) unsigned NOT NULL COMMENT '金额',
 `time` timestamp NOT NULL COMMENT '操作时间',
 PRIMARY KEY (`id`),
 KEY `uid` (`uid`),
 KEY `related_id` (`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户钱包明细表';
