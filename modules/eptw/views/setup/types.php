<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrf = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'types'; include __DIR__ . '/_setup_nav.php'; ?>

            <div class="eptw-card">
                <div class="eptw-card-head"><h3><i class="fa-solid fa-file-shield"></i> Permit types — the digitised V3 templates</h3>
                    <div class="eptw-card-actions"><a href="<?= admin_url('eptw/eptw_setup/type'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-primary"><i class="fa fa-plus"></i> New permit type</a></div></div>
                <div class="eptw-table-scroll"><table class="eptw-table">
                    <thead><tr><th>Type</th><th>Flags</th><th class="eptw-num">Hazards</th><th class="eptw-num">Controls</th><th class="eptw-num">Fields</th><th>Approvals</th><th class="eptw-num">Validity</th><th class="eptw-num">Permits</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($types as $t) { $nc = 0; foreach ($t->controls as $s) { $nc += count($s['items'] ?? []); } ?>
                        <tr>
                            <td><span class="eptw-type-chip"><span class="eptw-type-dot" style="background:<?= html_escape($t->color); ?>"><i class="<?= html_escape($t->icon); ?>"></i></span> <?= html_escape($t->name); ?></span> <span class="eptw-badge muted eptw-mono"><?= html_escape($t->code); ?></span> <?= $t->active ? '' : '<span class="eptw-badge muted">inactive</span>'; ?>
                                <div class="eptw-small eptw-muted"><?= html_escape($t->description); ?></div></td>
                            <td><?= $t->high_risk ? '<span class="eptw-badge bad">High risk</span> ' : ''; ?><?= $t->gas_test_required ? '<span class="eptw-badge warn">Gas test</span> ' : ''; ?><?= $t->isolation_required ? '<span class="eptw-badge info">Isolation</span>' : ''; ?></td>
                            <td class="eptw-num"><?= count($t->hazards); ?></td>
                            <td class="eptw-num"><?= $nc; ?></td>
                            <td class="eptw-num"><?= count($t->extra_fields); ?></td>
                            <td class="eptw-small"><?= html_escape(implode(' → ', array_map(function ($s) { return ['area_authority' => 'AA', 'hse' => 'HSE', 'manager' => 'Mgr', 'coordinator' => 'Coord'][$s] ?? $s; }, $t->approvals))); ?></td>
                            <td class="eptw-num"><?= (int) $t->default_validity_hours; ?> h</td>
                            <td class="eptw-num"><?= (int) ($counts[$t->id] ?? 0); ?></td>
                            <td class="eptw-actions">
                                <a href="<?= admin_url('eptw/eptw_setup/type/' . $t->id); ?>" class="eptw-btn eptw-btn-sm"><i class="fa fa-pen"></i> Edit</a>
                                <form method="post" action="<?= admin_url('eptw/eptw_setup/type_reset/' . $t->id); ?>" style="display:inline" onsubmit="return confirm('Restore hazards, controls and fields from the shipped V3 template? Your edits to this type are replaced.')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost" title="Restore V3 template"><i class="fa fa-rotate-left"></i></button></form>
                                <?php if (!($counts[$t->id] ?? 0)) { ?><form method="post" action="<?= admin_url('eptw/eptw_setup/type_delete/' . $t->id); ?>" style="display:inline" onsubmit="return confirm('Delete this permit type?')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-trash"></i></button></form><?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody></table></div>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
