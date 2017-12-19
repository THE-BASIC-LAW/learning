<?php namespace app\crontab;

use app\third\wxServer;
use EasyWeChat\Core\Exceptions\HttpException;
use think\Log;


/**
 * 更新微信用户信息
 *
 * 执行频率： 每天一次
 *
 * Class WechatUpdateUserInfo
 *
 * @package app\crontab
 */
class UpdateWechatUserInfo implements CrontabInterface
{
    public function exec()
    {
        // 7天内更新过的用户不更新
        $userInfo = db('user_info')
            ->field('id,openid')
            ->where(['update_time' => ['<', date('Y-m-d H:i:s', time()-7*24*3600)]])
            ->select();

        if (empty($userInfo)) {
            Log::notice('not user info need update');
            return ;
        }
        $openidList = array_filter($userInfo, function($a){
            if (substr($a['openid'], 0, 4) == '2088') {
                return false;
            } else {
                return true;
            }
        });

        Log::info('need update openid count: ' . count($openidList));

        $userService = wxServer::instance()->user;
        $i = 0;
        foreach ($openidList as $v) {
            try {
                $user = $userService->get($v['openid']);

                // 用户还在关注
                if ($user->subscribe == 1) {
                    // 更新数据
                    $data['id'] = $v['id'];

                    $data['nickname']       = json_encode($user->nickname);
                    $data['sex']            = $user->sex;
                    $data['country']        = $user->country;
                    $data['province']       = $user->province;
                    $data['city']           = $user->city;
                    $data['headimgurl']     = $user->headimgurl;
                    $data['language']       = $user->language;
                    $data['subscribe_time'] = $user->subscribe_time;
                    // unionid 只有在用户将公众号绑定到微信开放平台帐号后，才会出现该字段。
                    $data['unionid']        = is_null($user->unionid) ? 0 : $user->unionid ;
                    $data['remark']         = $user->remark;
                    $data['groupid']        = $user->groupid;

                    db('user_info')->update($data);
                    Log::info('update uid: ' . $v['id'] .' success');
                } else {
                    // 0 用户未关注了
                    db('user')->update(['id' => $v['id'], 'unsubscribe' => 1]);
                }
            } catch (HttpException $e) {
                $i++;
                Log::notice('easy wechat update fail, openid: ' . $v['openid'] . ', exception msg: ' . $e->getMessage());
            } catch (\Exception $e) {
                $i++;
                Log::notice('update fail, openid: ' . $v['openid'] . ', exception msg: ' . $e->getMessage());
            }
            if ($i > 20) {
                Log::alert('quit $i:' . $i);
                break;
            }
        }
    }
}