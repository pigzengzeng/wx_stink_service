<?php

class User extends BaseApiController {
	public function __construct(){
		parent::__construct();
		$this->load->library('curl');
	}
	/**
	 * 获取用户信息，如果没绑定，则进行绑定，返回sessionid
	 * 
	 */
	public function get_login_info(){
		$r = new stdClass();
		$code = $this->input->get("code");
		if(empty($code)){
			$this->fail(ErrorCode::$ParamError);
			
		}
		$url = "https://api.weixin.qq.com/sns/jscode2session";
		$params['appid'] = $this->config->item('appid');
		$params['secret'] = $this->config->item('app_secret');
		$params['js_code'] = $code;
		$params['grant_type'] = 'authorization_code';
		
		$data = $this->curl->simple_get($url,  $params);
		$data = json_decode($data);
		if(empty($data)){			
			$this->fail(ErrorCode::$IllegalJsonString);
		}
		if(empty($data->openid)){
			$this->fail(ErrorCode::$IllegalRequest);
		}
		
		$session_key = $data->session_key;  //
		$openid = $data->openid;
		
		$userinfo = array();

		$user = $this->user_model->get_user_by_openid($openid);
		if(empty($user)){//数据库里还没有，选塞一条记录进去
			$userid = $this->user_model->insert_user($openid);
			$userinfo['userid'] = (int)$userid;
			$userinfo['nickname'] = '';
			$userinfo['realname'] = '';
			$userinfo['tel'] = '';
			$userinfo['gender'] = 0;
			$userinfo['user_type'] = 0;
			$userinfo['state'] = 0;
		}else{//数据库里已有记录，则使用
			$userinfo['userid'] = (int)$user['pk_user'];
			$userinfo['nickname'] = $user['nickname'];
			$userinfo['realname'] = $user['realname'];
			$userinfo['tel'] = $user['tel'];
			$userinfo['gender'] = (int)$user['gender'];
			$userinfo['user_type'] = (int)$user['user_type'];
			$userinfo['state'] = (int)$user['state'];
		}
		$this->session->set_userdata(array(
			'session_key'=>$session_key, //可以通过前端存储的session_key和后端取得的session_key对比判断登录是否已过期
			'openid'=>$openid,
			'userid'=>$userinfo['userid'],
		));
		$r->session_id = $this->session->session_id;
		$r->userid = (int)$userinfo['userid'];
		$r->userinfo = $userinfo;
		$this->success($r);			
		
	}

	public function save_user_info(){
		$this->check_login();
		$json_data = $this->input->raw_input_stream;
		$data = json_decode($json_data);
		$r = new stdClass();
		if(empty($data)){
			$this->fail(ErrorCode::$ParamError);
		}
		$userid = $this->session->userid;

		$nickname = $data->nickname;
		$realname = $data->realname;
		$tel = $data->tel;
		$gender = $data->gender;
		$apply_wgy = $data->applyWgy;

		$user = $this->user_model->get_user_by_userid($userid);
		if(empty($user)){
			$this->fail(ErrorCode::$IllegalUser);
		}

		if($user['state']==1){//被屏蔽的用户
			$this->fail(ErrorCode::$PermissionDenied);
		}
		if($user['user_type']==1){//网格员只能更新昵称
			$fields['nickname'] = $nickname;
		}

		if($user['user_type']==0){//普通用户可能随便改自己所有信息
			$fields['nickname'] = $nickname;
			$fields['realname'] = $realname;
			$fields['tel'] = $tel;
			$fields['gender'] = $gender?1:0;

			if($apply_wgy){
				$fields['state']=2;

				//审请网格员，现在如果以上内容都填了自动审核通过
				if( !empty($fields['nickname']) && 
					!empty($fields['realname']) && 
					!empty($fields['tel'])
				){
					$fields['state']=0;
					$fields['user_type']=1;
				}
			}
		}

		try{
			$affect = $this->user_model->update_user($userid,$fields);
			$this->success($affect);

		}catch(Execaption $e){
			$this->fail(ErrorCode::$DbError,$e['message']);
		}

	}

	public function update_last_position(){
		$this->check_login();
		$json_data = $this->input->raw_input_stream;
		$data = json_decode($json_data);
		$r = new stdClass();
		if(empty($data)){
			$this->fail(ErrorCode::$ParamError);
		}
		$userid = $this->session->userid;

		$fields['last_lon'] = (double)$data->lon;
		$fields['last_lat'] = (double)$data->lat;

		try{
			$affect = $this->user_model->update_user($userid,$fields);
			$this->success( $affect );

		}catch(Execaption $e){
			$this->fail(ErrorCode::$DbError,$e['message']);
		}

	}

	public function bind_wx(){
		$openid = $this->session->openid;
		$userid = $this->session->userid;
		$session_key = $this->session->session_key;
		
		$json_data = $this->input->raw_input_stream;
		$data = json_decode($json_data);
		
		if(empty($data->nickName)){
			$this->fail(ErrorCode::$ParamError);
		}
		if(empty($data->nickNameMemo)){
			$this->fail(ErrorCode::$ParamError);
		}
		
		if(empty($this->session->openid)){//没有openid了，返回错误，客户端应该重新登录
			$this->fail(ErrorCode::$IllegalUser);
		}
		
		$fields['nickname'] = $data->nickName;
		$fields['nickname_memo'] = $data->nickNameMemo;
		
		$affect = $this->user_model->update_user($userid,$fields);
		$this->success($affect);
		
	}

	

}