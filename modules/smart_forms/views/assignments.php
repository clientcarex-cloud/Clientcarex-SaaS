<?php defined('BASEPATH') or exit('No direct script access allowed');

/* humanize seconds -> "2d 4h" / "3h 12m" / "45m" */
function sf_human_secs($secs)
{
    if ($secs === null) {
        return '—';
    }
    $secs = (int) $secs;
    if ($secs < 60) {
        return $secs . 's';
    }
    $d = floor($secs / 86400);
    $h = floor(($secs % 86400) / 3600);
    $m = floor(($secs % 3600) / 60);
    if ($d > 0) {
        return $d . 'd ' . $h . 'h';
    }
    if ($h > 0) {
        return $h . 'h ' . $m . 'm';
    }

    return $m . 'm';
}
?>
<?php init_head(); ?>
<style>
.sfa{--sf-primary:#6366f1;--sf-primary-dark:#4f46e5;--sf-success:#10b981;--sf-warning:#f59e0b;--sf-danger:#ef4444;--sf-text:#1e293b;--sf-muted:#64748b;--sf-border:#e2e8f0;--sf-shadow:0 1px 3px rgba(15,23,42,.08);--sf-shadow-lg:0 12px 32px rgba(15,23,42,.14)}
.sfa .sf-card{background:#fff;border:1px solid var(--sf-border);border-radius:14px;box-shadow:var(--sf-shadow);margin-bottom:16px}
.sfa .sf-card-head{padding:14px 20px;border-bottom:1px solid var(--sf-border);font-weight:700;color:var(--sf-text)}
.sfa .sf-card-body{padding:18px 20px}
.sf-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none!important;transition:all .15s}
.sf-btn-primary{background:linear-gradient(135deg,var(--sf-primary),var(--sf-primary-dark));color:#fff!important;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.sf-btn-outline{background:#fff;border-color:var(--sf-border);color:var(--sf-text)!important}
.sf-btn-outline:hover{border-color:var(--sf-primary);color:var(--sf-primary)!important}
.sf-btn-sm{padding:5px 11px;font-size:12px;border-radius:8px}
.sf-chips{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.sf-chip{background:#fff;border:1px solid var(--sf-border);border-radius:12px;padding:12px 18px;box-shadow:var(--sf-shadow)}
.sf-chip b{display:block;font-size:20px;color:var(--sf-text)}
.sf-chip span{font-size:12px;color:var(--sf-muted)}
.sf-chip.warn b{color:var(--sf-danger)}
.sfa label{font-size:12px;font-weight:600;color:var(--sf-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px;display:block}
.sfa .sf-input,.sfa .sf-select{width:100%;border:1px solid var(--sf-border);border-radius:9px;padding:9px 12px;font-size:13.5px;color:var(--sf-text);background:#fff;outline:none}
.sfa .sf-input:focus,.sfa .sf-select:focus{border-color:var(--sf-primary);box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.sf-assign-grid{display:grid;grid-template-columns:160px 1fr 200px 130px;gap:12px;align-items:end}
@media(max-width:991px){.sf-assign-grid{grid-template-columns:1fr}}
.sf-search-wrap{position:relative}
.sf-search-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--sf-border);border-radius:10px;box-shadow:var(--sf-shadow-lg);z-index:50;max-height:260px;overflow:auto;display:none}
.sf-sr{padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9}
.sf-sr:hover{background:#eef2ff}
.sf-sr b{display:block;font-size:13px;color:var(--sf-text)}
.sf-sr span{font-size:11.5px;color:var(--sf-muted)}
.sf-picked{display:none;align-items:center;gap:8px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:9px;padding:8px 12px;font-size:13px;font-weight:600;color:var(--sf-primary-dark)}
.sf-picked button{border:none;background:none;color:var(--sf-danger);cursor:pointer;margin-left:auto}
.sfa table.sf-table{width:100%;border-collapse:collapse}
.sfa .sf-table th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--sf-muted);text-align:left;padding:10px 12px;border-bottom:2px solid var(--sf-border)}
.sfa .sf-table td{padding:11px 12px;font-size:13px;color:var(--sf-text);border-bottom:1px solid var(--sf-border);vertical-align:middle}
.sfa .sf-table tr:hover td{background:#fafbff}
.sf-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.sf-badge-completed{background:#ecfdf5;color:var(--sf-success)}
.sf-badge-in_progress{background:#eff6ff;color:#2563eb}
.sf-badge-pending{background:#f1f5f9;color:var(--sf-muted)}
.sf-badge-overdue{background:#fef2f2;color:var(--sf-danger)}
.sf-ttag{display:inline-block;padding:2px 8px;border-radius:12px;font-size:10.5px;font-weight:700;background:#eef2ff;color:var(--sf-primary)}
.sf-ttag.patient{background:#ecfdf5;color:#047857}
.sf-prog{width:90px;height:7px;background:#f1f5f9;border-radius:5px;overflow:hidden;display:inline-block;vertical-align:middle}
.sf-prog i{display:block;height:100%;background:linear-gradient(90deg,var(--sf-primary),#8b5cf6);border-radius:5px}
</style>
<div id="wrapper" class="sfa">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
                    <h3 style="margin:0;font-weight:700;color:var(--sf-text)">
                        <a href="<?php echo admin_url('smart_forms'); ?>" style="color:var(--sf-muted);margin-right:8px"><i class="fa fa-arrow-left"></i></a>
                        <?php echo html_escape($form->title); ?> <small style="color:var(--sf-muted)">— Assignments &amp; Tracking</small>
                    </h3>
                    <div style="display:flex;gap:8px">
                        <a href="<?php echo admin_url('smart_forms/form/' . $form->id); ?>" class="sf-btn sf-btn-outline"><i class="fa fa-pencil"></i> Edit form</a>
                        <a href="<?php echo admin_url('smart_forms/submissions/' . $form->id); ?>" class="sf-btn sf-btn-outline"><i class="fa-solid fa-inbox"></i> Submissions</a>
                    </div>
                </div>

                <div class="sf-chips">
                    <div class="sf-chip"><b><?php echo $astats['total']; ?></b><span>Assigned</span></div>
                    <div class="sf-chip"><b><?php echo $astats['completed']; ?></b><span>Completed</span></div>
                    <div class="sf-chip"><b><?php echo $astats['in_progress']; ?></b><span>In progress</span></div>
                    <div class="sf-chip"><b><?php echo $astats['pending']; ?></b><span>Pending</span></div>
                    <div class="sf-chip <?php echo $astats['overdue'] ? 'warn' : ''; ?>"><b><?php echo $astats['overdue']; ?></b><span>Overdue</span></div>
                    <div class="sf-chip"><b><?php echo sf_human_secs($astats['avg_tat']); ?></b><span>Avg TAT</span></div>
                </div>

                <?php if (has_permission('smart_forms', '', 'create') || is_admin()) { ?>
                <div class="sf-card">
                    <div class="sf-card-head"><i class="fa-solid fa-user-plus" style="color:var(--sf-primary);margin-right:8px"></i>Assign this form</div>
                    <div class="sf-card-body">
                        <div class="sf-assign-grid">
                            <div>
                                <label>Assign to</label>
                                <select class="sf-select" id="sf-target-type">
                                    <option value="patient">Patient</option>
                                    <option value="contact">Customer</option>
                                </select>
                            </div>
                            <div class="sf-search-wrap">
                                <label>Search by name / phone / MR no / email</label>
                                <input type="text" class="sf-input" id="sf-target-search" placeholder="Type at least 2 characters..." autocomplete="off">
                                <div class="sf-search-results" id="sf-search-results"></div>
                                <div class="sf-picked" id="sf-picked">
                                    <i class="fa-solid fa-circle-user"></i><span id="sf-picked-name"></span>
                                    <button type="button" onclick="sfClearPick()"><i class="fa fa-xmark"></i></button>
                                </div>
                            </div>
                            <div>
                                <label>Due date (for TAT)</label>
                                <input type="datetime-local" class="sf-input" id="sf-due-date">
                            </div>
                            <div>
                                <button type="button" class="sf-btn sf-btn-primary" style="width:100%;justify-content:center" onclick="sfAssign()"><i class="fa fa-plus"></i> Assign</button>
                            </div>
                        </div>
                        <p style="font-size:12px;color:var(--sf-muted);margin:10px 0 0"><i class="fa-solid fa-circle-info" style="margin-right:5px"></i>Each assignment gets a personal link — the form opens pre-filled, supports save-as-draft, tracks due date &amp; TAT.</p>
                    </div>
                </div>
                <?php } ?>

                <div class="sf-card">
                    <div class="sf-card-head"><i class="fa-solid fa-list-check" style="color:var(--sf-primary);margin-right:8px"></i>Tracking</div>
                    <div class="sf-card-body" style="padding:0 20px 6px">
                        <?php if (empty($assignments)) { ?>
                            <p style="padding:26px 0;text-align:center;color:var(--sf-muted)">No assignments yet. Assign this form to a patient or customer above.</p>
                        <?php } else { ?>
                            <table class="sf-table">
                                <thead>
                                    <tr>
                                        <th>Assignee</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th>Due</th>
                                        <th>TAT</th>
                                        <th style="text-align:right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $a) {
                                        $link = site_url('form/' . $form->share_token . '?a=' . $a->token);
                                    ?>
                                    <tr id="sf-as-<?php echo $a->id; ?>">
                                        <td>
                                            <b><?php echo html_escape($a->name ?: 'Anonymous draft'); ?></b>
                                            <span class="sf-ttag <?php echo $a->target_type; ?>"><?php echo $a->target_type === 'contact' ? 'customer' : $a->target_type; ?></span>
                                            <?php if ($a->email) { ?><br><span style="font-size:12px;color:var(--sf-muted)"><?php echo html_escape($a->email); ?></span><?php } ?>
                                        </td>
                                        <td>
                                            <span class="sf-badge sf-badge-<?php echo $a->status; ?>"><?php echo str_replace('_', ' ', $a->status); ?></span>
                                            <?php if ($a->is_overdue) { ?><span class="sf-badge sf-badge-overdue">Overdue</span><?php } ?>
                                        </td>
                                        <td>
                                            <?php $prog = $a->status === 'completed' ? 100 : (int) $a->draft_progress; ?>
                                            <span class="sf-prog"><i style="width:<?php echo $prog; ?>%"></i></span>
                                            <span style="font-size:11.5px;color:var(--sf-muted);margin-left:6px"><?php echo $prog; ?>%</span>
                                        </td>
                                        <td>
                                            <?php echo $a->due_date ? _dt($a->due_date) : '—'; ?>
                                        </td>
                                        <td>
                                            <?php if ($a->tat_seconds !== null) { ?>
                                                <b style="color:<?php echo ($a->due_date && strtotime($a->completed_at) > strtotime($a->due_date)) ? 'var(--sf-danger)' : 'var(--sf-success)'; ?>"><?php echo sf_human_secs($a->tat_seconds); ?></b>
                                            <?php } else { ?>—<?php } ?>
                                        </td>
                                        <td style="text-align:right;white-space:nowrap">
                                            <button class="sf-btn sf-btn-outline sf-btn-sm" onclick="sfCopy('<?php echo $link; ?>')" title="Copy personal link"><i class="fa fa-link"></i></button>
                                            <?php if ($a->submission_id) { ?>
                                                <a href="<?php echo admin_url('smart_forms/submissions/' . $form->id); ?>" class="sf-btn sf-btn-outline sf-btn-sm" title="View submission"><i class="fa-regular fa-eye"></i></a>
                                            <?php } ?>
                                            <?php if (has_permission('smart_forms', '', 'delete') || is_admin()) { ?>
                                                <button class="sf-btn sf-btn-outline sf-btn-sm" style="color:var(--sf-danger)!important" onclick="sfDelAssign(<?php echo $a->id; ?>)"><i class="fa fa-trash"></i></button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var sfFormId = <?php echo (int) $form->id; ?>;
var sfPicked = null;

function sfEsc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

/* ── target search ── */
var sfSearchTimer = null;
$('#sf-target-search').on('input', function () {
    var q = this.value.trim();
    clearTimeout(sfSearchTimer);
    if (q.length < 2) { $('#sf-search-results').hide(); return; }
    sfSearchTimer = setTimeout(function () {
        $.post(admin_url + 'smart_forms/search_targets', { q: q, type: $('#sf-target-type').val() }).done(function (res) {
            res = typeof res === 'string' ? JSON.parse(res) : res;
            var html = '';
            (res || []).forEach(function (r) {
                var sub = [r.email, r.phone || r.phonenumber, r.mr_number ? 'MR: ' + r.mr_number : '', r.company].filter(Boolean).join(' · ');
                html += '<div class="sf-sr" data-obj="' + sfEsc(JSON.stringify(r)) + '"><b>' + sfEsc(r.name) + '</b><span>' + sfEsc(sub) + '</span></div>';
            });
            $('#sf-search-results').html(html || '<div class="sf-sr"><span>No matches found</span></div>').show();
        });
    }, 300);
});

$('#sf-search-results').on('click', '.sf-sr[data-obj]', function () {
    sfPicked = JSON.parse($(this).attr('data-obj'));
    $('#sf-picked-name').text(sfPicked.name + (sfPicked.email ? ' · ' + sfPicked.email : ''));
    $('#sf-picked').css('display', 'flex');
    $('#sf-target-search').hide();
    $('#sf-search-results').hide();
});

function sfClearPick() {
    sfPicked = null;
    $('#sf-picked').hide();
    $('#sf-target-search').show().val('').focus();
}

function sfAssign() {
    if (!sfPicked) { alert_float('warning', 'Search and select a patient or customer first.'); return; }
    $.post(admin_url + 'smart_forms/add_assignment', {
        form_id: sfFormId,
        target_type: $('#sf-target-type').val(),
        target_id: sfPicked.id,
        name: sfPicked.name,
        email: sfPicked.email || '',
        phone: sfPicked.phone || sfPicked.phonenumber || sfPicked.mobile_number || '',
        due_date: $('#sf-due-date').val()
    }).done(function (res) {
        res = typeof res === 'string' ? JSON.parse(res) : res;
        if (res.success) {
            sfCopy(res.link, 'Assigned! Personal link copied to clipboard.');
            setTimeout(function () { window.location.reload(); }, 900);
        } else {
            alert_float('danger', res.message || 'Failed to assign.');
        }
    });
}

function sfDelAssign(id) {
    if (!confirm('Remove this assignment? (Completed submissions are kept.)')) return;
    $.post(admin_url + 'smart_forms/delete_assignment/' + id).done(function (res) {
        res = typeof res === 'string' ? JSON.parse(res) : res;
        if (res.success) $('#sf-as-' + id).fadeOut(200, function () { $(this).remove(); });
    });
}

function sfCopy(url, msg) {
    var done = function () { alert_float('success', msg || 'Personal link copied:\n' + url); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done); }
    else { var t = document.createElement('textarea'); t.value = url; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); done(); }
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.sf-search-wrap')) $('#sf-search-results').hide();
});
</script>
</body>
</html>
