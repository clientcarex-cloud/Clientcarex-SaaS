<?php

defined('BASEPATH') or exit('No direct script access allowed');

function team_can($capability, $feature, $staff_id = '')
{
    $CI = &get_instance();

    $staff_id = $staff_id == '' ? get_staff_user_id() : $staff_id;

    if ($staff_id == '') {
        return false;
    }

    // Admin check - maybe Team members can be "admins" in their own scope? 
    // For now, let's assume no super-admin flag for Team unless we added it to tblteam (we did: 'admin').
    // But is_admin($id) checks tblstaff.

    // Check local permissions table
    $CI->db->select('1');
    $CI->db->from(db_prefix() . 'staff_permissions');
    $CI->db->where('staffid', $staff_id);
    $CI->db->where('feature', $feature);
    $CI->db->where('capability', $capability);

    return $CI->db->count_all_results() > 0;
}

/**
 * Return team profile image url
 * @param  mixed $team_id
 * @param  string $type
 * @return string
 */
function team_profile_image_url($team_id, $type = 'small')
{
    $url = base_url('assets/images/user-placeholder.jpg');

    $CI = &get_instance();
    $CI->db->select('profile_image');
    $CI->db->where('staffid', $team_id);
    $team = $CI->db->get(db_prefix() . 'staff')->row();

    if ($team) {
        if (!empty($team->profile_image)) {
            $profileImagePath = 'uploads/staff_profile_images/' . $team_id . '/' . $type . '_' . $team->profile_image;
            if (file_exists(FCPATH . $profileImagePath)) {
                $url = base_url($profileImagePath);
            }
        }
    }

    return $url;
}

/**
 * Team profile image with href
 * @param  boolean $id        team id
 * @param  array   $classes   image classes
 * @param  string  $type
 * @param  array   $img_attrs additional <img /> attributes
 * @return string
 */
function team_profile_image($id, $classes = ['staff-profile-image'], $type = 'small', $img_attrs = [])
{
    $url = base_url('assets/images/user-placeholder.jpg');

    $id = trim($id);

    $_attributes = '';
    foreach ($img_attrs as $key => $val) {
        $_attributes .= $key . '=' . '"' . e($val) . '" ';
    }

    $blankImageFormatted = '<img src="' . $url . '" ' . $_attributes . ' class="' . implode(' ', $classes) . '" />';

    $CI = &get_instance();
    $result = $CI->app_object_cache->get('team-profile-image-data-' . $id);

    if (!$result) {
        $CI->db->select('profile_image,firstname,lastname');
        $CI->db->where('staffid', $id);
        $result = $CI->db->get(db_prefix() . 'staff')->row();
        $CI->app_object_cache->add('team-profile-image-data-' . $id, $result);
    }

    if (!$result) {
        return $blankImageFormatted;
    }

    if ($result && $result->profile_image !== null) {
        $profileImagePath = 'uploads/staff_profile_images/' . $id . '/' . $type . '_' . $result->profile_image;
        if (file_exists(FCPATH . $profileImagePath)) {
            $profile_image = '<img ' . $_attributes . ' src="' . base_url($profileImagePath) . '" class="' . implode(' ', $classes) . '" />';
        } else {
            return $blankImageFormatted;
        }
    } else {
        $profile_image = '<img src="' . $url . '" ' . $_attributes . ' class="' . implode(' ', $classes) . '" />';
    }

    return $profile_image;
}

function handle_team_profile_image_upload($team_id = '')
{
    if (!is_numeric($team_id)) {
        return false;
    }
    if (function_exists('handle_staff_profile_image_upload')) {
        return handle_staff_profile_image_upload($team_id);
    }
    return false;
}

// Ensure we don't conflict if this function exists in global scope (unlikely for 'team_can')
