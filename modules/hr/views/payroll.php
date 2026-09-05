<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_create = has_permission('hr_payroll', '', 'create') || is_admin();
$can_edit   = has_permission('hr_payroll', '', 'edit') || is_admin();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 class="bold" style="margin:0;">Payroll Runs</h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                                <?php if ($can_create) { ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#generate_modal"><i class="fa fa-cogs"></i> Generate Payroll</button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Period</th><th>Payslips</th><th>Total Net Pay</th><th>Status</th><th>Generated</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php if (!count($runs)) { ?>
                                    <tr><td colspan="6" class="text-muted">No payroll generated yet.</td></tr>
                                <?php }
                                foreach ($runs as $run) { ?>
                                    <tr>
                                        <td class="bold"><?php echo date('F Y', mktime(0, 0, 0, $run['month'], 1, $run['year'])); ?></td>
                                        <td><?php echo (int) $run['slip_count']; ?></td>
                                        <td><?php echo app_format_money($run['total_net'], get_base_currency()); ?></td>
                                        <td><span class="label label-<?php echo $run['status'] === 'finalized' ? 'success' : 'warning'; ?>"><?php echo ucfirst($run['status']); ?></span></td>
                                        <td><?php echo _dt($run['created_at']); ?></td>
                                        <td class="text-right">
                                            <a href="<?php echo admin_url('hr/payroll_run/' . $run['id']); ?>" class="btn btn-default btn-icon" data-toggle="tooltip" title="Open"><i class="fa fa-folder-open"></i></a>
                                            <?php
                                            $is_draft   = $run['status'] === 'draft';
                                            $may_delete = $is_draft
                                                ? (has_permission('hr_payroll', '', 'delete') || is_admin())
                                                : is_admin(); // finalized runs: superadmin only
                                            if ($may_delete) { ?>
                                                <a href="<?php echo admin_url('hr/delete_payroll_run/' . $run['id']); ?>"
                                                   class="btn btn-danger btn-icon" data-toggle="tooltip"
                                                   title="Delete run &amp; all its payslips"
                                                   onclick="return confirm('Delete this <?php echo $is_draft ? 'draft' : 'FINALIZED'; ?> payroll run and all <?php echo (int) $run['slip_count']; ?> payslip(s) permanently? This cannot be undone.');"><i class="fa fa-remove"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold" style="margin:0 0 10px;">Salary Components</h4>
                        <?php if ($can_edit) { ?>
                            <?php echo form_open(admin_url('hr/save_salary_component'), ['id' => 'component_form']); ?>
                            <input type="hidden" name="id" id="comp_id">
                            <div class="row">
                                <div class="col-md-4"><input type="text" name="name" id="comp_name" class="form-control input-sm" placeholder="Name" required></div>
                                <div class="col-md-3">
                                    <select name="type" id="comp_type" class="form-control input-sm">
                                        <option value="earning">Earning</option>
                                        <option value="deduction">Deduction</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="calc_type" id="comp_calc" class="form-control input-sm">
                                        <option value="fixed">Fixed</option>
                                        <option value="percent_basic">% of Basic</option>
                                    </select>
                                </div>
                                <div class="col-md-2"><input type="number" step="0.01" min="0" name="default_value" id="comp_value" class="form-control input-sm" placeholder="Value"></div>
                            </div>
                            <div class="row mtop5">
                                <div class="col-md-4"><input type="number" name="sort_order" id="comp_sort" class="form-control input-sm" placeholder="Sort order" value="0"></div>
                                <div class="col-md-4"><label class="checkbox-inline" style="font-size:12px;"><input type="checkbox" name="is_active" id="comp_active" value="1" checked> Active</label></div>
                                <div class="col-md-4"><button type="submit" class="btn btn-primary btn-sm btn-block">Save</button></div>
                            </div>
                            <?php echo form_close(); ?>
                            <hr>
                        <?php } ?>
                        <table class="table table-striped" style="font-size:12px;">
                            <thead><tr><th>Component</th><th>Type</th><th>Default</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($components as $c) { ?>
                                    <tr class="<?php echo $c['is_active'] ? '' : 'text-muted'; ?>">
                                        <td><?php echo html_escape($c['name']); ?><?php echo $c['is_active'] ? '' : ' (inactive)'; ?></td>
                                        <td><span class="label label-<?php echo $c['type'] === 'earning' ? 'success' : 'danger'; ?>"><?php echo ucfirst($c['type']); ?></span></td>
                                        <td><?php echo (float) $c['default_value']; ?><?php echo $c['calc_type'] === 'percent_basic' ? '% of basic' : ''; ?></td>
                                        <td class="text-right">
                                            <?php if ($can_edit) { ?>
                                                <button type="button" class="btn btn-default btn-icon" onclick='editComponent(<?php echo json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                            <?php } ?>
                                            <?php if (has_permission('hr_payroll', '', 'delete') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('hr/delete_salary_component/' . $c['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <p class="text-muted" style="font-size:11px;">Components apply to every employee by default. Override per employee from the Salary Structure tab of the employee profile. Loss-of-pay is calculated from absents, half days and unpaid leave.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate payroll modal -->
    <div class="modal fade" id="generate_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/generate_payroll')); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Generate Payroll</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Month</label>
                        <select name="month" class="form-control">
                            <?php $prev = strtotime('first day of last month');
                            for ($m = 1; $m <= 12; $m++) { ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == date('n', $prev) ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php } ?>
                        </select></div>
                    <div class="form-group"><label>Year</label>
                        <select name="year" class="form-control">
                            <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--) { ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == date('Y', $prev) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php } ?>
                        </select></div>
                    <p class="text-muted" style="font-size:12px;">Re-generating an existing <em>draft</em> run replaces its payslips with fresh calculations. Finalized runs are locked.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    function editComponent(c) {
        $('#comp_id').val(c.id);
        $('#comp_name').val(c.name);
        $('#comp_type').val(c.type);
        $('#comp_calc').val(c.calc_type);
        $('#comp_value').val(c.default_value);
        $('#comp_sort').val(c.sort_order);
        $('#comp_active').prop('checked', c.is_active == 1);
    }
</script>
