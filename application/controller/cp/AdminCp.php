<?php
namespace app\controller\cp;

use app\controller\Cp;
use app\lib\Api;
use app\model\CommonSetting;
use app\third\wxServer;
use think\Session;

class AdminCp extends Cp
{

	public function _initialize(){
		parent::_initialize();
		$this->assign([
			'admin' => $this->admin,
		]);
	}

    /**
     * 显示后台首页
     *
     * @return \think\Response
     */
    public function index()
    {
        return $this->fetch();
    }

	/**
	 * 角色管理
	 *
	 * @return \think\Response
	 */
	public function role()
	{
		if ($GLOBALS['do']) {

			$access = $this->request->param('access/a');
			$role = $this->request->param('role');
			$global_search = $this->request->param('global_search');

			switch ($GLOBALS['do']) {
				case 'add':
					if ($this->request->isAjax()) {
						if ($this->auth->isAuthorizedAction($access, $GLOBALS['jjsan_nav_tree']) && $this->auth->createNewRole($role, $access, $global_search)) {

	                        $this->admin->logAddRole($role);
	                        Api::output([], 0, '角色创建成功');
	                    } else {
	                        Api::output([], 1, '角色创建失败');
	                    }

					}else {
						$jjsan_nav_tree = $GLOBALS['jjsan_nav_tree'];

						$this->assign('jjsan_nav_tree', $jjsan_nav_tree);
						return $this->fetch("cp/admin_cp/role/roleAdd");
					}
					break;

				case 'edit':
					$role_id = $this->request->param('role_id');

					if ($this->request->isAjax()) {
						if ($this->auth->isAuthorizedAction($access, $GLOBALS['jjsan_nav_tree']) && $this->auth->updateRoleAccess($role_id, $role, $access, $global_search)) {

							// 记录操作
	                        $this->admin->logEditRole($role_id, $role);
							Api::output([], 0, '角色更新成功');
	                    } else {
	                        Api::output([], 1, '角色更新失败');
	                    }

					}else {
						$role = $this->auth->getRoleInfo($role_id);
						$role['access'] = json_decode($role['access']);

						$jjsan_nav_tree = $GLOBALS['jjsan_nav_tree'];

						$this->assign([
                            'role'		     => $role,
                            'jjsan_nav_tree' => $jjsan_nav_tree,
                        ]);

						return $this->fetch("cp/admin_cp/role/roleEdit");
					}

					break;

				default:
					# code...
					break;
			}
		}

		$roles = $this->auth->getAllRoles(true);

		$this->assign('roles', $roles);

		return $this->fetch("cp/admin_cp/role/role");
	}

	/**
	 * 系统用户管理
	 *
	 * @return \think\Response
	 */
	public function adminManage(){

		$roles = $this->auth->getAllRoles();
		foreach($roles as $v) {
			$rolesArray[$v['id']] = $v['role'];
		}

		if ($GLOBALS['do']) {
			$aid = $this->request->param('aid');
			switch ($GLOBALS['do']) {
				case 'pass':
                case 'refuse':
                case 'lock':
                case 'unlock':
                case 'delete':
                case 'resume':
					// 非自己
					if ($aid != $this->admin->adminInfo['id']) {
						// 不支持同级别角色变动彼此账户状态
                        if ($this->admin->isTheSameRole([$aid, $this->admin->adminInfo['id']])) {
                            Api::output([], 1, '操作失败');
                        }
					}
					if($this->admin->handleRoleApplyUsers($aid, $GLOBALS['do'])) {
                        Api::output([], 0, '操作成功');
                    } else {
                        Api::output([], 1, '操作失败');
                    }
					break;

				case 'edit':
					if ($this->request->isAjax()) {
                            if ($this->admin->updateAdminUserInfo($this->request->param())) {
                                Api::output([], 0, '更新成功');
                            } else {
                                Api::output([], 1, '更新失败');
                            }
					}
					$this->adminInfo = $this->admin->getAdminUserInfo($aid);
                    $this->assign('adminInfo', $this->adminInfo);
                    return $this->fetch("cp/admin_cp/adminManage/adminEdit");



				default:
					# code...
					break;
			}
		}

		if ($search_name = $this->request->param('sname')) {
			$lists = $this->admin->getAdminByName($search_name, true);
		}else {
			$lists = $this->admin->allAdmins(true);
		}


		$this->assign([
            'userLists' => $lists,
            'rolesArray'=> $rolesArray,
        ]);

		return $this->fetch("cp/admin_cp/adminManage/adminManage");
	}

	/**
	 * 修改密码
	 *
	 * @return \think\Response
	 */
	public function pwd()
	{
		if ($this->request->isPost()) {
			$data['oldpassword'] = $this->request->param('oldpassword');
			$data['newpassword'] = $this->request->param('newpassword');
			if ($this->admin->changePassword($data)) {
				Session::clear();
				$this->success('密码修改成功，请重新登录', '/cp/login/index');
			}else {
				$this->error('密码修改失败', $_SERVER['HTTP_REFERER']);
			}
		}else {
			return $this->fetch("cp/admin_cp/pwd/pwd");
		}
	}

    public function installManManage()
    {

        if ($GLOBALS['do']) {
            switch ($GLOBALS['do']) {
                case 'add':
                    $qrcode = wxServer::instance()->qrcode;
                    $result = $qrcode->temporary(getApplyInstallManSceneId(), 30);
                    $qrcodeUrl = $qrcode->url($result->ticket);
                    return view('cp/admin_cp/installManManage/add', compact('qrcodeUrl'));

                case 'pass':
                    (new CommonSetting())->passInstallManApply($this->request->get('id'));
                    exit;

                case 'setCommon':
                    (new CommonSetting())->setCommonInstallMan($this->request->get('id'));
                    exit;

                case 'setInstall':
                    (new CommonSetting())->setInstallMan($this->request->get('id'));
                    exit;

                case 'delete':
                    (new CommonSetting())->deleteInstallMan($this->request->get('id'));
                    exit;

                default:
                    exit;

            }
        }

        $installInfo = (new CommonSetting())->getAllMaintainInfo();
        return view('cp/admin_cp/installManManage/index', compact(
            'installInfo'
        ));
	}

	public function accessVerify(){
		if ($this->request->param('do')) {
			extract($this->request->except(['mod', 'act', 'opt']));
			switch ($GLOBALS['do']) {
				case 'pass':
					if($this->auth->handleCityApplyUsers($aid, $GLOBALS['do'])) {
                        Api::output([], 0, '操作成功');
                    } else {
                        Api::output([], 1, '操作失败');
                    }
					break;

				default:
					# code...
					break;
			}
		}

		$info = $this->auth->applyCitesInfo(true);

		$this->assign([
			'info'	=>	$info,
		]);
		return $this->fetch("cp/admin_cp/accessVerify/accessVerify");
	}

	public function shopAccessVerify(){
		if ($this->request->param('do')) {
			extract($this->request->except(['mod', 'act', 'opt']));
			switch ($GLOBALS['do']) {
				case 'pass':
					if($this->auth->handleShopApplyUsers($adminShopId, $GLOBALS['do'])) {
                        Api::output([], 0, '操作成功');
                    } else {
                        Api::output([], 1, '操作失败');
                    }
					break;

				default:
					# code...
					break;
			}
		}

		$adminShops = $this->auth->applyShopsInfo();

		$this->assign([
			'adminShops'	=>	$adminShops,
			'shop'	=> model('Shop'),
		]);
		return $this->fetch("cp/admin_cp/shopAccessVerify/shopAccessVerify");
	}

	public function accessApply(){
		$shopName = $this->request->param('shopName');
		$province = $this->request->param('province');
		$city = $this->request->param('city');
		$area = $this->request->param('area');
		if($GLOBALS['do']){
			switch ($GLOBALS['do']) {
				case 'cityApply':

                    if ($this->auth->isAuthorizedCity($cities, $GLOBALS['area_nav_tree']) && $this->auth->addCityAccess($cities)) {
                        Api::output([], 0, '申请城市权限成功');
                    } else {
                        Api::output([], 1, '申请城市权限失败');
                    }
                    break;

				case 'cityModify':
                    if ($this->auth->isAuthorizedCity($cities, $GLOBALS['area_nav_tree']) && $this->auth->modifyCityAccess($cities)) {
                        Api::output([], 0, '修改城市权限成功');
                    } else {
                        Api::output([], 1, '修改城市权限失败');
                    }
                    break;

				case 'cityDelete':
					if ($this->request->isAjax()) {
						if ($this->auth->deleteCurrentCitiesAccess()) {
	                        Api::output([], 0, '城市权限删除成功');
	                    } else {
	                        Api::output([], 1, '城市权限删除失败');
	                    }
					}

                    break;

				case 'shopApply':
					if ($this->request->isAjax()) {
						if ($this->auth->addShopAccess($shopId)) {
							Api::output([], 0, '商铺权限申请成功');
						}else{
							Api::output([], 1, '商铺权限申请失败');
						}
					}

					break;

				case 'shopDelete':
					if ($this->auth->deleteShopAccess($adminShopId)) {
						Api::output([], 0, '商铺权限删除成功');
					}else{
						Api::output([], 1, '商铺权限删除失败');
					}
					break;

				default:
					# code...
					break;
			}
		}

		$shop = model('Shop', 'logic');

		// 已申请的商铺
		$shopsApply = $this->auth->getShopsApply();
		// 查询商铺
		$shops = [];
        if($shopName || $province || $city || $area){
            $shops = $shop->searchShops($shopName, $province, $city, $area);
        }

		$this->assign([
            'areaNavTree' => $GLOBALS['area_nav_tree'],
			'cityStatus' => $this->auth->checkCityStatus(),
			'cities'	=>	$this->auth->getCity(),
			'shopApplys'	=>	$shopsApply,
			'provinces'	=>	array_map(function($v){
											return $v['province'];
										}, $GLOBALS['area_nav_tree']),
			'shops'	=> $shops,
        ]);
		return $this->fetch("cp/admin_cp/accessApply/accessApply");
	}
}
