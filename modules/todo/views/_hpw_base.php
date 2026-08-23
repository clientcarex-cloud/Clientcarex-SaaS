<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Shared chrome for the ClientcareX dashboard widgets (Todo, Pro Tickets,
 * Mailbox, Caller). Every module ships an identical copy so any widget can
 * render first; the HPW_DASHBOARD_BASE constant guarantees the styles and
 * the Chart.js loader are emitted only once per page.
 */
?>
<?php if (!defined('HPW_DASHBOARD_BASE')) { define('HPW_DASHBOARD_BASE', 1); ?>
<!-- ── Shared base for ClientcareX dashboard widgets (emitted once per page) ── -->
<style>
.hpw-col{font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;}
.hpw-card{background:#fff;border:1px solid #e9e9f2;border-radius:16px;box-shadow:0 1px 2px rgba(15,15,35,.04),0 10px 28px -20px rgba(15,15,35,.28);margin-bottom:22px;overflow:hidden;}
.hpw-head{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid #f0f0f6;flex-wrap:wrap;}
.hpw-ico{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:14px;flex:none;}
.hpw-title{font-size:15px;font-weight:700;color:#1c1b26;display:flex;align-items:center;gap:10px;margin:0;}
.hpw-sub{font-size:11px;font-weight:600;color:#8a8894;text-transform:uppercase;letter-spacing:.06em;}
.hpw-head-badges{display:flex;gap:6px;flex-wrap:wrap;}
.hpw-badge{font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;}
.hpw-badge i{font-size:10px;}
.hpw-badge--crit{background:#fdeaea;color:#b02a2a;}
.hpw-badge--warn{background:#fdf3dc;color:#8a6100;}
.hpw-badge--ok{background:#e7f6e7;color:#0a7a0a;}
.hpw-open{margin-left:auto;font-size:12px;font-weight:600;color:#4F46E5;text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e3e1fb;border-radius:8px;background:#f7f7fe;transition:all .15s;}
.hpw-open:hover,.hpw-open:focus{background:#4F46E5;color:#fff;border-color:#4F46E5;text-decoration:none;}
.hpw-body{display:flex;flex-wrap:wrap;}
.hpw-kpis{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#f0f0f6;flex:0 0 252px;min-width:232px;border-right:1px solid #f0f0f6;align-content:stretch;}
.hpw-kpi{background:#fff;padding:11px 14px;display:block;text-decoration:none;}
a.hpw-kpi:hover,a.hpw-kpi:focus{background:#fafaff;text-decoration:none;}
.hpw-kpi-val{font-size:21px;font-weight:700;color:#1c1b26;line-height:1.15;}
.hpw-kpi-lbl{font-size:10.5px;font-weight:600;color:#8a8894;text-transform:uppercase;letter-spacing:.05em;margin-top:2px;}
.hpw-kpi--crit .hpw-kpi-val{color:#d03b3b;}
.hpw-kpi--warn .hpw-kpi-val{color:#a06e00;}
.hpw-kpi--good .hpw-kpi-val{color:#0a7a0a;}
.hpw-charts{flex:1 1 340px;min-width:300px;display:flex;gap:20px;padding:14px 18px;border-right:1px solid #f0f0f6;}
.hpw-chart{flex:1;min-width:0;display:flex;flex-direction:column;}
.hpw-sec-title{font-size:11px;font-weight:700;color:#6b6a76;text-transform:uppercase;letter-spacing:.07em;margin:0 0 8px;}
.hpw-canvas{position:relative;flex:1;min-height:150px;}
.hpw-list{flex:0 0 330px;min-width:280px;padding:14px 16px;display:flex;flex-direction:column;}
.hpw-rows{display:flex;flex-direction:column;gap:2px;overflow-y:auto;max-height:236px;}
.hpw-row{display:flex;align-items:center;gap:10px;padding:7px 8px;border-radius:9px;text-decoration:none;min-height:38px;}
a.hpw-row:hover,a.hpw-row:focus{background:#f6f6fb;text-decoration:none;}
.hpw-row-main{flex:1;min-width:0;}
.hpw-row-title,.hpw-row-meta{display:block;}
.hpw-row-title{font-size:12.5px;font-weight:600;color:#2a2935;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.hpw-row-meta{font-size:11px;color:#8a8894;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.hpw-chip{font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px;flex:none;text-transform:uppercase;letter-spacing:.03em;}
.hpw-chip--crit{background:#fdeaea;color:#b02a2a;}
.hpw-chip--warn{background:#fdf3dc;color:#8a6100;}
.hpw-chip--info{background:#e8f0fc;color:#1c5cab;}
.hpw-chip--muted{background:#f0f0f6;color:#6b6a76;}
.hpw-dot{width:8px;height:8px;border-radius:50%;flex:none;}
.hpw-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#8a8894;font-size:12.5px;padding:24px 0;text-align:center;}
.hpw-empty i{font-size:26px;color:#0ca30c;opacity:.85;}
@media (max-width:1350px){.hpw-kpis{flex-basis:100%;grid-template-columns:repeat(3,1fr);border-right:0;border-bottom:1px solid #f0f0f6;}}
@media (max-width:900px){.hpw-charts{flex-wrap:wrap;border-right:0;}.hpw-list{flex-basis:100%;}}
</style>
<script>
if (!window.hpwChart) {
    window.hpwChart = {
        C: { blue: '#2a78d6', aqua: '#1baf7a', yellow: '#eda100', violet: '#4a3aa7', red: '#e34948',
             crit: '#d03b3b', warn: '#fab219', good: '#0ca30c', grid: '#ecebf3', ink: '#8a8894', surface: '#ffffff' },
        _q: [], _loading: false,
        ready: function (cb) {
            if (window.Chart && parseInt((window.Chart.version || '0'), 10) >= 3) { cb(window.Chart); return; }
            this._q.push(cb);
            if (this._loading) { return; }
            this._loading = true;
            var s = document.createElement('script'), self = this;
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
            s.onload = function () { self._q.forEach(function (f) { try { f(window.Chart); } catch (e) {} }); self._q = []; };
            document.head.appendChild(s);
        },
        base: function () {
            return { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false },
                    tooltip: { backgroundColor: '#1c1b26', padding: 10, cornerRadius: 8,
                               titleFont: { size: 11 }, bodyFont: { size: 11 }, displayColors: false } } };
        },
        legend: function () {
            return { display: true, position: 'bottom',
                labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle',
                          color: '#6b6a76', font: { size: 11 }, padding: 12 } };
        },
        axes: function () {
            return { x: { grid: { display: false }, border: { display: false }, ticks: { color: '#8a8894', font: { size: 10 } } },
                     y: { beginAtZero: true, grid: { color: '#ecebf3' }, border: { display: false },
                          ticks: { color: '#8a8894', font: { size: 10 }, precision: 0, maxTicksLimit: 5 } } };
        }
    };
}
</script>
<?php } ?>
