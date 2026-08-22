<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Shared module header + tab navigation.
 * Expects $active in: dashboard | jobs | job | applications | pipeline |
 *                     interviews | setup | settings
 */
$crs_nav = [
    'dashboard'    => ['url' => admin_url('careers'),              'icon' => 'fa-solid fa-gauge-high',   'label' => _l('careers_dashboard')],
    'jobs'         => ['url' => admin_url('careers/jobs'),         'icon' => 'fa-solid fa-briefcase',    'label' => _l('careers_openings')],
    'applications' => ['url' => admin_url('careers/applications'), 'icon' => 'fa-solid fa-inbox',        'label' => _l('careers_applications')],
    'pipeline'     => ['url' => admin_url('careers/pipeline'),     'icon' => 'fa-solid fa-diagram-project', 'label' => _l('careers_pipeline')],
    'interviews'   => ['url' => admin_url('careers/interviews'),   'icon' => 'fa-regular fa-calendar-check', 'label' => _l('careers_interviews')],
];

// Live badge: the number of untouched applications, refreshed by careers.js.
$crs_new_count = (int) get_instance()->db->where('stage', 'new')->count_all_results(db_prefix() . 'careers_applications');
?>
<div class="crs-header">
    <h1 class="crs-title"><i class="fa-solid fa-user-tie"></i> <?= _l('careers'); ?></h1>

    <div class="crs-tabs">
        <?php foreach ($crs_nav as $key => $tab) { ?>
            <a href="<?= $tab['url']; ?>" class="crs-tab <?= ($active ?? '') === $key ? 'active' : ''; ?>">
                <i class="<?= $tab['icon']; ?>"></i> <?= $tab['label']; ?>
                <?php if ($key === 'applications' && $crs_new_count > 0) { ?>
                    <span class="crs-tab-count" data-crs-new-badge><?= $crs_new_count; ?></span>
                <?php } ?>
            </a>
        <?php } ?>
    </div>

    <div class="crs-header-actions">
        <?php if (careers_can('create')) { ?>
            <a href="<?= admin_url('careers/job'); ?>" class="crs-btn crs-btn-primary">
                <i class="fa fa-plus"></i> <?= _l('careers_new_job'); ?>
            </a>
        <?php } ?>
        <?php if (careers_can_settings()) { ?>
            <a href="<?= admin_url('careers/setup'); ?>" class="crs-btn crs-btn-icon <?= ($active ?? '') === 'setup' ? 'active' : ''; ?>"
               title="<?= _l('careers_setup'); ?>"><i class="fa-solid fa-sitemap"></i></a>
            <a href="<?= admin_url('careers/settings'); ?>" class="crs-btn crs-btn-icon <?= ($active ?? '') === 'settings' ? 'active' : ''; ?>"
               title="<?= _l('careers_settings'); ?>"><i class="fa-solid fa-gear"></i></a>
        <?php } ?>
    </div>
</div>
