<?php

namespace app\logic;

use app\lib\FileUpload;
use think\Db;
use think\Log;
use think\Session;

class Admin
{

    const STATUS_DELETE                         = -1;   //当前状态为被删除
    const EVENT_REFUSE                          = 1;    // 拒绝创建账户申请
    const EVENT_PASS                            = 2;    // 通过创建账户申请
    const EVENT_DELETE                          = 3;    // 删除系统用户
    const EVENT_LOCK                            = 4;    // 锁定系统用户
    const EVENT_UNLOCK                          = 5;    // 解锁系统用户
    const EVENT_RESUME		                    = 6;	// 恢复系统用户
    const EVENT_EDIT	        	            = 7;	// 编辑系统用户信息
    const EVENT_SYSTEM_SETTINGS		            = 8;	// 设置全局同步策略
    const EVENT_STATION_SETTINGS_ADD            = 9;	// 添加局部同步策略
    const EVENT_STATION_SETTINGS_EDIT	        = 10;	// 编辑局部同步策略
    const EVENT_STATION_SETTINGS_DELETE	        = 11;	// 删除局部同步策略
    const EVENT_FEE_SETTINGS		            = 12;	// 设置局部收费策略
    const EVENT_GLOBAL_SETTINGS		            = 13;	// 设置客服电话
    const EVENT_LOCAL_FEE_SETTINGS_ADD	        = 14;	// 添加局部收费策略
    const EVENT_LOCAL_FEE_SETTINGS_EDIT	        = 15;	// 编辑局部收费策略
    const EVENT_LOCAL_FEE_SETTINGS_DELETE	    = 16;	// 删除局部收费策略
    const EVENT_SHOP_TYPE_ADD           	    = 17;	// 添加商铺类型
    const EVENT_SHOP_ADD                	    = 18;	// 添加商铺
    const EVENT_SHOP_EDIT                	    = 19;	// 编辑商铺
    const EVENT_LOGO_UPDATE                	    = 20;	// 更新logo
    const EVENT_CAROUSEL_UPDATE                 = 21;	// 更新轮播图
    const EVENT_CP_BIND                         = 22;	// PC端绑定商铺
    const EVENT_CP_UNBIND                       = 23;	// PC端解绑商铺
    const EVENT_CP_GO_UP                        = 24;	// PC上机
    const EVENT_CP_REPLACE                      = 25;	// PC换机
    const EVENT_CP_REMOVE                       = 26;	// PC撤机
    const EVENT_CP_SHOP_STATION_SETTINGS        = 27;	// PC更改商铺站点设置
    const EVENT_CP_SLOT_LOCK                    = 28;	// PC锁住槽位
    const EVENT_CP_SLOT_UNLOCK                  = 29;	// PC解锁槽位
    const EVENT_CP_QUERY                        = 30;	// PC槽位查询
    const EVENT_CP_LEND                         = 31;	// PC人工借出
    const EVENT_CP_SYNC                         = 32;	// PC同步雨伞
    const EVENT_CP_REBOOT                       = 33;	// PC人工重启设备
    const EVENT_CP_MODULE_NUM                   = 34;	// PC人工设置模组数
    const EVENT_CP_UPGRADE                      = 35;	// PC人工升级控制
    const EVENT_CP_SYNC_STRATEGY                = 36;	// PC设置站点同步策略
    const EVENT_CANCEL_ORDER                    = 37;	// PC手动撤销订单
    const EVENT_RETURN_BACK                     = 38;	// PC手动退款
    const EVENT_ADD_ROLE                        = 39;	// 添加角色
    const EVENT_EDIT_ROLE                       = 54;	// 编辑角色
    const EVENT_PASS_INSTALL_MAN                = 40;	// 通过维护人员申请
    const EVENT_SET_COMMON                      = 41;	// 设置为普通人员
    const EVENT_SET_INSTALL                     = 42;	// 设置为维护人员
    const EVENT_DELETE_INSTALL                  = 43;	// 删除维护人员
    const EVENT_INIT_SET                        = 44;	// 机器初始化
    const EVENT_PICTEXT_SETTINGS_ADD	        = 45;	// 添加图文消息配置
    const EVENT_PICTEXT_SETTINGS_EDIT	        = 46;	// 编辑图文消息配置
    const EVENT_PICTEXT_SETTINGS_DELETE	        = 47;	// 删除图文消息配置
    const EVENT_ELEMENT_MODULE_OPEN             = 48;	// 开启机器模组功能
    const EVENT_ELEMENT_MODULE_CLOSE            = 49;	// 关闭机器模组功能
    const EVENT_VOICE_MODULE_OPEN               = 50;	// 开启机器模组功能

    const EVENT_VOICE_MODULE_CLOSE              = 51;	// 关闭机器模组功能

    const EVENT_ADD_ZERO_FEE_USER               = 52;	// 增加零费用用户openid
    const EVENT_DELETE_ZERO_FEE_USER            = 53;	// 移除零费用用户openid

	private $admin;
    private $adminSession;
	private $saltRand = '0123456789qwertyuiopasdfghjklzxcvbnm';
	private $phpsessid = '';
	private $adminAuth = [];
	private $nav_tree = [];
	private $cdo = [];

	public $adminInfo = [];
    public $isSuperAdmin = false;
	private $auth;

	public function __construct()
	{
		$this->auth                 = model('app\lib\Auth');
		$this->admin                = model('Admin');
		$this->adminLog             = model('AdminLog');;
		$this->adminRole			= model('AdminRole');;
		$this->phpsessid            = Session::get('phpsessid'); //未登录的用户phpsessid为空
	}
	/**
	*	密码加密
	*/
	public function encrypt($password, $salt)
	{
		return md5(md5($password).md5($salt));
	}

	public function createSessId()
    {
        $this->phpsessid = md5(time().$this->getSalt());
    }

	public function getSalt()
    {
        $saltRand = str_shuffle($this->saltRand);
        return substr($saltRand, 0, 8);
    }


    /**
     * @param array $data 用户名、密码等
     * @return bool
     */
	public function login($data){
		$name = $data['adminname'];
		$password = $data['password'];

		// $userInfo = $this->admin
		// 			->where(['adminname' => $name, 'status' => ADMIN_USER_STATUS_NORMAL])
		// 			->find();
		$userInfo = $this->admin->get(
			[
				'adminname' => $name,
				'status' => ADMIN_USER_STATUS_NORMAL
			]
		);

		if($userInfo) {
            if($this->encrypt($password, $userInfo['salt']) == $userInfo['pwd']) {
                //检查同一个用户登录个数, 超过限制, 踢掉最早登录的用户

                //创建phpsessid
                $this->createSessId();

                //设置session
                Session::set('phpsessid', $this->phpsessid);
				Session::set('aid', $userInfo['id']);
				Session::set('start_time', time());

				return true;
            }
        }

		return false;
	}

	/**
	*	验证是否已登录
	*/
	public function isLogin()
    {

		if (time() - Session::get('start_time') > ADMIN_SESSION_EXPIRED_TIME) {
			return false;
		}

		//更新start_time
		Session::set('start_time', time());

		// 验证后保存用户信息
		$this->adminInfo = $this->admin->where('id', Session::get('aid'))->find();
		$this->adminInfo['role_name'] = $this->auth->getCurrentRoleName();
		Session::set('adminInfo', ['name' => $this->adminInfo['name'], 'role_name' => $this->adminInfo['role_name']]);

		// 判断是否超级管理员
		if($this->adminInfo['role_id'] == SUPER_ADMINISTRATOR_ROLE_ID) $this->isSuperAdmin = true;
		// 将管理员权限更新进session
		$this->adminAuth = $this->auth->getAuth();
		Session::set('adminAuth', $this->adminAuth);
		Session::set('globalSearch', $this->auth->globalSearch);
		// 菜单栏权限
		$this->nav_tree = $this->auth->getNavAccessTree($GLOBALS['jjsan_nav_tree']);
		Session::set('nav_tree', $this->nav_tree);
		// 按钮检查用途
		if ($GLOBALS['opt'] != 'index' && $GLOBALS['act'] != 'login') {
			if (array_key_exists($GLOBALS['act'], $this->nav_tree)) {
				if (array_key_exists($GLOBALS['opt'], $this->nav_tree[$GLOBALS['act']]['sub_nav'])) {
					if (array_key_exists('do', $this->nav_tree[$GLOBALS['act']]['sub_nav'][$GLOBALS['opt']])) {
						$this->cdo = $this->nav_tree[$GLOBALS['act']]['sub_nav'][$GLOBALS['opt']]['do'];
					}
				}
			}

		}
		Session::set('cdo', $this->cdo);

		return true;
    }

	/*
	*	用户注册
	*/
	public function register($date)
    {
		$adminname = $date['adminname'];
		$password = $date['password'];
		$email = $date['email'];
		$name = $date['name'];
		$company = $date['company'];
		$auth_id = $date['auth_id'];

        if($this->checkRegisterAdminname($adminname)
            && $this->checkRegisterPassword($password)
            && $this->checkRegisterEmail($email)
            && $this->checkRegisterCompany($company)
            && $this->checkRegisterRoleId($auth_id)
        ) {
            $salt = $this->getSalt();
            $this->admin->insert([
                'adminname'      => $adminname,
                'name'          => $name,
                'email'         => $email,
                'company'       => $company,
                'status'         => ADMIN_USER_STATUS_APPLIED,
                'salt'          => $salt,
                'pwd'           => $this->encrypt($password, $salt),
                'role_id'       => $auth_id,
                'login_error'   => 0,
                'create_time'   => date('Y-m-d H:i:s'),
            ]);
            return true;
        }
        return false;
    }

	public function checkRegisterAdminname($adminname)
    {
        $forbidden_adminname = [
            'admin',
            'root',
            'administrator',
        ];
        if(in_array($adminname, $forbidden_adminname)) return false;
        if(!preg_match('/^[a-zA-Z_\d]{6,100}$/', $adminname)) return false;
        if($this->admin->where(['adminname' => $adminname])->find()) return false;
        return true;
    }

	public function checkRegisterPassword($password)
    {
        return strlen($password) >= 6 ? true : false;
    }

	public function checkRegisterEmail($email)
    {
        if(!preg_match('/^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/', $email)) return false;
        if($this->admin->where(['email' => $email])->find()) return false;
        return true;
    }

	public function checkRegisterCompany($company)
    {
        // 5个汉字以上
        return strlen($company) >= 5*3;
    }

	public function checkRegisterRoleId($auth_id)
    {
        $roles = db('admin_role')
            ->where('id', 'not in', SUPER_ADMINISTRATOR_ROLE_ID)
            ->select();
        $tmp = [];
        foreach($roles as $k => $v) {
            $tmp[] = $v['id'];
        }
        if(in_array($auth_id, $tmp)) return true;
        return false;
    }

	public function logAddRole($role){
	 $data = [
		 'uid' => $this->adminInfo['id'],
		 'type' => self::EVENT_ADD_ROLE,
		 'detail' => "添加角色：$role",
		 'create_time' => date("Y-m-d H:i:s"),
	 ];
	 $this->adminLog->insert($data);
	 return true;
	 }

	 public function logEditRole($role_id, $role){
		 $data = [
			 'uid' => $this->adminInfo['id'],
			 'type' => self::EVENT_EDIT_ROLE,
			 'detail' => "编辑角色：$role_id->$role",
			 'create_time' => date("Y-m-d H:i:s"),
		 ];
		 $this->adminLog->insert($data);
		 return true;
	 }

	 /**
	 * 获取全部管理员信息
	 * @param bool，是否需要分页处理
	 * @return arr 管理员信息
	 */
	 public function allAdmins($needPaginate = false){
		 if ($needPaginate) {
			 if ($this->isSuperAdmin) {
			 	return db('admin')->paginate();
			}else {
				return db('admin')->where('role_id', '<>', SUPER_ADMINISTRATOR_ROLE_ID)->paginate();
			}
		}else {
			if ($this->isSuperAdmin) {
			   return db('admin')->select();
		   }else {
			   return db('admin')->where('role_id', '<>', SUPER_ADMINISTRATOR_ROLE_ID)->select();
		   }
		}
	 }

	 /**
	 * 获取搜索管理员信息
	 * @param str，管理员登录名关键字
	 * @param bool，是否需要分页处理
	 * @return arr 查询管理员信息结果
	 */
	 public function getAdminByName($search_name, $needPaginate = false){
		 if ($needPaginate) {
			 if ($this->isSuperAdmin) {
			 	return $this->admin->where('adminname', 'like', '%'.$search_name.'%')->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>request()->param()]);
			}else {
				return $this->admin->where('role_id', '<>', SUPER_ADMINISTRATOR_ROLE_ID)->where('adminname', 'like', '%'.$search_name.'%')->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>request()->param()]);
			}
		}else {
			if ($this->isSuperAdmin) {
			   return $this->admin->where('adminname', 'like', '%'.$search_name.'%')->select();
		   }else {
			   return $this->admin->where('role_id', '<>', SUPER_ADMINISTRATOR_ROLE_ID)->where('adminname', 'like', '%'.$search_name.'%')->select();
		   }
		}
	 }

	 /**
     * 传入0个id, 返回false
     * 传入1个id, 返回true
     * 传入多个id, 角色判断
     */
    public function isTheSameRole(array $idArray)
    {
        if (empty($idArray)) return false;
        if (count($idArray) == 1) return true;
        $userInfo = $this->admin->where('id', '=', $idArray[0])->find();
		$count = count($this->admin->where('id', 'in', $idArray)->where('role_id', '=', $userInfo['role_id'])->select());
        return count($idArray) == $count;
    }

	public function handleRoleApplyUsers($id, $action)
    {
        switch ($action) {

            case 'pass':
                $before = ADMIN_USER_STATUS_APPLIED;
                $after = ADMIN_USER_STATUS_NORMAL;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_PASS,
                    'detail' => "申请通过id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> adminLog -> insert($data);
                break;

            case 'refuse':
                $before = ADMIN_USER_STATUS_APPLIED;
                $after = ADMIN_USER_STATUS_REFUSE;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_REFUSE,
                    'detail' => "申请被拒id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> adminLog -> insert($data);
                break;

            case 'delete':
                $before = [
                    ADMIN_USER_STATUS_NORMAL,
                    ADMIN_USER_STATUS_APPLIED,
                    ADMIN_USER_STATUS_REFUSE,
                    ADMIN_USER_STATUS_LOCKED,
                ];
                $after = ADMIN_USER_STATUS_DELETED;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_DELETE,
                    'detail' => "删除账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> adminLog -> insert($data);
                break;

            case 'lock':
                $before = ADMIN_USER_STATUS_NORMAL;
                $after = ADMIN_USER_STATUS_LOCKED;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_LOCK,
                    'detail' => "锁定账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> adminLog -> insert($data);
                break;

            case 'unlock':
                $before = ADMIN_USER_STATUS_LOCKED;
                $after = ADMIN_USER_STATUS_NORMAL;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_UNLOCK,
                    'detail' => "解锁账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> adminLog -> insert($data);
                break;

            case 'resume':
                $before = ADMIN_USER_STATUS_DELETED;
                $after = ADMIN_USER_STATUS_NORMAL;
                $data = [
                    'uid' => $this->adminInfo['id'],
                    'type' => self::EVENT_RESUME,
                    'detail' => "恢复账户id: $id",
                    'create_time' => date("Y-m-d H:i:s"),
                ];
                $this -> adminLog -> insert($data);
                break;

            default:
                return false;
        }
        // 不支持超级管理员账户状态变动
        if($id == SUPER_ADMINISTRATOR_ROLE_ID) return false;
        return self::changeUserStatus($id, $before, $after);
    }

	public function changeUserStatus($id, $before, $after)
    {
        // 不支持批量改用户信息
        if (is_array($id)) return false;
		return $this->admin
				->where('status', 'in', $before)
				->where('id', $id)
				->update([
					'status'	=>	$after
				]);
    }

	public function getAdminUserInfo($adminId)
    {
        return $this->admin->where('id', $adminId)->find();
    }

	public function updateAdminUserInfo($adminInfo)
    {
        // 修改他人的信息时
        if ($adminInfo['aid'] != $this->adminInfo['id']) {
            // 不能改超级管理员信息
			$adminInfo['role_id'] = $this->admin->where('id', $adminInfo['aid'])->value('role_id');
            if ($adminInfo['role_id'] == SUPER_ADMINISTRATOR_ROLE_ID) return false;
            // 用户不存在
            $user = $this->getAdminUserInfo($adminInfo['aid']);
            if (!$user) return false;
            // 不能修改同角色的信息
            if ($user['role_id'] == $this->adminInfo['role_id']) return false;
        }
        // 更新信息
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_EDIT,
            'detail' => "编辑账户信息id: " . $adminInfo['aid'],
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this -> adminLog -> insert($data);
        return $this->updateUserInfo($adminInfo);
    }

	// 更新用户的部分信息
    public function updateUserInfo($info)
    {
        // 登录名,邮箱不能重名
		$count = count($this->admin
					->where(
						'id != :id AND (adminname = :adminname OR email = :email)',
						[
							'id' => $info['aid'],
							'adminname' => $info['adminname'],
							'email' => $info['email']
						])
					->select());
        if ($count) return false;
        return $this->admin->update([
			'id'	=>	$info['aid'],
            'adminname' => $info['adminname'],
            'name' => $info['name'],
            'email' => $info['email'],
            'company' => $info['company'],
        ]);

    }

	public function changePassword($data)
    {
		$old = $data['oldpassword'];
		$new = $data['newpassword'];
        if(empty($old) || empty($new) || $old == $new) return false;
        if(!$this->checkRegisterPassword($new)) return false;
        if($this->encrypt($old, $this->adminInfo['salt']) == $this->adminInfo['pwd']) {
            // 盐和密码都更新
            $salt = $this->getSalt();
            return $this->admin->update([
				'id'	=> $this->adminInfo['id'],
                'salt' => $salt,
                'pwd' => $this->encrypt($new, $salt)
            ]);
        }
        return false;
    }


    public function shopTypeAdd($type){
        $files = FileUpload::img(UPLOAD_IMAGE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
        $files = json_encode($files);
        $res = model('ShopType')->isUpdate(false)->save(['type'=>$type, 'logo'=>$files]);
        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SHOP_TYPE_ADD,
                'detail' => '添加商铺类型: '. $type,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this ->adminLog->isUpdate(false)->save($data);
            return true;
        } else {
            return false;
        }
    }

    public function shopAdd(){
        $FILE_1 = $_FILES['logo'];
        $FILE_2 = $_FILES['carousels'];

        $_FILES = ['logo' => $FILE_1];
        $files = FileUpload::img(UPLOAD_IMAGE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
        $_POST['logo'] = $files;

        $_FILES = ['carousel' => $FILE_2];
        FileUpload::$files = [];
        $files = FileUpload::img(UPLOAD_IMAGE_ROOT_DIR.'carousel', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/carousel');
        $_POST['mats'] = $files;

        foreach($_POST['mats'] as $mat){
            $_POST['carousel'][] = $mat;
        }

        if(!isset($_POST['carousel'])){
            $_POST['carousel'] = json_encode([]);
        }
        $data = array_map(function($a){
            return str_replace('：', ':', $a);
        }, $_POST);
        unset($data['mats']);
        $data['logo'] = json_encode($data['logo']);
        $res = model('Shop')->save($data);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SHOP_ADD,
                'detail' => '添加商铺 id: '. $res,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this ->adminLog->isUpdate(false)->save($data);
            return true;
        }else{
            return false;
        }
    }

    public function shopEdit($shop_id){
        $res = model('Shop')->get($shop_id)->save($_POST);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SHOP_EDIT,
                'detail' => '编辑商铺 id: '. $shop_id,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this ->adminLog->isUpdate(false)->save($data);
            return true;
        }else{
            return false;
        }
    }

    public function updateLogo($shop_id, $data_url){
        $file_path = FileUpload::base64($data_url, UPLOAD_IMAGE_ROOT_DIR.'logo', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/logo');
        $res       = model('Shop')->get($shop_id)->save(['logo' => json_encode($file_path)]);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOGO_UPDATE,
                'detail' => '更新商铺logo id: '. $shop_id,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data);
            return true;
        }else{
            return false;
        }
    }

    public function carouselUpdate($shop_id, $mats){
        $files = FileUpload::img(UPLOAD_IMAGE_ROOT_DIR.'carousel', UPLOAD_FILE_RELATIVE_DIR_CONTAIN_DOMAIN.'/carousel');
        $carousel = [];
        if($mats){
            $mats = explode(',', $mats);
            foreach ($mats as $mat){
                $carousel[] = $mat;
            }
        }
        foreach ($files as $img){
            $carousel[] = $img;
        }
        $data['carousel'] = json_encode($carousel);
        $res = model('Shop')->get($shop_id)->save($data);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CAROUSEL_UPDATE,
                'detail' => '更新商铺轮播图 id: '. $shop_id,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data);
            return true;
        }else{
            return false;
        }
    }

    public function syncStrategy($station, $station_id, $strategy_id){
        $res = $station->setStationSettings($station_id, $strategy_id);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_SYNC_STRATEGY,
                'detail' => "站点设置同步策略  站点id: $station_id 同步策略: $strategy_id",
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data);
            return true;
        }else{
            return false;
        }
    }

    public function cpShopBind($shop_station_id, $shop_id){
        model('ShopStation')->bindShop($shop_station_id, $shop_id);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_BIND,
            'detail' => "站点绑定商铺  商铺站点id: $shop_station_id; 商铺id: $shop_id ",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function cpShopUnbind($shop_station_id){
        model('ShopStation')->unBindShop($shop_station_id);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_UNBIND,
            'detail' => "站点解绑商铺  商铺站点id: $shop_station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function cpGoUp($shop_station_id, $new_station_id){
        $shop_station = model('ShopStation')->get($shop_station_id);
        $data = ['enable' => 1];
        // 先更新lbs数据，让其在附近网点显示
        $ret = update_station_to_lbs($shop_station['lbsid'], $data);
        if ($ret['errcode'] == 0) {
            // 再更新数据库，让其在后台启用，并且更新相应机器的title和address
            $res = $shop_station->update($shop_station_id, ['station_id' => $new_station_id, 'status' => 1]) &&
                model('Station')->get($new_station_id)->save(['title' => $shop_station['title'], 'address' => $shop_station['address']]);
        }
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_GO_UP,
                'detail' => "商铺站点上机  商铺站点id: $shop_station_id 站点id: $new_station_id",
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data);
            return true;
        }else{
            return false;
        }
    }

    public function cpReplace($shop_station_id, $new_station_id, $origin_station_id){
        // 1.把这个商铺站点绑定到新的机器上
        // 2.把新机器的信息（title,address）与这个商铺站点同步
        // 3.把原来的机器信息（title,address）置空
        $shop_station = model('ShopStation')->get($shop_station_id);
        $res = $shop_station->save(['station_id' => $new_station_id]) &&
            model('Station')->get($new_station_id)->save(['title' => $shop_station['title'], 'address' => $shop_station['address']]) &&
            model('Station')->get($origin_station_id)->save(['title' => '', 'address' => '']);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_REPLACE,
                'detail' => "商铺站点换机  商铺站点id: $shop_station_id 新站点id: $new_station_id 旧站点id: $origin_station_id",
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data);
            return true;
        }else{
            return false;
        }
    }

    public function cpRemove($station_id, $shop_station_id){
        // 撤机操作：将已绑定机器的商铺站点进行解绑操作
        $shop_station_id = $shop_station_id ? : model('Station')->getShopStationId($station_id);
        $shop_station    = model('ShopStation')->get($shop_station_id);
        $station_id      = $shop_station['station_id'];
        $lbsid           = $shop_station['lbsid'];
        $data            = ['enable' => 0];
        // 先更新lbs数据，让其不在附近网点显示
        $ret = update_station_to_lbs($lbsid, $data);
        $res = false;
        if ($ret['errcode'] == 0) {
//             再更新数据库，让其在后台禁用，并且清空相应机器的title和address
            $res = $shop_station->get($shop_station_id)-save(['station_id' => 0, 'status' => 0]) &&
                model('station')->get($station_id)->save(['title' => '', 'address' => '']);
        }
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_REMOVE,
                'detail' => "商铺站点撤机  商铺站点id: $shop_station_id 站点id: $station_id",
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data);
            return true;
        }else{
            return false;
        }
    }

    public function shopStationSettings($shop_station_id, $update_shop_station_fields){
        $shop_station = model('ShopStation')->get($shop_station_id);
        $res = $shop_station->save($update_shop_station_fields);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_CP_SHOP_STATION_SETTINGS ,
                'detail' => "商铺站点设置变更  商铺站点id: $shop_station_id 设置详情: ". json_encode($update_shop_station_fields),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data);
            return true;
        }else{
            return false;
        }
    }

    public function slotLock($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_SLOT_LOCK,
            'detail' => "锁住槽位 站点id: $station_id; 槽位号: " . ($all? '全选' : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function slotUnlock($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_SLOT_UNLOCK,
            'detail' => "解锁槽位 站点id: $station_id; 槽位号: " . ($all? '全选' : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function query($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_QUERY,
            'detail' => "查询槽位信息 站点id: $station_id; 槽位号: " . ($all? '全选' : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function lend($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_LEND,
            'detail' => "人工借出雨伞 站点id: $station_id; 槽位号: " . ($all? '全选' : $slot_num),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function syncUmbrella($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_SYNC,
            'detail' => "同步雨伞信息 站点id: $station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function reboot($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_REBOOT,
            'detail' => "人工重启设备 站点id: $station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }
    public function moduleNum($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_MODULE_NUM,
            'detail' => "人工设置模组数 站点id: $station_id; 模组数量: $module_num",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function upgrade($params){
        extract($params);
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CP_UPGRADE,
            'detail' => "人工升级控制 站点id: $station_id; 文件名: $file_name; 文件大小: $file_size",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function logCancelOrder($order_id, $amount, $uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_CANCEL_ORDER,
            'detail' => "手动撤销 订单id: $order_id; 金额: $amount; 用户id: $uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function logReturnBack($order_id, $amount, $uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_RETURN_BACK,
            'detail' => "手动退款 订单id: $order_id; 金额: $amount; 用户id: $uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function logPassInstallMan($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_PASS_INSTALL_MAN,
            'detail' => "通过维护人员申请：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function logSetCommon($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_SET_COMMON,
            'detail' => "将该id设为普通人员：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function logSetInstall($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_SET_INSTALL,
            'detail' => "讲该id设为维护人员：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function logDeleteInstall($uid){
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_DELETE_INSTALL,
            'detail' => "删除维护人员 user_id：$uid",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
        return true;
    }

    public function initSet($params){
        $station_id = $params['station_id'];
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_INIT_SET,
            'detail' => "初始化设备 机器id：$station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
    }

    public function elementModuleOpen($params){
        $station_id = $params['station_id'];
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_ELEMENT_MODULE_OPEN,
            'detail' => "开启设备模组 机器id：$station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
    }

    public function elementModuleClose($params){
        $station_id = $params['station_id'];
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_ELEMENT_MODULE_CLOSE,
            'detail' => "关闭设备模组 机器id：$station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
    }

    public function voiceModuleOpen($params){
        $station_id = $params['station_id'];
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_VOICE_MODULE_OPEN,
            'detail' => "语音功能开启 机器id：$station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
    }

    public function voiceModuleClose($params){
        $station_id = $params['station_id'];
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_VOICE_MODULE_CLOSE,
            'detail' => "语音功能休眠 机器id：$station_id",
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $this->adminLog->insert($data);
    }

    public function returnDeposit($data){
        // 退款注意事项
        // 1. tradelog表message字段 里面的refund_fee指的是单个订单已退款的（只用在后台退款，后台退款时会调整为该值）
        // 2. tradelog表usefee字段 指的是已向用户收取的费用（后台手动退款时会调整该值）
        // 3. tradelog表refunded字段 指的是已退款的金额（该字段用在提现业务中）
        // 4. tradelog表price字段  指的是用户支付的押金（目前就是雨伞的押金30元，不分平台）
        // 5. tradelog表paid字段 指用户在线支付的金额（芝麻信用/账户内支付为0，全款在线支付为30元）

        $time                = null;
        $date                = null;
        $zhima_opt           = null;
        $full_return         = null;
        $money_return        = null;
        $order_cancel        = null;
        $part_return_confirm = null;
        extract($data);
        $time = $time ? : date("H:i", time());
        $date = $date ? : date("Y-m-d", time());


        $zhima_operation        = $zhima_opt;
        $part_return_confirm    = $part_return_confirm ? 1 : 0;
        $full_return_operation  = $full_return ? 1 : 0;
        $money_return_operation = $money_return ? 1 : 0;
        Log::info('data are :' . print_r($data, 1));

        $order = model('Tradelog')->get($order_id);

        if (!$order) {
            Log::error("can not get this order : " . $order_id);
            return false;
        }

        if ($order['status'] <= 0) {
            Log::info("this order not paid");
            return false;
        }
        $refund   = round($order['price'], 2);
        $message  = unserialize($order['message']);
        $platform = $order['platform'];
        $is_zhima = $platform == PLATFORM_ZHIMA;


        // 订单撤销, 包括普通和芝麻
        if ($order_cancel) {
            // 只能撤销借出状态和第一次借出确认状态的订单
            if (!in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM_FIRST, ORDER_STATUS_RENT_CONFIRM])) {
                return make_error_data(1, '只能撤销借出状态订单或者第一次确认状态订单');
            }
            if ($order['platform'] == PLATFORM_ZHIMA) {
                $zhima_order = model('TradeZhima')->get($order_id);
            }
            // 更新芝麻订单状态
            if ($zhima_order && $zhima_order['status'] != ZHIMA_ORDER_CREATE) {
                return make_error_data(1, '芝麻订单只能撤销创建状态的订单');
            }

            if ($order['platform'] != PLATFORM_ZHIMA) {
                if (model('User')->returnBack($order['uid'], $order['price'], $order['price'])) {
                    // order cancel not need record wallet statement log
                    Log::info('success to return money to user account');
                } else {
                    Log::error('fail to return money to user account');
                    return make_error_data(1, '撤销失败, 归还押金失败');

                }
            } else {
                // 待撤销, 定时任务撤销该订单
                model('TradeZhima')->get($order_id)->save(['status' => ZHIMA_ORDER_CANCEL_WAIT, 'update_time' => time()]);
                Log::info('update zhima order waitting for cancel, orderid: ' . $order_id);
            }

            $umbrella = model('Umbrella')->getByOrderId($order_id);
            if ($umbrella) {
                $umbrella->save([
                    'status' => UMBRELLA_INSIDE,
                    'station_id' => $order['borrow_station']
                ]);
            }

            if ($order['platform'] == PLATFORM_ZHIMA) {
                $message['refund_fee'] = 0;
            } else {
                $message['refund_fee'] = $order['price'];
            }

            $usefee = 0;
            // 记录信息
            $message['manually_return_time'] = time();
            $message['operator'] = $this->adminInfo['id'];
            $res = model('Tradelog')->get($order_id)->save([
                    'status'                 => ORDER_STATUS_RETURN_MANUALLY,
                    'usefee'                 => $usefee,
                    'message'                => serialize($message),
                    'return_time'            => time(),
                    'return_city'            => $order['borrow_city'],
                    'return_shop_id'         => $order['borrow_shop_id'],
                    'return_station'         => $order['borrow_station'],
                    'return_station_name'    => $order['borrow_station_name'],
                    'return_shop_station_id' => $order['borrow_shop_station_id'],
                ]
            );

            if ($res) {
                $this->logCancelOrder($order_id, $order['price'], $order['uid']);
            }

            // 推送费用退还信息
            $msg = [
                'openid'  => $order['openid'],
                'refund'  => $is_zhima ? 0 : $order['price'], //芝麻订单费用为0
                'orderid' => $order['orderid'],
            ];
            TplMsg::send(TplMsg::MSG_TYPE_REFUND_FEE, $msg);

            Log::info('uid: ' . $this->adminInfo['id'] . " cancel order, orderid: " . $zhima_operation . ',' . $_GET['refund'] . ',' . $order_id);
            return make_error_data(0, '撤销订单成功');
        }
        // 芝麻信用退款
        // 此退款无接口，只能通过先通过支付宝后台系统退款，再进行系统后台增加退款记录。
        else if ($order['platform'] == PLATFORM_ZHIMA && $zhima_operation) {
            switch ($zhima_operation) {
                case 'refund':
                    // 退款金额大于已收取的费用
                    if ($refund > $order['usefee']) {
                        return make_error_data(1, '芝麻信用订单的退款金额不能大于已产生费用');
                    }
                    break;
                default:
                    return make_error_data(1, '未定义退款');
            }

            $zhima_order = model('TradeZhima')->get($order_id);
            // 记录信息
            $message['refund_fee'] += $refund;
            $message['refund_fee'] = round($message['refund_fee'], 2);
            $message['manually_return_time'] = time();
            $message['operator'] = $this->adminInfo['id'];
            model('Tradelog')->get($order_id)->save([
                'status'  => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                'usefee'  => $order['usefee'] - $refund,
                'message' => serialize($message),
            ]);

            // 芝麻退款
            model('WalletStatement')->insert([
                'uid'        => $order['uid'],
                'time'       => date('Y-m-d H:i:s'),
                'type'       => WALLET_TYPE_REFUND,
                'amount'     => $refund,
                'related_id' => $order_id,
            ]);
            // 推送消息
            $msg = [
                'openid'  => $order['openid'],
                'refund'  => $refund,
                'orderid' => $order['orderid'],
            ];
            // 强制使用芝麻模板（其实和支付宝是同一个模板）
            TplMsg::send(TplMsg::MSG_TYPE_REFUND_FEE, $msg);
            $this->logReturnBack($order_id, $refund, $order['uid']);
            Log::info("uid: {$this->adminInfo['id']} , manually return zhima opt, orderid: $order_id , refund: $refund");
            return make_error_data(0, '手动退款成功');
        }

        // 全额退款
        if ($full_return_operation) {
            if (!in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                return make_error_data(1, '订单状态非借出状态，无法全额退押金。');
            }
            $time              = $full_return_time;
            $shop_station_info = model('ShopStation')->getByStationId($station);
            $shop              = model('Shop')->get($shop_station_info['shopid']);
            $title             = '';
            $station           = model('Station')->get($station);
            $message           = unserialize($order['message']);
            $return_time       = $order['borrow_time'] + $time * 3600;
            $order_status      = $order['status'];
            if ($shop['name']) {
                $title = $shop['name'];
            } elseif ($shop_station_info['title']) {
                $title = $shop_station_info['title'];
            } else {
                $title = $station['title'];
            }

            if (!isset($message['refund_fee']) || $message['refund_fee'] == 0) {

                //事务操作
                Db::startTrans();
                $sql1 = db('Umbrella')->where('id', $order['umbrella_id'])->update([
                    'status'     => UMBRELLA_INSIDE,
                    'station_id' => $station['id'],
                ]);

                $sql2 = db('Tradelog')->where('orderid', $order_id)->update([
                    'status'                 => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                    'return_time'            => $return_time,
                    'return_shop_id'         => $shop_station_info['shopid'],
                    'return_station'         => $station['id'],
                    'return_station_name'    => $title,
                    'return_shop_station_id' => $shop_station_info['id'],
                ]);

                if ($order['platform'] == PLATFORM_ZHIMA) {
                    $sql3 = db('TradeZhima')->where('id', $order_id)->update([
                        'status'      => ZHIMA_ORDER_COMPLETE_WAIT,
                        'update_time' => time(),
                    ]);
                } else {
                    $sql3 = model('User')->returnBack($order['uid'], $order['price'], $order['price']);
                }
                if($sql1 && $sql2 && $sql3) {
                    Db::commit();
                } else {
                    Db::rollback();
                    Log::error("full return deposit:return deposit fail");
                    return make_error_data(1, '押金不足，无法退款。');
                }

                $msg = [
                    'openid'  => $order['openid'],
                    'refund'  => $is_zhima ? 0 : $order['price'],
                    'orderid' => $order['orderid'],
                ];
                TplMsg::send(TplMsg::MSG_TYPE_REFUND_FEE, $msg);

                $message['operator']             = $this->adminInfo['id'];
                $message['refund_fee']           = round($order['price'], 2);
                $message['manually_return_time'] = time();
                model('Tradelog')->get($order_id)->save(['message' => serialize($message)]);
                $this->logReturnBack($order_id, $order['price'], $order['uid']);
                Log::info("full return deposit:return all deposit to usablemoney success");
                return make_error_data(0, '押金退还成功');
            } else {
                Log::error("full return deposit:change order status fail");
                return make_error_data(1, '无法在退过押金的状态下进行全额退押金，请在手动退押金中进行此操作。');
            }
        }

        // 部分退款
        elseif ($part_return_confirm) {
            if (!in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                return make_error_data(1, '订单状态非借出状态，无法部分退押金。');
            }
            $return_time = strtotime($date . $time);
            if ($order['borrow_time'] > $return_time) {
                return make_error_data(1, '归还时间不能小于借出时间');
            }
            $usefee = calc_fee($order['orderid'], $order['borrow_time'], $return_time);
            $return_fee = $order['price'] - $usefee;
            if ($return_fee < 0) {
                return make_error_data(1, '根据时间计算的退款金额大于订单金额，不可退。');
            }
            Log::info('begin search shop station and the station id is : ' . $station1);
            $shop_station_info = model('ShopStation')->getByStationId($station1);

            $shop = model('Shop')->get($shop_station_info['shopid']);
            $station_1 = model('Station')->get($station1);
            $title = '';
            if ($shop['name']) {
                $title = $shop['name'];
            } elseif ($shop_station_info['title']) {
                $title = $shop_station_info['title'];
            } else {
                $title = $station_1['title'];
            }
            if ($return_fee + $message['refund_fee'] <= $order['price']) {
                // 事务处理
                Db::startTrans();
                $sql1 = db('Tradelog')->where('orderid', $order_id)->update([
                    'status'                 => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                    'return_time'            => $return_time,
                    'return_shop_id'         => $shop_station_info['shopid'],
                    'return_station'         => $station1,
                    'return_station_name'    => $title,
                    'return_shop_station_id' => $shop_station_info['id'],
                ]);

                $sql2 = db('Umbrella')->where('id', $order['umbrella_id'])->update([
                    'status'     => UMBRELLA_INSIDE,
                    'station_id' => $station1,
                ]);

                if ($order['platform'] == PLATFORM_ZHIMA) {
                    $sql3 = db('TradeZhima')->where('id', $order_id)->update([
                        'status'      => ZHIMA_ORDER_COMPLETE_WAIT,
                        'update_time' => time(),
                    ]);
                } else {
                    $sql3 = model('User')->returnBack($order['uid'], $return_fee, $order['price']);
                }
                if($sql1 && $sql2 && $sql3){
                    Db::commit();
                } else {
                    Db::rollback();
                    Log::error("part return deposit:return deposit fail");
                    return make_error_data(1, '退款失败。');
                }

                $order['usefee'] += ($order['price'] - $return_fee);
                $message = unserialize($order['message']);
                $message['refund_fee'] += $return_fee;
                $message['refund_fee'] = round($message['refund_fee'], 2);
                $message['manually_return_time'] = time();
                $message['operator'] = $this->adminInfo['id'];
                model('Tradelog')->get($order_id)->save(['usefee' => $order['usefee'], 'message' => serialize($message)]);
                $msg = [
                    'openid'  => $order['openid'],
                    'refund'  => $return_fee,
                    'orderid' => $order['orderid'],
                ];
                TplMsg::send(TplMsg::MSG_TYPE_REFUND_FEE, $msg);
                Log::info("part return deposit:all success");

                // 这里退款实际上就是钱包明细支付
                if ($order['usefee'] > 0) {
                    // 记录用户流水
                    model('WalletStatement')->insert([
                        'uid'        => $order['uid'],
                        'time'       => date('Y-m-d H:i:s'),
                        'type'       => $order['platform'] == PLATFORM_ZHIMA ? WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED : WALLET_TYPE_PAID,
                        'amount'     => $order['usefee'],
                        'related_id' => $order_id,
                    ]);
                    Log::info('wallet pay record , orderid: ' . $order_id . ' amount: ' . $order['usefee']);
                }

                $this->logReturnBack($order_id, $return_fee, $order['uid']);
                if ($order['platform'] == PLATFORM_ZHIMA) {
                    return make_error_data(0, '芝麻信用退款成功： ');
                } else {
                    return make_error_data(0, '押金退还成功，退款金额： ' . $return_fee);
                }
            } else {
                Log::error("part return deposit: calc fee fail, return_fee: $return_fee , refund_fee: {$message['refund_fee']} , price: {$order['price']}");
                return make_error_data(1, '总退款金额超过支付押金');
            }
        }

        // 手动退款
        elseif ($money_return_operation) {
            $message    = unserialize($order['message']);
            $return_fee = $deposit + 0;

            if ($return_fee <= 0) {
                return make_error_data(1, '退款不能少于0');
            }

            // 支付过的订单都可以进行退款操作(包括已退款的)
            // 由于之前归还订单没有在message里面所以进行了以下处理
            if ($return_fee + $message['refund_fee'] <= $order['price']) {
                // 事务处理
                Db::startTrans();
                $sql1 = db('Tradelog')->where('orderid', $order_id)->update([
                    'status' => ORDER_STATUS_RETURN_EXCEPTION_MANUALLY_REFUND,
                    'lastupdate'             => time(), // 手动退款，有可能是多次手动退款，所以必须加入lastupdate保证$updateTradelogResult每次都可以执行成功
                    'return_time'            => $order['return_time'] ? : time(),
                    'return_station'         => $order['return_station'] ? : $order['borrow_station'],
                    'return_shop_id'         => $order['return_shop_id'] ? : $order['borrow_shop_id'],
                    'return_station_name'    => $order['return_station_name'] ? : $order['borrow_station_name'],
                    'return_shop_station_id' => $order['return_shop_station_id'] ? : $order['borrow_shop_station_id'],
                ]);
                if (in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
                    // 借出状态的订单有押金
                    $deposit = $order['price'];
                    // 借出状态没有usefee, usefee === 0
                    $usefee = $order['price'] - $return_fee;
                } else {
                    // 非借出状态的订单没有押金
                    $deposit = 0;
                    // 非借出状态有usefee (可能为0, 或者大于0)
                    $usefee = $order['usefee'] - $return_fee;  // $usefee 有可能是负数, 但是数据库里面是UNSIGN, 所以没关系
                }
                $sql2 = model('User')->returnBack($order['uid'], $return_fee, $deposit);

                if($sql1 && $sql2){
                    Db::commit();
                } else {
                    Db::rollback();
                    Log::error("money return deposit : return deposit fail");
                    return make_error_data(1, '押金退还失败。');
                }
                //更新雨伞信息
                model('Umbrella')->get($order['umbrella_id'])->save(['status' => UMBRELLA_INSIDE]);
                $message['operator']             = $this->adminInfo['id'];
                $message['refund_fee']           += $return_fee;
                $message['refund_fee']           = round($message['refund_fee'] , 2);
                $message['manually_return_time'] = time();

                model('Tradelog')->get($order_id)->save([
                    'usefee'  => $usefee,
                    'message' => serialize($message)
                ]);

                $msg = [
                    'openid'     => $order['openid'],
                    'orderid'    => $order['orderid'],
                    'refund'     => $return_fee,
                ];
                TplMsg::send(TplMsg::MSG_TYPE_REFUND_FEE, $msg);
                Log::info("money return deposit:all success");
                model('WalletStatement')->insert([
                    'uid'        => $order['uid'],
                    'type'       => WALLET_TYPE_REFUND,
                    'time'       => date('Y-m-d H:i:s'),
                    'amount'     => $return_fee,
                    'related_id' => $order_id,
                ]);
                $this->logReturnBack($order_id, $return_fee, $order['uid']);
                return make_error_data(0, '押金退还成功');
            } else {
                Log::error("money return deposit:change order three fields fail");
                return make_error_data(1, '退款金额超出限制, 订单最多可退款: '. ($order['price'] = $message['refund_fee']));
            }
        }
    }

    public function lostOrderFinish($order_id){
        $order = model('Tradelog')->get($order_id);
        Log::info('lost order finish order info, ' . print_r($order, 1));
        if(!in_array($order['status'], [ORDER_STATUS_RENT_CONFIRM, ORDER_STATUS_RENT_CONFIRM_FIRST])) {
            return make_error_data(1, '非借出状态的订单不能进行遗失处理！');
        }
        // 事务处理
        Db::startTrans();
        $sql1 = $order->save([
            'status' => ORDER_STATUS_TIMEOUT_NOT_RETURN,
            'usefee' => $order['price'],
            'return_time' => time(),
            'lastupdate' => time()
        ]);

        if($order['platform'] == PLATFORM_ZHIMA){
            Log::info("zhima order");
            $sql2 = model('TradeZhima')->get($order_id)->save(['status' => ZHIMA_ORDER_COMPLETE_WAIT, 'update_time'=>time()]);
        } else {
            $sql2 = model('User')->reduceDeposit($order['uid'], $order['price']);
        }
        if ($sql1 && $sql2) {
            Log::info("lost order finish order success");
            DB::commit();
            // 记录用户流水
            model('WalletStatement')->insert([
                'uid'        => $order['uid'],
                'time'       => date('Y-m-d H:i:s'),
                'type'       => $order['platform'] == PLATFORM_ZHIMA ? WALLET_TYPE_ZHIMA_PAID_UNCONFIRMED : WALLET_TYPE_PAID,
                'amount'     => $order['price'],
                'related_id' => $order_id,
            ]);
            return make_error_data(0, '处理成功!');
        } else {
            Log::error("reduce deposit failed: {$order['orderid']} , reduce deposit: {$order['price']}");
            DB::rollback();
            return make_error_data(1, '遗失处理失败');
        }
    }

    public function feeSettings($settings){
        $res = model('CommonSetting')->insert([
            'skey'   => 'fee_settings',
            'svalue' => json_encode($settings)
        ], 1);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_FEE_SETTINGS,
                'detail' => '设置全局收费策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        }else{
            return false;
        }
    }

    public function localFeeSettingsAdd($settings, $name){
        $res = model('FeeStrategy')->insert(['name' => $name, 'fee' => json_encode($settings)]);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOCAL_FEE_SETTINGS_ADD,
                'detail' => '添加局部收费策略'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        }else{
            return false;
        }
    }

    public function localFeeSettingsEdit($settings, $fee_strategy_id, $name){
        $res = model('FeeStrategy')->get($fee_strategy_id)->save(['name'=>$name, 'fee'=>json_encode($settings)]);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOCAL_FEE_SETTINGS_EDIT,
                'detail' => '编辑局部收费策略'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        }else{
            return false;
        }
    }

    public function systemSettings($settings){
        $res = model('CommonSetting')->insert([
            'skey'   => 'system_settings',
            'svalue' => json_encode($settings)
        ], 1);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_SYSTEM_SETTINGS,
                'detail' => '设置全局同步策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        }else{
            return false;
        }
    }

    public function localFeeSettingsDelete($fee_strategy_id){
        $res = model('FeeStrategy')->get($fee_strategy_id)->delete();

        if ($res) {
            $fee_info = model('FeeStrategy')->get($fee_strategy_id);
            $name     = $fee_info['name'];
            $settings = $fee_info['fee'];
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_LOCAL_FEE_SETTINGS_DELETE,
                'detail' => '删除局部收费策略 名称: '. $name . '策略: ' .$settings,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        } else {
            return false;
        }
    }

    public function stationSettingsAdd($settings, $name){
        $res = model('StationSettings')->insert(['name'=>$name, 'settings'=>json_encode($settings)]);

        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_STATION_SETTINGS_ADD,
                'detail' => '添加局部同步策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        } else {
            return false;
        }
    }

    public function stationSettingsEdit($shop_station_id, $settings, $name){
        $res = model('StationSettings')->get($shop_station_id)->save(['name'=>$name, 'settings'=>json_encode($settings)]);

        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_STATION_SETTINGS_EDIT,
                'detail' => '编辑局部同步策略'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        } else {
            return false;
        }
    }

    public function stationSettingsDelete($shop_station_id){
        $res = model('StationSettings')->update($shop_station_id,['status'=>self::STATUS_DELETE]);

        if ($res) {
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_STATION_SETTINGS_DELETE,
                'detail' => '删除局部同步策略:'.$shop_station_id,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        } else {
            return false;
        }
    }

    public function pictextSettingsAdd($settings, $name){
        $res = model('PictextSettings')->insert([
                'name'    => $name,
                'stime'   => $settings['stime'],
                'etime'   => $settings['etime'],
                'pictext' => json_encode($settings['pictext'])
        ], 1);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_PICTEXT_SETTINGS_ADD,
                'detail' => '添加图文消息配置'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        }else{
            return false;
        }
    }

    public function pictextSettingsEdit($settings, $pictext_id, $name){
        $res = model('PictextSettings')->get($pictext_id)->save([
            'name'    => $name,
            'stime'   => $settings['stime'],
            'etime'   => $settings['etime'],
            'pictext' => json_encode($settings['pictext'])
        ], 1);

        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_PICTEXT_SETTINGS_EDIT,
                'detail' => '编辑图文消息配置'.json_encode([$name,$settings]),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        }else{
            return false;
        }
    }

    public function pictextSettingsDelete($pictext_id){
        $res = model('PictextSettings')->get($pictext_id)->delete();
        if ($res) {
            $pictext_info = model('PictextSettings')->get($pictext_id);
            $name         = $pictext_info['name'];
            $settings     = $pictext_info['pictext'];
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_PICTEXT_SETTINGS_DELETE,
                'detail' => '删除图文消息配置 名称: '. $name . '配置: ' .$settings,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        } else {
            return false;
        }
    }

    public function globalSettings($settings){
        $res = model('CommonSetting')->get('global_settings')->save(['svalue' => json_encode($settings)]);
        if($res){
            $data = [
                'uid' => $this->adminInfo['id'],
                'type' => self::EVENT_GLOBAL_SETTINGS,
                'detail' => '设置客服电话'.json_encode($settings),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->adminLog->insert($data, true);
            return true;
        }else{
            return false;
        }
    }

	public function logAddZeroFeeUser($openid)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_ADD_ZERO_FEE_USER,
            'detail' => json_encode(['openid' => $openid, 'desc' => '增加零费用用户openid'], JSON_UNESCAPED_UNICODE),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        return $this->adminLog->insert($data);
    }

	public function logDeleteZeroFeeUser($openid)
    {
        $data = [
            'uid' => $this->adminInfo['id'],
            'type' => self::EVENT_DELETE_ZERO_FEE_USER,
            'detail' => json_encode(['openid' => $openid, 'desc' => '移除零费用用户openid'], JSON_UNESCAPED_UNICODE),
            'create_time' => date("Y-m-d H:i:s"),
        ];
        return $this->adminLog->insert($data);
    }
}
