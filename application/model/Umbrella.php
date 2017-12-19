<?php

namespace app\model;

use app\logic\TplMsg;
use think\Model;
use think\Log;

class Umbrella extends Model
{
    // 相关模型
    protected $common_setting = null;

    protected $user = null;

    protected $tradelog = null;


    public function handleRent($id, $order_id){
        return $this->get($id)->save([
            'slot'       => 0,
            'status'     => UMBRELLA_OUTSIDE,
            'order_id'   => $order_id,
            'sync_time'  => time(),
            'station_id' => 0,
        ]);
    }

    public function newUmbrella($id, $station_id, $slot){
        return $this->save([
            'id'         => $id,
            'station_id' => $station_id,
            'sync_time'  => time(),
            'slot'       => $slot,
        ]);
    }

    public function getLimitedUmbrellas($station_id, $limit) {
        if ($limit == 0){
            return [];
        }
        return $this
            ->where(['station_id' => $station_id, 'status' => ['<>', UMBRELLA_OUTSIDE]])
            ->order('sync_time desc')
            ->limit($limit)->select();
    }

    public function getAllStationIdsWithUmbrellaSync() {
        $station_ids = $this->where(['status' => UMBRELLA_OUTSIDE_SYNC, 'station_id' => ['>', '0']])->group('station_id')->column('station_id');
        return array_values($station_ids);
    }
}
