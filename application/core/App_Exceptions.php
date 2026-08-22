<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class App_Exceptions extends CI_Exceptions {

    public function __construct() {
        parent::__construct();
    }

    private function _log_custom_error($type, $message, $status_code = null) {
        $log_path = config_item('log_path');
        if (empty($log_path)) {
            $log_path = APPPATH . 'logs/';
        }
        $filepath = rtrim($log_path, '/') . '/http_errors.log';
        $date = date('Y-m-d H:i:s');
        
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
        
        if (!is_cli()) {
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $scheme = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? "https" : "http";
            $url = "{$scheme}://{$host}{$uri}";
        } else {
            $url = 'CLI Command: ' . (isset($_SERVER['argv']) ? implode(' ', $_SERVER['argv']) : '');
        }

        $log_message = "[{$date}] [IP: {$ip}] [{$type}" . ($status_code ? " {$status_code}" : "") . "] [URL: {$url}] - " . (is_array($message) ? implode(' ', $message) : $message) . PHP_EOL;

        if (file_exists($log_path) && is_writable($log_path)) {
            @file_put_contents($filepath, $log_message, FILE_APPEND | LOCK_EX);
        }
    }

    public function show_404($page = '', $log_error = TRUE) {
        $this->_log_custom_error('404 Not Found', $page, 404);
        parent::show_404($page, $log_error);
    }

    public function show_error($heading, $message, $template = 'error_general', $status_code = 500) {
        $this->_log_custom_error('Error', $heading . ' - ' . (is_array($message) ? implode(' ', $message) : $message), $status_code);
        return parent::show_error($heading, $message, $template, $status_code);
    }

    public function show_exception($exception) {
        $this->_log_custom_error('Exception', $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        parent::show_exception($exception);
    }

    public function show_php_error($severity, $message, $filepath, $line) {
        $severity_name = isset($this->levels[$severity]) ? $this->levels[$severity] : $severity;
        $this->_log_custom_error('PHP Error', "Severity: {$severity_name} --> {$message} {$filepath} {$line}");
        parent::show_php_error($severity, $message, $filepath, $line);
    }
}
