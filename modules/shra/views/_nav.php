<?php defined('BASEPATH') or exit('No direct script access allowed');
$shra_active = isset($shra_active) ? $shra_active : '';
$shra_tabs   = [];
if (shra_can('view')) {
    $shra_tabs[] = ['dashboard', 'shra', 'fa-solid fa-gauge-high', _l('shra_dashboard')];
    $shra_tabs[] = ['riders', 'shra/riders', 'fa-solid fa-users', _l('shra_riders')];
}
if (shra_can_billing()) {
    $shra_tabs[] = ['billing', 'shra/billing', 'fa-solid fa-cash-register', _l('shra_billing')];
}
if (shra_can_attendance()) {
    $shra_tabs[] = ['attendance', 'shra/attendance', 'fa-solid fa-clipboard-check', _l('shra_attendance')];
}
if (shra_can('view')) {
    $shra_tabs[] = ['enrollments', 'shra/enrollments', 'fa-solid fa-ticket', _l('shra_enrollments')];
}
if (is_admin()) {
    $shra_tabs[] = ['packages', 'shra/packages', 'fa-solid fa-tags', _l('shra_packages')];
    $shra_tabs[] = ['trainers', 'shra/trainers', 'fa-solid fa-user-tie', 'Trainers'];
    $shra_tabs[] = ['settings', 'shra/settings', 'fa-solid fa-sliders', _l('shra_settings')];
}
?>
<div class="shra-head">
    <div class="shra-brand">
        <img src="<?php echo shra_logo_url(); ?>" alt="">
        <div>
            <h3><?php echo html_escape(get_option('shra_academy_name') ?: 'Stallion Horse Riding Academy'); ?></h3>
            <small><?php echo html_escape(get_option('shra_tagline')); ?></small>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if (shra_can('view')) { ?>
            <a href="<?php echo admin_url('shra/qr'); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa-solid fa-qrcode"></i> Registration QR</a>
        <?php } ?>
        <?php if (shra_can('create')) { ?>
            <a href="<?php echo admin_url('shra/rider_form'); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-user-plus"></i> New rider</a>
        <?php } ?>
        <?php if (shra_can_billing()) { ?>
            <a href="<?php echo admin_url('shra/billing'); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-cash-register"></i> New bill</a>
        <?php } ?>
    </div>
</div>
<nav class="shra-nav">
    <?php foreach ($shra_tabs as $t) { ?>
        <a href="<?php echo admin_url($t[1]); ?>" class="<?php echo $shra_active === $t[0] ? 'active' : ''; ?>"><i class="<?php echo $t[2]; ?>"></i> <?php echo $t[3]; ?></a>
    <?php } ?>
</nav>
