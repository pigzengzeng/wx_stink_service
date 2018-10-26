<?php


class Marker extends BaseApiController {
	const ICON_PATH = '../../images/';
	//key用于icon对应的文件名
	private $odours = [
			'1'=>'药味',
			'2'=>'臭味',
			'3'=>'咸鱼味',
			'4'=>'臭鸡蛋味',
			'5'=>'油烟味',
			'99'=>'无法分辨'
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
	
	public function get_odours(){
		$r = new stdClass();
		$r->odours = $this->odours;
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
			$marker['id'] = $item['pk_marker'];
			$marker['latitude'] = $item['latitude'];
			$marker['longitude'] = $item['longitude'];
			//$marker['title'] = $item['odour'];
			//$marker['iconPath'] = self::ICON_PATH."stink_{$item['odour']}.png";
			$marker['iconPath'] = self::ICON_PATH."marker_{$item['odour']}.png";
			//$marker['iconPathSelected'] = self::ICON_PATH."marker_{$item['odour']}_checked.png";
			$marker['iconPathChecked'] = self::ICON_PATH."marker_checked.png";
			$marker['iconPathUnchecked'] = self::ICON_PATH."marker_{$item['odour']}.png";
			$marker['width'] = 22;
			$marker['height']= 32;
// 			$marker['callout']['content'] = $this->odours[$item['odour']];
// 			$marker['callout']['display'] = "ALWAYS";  //BYCLICK
// 			$marker['callout']['textAlign'] = "center";
			
			//$marker['label']['content'] = $this->odours[$item['odour']];
			
			
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
		
		if(!$this->is_bindwx()){
			$this->response($this->retv->gen_error(ErrorCode::$UnBind) );
		}

		$lastid = 0;
		if( $lastid = $this->marker_model->add_marker(
				$data->longitude, 
				$data->latitude, 
				$data->odour,
				$userid) ){
			
			$r->lastid = $lastid;
			$this->response($this->retv->gen_result($r));
					
		}else{
			$this->response($this->retv->gen_error(ErrorCode::$DbError));
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
		$markerInfo['longitude'] = $marker['longitude'];
		$markerInfo['latitude'] = $marker['latitude'];
		$markerInfo['title'] = $this->odours[$marker['odour']];
		$markerInfo['state'] = $marker['state'];
		$markerInfo['createtime'] = date('m月d日H点',strtotime($marker['createtime']));
		$markerInfo['user']['id'] = $marker['fk_user'];
		
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