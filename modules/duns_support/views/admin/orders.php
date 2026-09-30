<?php defined('BASEPATH') or exit('No direct script access allowed');
init_head();
$qs = function (array $over = []) use ($f) {
    $q = http_build_query(array_filter(array_merge($f, $over), function ($v) { return $v !== '' && $v !== null; }));

    return $q !== '' ? '?' . $q : '';
};
?>
<div id="wrapper">
<div class="content duns-wrap">
    <?php $this->load->view('admin/_nav'); ?>

    <div class="duns-stats">
        <a class="duns-stat" href="<?php echo admin_url('duns_support?status=open'); ?>"><small>In queue</small><b><?php echo $stats['open']; ?></b></a>
        <a class="duns-stat<?php echo $stats['overdue'] ? ' bad' : ''; ?>" href="<?php echo admin_url('duns_support?status=overdue'); ?>"><small>Overdue</small><b><?php echo $stats['overdue']; ?></b></a>
        <a class="duns-stat warn" href="<?php echo admin_url('duns_support?status=pending_payment'); ?>"><small>Awaiting payment</small><b><?php echo $stats['pending_payment']; ?></b></a>
        <a class="duns-stat good" href="<?php echo admin_url('duns_support?status=completed'); ?>"><small>Delivered</small><b><?php echo $stats['completed']; ?></b></a>
        <div class="duns-stat"><small>Revenue this month</small><b><?php echo duns_money($stats['revenue_month']); ?></b></div>
        <div class="duns-stat"><small>Revenue all time</small><b><?php echo duns_money($stats['revenue']); ?></b></div>
    </div>

    <div class="duns-card">
        <div class="duns-card-body">
            <form method="get" action="<?php echo admin_url('duns_support'); ?>" class="duns-filters">
                <div class="form-group" style="flex:1;min-width:200px"><label>Search</label>
                    <input type="text" name="q" class="form-control" value="<?php echo html_escape($f['q'] ?? ''); ?>" placeholder="Order no, company, director, email, mobile, DUNS…"></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <option value="open" <?php echo ($f['status'] ?? '') === 'open' ? 'selected' : ''; ?>>In queue (paid + in progress)</option>
                        <option value="overdue" <?php echo ($f['status'] ?? '') === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <?php foreach (duns_statuses() as $k => $s) { ?><option value="<?php echo $k; ?>" <?php echo ($f['status'] ?? '') === $k ? 'selected' : ''; ?>><?php echo $s['label']; ?></option><?php } ?>
                    </select></div>
                <div class="form-group"><label>Plan</label>
                    <select name="plan_id" class="form-control">
                        <option value="">All</option>
                        <?php foreach ($plans as $pl) { ?><option value="<?php echo $pl->id; ?>" <?php echo (int) ($f['plan_id'] ?? 0) === (int) $pl->id ? 'selected' : ''; ?>><?php echo html_escape($pl->name); ?></option><?php } ?>
                    </select></div>
                <div class="form-group"><label>From</label><input type="date" name="from" class="form-control" value="<?php echo html_escape($f['from'] ?? ''); ?>"></div>
                <div class="form-group"><label>To</label><input type="date" name="to" class="form-control" value="<?php echo html_escape($f['to'] ?? ''); ?>"></div>
                <button class="btn btn-primary" style="height:34px"><i class="fa fa-filter"></i> Filter</button>
                <?php if (count($f)) { ?><a href="<?php echo admin_url('duns_support'); ?>" class="btn btn-default" style="height:34px">Reset</a><?php } ?>
                <a href="<?php echo admin_url('duns_support/export' . $qs()); ?>" class="btn btn-default" style="height:34px;margin-left:auto"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            </form>
        </div>
    </div>

    <div class="duns-card">
        <div class="duns-card-head"><h4><?php echo $total; ?> order<?php echo $total === 1 ? '' : 's'; ?></h4></div>
        <?php if (!count($orders)) { ?>
            <div class="duns-empty"><i class="fa-solid fa-inbox"></i>No orders yet. Share <a href="<?php echo duns_public_url(); ?>" target="_blank"><?php echo duns_public_url(); ?></a> in your ads.</div>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="duns-table">
                <thead><tr><th>Order</th><th>Company</th><th>Director</th><th>Plan</th><th>Amount</th><th>Status</th><th>Deadline</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o) { $late = duns_is_overdue($o); ?>
                    <tr>
                        <td><a class="no" href="<?php echo admin_url('duns_support/order/' . $o->id); ?>"><?php echo html_escape($o->order_no); ?></a>
                            <?php if ($o->utm_source) { ?><span class="sub"><i class="fa-solid fa-bullhorn"></i> <?php echo html_escape($o->utm_source); ?></span><?php } ?></td>
                        <td><b><?php echo html_escape($o->company_name); ?></b><?php if ($o->duns_number) { ?><span class="sub">DUNS <?php echo duns_format_number($o->duns_number); ?></span><?php } ?></td>
                        <td><?php echo html_escape($o->director_name); ?><span class="sub"><?php echo html_escape($o->director_mobile); ?></span></td>
                        <td><?php echo html_escape($o->plan_name); ?><span class="sub"><?php echo duns_hours_label($o->delivery_hours); ?></span></td>
                        <td><?php echo duns_money($o->amount); ?><?php if ((float) $o->amount_paid > 0) { ?><span class="sub">Paid <?php echo duns_money($o->amount_paid); ?></span><?php } ?></td>
                        <td><?php echo duns_status_badge($o->status); ?></td>
                        <td><?php if (duns_is_open($o)) { ?><span class="duns-clock<?php echo $late ? ' late' : ''; ?>"><i class="fa-regular fa-clock"></i> <?php echo duns_time_left($o); ?></span><span class="sub"><?php echo duns_datetime($o->due_at); ?></span><?php } elseif ($o->status === 'completed') { ?><span class="sub">Delivered <?php echo duns_datetime($o->completed_at); ?></span><?php } else { ?>—<?php } ?></td>
                        <td><span class="sub" style="color:inherit"><?php echo duns_datetime($o->created_at); ?></span></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1) { ?>
        <div class="duns-pager">
            <?php if ($page > 1) { ?><a class="btn btn-default btn-sm" href="<?php echo admin_url('duns_support' . $qs(['page' => $page - 1])); ?>">&larr; Previous</a><?php } ?>
            <span class="btn btn-link btn-sm disabled">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
            <?php if ($page < $pages) { ?><a class="btn btn-default btn-sm" href="<?php echo admin_url('duns_support' . $qs(['page' => $page + 1])); ?>">Next &rarr;</a><?php } ?>
        </div>
        <?php } ?>
        <?php } ?>
    </div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
