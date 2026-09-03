<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrf = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'contractors'; include __DIR__ . '/_setup_nav.php'; ?>

            <div class="eptw-card">
                <div class="eptw-card-head"><h3><i class="fa-solid fa-helmet-safety"></i> Contractors</h3>
                    <div class="eptw-card-actions"><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-contractor"><i class="fa fa-plus"></i> Add contractor</button></div></div>
                <?php if (!count($contractors)) { ?><div class="eptw-empty"><i class="fa-solid fa-helmet-safety"></i><h4>No contractors yet</h4></div><?php } else { ?>
                <div class="eptw-table-scroll"><table class="eptw-table">
                    <thead><tr><th>Code</th><th>Contractor</th><th>Contact</th><th class="eptw-num">Permits</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($contractors as $c) { ?>
                        <tr>
                            <td class="eptw-mono eptw-strong"><?= html_escape($c->code); ?></td>
                            <td><span class="eptw-strong"><?= html_escape($c->name); ?></span> <?= $c->active ? '' : '<span class="eptw-badge muted">inactive</span>'; ?></td>
                            <td class="eptw-small"><?= html_escape($c->contact_name); ?><div class="eptw-muted"><?= html_escape(trim($c->phone . ' ' . $c->email)); ?></div></td>
                            <td class="eptw-num"><?= (int) ($counts[$c->id] ?? 0); ?></td>
                            <td class="eptw-actions">
                                <button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-contractor" data-fill-id="<?= $c->id; ?>" data-fill-name="<?= html_escape($c->name); ?>" data-fill-code="<?= html_escape($c->code); ?>" data-fill-contact_name="<?= html_escape($c->contact_name); ?>" data-fill-phone="<?= html_escape($c->phone); ?>" data-fill-email="<?= html_escape($c->email); ?>" data-fill-active="<?= (int) $c->active; ?>"><i class="fa fa-pen"></i></button>
                                <?php if (!($counts[$c->id] ?? 0)) { ?><form method="post" action="<?= admin_url('eptw/eptw_setup/contractor_delete/' . $c->id); ?>" style="display:inline" onsubmit="return confirm('Delete this contractor?')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-trash"></i></button></form><?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody></table></div>
                <?php } ?>
            </div>

            <script type="text/template" id="m-contractor">
                <div class="eptw-modal"><form method="post" action="<?= admin_url('eptw/eptw_setup/contractor_save'); ?>" onsubmit="this.action += (this.querySelector('[name=id]').value ? '/' + this.querySelector('[name=id]').value : '')"><?= $csrf; ?><input type="hidden" name="id" value="">
                    <div class="eptw-modal-head"><h3>Contractor</h3><button type="button" class="x" data-eptw-close>&times;</button></div>
                    <div class="eptw-modal-body">
                        <div class="eptw-grid-2">
                            <div class="eptw-field"><label class="eptw-label">Name <span class="req">*</span></label><input name="name" class="eptw-input" required></div>
                            <div class="eptw-field"><label class="eptw-label">Code</label><input name="code" class="eptw-input eptw-mono" maxlength="20"></div>
                        </div>
                        <div class="eptw-field"><label class="eptw-label">Contact person</label><input name="contact_name" class="eptw-input"></div>
                        <div class="eptw-grid-2">
                            <div class="eptw-field"><label class="eptw-label">Phone</label><input name="phone" class="eptw-input"></div>
                            <div class="eptw-field"><label class="eptw-label">Email</label><input name="email" type="email" class="eptw-input"></div>
                        </div>
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
