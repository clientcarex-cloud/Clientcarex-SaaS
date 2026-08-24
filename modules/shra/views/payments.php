<?php defined('BASEPATH') or exit('No direct script access allowed');
/* Superadmin money ledger: every invoice, every receipt, every advance taken on a call. */
$s     = $summary;
$q     = (string) $filters['q'];
$scope = $filters['scope'];
$status = (string) $filters['status'];
$days  = max(1, (strtotime($to) - strtotime($from)) / 86400 + 1);

/* The query the user is looking at — deletes post it back so we return to the same list. */
$back  = http_build_query(array_filter([
    'view'  => $view,
    'range' => $range,
    'from'  => $range === 'custom' ? $from : null,
    'to'    => $range === 'custom' ? $to : null,
    'q'     => $q !== '' ? $q : null,
    'scope' => $scope !== 'all' ? $scope : null,
    'status' => $status !== '' ? $status : null,
], function ($v) { return $v !== null && $v !== ''; }));

/* Same list, one parameter changed. */
$url = function (array $over = []) use ($view, $range, $from, $to, $q, $scope, $status) {
    $args = array_merge(['view' => $view, 'range' => $range, 'from' => $from, 'to' => $to, 'q' => $q, 'scope' => $scope, 'status' => $status], $over);

    return admin_url('shra/payments?' . http_build_query(array_filter($args, function ($v) { return (string) $v !== ''; })));
};

/* Delete button + its POST form. Nothing destructive ever sits behind a plain link. */
$del = function ($action, $id, $confirm, $label = '') use ($back) {
    $o  = '<form method="post" action="' . admin_url('shra/' . $action . '/' . (int) $id) . '" style="display:inline">';
    $o .= form_hidden(get_instance()->security->get_csrf_token_name(), get_instance()->security->get_csrf_hash());
    $o .= '<input type="hidden" name="back" value="' . html_escape($back) . '">';
    $o .= '<button type="submit" class="shra-btn shra-btn-danger shra-btn-sm" data-shra-confirm="' . html_escape($confirm) . '"><i class="fa fa-trash"></i>' . ($label ? ' ' . $label : '') . '</button>';

    return $o . '</form>';
};

$tabs = [
    'invoices' => ['fa-file-invoice-dollar', 'Invoices', (int) $s['invoices']],
    'receipts' => ['fa-receipt', 'Payment receipts', (int) $s['receipts']],
    'advances' => ['fa-hand-holding-dollar', 'Advances on calls', null],
];
?>
<?php init_head(); ?>
<style>
.shra-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
@media(max-width:900px){.shra-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.shra-kpi{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px}
.shra-kpi .l{font-size:10.5px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);font-weight:700}
.shra-kpi .v{font-size:24px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums}
.shra-kpi .s{font-size:12px;color:var(--muted);margin-top:2px}
.shra-vtabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}
.shra-vtabs a{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:13px;font-weight:600;color:var(--ink-2)!important}
.shra-vtabs a:hover{border-color:var(--gold-2)}
.shra-vtabs a.active{background:var(--ink);border-color:var(--ink);color:var(--cream)!important}
.shra-vtabs a .n{font-variant-numeric:tabular-nums;opacity:.75;font-weight:700}
.shra-proof{width:34px;height:34px;border-radius:8px;object-fit:cover;border:1px solid var(--line);display:block}
.shra-danger-note{background:#fdf3f2;border:1px solid #f0d2d0;color:var(--red);border-radius:12px;padding:10px 14px;font-size:12.5px;margin-bottom:14px}
</style>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'payments'; include __DIR__ . '/_nav.php'; ?>

    <form method="get" class="shra-toolbar" id="shra-pay-form">
        <input type="hidden" name="view" value="<?php echo html_escape($view); ?>">
        <?php if ($view !== 'advances') { ?><input type="hidden" name="scope" value="<?php echo html_escape($scope); ?>"><input type="hidden" name="status" value="<?php echo html_escape($status); ?>"><?php } ?>
        <div class="shra-seg" style="margin:0;flex-wrap:wrap">
            <?php foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month', 'quarter' => '3 months', 'year' => 'This year', 'all' => 'All time'] as $kk => $l) { ?>
                <label><input type="radio" name="range" value="<?php echo $kk; ?>" <?php echo $range === $kk ? 'checked' : ''; ?> onchange="this.form.submit()"><span><?php echo $l; ?></span></label>
            <?php } ?>
            <label><input type="radio" name="range" value="custom" <?php echo $range === 'custom' ? 'checked' : ''; ?>><span>Custom</span></label>
        </div>
        <input type="date" name="from" class="form-control" style="width:auto" value="<?php echo $from; ?>" onchange="document.querySelector('#shra-pay-form [value=custom]').checked=true">
        <span class="shra-muted">to</span>
        <input type="date" name="to" class="form-control" style="width:auto" value="<?php echo $to; ?>" onchange="document.querySelector('#shra-pay-form [value=custom]').checked=true">
        <div class="shra-search grow" style="min-width:220px"><i class="fa fa-search"></i><input type="text" name="q" class="form-control" placeholder="Invoice no., rider, mobile, reference…" value="<?php echo html_escape($q); ?>"></div>
        <button class="shra-btn shra-btn-primary shra-btn-sm">Apply</button>
        <?php if ($q !== '' || $range !== 'month') { ?><a href="<?php echo admin_url('shra/payments?view=' . $view); ?>" class="shra-btn shra-btn-outline shra-btn-sm">Reset</a><?php } ?>
        <span class="shra-pill" style="margin-left:auto"><?php echo _d($from); ?> – <?php echo _d($to); ?> · <?php echo (int) $days; ?> day<?php echo $days == 1 ? '' : 's'; ?></span>
    </form>

    <div class="shra-kpis">
        <div class="shra-kpi"><div class="l">Billed</div><div class="v"><?php echo shra_money($s['billed']); ?></div><div class="s"><?php echo (int) $s['invoices']; ?> invoice<?php echo $s['invoices'] == 1 ? '' : 's'; ?> in this range</div></div>
        <div class="shra-kpi"><div class="l">Collected</div><div class="v" style="color:var(--green)"><?php echo shra_money($s['collected']); ?></div><div class="s"><?php echo (int) $s['receipts']; ?> receipt<?php echo $s['receipts'] == 1 ? '' : 's'; ?></div></div>
        <div class="shra-kpi"><div class="l">Advances on calls</div><div class="v"><?php echo shra_money($s['advances']); ?></div><div class="s">taken before a bill was raised</div></div>
        <div class="shra-kpi"><div class="l">Balance due</div><div class="v" style="<?php echo $s['due'] > 0.009 ? 'color:var(--red)' : ''; ?>"><?php echo shra_money($s['due']); ?></div><div class="s"><?php echo $s['due'] > 0.009 ? 'open on invoices in this range' : 'everything settled'; ?></div></div>
    </div>

    <div class="shra-vtabs">
        <?php foreach ($tabs as $k => $t) { ?>
            <a href="<?php echo $url(['view' => $k]); ?>" class="<?php echo $view === $k ? 'active' : ''; ?>"><i class="fa-solid <?php echo $t[0]; ?>"></i> <?php echo $t[1]; ?><?php if ($t[2] !== null) { ?> <span class="n"><?php echo $t[2]; ?></span><?php } ?></a>
        <?php } ?>
    </div>

    <div class="shra-danger-note"><i class="fa fa-triangle-exclamation"></i> Deleting is permanent and superadmin-only. Removing an invoice also removes its receipts and cancels the enrollment it paid for; removing a receipt puts the balance back on the invoice.</div>

    <?php if ($view !== 'advances') { ?>
    <div class="shra-toolbar" style="margin-top:0">
        <div class="shra-seg" style="margin:0">
            <?php foreach (['all' => 'Everything', 'shra' => 'Academy billing', 'other' => 'Raised elsewhere'] as $kk => $l) { ?>
                <label><input type="radio" name="scope" <?php echo $scope === $kk ? 'checked' : ''; ?> onclick="location.href='<?php echo $url(['scope' => $kk]); ?>'"><span><?php echo $l; ?></span></label>
            <?php } ?>
        </div>
        <?php if ($view === 'invoices') { ?>
        <select class="form-control" style="width:auto" onchange="location.href=this.value">
            <option value="<?php echo $url(['status' => '']); ?>" <?php echo $status === '' ? 'selected' : ''; ?>>Any status</option>
            <?php foreach ([2 => 'Paid', 3 => 'Partially paid', 1 => 'Unpaid', 4 => 'Overdue', 5 => 'Cancelled', 6 => 'Draft'] as $sk => $sl) { ?>
                <option value="<?php echo $url(['status' => $sk]); ?>" <?php echo (int) $status === $sk ? 'selected' : ''; ?>><?php echo $sl; ?></option>
            <?php } ?>
        </select>
        <?php } ?>
    </div>
    <?php } ?>

    <?php /* ───────────────────────── Invoices ───────────────────────── */ ?>
    <?php if ($view === 'invoices') { ?>
    <div class="shra-card">
        <div class="shra-card-head"><h4><i class="fa-solid fa-file-invoice-dollar" style="color:var(--gold)"></i> Invoices</h4><span class="shra-pill"><?php echo count($invoices); ?> shown</span></div>
        <?php if (!count($invoices)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-file-invoice-dollar"></i>No invoices match this range or search.</div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Invoice</th><th>Billed to</th><th>For</th><th>Date</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv) { ?>
                <tr>
                    <td><a href="<?php echo admin_url('invoices/list_invoices/' . $inv->id); ?>" class="strong" target="_blank" rel="noopener"><?php echo html_escape(format_invoice_number($inv)); ?></a><span class="sub"><?php echo (int) $inv->receipts; ?> receipt<?php echo $inv->receipts == 1 ? '' : 's'; ?></span></td>
                    <td>
                        <?php if ($inv->rider_id) { ?>
                            <a href="<?php echo admin_url('shra/rider/' . (int) $inv->rider_id); ?>"><?php echo html_escape($inv->rider_name); ?></a><span class="sub"><?php echo html_escape($inv->rider_no); ?><?php echo $inv->mobile ? ' · ' . html_escape($inv->mobile) : ''; ?></span>
                        <?php } else { ?>
                            <?php echo html_escape($inv->company ?: '—'); ?><span class="sub">not an academy rider</span>
                        <?php } ?>
                    </td>
                    <td><?php if ($inv->enrollment_id) { ?><?php echo html_escape($inv->package_name); ?><span class="sub"><?php echo html_escape($inv->enrollment_no); ?> · <?php echo shra_status_badge($inv->enrollment_status); ?></span><?php } else { ?><span class="shra-badge shra-badge-muted">Raised elsewhere</span><?php } ?></td>
                    <td><?php echo _d($inv->date); ?></td>
                    <td class="num"><?php echo shra_money($inv->total); ?></td>
                    <td class="num" style="color:var(--green)"><?php echo shra_money($inv->paid); ?></td>
                    <td class="num" style="<?php echo $inv->due > 0.009 ? 'color:var(--red);font-weight:600' : ''; ?>"><?php echo $inv->due > 0.009 ? shra_money($inv->due) : '—'; ?></td>
                    <td><?php echo shra_invoice_badge($inv->status); ?></td>
                    <td style="white-space:nowrap;text-align:right">
                        <?php if ($inv->enrollment_id) { ?><a href="<?php echo admin_url('shra/receipt_pdf/' . (int) $inv->enrollment_id); ?>" target="_blank" rel="noopener" class="shra-btn shra-btn-outline shra-btn-sm" title="Academy receipt PDF"><i class="fa fa-file-pdf"></i></a> <?php } ?>
                        <?php echo $del('delete_invoice', $inv->id, 'Delete invoice ' . format_invoice_number($inv) . ' (' . shra_money($inv->total) . ')? Its ' . (int) $inv->receipts . ' payment receipt(s) go with it' . ($inv->enrollment_id ? ' and enrollment ' . $inv->enrollment_no . ' will be cancelled' : '') . '. This cannot be undone.'); ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php /* ───────────────────────── Payment receipts ───────────────────────── */ ?>
    <?php if ($view === 'receipts') { $rsum = 0; foreach ($receipts as $rr) { $rsum += (float) $rr->amount; } ?>
    <div class="shra-card">
        <div class="shra-card-head"><h4><i class="fa-solid fa-receipt" style="color:var(--green)"></i> Payment receipts</h4><span class="shra-pill"><?php echo count($receipts); ?> shown · <?php echo shra_money($rsum); ?></span></div>
        <?php if (!count($receipts)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-receipt"></i>No payments recorded in this range.</div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Receipt</th><th>Invoice</th><th>Paid by</th><th class="num">Amount</th><th>Mode</th><th>Reference</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($receipts as $pay) { $inv_label = $pay->inv_id ? format_invoice_number((object) ['id' => $pay->inv_id, 'number' => $pay->number, 'prefix' => $pay->prefix, 'number_format' => $pay->number_format, 'date' => $pay->invoice_date, 'status' => $pay->invoice_status]) : ''; ?>
                <tr>
                    <td><span class="strong">#<?php echo (int) $pay->id; ?></span><span class="sub"><?php echo html_escape(shra_datetime($pay->daterecorded)); ?></span></td>
                    <td><?php if ($pay->inv_id) { ?><a href="<?php echo admin_url('invoices/list_invoices/' . (int) $pay->inv_id); ?>" target="_blank" rel="noopener"><?php echo html_escape($inv_label); ?></a><span class="sub"><?php echo shra_money($pay->invoice_total); ?> · <?php echo shra_invoice_badge($pay->invoice_status); ?></span><?php } else { ?><span class="shra-muted">Invoice deleted</span><?php } ?></td>
                    <td>
                        <?php if ($pay->rider_id) { ?>
                            <a href="<?php echo admin_url('shra/rider/' . (int) $pay->rider_id); ?>"><?php echo html_escape($pay->rider_name); ?></a><span class="sub"><?php echo html_escape($pay->rider_no); ?><?php echo $pay->package_name ? ' · ' . html_escape($pay->package_name) : ''; ?></span>
                        <?php } else { ?>
                            <?php echo html_escape($pay->company ?: '—'); ?><span class="sub">not an academy rider</span>
                        <?php } ?>
                    </td>
                    <td class="num strong" style="color:var(--green)"><?php echo shra_money($pay->amount); ?></td>
                    <td><?php echo html_escape($pay->mode_name ?: ($pay->paymentmode ?: '—')); ?></td>
                    <td><?php echo $pay->transactionid ? html_escape($pay->transactionid) : '<span class="shra-muted">—</span>'; ?><?php if ($pay->note) { ?><span class="sub"><?php echo html_escape($pay->note); ?></span><?php } ?></td>
                    <td><?php echo _d($pay->date); ?></td>
                    <td style="text-align:right"><?php echo $del('delete_receipt', $pay->id, 'Delete this receipt of ' . shra_money($pay->amount) . ($inv_label ? ' on invoice ' . $inv_label : '') . '? The amount goes back onto the invoice as due. This cannot be undone.'); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php /* ───────────────────── Advances taken on calls ───────────────────── */ ?>
    <?php if ($view === 'advances') { $asum = 0; foreach ($advances as $aa) { $asum += (float) $aa->amount; } $stages = shra_lead_stage_defs(); ?>
    <div class="shra-card">
        <div class="shra-card-head"><h4><i class="fa-solid fa-hand-holding-dollar" style="color:var(--gold)"></i> Advances taken on calls</h4><span class="shra-pill"><?php echo count($advances); ?> shown · <?php echo shra_money($asum); ?></span></div>
        <?php if (!count($advances)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-hand-holding-dollar"></i>No advance payments in this range.<br>Agents record these from the Log-call dialog on a lead.</div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Proof</th><th>Lead</th><th class="num">Amount</th><th>Method</th><th>Reference</th><th>Collected by</th><th>Taken</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($advances as $pay) { $stage = $stages[$pay->stage_key] ?? null; ?>
                <tr>
                    <td>
                        <?php if ($pay->file) { $is_pdf = strtolower(pathinfo($pay->file, PATHINFO_EXTENSION)) === 'pdf'; ?>
                            <a href="<?php echo shra_lead_payment_file_url($pay->id); ?>" target="_blank" rel="noopener" title="Open the proof">
                                <?php if ($is_pdf) { ?><i class="fa fa-file-pdf" style="font-size:20px;color:var(--red)"></i><?php } else { ?><img src="<?php echo shra_lead_payment_file_url($pay->id); ?>" alt="Payment proof" class="shra-proof"><?php } ?>
                            </a>
                        <?php } else { ?><i class="fa fa-image shra-muted" title="No proof attached"></i><?php } ?>
                    </td>
                    <td><?php if ($pay->lead_id && $pay->lead_name) { ?><a href="<?php echo shra_lead_url($pay->lead_id); ?>"><?php echo html_escape($pay->lead_name); ?></a><span class="sub"><?php echo html_escape($pay->phonenumber); ?><?php echo $stage ? ' · ' . html_escape($stage[0]) : ''; ?></span><?php } else { ?><span class="shra-muted">Lead deleted</span><?php } ?></td>
                    <td class="num strong" style="color:var(--green)"><?php echo shra_money($pay->amount); ?></td>
                    <td><?php echo html_escape($pay->method ?: '—'); ?></td>
                    <td><?php echo $pay->reference ? html_escape($pay->reference) : '<span class="shra-muted">—</span>'; ?><?php if ($pay->note) { ?><span class="sub"><?php echo html_escape($pay->note); ?></span><?php } ?></td>
                    <td><?php echo html_escape(trim((string) $pay->staff_name) ?: '—'); ?></td>
                    <td><?php echo html_escape(shra_datetime($pay->created_at)); ?></td>
                    <td style="text-align:right"><?php echo $del('delete_advance', $pay->id, 'Delete this advance of ' . shra_money($pay->amount) . ($pay->lead_name ? ' from ' . $pay->lead_name : '') . '? It comes off the lead total and the proof file is removed. This cannot be undone.'); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
        <?php } ?>
    </div>
    <?php } ?>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
