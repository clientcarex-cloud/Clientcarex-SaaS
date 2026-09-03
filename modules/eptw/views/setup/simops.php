<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$csrf  = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash());
$names = [];
foreach ($types as $t) { $names[$t->code] = $t->name; }
?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'simops'; include __DIR__ . '/_setup_nav.php'; ?>

            <div class="eptw-split">
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-diagram-project"></i> SIMOPS conflict rules</h3>
                            <div class="eptw-card-actions"><?= eptw_opt('eptw_simops_enabled') === '1' ? '<span class="eptw-badge ok">Detection on</span>' : '<span class="eptw-badge bad">Detection off</span>'; ?> <button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-rule"><i class="fa fa-plus"></i> Add rule</button></div></div>
                        <div class="eptw-table-scroll"><table class="eptw-table">
                            <thead><tr><th>Permit type A</th><th>Permit type B</th><th>Severity</th><th>Description</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($rules as $r) { ?>
                                <tr>
                                    <td><span class="eptw-badge muted eptw-mono"><?= html_escape($r->type_a); ?></span> <?= html_escape($names[$r->type_a] ?? ''); ?></td>
                                    <td><span class="eptw-badge muted eptw-mono"><?= html_escape($r->type_b); ?></span> <?= html_escape($names[$r->type_b] ?? ''); ?></td>
                                    <td><span class="eptw-badge <?= $r->severity === 'block' ? 'bad' : 'warn'; ?>"><?= $r->severity === 'block' ? 'Block — on hold' : 'Warn — flag only'; ?></span> <?= $r->active ? '' : '<span class="eptw-badge muted">off</span>'; ?></td>
                                    <td class="eptw-small"><?= html_escape($r->description); ?></td>
                                    <td class="eptw-actions">
                                        <button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-rule" data-fill-id="<?= $r->id; ?>" data-fill-type_a="<?= $r->type_a; ?>" data-fill-type_b="<?= $r->type_b; ?>" data-fill-severity="<?= $r->severity; ?>" data-fill-description="<?= html_escape($r->description); ?>" data-fill-active="<?= (int) $r->active; ?>"><i class="fa fa-pen"></i></button>
                                        <form method="post" action="<?= admin_url('eptw/eptw_setup/rule_delete/' . $r->id); ?>" style="display:inline" onsubmit="return confirm('Delete this rule?')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-trash"></i></button></form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody></table></div>
                    </div>
                </div>
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> How detection works</h3></div>
                        <div class="eptw-card-body eptw-small">
                            <p>When a permit number is issued, ePTW looks for other live permits in the <b>same project and area</b> whose <b>time window overlaps</b>, and checks the pair of permit types against these rules.</p>
                            <p><b>Block</b> issues the number but puts the permit <i>On hold – SIMOPS conflict</i> until the coordinator records how it was resolved. <b>Warn</b> flags the permit and alerts the coordinator, HSE and managers, but work can proceed.</p>
                            <p class="eptw-muted" style="margin:0">The form also previews conflicts live while the engineer fills it in.</p>
                        </div>
                    </div>
                </div>
            </div>

            <script type="text/template" id="m-rule">
                <div class="eptw-modal"><form method="post" action="<?= admin_url('eptw/eptw_setup/rule_save'); ?>" onsubmit="this.action += (this.querySelector('[name=id]').value ? '/' + this.querySelector('[name=id]').value : '')"><?= $csrf; ?><input type="hidden" name="id" value="">
                    <div class="eptw-modal-head"><h3>SIMOPS rule</h3><button type="button" class="x" data-eptw-close>&times;</button></div>
                    <div class="eptw-modal-body">
                        <div class="eptw-grid-2">
                            <div class="eptw-field"><label class="eptw-label">Permit type A</label><select name="type_a" class="eptw-select"><?php foreach ($types as $t) { ?><option value="<?= $t->code; ?>"><?= html_escape($t->name); ?></option><?php } ?></select></div>
                            <div class="eptw-field"><label class="eptw-label">Permit type B</label><select name="type_b" class="eptw-select"><?php foreach ($types as $t) { ?><option value="<?= $t->code; ?>"><?= html_escape($t->name); ?></option><?php } ?></select></div>
                        </div>
                        <div class="eptw-field"><label class="eptw-label">Severity</label><select name="severity" class="eptw-select"><option value="warn">Warn — flag and notify</option><option value="block">Block — put on hold until resolved</option></select></div>
                        <div class="eptw-field"><label class="eptw-label">Description</label><input name="description" class="eptw-input" placeholder="Hot work near radiography"></div>
                        <label class="eptw-check"><input type="checkbox" name="active" value="1" checked> <span>Active</span></label>
                    </div>
                    <div class="eptw-modal-foot"><button type="button" class="eptw-btn" data-eptw-close>Cancel</button><button type="submit" class="eptw-btn eptw-btn-primary">Save</button></div>
                </form></div>
            </script>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-eptw-modal]');
    if (!t) { return; }
    setTimeout(function () {
        var cb = document.querySelector('.eptw-modal-back.open [name=active]');
        if (cb && cb.type === 'checkbox') { cb.checked = t.hasAttribute('data-fill-active') ? t.getAttribute('data-fill-active') === '1' : true; cb.value = '1'; }
    }, 60);
});
</script>
</body>
</html>
