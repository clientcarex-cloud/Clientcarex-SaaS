<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Create field settings table
if (!$CI->db->table_exists(db_prefix() . 'ccx_lead_field_settings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'ccx_lead_field_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `slug` VARCHAR(100) NOT NULL,
        `label` VARCHAR(255) NOT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `required` TINYINT(1) NOT NULL DEFAULT 0,
        `field_order` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');

    // Seed default fields
    $defaults = [
        ['slug' => 'name', 'label' => 'Name', 'active' => 1, 'required' => 1, 'field_order' => 1],
        ['slug' => 'title', 'label' => 'Title', 'active' => 1, 'required' => 0, 'field_order' => 2],
        ['slug' => 'email', 'label' => 'Email', 'active' => 1, 'required' => 0, 'field_order' => 3],
        ['slug' => 'phonenumber', 'label' => 'Phone', 'active' => 1, 'required' => 0, 'field_order' => 4],
        ['slug' => 'website', 'label' => 'Website', 'active' => 1, 'required' => 0, 'field_order' => 5],
        ['slug' => 'lead_value', 'label' => 'Lead Value', 'active' => 1, 'required' => 0, 'field_order' => 6],
        ['slug' => 'company', 'label' => 'Company', 'active' => 1, 'required' => 0, 'field_order' => 7],
        ['slug' => 'address', 'label' => 'Address', 'active' => 1, 'required' => 0, 'field_order' => 8],
        ['slug' => 'city', 'label' => 'City', 'active' => 1, 'required' => 0, 'field_order' => 9],
        ['slug' => 'state', 'label' => 'State', 'active' => 1, 'required' => 0, 'field_order' => 10],
        ['slug' => 'country', 'label' => 'Country', 'active' => 1, 'required' => 0, 'field_order' => 11],
        ['slug' => 'zip', 'label' => 'Zip Code', 'active' => 1, 'required' => 0, 'field_order' => 12],
        ['slug' => 'description', 'label' => 'Description', 'active' => 1, 'required' => 0, 'field_order' => 13],
        ['slug' => 'status', 'label' => 'Status', 'active' => 1, 'required' => 1, 'field_order' => 14],
        ['slug' => 'source', 'label' => 'Source', 'active' => 1, 'required' => 0, 'field_order' => 15],
        ['slug' => 'assigned', 'label' => 'Assigned', 'active' => 1, 'required' => 0, 'field_order' => 16],
        ['slug' => 'tags', 'label' => 'Tags', 'active' => 1, 'required' => 0, 'field_order' => 17],
        ['slug' => 'is_public', 'label' => 'Public', 'active' => 1, 'required' => 0, 'field_order' => 18],
    ];

    foreach ($defaults as $field) {
        $CI->db->insert(db_prefix() . 'ccx_lead_field_settings', $field);
    }
}
