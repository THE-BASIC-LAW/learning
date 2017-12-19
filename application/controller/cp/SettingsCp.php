<?php
/**
 * Created by PhpStorm.
 * User: dlq
 * Date: 17-12-5
 * Time: 下午9:11
 */

namespace app\controller\cp;

use app\lib\Api;
use app\controller\Cp;
use think\Request;

class SettingsCp extends Cp
{
    // 关联的Settings模型
    protected $station_settings;

    protected $common_setting;

    protected $fee_strategy;

    /**
     * 构造函数
     * @access public
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->fee_strategy     = model('FeeStrategy');
        $this->common_setting   = model('CommonSetting');
        $this->station_settings = model('StationSettings');
    }

    public function feeSettings(){
        extract(input());
        if($GLOBALS['do'] == 'strategy') {
            $settings = array();
            $settings['fee']            = $fee;
            $settings['fixed']          = $fixed;
            $settings['max_fee']        = $max_fee;
            $settings['fee_time']       = $fee_time;
            $settings['fee_unit']       = $fee_unit;
            $settings['free_time']      = $free_time;
            $settings['free_unit']      = $free_unit;
            $settings['fixed_time']     = $fixed_time;
            $settings['fixed_unit']     = $fixed_unit;
            $settings['max_fee_time']   = $max_fee_time;
            $settings['max_fee_unit']   = $max_fee_unit;
            $res = $this->admin->feeSettings($settings);
            if ($res) {
                Api::output([], 0, '全局收费策略更新成功');
            } else {
                Api::fail(1, '全局收费策略更新失败');
            }
        }
        $fee_settings = json_decode($this->common_setting->get('fee_settings')['svalue'], 1);

        $this->assign('fee_settings', $fee_settings);
        return $this->fetch();
    }

    public function localFeeSettings(){
        extract(input());
        if ($GLOBALS['do']) {
            switch ($GLOBALS['do']) {
                case 'add':
                case 'edit':
                    if ($_POST) {
                        $settings = array();
                        $settings['fee']            = $fee;
                        $settings['fixed']          = $fixed;
                        $settings['max_fee']        = $max_fee;
                        $settings['fee_unit']       = $fee_unit;
                        $settings['fee_time']       = $fee_time;
                        $settings['free_time']      = $free_time;
                        $settings['free_unit']      = $free_unit;
                        $settings['fixed_time']     = $fixed_time;
                        $settings['fixed_unit']     = $fixed_unit;
                        $settings['max_fee_time']   = $max_fee_time;
                        $settings['max_fee_unit']   = $max_fee_unit;
                        if ($fee_strategy_id) {
                            $res = $this->admin->localFeeSettingsEdit($settings, $fee_strategy_id, $name);
                            if ($res) {
                                Api::output([], 0, '更新成功');
                            } else {
                                Api::fail(1, '更新失败');
                            }
                        } else {
                            $res = $this->admin->localFeeSettingsAdd($settings, $name);
                            if ($res) {
                                Api::output([], 0, '添加成功');
                            } else {
                                Api::fail(1, '添加失败');
                            }
                        }
                    }
                    $fee = $this->fee_strategy->get($fee_strategy_id);
                    $fee['fee'] = json_decode($fee['fee'], 1);
                    $this->assign('fee', $fee);
                    return $this->fetch('localFeeSettingsEdit');
                case 'delete':
                    // 如果没有设备使用此配置　就直接删除配置　
                    // 如果有　则提醒用户哪些站点使用了此配置　必须先更改配置　才能删除
                    if ($res = model('ShopStation')->where('fee_settings', $fee_strategy_id)->limit(3)->select()) {
                        $name = array_map(function($a){
                            return $a['title'];
                        }, $res);
                        Api::fail(1, '删除失败:'.implode(',', $name).'等正在使用这个策略');
                    } else {
                        $res = $this->admin->localFeeSettingsDelete($fee_strategy_id);
                        if ($res) {
                            Api::output([], 0, '删除成功');
                        } else {
                            Api::fail(1, '删除失败');
                        }
                    }
            }
        }

        $res  = $this->fee_strategy->order('id desc')->select();
        $fees = array_map(function($a){
            $fee_strategy_id = $a['id'];
            $tmp = model('ShopStation')->where('fee_settings', $fee_strategy_id)->select();
            $a['shops'] = $tmp;
            return $a;
        }, $res);

        $this->assign('fees', $fees);
        return $this->fetch();
    }

    public function systemSettings(){
        extract(input());
        if($GLOBALS['do'] == 'set') {
            $settings = array();
            $settings['ip']               = $ip;
            $settings['port']             = $port;
            $settings['domain']           = $domain;
            $settings['soft_ver']         = $soft_ver;
            $settings['file_name']        = $file_name;
            $settings['heartbeat']        = $heartbeat;
            $settings['checkupdatedelay'] = $checkupdatedelay;
            $res = $this->admin->systemSettings($settings);
            if($res){
                Api::output([], 0, '全局同步策略更新成功');
            }else{
                Api::fail(1, '全局同步策略更新失败');
            }
        }
        $system_settings = json_decode($this->common_setting->get('system_settings')['svalue'], 1);

        $this->assign('system_settings', $system_settings);
        return $this->fetch();
    }

    public function stationSettingsStrategy(){
        extract(input());
        // 删除同步配置策略操作
        if($GLOBALS['do']){
            switch ($GLOBALS['do']) {
                case 'add':
                case 'edit':
                    if ($_POST) {
                        $settings = array();
                        $settings['ip']               =  $ip;
                        $settings['port']             =  $port;
                        $settings['domain']           =  $domain;
                        $settings['soft_ver']         =  $soft_ver;
                        $settings['file_name']        =  $file_name;
                        $settings['heartbeat']        =  $heartbeat;
                        $settings['checkupdatedelay'] =  $checkupdatedelay;
                        if($station_strategy_id){
                            $res = $this->admin->stationSettingsEdit($station_strategy_id, $settings, $name);
                            if($res){
                                Api::output([], 0, '更新成功');
                            }else{
                                Api::fail(1, '更新失败');
                            }
                        }else{
                            $res = $this->admin->stationSettingsAdd($settings, $name);
                            if($res){
                                Api::output([], 0, '添加成功');
                            }else{
                                Api::fail(1, '添加失败');
                            }
                        }
                        $res = $this->admin->stationSettingsEdit($station_strategy_id, $settings, $name);
                        if($res){
                            Api::output([], 0, '更新成功');
                        }else{
                            Api::fail(1, '更新失败');
                        }
                    }
                    // show setting detail

                    $res = $this->station_settings->get($station_strategy_id);
                    if($res['settings']){
                        $system_settings         = json_decode($res['settings'],true);
                        $system_settings['name'] = $res['name'];
                    }
                    $this->assign('system_settings', $system_settings);
                    return $this->fetch('strategyEdit');

                case 'delete':
                    // 如果没有设备使用此配置　就直接删除配置　
                    // 如果有　则提醒用户哪些站点使用了此配置　必须先更改配置　才能删除
                    if ($res = model('Station')->where('station_setting_id', $station_strategy_id)->limit(10)->select()) {
                        $name = array_map(function($a){
                            return $a['title'];
                        }, $res);
                        Api::fail(1, '删除失败:'.implode(',', $name).'等正在使用这个策略');
                    } else {
                        $res = $this->admin->stationSettingsDelete($station_strategy_id);
                        if ($res) {
                            Api::output([], 0, '删除成功');
                        } else {
                            Api::fail(1, '删除失败');
                        }
                    }
            }
        }
        // show station setting list
        $where = ['status' => 0];
        $settings = $this->station_settings
            ->where($where)
            ->paginate(RECORD_LIMIT_PER_PAGE, false, ['query'=>$where]);
        $pagehtm = $settings->render();
        $settings = $settings->toArray()['data'];
        $settings = array_map(function($a){
            $station_strategy_id = $a['id'];
            $tmp = model('Station')->where('station_setting_id', $station_strategy_id)->select();
            $a['stations'] = $tmp;
            return $a;
        }, $settings);

        $this->assign([
            'pagehtm'  => $pagehtm,
            'settings' => $settings,
        ]);
        return $this->fetch();
    }

    public function wechatSettings(){
        extract(input());
        if (isset($wechat)) {
            $res = $this->get('jjsan_wechat_replyMsg')->save(['svalue' => json_encode($replymsg)]) &&
                $this->common_setting->get('jjsan_wechat_defaultMsg')-save(['svalue' => json_encode($defaultMsg)]) &&
                $this->common_setting->get('jjsan_wechat_defaultMsg')-save(['svalue' => json_encode($subscribeMsg)]);
            if (!$res) {
                Log::error('update wechat replymsg fail');
            }
        } elseif (isset($func)) {
            $keywords = json_decode( $this->common_setting->get('jjsan_wechat_keywords')['svalue'], true );
            if ($func == 'add') {
                $keywords[$row][] = '';
            } elseif ($func == 'delete') {
                unset($keywords[$row][$num]);
            } elseif ($func == 'edit') {
                $keywords[$row][$num] = $keyword;
            } elseif ($func == 'add_new_rule') {
                $keywords[] = array();
            } elseif ($func == 'delete_rule') {
                unset($keywords[$row]);
            }
            if (! $this->common_setting->get('wechat_keywords')-save(['svalue' => json_encode($keywords)])) {
                Log::error('update wechat keyword fail');
            } else {
                echo "success";
            }
            exit;
        }

        $this->assign([
            'keywords' => json_decode( $this->common_setting->get('wechat_keywords')['svalue'], 1),
            'replyMsg' => json_decode( $this->common_setting->get('wechat_replyMsg')['svalue'], 1),
            'defaultMsg' => json_decode( $this->common_setting->get('wechat_defaultMsg')['svalue'], 1),
            'subscribeMsg' => json_decode( $this->common_setting->get('wechat_subscribeMsg')['svalue'], 1),
        ]);
        return $this->fetch();
    }

    public function wechatPictext(){
        extract(input());
        if (isset($GLOBALS['do'])) {
            switch ($GLOBALS['do']) {
                case 'add':
                case 'edit':
                    if ($_POST) {
                        $settings = array();
                        $settings['etime']                    = strtotime($etime);
                        $settings['stime']                    = strtotime($stime);
                        $settings['pictext']['url']           = $url;
                        $settings['pictext']['title']         = $title;
                        $settings['pictext']['wechat_picurl'] = $wechat_picurl;
                        $settings['pictext']['alipay_picurl'] = $alipay_picurl;
                        if($pictext_id){
                            $res = $this->admin->pictextSettingsEdit($settings, $pictext_id, $name);
                        } else {
                            $res = $this->admin->pictextSettingsAdd($settings, $name);
                        }
                        if ($res) {
                            Api::output([], 0, '微信图文消息设置成功');
                        } else {
                            Api::fail(1, '微信图文消息设置失败');
                        }
                        exit;
                    }
                    $pictext = model('PictextSettings')->get($pictext_id);
                    if($pictext){
                        $pictext['etime']   = date('Y-m-d H:i:s', $pictext['etime']);
                        $pictext['stime']   = date('Y-m-d H:i:s', $pictext['stime']);
                        $pictext['pictext'] = json_decode($pictext['pictext'], 1);
                    }
                    $this->assign('pictext', $pictext);
                    return $this->fetch('wechatPictextEdit');
                case 'delete':
                    // 如果没有设备使用此配置　就直接删除配置　
                    // 如果有　则提醒用户哪些站点使用了此配置　必须先更改配置　才能删除
                    if ($res = model('ShopStation')->where('pictext_settings', $pictext_id)->limit(3)->get()) {
                        $name = array_map(function ($a) {
                            return $a['title'];
                        }, $res);
                        Api::fail(1, '删除失败:' . implode(',', $name) . '等正在使用该图文配置');
                    } else {
                        $res = $this->admin->pictextSettingsDelete($pictext_id);
                        if ($res) {
                            Api::output([], 0, '删除成功');
                        } else {
                            Api::fail(1, '删除失败');
                        }
                    }
            }
        }
        $res = model('PictextSettings')->order('id desc')->select();
        $pictexts = array_map(function($a){
            $tmp = model('ShopStation')->where('pictext_settings', $a['id'])->select();
            $a['shops'] = $tmp;
            return $a;
        }, $res);
        $this->assign('pictexts', $pictexts);
        return $this->fetch();
    }

    public function globalSettings(){
        if($_GET['submit']){
            $settings['service_phone'] = $_POST['service_phone'];
            $res = $this->admin->globalSettings($settings);
            if ($res) {
                Api::output([], 0, '客服电话更新成功');
            } else {
                Api::fail(1, '客服电话更新失败');
            }
        }
        return $this->fetch();
    }

}
