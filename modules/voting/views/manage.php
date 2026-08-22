<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.vt-page{--vt-primary:#6366f1;--vt-primary-dark:#4f46e5;--vt-success:#10b981;--vt-warning:#f59e0b;--vt-danger:#ef4444;--vt-text:#1e293b;--vt-muted:#64748b;--vt-border:#e2e8f0;--vt-shadow:0 1px 3px rgba(15,23,42,.08);--vt-shadow-lg:0 12px 32px rgba(15,23,42,.14)}
.vt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.vt-header h3{margin:0;font-weight:700;color:var(--vt-text)}
.vt-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none!important;transition:all .15s}
.vt-btn-primary{background:linear-gradient(135deg,var(--vt-primary),var(--vt-primary-dark));color:#fff!important;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.vt-btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(99,102,241,.4);color:#fff}
.vt-btn-outline{background:#fff;border-color:var(--vt-border);color:var(--vt-text)!important}
.vt-btn-outline:hover{border-color:var(--vt-primary);color:var(--vt-primary)!important}
.vt-btn-sm{padding:6px 12px;font-size:12px;border-radius:8px}
.vt-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
@media(max-width:991px){.vt-stats{grid-template-columns:repeat(2,1fr)}}
.vt-stat{background:#fff;border:1px solid var(--vt-border);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:var(--vt-shadow)}
.vt-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.vt-stat-icon.purple{background:#eef2ff;color:var(--vt-primary)}
.vt-stat-icon.red{background:#fef2f2;color:var(--vt-danger)}
.vt-stat-icon.green{background:#ecfdf5;color:var(--vt-success)}
.vt-stat-icon.amber{background:#fffbeb;color:var(--vt-warning)}
.vt-stat-value{font-size:22px;font-weight:800;color:var(--vt-text);line-height:1.1}
.vt-stat-label{font-size:12px;color:var(--vt-muted);margin-top:2px}
.vt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}
.vt-card{background:#fff;border:1px solid var(--vt-border);border-radius:14px;box-shadow:var(--vt-shadow);transition:box-shadow .15s}
.vt-card:hover{box-shadow:var(--vt-shadow-lg)}
.vt-card-body{padding:18px 20px}
.vt-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.vt-badge-live{background:#ecfdf5;color:var(--vt-success)}
.vt-badge-live i{animation:vt-pulse 1.2s infinite}
@keyframes vt-pulse{0%,100%{opacity:1}50%{opacity:.35}}
.vt-badge-draft{background:#f1f5f9;color:var(--vt-muted)}
.vt-badge-ended{background:#eef2ff;color:var(--vt-primary)}
.vt-code{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:700;background:#f8fafc;border:1px dashed var(--vt-border);color:var(--vt-text);font-family:monospace;letter-spacing:1px}
.vt-poll-title{margin:12px 0 6px;font-size:16px;font-weight:700;color:var(--vt-text)}
.vt-poll-desc{font-size:13px;color:var(--vt-muted);margin:0 0 10px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.vt-meta{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:12px;color:var(--vt-muted);margin-bottom:12px}
.vt-meta i{margin-right:5px}
.vt-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;border-top:1px solid var(--vt-border);padding-top:12px}
.vt-empty{text-align:center;padding:60px 20px;color:var(--vt-muted)}
.vt-empty-icon{font-size:44px;color:#cbd5e1;margin-bottom:14px}
</style>
<div id="wrapper" class="vt-page">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="vt-header">
                    <h3><i class="fa-solid fa-square-poll-vertical" style="color:var(--vt-primary);margin-right:8px"></i><?php echo _l('voting'); ?></h3>
                    <?php if (has_permission('voting', '', 'create') || is_admin()) { ?>
                        <a href="<?php echo admin_url('voting/poll'); ?>" class="vt-btn vt-btn-primary"><i class="fa fa-plus"></i> <?php echo _l('voting_new_poll'); ?></a>
                    <?php } ?>
                </div>

                <div class="vt-stats">
                    <div class="vt-stat"><div class="vt-stat-icon purple"><i class="fa-solid fa-square-poll-vertical"></i></div><div><div class="vt-stat-value"><?php echo $summary['total_polls']; ?></div><div class="vt-stat-label">Total Polls</div></div></div>
                    <div class="vt-stat"><div class="vt-stat-icon red"><i class="fa-solid fa-tower-broadcast"></i></div><div><div class="vt-stat-value"><?php echo $summary['live_polls']; ?></div><div class="vt-stat-label">Live Now</div></div></div>
                    <div class="vt-stat"><div class="vt-stat-icon green"><i class="fa-solid fa-check-to-slot"></i></div><div><div class="vt-stat-value"><?php echo $summary['total_votes']; ?></div><div class="vt-stat-label">Total Votes</div></div></div>
                    <div class="vt-stat"><div class="vt-stat-icon amber"><i class="fa-regular fa-calendar-check"></i></div><div><div class="vt-stat-value"><?php echo $summary['today_votes']; ?></div><div class="vt-stat-label">Votes Today</div></div></div>
                </div>

                <?php if (empty($polls)) { ?>
                    <div class="vt-card">
                        <div class="vt-empty">
                            <div class="vt-empty-icon"><i class="fa-solid fa-tower-broadcast"></i></div>
                            <h4 style="color:var(--vt-text);font-weight:700">No polls yet</h4>
                            <p>Create a live poll for your webinar — share one short link, drive the questions, watch results in real-time.</p>
                            <?php if (has_permission('voting', '', 'create') || is_admin()) { ?>
                                <a href="<?php echo admin_url('voting/poll'); ?>" class="vt-btn vt-btn-primary" style="margin-top:14px"><i class="fa fa-plus"></i> Create your first poll</a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="vt-grid">
                        <?php foreach ($polls as $poll) {
                            $voter_link  = site_url('v/' . $poll->code);
                            $screen_link = site_url('vote/' . $poll->code); ?>
                            <div class="vt-card">
                                <div class="vt-card-body">
                                    <div style="display:flex;justify-content:space-between;align-items:center">
                                        <?php if ($poll->status === 'live') { ?>
                                            <span class="vt-badge vt-badge-live"><i class="fa-solid fa-circle" style="font-size:8px;margin-right:5px"></i>Live</span>
                                        <?php } elseif ($poll->status === 'ended') { ?>
                                            <span class="vt-badge vt-badge-ended">Ended</span>
                                        <?php } else { ?>
                                            <span class="vt-badge vt-badge-draft">Draft</span>
                                        <?php } ?>
                                        <span class="vt-code" title="<?php echo html_escape($voter_link); ?>"><i class="fa-solid fa-link" style="font-size:10px"></i><?php echo html_escape($poll->code); ?></span>
                                    </div>
                                    <div class="vt-poll-title"><?php echo html_escape($poll->title); ?></div>
                                    <?php if (!empty($poll->description)) { ?>
                                        <p class="vt-poll-desc"><?php echo html_escape($poll->description); ?></p>
                                    <?php } ?>
                                    <div class="vt-meta">
                                        <span><i class="fa-regular fa-circle-question"></i><?php echo (int) $poll->question_count; ?> questions</span>
                                        <span><i class="fa-solid fa-check-to-slot"></i><?php echo (int) $poll->vote_count; ?> votes</span>
                                        <span><i class="fa-solid fa-users"></i><?php echo (int) $poll->voter_count; ?> voters</span>
                                        <span><i class="fa-regular fa-clock"></i><?php echo _dt($poll->created_at); ?></span>
                                    </div>
                                    <div class="vt-actions">
                                        <a href="<?php echo admin_url('voting/monitor/' . $poll->id); ?>" class="vt-btn vt-btn-primary vt-btn-sm"><i class="fa-solid fa-tower-broadcast"></i> Monitor</a>
                                        <?php if (has_permission('voting', '', 'edit') || is_admin()) { ?>
                                            <a href="<?php echo admin_url('voting/poll/' . $poll->id); ?>" class="vt-btn vt-btn-outline vt-btn-sm"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
                                        <?php } ?>
                                        <a href="<?php echo $screen_link; ?>" target="_blank" class="vt-btn vt-btn-outline vt-btn-sm" title="Open telecast screen (live results)"><i class="fa-solid fa-display"></i></a>
                                        <button type="button" class="vt-btn vt-btn-outline vt-btn-sm" onclick="vtCopy('<?php echo $voter_link; ?>', this)" title="Copy voter link (vote only)"><i class="fa-regular fa-copy"></i></button>
                                        <?php if (has_permission('voting', '', 'delete') || is_admin()) { ?>
                                            <a href="<?php echo admin_url('voting/delete/' . $poll->id); ?>" class="vt-btn vt-btn-outline vt-btn-sm" style="margin-left:auto;color:var(--vt-danger)!important" onclick="return confirm('Delete this poll and all its votes?');"><i class="fa-regular fa-trash-can"></i></a>
                                        <?php } ?>
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
function vtCopy(text, btn) {
    var done = function() {
        btn.innerHTML = '<i class="fa-solid fa-check"></i>';
        setTimeout(function() { btn.innerHTML = '<i class="fa-regular fa-copy"></i>'; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done);
    } else {
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta); done();
    }
}
</script>
</body>
</html>
