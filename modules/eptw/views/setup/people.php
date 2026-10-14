<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrf = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'people'; include __DIR__ . '/_setup_nav.php'; ?>

            <div class="eptw-split">
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-id-badge"></i> People — site personnel</h3>
                            <div class="eptw-card-actions"><?php if (eptw_perm('eptw_setup_people', 'create')) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-person"><i class="fa fa-plus"></i> Add person</button><?php } ?></div></div>
                        <?php if (!count($people)) { ?>
                            <div class="eptw-empty"><i class="fa-solid fa-id-badge"></i><h4>No people listed yet</h4><p>Add permit holders, supervisors, fire watches, isolation authorities and crew — they become selectable on permits and toolbox talks without needing a login.</p></div>
                        <?php } else { ?>
                            <div class="eptw-table-scroll"><table class="eptw-table">
                                <thead><tr><th>Name</th><th>Position</th><th>Company</th><th>Badge / ID</th><th>Phone</th><th>Projects</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($people as $pe) {
                                    $pids  = json_decode((string) $pe->project_ids, true) ?: [];
                                    $names = [];
                                    foreach ($projects as $pr) { if (in_array((int) $pr->id, array_map('intval', $pids), true)) { $names[] = $pr->name; } }
                                ?>
                                    <tr>
                                        <td><span class="eptw-avatar"><?= html_escape(eptw_initials($pe->name)); ?></span> <span class="eptw-strong"><?= html_escape($pe->name); ?></span> <?= $pe->active ? '' : '<span class="eptw-badge muted">inactive</span>'; ?>
                                            <?php if ((int) $pe->staff_id) { ?><div class="eptw-small eptw-muted"><i class="fa-solid fa-user-check"></i> login: <?= html_escape(eptw_staff_name($pe->staff_id)); ?></div><?php } ?></td>
                                        <td class="eptw-small"><?= html_escape($pe->position); ?></td>
                                        <td class="eptw-small"><?= html_escape($pe->contractor_name ?: $pe->company); ?></td>
                                        <td class="eptw-small eptw-mono"><?= html_escape($pe->badge_no); ?></td>
                                        <td class="eptw-small"><?= html_escape($pe->phone); ?></td>
                                        <td class="eptw-small"><?= count($names) ? html_escape(implode(', ', $names)) : '<span class="eptw-muted">all projects</span>'; ?></td>
                                        <td class="eptw-actions">
                                            <?php if (eptw_perm('eptw_setup_people', 'edit')) { ?><button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-person" data-fill-id="<?= $pe->id; ?>" data-fill-name="<?= html_escape($pe->name); ?>" data-fill-position="<?= html_escape($pe->position); ?>" data-fill-contractor_id="<?= (int) $pe->contractor_id ?: ''; ?>" data-fill-company="<?= html_escape($pe->company); ?>" data-fill-badge_no="<?= html_escape($pe->badge_no); ?>" data-fill-phone="<?= html_escape($pe->phone); ?>" data-fill-staff_id="<?= (int) $pe->staff_id ?: ''; ?>" data-fill-active="<?= (int) $pe->active; ?>" data-projects="<?= html_escape(json_encode(array_map('intval', $pids))); ?>"><i class="fa fa-pen"></i></button><?php } ?>
                                            <?php if (eptw_perm('eptw_setup_people', 'delete')) { ?><form method="post" action="<?= admin_url('eptw/eptw_setup/person_delete/' . $pe->id); ?>" style="display:inline" onsubmit="return confirm('Remove this person from the list? Permits already naming them keep the name.')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-trash"></i></button></form><?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody></table></div>
                        <?php } ?>
                    </div>
                </div>
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> People vs. team</h3></div>
                        <div class="eptw-card-body eptw-small">
                            <p><b>People</b> is the list of everyone who may be <i>named</i> on a permit — permit holder, supervisor, fire watch, standby man, isolation authority, toolbox attendees. They do not need a login.</p>
                            <p><b>Team &amp; roles</b> is who may <i>act</i> in the system — review, sign, issue, suspend, close. Approvals and permissions stay with those logged-in members only.</p>
                            <p class="eptw-muted" style="margin:0">Badge / ID is filled in automatically when a person is picked on a toolbox talk.</p>
                        </div>
                    </div>
                </div>
            </div>

            <script type="text/template" id="m-person">
                <div class="eptw-modal"><form method="post" action="<?= admin_url('eptw/eptw_setup/person_save'); ?>" onsubmit="this.action += (this.querySelector('[name=id]').value ? '/' + this.querySelector('[name=id]').value : '')"><?= $csrf; ?><input type="hidden" name="id" value="">
                    <div class="eptw-modal-head"><h3>Person</h3><button type="button" class="x" data-eptw-close>&times;</button></div>
                    <div class="eptw-modal-body">
                        <div class="eptw-grid-2">
                            <div class="eptw-field"><label class="eptw-label">Name <span class="req">*</span></label><input name="name" class="eptw-input" required></div>
                            <div class="eptw-field"><label class="eptw-label">Position / role on site</label><input name="position" class="eptw-input" placeholder="Supervisor, fire watch, rigger…"></div>
                            <div class="eptw-field"><label class="eptw-label">Contractor</label><select name="contractor_id" class="eptw-select"><option value="">— own staff / other —</option><?php foreach ($contractors as $c) { ?><option value="<?= $c->id; ?>"><?= html_escape($c->name); ?></option><?php } ?></select></div>
                            <div class="eptw-field"><label class="eptw-label">Company (if not a listed contractor)</label><input name="company" class="eptw-input"></div>
                            <div class="eptw-field"><label class="eptw-label">Badge / ID no.</label><input name="badge_no" class="eptw-input eptw-mono"></div>
                            <div class="eptw-field"><label class="eptw-label">Phone</label><input name="phone" class="eptw-input"></div>
                        </div>
                        <div class="eptw-field"><label class="eptw-label">Linked login (optional)</label><select name="staff_id" class="eptw-select"><option value="">— none —</option><?php foreach ($staff as $sid => $sname) { ?><option value="<?= $sid; ?>"><?= html_escape($sname); ?></option><?php } ?></select></div>
                        <div class="eptw-field"><label class="eptw-label">Projects (empty = all)</label>
                            <div class="eptw-hazard-grid"><?php foreach ($projects as $pr) { ?><label class="eptw-check" style="margin:0;padding:6px 10px;border:1px solid var(--e-line);border-radius:10px"><input type="checkbox" name="project_ids[]" value="<?= $pr->id; ?>"> <span><?= html_escape($pr->name); ?></span></label><?php } ?></div></div>
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
        var m = document.querySelector('.eptw-modal-back.open');
        if (!m) { return; }
        var cb = m.querySelector('[name=active]');
        if (cb && cb.type === 'checkbox') { cb.checked = t.hasAttribute('data-fill-active') ? t.getAttribute('data-fill-active') === '1' : true; cb.value = '1'; }
        var ids = [];
        try { ids = JSON.parse(t.getAttribute('data-projects') || '[]'); } catch (err) { ids = []; }
        m.querySelectorAll('[name="project_ids[]"]').forEach(function (c) { c.checked = ids.indexOf(parseInt(c.value, 10)) !== -1; });
    }, 60);
});
</script>
</body>
</html>
