<?php

defined('BASEPATH') or exit('No direct script access allowed');

if ($is_tenant) {
    hooks()->add_action('clients_login_form_end', 'perfex_saas_render_demo_instance_credentials');
    hooks()->add_action('before_admin_login_form_close', 'perfex_saas_render_demo_instance_credentials');
} else {
    hooks()->add_filter('perfex_saas_tenant_seeding_tables', 'perfex_saas_filter_demo_instance_seed_tables');
}