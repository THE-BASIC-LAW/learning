JJ伞 —— 大地走红后台管理系统

===============

> ThinkPHP5的运行环境要求PHP5.4以上。

详细开发文档参考 [ThinkPHP5完全开发手册](http://www.kancloud.cn/manual/thinkphp5)

## 目录结构

大地走红项目目录结构：

~~~
www  WEB部署目录（或者子目录）
├─application           应用目录
│  ├─cert               支付证书相关目录
│  ├─common             公共模块目录（可以更改）
|  ├─controller         控制器目录
│  │  ├─api             api 控制器目录
│  │  ├─cp				cp 控制器目录
│  │  ├─Api.php			Api 控制器入口
│  │  ├─Cp.php			Cp 控制器入口
│  │  ├─Index.php		控制器入口
│  │  ├─Rent.php        一键借伞
│  │  ├─Test.php        测试环境代码
│  │  ├─Maintain.php    机器维护
│  │  ├─Oauth.php       网页授权
│  │  └─Wechat.php      微信回调
│  │
|  ├─logs               日志目录
│  ├─model              模型目录
│  ├─table              数据库表
│  ├─third              第三方库
│  ├─validate           验证目录
│  ├─view               视图目录
│  ├─area_tree.php	    管理员区域权限文件
│  ├─command.php        命令行工具配置文件
│  ├─common.php         公共函数文件
│  ├─config.php         公共配置文件
│  ├─const.php          公共参数文件
│  ├─database.php       数据库配置文件
│  ├─nav_tree.php	    管理员权限控制文件
│  ├─route.php          路由配置文件
│  └─tags.php           应用行为扩展定义文件
│
├─public                WEB目录（对外访问目录）
│  ├─css                前端css目录
│  ├─fonts              前端字体目录
│  ├─images             前端图片
│  ├─js                 前端js目录
│  ├─lib                前端框架目录
│  ├─index.php          入口文件
│  ├─router.php         快速测试文件
│  └─.htaccess          用于apache的重写
│
├─thinkphp              框架系统目录
│  ├─lang               语言文件目录
│  ├─library            框架类库目录
│  │  ├─think           Think类库包目录
│  │  └─traits          系统Trait目录
│  │
│  ├─tpl                系统模板目录
│  ├─base.php           基础定义文件
│  ├─console.php        控制台入口文件
│  ├─convention.php     框架惯例配置文件
│  ├─helper.php         助手函数文件
│  ├─phpunit.xml        phpunit配置文件
│  └─start.php          框架入口文件
│
├─extend                扩展类库目录
│  └─page               扩展分页类
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                第三方类库目录（Composer依赖库）
├─build.php             自动生成定义文件（参考）
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
├─think                 命令行入口文件
~~~

> router.php用于php自带webserver支持，可用于快速测试
> 切换到public目录后，启动命令：php -S localhost:8888  router.php
> 上面的目录结构和名称是可以改变的，这取决于你的入口文件和配置参数。

## 命名规范

`ThinkPHP5`遵循PSR-2命名规范和PSR-4自动加载规范，并且注意如下规范：

### 目录和文件

*   目录不强制规范，驼峰和小写+下划线模式均支持；
*   类库、函数文件统一以`.php`为后缀；
*   类的文件名均以命名空间定义，并且命名空间的路径和类库文件所在路径一致；
*   类名和类文件名保持一致，统一采用驼峰法命名（首字母大写）；

### 函数和类、属性命名
*   类的命名采用驼峰法，并且首字母大写，例如 `User`、`UserType`，默认不需要添加后缀；
*   控制器易于模型类名产生重复冲突，故控制器按照模型名+类型命名，如 `AdminCp`，对应 `Admin` 模型在 Cp 下的控制器， `AdminApi` 对应 `Admin` 模型在 Api 下的控制器；
*   方法的命名使用驼峰法，并且首字母小写，例如 `getUserName`；
*   属性的命名使用驼峰法，并且首字母小写，例如 `tableName`、`instance`；
*   以双下划线“__”打头的函数或方法作为魔法方法，例如 `__call` 和 `__autoload`；

### 常量和配置
*	常量在 `application\const.php` 中配置；
*   常量以大写字母和下划线命名，例如 `APP_PATH`和 `THINK_PATH`；
*   配置参数以小写字母和下划线命名，例如 `url_route_on` 和`url_convert`；

### 路由使用说明
*	目前路由配置尽量按照原来的路径写法，将原来 `/index.php?mod=cp&act=admin&opt=help` 这样的格式换成 `/cp/admin/help`；
*	路由格式为 `/mod/act/opt？do=XXX` 的格式，其中 `do` 以参数形式传入；
*	各路由需要去对应控制器建立对应方法方可使用，总控制起名字见前文控制器的命名规则，以免发生冲突；
*	在页面顶部添加 {layout name='example' /}
