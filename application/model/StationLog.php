<?php

namespace app\model;

use think\Model;

class StationLog extends Model
{
    public function updateLoginCount($id){
        $this->where('id', $id)->inc('login_count')->exp('updated_at', 'unix_timestamp()')->update();
    }

    public function updateHeartbeatCount($id){
        $this->where('id', $id)
             ->inc('heartbeat_count')
             ->exp('updated_at', 'unix_timestamp()')
             ->exp('online_time', 'heartbeat_count * 1.5')
             ->update();
    }
}
