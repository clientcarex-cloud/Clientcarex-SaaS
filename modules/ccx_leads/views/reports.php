<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    /* ─── Reports Page Styles ─── */
    .ccx-reports-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 25px;
    }

    .ccx-reports-header h4 {
        margin: 0;
        font-weight: 600;
        font-size: 20px;
        color: #1f2937;
    }

    .ccx-reports-filters {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 18px 22px;
        margin-bottom: 28px;
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 16px;
    }

    .ccx-filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .ccx-filter-group label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0;
    }

    .ccx-filter-group input,
    .ccx-filter-group select {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 7px 12px;
        font-size: 13px;
        min-width: 170px;
        color: #374151;
        background: #fff;
    }

    .ccx-filter-group input:focus,
    .ccx-filter-group select:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }

    .ccx-filter-apply {
        background: #3b82f6;
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 13px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .ccx-filter-apply:hover {
        background: #2563eb;
    }

    .ccx-reports-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
    }

    @media (max-width: 768px) {
        .ccx-reports-grid {
            grid-template-columns: 1fr;
        }
    }

    .ccx-report-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 22px 24px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        transition: box-shadow 0.2s, border-color 0.2s;
    }

    .ccx-report-card:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
        border-color: #93c5fd;
    }

    .ccx-report-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #fff;
        flex-shrink: 0;
    }

    .ccx-report-body {
        flex: 1;
    }

    .ccx-report-body h5 {
        margin: 0 0 4px;
        font-size: 15px;
        font-weight: 600;
        color: #1f2937;
    }

    .ccx-report-body p {
        margin: 0 0 12px;
        font-size: 12.5px;
        color: #6b7280;
        line-height: 1.5;
    }

    .ccx-report-btn {
        font-size: 12px;
        padding: 5px 14px;
        border-radius: 5px;
        border: 1px solid #3b82f6;
        background: #eff6ff;
        color: #3b82f6;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
        transition: all 0.2s;
    }

    .ccx-report-btn:hover {
        background: #3b82f6;
        color: #fff;
        text-decoration: none;
    }

    .ccx-report-card {
        cursor: pointer;
    }
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <!-- Header -->
                        <div class="ccx-reports-header">
                            <h4><i class="fa fa-chart-bar" style="color:#3b82f6;"></i> Leads Reports</h4>
                            <a href="<?php echo admin_url('ccx_leads'); ?>" class="btn btn-default btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to Leads
                            </a>
                        </div>



                        <!-- Reports Grid -->
                        <?php
                        $reports = [
                            ['name' => 'Overall Leads Assigned', 'desc' => 'Summary of all leads assigned across the team.', 'icon' => 'fa-user-plus', 'color' => '#3b82f6'],
                            ['name' => 'Overall Staff New Leads & Calls', 'desc' => 'New leads received and calls made by each staff member.', 'icon' => 'fa-phone-volume', 'color' => '#10b981'],
                            ['name' => 'Overall Missed Leads Calls', 'desc' => 'Leads whose calls were missed or not attempted.', 'icon' => 'fa-phone-slash', 'color' => '#ef4444'],
                            ['name' => 'Staff Wise Leads Call TAT Report', 'desc' => 'Turn-around time for staff calls on assigned leads.', 'icon' => 'fa-stopwatch', 'color' => '#f59e0b'],
                            ['name' => 'Leads Follow-ups', 'desc' => 'Scheduled and completed follow-up activities on leads.', 'icon' => 'fa-calendar-check', 'color' => '#8b5cf6'],
                            ['name' => 'Overview of Leads by Staff', 'desc' => 'Staff-wise breakdown of lead counts, statuses, and progress.', 'icon' => 'fa-users', 'color' => '#06b6d4'],
                            ['name' => 'Complete Overview of Whole Leads', 'desc' => 'Comprehensive overview of all leads in the system.', 'icon' => 'fa-chart-pie', 'color' => '#ec4899'],
                            ['name' => 'Last One Week Leads & Conversion', 'desc' => 'Leads received and converted in the last 7 days.', 'icon' => 'fa-calendar-week', 'color' => '#14b8a6'],
                            ['name' => 'Last 2 Week Leads & Conversion', 'desc' => 'Leads received and converted in the last 14 days.', 'icon' => 'fa-calendar-days', 'color' => '#6366f1'],
                            ['name' => 'Overall Leads Statuses', 'desc' => 'Distribution of leads across all statuses.', 'icon' => 'fa-signal', 'color' => '#f97316'],
                        ];
                        ?>

                        <div class="ccx-reports-grid">
                            <?php foreach ($reports as $idx => $r) { ?>
                                <div class="ccx-report-card"
                                    onclick="window.location='<?php echo admin_url('ccx_leads/report_view/' . ($idx + 1)); ?>';">
                                    <div class="ccx-report-icon" style="background:<?php echo $r['color']; ?>;">
                                        <i class="fa-solid <?php echo $r['icon']; ?>"></i>
                                    </div>
                                    <div class="ccx-report-body">
                                        <h5><?php echo ($idx + 1) . '. ' . $r['name']; ?></h5>
                                        <p><?php echo $r['desc']; ?></p>
                                        <a href="<?php echo admin_url('ccx_leads/report_view/' . ($idx + 1)); ?>"
                                            class="ccx-report-btn">
                                            <i class="fa fa-eye"></i> View Report
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
</body>

</html>