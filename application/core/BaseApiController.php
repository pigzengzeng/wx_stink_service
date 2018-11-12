<?php

class BaseApiController extends CI_Controller {

    protected $user = null;

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->library('retv');
        
        
        
        //$this->user = $this->getUser();
        //$this->checkLogin();
    }

    protected function checkLogin(){
//         $bypass = $this->session->userdata('user_bypss');
//         if($bypass === true || $this->inLoginExlude()){
//             return true;
//         }
        
        if(empty($this->session->openid)){//没有openid了，返回错误，客户端应该重新登录
        	$this->response($this->retv->gen_error(ErrorCode::$IllegalUser) );
        }
        
        
//         if(empty($this->user)){
//             $this->fail(-403, 'no login');
//         }
        return true;
    }

  
/*
    protected function getUser(){
        $this->load->library('session');
        $user = $this->session->userdata('user');
        if(empty($user) || empty($user->userid)){
            return null;
        }
        return $user;
    }
*/

    protected function response($data){
        //header('Content-type:text/json');  
        echo json_encode($data);
        exit();
    }




}