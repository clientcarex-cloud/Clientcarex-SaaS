<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_create = has_permission('hr_perks', '', 'create') || is_admin();
$can_edit   = has_permission('hr_perks', '', 'edit') || is_admin();
$can_delete = has_permission('hr_perks', '', 'delete') || is_admin();

// Group items by category, preserving the model's ordering within each group.
$grouped = [];
foreach ($items as $it) {
    $grouped[$it['category'] ?: 'Other'][] = $it;
}
?>
<?php init_head(); ?>
<style>
    .perk-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .perk-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; background: #f1f5f9; font-size: 13px; font-weight: 600; color: #334155; border: 1px solid #e2e8f0; }
    .perk-chip .perk-chip-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
    .perk-chip .perk-chip-num { background: #fff; border-radius: 12px; padding: 0 8px; font-size: 12px; }
    .perk-cat-head { display: flex; align-items: center; gap: 8px; margin: 22px 0 8px; font-size: 15px; font-weight: 700; color: #0f172a; }
    .perk-cat-head .perk-cat-count { font-size: 12px; font-weight: 600; color: #64748b; background: #f1f5f9; border-radius: 12px; padding: 1px 9px; }
    .perk-table td { vertical-align: middle !important; }
    .perk-title { font-weight: 600; color: #0f172a; }
    .perk-title.perk-done { text-decoration: line-through; color: #94a3b8; }
    .perk-meta { font-size: 12px; color: #64748b; }
    .perk-status-group { display: inline-flex; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
    .perk-status-group button { border: 0; background: #fff; padding: 4px 9px; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; border-right: 1px solid #e2e8f0; line-height: 1.5; }
    .perk-status-group button:last-child { border-right: 0; }
    .perk-status-group button.active { color: #fff; }
    .perk-status-group button:disabled { cursor: default; }
    .perk-prio { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .perk-empty { padding: 40px 15px; text-align: center; color: #94a3b8; }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin bold"><i class="fa fa-clipboard-list"></i> <?php echo _l('hr_perks'); ?></h4>
                                <p class="text-muted no-margin" style="margin-top:4px;">Office supplies &amp; maintenance ordering checklist — snacks, beverages, cleaning, upkeep and more.</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <?php if ($can_delete && $counts['received'] > 0) { ?>
                                    <?php echo form_open(admin_url('hr/clear_received_perks'), ['style' => 'display:inline-block', 'onsubmit' => "return confirm('Remove all received items from the checklist? This cannot be undone.');"]); ?>
                                    <button type="submit" class="btn btn-default"><i class="fa fa-broom"></i> Clear received</button>
                                    <?php echo form_close(); ?>
                                <?php } ?>
                                <?php if ($can_create) { ?>
                                    <button type="button" class="btn btn-primary" onclick="openPerkModal()"><i class="fa fa-plus"></i> Add Item</button>
                                <?php } ?>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-md-8">
                                <div class="perk-chips">
                                    <?php foreach ($statuses as $skey => $s) { ?>
                                        <span class="perk-chip">
                                            <span class="perk-chip-dot" style="background:<?php echo $s['color']; ?>"></span>
                                            <?php echo $s['label']; ?>
                                            <span class="perk-chip-num"><?php echo (int) $counts[$skey]; ?></span>
                                        </span>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="row">
                                    <div class="col-xs-6">
                                        <select id="perk_filter_status" class="selectpicker" data-width="100%" title="All statuses">
                                            <option value="">All statuses</option>
                                            <?php foreach ($statuses as $skey => $s) { ?>
                                                <option value="<?php echo $skey; ?>" <?php echo $status === $skey ? 'selected' : ''; ?>><?php echo $s['label']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-xs-6">
                                        <select id="perk_filter_category" class="selectpicker" data-width="100%" data-live-search="true" title="All categories">
                                            <option value="">All categories</option>
                                            <?php foreach ($categories as $cat) { ?>
                                                <option value="<?php echo html_escape($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>><?php echo html_escape($cat); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!count($items)) { ?>
                            <div class="perk-empty">
                                <i class="fa fa-clipboard-list fa-2x" style="opacity:.4"></i>
                                <p style="margin-top:10px;">No checklist items<?php echo ($status || $category) ? ' match the current filter' : ' yet'; ?>.
                                <?php if ($can_create && !$status && !$category) { ?><br>Click <strong>Add Item</strong> to add snacks, beverages or maintenance things to order.<?php } ?></p>
                            </div>
                        <?php } else {
                            foreach ($grouped as $cat => $rows) { ?>
                                <div class="perk-cat-head">
                                    <i class="fa fa-tag text-muted"></i>
                                    <?php echo html_escape($cat); ?>
                                    <span class="perk-cat-count"><?php echo count($rows); ?></span>
                                </div>
                                <table class="table table-striped perk-table">
                                    <thead>
                                        <tr>
                                            <th style="width:36%">Item</th>
                                            <th>Qty</th>
                                            <th>Priority</th>
                                            <th>Assigned</th>
                                            <th>Needed by</th>
                                            <th class="text-center" style="width:190px">Status</th>
                                            <th class="text-right" style="width:80px">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $it) {
                                            $prio     = $priorities[$it['priority']] ?? $priorities['medium'];
                                            $is_done  = $it['status'] === 'received';
                                            $assignee = trim(($it['assignee_firstname'] ?? '') . ' ' . ($it['assignee_lastname'] ?? '')); ?>
                                            <tr>
                                                <td>
                                                    <span class="perk-title<?php echo $is_done ? ' perk-done' : ''; ?>" data-perk-title="<?php echo $it['id']; ?>"><?php echo html_escape($it['title']); ?></span>
                                                    <?php if (!empty($it['notes'])) { ?>
                                                        <div class="perk-meta"><i class="fa fa-sticky-note"></i> <?php echo html_escape($it['notes']); ?></div>
                                                    <?php } ?>
                                                </td>
                                                <td><?php echo $it['quantity'] ? html_escape($it['quantity']) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                                <td><span class="perk-prio" style="color:<?php echo $prio['color']; ?>"><?php echo $prio['label']; ?></span></td>
                                                <td><?php echo $assignee !== '' ? html_escape($assignee) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                                <td>
                                                    <?php if (!empty($it['needed_by']) && $it['needed_by'] !== '0000-00-00') {
                                                        $overdue = !$is_done && strtotime($it['needed_by']) < strtotime(date('Y-m-d')); ?>
                                                        <span class="<?php echo $overdue ? 'text-danger bold' : ''; ?>"><?php echo _d($it['needed_by']); ?></span>
                                                    <?php } else { ?>
                                                        <span class="text-muted">&mdash;</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="perk-status-group" data-id="<?php echo $it['id']; ?>">
                                                        <?php foreach ($statuses as $skey => $s) {
                                                            $active = $it['status'] === $skey; ?>
                                                            <button type="button" class="perk-status-btn<?php echo $active ? ' active' : ''; ?>"
                                                                data-status="<?php echo $skey; ?>"
                                                                style="<?php echo $active ? 'background:' . $s['color'] . ';' : ''; ?>"
                                                                <?php echo $can_edit ? '' : 'disabled'; ?>
                                                                title="<?php echo $s['label']; ?>"><?php echo $s['label']; ?></button>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    <?php if ($can_edit) { ?>
                                                        <button type="button" class="btn btn-default btn-icon" onclick='openPerkModal(<?php echo json_encode($it, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                                    <?php } ?>
                                                    <?php if ($can_delete) { ?>
                                                        <a href="<?php echo admin_url('hr/delete_perk_item/' . $it['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            <?php }
                        } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_create || $can_edit) { ?>
    <div class="modal fade" id="perk_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_perk_item'), ['id' => 'perk_form']); ?>
                <input type="hidden" name="id" id="perk_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><?php echo _l('hr_perk_item'); ?></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Item name <small class="text-danger">*</small></label>
                        <input type="text" name="title" id="perk_title" class="form-control" placeholder="e.g. Biscuits, Hand wash, Tube light" required>
                    </div>
                    <div class="row">
                        <div class="col-md-7 form-group">
                            <label>Category</label>
                            <input type="text" name="category" id="perk_category" class="form-control" list="perk_category_list" placeholder="Pantry &amp; Snacks">
                            <datalist id="perk_category_list">
                                <?php foreach ($categories as $cat) { ?>
                                    <option value="<?php echo html_escape($cat); ?>"></option>
                                <?php } ?>
                            </datalist>
                        </div>
                        <div class="col-md-5 form-group">
                            <label>Quantity</label>
                            <input type="text" name="quantity" id="perk_quantity" class="form-control" placeholder="e.g. 2 boxes, 5 kg">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Priority</label>
                            <select name="priority" id="perk_priority" class="form-control">
                                <?php foreach ($priorities as $pkey => $p) { ?>
                                    <option value="<?php echo $pkey; ?>" <?php echo $pkey === 'medium' ? 'selected' : ''; ?>><?php echo $p['label']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Status</label>
                            <select name="status" id="perk_status" class="form-control">
                                <?php foreach ($statuses as $skey => $s) { ?>
                                    <option value="<?php echo $skey; ?>"><?php echo $s['label']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Needed by</label>
                            <input type="date" name="needed_by" id="perk_needed_by" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Assign to <small class="text-muted">(optional)</small></label>
                        <select name="assigned_to" id="perk_assigned_to" class="selectpicker" data-width="100%" data-live-search="true" data-none-selected-text="— Nobody —">
                            <option value="">— Nobody —</option>
                            <?php foreach ($employees as $emp) { ?>
                                <option value="<?php echo $emp['staffid']; ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="perk_notes" class="form-control" rows="2" placeholder="Brand, vendor, or any specific instruction"></textarea>
                    </div>
                    <input type="hidden" name="sort_order" id="perk_sort_order" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php init_tail(); ?>
<script>
    var PERK_CSRF_NAME = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var PERK_CSRF_HASH = '<?php echo $this->security->get_csrf_hash(); ?>';
    var PERK_STATUS_COLORS = <?php echo json_encode(array_map(function ($s) { return $s['color']; }, $statuses)); ?>;

    $(function () {
        function applyFilters() {
            var params = [];
            var st = $('#perk_filter_status').val();
            var ct = $('#perk_filter_category').val();
            if (st) { params.push('status=' + encodeURIComponent(st)); }
            if (ct) { params.push('category=' + encodeURIComponent(ct)); }
            window.location.href = '<?php echo admin_url('hr/perks'); ?>' + (params.length ? '?' + params.join('&') : '');
        }
        $('#perk_filter_status, #perk_filter_category').on('change', applyFilters);

        $(document).on('click', '.perk-status-btn', function () {
            var $btn    = $(this),
                $group  = $btn.closest('.perk-status-group'),
                id      = $group.data('id'),
                status  = $btn.data('status');
            if ($btn.hasClass('active')) { return; }

            var data = { id: id, status: status };
            data[PERK_CSRF_NAME] = PERK_CSRF_HASH;
            $group.find('button').prop('disabled', true);

            $.post('<?php echo admin_url('hr/update_perk_status'); ?>', data, function (r) {
                if (r && r[PERK_CSRF_NAME]) { PERK_CSRF_HASH = r[PERK_CSRF_NAME]; }
                if (!r || !r.success) {
                    if (typeof alert_float === 'function') { alert_float('danger', 'Could not update the item. Please try again.'); }
                    return;
                }
                // Repaint the group.
                $group.find('button').each(function () {
                    var isActive = $(this).data('status') === status;
                    $(this).toggleClass('active', isActive)
                           .css('background', isActive ? (PERK_STATUS_COLORS[status] || '') : '');
                });
                // Strike-through the title when received.
                var $title = $('[data-perk-title="' + id + '"]');
                $title.toggleClass('perk-done', status === 'received');
            }, 'json').fail(function () {
                if (typeof alert_float === 'function') { alert_float('danger', 'Could not update the item. Please try again.'); }
            }).always(function () {
                $group.find('button').prop('disabled', false);
            });
        });
    });

    function openPerkModal(it) {
        var $f = $('#perk_form');
        $f[0].reset();
        $('#perk_id').val('');
        $('#perk_assigned_to').selectpicker('val', '');
        if (it) {
            $('#perk_id').val(it.id);
            $('#perk_title').val(it.title);
            $('#perk_category').val(it.category);
            $('#perk_quantity').val(it.quantity || '');
            $('#perk_priority').val(it.priority || 'medium');
            $('#perk_status').val(it.status || 'pending');
            $('#perk_needed_by').val(it.needed_by && it.needed_by !== '0000-00-00' ? it.needed_by : '');
            $('#perk_notes').val(it.notes || '');
            $('#perk_sort_order').val(it.sort_order || 0);
            $('#perk_assigned_to').selectpicker('val', it.assigned_to ? String(it.assigned_to) : '');
        }
        $('#perk_modal').modal('show');
    }
</script>
