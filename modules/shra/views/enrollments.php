<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'enrollments'; include __DIR__ . '/_nav.php'; ?>

    <form method="get" class="shra-toolbar">
        <div class="shra-search grow"><i class="fa fa-search"></i><input type="text" name="q" class="form-control" placeholder="Rider, mobile, enrollment or certificate no." value="<?php echo html_escape($filters['q']); ?>"></div>
        <select name="status" class="form-control" style="width:auto"><option value="">Any status</option><?php foreach (['active', 'completed', 'expired', 'cancelled'] as $s) { ?><option value="<?php echo $s; ?>" <?php echo $filters['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option><?php } ?></select>
        <input type="date" name="from" class="form-control" style="width:auto" value="<?php echo html_escape($filters['from']); ?>">
        <input type="date" name="to" class="form-control" style="width:auto" value="<?php echo html_escape($filters['to']); ?>">
        <button class="shra-btn shra-btn-outline">Filter</button>
    </form>

    <div class="shra-card">
        <?php if (!count($enrollments)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-ticket"></i>No enrollments found.</div>
        <?php } else { $sum = 0; ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Date</th><th>Rider</th><th>Package</th><th>Progress</th><th class="num">Paid</th><th>Invoice</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($enrollments as $e) { $sum += $e->paid_amount; $pct = $e->sessions_total ? round($e->sessions_used / $e->sessions_total * 100) : 0; ?>
                <tr>
                    <td><?php echo _d($e->created_at); ?><span class="sub"><?php echo html_escape($e->enrollment_no); ?></span></td>
                    <td><a href="<?php echo admin_url('shra/rider/' . $e->rider_id); ?>" class="strong"><?php echo html_escape($e->full_name); ?></a><span class="sub"><?php echo html_escape($e->rider_no); ?> · <?php echo html_escape($e->mobile); ?></span></td>
                    <td><?php echo html_escape($e->package_name); ?><span class="sub"><?php echo $e->audience; ?><?php echo $e->is_guest ? ' · guest' : ''; ?></span></td>
                    <td style="min-width:130px"><?php echo (int) $e->sessions_used; ?> / <?php echo (int) $e->sessions_total; ?><div class="shra-progress" style="margin-top:5px"><span style="width:<?php echo $pct; ?>%"></span></div></td>
                    <td class="num strong"><?php echo shra_money($e->paid_amount); ?><?php echo $e->discount_percent > 0 ? '<span class="sub">' . ($e->discount_percent + 0) . '% off ' . shra_money($e->list_price) . '</span>' : ''; ?></td>
                    <td><?php echo $e->invoice_id ? '<a href="' . admin_url('invoices/list_invoices/' . $e->invoice_id) . '">' . html_escape(format_invoice_number($e->invoice_id)) . '</a>' : '—'; ?></td>
                    <td><?php echo shra_status_badge($e->status); ?><?php echo $e->certificate_no ? '<span class="sub">' . html_escape($e->certificate_no) . '</span>' : ''; ?></td>
                    <td style="white-space:nowrap;text-align:right">
                        <?php if ($e->certificate_no) { ?><a href="<?php echo admin_url('shra/certificate_pdf/' . $e->id); ?>" target="_blank" class="shra-btn shra-btn-gold shra-btn-sm" title="Certificate"><i class="fa-solid fa-award"></i></a>
                        <?php } elseif (!$e->is_guest && $e->status === 'completed' && shra_can('edit')) { ?><a href="<?php echo admin_url('shra/certificate/' . $e->id); ?>" class="shra-btn shra-btn-gold shra-btn-sm">Issue certificate</a>
                        <?php } elseif (!$e->is_guest && $e->status === 'active' && shra_can('edit')) { ?><a href="<?php echo admin_url('shra/complete/' . $e->id); ?>" data-shra-confirm="Mark as completed and issue the certificate?" class="shra-btn shra-btn-outline shra-btn-sm" title="Complete course"><i class="fa-solid fa-flag-checkered"></i></a><?php } ?>
                        <?php if ($e->status === 'active' && shra_can('delete')) { ?><a href="<?php echo admin_url('shra/cancel_enrollment/' . $e->id); ?>" data-shra-confirm="Cancel this enrollment? Remaining sessions will be lost." class="shra-btn shra-btn-danger shra-btn-sm" title="Cancel"><i class="fa fa-ban"></i></a><?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
            <tfoot><tr><th colspan="4" style="border-radius:0">Total (<?php echo count($enrollments); ?>)</th><th class="num" style="border-radius:0"><?php echo shra_money($sum); ?></th><th colspan="3" style="border-radius:0"></th></tr></tfoot>
        </table></div>
        <?php } ?>
    </div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
