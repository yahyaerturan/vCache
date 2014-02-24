<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Vcache Class
 *
 * Database & JSON Driven Caching Library for CodeIgniter
 *
 * @category  Libraries
 * @author    Yahya A. ERTURAN
 * @link    http://yahyaerturan.com/
 * @license   MIT
 * @version   0.1
 */

class Vcache
{
  private $_ci;
  private $_time_zone;
  private $_ttl_unit;
  private $_db_tbl_name;
  private $_db_cache_key;
  private $_db_cache_val;
  private $_db_cache_user;

  /**
   * Constructor - Initializes and references CI
   */
  function __construct()
  {
    $this->_ci =& get_instance();
    $this->_ttl_unit      = 'M';
    $this->_time_zone     = 'Europe/Istanbul';
    $this->_db_tbl_name   = 'v_cached';
    $this->_db_cache_key  = 'c_key';
    $this->_db_cache_val  = 'c_val';
    $this->_db_cache_type = 'c_type';
    $this->_db_cache_user = 'id_user';

    // Uncomment if cache table is not created by Migrations
    // $this->_check_cache_table();
  }

// ------------------------------------------------------------------------

  /**
   * vcache::_secure_key
   *
   * @access  private
   * @param string $str
   * @return string
   */
  private function _secure_key ($str) {
    return preg_replace('/[^A-Za-z0-9_-|]+/', '', $str);
  }

// ------------------------------------------------------------------------

  /**
   * vcache::_jsonify
   *
   * @access  private
   * @param mixed $data
   * @return string
   */
  private function _jsonify ($data) {
    return json_encode($data);
  }

// ------------------------------------------------------------------------

  /**
   * vcache::_now
   *
   * @access  private
   * @return mixed
   */
  private function _now () {
    return new DateTime('now', new DateTimeZone($this->_time_zone));
  }

// ------------------------------------------------------------------------

  /**
   * vcache::_expires_at
   *
   * @access  public
   * @param integer $ttl
   * @return string
   */
  public function _expires_at ($ttl=0) {
    $now = $this->_now();
    $die  = $now->format('Y-m-d H:i:s');
    if(intval($ttl)){
      $now->add(new DateInterval('PT'.$ttl.$this->_ttl_unit));
      $die = $now->format('Y-m-d H:i:s');
      if($ttl == 1001) $die = NULL;
    }
    return $die;
  }

// ------------------------------------------------------------------------

  /**
   * vcache::save
   *
   * @access  public
   * @param string $key
   * @param mixed $data
   * @param integer $ttl
   * @param integer $id_user
   * @return boolean
   */
  public function save ($key,$data,$ttl=0,$id_user=NULL)
  {
    // Set values
    $key  = $this->_secure_key($key);
    $data = $this->_jsonify($data);
    $till = $this->_expires_at($ttl);

    // Check if cache_key exists
    $cont = $this->_ci->db->get_where($this->_db_tbl_name,array($this->_db_cache_key=>$key));

    // Process request
    if($cont->row()) {
      // Update Cache Record
      $this->_ci->db->set(array(
        $this->_db_cache_val=>$data,
        'expires_at'=>$till,
        $this->_db_cache_user=>$id_user
      ));
      $this->_ci->db->where(array($this->_db_cache_key=>$key));
      if($this->_ci->db->update($this->_db_tbl_name))  return TRUE;
      else return FALSE;
    } else {
      // Create Cache Record
      $this->_ci->db->set(array(
        $this->_db_cache_key=>$key,
        $this->_db_cache_val=>$data,
        'expires_at'=>$till,
        $this->_db_cache_user=>$id_user
      ));
      if($this->_ci->db->insert($this->_db_tbl_name)) return TRUE;
      else return FALSE;
    }
  }

// ------------------------------------------------------------------------

  /**
   * vcache::get
   *
   * @access  public
   * @param string $key
   * @param integer $id_user
   * @return mixed
   */
  public function get ($key,$id_user=NULL) {
    $key  = $this->_secure_key($key);
    $cont = $this->_ci->db->get_where($this->_db_tbl_name,array($this->_db_cache_key=>$key));
    if($cached = $cont->row()) {
      if($cached->expires_at) {
        $now    = $this->_now();
        $expire = new DateTime($cached->expires_at);
        if($now >= $expire) $this->_ci->db->delete($this->_db_tbl_name,array($this->_db_cache_key=>$key));
      }
      $cached_val_field = $this->_db_cache_val;
      return json_decode($cached->$cached_val_field);
    } else {
      return FALSE;
    }
  }

// ------------------------------------------------------------------------

  /**
   * vcache::delete
   *
   * @access  public
   * @param string $key
   * @return boolean
   */
  public function delete ($key) {
    $key  = $this->_secure_key($key);
    $this->_ci->db->delete($this->_db_tbl_name,array($this->_db_cache_key=>$key));
    return $this->_ci->db->affected_rows();
  }

// ------------------------------------------------------------------------

  /**
   * vcache::delete_userdata
   *
   * @access  public
   * @param integer $id_user
   * @return boolean
   */
  public function delete_userdata ($id_user) {
    $this->_ci->db->delete($this->_db_tbl_name,array($this->_db_cache_user=>intval($id_user)));
    return $this->_ci->db->affected_rows();
  }

// ------------------------------------------------------------------------

  /**
   * vcache::delete_all
   *
   * @access  public
   * @return void
   */
  public function delete_all () {
    $this->_ci->db->truncate($this->_db_tbl_name);
  }

// ------------------------------------------------------------------------

  /**
   * vcache::_check_cache_table
   *
   * @access  private
   * @return void
   */
  private function _check_cache_table() {
    ($this->_ci->db->table_exists($this->_db_tbl_name)) || $this->_create_cache_table();
  }

// ------------------------------------------------------------------------

  /**
   * vcache::_create_table
   *
   * @access  private
   * @return void
   */
  private function _create_cache_table() {
    $this->_ci->load->dbforge();
    $fields = array(
      'c_key'=>array('type'=>'VARCHAR','constraint'=>'255'),
      'c_val'=>array('type'=>'MEDIUMTEXT','null'=>TRUE),
      'expires_at'=>array('type'=>'DATETIME','null'=>TRUE),
      'id_user'=>array('type'=>'BIGINT','constraint'=>20,'unsigned'=>TRUE,'null'=>TRUE)
    );
    $this->_ci->dbforge->add_field($fields);
    $this->_ci->dbforge->create_table($this->_db_tbl_name);

    $q = 'ALTER TABLE ' . $this->_db_tbl_name . ' ADD UNIQUE INDEX ' . $this->_db_cache_key . '_unique ('.$this->_db_cache_key.');';
    $this->_ci->db->query($q);
  }

// ------------------------------------------------------------------------

}

/* End of file Vcache.php */
/* Location: ./application/libraries/Vcache.php */
