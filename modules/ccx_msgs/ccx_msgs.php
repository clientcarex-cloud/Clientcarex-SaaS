<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: CCX Msgs
Description: Module to manage message counts and expiry dates for companies (SMS, WhatsApp, Email, AI Call).
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CCX_MSGS_MODULE_NAME', 'ccx_msgs');

hooks()->add_action('admin_init', 'ccx_msgs_module_init_menu_items');
hooks()->add_action('after_cron_run', 'ccx_msgs_cron_purge_old_logs');

/**
 * Cron job: delete API logs older than 48 hours
 */
function ccx_msgs_cron_purge_old_logs()
{
    $CI = &get_instance();
    $CI->db->where('created_at <', date('Y-m-d H:i:s', strtotime('-48 hours')));
    $CI->db->delete(db_prefix() . 'ccx_msgs_api_logs');
}

/**
 * Register activation module hook
 */
register_activation_hook(CCX_MSGS_MODULE_NAME, 'ccx_msgs_module_activation_hook');

function ccx_msgs_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

// Self-healing: run install.php on admin_init to apply any new migrations
hooks()->add_action('admin_init', 'ccx_msgs_run_migrations');
function ccx_msgs_run_migrations()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(CCX_MSGS_MODULE_NAME, [CCX_MSGS_MODULE_NAME]);

/**
 * Init ccx_msgs module menu items in setup in admin_init hook
 * @return null
 */
function ccx_msgs_module_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('ccx_msgs', '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('ccx_msgs', [
            'name' => _l('ccx_msgs'),
            'collapse' => true,
            'icon' => 'fa fa-envelope',
            'position' => 45,
        ]);

        $CI->app_menu->add_sidebar_children_item('ccx_msgs', [
            'slug' => 'ccx_msgs_allocations',
            'name' => _l('ccx_msgs_allocations'),
            'href' => admin_url('ccx_msgs'),
            'position' => 1,
        ]);

        $CI->app_menu->add_sidebar_children_item('ccx_msgs', [
            'slug' => 'ccx_msgs_pricing',
            'name' => _l('ccx_msgs_pricing'),
            'href' => admin_url('ccx_msgs/pricing'),
            'position' => 2,
        ]);

        $CI->app_menu->add_sidebar_children_item('ccx_msgs', [
            'slug' => 'ccx_msgs_apis',
            'name' => _l('ccx_msgs_apis'),
            'href' => admin_url('ccx_msgs/apis'),
            'position' => 3,
        ]);

        $CI->app_menu->add_sidebar_children_item('ccx_msgs', [
            'slug' => 'ccx_msgs_recharge_logs',
            'name' => _l('ccx_msgs_recharge_logs'),
            'href' => admin_url('ccx_msgs/recharge_logs'),
            'position' => 4,
        ]);

        $CI->app_menu->add_sidebar_children_item('ccx_msgs', [
            'slug' => 'ccx_msgs_promo_codes',
            'name' => _l('ccx_msgs_promo_codes'),
            'href' => admin_url('ccx_msgs/promo_codes'),
            'position' => 5,
        ]);

        $CI->app_menu->add_sidebar_children_item('ccx_msgs', [
            'slug' => 'ccx_msgs_coupons',
            'name' => _l('ccx_msgs_coupons'),
            'href' => admin_url('ccx_msgs/coupons'),
            'position' => 6,
        ]);
    }
}

// Hook to add credits upon successful payment of a recharge invoice
hooks()->add_action('invoice_paid', 'ccx_msgs_handle_recharge_payment');
function ccx_msgs_handle_recharge_payment($invoice_id)
{
    $CI = &get_instance();
    
    // Check if this invoice is a recharge invoice
    $CI->db->where('invoice_id', $invoice_id);
    $CI->db->where('status', 'pending');
    $log = $CI->db->get(db_prefix() . 'ccx_msgs_recharge_logs')->row();

    if ($log) {
        // Fetch the plan
        $CI->load->model('ccx_msgs/ccx_msgs_pricing_model');
        $plan = $CI->ccx_msgs_pricing_model->get($log->plan_id);

        if ($plan) {
            // Update log status
            $CI->db->where('id', $log->id);
            $CI->db->update(db_prefix() . 'ccx_msgs_recharge_logs', ['status' => 'paid']);

            // Parse plan to find which count to update
            // Plans just have message_type (sms, whatsapp, email, aicall) and message_count, expiry_days
            // By default, assuming promo credits for self-purchased plans, or distributing to trans if needed. 
            // We'll put them in promo_count for now.
            $type = $plan->message_type;
            if ($type == 'aicall') {
                $count_field = 'aicall_promo_count_add';
                $expiry_field = 'aicall_promo_expiry';
            } else {
                $count_field = $type . '_promo_count_add';
                $expiry_field = $type . '_promo_expiry';
            }

            $current_allocation = $CI->db->where('client_id', $log->client_id)->get(db_prefix() . 'ccx_msgs_allocations')->row();
            
            $allocation_data = [
                'client_id' => $log->client_id,
                $count_field => (int)$plan->message_count,
            ];

            // If an expiry date exists and is in the future, extend it?
            // Simple logic: add expiry_days from today.
            if ($plan->expiry_days > 0) {
                // Determine new expiry
                $current_expiry_val = $current_allocation && isset($current_allocation->{$expiry_field}) ? $current_allocation->{$expiry_field} : null;
                $new_expiry = date('Y-m-d', strtotime('+' . $plan->expiry_days . ' days'));
                
                // Only overwrite if the new expiry is greater than current (extend) or if current is empty/past
                if (!$current_expiry_val || strtotime($new_expiry) > strtotime($current_expiry_val)) {
                    $allocation_data[$expiry_field] = $new_expiry;
                }
            }

            // Save allocation
            $CI->load->model('ccx_msgs/ccx_msgs_model');
            $CI->ccx_msgs_model->save_allocation($allocation_data);
        }
    }
}
