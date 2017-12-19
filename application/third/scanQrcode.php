<?php namespace app\third;

use app\model\ShopStation;
use app\model\Station;
use EasyWeChat\Message\News;
use think\Log;

class scanQrcode {

    public static function replyMessage($stationId, $isMaintain = false)
    {
        // 场景ID即站点ID
        // 场景id 1000以内待定, 1001以上绑定站点id
        if (!(env('app.env') == 'development') && $stationId <= 1000) {
            return '场景id未设定';
        }
        // 判断是否存在
        if (!Station::get($stationId)) {
            return '设备未激活';
        }
        // 是否安装维护人员
        if ($isMaintain) {
            $ret = ShopStation::get(['station_id' => $stationId, 'lbsid' => ['gt', 0]]);
            if ($ret) {
                // 已描点
                $newsData = [
                    'title' => '点击本消息进入维护界面',
                    'description' => '站点维护',
                    'image' => 'https://mmbiz.qlogo.cn/mmbiz/hX1d1OhZWxvX8SkHadEtGDx0sghYlRDibU51icujNR0LH5UTJn36oh5iaO7grG6IkPSnJUL0n3xbl8IFoJYAAYD0A/0?wx_fmt=jpeg',
                    'url' => '//' . SERVER_DOMAIN . "/maintain/station/$stationId/manage?_t=" . time(),
                ];
            } else {
                // 未描点
                $newsData = [
                    'title' => '初始化地理位置',
                    'description' => '描述',
                    'image' => 'http://mmsns.qpic.cn/mmsns/hX1d1OhZWxv7pQJbrtosNDCENz4EfaPLW3wVCGbJwhH68sLw8icHXbA/0',
                    'url' => '//' . SERVER_DOMAIN . "/maintain/station/$stationId/init?_t=" . time(),
                ];
            }
            return new News($newsData);
        }
        // 检查设备是否在线, 若断线, 则直接回复用户提示, 若在线则返回借出图文
        if (!swApi::isStationOnline($stationId)) {
            Log::alert('station id: ' . $stationId . ' offline');
            return '非常抱歉，这台大地走红共享伞设备暂时无法连接网络，请稍后再试。或查看附近网点，前往附近的网点进行租借。';
        }
        // 发送图文消息一键借伞
        $newsData = [
            'title' => '请点击“一键借伞”按钮',
            'description' => "",
            'image' => 'https://mmbiz.qpic.cn/mmbiz_png/0shRicALAmH0HjURf2SfyRRZMmAbibnvWV6xLCbrNgWiaEg14x3EA6DdXPic4CB9wFEHJuOSxnvUQ9JOVAXfKAhh4g/0?wx_fmt=png',
            'url' => "//" . SERVER_DOMAIN . "/rent/$stationId?_t=" . time(),
        ];
        return new News($newsData);
    }

}