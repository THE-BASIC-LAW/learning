<?php

namespace app\model;

use think\Model;

class StationSlotLog extends Model
{
    public function deleteStationSlotLog($station_id, $slot){
        return $this->where(['station_id' => $station_id, 'slot' => ['in', $slot]])->delete();
    }


    public function getLastSyncTime($station_id, $slot, $type){
        return $this->where([
            'station_id' => $station_id,
            'slot' => $slot,
            'type' => $type
        ])->order('id DESC')->find();
    }
}
