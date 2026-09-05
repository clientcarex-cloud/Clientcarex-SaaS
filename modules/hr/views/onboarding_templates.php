<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_delete = has_permission('hr_onboarding', '', 'delete') || is_admin();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-list-check text-info"></i> Onboarding Templates</h4>
                            <div>
                                <a href="<?php echo admin_url('hr/onboarding'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                                <button type="button" class="btn btn-primary" onclick="openTplModal()"><i class="fa fa-plus"></i> New Template</button>
                            </div>
                        </div>
                        <p class="text-muted" style="margin:6px 0 0;">Reusable checklists applied when you start onboarding for an employee. Task due dates are offset (in days) from the employee's start date — use negative numbers for pre-boarding.</p>
                        <hr class="hr-panel-heading" />

                        <?php foreach ($templates as $t) { ?>
                            <div class="panel-body" style="border:1px solid #e5e7eb;border-radius:8px;margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                    <div>
                                        <strong style="font-size:15px;"><?php echo html_escape($t['name']); ?></strong>
                                        <?php if (!empty($t['is_default'])) { ?><span class="label label-info">Default</span><?php } ?>
                                        <?php if (empty($t['is_active'])) { ?><span class="label label-default">Inactive</span><?php } ?>
                                        <span class="text-muted" style="font-size:12px;"> · <?php echo count($t['items']); ?> tasks</span>
                                        <?php if (!empty($t['description'])) { ?><div class="text-muted" style="font-size:12px;"><?php echo html_escape($t['description']); ?></div><?php } ?>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-default btn-sm" onclick='openTplModal(<?php echo json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i> Edit</button>
                                        <?php if ($can_delete && empty($t['is_default'])) { ?>
                                            <a href="<?php echo admin_url('hr/delete_onboarding_template/' . $t['id']); ?>" class="btn btn-danger btn-sm _delete"><i class="fa fa-remove"></i></a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tpl_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_onboarding_template'), ['id' => 'tpl_form']); ?>
                <input type="hidden" name="id" id="tpl_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Onboarding Template</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8"><div class="form-group"><label>Template name <small class="req text-danger">*</small></label>
                            <input type="text" name="name" id="tpl_name" class="form-control" maxlength="150" required></div></div>
                        <div class="col-md-4"><div class="form-group"><label>&nbsp;</label>
                            <div class="checkbox checkbox-primary" style="margin-top:6px;">
                                <input type="checkbox" name="is_active" id="tpl_active" value="1" checked>
                                <label for="tpl_active">Active</label>
                            </div></div></div>
                    </div>
                    <div class="form-group"><label>Description</label>
                        <input type="text" name="description" id="tpl_description" class="form-control" maxlength="255"></div>

                    <label>Tasks</label>
                    <table class="table" id="tpl_items_table">
                        <thead><tr><th style="width:35%;">Task</th><th>Description</th><th style="width:130px;">Phase</th><th style="width:90px;">Due (days)</th><th style="width:40px;"></th></tr></thead>
                        <tbody id="tpl_items_body"></tbody>
                    </table>
                    <button type="button" class="btn btn-default btn-sm" onclick="addTplRow()"><i class="fa fa-plus"></i> Add task</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    var tplPhases = <?php echo json_encode(array_map(function ($p) { return $p['label']; }, $phases)); ?>;

    function tplPhaseOptions(sel) {
        var h = '';
        for (var k in tplPhases) {
            h += '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + tplPhases[k] + '</option>';
        }
        return h;
    }
    function addTplRow(it) {
        it = it || {};
        var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
        var row = '<tr>' +
            '<td><input type="text" name="item_title[]" class="form-control input-sm" value="' + esc(it.title) + '"></td>' +
            '<td><input type="text" name="item_description[]" class="form-control input-sm" value="' + esc(it.description) + '"></td>' +
            '<td><select name="item_phase[]" class="form-control input-sm">' + tplPhaseOptions(it.phase || 'first_day') + '</select></td>' +
            '<td><input type="number" name="item_offset[]" class="form-control input-sm" value="' + (it.due_offset_days != null ? esc(it.due_offset_days) : 0) + '"></td>' +
            '<td class="text-center"><a href="#" class="text-danger tpl-del"><i class="fa fa-remove"></i></a></td>' +
            '</tr>';
        $('#tpl_items_body').append(row);
    }
    $(function () {
        $('#tpl_items_body').on('click', '.tpl-del', function (e) { e.preventDefault(); $(this).closest('tr').remove(); });
    });
    function openTplModal(t) {
        var f = $('#tpl_form')[0];
        f.reset();
        $('#tpl_id').val('');
        $('#tpl_items_body').empty();
        $('#tpl_active').prop('checked', true);
        if (t) {
            $('#tpl_id').val(t.id);
            $('#tpl_name').val(t.name);
            $('#tpl_description').val(t.description || '');
            $('#tpl_active').prop('checked', parseInt(t.is_active, 10) === 1);
            (t.items || []).forEach(function (it) { addTplRow(it); });
        }
        if (!$('#tpl_items_body tr').length) { addTplRow(); }
        $('#tpl_modal').modal('show');
    }
</script>
