(function() {
    'use strict';

    var POLL_INTERVAL     = 5000;  // 5 seconds
    var CAPACITY_INTERVAL = 15000; // capacity moves slowly and costs more to compute
    var MAX_POINTS        = 60;    // 5 min of history at 5s intervals
    var statsUrl          = admin_url + 'ccx_db/server_stats_json';
    var capacityUrl       = admin_url + 'ccx_db/capacity_json';

    // ─── History Arrays ───
    var cpuHistory     = [];
    var memHistory     = [];
    var threadsHistory = [];
    var timeLabels     = [];

    // ─── Chart Instances ───
    var cpuChart, memChart, mysqlChart, diskChart;

    // ─── Colour helpers ───
    function gaugeColor(pct) {
        if (pct < 50)  return 'green';
        if (pct < 75)  return 'yellow';
        if (pct < 90)  return 'orange';
        return 'red';
    }

    function hexColor(pct) {
        if (pct < 50)  return '#2ecc71';
        if (pct < 75)  return '#f1c40f';
        if (pct < 90)  return '#e67e22';
        return '#e74c3c';
    }

    // ─── Init Charts ───
    function initCharts() {
        var commonOpts = {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400 },
            scales: {
                x: {
                    display: true,
                    ticks: { maxTicksLimit: 10, font: { size: 10 }, color: '#95a5a6' },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 }, color: '#95a5a6' },
                    grid: { color: 'rgba(0,0,0,0.04)' }
                }
            },
            plugins: {
                legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
            }
        };

        // CPU Chart
        cpuChart = new Chart(document.getElementById('chart_cpu').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'CPU %',
                    data: [],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52,152,219,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 1
                }]
            },
            options: Object.assign({}, commonOpts, {
                scales: Object.assign({}, commonOpts.scales, {
                    y: Object.assign({}, commonOpts.scales.y, { max: 100, ticks: { callback: function(v) { return v + '%'; }, font: { size: 10 }, color: '#95a5a6' } })
                })
            })
        });

        // Memory Chart
        memChart = new Chart(document.getElementById('chart_memory').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Memory %',
                    data: [],
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231,76,60,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 1
                }]
            },
            options: Object.assign({}, commonOpts, {
                scales: Object.assign({}, commonOpts.scales, {
                    y: Object.assign({}, commonOpts.scales.y, { max: 100, ticks: { callback: function(v) { return v + '%'; }, font: { size: 10 }, color: '#95a5a6' } })
                })
            })
        });

        // MySQL Threads Chart
        mysqlChart = new Chart(document.getElementById('chart_mysql').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Connected',
                        data: [],
                        borderColor: '#9b59b6',
                        backgroundColor: 'rgba(155,89,182,0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 1
                    },
                    {
                        label: 'Running',
                        data: [],
                        borderColor: '#e67e22',
                        backgroundColor: 'rgba(230,126,34,0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.3,
                        pointRadius: 1
                    }
                ]
            },
            options: commonOpts
        });

        // Disk Chart (Doughnut)
        diskChart = new Chart(document.getElementById('chart_disk').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Free'],
                datasets: [{
                    data: [0, 100],
                    backgroundColor: ['#f39c12', '#ecf0f1'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 } } }
                }
            }
        });
    }

    // ─── Update UI ───
    function updateUI(data) {
        // Server time
        document.getElementById('server_time').textContent = data.server_time || '--';

        // CPU
        var cpuPct = data.cpu.usage_pct || 0;
        document.getElementById('cpu_pct').textContent = cpuPct + '%';
        document.getElementById('cpu_load').textContent = data.cpu.load_1 + ' / ' + data.cpu.load_5 + ' / ' + data.cpu.load_15;
        document.getElementById('cpu_cores').textContent = data.cpu.cores;
        var cpuBar = document.getElementById('cpu_bar');
        cpuBar.style.width = cpuPct + '%';
        cpuBar.className = 'fill ' + gaugeColor(cpuPct);

        // Memory
        var memPct = data.memory.usage_pct || 0;
        document.getElementById('mem_pct').textContent = memPct + '%';
        document.getElementById('mem_used').textContent = data.memory.used_mb;
        document.getElementById('mem_total').textContent = data.memory.total_mb;
        var memBar = document.getElementById('mem_bar');
        memBar.style.width = memPct + '%';
        memBar.className = 'fill ' + gaugeColor(memPct);

        // Disk
        var diskPct = data.disk.usage_pct || 0;
        document.getElementById('disk_pct').textContent = diskPct + '%';
        document.getElementById('disk_used').textContent = data.disk.used_gb;
        document.getElementById('disk_total').textContent = data.disk.total_gb;
        var diskBar = document.getElementById('disk_bar');
        diskBar.style.width = diskPct + '%';
        diskBar.className = 'fill ' + gaugeColor(diskPct);

        // Server Info card
        document.getElementById('server_qps').innerHTML = data.mysql.queries_per_sec + '<small style="font-size:14px; font-weight:400;"> q/s</small>';
        document.getElementById('server_locks').textContent = data.mysql.table_locks_waited;

        // PHP
        document.getElementById('php_mem').textContent = data.php.memory_usage_mb;
        document.getElementById('php_peak').textContent = data.php.memory_peak_usage_mb;

        // MySQL stats
        document.getElementById('mysql_threads').textContent = data.mysql.threads_connected;
        document.getElementById('mysql_running').textContent = data.mysql.threads_running;
        document.getElementById('mysql_qps').textContent = data.mysql.queries_per_sec;
        document.getElementById('mysql_slow').textContent = data.mysql.slow_queries;
        document.getElementById('mysql_open_tables').textContent = data.mysql.open_tables;
        document.getElementById('mysql_uptime').textContent = data.mysql.uptime_human;

        // Slow queries warning color
        var slowEl = document.getElementById('mysql_slow');
        slowEl.style.color = data.mysql.slow_queries > 0 ? '#e74c3c' : '#2c3e50';

        // ─── Update Charts ───
        var now = new Date();
        var label = now.getHours().toString().padStart(2, '0') + ':' +
                    now.getMinutes().toString().padStart(2, '0') + ':' +
                    now.getSeconds().toString().padStart(2, '0');

        timeLabels.push(label);
        cpuHistory.push(cpuPct);
        memHistory.push(memPct);
        threadsHistory.push(data.mysql.threads_connected);

        if (timeLabels.length > MAX_POINTS) {
            timeLabels.shift();
            cpuHistory.shift();
            memHistory.shift();
            threadsHistory.shift();
        }

        // CPU chart
        cpuChart.data.labels = timeLabels.slice();
        cpuChart.data.datasets[0].data = cpuHistory.slice();
        cpuChart.data.datasets[0].borderColor = hexColor(cpuPct);
        cpuChart.data.datasets[0].backgroundColor = hexColor(cpuPct).replace(')', ',0.1)').replace('rgb', 'rgba');
        cpuChart.update('none');

        // Memory chart
        memChart.data.labels = timeLabels.slice();
        memChart.data.datasets[0].data = memHistory.slice();
        memChart.update('none');

        // MySQL chart
        mysqlChart.data.labels = timeLabels.slice();
        mysqlChart.data.datasets[0].data = threadsHistory.slice();
        // Running threads — need separate history
        if (!window._mysqlRunningHistory) window._mysqlRunningHistory = [];
        window._mysqlRunningHistory.push(data.mysql.threads_running);
        if (window._mysqlRunningHistory.length > MAX_POINTS) window._mysqlRunningHistory.shift();
        mysqlChart.data.datasets[1].data = window._mysqlRunningHistory.slice();
        mysqlChart.update('none');

        // Disk doughnut
        diskChart.data.datasets[0].data = [data.disk.used_gb, data.disk.free_gb];
        diskChart.data.datasets[0].backgroundColor = [hexColor(diskPct), '#ecf0f1'];
        diskChart.update('none');

        // ─── Process table ───
        var tbody = document.getElementById('process_tbody');
        if (data.processes && data.processes.length > 0) {
            var html = '';
            for (var i = 0; i < data.processes.length; i++) {
                var p = data.processes[i];
                var rowClass = '';
                if (p.command === 'Query' && parseInt(p.time) > 5) rowClass = ' style="background:#fff3cd;"';
                html += '<tr' + rowClass + '>';
                html += '<td>' + escHtml(p.id) + '</td>';
                html += '<td>' + escHtml(p.user) + '</td>';
                html += '<td>' + escHtml(p.host) + '</td>';
                html += '<td>' + escHtml(p.db) + '</td>';
                html += '<td><span class="label label-' + commandLabel(p.command) + '">' + escHtml(p.command) + '</span></td>';
                html += '<td>' + escHtml(p.time) + 's</td>';
                html += '<td>' + escHtml(p.state) + '</td>';
                html += '<td><small>' + escHtml(p.info) + '</small></td>';
                html += '</tr>';
            }
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No active processes</td></tr>';
        }

        // ─── Tenant Processes — Unified Table ───
        var tenants = data.tenant_processes || [];
        var allRows = [];
        var errorCount = 0;
        var errorHtml = '';

        for (var t = 0; t < tenants.length; t++) {
            var tenant = tenants[t];
            var tName = tenant.company || tenant.slug;
            if (tenant.error) {
                errorCount++;
                errorHtml += '<div class="te-item"><i class="fa fa-exclamation-triangle"></i> <b>' + escHtml(tName) + '</b>: ' + escHtml(tenant.error) + '</div>';
                continue;
            }
            if (tenant.processes) {
                for (var p = 0; p < tenant.processes.length; p++) {
                    var proc = tenant.processes[p];
                    proc._tenant = tName;
                    proc._processCount = tenant.processes.length;
                    allRows.push(proc);
                }
            }
        }

        // Sort: active queries first, then by time descending
        allRows.sort(function(a, b) {
            var aIsQuery = a.command === 'Query' ? 0 : 1;
            var bIsQuery = b.command === 'Query' ? 0 : 1;
            if (aIsQuery !== bIsQuery) return aIsQuery - bIsQuery;
            return (parseInt(b.time) || 0) - (parseInt(a.time) || 0);
        });

        // Compute stats
        var totalProcs = allRows.length;
        var activeQueries = 0, sleepCount = 0, slowCount = 0;
        for (var s = 0; s < allRows.length; s++) {
            if (allRows[s].command === 'Query') activeQueries++;
            if (allRows[s].command === 'Sleep') sleepCount++;
            if (allRows[s].command === 'Query' && parseInt(allRows[s].time) > 5) slowCount++;
        }

        // Update summary boxes
        document.getElementById('ts_tenants').textContent = tenants.length;
        document.getElementById('ts_total').textContent = totalProcs;
        document.getElementById('ts_active').textContent = activeQueries;
        document.getElementById('ts_sleeping').textContent = sleepCount;
        document.getElementById('ts_slow').textContent = slowCount;
        document.getElementById('ts_errors').textContent = errorCount;

        // Store data globally for filter/search to use
        window._tenantAllRows = allRows;
        window._tenantErrorHtml = errorHtml;

        // Render with current filters
        renderTenantTable();

        // Error list
        document.getElementById('tenant_error_list').innerHTML = errorHtml;
    }

    // ─── Render tenant table (called by updateUI and by filter changes) ───
    function renderTenantTable() {
        var allRows = window._tenantAllRows || [];
        var tbody = document.getElementById('tenant_unified_tbody');
        if (!tbody) return;

        var searchVal = (document.getElementById('tenant_search').value || '').toLowerCase();
        var hideSleep = document.getElementById('tenant_hide_sleep').checked;
        var onlySlow  = document.getElementById('tenant_only_slow').checked;

        var filtered = [];
        for (var i = 0; i < allRows.length; i++) {
            var r = allRows[i];
            // Filter: hide sleep
            if (hideSleep && r.command === 'Sleep') continue;
            // Filter: only slow
            if (onlySlow && !(r.command === 'Query' && parseInt(r.time) > 5)) continue;
            // Search
            if (searchVal) {
                var haystack = ((r._tenant || '') + ' ' + (r.user || '') + ' ' + (r.db || '') + ' ' + (r.info || '') + ' ' + (r.command || '') + ' ' + (r.state || '')).toLowerCase();
                if (haystack.indexOf(searchVal) === -1) continue;
            }
            filtered.push(r);
        }

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted" style="padding:15px;">No matching processes</td></tr>';
            return;
        }

        var html = '';
        for (var j = 0; j < filtered.length; j++) {
            var row = filtered[j];
            var rowStyle = '';
            if (row.command === 'Query' && parseInt(row.time) > 5) rowStyle = ' style="background:#fff3cd;"';
            html += '<tr' + rowStyle + '>';
            html += '<td><span class="tenant-label">' + escHtml(row._tenant) + '</span></td>';
            html += '<td style="text-align:center; font-weight:600;">' + row._processCount + '</td>';
            html += '<td>' + escHtml(row.id) + '</td>';
            html += '<td>' + escHtml(row.user) + '</td>';
            html += '<td>' + escHtml(row.host) + '</td>';
            html += '<td>' + escHtml(row.db) + '</td>';
            html += '<td><span class="label label-' + commandLabel(row.command) + '">' + escHtml(row.command) + '</span></td>';
            html += '<td>' + escHtml(row.time) + 's</td>';
            html += '<td>' + escHtml(row.state) + '</td>';
            html += '<td><small>' + escHtml(row.info) + '</small></td>';
            html += '</tr>';
        }
        tbody.innerHTML = html;
    }

    function commandLabel(cmd) {
        switch (cmd) {
            case 'Query':   return 'primary';
            case 'Sleep':   return 'default';
            case 'Connect': return 'success';
            case 'Execute': return 'warning';
            default:        return 'default';
        }
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        str = String(str);
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ─── Fetch Stats ───
    function fetchStats() {
        $.ajax({
            url: statsUrl,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && data.error) {
                    console.error('[Server Performance] Server returned error:', data.error);
                    return;
                }
                if (data && data.cpu) {
                    updateUI(data);
                } else {
                    console.warn('[Server Performance] Unexpected response format:', data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('[Server Performance] AJAX error:', textStatus, errorThrown, 'Response:', jqXHR.responseText ? jqXHR.responseText.substring(0, 500) : 'empty');
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════════
     *  CLIENT ON-BOARDING CAPACITY
     *  Renders the live capacity model. Every figure comes from the server;
     *  nothing here decides policy, it only presents what was measured.
     * ══════════════════════════════════════════════════════════════════ */

    function renderCapacity(d) {
        var v = d.verdict, b = d.basis, p = d.per_tenant;

        // ─── Headline ───
        var hero = document.getElementById('cap_hero');
        hero.className = 'cap-hero st-' + (v.status || 'healthy');
        document.getElementById('cap_can_add').textContent  = v.can_add_now;
        document.getElementById('cap_onboarded').textContent = v.onboarded;
        document.getElementById('cap_safe').textContent      = v.safe;
        document.getElementById('cap_hard').textContent      = v.hard_max;
        document.getElementById('cap_tuned').textContent     = v.tuned_safe;

        var binding = null;
        for (var i = 0; i < d.constraints.length; i++) {
            if (d.constraints[i].binding) { binding = d.constraints[i]; break; }
        }
        var sub = 'Running <strong>' + v.onboarded + '</strong> active tenants at <strong>' +
                  v.used_pct + '%</strong> of this server\'s capacity. ';
        if (binding) {
            sub += 'The wall is <strong>' + escHtml(binding.label) + '</strong> at ' +
                   binding.tenants + ' tenants. ';
        }
        if (v.tuned_max > v.hard_max) {
            sub += 'Clearing the items below lifts that to about <strong>' + v.tuned_max +
                   '</strong> (' + v.tuned_multiple + '×) on this same hardware.';
        }
        document.getElementById('cap_hero_sub').innerHTML = sub;

        // ─── Confidence warning ───
        var warn = document.getElementById('cap_warnbar');
        if (b.confidence === 'low') {
            warn.style.display = '';
            warn.innerHTML = '<strong>Low-traffic sample.</strong> Peak load seen so far is only ' +
                b.load_ref + ' across ' + b.cores + ' cores, so the CPU ceiling is an extrapolation ' +
                'rather than a measurement. This page records every new peak it observes' +
                (b.peak_load_at ? ' (best so far: ' + escHtml(b.peak_load_at) + ')' : '') +
                ' — leave it open through a busy period and the estimate tightens by itself.';
        } else {
            warn.style.display = 'none';
        }

        // ─── Fill meter ───
        var pct  = v.hard_max > 0 ? Math.min(100, (v.onboarded / v.hard_max) * 100) : 100;
        var fill = document.getElementById('cap_meter_fill');
        fill.style.width = pct.toFixed(1) + '%';
        fill.className   = 'cap-meter-fill st-' + (v.status || 'healthy');
        document.getElementById('cap_meter_safe').style.left =
            (v.hard_max > 0 ? Math.min(100, (v.safe / v.hard_max) * 100) : 70) + '%';
        document.getElementById('cap_meter_caption').textContent =
            v.onboarded + ' on-boarded  ·  ' + v.can_add_now + ' free before the safe limit of ' + v.safe;
        document.getElementById('cap_meter_max').textContent = v.hard_max + ' tenants (hard wall)';

        // ─── Cost of one tenant ───
        document.getElementById('cap_u_db').innerHTML     = p.db_mb + '<small> MB</small>';
        document.getElementById('cap_u_tables').textContent = p.tables;
        document.getElementById('cap_u_ram').innerHTML    = p.ram_mb + '<small> MB</small>';
        document.getElementById('cap_u_qps').innerHTML    = p.qps + '<small> q/s</small>';
        document.getElementById('cap_u_load').textContent = p.load;
        document.getElementById('cap_u_conns').textContent = p.conns;

        // ─── Ceiling per constraint ───
        var rows = '';
        for (var j = 0; j < d.constraints.length; j++) {
            var c = d.constraints[j];
            rows += '<tr class="' + (c.binding ? 'is-binding' : '') + '">';
            rows += '<td>' + escHtml(c.label) +
                    (c.binding ? '<span class="cap-bind-tag">binding</span>' : '') + '</td>';
            rows += '<td>' + escHtml(c.current) + '</td>';
            rows += '<td>' + escHtml(c.limit) + '</td>';
            rows += '<td style="text-align:center;"><span class="cap-ceiling">' +
                    (c.tenants === null ? '&mdash;' : c.tenants) + '</span></td>';
            rows += '<td style="text-align:center;"><span class="cap-conf ' + escHtml(c.confidence) +
                    '">' + escHtml(c.confidence) + '</span></td>';
            rows += '<td class="cap-note">' + escHtml(c.note) + '</td>';
            rows += '</tr>';
        }
        document.getElementById('cap_constraint_tbody').innerHTML = rows ||
            '<tr><td colspan="6" class="text-center text-muted">No constraints returned</td></tr>';

        // ─── Blockers ───
        var bl = '';
        for (var k = 0; k < d.blockers.length; k++) {
            var x = d.blockers[k];
            var gain = x.multiplier > 1
                ? '<span class="b-gain">+' + Math.round((x.multiplier - 1) * 100) + '% capacity</span>'
                : '';
            bl += '<div class="cap-blocker sev-' + escHtml(x.severity) + '">';
            bl += gain + '<div class="b-title">' + escHtml(x.title) + '</div>';
            if (x.detail) bl += '<div class="b-detail">' + escHtml(x.detail) + '</div>';
            if (x.fix)    bl += '<div class="b-fix">' + escHtml(x.fix) + '</div>';
            bl += '</div>';
        }
        document.getElementById('cap_blockers').innerHTML = bl ||
            '<div class="cap-blocker sev-info"><div class="b-title">Nothing is artificially capping capacity</div>' +
            '<div class="b-detail">Configuration and application load look clean — the ceiling above is a genuine hardware limit.</div></div>';

        // ─── Basis footnote ───
        var basis = 'Computed from: ' + b.cores + ' cores · load ' + b.load_now +
            ' now / ' + b.load_ref + ' peak · ' + b.qps_now + ' q/s now / ' + b.qps_ref + ' q/s peak · ' +
            b.php_workers + ' PHP workers at ' + b.php_avg_mb + ' MB · tenant footprint ' +
            escHtml(b.tenant_scan) + '. Peaks tracked since ' + escHtml(b.peak_since) + '. ' +
            'Safe limit is 70% of the hard wall. Updated ' + escHtml(b.server_time) + '.';
        if (b.tenant_errors && b.tenant_errors.length) {
            basis += '<br><span style="color:#e74c3c;">Could not measure ' + b.tenant_errors.length +
                     ' tenant database(s): ' + escHtml(b.tenant_errors.join('; ')) +
                     ' — the per-tenant averages exclude them.</span>';
        }
        document.getElementById('cap_basis').innerHTML = basis;
    }

    function fetchCapacity() {
        $.ajax({
            url: capacityUrl,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && data.error) {
                    console.error('[Capacity] Server returned error:', data.error);
                    document.getElementById('cap_hero_sub').textContent = 'Capacity model failed: ' + data.error;
                    return;
                }
                if (data && data.verdict) {
                    renderCapacity(data);
                } else {
                    console.warn('[Capacity] Unexpected response format:', data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('[Capacity] AJAX error:', textStatus, errorThrown,
                    'Response:', jqXHR.responseText ? jqXHR.responseText.substring(0, 500) : 'empty');
            }
        });
    }

    // ─── Init ───
    $(function() {
        initCharts();
        fetchStats();
        setInterval(fetchStats, POLL_INTERVAL);
        fetchCapacity();
        setInterval(fetchCapacity, CAPACITY_INTERVAL);

        // Tenant filter/search events — re-render without waiting for next poll
        $('#tenant_search').on('input', function() { renderTenantTable(); });
        $('#tenant_hide_sleep').on('change', function() { renderTenantTable(); });
        $('#tenant_only_slow').on('change', function() { renderTenantTable(); });
    });
})();
