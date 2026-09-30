<?php defined('BASEPATH') or exit('No direct script access allowed');
init_head();
$p = $edit ?: (object) ['id' => 0, 'name' => '', 'delivery_hours' => 12, 'price' => '', 'compare_price' => '', 'badge' => '', 'features' => '', 'is_featured' => 0, 'active' => 1, 'sort_order' => count($plans) + 1];
$show_form = $edit || $is_new;
?>
<div id="wrapper">
<div class="content duns-wrap">
    <?php $this->load->view('admin/_nav'); ?>

    <div class="row">
        <div class="<?php echo $show_form ? 'col-md-7' : 'col-md-12'; ?>">
            <div class="duns-card">
                <div class="duns-card-head"><h4>Plans on the landing page</h4>
                    <a href="<?php echo admin_url('duns_support/plans?new=1'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New plan</a></div>
                <div class="table-responsive">
                <table class="duns-table">
                    <thead><tr><th>#</th><th>Plan</th><th>Delivery</th><th>Price</th><th>Shown</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($plans as $pl) { ?>
                        <tr class="duns-plan-row">
                            <td><?php echo (int) $pl->sort_order; ?></td>
                            <td><b><?php echo html_escape($pl->name); ?></b>
                                <?php if ($pl->badge) { ?> <span class="duns-badge duns-badge-blue"><?php echo html_escape($pl->badge); ?></span><?php } ?>
                                <?php if ($pl->is_featured) { ?> <span class="duns-badge duns-badge-amber"><i class="fa-solid fa-star"></i> Featured</span><?php } ?>
                                <span class="sub" style="display:block;color:#6b7280;font-size:12px;white-space:pre-line;margin-top:4px"><?php echo html_escape($pl->features); ?></span></td>
                            <td><?php echo duns_hours_label($pl->delivery_hours); ?></td>
                            <td><b><?php echo duns_money($pl->price); ?></b><?php if ($pl->compare_price) { ?><br><s class="text-muted"><?php echo duns_money($pl->compare_price); ?></s><?php } ?></td>
                            <td><?php echo $pl->active ? '<span class="duns-badge duns-badge-green">Active</span>' : '<span class="duns-badge duns-badge-muted">Hidden</span>'; ?></td>
                            <td style="white-space:nowrap">
                                <a class="btn btn-default btn-sm" href="<?php echo admin_url('duns_support/plans?edit=' . $pl->id); ?>"><i class="fa-solid fa-pen"></i></a>
                                <?php echo form_open(admin_url('duns_support/delete_plan/' . $pl->id), ['style' => 'display:inline', 'onsubmit' => "return confirm('Delete this plan? Existing orders keep their details. Tip: untick Active to just hide it.');"]); ?>
                                <button class="btn btn-default btn-sm text-danger"><i class="fa-solid fa-trash"></i></button><?php echo form_close(); ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (!count($plans)) { ?><tr><td colspan="6" class="duns-empty">No plans yet — the landing page needs at least one active plan.</td></tr><?php } ?>
                    </tbody>
                </table>
                </div>
            </div>
            <p class="duns-help">Prices are in the base currency (<?php echo html_escape(get_base_currency()->name); ?>). The delivery clock on an order starts when its payment is confirmed. Changing a plan never changes orders already placed.</p>
        </div>

        <?php if ($show_form) { ?>
        <div class="col-md-5">
            <div class="duns-card">
                <div class="duns-card-head"><h4><?php echo $p->id ? 'Edit plan' : 'New plan'; ?></h4><a href="<?php echo admin_url('duns_support/plans'); ?>" class="text-muted"><i class="fa-solid fa-xmark"></i></a></div>
                <div class="duns-card-body">
                    <?php echo form_open(admin_url('duns_support/plans')); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
                    <div class="row">
                        <div class="col-md-7"><div class="form-group"><label>Name</label><input name="name" class="form-control" value="<?php echo html_escape($p->name); ?>" placeholder="Express" required></div></div>
                        <div class="col-md-5"><div class="form-group"><label>Delivery (hours)</label><input type="number" min="1" name="delivery_hours" class="form-control" value="<?php echo (int) $p->delivery_hours; ?>" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Price</label><input type="number" min="1" step="0.01" name="price" class="form-control" value="<?php echo html_escape($p->price + 0 ?: ''); ?>" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Old price <span class="text-muted">(struck through)</span></label><input type="number" min="0" step="0.01" name="compare_price" class="form-control" value="<?php echo $p->compare_price ? html_escape($p->compare_price + 0) : ''; ?>"></div></div>
                        <div class="col-md-7"><div class="form-group"><label>Badge <span class="text-muted">(optional)</span></label><input name="badge" class="form-control" value="<?php echo html_escape($p->badge); ?>" placeholder="Most popular"></div></div>
                        <div class="col-md-5"><div class="form-group"><label>Sort order</label><input type="number" name="sort_order" class="form-control" value="<?php echo (int) $p->sort_order; ?>"></div></div>
                        <div class="col-md-12"><div class="form-group"><label>Features <span class="text-muted">(one per line)</span></label><textarea name="features" class="form-control" rows="5"><?php echo html_escape($p->features); ?></textarea></div></div>
                    </div>
                    <div class="checkbox"><input type="checkbox" id="dn-active" name="active" value="1" <?php echo $p->active ? 'checked' : ''; ?>><label for="dn-active">Active (shown on the landing page)</label></div>
                    <div class="checkbox"><input type="checkbox" id="dn-is_featured" name="is_featured" value="1" <?php echo $p->is_featured ? 'checked' : ''; ?>><label for="dn-is_featured">Featured (highlighted and pre-selected)</label></div>
                    <button class="btn btn-primary">Save plan</button>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
