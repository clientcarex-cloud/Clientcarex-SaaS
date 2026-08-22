<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'smart_pdf_templates')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'smart_pdf_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `content` longtext,
  `fields` longtext,
  `paper_size` varchar(10) NOT NULL DEFAULT "A4",
  `orientation` varchar(1) NOT NULL DEFAULT "P",
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'smart_pdf_generations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'smart_pdf_generations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `mode` varchar(10) NOT NULL DEFAULT "print",
  `values` longtext,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// Added for the HR integration: which employee (staff id) a document was
// generated for, mirroring patient_id. Self-healed on existing installs.
if ($CI->db->table_exists(db_prefix() . 'smart_pdf_generations')
    && !$CI->db->field_exists('employee_id', db_prefix() . 'smart_pdf_generations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'smart_pdf_generations` ADD `employee_id` INT(11) DEFAULT NULL AFTER `patient_id`');
}

// Per-template branding override (JSON). When enabled it overrides the global
// Branding Center profile for that one template (e.g. a dark Employee ID card).
// Self-healed on existing installs.
if ($CI->db->table_exists(db_prefix() . 'smart_pdf_templates')
    && !$CI->db->field_exists('branding', db_prefix() . 'smart_pdf_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'smart_pdf_templates` ADD `branding` LONGTEXT NULL AFTER `fields`');
}
