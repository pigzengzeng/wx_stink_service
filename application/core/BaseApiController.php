<?php

class BaseApiController extends CI_Controller {

    protected $user = null;

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->library('retv');
        $this->load->model('user_model');

        $this->user = $this->_get_user();

        //$this->checkLogin();
    }

    protected function check_login(){
//         $bypass = $this->session->userdata('user_bypss');
//         if($bypass === true || $this->inLoginExlude()){
//             return true;
//         }
        
        if(empty($this->user)){
        	$this->response($this->retv->gen_error(ErrorCode::$IllegalUser) );
        }
        
//         if(empty($this->user)){
//             $this->fail(-403, 'no login');
//         }
        return true;
    }

  

    protected function _get_user(){
        $userid = $this->session->userid;

        if(empty($userid)){
            return null;
        }
        $user = $this->user_model->get_user_by_userid($userid);
        if(empty($user)){
            return null;
        }
        return $user;
    }


    protected function response($data){
        //header('Content-type:text/json');  
        echo json_encode($data);
        exit();
    }




}