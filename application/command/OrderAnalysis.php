<?php
/**
 * 定时任务 * 6 * * *  php think orderanalysis
 * 此定时脚本为每天处理前一天的订单数据 具体使用方式如下
 * 处理前一天的数据		: 	php think orderanalysis
 * 处理具体某一天的数据		:	php think orderanalysis 2017-10-15 2017-10-15
 * 处理某段时间内的数据		:	php think orderanalysis 2017-10-15 2017-10-18
 * 处理某一天到昨天的数据	:	php think orderanalysis 2017-10-15
 */
namespace app\command;

use app\model\Tradelog;
use app\model\ShopStation;
use think\Db;
use think\Env;
use think\Log;
use think\Config;
use Exception;
use think\console\Command;
use think\console\input\Argument;
use think\console\Input;
use think\console\Output;

class OrderAnalysis extends Command
{
    protected function configure()
    {
        $this->setName('orderanalysis')->setDescription('Order Analysis pre day');
        $this->addArgument('begin', Argument::OPTIONAL, 'the begin date e.g:2017-10-1');
        $this->addArgument('end', Argument::OPTIONAL, 'the end date e.g:2017-10-1');
    }

    protected function execute(Input $input, Output $output)
    {
    	$begin_date = $input->getArgument('begin');
    	$end_date = $input->getArgument('end');

		$begin_time = !empty($begin_date)?strtotime(date('Y-m-d',strtotime($begin_date))):strtotime(date('Y-m-d', strtotime('-1 day')));
		$end_time = !empty($end_date)?strtotime(date('Y-m-d',strtotime($end_date))) + 86399:strtotime(date('Y-m-d', time())) - 1;
		
		if($end_time - $begin_time < 86399){
			$end_time = $begin_time + 86399;
		}

		if($end_time < $begin_time){
			$output->writeln("err: end_time can not smaller than begin_time");die;
		}

		$span_days = ($end_time + 1 - $begin_time) / 86400;
			
		for($i = 0;$i < $span_days;$i++){
			$begin_time = $begin_time + (86400 * $i);
			$end_time = $begin_time + 86399;

			Log::init([
			    'type'  =>  'File',
			    'path'  =>  APP_PATH.'/../logs/orderAnalysis/',
			    'apart_level'   =>  ['error','sql'],
			]);

			$temp_borrow_name = "temp_borrow";
			$temp_return_name = "temp_return";
			$temp_borrow_success = "temp_borrow_success_table";
			$temp_return_success = "temp_return_success_table";

			$tradelog = new Tradelog;
			$tradelog_name = $tradelog->getTable();

			$borrow_success_status = Tradelog::$borrow_success_status;
			$return_success_status = Tradelog::$return_success_status;

			$order_analysis = Db::name('order_analysis');
			$all_shop_stations = ShopStation::column('id');

			$groupby_borrow_shop_station_id = ' GROUP BY borrow_shop_station_id';
			$orderby_borrow_shop_station_id = ' ORDER BY borrow_shop_station_id';
			$groupby_return_shop_station_id = ' GROUP BY return_shop_station_id';
			$orderby_return_shop_station_id = ' ORDER BY return_shop_station_id';
			$orderby_borrow_success_order 	= ' ORDER BY borrow_success_order DESC';

			Log::info('begin init mysql...');
			//动态连接数据库，避免读写分离的情况下导致无法读取临时表的情况
			$DB = Db::connect([
			    // 数据库类型
			    'type'        		=> 'mysql',
			    // 数据库连接DSN配置
			    'dsn'         		=> '',
			    // 服务器地址
			    'hostname'    		=> Env::get('database.hostname', 'localhost'),
			    // 数据库名
			    'database'    		=> Env::get('database.database', 'ddzh'),
			    // 数据库用户名
			    'username'    		=> Env::get('database.username', 'root'),
			    // 数据库密码
			    'password'    		=> Env::get('database.password', ''),
			    // 数据库连接端口
			    'hostport'    		=> Env::get('database.hostport', '3306'),
			    // 数据库连接参数
			    'params'      		=> [],
			    // 数据库编码默认采用utf8
			    'charset'     		=> 'utf8',
			    // 数据库表前缀
			    'prefix'      		=> Env::get('database.prefix', 'pre_ddzh_'),
			    // 数据库调试模式 默认打开
			    'debug'       		=> true,
			    // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
			    'deploy'          	=> 0,
			    // 数据库读写是否分离 主从式有效
			    'rw_separate'		=> false,
			    // 读写分离后 主服务器数量
			    'master_num'      	=> 1,
			    // 指定从服务器序号
			    'slave_no'        	=> '',
			    // 数据集返回类型
			    'resultset_type'	=> 'array',
			    // 自动写入时间戳字段
			    'auto_timestamp'  	=> false,
			    // 是否严格检查字段是否存在
			    'fields_strict'   	=> true,
			    // 是否需要进行SQL性能分析
			    'sql_explain'     	=> false,
			]);
			Log::info('init mysql success');
			
			try{
				Log::write('order analysis begin..','log');

				$sql = "create temporary table {$temp_borrow_name} ENGINE='MEMORY' SELECT borrow_shop_station_id,status,uid FROM {$tradelog_name} WHERE borrow_shop_station_id in (" . implode(',', $all_shop_stations) . ") and borrow_time > :begin_time and borrow_time <= :end_time ";
				if(empty($DB->execute($sql,['begin_time'=>$begin_time,'end_time'=>$end_time]))){
					throw new Exception("create {$temp_borrow_name} table failed!");
				}

				$sql = "create temporary table {$temp_return_name} ENGINE='MEMORY' SELECT borrow_shop_station_id,return_shop_station_id,status,uid,usefee,(return_time - borrow_time) as renting_time FROM {$tradelog_name}  WHERE borrow_shop_station_id in (" . implode(',', $all_shop_stations) . ") and return_time > :begin_time and return_time <= :end_time ";
				if(empty($DB->execute($sql,['begin_time'=>$begin_time,'end_time'=>$end_time]))){
					throw new Exception("create {$temp_return_name} table failed!");
				}

				$sql = "create temporary table {$temp_borrow_success} ENGINE='MEMORY' SELECT borrow_shop_station_id, count(*) as borrow_success_order FROM {$temp_borrow_name} where status in (" . implode(',', $borrow_success_status) . ") group by borrow_shop_station_id";
				if(empty($DB->execute($sql))){
					throw new Exception("create {$temp_borrow_success} table failed!");
				}

				$sql = "create temporary table {$temp_return_success} ENGINE='MEMORY' SELECT return_shop_station_id, count(*) as return_success_order FROM {$temp_return_name} where status in (" . implode(',', $return_success_status) . ") group by return_shop_station_id";
				print_r($sql);
				if(empty($DB->execute($sql))){
					throw new Exception("create {$temp_return_success} table failed!");
				}

				$borrow_success_order = $DB->query("SELECT * FROM {$temp_borrow_success} " . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id);
			    $borrow_success_order = array_column($borrow_success_order, 'borrow_success_order', 'borrow_shop_station_id');
			    $sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_borrow_success}";
			    $DB->execute($sql);
			    
			    $return_success_order = $DB->query("SELECT * FROM {$temp_return_success} " . $groupby_return_shop_station_id . $orderby_return_shop_station_id);
			    $return_success_order = array_column($return_success_order, 'return_success_order', 'return_shop_station_id');

			    $sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_return_success}";
			    $DB->execute($sql);

			    $borrow_try_user = array();
			    $t_borrow_try_user = $DB->query("SELECT count(*) as borrow_try_user,borrow_shop_station_id FROM {$temp_borrow_name} WHERE status > 0 GROUP BY uid, borrow_shop_station_id");
			    foreach ($t_borrow_try_user as $key => &$value) {
			    	if(!isset($borrow_try_user[$value['borrow_shop_station_id']])){
			    		$borrow_try_user[$value['borrow_shop_station_id']] = 1;
			    	}else{
			    		$borrow_try_user[$value['borrow_shop_station_id']] += 1;
			    	}  
			    }
			    unset($t_borrow_try_user);
			    
			    $borrow_success_user = array();
			    $t_borrow_success_user = $DB->query("SELECT count(*) as borrow_success_user,borrow_shop_station_id FROM {$temp_borrow_name} WHERE status in (" . implode(',', $borrow_success_status) . ") GROUP BY uid, borrow_shop_station_id");
			    foreach ($t_borrow_success_user as $key => &$value) {
			    	if(!isset($borrow_success_user[$value['borrow_shop_station_id']])){
			    		$borrow_success_user[$value['borrow_shop_station_id']] = 1;
			    	}else{
			        	$borrow_success_user[$value['borrow_shop_station_id']] += 1;
			    	}
			    }
			    unset($t_borrow_success_user);

			    $borrow_try_order = array();
			    $t_borrow_try_order = $DB->query("SELECT count(*) as borrow_try_order,borrow_shop_station_id FROM {$temp_borrow_name} GROUP BY borrow_shop_station_id");
			    foreach ($t_borrow_try_order as $key => &$value) {
			        if (empty($borrow_try_order[$value['borrow_shop_station_id']])) {
			            $borrow_try_order[$value['borrow_shop_station_id']] = $value['borrow_try_order'];
			        } else {
			            $borrow_try_order[$value['borrow_shop_station_id']] += $value['borrow_try_order'];
			        }
			    }
			    unset($t_borrow_try_order);

			    $sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_borrow_name}";
			    $DB->execute($sql);

			    $total_usefee = $DB->query("SELECT borrow_shop_station_id,IFNULL(SUM(usefee),0) as total_usefee FROM {$temp_return_name} WHERE status > 0 " . $groupby_borrow_shop_station_id);
			    $temp = array();
			    foreach ($total_usefee as $key => &$value) {
			        if (empty($temp[$value['borrow_shop_station_id']])) {
			            $temp[$value['borrow_shop_station_id']] = $value['total_usefee'];
			        } else {
			            $temp[$value['borrow_shop_station_id']] += $value['total_usefee'];
			        }
			    }
			    $total_usefee = $temp;
			    unset($temp);

			    $timeout_order = $DB->query("SELECT borrow_shop_station_id,count(*) as timeout_order FROM {$temp_return_name} WHERE status = :status " . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id,['status'=>ORDER_STATUS_TIMEOUT_NOT_RETURN]);
			    $temp = array();
			    foreach ($timeout_order as $key => &$value) {
			        if (empty($temp[$value['borrow_shop_station_id']])) {
			            $temp[$value['borrow_shop_station_id']] = $value['timeout_order'];
			        } else {
			            $temp[$value['borrow_shop_station_id']] += $value['timeout_order'];
			        }
			    }
			    $timeout_order = $temp;
			    unset($temp);

			    $charge_order = $DB->query("SELECT borrow_shop_station_id,count(*) as charge_order FROM {$temp_return_name} WHERE status > 0 and usefee > 0 " . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id);
			    $temp = array();
			    foreach ($charge_order as $key => &$value) {
			        if (empty($temp[$value['borrow_shop_station_id']])) {
			            $temp[$value['borrow_shop_station_id']] = $value['charge_order'];
			        } else {
			            $temp[$value['borrow_shop_station_id']] += $value['charge_order'];
			        }
			    }
			    $charge_order = $temp;
			    unset($temp);

			    $renting_time = array();
			    $t_renting_time = $DB->query("SELECT SUM(renting_time) as total_renting_time,borrow_shop_station_id FROM {$temp_return_name} GROUP BY borrow_shop_station_id");
			    foreach ($t_renting_time as $key => &$value) {
			    	if(!isset($renting_time[$value['borrow_shop_station_id']])){
			    		$renting_time[$value['borrow_shop_station_id']] = $value['total_renting_time'];
			    	}else{
			        	$renting_time[$value['borrow_shop_station_id']] += $value['total_renting_time'];
			    	}
			    }
			    unset($t_renting_time);

			    $return_success_user = array();
			    $t_return_success_user = $DB->query("SELECT count(*) as return_success_user,return_shop_station_id FROM {$temp_return_name} WHERE status in (" . implode(',', $return_success_status) . ') GROUP BY uid, return_shop_station_id');
			    foreach ($t_return_success_user as $key => &$value) {
			    	if(!isset($return_success_user[$value['return_shop_station_id']])){
			    		$return_success_user[$value['return_shop_station_id']] = 1;
			    	}else{
			    		$return_success_user[$value['return_shop_station_id']] += 1;
			    	}
			    }
			    unset($t_return_success_user);

			    $return_try_order = array();
			    $t_return_try_order = $DB->query("SELECT return_shop_station_id, count(*) as return_try_order FROM {$temp_return_name} " . $groupby_return_shop_station_id);
			    foreach ($t_return_try_order as $key => &$value) {
			        if (empty($return_try_order[$value['return_shop_station_id']])) {
			            $return_try_order[$value['return_shop_station_id']] = $value['return_try_order'];
			        } else {
			            $return_try_order[$value['return_shop_station_id']] += $value['return_try_order'];
			        }
			    }
			    unset($t_return_try_order);

			    $return_all_order = $DB->query("SELECT borrow_shop_station_id,count(*) as return_all_order FROM {$temp_return_name} WHERE  usefee > 0 OR ( status in (" . implode(',', $return_success_status) . ') and usefee = 0)' . $groupby_borrow_shop_station_id . $orderby_borrow_shop_station_id);
			    $temp = array();
			    foreach ($return_all_order as $key => &$value) {
			        if (empty($temp[$value['borrow_shop_station_id']])) {
			            $temp[$value['borrow_shop_station_id']] = $value['return_all_order'];
			        } else {
			            $temp[$value['borrow_shop_station_id']] += $value['return_all_order'];
			        }
			    }
			    $return_all_order = $temp;
			    unset($temp);

			    $sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_return_name}";
			    $DB->execute($sql);

			    foreach ($all_shop_stations as $key => &$value) {
			    	$data = array();
			    	$data['shop_station_id'] = $value;
			    	$data['borrow_success_order'] = isset($borrow_success_order[$value]) ? $borrow_success_order[$value] : 0;
			        $data['return_success_order'] = isset($return_success_order[$value]) ? $return_success_order[$value] : 0;
			        $data['borrow_try_user'] = isset($borrow_try_user[$value]) ? $borrow_try_user[$value] : 0;
			        $data['borrow_success_user'] = isset($borrow_success_user[$value]) ? $borrow_success_user[$value] : 0;
			        $data['return_success_user'] = isset($return_success_user[$value]) ? $return_success_user[$value] : 0;
			        $data['return_all_order'] = isset($return_all_order[$value]) ? $return_all_order[$value] : 0;
			        $data['borrow_try_order'] = isset($borrow_try_order[$value]) ? $borrow_try_order[$value] : 0;
			        $t_return_try_order = isset($return_try_order[$value]) ? $return_try_order[$value] : 0;
			        $data['return_try_order'] = $t_return_try_order;
			        $data['renting_time'] = isset($renting_time[$value])?$renting_time[$value]:0;
			        $data['charge_order'] = isset($charge_order[$value]) ? $charge_order[$value] : 0;
			        $t_total_usefee = isset($total_usefee[$value]) ? $total_usefee[$value] : 0;
			        $data['total_usefee'] = $t_total_usefee;
			        $timeout_value = isset($timeout_order[$value]) ? $timeout_order[$value] : 0;
			        $data['seller_usefee'] = sprintf("%.2f", $t_total_usefee - $timeout_value * 20);
			        $data['finish_time'] = $begin_time;
			        $data['create_time'] = time();
			        $data['update_time'] = time();

			        //这里使用replace方式进行插入，以便多次跑脚本时，可以对数据进行更新
			        $res = $order_analysis->insert($data,true);
			        if(!$res){
			        	Log::write('insert data err:'.print_r($data, 1),'error');
			        };
			    }
			}catch(Exception $e){
				print_r($e->getMessage());
			    Log::write(print_r($e->getMessage(), 1),'error');
			}finally{
				$sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_return_success}";
			    $DB->execute($sql);
			    $sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_borrow_success}";
			    $DB->execute($sql);
			    $sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_borrow_name}";
			    $DB->execute($sql);
			    $sql = "DROP TEMPORARY TABLE  IF EXISTS {$temp_return_name}";
			    $DB->execute($sql);
			    Log::write("order analysis end..",'log');
			}
		}
    }
}