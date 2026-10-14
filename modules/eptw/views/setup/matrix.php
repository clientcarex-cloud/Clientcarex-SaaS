<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$steps   = ['area_authority' => 'Area Authority', 'hse' => 'HSE Officer', 'manager' => 'Manager'];
$can_edit = eptw_perm('eptw_setup_types', 'edit');
?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'matrix'; include __DIR__ . '/_setup_nav.php'; ?>

            <?php if (!count($projects)) { ?>
                <div class="eptw-note warn">Add a project first under <a href="<?= admin_url('eptw/eptw_setup/projects'); ?>">Projects &amp; areas</a>.</div>
            <?php } else { ?>
            <?= form_open(admin_url('eptw/eptw_setup/matrix')); ?>
            <div class="eptw-split">
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head">
                            <h3><i class="fa-solid fa-table-cells"></i> Approval matrix</h3>
                            <div class="eptw-card-actions">
                                <select name="project_id" class="eptw-select" onchange="window.location='<?= admin_url('eptw/eptw_setup/matrix?project='); ?>' + this.value">
                                    <?php foreach ($projects as $pr) { ?><option value="<?= $pr->id; ?>" <?= (int) $project_id === (int) $pr->id ? 'selected' : ''; ?>><?= html_escape($pr->name); ?></option><?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="eptw-table-scroll">
                            <table class="eptw-table">
                                <thead><tr><th>Permit type</th><th>Steps for this project</th><?php foreach ($steps as $l) { ?><th style="text-align:center"><?= $l; ?></th><?php } ?><th style="text-align:center">Permit issuer</th></tr></thead>
                                <tbody>
                                <?php foreach ($types as $t) {
                                    $custom = isset($matrix[(int) $t->id]);
                                    $cur    = $custom ? $matrix[(int) $t->id] : $t->approvals;
                                ?>
                                    <tr data-eptw-matrix-row>
                                        <td><span class="eptw-type-chip"><span class="eptw-type-dot" style="background:<?= html_escape($t->color); ?>"><i class="<?= html_escape($t->icon); ?>"></i></span> <?= html_escape($t->name); ?></span></td>
                                        <td class="eptw-small">
                                            <label class="eptw-check" style="margin:0"><input type="checkbox" name="custom[<?= $t->id; ?>]" value="1" <?= $custom ? 'checked' : ''; ?> data-eptw-matrix-custom> <span>Own steps on this project</span></label>
                                            <div class="eptw-muted" data-eptw-matrix-default>Type default: <?= html_escape(implode(' → ', array_map(function ($s) use ($steps) { return $steps[$s] ?? 'Issuer'; }, $t->approvals))); ?></div>
                                        </td>
                                        <?php foreach ($steps as $k => $l) { ?>
                                            <td style="text-align:center"><input type="checkbox" name="steps[<?= $t->id; ?>][]" value="<?= $k; ?>" <?= in_array($k, $cur, true) ? 'checked' : ''; ?> data-eptw-matrix-step></td>
                                        <?php } ?>
                                        <td style="text-align:center"><i class="fa-solid fa-check eptw-muted" title="Always — signs by issuing the number"></i></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($can_edit) { ?><div class="eptw-card-body"><button type="submit" class="eptw-btn eptw-btn-primary"><i class="fa fa-check"></i> Save matrix for this project</button></div><?php } ?>
                    </div>
                </div>
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> How it works</h3></div>
                        <div class="eptw-card-body eptw-small">
                            <p>Each permit type has default review steps (Setup → Permit types). Here a project can use its own steps for any type — e.g. Manager approval for Lifting on one project only.</p>
                            <p>The permit issuer (PTW Coordinator) always signs last, by issuing the permit number.</p>
                            <p><b>Risk-based escalation:</b> <?= eptw_opt('eptw_escalate_high_risk') === '1' ? 'on — every high-risk permit also needs the Manager.' : 'off.'; ?> <a href="<?= admin_url('eptw/eptw_setup'); ?>">Change in General</a>.</p>
                            <p class="eptw-muted" style="margin:0">Changes apply to permits submitted from now on; permits already in review keep their steps.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?= form_close(); ?>
            <?php } ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
document.querySelectorAll('[data-eptw-matrix-row]').forEach(function (row) {
    var custom = row.querySelector('[data-eptw-matrix-custom]');
    var sync = function () {
        row.querySelectorAll('[data-eptw-matrix-step]').forEach(function (c) { c.disabled = !custom.checked; });
        row.querySelector('[data-eptw-matrix-default]').style.opacity = custom.checked ? '.45' : '1';
    };
    custom.addEventListener('change', sync);
    sync();
});
</script>
</body>
</html>
