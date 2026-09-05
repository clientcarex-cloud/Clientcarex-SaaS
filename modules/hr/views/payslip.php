<?php defined('BASEPATH') or exit('No direct script access allowed');

$currency   = get_base_currency();
$earnings   = json_decode($slip['earnings'], true) ?: [];
$deductions = json_decode($slip['deductions'], true) ?: [];
$period     = date('F Y', mktime(0, 0, 0, $slip['month'], 1, $slip['year']));
$company    = get_option('invoice_company_name') ?: get_option('companyname');

// Company logo for the payslip header (toggle in HR Settings; on by default).
// Prefer the "dark" logo variant — it is the dark-coloured mark meant for
// light/white backgrounds like this payslip.
$show_logo = get_option('hr_payslip_show_logo') !== '0';
$logo_file = get_option('company_logo_dark') ?: get_option('company_logo');
$logo_url  = ($show_logo && $logo_file) ? base_url('uploads/company/' . $logo_file) : '';
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="text-right hidden-print" style="margin-bottom:10px;">
                            <a href="<?php echo admin_url('hr/payroll_run/' . $slip['run_id']); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print / Save PDF</button>
                        </div>

                        <div id="payslip_area" style="border:1px solid #e2e8f0;border-radius:8px;padding:30px;">
                            <div style="text-align:center;border-bottom:2px solid #334155;padding-bottom:15px;margin-bottom:20px;">
                                <?php if ($logo_url) { ?>
                                    <img src="<?php echo $logo_url; ?>" alt="<?php echo html_escape($company); ?>" style="max-height:70px;max-width:260px;margin-bottom:10px;">
                                <?php } ?>
                                <h3 style="margin:0;font-weight:800;"><?php echo html_escape($company); ?></h3>
                                <p style="margin:5px 0 0;font-size:15px;">Payslip for <?php echo $period; ?></p>
                                <?php if ($slip['run_status'] === 'draft') { ?>
                                    <span class="label label-warning">DRAFT - not finalized</span>
                                <?php } ?>
                            </div>

                            <table style="width:100%;font-size:13px;margin-bottom:20px;">
                                <tr>
                                    <td style="padding:3px 0;width:20%;color:#64748b;">Employee</td>
                                    <td style="padding:3px 0;width:30%;font-weight:700;"><?php echo html_escape($slip['firstname'] . ' ' . $slip['lastname']); ?></td>
                                    <td style="padding:3px 0;width:20%;color:#64748b;">Employee Code</td>
                                    <td style="padding:3px 0;width:30%;"><?php echo html_escape($slip['employee_code']); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Designation</td>
                                    <td style="padding:3px 0;"><?php echo html_escape($slip['designation'] ?: '-'); ?></td>
                                    <td style="padding:3px 0;color:#64748b;">Department</td>
                                    <td style="padding:3px 0;"><?php echo html_escape($department ? $department->name : '-'); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Date of Joining</td>
                                    <td style="padding:3px 0;"><?php echo $slip['date_of_joining'] ? _d($slip['date_of_joining']) : '-'; ?></td>
                                    <td style="padding:3px 0;color:#64748b;">PAN</td>
                                    <td style="padding:3px 0;"><?php echo html_escape($slip['pan_number'] ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Bank</td>
                                    <td style="padding:3px 0;"><?php echo html_escape($slip['bank_name'] ?: '-'); ?></td>
                                    <td style="padding:3px 0;color:#64748b;">Account No.</td>
                                    <td style="padding:3px 0;"><?php echo html_escape($slip['bank_account_no'] ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">PF / UAN</td>
                                    <td style="padding:3px 0;"><?php echo html_escape($slip['pf_uan'] ?: '-'); ?></td>
                                    <td style="padding:3px 0;color:#64748b;">ESI No.</td>
                                    <td style="padding:3px 0;"><?php echo html_escape($slip['esi_number'] ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Payable Days</td>
                                    <td style="padding:3px 0;"><?php echo (float) $slip['payable_days']; ?></td>
                                    <td style="padding:3px 0;color:#64748b;">LOP Days</td>
                                    <td style="padding:3px 0;"><?php echo (float) $slip['lop_days']; ?></td>
                                </tr>
                            </table>

                            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                <tr style="background:#f1f5f9;">
                                    <th style="border:1px solid #cbd5e1;padding:8px;text-align:left;width:35%;">Earnings</th>
                                    <th style="border:1px solid #cbd5e1;padding:8px;text-align:right;width:15%;">Amount</th>
                                    <th style="border:1px solid #cbd5e1;padding:8px;text-align:left;width:35%;">Deductions</th>
                                    <th style="border:1px solid #cbd5e1;padding:8px;text-align:right;width:15%;">Amount</th>
                                </tr>
                                <?php
                                $left  = array_merge([['name' => 'Basic Salary', 'amount' => $slip['basic']]], $earnings);
                                $right = $deductions;
                                if ((float) $slip['lop_amount'] > 0) {
                                    $right[] = ['name' => 'Loss of Pay (' . (float) $slip['lop_days'] . ' days)', 'amount' => $slip['lop_amount']];
                                }
                                $rows = max(count($left), count($right));
                                for ($i = 0; $i < $rows; $i++) { ?>
                                    <tr>
                                        <td style="border:1px solid #cbd5e1;padding:6px 8px;"><?php echo isset($left[$i]) ? html_escape($left[$i]['name']) : ''; ?></td>
                                        <td style="border:1px solid #cbd5e1;padding:6px 8px;text-align:right;"><?php echo isset($left[$i]) ? app_format_money($left[$i]['amount'], $currency) : ''; ?></td>
                                        <td style="border:1px solid #cbd5e1;padding:6px 8px;"><?php echo isset($right[$i]) ? html_escape($right[$i]['name']) : ''; ?></td>
                                        <td style="border:1px solid #cbd5e1;padding:6px 8px;text-align:right;"><?php echo isset($right[$i]) ? app_format_money($right[$i]['amount'], $currency) : ''; ?></td>
                                    </tr>
                                <?php } ?>
                                <tr style="background:#f8fafc;font-weight:700;">
                                    <td style="border:1px solid #cbd5e1;padding:8px;">Gross Earnings</td>
                                    <td style="border:1px solid #cbd5e1;padding:8px;text-align:right;"><?php echo app_format_money($slip['gross'], $currency); ?></td>
                                    <td style="border:1px solid #cbd5e1;padding:8px;">Total Deductions</td>
                                    <td style="border:1px solid #cbd5e1;padding:8px;text-align:right;"><?php echo app_format_money($slip['total_deductions'] + $slip['lop_amount'], $currency); ?></td>
                                </tr>
                            </table>

                            <div style="margin-top:20px;padding:14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-weight:700;font-size:15px;">NET PAY</span>
                                <span style="font-weight:800;font-size:20px;"><?php echo app_format_money($slip['net_pay'], $currency); ?></span>
                            </div>

                            <?php if ($slip['status'] === 'paid') { ?>
                                <p style="margin-top:12px;font-size:12px;color:#16a34a;font-weight:600;">
                                    Paid on <?php echo _d($slip['paid_date']); ?><?php echo $slip['payment_mode'] ? ' via ' . html_escape($slip['payment_mode']) : ''; ?>
                                </p>
                            <?php } ?>

                            <p style="margin-top:25px;font-size:11px;color:#94a3b8;text-align:center;">
                                This is a system-generated payslip and does not require a signature.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    @media print {
        /* Hide the whole admin chrome (sidebar, header, menus) and reveal ONLY
           the payslip — selector-agnostic, so it works regardless of theme. */
        body * { visibility: hidden !important; }
        #payslip_area, #payslip_area * { visibility: visible !important; }
        .hidden-print { display: none !important; }

        #payslip_area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        #wrapper, .content, .panel_s, .panel-body, .row, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            width: auto !important;
            float: none !important;
        }
        /* Keep the coloured backgrounds in the printout. */
        #payslip_area * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        @page { margin: 12mm; }
    }
</style>
<?php init_tail(); ?>
