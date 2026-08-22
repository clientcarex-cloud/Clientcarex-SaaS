<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.sf-page{--sf-primary:#6366f1;--sf-primary-dark:#4f46e5;--sf-success:#10b981;--sf-warning:#f59e0b;--sf-danger:#ef4444;--sf-text:#1e293b;--sf-muted:#64748b;--sf-border:#e2e8f0;--sf-bg:#f8fafc;--sf-shadow:0 1px 3px rgba(15,23,42,.08);--sf-shadow-lg:0 12px 32px rgba(15,23,42,.14)}
.sf-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.sf-header h3{margin:0;font-weight:700;color:var(--sf-text)}
.sf-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none!important;transition:all .15s}
.sf-btn-primary{background:linear-gradient(135deg,var(--sf-primary),var(--sf-primary-dark));color:#fff!important;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.sf-btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(99,102,241,.4);color:#fff}
.sf-btn-outline{background:#fff;border-color:var(--sf-border);color:var(--sf-text)!important}
.sf-btn-outline:hover{border-color:var(--sf-primary);color:var(--sf-primary)!important}
.sf-btn-sm{padding:6px 12px;font-size:12px;border-radius:8px}
.sf-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}
@media(max-width:991px){.sf-stats{grid-template-columns:repeat(2,1fr)}}
.sf-stat{background:#fff;border:1px solid var(--sf-border);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:var(--sf-shadow)}
.sf-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.sf-stat-icon.purple{background:#eef2ff;color:var(--sf-primary)}
.sf-stat-icon.green{background:#ecfdf5;color:var(--sf-success)}
.sf-stat-icon.cyan{background:#ecfeff;color:#06b6d4}
.sf-stat-icon.amber{background:#fffbeb;color:var(--sf-warning)}
.sf-stat-value{font-size:22px;font-weight:800;color:var(--sf-text);line-height:1.1}
.sf-stat-label{font-size:12px;color:var(--sf-muted);margin-top:2px}
.sf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}
.sf-card{background:#fff;border:1px solid var(--sf-border);border-radius:14px;box-shadow:var(--sf-shadow);transition:box-shadow .15s}
.sf-card:hover{box-shadow:var(--sf-shadow-lg)}
.sf-card-body{padding:18px 20px}
.sf-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.sf-badge-active{background:#ecfdf5;color:var(--sf-success)}
.sf-badge-draft{background:#f1f5f9;color:var(--sf-muted)}
.sf-badge-closed{background:#fef2f2;color:var(--sf-danger)}
.sf-cat{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#eef2ff;color:var(--sf-primary)}
.sf-form-title{margin:12px 0 6px;font-size:16px;font-weight:700;color:var(--sf-text)}
.sf-form-desc{font-size:13px;color:var(--sf-muted);margin:0 0 10px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.sf-meta{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:12px;color:var(--sf-muted);margin-bottom:12px}
.sf-meta i{margin-right:5px}
.sf-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;border-top:1px solid var(--sf-border);padding-top:12px}
.sf-actions .sf-btn-sm{padding:6px 10px}
.sf-actions .dropdown .sf-btn-sm{padding:6px 9px}
.sf-empty{text-align:center;padding:60px 20px;color:var(--sf-muted)}
.sf-empty-icon{font-size:44px;color:#cbd5e1;margin-bottom:14px}
.sf-fb-stars{color:var(--sf-warning);font-size:12px}
.sf-page .dropdown-menu{border-radius:10px;padding:6px;box-shadow:var(--sf-shadow-lg);border:1px solid var(--sf-border)}
.sf-page .dropdown-menu>li>a{border-radius:6px;padding:7px 10px;font-size:13px}
</style>
<div id="wrapper" class="sf-page">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="sf-header">
                    <h3><i class="fa-solid fa-rectangle-list" style="color:var(--sf-primary);margin-right:8px"></i><?php echo _l('smart_forms'); ?></h3>
                    <?php if (has_permission('smart_forms', '', 'create') || is_admin()) { ?>
                        <a href="<?php echo admin_url('smart_forms/form'); ?>" class="sf-btn sf-btn-primary"><i class="fa fa-plus"></i> <?php echo _l('smart_forms_new_form'); ?></a>
                    <?php } ?>
                </div>

                <div class="sf-stats">
                    <div class="sf-stat"><div class="sf-stat-icon purple"><i class="fa-solid fa-rectangle-list"></i></div><div><div class="sf-stat-value"><?php echo $summary['total_forms']; ?></div><div class="sf-stat-label">Total Forms</div></div></div>
                    <div class="sf-stat"><div class="sf-stat-icon green"><i class="fa-solid fa-circle-check"></i></div><div><div class="sf-stat-value"><?php echo $summary['active_forms']; ?></div><div class="sf-stat-label">Active Forms</div></div></div>
                    <div class="sf-stat"><div class="sf-stat-icon cyan"><i class="fa-solid fa-inbox"></i></div><div><div class="sf-stat-value"><?php echo $summary['total_submissions']; ?></div><div class="sf-stat-label">Submissions</div></div></div>
                    <div class="sf-stat"><div class="sf-stat-icon green"><i class="fa-regular fa-calendar-check"></i></div><div><div class="sf-stat-value"><?php echo $summary['today_submissions']; ?></div><div class="sf-stat-label">Today</div></div></div>
                    <div class="sf-stat"><div class="sf-stat-icon amber"><i class="fa-solid fa-star"></i></div><div><div class="sf-stat-value"><?php echo $summary['avg_feedback'] ?: '—'; ?></div><div class="sf-stat-label">Avg Quick Feedback</div></div></div>
                </div>

                <?php if (empty($forms)) { ?>
                    <div class="sf-card">
                        <div class="sf-empty">
                            <div class="sf-empty-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                            <h4 style="color:var(--sf-text);font-weight:700">No forms yet</h4>
                            <p>Build smart forms for onboarding, process implementation, T&amp;C acceptance and more.</p>
                            <?php if (has_permission('smart_forms', '', 'create') || is_admin()) { ?>
                                <a href="<?php echo admin_url('smart_forms/form'); ?>" class="sf-btn sf-btn-primary" style="margin-top:14px"><i class="fa fa-plus"></i> Create your first form</a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="sf-grid">
                        <?php foreach ($forms as $form) {
                            $cats = ['onboarding' => 'Onboarding', 'process' => 'Process', 'training' => 'Training', 'acknowledgement' => 'Acknowledgement', 'general' => 'General'];
                            $cat  = isset($cats[$form->category]) ? $cats[$form->category] : ucfirst($form->category);
                        ?>
                        <div class="sf-card">
                            <div class="sf-card-body">
                                <div style="display:flex;align-items:center;justify-content:space-between">
                                    <div style="display:flex;gap:6px">
                                        <span class="sf-badge sf-badge-<?php echo $form->status; ?>"><?php echo ucfirst($form->status); ?></span>
                                        <span class="sf-cat"><?php echo html_escape($cat); ?></span>
                                    </div>
                                    <span style="font-size:11px;color:var(--sf-muted)">#<?php echo $form->id; ?></span>
                                </div>
                                <h4 class="sf-form-title"><?php echo html_escape($form->title); ?></h4>
                                <?php if ($form->description) { ?>
                                    <p class="sf-form-desc"><?php echo html_escape($form->description); ?></p>
                                <?php } ?>
                                <div class="sf-meta">
                                    <span><i class="fa-solid fa-list-ol"></i><?php echo $form->question_count; ?> questions</span>
                                    <span><i class="fa-solid fa-inbox"></i><?php echo $form->submission_count; ?> submissions</span>
                                    <span><i class="fa-regular fa-clock"></i><?php echo date('d M Y', strtotime($form->created_at)); ?></span>
                                    <?php if (!empty($form->collect_feedback)) { ?>
                                        <span class="sf-fb-stars" title="Quick feedback enabled"><i class="fa-solid fa-star"></i>Quick feedback</span>
                                    <?php } ?>
                                </div>
                                <div class="sf-actions">
                                    <a href="<?php echo admin_url('smart_forms/form/' . $form->id); ?>" class="sf-btn sf-btn-outline sf-btn-sm"><i class="fa fa-pencil"></i> Edit</a>
                                    <a href="<?php echo admin_url('smart_forms/submissions/' . $form->id); ?>" class="sf-btn sf-btn-outline sf-btn-sm"><i class="fa-regular fa-eye"></i> Submissions</a>
                                    <a href="<?php echo admin_url('smart_forms/assignments/' . $form->id); ?>" class="sf-btn sf-btn-outline sf-btn-sm"><i class="fa-solid fa-user-check"></i> Assign</a>
                                    <?php if ($form->share_token) { ?>
                                        <a href="#" class="sf-btn sf-btn-outline sf-btn-sm" onclick="sfCopyLink(this);return false" data-url="<?php echo site_url('form/' . $form->share_token); ?>" title="Copy public link"><i class="fa fa-link"></i></a>
                                    <?php } ?>
                                    <div class="dropdown" style="margin-left:auto">
                                        <button class="sf-btn sf-btn-outline sf-btn-sm dropdown-toggle" data-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-right">
                                            <?php if (has_permission('smart_forms', '', 'edit') || is_admin()) { ?>
                                                <li><a href="#" onclick="sfToggleStatus(<?php echo $form->id; ?>);return false"><i class="fa fa-<?php echo $form->status === 'active' ? 'pause' : 'play'; ?>" style="width:16px"></i> <?php echo $form->status === 'active' ? 'Close form' : 'Activate form'; ?></a></li>
                                            <?php } ?>
                                            <?php if ($form->share_token && $form->status === 'active') { ?>
                                                <li><a href="<?php echo site_url('form/' . $form->share_token); ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square" style="width:16px"></i> Open public form</a></li>
                                            <?php } ?>
                                            <?php if (has_permission('smart_forms', '', 'create') || is_admin()) { ?>
                                                <li><a href="<?php echo admin_url('smart_forms/duplicate/' . $form->id); ?>"><i class="fa fa-copy" style="width:16px"></i> Duplicate</a></li>
                                            <?php } ?>
                                            <li><a href="<?php echo admin_url('smart_forms/export_csv/' . $form->id); ?>"><i class="fa fa-download" style="width:16px"></i> Export CSV</a></li>
                                            <?php if (has_permission('smart_forms', '', 'delete') || is_admin()) { ?>
                                                <li class="divider"></li>
                                                <li><a href="<?php echo admin_url('smart_forms/delete/' . $form->id); ?>" onclick="return confirm('Delete this form and ALL its submissions?')" style="color:var(--sf-danger)"><i class="fa fa-trash" style="width:16px"></i> Delete</a></li>
                                            <?php } ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
function sfCopyLink(el) {
    var url = el.getAttribute('data-url');
    var done = function () { alert_float('success', 'Public link copied:\n' + url); };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(done);
    } else {
        var t = document.createElement('textarea');
        t.value = url; document.body.appendChild(t); t.select();
        document.execCommand('copy'); document.body.removeChild(t); done();
    }
}
function sfToggleStatus(id) {
    $.post(admin_url + 'smart_forms/toggle_status/' + id).done(function (res) {
        res = typeof res === 'string' ? JSON.parse(res) : res;
        if (res.success) { window.location.reload(); }
    });
}
</script>
</body>
</html>
