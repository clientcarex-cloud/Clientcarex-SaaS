<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Transcriptor_debug extends CI_Controller {
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        echo "<h1>PDF Generator Debug Page</h1>";
        
        echo "<h3>1. Directory Permissions</h3>";
        $upload_path = FCPATH . 'uploads/transcriptor_reports/';
        echo "Target Path: " . $upload_path . "<br>";
        if (!is_dir($upload_path)) {
            echo "Directory does not exist. Attempting to create...<br>";
            $res = @mkdir($upload_path, 0775, true);
            echo "Create result: " . ($res ? "Success" : "Failed") . "<br>";
        } else {
            echo "Directory exists.<br>";
        }
        echo "Is Writable: " . (is_writable($upload_path) ? "YES" : "NO") . "<br>";
        
        echo "<h3>2. Database Schema</h3>";
        $this->load->database();
        $fields = $this->db->list_fields(db_prefix() . 'transcriptor_public_tokens');
        echo "Fields in transcriptor_public_tokens: " . implode(', ', $fields) . "<br>";
        if (in_array('is_static', $fields)) {
            echo "is_static exists: YES<br>";
        } else {
            echo "is_static exists: NO<br>";
        }
        
        echo "<h3>3. Recent Tokens Log</h3>";
        $query = $this->db->order_by('id', 'DESC')->limit(10)->get(db_prefix() . 'transcriptor_public_tokens');
        echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Token</th><th>Static?</th><th>File Path</th><th>Created</th></tr>";
        foreach ($query->result() as $row) {
            $static = isset($row->is_static) ? $row->is_static : 'N/A';
            $path = isset($row->file_path) ? $row->file_path : 'N/A';
            echo "<tr><td>{$row->id}</td><td>{$row->token}</td><td>{$static}</td><td>{$path}</td><td>{$row->created_at}</td></tr>";
        }
        echo "</table>";
    }
}
