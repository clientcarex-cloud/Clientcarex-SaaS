<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url('todo', 'assets/css/todo.css'); ?>?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo module_dir_url('todo', 'assets/css/reports.css'); ?>?v=<?php echo time(); ?>">

<div id="wrapper">
  <div class="content" style="padding:0 !important;margin:0 !important;">

      <!-- ═══ Page Tabs ═══ -->
      <div class="rpt-page-tabs">
        <a href="<?php echo admin_url('todo'); ?>" class="rpt-page-tab">
          <i class="fa-solid fa-list-check"></i> Tasks
        </a>
        <a href="<?php echo admin_url('todo'); ?>#templates" class="rpt-page-tab">
          <i class="fa-solid fa-clipboard-list"></i> Templates
        </a>
        <a href="<?php echo admin_url('todo/reports'); ?>" class="rpt-page-tab active">
          <i class="fas fa-chart-bar"></i> Reports
        </a>
      </div>

    <div id="todo-app">

      <!-- ═══ Report Header ═══ -->
      <div class="rpt-header">
        <div class="rpt-header-info">
          <h1 class="rpt-title">Performance Reports</h1>
          <p class="rpt-subtitle">Corporate-level staff task & checklist analytics — Plan-Do-Check-Act</p>
        </div>
        <div class="rpt-header-actions">
          <button class="todo-btn todo-btn-sm todo-btn-ghost" id="btn-toggle-filters">
            <i class="fas fa-sliders-h"></i> Filters
          </button>
          <button class="todo-btn todo-btn-sm todo-btn-primary" id="btn-export-report">
            <i class="fas fa-download"></i> Export
          </button>
        </div>
      </div>

      <!-- ═══ Filters Panel ═══ -->
      <div class="rpt-filters-panel" id="rpt-filters-panel">
        <div class="rpt-filters-row">
          <div class="rpt-filter-group">
            <label class="rpt-filter-label">Staff Member</label>
            <select class="todo-form-input rpt-filter-select" id="rpt-filter-staff">
              <option value="">All Staff</option>
              <?php foreach ($staff_list as $s) { ?>
                <option value="<?php echo $s['staffid']; ?>"><?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="rpt-filter-group">
            <label class="rpt-filter-label">Category</label>
            <select class="todo-form-input rpt-filter-select" id="rpt-filter-category">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat) { ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="rpt-filter-group">
            <label class="rpt-filter-label">From</label>
            <input type="date" class="todo-form-input rpt-filter-date" id="rpt-filter-from" />
          </div>
          <div class="rpt-filter-group">
            <label class="rpt-filter-label">To</label>
            <input type="date" class="todo-form-input rpt-filter-date" id="rpt-filter-to" />
          </div>
          <div class="rpt-filter-group rpt-filter-actions">
            <button class="todo-btn todo-btn-sm todo-btn-primary" id="btn-apply-filters">
              <i class="fas fa-search"></i> Apply
            </button>
            <button class="todo-btn todo-btn-sm todo-btn-ghost" id="btn-clear-filters">
              <i class="fas fa-times"></i> Clear
            </button>
          </div>
        </div>
      </div>

      <?php
        $summary = $report['summary'];
        $total = (int)($summary['total_tasks'] ?? 0);
        $completed = (int)($summary['completed'] ?? 0);
        $pending = (int)($summary['pending'] ?? 0);
        $inProgress = (int)($summary['in_progress'] ?? 0);
        $overdue = (int)($summary['overdue'] ?? 0);
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
      ?>

      <!-- ═══ KPI Summary Cards ═══ -->
      <div class="rpt-kpi-grid" id="rpt-kpi-grid">
        <div class="rpt-kpi-card rpt-kpi-total">
          <div class="rpt-kpi-ring" data-pct="100">
            <svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/><circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:100, 100; stroke:#6366f1;"/></svg>
            <div class="rpt-kpi-ring-val"><?php echo $total; ?></div>
          </div>
          <div class="rpt-kpi-text">
            <div class="rpt-kpi-value" id="kpi-total"><?php echo $total; ?></div>
            <div class="rpt-kpi-label">Total Tasks</div>
          </div>
        </div>
        <div class="rpt-kpi-card rpt-kpi-completed">
          <div class="rpt-kpi-ring" data-pct="<?php echo $completionRate; ?>">
            <svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/><circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:<?php echo $completionRate; ?>, 100; stroke:#16a34a;"/></svg>
            <div class="rpt-kpi-ring-val"><?php echo $completionRate; ?>%</div>
          </div>
          <div class="rpt-kpi-text">
            <div class="rpt-kpi-value" id="kpi-completed"><?php echo $completed; ?></div>
            <div class="rpt-kpi-label">Completed</div>
          </div>
        </div>
        <div class="rpt-kpi-card rpt-kpi-progress">
          <div class="rpt-kpi-ring" data-pct="<?php echo $total > 0 ? round(($inProgress / $total) * 100, 1) : 0; ?>">
            <svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/><circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:<?php echo $total > 0 ? round(($inProgress / $total) * 100, 1) : 0; ?>, 100; stroke:#2563eb;"/></svg>
            <div class="rpt-kpi-ring-val"><?php echo $inProgress; ?></div>
          </div>
          <div class="rpt-kpi-text">
            <div class="rpt-kpi-value" id="kpi-progress"><?php echo $inProgress; ?></div>
            <div class="rpt-kpi-label">In Progress</div>
          </div>
        </div>
        <div class="rpt-kpi-card rpt-kpi-pending">
          <div class="rpt-kpi-ring" data-pct="<?php echo $total > 0 ? round(($pending / $total) * 100, 1) : 0; ?>">
            <svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/><circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:<?php echo $total > 0 ? round(($pending / $total) * 100, 1) : 0; ?>, 100; stroke:#ea580c;"/></svg>
            <div class="rpt-kpi-ring-val"><?php echo $pending; ?></div>
          </div>
          <div class="rpt-kpi-text">
            <div class="rpt-kpi-value" id="kpi-pending"><?php echo $pending; ?></div>
            <div class="rpt-kpi-label">Pending</div>
          </div>
        </div>
        <div class="rpt-kpi-card rpt-kpi-overdue">
          <div class="rpt-kpi-ring" data-pct="<?php echo $total > 0 ? round(($overdue / $total) * 100, 1) : 0; ?>">
            <svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/><circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:<?php echo $total > 0 ? round(($overdue / $total) * 100, 1) : 0; ?>, 100; stroke:#dc2626;"/></svg>
            <div class="rpt-kpi-ring-val"><?php echo $overdue; ?></div>
          </div>
          <div class="rpt-kpi-text">
            <div class="rpt-kpi-value" id="kpi-overdue"><?php echo $overdue; ?></div>
            <div class="rpt-kpi-label">Overdue</div>
          </div>
        </div>
      </div>

      <!-- ═══ PDCA Section ═══ -->
      <div class="rpt-section">
        <div class="rpt-section-header">
          <h2 class="rpt-section-title"><i class="fas fa-sync-alt"></i> Plan-Do-Check-Act Cycle</h2>
        </div>
        <div class="rpt-pdca-grid">
          <div class="rpt-pdca-card pdca-plan">
            <div class="rpt-pdca-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="rpt-pdca-label">PLAN</div>
            <div class="rpt-pdca-value" id="pdca-plan"><?php echo $total; ?></div>
            <div class="rpt-pdca-desc">Tasks Assigned</div>
          </div>
          <div class="rpt-pdca-arrow"><i class="fas fa-chevron-right"></i></div>
          <div class="rpt-pdca-card pdca-do">
            <div class="rpt-pdca-icon"><i class="fas fa-cogs"></i></div>
            <div class="rpt-pdca-label">DO</div>
            <div class="rpt-pdca-value" id="pdca-do"><?php echo $inProgress + $completed; ?></div>
            <div class="rpt-pdca-desc">Tasks Acted Upon</div>
          </div>
          <div class="rpt-pdca-arrow"><i class="fas fa-chevron-right"></i></div>
          <div class="rpt-pdca-card pdca-check">
            <div class="rpt-pdca-icon"><i class="fas fa-check-double"></i></div>
            <div class="rpt-pdca-label">CHECK</div>
            <div class="rpt-pdca-value" id="pdca-check"><?php echo $completionRate; ?>%</div>
            <div class="rpt-pdca-desc">Completion Rate</div>
          </div>
          <div class="rpt-pdca-arrow"><i class="fas fa-chevron-right"></i></div>
          <div class="rpt-pdca-card pdca-act">
            <div class="rpt-pdca-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="rpt-pdca-label">ACT</div>
            <div class="rpt-pdca-value" id="pdca-act"><?php echo $overdue; ?></div>
            <div class="rpt-pdca-desc">Overdue / Need Action</div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════ -->
      <!--  BNI-STYLE INCENTIVE SCORECARD SECTION  -->
      <!-- ═══════════════════════════════════════ -->
      <div class="rpt-section sc-section">
        <div class="rpt-section-header">
          <h2 class="rpt-section-title"><i class="fas fa-trophy"></i> Staff Incentive Scorecard — Power of One</h2>
          <div class="sc-period-picker">
            <select class="todo-form-input" id="sc-month">
              <?php
                $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                for ($m = 1; $m <= 12; $m++) {
                  $sel = ($m == (int)date('m')) ? ' selected' : '';
                  echo '<option value="' . $m . '"' . $sel . '>' . $months[$m-1] . '</option>';
                }
              ?>
            </select>
            <select class="todo-form-input" id="sc-year">
              <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--) { ?>
                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
              <?php } ?>
            </select>
            <button class="todo-btn todo-btn-sm todo-btn-primary" id="btn-load-scorecards"><i class="fas fa-sync-alt"></i> Load</button>
            <button class="todo-btn todo-btn-sm todo-btn-ghost" id="btn-print-scorecard"><i class="fas fa-print"></i> Print Report</button>
          </div>
        </div>

        <!-- Traffic Light Summary Cards -->
        <div class="sc-tl-summary" id="sc-tl-summary">
          <?php
            $tl_green = 0; $tl_yellow = 0; $tl_red = 0;
            if (!empty($scorecards)) {
              foreach ($scorecards as $sc) {
                if ($sc['traffic_light'] === 'green') $tl_green++;
                elseif ($sc['traffic_light'] === 'yellow') $tl_yellow++;
                else $tl_red++;
              }
            }
          ?>
          <div class="sc-tl-card sc-tl-green">
            <div class="sc-tl-dot tl-green"></div>
            <div class="sc-tl-info">
              <div class="sc-tl-count" id="sc-tl-green-count"><?php echo $tl_green; ?></div>
              <div class="sc-tl-label">Green Zone <small>≥80%</small></div>
            </div>
            <div class="sc-tl-desc">Excellent</div>
          </div>
          <div class="sc-tl-card sc-tl-yellow">
            <div class="sc-tl-dot tl-yellow"></div>
            <div class="sc-tl-info">
              <div class="sc-tl-count" id="sc-tl-yellow-count"><?php echo $tl_yellow; ?></div>
              <div class="sc-tl-label">Yellow Zone <small>40-79%</small></div>
            </div>
            <div class="sc-tl-desc">Needs Improvement</div>
          </div>
          <div class="sc-tl-card sc-tl-red">
            <div class="sc-tl-dot tl-red"></div>
            <div class="sc-tl-info">
              <div class="sc-tl-count" id="sc-tl-red-count"><?php echo $tl_red; ?></div>
              <div class="sc-tl-label">Red Zone <small>&lt;40%</small></div>
            </div>
            <div class="sc-tl-desc">At Risk</div>
          </div>
        </div>

        <!-- Staff Scorecard Table -->
        <div class="rpt-table-wrap" id="sc-table-wrap">
          <?php if (!empty($scorecards)) { ?>
          <table class="rpt-table sc-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Staff Member</th>
                <th class="rpt-th-center">Status</th>
                <th class="rpt-th-center">Score</th>
                <th class="rpt-th-center">Power of One</th>
                <th class="rpt-th-center">On Time</th>
                <th class="rpt-th-center">Early</th>
                <th class="rpt-th-center">Late</th>
                <th class="rpt-th-center">Overdue</th>
                <th class="rpt-th-center">Attendance</th>
                <th class="rpt-th-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $rank = 0; foreach ($scorecards as $sc) { $rank++;
                $ts = $sc['task_stats'];
                $att = $sc['attendance'];
                $tlClass = 'tl-' . $sc['traffic_light'];
                $tlLabel = ucfirst($sc['traffic_light']);
              ?>
              <tr class="sc-row <?php echo $tlClass; ?>-row">
                <td class="rpt-td-center"><span class="sc-rank"><?php echo $rank; ?></span></td>
                <td>
                  <div class="rpt-staff-name">
                    <div class="rpt-avatar"><?php echo strtoupper(substr($sc['staff_name'], 0, 1)); ?></div>
                    <span><?php echo htmlspecialchars($sc['staff_name']); ?></span>
                  </div>
                </td>
                <td class="rpt-td-center">
                  <span class="sc-tl-badge <?php echo $tlClass; ?>"><?php echo $tlLabel; ?></span>
                </td>
                <td class="rpt-td-center">
                  <span class="sc-score-val"><?php echo $sc['total_score']; ?></span>
                  <span class="sc-score-max">/ <?php echo $sc['max_possible']; ?></span>
                </td>
                <td class="rpt-td-center">
                  <div class="sc-power-ring">
                    <svg viewBox="0 0 36 36">
                      <circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/>
                      <circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155"
                        style="stroke-dasharray:<?php echo $sc['power_score']; ?>, 100;
                        stroke:<?php echo $sc['traffic_light'] === 'green' ? '#16a34a' : ($sc['traffic_light'] === 'yellow' ? '#eab308' : '#dc2626'); ?>;"/>
                    </svg>
                    <div class="sc-power-val"><?php echo $sc['power_score']; ?>%</div>
                  </div>
                </td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-success"><?php echo (int)($ts['on_time'] ?? 0); ?></span></td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-info"><?php echo (int)($ts['early'] ?? 0); ?></span></td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-warn"><?php echo (int)($ts['late'] ?? 0); ?></span></td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-danger"><?php echo (int)($ts['still_overdue'] ?? 0); ?></span></td>
                <td class="rpt-td-center">
                  <span class="sc-att-badge"><?php echo (int)($att['present'] ?? 0); ?>P / <?php echo (int)($att['late'] ?? 0); ?>L / <?php echo (int)($att['absent'] ?? 0); ?>A</span>
                </td>
                <td class="rpt-td-center">
                  <button class="todo-btn todo-btn-sm todo-btn-ghost sc-detail-btn" data-staff-id="<?php echo $sc['staff_id']; ?>">
                    <i class="fas fa-eye"></i>
                  </button>
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
          <?php } else { ?>
          <div class="rpt-empty-state">
            <i class="fas fa-trophy"></i>
            <h3>No scorecard data yet</h3>
            <p>Complete tasks and mark attendance to see scores here.</p>
          </div>
          <?php } ?>
        </div>

        <!-- Scoring Legend -->
        <div class="sc-legend">
          <div class="sc-legend-title"><i class="fas fa-info-circle"></i> Scoring Rules</div>
          <div class="sc-legend-grid">
            <div class="sc-legend-item sc-legend-pos"><span class="sc-legend-pts">+10</span> Task completed</div>
            <div class="sc-legend-item sc-legend-pos"><span class="sc-legend-pts">+5</span> Completed 1+ day early</div>
            <div class="sc-legend-item sc-legend-pos"><span class="sc-legend-pts">+3</span> Completed 2+ days early</div>
            <div class="sc-legend-item sc-legend-pos"><span class="sc-legend-pts">+5</span> High/Urgent priority on-time</div>
            <div class="sc-legend-item sc-legend-pos"><span class="sc-legend-pts">+3</span> 100% checklist complete</div>
            <div class="sc-legend-item sc-legend-pos"><span class="sc-legend-pts">+5</span> Attendance (present)</div>
            <div class="sc-legend-item sc-legend-neg"><span class="sc-legend-pts">-1/day</span> Late (1-3 days)</div>
            <div class="sc-legend-item sc-legend-neg"><span class="sc-legend-pts">-5</span> Late (3+ days)</div>
            <div class="sc-legend-item sc-legend-neg"><span class="sc-legend-pts">-2/day</span> Overdue running (cap -20)</div>
            <div class="sc-legend-item"><span class="sc-legend-pts">+3</span> Attendance (late arrival)</div>
          </div>
        </div>
      </div>

      <!-- ═══ Attendance & Manual Adjustment ═══ -->
      <div class="rpt-two-col">
        <!-- Quick Attendance Entry -->
        <div class="rpt-section">
          <div class="rpt-section-header">
            <h2 class="rpt-section-title"><i class="fas fa-calendar-check"></i> Quick Attendance</h2>
          </div>
          <div class="sc-attendance-box">
            <div class="sc-att-date-row">
              <label class="rpt-filter-label">Date</label>
              <input type="date" class="todo-form-input" id="sc-att-date" value="<?php echo date('Y-m-d'); ?>" />
            </div>
            <div class="sc-att-grid" id="sc-att-grid">
              <?php foreach ($staff_list as $s) { ?>
              <div class="sc-att-row" data-staff-id="<?php echo $s['staffid']; ?>">
                <div class="sc-att-name"><?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?></div>
                <div class="sc-att-btns">
                  <button class="sc-att-btn sc-att-present" data-status="1" title="Present"><i class="fas fa-check"></i></button>
                  <button class="sc-att-btn sc-att-late-btn" data-status="2" title="Late"><i class="fas fa-clock"></i></button>
                  <button class="sc-att-btn sc-att-absent" data-status="0" title="Absent"><i class="fas fa-times"></i></button>
                </div>
              </div>
              <?php } ?>
            </div>
          </div>
        </div>

        <!-- Manual Adjustment -->
        <div class="rpt-section">
          <div class="rpt-section-header">
            <h2 class="rpt-section-title"><i class="fas fa-sliders-h"></i> Manual Score Adjustment</h2>
          </div>
          <div class="sc-manual-box">
            <div class="sc-manual-form">
              <div class="rpt-add-cat-row">
                <div class="rpt-add-cat-field">
                  <label class="rpt-filter-label">Staff Member</label>
                  <select class="todo-form-input" id="sc-manual-staff">
                    <?php foreach ($staff_list as $s) { ?>
                    <option value="<?php echo $s['staffid']; ?>"><?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="rpt-add-cat-field" style="max-width:100px;">
                  <label class="rpt-filter-label">Points (±)</label>
                  <input type="number" class="todo-form-input" id="sc-manual-points" placeholder="e.g. 5 or -3" step="1" />
                </div>
              </div>
              <div class="rpt-add-cat-row">
                <div class="rpt-add-cat-field" style="flex:1;">
                  <label class="rpt-filter-label">Reason *</label>
                  <input type="text" class="todo-form-input" id="sc-manual-reason" placeholder="e.g. Extra initiative on client report..." />
                </div>
              </div>
              <button class="todo-btn todo-btn-primary" id="btn-add-manual-score">
                <i class="fas fa-plus-circle"></i> Apply Adjustment
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Score Detail Modal (hidden, populated via JS) -->
      <div class="sc-modal-overlay" id="sc-modal-overlay" style="display:none;">
        <div class="sc-modal">
          <div class="sc-modal-header">
            <h3 class="sc-modal-title"><i class="fas fa-chart-line"></i> Score Detail</h3>
            <button class="sc-modal-close" id="sc-modal-close"><i class="fas fa-times"></i></button>
          </div>
          <div class="sc-modal-body" id="sc-modal-body">
            <div class="todo-loading"><div class="spinner"></div>Loading...</div>
          </div>
        </div>
      </div>

      <!-- ═══ PRINTABLE SCORECARD (hidden, used for window.print()) ═══ -->
      <div id="sc-print-area" class="sc-print-area">
        <!-- Populated dynamically by JS -->
      </div>

      <!-- ═══ Staff Performance Table ═══ -->
      <div class="rpt-section">
        <div class="rpt-section-header">
          <h2 class="rpt-section-title"><i class="fas fa-users"></i> Staff Performance</h2>
        </div>
        <div class="rpt-table-wrap" id="rpt-staff-table-wrap">
          <?php if (!empty($report['staff'])) { ?>
          <table class="rpt-table">
            <thead>
              <tr>
                <th>Staff Member</th>
                <th class="rpt-th-center">Total</th>
                <th class="rpt-th-center">Completed</th>
                <th class="rpt-th-center">In Progress</th>
                <th class="rpt-th-center">Pending</th>
                <th class="rpt-th-center">Overdue</th>
                <th class="rpt-th-center">Task Rate</th>
                <th class="rpt-th-center">Checklist Rate</th>
                <th>Plan → Do</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($report['staff'] as $staff) { ?>
              <tr>
                <td>
                  <div class="rpt-staff-name">
                    <div class="rpt-avatar"><?php echo strtoupper(substr($staff['staff_name'], 0, 1)); ?></div>
                    <span><?php echo htmlspecialchars($staff['staff_name']); ?></span>
                  </div>
                </td>
                <td class="rpt-td-center"><span class="rpt-num"><?php echo $staff['total_tasks']; ?></span></td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-success"><?php echo $staff['completed']; ?></span></td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-info"><?php echo $staff['in_progress']; ?></span></td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-warn"><?php echo $staff['pending']; ?></span></td>
                <td class="rpt-td-center"><span class="rpt-num rpt-num-danger"><?php echo $staff['overdue']; ?></span></td>
                <td class="rpt-td-center">
                  <div class="rpt-rate-bar">
                    <div class="rpt-rate-fill rpt-rate-success" style="width:<?php echo $staff['completion_rate']; ?>%"></div>
                    <span class="rpt-rate-label"><?php echo $staff['completion_rate']; ?>%</span>
                  </div>
                </td>
                <td class="rpt-td-center">
                  <div class="rpt-rate-bar">
                    <div class="rpt-rate-fill rpt-rate-accent" style="width:<?php echo $staff['checklist_rate']; ?>%"></div>
                    <span class="rpt-rate-label"><?php echo $staff['checklist_rate']; ?>%</span>
                  </div>
                </td>
                <td>
                  <div class="rpt-plan-do">
                    <span class="rpt-plan-badge"><?php echo $staff['plan_score']; ?></span>
                    <i class="fas fa-arrow-right rpt-plan-arrow"></i>
                    <span class="rpt-do-badge"><?php echo $staff['do_score']; ?></span>
                  </div>
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
          <?php } else { ?>
          <div class="rpt-empty-state">
            <i class="fas fa-chart-line"></i>
            <h3>No staff data found</h3>
            <p>Assign tasks to staff members to see their performance here.</p>
          </div>
          <?php } ?>
        </div>
      </div>

      <!-- ═══ Two-Column: Categories + Add New Category ═══ -->
      <div class="rpt-two-col">

        <!-- Category Breakdown -->
        <div class="rpt-section">
          <div class="rpt-section-header">
            <h2 class="rpt-section-title"><i class="fas fa-layer-group"></i> Category Breakdown</h2>
          </div>
          <div class="rpt-cat-grid" id="rpt-cat-grid">
            <?php if (!empty($report['categories'])) { ?>
              <?php foreach ($report['categories'] as $cat) { ?>
              <div class="rpt-cat-card">
                <div class="rpt-cat-icon-wrap" style="background:<?php echo $cat['category_color']; ?>15;">
                  <i class="fa <?php echo $cat['category_icon']; ?>" style="color:<?php echo $cat['category_color']; ?>;"></i>
                </div>
                <div class="rpt-cat-info">
                  <div class="rpt-cat-title"><?php echo htmlspecialchars($cat['category_name']); ?></div>
                  <div class="rpt-cat-stats">
                    <span><?php echo $cat['total_tasks']; ?> tasks</span>
                    <span class="rpt-cat-sep">•</span>
                    <span class="rpt-cat-done"><?php echo $cat['completed']; ?> done</span>
                    <?php if ((int)$cat['overdue'] > 0) { ?>
                      <span class="rpt-cat-sep">•</span>
                      <span class="rpt-cat-overdue"><?php echo $cat['overdue']; ?> overdue</span>
                    <?php } ?>
                  </div>
                </div>
                <div class="rpt-cat-rate">
                  <div class="rpt-mini-ring" data-pct="<?php echo $cat['completion_rate']; ?>">
                    <svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/><circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:<?php echo $cat['completion_rate']; ?>, 100; stroke:<?php echo $cat['category_color']; ?>;"/></svg>
                    <div class="rpt-mini-ring-val"><?php echo $cat['completion_rate']; ?>%</div>
                  </div>
                </div>
              </div>
              <?php } ?>
            <?php } else { ?>
              <div class="rpt-empty-state rpt-empty-sm">
                <i class="fas fa-folder-open"></i>
                <p>No category data yet.</p>
              </div>
            <?php } ?>
          </div>
        </div>

        <!-- Add New Category Box -->
        <div class="rpt-section">
          <div class="rpt-section-header">
            <h2 class="rpt-section-title"><i class="fas fa-plus-circle"></i> Add New Category</h2>
          </div>
          <div class="rpt-add-cat-box">
            <div class="rpt-add-cat-form">
              <div class="rpt-add-cat-row">
                <div class="rpt-add-cat-field">
                  <label class="rpt-filter-label">Category Name</label>
                  <input type="text" class="todo-form-input" id="rpt-new-cat-name" placeholder="e.g. Training, Marketing..." />
                </div>
                <div class="rpt-add-cat-field rpt-add-cat-color-field">
                  <label class="rpt-filter-label">Color</label>
                  <input type="color" class="rpt-color-picker" id="rpt-new-cat-color" value="#6366f1" />
                </div>
              </div>
              <div class="rpt-add-cat-row">
                <div class="rpt-add-cat-field">
                  <label class="rpt-filter-label">Icon</label>
                  <select class="todo-form-input" id="rpt-new-cat-icon">
                    <option value="fa-folder">📁 Folder</option>
                    <option value="fa-briefcase">💼 Briefcase</option>
                    <option value="fa-user">👤 User</option>
                    <option value="fa-users">👥 Users</option>
                    <option value="fa-phone">📞 Phone</option>
                    <option value="fa-bolt">⚡ Bolt</option>
                    <option value="fa-lightbulb">💡 Lightbulb</option>
                    <option value="fa-cog">⚙️ Settings</option>
                    <option value="fa-star">⭐ Star</option>
                    <option value="fa-heart">❤️ Heart</option>
                    <option value="fa-flag">🚩 Flag</option>
                    <option value="fa-calendar">📅 Calendar</option>
                    <option value="fa-book">📚 Book</option>
                    <option value="fa-chart-bar">📊 Chart</option>
                    <option value="fa-truck">🚚 Delivery</option>
                    <option value="fa-graduation-cap">🎓 Education</option>
                    <option value="fa-stethoscope">🩺 Medical</option>
                    <option value="fa-code">💻 Development</option>
                    <option value="fa-paint-brush">🎨 Design</option>
                    <option value="fa-bullhorn">📢 Marketing</option>
                  </select>
                </div>
              </div>
              <div class="rpt-add-cat-preview" id="rpt-cat-preview">
                <div class="rpt-cat-card rpt-cat-card-preview">
                  <div class="rpt-cat-icon-wrap" id="rpt-cat-preview-icon" style="background:#6366f115;">
                    <i class="fa fa-folder" style="color:#6366f1;" id="rpt-cat-preview-i"></i>
                  </div>
                  <div class="rpt-cat-info">
                    <div class="rpt-cat-title" id="rpt-cat-preview-name">Category Preview</div>
                    <div class="rpt-cat-stats"><span>0 tasks</span></div>
                  </div>
                </div>
              </div>
              <button class="todo-btn todo-btn-primary rpt-add-cat-btn" id="btn-rpt-add-cat">
                <i class="fas fa-plus-circle"></i> Create Category
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- ═══ 30-Day Activity Timeline ═══ -->
      <div class="rpt-section">
        <div class="rpt-section-header">
          <h2 class="rpt-section-title"><i class="fas fa-chart-area"></i> 30-Day Activity</h2>
        </div>
        <div class="rpt-timeline-chart" id="rpt-timeline-chart">
          <?php if (!empty($report['timeline'])) { ?>
            <?php
              $maxVal = 1;
              foreach ($report['timeline'] as $t) {
                $maxVal = max($maxVal, (int)$t['created'], (int)$t['completed']);
              }
            ?>
            <div class="rpt-chart-bars">
              <?php foreach ($report['timeline'] as $t) {
                $createdH = $maxVal > 0 ? round(((int)$t['created'] / $maxVal) * 100) : 0;
                $completedH = $maxVal > 0 ? round(((int)$t['completed'] / $maxVal) * 100) : 0;
                $dayLabel = date('M j', strtotime($t['date_val']));
              ?>
                <div class="rpt-chart-bar-group" title="<?php echo $dayLabel; ?> — Created: <?php echo $t['created']; ?>, Completed: <?php echo $t['completed']; ?>">
                  <div class="rpt-chart-bar rpt-bar-created" style="height:<?php echo max($createdH, 4); ?>%"></div>
                  <div class="rpt-chart-bar rpt-bar-completed" style="height:<?php echo max($completedH, 4); ?>%"></div>
                  <div class="rpt-chart-label"><?php echo date('j', strtotime($t['date_val'])); ?></div>
                </div>
              <?php } ?>
            </div>
            <div class="rpt-chart-legend">
              <span class="rpt-legend-item"><span class="rpt-legend-dot rpt-dot-created"></span> Created</span>
              <span class="rpt-legend-item"><span class="rpt-legend-dot rpt-dot-completed"></span> Completed</span>
            </div>
          <?php } else { ?>
            <div class="rpt-empty-state rpt-empty-sm">
              <i class="fas fa-chart-area"></i>
              <p>No activity data in the last 30 days.</p>
            </div>
          <?php } ?>
        </div>
      </div>

      <!-- ═══ Priority Mix ═══ -->
      <div class="rpt-section">
        <div class="rpt-section-header">
          <h2 class="rpt-section-title"><i class="fas fa-signal"></i> Priority Distribution</h2>
        </div>
        <div class="rpt-priority-mix">
          <?php
            $pData = [
              ['label' => 'Urgent', 'count' => (int)($summary['urgent'] ?? 0), 'color' => '#e11d48', 'icon' => 'fa-bolt'],
              ['label' => 'High',   'count' => (int)($summary['high'] ?? 0),   'color' => '#ea580c', 'icon' => 'fa-arrow-up'],
              ['label' => 'Medium', 'count' => (int)($summary['medium'] ?? 0), 'color' => '#2563eb', 'icon' => 'fa-minus'],
              ['label' => 'Low',    'count' => (int)($summary['low'] ?? 0),    'color' => '#16a34a', 'icon' => 'fa-arrow-down'],
            ];
            foreach ($pData as $pr) {
              $prPct = $total > 0 ? round(($pr['count'] / $total) * 100, 1) : 0;
          ?>
            <div class="rpt-priority-card">
              <div class="rpt-priority-icon" style="background:<?php echo $pr['color']; ?>15; color:<?php echo $pr['color']; ?>;">
                <i class="fas <?php echo $pr['icon']; ?>"></i>
              </div>
              <div class="rpt-priority-info">
                <div class="rpt-priority-label"><?php echo $pr['label']; ?></div>
                <div class="rpt-priority-count"><?php echo $pr['count']; ?> tasks</div>
              </div>
              <div class="rpt-priority-bar-wrap">
                <div class="rpt-priority-bar" style="width:<?php echo $prPct; ?>%; background:<?php echo $pr['color']; ?>;"></div>
              </div>
              <div class="rpt-priority-pct" style="color:<?php echo $pr['color']; ?>;"><?php echo $prPct; ?>%</div>
            </div>
          <?php } ?>
        </div>
      </div>

    </div><!-- /todo-app -->
  </div>
</div>

<?php init_tail(); ?>

<script>
  var csrfData = {
    token_name: '<?php echo $this->security->get_csrf_token_name(); ?>',
    hash: '<?php echo $this->security->get_csrf_hash(); ?>'
  };
</script>
<script src="<?php echo module_dir_url('todo', 'assets/js/reports.js'); ?>?v=<?php echo time(); ?>"></script>
