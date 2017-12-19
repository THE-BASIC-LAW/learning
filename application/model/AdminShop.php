<?php

namespace app\model;

use think\Model;

class AdminShop extends Model
{
	const STATUS_APPLY = 0; // 申请中
    const STATUS_PASS = 1;

    public static $STATUS = [
        self::STATUS_APPLY => '申请中',
        self::STATUS_PASS  => '通过',
    ];

    public function changeShopStatus($admin_shop_id, $before, $after)
    {
        $count = $this->where(['id' => $admin_shop_id, 'status' => $before])->count();
        if (empty($count) || count((array) $admin_shop_id) != $count) return false;
        return $this->where(['status' => $before, 'id' => $admin_shop_id])->save(['status' => $after]);
    }

    public function getAccessShops($admin_id)
    {
        $shop_ids = $this->where(['admin_id' => $admin_id, 'status' => self::STATUS_PASS])->column('shop_id');
        return array_values($shop_ids);
    }
}
