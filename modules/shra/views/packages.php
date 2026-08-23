<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'packages'; include __DIR__ . '/_nav.php'; ?>

    <div class="shra-head" style="margin-bottom:12px">
        <h4 class="shra-title" style="margin:0">Riding plans <span class="thin">· price list</span></h4>
        <button type="button" class="shra-btn shra-btn-primary" onclick="shraEditPackage(null)"><i class="fa fa-plus"></i> Add package</button>
    </div>
    <?php if ($offer['active']) { ?><div class="shra-offer" style="margin-bottom:16px"><span class="stamp"><?php echo $offer['percent'] + 0; ?>% OFF</span> <?php echo html_escape($offer['label']); ?> — the "You pay" column shows the discounted price. <a href="<?php echo admin_url('shra/settings'); ?>" style="margin-left:auto">Change offer</a></div><?php } ?>

    <?php foreach (['children' => 'Children · under ' . (int) get_option('shra_minor_age'), 'adults' => 'Adults · ' . (int) get_option('shra_minor_age') . ' and over'] as $aud => $label) { ?>
    <div class="shra-card" style="margin-bottom:18px">
        <div class="shra-card-head"><h4><?php echo $label; ?></h4></div>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Plan</th><th class="num">Sessions</th><th class="num">Minutes</th><th class="num">Per session</th><th class="num">Price</th><th class="num" style="color:var(--red)">You pay<?php echo $offer['active'] ? ' −' . ($offer['percent'] + 0) . '%' : ''; ?></th><th>Validity</th><th></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($packages as $p) { if ($p->audience !== $aud) { continue; } $q = $this->shra_model->quote($p); ?>
                <tr style="<?php echo $p->active ? '' : 'opacity:.5'; ?>">
                    <td class="strong"><?php echo html_escape($p->name); ?> <?php echo $p->is_guest ? '<span class="shra-badge shra-badge-gold">Guest</span>' : ''; ?> <?php echo $p->is_featured ? '<span class="shra-badge shra-badge-green">Best value</span>' : ''; ?><?php echo !$p->active ? '<span class="sub">Hidden</span>' : ''; ?></td>
                    <td class="num"><?php echo (int) $p->sessions; ?></td>
                    <td class="num"><?php echo (int) $p->duration_min; ?></td>
                    <td class="num"><?php echo shra_money($p->per_session); ?></td>
                    <td class="num"><?php echo $q['discount_percent'] > 0 ? '<s class="shra-muted">' . shra_money($p->price) . '</s>' : shra_money($p->price); ?></td>
                    <td class="num strong" style="color:var(--red)"><?php echo shra_money($q['total']); ?></td>
                    <td><?php echo $p->validity_days ? (int) $p->validity_days . ' days' : 'No expiry'; ?></td>
                    <td><button type="button" class="shra-btn shra-btn-outline shra-btn-sm" onclick='shraEditPackage(<?php echo json_encode($p); ?>)'><i class="fa fa-pen"></i></button></td>
                    <td><a href="<?php echo admin_url('shra/delete_package/' . $p->id); ?>" data-shra-confirm="Delete this package? Existing enrollments keep their snapshot." class="shra-btn shra-btn-danger shra-btn-sm"><i class="fa fa-trash"></i></a></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
    </div>
    <?php } ?>
</div>
</div>

<div class="modal fade" id="shra-pkg-modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content shra">
    <?php echo form_open(admin_url('shra/packages')); ?>
    <input type="hidden" name="id" id="pk-id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="pk-title">Package</h4></div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-7"><div class="form-group"><label>Name *</label><input type="text" name="name" id="pk-name" class="form-control" required></div></div>
            <div class="col-md-5"><div class="form-group"><label>Audience</label><select name="audience" id="pk-audience" class="form-control"><option value="children">Children</option><option value="adults">Adults</option></select></div></div>
        </div>
        <div class="row">
            <div class="col-md-4"><div class="form-group"><label>Sessions</label><input type="number" min="1" name="sessions" id="pk-sessions" class="form-control"></div></div>
            <div class="col-md-4"><div class="form-group"><label>Minutes / session</label><input type="number" min="5" name="duration_min" id="pk-duration" class="form-control"></div></div>
            <div class="col-md-4"><div class="form-group"><label>Sort order</label><input type="number" name="sort_order" id="pk-sort" class="form-control"></div></div>
        </div>
        <div class="row">
            <div class="col-md-4"><div class="form-group"><label>Per session</label><input type="number" step="0.01" min="0" name="per_session" id="pk-per" class="form-control"></div></div>
            <div class="col-md-4"><div class="form-group"><label>Package price</label><input type="number" step="0.01" min="0" name="price" id="pk-price" class="form-control" placeholder="auto = per × sessions"></div></div>
            <div class="col-md-4"><div class="form-group"><label>Validity (days)</label><input type="number" min="1" name="validity_days" id="pk-validity" class="form-control" placeholder="blank = none"></div></div>
        </div>
        <div style="display:flex;gap:18px;flex-wrap:wrap">
            <label style="font-weight:500"><input type="checkbox" name="is_guest" value="1" id="pk-guest"> Guest ride (no membership / certificate)</label>
            <label style="font-weight:500"><input type="checkbox" name="is_featured" value="1" id="pk-featured"> "Best value" badge</label>
            <label style="font-weight:500"><input type="checkbox" name="active" value="1" id="pk-active" checked> Active</label>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-primary">Save package</button></div>
    <?php echo form_close(); ?>
</div></div></div>

<?php init_tail(); ?>
<script>
function shraEditPackage(p) {
    p = p || { id: '', name: '', audience: 'children', sessions: 8, duration_min: 30, per_session: '', price: '', validity_days: '', is_guest: 0, is_featured: 0, active: 1, sort_order: 0 };
    $('#pk-title').text(p.id ? 'Edit package' : 'New package');
    $('#pk-id').val(p.id); $('#pk-name').val(p.name); $('#pk-audience').val(p.audience); $('#pk-sessions').val(p.sessions); $('#pk-duration').val(p.duration_min);
    $('#pk-sort').val(p.sort_order); $('#pk-per').val(p.per_session); $('#pk-price').val(p.price); $('#pk-validity').val(p.validity_days || '');
    $('#pk-guest').prop('checked', +p.is_guest === 1); $('#pk-featured').prop('checked', +p.is_featured === 1); $('#pk-active').prop('checked', +p.active === 1);
    $('#shra-pkg-modal').modal('show');
}
$('#pk-per, #pk-sessions').on('input', function () {
    var per = parseFloat($('#pk-per').val()) || 0, n = parseInt($('#pk-sessions').val(), 10) || 0;
    if (per && n) { $('#pk-price').val((per * n).toFixed(2)); }
});
</script>
</body>
</html>
