<?php

namespace app\model;

use think\Model;

class StationSettings extends Model
{

    const STATUS_NORMAL = 0;
    const STATUS_DELETE = -1;

    public function __construct($data = [])
    {
        parent::__construct($data);
    }

    public function getUsingSetting($id){
        $setting = $this->where('id', $id)->find()['settings'];
        if(!$setting){
            $setting = model('CommonSetting')->get('system_settings')['svalue'];
        }
        return json_decode($setting, 1);
    }

    public function allSettings(){
        return $this->all(['status' => ['<>', self::STATUS_DELETE]]);
    }
}
