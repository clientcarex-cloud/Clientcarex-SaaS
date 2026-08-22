<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Schema_dump extends App_Controller {
    public function __construct() {
        parent::__construct();
    }
    public function index() {
        $res = $this->db->query("SHOW COLUMNS FROM " . db_prefix() . "visits")->result_array();
        echo json_encode($res);
    }
}
