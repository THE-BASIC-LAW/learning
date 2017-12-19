<?php

namespace app\model;

use think\Model;

class Qrcode extends Model
{
    public function getStationId(string $qrcode, int $platform)
    {
        switch ($platform) {
            case PLATFORM_WX:
                $field = 'wx';
                break;

            case PLATFORM_ALIPAY:
                $field = 'alipay';
                break;

            default:
                return false;
        }
        $result = $this->where($field, $qrcode)->find();
        if ($result) {
            return $result['id'];
        }
        return false;
    }
}
