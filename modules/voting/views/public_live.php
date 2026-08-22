<?php defined('BASEPATH') or exit('No direct script access allowed');

$CI        = &get_instance();
$company   = get_option('companyname');
$link      = site_url('v/' . $poll->code); // voter link — what the QR points to
$link_disp = preg_replace('#^https?://#', '', $link);
$csrf_name = $CI->security->get_csrf_token_name();
$csrf_hash = $CI->security->get_csrf_hash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($poll->title); ?> — Live Voting</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--p:#6366f1;--pd:#4f46e5;--p2:#8b5cf6;--ok:#10b981;--okd:#059669;--text:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#ffffff}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:radial-gradient(900px 420px at 85% -8%,#e0e7ff 0%,transparent 60%),radial-gradient(700px 380px at -10% 30%,#f5f3ff 0%,transparent 55%),linear-gradient(160deg,#eef2ff 0%,#f8fafc 45%,#f1f5f9 100%);background-attachment:fixed;min-height:100vh;color:var(--text);padding:22px 18px 50px}
.wrap{max-width:1080px;margin:0 auto}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:22px}
.brand{display:inline-flex;align-items:center;gap:9px;font-size:13px;font-weight:800;color:var(--muted);letter-spacing:.6px;text-transform:uppercase}
.brand .dot-logo{width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--p),var(--p2));display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:13px;box-shadow:0 4px 10px rgba(99,102,241,.35)}
.live-badge{display:inline-flex;align-items:center;gap:7px;padding:6px 15px;border-radius:20px;font-size:12px;font-weight:800;letter-spacing:1px;background:#fff;color:#059669;border:1px solid #a7f3d0;box-shadow:0 2px 8px rgba(16,185,129,.15)}
.live-badge .dot{width:8px;height:8px;border-radius:50%;background:#10b981;animation:pulse 1.2s infinite}
.live-badge.ended{color:var(--pd);border-color:#c7d2fe;box-shadow:0 2px 8px rgba(99,102,241,.15)}
.live-badge.ended .dot{background:var(--p);animation:none}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}
h1.poll-title{font-size:clamp(22px,3.4vw,34px);font-weight:900;line-height:1.25;margin-bottom:6px;letter-spacing:-.5px;background:linear-gradient(120deg,#0f172a 60%,#4338ca);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.poll-desc{font-size:14px;color:var(--muted);max-width:640px;line-height:1.55}
.grid{display:grid;grid-template-columns:1fr 300px;gap:20px;margin-top:24px;align-items:start}
@media(max-width:920px){.grid{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--border);border-radius:20px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 20px 50px rgba(15,23,42,.08)}
.stage{padding:clamp(22px,3.5vw,38px);position:relative;overflow:hidden}
.stage::before{content:'';position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,var(--p),var(--p2),#ec4899)}
.q-meta{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px}
.q-chip{background:linear-gradient(135deg,#eef2ff,#f5f3ff);color:var(--pd);padding:5px 14px;border-radius:16px;font-size:12px;font-weight:800;letter-spacing:.5px;border:1px solid #e0e7ff}
.q-text{font-size:clamp(20px,3vw,30px);font-weight:800;line-height:1.3;margin-bottom:24px;letter-spacing:-.4px}
.opt{position:relative;display:block;width:100%;text-align:left;border:1.5px solid var(--border);background:#f8fafc;border-radius:14px;margin-bottom:12px;cursor:pointer;overflow:hidden;padding:0;transition:border-color .2s,box-shadow .2s,transform .1s;-webkit-tap-highlight-color:transparent}
.opt:hover{border-color:#a5b4fc;box-shadow:0 4px 14px rgba(99,102,241,.14)}
.opt:active{transform:scale(.99)}
.opt.selected{border-color:var(--ok);box-shadow:0 0 0 3px rgba(16,185,129,.15),0 4px 14px rgba(16,185,129,.15)}
.opt.disabled{cursor:default}
.opt.disabled:hover{border-color:var(--border);box-shadow:none}
.opt.selected.disabled:hover{border-color:var(--ok);box-shadow:0 0 0 3px rgba(16,185,129,.15)}
.opt.locked{cursor:default}
.opt.locked:not(.selected){opacity:.6}
.opt.locked:hover{border-color:var(--border);box-shadow:none}
.opt.locked:active{transform:none}
.opt.selected.locked:hover{border-color:var(--ok);box-shadow:0 0 0 3px rgba(16,185,129,.15),0 4px 14px rgba(16,185,129,.15)}
.opt .fill{position:absolute;inset:0;width:0;background:linear-gradient(90deg,rgba(99,102,241,.16),rgba(139,92,246,.20));transition:width .7s cubic-bezier(.4,0,.2,1);min-width:0}
.opt.selected .fill{background:linear-gradient(90deg,rgba(16,185,129,.18),rgba(16,185,129,.10))}
.opt .row{position:relative;display:flex;align-items:center;gap:12px;padding:15px 18px}
.opt .label{flex:1;font-size:clamp(14px,1.8vw,17px);font-weight:600;line-height:1.35;color:var(--text)}
.opt .pct{font-size:clamp(15px,2vw,19px);font-weight:900;min-width:54px;text-align:right;font-variant-numeric:tabular-nums;color:var(--text)}
.opt.selected .pct{color:var(--okd)}
.opt .cnt{font-size:11px;color:var(--muted);font-weight:600;min-width:52px;text-align:right;font-variant-numeric:tabular-nums}
.opt .tick{width:22px;height:22px;border-radius:50%;border:2px solid #cbd5e1;background:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;color:transparent;transition:all .15s}
.opt.selected .tick{background:var(--ok);border-color:var(--ok);color:#fff;box-shadow:0 2px 6px rgba(16,185,129,.4)}
.hint{font-size:13px;color:var(--muted);margin-top:6px;min-height:18px}
.hint.ok{color:var(--okd);font-weight:700}
.hint.bad{color:#dc2626;font-weight:700}
.total-line{display:inline-flex;align-items:center;gap:8px;margin-top:16px;font-size:13px;font-weight:700;color:var(--pd);background:#eef2ff;border:1px solid #e0e7ff;padding:7px 14px;border-radius:20px}
.waiting{text-align:center;padding:60px 20px}
.waiting .ring{width:74px;height:74px;border-radius:50%;border:4px solid #e0e7ff;border-top-color:var(--p);margin:0 auto 22px;animation:spin 1.1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.waiting h2{font-size:22px;font-weight:800;margin-bottom:8px}
.waiting p{color:var(--muted);font-size:14px}
.side{padding:26px 24px;text-align:center;position:sticky;top:20px;background:linear-gradient(180deg,#ffffff,#fafaff)}
.side h3{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--pd);margin-bottom:16px}
.qr-box{background:#fff;border:1px solid var(--border);border-radius:16px;padding:14px;display:inline-block;box-shadow:0 12px 32px rgba(79,70,229,.14)}
.qr-box img{width:200px;height:200px;display:block}
.side .code{font-size:34px;font-weight:900;letter-spacing:8px;margin-top:16px;font-family:'Inter',monospace;color:var(--pd)}
.side .url{display:inline-block;font-size:13px;color:var(--muted);font-weight:700;word-break:break-all;margin-top:8px;background:#f1f5f9;border:1px dashed #cbd5e1;padding:6px 12px;border-radius:10px}
.side-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}
.side-stat{background:#f8fafc;border:1px solid var(--border);border-radius:14px;padding:12px 8px}
.side-stat .n{font-size:26px;font-weight:900;font-variant-numeric:tabular-nums;line-height:1.1}
.side-stat.devices .n{color:var(--pd)}
.side-stat.votes .n{color:var(--okd)}
.side-stat .l{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
@media(max-width:920px){.side{position:static;order:2}.qr-box img{width:150px;height:150px}}
.summary-q{border:1px solid var(--border);border-radius:16px;padding:20px 22px;margin-bottom:16px;background:#fdfdff}
.summary-q .sq-head{display:flex;gap:10px;align-items:baseline;margin-bottom:14px}
.summary-q .sq-num{font-size:12px;font-weight:900;color:#fff;background:linear-gradient(135deg,var(--p),var(--p2));padding:3px 9px;border-radius:8px;white-space:nowrap}
.summary-q .sq-text{font-size:16px;font-weight:700;line-height:1.35}
.footer{text-align:center;margin-top:34px;font-size:12px;color:#94a3b8;font-weight:600}
</style>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div class="brand"><span class="dot-logo">🗳</span><?php echo html_escape($company ?: 'Live Voting'); ?></div>
        <div id="badge"></div>
    </div>

    <h1 class="poll-title"><?php echo html_escape($poll->title); ?></h1>
    <?php if (!empty($poll->description)) { ?>
        <p class="poll-desc"><?php echo html_escape($poll->description); ?></p>
    <?php } ?>

    <div class="grid">
        <div class="card stage" id="stage"></div>

        <div class="card side">
            <h3>📱 Scan to vote</h3>
            <div class="qr-box"><img src="<?php echo site_url('voting/voting_public/qr/' . $poll->code); ?>" alt="Scan to vote"></div>
            <div class="code"><?php echo html_escape($poll->code); ?></div>
            <div class="url"><?php echo html_escape($link_disp); ?></div>
            <div class="side-stats">
                <div class="side-stat devices"><div class="n" id="stat-devices">0</div><div class="l">📱 Joined</div></div>
                <div class="side-stat votes"><div class="n" id="stat-votes">0</div><div class="l">🗳 Votes</div></div>
            </div>
        </div>
    </div>

    <div class="footer">Live voting powered by <?php echo html_escape($company ?: 'Clientcarex'); ?></div>
</div>

<script>
var CODE       = <?php echo json_encode($poll->code); ?>;
var STATE_URL  = <?php echo json_encode(site_url('voting/voting_public/state/' . $poll->code)); ?>;
var VOTE_URL   = <?php echo json_encode(site_url('voting/voting_public/vote')); ?>;
var CSRF_NAME  = <?php echo json_encode($csrf_name); ?>;
var CSRF_HASH  = <?php echo json_encode($csrf_hash); ?>;
var state      = <?php echo json_encode($state); ?>;

/* ── anonymous per-device voter token ── */
var VT = (function() {
    try {
        var t = localStorage.getItem('voting_vt');
        if (!t) {
            t = (window.crypto && crypto.randomUUID) ? crypto.randomUUID()
                : 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
            localStorage.setItem('voting_vt', t);
        }
        return t;
    } catch (e) {
        return 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    }
})();

/* my selections per question, survives refresh */
function mySel() {
    try { return JSON.parse(localStorage.getItem('voting_sel_' + CODE)) || {}; } catch (e) { return {}; }
}
function saveSel(qid, oid) {
    try {
        var s = mySel(); s[qid] = oid;
        localStorage.setItem('voting_sel_' + CODE, JSON.stringify(s));
    } catch (e) {}
}

var stage = document.getElementById('stage');
var badge = document.getElementById('badge');
var renderedQid = null, renderedMode = null, voting = false;

function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

function renderBadge() {
    if (state.status === 'ended') {
        badge.innerHTML = '<span class="live-badge ended"><span class="dot"></span>FINAL RESULTS</span>';
    } else {
        badge.innerHTML = '<span class="live-badge"><span class="dot"></span>LIVE</span>';
    }
}

function optionRow(o, selected, disabled, crown, locked) {
    return '<button type="button" class="opt' + (selected ? ' selected' : '') + (disabled ? ' disabled' : '') + (locked ? ' locked' : '') + '" data-oid="' + o.id + '">' +
        '<div class="fill" style="width:' + o.percent + '%"></div>' +
        '<div class="row">' +
        '<span class="tick">✓</span>' +
        '<span class="label">' + (crown ? '🏆 ' : '') + esc(o.label) + '</span>' +
        '<span class="cnt">' + o.votes + ' vote' + (o.votes === 1 ? '' : 's') + '</span>' +
        '<span class="pct">' + o.percent + '%</span>' +
        '</div></button>';
}

function renderQuestion(rebuild) {
    var q = state.active;
    var sel = mySel()[q.id] || null;

    if (rebuild) {
        var h = '<div class="q-meta"><span class="q-chip">QUESTION ' + q.number + ' / ' + q.of + '</span></div>';
        h += '<div class="q-text">' + esc(q.question) + '</div>';
        h += '<div id="opts">';
        q.options.forEach(function(o) { h += optionRow(o, sel === o.id, false, false, !!sel); });
        h += '</div>';
        h += '<div class="hint" id="hint"></div>';
        h += '<div class="total-line">📊 <span id="total">' + q.total + '</span>&nbsp;total votes — updating live</div>';
        stage.innerHTML = h;
        stage.querySelectorAll('.opt').forEach(function(btn) {
            btn.addEventListener('click', function() { vote(q.id, parseInt(btn.dataset.oid, 10)); });
        });
    } else {
        q.options.forEach(function(o) {
            var btn = stage.querySelector('.opt[data-oid="' + o.id + '"]');
            if (!btn) return;
            btn.querySelector('.fill').style.width = o.percent + '%';
            btn.querySelector('.pct').textContent = o.percent + '%';
            btn.querySelector('.cnt').textContent = o.votes + (o.votes === 1 ? ' vote' : ' votes');
            btn.classList.toggle('selected', sel === o.id);
            btn.classList.toggle('locked', !!sel);
        });
        var t = document.getElementById('total');
        if (t) t.textContent = q.total;
    }
    updateHint(sel);
}

function updateHint(sel) {
    var el = document.getElementById('hint');
    if (!el) return;
    if (sel) {
        el.className = 'hint ok';
        el.textContent = '✓ Your vote is in — votes are final and can’t be changed';
    } else {
        el.className = 'hint';
        el.textContent = 'Tap an option to cast your vote — choose carefully, votes are final';
    }
}

function renderWaiting(message, sub) {
    stage.innerHTML = '<div class="waiting"><div class="ring"></div><h2>' + esc(message) + '</h2><p>' + esc(sub) + '</p></div>';
}

function renderSummary() {
    var h = '<div class="q-meta"><span class="q-chip">🏁 FINAL RESULTS</span></div>';
    if (!state.summary || !state.summary.length) {
        h += '<div class="waiting"><h2>Voting has ended</h2><p>Thanks for participating!</p></div>';
    } else {
        state.summary.forEach(function(q) {
            var max = 0;
            q.options.forEach(function(o) { if (o.votes > max) max = o.votes; });
            h += '<div class="summary-q">';
            h += '<div class="sq-head"><span class="sq-num">Q' + q.number + '</span><span class="sq-text">' + esc(q.question) + '</span></div>';
            q.options.forEach(function(o) {
                var winner = max > 0 && o.votes === max;
                h += optionRow(o, winner, true, winner);
            });
            h += '<div class="total-line">📊 ' + q.total + ' total votes</div>';
            h += '</div>';
        });
    }
    stage.innerHTML = h;
}

function renderStats() {
    var d = document.getElementById('stat-devices');
    var v = document.getElementById('stat-votes');
    if (d && typeof state.devices !== 'undefined') d.textContent = state.devices;
    if (v && typeof state.votes !== 'undefined') v.textContent = state.votes;
}

function render() {
    renderBadge();
    renderStats();

    if (state.status === 'ended') {
        if (renderedMode !== 'ended') { renderSummary(); renderedMode = 'ended'; renderedQid = null; }
        return;
    }

    if (state.status === 'live' && state.active) {
        var rebuild = renderedMode !== 'question' || renderedQid !== state.active.id;
        renderQuestion(rebuild);
        renderedMode = 'question';
        renderedQid = state.active.id;
        return;
    }

    if (renderedMode !== 'waiting') {
        renderWaiting('Get ready…', 'The host will open the next question shortly. This page updates automatically.');
        renderedMode = 'waiting';
        renderedQid = null;
    }
}

function vote(qid, oid) {
    if (voting || state.status !== 'live' || !state.active || state.active.id !== qid) return;
    if (mySel()[qid]) return; // votes are final — already voted on this question

    voting = true;
    saveSel(qid, oid);
    renderQuestion(false); // optimistic highlight

    var fd = new FormData();
    fd.append('code', CODE);
    fd.append('question_id', qid);
    fd.append('option_id', oid);
    fd.append('vt', VT);
    fd.append('full', '1'); // screen page gets results back
    if (CSRF_NAME) fd.append(CSRF_NAME, CSRF_HASH);

    fetch(VOTE_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res && !res.success) {
                // undo the optimistic highlight; explain (e.g. names are
                // collected — voting happens on the voter link instead)
                saveSel(qid, null);
                renderQuestion(false);
                if (res.message) {
                    var el = document.getElementById('hint');
                    if (el) { el.className = 'hint bad'; el.textContent = res.message + (res.name_required ? ' Scan the QR to vote from your phone.' : ''); }
                }
                return;
            }
            if (res && res.state) { state = res.state; render(); }
        })
        .catch(function() {})
        .finally(function() { voting = false; });
}

/* ── live polling ── */
setInterval(function() {
    fetch(STATE_URL, { cache: 'no-store', credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) { if (res && res.status) { state = res; render(); } })
        .catch(function() {});
}, 2500);

render();
</script>
</body>
</html>
