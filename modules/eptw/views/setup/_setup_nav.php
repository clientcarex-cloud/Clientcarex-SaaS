<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Setup sub-navigation. Expects $setup_active. */
$setup_tabs = [];
if (eptw_can('setup')) {
    $setup_tabs = [
        'settings'    => ['eptw/eptw_setup',             'fa-solid fa-sliders',          'General & numbering'],
        'projects'    => ['eptw/eptw_setup/projects',    'fa-solid fa-diagram-project',  'Projects & areas'],
        'contractors' => ['eptw/eptw_setup/contractors', 'fa-solid fa-helmet-safety',    'Contractors'],
        'types'       => ['eptw/eptw_setup/types',       'fa-solid fa-file-shield',      'Permit types'],
        'team'        => ['eptw/eptw_setup/team',        'fa-solid fa-users-gear',       'Team & roles'],
        'simops'      => ['eptw/eptw_setup/simops',      'fa-solid fa-diagram-project',  'SIMOPS rules'],
    ];
}
$setup_tabs['import'] = ['eptw/eptw_setup/import', 'fa-solid fa-file-import', 'Import Excel register'];
?>
<div class="eptw-views" style="margin-bottom:16px">
    <?php // Prefixed names on purpose: this partial is include()d into views that own $t / $key.
    foreach ($setup_tabs as $eptw_skey => $eptw_stab) { ?>
        <a href="<?= admin_url($eptw_stab[0]); ?>" class="<?= ($setup_active ?? '') === $eptw_skey ? 'active' : ''; ?>"><i class="<?= $eptw_stab[1]; ?>"></i> <?= $eptw_stab[2]; ?></a>
    <?php } ?>
</div>
