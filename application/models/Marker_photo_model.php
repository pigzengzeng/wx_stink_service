<?php
class Marker_photo_model extends CI_model
{
    private $db_main  = null;
    private $db_query = null;
	
    public function __construct() {
        parent::__construct();

        $this->db_main = $this->load->database('db_main',TRUE, true);
        $this->db_query = $this->load->database('db_query', TRUE, true);
        
    }
    public function add_marker_photo($markerid,$photos=array()){
    	if(empty((int)$markerid))return false;
    	if(empty($photos)||!is_array($photos))return false;

		foreach ($photos as $file_name_key) {
			$data[] = array(
				'fk_marker' => $markerid,
				'file_name_key' => $file_name_key
			);
		}
	        
        if(!$this->db_main->insert_batch('t_marker_photo', $data)) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
            return false;
        }
        return true;
    }

    public function del_marker_photo_by_markerid($markerid){
    	if(empty((int)$markerid))return false;
    	return $this->db_main->delete('t_marker_photo', array('fk_marker' => $markerid));


    }

    public function get_photos_by_markerid($markerid){
    	
    	
                

    	//$query = $this->db_query->get_where('t_marker_photo', ['fk_marker'=>$markerid])


    	if (!$query = $this->db_query->select('*')
    			->where('fk_marker',$markerid)
    			->order_by('pk_marker_photo','ASC')
    			->get('t_marker_photo')) 
    	{
    		$e = $this->db_query->error();
    		throw new Exception($e['message'], $e['code']);
    	}
    	return $query->result_array();
    }
}