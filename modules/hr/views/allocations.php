<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit  = has_permission('hr_leaves', '', 'edit') || is_admin();
$has_cf    = false;
foreach ($leave_types as $lt) { if (!empty($lt['carry_forward'])) { $has_cf = true; break; } }
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-3">
                                <select id="alloc_year" class="selectpicker" data-width="100%">
                                    <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 3; $y--) { ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-9 text-right">
                                <?php if ($can_edit && $has_cf) { ?>
                                    <?php echo form_open(admin_url('hr/carry_forward'), ['style' => 'display:inline-block;', 'onsubmit' => "return confirm('Carry forward each employee\\'s unused balance from " . ($year - 1) . " into " . $year . "? Existing carried values for " . $year . " will be recomputed.');"]); ?>
                                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                                        <button type="submit" class="btn btn-info" data-toggle="tooltip" title="Roll each employee's unused <?php echo $year - 1; ?> balance into <?php echo $year; ?> for carry-forward leave types (respecting any cap)."><i class="fa fa-refresh"></i> Carry Forward from <?php echo $year - 1; ?></button>
                                    <?php echo form_close(); ?>
                                <?php } ?>
                                <a href="<?php echo admin_url('hr/leaves'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Leaves</a>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />
                        <p class="text-muted">Per-employee annual quota for <strong><?php echo $year; ?></strong>. Blank cells fall back to the leave type's default days/year. <span class="text-danger">Red</span> = days already used; <span style="color:#0891b2;">teal</span> = carried forward from <?php echo $year - 1; ?>.</p>

                        <?php if ($can_edit) { ?>
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <strong style="font-size:13px;"><i class="fa fa-magic text-info"></i> Bulk fill:</strong>
                                <button type="button" class="btn btn-default btn-sm" id="bulk_fill_defaults" data-toggle="tooltip" title="Put each leave type's default days into every employee's cell">Fill everyone with defaults</button>
                                <button type="button" class="btn btn-default btn-sm" id="bulk_clear_all">Clear all</button>
                                <span class="text-muted" style="font-size:12px;">— or type a number in a column header box below and click <i class="fa fa-arrow-down"></i> to apply it down the whole column. Then hit <strong>Save Allocations</strong> once.</span>
                            </div>
                        <?php } ?>

                        <?php echo form_open(admin_url('hr/save_allocations'), ['id' => 'alloc_form']); ?>
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <div class="table-responsive">
                            <table class="table table-striped" id="alloc_table">
                                <thead>
                                    <tr>
                                        <th style="min-width:200px;">Employee</th>
                                        <?php foreach ($leave_types as $lt) { ?>
                                            <th class="text-center" style="min-width:120px;">
                                                <i class="fa <?php echo hr_leave_type_icon($lt); ?>" style="color:<?php echo html_escape($lt['color']); ?>;"></i>
                                                <span style="color:<?php echo html_escape($lt['color']); ?>;font-weight:700;"><?php echo html_escape($lt['name']); ?></span>
                                                <div class="text-muted" style="font-weight:400;font-size:11px;">
                                                    <?php echo html_escape($lt['code']); ?> · default <?php echo (float) $lt['days_per_year']; ?>
                                                    <?php if (!empty($lt['carry_forward'])) { ?><span title="Carry-forward enabled<?php echo (float) ($lt['carry_forward_max'] ?? 0) > 0 ? ', capped at ' . (float) $lt['carry_forward_max'] : ''; ?>"><i class="fa fa-refresh text-info"></i></span><?php } ?>
                                                </div>
                                                <?php if ($can_edit) { ?>
                                                    <div class="input-group input-group-sm" style="margin-top:4px;">
                                                        <input type="number" step="0.5" min="0" class="form-control input-sm text-center col-fill-input" placeholder="all" data-type="<?php echo $lt['id']; ?>" style="font-weight:400;">
                                                        <span class="input-group-addon col-fill-apply" data-type="<?php echo $lt['id']; ?>" style="cursor:pointer;" title="Apply to all employees"><i class="fa fa-arrow-down"></i></span>
                                                    </div>
                                                <?php } ?>
                                            </th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp) { ?>
                                        <tr>
                                            <td><a href="<?php echo admin_url('hr/employee/' . $emp['staffid'] . '?tab=leave'); ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></a>
                                                <div class="text-muted" style="font-size:11px;"><?php echo html_escape($emp['employee_code']); ?></div></td>
                                            <?php foreach ($leave_types as $lt) {
                                                $val  = $allocations[$emp['staffid']][$lt['id']] ?? '';
                                                $used = $leave_used[$emp['staffid']][$lt['id']] ?? 0;
                                                $cf   = $carried[$emp['staffid']][$lt['id']] ?? 0; ?>
                                                <td class="text-center" style="max-width:120px;">
                                                    <input type="number" step="0.5" min="0" class="form-control input-sm text-center alloc-cell alloc-col-<?php echo $lt['id']; ?>"
                                                           name="alloc[<?php echo $emp['staffid']; ?>][<?php echo $lt['id']; ?>]"
                                                           value="<?php echo $val; ?>" placeholder="<?php echo (float) $lt['days_per_year']; ?>" data-default="<?php echo (float) $lt['days_per_year']; ?>" <?php echo $can_edit ? '' : 'readonly'; ?>>
                                                    <div style="font-size:10px;line-height:1.4;">
                                                        <?php if ($cf > 0) { ?><span style="color:#0891b2;">+<?php echo (float) $cf; ?> cf</span><?php } ?>
                                                        <?php if ($used > 0) { ?><span class="text-danger"><?php echo $cf > 0 ? ' · ' : ''; ?>used <?php echo (float) $used; ?></span><?php } ?>
                                                    </div>
                                                </td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($can_edit) { ?>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Allocations</button>
                        <?php } ?>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        $('#alloc_year').on('change', function () {
            window.location.href = '<?php echo admin_url('hr/allocations'); ?>?year=' + this.value;
        });

        // Fill every cell with its column default.
        $('#bulk_fill_defaults').on('click', function () {
            $('#alloc_table .alloc-cell').each(function () {
                $(this).val($(this).data('default'));
            });
        });

        // Clear every cell (revert to default fallback on save).
        $('#bulk_clear_all').on('click', function () {
            $('#alloc_table .alloc-cell').val('');
        });

        // Apply one column-header value down the whole column.
        function applyColumn(typeId) {
            var v = $('.col-fill-input[data-type="' + typeId + '"]').val();
            if (v === '') { return; }
            $('.alloc-col-' + typeId).val(v);
        }
        $('.col-fill-apply').on('click', function () { applyColumn($(this).data('type')); });
        $('.col-fill-input').on('keydown', function (e) {
            if (e.which === 13) { e.preventDefault(); applyColumn($(this).data('type')); }
        });
    });
</script>
