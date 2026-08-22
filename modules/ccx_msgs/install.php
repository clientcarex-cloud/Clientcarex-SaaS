<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_allocations')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  
  `sms_promo_count` int(11) DEFAULT '0',
  `sms_trans_count` int(11) DEFAULT '0',
  `whatsapp_promo_count` int(11) DEFAULT '0',
  `whatsapp_trans_count` int(11) DEFAULT '0',
  `email_promo_count` int(11) DEFAULT '0',
  `email_trans_count` int(11) DEFAULT '0',
  `aicall_promo_count` int(11) DEFAULT '0',
  `aicall_trans_count` int(11) DEFAULT '0',
  
  `sms_promo_expiry` date DEFAULT NULL,
  `sms_trans_expiry` date DEFAULT NULL,
  `whatsapp_promo_expiry` date DEFAULT NULL,
  `whatsapp_trans_expiry` date DEFAULT NULL,
  `email_promo_expiry` date DEFAULT NULL,
  `email_trans_expiry` date DEFAULT NULL,
  `aicall_promo_expiry` date DEFAULT NULL,
  `aicall_trans_expiry` date DEFAULT NULL,
  
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
} else {
  // Migration: Table exists, apply new columns and rename old ones if missing

  // 1. Rename existing metric columns to promo
  if ($CI->db->field_exists('sms_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `sms_count` `sms_promo_count` int(11) DEFAULT "0";');
  }
  if ($CI->db->field_exists('whatsapp_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `whatsapp_count` `whatsapp_promo_count` int(11) DEFAULT "0";');
  }
  if ($CI->db->field_exists('email_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `email_count` `email_promo_count` int(11) DEFAULT "0";');
  }
  if ($CI->db->field_exists('aicall_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `aicall_count` `aicall_promo_count` int(11) DEFAULT "0";');
  }

  // 2. Rename existing expiry columns to promo
  if ($CI->db->field_exists('sms_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `sms_expiry` `sms_promo_expiry` date DEFAULT NULL;');
  }
  if ($CI->db->field_exists('whatsapp_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `whatsapp_expiry` `whatsapp_promo_expiry` date DEFAULT NULL;');
  }
  if ($CI->db->field_exists('email_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `email_expiry` `email_promo_expiry` date DEFAULT NULL;');
  }
  if ($CI->db->field_exists('aicall_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` CHANGE `aicall_expiry` `aicall_promo_expiry` date DEFAULT NULL;');
  }

  // 3. Add transactional metric columns
  if (!$CI->db->field_exists('sms_trans_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `sms_trans_count` int(11) DEFAULT "0";');
  }
  if (!$CI->db->field_exists('whatsapp_trans_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `whatsapp_trans_count` int(11) DEFAULT "0";');
  }
  if (!$CI->db->field_exists('email_trans_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `email_trans_count` int(11) DEFAULT "0";');
  }
  if (!$CI->db->field_exists('aicall_trans_count', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `aicall_trans_count` int(11) DEFAULT "0";');
  }

  // 4. Add transactional expiry columns
  if (!$CI->db->field_exists('sms_trans_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `sms_trans_expiry` date DEFAULT NULL;');
  }
  if (!$CI->db->field_exists('whatsapp_trans_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `whatsapp_trans_expiry` date DEFAULT NULL;');
  }
  if (!$CI->db->field_exists('email_trans_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `email_trans_expiry` date DEFAULT NULL;');
  }
  if (!$CI->db->field_exists('aicall_trans_expiry', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` ADD `aicall_trans_expiry` date DEFAULT NULL;');
  }

  // Cleanup old expiry_date column if it exists from earlier version
  if ($CI->db->field_exists('expiry_date', db_prefix() . 'ccx_msgs_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_allocations` DROP COLUMN `expiry_date`;');
  }
}

if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_pricing')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_pricing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_type` varchar(20) NOT NULL COMMENT 'sms, whatsapp, email, aicall',
  `billing_cycle` varchar(20) NOT NULL DEFAULT 'monthly' COMMENT 'monthly, quarterly, yearly',
  `plan_name` varchar(100) NOT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `message_count` int(11) NOT NULL DEFAULT '0',
  `expiry_days` int(11) NOT NULL DEFAULT '0' COMMENT 'Validity in days',
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `tax_id` int(11) DEFAULT '0' COMMENT 'FK to tbltaxes.id (0 = no tax)',
  `tax_percent` decimal(5,2) DEFAULT '0.00' COMMENT 'Snapshot of tax rate at plan creation',
  `offer_description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
} else {
  if (!$CI->db->field_exists('billing_cycle', db_prefix() . 'ccx_msgs_pricing')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_pricing` ADD `billing_cycle` varchar(20) NOT NULL DEFAULT "monthly" AFTER `message_type`;');
  }
  // Migration: add tax columns
  if (!$CI->db->field_exists('tax_id', db_prefix() . 'ccx_msgs_pricing')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_pricing` ADD `tax_id` int(11) DEFAULT 0 COMMENT "FK to tbltaxes.id" AFTER `discount_percent`;');
  }
  if (!$CI->db->field_exists('tax_percent', db_prefix() . 'ccx_msgs_pricing')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_pricing` ADD `tax_percent` decimal(5,2) DEFAULT 0.00 COMMENT "Snapshot of tax rate" AFTER `tax_id`;');
  }
  // Migration: add currency_id column (0 = use base currency)
  if (!$CI->db->field_exists('currency_id', db_prefix() . 'ccx_msgs_pricing')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_pricing` ADD `currency_id` int(11) DEFAULT 0 COMMENT "FK to tblcurrencies.id (0 = base currency)" AFTER `tax_percent`;');
  }
  // Migration: add message_subtype column (promotional/transactional)
  if (!$CI->db->field_exists('message_subtype', db_prefix() . 'ccx_msgs_pricing')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_pricing` ADD `message_subtype` varchar(20) NOT NULL DEFAULT "promotional" COMMENT "promotional, transactional" AFTER `message_type`;');
  }
}

// --- CCX Msgs APIs table ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_apis')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_apis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_type` varchar(20) NOT NULL COMMENT 'sms, whatsapp, email, aicall',
  `message_subtype` varchar(20) NOT NULL DEFAULT 'promotional' COMMENT 'promotional, transactional',
  `api_name` varchar(150) NOT NULL,
  `api_url` text NOT NULL,
  `request_method` varchar(10) NOT NULL DEFAULT 'POST',
  `headers` text DEFAULT NULL COMMENT 'JSON key-value pairs',
  `body_template` text DEFAULT NULL COMMENT 'JSON body template',
  `auth_type` varchar(20) NOT NULL DEFAULT 'none' COMMENT 'none, bearer, api_key, basic',
  `auth_credentials` text DEFAULT NULL,
  `overall_url` text DEFAULT NULL COMMENT 'Full URL with query params for GET mode',
  `get_mode` varchar(20) NOT NULL DEFAULT 'overall_url' COMMENT 'overall_url or headers_body',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sample_content` text DEFAULT NULL COMMENT 'Sample message content for reference',
  `msg_template_id` varchar(100) DEFAULT NULL COMMENT 'DLT / provider template ID',
  `header_id` varchar(100) DEFAULT NULL COMMENT 'DLT header / sender ID',
  `source_provider` varchar(150) DEFAULT NULL COMMENT 'Source provider name',
  `entity_id` varchar(100) DEFAULT NULL COMMENT 'DLT entity ID',
  `api_provider` varchar(150) DEFAULT NULL COMMENT 'API provider name e.g. Fast2SMS',
  `api_scope` varchar(10) NOT NULL DEFAULT 'global' COMMENT 'global or client',
  `client_id` int(11) DEFAULT NULL COMMENT 'Client ID when api_scope=client',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `message_type` (`message_type`),
  KEY `message_subtype` (`message_subtype`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
} else {
  // Migration: add is_default column if missing
  if (!$CI->db->field_exists('is_default', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `is_default` tinyint(1) NOT NULL DEFAULT "0" AFTER `active`;');
  }
  if (!$CI->db->field_exists('overall_url', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `overall_url` text DEFAULT NULL AFTER `auth_credentials`;');
  }
  if (!$CI->db->field_exists('get_mode', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `get_mode` varchar(20) NOT NULL DEFAULT "overall_url" AFTER `overall_url`;');
  }
  if (!$CI->db->field_exists('sample_content', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `sample_content` text DEFAULT NULL AFTER `is_default`;');
  }
  if (!$CI->db->field_exists('msg_template_id', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `msg_template_id` varchar(100) DEFAULT NULL AFTER `sample_content`;');
  }
  if (!$CI->db->field_exists('header_id', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `header_id` varchar(100) DEFAULT NULL AFTER `msg_template_id`;');
  }
  if (!$CI->db->field_exists('source_provider', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `source_provider` varchar(150) DEFAULT NULL AFTER `header_id`;');
  }
  if (!$CI->db->field_exists('entity_id', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `entity_id` varchar(100) DEFAULT NULL AFTER `source_provider`;');
  }
  if (!$CI->db->field_exists('api_provider', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `api_provider` varchar(150) DEFAULT NULL AFTER `entity_id`;');
  }
  if (!$CI->db->field_exists('api_scope', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `api_scope` varchar(10) NOT NULL DEFAULT "global" AFTER `api_provider`;');
  }
  if (!$CI->db->field_exists('client_id', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `client_id` int(11) DEFAULT NULL AFTER `api_scope`;');
  }
  // SMTP columns for email type APIs
  if (!$CI->db->field_exists('use_crm_smtp', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `use_crm_smtp` tinyint(1) NOT NULL DEFAULT 0 COMMENT "1 = use Perfex CRM Setup>Settings>Email SMTP config instead of the fields below" AFTER `client_id`;');
  }
  if (!$CI->db->field_exists('smtp_host', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `smtp_host` varchar(255) DEFAULT NULL COMMENT "SMTP server host" AFTER `client_id`;');
  }
  if (!$CI->db->field_exists('smtp_port', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `smtp_port` int(11) DEFAULT 587 COMMENT "SMTP port" AFTER `smtp_host`;');
  }
  if (!$CI->db->field_exists('smtp_username', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `smtp_username` varchar(255) DEFAULT NULL COMMENT "SMTP login username" AFTER `smtp_port`;');
  }
  if (!$CI->db->field_exists('smtp_password', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `smtp_password` varchar(255) DEFAULT NULL COMMENT "SMTP login password" AFTER `smtp_username`;');
  }
  if (!$CI->db->field_exists('smtp_encryption', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `smtp_encryption` varchar(10) DEFAULT "tls" COMMENT "tls, ssl, or none" AFTER `smtp_password`;');
  }
  if (!$CI->db->field_exists('smtp_from_email', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `smtp_from_email` varchar(255) DEFAULT NULL COMMENT "From email address" AFTER `smtp_encryption`;');
  }
  // Email template columns
  if (!$CI->db->field_exists('email_to_tpl', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `email_to_tpl` varchar(500) DEFAULT NULL COMMENT "Email recipient template with tags e.g. {patient_email}" AFTER `smtp_from_email`;');
  }
  if (!$CI->db->field_exists('email_subject_tpl', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `email_subject_tpl` varchar(500) DEFAULT NULL COMMENT "Email subject template with tags" AFTER `email_to_tpl`;');
  }
  if (!$CI->db->field_exists('email_from_name_tpl', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `email_from_name_tpl` varchar(255) DEFAULT NULL COMMENT "Email from name template" AFTER `email_subject_tpl`;');
  }
  if (!$CI->db->field_exists('email_body_tpl', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `email_body_tpl` text DEFAULT NULL COMMENT "Email body template with tags" AFTER `email_from_name_tpl`;');
  }
  // Perfex CRM email settings: charset, bcc, signature, header, footer
  if (!$CI->db->field_exists('smtp_email_charset', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `smtp_email_charset` varchar(20) DEFAULT NULL COMMENT "Email charset e.g. utf-8" AFTER `email_body_tpl`;');
  }
  if (!$CI->db->field_exists('bcc_emails', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `bcc_emails` varchar(500) DEFAULT NULL COMMENT "BCC email addresses" AFTER `smtp_email_charset`;');
  }
  if (!$CI->db->field_exists('email_signature', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `email_signature` text DEFAULT NULL COMMENT "Email signature" AFTER `bcc_emails`;');
  }
  if (!$CI->db->field_exists('email_header', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `email_header` text DEFAULT NULL COMMENT "Email header HTML" AFTER `email_signature`;');
  }
  if (!$CI->db->field_exists('email_footer', db_prefix() . 'ccx_msgs_apis')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_apis` ADD `email_footer` text DEFAULT NULL COMMENT "Email footer HTML" AFTER `email_header`;');
  }
}

// --- Migration: Add header and active columns per channel+subtype ---
$alloc_table = db_prefix() . 'ccx_msgs_allocations';
if ($CI->db->table_exists($alloc_table)) {
  $channel_subtypes = [
    'sms_promo',
    'sms_trans',
    'whatsapp_promo',
    'whatsapp_trans',
    'email_promo',
    'email_trans',
    'aicall_promo',
    'aicall_trans',
  ];
  foreach ($channel_subtypes as $cs) {
    // Header column
    if (!$CI->db->field_exists($cs . '_header', $alloc_table)) {
      $CI->db->query('ALTER TABLE `' . $alloc_table . '` ADD `' . $cs . '_header` varchar(255) DEFAULT NULL;');
    }
    // Active column (default 1 = active)
    if (!$CI->db->field_exists($cs . '_active', $alloc_table)) {
      $CI->db->query('ALTER TABLE `' . $alloc_table . '` ADD `' . $cs . '_active` tinyint(1) NOT NULL DEFAULT 1;');
    }
  }
}

// --- CCX Msgs API Logs table ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_api_logs')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_id` int(11) NOT NULL,
  `triggered_by` int(11) DEFAULT NULL COMMENT 'Staff ID',
  `tenant_name` varchar(255) DEFAULT NULL COMMENT 'Tenant/clinic name for SaaS',
  `request_url` text DEFAULT NULL,
  `request_payload` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'success, failed, timeout',
  `execution_time_ms` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `api_id` (`api_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
} else {
  // Migration: add tenant_name column if missing
  if (!$CI->db->field_exists('tenant_name', db_prefix() . 'ccx_msgs_api_logs')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_api_logs` ADD `tenant_name` varchar(255) DEFAULT NULL COMMENT "Tenant/clinic name for SaaS" AFTER `triggered_by`;');
  }
}

// --- CCX Msgs Recharge Logs table ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_recharge_logs')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_recharge_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'initiated, pending, paid, failed',
  `gateway_used` varchar(50) DEFAULT NULL COMMENT 'Payment gateway ID e.g. razorpay, stripe',
  `gateway_txn_id` varchar(150) DEFAULT NULL COMMENT 'Gateway transaction/payment ID',
  `invoice_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
} else {
  // Migration: add new tracking columns
  if (!$CI->db->field_exists('gateway_used', db_prefix() . 'ccx_msgs_recharge_logs')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_recharge_logs` ADD `gateway_used` varchar(50) DEFAULT NULL COMMENT "Payment gateway ID" AFTER `status`;');
  }
  if (!$CI->db->field_exists('gateway_txn_id', db_prefix() . 'ccx_msgs_recharge_logs')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_recharge_logs` ADD `gateway_txn_id` varchar(150) DEFAULT NULL COMMENT "Gateway transaction ID" AFTER `gateway_used`;');
  }
}

// --- CCX Msgs Checkout Sessions table ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_checkout_sessions')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_checkout_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_token` varchar(100) NOT NULL,
  `client_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, completed, expired',
  `invoice_id` int(11) DEFAULT NULL,
  `gateway_txn_id` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
// Migration: add cart_items column for multi-plan cart support
if ($CI->db->table_exists(db_prefix() . 'ccx_msgs_checkout_sessions')) {
  if (!$CI->db->field_exists('cart_items', db_prefix() . 'ccx_msgs_checkout_sessions')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_checkout_sessions` ADD `cart_items` TEXT DEFAULT NULL COMMENT "JSON array of plan IDs for cart checkout" AFTER `plan_id`;');
  }
  if (!$CI->db->field_exists('promo_id', db_prefix() . 'ccx_msgs_checkout_sessions')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_checkout_sessions` ADD `promo_id` int(11) DEFAULT NULL COMMENT "Applied promo code ID" AFTER `cart_items`;');
  }
  if (!$CI->db->field_exists('promo_discount', db_prefix() . 'ccx_msgs_checkout_sessions')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_checkout_sessions` ADD `promo_discount` decimal(15,2) DEFAULT 0.00 COMMENT "Promo discount amount" AFTER `promo_id`;');
  }
  if (!$CI->db->field_exists('return_url', db_prefix() . 'ccx_msgs_checkout_sessions')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_msgs_checkout_sessions` ADD `return_url` VARCHAR(500) DEFAULT "" COMMENT "Tenant return URL after payment" AFTER `gateway_txn_id`;');
  }
}

// --- CCX Msgs Promo Codes table ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_promo_codes')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_promo_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `code_type` varchar(20) NOT NULL DEFAULT 'promo' COMMENT 'promo or referral',
  `discount_type` varchar(20) NOT NULL DEFAULT 'percentage' COMMENT 'percentage or fixed',
  `discount_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `min_order_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `max_discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '0 = no cap',
  `usage_limit` int(11) NOT NULL DEFAULT '0' COMMENT '0 = unlimited',
  `usage_count` int(11) NOT NULL DEFAULT '0',
  `per_client_limit` int(11) NOT NULL DEFAULT '0' COMMENT '0 = unlimited',
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `applicable_channels` text DEFAULT NULL COMMENT 'JSON array',
  `referrer_client_id` int(11) DEFAULT NULL,
  `referrer_reward_credits` int(11) NOT NULL DEFAULT '0',
  `referrer_reward_channel` varchar(20) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// --- CCX Msgs Promo Usage table ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_promo_usage')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_promo_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `discount_applied` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `promo_id` (`promo_id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Migration: add referrer_type and referrer_staff_id to promo_codes for staff referral support
if ($CI->db->table_exists(db_prefix() . 'ccx_msgs_promo_codes')) {
  if (!$CI->db->field_exists('referrer_type', db_prefix() . 'ccx_msgs_promo_codes')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ccx_msgs_promo_codes` ADD `referrer_type` varchar(10) DEFAULT 'client' COMMENT 'client or staff' AFTER `applicable_channels`;");
  }
  if (!$CI->db->field_exists('referrer_staff_id', db_prefix() . 'ccx_msgs_promo_codes')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ccx_msgs_promo_codes` ADD `referrer_staff_id` int(11) DEFAULT NULL AFTER `referrer_client_id`;");
  }
}

// Migration: add referrer tracking columns to promo_usage for compensation records
if ($CI->db->table_exists(db_prefix() . 'ccx_msgs_promo_usage')) {
  if (!$CI->db->field_exists('referrer_type', db_prefix() . 'ccx_msgs_promo_usage')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ccx_msgs_promo_usage` ADD `referrer_type` varchar(10) DEFAULT NULL COMMENT 'client or staff' AFTER `discount_applied`;");
  }
  if (!$CI->db->field_exists('referrer_id', db_prefix() . 'ccx_msgs_promo_usage')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ccx_msgs_promo_usage` ADD `referrer_id` int(11) DEFAULT NULL COMMENT 'client_id or staff_id of referrer' AFTER `referrer_type`;");
  }
  if (!$CI->db->field_exists('referrer_name', db_prefix() . 'ccx_msgs_promo_usage')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ccx_msgs_promo_usage` ADD `referrer_name` varchar(200) DEFAULT NULL AFTER `referrer_id`;");
  }
  if (!$CI->db->field_exists('reward_status', db_prefix() . 'ccx_msgs_promo_usage')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ccx_msgs_promo_usage` ADD `reward_status` varchar(20) DEFAULT 'pending' COMMENT 'pending or compensated' AFTER `referrer_name`;");
  }
}

// --- CCX Msgs Coupons table (Free Credits) ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_coupons')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL COMMENT 'Shown to tenant on claim',
  `credits` text NOT NULL COMMENT 'JSON: {\"sms\":100,\"whatsapp\":200,...}',
  `expiry_days` int(11) NOT NULL DEFAULT '0' COMMENT '0 = no expiry on awarded credits',
  `usage_limit` int(11) NOT NULL DEFAULT '0' COMMENT '0 = unlimited',
  `usage_count` int(11) NOT NULL DEFAULT '0',
  `per_client_limit` int(11) NOT NULL DEFAULT '1' COMMENT '0 = unlimited per tenant',
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text DEFAULT NULL COMMENT 'Internal admin notes',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// --- CCX Msgs Coupon Claims table ---
if (!$CI->db->table_exists(db_prefix() . 'ccx_msgs_coupon_claims')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "ccx_msgs_coupon_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `credits_awarded` text NOT NULL COMMENT 'JSON snapshot of credits given',
  `claimed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `coupon_id` (`coupon_id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
