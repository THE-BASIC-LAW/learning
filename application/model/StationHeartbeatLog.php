<?php

namespace app\model;

use think\Model;

class StationHeartbeatLog extends Model
{
    public function heartbeat($station_id) {
        $this->save(['station_id' => $station_id, 'created_at' => time()]);
    }

    public function findAllBySearch($begin_time, $end_time, $station_id, $page_size = 0) {
        return $this
            ->where('station_id', $station_id)
            ->whereBetween('created_at', [$begin_time, $end_time])
            ->order('id DESC')->paginate($page_size, false, ['query'=>$_GET]);
    }

    public function countAllBySearch($begin_time, $end_time, $station_id) {
        return $this
            ->where('station_id', $station_id)
            ->whereBetween('created_at', [$begin_time, $end_time])
            ->count();
    }

    public function getTimeList($begin_time, $end_time, $station_id){
        return $this
            ->where('station_id', $station_id)
            ->whereBetween('created_at', [$begin_time, $end_time])
            ->column('create_at');
    }

}
