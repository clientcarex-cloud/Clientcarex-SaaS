<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit   = has_permission('hr_payroll', '', 'edit') || is_admin();
$can_delete = has_permission('hr_payroll', '', 'delete') || is_admin();
$currency   = get_base_currency();
$period   = date('F Y', mktime(0, 0, 0, $run['month'], 1, $run['year']));

$totals = ['gross' => 0, 'deductions' => 0, 'lop' => 0, 'net' => 0];
foreach ($payslips as $p) {
    $totals['gross'] += $p['gross'];
    $totals['deductions'] += $p['total_deductions'];
    $totals['lop'] += $p['lop_amount'];
    $totals['net'] += $p['net_pay'];
}
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="bold" style="margin:0;">
                                    Payroll &mdash; <?php echo $period; ?>
                                    <span class="label label-<?php echo $run['status'] === 'finalized' ? 'success' : 'warning'; ?>"><?php echo ucfirst($run['status']); ?></span>
                                </h4>
                                <span class="text-muted"><?php echo count($payslips); ?> payslips &middot; Net total <?php echo app_format_money($totals['net'], $currency); ?></span>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('hr/payroll'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> All Runs</a>
                                <?php if ($run['status'] === 'draft' && $can_edit) { ?>
                                    <a href="<?php echo admin_url('hr/finalize_payroll/' . $run['id']); ?>" class="btn btn-success"
                                       onclick="return confirm('Finalize this payroll? Payslips will be locked against re-generation.');">
                                        <i class="fa fa-lock"></i> Finalize
                                    </a>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />

                        <table class="table table-striped dt-table">
                            <thead>
                                <tr>
                                    <th>Employee</th><th>Code</th><th>Basic</th><th>Gross</th><th>LOP Days</th><th>LOP Amt</th>
                                    <th>Deductions</th><th>Net Pay</th><th>Status</th><th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payslips as $p) { ?>
                                    <tr>
                                        <td><a href="<?php echo admin_url('hr/employee/' . $p['staff_id']); ?>"><?php echo html_escape($p['firstname'] . ' ' . $p['lastname']); ?></a>
                                            <?php if ($p['designation']) { ?><div class="text-muted" style="font-size:11px;"><?php echo html_escape($p['designation']); ?></div><?php } ?></td>
                                        <td><?php echo html_escape($p['employee_code']); ?></td>
                                        <td data-order="<?php echo $p['basic']; ?>"><?php echo app_format_money($p['basic'], $currency); ?></td>
                                        <td data-order="<?php echo $p['gross']; ?>"><?php echo app_format_money($p['gross'], $currency); ?></td>
                                        <td><?php echo (float) $p['lop_days']; ?></td>
                                        <td data-order="<?php echo $p['lop_amount']; ?>"><?php echo app_format_money($p['lop_amount'], $currency); ?></td>
                                        <td data-order="<?php echo $p['total_deductions']; ?>"><?php echo app_format_money($p['total_deductions'], $currency); ?></td>
                                        <td data-order="<?php echo $p['net_pay']; ?>" class="bold"><?php echo app_format_money($p['net_pay'], $currency); ?></td>
                                        <td>
                                            <?php if ($p['status'] === 'paid') { ?>
                                                <span class="label label-success">Paid</span>
                                                <div class="text-muted" style="font-size:10px;"><?php echo _d($p['paid_date']); ?><?php echo $p['payment_mode'] ? ' &middot; ' . html_escape($p['payment_mode']) : ''; ?></div>
                                            <?php } else { ?>
                                                <span class="label label-default">Unpaid</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-right" style="min-width:100px;">
                                            <a href="<?php echo admin_url('hr/payslip/' . $p['id']); ?>" class="btn btn-default btn-icon" data-toggle="tooltip" title="View / Print Payslip"><i class="fa fa-file-text-o"></i></a>
                                            <?php if ($p['status'] !== 'paid' && $run['status'] === 'finalized' && $can_edit) { ?>
                                                <button type="button" class="btn btn-success btn-icon" data-toggle="tooltip" title="Mark Paid" onclick="markPaid(<?php echo $p['id']; ?>)"><i class="fa fa-check"></i></button>
                                            <?php } ?>
                                            <?php if ($can_delete) { ?>
                                                <a href="<?php echo admin_url('hr/delete_payslip/' . $p['id']); ?>" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete this payslip"><i class="fa fa-remove"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr class="bold">
                                    <td colspan="3">Totals</td>
                                    <td><?php echo app_format_money($totals['gross'], $currency); ?></td>
                                    <td></td>
                                    <td><?php echo app_format_money($totals['lop'], $currency); ?></td>
                                    <td><?php echo app_format_money($totals['deductions'], $currency); ?></td>
                                    <td><?php echo app_format_money($totals['net'], $currency); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                        <?php if ($run['status'] === 'draft') { ?>
                            <p class="text-muted">Draft: adjust salary structures or attendance, then re-generate from the Payroll page. Mark-paid becomes available after finalizing.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="paid_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <form method="post" id="paid_form" action="">
                    <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title">Mark Payslip Paid</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group"><label>Payment Date</label>
                            <input type="date" name="paid_date" id="paid_date_input" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            <small class="text-muted">Set the actual date paid — you can backdate it for older payslips.</small></div>
                        <div class="form-group"><label>Payment Mode</label>
                            <select name="payment_mode" class="form-control">
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                                <option value="UPI">UPI</option>
                            </select></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                        <button type="submit" class="btn btn-success">Mark Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    function markPaid(id) {
        $('#paid_form').attr('action', '<?php echo admin_url('hr/mark_paid/'); ?>' + id);
        $('#paid_modal').modal('show');
    }
</script>
