<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <?php if (!empty($active_filter)) {
                            $filter_labels = ['new_joiners' => 'New Joiners (joined in the last 30 days)', 'probation' => 'On Probation'];
                        ?>
                            <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;">
                                <span><i class="fa fa-filter"></i> Showing: <strong><?php echo html_escape($filter_labels[$active_filter]); ?></strong></span>
                                <a href="<?php echo admin_url('hr/employees'); ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Clear filter</a>
                            </div>
                        <?php } ?>
                        <div class="row">
                            <div class="col-md-3">
                                <select id="department_filter" class="selectpicker" data-width="100%" data-none-selected-text="All Departments">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $d) { ?>
                                        <option value="<?php echo $d['departmentid']; ?>"><?php echo html_escape($d['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="employment_type_filter" class="selectpicker" data-width="100%">
                                    <option value="">All Employment Types</option>
                                    <option value="permanent">Permanent</option>
                                    <option value="contract">Contract</option>
                                    <option value="probation">Probation</option>
                                    <option value="consultant">Consultant</option>
                                    <option value="part_time">Part Time</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="status_filter" class="selectpicker" data-width="100%">
                                    <option value="1" selected>Active Staff</option>
                                    <option value="0">Inactive Staff</option>
                                    <option value="">All</option>
                                </select>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <a href="<?php echo admin_url('hr/export_employees'); ?>" class="btn btn-default" data-toggle="tooltip" title="Download all employees as an Excel-compatible CSV — edit it and import it back to bulk-update">
                                    <i class="fa fa-download"></i> Export
                                </a>
                                <?php if (has_permission('hr_employees', '', 'create') || is_admin()) { ?>
                                    <a href="<?php echo admin_url('hr/import_employees'); ?>" class="btn btn-default" data-toggle="tooltip" title="Bulk add new employees or bulk update existing ones from a CSV sheet">
                                        <i class="fa fa-upload"></i> Import
                                    </a>
                                <?php } ?>
                                <?php if (!empty($idcard_templates)) { ?>
                                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#bulk_idcard_modal" title="Print many employee ID cards on shared pages to save paper">
                                        <i class="fa fa-id-card-o"></i> Print ID Cards
                                    </button>
                                <?php } ?>
                                <?php if (has_permission('hr_employees', '', 'create') || is_admin()) { ?>
                                    <a href="<?php echo admin_url('hr/sync_employees'); ?>" class="btn btn-default" data-toggle="tooltip" title="Create HR profiles for all existing staff members that do not have one yet">
                                        <i class="fa fa-refresh"></i> Sync from Staff
                                    </a>
                                <?php } ?>
                                <?php if (has_permission('hr_employees', '', 'edit') || is_admin()) { ?>
                                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#designations_modal">
                                        <i class="fa fa-tags"></i> Designations
                                    </button>
                                <?php } ?>
                                <?php if (has_permission('staff', '', 'create') || is_admin()) { ?>
                                    <a href="<?php echo admin_url('staff/member'); ?>" class="btn btn-primary"><i class="fa fa-plus"></i> New Staff</a>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>

                        <?php render_datatable([
                            'Employee',
                            'Code',
                            'Department',
                            'Designation',
                            'Type',
                            'Joined',
                            'Status',
                            'Last Login',
                            'Actions',
                        ], 'hr-employees'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Designations modal -->
    <div class="modal fade" id="designations_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Designations</h4>
                </div>
                <div class="modal-body">
                    <?php echo form_open(admin_url('hr/save_designation')); ?>
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control" placeholder="Designation name" required>
                        </div>
                        <div class="col-md-3">
                            <select name="department_id" class="form-control">
                                <option value="">Any department</option>
                                <?php foreach ($departments as $d) { ?>
                                    <option value="<?php echo $d['departmentid']; ?>"><?php echo html_escape($d['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="role_id" class="form-control">
                                <option value="">No mapped role</option>
                                <?php foreach ($roles as $r) { ?>
                                    <option value="<?php echo $r['roleid']; ?>"><?php echo html_escape($r['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="hidden" name="is_active" value="1">
                            <button type="submit" class="btn btn-primary btn-block">Add</button>
                        </div>
                    </div>
                    <?php echo form_close(); ?>
                    <hr>
                    <?php
                    $hr_role_names = [];
                    foreach ($roles as $r) { $hr_role_names[(int) $r['roleid']] = $r['name']; }
                    ?>
                    <table class="table table-striped">
                        <thead><tr><th>Name</th><th>Mapped Role</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($designations as $dg) { ?>
                                <tr>
                                    <td><?php echo html_escape($dg['name']); ?></td>
                                    <td><?php echo !empty($dg['role_id']) && isset($hr_role_names[(int) $dg['role_id']]) ? html_escape($hr_role_names[(int) $dg['role_id']]) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                    <td class="text-right">
                                        <?php if (has_permission('hr_employees', '', 'delete') || is_admin()) { ?>
                                            <a href="<?php echo admin_url('hr/delete_designation/' . $dg['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($idcard_templates)) { ?>
    <!-- Bulk ID-card print -->
    <div class="modal fade" id="bulk_idcard_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <?php echo form_open(admin_url('smart_pdf/bulk_generate'), ['id' => 'bulk_idcard_form', 'target' => '_blank']); ?>
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-id-card-o"></i> Print Employee ID Cards</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Select employees and the card design. All the selected cards are laid out on shared pages so you print several per sheet instead of one card per page.</p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="control-label">Front side <small class="req text-danger">*</small></label>
                            <select name="template_id" class="form-control" required>
                                <?php foreach ($idcard_templates as $t) { ?>
                                    <option value="<?php echo (int) $t['id']; ?>"><?php echo html_escape($t['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="control-label">Back side <small class="text-muted">(optional)</small></label>
                            <select name="back_template_id" class="form-control">
                                <option value="">— None (front only) —</option>
                                <?php foreach ($idcard_templates as $t) { ?>
                                    <option value="<?php echo (int) $t['id']; ?>"><?php echo html_escape($t['name']); ?></option>
                                <?php } ?>
                            </select>
                            <p class="help-block">Backs print on separate pages in the same order, so a double-sided print lines up.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label">Employees</label>
                        <div class="row" style="margin-bottom:8px;">
                            <div class="col-md-8">
                                <input type="text" id="bulk_idcard_search" class="form-control" placeholder="Search by name, code, department...">
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="#" id="bulk_idcard_all" class="btn btn-default btn-sm">Select all</a>
                                <a href="#" id="bulk_idcard_none" class="btn btn-default btn-sm">None</a>
                            </div>
                        </div>
                        <div id="bulk_idcard_list" style="max-height:320px; overflow-y:auto; border:1px solid #e0e6ea; border-radius:6px; padding:6px;">
                            <?php foreach ($idcard_employees as $emp) {
                                $meta = array_filter([$emp['code'], $emp['designation'], $emp['department']]);
                                $hay  = strtolower($emp['name'] . ' ' . implode(' ', $meta));
                            ?>
                                <label class="bulk-idcard-row" data-search="<?php echo html_escape($hay); ?>"
                                    style="display:flex; align-items:center; gap:9px; padding:6px 8px; margin:0; border-bottom:1px solid #f1f4f6; cursor:pointer; font-weight:normal;">
                                    <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['staffid']; ?>" checked style="margin:0;">
                                    <span>
                                        <span style="font-weight:600;"><?php echo html_escape($emp['name']); ?></span>
                                        <?php if ($meta) { ?><span class="text-muted" style="font-size:12px;"> — <?php echo html_escape(implode(' · ', $meta)); ?></span><?php } ?>
                                    </span>
                                </label>
                            <?php } ?>
                            <?php if (empty($idcard_employees)) { ?>
                                <p class="text-muted" style="padding:8px; margin:0;">No employees found.</p>
                            <?php } ?>
                        </div>
                        <p class="help-block"><span id="bulk_idcard_count"><?php echo count($idcard_employees); ?></span> employee(s) selected.</p>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="cut_guides" id="bulk_idcard_guides" value="1">
                        <label for="bulk_idcard_guides">Show dashed cut guides around each card</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info"><i class="fa fa-print"></i> Print ID Cards</button>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
    <?php } ?>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        var HrServerParams = {
            "status": "[id='status_filter']",
            "department": "[id='department_filter']",
            "employment_type": "[id='employment_type_filter']"
        };
        var table = initDataTable('.table-hr-employees', window.location.href, [8], [8], HrServerParams, [0, 'asc']);
        $('#status_filter, #department_filter, #employment_type_filter').on('change', function () {
            table.ajax.reload();
        });

        // ---- Bulk ID-card modal ----
        var $list = $('#bulk_idcard_list');
        if ($list.length) {
            function refreshCount() {
                $('#bulk_idcard_count').text($list.find('input[type=checkbox]:checked').length);
            }
            // Filter rows (only toggles visibility; hidden rows keep their state)
            $('#bulk_idcard_search').on('keyup', function () {
                var q = $.trim(this.value.toLowerCase());
                $list.find('.bulk-idcard-row').each(function () {
                    var match = q === '' || $(this).attr('data-search').indexOf(q) !== -1;
                    $(this).toggle(match);
                });
            });
            // Select all / none applies to the currently VISIBLE rows only
            $('#bulk_idcard_all').on('click', function (e) {
                e.preventDefault();
                $list.find('.bulk-idcard-row:visible input[type=checkbox]').prop('checked', true);
                refreshCount();
            });
            $('#bulk_idcard_none').on('click', function (e) {
                e.preventDefault();
                $list.find('.bulk-idcard-row:visible input[type=checkbox]').prop('checked', false);
                refreshCount();
            });
            $list.on('change', 'input[type=checkbox]', refreshCount);

            $('#bulk_idcard_form').on('submit', function (e) {
                if ($list.find('input[type=checkbox]:checked').length === 0) {
                    e.preventDefault();
                    alert_float('warning', 'Please select at least one employee.');
                }
            });
        }
    });
</script>
