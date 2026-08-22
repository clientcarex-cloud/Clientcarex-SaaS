<?php defined('BASEPATH') or exit('No direct script access allowed');

$voter_link  = site_url('v/' . $poll->code);      // audience: vote only, no results
$screen_link = site_url('vote/' . $poll->code);   // telecast: question + QR + live chart
?>
<?php init_head(); ?>
<style>
.vt-page{--vt-primary:#6366f1;--vt-primary-dark:#4f46e5;--vt-success:#10b981;--vt-warning:#f59e0b;--vt-danger:#ef4444;--vt-text:#1e293b;--vt-muted:#64748b;--vt-border:#e2e8f0;--vt-shadow:0 1px 3px rgba(15,23,42,.08)}
.vt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.vt-header h3{margin:0;font-weight:700;color:var(--vt-text)}
.vt-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none!important;transition:all .15s}
.vt-btn-primary{background:linear-gradient(135deg,var(--vt-primary),var(--vt-primary-dark));color:#fff!important;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.vt-btn-primary:hover{transform:translateY(-1px);color:#fff}
.vt-btn-success{background:var(--vt-success);color:#fff!important}
.vt-btn-danger{background:var(--vt-danger);color:#fff!important}
.vt-btn-warning{background:#fff;border-color:var(--vt-warning);color:#b45309!important}
.vt-btn-outline{background:#fff;border-color:var(--vt-border);color:var(--vt-text)!important}
.vt-btn-outline:hover{border-color:var(--vt-primary);color:var(--vt-primary)!important}
.vt-btn-sm{padding:6px 12px;font-size:12px;border-radius:8px}
.vt-btn[disabled]{opacity:.5;pointer-events:none}
.vt-card{background:#fff;border:1px solid var(--vt-border);border-radius:14px;box-shadow:var(--vt-shadow);margin-bottom:16px}
.vt-card-body{padding:18px 20px}
.vt-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.vt-badge-live{background:#ecfdf5;color:var(--vt-success)}
.vt-badge-live i{animation:vt-pulse 1.2s infinite;font-size:8px}
@keyframes vt-pulse{0%,100%{opacity:1}50%{opacity:.3}}
.vt-badge-draft{background:#f1f5f9;color:var(--vt-muted)}
.vt-badge-ended{background:#eef2ff;color:var(--vt-primary)}
.vt-share{display:flex;gap:18px;align-items:center;flex-wrap:wrap}
.vt-share-qr{width:130px;height:130px;border:1px solid var(--vt-border);border-radius:12px;padding:8px;background:#fff;flex-shrink:0}
.vt-share-qr img{width:100%;height:100%}
.vt-share-code{font-size:28px;font-weight:800;letter-spacing:5px;color:var(--vt-text);font-family:monospace}
.vt-link-row{border:1px solid var(--vt-border);border-radius:12px;padding:12px 14px;margin-bottom:10px;background:#fafbfc}
.vt-link-row:last-child{margin-bottom:0}
.vt-link-tag{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.vt-link-tag.voter{color:var(--vt-success)}
.vt-link-tag.screen{color:var(--vt-primary)}
.vt-link-url{font-family:monospace;font-size:14px;font-weight:700;color:var(--vt-text);word-break:break-all}
.vt-link-note{font-size:11px;color:var(--vt-muted);margin-top:2px}
.vt-link-actions{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
.vt-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.vt-q{border:1px solid var(--vt-border);border-radius:12px;padding:16px 18px;margin-bottom:12px;background:#fff;transition:all .2s}
.vt-q.active{border-color:var(--vt-success);box-shadow:0 0 0 3px rgba(16,185,129,.14)}
.vt-q-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px}
.vt-q-num{width:28px;height:28px;border-radius:8px;background:#eef2ff;color:var(--vt-primary);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0}
.vt-q.active .vt-q-num{background:#ecfdf5;color:var(--vt-success)}
.vt-q-text{flex:1;font-size:15px;font-weight:700;color:var(--vt-text);min-width:200px}
.vt-q-total{font-size:12px;color:var(--vt-muted);font-weight:600}
.vt-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:7px}
.vt-bar-label{width:200px;font-size:13px;color:var(--vt-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0}
.vt-bar-track{flex:1;height:22px;background:#f1f5f9;border-radius:6px;overflow:hidden}
.vt-bar-fill{height:100%;background:linear-gradient(90deg,var(--vt-primary),#8b5cf6);border-radius:6px;transition:width .6s cubic-bezier(.4,0,.2,1);min-width:0}
.vt-bar-val{width:90px;font-size:12px;font-weight:700;color:var(--vt-muted);flex-shrink:0;text-align:right}
.vt-voters{font-size:13px;color:var(--vt-muted);font-weight:600}
.vt-voters b{color:var(--vt-text);font-size:16px}
.vt-names{margin-top:10px;border-top:1px dashed var(--vt-border);padding-top:8px}
.vt-names summary{cursor:pointer;font-size:12px;font-weight:700;color:var(--vt-primary);user-select:none;outline:none}
.vt-names-list{max-height:180px;overflow-y:auto;margin-top:8px;display:flex;flex-wrap:wrap;gap:6px}
.vt-name-chip{display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid var(--vt-border);border-radius:16px;padding:3px 11px;font-size:12px;color:var(--vt-text)}
.vt-name-chip .opt{color:var(--vt-muted);font-weight:600}
@media(max-width:767px){.vt-bar-label{width:110px}}
</style>
<div id="wrapper" class="vt-page">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="vt-header">
                    <h3><i class="fa-solid fa-tower-broadcast" style="color:var(--vt-primary);margin-right:8px"></i><?php echo html_escape($poll->title); ?></h3>
                    <div style="display:flex;gap:8px;align-items:center">
                        <span id="vt-status-badge"></span>
                        <a href="<?php echo admin_url('voting/poll/' . $poll->id); ?>" class="vt-btn vt-btn-outline vt-btn-sm"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
                        <a href="<?php echo admin_url('voting'); ?>" class="vt-btn vt-btn-outline vt-btn-sm"><i class="fa fa-arrow-left"></i> All Polls</a>
                    </div>
                </div>

                <div class="vt-card">
                    <div class="vt-card-body vt-share">
                        <div style="text-align:center;flex-shrink:0">
                            <div class="vt-share-qr"><img src="<?php echo site_url('voting/voting_public/qr/' . $poll->code); ?>" alt="QR"></div>
                            <div class="vt-share-code"><?php echo html_escape($poll->code); ?></div>
                            <div style="font-size:11px;color:var(--vt-muted);font-weight:600">QR → voter link</div>
                        </div>
                        <div style="flex:1;min-width:260px">
                            <div class="vt-link-row">
                                <div class="vt-link-tag voter"><i class="fa-solid fa-users"></i> Voter link — audience</div>
                                <div class="vt-link-url"><?php echo $voter_link; ?></div>
                                <div class="vt-link-note">Vote only — results are never shown on this link. This is what the QR opens.</div>
                                <div class="vt-link-actions">
                                    <button type="button" class="vt-btn vt-btn-outline vt-btn-sm" onclick="vtCopy('<?php echo $voter_link; ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
                                    <a href="<?php echo $voter_link; ?>" target="_blank" class="vt-btn vt-btn-outline vt-btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a>
                                </div>
                            </div>
                            <div class="vt-link-row">
                                <div class="vt-link-tag screen"><i class="fa-solid fa-display"></i> Screen link — live telecast</div>
                                <div class="vt-link-url"><?php echo $screen_link; ?></div>
                                <div class="vt-link-note">Question + QR + real-time result chart. Put this on the projector / webinar share.</div>
                                <div class="vt-link-actions">
                                    <button type="button" class="vt-btn vt-btn-outline vt-btn-sm" onclick="vtCopy('<?php echo $screen_link; ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
                                    <a href="<?php echo $screen_link; ?>" target="_blank" class="vt-btn vt-btn-outline vt-btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a>
                                </div>
                            </div>
                        </div>
                        <div style="text-align:center;padding:0 10px;display:flex;flex-direction:column;gap:14px">
                            <div class="vt-voters"><b id="vt-devices">0</b><br>joined</div>
                            <div class="vt-voters"><b id="vt-voters">0</b><br>voters</div>
                        </div>
                    </div>
                </div>

                <div class="vt-card">
                    <div class="vt-card-body vt-controls" id="vt-controls"></div>
                </div>

                <div id="vt-questions"></div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
var vtState     = <?php echo json_encode($state); ?>;
var vtActionUrl = '<?php echo admin_url('voting/action/' . $poll->id); ?>';
var vtStateUrl  = '<?php echo admin_url('voting/state/' . $poll->id); ?>';
var vtCanDelete = <?php echo (has_permission('voting', '', 'delete') || is_admin()) ? 'true' : 'false'; ?>;
var vtBusy      = false;
var vtNamesOpen = {}; // keep <details> open across re-renders

function vtEsc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

function vtCopy(text, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
    }
    alert_float('success', 'Link copied');
}

function vtAction(act, questionId) {
    if (vtBusy) return;
    vtBusy = true;
    var payload = { act: act };
    if (questionId) payload.question_id = questionId;
    $.post(vtActionUrl, payload).done(function(res) {
        if (typeof res === 'string') res = JSON.parse(res);
        if (res && res.state) { vtState = res.state; vtRender(); }
    }).always(function() { vtBusy = false; });
}

function vtRenderBadge() {
    var el = document.getElementById('vt-status-badge');
    if (vtState.status === 'live') {
        el.innerHTML = '<span class="vt-badge vt-badge-live"><i class="fa-solid fa-circle"></i>Live</span>';
    } else if (vtState.status === 'ended') {
        el.innerHTML = '<span class="vt-badge vt-badge-ended">Ended</span>';
    } else {
        el.innerHTML = '<span class="vt-badge vt-badge-draft">Draft</span>';
    }
}

function vtRenderControls() {
    var h = '';
    if (vtState.status === 'draft') {
        h += '<button class="vt-btn vt-btn-success" onclick="vtAction(\'start\')"><i class="fa fa-play"></i> Start Voting</button>';
        h += '<span style="font-size:13px;color:var(--vt-muted)">Going live activates the first question automatically.</span>';
    } else if (vtState.status === 'live') {
        h += '<button class="vt-btn vt-btn-primary" onclick="vtAction(\'next\')"><i class="fa fa-forward-step"></i> Next Question</button>';
        h += '<button class="vt-btn vt-btn-warning" onclick="vtAction(\'hold\')" ' + (vtState.active_id ? '' : 'disabled') + '><i class="fa fa-pause"></i> Hold Screen</button>';
        h += '<button class="vt-btn vt-btn-danger" onclick="if(confirm(\'End voting? The public page will show the final results.\'))vtAction(\'end\')"><i class="fa fa-stop"></i> End Voting</button>';
    } else {
        h += '<button class="vt-btn vt-btn-success" onclick="vtAction(\'reopen\')"><i class="fa fa-rotate-left"></i> Reopen Voting</button>';
        h += '<span style="font-size:13px;color:var(--vt-muted)">The public page is showing the final results.</span>';
    }
    document.getElementById('vt-controls').innerHTML = h;
}

function vtRenderQuestions() {
    var h = '';
    vtState.questions.forEach(function(q) {
        var isActive = vtState.active_id === q.id;
        h += '<div class="vt-q' + (isActive ? ' active' : '') + '">';
        h += '<div class="vt-q-head">';
        h += '<div class="vt-q-num">' + q.number + '</div>';
        h += '<div class="vt-q-text">' + vtEsc(q.question) + '</div>';
        h += '<span class="vt-q-total"><i class="fa-solid fa-check-to-slot"></i> ' + q.total + ' votes</span>';
        if (vtState.status === 'live') {
            if (isActive) {
                h += '<span class="vt-badge vt-badge-live"><i class="fa-solid fa-circle"></i>On screen</span>';
            } else {
                h += '<button class="vt-btn vt-btn-outline vt-btn-sm" onclick="vtAction(\'activate\',' + q.id + ')"><i class="fa fa-play"></i> Activate</button>';
            }
        }
        if (vtCanDelete && q.total > 0) {
            h += '<button class="vt-btn vt-btn-outline vt-btn-sm" title="Reset votes" onclick="if(confirm(\'Reset all votes for this question?\'))vtAction(\'reset\',' + q.id + ')"><i class="fa fa-eraser"></i></button>';
        }
        h += '</div>';
        q.options.forEach(function(o) {
            h += '<div class="vt-bar-row">';
            h += '<div class="vt-bar-label" title="' + vtEsc(o.label) + '">' + vtEsc(o.label) + '</div>';
            h += '<div class="vt-bar-track"><div class="vt-bar-fill" style="width:' + o.percent + '%"></div></div>';
            h += '<div class="vt-bar-val">' + o.votes + ' · ' + o.percent + '%</div>';
            h += '</div>';
        });
        if (q.names && q.names.length) {
            var open = vtNamesOpen[q.id] ? ' open' : '';
            h += '<details class="vt-names" data-qid="' + q.id + '"' + open + '><summary><i class="fa-regular fa-address-card"></i> Voter names (' + q.names.length + ')</summary>';
            h += '<div class="vt-names-list">';
            q.names.forEach(function(n) {
                h += '<span class="vt-name-chip"><b>' + vtEsc(n.name) + '</b><span class="opt">→ ' + vtEsc(n.option) + '</span></span>';
            });
            h += '</div></details>';
        }
        h += '</div>';
    });
    if (!vtState.questions.length) {
        h = '<div class="vt-card"><div class="vt-card-body" style="text-align:center;color:var(--vt-muted)">No questions yet — <a href="<?php echo admin_url('voting/poll/' . $poll->id); ?>">add some in the editor</a>.</div></div>';
    }
    document.getElementById('vt-questions').innerHTML = h;
    document.querySelectorAll('.vt-names').forEach(function(d) {
        d.addEventListener('toggle', function() { vtNamesOpen[d.dataset.qid] = d.open; });
    });
}

function vtRender() {
    vtRenderBadge();
    vtRenderControls();
    vtRenderQuestions();
    document.getElementById('vt-voters').textContent = vtState.voters;
    document.getElementById('vt-devices').textContent = (typeof vtState.devices !== 'undefined') ? vtState.devices : 0;
}

setInterval(function() {
    if (vtBusy) return;
    $.get(vtStateUrl).done(function(res) {
        if (typeof res === 'string') res = JSON.parse(res);
        if (res && res.questions) { vtState = res; vtRender(); }
    });
}, 3000);

vtRender();
</script>
</body>
</html>
