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
		$this->load->library('curl');
		$this->load->library('session');
		$this->load->library('retv');
		$this->load->model('marker_model');
		$this->load->model('user_model');
	}
	
	public function get_odours(){//老版
		$r = new stdClass();
		$r->odours = $this->odours;
		$this->response($this->retv->gen_result($r));
		
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
		$this->response($this->retv->gen_result($r));
	}
	public function get_markers(){
		$x1 = $this->input->get('x1');
		$y1 = $this->input->get('y1');
		$x2 = $this->input->get('x2');
		$y2 = $this->input->get('y2');
		$r = new stdClass();
		
		$markers = $this->marker_model->get_markers($x1,$y1,$x2,$y2);
		if(empty($markers)){
			$this->response($this->retv->gen_error(ErrorCode::$DbEmpty) );
		}
		
 		if(empty($this->session->openid)){//没有openid了，返回错误，客户端应该重新登录
 			$this->response($this->retv->gen_error(ErrorCode::$IllegalUser) );
 		}
		foreach ($markers as $item){
			$marker = [];
			$marker['id'] = (int)$item['pk_marker'];
			//$marker['zIndex'] = 0;
		
			$marker['latitude'] = (double)$item['latitude'];
			$marker['longitude'] = (double)$item['longitude'];
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
		$this->response($this->retv->gen_result($r));

	}
	public function save_marker(){
		$json_data = $this->input->raw_input_stream;
		$data = json_decode($json_data);
		$r = new stdClass();

		if(empty($data)){
			$this->response($this->renv->gen_error(ErrorCode::$ParamError));
		}
		if(empty($this->session->openid)){//没有openid了，返回错误，客户端应该重新登录
			$this->response($this->retv->gen_error(ErrorCode::$IllegalUser) );
		}
		$userid = $this->session->userid;
		
		// if(!$this->is_bindwx()){
		// 	$this->response($this->retv->gen_error(ErrorCode::$UnBind) );
		// }

		
		if(empty($data->markerId)){//新建
			$lastid = 0;
			if( $lastid = $this->marker_model->add_marker(
					$data->longitude, 
					$data->latitude, 
					$data->odour,
					$data->intensity,
					$userid) ){
				$r->lastid = $lastid;
				$this->response($this->retv->gen_result($r));
						
			}else{
				$this->response($this->retv->gen_error(ErrorCode::$DbError));
			}
		}else{//更新
			$markerid=$data->markerId;
			$marker = $this->marker_model->get_marker_by_id($markerid);
			if($marker['fk_user']!=$userid){
				$this->response($this->retv->gen_error(ErrorCode::$PermissionDenied) );
			}
			$affect = $this->marker_model->update_marker($markerid,$data->odour,$data->intensity);
			if($affect==1){
				$this->response($this->retv->gen_update($affect));
			}else{
				$this->response($this->retv->gen_error(ErrorCode::$DbError));
			}

		}
		
	}
	
	public function get_marker(){
		$markerid = $this->input->get('markerid');
		if(empty($markerid)){
			$this->response($this->retv->gen_error(ErrorCode::$ParamError));
		}
		
		$marker = $this->marker_model->get_marker_by_id($markerid);
		
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
		$markerInfo['state'] = (int)$marker['state'];
		$markerInfo['createtime'] = date('m月d日H点',strtotime($marker['createtime']));
		$markerInfo['user']['id'] = (int)$marker['fk_user'];
		
		$user = $this->user_model->get_user_by_userid($marker['fk_user']);
		$markerInfo['user']['name'] = empty($user['nickname_memo'])?$user['nickname']:$user['nickname_memo'];
		$this->response($this->retv->gen_result($markerInfo));
		
	}
	public function delete_marker(){
		$markerid = $this->input->get('markerid');
		if(empty($markerid)){
			$this->response($this->retv->gen_error(ErrorCode::$ParamError));
		}
		if(empty($this->session->userid)){
			$this->response($this->retv->gen_error(ErrorCode::$IllegalUser) );
		}
		$userid = $this->session->userid;
		
		$marker = $this->marker_model->get_marker_by_id($markerid);
		if($marker['fk_user']!=$userid){
			$this->response($this->retv->gen_error(ErrorCode::$PermissionDenied) );
		}
		$affect = $this->marker_model->delete_marker($markerid);
		
		$this->response($this->retv->gen_delete($affect));
	}
	public function update_marker(){
		$json_data = $this->input->raw_input_stream;
		$data = json_decode($json_data);
		$r = new stdClass();
		if(empty($data)){
			$this->response($this->renv->gen_error(ErrorCode::$ParamError));
		}
		$markerid=$data->markerId;
		if(empty($markerid)){
			$this->response($this->retv->gen_error(ErrorCode::$ParamError));
		}
		
		if(empty($this->session->userid)){
			$this->response($this->retv->gen_error(ErrorCode::$IllegalUser) );
		}
		$userid = $this->session->userid;
		$marker = $this->marker_model->get_marker_by_id($markerid);
		if($marker['fk_user']!=$userid){
			$this->response($this->retv->gen_error(ErrorCode::$PermissionDenied) );
		}
		$affect = $this->marker_model->update_marker($markerid,$data->odour);
		if($affect==1){
			$this->response($this->retv->gen_update($affect));
		}else{
			$this->response($this->retv->gen_error(ErrorCode::$DbError));
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