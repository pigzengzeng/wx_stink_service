<?php

/**
 * bash: php webroot/index.php mysql_to_elastic/company_to_es start
 * bash: php webroot/index.php mysql_to_elastic/company_to_es start_worker
 * 同步清单时，每个宽表可以是一个type，将该异步脚本同时开启35个，通过参数传type，每个宽表单独同步
 * 
 * 如果使用gearman进行并发处理请启动
 * php serviceroot/index.php mysql_to_elastic/testcase start_worker 
 * 可以通过supervisord开启N多个worker
 */
require_once('MysqlElasticSynchronizer.php');
class Company_to_es extends MysqlElasticSynchronizer {
	const SIZE = 2000; 
	const SLEEPTIME = 3;
	const USE_GEARMAN = 1;
	public function __construct(){
		parent::__construct();
		$this->load->config('elastic');
		$es_config = $this->config->item('hosts');
		$this->set_elastic_config($es_config);
		$this->set_mysql_db('default');

		
		
		$this->set_select('pk_company');//由于下面对mysql_read进行了重构，所以这里的设置只是为了用来统计需要处理的记录数,没必要返回过多字段
		$this->set_table('t_company');
		$this->set_utime_field('utime'); //最后更新时间字段，数据库的字段名
		$this->set_primary_field('pk_company'); //主键字段，如果最后更新时间相同，没有主键进行辅助排序，分页会造成数据丢失
		
		$this->set_elastic_index('companies');
		$this->set_elastic_index_type('test');
		$this->set_elastic_id_field('pk_company'); //ES上的字段名,使用schema时一定在下面的schema中的key出现，如果不使用schema，则一定在select中出现
		
		//保存最后更新时间，下次启动会从该时间作增量
		$this->set_last_utime_filepath('/tmp/mysql_to_elastic_'.md5(__Class__));
		
		$this->set_size(self::SIZE);
		$this->set_sleeptime(self::SLEEPTIME);
		$this->set_use_gearman(self::USE_GEARMAN);//用gearman进行并发处理，必须使用start_worker启动工作进程
		
		/**
		 * 自定义结构，结构中的key为es上的字段，value为数据库字段,如果不设置schema，默认使用数据库结构（sql语句的字段部分）
		 */
		$schema = <<<JSON
		{
			"pk_company":"pk_company",
			"company_name":"company_name",
			"fk_group":"fk_group",
			"fk_industry":"fk_industry",
			"class_name":"class_name",
			"big_name":"big_name",
			"middle_name":"middle_name",
			"small_name":"small_name",
			"organization_code":"organization_code",
			"taxpayer_code":"taxpayer_code",
			"commercial_code":"commercial_code",
			"credit_code":"credit_code",
			"state":"state",
			"product_state":"product_state",
			"address":"address",
			"legal_person":"legal_person",
			"contact_person":"contact_person",
			"contact_tel":"contact_tel",
			"email":"email",
			"product_type":"product_type",
			"component_ctype":"component_ctype",
			"ctime":"ctime",
			"utime":"utime",
			"province":"province",
			"city":"city",
			"country":"country",
			"alias":"alias",
			"tags":"tags",
		    "location":{
		        "lat":"geo_lat",
	            "lon":"geo_lon"
	        }
		}
JSON;
		$this->set_schema($schema);
		
	}
	
	/**
	 * 由于点源结构特殊，需要重写获取记录部分,合适的作法是将读数据库的工作放在model中
	 * {@inheritDoc}
	 * @see MysqlElasticSynchronizer::mysql_read()
	 */
	public function mysql_read($from_utime,$to_utime,$limit=0){
		$this->init_db();
		
		$this->db->select('
			t_company.pk_company as pk_company,
			t_company.fk_group as fk_group,
			t_company.fk_industry as fk_industry,
			t_industry.class_name as class_name,
			t_industry.big_name as big_name,
			t_industry.middle_name as middle_name,
			t_industry.small_name as small_name,
			t_company.company_name as company_name,
			t_company.organization_code as organization_code,
			t_company.taxpayer_code as taxpayer_code,
			t_company.commercial_code as commercial_code,
			t_company.credit_code as credit_code,
			t_company.state as state,
			t_company.product_state as product_state,
			t_company.address as address,
			t_company.geo_lon as geo_lon,
			t_company.geo_lat as geo_lat,
			t_company.legal_person as legal_person,
			t_company.contact_person as contact_person,
			t_company.contact_tel as contact_tel,
			t_company.email as email,
			t_company.product_type as product_type,
			t_company.component_ctype as component_ctype,
			t_company.ctime as ctime,
			t_company.utime as utime,
			area1.rd_area_name as province,
			area2.rd_area_name as city,
			area3.rd_area_name as country
		');
		
		$this->db->join('t_industry','on t_company.fk_industry=t_industry.pk_industry','left');
		$this->db->join('t_mapping_company_area as area1','on t_company.pk_company=area1.fk_company and area1.rd_level=1','left');
		$this->db->join('t_mapping_company_area as area2','on t_company.pk_company=area2.fk_company and area2.rd_level=2','left');
		$this->db->join('t_mapping_company_area as area3','on t_company.pk_company=area3.fk_company and area3.rd_level=3','left');
		
		$this->db->where("t_company.utime >",$from_utime);
		if(!empty($to_utime)){
			$this->db->where("t_company.utime <=",$to_utime);
		}
		$this->db->order_by("t_company.pk_company", 'ASC');
		if($result = $this->db->get('t_company',self::SIZE,$limit)){
			//echo $this->db->last_query(),"\n";
			$companies =  $result->result_array();
			$result->free_result();
		}
		
		//重置查寻构造器，查别名
		$this->db->reset_query();
		
		$sql = 'select a.fk_company,a.alias from t_company as c
				join t_company_alias as a on c.pk_company=a.fk_company
				where c.utime > ? '. (empty($to_utime)?'':'and c.utime<= ?') .' and a.state=1
				order by c.pk_company asc';
		$params[] = $from_utime;
		if(!empty($to_utime)) $params[] = $to_utime;
		
		if($result = $this->db->query($sql,$params)){
			$alias_list = $result->result_array();
			foreach ($alias_list as $alias){
				$alias_dict[$alias['fk_company']][] = $alias['alias'];
			}
			$result->free_result();
		}
		
		
		//重置查寻构造器，查标签
		$this->db->reset_query();
		$params = [];
		$sql = 'select t.fk_company,t.tag_name from t_company as c
				join t_mapping_company_tag as t on c.pk_company=t.fk_company
				where c.utime > ? '. (empty($to_utime)?'':'and c.utime<= ?').'
				order by c.pk_company asc';
		$params[] = $from_utime;
		if(!empty($to_utime)) $params[] = $to_utime;
		
		if($result = $this->db->query($sql,$params)){
			$tag_list = $result->result_array();
			foreach ($tag_list as $tag){
				$tag_dict[$tag['fk_company']][] = $tag['tag_name'];
			}
			$result->free_result();
		}
		
		
		foreach ($companies as &$company){
			$company['alias'] = empty($alias_dict[$company['pk_company']])?array():$alias_dict[$company['pk_company']];
			$company['tags'] = empty($tag_dict[$company['pk_company']])?array():$tag_dict[$company['pk_company']];
		}
		
		
		return $companies;
		
		return false;
	}
	public function log($sync_result){
		
		$this->load->helper('json_pretty');
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



