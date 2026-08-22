<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cron_debug extends CI_Controller
{
    public function index()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $row = $this->db->get_where(db_prefix() . 'options', ['name' => 'last_cron_run'])->row();
        echo "<h1>Cron Debugger</h1>";
        echo "DB Raw last_cron_run: "; var_dump($row ? $row->value : null); echo "<br>";
        echo "get_option('last_cron_run'): "; var_dump(get_option('last_cron_run')); echo "<br>";
        
        $last_cron_run = get_option('last_cron_run');
        $fromCli = get_option('cron_has_run_from_cli');
        echo "CronJobFailure Condition: (" . ($last_cron_run != '' ? 'true' : 'false') . ") && (" . ($fromCli == '1' ? 'true' : 'false') . ") && (" . ($last_cron_run <= strtotime('-48 hours') ? 'TRUE: (Value ' . $last_cron_run . ' is less than ' . strtotime('-48 hours') . ')' : 'FALSE') . ")<br>";

        echo "<h2>Testing 'after_cron_run' hooks separately</h2>";
        try {
            hooks()->do_action('after_cron_run', true);
            echo "after_cron_run hooks completed successfully.<br>";
        } catch (\Throwable $e) {
            echo "<b>FATAL ERROR IN AFTER_CRON_RUN CAUGHT:</b><br>";
            echo "Message: " . $e->getMessage() . "<br>";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    }
}
