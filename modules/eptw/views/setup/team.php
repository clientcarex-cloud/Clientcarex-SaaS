<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrf = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'team'; include __DIR__ . '/_setup_nav.php'; ?>

            <div class="eptw-split">
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-users-gear"></i> ePTW team</h3>
                            <div class="eptw-card-actions"><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-member"><i class="fa fa-plus"></i> Add member</button></div></div>
                        <?php if (!count($team)) { ?>
                            <div class="eptw-empty"><i class="fa-solid fa-users"></i><h4>Only administrators can use ePTW so far</h4><p>Add engineers, HSE officers, area authorities, the PTW coordinator and managers. Each gets exactly what their role allows.</p></div>
                        <?php } else { ?>
                            <div class="eptw-table-scroll"><table class="eptw-table">
                                <thead><tr><th>Staff</th><th>Role</th><th>Projects</th><th>Phone</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($team as $m) { $pids = json_decode((string) $m->project_ids, true) ?: []; $names = []; foreach ($projects as $pr) { if (in_array((int) $pr->id, array_map('intval', $pids), true)) { $names[] = $pr->name; } } ?>
                                    <tr>
                                        <td><span class="eptw-avatar"><?= html_escape(eptw_initials($m->firstname . ' ' . $m->lastname)); ?></span> <span class="eptw-strong"><?= html_escape($m->firstname . ' ' . $m->lastname); ?></span> <?= $m->active && $m->staff_active ? '' : '<span class="eptw-badge muted">inactive</span>'; ?><div class="eptw-small eptw-muted"><?= html_escape($m->email); ?></div></td>
                                        <td><span class="eptw-badge info"><i class="<?= eptw_roles()[$m->role]['icon'] ?? ''; ?>"></i> <?= html_escape(eptw_role_label($m->role)); ?></span></td>
                                        <td class="eptw-small"><?= count($names) ? html_escape(implode(', ', $names)) : '<span class="eptw-muted">all projects</span>'; ?></td>
                                        <td class="eptw-small"><?= html_escape($m->phone); ?></td>
                                        <td class="eptw-actions">
                                            <button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-member" data-fill-staff_id="<?= $m->staff_id; ?>" data-fill-role="<?= $m->role; ?>" data-fill-phone="<?= html_escape($m->phone); ?>" data-fill-active="<?= (int) $m->active; ?>" data-projects="<?= html_escape(json_encode(array_map('intval', $pids))); ?>"><i class="fa fa-pen"></i></button>
                                            <form method="post" action="<?= admin_url('eptw/eptw_setup/team_delete/' . $m->id); ?>" style="display:inline" onsubmit="return confirm('Remove from the ePTW team?')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-trash"></i></button></form>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody></table></div>
                        <?php } ?>
                    </div>
                </div>
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> What each role can do</h3></div>
                        <div class="eptw-card-body eptw-small">
                            <dl class="eptw-dl" style="grid-template-columns:1fr">
                                <div><dt>Engineer / Performing Authority</dt><dd>Create drafts, request permit numbers, see their own permits, request extensions, upload attachments, record gas tests and toolbox talks.</dd></div>
                                <div><dt>HSE Officer</dt><dd>Review and sign permits, add safety remarks, suspend unsafe work, see high-risk permits and every permit in their projects.</dd></div>
                                <div><dt>Area Authority</dt><dd>Review and sign for area safety, start work, suspend, revalidate shifts.</dd></div>
                                <div><dt>PTW Coordinator</dt><dd>Issue permit numbers, record paper approvals, run the register, extend, hold, resume, close, archive, import the Excel register.</dd></div>
                                <div><dt>Manager</dt><dd>Dashboard, register and reports for their projects. Signs only where a template asks for manager approval.</dd></div>
                                <div><dt>ePTW Administrator</dt><dd>Everything, plus this setup area. CRM administrators are ePTW administrators automatically.</dd></div>
                            </dl>
                            <p class="eptw-muted" style="margin:12px 0 0">Leave "Projects" empty to cover every project.</p>
                        </div>
                    </div>
                </div>
            </div>

            <script type="text/template" id="m-member">
                <div class="eptw-modal"><form method="post" action="<?= admin_url('eptw/eptw_setup/team_save'); ?>"><?= $csrf; ?>
                    <div class="eptw-modal-head"><h3>Team member</h3><button type="button" class="x" data-eptw-close>&times;</button></div>
                    <div class="eptw-modal-body">
                        <div class="eptw-field"><label class="eptw-label">Staff member <span class="req">*</span></label><select name="staff_id" class="eptw-select" required><option value="">— choose —</option><?php foreach ($staff as $sid => $name) { ?><option value="<?= $sid; ?>"><?= html_escape($name); ?></option><?php } ?></select></div>
                        <div class="eptw-field"><label class="eptw-label">Role <span class="req">*</span></label><select name="role" class="eptw-select"><?php foreach (eptw_roles() as $k => $r) { ?><option value="<?= $k; ?>"><?= html_escape($r['label']); ?></option><?php } ?></select></div>
                        <div class="eptw-field"><label class="eptw-label">Projects (empty = all)</label>
                            <div class="eptw-hazard-grid"><?php foreach ($projects as $pr) { ?><label class="eptw-check" style="margin:0;padding:6px 10px;border:1px solid var(--e-line);border-radius:10px"><input type="checkbox" name="project_ids[]" value="<?= $pr->id; ?>"> <span><?= html_escape($pr->name); ?></span></label><?php } ?></div></div>
                        <div class="eptw-field"><label class="eptw-label">Phone</label><input name="phone" class="eptw-input"></div>
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
