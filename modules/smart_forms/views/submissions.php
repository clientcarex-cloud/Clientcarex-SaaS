<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.sfs{--sf-primary:#6366f1;--sf-primary-dark:#4f46e5;--sf-success:#10b981;--sf-warning:#f59e0b;--sf-danger:#ef4444;--sf-text:#1e293b;--sf-muted:#64748b;--sf-border:#e2e8f0;--sf-shadow:0 1px 3px rgba(15,23,42,.08);--sf-shadow-lg:0 12px 32px rgba(15,23,42,.14)}
.sfs .sf-card{background:#fff;border:1px solid var(--sf-border);border-radius:14px;box-shadow:var(--sf-shadow);margin-bottom:16px}
.sfs .sf-card-head{padding:14px 20px;border-bottom:1px solid var(--sf-border);font-weight:700;color:var(--sf-text)}
.sfs .sf-card-body{padding:18px 20px}
.sf-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none!important;transition:all .15s}
.sf-btn-outline{background:#fff;border-color:var(--sf-border);color:var(--sf-text)!important}
.sf-btn-outline:hover{border-color:var(--sf-primary);color:var(--sf-primary)!important}
.sf-btn-sm{padding:5px 11px;font-size:12px;border-radius:8px}
.sf-chips{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.sf-chip{background:#fff;border:1px solid var(--sf-border);border-radius:12px;padding:12px 18px;box-shadow:var(--sf-shadow)}
.sf-chip b{display:block;font-size:20px;color:var(--sf-text)}
.sf-chip span{font-size:12px;color:var(--sf-muted)}
.sf-qstat{margin-bottom:16px}
.sf-qstat-label{font-size:13px;font-weight:600;color:var(--sf-text);margin-bottom:7px}
.sf-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:5px;font-size:12px}
.sf-bar-name{width:170px;color:var(--sf-muted);text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sf-bar-track{flex:1;background:#f1f5f9;border-radius:6px;height:16px;overflow:hidden}
.sf-bar-fill{height:100%;background:linear-gradient(90deg,var(--sf-primary),#8b5cf6);border-radius:6px;min-width:2px}
.sf-bar-count{width:36px;color:var(--sf-text);font-weight:600}
.sf-avg-pill{display:inline-flex;align-items:center;gap:6px;background:#fffbeb;color:#b45309;border-radius:20px;padding:3px 12px;font-size:12px;font-weight:700}
.sfs table.sf-table{width:100%;border-collapse:collapse}
.sfs .sf-table th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--sf-muted);text-align:left;padding:10px 12px;border-bottom:2px solid var(--sf-border)}
.sfs .sf-table td{padding:11px 12px;font-size:13px;color:var(--sf-text);border-bottom:1px solid var(--sf-border)}
.sfs .sf-table tr:hover td{background:#fafbff}
.sf-stars{color:var(--sf-warning)}
.sf-stars .off{color:#e2e8f0}
/* modal */
.sf-modal-answers{max-height:52vh;overflow:auto}
.sf-ans{padding:10px 0;border-bottom:1px solid var(--sf-border)}
.sf-ans:last-child{border-bottom:none}
.sf-ans-q{font-size:12px;font-weight:600;color:var(--sf-muted);margin-bottom:3px}
.sf-ans-v{font-size:13.5px;color:var(--sf-text);white-space:pre-wrap}
.sf-pill-yes{display:inline-block;background:#ecfdf5;color:var(--sf-success);border-radius:14px;padding:2px 10px;font-size:12px;font-weight:700}
.sf-pill-no{display:inline-block;background:#fef2f2;color:var(--sf-danger);border-radius:14px;padding:2px 10px;font-size:12px;font-weight:700}
</style>
<div id="wrapper" class="sfs">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
                    <h3 style="margin:0;font-weight:700;color:var(--sf-text)">
                        <a href="<?php echo admin_url('smart_forms'); ?>" style="color:var(--sf-muted);margin-right:8px"><i class="fa fa-arrow-left"></i></a>
                        <?php echo html_escape($form->title); ?> <small style="color:var(--sf-muted)">— Submissions</small>
                    </h3>
                    <div style="display:flex;gap:8px">
                        <a href="<?php echo admin_url('smart_forms/form/' . $form->id); ?>" class="sf-btn sf-btn-outline"><i class="fa fa-pencil"></i> Edit form</a>
                        <a href="<?php echo admin_url('smart_forms/assignments/' . $form->id); ?>" class="sf-btn sf-btn-outline"><i class="fa-solid fa-user-check"></i> Assignments</a>
                        <a href="<?php echo admin_url('smart_forms/export_csv/' . $form->id); ?>" class="sf-btn sf-btn-outline"><i class="fa fa-download"></i> Export CSV</a>
                    </div>
                </div>

                <div class="sf-chips">
                    <div class="sf-chip"><b><?php echo $stats['total']; ?></b><span>Submissions</span></div>
                    <div class="sf-chip"><b><?php echo $stats['avg_duration'] ? gmdate('i:s', $stats['avg_duration']) : '—'; ?></b><span>Avg fill time</span></div>
                    <div class="sf-chip"><b><?php echo $stats['avg_feedback'] ?: '—'; ?><?php echo $stats['avg_feedback'] ? ' <i class="fa-solid fa-star" style="color:var(--sf-warning);font-size:14px"></i>' : ''; ?></b><span>Avg quick feedback (<?php echo $stats['feedback_count']; ?> ratings)</span></div>
                </div>

                <?php if (!empty($qstats) && $stats['total'] > 0) { ?>
                    <div class="sf-card">
                        <div class="sf-card-head"><i class="fa-solid fa-chart-simple" style="color:var(--sf-primary);margin-right:8px"></i>Question Insights</div>
                        <div class="sf-card-body">
                            <?php foreach ($qstats as $qs) { if ($qs['total'] == 0) continue; ?>
                                <div class="sf-qstat">
                                    <div class="sf-qstat-label">
                                        <?php echo html_escape($qs['question']->label); ?>
                                        <?php if ($qs['average'] !== null) { ?>
                                            <span class="sf-avg-pill"><i class="fa-solid fa-star"></i>avg <?php echo $qs['average']; ?></span>
                                        <?php } ?>
                                        <span style="color:var(--sf-muted);font-weight:400;font-size:11.5px">· <?php echo $qs['total']; ?> answers</span>
                                    </div>
                                    <?php if (!empty($qs['choices'])) {
                                        $max = max($qs['choices']);
                                        foreach ($qs['choices'] as $choice => $count) { ?>
                                            <div class="sf-bar-row">
                                                <div class="sf-bar-name" title="<?php echo html_escape($choice); ?>"><?php echo html_escape($choice); ?></div>
                                                <div class="sf-bar-track"><div class="sf-bar-fill" style="width:<?php echo $max ? round($count / $max * 100) : 0; ?>%"></div></div>
                                                <div class="sf-bar-count"><?php echo $count; ?></div>
                                            </div>
                                    <?php } } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="sf-card">
                    <div class="sf-card-head"><i class="fa-solid fa-inbox" style="color:var(--sf-primary);margin-right:8px"></i>All Submissions</div>
                    <div class="sf-card-body" style="padding:0 20px 6px">
                        <?php if (empty($submissions)) { ?>
                            <p style="padding:26px 0;text-align:center;color:var(--sf-muted)">No submissions yet. Share the public link to start collecting responses.</p>
                        <?php } else { ?>
                            <table class="sf-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Respondent</th>
                                        <th>Submitted</th>
                                        <th>Fill time</th>
                                        <th>Quick feedback</th>
                                        <th style="text-align:right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $s) { ?>
                                        <tr id="sf-sub-<?php echo $s->id; ?>">
                                            <td><?php echo $s->id; ?></td>
                                            <td>
                                                <b><?php echo html_escape($s->respondent_name ?: 'Anonymous'); ?></b>
                                                <?php if ($s->respondent_email) { ?><br><span style="font-size:12px;color:var(--sf-muted)"><?php echo html_escape($s->respondent_email); ?></span><?php } ?>
                                            </td>
                                            <td><?php echo _dt($s->created_at); ?></td>
                                            <td><?php echo $s->duration_seconds ? gmdate('i:s', $s->duration_seconds) : '—'; ?></td>
                                            <td>
                                                <?php if ($s->feedback_rating) { ?>
                                                    <span class="sf-stars"><?php for ($i = 1; $i <= 5; $i++) { echo '<i class="fa-solid fa-star' . ($i <= $s->feedback_rating ? '' : ' off') . '"></i>'; } ?></span>
                                                    <?php if ($s->feedback_comment) { ?><i class="fa-regular fa-comment" style="color:var(--sf-muted);margin-left:6px" title="<?php echo html_escape($s->feedback_comment); ?>"></i><?php } ?>
                                                <?php } else { ?>
                                                    <span style="color:var(--sf-muted)">—</span>
                                                <?php } ?>
                                            </td>
                                            <td style="text-align:right;white-space:nowrap">
                                                <button class="sf-btn sf-btn-outline sf-btn-sm" onclick="sfViewSub(<?php echo $s->id; ?>)"><i class="fa-regular fa-eye"></i> View</button>
                                                <?php if (has_permission('smart_forms', '', 'delete') || is_admin()) { ?>
                                                    <button class="sf-btn sf-btn-outline sf-btn-sm" style="color:var(--sf-danger)!important" onclick="sfDelSub(<?php echo $s->id; ?>)"><i class="fa fa-trash"></i></button>
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

<!-- Submission detail modal -->
<div class="modal fade" id="sf-sub-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;overflow:hidden">
            <div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.85">&times;</button>
                <h4 class="modal-title" style="font-weight:700">Submission <span id="sf-m-id"></span></h4>
                <div id="sf-m-meta" style="font-size:12.5px;opacity:.9;margin-top:4px"></div>
            </div>
            <div class="modal-body">
                <div id="sf-m-feedback" style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px"></div>
                <div class="sf-modal-answers" id="sf-m-answers"></div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
function sfEsc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function sfViewSub(id) {
    $.get(admin_url + 'smart_forms/submission/' + id).done(function (res) {
        res = typeof res === 'string' ? JSON.parse(res) : res;
        if (!res.success) return;
        $('#sf-m-id').text('#' + res.id);
        $('#sf-m-meta').html(sfEsc(res.respondent_name) + (res.respondent_email ? ' · ' + sfEsc(res.respondent_email) : '') + ' · ' + sfEsc(res.created_at) + (res.duration_seconds ? ' · took ' + res.duration_seconds + 's' : ''));

        if (res.feedback_rating) {
            var stars = '';
            for (var i = 1; i <= 5; i++) stars += '<i class="fa-solid fa-star" style="color:' + (i <= res.feedback_rating ? '#f59e0b' : '#e2e8f0') + '"></i>';
            $('#sf-m-feedback').html('<b>Quick feedback:</b> ' + stars + (res.feedback_comment ? '<div style="margin-top:5px;color:#78350f">&ldquo;' + sfEsc(res.feedback_comment) + '&rdquo;</div>' : '')).show();
        } else {
            $('#sf-m-feedback').hide();
        }

        var html = '';
        (res.answers || []).forEach(function (a) {
            var v = sfEsc(a.value);
            if (a.type === 'yes_no' || a.type === 'true_false' || a.type === 'terms') {
                var pos = ['yes', 'true', 'accepted'].indexOf(String(a.value).toLowerCase()) !== -1;
                v = '<span class="' + (pos ? 'sf-pill-yes' : 'sf-pill-no') + '">' + sfEsc(a.value) + '</span>';
            }
            if (a.type === 'file' && a.files) {
                v = a.files.length ? a.files.map(function (f) {
                    return '<a href="' + sfEsc(f.url) + '" style="display:inline-flex;align-items:center;gap:6px;margin:2px 8px 2px 0;padding:5px 12px;background:#eef2ff;border-radius:8px;color:#4f46e5;font-weight:600;font-size:12.5px;text-decoration:none"><i class="fa-solid fa-paperclip"></i>' + sfEsc(f.name) + '</a>';
                }).join('') : '<span style="color:#94a3b8">No files</span>';
            }
            if (a.type === 'credentials' && a.credentials) {
                var pid = 'sf-pw-' + Math.random().toString(36).slice(2);
                v = '<div style="font-size:13px"><i class="fa-regular fa-user" style="width:18px;color:#64748b"></i>' + sfEsc(a.credentials.username) + '</div>'
                  + '<div style="font-size:13px;margin-top:4px"><i class="fa-solid fa-key" style="width:18px;color:#64748b"></i>'
                  + '<span id="' + pid + '" data-pw="' + sfEsc(a.credentials.password) + '">••••••••</span> '
                  + '<a href="#" onclick="var s=document.getElementById(\'' + pid + '\');var on=s.textContent!==\'••••••••\';s.textContent=on?\'••••••••\':s.getAttribute(\'data-pw\');this.textContent=on?\'show\':\'hide\';return false" style="font-size:11.5px;color:#6366f1;font-weight:600">show</a></div>';
            }
            html += '<div class="sf-ans"><div class="sf-ans-q">' + sfEsc(a.label) + '</div><div class="sf-ans-v">' + (v || '<span style="color:#94a3b8">—</span>') + '</div></div>';
        });
        $('#sf-m-answers').html(html || '<p style="color:#94a3b8">No answers recorded.</p>');
        $('#sf-sub-modal').modal('show');
    });
}
function sfDelSub(id) {
    if (!confirm('Delete this submission?')) return;
    $.post(admin_url + 'smart_forms/delete_submission/' + id).done(function (res) {
        res = typeof res === 'string' ? JSON.parse(res) : res;
        if (res.success) { $('#sf-sub-' + id).fadeOut(200, function () { $(this).remove(); }); }
    });
}
</script>
</body>
</html>
