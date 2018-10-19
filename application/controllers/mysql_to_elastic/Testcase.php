<?php
use Elasticsearch\Endpoints\Indices\Flush;

/**
 * bash: php serviceroot/index.php mysql_to_elastic/testcase start 
 * 同步清单时，每个宽表可以是一个type，将该异步脚本同时开启35个，通过参数传type，每个宽表单独同步
 * 
 * 如果使用gearman进行并发处理请启动
 * php serviceroot/index.php mysql_to_elastic/testcase start_worker 
 * 可以通过supervisord开启N多个worker
 */
require_once('MysqlElasticSynchronizer.php');
class Testcase extends MysqlElasticSynchronizer {
	public function __construct(){
		parent::__construct();
		$this->load->config('elastic');
		$es_config = $this->config->item('hosts');
		$this->set_elastic_config($es_config);
		$this->set_mysql_db('db_user');
		

		$this->set_select('*');
		$this->set_table('t_user');
		//$this->set_where('sys_admin',1);
		$this->set_jointable('t_group','on t_user.fk_group=t_group.pk_group');
		
		$this->set_utime_field('lastupdate'); //最后更新时间字段，数据库的字段名
		$this->set_primary_field('pk_user'); //主键字段，如果最后更新时间相同，没有主键进行辅助排序，分页会造成数据丢失
		
		$this->set_elastic_index('users');
		$this->set_elastic_index_type('test');
		//$this->set_elastic_id_field('pk_user'); //ES上的字段名
		$this->set_elastic_id_field('userid'); //ES上的字段名,使用schema时一定在下面的schema中的key出现，如果不使用schema，则一定在select中出现
		
		//保存最后更新时间，下次启动会从该时间作增量
		$this->set_last_utime_filepath('/tmp/mysql_to_elastic_'.md5(__Class__));
		
		$this->set_size(2000);
		$this->set_sleeptime(3);
		$this->set_use_gearman(1);//用gearman进行并发处理
		/**
		 * 自定义结构，结构中的key为es上的字段，value为数据库字段,如果不设置schema，默认使用数据库结构（sql语句的字段部分）
		 */
		$schema = <<<JSON
		{
		    "userid":"pk_user",
			"login_username":"login_username",
		    "username":"username",
		    "group":{
		        "groupid":"fk_group",
		        "groupname":"a"
		    }
		}
JSON;
		$this->set_schema($schema);
		
		/**
		 * schema扩展，一般设置该项时不设置schema，对数据库结构进行扩展。
		 * 比如数据库有name lon lat 三个字段，而es上的结构需要location字段，
		 * 可通过schema_extra配置geo_point类型:
		 * {
		 *     "location":{
		 *         "lat":"lat",
		 *         "lon":"lon"
		 *     }
		 * }
		 */
		$schema_extra = <<<JSON
		{
		    "ctime":"createtime"
		}
JSON;
		$this->set_schema_extra($schema_extra);
		
	}
	
	public function log($sync_result){
		
		//$this->load->helper('json_pretty');
		//echo json_pretty($sync_result);
	}
	/**
	 * 如果需要自己写逻辑拼装，需重写mysql_read方法，将结构以记录集形式返回
	 * {@inheritDoc}
	 * @see MysqlElasticSynchronizer::mysql_read()
	 */
// 	public function mysql_read($from_utime,$to_utime,$limit=0){
// 		$rs=array();
// 		return $rs;
// 	}

}



