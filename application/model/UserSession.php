<?php

namespace app\model;

use think\Model;

class UserSession extends Model
{
    // 用户端session过期时间
    const MAX_PLATFORM_SESSION_EXPIRE_TIME = 1800;

    const SALT = 'Nothing Is Impossible';

    private function _getSessionId($uid)
    {
        return md5(self::SALT . $uid . md5(microtime(true) . mt_rand()));
    }

    public function addSession($uid)
    {
        $session = $this->_getSessionId($uid);
        $this->insert([
            'uid' => $uid,
            'session' => $session,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ]);
        return $session;
    }

    /**
     * 通过session找uid
     * @param $session
     * @return bool|mixed
     */
    public function getUidBySession($session)
    {
        if (empty($session)) return false;
        $userSession = $this->where('session', $session)->find();
        if (!$userSession) return false;
        if ($userSession['update_time'] + self::MAX_PLATFORM_SESSION_EXPIRE_TIME > time()) return false;
        return $userSession['uid'];
    }

    public function updateSessionTime($session)
    {
        $this->update(['update_time' => date('Y-m-d H:i:s')], ['session' => $session]);
    }
}
