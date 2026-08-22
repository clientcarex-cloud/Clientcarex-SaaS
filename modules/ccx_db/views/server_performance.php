<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .perf-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        border-left: 4px solid #1abc9c;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .perf-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    .perf-card.card-cpu    { border-left-color: #3498db; }
    .perf-card.card-memory { border-left-color: #e74c3c; }
    .perf-card.card-disk   { border-left-color: #f39c12; }
    .perf-card.card-mysql  { border-left-color: #9b59b6; }
    .perf-card.card-backup { border-left-color: #1abc9c; }
    .perf-card .card-title {
        font-size: 14px;
        color: #95a5a6;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
        font-weight: 600;
    }
    .perf-card .card-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    .perf-card .card-sub {
        font-size: 12px;
        color: #7f8c8d;
    }
    .gauge-bar {
        height: 10px;
        background: #ecf0f1;
        border-radius: 5px;
        overflow: hidden;
        margin-top: 10px;
    }
    .gauge-bar .fill {
        height: 100%;
        border-radius: 5px;
        transition: width 0.6s ease, background 0.6s ease;
    }
    .gauge-bar .fill.green  { background: linear-gradient(90deg, #2ecc71, #27ae60); }
    .gauge-bar .fill.yellow { background: linear-gradient(90deg, #f1c40f, #f39c12); }
    .gauge-bar .fill.orange { background: linear-gradient(90deg, #e67e22, #d35400); }
    .gauge-bar .fill.red    { background: linear-gradient(90deg, #e74c3c, #c0392b); }
    .mysql-stat {
        text-align: center;
        padding: 12px 8px;
    }
    .mysql-stat .stat-val {
        font-size: 24px;
        font-weight: 700;
        color: #2c3e50;
    }
    .mysql-stat .stat-label {
        font-size: 11px;
        color: #95a5a6;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .status-badge.active   { background: #e74c3c; color: #fff; animation: pulse-badge 1.5s infinite; }
    .status-badge.inactive { background: #2ecc71; color: #fff; }
    @keyframes pulse-badge {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    .chart-container {
        position: relative;
        height: 250px;
        width: 100%;
    }
    #process_table tbody tr:hover {
        background: #f8f9fa;
    }
    .refresh-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #2ecc71;
        margin-right: 6px;
        animation: blink-dot 2s infinite;
    }
    @keyframes blink-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }
    .section-title {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #ecf0f1;
    }
    .section-title i {
        margin-right: 6px;
        color: #95a5a6;
    }
    .tenant-panel {
        margin-bottom: 10px;
        border: 1px solid #ecf0f1;
        border-radius: 6px;
        overflow: hidden;
        transition: box-shadow 0.2s;
    }
    .tenant-panel:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .tenant-panel-header {
        padding: 10px 15px;
        background: #f8f9fa;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        user-select: none;
        border-bottom: 1px solid #ecf0f1;
    }
    .tenant-panel-header:hover {
        background: #ecf0f1;
    }
    .tenant-panel-header .tenant-name {
        font-weight: 600;
        font-size: 13px;
        color: #2c3e50;
    }
    .tenant-panel-header .tenant-name i {
        margin-right: 6px;
        transition: transform 0.3s;
        color: #95a5a6;
        font-size: 11px;
    }
    .tenant-panel-header .tenant-name i.expanded {
        transform: rotate(90deg);
    }
    .tenant-panel-header .badge-count {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .tenant-panel-body {
        display: none;
        padding: 0;
    }
    .tenant-panel-body.open {
        display: block;
    }
    .tenant-panel-body table {
        margin-bottom: 0;
        font-size: 12px;
    }
    .tenant-error {
        padding: 8px 15px;
        color: #e74c3c;
        font-size: 12px;
    }
    /* ── Tenant Summary Stats ── */
    .tenant-summary-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }
    .tenant-stat-box {
        flex: 1;
        min-width: 140px;
        background: #f8f9fa;
        border-radius: 6px;
        padding: 12px 16px;
        text-align: center;
        border: 1px solid #ecf0f1;
    }
    .tenant-stat-box .tsb-val {
        font-size: 26px;
        font-weight: 700;
        color: #2c3e50;
        line-height: 1.2;
    }
    .tenant-stat-box .tsb-label {
        font-size: 10px;
        color: #95a5a6;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-top: 2px;
    }
    .tenant-stat-box.stat-danger .tsb-val { color: #e74c3c; }
    .tenant-stat-box.stat-warning .tsb-val { color: #e67e22; }
    .tenant-stat-box.stat-info .tsb-val { color: #3498db; }
    .tenant-stat-box.stat-ok .tsb-val { color: #27ae60; }
    /* ── Tenant toolbar ── */
    .tenant-toolbar {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    .tenant-toolbar .tenant-search {
        flex: 1;
        min-width: 200px;
        padding: 6px 12px;
        border: 1px solid #dce4ec;
        border-radius: 4px;
        font-size: 12px;
    }
    .tenant-toolbar .tenant-search:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52,152,219,0.15);
    }
    .tenant-toolbar label {
        font-size: 12px;
        color: #7f8c8d;
        margin: 0;
        cursor: pointer;
        white-space: nowrap;
    }
    /* ── Unified table ── */
    #tenant_unified_table {
        font-size: 12px;
        margin-bottom: 0;
    }
    #tenant_unified_table .tenant-label {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        background: #ebf5fb;
        color: #2980b9;
        white-space: nowrap;
    }
    #tenant_unified_table tbody tr:hover {
        background: #f0f7ff;
    }
    .tenant-error-list {
        margin-top: 8px;
        font-size: 11px;
        color: #e74c3c;
    }
    .tenant-error-list .te-item {
        padding: 3px 0;
    }
    /* ══ On-boarding capacity ══ */
    .cap-hero {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        background: linear-gradient(135deg, #2c3e50, #34495e);
        border-radius: 8px;
        padding: 22px 26px;
        margin-bottom: 16px;
        color: #fff;
        box-shadow: 0 2px 12px rgba(0,0,0,0.15);
    }
    .cap-hero.st-healthy  { background: linear-gradient(135deg, #16a085, #1abc9c); }
    .cap-hero.st-moderate { background: linear-gradient(135deg, #d68910, #f39c12); }
    .cap-hero.st-tight    { background: linear-gradient(135deg, #ca6f1e, #e67e22); }
    .cap-hero.st-critical { background: linear-gradient(135deg, #a93226, #e74c3c); }
    .cap-hero-main { flex: 1 1 260px; min-width: 240px; }
    .cap-hero-num {
        font-size: 58px;
        font-weight: 700;
        line-height: 1;
        letter-spacing: -1px;
    }
    .cap-hero-lbl {
        font-size: 14px;
        font-weight: 600;
        opacity: 0.95;
        margin-top: 6px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .cap-hero-sub {
        font-size: 12.5px;
        opacity: 0.85;
        margin-top: 10px;
        line-height: 1.5;
        max-width: 460px;
    }
    .cap-hero-side {
        flex: 1 1 340px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-start;
    }
    .cap-kpi {
        flex: 1 1 96px;
        min-width: 92px;
        background: rgba(255,255,255,0.14);
        border: 1px solid rgba(255,255,255,0.18);
        border-radius: 6px;
        padding: 10px 12px;
        text-align: center;
    }
    .cap-kpi .k-val { font-size: 24px; font-weight: 700; line-height: 1.15; }
    .cap-kpi .k-lbl {
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        opacity: 0.9;
        margin-top: 3px;
    }
    /* Fill meter: onboarded vs safe vs wall */
    .cap-meter { margin-bottom: 18px; }
    .cap-meter-track {
        position: relative;
        height: 26px;
        background: #ecf0f1;
        border-radius: 5px;
        overflow: hidden;
    }
    .cap-meter-fill {
        height: 100%;
        background: linear-gradient(90deg, #16a085, #1abc9c);
        transition: width 0.6s ease, background 0.6s ease;
    }
    .cap-meter-fill.st-moderate { background: linear-gradient(90deg, #d68910, #f39c12); }
    .cap-meter-fill.st-tight    { background: linear-gradient(90deg, #ca6f1e, #e67e22); }
    .cap-meter-fill.st-critical { background: linear-gradient(90deg, #a93226, #e74c3c); }
    .cap-meter-safe {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #2c3e50;
    }
    .cap-meter-safe:after {
        content: 'safe';
        position: absolute;
        top: -1px;
        left: 4px;
        font-size: 9px;
        color: #2c3e50;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .cap-meter-scale {
        display: flex;
        justify-content: space-between;
        font-size: 10.5px;
        color: #95a5a6;
        margin-top: 4px;
    }
    /* Constraint table */
    #cap_constraint_table { font-size: 12.5px; margin-bottom: 0; }
    #cap_constraint_table td { vertical-align: middle; }
    #cap_constraint_table tr.is-binding { background: #fdf2e9 !important; }
    #cap_constraint_table tr.is-binding td { font-weight: 600; }
    .cap-bind-tag {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 3px;
        background: #e67e22;
        color: #fff;
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-left: 6px;
    }
    .cap-ceiling { font-weight: 700; font-size: 15px; color: #2c3e50; }
    .cap-conf {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 10px;
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .cap-conf.high   { background: #d5f5e3; color: #1e8449; }
    .cap-conf.medium { background: #fdebd0; color: #b9770e; }
    .cap-conf.low    { background: #fadbd8; color: #b03a2e; }
    .cap-note { font-size: 11px; color: #7f8c8d; line-height: 1.45; }
    /* Per-tenant unit cost */
    .cap-unit-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 4px; }
    .cap-unit {
        flex: 1 1 110px;
        min-width: 100px;
        background: #f8f9fa;
        border: 1px solid #ecf0f1;
        border-radius: 6px;
        padding: 11px 8px;
        text-align: center;
    }
    .cap-unit .u-val { font-size: 20px; font-weight: 700; color: #2c3e50; line-height: 1.2; }
    .cap-unit .u-val small { font-size: 11px; font-weight: 400; color: #95a5a6; }
    .cap-unit .u-lbl {
        font-size: 9.5px;
        color: #95a5a6;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-top: 3px;
    }
    /* Blockers */
    .cap-blocker {
        border-left: 4px solid #bdc3c7;
        background: #f8f9fa;
        border-radius: 5px;
        padding: 11px 14px;
        margin-bottom: 9px;
    }
    .cap-blocker.sev-critical { border-left-color: #e74c3c; background: #fdf0ef; }
    .cap-blocker.sev-warning  { border-left-color: #f39c12; background: #fdf6ec; }
    .cap-blocker.sev-info     { border-left-color: #3498db; background: #eef6fc; }
    .cap-blocker .b-title { font-weight: 700; font-size: 12.5px; color: #2c3e50; }
    .cap-blocker .b-gain {
        float: right;
        font-size: 10.5px;
        font-weight: 700;
        color: #16a085;
        white-space: nowrap;
    }
    .cap-blocker .b-detail { font-size: 11.5px; color: #5d6d7e; margin-top: 4px; line-height: 1.5; }
    .cap-blocker .b-fix {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px;
        color: #1e8449;
        background: #fff;
        border: 1px solid #e5e8ea;
        border-radius: 4px;
        padding: 6px 9px;
        margin-top: 7px;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .cap-basis {
        font-size: 11px;
        color: #95a5a6;
        margin-top: 10px;
        line-height: 1.6;
    }
    .cap-warnbar {
        background: #fef5e7;
        border: 1px solid #f8e0b0;
        border-radius: 5px;
        padding: 9px 13px;
        font-size: 11.5px;
        color: #935116;
        margin-bottom: 14px;
        line-height: 1.5;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-tachometer"></i> <?php echo $title; ?>
                            <a href="<?php echo admin_url('ccx_db'); ?>" class="btn btn-default pull-right" style="margin-top:-5px;">
                                <i class="fa fa-arrow-left"></i> Back to CCX DB
                            </a>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <!-- Live Indicator -->
                        <div class="text-right mbot10">
                            <span class="refresh-indicator"></span>
                            <small class="text-muted">Live — updates every 5s &nbsp;|&nbsp; Server: <span id="server_time">--</span></small>
                        </div>



                        <!-- ═══ CLIENT ON-BOARDING CAPACITY ═══ -->
                        <div class="section-title">
                            <i class="fa fa-users"></i> Client On-boarding Capacity
                            <small class="text-muted" style="font-weight:400;text-transform:none;letter-spacing:0;">
                                &mdash; how many more tenants this server can take, derived live from the metrics below
                            </small>
                        </div>

                        <!-- Headline verdict -->
                        <div class="cap-hero" id="cap_hero">
                            <div class="cap-hero-main">
                                <div class="cap-hero-num" id="cap_can_add">&mdash;</div>
                                <div class="cap-hero-lbl">more clients can be on-boarded today</div>
                                <div class="cap-hero-sub" id="cap_hero_sub">Measuring live server capacity&hellip;</div>
                            </div>
                            <div class="cap-hero-side">
                                <div class="cap-kpi">
                                    <div class="k-val" id="cap_onboarded">&mdash;</div>
                                    <div class="k-lbl">Live now</div>
                                </div>
                                <div class="cap-kpi">
                                    <div class="k-val" id="cap_safe">&mdash;</div>
                                    <div class="k-lbl">Safe limit</div>
                                </div>
                                <div class="cap-kpi">
                                    <div class="k-val" id="cap_hard">&mdash;</div>
                                    <div class="k-lbl">Hard wall</div>
                                </div>
                                <div class="cap-kpi">
                                    <div class="k-val" id="cap_tuned">&mdash;</div>
                                    <div class="k-lbl">After tuning</div>
                                </div>
                            </div>
                        </div>

                        <!-- Confidence warning (only when the sample is too idle to trust) -->
                        <div class="cap-warnbar" id="cap_warnbar" style="display:none;"></div>

                        <!-- Fill meter -->
                        <div class="cap-meter">
                            <div class="cap-meter-track">
                                <div class="cap-meter-fill" id="cap_meter_fill" style="width:0%"></div>
                                <div class="cap-meter-safe" id="cap_meter_safe" style="left:70%"></div>
                            </div>
                            <div class="cap-meter-scale">
                                <span>0 tenants</span>
                                <span id="cap_meter_caption">&mdash;</span>
                                <span id="cap_meter_max">&mdash;</span>
                            </div>
                        </div>

                        <!-- What each tenant actually costs -->
                        <div class="section-title" style="font-size:13px;">
                            <i class="fa fa-cube"></i> Measured cost of one tenant
                        </div>
                        <div class="cap-unit-row">
                            <div class="cap-unit">
                                <div class="u-val" id="cap_u_db">&mdash;<small> MB</small></div>
                                <div class="u-lbl">Database</div>
                            </div>
                            <div class="cap-unit">
                                <div class="u-val" id="cap_u_tables">&mdash;</div>
                                <div class="u-lbl">Tables</div>
                            </div>
                            <div class="cap-unit">
                                <div class="u-val" id="cap_u_ram">&mdash;<small> MB</small></div>
                                <div class="u-lbl">RAM</div>
                            </div>
                            <div class="cap-unit">
                                <div class="u-val" id="cap_u_qps">&mdash;<small> q/s</small></div>
                                <div class="u-lbl">Query load</div>
                            </div>
                            <div class="cap-unit">
                                <div class="u-val" id="cap_u_load">&mdash;</div>
                                <div class="u-lbl">CPU load</div>
                            </div>
                            <div class="cap-unit">
                                <div class="u-val" id="cap_u_conns">&mdash;</div>
                                <div class="u-lbl">DB connections</div>
                            </div>
                        </div>

                        <!-- Ceiling per constraint -->
                        <div class="section-title mtop20" style="font-size:13px;">
                            <i class="fa fa-compress"></i> Ceiling by constraint
                            <small class="text-muted" style="font-weight:400;text-transform:none;letter-spacing:0;">
                                &mdash; the smallest number is the wall
                            </small>
                        </div>
                        <div class="perf-card" style="border-left-color:#8e44ad; overflow-x:auto; padding:0;">
                            <table class="table table-condensed table-striped" id="cap_constraint_table">
                                <thead>
                                    <tr style="background:#34495e; color:#fff;">
                                        <th>Constraint</th>
                                        <th>Right now</th>
                                        <th>Limit</th>
                                        <th style="text-align:center;">Tenants allowed</th>
                                        <th style="text-align:center;">Evidence</th>
                                        <th>Basis</th>
                                    </tr>
                                </thead>
                                <tbody id="cap_constraint_tbody">
                                    <tr><td colspan="6" class="text-center text-muted">Loading&hellip;</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- What is holding the number down -->
                        <div class="section-title mtop20" style="font-size:13px;">
                            <i class="fa fa-unlock-alt"></i> What is holding the number down
                        </div>
                        <div id="cap_blockers"><p class="text-muted" style="font-size:12px;">Loading&hellip;</p></div>

                        <div class="cap-basis" id="cap_basis"></div>

                        <hr />

                        <!-- ═══ TOP GAUGES ═══ -->
                        <div class="row">
                            <!-- CPU -->
                            <div class="col-md-3 col-sm-6">
                                <div class="perf-card card-cpu">
                                    <div class="card-title"><i class="fa fa-microchip"></i> CPU Load</div>
                                    <div class="card-value" id="cpu_pct">--%</div>
                                    <div class="card-sub">Load: <span id="cpu_load">--</span> &nbsp;|&nbsp; Cores: <span id="cpu_cores">--</span></div>
                                    <div class="gauge-bar"><div class="fill green" id="cpu_bar" style="width:0%"></div></div>
                                </div>
                            </div>
                            <!-- Memory -->
                            <div class="col-md-3 col-sm-6">
                                <div class="perf-card card-memory">
                                    <div class="card-title"><i class="fa fa-database"></i> Memory</div>
                                    <div class="card-value" id="mem_pct">--%</div>
                                    <div class="card-sub"><span id="mem_used">--</span> / <span id="mem_total">--</span> MB</div>
                                    <div class="gauge-bar"><div class="fill green" id="mem_bar" style="width:0%"></div></div>
                                </div>
                            </div>
                            <!-- Disk -->
                            <div class="col-md-3 col-sm-6">
                                <div class="perf-card card-disk">
                                    <div class="card-title"><i class="fa fa-hdd-o"></i> Disk</div>
                                    <div class="card-value" id="disk_pct">--%</div>
                                    <div class="card-sub"><span id="disk_used">--</span> / <span id="disk_total">--</span> GB</div>
                                    <div class="gauge-bar"><div class="fill green" id="disk_bar" style="width:0%"></div></div>
                                </div>
                            </div>
                            <!-- Server Info -->
                            <div class="col-md-3 col-sm-6">
                                <div class="perf-card card-backup">
                                    <div class="card-title"><i class="fa fa-server"></i> Server Info</div>
                                    <div class="card-value" id="server_qps">--<small style="font-size:14px; font-weight:400;"> q/s</small></div>
                                    <div class="card-sub">Table Lock Waits: <span id="server_locks">--</span></div>
                                    <div class="card-sub mtop5">PHP Mem: <span id="php_mem">--</span> MB (Peak: <span id="php_peak">--</span> MB)</div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ MYSQL STATS ═══ -->
                        <div class="section-title"><i class="fa fa-database"></i> MySQL Server Statistics</div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="perf-card card-mysql" style="border-left-width:4px;">
                                    <div class="row">
                                        <div class="col-xs-4 col-sm-2 mysql-stat">
                                            <div class="stat-val" id="mysql_threads">--</div>
                                            <div class="stat-label">Threads</div>
                                        </div>
                                        <div class="col-xs-4 col-sm-2 mysql-stat">
                                            <div class="stat-val" id="mysql_running">--</div>
                                            <div class="stat-label">Running</div>
                                        </div>
                                        <div class="col-xs-4 col-sm-2 mysql-stat">
                                            <div class="stat-val" id="mysql_qps">--</div>
                                            <div class="stat-label">Queries/sec</div>
                                        </div>
                                        <div class="col-xs-4 col-sm-2 mysql-stat">
                                            <div class="stat-val" id="mysql_slow">--</div>
                                            <div class="stat-label">Slow Queries</div>
                                        </div>
                                        <div class="col-xs-4 col-sm-2 mysql-stat">
                                            <div class="stat-val" id="mysql_open_tables">--</div>
                                            <div class="stat-label">Open Tables</div>
                                        </div>
                                        <div class="col-xs-4 col-sm-2 mysql-stat">
                                            <div class="stat-val" id="mysql_uptime">--</div>
                                            <div class="stat-label">Uptime</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ CHARTS ═══ -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="perf-card card-cpu">
                                    <div class="section-title"><i class="fa fa-line-chart"></i> CPU Load History</div>
                                    <div class="chart-container">
                                        <canvas id="chart_cpu"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="perf-card card-memory">
                                    <div class="section-title"><i class="fa fa-area-chart"></i> Memory Usage History</div>
                                    <div class="chart-container">
                                        <canvas id="chart_memory"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="perf-card card-mysql">
                                    <div class="section-title"><i class="fa fa-bar-chart"></i> MySQL Threads History</div>
                                    <div class="chart-container">
                                        <canvas id="chart_mysql"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="perf-card card-disk">
                                    <div class="section-title"><i class="fa fa-pie-chart"></i> Disk Usage</div>
                                    <div class="chart-container">
                                        <canvas id="chart_disk"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ PROCESS LIST ═══ -->
                        <div class="section-title"><i class="fa fa-list"></i> MySQL Active Processes</div>
                        <div class="perf-card" style="border-left-color:#34495e; overflow-x:auto;">
                            <table class="table table-condensed table-striped" id="process_table" style="margin-bottom:0; font-size:12px;">
                                <thead>
                                    <tr style="background:#34495e; color:#fff;">
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Host</th>
                                        <th>DB</th>
                                        <th>Command</th>
                                        <th>Time</th>
                                        <th>State</th>
                                        <th>Info</th>
                                    </tr>
                                </thead>
                                <tbody id="process_tbody">
                                    <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ═══ TENANT PROCESS LISTS ═══ -->
                        <div class="section-title mtop20"><i class="fa fa-sitemap"></i> MySQL Active Processes — Tenants</div>

                        <!-- Summary stats -->
                        <div class="tenant-summary-row" id="tenant_summary_row">
                            <div class="tenant-stat-box stat-info">
                                <div class="tsb-val" id="ts_tenants">--</div>
                                <div class="tsb-label">Tenants</div>
                            </div>
                            <div class="tenant-stat-box">
                                <div class="tsb-val" id="ts_total">--</div>
                                <div class="tsb-label">Total Processes</div>
                            </div>
                            <div class="tenant-stat-box stat-warning">
                                <div class="tsb-val" id="ts_active">--</div>
                                <div class="tsb-label">Active Queries</div>
                            </div>
                            <div class="tenant-stat-box stat-ok">
                                <div class="tsb-val" id="ts_sleeping">--</div>
                                <div class="tsb-label">Sleeping</div>
                            </div>
                            <div class="tenant-stat-box stat-danger">
                                <div class="tsb-val" id="ts_slow">--</div>
                                <div class="tsb-label">Slow (&gt;5s)</div>
                            </div>
                            <div class="tenant-stat-box stat-danger">
                                <div class="tsb-val" id="ts_errors">--</div>
                                <div class="tsb-label">Errors</div>
                            </div>
                        </div>

                        <!-- Toolbar -->
                        <div class="tenant-toolbar">
                            <input type="text" class="tenant-search" id="tenant_search" placeholder="🔍  Search tenant name, user, DB, query...">
                            <label><input type="checkbox" id="tenant_hide_sleep"> Hide Sleep</label>
                            <label><input type="checkbox" id="tenant_only_slow"> Only Slow (&gt;5s)</label>
                        </div>

                        <!-- Unified Table -->
                        <div class="perf-card" style="border-left-color:#2980b9; overflow-x:auto; padding:0;">
                            <table class="table table-condensed table-striped" id="tenant_unified_table">
                                <thead>
                                    <tr style="background:#34495e; color:#fff;">
                                        <th>Tenant</th>
                                        <th>Processes</th>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Host</th>
                                        <th>DB</th>
                                        <th>Command</th>
                                        <th>Time</th>
                                        <th>State</th>
                                        <th>Info</th>
                                    </tr>
                                </thead>
                                <tbody id="tenant_unified_tbody">
                                    <tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Error list -->
                        <div class="tenant-error-list" id="tenant_error_list"></div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php init_tail(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo module_dir_url('ccx_db', 'assets/js/ccx_db_performance.js'); ?>?v=<?php echo time(); ?>"></script>
