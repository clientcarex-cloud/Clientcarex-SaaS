/**
 * TODO Reports — Dashboard JS Engine
 * Handles filters, AJAX report refresh, add category, and export
 */
var TodoReports = (function() {
    'use strict';

    var BASE_URL   = typeof admin_url !== 'undefined' ? admin_url : '';
    var CSRF_NAME  = typeof csrfData !== 'undefined' ? csrfData.token_name : '';
    var CSRF_HASH  = typeof csrfData !== 'undefined' ? csrfData.hash : '';

    function init() {
        bindEvents();
        bindScorecardEvents();
        updateCategoryPreview();
    }

    function bindEvents() {
        // Toggle filters panel
        $(document).on('click', '#btn-toggle-filters', function() {
            $('#rpt-filters-panel').toggleClass('show');
        });

        // Apply filters
        $(document).on('click', '#btn-apply-filters', function() {
            loadReportData();
        });

        // Clear filters
        $(document).on('click', '#btn-clear-filters', function() {
            $('#rpt-filter-staff').val('');
            $('#rpt-filter-category').val('');
            $('#rpt-filter-from').val('');
            $('#rpt-filter-to').val('');
            loadReportData();
        });

        // Live category preview
        $(document).on('input change', '#rpt-new-cat-name, #rpt-new-cat-color, #rpt-new-cat-icon', function() {
            updateCategoryPreview();
        });

        // Add category
        $(document).on('click', '#btn-rpt-add-cat', function() {
            addCategory();
        });

        // Export report
        $(document).on('click', '#btn-export-report', function() {
            exportReport();
        });
    }

    // ── AJAX Post ──
    function ajaxPost(endpoint, data, callback) {
        data[CSRF_NAME] = CSRF_HASH;
        $.ajax({
            url: BASE_URL + 'todo/' + endpoint,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res && res.csrf_hash) { CSRF_HASH = res.csrf_hash; }
                callback(res);
            },
            error: function(xhr) {
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.csrf_hash) CSRF_HASH = r.csrf_hash;
                } catch(e) {}
                console.error('AJAX Error:', endpoint, xhr.statusText);
            }
        });
    }

    // ── Load Report Data via AJAX ──
    function loadReportData() {
        var filters = {
            staff_id:    $('#rpt-filter-staff').val(),
            category_id: $('#rpt-filter-category').val(),
            date_from:   $('#rpt-filter-from').val(),
            date_to:     $('#rpt-filter-to').val()
        };

        // Show loading state on KPI cards
        $('.rpt-kpi-value, .rpt-pdca-value').css('opacity', '0.4');

        ajaxPost('ajax_report_data', filters, function(res) {
            if (res.success) {
                updateDashboard(res.report);
            }
            $('.rpt-kpi-value, .rpt-pdca-value').css('opacity', '1');
        });
    }

    // ── Update entire dashboard ──
    function updateDashboard(report) {
        var s = report.summary;
        var total = parseInt(s.total_tasks) || 0;
        var completed = parseInt(s.completed) || 0;
        var inProgress = parseInt(s.in_progress) || 0;
        var pending = parseInt(s.pending) || 0;
        var overdue = parseInt(s.overdue) || 0;
        var completionRate = total > 0 ? Math.round((completed / total) * 1000) / 10 : 0;

        // KPI cards
        $('#kpi-total').text(total);
        $('#kpi-completed').text(completed);
        $('#kpi-progress').text(inProgress);
        $('#kpi-pending').text(pending);
        $('#kpi-overdue').text(overdue);

        // Update ring SVGs
        var rings = document.querySelectorAll('.rpt-kpi-card');
        if (rings.length >= 5) {
            updateRing(rings[0], 100, total);
            updateRing(rings[1], completionRate, completionRate + '%');
            updateRing(rings[2], total > 0 ? Math.round((inProgress / total) * 1000) / 10 : 0, inProgress);
            updateRing(rings[3], total > 0 ? Math.round((pending / total) * 1000) / 10 : 0, pending);
            updateRing(rings[4], total > 0 ? Math.round((overdue / total) * 1000) / 10 : 0, overdue);
        }

        // PDCA
        $('#pdca-plan').text(total);
        $('#pdca-do').text(inProgress + completed);
        $('#pdca-check').text(completionRate + '%');
        $('#pdca-act').text(overdue);

        // Staff table
        updateStaffTable(report.staff);

        // Category grid
        updateCategoryGrid(report.categories);

        // Timeline chart
        updateTimeline(report.timeline);

        // Priority mix
        updatePriorityMix(s, total);
    }

    function updateRing(card, pct, label) {
        var ring = card.querySelector('.rpt-ring-fill');
        var val = card.querySelector('.rpt-kpi-ring-val');
        if (ring) ring.style.strokeDasharray = pct + ', 100';
        if (val) val.textContent = label;
    }

    // ── Staff Table ──
    function updateStaffTable(staffData) {
        var $wrap = $('#rpt-staff-table-wrap');
        if (!staffData || staffData.length === 0) {
            $wrap.html(
                '<div class="rpt-empty-state">' +
                    '<i class="fas fa-chart-line"></i>' +
                    '<h3>No staff data found</h3>' +
                    '<p>Assign tasks to staff members to see their performance here.</p>' +
                '</div>'
            );
            return;
        }

        var html = '<table class="rpt-table"><thead><tr>' +
            '<th>Staff Member</th>' +
            '<th class="rpt-th-center">Total</th>' +
            '<th class="rpt-th-center">Completed</th>' +
            '<th class="rpt-th-center">In Progress</th>' +
            '<th class="rpt-th-center">Pending</th>' +
            '<th class="rpt-th-center">Overdue</th>' +
            '<th class="rpt-th-center">Task Rate</th>' +
            '<th class="rpt-th-center">Checklist Rate</th>' +
            '<th>Plan → Do</th>' +
        '</tr></thead><tbody>';

        staffData.forEach(function(s) {
            var initial = (s.staff_name || '?').charAt(0).toUpperCase();
            html += '<tr>' +
                '<td><div class="rpt-staff-name"><div class="rpt-avatar">' + initial + '</div><span>' + escHtml(s.staff_name) + '</span></div></td>' +
                '<td class="rpt-td-center"><span class="rpt-num">' + s.total_tasks + '</span></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-success">' + s.completed + '</span></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-info">' + s.in_progress + '</span></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-warn">' + s.pending + '</span></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-danger">' + s.overdue + '</span></td>' +
                '<td class="rpt-td-center">' +
                    '<div class="rpt-rate-bar"><div class="rpt-rate-fill rpt-rate-success" style="width:' + s.completion_rate + '%"></div></div>' +
                    '<span class="rpt-rate-label">' + s.completion_rate + '%</span>' +
                '</td>' +
                '<td class="rpt-td-center">' +
                    '<div class="rpt-rate-bar"><div class="rpt-rate-fill rpt-rate-accent" style="width:' + s.checklist_rate + '%"></div></div>' +
                    '<span class="rpt-rate-label">' + s.checklist_rate + '%</span>' +
                '</td>' +
                '<td><div class="rpt-plan-do">' +
                    '<span class="rpt-plan-badge">' + s.plan_score + '</span>' +
                    '<i class="fas fa-arrow-right rpt-plan-arrow"></i>' +
                    '<span class="rpt-do-badge">' + s.do_score + '</span>' +
                '</div></td>' +
            '</tr>';
        });

        html += '</tbody></table>';
        $wrap.html(html);
    }

    // ── Category Grid ──
    function updateCategoryGrid(catData) {
        var $grid = $('#rpt-cat-grid');
        if (!catData || catData.length === 0) {
            $grid.html('<div class="rpt-empty-state rpt-empty-sm"><i class="fas fa-folder-open"></i><p>No category data yet.</p></div>');
            return;
        }

        var html = '';
        catData.forEach(function(c) {
            html += '<div class="rpt-cat-card">' +
                '<div class="rpt-cat-icon-wrap" style="background:' + c.category_color + '15;">' +
                    '<i class="fa ' + c.category_icon + '" style="color:' + c.category_color + ';"></i>' +
                '</div>' +
                '<div class="rpt-cat-info">' +
                    '<div class="rpt-cat-title">' + escHtml(c.category_name) + '</div>' +
                    '<div class="rpt-cat-stats">' +
                        '<span>' + c.total_tasks + ' tasks</span>' +
                        '<span class="rpt-cat-sep">•</span>' +
                        '<span class="rpt-cat-done">' + c.completed + ' done</span>' +
                        (parseInt(c.overdue) > 0 ? '<span class="rpt-cat-sep">•</span><span class="rpt-cat-overdue">' + c.overdue + ' overdue</span>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="rpt-cat-rate">' +
                    '<div class="rpt-mini-ring">' +
                        '<svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/>' +
                        '<circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:' + c.completion_rate + ', 100; stroke:' + c.category_color + ';"/></svg>' +
                        '<div class="rpt-mini-ring-val">' + c.completion_rate + '%</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        });

        $grid.html(html);
    }

    // ── Timeline Chart ──
    function updateTimeline(timeline) {
        var $chart = $('#rpt-timeline-chart');
        if (!timeline || timeline.length === 0) {
            $chart.html('<div class="rpt-empty-state rpt-empty-sm"><i class="fas fa-chart-area"></i><p>No activity data in the last 30 days.</p></div>');
            return;
        }

        var maxVal = 1;
        timeline.forEach(function(t) {
            maxVal = Math.max(maxVal, parseInt(t.created), parseInt(t.completed));
        });

        var barsHtml = '';
        timeline.forEach(function(t) {
            var createdH = Math.round((parseInt(t.created) / maxVal) * 100);
            var completedH = Math.round((parseInt(t.completed) / maxVal) * 100);
            var d = new Date(t.date_val + 'T00:00:00');
            var dayLabel = d.getDate();
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var fullLabel = months[d.getMonth()] + ' ' + d.getDate();

            barsHtml += '<div class="rpt-chart-bar-group" title="' + fullLabel + ' — Created: ' + t.created + ', Completed: ' + t.completed + '">' +
                '<div class="rpt-chart-bar rpt-bar-created" style="height:' + Math.max(createdH, 4) + '%"></div>' +
                '<div class="rpt-chart-bar rpt-bar-completed" style="height:' + Math.max(completedH, 4) + '%"></div>' +
                '<div class="rpt-chart-label">' + dayLabel + '</div>' +
            '</div>';
        });

        $chart.html(
            '<div class="rpt-chart-bars">' + barsHtml + '</div>' +
            '<div class="rpt-chart-legend">' +
                '<span class="rpt-legend-item"><span class="rpt-legend-dot rpt-dot-created"></span> Created</span>' +
                '<span class="rpt-legend-item"><span class="rpt-legend-dot rpt-dot-completed"></span> Completed</span>' +
            '</div>'
        );
    }

    // ── Priority Mix ──
    function updatePriorityMix(summary, total) {
        var pData = [
            { label: 'Urgent', count: parseInt(summary.urgent) || 0, color: '#e11d48', icon: 'fa-bolt' },
            { label: 'High',   count: parseInt(summary.high) || 0,   color: '#ea580c', icon: 'fa-arrow-up' },
            { label: 'Medium', count: parseInt(summary.medium) || 0, color: '#2563eb', icon: 'fa-minus' },
            { label: 'Low',    count: parseInt(summary.low) || 0,    color: '#16a34a', icon: 'fa-arrow-down' }
        ];

        var html = '';
        pData.forEach(function(pr) {
            var pct = total > 0 ? Math.round((pr.count / total) * 1000) / 10 : 0;
            html += '<div class="rpt-priority-card">' +
                '<div class="rpt-priority-icon" style="background:' + pr.color + '15; color:' + pr.color + ';">' +
                    '<i class="fas ' + pr.icon + '"></i>' +
                '</div>' +
                '<div class="rpt-priority-info">' +
                    '<div class="rpt-priority-label">' + pr.label + '</div>' +
                    '<div class="rpt-priority-count">' + pr.count + ' tasks</div>' +
                '</div>' +
                '<div class="rpt-priority-bar-wrap">' +
                    '<div class="rpt-priority-bar" style="width:' + pct + '%; background:' + pr.color + ';"></div>' +
                '</div>' +
                '<div class="rpt-priority-pct" style="color:' + pr.color + ';">' + pct + '%</div>' +
            '</div>';
        });

        $('.rpt-priority-mix').html(html);
    }

    // ── Category Preview ──
    function updateCategoryPreview() {
        var name  = $('#rpt-new-cat-name').val().trim() || 'Category Preview';
        var color = $('#rpt-new-cat-color').val() || '#6366f1';
        var icon  = $('#rpt-new-cat-icon').val() || 'fa-folder';

        $('#rpt-cat-preview-name').text(name);
        $('#rpt-cat-preview-icon').css('background', color + '15');
        $('#rpt-cat-preview-i').attr('class', 'fa ' + icon).css('color', color);
    }

    // ── Add Category ──
    function addCategory() {
        var name  = $('#rpt-new-cat-name').val().trim();
        var color = $('#rpt-new-cat-color').val();
        var icon  = $('#rpt-new-cat-icon').val();

        if (!name) {
            $('#rpt-new-cat-name').focus();
            return;
        }

        ajaxPost('ajax_add_category', { name: name, color: color, icon: icon }, function(res) {
            if (res.success) {
                // Reset form
                $('#rpt-new-cat-name').val('');
                $('#rpt-new-cat-color').val('#6366f1');
                $('#rpt-new-cat-icon').val('fa-folder');
                updateCategoryPreview();

                // Add to filter dropdown
                var c = res.category;
                $('#rpt-filter-category').append('<option value="' + c.id + '">' + escHtml(c.name) + '</option>');

                // Show success flash
                showToast('Category "' + escHtml(name) + '" created successfully!');

                // Reload report data
                loadReportData();
            } else {
                showToast('Failed to create category.', 'error');
            }
        });
    }

    // ── Export (simple CSV) ──
    function exportReport() {
        var $table = $('.rpt-table');
        if ($table.length === 0) {
            showToast('No data to export.', 'error');
            return;
        }

        var csv = [];
        $table.find('tr').each(function() {
            var row = [];
            $(this).find('th, td').each(function() {
                var text = $(this).text().replace(/"/g, '""').replace(/\s+/g, ' ').trim();
                row.push('"' + text + '"');
            });
            csv.push(row.join(','));
        });

        var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'todo_report_' + todayDate() + '.csv';
        link.click();

        showToast('Report exported successfully!');
    }

    // ═══════════════════════════════════════════
    //  BNI SCORECARD — Traffic Light / Power of One
    // ═══════════════════════════════════════════

    var scorecardCache = null;

    function bindScorecardEvents() {
        // Load scorecards
        $(document).on('click', '#btn-load-scorecards', function() {
            loadScorecards();
        });

        // Print scorecard
        $(document).on('click', '#btn-print-scorecard', function() {
            printScorecard();
        });

        // Staff detail modal
        $(document).on('click', '.sc-detail-btn', function() {
            var staffId = $(this).data('staff-id');
            showScoreDetail(staffId);
        });

        // Close modal
        $(document).on('click', '#sc-modal-close, #sc-modal-overlay', function(e) {
            if (e.target === this) {
                $('#sc-modal-overlay').fadeOut(200);
            }
        });

        // Attendance buttons
        $(document).on('click', '.sc-att-btn', function() {
            var $row = $(this).closest('.sc-att-row');
            var staffId = $row.data('staff-id');
            var status = $(this).data('status');
            var date = $('#sc-att-date').val();
            markAttendance(staffId, date, status, $(this));
        });

        // Manual score
        $(document).on('click', '#btn-add-manual-score', function() {
            addManualScore();
        });
    }

    // ── Load Scorecards ──
    function loadScorecards() {
        var month = $('#sc-month').val();
        var year = $('#sc-year').val();
        var daysInMonth = new Date(year, month, 0).getDate();
        var periodFrom = year + '-' + ('0' + month).slice(-2) + '-01';
        var periodTo = year + '-' + ('0' + month).slice(-2) + '-' + ('0' + daysInMonth).slice(-2);

        ajaxPost('ajax_get_scorecards', { period_from: periodFrom, period_to: periodTo }, function(res) {
            if (res.success) {
                scorecardCache = res;
                renderTrafficLightSummary(res.tl_summary);
                renderScorecardTable(res.scorecards);
            }
        });
    }

    // ── Render Traffic Light Summary ──
    function renderTrafficLightSummary(tl) {
        $('#sc-tl-green-count').text(tl.green || 0);
        $('#sc-tl-yellow-count').text(tl.yellow || 0);
        $('#sc-tl-red-count').text(tl.red || 0);
    }

    // ── Render Scorecard Table ──
    function renderScorecardTable(scorecards) {
        var $wrap = $('#sc-table-wrap');
        if (!scorecards || scorecards.length === 0) {
            $wrap.html('<div class="rpt-empty-state"><i class="fas fa-trophy"></i><h3>No scorecard data</h3><p>Complete tasks and mark attendance to see scores.</p></div>');
            return;
        }

        var html = '<table class="rpt-table sc-table"><thead><tr>' +
            '<th>#</th><th>Staff Member</th><th class="rpt-th-center">Status</th>' +
            '<th class="rpt-th-center">Score</th><th class="rpt-th-center">Power of One</th>' +
            '<th class="rpt-th-center">On Time</th><th class="rpt-th-center">Early</th>' +
            '<th class="rpt-th-center">Late</th><th class="rpt-th-center">Overdue</th>' +
            '<th class="rpt-th-center">Attendance</th><th class="rpt-th-center">Actions</th>' +
        '</tr></thead><tbody>';

        scorecards.forEach(function(sc, i) {
            var ts = sc.task_stats || {};
            var att = sc.attendance || {};
            var tlClass = 'tl-' + sc.traffic_light;
            var tlLabel = sc.traffic_light.charAt(0).toUpperCase() + sc.traffic_light.slice(1);
            var initial = (sc.staff_name || '?').charAt(0).toUpperCase();
            var strokeColor = sc.traffic_light === 'green' ? '#16a34a' : (sc.traffic_light === 'yellow' ? '#eab308' : '#dc2626');

            html += '<tr class="sc-row ' + tlClass + '-row">' +
                '<td class="rpt-td-center"><span class="sc-rank">' + (i + 1) + '</span></td>' +
                '<td><div class="rpt-staff-name"><div class="rpt-avatar">' + initial + '</div><span>' + escHtml(sc.staff_name) + '</span></div></td>' +
                '<td class="rpt-td-center"><span class="sc-tl-badge ' + tlClass + '">' + tlLabel + '</span></td>' +
                '<td class="rpt-td-center"><span class="sc-score-val">' + sc.total_score + '</span><span class="sc-score-max">/ ' + sc.max_possible + '</span></td>' +
                '<td class="rpt-td-center"><div class="sc-power-ring">' +
                    '<svg viewBox="0 0 36 36"><circle class="rpt-ring-bg" cx="18" cy="18" r="15.9155"/>' +
                    '<circle class="rpt-ring-fill" cx="18" cy="18" r="15.9155" style="stroke-dasharray:' + sc.power_score + ', 100; stroke:' + strokeColor + ';"/></svg>' +
                    '<div class="sc-power-val">' + sc.power_score + '%</div></div></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-success">' + (parseInt(ts.on_time) || 0) + '</span></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-info">' + (parseInt(ts.early) || 0) + '</span></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-warn">' + (parseInt(ts.late) || 0) + '</span></td>' +
                '<td class="rpt-td-center"><span class="rpt-num rpt-num-danger">' + (parseInt(ts.still_overdue) || 0) + '</span></td>' +
                '<td class="rpt-td-center"><span class="sc-att-badge">' + (parseInt(att.present) || 0) + 'P / ' + (parseInt(att.late) || 0) + 'L / ' + (parseInt(att.absent) || 0) + 'A</span></td>' +
                '<td class="rpt-td-center"><button class="todo-btn todo-btn-sm todo-btn-ghost sc-detail-btn" data-staff-id="' + sc.staff_id + '"><i class="fas fa-eye"></i></button></td>' +
            '</tr>';
        });

        html += '</tbody></table>';
        $wrap.html(html);
    }

    // ── Mark Attendance ──
    function markAttendance(staffId, date, status, $btn) {
        var $row = $btn.closest('.sc-att-row');
        $row.find('.sc-att-btn').removeClass('active-present active-late active-absent');

        var activeClass = status == 1 ? 'active-present' : (status == 2 ? 'active-late' : 'active-absent');
        $btn.addClass(activeClass);

        ajaxPost('ajax_mark_attendance', { staff_id: staffId, date: date, status: status }, function(res) {
            if (res.success) {
                showToast('Attendance marked!');
            } else {
                showToast('Failed to mark attendance.', 'error');
                $btn.removeClass(activeClass);
            }
        });
    }

    // ── Manual Score Adjustment ──
    function addManualScore() {
        var staffId = $('#sc-manual-staff').val();
        var points = $('#sc-manual-points').val();
        var reason = $('#sc-manual-reason').val().trim();

        if (!points || !reason) {
            showToast('Points and reason are required.', 'error');
            return;
        }

        ajaxPost('ajax_add_manual_score', { staff_id: staffId, points: points, reason: reason }, function(res) {
            if (res.success) {
                showToast('Score adjustment applied!');
                $('#sc-manual-points').val('');
                $('#sc-manual-reason').val('');
                loadScorecards();
            } else {
                showToast(res.message || 'Failed to apply adjustment.', 'error');
            }
        });
    }

    // ── Score Detail Modal ──
    function showScoreDetail(staffId) {
        var month = $('#sc-month').val();
        var year = $('#sc-year').val();
        var daysInMonth = new Date(year, month, 0).getDate();
        var periodFrom = year + '-' + ('0' + month).slice(-2) + '-01';
        var periodTo = year + '-' + ('0' + month).slice(-2) + '-' + ('0' + daysInMonth).slice(-2);

        $('#sc-modal-body').html('<div class="todo-loading"><div class="spinner"></div>Loading...</div>');
        $('#sc-modal-overlay').fadeIn(200);

        ajaxPost('ajax_get_staff_score_detail', { staff_id: staffId, period_from: periodFrom, period_to: periodTo }, function(res) {
            if (res.success) {
                renderScoreDetailModal(res.scorecard, res.score_log);
            }
        });
    }

    function renderScoreDetailModal(sc, logEntries) {
        var ts = sc.task_stats || {};
        var att = sc.attendance || {};
        var strokeColor = sc.traffic_light === 'green' ? '#16a34a' : (sc.traffic_light === 'yellow' ? '#eab308' : '#dc2626');
        var tlLabel = sc.traffic_light.charAt(0).toUpperCase() + sc.traffic_light.slice(1);

        var html = '<div style="text-align:center;margin-bottom:18px;">' +
            '<h3 style="margin:0 0 4px;font-size:18px;font-weight:700;">' + escHtml(sc.staff_name) + '</h3>' +
            '<span class="sc-tl-badge tl-' + sc.traffic_light + '">' + tlLabel + ' Zone</span>' +
        '</div>';

        // Stats grid
        html += '<div class="sc-detail-stats">' +
            '<div class="sc-detail-stat"><div class="sc-detail-stat-val">' + sc.total_score + '</div><div class="sc-detail-stat-label">Total Score</div></div>' +
            '<div class="sc-detail-stat"><div class="sc-detail-stat-val">' + sc.power_score + '%</div><div class="sc-detail-stat-label">Power of One</div></div>' +
            '<div class="sc-detail-stat"><div class="sc-detail-stat-val">' + (parseInt(ts.completed) || 0) + '/' + (parseInt(ts.total_tasks) || 0) + '</div><div class="sc-detail-stat-label">Tasks Done</div></div>' +
            '<div class="sc-detail-stat"><div class="sc-detail-stat-val">' + (parseInt(att.present) || 0) + '/' + sc.working_days + '</div><div class="sc-detail-stat-label">Attendance</div></div>' +
        '</div>';

        // Breakdown
        if (sc.breakdown && sc.breakdown.length > 0) {
            html += '<h4 style="font-size:13px;font-weight:700;margin:16px 0 8px;color:var(--todo-text-muted);text-transform:uppercase;letter-spacing:.5px;">Score Breakdown</h4>';
            html += '<table class="sc-log-table"><thead><tr><th>Type</th><th>Count</th><th>Points</th></tr></thead><tbody>';
            var typeLabels = {
                'task_completed': 'Task Completed', 'on_time': 'On Time', 'early_1day': 'Early (1+ day)',
                'early_2day': 'Early (2+ days)', 'late_daily': 'Late (daily)', 'late_3plus': 'Late (3+ days)',
                'overdue_running': 'Overdue Running', 'high_priority': 'High Priority Bonus',
                'checklist_complete': 'Checklist 100%', 'attendance': 'Attendance', 'manual_adjustment': 'Manual Adjustment'
            };
            sc.breakdown.forEach(function(b) {
                var pts = parseFloat(b.type_total);
                var cls = pts >= 0 ? 'pos' : 'neg';
                var prefix = pts > 0 ? '+' : '';
                html += '<tr>' +
                    '<td><span class="sc-log-type">' + (typeLabels[b.score_type] || b.score_type) + '</span></td>' +
                    '<td>' + b.type_count + '</td>' +
                    '<td><span class="sc-log-pts ' + cls + '">' + prefix + pts + '</span></td>' +
                '</tr>';
            });
            html += '</tbody></table>';
        }

        // Recent log entries
        if (logEntries && logEntries.length > 0) {
            html += '<h4 style="font-size:13px;font-weight:700;margin:20px 0 8px;color:var(--todo-text-muted);text-transform:uppercase;letter-spacing:.5px;">Recent Score Log</h4>';
            html += '<table class="sc-log-table"><thead><tr><th>Date</th><th>Type</th><th>Task</th><th>Points</th><th>Reason</th></tr></thead><tbody>';
            logEntries.slice(0, 50).forEach(function(entry) {
                var pts = parseFloat(entry.points);
                var cls = pts >= 0 ? 'pos' : 'neg';
                var prefix = pts > 0 ? '+' : '';
                html += '<tr>' +
                    '<td>' + entry.score_date + '</td>' +
                    '<td><span class="sc-log-type">' + (entry.score_type || '—').replace(/_/g, ' ') + '</span></td>' +
                    '<td>' + escHtml(entry.task_title || '—') + '</td>' +
                    '<td><span class="sc-log-pts ' + cls + '">' + prefix + pts + '</span></td>' +
                    '<td>' + escHtml(entry.reason || '') + '</td>' +
                '</tr>';
            });
            html += '</tbody></table>';
        }

        $('#sc-modal-body').html(html);
    }

    // ── Print Scorecard ──
    function printScorecard() {
        var month = $('#sc-month option:selected').text();
        var year = $('#sc-year').val();
        var $area = $('#sc-print-area');

        // Get current table data
        var scorecards = scorecardCache ? scorecardCache.scorecards : [];
        var tl = scorecardCache ? scorecardCache.tl_summary : { green: 0, yellow: 0, red: 0 };

        var html = '<div class="sc-print-header">' +
            '<h1>Staff Performance Scorecard</h1>' +
            '<p>' + month + ' ' + year + ' — Power of One Report</p>' +
        '</div>';

        // Traffic light summary
        html += '<div class="sc-print-tl-summary">' +
            '<div class="sc-print-tl-item"><div class="sc-print-tl-dot" style="background:#16a34a;"></div><div><strong>' + (tl.green || 0) + '</strong> Green</div></div>' +
            '<div class="sc-print-tl-item"><div class="sc-print-tl-dot" style="background:#eab308;"></div><div><strong>' + (tl.yellow || 0) + '</strong> Yellow</div></div>' +
            '<div class="sc-print-tl-item"><div class="sc-print-tl-dot" style="background:#dc2626;"></div><div><strong>' + (tl.red || 0) + '</strong> Red</div></div>' +
        '</div>';

        // Table
        html += '<table class="sc-print-table"><thead><tr>' +
            '<th>#</th><th>Staff Member</th><th>Zone</th><th>Score</th><th>Power %</th>' +
            '<th>On Time</th><th>Early</th><th>Late</th><th>Overdue</th><th>Attendance</th>' +
        '</tr></thead><tbody>';

        if (scorecards.length > 0) {
            scorecards.forEach(function(sc, i) {
                var ts = sc.task_stats || {};
                var att = sc.attendance || {};
                var dotColor = sc.traffic_light === 'green' ? '#16a34a' : (sc.traffic_light === 'yellow' ? '#eab308' : '#dc2626');
                html += '<tr>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td>' + escHtml(sc.staff_name) + '</td>' +
                    '<td><span style="color:' + dotColor + ';font-weight:700;">●</span> ' + sc.traffic_light.charAt(0).toUpperCase() + sc.traffic_light.slice(1) + '</td>' +
                    '<td>' + sc.total_score + ' / ' + sc.max_possible + '</td>' +
                    '<td><strong>' + sc.power_score + '%</strong></td>' +
                    '<td>' + (parseInt(ts.on_time) || 0) + '</td>' +
                    '<td>' + (parseInt(ts.early) || 0) + '</td>' +
                    '<td>' + (parseInt(ts.late) || 0) + '</td>' +
                    '<td>' + (parseInt(ts.still_overdue) || 0) + '</td>' +
                    '<td>' + (parseInt(att.present) || 0) + 'P/' + (parseInt(att.late) || 0) + 'L/' + (parseInt(att.absent) || 0) + 'A</td>' +
                '</tr>';
            });
        } else {
            html += '<tr><td colspan="10" style="text-align:center;">No data available</td></tr>';
        }

        html += '</tbody></table>';

        // Legend
        html += '<div class="sc-print-legend">' +
            '<div class="sc-print-legend-title">Scoring Rules</div>' +
            '<div>+10 Task completed | +5 Early (1+ day) | +3 Early (2+ days) | +5 High priority on-time | +3 Checklist 100% | +5 Attendance (present) | +3 Attendance (late) | -1/day Late (1-3 days) | -5 Late (3+ days) | -2/day Overdue running (cap -20)</div>' +
            '<div style="margin-top:4px;">Green ≥80% | Yellow 40-79% | Red &lt;40%</div>' +
        '</div>';

        // Footer
        html += '<div class="sc-print-footer">Generated on ' + new Date().toLocaleString() + '</div>';

        $area.html(html);

        setTimeout(function() { window.print(); }, 200);
    }

    // ── Export Scorecard CSV ──
    function exportScorecardCSV() {
        var $table = $('.sc-table');
        if ($table.length === 0) {
            showToast('No scorecard data to export.', 'error');
            return;
        }

        var csv = [];
        $table.find('tr').each(function() {
            var row = [];
            $(this).find('th, td').each(function() {
                var text = $(this).text().replace(/"/g, '""').replace(/\s+/g, ' ').trim();
                row.push('"' + text + '"');
            });
            csv.push(row.join(','));
        });

        var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'scorecard_' + todayDate() + '.csv';
        link.click();
        showToast('Scorecard exported!');
    }

    // ── Toast Notification ──
    function showToast(msg, type) {
        var bgColor = type === 'error' ? '#dc2626' : '#16a34a';
        var $toast = $('<div class="rpt-toast" style="background:' + bgColor + ';">' + msg + '</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.addClass('show'); }, 50);
        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() { $toast.remove(); }, 300);
        }, 3000);
    }

    // ── Utilities ──
    function escHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
    function todayDate() {
        var d = new Date();
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }

    return { init: init };
})();

$(function() {
    TodoReports.init();
});

/* Toast CSS injected via JS */
(function() {
    var style = document.createElement('style');
    style.textContent = '.rpt-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);padding:12px 24px;border-radius:12px;color:#fff;font-size:14px;font-weight:600;font-family:Inter,sans-serif;z-index:99999;opacity:0;transition:all .3s ease;box-shadow:0 8px 30px rgba(0,0,0,.2);pointer-events:none;}.rpt-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}';
    document.head.appendChild(style);
})();
