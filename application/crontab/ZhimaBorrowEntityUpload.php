<?php namespace app\crontab;

use app\third\alipay\AlipayAPI;
use app\third\baiduLbs;
use think\Log;


/**
 * 芝麻实体数据上传(只传有商铺的网点)
 *
 * Class ZhimaBorrowEntityUpload
 *
 * @package app\crontab
 */
class ZhimaBorrowEntityUpload implements CrontabInterface
{
    public function exec()
    {
        $shops = db('shop')->select();

        foreach ($shops as $v) {
            $shopStation = db('shop_station')
                ->where('shopid', $v['id'])
                ->find();
            if (empty($shopStation) || empty($shopStation['station_id'])) {
                continue;
            }
            $coordinates = baiduLbs::aMapCoordinateConvert($shopStation['longitude'] . ',' . $shopStation['latitude']);
            if (empty($coordinates)) {
                Log::alert("amap coordinate convert fail");
                continue;
            }
            $station = db('station')->find($shopStation['station_id']);
            $feeStr = makeFeeStrForZhima(model('FeeStrategy')->getStrategySettings($shopStation['fee_settings']));
            if ($v['province'] == $v['city']) {
                $v['province'] = '';
            }
            if ($shopStation['status'] == 0) {
                $isCanBorrow = 'N';
            } else {
                if ($station['usable']) {
                    $isCanBorrow = 'Y';
                } else {
                    $isCanBorrow = 'N';
                }
            }
            $biz = [
                'product_code' => 'w1010100000000002858',
                'category_code' => 'umbrella',
                'entity_code' => $shopStation['id'], //站点/商铺
                'entity_name' => '['.DEFAULT_TITLE.']'.$v['name'],
                'address_desc' => $v['province'].$v['city'].$v['area'].$v['locate'],
                'longitude' => $coordinates['lng'],
                'latitude' => $coordinates['lat'],
                'rent_desc' => $feeStr, // 租金描述
                'collect_rent' => 'Y',
                'can_borrow' => $isCanBorrow, // 可借判断 可接数量大于0
                'can_borrow_cnt' => $station['usable'], // 可借数量
                'total_borrow_cnt' => $station['total'] > $station['usable'] ? $station['total'] : $station['usable'], // 借用总数
                'upload_time' => date('Y-m-d H:i:s')
            ];
            $resp = AlipayAPI::zhimaBorrowEntityUpload($biz);
            if ($resp->code == '10000') {
                Log::info("upload success , shop id: {$shopStation['id']} , usable: {$station['usable']} , total: {$station['total']}");
            } else {
                Log::notice("shop id upload fail" . print_r($resp, 1));
            }
        }
    }
}