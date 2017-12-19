<?php namespace app\model;

use think\Model;

class UserInfo extends Model
{

    public function addUser($user)
    {
        $data = [
            'id' => $user['id'],
            'openid' => $user['openid'],
            'nickname' => is_null($user['city']) ? '' : json_encode($user['nickname']),
            'sex' => is_null($user['sex']) ? 0 : $user['sex'], // 男1女2未知0
            'city' => is_null($user['city']) ? '' : $user['city'],
            'province' => is_null($user['province']) ? '' : $user['province'],
            'country' => is_null($user['country']) ? '' : $user['country'],
            'headimgurl' => is_null($user['headimgurl']) ? '' : $user['headimgurl'],
            'language' => is_null($user['language']) ? '' : $user['language'],
            'subscribe_time' => is_null($user['subscribe_time']) ? 0 : $user['subscribe_time'],
            'unionid' => is_null($user['unionid']) ? '' : $user['unionid'],
            'remark' => is_null($user['remark']) ? '' : $user['remark'],
            'groupid' => is_null($user['groupid']) ? '' : $user['groupid'],
        ];
        return$this->insert($data);
    }

}
