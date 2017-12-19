<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
define('ENV_PREFIX', 'ddzh_'); // 环境变量的配置前缀
// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
// 加载自定义常量
require APP_PATH . 'const.php';
// 加载权限控制导航
require APP_PATH . 'nav_tree.php';
// 加载管理员区域导航
require APP_PATH . 'area_tree.php';
// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';

