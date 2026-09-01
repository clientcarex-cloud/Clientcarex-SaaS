<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Shared module header + tab navigation.
 * Expects $active in: connections | connection | logs | settings
 */
$lsy_nav = [
    'connections' => ['url' => admin_url('lead_sync'),          'icon' => 'fa-solid fa-plug-circle-bolt', 'label' => _l('lead_sync_connections')],
    'logs'        => ['url' => admin_url('lead_sync/logs'),     'icon' => 'fa-solid fa-clock-rotate-left', 'label' => _l('lead_sync_history')],
    'settings'    => ['url' => admin_url('lead_sync/settings'), 'icon' => 'fa-solid fa-sliders',          'label' => _l('lead_sync_settings')],
];
?>
<div class="lsy-header">
    <h1 class="lsy-title"><i class="fa-solid fa-file-import"></i> <?= _l('lead_sync'); ?></h1>

    <div class="lsy-tabs">
        <?php foreach ($lsy_nav as $key => $tab) { ?>
            <a href="<?= $tab['url']; ?>" class="lsy-tab <?= ($active ?? '') === $key ? 'active' : ''; ?>">
                <i class="<?= $tab['icon']; ?>"></i> <?= $tab['label']; ?>
            </a>
        <?php } ?>
    </div>

    <div class="lsy-header-actions">
        <?php if (lead_sync_opt('lead_sync_enabled') !== '1') { ?>
            <span class="lsy-badge bad"><i class="fa fa-pause"></i> Syncing is off</span>
        <?php } ?>
        <?php if (lead_sync_can('create')) { ?>
            <a href="<?= admin_url('lead_sync/connection'); ?>" class="lsy-btn lsy-btn-primary">
                <i class="fa fa-plus"></i> <?= _l('lead_sync_new_connection'); ?>
            </a>
        <?php } ?>
    </div>
</div>
