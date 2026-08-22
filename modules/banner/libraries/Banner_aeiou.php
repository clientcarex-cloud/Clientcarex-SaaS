<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__.'/../third_party/node.php';
require_once __DIR__.'/../vendor/autoload.php';

use Corbital\Rightful\Classes\CTLExternalAPI as Banner_CTLExternalAPI;

class Banner_aeiou {

    private $bn_lcb;

    public function __construct() 
    {
        $this->bn_lcb = new Banner_CTLExternalAPI();
    }

    public function checkUpdate($module) 
    {
        $CI = &get_instance();
        $data = [
            'title'      => _l('update'),
            'module'     => $module,
            'submit_url' => admin_url($module['system_name']).'/env_ver/check_update',
            'update'     => $this->bn_lcb->checkUpdate(),
            'support'    => $this->bn_lcb->checkSupportExpiryStatus(get_option('banner_support_until_date')),
        ];
        echo $CI->load->view($module['system_name'].'/update', $data, true);
        exit;
    }

    public function downloadUpdate($module, $data) 
    {
        $result = $this->bn_lcb->downloadUpdate(
            $data['update_id'],
            $data['has_sql'],
            $data['latest_version'],
            $data['purchase_key'],
            $data['username']
        );

        echo json_encode([
            'type'    => isset($result['status']) ? 'danger' : 'success',
            'message' => isset($result['message']) ? $result['message'] : _l('module_updated_successfully'),
            'url'     => admin_url('banner/env_ver/check_update'),
        ]);
    }

    public function checkUpdateStatus($module_name) 
    {
        $updateAvailable = $this->bn_lcb->checkUpdate();
        $module = get_instance()->app_modules->get($module_name);

        return isset($updateAvailable['success']) &&
               !empty($updateAvailable['success']) &&
               $updateAvailable['version'] >= $module['installed_version'];
    }

    public function validatePurchase($module_name) {
        return true;
    }
}
