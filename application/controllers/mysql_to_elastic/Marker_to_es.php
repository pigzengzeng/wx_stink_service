<?php

/**
 * bash: php index.php mysql_to_elastic/marker_to_es start
 * bash: php index.php mysql_to_elastic/marker_to_es start_worker
 * 同步清单时，每个宽表可以是一个type，将该异步脚本同时开启35个，通过参数传type，每个宽表单独同步
 * 
 * 如果使用gearman进行并发处理请启动
 * php serviceroot/index.php mysql_to_elastic/testcase start_worker 
 * 可以通过supervisord开启N多个worker
 */
require_once('MysqlElasticSynchronizer.php');
class Marker_to_es extends MysqlElasticSynchronizer {
	const SIZE = 2000; 
	const SLEEPTIME = 1;
	const USE_GEARMAN = 0;
	public function __construct(){
		parent::__construct();
		$this->load->config('elastic');
		$es_config = $this->config->item('hosts');
		$this->set_elastic_config($es_config);
		$this->set_mysql_db('default');

		
		
		$this->set_select('pk_marker,longitude,latitude,odour,intensity,fk_user,state,level,createtime,lastupdate');//由于下面对mysql_read进行了重构，所以这里的设置只是为了用来统计需要处理的记录数,没必要返回过多字段
		$this->set_table('t_marker');
		$this->set_utime_field('lastupdate'); //最后更新时间字段，数据库的字段名
		$this->set_primary_field('pk_marker'); //主键字段，如果最后更新时间相同，没有主键进行辅助排序，分页会造成数据丢失
		
		$this->set_elastic_index('markers_v2');
		$this->set_elastic_index_type('test');
		$this->set_elastic_id_field('pk_marker'); //ES上的字段名,使用schema时一定在下面的schema中的key出现，如果不使用schema，则一定在select中出现
		
		//保存最后更新时间，下次启动会从该时间作增量
		$this->set_last_utime_filepath('/tmp/mysql_to_elastic_'.md5(__Class__));
		
		$this->set_size(self::SIZE);
		$this->set_sleeptime(self::SLEEPTIME);
		$this->set_use_gearman(self::USE_GEARMAN);//用gearman进行并发处理，必须使用start_worker启动工作进程
		
		/**
		 * 自定义结构，结构中的key为es上的字段，value为数据库字段,如果不设置schema，默认使用数据库结构（sql语句的字段部分）
		 */
		$schema_extra = <<<JSON
		{
		    "location":{
		        "lat":"latitude",
	            "lon":"longitude"
	        }
		}
JSON;
		$this->set_schema_extra($schema_extra);
		
	}
	
	
	public function log($sync_result){
		
		$this->load->helper('json_pretty');
		echo json_pretty($sync_result);
	}
	

}



