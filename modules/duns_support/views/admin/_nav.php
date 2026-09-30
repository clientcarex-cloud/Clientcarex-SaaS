<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Module header + tabs. Expects $active: orders | plans | settings */
$duns_tabs = ['orders' => ['duns_support', 'fa-solid fa-list-check', 'Orders']];
if (duns_can('edit')) {
    $duns_tabs['plans'] = ['duns_support/plans', 'fa-solid fa-tags', 'Plans & pricing'];
}
if (is_admin()) {
    $duns_tabs['settings'] = ['duns_support/settings', 'fa-solid fa-sliders', 'Settings'];
}
?>
<div class="duns-head">
    <h1 class="duns-title"><i class="fa-solid fa-id-card"></i> DUNS Support</h1>
    <div class="duns-tabs">
        <?php foreach ($duns_tabs as $duns_key => $duns_tab) { ?>
            <a class="duns-tab<?php echo ($active ?? '') === $duns_key ? ' active' : ''; ?>" href="<?php echo admin_url($duns_tab[0]); ?>"><i class="<?php echo $duns_tab[1]; ?>"></i><?php echo $duns_tab[2]; ?></a>
        <?php } ?>
    </div>
    <div class="duns-actions">
        <a href="<?php echo duns_public_url(); ?>" target="_blank" class="btn btn-default"><i class="fa-solid fa-arrow-up-right-from-square"></i> Landing page</a>
    </div>
</div>
