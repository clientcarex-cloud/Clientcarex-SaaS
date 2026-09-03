<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrf = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'projects'; include __DIR__ . '/_setup_nav.php'; ?>

            <div class="eptw-split">
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-diagram-project"></i> Projects / packages</h3>
                            <div class="eptw-card-actions"><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-project"><i class="fa fa-plus"></i> Add project</button></div></div>
                        <div class="eptw-table-scroll"><table class="eptw-table">
                            <thead><tr><th>Code</th><th>Project</th><th>Client</th><th>Camera</th><th class="eptw-num">Permits</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($projects as $pr) { ?>
                                <tr>
                                    <td class="eptw-mono eptw-strong"><?= html_escape($pr->code); ?></td>
                                    <td><span class="eptw-strong"><?= html_escape($pr->name); ?></span> <?= $pr->active ? '' : '<span class="eptw-badge muted">inactive</span>'; ?><div class="eptw-small eptw-muted"><?= html_escape($pr->description); ?></div></td>
                                    <td class="eptw-small"><?= html_escape($pr->client_name); ?></td>
                                    <td class="eptw-small"><?= $pr->camera_mode === 'inherit' ? '<span class="eptw-muted">default</span>' : html_escape(ucfirst($pr->camera_mode)); ?></td>
                                    <td class="eptw-num"><?= (int) ($counts[$pr->id] ?? 0); ?></td>
                                    <td class="eptw-actions">
                                        <button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-project" data-fill-name="<?= html_escape($pr->name); ?>" data-fill-code="<?= html_escape($pr->code); ?>" data-fill-client_name="<?= html_escape($pr->client_name); ?>" data-fill-description="<?= html_escape($pr->description); ?>" data-fill-camera_mode="<?= $pr->camera_mode; ?>" data-fill-active="<?= (int) $pr->active; ?>" data-fill-id="<?= $pr->id; ?>"><i class="fa fa-pen"></i></button>
                                        <button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-area" data-fill-project_id="<?= $pr->id; ?>" title="Add area"><i class="fa fa-plus"></i> Area</button>
                                        <?php if (!($counts[$pr->id] ?? 0)) { ?><form method="post" action="<?= admin_url('eptw/eptw_setup/project_delete/' . $pr->id); ?>" style="display:inline" onsubmit="return confirm('Delete this project and its areas?')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-trash"></i></button></form><?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody></table></div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-map-location-dot"></i> Areas / zones</h3>
                            <div class="eptw-card-actions"><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-area"><i class="fa fa-plus"></i> Add area</button></div></div>
                        <div class="eptw-table-scroll"><table class="eptw-table">
                            <thead><tr><th>Project</th><th>Code</th><th>Area</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($areas as $a) { ?>
                                <tr>
                                    <td class="eptw-small"><?= $a->project_id ? html_escape($a->project_name) : '<span class="eptw-badge info">shared — all projects</span>'; ?></td>
                                    <td class="eptw-mono eptw-strong"><?= html_escape($a->code); ?></td>
                                    <td><span class="eptw-strong"><?= html_escape($a->name); ?></span> <?= $a->active ? '' : '<span class="eptw-badge muted">inactive</span>'; ?><div class="eptw-small eptw-muted"><?= html_escape($a->description); ?></div></td>
                                    <td class="eptw-actions">
                                        <button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-area" data-fill-id="<?= $a->id; ?>" data-fill-project_id="<?= $a->project_id; ?>" data-fill-code="<?= html_escape($a->code); ?>" data-fill-name="<?= html_escape($a->name); ?>" data-fill-description="<?= html_escape($a->description); ?>" data-fill-active="<?= (int) $a->active; ?>"><i class="fa fa-pen"></i></button>
                                        <form method="post" action="<?= admin_url('eptw/eptw_setup/area_delete/' . $a->id); ?>" style="display:inline" onsubmit="return confirm('Delete this area?')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-trash"></i></button></form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody></table></div>
                    </div>
                </div>
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> About codes</h3></div>
                        <div class="eptw-card-body eptw-small">
                            <p>Project and area codes appear in the permit number (<span class="eptw-mono"><?= html_escape(eptw_opt('eptw_number_format')); ?></span>), so keep them short and stable — <b>ALPHA</b>, <b>Z2</b>, <b>A01</b>.</p>
                            <p class="eptw-muted" style="margin:0">An area with no project is shared by every project (e.g. "Laydown yard").</p>
                        </div>
                    </div>
                </div>
            </div>

            <script type="text/template" id="m-project">
                <div class="eptw-modal"><form method="post" action="<?= admin_url('eptw/eptw_setup/project_save'); ?>" onsubmit="this.action += (this.querySelector('[name=id]').value ? '/' + this.querySelector('[name=id]').value : '')"><?= $csrf; ?><input type="hidden" name="id" value="">
                    <div class="eptw-modal-head"><h3>Project</h3><button type="button" class="x" data-eptw-close>&times;</button></div>
                    <div class="eptw-modal-body">
                        <div class="eptw-grid-2">
                            <div class="eptw-field"><label class="eptw-label">Name <span class="req">*</span></label><input name="name" class="eptw-input" required></div>
                            <div class="eptw-field"><label class="eptw-label">Code</label><input name="code" class="eptw-input eptw-mono" placeholder="auto from name" maxlength="20"></div>
                        </div>
                        <div class="eptw-field"><label class="eptw-label">Client / owner</label><input name="client_name" class="eptw-input"></div>
                        <div class="eptw-field"><label class="eptw-label">Description</label><textarea name="description" class="eptw-textarea" style="min-height:60px"></textarea></div>
                        <div class="eptw-field"><label class="eptw-label">Camera policy</label><select name="camera_mode" class="eptw-select"><option value="inherit">Client default</option><?php foreach (eptw_camera_modes() as $k => $l) { ?><option value="<?= $k; ?>"><?= html_escape($l); ?></option><?php } ?></select></div>
                        <label class="eptw-check"><input type="checkbox" name="active" value="1" checked> <span>Active</span></label>
                    </div>
                    <div class="eptw-modal-foot"><button type="button" class="eptw-btn" data-eptw-close>Cancel</button><button type="submit" class="eptw-btn eptw-btn-primary">Save</button></div>
                </form></div>
            </script>
            <script type="text/template" id="m-area">
                <div class="eptw-modal"><form method="post" action="<?= admin_url('eptw/eptw_setup/area_save'); ?>" onsubmit="this.action += (this.querySelector('[name=id]').value ? '/' + this.querySelector('[name=id]').value : '')"><?= $csrf; ?><input type="hidden" name="id" value="">
                    <div class="eptw-modal-head"><h3>Area / zone</h3><button type="button" class="x" data-eptw-close>&times;</button></div>
                    <div class="eptw-modal-body">
                        <div class="eptw-field"><label class="eptw-label">Project</label><select name="project_id" class="eptw-select"><option value="0">Shared — all projects</option><?php foreach ($projects as $pr) { ?><option value="<?= $pr->id; ?>"><?= html_escape($pr->name); ?></option><?php } ?></select></div>
                        <div class="eptw-grid-2">
                            <div class="eptw-field"><label class="eptw-label">Name <span class="req">*</span></label><input name="name" class="eptw-input" required></div>
                            <div class="eptw-field"><label class="eptw-label">Code</label><input name="code" class="eptw-input eptw-mono" placeholder="auto from name" maxlength="20"></div>
                        </div>
                        <div class="eptw-field"><label class="eptw-label">Description</label><input name="description" class="eptw-input"></div>
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
// The "active" checkbox is prefilled with "1"/"0" from data-fill-active; turn that into checked state.
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
