<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHRA schema migration.
 *
 * Perfex derives the target migration number from the module's `Version:` header
 * (App_module_migration::set_current_module_version strips the dots), so the
 * 1.3.3 header looks for migration 133. Without a matching file
 * App_modules::upgrade_database() ALWAYS fails for any instance whose
 * tblmodules.installed_version has drifted from the header, and
 * perfex_saas_setup_modules_for_tenant() responds by deleting the module row and
 * re-activating — which cannot restore the row, so the module ends up gone.
 *
 * Keep exactly ONE migration file here and rename it whenever the `Version:`
 * header changes (1.4.0 -> 140_version_140.php). Two files whose numbers differ
 * by more than one trip CodeIgniter's sequence-gap check on a fresh install.
 */
class Migration_Version_133 extends App_module_migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        // install.php is idempotent — every statement is guarded by table_exists /
        // field_exists / add_option — so this doubles as the schema self-heal.
        require(__DIR__ . '/../install.php');
    }

    public function down()
    {
    }
}
