<?php defined('BASEPATH') or exit('No direct script access allowed');

$currency = get_base_currency();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 style="margin-top:6px;font-weight:800;"><i class="fa fa-money-bill"></i> My Payslips</h4>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Period</th><th class="text-right">Gross</th><th class="text-right">Deductions</th><th class="text-right">LOP</th><th class="text-right">Net Pay</th><th></th></tr></thead>
                            <tbody>
                                <?php if (!count($payslips)) { ?>
                                    <tr><td colspan="6" class="text-muted">No payslips available yet. They appear here once payroll is finalized.</td></tr>
                                <?php }
                                foreach ($payslips as $p) { ?>
                                    <tr>
                                        <td class="bold"><?php echo date('F Y', mktime(0, 0, 0, $p['month'], 1, $p['year'])); ?></td>
                                        <td class="text-right"><?php echo app_format_money($p['gross'], $currency); ?></td>
                                        <td class="text-right"><?php echo app_format_money($p['total_deductions'], $currency); ?></td>
                                        <td class="text-right"><?php echo (float) $p['lop_days']; ?></td>
                                        <td class="text-right bold" style="color:#166534;"><?php echo app_format_money($p['net_pay'], $currency); ?></td>
                                        <td class="text-right"><a href="<?php echo admin_url('hr/myhr/payslip/' . $p['id']); ?>" class="btn btn-default btn-sm"><i class="fa fa-eye"></i> View</a></td>
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
