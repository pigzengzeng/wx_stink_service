<?php
class Res extends BaseApiController {
	//需要从res配置文件中读取
	private $res_file_upload_path;
	private $res_filename_key;
	private $res_file_max_size;

	public function __construct(){
		parent::__construct();
		$this->load->library('curl');
		$this->load->library('session');
		$this->load->library('retv');
		$this->load->model('user_model');
		$this->load->model('res_model');
		$this->load->library('upload');
		$this->load->config('res');

		$this->res_file_upload_path = $this->config->item('res_file_upload_path');
		$this->res_filename_key = $this->config->item('res_filename_key');
		$this->res_file_max_size = $this->config->item('res_file_max_size');
		
	}
	public function upload(){
		$this->check_login();
		
		
		$resid = (int)$this->res_model->insert_res();
		if($resid>0){
			$file_name_key = md5($this->res_filename_key.$resid);
		}else{
			$this->response($this->retv->gen_error(ErrorCode::$DbError));
		}
		$config = array();
		
		$config['upload_path'] = $this->res_file_upload_path;
		$config['allowed_types'] = 'gif|jpg|png|jpeg';
		$config['max_size'] = $this->res_file_max_size;
		$config['max_width'] = '0';
		$config['max_height'] = '0';
		$config['file_name'] = $file_name_key;//存储的文件名
		
		$this->load->library('upload');
		$this->upload->initialize($config);

		
		if ( ! $this->upload->do_upload('file')){
			$this->response($this->retv->gen_error(ErrorCode::$UploadError,$this->upload->display_errors()));
		}
		else{
			$data = $this->upload->data();
			$file['file_name_key'] = $file_name_key;
			$file['file_name'] = $data['file_name'];
			$file['file_type'] = $data['file_type'];
			$file['raw_name'] = $data['raw_name'];
			$file['orig_name'] = $data['orig_name'];
			$file['client_name'] = $data['client_name'];
			$file['file_ext'] = $data['file_ext'];
			$file['file_size'] = $data['file_size'];
			$file['is_image'] = $data['is_image'];
			if($file['is_image']){
				$file['image_width'] = $data['image_width'];
				$file['image_height'] = $data['image_height'];
				$file['image_type'] = $data['image_type'];
				$file['image_size_str'] = $data['image_size_str'];
			}

			$r = array();
			try{
				if($this->res_model->update_res($resid,$file)>0){
					$r['file_name_key'] = $file_name_key;
					$this->response($this->retv->gen_result($r));
				}else{
					$this->response($this->retv->gen_error(ErrorCode::$UploadError));
				}
			}catch(Exception $e){
				$this->response($this->retv->gen_error(ErrorCode::$UploadError,$e['message']));
			}
			
			
		}
	}
	
	
}