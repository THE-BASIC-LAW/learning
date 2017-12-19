<?php

namespace app\controller\cp;

use think\Db;
use app\common\controller\Base;
use app\controller\Cp;
use app\lib\Auth;
use think\Request;
use think\Loader;

class DataCp extends Cp
{
    /**
     * 显示资源列表
     *
     * @return string
     */
    public function index()
    {
        return 'index';
    }

    public function order_analysis(){
    	$input = input();  
        $shop_station = Loader::model('ShopStation');
        $all_shop_stations = $shop_station::column('title','id');
        $order_analysis = Loader::model('OrderAnalysis');
        if(isset($input['start_time']) || isset($input['end_time'])){
            $shop = Loader::model('Shop');
            $admin = Loader::model('Admin');
            $auth = Loader::model('app\lib\Auth');
            $shop_type = Loader::model('ShopType');
            $admin_role = Loader::model('AdminRole');
            $order_analysis = Loader::model('OrderAnalysis');

            $check_all = $_SESSION['think']['cdo']['check_all_status']?1:0;

            $query_stations = [];
            if (!$auth->globalSearch) {
                $accessCities = $auth->getAccessCities();
                $accessShops = $auth->getAccessShops();
                // $access_shop_stations = $shop_station->searchShopStation(Request::instance()->get(), 0, 0, $accessCities, $accessShops);
                if (!$access_shop_stations['data']) {
                    $this->error('您还未申请商铺，请先申请商铺再查看商户订单数据。',url($input['mod'].'/'.$input['act'].'/'.$input['opt']));
                    exit;
                }  
                $query_stations = array_column($access_shop_stations['data'], 'id');
            }else{
                $query_stations = array_keys($all_shop_stations);
            }

            $seconds_per_day = 86400;
            $end_time = (strtotime($input['end_time']) > strtotime('today') || empty($input['end_time'])) ? strtotime('-1 day') : strtotime($input['end_time']);
            $begin_time = !empty($input['start_time']) ? strtotime($input['start_time']) : $end_time - $seconds_per_day;
            if ($end_time - $begin_time > 31 * 24 * 60 * 60) {
                $this->error('时间跨度最长一个月',url($input['mod'].'/'.$input['act'].'/'.$input['opt']));
                exit;
            }

            $shop_station_id = !empty($input['shop_station_id']) ? intval($input['shop_station_id']) : 0;
            if(!empty($shop_station_id)){
                $query_stations = [$shop_station_id];
            }

            $start = isset($input['page']) ? ($input['page'] - 1) * RECORD_LIMIT_PER_PAGE : 0;

            $orderby = isset($input['orderby']) && !empty($input['orderby'])? $input['orderby'] : BORROW_SUCCESS_ORDER;
            $show_zero = isset($input['show_zero']) ? $input['orderby'] : 0;
            $all_data = [];
            if($start == 0 || isset($input['export'])){
                $all_data = $order_analysis->sum_all_data($query_stations,$begin_time,$end_time);
                
                if(!empty($check_all)){
                    $all_data['usefee_per_order'] = !empty($all_data['borrow_success_order']) ? (round($all_data['total_usefee'] / $all_data['borrow_success_order'], 2)) : 0;
                    $all_data['renting_time'] = !empty($all_data['return_all_order'])?(round($all_data['renting_time']/$all_data['return_all_order'],2)):0;
                    $all_data['usefee_pre_return'] = !empty($all_data['return_all_order'])?(round($all_data['total_usefee']/$all_data['return_all_order'],2)):0;
                    $all_data['return_rate'] = !empty($all_data['borrow_success_order'])?(round($all_data['return_success_order']/$all_data['borrow_success_order'],4) * 100 . '%'):0;
                    // 租金转化率
                    $all_data['charge_order_rate'] = !empty($all_data['return_all_order']) ? (round($all_data['charge_order'] / $all_data['return_all_order'], 4) * 100 . '%') : 0;
                    $all_data['usefee_per_user'] = !empty($all_data['borrow_success_user']) ? (round($all_data['total_usefee'] / $all_data['borrow_success_user'], 2)) : 0;
                    $all_data['borrow_success_order_rate'] = !empty($all_data['borrow_try_order']) ? (round($all_data['borrow_success_order'] / $all_data['borrow_try_order'], 4) * 100 . '%') : 0;
                    $all_data['return_success_order_rate'] = !empty($all_data['return_try_order']) ? (round($all_data['return_success_order'] / $all_data['return_try_order'], 4) * 100 . '%') : 0;
                }
            }

            if(empty($input['export'])){
                switch ($orderby) {
                    case RETURN_SUCCESS_ORDER:
                    case BORROW_SUCCESS_ORDER:
                        $data = $order_analysis->sum_shop_station_data($query_stations,$begin_time,$end_time,$orderby,[$start,RECORD_LIMIT_PER_PAGE]);
                        $page_html = $order_analysis->sum_shop_station_data($query_stations,$begin_time,$end_time,$orderby,[],true)->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>input()]);
                        break;

                    case BORROW_SUCCESS_ORDER_RATE:
                    case RETURN_SUCCESS_ORDER_RATE:
                        if(empty($shop_station_id)){
                            $search = array_merge(['shop_station_id'],$order_analysis->search);
                            $where = array(
                                'shop_station_id' => ['in',$query_stations],
                                'finish_time' => ['between',[$begin_time,$end_time]],
                            );
                            $DB = Db::name('order_analysis')->field($search)->where($where)->group('shop_station_id');
                            
                            $page_html = $DB->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>input()]);
                            $t_data = $DB->select();
                            $temp = array();
                            if($orderby == BORROW_SUCCESS_ORDER_RATE){
                                foreach ($t_data as $key => &$value) {
                                    $temp[$value['shop_station_id']] = !empty($value['borrow_try_order'])?round($value['borrow_success_order']/$value['borrow_try_order'],4):0;
                                }
                            }else{
                                foreach ($t_data as $key => &$value) {
                                    $temp[$value['shop_station_id']] = !empty($value['return_all_order'])?round($value['return_success_order']/$value['return_all_order'],4):0;
                                }
                            }
                            
                            $t_data = $temp;
                            unset($temp);
                            arsort($t_data);
                            $shop_stations = array_keys($t_data);
                            $query_stations = array_slice($shop_stations, $start, RECORD_LIMIT_PER_PAGE);
                        }else{
                            $page_html = $order_analysis->sum_shop_station_data($query_stations,$begin_time,$end_time,'',[],true)->paginate(RECORD_LIMIT_PER_PAGE,false,['query'=>input()]);
                        }

                        $data = $order_analysis->sum_shop_station_data($query_stations,$begin_time,$end_time,true);
                        break;

                    default:
                        # code...
                        break;
                }


                foreach ($data as $key => &$value) {
                    $shop_station_info = $shop_station::get($value['shop_station_id'])->toArray();
                    $value['station_id'] = $shop_station_info['station_id'];
                    $value['station_title'] = $shop_station_info['title'];
                    $value['city'] = substr($shop_station_info['address'], 0, strpos($shop_station_info['address'], '市') + 3);
                    $shop_id = $shop_station_info['shopid'];
                    $shop_type_id = $shop::where('id',$shop_id)->value('type');
                    $rst = $shop_type::where('id',$shop_type_id)->value('type');
                    $value['shop_type'] = $rst ?: '无';
                    $seller_id = $shop_station_info['seller_id'];
                    $value['shop_station_seller_name'] = $admin::where('id',$seller_id)->value('name') ?: '无';
                    $role_id = $admin::where('id',$seller_id)->value('role_id');
                    $value['shop_station_seller_role'] = $admin_role::where('id',$seller_id)->value('role') ?: '无';
                    if($check_all){
                        $value['renting_time'] = !empty($value['return_all_order'])?(round($value['renting_time']/$value['return_all_order'],2)):0;
                        $value['usefee_per_order'] = !empty($value['borrow_success_order']) ? (round($value['total_usefee'] / $value['borrow_success_order'], 2)) : 0;
                        $value['usefee_pre_return'] = !empty($value['return_all_order'])?(round($value['total_usefee']/$value['return_all_order'],2)):0;
                        $value['return_rate'] = !empty($value['borrow_success_order'])?(round($value['return_success_order']/$value['borrow_success_order'],4) * 100 . '%'):0;
                        // 租金转化率
                        $value['charge_order_rate'] = !empty($value['return_all_order']) ? (round($value['charge_order'] / $value['return_all_order'], 4) * 100 . '%') : 0;
                        $value['usefee_per_user'] = !empty($value['borrow_success_user']) ? (round($value['total_usefee'] / $value['borrow_success_user'], 2)) : 0;
                        $value['borrow_success_order_rate'] = !empty($value['borrow_try_order']) ? (round($value['borrow_success_order'] / $value['borrow_try_order'], 4) * 100 . '%') : 0;
                        $value['return_success_order_rate'] = !empty($value['return_try_order']) ? (round($value['return_success_order'] / $value['return_try_order'], 4) * 100 . '%') : 0;
                    }
                }
                $this->assign('data',$data);
                $this->assign('start',$start);
                $this->assign('check_all',$check_all);
                $this->assign('all_data',$all_data);
                $this->assign('page_html',$page_html);
            }else{
                set_time_limit(1800);
                $filename = "order_analysis".date('_YmdHis',time());
                $start_date = isset($input['start_time'])?$input['start_time']:'';
                $end_date = isset($input['end_time'])?$input['end_time']:'';
                $file_header = array(
                        'station_title' => $start_date . '---' . $end_date,
                        'shop_station_id' => '商铺站点id',
                        'station_id' => '站点id',
                        'city' => '城市', 
                        'shop_type' => '业态',
                        'shop_station_seller_role' => '归属',
                        'shop_station_seller_name' => '负责人',
                        'borrow_success_order' => '租借成功订单数',
                        'return_success_order' => '归还成功订单数',
                    );
                if ($check_all){
                    $file_header['borrow_try_user'] = '尝试租借用户数';
                    $file_header['borrow_success_user'] = '借成功用户数';
                    $file_header['return_success_user'] = '还成功用户数';
                    $file_header['total_usefee'] = '盈利总额（元）';
                }
                    $file_header['seller_usefee'] = '盈利总额（扣除电池成本85元）';
                    $file_header['charge_order'] = '盈利订单数';
                if ($check_all){
                    $file_header['usefee_per_user'] = '平均每人收益';
                    $file_header['usefee_per_order'] = '平均每人次收益';
                    $file_header['renting_time'] = '平均租借时间';
                    $file_header['charge_order_rate'] = '租金转化率';
                    $file_header['usefee_pre_return'] = '客单价（元）';
                    $file_header['return_rate'] = '归还率';
                    $file_header['borrow_try_order'] = '总租借订单数（所有状态的订单，包括成功和不成功的）';
                    $file_header['borrow_success_order_rate'] = '租借成功订单比（租借成功订单数/总订单数）';
                    $file_header['return_success_order_rate'] = '归还成功订单比（归还成功订单数/总订单数）';
                }
                $first_line = array(
                        'station_title' => '总计',
                        'shop_station_id' => '总计',
                        'station_id' => '总计',
                        'city' => '全部',
                        'shop_type' => '/',
                        'shop_station_seller_role' => '/',
                        'shop_station_seller_name' => '/',
                        'borrow_success_order' => $all_data['borrow_success_order'],
                        'return_success_order' => $all_data['return_success_order'],
                    );
                if ($check_all){
                    $first_line['borrow_try_user'] = $all_data['borrow_try_user'];
                    $first_line['borrow_success_user'] = $all_data['borrow_success_user'];
                    $first_line['return_success_user'] = $all_data['return_success_user'];
                    $first_line['total_usefee'] = $all_data['total_usefee'];
                }
                    $first_line['seller_usefee'] = $all_data['seller_usefee'];
                    $first_line['charge_order'] = $all_data['charge_order'];
                if ($check_all){
                    $first_line['usefee_per_user'] = $all_data['usefee_per_user'];
                    $first_line['usefee_per_order'] = $all_data['usefee_per_order'];
                    $first_line['renting_time'] = $all_data['renting_time'];
                    $first_line['charge_order_rate'] = $all_data['charge_order_rate'];
                    $first_line['usefee_pre_return'] = $all_data['usefee_pre_return'];
                    $first_line['return_rate'] = $all_data['return_rate'];
                    $first_line['borrow_try_order'] = $all_data['borrow_try_order'];
                    $first_line['borrow_success_order_rate'] = $all_data['borrow_success_order_rate'];
                    $first_line['return_success_order_rate'] = $all_data['return_success_order_rate'];
                }

                createCSVFile($filename,false,[$file_header,$first_line]);
                $data = $order_analysis->sum_shop_station_data($query_stations,$begin_time,$end_time,BORROW_SUCCESS_ORDER);
                foreach ($data as $key => &$value) {
                    $line = array();
                    $shop_station_info = $shop_station::get($value['shop_station_id'])->toArray();
                    $line['station_title'] = $shop_station_info['title'];
                    $line['shop_station_id'] = $value['shop_station_id'];
                    $line['station_id'] = $shop_station_info['station_id'];
                    $line['city'] = substr($shop_station_info['address'], 0, strpos($shop_station_info['address'], '市') + 3);
                    $shop_id = $shop_station_info['shopid'];
                    $shop_type_id = $shop::where('id',$shop_id)->value('type');
                    $rst = $shop_type::where('id',$shop_type_id)->value('type');
                    $line['shop_type'] = $rst ? : '无';
                    $seller_id = $shop_station_info['seller_id'];
                    $role_id = $admin::where('id',$seller_id)->value('role_id');
                    $line['shop_station_seller_role'] = $admin_role::where('id',$seller_id)->value('role') ?: '无';
                    $line['shop_station_seller_name'] = $admin::where('id',$seller_id)->value('name') ?: '无';
                    $line['borrow_success_order'] = $value['borrow_success_order'];
                    $line['return_success_order'] = $value['return_success_order'];
                    if($check_all){
                        $line['borrow_try_user'] = $value['borrow_try_user'];
                        $line['borrow_success_user'] = $value['borrow_success_user'];
                        $line['return_success_user'] = $value['return_success_user'];
                        $line['total_usefee'] = $value['total_usefee'];
                    }
                    $line['seller_usefee'] = $value['seller_usefee'];
                    $line['charge_order'] = $value['charge_order'];
                    if($check_all){
                        $line['usefee_per_user'] = !empty($value['borrow_success_user']) ? (round($value['total_usefee'] / $value['borrow_success_user'], 2)) : 0;
                        $line['usefee_per_order'] = !empty($value['borrow_success_order']) ? (round($value['total_usefee'] / $value['borrow_success_order'], 2)) : 0;
                        $line['renting_time'] = !empty($value['return_all_order'])?(round($value['renting_time']/$value['return_all_order'],2)):0;
                        // 租金转化率
                        $line['charge_order_rate'] = !empty($value['return_all_order']) ? (round($value['charge_order'] / $value['return_all_order'], 4) * 100 . '%') : 0;
                        $line['usefee_pre_return'] = !empty($value['return_all_order'])?(round($value['total_usefee']/$value['return_all_order'],2)):0;
                        $line['return_rate'] = !empty($value['borrow_success_order'])?(round($value['return_success_order']/$value['borrow_success_order'],4) * 100 . '%'):0;
                        $line['borrow_try_order'] = $value['borrow_try_order'];
                        $line['borrow_success_order_rate'] = !empty($value['borrow_try_order']) ? (round($value['borrow_success_order'] / $value['borrow_try_order'], 4) * 100 . '%') : 0;
                        $line['return_success_order_rate'] = !empty($value['return_try_order']) ? (round($value['return_success_order'] / $value['return_try_order'], 4) * 100 . '%') : 0;
                    }
                    createCSVFile($filename,false,[$line]);
                    unset($line);
                }
                createCSVFile($filename,true);
            }
        }
        
        $this->assign([
            'all_shop_stations' => $all_shop_stations,
        ]);
    	return $this->fetch();
    }
}