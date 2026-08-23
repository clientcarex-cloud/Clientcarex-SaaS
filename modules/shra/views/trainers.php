<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'trainers'; include __DIR__ . '/_nav.php'; ?>

    <div class="shra-head" style="margin-bottom:12px">
        <h4 class="shra-title" style="margin:0">Trainers <span class="thin">· instructors who take the sessions</span></h4>
        <button type="button" class="shra-btn shra-btn-primary" onclick="shraEditTrainer(null)"><i class="fa fa-plus"></i> Add trainer</button>
    </div>

    <div class="shra-card">
        <?php if (!count($trainers)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-user-tie"></i>No trainers yet. Add the instructors who take the riding sessions — they appear in the attendance and billing trainer pickers.</div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Trainer</th><th>Mobile</th><th>Specialty</th><th>CRM login</th><th class="num">Sessions today</th><th class="num">Total sessions</th><th>Status</th><th></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($trainers as $t) { ?>
                <tr style="<?php echo $t->active ? '' : 'opacity:.5'; ?>">
                    <td><div class="shra-person"><span class="shra-avatar"><?php echo strtoupper(mb_substr($t->name, 0, 1)); ?></span><div><span class="name"><?php echo html_escape($t->name); ?></span></div></div></td>
                    <td><?php echo html_escape($t->mobile ?: '—'); ?></td>
                    <td><?php echo html_escape($t->specialty ?: '—'); ?></td>
                    <td><?php $sn = ''; foreach ($staff as $st) { if ($st->staffid == $t->staff_id) { $sn = $st->firstname . ' ' . $st->lastname; } } echo $sn ? html_escape($sn) : '<span class="shra-muted">—</span>'; ?></td>
                    <td class="num"><?php echo (int) $t->sessions_today ? '<span class="shra-badge shra-badge-green">' . (int) $t->sessions_today . '</span>' : '<span class="shra-muted">0</span>'; ?></td>
                    <td class="num"><?php echo (int) $t->sessions; ?></td>
                    <td><?php echo $t->active ? '<span class="shra-badge shra-badge-green">Active</span>' : '<span class="shra-badge shra-badge-muted">Inactive</span>'; ?></td>
                    <td><button type="button" class="shra-btn shra-btn-outline shra-btn-sm" onclick='shraEditTrainer(<?php echo json_encode($t); ?>)'><i class="fa fa-pen"></i></button></td>
                    <td><a href="<?php echo admin_url('shra/delete_trainer/' . $t->id); ?>" data-shra-confirm="Remove this trainer? If they have past sessions they will be deactivated instead." class="shra-btn shra-btn-danger shra-btn-sm"><i class="fa fa-trash"></i></a></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
        <?php } ?>
    </div>
    <div class="help" style="margin-top:10px">Link a trainer to a CRM login so that when they mark attendance, they are picked as the trainer automatically.</div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>

<div class="modal fade" id="shra-trainer-modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content shra">
    <?php echo form_open(admin_url('shra/trainers')); ?>
    <input type="hidden" name="id" id="tr-id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="tr-title">Trainer</h4></div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-7"><div class="form-group"><label>Name *</label><input type="text" name="name" id="tr-name" class="form-control" required></div></div>
            <div class="col-md-5"><div class="form-group"><label>Mobile</label><input type="tel" name="mobile" id="tr-mobile" class="form-control"></div></div>
        </div>
        <div class="row">
            <div class="col-md-7"><div class="form-group"><label>Specialty</label><input type="text" name="specialty" id="tr-specialty" class="form-control" placeholder="e.g. Beginners, Dressage, Show jumping"></div></div>
            <div class="col-md-5"><div class="form-group"><label>Sort order</label><input type="number" name="sort_order" id="tr-sort" class="form-control"></div></div>
        </div>
        <div class="form-group"><label>CRM login (optional)</label><select name="staff_id" id="tr-staff" class="form-control"><option value="">— not linked —</option><?php foreach ($staff as $st) { ?><option value="<?php echo $st->staffid; ?>"><?php echo html_escape($st->firstname . ' ' . $st->lastname); ?></option><?php } ?></select></div>
        <label style="font-weight:500"><input type="checkbox" name="active" value="1" id="tr-active" checked> Active — shown in trainer pickers</label>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-primary">Save trainer</button></div>
    <?php echo form_close(); ?>
</div></div></div>

<?php init_tail(); ?>
<script>
function shraEditTrainer(t) {
    t = t || { id: '', name: '', mobile: '', specialty: '', staff_id: '', active: 1, sort_order: 0 };
    $('#tr-title').text(t.id ? 'Edit trainer' : 'New trainer');
    $('#tr-id').val(t.id); $('#tr-name').val(t.name); $('#tr-mobile').val(t.mobile || ''); $('#tr-specialty').val(t.specialty || '');
    $('#tr-sort').val(t.sort_order || 0); $('#tr-staff').val(t.staff_id || ''); $('#tr-active').prop('checked', +t.active === 1);
    $('#shra-trainer-modal').modal('show');
}
</script>
</body>
</html>
