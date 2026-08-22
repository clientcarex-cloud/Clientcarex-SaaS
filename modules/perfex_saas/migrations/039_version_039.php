<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_039 extends App_module_migration
{
    function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        perfex_saas_install();

        if (isset($_COOKIE['autologin'])) {
            setcookie('autologin', '', time() - 3600, '/');
            unset($_COOKIE['autologin']);
        }
    }

    public function down()
    {
    }
}
