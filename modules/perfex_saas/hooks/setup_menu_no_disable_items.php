<?php
defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_filter('setup_menu_no_disable_items', function ($no_disable) use ($is_tenant) {
    if (!$is_tenant) {
        return [];
    } else {
        return array_diff($no_disable, ['modules']);
    }
});