<?php


class Marker extends BaseApiController {
	const ICON_PATH = '../../images/';
	//key用于icon对应的文件名
	private $odours = [
			'1'=>'农药化工味',
			'2'=>'臭鸡蛋味',
			'3'=>'臭鱼烂肉味',
			'4'=>'烂白菜味',
			'5'=>'油脂薰蒸味',
			'99'=>'难以辨别'
	];
	private $odours_sign = [ //简写，用于marker图标
			'1'=>'农化',
			'2'=>'臭蛋',
			'3'=>'鱼腐',
			'4'=>'烂菜',
			'5'=>'油脂',
			'99'=>'难辨'
	];
	

	const APPID = 'wxa6e3fde558ae29e2';
	const APP_SECRET = '5b622697af174a0bb384433366801d49';
	
	
	public function __construct(){
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');
		$this->load->model('marker_model');
		$this->load->model('marker_photo_model');
		$this->load->model('res_model');
	}
	
	public function get_odours(){//老版,已废弃
		$r = new stdClass();
		$r->odours = $this->odours;
		$this->success($r);
		
	}
	public function get_odours_v2(){
		$r = new stdClass();
		foreach ($this->odours as $key => $value) {
			$odour = array();
			$odour['name'] = $value;
			$odour['value'] = $key;
			if($key==1)$odour['checked'] = true;
			$r->odours[] = $odour;
		}
		$this->success($r);
	}
	public function get_markers(){
		$this->check_login();

		$x1 = $this->input->get('x1');
		$y1 = $this->input->get('y1');
		$x2 = $this->input->get('x2');
		$y2 = $this->input->get('y2');
		$time_flag =  $this->input->get('time_flag');
		

		$domain_pos = array();
		$domain_pos['x1'] = $this->input->get('domain_x1');
		$domain_pos['y1'] = $this->input->get('domain_y1');
		$domain_pos['x2'] = $this->input->get('domain_x2');
		$domain_pos['y2'] = $this->input->get('domain_y2');

		

		$time_flag = empty($time_flag)?0:$time_flag;
		$userid = $this->session->userid;
		$userid = empty($userid)?0:$userid;

		$r = new stdClass();
		$r->markers = array();

		$df = 'Y-m-d H:i:s';
		switch ($time_flag) {
			case 1:
				$time_from = date($df,time()-86400*7);
				$time_to = date($df,time());
				$userid = 0;
				break;
			case 2:
				$time_from = date($df,time()-86400*30);
				$time_to = date($df,time());
				$userid = 0;
				break;
			case 3:
				$time_from = 0;
				$time_to = 0;
				break;
			default:
				$time_from = date($df,time()-86400);
				$time_to = date($df,time());
				$userid = 0;
				break;
		}
		try{
			$markers = $this->marker_model->get_markers($x1,$y1,$x2,$y2,$time_from,$time_to,$userid,$domain_pos);
		}catch (Exception $e){
			$this->success($r);
		}
		
		
 		
		foreach ($markers as $item){
			$marker = [];
			$marker['id'] = (int)$item['pk_marker'];
			//$marker['zIndex'] = 0;
		
			$marker['latitude'] = (double)$item['latitude'];
			$marker['longitude'] = (double)$item['longitude'];
			$marker['createtime'] = $item['createtime'];
			$marker['lastupdate'] = $item['lastupdate'];
			$marker['userid'] = $item['fk_user'];
			//$marker['title'] = $item['odour'];
			//$marker['iconPath'] = self::ICON_PATH."stink_{$item['odour']}.png";
			//$marker['iconPath'] = self::ICON_PATH."marker_{$item['odour']}.png";
			//$marker['iconPathSelected'] = self::ICON_PATH."marker_{$item['odour']}_checked.png";
			$marker['iconPathChecked'] = self::ICON_PATH."marker_{$item['odour']}_{$item['intensity']}_s.png";
			$marker['iconPathUnchecked'] = self::ICON_PATH."marker_{$item['odour']}_{$item['intensity']}.png";
			$marker['iconPath'] = $marker['iconPathUnchecked'];
			$marker['width'] = 50;
			$marker['height']= 44;
			$marker['anchor']['x']= 0.3;
			$marker['anchor']['y']= 1;

			//$marker['label']['content'] = $this->odours_sign[$item['odour']];
			// $marker['label']['color'] = "#FFFFFF";
			// $marker['label']['padding'] = 0;
			//$marker['label']['anchorX'] = -6;
			//$marker['label']['anchorY'] = -13;
			// $marker['label']['x'] = -8;
			// $marker['label']['y'] = -20;
			//$marker['label']['textAlign'] = 'center';


// 			$marker['callout']['content'] = $this->odours[$item['odour']];
// 			$marker['callout']['display'] = "ALWAYS";  //BYCLICK
// 			$marker['callout']['textAlign'] = "center";
			
			
			$r->markers[] = (object)$marker;
		}
		$this->success($r);

	}
	public function save_marker(){
		$this->check_login();

		if( $this->user['state'] ==1 ){//屏蔽状态
			$this->fail(ErrorCode::$PermissionDenied);
		}


		$json_data = $this->input->raw_input_stream;

		$data = json_decode($json_data);
		$r = new stdClass();

		if(empty($data)){
			$this->fail(ErrorCode::$ParamError);
		}
		

		$userid = $this->session->userid;
		
		if(empty($data->remark))$data->remark='';
		if(empty($data->photos))$data->photos=array();

		// if(!$this->is_bindwx()){
		// 	$this->response($this->retv->gen_error(ErrorCode::$UnBind) );
		// }


		$level = 0;

		
		if( $this->user['user_type'] ==1 ){//网格员打的点，level=1
			$level = 1;
		}
		

		
		if(empty($data->markerId)){//新建
			$lon = $data->longitude;
			$lat = $data->latitude;
			$params = [
				"key"=>"7de23dce7da811e6cac92d926932a1f9",
				"location"=>"$lon,$lat"
			];
			$url = "https://restapi.amap.com/v3/geocode/regeo";
			$gd_data = $this->curl->simple_get($url,$params);	
			$gd_data = json_decode($gd_data);
			
			$province = empty($gd_data->regeocode->addressComponent->province)?'':$gd_data->regeocode->addressComponent->province;
			$city = empty($gd_data->regeocode->addressComponent->city)?'':$gd_data->regeocode->addressComponent->city;
			if(empty($city))$city=$province;
			$district = empty($gd_data->regeocode->addressComponent->district)?'':$gd_data->regeocode->addressComponent->district;


			$lastid = 0;
			if( $lastid = $this->marker_model->add_marker(
					$data->longitude, 
					$data->latitude, 
					$data->odour,
					$data->intensity,
					$data->remark,
					$level,
					$userid,
					$province,
					$city,
					$district
				) ){
				$r->lastid = $lastid;

				$this->marker_photo_model->add_marker_photo($lastid,$data->photos);

				$this->success($r);
						
			}else{
				$this->fail(ErrorCode::$DbError);
			}
		}else{//更新
			$markerid=$data->markerId;
			$marker = $this->marker_model->get_marker_by_id($markerid);
			if($marker['fk_user']!=$userid){
				$this->fail(ErrorCode::$PermissionDenied);
			}
			$affect = $this->marker_model->update_marker($markerid,$data->odour,$data->intensity,$data->remark,$level);

			if(!empty($data->photos)){
				$this->marker_photo_model->del_marker_photo_by_markerid($markerid);
				$this->marker_photo_model->add_marker_photo($markerid,$data->photos);
			}
			$this->success($affect);
		}
	}

	
	public function get_marker(){
		$this->check_login();


		$markerid = $this->input->get('markerid');
		if(empty($markerid)){
			$this->fail(ErrorCode::$ParamError);
		}
		
		$marker = $this->marker_model->get_marker_by_id($markerid);
		if(empty($marker)){
			$this->fail(ErrorCode::$ParamError);
		}
		$markerInfo['markerId'] = $marker['pk_marker'];

		//$markerInfo['iconPath'] = self::ICON_PATH."stink_{$marker['odour']}.png";
		//$markerInfo['iconPath'] = self::ICON_PATH."marker_{$item['odour']}.png";

		//$markerInfo['iconPath'] = self::ICON_PATH."marker.png";
		//$markerInfo['iconPathChecked'] = self::ICON_PATH."marker_checked.png";
		$markerInfo['longitude'] = (double)$marker['longitude'];
		$markerInfo['latitude'] = (double)$marker['latitude'];
		$markerInfo['title'] = $this->odours[$marker['odour']];
		$markerInfo['odour'] = (int)$marker['odour'];
		$markerInfo['intensity'] = (int)$marker['intensity'];
		$markerInfo['remark'] = $marker['remark'];
		$markerInfo['state'] = (int)$marker['state'];
		$markerInfo['createtime'] = date('m月d日H点m分',strtotime($marker['createtime']));
		$markerInfo['user']['id'] = (int)$marker['fk_user'];
		$markerInfo['photos'] = array();
		$user = $this->user_model->get_user_by_userid($marker['fk_user']);
		if(!empty($user['nickname'])){
			$markerInfo['user']['name'] = $user['nickname'];
		}else{
			$markerInfo['user']['name'] = '有人';
		}
		
		$photos = $this->marker_photo_model->get_photos_by_markerid($markerid);
		if(!empty($photos)){
			$file_name_keys = array();
			foreach ($photos as  $photo) {
				$file_name_keys[] = $photo['file_name_key'];
			}
			$files = $this->res_model->get_files_by_key($file_name_keys);
			if(!empty($files)){
				foreach ($files as $file) {
					$markerInfo['photo_urls'][] = base_url('uploadfiles/').$file['orig_name'] ;
				}
			}
			
		}
		$this->success($markerInfo);
		
	}
	public function delete_marker(){
		$this->check_login();

		$markerid = $this->input->get('markerid');
		if(empty($markerid)){
			$this->fail(ErrorCode::$ParamError);
		}
		if(empty($this->session->userid)){
			$this->fail(ErrorCode::$IllegalUser);
		}
		$userid = $this->session->userid;
		
		$marker = $this->marker_model->get_marker_by_id($markerid);
		if($marker['fk_user']!=$userid){
			$this->fail(ErrorCode::$PermissionDenied);
		}
		$affect = $this->marker_model->delete_marker($markerid);
		
		$this->success($affect);
	}
	public function update_marker(){
		$this->check_login();

		$json_data = $this->input->raw_input_stream;
		$data = json_decode($json_data);
		$r = new stdClass();
		if(empty($data)){
			$this->fail(ErrorCode::$ParamError);
		}
		$markerid=$data->markerId;
		if(empty($markerid)){
			$this->fail(ErrorCode::$ParamError);
		}
		
		if(empty($this->session->userid)){
			$this->fail(ErrorCode::$IllegalUser);
		}
		$userid = $this->session->userid;
		$marker = $this->marker_model->get_marker_by_id($markerid);
		if($marker['fk_user']!=$userid){
			$this->fail(ErrorCode::$PermissionDenied);
		}
		$affect = $this->marker_model->update_marker($markerid,$data->odour);
		if($affect==1){
			$this->fail($affect);
		}else{
			$this->fail(ErrorCode::$DbError);
		}
		
		
		
	}
	private function is_bindwx(){
		if(empty($this->session->userid)){
			return false;
		}
		$userid = $this->session->userid;
		$user = $this->user_model->get_user_by_userid($userid);
		
		if(empty($user)){
			return false;
		}
		
		//这里需要换成电话，手机号码必须留
		if(empty($user['nickname'])){
			return false;
		}
		return true;
	}
	
	
}