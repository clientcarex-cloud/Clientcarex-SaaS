<?php

defined('BASEPATH') or exit('No direct script access allowed');

$resources = get_instance()->perfex_saas_model->companies('', true);
require __DIR__ . '/_resources_select_base.php';