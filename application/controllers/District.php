<?php
class District extends BaseApiController {
	public function __construct(){
		parent::__construct();
		$this->load->config('district');
	}
	public function get_local_city(){
		$lat = $this->input->get('lat');
		$lon = $this->input->get('lon');
		$districts = $this->config->item("district");
		$in_idx = array();
		for($i=0;$i<count($districts);$i++) {
			if( $lat<=$districts[$i]['north_east']['lat'] && 
				$lat>=$districts[$i]['south_west']['lat'] &&
				$lon<=$districts[$i]['north_east']['lon'] &&
				$lon>=$districts[$i]['south_west']['lon']){
				$in_idx[]=$i;
			}
		}

		if(count($in_idx)==0){
			$this->fail(-1,'该区域没有权限');
		}

		$select_district = [
			"distance" => 180,
			"idx" => 0
		];

		if(count($in_idx)>1){//在多个domain中，找距离中心位置近的一个作为唯一输出
			foreach ($in_idx as $idx) {
				$delta_lon = abs($districts[$idx]['center']['lon']-$lon);
				$delta_lat = abs($districts[$idx]['center']['lat']-$lat);
				$distance = sqrt( pow($delta_lat,2)+pow($delta_lon,2) );
				if($distance<$select_district['distance']){
					$select_district['idx'] = $idx;
					$select_district['distance'] = $distance;
				}				
			}
			$this->success($districts[ $select_district['idx'] ] );

		}

		$this->success($districts[$in_idx[0]]);

	}
}