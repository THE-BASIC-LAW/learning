<?php
namespace app\controller\cp;

use app\controller\Cp;
use app\lib\Api;

class UserCp extends Cp
{
	private $user = null;

	public function _initialize(){
		parent::_initialize();
		$this->user = model('User', 'logic');
		$this->assign([
			'platformArr'	=>	[ 0 => '微信',1 => '支付宝',2 => '全部'],	// 支付平台数组
			'statusArr'	=>	[0 => '全部',1 => '借出中',2 => '已归还'],	// 租借状态
		]);
	}

	public function userList()
	{
		$users = $this->user->getAllUsersLists($this->request->except(['mod', 'opt', 'act']));
		$this->assign([
			"users"	=>	$users,
			'platform' => $this->request->param('platform')?$this->request->param('platform'):'2',
			'status' => $this->request->param('status')?$this->request->param('status'):'0',
			'nickname' => $this->request->param('nickname')?$this->request->param('nickname'):'',
			'id' => $this->request->param('id')?$this->request->param('id'):'',
			'openid' => $this->request->param('openid')?$this->request->param('openid'):'',
			'searchTime' => $this->request->param('searchTime')?$this->request->param('searchTime'):'',
		]);
		return $this->fetch("cp/user_cp/userList/userList");
	}

	public function refundList()
	{
		$refundLists = $this->user->getUserRefundLists($this->request->except(['mod', 'opt', 'act']));

		$this->assign([
			'refundLists' => $refundLists,
			'searchType' => $this->request->param('searchType')?$this->request->param('searchType'):'openid',
		]);
		return $this->fetch("cp/user_cp/refundList/refundList");
	}

	public function zeroFeeUserList()
	{
		if ($this->request->param('do')) {

			switch ($GLOBALS['do']) {
				case 'add':

					if ($this->request->isAjax()) {

						if (!model('User')->where('openid', $GLOBALS['openid'])->find()) {
							Api::output([], 1, '用户不存在');
						}

						$addResult = $this->user->addZeroFeeUser($GLOBALS['openid']);

						if ($addResult) {
							$this->admin->logAddZeroFeeUser($GLOBALS['openid']);
							Api::output([], 0, '添加成功');
						}else {
							Api::output([], 1, 'openid重复，添加失败');
						}
					}
					return $this->fetch("cp/user_cp/zeroFeeUserList/add");
					break;

				case 'delete':
					if ($this->request->isAjax()) {

						if (!model('User')->where('openid', $GLOBALS['openid'])->find()) {
							Api::output([], 1, '用户不存在');
						}

						$deleteResult = $this->user->deleteZeroFeeUser($GLOBALS['openid']);

						if ($deleteResult) {
							$this->admin->logDeleteZeroFeeUser($GLOBALS['openid']);
							Api::output([], 0, 'openid移除成功');
						}else {
							Api::output([], 1, 'openid不是零收费用户');
						}
					}
					break;

				default:
					exit;
			}
		}
		$zeroFeeUserList = $this->user->getZeroFeeUserLists();

		$this->assign([
			'zeroFeeUserList' => $zeroFeeUserList,
		]);
		return $this->fetch("cp/user_cp/zeroFeeUserList/zeroFeeUserList");
	}
}
