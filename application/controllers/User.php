<?php

class User extends BaseApiController {
	public function __construct(){
		parent::__construct();
		$this->load->library('curl');
		$this->load->library('session');
		$this->load->library('retv');
		$this->load->model('user_model');

	}
	/**
	 * 获取用户信息，如果没绑定，则进行绑定，返回sessionid
	 * @return string
	 */
	public function get_login_info(){
		$r = new stdClass();
		$code = $this->input->get("code");
		if(empty($code)){
			$this->response($this->retv->gen_error(ErrorCode::$ParamError));
			
		}
		$url = "https://api.weixin.qq.com/sns/jscode2session";
		$params['appid'] = $this->config->item('appid');
		$params['secret'] = $this->config->item('app_secret');
		$params['js_code'] = $code;
		$params['grant_type'] = 'authorization_code';
		
		$data = $this->curl->simple_get($url,  $params);
		$data = json_decode($data);
		if(empty($data)){			
			$this->response($this->retv->gen_error(ErrorCode::$IllegalJsonString));			
		}
		if(empty($data->openid)){
			$this->response($this->retv->gen_error(ErrorCode::$IllegalRequest));			
		}
		
		$session_key = $data->session_key;
		$openid = $data->openid;
		
		$userid = $this->user_model->bind_wx($openid);
		if(!$userid){
			$this->response($this->retv->gen_error(ErrorCode::$DbError) );
		}
		$this->session->set_userdata(array(
				'session_key'=>$session_key,
				'openid'=>$openid,
				'userid'=>$userid)
		);
		$r->session_id = $this->session->session_id;
		$r->userid = $userid;
		$this->response($this->retv->gen_result($r));			
		
		
	}
	
	public function bind_wx(){
		$openid = $this->session->openid;
		$userid = $this->session->userid;
		$session_key = $this->session->session_key;
		
		$json_data = $this->input->raw_input_stream;
		$data = json_decode($json_data);
		
		if(empty($data->nickName)){
			$this->response($this->retv->gen_error(ErrorCode::$ParamError));
		}
		if(empty($data->nickNameMemo)){
			$this->response($this->retv->gen_error(ErrorCode::$ParamError));
		}
		
		if(empty($this->session->openid)){//没有openid了，返回错误，客户端应该重新登录
			$this->response($this->retv->gen_error(ErrorCode::$IllegalUser) );
		}
		
		$fields['nickname'] = $data->nickName;
		$fields['nickname_memo'] = $data->nickNameMemo;
		
		$affect = $this->user_model->update_user($userid,$openid,$fields);
		$this->response($this->retv->gen_update($affect));
		
	}
}