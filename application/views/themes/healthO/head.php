<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="<?= e($locale); ?>">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php if (get_option('ccx_wpa_enabled') == '1') { ?>
	<meta name="theme-color" content="<?= e(get_option('ccx_wpa_theme_color') ?: '#1B74E4'); ?>" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<link rel="manifest" href="<?= site_url('pwa/manifest'); ?>" />
	<link rel="apple-touch-icon" href="<?= base_url('assets/images/pwa/icon-192x192.png'); ?>" />
	<?php } ?>
	<title><?= $title ?? ''; ?></title>
	<?= compile_theme_css(); ?>
	<script
		src="<?= base_url('assets/plugins/jquery/jquery.min.js'); ?>">
	</script>
	<?php app_customers_head(); ?>
</head>

<body
	class="customers <?= strtolower($this->agent->browser()); ?><?= is_mobile() ? ' mobile' : ''; ?><?= isset($bodyclass) ? ' ' . $bodyclass : ''; ?>"
	<?= $isRTL == 'true' ? 'dir="rtl"' : ''; ?>>

	<?php hooks()->do_action('customers_after_body_start'); ?>