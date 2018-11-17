<?php

class BaseApiController extends CI_Controller {
    private $_start_time;
    protected $user = null;

    public function __construct() {
        parent::__construct();
        $this->load->library('session');
        $this->load->library('retv');
        $this->load->model('user_model');

        $this->user = $this->get_user();

        $BM =& load_class('Benchmark', 'core');
        $this->_start_time = $BM->marker['total_execution_time_start'];

    }

    protected function check_login(){                
        if(empty($this->user)){
            $this->fail(-403, 'no login');
        }
        if($this->user['state']==1){
            $this->fail(-403, 'no login');
        }
        return true;
    }

  

    protected function get_user(){
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
        echo json_encode($data);
        exit();
    }


    protected function success($data){
        $res = array(
            'error'     => 0,
            'message'   => 'success',
            'cost'      => (microtime(true) - $this->_start_time)*1000 . 'ms',
            'result'    => $data
        );
        $this->response($res);
        exit();
    }

    protected function fail($errorcode=-9999, $message='unknown error.', $result = null){
        $res = array(
            'error'     => $errorcode,
            'message'   => $message,
            'cost'      => (microtime(true) - $this->_start_time)*1000 . 'ms',
            'result'    => !empty($result) ? $result : null
        );
        $this->response($res);
        die();
    }




}