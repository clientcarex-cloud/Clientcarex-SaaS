<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .ccx-rpt-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }

    .ccx-rpt-header h4 {
        margin: 0;
        font-weight: 600;
        font-size: 18px;
        color: #1f2937;
    }

    .ccx-rpt-filters {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 22px;
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 14px;
    }

    .ccx-rpt-fg {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .ccx-rpt-fg label {
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 0;
    }

    .ccx-rpt-fg input,
    .ccx-rpt-fg select {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 13px;
        min-width: 160px;
        color: #374151;
        background: #fff;
    }

    .ccx-rpt-fg input:focus,
    .ccx-rpt-fg select:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, .15);
    }

    .ccx-rpt-apply {
        background: #3b82f6;
        color: #fff;
        border: none;
        padding: 7px 18px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 13px;
        cursor: pointer;
    }

    .ccx-rpt-apply:hover {
        background: #2563eb;
    }

    .ccx-rpt-reset {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
        padding: 7px 14px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 13px;
        cursor: pointer;
        text-decoration: none;
    }

    .ccx-rpt-reset:hover {
        background: #e5e7eb;
        color: #111827;
        text-decoration: none;
    }

    .ccx-rpt-summary {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .ccx-rpt-badge {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 18px;
        text-align: center;
        min-width: 110px;
    }

    .ccx-rpt-badge .num {
        font-size: 22px;
        font-weight: 700;
        color: #1f2937;
        display: block;
    }

    .ccx-rpt-badge .lbl {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .ccx-rpt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .ccx-rpt-table th {
        background: #f9fafb;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .ccx-rpt-table td {
        padding: 9px 12px;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
    }

    .ccx-rpt-table tr:hover td {
        background: #f9fafb;
    }

    .ccx-rpt-table .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        color: #fff;
    }

    .ccx-rpt-no-data {
        text-align: center;
        padding: 40px;
        color: #9ca3af;
        font-size: 14px;
    }

    .ccx-rpt-status-bar {
        display: inline-flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .ccx-rpt-status-chip {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 500;
        color: #fff;
    }

    .ccx-rpt-tat-good {
        color: #059669;
        font-weight: 600;
    }

    .ccx-rpt-tat-avg {
        color: #d97706;
        font-weight: 600;
    }

    .ccx-rpt-tat-bad {
        color: #dc2626;
        font-weight: 600;
    }

    .ccx-rpt-tat-na {
        color: #9ca3af;
    }

    .conversion-yes {
        color: #059669;
        font-weight: 600;
    }

    .conversion-no {
        color: #9ca3af;
    }

    /* ─── KPI Cards Grid ─── */
    .ccx-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 22px;
    }

    .ccx-kpi-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-left: 4px solid #3b82f6;
        border-radius: 10px;
        padding: 14px 18px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ccx-kpi-card.kpi-green {
        border-left-color: #059669;
    }

    .ccx-kpi-card.kpi-red {
        border-left-color: #dc2626;
    }

    .ccx-kpi-card.kpi-amber {
        border-left-color: #d97706;
    }

    .ccx-kpi-card.kpi-purple {
        border-left-color: #7c3aed;
    }

    .ccx-kpi-card.kpi-pink {
        border-left-color: #ec4899;
    }

    .ccx-kpi-card.kpi-indigo {
        border-left-color: #4f46e5;
    }

    .ccx-kpi-val {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
        line-height: 1.1;
    }

    .ccx-kpi-lbl {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .5px;
        font-weight: 600;
    }

    .ccx-kpi-sub {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 2px;
    }

    /* ─── Percentage inline bar ─── */
    .ccx-pct-bar {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ccx-pct-track {
        flex: 1;
        max-width: 120px;
        height: 7px;
        background: #f3f4f6;
        border-radius: 4px;
        overflow: hidden;
    }

    .ccx-pct-fill {
        height: 100%;
        border-radius: 4px;
        background: #3b82f6;
        transition: width .3s;
    }

    .ccx-pct-text {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        min-width: 40px;
    }

    /* ─── Totals Row ─── */
    .ccx-rpt-table tr.totals-row td {
        font-weight: 700;
        background: #f0f4ff;
        border-top: 2px solid #3b82f6;
        color: #1e3a5f;
    }

    /* ─── Urgency colors ─── */
    .ccx-age-green {
        color: #059669;
        font-weight: 600;
    }

    .ccx-age-amber {
        color: #d97706;
        font-weight: 600;
    }

    .ccx-age-red {
        color: #dc2626;
        font-weight: 600;
    }

    /* ─── Mini Summary Table ─── */
    .ccx-mini-summary {
        margin-bottom: 18px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
    }

    .ccx-mini-summary .mini-title {
        background: #f0f4ff;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 700;
        color: #1e40af;
        text-transform: uppercase;
        letter-spacing: .5px;
        border-bottom: 1px solid #e5e7eb;
    }

    .ccx-mini-summary table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .ccx-mini-summary table th {
        background: #f9fafb;
        padding: 7px 12px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .ccx-mini-summary table td {
        padding: 6px 12px;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
    }

    /* ─── SLA indicator ─── */
    .ccx-sla-pass {
        color: #059669;
        font-weight: 700;
    }

    .ccx-sla-fail {
        color: #dc2626;
        font-weight: 700;
    }

    /* ─── Overdue / Upcoming badges ─── */
    .ccx-badge-overdue {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        background: #fef2f2;
        color: #dc2626;
        font-size: 11px;
        font-weight: 600;
    }

    .ccx-badge-upcoming {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 11px;
        font-weight: 600;
    }

    .ccx-badge-done {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        background: #f0fdf4;
        color: #059669;
        font-size: 11px;
        font-weight: 600;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <!-- Header -->
                        <div class="ccx-rpt-header">
                            <h4><i class="fa fa-chart-bar" style="color:#3b82f6;"></i>
                                <?php echo $report_name; ?>
                            </h4>
                            <a href="<?php echo admin_url('ccx_leads/reports'); ?>" class="btn btn-default btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to Reports
                            </a>
                        </div>

                        <!-- Filters -->
                        <form method="GET" action="<?php echo admin_url('ccx_leads/report_view/' . $report_id); ?>"
                            class="ccx-rpt-filters">
                            <div class="ccx-rpt-fg">
                                <label>Date From</label>
                                <input type="date" name="date_from" value="<?php echo $date_from; ?>" />
                            </div>
                            <div class="ccx-rpt-fg">
                                <label>Date To</label>
                                <input type="date" name="date_to" value="<?php echo $date_to; ?>" />
                            </div>
                            <div class="ccx-rpt-fg">
                                <label>Staff</label>
                                <select name="staff_id">
                                    <option value="">All Staff</option>
                                    <?php foreach ($members as $m) { ?>
                                        <option value="<?php echo $m['staffid']; ?>" <?php echo ($staff_id == $m['staffid']) ? 'selected' : ''; ?>>
                                            <?php echo $m['firstname'] . ' ' . $m['lastname']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <button type="submit" class="ccx-rpt-apply"><i class="fa fa-filter"></i> Apply</button>
                            <a href="<?php echo admin_url('ccx_leads/report_view/' . $report_id); ?>"
                                class="ccx-rpt-reset">Reset</a>
                        </form>

                        <!-- Summary -->
                        <div class="ccx-rpt-summary">
                            <div class="ccx-rpt-badge">
                                <span class="num">
                                    <?php echo count($report_data); ?>
                                </span>
                                <span class="lbl">Total Records</span>
                            </div>
                            <?php if (!empty($date_from) || !empty($date_to)) { ?>
                                <div class="ccx-rpt-badge">
                                    <span class="num" style="font-size:13px; color:#3b82f6;">
                                        <?php echo $date_from ?: '∞'; ?> →
                                        <?php echo $date_to ?: '∞'; ?>
                                    </span>
                                    <span class="lbl">Date Range</span>
                                </div>
                            <?php } ?>
                        </div>

                        <!-- Report Content -->
                        <?php if (empty($report_data)) { ?>
                            <div class="ccx-rpt-no-data"><i class="fa fa-inbox fa-2x"></i><br>No data found for the selected
                                filters.</div>
                        <?php } else { ?>
                            <div style="overflow-x:auto;">
                                <?php echo $report_content; ?>
                            </div>
                        <?php } ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>

</html>