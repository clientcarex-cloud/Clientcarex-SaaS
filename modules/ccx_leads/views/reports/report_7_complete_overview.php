<table class="ccx-rpt-table">
    <thead>
        <tr>
            <th>#</th>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Company</th>
            <th>Source</th>
            <th>Assigned</th>
            <th>Status</th>
            <th>Value</th>
            <th>Last Contact</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $st_label = '';
            if ($r['junk'] == 1) {
                $st_label = '<span class="status-badge" style="background:#9CA3AF;">Junk</span>';
            } elseif ($r['lost'] == 1) {
                $st_label = '<span class="status-badge" style="background:#EF4444;">Lost</span>';
            } elseif ($r['status_name']) {
                $st_label = '<span class="status-badge" style="background:' . ($r['status_color'] ?: '#6b7280') . ';">' . $r['status_name'] . '</span>';
            }
            ?>
            <tr>
                <td>
                    <?php echo $i++; ?>
                </td>
                <td>
                    <?php echo $r['id']; ?>
                </td>
                <td>
                    <?php echo $r['name']; ?>
                </td>
                <td>
                    <?php echo $r['email']; ?>
                </td>
                <td>
                    <?php echo $r['phonenumber']; ?>
                </td>
                <td>
                    <?php echo $r['company']; ?>
                </td>
                <td>
                    <?php echo $r['source_name'] ?: '-'; ?>
                </td>
                <td>
                    <?php echo $r['staff_name'] ?: '<span style="color:#9ca3af;">Unassigned</span>'; ?>
                </td>
                <td>
                    <?php echo $st_label; ?>
                </td>
                <td>
                    <?php echo $r['lead_value'] ? number_format($r['lead_value'], 2) : '-'; ?>
                </td>
                <td>
                    <?php echo ($r['lastcontact'] && $r['lastcontact'] != '0000-00-00 00:00:00') ? date('d M Y H:i', strtotime($r['lastcontact'])) : '-'; ?>
                </td>
                <td>
                    <?php echo date('d M Y H:i', strtotime($r['dateadded'])); ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>