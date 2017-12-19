<?php
namespace app\logic;

use think\Db;
use think\Model;
use think\Session;

class User extends Model
{
	private $user;
	private $userInfo;

	public function __construct()
	{
		$this->user	= Db::name('user');
		$this->userInfo = Db::name('user_info');
		$this->tradelog = Db::name('tradelog');
	}

	/**
	* 用户列表中全体用户，含分页
	* @param 查询条件
	* @return 数据对象，若无则为null
	*/
	public function getAllUsersLists($conditon)
	{
		$where = [];
		if (!empty($conditon)) {
			extract($conditon);
			if (isset($platform) && $platform != 2) {
				$where['platform'] = $platform;
			}
			if ($id) {
				$where['id'] = $id;
			}
			if ($openid) {
				$where['openid'] = ['like', '%' . $openid . '%'];
			}
			if ($searchTime) {
				extract(getTimeRange($searchTime));// 或者开始和结束时间
				$where['create_time'] = [
					['>=', $startTime],
					['<=', $endTime],
				];
			}
			if ($nickname) {
				// 用户名 json 处理
				$nickname = str_replace('"','',trim(json_encode($nickname))); // 临时方案
				$nickname = str_replace("\\",'_',$nickname);

				$searchId = $this->userInfo->where('nickname', 'like', '%' . $nickname . '%')->column('id');
				if ($id) {
					if (!in_array($id, $searchId)) {
						return null;
					}
				}else {
					$where['id'] = ['in', $searchId];
				}
			}
			if ($status) {
				$searchByStatus = $this->tradelog->where('status', $status)->column('uid');
				if (!$searchByStatus) {
					return null;
				}
				if ($id) {
					if (!in_array($id, $searchByStatus)) {
						return null;
					}
				}else {
					$where['id'] = ['in', array_merge($searchId, $searchByStatus)];
				}
			}
		}

		$users = $this->user
					->order('create_time desc')
					->where($where)
					->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>request()->param()])
					->each(function($item, $key){
						$data = $this->userInfo
									->where('id', $item['id'])
									->find();
						$data['nickname'] = json_decode($data['nickname'], true);
						$item = array_merge($item, $data);
						$item['order_count'] = $this->tradelog->where('uid', $item['id'])->count();
						$item['usefee_count'] = $this->tradelog->where('uid', $item['id'])->sum('usefee');
						$item['outstanding_order_count'] = $this->tradelog
																->where('uid', $item['id'])
																->where('status', ORDER_STATUS_RENT_CONFIRM)
																->count();
						return $item;
					});
		return $users;
	}

	/**
	* 用户提现列表
	* @param arr 查询条件
	* @return obj 列表对象
	*/
	public function getUserRefundLists($conditon)
	{
		extract($conditon);
		$where = [];
		if (isset($searchType)) {
			switch ($searchType) {
				case 'openid':
					$where['openid'] = ['like', '%' . $searchValue . '%'];
					break;

				case 'nickname':
					// 用户名 json 处理
					$searchValue = str_replace('"','',trim(json_encode($searchValue))); // 临时方案
					$searchValue = str_replace("\\",'_',$searchValue);
					$where['nickname'] = ['like', '%' . $searchValue . '%'];
					break;
			}
		}

		if (isset($onlyRefunding)) {
			$whereForRefund['status'] = REFUND_STATUS_REQUEST;
		}

		$ids = $this->userInfo
				->where($where)
				->column('id');

		$whereForRefund['uid'] = ['in', $ids];
		$refundLists = Db::name('refund_log')
						->where($whereForRefund)
						->order('request_time', 'DESC')
						->paginate()
						->each(function($item, $key){
							$userInfo = $this->userInfo
										->where('id', $item['uid'])
										->find();

							$user = $this->user
										->where('id', $item['uid'])
										->find();

							$item['request_time'] = date("Y-m-d H:i:s", $item['request_time']);
							if ($item['refund_time'] == 0) {
						        $item['refund_time']  = '暂未退款';
						    } else {
						        $item['refund_time']  = date("Y-m-d H:i:s", $item['refund_time']);
						    }
							$item['nickname'] = json_decode($userInfo['nickname'], true);
							$item['openid'] = $userInfo['openid'];
							$item['platform'] = $user['platform'];
							$item['detail_count'] = count(json_decode($item['detail'], true));
							return $item;
						});
		return $refundLists;
	}

	/**
	* 获得存在 common_setting 中 zero_fee_user_list 字段的值
	* @return arr openid
	*/
	private function getZeroFeeUserListSvalue()
	{
		$data = Db::name('common_setting')
								->find('zero_fee_user_list');
		$openids = json_decode($data['svalue'], true);

		return $openids;
	}

	/**
	* 获取零收费人员列表
	*/
	public function getZeroFeeUserLists()
	{
		$openids = $this->getZeroFeeUserListSvalue();

		$zeroFeeUserList = $this->userInfo
								->where('openid', 'in', $openids)
								->paginate()
								->each(function($item, $key){
									$item['nickname'] = json_decode($item['nickname'], true);
									return $item;
								});
        return $zeroFeeUserList;
	}

	/**
	* 添加零收费人员
	* @param str 待添加人员的openid
	* @return bool 是否添加成功
	*/
	public function addZeroFeeUser($openid)
	{
		$openids = $this->getZeroFeeUserListSvalue();
		if (is_null($openids)) {
			return Db::name('common_setting')
							->insert([
								'skey' => 'zero_fee_user_list',
								'svalue' => json_encode([$openid])
							]);
		}elseif (empty($openids)) {
			return Db::name('common_setting')
							->update([
								'skey' => 'zero_fee_user_list',
								'svalue' => json_encode([$openid])
							]);
		}

		if (in_array($openid, $openids)) {
			return false;
		}else {
			array_push($openids, $openid);
			return Db::name('common_setting')
							->update([
								'skey' => 'zero_fee_user_list',
								'svalue' => json_encode($openids)
							]);
		}
	}

	/**
	* 删除零收费人员
	* @param str 待删除人员的openid
	* @return bool 是否删除成功
	*/
	public function deleteZeroFeeUser($openid)
	{
		$openids = $this->getZeroFeeUserListSvalue();
		if (empty($openids)) {
			return false;
		}

		$key = array_search($openid, $openids);

		if ($key !== false) {
			array_splice($openids, $key, strlen($openid));
			return Db::name('common_setting')
							->update([
								'skey' => 'zero_fee_user_list',
								'svalue' => json_encode($openids),
							]);
		}else {
			return false;
		}
	}
}
