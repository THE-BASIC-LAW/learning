<?php

namespace app\model;

use think\Model;

class FeeStrategy extends Model
{

    public function getStrategySettings($id)
    {
        $rst = $this->find($id);
        if ($rst) {
            return json_decode($rst['fee'], true);
        }
        $rst = db('common_setting')->find('fee_settings');
        return json_decode($rst['svalue'], true);
    }
}
