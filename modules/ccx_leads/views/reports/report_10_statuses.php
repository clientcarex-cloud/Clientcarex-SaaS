<?php $grand_total = array_sum(array_column($report_data, 'total')); ?>
<table class="ccx-rpt-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Status</th>
            <th>Color</th>
            <th>Total Leads</th>
            <th>Percentage</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $pct = $grand_total > 0 ? round(($r['total'] / $grand_total) * 100, 1) : 0;
            ?>
            <tr>
                <td>
                    <?php echo $i++; ?>
                </td>
                <td>
                    <span class="status-badge" style="background:<?php echo $r['color'] ?: '#6b7280'; ?>;">
                        <?php echo $r['status_name']; ?>
                    </span>
                </td>
                <td>
                    <span
                        style="display:inline-block;width:14px;height:14px;border-radius:3px;background:<?php echo $r['color'] ?: '#6b7280'; ?>;vertical-align:middle;"></span>
                </td>
                <td><strong>
                        <?php echo $r['total']; ?>
                    </strong></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div
                            style="flex:1;max-width:200px;height:8px;background:#f3f4f6;border-radius:4px;overflow:hidden;">
                            <div
                                style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $r['color'] ?: '#6b7280'; ?>;border-radius:4px;">
                            </div>
                        </div>
                        <span>
                            <?php echo $pct; ?>%
                        </span>
                    </div>
                </td>
            </tr>
        <?php } ?>
        <?php if ($grand_total > 0) { ?>
            <tr style="font-weight:700;background:#f9fafb;">
                <td colspan="3" style="text-align:right;">Grand Total</td>
                <td>
                    <?php echo $grand_total; ?>
                </td>
                <td>100%</td>
            </tr>
        <?php } ?>
    </tbody>
</table>