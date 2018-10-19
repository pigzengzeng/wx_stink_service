<?php
/**
 * 将结果进行标准化输出
 *
 * @author xumenghe
 *
 */
class Retv 
{
    const EXEC_CORRECT = 0;  // 执行正确
    private $_canonical = NULL;  //返回结果
    public function __construct() {

        $this->_canonical = new stdClass();
        $this->_canonical->error = self::EXEC_CORRECT;  // 默认执行成功，只有错误对象才改变error属性的值。
        $this->_canonical->cost  = 0;
        $this->_canonical->message = "success";
        
        $BM =& load_class('Benchmark', 'core');
        $this->_start_time = $BM->marker['total_execution_time_start'];
    }
    /**
     * 设置接口运行时间
     * @param timestamp $entry_point
     * @return mixed|null
     */
    public function calcu_cost($entry_point=null){
        if (is_null($entry_point)){
            $entry_point = $this->_start_time;
        }
        return $this->_canonical->cost = microtime(true) - $entry_point;
    }

    /**
     * @return stdClass
     */
    public function gen_empty(){
        $this->calcu_cost();
        return $this->_canonical;
    }

    /**
     * 标准结果处理
     * @param $data
     * @param bool $is_object
     * @return stdClass
     */
    public function gen_result($data, $is_object=true){
        $data = $is_object && is_array($data) ? (object)$data : $data;
        $this->_canonical->result = $data;
        $this->calcu_cost();
        return $this->_canonical;
    }

    /**
     *  标准错误返回处理
     * @param $error_code
     * @param string $error_msg
     * @return stdClass
     */
    public function gen_error($error_code, $error_msg=''){
        $this->_canonical->error = $error_code;
        if ($error_code != self::EXEC_CORRECT) {
            $this->_canonical->message = $error_msg;
        }
        $this->calcu_cost();
        return $this->_canonical;
    }

    public function gen_insert($lastid)
    {
        $result['lastid'] = $lastid;
        return $this->gen_result($result);
    }

    public function gen_insert_batch($affected_rows)
    {
        $result['affected_rows'] = $affected_rows;
        return $this->gen_result($result);
    }

    public function gen_update($affected_rows)
    {
        $result['affected_rows'] = $affected_rows;
        return $this->gen_result($result);
    }

    public function gen_delete($affected_rows)
    {
        $result['affected_rows'] = $affected_rows;
        return $this->gen_result($result);
    }

    public function gen_collections($data = array(), $total = 0, $page = 1, $size = 10, $ext = array())
    {
        $result['total'] = (int)$total;
        $result['page'] = (int)$page;
        $result['size'] = (int)$size;
        if($ext){
            foreach($ext as $k=>$v){
                $result[$k] = $v;
            }
        }
        $result['data'] = $data;
        return $this->gen_result($result);
    }

    public function gen_object($data)
    {
        is_object($data) or $data = (object)$data;
        $this->_canonical->result = $data;
        $this->calcu_cost();
        return $this->_canonical;
    }

    public function gen_boolean($bool)
    {
        $this->_canonical->result = $bool;
        $this->calcu_cost();
        return $this->_canonical;
    }

    public function gen_item($key, $value)
    {
        if (is_array($value)){
            if (empty($this->_canonical->$key)){
                $this->_canonical->$key = $value;
            }else if(is_array($this->_canonical->$key)){
                $this->_canonical->$key = array_merge($this->_canonical->$key, $value);
            }else if (is_object($this->_canonical->$key)){
                foreach($value as $k=>$v){
                    $this->_canonical->$key->$k = $v;
                }
            }
        }else{
            $this->_canonical->$key = $value;
        }
        $this->calcu_cost();
        return $this->_canonical;
    }
}


