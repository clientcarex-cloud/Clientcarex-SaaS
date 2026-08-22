<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_037 extends App_module_migration
{
    function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        perfex_saas_install();

        $filePath = APPPATH . 'migrations/340_version_340.php';
        if (!file_exists($filePath)) return;

        $newContent = <<<'PHP'
<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_340 extends CI_Migration
{
    public function up(): void
    {
        $CI = &get_instance();

        // Add columns to itemable table safely
        if (!$CI->db->field_exists('is_optional', db_prefix() . 'itemable')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "itemable` ADD `is_optional` TINYINT NOT NULL DEFAULT '0' AFTER `unit`;");
        }
        if (!$CI->db->field_exists('is_selected', db_prefix() . 'itemable')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "itemable` ADD `is_selected` TINYINT NOT NULL DEFAULT '1' AFTER `is_optional`;");
        }

        // Add columns to project_notes table safely
        if (!$CI->db->field_exists('title', db_prefix() . 'project_notes')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'project_notes` ADD `title` VARCHAR(255) NULL AFTER `project_id`;');
        }
        if (!$CI->db->field_exists('dateadded', db_prefix() . 'project_notes')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'project_notes` ADD COLUMN `dateadded` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `staff_id`;');
        }

        // Add content_type column to templates table safely
        if (!$CI->db->field_exists('content_type', db_prefix() . 'templates')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'templates` ADD `content_type` VARCHAR(20) NOT NULL DEFAULT "html" AFTER `content`;');
        }
    }
}
PHP;

        if (file_put_contents($filePath, $newContent) == false) {
            exit("Failed to update migration file: {$filePath}\n");
        }
    }

    public function down()
    {
    }
}