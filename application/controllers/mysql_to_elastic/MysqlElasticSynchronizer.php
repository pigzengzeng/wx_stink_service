<?php
class MysqlElasticSynchronizer extends CI_Controller {
    private $signo = -1; //信号编号
    
    private $mysql_db;
    private $elastic_config;
    private $elastic_index;
    private $elastic_index_type;
    private $elastic_id_field;
    
    private $select = '*';
    private $table = '';
    private $jointables = [];
    private $wheres = [];
    private $utime_field = '' ;
    private $primary_field = '';
    private $schema;
    private $schema_extra;
    
    
    
    private $last_utime = 0;
    
    private $size = 1000;
    
    private $sleep_time = 3;
    
    
    private $last_utime_filepath = '/tmp/';
    
    
    
    private $gm_client;
    private $gm_worker;
    private $gm_host;
    private $gm_port;
    private $use_gearman = 0;
	public function __construct(){
	    parent::__construct();
	    if (!is_cli()){
	        log_message('error','This script is only run in cli mode.');
	        exit();
	    }
// 	    if (!function_exists('pcntl_signal')){
// 	        log_message('info','pcntl_signal is required, IgnoreSignal not work!');
// 	        exit();
// 	    }
// 	    $sig_handler = function($signo){
// 	        $this->signo = $signo;
// 	    };
// 	    //注册信号
// 	    pcntl_signal(SIGTERM, $sig_handler);
// 	    pcntl_signal(SIGINT, $sig_handler);//Ctrl+C
// 	    pcntl_signal(SIGUSR1, $sig_handler);
// 	    pcntl_signal(SIGHUP, $sig_handler);
	    
		$this->load->library('curl');
		$this->load->helper('file');
		
		
		if(class_exists('GearmanClient')){
			$this->load->config('gearman');
			$host = $this->config->item('host');
			$port = $this->config->item('port');
			$timeout = $this->config->item('timeout');
			
			if(empty($host)) $host = '127.0.0.1';
			if(empty($port)) $port = 4730;
			if(empty($timeout)) $timeout = -1;//毫秒级
			
			
			$this->gm_worker = new GearmanWorker();
			$this->gm_worker->addServer($host, $port);
			$this->gm_worker->setTimeout ( $timeout );
			
			$this->gm_client = new GearmanClient();
			$this->gm_client->addServer($host, $port);
			$this->gm_client->setTimeout ( $timeout );
		}	
		
		
	}
	
	protected function set_use_gearman($flag=0){
		$this->use_gearman = $flag;
	}
	protected function set_usegearman($flag){
		$this->use_gearman = $flag;
	}
	protected function set_mysql_db($db){
		$this->mysql_db = $db;
	}
	protected function set_elastic_config($config){
		$this->elastic_config = $config;
	}
	
	protected function set_select($select='*'){
		$this->select = $select;
	}
	protected function set_table($table){
		$this->table = $table;
	}
	protected function set_where($key,$val){
		$this->wheres[] = ['key'=>$key,'val'=>$val];
	}
	protected function set_jointable($table,$cond,$ctype = 'inner'){
		$this->jointables[] = ['table'=>$table,'cond'=>$cond,'ctype'=>$ctype];
	}
	
	protected function set_utime_field($field){
		$this->utime_field = $field;
	}
	protected function set_primary_field($field){
		$this->primary_field = $field;
	}
	protected function set_schema($schema){
		$this->schema = $schema;
	}
	protected function set_schema_extra($schema_extra){
		$this->schema_extra = $schema_extra;
	}
	protected function set_size($size){
		$this->size = $size;
	}
	protected function set_sleeptime($seconds){
	    $this->sleep_time = $seconds;
	}
	
	protected function set_elastic_id_field($field){
		$this->elastic_id_field = $field;
	}
	protected function get_last_utime(){
		return $this->last_utime;
	}
	protected function set_last_utime($utime){
		$this->last_utime = $utime;
	}
	protected function set_elastic_index($index){
		$this->elastic_index = $index;
	}
	protected function set_elastic_index_type($type){
		$this->elastic_index_type = $type;
	}
	protected function set_last_utime_filepath($filepath){
		$this->last_utime_filepath = $filepath;
	}
	
	private function schema_patch($schema){
		if(empty($this->schema_extra)){
			return $schema + $this->schema_extra;
		}else{
			return $schema;
		}
	}
	
	protected function is_stop(){
	    if (is_cli() && $this->signo > -1){
	        return true;
	    }else{
	        return false;
	    }
	    
	}
	protected function get_signo(){
	    return $this->signo;
	}
	protected function stop(){
	    exit() ;
	}
	private function save_last_utime(){
		$utime = $this->last_utime;
		if(!write_file($this->last_utime_filepath, $utime)){
			log_message('error', 'Unable to write the '.$this->last_utime_filepath);
		}
		return true;
	}
	private function get_last_utime_from_file(){
		if( $utime = @file_get_contents($this->last_utime_filepath) ){
			return $utime;
		}
		log_message('error', 'Not found file '.$this->last_utime_filepath);
		return false;
	}
	
	public function start(){
 	    
		if(!$this->verification()){
			$this->stop();
		}
		
		
		$last_utime=$this->get_last_utime_from_file() ;
		if(empty($last_utime)){
			$from_utime = 0;
		}else{
			$from_utime = $last_utime;
		}

	    while(true) {
	        
	        $to_utime = $this->mysql_max_utime($from_utime);
	        $rs_count = $this->mysql_count($from_utime,$to_utime);
	        
	        
	        if(empty($rs_count) || empty($to_utime) ){
	        	echo "the recordset is empty,wait...\n";
	        	sleep($this->sleep_time);
	        	continue;
	        }
	        echo "do recordset count:$rs_count \n";
	        
	        $max_page = ceil($rs_count/$this->size);
	        
	        for($i=1;$i<=$max_page;$i++){
	        	if($this->use_gearman){ //使用gearman并发
		        	$params = [
		        			'page'=>$i,
		        			'from_utime'=>$from_utime,
		        			'to_utime'=>$to_utime
		        	];
		        	$ret[$i] = $this->gm_client->doBackground('push_elastic',json_encode($params) );
	        	}else{ //使用程序串行执行
	        		$limit = ($i-1) * $this->size;
	        		$rs = $this->mysql_read($from_utime, $to_utime, $limit);
	        		echo "do ".count($rs),"\n";
	        		if(!empty($rs)){
	        			$this->put_elastic($rs);
	        		}
	        	}
	        }
	        
	        $from_utime = $to_utime;
	        $this->set_last_utime($to_utime);
	        $this->save_last_utime();
	    }
	}
	
	public function start_worker(){
		if(!$this->verification()){
			$this->stop();
		}
		
		$this->gm_worker->addFunction('push_elastic', array($this, 'push_elastic') );
		
		//declare(ticks = 1);
		//死循环
		while(true) {
			//if ($this->is_stop()) $this->stop();
			//等待job提交的任务
			$this->gm_worker->work();
			
			if ($this->gm_worker->returnCode() != GEARMAN_SUCCESS) {
				$message = 'gearman worker error:'.$this->gm_worker->error();
				log_message('warning', $message);
				break;
			}
			
		}
		
		
	}
	
	public function push_elastic($job){
		$params = json_decode($job->workload(),true);
		$page = $params['page'];
		$from_utime = $params['from_utime'];
		$to_utime = $params['to_utime'];
		
		$limit = ($page-1) * $this->size;
		$rs = $this->mysql_read($from_utime, $to_utime, $limit);
		echo "do ".count($rs),"\n";
		if(!empty($rs)){
			$this->put_elastic($rs);
		}
		return ;
		
	}
	
	private function put_elastic($rs){
		$adapt = function (&$adapt,&$receiver,$key,$value){
			if(is_object($receiver) || is_array($receiver)){
				foreach ($receiver as &$item){
					$adapt($adapt,$item,$key,$value);
				}
			}else{
				if($receiver==$key){
					$receiver = $value;
				}
			}
		};
		$es_recordset = array();
		foreach($rs as $item){
			if(!empty($this->schema)){
				$receiver = json_decode($this->schema);
			}else{
				$receiver = new stdClass();
				foreach ($item as $key=>$val){
					$receiver->{$key} = $key;
				}
			}
			if(!empty($this->schema_extra)){
				$receiver = (object)((array)$receiver + (array)json_decode($this->schema_extra));
			}
			
			foreach ($item as $key=>$val){
				$adapt($adapt,$receiver,$key,$val);
			}
			$es_recordset[] = $receiver;
		}
// 		foreach ($es_recordset as $data){
// 			$params = [
// 					'index' => $this->elastic_index,
// 					'type' => $this->elastic_index_type,
// 					'body' => $data
// 			];
// 			if(!empty($this->elastic_id_field) ){
// 				if(!empty($data->{$this->elastic_id_field}))
// 					$params['id'] = $data->{$this->elastic_id_field};
// 			}
// 			$response = $this->client->index($params);
// 			$this->log($response);
// 		}
		//批量操作
		$bulk['refresh'] = true;
		foreach ($es_recordset as $data){
			$index = array();
			$index['_index'] =  $this->elastic_index;
			$index['_type'] =  $this->elastic_index_type;
			if(!empty($this->elastic_id_field))
				if(!empty($data->{$this->elastic_id_field}))
					$index['_id'] = $data->{$this->elastic_id_field};
				
			$bulk['body'][]['index'] = $index;
			$bulk['body'][] = $data;
			//log_message('info',$data->{$this->elastic_id_field});
		}
		
		//初始化ES_Client
		$client = Elasticsearch\ClientBuilder::create()->setHosts($this->elastic_config)->setRetries(2)->build();
 		$response = $client->bulk($bulk);
 		
 		$this->log($response);
	
	

	}
	private function mysql_count($from_utime,$to_utime){
		$table = $this->table;
		$this->init_db_condition($from_utime,$to_utime);
		$rs_count = $this->db->count_all_results($table);
		return $rs_count;
	}
	private function mysql_max_utime($from_utime){
		$table = $this->table;
		$utime_field = $this->utime_field;
		
		$this->init_db_condition($from_utime);
		$this->db->select_max("$table.$utime_field");
		$query = $this->db->get($table);
		
		$max_utime = $query->row()->{$utime_field};
		return $max_utime;
		
	}
	
	/**
	 * 如果需要自己写逻辑拼装结构，可在继承类中重新实现该方法
	 * @param unknown $from_utime
	 * @param unknown $to_utime
	 * @param number $limit
	 * @return unknown|boolean
	 */
	public function mysql_read($from_utime,$to_utime,$limit=0){
		
		$this->init_db_condition($from_utime,$to_utime);
		
		$select = $this->select;
		$utime_field = $this->utime_field;
		$primary_field = $this->primary_field;
		$table = $this->table;
		
		
		$this->db->select($select);
		
		$this->db->order_by("$table.$utime_field", 'ASC');
		if(!empty($primary_field)){
			$this->db->order_by("$table.$primary_field", 'ASC');
		}
		
		$size = $this->size;
		
		if($result = $this->db->get($table,$size,$limit)){
			//echo $this->db->last_query(),"\n";
			return $result->result_array();
		}
		
		return false;
	}
	protected function init_db(){
		$L =& load_class('Loader', 'core');
		$L->database($this->mysql_db);
	}
	private function init_db_condition($from_utime,$to_utime=null){
		
		$this->init_db();
		
		$table = $this->table;
		$utime_field = $this->utime_field;
		
		$this->db->where("$table.$utime_field >",$from_utime);
		if(!empty($to_utime)){
			$this->db->where("$table.$utime_field <=",$to_utime);
		}
		
		if(!empty($this->jointables)){
			foreach ($this->jointables as $t){
				$this->db->join($t['table'],$t['cond'],$t['ctype']);
			}
		}
		if(!empty($this->wheres)){
			foreach ($this->wheres as $w){
				$this->db->where($w['key'],$w['val']);
			}
		}
		return $this->db;
	}
	private function verification(){
		if(empty($this->mysql_db) ){
			echo 'the mysql db is not set.\n';
			return false;
		}
		if(empty($this->table) ){
			echo 'the table is not set.\n';
			return false;
		}
		
		if(empty($this->utime_field) ){
			echo 'the utime_field is not set.\n';
			return false;
		}
		
		if(empty($this->elastic_config) ){
			echo 'the elastic_config is not set.\n';
			return false;
		}
		if(empty($this->elastic_index) ){
			echo 'the elastic index is not set.\n';
			return false;
		}
		if(empty($this->elastic_index_type) ){
			echo 'the elastic index type is not set.\n';
			return false;
		}
		if(empty($this->elastic_id_field) ){
			echo 'the elastic index id field is not set.\n';
			return false;
		}
		
		
		
		if(empty($this->last_utime_filepath) ){
			echo 'the last_utime_filepath is not set.\n';
			return false;
		}
		return true;
	}
	

	/**
	 * 接口函数，可在子类中实现，将结果进行保存
	 * @param string $message
	 */
	protected function log($sync_result){
	    print_r($sync_result);
	}
}
