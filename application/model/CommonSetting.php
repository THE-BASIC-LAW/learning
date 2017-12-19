<?php

namespace app\model;

use app\third\wxServer;
use think\Model;
use think\Log;

class CommonSetting extends Model
{
    protected $pk = 'skey';

    /**
     * @var string 常用主键名称
     */
    protected $skey_wechat_access_token   = 'wechat_access_token';
    protected $skey_wechat_js_api         = 'wechat_js_api';
    protected $skey_install_man           = 'install_man';
    protected $skey_install_man_user      = 'install_man_user';
    protected $skey_install_man_verifying = 'install_man_verifying';
    protected $skey_zero_fee_user_list    = 'zero_fee_user_list';


    /**
     * 获取skey对应的svalue值
     * @param $skey
     * @param bool $json_decode 默认不解析json数据
     * @return mixed
     */
    protected function getValue($skey, $json_decode = false)
    {
        $rst = self::get($skey);
        if ($rst && $rst->svalue) {
            if ($json_decode) {
                return json_decode($rst->svalue, 1);
            }
            return $rst->svalue;
        }
        if ($json_decode) {
            return [];
        }
        return false;
    }

    public function applyInstallMan(UserInfo $user)
    {
        $installManInfo = $this->getValue($this->skey_install_man, true);
        $installManUserInfo = $this->getValue($this->skey_install_man_user, true);
        $installManVerifyingInfo = $this->getValue($this->skey_install_man_verifying, true);
        if (key_exists($user['id'], $installManInfo)) return false;
        if (key_exists($user['id'], $installManUserInfo)) return false;
        if (key_exists($user['id'], $installManVerifyingInfo)) return false;
        if ($installManVerifyingInfo) {
            $installManVerifyingInfo[$user['id']] = $user['nickname'];
            return $this->save(['svalue' => json_encode($installManVerifyingInfo)], ['skey' => $this->skey_install_man_verifying]);
        } else {
            $installManVerifyingInfo[$user['id']] = $user['nickname'];
            return $this->insert(['skey' => $this->skey_install_man_verifying, 'svalue' => json_encode($installManVerifyingInfo)]);
        }
    }

    public function addInstallMan(UserInfo $user)
    {
        Log::info('add maintain user: ' . $user->id);
        $ret = $this->get($this->skey_install_man);
        if (empty($ret)) {
            $data = [$user->id => $user->nickname];
            return $this->save(['skey' => $this->skey_install_man, 'svalue' => json_encode($data)]);
        } else {
            if (empty($ret['svalue'])) {
                $data = [$user->id => $user->nickname];
            } else {
                $data = json_decode($ret['svalue'], true);
                $data[$user->id] = $user->nickname;
            }
            return $this->update(['skey' => $this->skey_install_man, 'svalue' => json_encode($data)]);
        }
    }

    // 维护人员---用户角色
    public function isMaintainUser($uid)
    {
        if (empty($uid)) return false;
        $ret = $this->getValue($this->skey_install_man_user, true);
        if (key_exists($uid, $ret)) {
            return true;
        }
        return false;
    }

    // 维护人员---维护角色
    public function isMaintainMan($uid)
    {
        if (empty($uid)) return false;
        $ret = $this->getValue($this->skey_install_man, true);
        if (key_exists($uid, $ret)) {
            return true;
        }
        return false;
    }

    /**
     * 获取所有维护人员信息
     * @return array
     */
    public function getAllMaintainInfo()
    {
        $installManInfo = $this->getValue($this->skey_install_man, true);
        $installManUserInfo = $this->getValue($this->skey_install_man_user, true);
        $installManVerifyingInfo = $this->getValue($this->skey_install_man_verifying, true);

        $data = [];
        if ($installManVerifyingInfo) {
            foreach ($installManVerifyingInfo as $k => $v) {
                $data[] = [
                    'id'     => $k,
                    'status' => '待通过',
                    'name'   => json_decode($v, true),
                ];
            }
        }
        if ($installManInfo) {
            foreach ($installManInfo as $k => $v) {
                $data[] = [
                    'id'     => $k,
                    'status' => '维护角色',
                    'name'   => json_decode($v, true),
                ];
            }
        }
        if ($installManUserInfo) {
            foreach ($installManUserInfo as $k => $v) {
                $data[] = [
                    'id'     => $k,
                    'status' => '用户角色',
                    'name'   => json_decode($v, true),
                ];
            }
        }

        return $data;
    }


    /**
     * 通过维护人员申请
     * @param $uid
     * @return bool
     */
    public function passInstallManApply($uid)
    {
        if (empty($uid)) return false;
        $verifyingInfo = $this->getValue($this->skey_install_man_verifying, true);
        if (!$verifyingInfo || !key_exists($uid, $verifyingInfo)) return false;
        unset($verifyingInfo[$uid]);
        if ($verifyingInfo) {
            $this->save(['svalue' => json_encode($verifyingInfo)], ['skey' => $this->skey_install_man_verifying]);
        } else {
            $this->where('skey', $this->skey_install_man_verifying)->delete();
        }
        return $this->addInstallMan(UserInfo::get($uid));
    }

    /**
     * 删除维护人员信息
     * @param $uid
     * @return bool
     */
    public function deleteInstallMan($uid)
    {
        if (empty($uid)) return false;
        $installManInfo = $this->getValue($this->skey_install_man, true);
        if (key_exists($uid, $installManInfo)) {
            unset($installManInfo[$uid]);
            if ($installManInfo) {
                return $this->save(['svalue' => json_encode($installManInfo)], ['skey' => $this->skey_install_man]);
            } else {
                return $this->where('skey', $this->skey_install_man)->delete();
            }
        }
        $installManUserInfo = $this->getValue($this->skey_install_man_user, true);
        if (key_exists($uid, $installManUserInfo)) {
            unset($installManUserInfo[$uid]);
            if ($installManUserInfo) {
                return $this->save(['svalue' => json_encode($installManUserInfo)], ['skey' => $this->skey_install_man_user]);
            } else {
                return $this->where('skey', $this->skey_install_man_user)->delete();
            }
        }
        $installManVerifyingInfo = $this->getValue($this->skey_install_man_verifying, true);
        if (key_exists($uid, $installManVerifyingInfo)) {
            unset($installManVerifyingInfo[$uid]);
            if ($installManVerifyingInfo) {
                return $this->save(['svalue' => json_encode($installManVerifyingInfo)], ['skey' => $this->skey_install_man_verifying]);
            } else {
                return $this->where('skey', $this->skey_install_man_verifying)->delete();
            }
        }
    }

    public function setCommonInstallMan($uid)
    {
        if (empty($uid)) return false;
        $installManInfo = $this->getValue($this->skey_install_man, true);
        if (key_exists($uid, $installManInfo)) {
            unset($installManInfo[$uid]);
            if ($installManInfo) {
                $this->save(['svalue' => json_encode($installManInfo)], ['skey' => $this->skey_install_man]);
            } else {
                $this->where('skey', $this->skey_install_man)->delete();
            }
            $installManUserInfo = $this->getValue($this->skey_install_man_user, true);
            if ($installManUserInfo) {
                $installManUserInfo[$uid] = UserInfo::get($uid)['nickname'];
                return $this->save(['svalue' => json_encode($installManUserInfo)], ['skey' => $this->skey_install_man_user]);
            } else {
                $installManUserInfo[$uid] = UserInfo::get($uid)['nickname'];
                return $this->insert(['skey' => $this->skey_install_man_user, 'svalue' => json_encode($installManUserInfo)]);
            }
        }
        return false;
    }

    public function setInstallMan($uid)
    {
        if (empty($uid)) return false;
        $installManUserInfo = $this->getValue($this->skey_install_man_user, true);
        if (key_exists($uid, $installManUserInfo)) {
            unset($installManUserInfo[$uid]);
            if ($installManUserInfo) {
                $this->save(['svalue' => json_encode($installManUserInfo)], ['skey' => $this->skey_install_man_user]);
            } else {
                $this->where('skey', $this->skey_install_man_user)->delete();
            }
            $installManInfo = $this->getValue($this->skey_install_man, true);
            if ($installManInfo) {
                $installManInfo[$uid] = UserInfo::get($uid)['nickname'];
                return $this->save(['svalue' => json_encode($installManInfo)], ['skey' => $this->skey_install_man]);
            } else {
                $installManInfo[$uid] = UserInfo::get($uid)['nickname'];
                return $this->insert(['skey' => $this->skey_install_man, 'svalue' => json_encode($installManInfo)]);
            }
        }
        return false;
    }

    /**
     * 变更维护人员角色
     * @param $uid
     * @return bool|int  0 返回用户角色 1 返回维护角色 false 变更失败
     */
    public function changeInstallManRole($uid)
    {
        $installManInfo = $this->getValue($this->skey_install_man, true);
        $installManUserInfo = $this->getValue($this->skey_install_man_user, true);

        if (key_exists($uid, $installManInfo)) {
            if ($this->setCommonInstallMan($uid)) {
                return 1;
            }
        }
        if (key_exists($uid, $installManUserInfo)) {
            if ($this->setInstallMan($uid)) {
                return 0;
            }
        }
        return false;
    }


    public function getZeroFeeUserList()
    {
        return $this->getValue($this->skey_zero_fee_user_list, true);
    }
}
