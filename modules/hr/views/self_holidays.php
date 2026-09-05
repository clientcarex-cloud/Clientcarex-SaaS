<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6"><h4 style="margin-top:6px;font-weight:800;"><i class="fa fa-calendar"></i> Holidays <?php echo $year; ?></h4></div>
                            <div class="col-md-6 text-right">
                                <select id="holiday_year" class="selectpicker" data-width="140px">
                                    <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 2; $y--) { ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Date</th><th>Day</th><th>Holiday</th><th>Type</th></tr></thead>
                            <tbody>
                                <?php if (!count($holidays)) { ?>
                                    <tr><td colspan="4" class="text-muted">No holidays published for <?php echo $year; ?>.</td></tr>
                                <?php }
                                foreach ($holidays as $h) {
                                    $past = $h['holiday_date'] < date('Y-m-d'); ?>
                                    <tr <?php echo $past ? 'style="opacity:.55;"' : ''; ?>>
                                        <td><?php echo _d($h['holiday_date']); ?></td>
                                        <td><?php echo date('l', strtotime($h['holiday_date'])); ?></td>
                                        <td class="bold"><?php echo html_escape($h['name']); ?></td>
                                        <td><span class="label label-<?php echo $h['is_optional'] ? 'default' : 'info'; ?>"><?php echo $h['is_optional'] ? 'Optional' : 'Public'; ?></span></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        $('#holiday_year').on('change', function () {
            window.location.href = '<?php echo admin_url('hr/myhr/holidays'); ?>?year=' + this.value;
        });
    });
</script>
