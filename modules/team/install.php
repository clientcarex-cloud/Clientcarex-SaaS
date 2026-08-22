<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!get_option('team_module_hidden_roles')) {
    $CI = &get_instance();
    $roles_to_hide = ['Doctor', 'Jr. Doctor', 'Sr. Doctor', 'Company', 'Referral Lab'];
    $hidden_role_ids = [];

    $CI->db->where_in('name', $roles_to_hide);
    $roles = $CI->db->get(db_prefix() . 'roles')->result_array();

    foreach ($roles as $role) {
        $hidden_role_ids[] = $role['roleid'];
    }

    if (!empty($hidden_role_ids)) {
        add_option('team_module_hidden_roles', json_encode($hidden_role_ids));
    } else {
        add_option('team_module_hidden_roles', json_encode([]));
    }
}
