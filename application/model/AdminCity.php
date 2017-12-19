<?php

namespace app\model;

use think\Model;

class AdminCity extends Model
{

    const STATUS_PASS = 1;

    public function __construct($data = [])
    {
        parent::__construct($data);
    }

    // 用户负责的城市
    public function getAccessCities($admin_id){
        $citys = []; // 用户负责的城市
        $tmp = self::where(['admin_id' => $admin_id,'status' => self::STATUS_PASS])->value('city');
        $tmp = json_decode($tmp['city']);
        foreach($tmp as $v){
            $res = explode('/',$v);
            if($res[1]){
                $citys[] = $res[1];
            }
        }
        return $citys;
    }
}
