<?php
class Res_model extends CI_model
{
	private $db_main  = null;
	private $db_query = null;
	
	public function __construct() {
		parent::__construct();
		
		$this->db_main = $this->load->database('db_main',TRUE, true);
		$this->db_query = $this->load->database('db_query', TRUE, true);
	}
	
	public function insert_res(){
		if(!$this->db_main
				->set('state', 0)
				->set('createtime',date('Y-m-d H:i:s'))
				->insert('t_res')) {
			$error = $this->db_main->error();
			throw new Exception($error['message'], $error['code']);
		}
		$lastid = $this->db_main->insert_id();
		if($lastid) {
			return $lastid;
		}
		return 0;
	}
	public function update_res($resid,$file){
		if(empty($resid)) return 0;
		$fields = $file;
		$fields['createtime'] = date('Y-m-d H:i:s');
		$fields['state'] = 1;
		
		if(!$this->db_main->set($fields)->
				where('pk_res', $resid)->
				update('t_res')) {
			$error = $this->db_main->error();
			throw new Exception($error['message'], $error['code']);
		}
		$affected_rows = (int)$this->db_main->affected_rows();
		return $affected_rows;
	}

	public function get_files_by_key($keys=array()){
		if(empty($keys))return false;
		if(!$query = $this->db_query
			->select('*')
			->where_in('file_name_key',$keys)
			->order_by('pk_res','ASC')
			->get('t_res')){

			$e = $this->db_query->error();
    		throw new Exception($e['message'], $e['code']);
    	}
    	//echo $this->db_query->last_query();
    	return $query->result_array();
	}
}