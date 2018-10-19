<?php

class Marker_model extends CI_model
{
    private $db_main  = null;
    private $db_query = null;

    public function __construct() {
        parent::__construct();

        $this->db_main = $this->load->database('db_main',TRUE, true);
        $this->db_query = $this->load->database('db_query', TRUE, true);
    }
	/**
	 * 通过markerid，获取一个marker点
	 * @param unknown $markerid
	 */
    public function get_marker_by_id($markerid){
    	if (!$query = $this->db_query->get_where('t_marker', ['pk_marker'=>$markerid])) {
    		$e = $this->db_query->error();
    		throw new Exception($e['message'], $e['code']);
    	}
    	return $query->row_array();
    }
    
    
    
	/**
	 * 获取多个marker点
	 */
    
    public function get_markers($range){
    	$size =100 ;
    	$this->db_query->select('*');
    	$this->db_query->from('t_marker');
    	$this->db_query->limit($size);
    	
    	if(!$query = $this->db_query->get()){
    		$e = $this->db_query->error();
    		throw new Exception($e['message'], $e['code']);
    	}
    	return $query->result_array();
    	
    }
    
    /**
     * 新增点
     *
     **/
    public function add_marker($longitude,$latitude,$odour,$userid) {
        $fields = array();
        $longitude = empty((double)$longitude)?0:(double)$longitude;
        $latitude = empty((double)$latitude)?0:(double)$latitude;
        $odour = empty($odour)?99:(int)$odour;
        
        if(!$this->db_main
        		->set('longitude', $longitude)
        		->set('latitude', $latitude)
        		->set('odour',$odour)
        		->set('fk_user',$userid)
            ->insert('t_marker')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        $lastid = $this->db_main->insert_id();

        if($lastid) {
	        return $lastid;
        }
        return false;
    }
    
   

    /**
     * 更新企业信息
     *
     **/
    public function update_company($company_id, array $update_fields) {
        $fields = array();
        empty($update_fields['nm'])    or $fields['company_name']      = $update_fields['nm'];
        empty($update_fields['oc'])    or $fields['organization_code'] = $update_fields['oc'];
        empty($update_fields['tc'])    or $fields['taxpayer_code']     = $update_fields['tc'];
        empty($update_fields['crc'])   or $fields['commercial_code']   = $update_fields['crc'];
        empty($update_fields['cc'])    or $fields['credit_code']       = $update_fields['cc'];
        empty($update_fields['pstate'])    or $fields['product_state']       = $update_fields['pstate'];
        empty($update_fields['addr'])  or $fields['address']           = $update_fields['addr'];
        empty($update_fields['lon'])   or $fields['geo_lon']           = $update_fields['lon'];
        empty($update_fields['lat'])   or $fields['geo_lat']           = $update_fields['lat'];
        empty($update_fields['lp'])    or $fields['legal_person']      = $update_fields['lp'];
        empty($update_fields['cp'])    or $fields['contact_person']    = $update_fields['cp'];
        empty($update_fields['tel'])   or $fields['contact_tel']       = $update_fields['tel'];
        empty($update_fields['email']) or $fields['email']             = $update_fields['email'];
        empty($update_fields['ptype']) or $fields['product_type']             = $update_fields['ptype'];
        empty($update_fields['ctype']) or $fields['component_ctype']          = $update_fields['ctype'];
        $fields['fk_location_province']=$update_fields['province_id'];
        $fields['fk_location_city'] = $update_fields['city_id'];
        $fields['fk_location_district']=$update_fields['district_id'];

        if(empty($fields)) return 0;

        if(!$this->db_main->set($fields)->where('pk_company', $company_id)->update('t_company')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        $affected_rows = $this->db_main->affected_rows();

        if($affected_rows > 0) {
            $this->company_change_log($company_id, self::COMPANY_CHANGE_TYPE_UPDATE, $fields);
        }

        return $affected_rows;
    }

    /**
     * 更新企业状态state信息
     *
     **/
    public function update_company_state($company_id, $state) {
        if(empty($state) || !in_array($state, array(self::COMPANY_STATE_PENDING, self::COMPANY_STATE_NORMAL, self::COMPANY_STATE_REMOVED))){
            return -1;    //参数非法
        }
        $fields = array();
        $fields['state'] = $state;
        if(!$this->db_main->set($fields)->where('pk_company', $company_id)->update('t_company')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }
        $affected_rows = $this->db_main->affected_rows();
        if($affected_rows > 0) {
            $this->company_change_log($company_id, self::COMPANY_CHANGE_TYPE_UPDATE, $fields);
        }
        return $affected_rows;
    }

    /**
     * 获取企业列表(临时)
     *
     **/
    public function list_company($data, $page, $size){
        $fields = array();
        empty($data['group_id'])               or $fields['fk_group']                   = $data['group_id'];
        empty($data['class'])                  or $fields['class']                       = $data['class'];
        empty($data['big'])                     or $fields['big']                         = $data['big'];
        empty($data['middle'])                 or $fields['middle']                      = $data['middle'];
        empty($data['small'])                  or $fields['small']                       = $data['small'];
        empty($data['state'])                  or $fields['state']                       = $data['state'];
        empty($data['province_id'])           or $fields['fk_location_province']      = $data['province_id'];
        empty($data['city_id'])                or $fields['fk_location_city']           = $data['city_id'];
        empty($data['district_id'])           or $fields['fk_location_district']       = $data['district_id'];

        $offset  = ($page - 1) * $size;
        $this->db_query->select('*');
        $this->db_query->from('t_company c');
        $this->db_query->join('t_industry i', 'c.fk_industry = i.pk_industry', 'left');

        if (!empty($fields)){
            $this->db_query->where($fields);
        }
        if (empty($data['state'])){
            $this->db_query->where('c.state !=', self::COMPANY_STATE_REMOVED);
        }
        if (!empty($data['pstate'])){
            $this->db_query->where_in('c.product_state', $data['pstate']);
        }
        if (!empty($data['component_ctype'])){
            $this->db_query->where_in('c.component_ctype', $data['component_ctype']);
        }
        if (!empty($data['district'])){
            $this->db_query->where("c.pk_company in (SELECT DISTINCT(fk_company) FROM t_mapping_company_area WHERE rd_area_name LIKE '%{$data['district']}%')");
        }
        if (!empty($data['company_name'])){
            $this->db_query->like("company_name", $data['company_name'], 'both');
        }
        $this->db_query->order_by('pk_company', 'desc');
        $this->db_query->limit($size, $offset);

        if(!$query = $this->db_query->get()){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        return $query->result_array();
    }

    public function list_company_total($data){
        $fields = array();
        empty($data['group_id'])               or $fields['fk_group']                   = $data['group_id'];
        empty($data['class'])                  or $fields['class']                       = $data['class'];
        empty($data['big'])                     or $fields['big']                         = $data['big'];
        empty($data['middle'])                 or $fields['middle']                      = $data['middle'];
        empty($data['small'])                  or $fields['small']                       = $data['small'];
        empty($data['state'])                  or $fields['state']                       = $data['state'];
        empty($data['province_id'])           or $fields['fk_location_province']      = $data['province_id'];
        empty($data['city_id'])                or $fields['fk_location_city']          = $data['city_id'];
        empty($data['district_id'])           or $fields['fk_location_district']      = $data['district_id'];

        $this->db_query->select('count(1) as total');
        $this->db_query->from('t_company c');
        $this->db_query->join('t_industry i', 'c.fk_industry = i.pk_industry', 'left');

        if (!empty($fields)){
            $this->db_query->where($fields);
        }
        if (empty($data['state'])){
            $this->db_query->where('c.state !=', self::COMPANY_STATE_REMOVED);
        }
        if (!empty($data['pstate'])){
            $this->db_query->where_in('c.product_state', $data['pstate']);
        }
        if (!empty($data['district'])){
            $this->db_query->where("c.pk_company in (SELECT DISTINCT(fk_company) FROM t_mapping_company_area WHERE rd_area_name LIKE '%{$data['district']}%')");
        }
        if (!empty($data['company_name'])){
            $this->db_query->like("company_name", $data['company_name'], 'both');
        }

        if(!$query = $this->db_query->get()){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        $row = $query->row_array();
        return $row['total'];
    }

    /**
     * 添加填报任务时  填报项筛选
     * （搜索当前组企业列表， 排除已添加到同一任务树种的填报企业）
     */
    public function search_report_company($group_id, $task_id_list, $data, $page=1, $size=20){
        $fields = array();
        empty($data['class'])                  or $fields['class']                       = $data['class'];
        empty($data['big'])                     or $fields['big']                         = $data['big'];
        empty($data['middle'])                 or $fields['middle']                      = $data['middle'];
        empty($data['small'])                  or $fields['small']                       = $data['small'];
        empty($data['province_id'])           or $fields['fk_location_province']      = $data['province_id'];
        empty($data['city_id'])                or $fields['fk_location_city']          = $data['city_id'];
        empty($data['district_id'])           or $fields['fk_location_district']      = $data['district_id'];

        $offset  = ($page - 1) * $size;
        $this->db_query->select('*');
        $this->db_query->from('t_company c');
        $this->db_query->join('t_industry i', 'c.fk_industry = i.pk_industry', 'left');

        if (!empty($fields)){
            $this->db_query->where($fields);
        }
        $this->db_query->where('c.state', self::COMPANY_STATE_NORMAL);
        $this->db_query->where('c.fk_group', $group_id);
        if (!empty($data['pstate'])){
            $this->db_query->where_in('c.product_state', $data['pstate']);
        }
        if (!empty($data['district'])){
            $this->db_query->where("c.pk_company in (SELECT DISTINCT(fk_company) FROM t_mapping_company_area WHERE rd_area_name LIKE '%{$data['district']}%')");
        }
        if (!empty($data['company_name'])){
            $this->db_query->like("c.company_name", $data['company_name'], 'both');
        }
        if (!empty($data['component_ctype'])){
            $this->db_query->where_in('c.component_ctype', $data['component_ctype']);
        }
        $this->load->model('task/Report_model');
        $str_term = "c.pk_company not in (SELECT fk_company FROM t_report r LEFT JOIN t_task t ON r.fk_task=t.pk_task WHERE r.ctype=%d AND r.fk_company IS NOT NULL AND r.state!=%d AND r.state!=%d AND t.pk_task in (%s))";
        $this->db_query->where(sprintf($str_term, Report_model::REPORTED_CTYPE_POINT, Report_model::REPORTED_STATE_CLOSED, Report_model::REPORTED_STATE_DELETE, implode(',', $task_id_list)));
        $this->db_query->order_by('pk_company', 'desc');
        $this->db_query->limit($size, $offset);

        if(!$query = $this->db_query->get()){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        return $query->result_array();
    }

    public function search_report_company_total($group_id, $task_id_list, $data){
        $fields = array();
        empty($data['class'])                  or $fields['class']                       = $data['class'];
        empty($data['big'])                     or $fields['big']                         = $data['big'];
        empty($data['middle'])                 or $fields['middle']                      = $data['middle'];
        empty($data['small'])                  or $fields['small']                       = $data['small'];
        empty($data['province_id'])           or $fields['fk_location_province']      = $data['province_id'];
        empty($data['city_id'])                or $fields['fk_location_city']          = $data['city_id'];
        empty($data['district_id'])           or $fields['fk_location_district']      = $data['district_id'];

        $this->db_query->select('count(*) as total');
        $this->db_query->from('t_company c');
        $this->db_query->join('t_industry i', 'c.fk_industry = i.pk_industry', 'left');

        if (!empty($fields)){
            $this->db_query->where($fields);
        }
        $this->db_query->where('c.state', self::COMPANY_STATE_NORMAL);
        $this->db_query->where('c.fk_group', $group_id);
        if (!empty($data['pstate'])){
            $this->db_query->where_in('c.product_state', $data['pstate']);
        }
        if (!empty($data['district'])){
            $this->db_query->where("c.pk_company in (SELECT DISTINCT(fk_company) FROM t_mapping_company_area WHERE rd_area_name LIKE '%{$data['district']}%')");
        }
        if (!empty($data['company_name'])){
            $this->db_query->like("c.company_name", $data['company_name'], 'both');
        }
        if (!empty($data['component_ctype'])){
            $this->db_query->where_in('c.component_ctype', $data['component_ctype']);
        }
        $this->load->model('task/Report_model');
        $str_term = "c.pk_company not in (SELECT fk_company FROM t_report r LEFT JOIN t_task t ON r.fk_task=t.pk_task WHERE r.ctype=%d AND r.fk_company IS NOT NULL AND r.state!=%d AND r.state!=%d AND t.pk_task in (%s))";
        $this->db_query->where(sprintf($str_term, Report_model::REPORTED_CTYPE_POINT, Report_model::REPORTED_STATE_CLOSED, Report_model::REPORTED_STATE_DELETE, implode(',', $task_id_list)));

        if(!$query = $this->db_query->get()){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        $row = $query->row_array();
        return $row['total'];
    }


    /**
     *设置企业所属行业
     *
     **/
    public function set_company_industry($company_id, $industry_id) {
        if(!$this->db_main->set(['fk_industry'=>$industry_id])->where('pk_company', $company_id)->update('t_company')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        return $this->db_main->affected_rows();
    }

    /**
     * 根据group_id,企业名称获取企业信息
     *
     **/
    public function get_company_by_name($group_id, $company_name) {
        $this->db_query->select('*');
        $this->db_query->where('fk_group', $group_id);
        $this->db_query->where('company_name', $company_name);

        if(!$query = $this->db_query->get('t_company')) {
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $query->row();
    }

    /**
     * 获取企业信息
     *
     **/
    public function get_company($company_id) {
        $this->db_query->select('*');
        $this->db_query->where('pk_company', $company_id);

        if(!$query = $this->db_query->get('t_company')) {
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $query->row_array();
    }

    /**
     * 根据id 批量获取企业信息
     * @param $company_ids
     * @return mixed
     * @throws Exception
     */
    public function mget_company($company_ids){
        $this->db_query->select('*');
        $this->db_query->where_in('c.pk_company', $company_ids);
        $this->db_query->from('t_company c');
        $this->db_query->join('t_industry i', 'c.fk_industry = i.pk_industry', 'left');
        if(!$query = $this->db_query->get()) {
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        return $query->result_array();
    }

    /**
     * 更新企业状态
     *
     **/
    public function change_company_state($company_id, $from_state, $to_state) {
        $this->db_main->set('state', $to_state);
        $this->db_main->where('pk_company', $company_id);
        $this->db_main->where('state', $from_state);

        if(!$this->db_main->update('t_company')) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $this->db_main->affected_rows();
    }

    /**
     * 设置企业行政区划信息
     *
     **/
    public function set_company_area($company_id, $area_obj) {
        $area = array();
        $sql = "INSERT IGNORE INTO t_area (`area_name`, `area_code`, `level`) VALUES (?, ?, ?)";

        foreach($area_obj as $key => $val) {
            if (!empty($val->name) and !empty($val->code)) {
                $level = $key == self::AREA_LEVEL_NAME_PROVINCE ? self::AREA_LEVEL_PROVINCE :
                    ($key == self::AREA_LEVEL_NAME_CITY ? self::AREA_LEVEL_CITY : self::AREA_LEVEL_DISTRICT);

                $params = [$val->name, $val->code, $level];

                if(!$this->db_main->query($sql, $params)) {
                    $error = $this->db_main->error();
                    throw new Exception($error['message'], $error['code']);
                }

                if($lastid = $this->db_main->insert_id()) {
                   $area[$key]['pk_area']   = $lastid;
                   $area[$key]['area_name'] = $val->name;
                   $area[$key]['area_code'] = $val->code;
                   $area[$key]['level']     = $level;

                } else {
                    $this->db_query->select('pk_area, area_name, area_code, level');
                    $this->db_query->where('area_name', $val->name);
                    $this->db_query->where('level', $level);

                    if(!$query = $this->db_query->get('t_area')) {
                        $error = $this->db_query->error();
                        throw new Exception($error['message'], $error['code']);
                    }

                    $area[$key] = $query->row_array();
                }
            }
        }

        $this->db_main->where('fk_company', $company_id);
        if(!$this->db_main->delete('t_mapping_company_area')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        $company_area = array();
        foreach($area as $key=>$val) {
            $company_area[] = [
                'fk_company'   => $company_id,
                'fk_area'      => $val['pk_area'],
                'rd_area_name' => $val['area_name'],
                'rd_area_code' => $val['area_code'],
                'rd_level'     => $val['level']
            ];
        }

        if(!$this->db_main->insert_batch('t_mapping_company_area', $company_area)) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        return true;
    }


    /**
     * 获取企业行政区划
     *
     **/
    public function get_company_area($company_id) {
        $this->db_query->select('*');
        $this->db_query->where('fk_company', $company_id);

        if(!$query = $this->db_query->get('t_mapping_company_area')) {
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $query->result_array();
    }

    /**
     * 增加企业照片
     *
     **/
    public function add_company_photo($company_id, $fileid, $shtime, $memo=null) {

        $fields = array();
        $fields['fk_company']     = $company_id;
        $fields['fileid']         = $fileid;
        $fields['shooting_time']  = $shtime;
        if($memo) $fields['memo'] = $memo;

        if(!$this->db_main->set($fields)->insert('t_company_photos')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        return $this->db_main->insert_id();
    }

    /**
     * 修改企业照片
     *
     **/
    public function update_company_photo($id, $sht, $memo=null) {
        $this->db_main->set('shooting_time', $sht);
        if($memo) $this->db_main->set('memo', $memo);
        $this->db_main->where('pk_company_photos', $id);

        if(!$this->db_main->update('t_company_photos')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        return $this->db_main->affected_rows();
    }

    /**
     * 获取企业某一张照片信息
     *
     **/
    public function get_company_photo($id) {

        if(!$query = $this->db_query->get_where('t_company_photos', ['pk_company_photos'=>$id])) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        return $query->row_array();
    }
    /**
     * 更新企业照片状态
     *
     **/
    public function change_company_photo_state($photo_id, $from_state, $to_state) {
        $this->db_main->set('state', $to_state);
        $this->db_main->where('pk_company_photos', $photo_id);
        $this->db_main->where('state', $from_state);

        if(!$this->db_main->update('t_company_photos')) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $this->db_main->affected_rows();
    }

    /**
     * 获取企业图片列表
     *
     **/
    public function list_company_photos($company_id, $page=1, $size=20) {
        $offset  = ($page - 1) * $size;

        $this->db_query->select('*');
        $this->db_query->where(['fk_company'=>$company_id, 'state'=>self::COMPANY_PHOTO_STATE_NORMAL]);
        $this->db_query->limit($size, $offset);

        if(!$query = $this->db_query->get('t_company_photos')){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $query->result();
    }

    /**
     * 增加企业别名
     *
     **/
    public function add_company_alias($company_id, $alias, $source) {
        $sql = "INSERT IGNORE INTO t_company_alias (`fk_company`, `alias`, `source`) VALUES (?, ?, ?)";
        $values = [$company_id, $alias, $source];

        if(!$this->db_main->query($sql, $values)) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $this->db_main->insert_id();
    }

    /**
     * 获取企业某个别名信息
     *
     **/
    public function get_company_alias($id) {

        if (!$query = $this->db_query->get_where('t_company_alias', ['pk_company_alias'=>$id])) {
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $query->row_array();
    }

    /**
     * 更新企业别名
     *
     **/
    public function update_company_alias($id, $alias, $source) {
        $fields = [
            'alias'  => $alias,
            'source' => $source
        ];

        $this->db_main->where('pk_company_alias', $id);

        if(!$this->db_main->update('t_company_alias', $fields)) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $this->db_main->affected_rows();
    }


    /**
     * 更新企业别名状态
     *
     **/
    public function change_company_alias_state($id, $from_state, $to_state) {
        $this->db_main->set('state', $to_state);
        $this->db_main->where(['pk_company_alias'=>$id, 'state'=>$from_state]);

        if(!$this->db_main->update('t_company_alias')) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $this->db_main->affected_rows();
    }

    /**
     * 获取企业别名列表
     *
     **/
    public function list_company_alias($company_id, $page=1, $size=20) {
        $offset  = ($page - 1) * $size;

        $this->db_query->select('*');
        $this->db_query->where(['fk_company'=>$company_id, 'state'=>self::COMPANY_ALIAS_STATE_NORMAL]);
        $this->db_query->limit($size, $offset);

        if(!$query = $this->db_query->get('t_company_alias')){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $query->result();
    }



    /**
     * 获取某个标签
     *
     **/
    public function get_tag($id) {
        if (!$query = $this->db_query->get_where('t_company_tag', ['pk_company_tag'=>$id])) {
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        return $query->row_array();
    }

    /**
     * 增加企业标签
     **/
    public function add_tag($tag) {
        $sql = "INSERT INTO t_company_tag (`tag_name` ) VALUES (?)";
        $values = [$tag];

        if(!$this->db_main->query($sql, $values)) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $this->db_main->insert_id();
    }

    /**
     * 更新企业标签
     **/
    public function update_tag($id, $tag) {
        $fields = [
            'tag_name'  => $tag
        ];

        $this->db_main->where('pk_company_tag', $id);

        if(!$this->db_main->update('t_company_tag', $fields)) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }

        return $this->db_main->affected_rows();
    }

    /**
     * 删除企业标签
     **/
    public function delete_tag($id) {
        $this->db_main->where('pk_company_tag', $id);
        if(!$this->db_main->delete('t_company_tag')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }
        return $this->db_main->affected_rows();
    }


    public function set_company_tags($company_id, $tags){
        $this->db_query->select('pk_company_tag, tag_name');
        $this->db_query->where_in('tag_name', $tags);
        if(!$query = $this->db_query->get('t_company_tag')){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }

        $tags_data = array();
        foreach($query->result_array() as $row){
            $tags_data[$row['tag_name']] = $row;
        }
        foreach(array_diff($tags, array_keys($tags_data)) as $tag ){
            try {
                $last_id = $this->add_tag($tag);
            } catch (Exception $e) {
                throw $e;
            }
            if (empty($last_id)){
                continue;
            }
            $_data = [
                'pk_company_tag'=> $last_id,
                'tag_name'=> $tag
            ];
            $tags_data[$tag] = $_data;
        }

        $this->db_main->where('fk_company', $company_id);
        if(!$this->db_main->delete('t_mapping_company_tag')) {
            $error = $this->db_main->error();
            throw new Exception($error['message'], $error['code']);
        }

        $fields = array();
        foreach($tags as $key=>$tag){
            if (!array_key_exists($tag, $tags_data)){
                continue;
            }
            $item = [
                'fk_company' => $company_id,
                'fk_company_tag' => $tags_data[$tag]['pk_company_tag'],
                'tag_name' => $tag,
                'sort' => $key+1
            ];
            $fields[] = $item;
        }
        if(!$this->db_main->insert_batch('t_mapping_company_tag', $fields)) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }
        return true;
    }

    /**
     * 获取企业标签列表
     **/
    public function list_company_tag($company_id, $page=1, $size=20) {
        $offset  = ($page - 1) * $size;
        $this->db_query->select('*');
        $this->db_query->where(['fk_company'=>$company_id]);
        $this->db_query->order_by('sort', 'ASC');
        $this->db_query->limit($size, $offset);

        if(!$query = $this->db_query->get('t_mapping_company_tag')){
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        return $query->result();
    }


    /**
     * 设置企业生产月份
     **/
    public function set_company_production_month($company_id, $month) {
        $sql = "INSERT INTO t_company_production_month (fk_comany, prd_month) values (?, ?) ON DUPLICATE KEY UPDATE prd_month=?";
        $values = [$company_id, $month, $month];
        if(!$this->db_main->query($sql, $values)) {
            $e = $this->db_main->error();
            throw new Exception($e['message'], $e['code']);
        }
        return true;
    }

    /**
     * 获取企业生产月份
     **/
    public function get_company_production_month($company_id) {
        if (!$query = $this->db_query->get_where('t_company_production_month', ['fk_comany'=>$company_id])) {
            $e = $this->db_query->error();
            throw new Exception($e['message'], $e['code']);
        }
        return $query->row_array();
    }

}
