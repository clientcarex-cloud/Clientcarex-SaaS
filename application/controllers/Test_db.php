<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Test_db extends App_Controller {
    public function index() {
        $fields = $this->db->list_fields(db_prefix() . 'patient_tests');
        echo "Fields: " . implode(', ', $fields) . "\n";
        
        $error = $this->db->error();
        echo "DB Error: " . print_r($error, true) . "\n";
    }
}
