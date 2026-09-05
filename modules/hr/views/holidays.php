<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit = has_permission('hr_holidays', '', 'edit') || is_admin();
?>
<?php init_head(); ?>
<style>
    .hr-switch { position: relative; display: inline-block; width: 44px; height: 22px; margin: 0; vertical-align: middle; }
    .hr-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .hr-switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 22px; transition: background .2s; }
    .hr-switch-slider:before { content: ""; position: absolute; height: 16px; width: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 2px rgba(0,0,0,.35); }
    .hr-switch input:checked + .hr-switch-slider { background: #16a34a; }
    .hr-switch input:checked + .hr-switch-slider:before { transform: translateX(22px); }
    .hr-switch input:disabled + .hr-switch-slider { opacity: .5; cursor: not-allowed; }
    tr.holiday-off td:not(:nth-last-child(-n+2)) { opacity: .5; }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-3">
                                <select id="holiday_year" class="selectpicker" data-width="100%">
                                    <?php for ($y = (int) date('Y') + 2; $y >= (int) date('Y') - 3; $y--) { ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-9 text-right">
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <?php if ($can_edit) { ?>
                                    <?php echo form_open(admin_url('hr/import_indian_holidays'), ['style' => 'display:inline-block', 'onsubmit' => "return confirm('Add the standard Indian public holidays for " . $year . "? Existing holidays will not be duplicated.');"]); ?>
                                    <input type="hidden" name="year" value="<?php echo $year; ?>">
                                    <button type="submit" class="btn btn-info"><i class="fa fa-flag"></i> Add Indian Holidays</button>
                                    <?php echo form_close(); ?>
                                    <button type="button" class="btn btn-primary" onclick="openHolidayModal()"><i class="fa fa-plus"></i> Add Holiday</button>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Date</th><th>Day</th><th>Holiday</th><th>Type</th><th class="text-center">Show</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php if (!count($holidays)) { ?>
                                    <tr><td colspan="6" class="text-muted">No holidays configured for <?php echo $year; ?>.</td></tr>
                                <?php }
                                foreach ($holidays as $h) {
                                    $is_active = !isset($h['is_active']) || $h['is_active']; ?>
                                    <tr class="holiday-row<?php echo $is_active ? '' : ' holiday-off'; ?>">
                                        <td><?php echo _d($h['holiday_date']); ?></td>
                                        <td><?php echo date('l', strtotime($h['holiday_date'])); ?></td>
                                        <td class="bold"><?php echo html_escape($h['name']); ?></td>
                                        <td><span class="label label-<?php echo $h['is_optional'] ? 'default' : 'info'; ?>"><?php echo $h['is_optional'] ? 'Optional' : 'Public'; ?></span></td>
                                        <td class="text-center">
                                            <label class="hr-switch" title="Show/hide this holiday">
                                                <input type="checkbox" class="holiday-toggle" data-id="<?php echo $h['id']; ?>" <?php echo $is_active ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>>
                                                <span class="hr-switch-slider"></span>
                                            </label>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($can_edit) { ?>
                                                <button type="button" class="btn btn-default btn-icon" onclick='openHolidayModal(<?php echo json_encode($h, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                            <?php } ?>
                                            <?php if (has_permission('hr_holidays', '', 'delete') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('hr/delete_holiday/' . $h['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
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
    </div>

    <div class="modal fade" id="holiday_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_holiday'), ['id' => 'holiday_form']); ?>
                <input type="hidden" name="id" id="holiday_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Holiday</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Name</label>
                        <input type="text" name="name" id="holiday_name" class="form-control" required></div>
                    <div class="form-group"><label>Date</label>
                        <input type="date" name="holiday_date" id="holiday_date" class="form-control" required></div>
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="is_optional" id="holiday_optional" value="1"><label for="holiday_optional">Optional / restricted holiday</label>
                    </div>
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
    var HOLIDAY_CSRF_NAME = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var HOLIDAY_CSRF_HASH = '<?php echo $this->security->get_csrf_hash(); ?>';
    $(function () {
        $('#holiday_year').on('change', function () {
            window.location.href = '<?php echo admin_url('hr/holidays'); ?>?year=' + this.value;
        });

        $(document).on('change', '.holiday-toggle', function () {
            var $cb    = $(this),
                active = $cb.is(':checked') ? 1 : 0,
                data   = { id: $cb.data('id'), active: active };
            data[HOLIDAY_CSRF_NAME] = HOLIDAY_CSRF_HASH;
            $cb.prop('disabled', true);
            $.post('<?php echo admin_url('hr/toggle_holiday'); ?>', data, function (r) {
                if (r && r[HOLIDAY_CSRF_NAME]) { HOLIDAY_CSRF_HASH = r[HOLIDAY_CSRF_NAME]; }
                $cb.closest('tr').toggleClass('holiday-off', active === 0);
            }, 'json').fail(function () {
                $cb.prop('checked', active === 0); // revert
                if (typeof alert_float === 'function') {
                    alert_float('danger', 'Could not update the holiday. Please try again.');
                }
            }).always(function () {
                $cb.prop('disabled', false);
            });
        });
    });
    function openHolidayModal(h) {
        $('#holiday_form')[0].reset();
        $('#holiday_id').val('');
        if (h) {
            $('#holiday_id').val(h.id);
            $('#holiday_name').val(h.name);
            $('#holiday_date').val(h.holiday_date);
            $('#holiday_optional').prop('checked', h.is_optional == 1);
        }
        $('#holiday_modal').modal('show');
    }
</script>
