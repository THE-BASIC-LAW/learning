<?php

namespace app\model;

use think\Db;
use think\Model;

class OrderAnalysis extends Model
{

    public $search = array(
                'IFNULL(SUM(borrow_success_order),0)'  => 'borrow_success_order',
                'IFNULL(SUM(return_success_order),0)'  => 'return_success_order',
                'IFNULL(SUM(borrow_try_user),0)'       => 'borrow_try_user',
                'IFNULL(SUM(borrow_success_user),0)'   => 'borrow_success_user',
                'IFNULL(SUM(return_success_user),0)'   => 'return_success_user',
                'IFNULL(SUM(borrow_try_order),0)'      => 'borrow_try_order',
                'IFNULL(SUM(return_try_order),0)'      => 'return_try_order',
                'IFNULL(SUM(renting_time),0)'          => 'renting_time',
                'IFNULL(SUM(charge_order),0)'          => 'charge_order',
                'IFNULL(SUM(total_usefee),0)'          => 'total_usefee',
                'IFNULL(SUM(seller_usefee),0)'         => 'seller_usefee',
                'IFNULL(SUM(return_all_order),0)'      => 'return_all_order',
            );

    public function sum_all_data($query_stations,$begin_time,$end_time){
    	$where = array(
    		'shop_station_id' => ['in',$query_stations],
    		'finish_time' => ['between',[$begin_time,$end_time]]
    	);
    	
    	return $this->field($this->search)->where($where)->select()[0]->toArray();
    }

    public function sum_shop_station_data($query_stations,$begin_time,$end_time,$order_by='',$limit=[],$get_page=false){
		$search = array_merge(['shop_station_id'],$this->search);
    	$where = array(
    		'shop_station_id' => count($query_stations) == 1?$query_stations[0]:['in',$query_stations],
    		'finish_time' => ['between',[$begin_time,$end_time]],
    	);
    	$DB = Db::name('order_analysis')->field($search)->where($where)->group('shop_station_id');

    	if($order_by === RETURN_SUCCESS_ORDER){
    		$DB = $DB->order('return_success_order desc');
    	}else if($order_by === BORROW_SUCCESS_ORDER){
    		$DB = $DB->order('borrow_success_order desc');
    	}else if($order_by === true){
    		$DB = $DB->order('field(shop_station_id,'.implode(',', $query_stations).') ');
    	}

    	if(!empty($limit) && is_array($limit) && count($limit) && !$get_page){
    		$DB = $DB->limit($limit[0],$limit[1]);
    	}

    	if($get_page){
    		return $DB;
    	}
    	return $DB->select();
    }

    public function test($func){
    	Db::name('order_analysis')->where('shop_station_id','<','200')->chunk(100,$func);
    }
}    