<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\Log;
use app\third\wxServer;

// 应用公共文件
error_reporting(0);

/**
 * 允许添加与框架无关的函数
 */

// 生成返回信息
function make_error_data($errcode, $errmsg, $ACK = NULL, $id = NULL) {
    if(empty($ACK || $id)) {
        return ['errcode' => $errcode, 'errmsg' => $errmsg];
    } elseif(!$id){
        return ['ERRCODE' => $errcode, 'ERRMSG' => $errmsg, 'ACK' => $ACK];
    } elseif($ACK == 'rent_confirm'){
        return ['ERRCODE' => $errcode, 'ERRMSG' => $errmsg, 'ORDERID' => $id, 'ACK' => $ACK];
    } else{
        return ['ERRCODE' => $errcode, 'ERRMSG' => $errmsg, 'ID' => $id, 'ACK' => $ACK];
    }
}

// 计算租借费用
function calc_fee($order_id, $rent_time, $return_time) {
    Log::notice('orderid: ' . $order_id . ' renttime: ' . $rent_time . ', returntime: ' . $return_time);
    $fee_settings = json_decode(model('Tradeinfo')->where('orderid', $order_id)->value('fee_strategy'), 1);
    Log::notice('fee settings:' . json_encode($fee_settings));
    return calc_fee_with_fee_settings($fee_settings, $rent_time, $return_time);
}

function calc_feeForStation($shop_station_id, $rent_time, $return_time) {
    Log::notice('calc shop station fee sid: ' . $shop_station_id . ' renttime: ' . $rent_time . ', returntime: ' . $return_time);
    $fee_settings = model('ShopStation')->getFeeSettings($shop_station_id);
    Log::notice('fee settings:' . json_encode($fee_settings));
    return calc_fee_with_fee_settings($fee_settings, $rent_time, $return_time);
}

function calc_fee_with_fee_settings($fee_settings, $rent_time, $return_time) {
    $usetime = $return_time - $rent_time;
    if ( !empty($fee_settings['free_time']) && $usetime <= $fee_settings['free_time']*$fee_settings['free_unit'] ) {
        Log::notice('uid return umbrella in free time');
        return 0;
    }

    if ( !empty($fee_settings['max_fee_time']) && !empty($fee_settings['max_fee']) ) {
        return calc_fee_new($fee_settings, $rent_time, $return_time);
    } else {

        $usefee = ceil(($usetime - ($fee_settings['fixed_time']*$fee_settings['fixed_unit'])) / ($fee_settings['fee_unit'] * $fee_settings['fee_time'])) * $fee_settings['fee'];
        $usefee = ($usefee > 0 ? $usefee : 0) + $fee_settings['fixed'];
        if(!empty($fee_settings['max_fee'])){
            $usefee = min($usefee, $fee_settings['max_fee']);
        }
    }

    return $usefee;
}

function calc_fee_new($fee_settings, $rent_time, $return_time)
{
    Log::notice('calculate fee with max_day_fee');

    Log::notice('calculate fee feeSetting: '. json_encode($fee_settings));
    $usetime = $return_time - $rent_time;

    $day = floor($usetime / ( $fee_settings['max_fee_time'] * $fee_settings['max_fee_unit'] ));
    Log::notice('usetime : ' . $usetime . ' day : ' . $day);
    $remain_time = $usetime % ( $fee_settings['max_fee_time'] * $fee_settings['max_fee_unit'] );
    if ($day > 0) {
        $remain_fee = ceil( ($remain_time) / $fee_settings['fee_unit']) * $fee_settings['fee'];
        $remain_fee = $remain_fee > $fee_settings['max_fee'] ? $fee_settings['max_fee'] : $remain_fee;
        $total_fee = $remain_fee + $day * $fee_settings['max_fee'];
    } else {
        $total_fee = ceil(($usetime - ($fee_settings['fixed_time']*$fee_settings['fixed_unit'])) / ($fee_settings['fee_unit'] * $fee_settings['fee_time'])) * $fee_settings['fee'];
        $total_fee = ($total_fee > 0) ? $total_fee : 0;
        $total_fee += $fee_settings['fixed'];
        $total_fee = $total_fee > $fee_settings['max_fee'] ? $fee_settings['max_fee'] : $total_fee;
    }
    $total_fee = ($total_fee < 0) ?  0 : $total_fee;

    return $total_fee;
}

function dheader($string, $replace = true, $http_response_code = 0) {
    $string = str_replace(array("\r", "\n"), array('', ''), $string);
    if(empty($http_response_code) || PHP_VERSION < '4.3' ) {
        @header($string, $replace);
    } else {
        @header($string, $replace, $http_response_code);
    }
    if(preg_match('/^\s*location:/is', $string)) {
        exit();
    }
}

/**
 * csv文件生成函数，支持cli模式
 * @param  string $filename [文件名，需要保证文件名的唯一性]
 * @param  bool   $export   [http请求下，当$export为true时，导出文件]
 * @param  array  $data     [文件内容，二维数组]
 * @param  string $encode   [编码格式，只支持utf-8与gb2312，cli模式下及windowns系统下http请求编码默认为gb2312]
 * @return string           [cli模式下返回文件路径]
 */
function createCSVFile($filename,$export = true,$data = array(),$encode = ''){
    if(!$export){
        if(empty($data)){
            exit("empty data!");
        }
        if(empty($filename)){
            exit("empty filename!");
        }
        if(!empty($encode)){
            if(strtolower($encode) != 'utf-8' && strtolower($encode) != 'gb2312'){
                exit("illegal encode!");
            }
        }
    }
    $request = '';
    $eof = "";
    $file_pre = '';
    if(preg_match('/apache/i', PHP_SAPI) || preg_match('/nginx/i', PHP_SAPI)){
        $request = 'http';
        $OS = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/win/i',$OS)) {
            $eof = "\r\n";
            if(empty($encode)){
                $encode = "gb2312";
            }
        }elseif (preg_match('/mac/i',$OS)) {
            $eof = "\r";
        }else{
            $eof = "\n";
        }
    }else if(PHP_SAPI == 'cli'){
        $request = 'cli';
        if(empty($encode)){
            $encode = 'gb2312';
            $eof = "\r\n";
        }else{
            $eof = "\n";
        }
    }else{
        exit('Access Denied!');
    }
    $filename = DIRECTORY_SEPARATOR.'static'.DIRECTORY_SEPARATOR.'export'.DIRECTORY_SEPARATOR.$filename.".csv";
    if(!empty($data)){
        if(!is_dir(DDZH . '/static/export')){
            mkdir(DDZH . '/static/export');
            chmod(DDZH . '/static/export', 0777);
        }

        $fp = fopen(DDZH . $filename,'a');
        foreach ($data as $key => &$value) {
            $csv = "";
            if(!empty($encode) && $encode != 'utf-8'){
                // $csv = mb_convert_encoding($csv,'gb2312','utf-8');
                $value['station_title'] = str_replace('•', ' ', $value['station_title']);
                foreach ($value as $k => &$v) {
                    $v = iconv('utf-8', $encode.'//IGNORE', $v);
                }
            }

            $value = str_replace([',',"\n","\t","\r","\n\t"], ['，',' ',' ',' ',' '], $value);

            $csv = implode(',', $value);

            fwrite($fp, $csv.$eof);
        }
        fclose($fp);
        if($request == 'http' && $export){
            dheader("location:" . "http://" . $_SERVER['HTTP_HOST'] . $filename);
            exit;
        }else{
            return DDZH.$filename;
        }
    }else if($export && $request == 'http'){
        dheader("location:" . "http://" . $_SERVER['HTTP_HOST'] . $filename);
        exit;
    }
}

// 二维数组排序
function multi_array_sort(array $arrays, $sort_key, $sort_order = SORT_ASC, $sort_type = SORT_NUMERIC) {
    $key_arrays = [];
    foreach ($arrays as $array) {
        if (is_array($array) || is_object($array)) {
            $key_arrays[] = $array[$sort_key];
        } else {
            return false;
        }
    }
    array_multisort($key_arrays, $sort_order, $sort_type, $arrays);
    return $arrays;
}

/**
 * 获取微信端申请维护人员权限的sceneId
 * @return string
 */
function getApplyInstallManSceneId()
{
    return 'apply_install_man';
}

/**
 * 判断是否申请维护人员权限的sceneId
 * @param $scene_id
 * @return bool
 */
function isApplyInstallManSceneId($scene_id)
{
    if ($scene_id == 'apply_install_man') {
        return true;
    }
    return false;
}

// 分页下标
function get_pages($total, $curpage, $nums, $baseurl, $lang = ['prev'=>"上一页",'next' => "下一页"], $showwindow = ''){
    $page['range'] = 2;
    $page['max'] = ceil($total / $nums);

    $pagehtm = '<ol class="paging">';

    $page['start'] = $curpage - $page['range'] > 0 ? $curpage - $page['range'] : 0 ;
    $page['end'] = $page['start']  + $page['range'] * 2 < $page['max'] ? $page['start']  + $page['range'] * 2 : $page['max'] - 1;
    $page['start'] = $page['end'] - $page['range'] * 2 > 0 ? $page['end'] - $page['range'] * 2 : 0;

    if($curpage > 0){
        $url = $baseurl."&page=$curpage";
        $pagehtm.= '<li class="prev"><a href="'.$url.'"'. $showwindow .'><em>&laquo;</em> '.$lang['prev'].'</a></li>';
    }

    if($page['start'] > 1){
        $url = $baseurl."&page=1";
        $pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>1</a></li>';
        $pagehtm.= '<li>...</li>';
    }elseif($page['start'] == 1){
        $url = $baseurl."&page=1";
        $pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>1</a></li>';
    }


    for($i = $page['start']; $i <= $page['end']; $i++){
        $url = $baseurl."&page=".($i + 1);
        if($curpage == $i){
            $pagehtm.= '<li class="current"><a href="'.$url.'"'. $showwindow .'>'.($i + 1).'</a></li>';
        }else{
            $pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>'.($i + 1).'</a></li>';
        }
    }

    if($page['end'] + 2 == $page['max']){
        $url = $baseurl."&page=".$page['max'];
        $pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>'.$page['max'].'</a></li>';
    }elseif($page['end'] + 2 < $page['max']){
        $url = $baseurl."&page=".$page['max'];
        $pagehtm.= '<li>...</li>';
        $pagehtm.= '<li><a href="'.$url.'"'. $showwindow .'>'.$page['max'].'</a></li>';
    }

    if($curpage + 1 < $page['max']){
        $url = $baseurl."&page=".($curpage + 2);
        $pagehtm.= '<li class="next"><a href="'.$url.'"'. $showwindow .'>'.$lang['next'].' <em>&raquo;</em></a></li>';
    }

    $pagehtm.= '</ol>';

    return $pagehtm;
}

/*
  更新LBS数据
  $shop_station_id lbs上绑定的索引key, 对应的商铺站点ID
*/
function update_station_to_lbs($lbsid, $item) {
    //更新LBS云数据
    $item['id'] = $lbsid; //百度LBS默认索引key
    Log::info('lbs item:' . json_encode($item));
    $ret = \app\third\baiduLbs::updatePOI($item);
    if($ret['status'] != 0) {
        Log::error("update station lbs info fail, ret:" . print_r($ret, true));
    }
    return make_error_data($ret['status'], $ret['message']);
}

// 将数组拼接成url参数
function implode_with_key($assoc, $inglue = '=', $outglue = '&') {
    $return = '';

    foreach ($assoc as $tk => $tv) {
        $return .= $outglue . $tk . $inglue . $tv;
    }

    return substr($return, strlen($outglue));
}

// 行列互换函数
function transpose($arr) {
    for($j=0; $j<count($arr[0]); $j++){
        $transposed_arr[$j] = array();   //确定转置后的数组有几行
    }
    for($i=0; $i<count($arr); $i++){
        for($j=0; $j<count($arr[$i]); $j++){
            $transposed_arr[$j][$i] = $arr[$i][$j];   //行列互换
        }
    }
    return $transposed_arr;
}

// 导出数据到excel
function create_excel_column($data, $column_name, $column_title, $all_data = '') {
    $row_data = array_column($data, $column_name);
    array_unshift($row_data, $column_title);
    array_push($row_data, $all_data);
    return $row_data;
}

function export_excel($arrayData, $name = '01simple', $begin_grid = 'A1') {
    $objPHPExcel = new PHPExcel();
    $objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
        ->setLastModifiedBy("Maarten Balliauw")
        ->setTitle("Office 2007 XLSX Test Document")
        ->setSubject("Office 2007 XLSX Test Document")
        ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("Test result file");

    // print_r($sheetarray);
    // exit;
    $objPHPExcel->getActiveSheet()->fromArray($arrayData, null, $begin_grid, true);
    // Rename worksheet
    $objPHPExcel->getActiveSheet()->setTitle('Simple');

    // Set active sheet index to the first sheet, so Excel opens this as the first sheet
    $objPHPExcel->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $name . '.xlsx"');
    header('Cache-Control: max-age=0');
    // If you're serving to IE 9, then the following may be needed
    header('Cache-Control: max-age=1');

    // If you're serving to IE over SSL, then the following may be needed
    header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
    header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
    header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
    header ('Pragma: public'); // HTTP/1.0

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save('php://output');
}

// 生成随机字符串
function random($length, $numeric = 0) {
    PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
    if($numeric) {
        $hash = sprintf('%0'.$length.'d', mt_rand(0, pow(10, $length) - 1));
    } else {
        $hash = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max = strlen($chars) - 1;
        for($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
    }
    return $hash;
}

// 生成微信临时二维码
function get_limit_qrcode_url($scene_id, $limit = 60) {
    $ret = wxServer::instance()->qrcode->temporary($scene_id, $limit);
    if(!$ret['ticket']) {
        Log::error("Fail to get qrcode ticket from weixin, sceneId:" . $scene_id . ', access token:' . print_r($ret, 1) . ', $ret:' . print_r($ret, 1));
        return $ret; // errcode
    }
    return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . $ret['ticket'];
}

function getFeeInfoForZhima($feeSettings, $deposit) {
    $feeSettings = is_array($feeSettings) ? $feeSettings : json_decode($feeSettings, true);
    $ret[0] = $feeSettings['fee'];
    // 芝麻信用只支持单位 HOUR DAY
    if($feeSettings['fee_unit'] == 3600){
        $dayFee = 24 * $feeSettings['fee'];
        if($feeSettings['fee_time'] < 2){
            $ret[0] = $feeSettings['fee'];
            $ret[1] = 'HOUR_YUAN';
        } else {
            $dayFee = 24 / $feeSettings['fee_time'] * $feeSettings['fee'];
            $ret[0] = $dayFee;
            $ret[1] = 'DAY_YUAN';
        }
    } else {
        $dayFee = $feeSettings['fee'];
    }
    $ret[2] = makeFeeStrForZhima($feeSettings);
    $now = time();
    $days = ceil($deposit / $dayFee);
    $expire_time = date('Y-m-d H:i:s', $now + $days * 24 * 3600);
    $ret[3] = $expire_time;
    return $ret;
}


function makeFeeStrForZhima($feeSettings) {
    $feeSettings = is_array($feeSettings) ? $feeSettings : json_decode($feeSettings, true);
    // 只显示固定收费和固定外收费 14字符以内
    $feeUnit = $feeSettings['fee_time'] . timeUnit($feeSettings['fee_unit']);
    $fee = $feeSettings['fee'];
    // 固定收费和固定外收费
    if ( !empty($feeSettings['fixed_time']) && $feeSettings['fixed_time'] != 0 ) {
        if(empty($feeSettings['fixed'])) {
            $fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . '免费';
        } else {
            $fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . $feeSettings['fixed'] . '元';
        }
        return '首' . $fixStr . '，' . $feeUnit . $fee . '元';
        // 只有固定外收费
    } else {
        $fixStr = "{$fee}元/{$feeUnit}";
        return $fixStr;
    }

}



function humanTime($seconds)
{
    $minutes = floor($seconds / 60);
    $seconds = $seconds - $minutes * 60;
    $hours   = floor($minutes / 60);
    $minutes = $minutes - $hours * 60;
    $days    = floor($hours / 24);
    $hours = $hours - $days * 24;

    $ret = '';
    if ($days > 0) {
        $ret .= $days . '天';
    }
    if($hours > 0) {
        $ret .= $hours . '小时';
    }
    if($minutes > 0) {
        $ret .= $minutes . '分';
    }
    if($seconds > 0) {
        $ret .= $seconds . '秒';
    }
    //特殊
    if($ret == '1分') {
        $ret = '60秒';
    }

    if(! $ret)
        return '0秒';
    return $ret;
}

function makeFeeStr($fee_strategy)
{
    $feeSettings = is_array($fee_strategy) ? $fee_strategy : json_decode($fee_strategy, true);
    $feeUnit = $feeSettings['fee_time'] . timeUnit($feeSettings['fee_unit']);
    $fee = $feeSettings['fee'];

    if ( !empty($feeSettings['fixed_time']) && $feeSettings['fixed_time'] != 0 ) {
        if(!empty($fee) && !empty($feeUnit)){
            $fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . '内' . (empty($feeSettings['fixed'])? '免费' : ($feeSettings['fixed'] . '元')) . "，逾期{$fee}元/{$feeUnit}";
        } else {
            $fixStr = $feeSettings['fixed_time'] . timeUnit($feeSettings['fixed_unit']) . '内' . (empty($feeSettings['fixed'])? '免费' : ($feeSettings['fixed'] . '元'));
        }
    } else {
        $fixStr = "{$fee}元/{$feeUnit}";
    }
    $max_fee_time = $feeSettings["max_fee_time"];
    $max_fee_unit = timeUnit($feeSettings["max_fee_unit"]);
    $max_fee      = $feeSettings['max_fee'];

    if ( !empty($max_fee) && $max_fee != 0 ) {
        if (!empty($max_fee_time)) {
            if($max_fee_time == 1){
                $maxStr = "每{$max_fee_unit}最高收费{$max_fee}元";
            } else {
                $maxStr = "每{$max_fee_time}{$max_fee_unit}最高收费{$max_fee}元";
            }
        } else {
            $maxStr = "最高收费{$max_fee}元";
        }
    }

    return $fixStr. $maxStr;
}

function timeUnit($sec)
{
    switch($sec) {
        case 1:
            return '秒';
        case 60:
            return '分钟';
        case 3600:
            return '小时';
        case 86400:
            return '天';
        default:
            return humanTime($sec);
    }
}

function getCitiesByProvince($province, $tree) {
    $tmp = [];
    foreach ($tree as $v) {
        if($v['province'] == $province) {
            foreach($v['city'] as $vv) {
                $tmp[] = $vv['name'];
            }
        }
    }
    return $tmp;
}

function getAreasByCity($province, $city, $tree) {
    $tmp = [];
    foreach ($tree as $v) {
        if($v['province'] == $province) {
            foreach($v['city'] as $vv) {
                if($vv['name'] == $city) {
                    $tmp = $vv['area'];
                }
            }
        }
    }
    return $tmp;
}

function checkProvenceCityAreaLegal($areaNavTree, $province, $city = '', $area = '') {
    foreach ($areaNavTree as $v) {
        // 省份为空
        if (empty($province)) {
            return false;
        }
        // 省
        if (empty($city) && empty($area)) {
            if ($v['province'] == $province) {
                return true;
            }
        }
        // 省市
        if ($city && empty($area)) {
            if ($v['province'] == $province) {
                foreach ($v['city'] as $vv) {
                    if ($vv['name'] == $city) {
                        return true;
                    }
                }
            }
        }
        // 省市区
        if ($city && $area) {
            if ($v['province'] == $province) {
                foreach ($v['city'] as $vv) {
                    if ($vv['name'] == $city) {
                        if (in_array($area, $vv['area'])) {
                            return true;
                        }
                    }
                }
            }
        }
    }
    return false;
}

/**
 * 将后台页面请求的时间范围解析出开始时间和结束时间
 * @param $searchTime string input: "2017-12-22 00:00:00 - 2017-12-21 00:00:00"
 * @return array ['startTime'=> XXXX, 'endTime'=> XXXX]
 */
function getTimeRange($searchTime){
	$rangeTime = explode(" - ",$searchTime);
	return [
		'startTime'	=> $rangeTime[0],
		'endTime'	=> $rangeTime[1],
	];
}


function getPlatform()
{
    if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        return PLATFORM_WX;
    } else if(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
        return PLATFORM_ALIPAY;
    }
    return PLATFORM_NO_SUPPORT;
}
