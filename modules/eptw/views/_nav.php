<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Module header + primary tabs. Expects $active in:
 * dashboard | register | new | reports | setup
 */
$eptw_me   = eptw_me();
$eptw_tabs = [
    'dashboard' => ['url' => admin_url('eptw'),          'icon' => 'fa-solid fa-gauge-high',   'label' => 'Dashboard'],
    'register'  => ['url' => admin_url('eptw/register'), 'icon' => 'fa-solid fa-table-list',   'label' => 'Permit register'],
];
if (eptw_can('reports')) {
    $eptw_tabs['reports'] = ['url' => admin_url('eptw/reports'), 'icon' => 'fa-solid fa-chart-column', 'label' => 'Reports'];
}
if (eptw_can('setup') || eptw_can('import')) {
    $eptw_tabs['setup'] = ['url' => admin_url(eptw_can('setup') ? 'eptw/eptw_setup' : 'eptw/eptw_setup/import'), 'icon' => 'fa-solid fa-sliders', 'label' => 'Setup'];
}
?>
<div class="eptw-header">
    <div class="eptw-brand">
        <div class="eptw-brand-mark"><i class="fa-solid fa-file-shield"></i></div>
        <div>
            <h1>ePTW</h1>
            <small><?= html_escape(eptw_opt('eptw_company_name') ?: 'Electronic Permit to Work'); ?></small>
        </div>
    </div>

    <div class="eptw-tabs">
        <?php foreach ($eptw_tabs as $key => $tab) { ?>
            <a href="<?= $tab['url']; ?>" class="eptw-tab <?= ($active ?? '') === $key ? 'active' : ''; ?>">
                <i class="<?= $tab['icon']; ?>"></i> <?= $tab['label']; ?>
            </a>
        <?php } ?>
    </div>

    <div class="eptw-header-actions">
        <span class="eptw-role-chip" title="Your ePTW role"><?= html_escape(eptw_roles()[$eptw_me['role']]['short'] ?? $eptw_me['role']); ?></span>
        <?php if (eptw_can('create')) { ?>
            <a href="<?= admin_url('eptw/permit'); ?>" class="eptw-btn eptw-btn-primary">
                <i class="fa fa-plus"></i> New permit
            </a>
        <?php } ?>
    </div>
</div>
