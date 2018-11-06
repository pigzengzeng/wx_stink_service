<?php

class Marker_model extends CI_model
{
    private $db_main  = null;
    private $db_query = null;
	const STATE_DELETED = 1;
    public function __construct() {
        parent::__construct();

        $this->db_main = $this->load->database('db_main',TRUE, true);
        $this->db_query = $this->load->database('db_query', TRUE, true);
        
        $this->load->config('elastic');
        
    }
	/**
	 * 通过markerid，获取一个marker点
	 * @param unknown $markerid
	 */
    public function get_marker_by_id($markerid){
    	if (!$query = $this->db_query->get_where('t_marker', ['pk_marker'=>$markerid])) {
    		$e = $this->db_query->error();
    		throw new Exception($e['message'], $e['code']);
    	}
    	return $query->row_array();
    }
    
    
    
	/**
	 * 获取多个marker点
	 */
    
    public function get_markers($x1,$y1,$x2,$y2,$time_from,$time_to,$userid){
    	/*数据库取数据
    	$size =100 ;
    	$this->db_query->select('*');
    	$this->db_query->from('t_marker');
    	$this->db_query->where('state',0);
    	$this->db_query->limit($size);
    	
    	if(!$query = $this->db_query->get()){
    		$e = $this->db_query->error();
    		throw new Exception($e['message'], $e['code']);
    	}
    	return $query->result_array();
    	*/
    	/*ES取数据
    	 * 
    	 */
    	//初始化ES_Client
    	$client = Elasticsearch\ClientBuilder::create()->setHosts($this->config->item('hosts'))->setRetries(2)->build();

    	
    	$r=array();
    	$params = [
			'index'=>'markers',
    		'type' => 'test',
    		'body' => [
    			'query'=>[
					'bool'=>[
						'filter' =>[
							['term'=>['state'=>0]],
							['geo_bounding_box' =>[
									'location' =>[
											'top_right'=>[
													'lat'=> doubleval($y1),
													'lon'=> doubleval($x1)
											],
											'bottom_left'=>[
													'lat'=> doubleval($y2),
													'lon'=> doubleval($x2)
											]
									]
							]]
						]
					]
    			],
                'size'=>200
    		]
		];
        //$params['body']=array();
        if(!empty($time_from)){
            $params['body']['query']['bool']['must']['range']['lastupdate']['gte'] = $time_from;
            //$params['body']['query']['range']['lastupdate']['gte'] = $time_from;
        }
        
        if(!empty($time_to)){
            $params['body']['query']['bool']['must']['range']['lastupdate']['lte'] = $time_to;
        }
        
        if(!empty($userid)){
            $params['body']['query']['bool']['filter'][]['term']['fk_user'] = $userid;
            
        }
    //print_r($params);
    	$markers = $client->search($params);
        
    	if(empty($markers['hits']['hits'])){
            
    		return $r; 
    	}

    	foreach ($markers['hits']['hits'] as $marker) {
            $item=array();
            $item['pk_marker'] = $marker['_source']['pk_marker'];
            $item['longitude'] = $marker['_source']['longitude'];
            $item['latitude'] = $marker['_source']['latitude'];
            $item['odour'] = $marker['_source']['odour'];
            $item['intensity'] = $marker['_source']['intensity'];
            $item['fk_user'] = $marker['_source']['fk_user'];
            $item['state'] = $marker['_source']['state'];
            $item['createtime'] = $marker['_source']['createtime'];
            $item['lastupdate'] = $marker['_source']['lastupdate'];
            $r[] = $item;
        }
        return $r;
    }
    
    /**
     * 新增点
     *
     **/
    public function add_marker($longitude,$latitude,$odour,$intensity,$userid) {
        $fields = array();
        $longitude = empty((double)$longitude)?0:(double)$longitude;
        $latitude = empty((double)$latitude)?0:(double)$latitude;
        $odour = empty($odour)?99:(int)$odour;
        $intensity = empty($intensity)?1:(int)$intensity;
        
        if(!$this->db_main
        		->set('longitude', $longitude)
        		->set('latitude', $latitude)
        		->set('odour',$odour)
                ->set('intensity',$intensity)
        		->set('fk_user',$userid)
                ->set('createtime',date('Y-m-d H:i:s'))
            ->insert('t_marker')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        $lastid = $this->db_main->insert_id();

        if($lastid) {
	        return $lastid;
        }
        return false;
    }
    /**
     * 删除marker，不真删除，使state=1
     *
     **/
    public function delete_marker($markerid) {
    	
    	$fields = array();
    	$fields['state'] = self::STATE_DELETED;
    	if(!$this->db_main->set($fields)->where('pk_marker', $markerid)->update('t_marker')) {
    		$error = $this->db_main->error();
    		throw new Exception($error['message'], $error['code']);
    	}
    	$affected_rows = $this->db_main->affected_rows();
    	return $affected_rows;
    }
    /**
     * 更新marker，目前只能更新odour
     *
     **/
    public function update_marker($markerid,$odour,$intensity) {
    	
    	$odour = empty($odour)?99:(int)$odour;
        $intensity = empty($intensity)?1:(int)$intensity;
    	$fields = array();
    	
    	$fields['odour'] = $odour;
        $fields['intensity'] = $intensity;
        
    	if(!$this->db_main->set($fields)->where('pk_marker', $markerid)->update('t_marker')) {
    		$error = $this->db_main->error();
    		throw new Exception($error['message'], $error['code']);
    	}
    	$affected_rows = $this->db_main->affected_rows();
    	return $affected_rows;
    }
    
   


}
