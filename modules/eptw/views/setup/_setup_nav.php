<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Setup sub-navigation. Expects $setup_active. */
$setup_icons = [
    'eptw_setup_settings'    => 'fa-solid fa-sliders',
    'eptw_setup_projects'    => 'fa-solid fa-diagram-project',
    'eptw_setup_contractors' => 'fa-solid fa-helmet-safety',
    'eptw_setup_types'       => 'fa-solid fa-file-shield',
    'eptw_setup_team'        => 'fa-solid fa-users-gear',
    'eptw_setup_simops'      => 'fa-solid fa-diagram-project',
    'eptw_setup_import'      => 'fa-solid fa-file-import',
];
// One tab per setup menu the staff member holds "View" on (Staff → Permissions).
$setup_tabs = [];
foreach (eptw_setup_features() as $eptw_feature) {
    $eptw_menu = eptw_menu_permissions()[$eptw_feature];
    $setup_tabs[substr($eptw_feature, strlen('eptw_setup_'))] = [$eptw_menu['url'], $setup_icons[$eptw_feature], substr($eptw_menu['name'], strlen('Setup — '))];
}
?>
<div class="eptw-views" style="margin-bottom:16px">
    <?php // Prefixed names on purpose: this partial is include()d into views that own $t / $key.
    foreach ($setup_tabs as $eptw_skey => $eptw_stab) { ?>
        <a href="<?= admin_url($eptw_stab[0]); ?>" class="<?= ($setup_active ?? '') === $eptw_skey ? 'active' : ''; ?>"><i class="<?= $eptw_stab[1]; ?>"></i> <?= $eptw_stab[2]; ?></a>
    <?php } ?>
</div>
