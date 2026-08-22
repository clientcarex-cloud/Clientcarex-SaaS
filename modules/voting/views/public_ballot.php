<?php defined('BASEPATH') or exit('No direct script access allowed');

$CI        = &get_instance();
$company   = get_option('companyname');
$csrf_name = $CI->security->get_csrf_token_name();
$csrf_hash = $CI->security->get_csrf_hash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($poll->title); ?> — Vote</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--p:#6366f1;--pd:#4f46e5;--p2:#8b5cf6;--ok:#10b981;--okd:#059669;--text:#0f172a;--muted:#64748b;--border:#e2e8f0}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:radial-gradient(700px 380px at 85% -8%,#e0e7ff 0%,transparent 60%),linear-gradient(160deg,#eef2ff 0%,#f8fafc 45%,#f1f5f9 100%);background-attachment:fixed;min-height:100vh;color:var(--text);padding:20px 16px 46px}
.wrap{max-width:560px;margin:0 auto}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.brand{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;color:var(--muted);letter-spacing:.6px;text-transform:uppercase}
.brand .dot-logo{width:24px;height:24px;border-radius:7px;background:linear-gradient(135deg,var(--p),var(--p2));display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:12px;box-shadow:0 4px 10px rgba(99,102,241,.35)}
.live-badge{display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:20px;font-size:11px;font-weight:800;letter-spacing:1px;background:#fff;color:#059669;border:1px solid #a7f3d0;box-shadow:0 2px 8px rgba(16,185,129,.15)}
.live-badge .dot{width:7px;height:7px;border-radius:50%;background:#10b981;animation:pulse 1.2s infinite}
.live-badge.ended{color:var(--pd);border-color:#c7d2fe;box-shadow:0 2px 8px rgba(99,102,241,.15)}
.live-badge.ended .dot{background:var(--p);animation:none}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}
h1.poll-title{font-size:clamp(19px,5vw,24px);font-weight:900;line-height:1.28;margin-bottom:4px;letter-spacing:-.4px}
.poll-desc{font-size:13px;color:var(--muted);line-height:1.5}
.card{background:#fff;border:1px solid var(--border);border-radius:20px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 18px 44px rgba(15,23,42,.09);margin-top:18px;position:relative;overflow:hidden;padding:clamp(20px,5vw,30px)}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,var(--p),var(--p2),#ec4899)}
.q-chip{display:inline-block;background:linear-gradient(135deg,#eef2ff,#f5f3ff);color:var(--pd);padding:5px 13px;border-radius:16px;font-size:11px;font-weight:800;letter-spacing:.5px;border:1px solid #e0e7ff;margin-bottom:12px}
.q-text{font-size:clamp(18px,4.6vw,23px);font-weight:800;line-height:1.32;margin-bottom:20px;letter-spacing:-.3px}
.opt{position:relative;display:flex;align-items:center;gap:12px;width:100%;text-align:left;border:1.5px solid var(--border);background:#f8fafc;border-radius:14px;margin-bottom:11px;cursor:pointer;padding:16px 16px;transition:border-color .2s,box-shadow .2s,transform .1s;-webkit-tap-highlight-color:transparent}
.opt:hover{border-color:#a5b4fc;box-shadow:0 4px 14px rgba(99,102,241,.14)}
.opt:active{transform:scale(.985)}
.opt.selected{border-color:var(--ok);background:#f0fdf9;box-shadow:0 0 0 3px rgba(16,185,129,.15)}
.opt.locked{cursor:default;opacity:.55}
.opt.locked:hover{border-color:var(--border);box-shadow:none}
.opt.locked:active{transform:none}
.opt.selected.locked{opacity:1;cursor:default}
.opt.selected.locked:hover{border-color:var(--ok);box-shadow:0 0 0 3px rgba(16,185,129,.15)}
.opt .tick{width:24px;height:24px;border-radius:50%;border:2px solid #cbd5e1;background:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;color:transparent;transition:all .15s}
.opt.selected .tick{background:var(--ok);border-color:var(--ok);color:#fff;box-shadow:0 2px 6px rgba(16,185,129,.4)}
.opt .label{flex:1;font-size:16px;font-weight:600;line-height:1.35}
.hint{font-size:13px;color:var(--muted);margin-top:4px;min-height:18px}
.hint.ok{color:var(--okd);font-weight:700}
.center{text-align:center;padding:44px 14px}
.center .ring{width:66px;height:66px;border-radius:50%;border:4px solid #e0e7ff;border-top-color:var(--p);margin:0 auto 20px;animation:spin 1.1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.center .big{width:76px;height:76px;border-radius:50%;background:#ecfdf5;border:2px solid #a7f3d0;display:flex;align-items:center;justify-content:center;font-size:34px;margin:0 auto 18px}
.center h2{font-size:20px;font-weight:800;margin-bottom:8px}
.center p{color:var(--muted);font-size:14px;line-height:1.55}
.gate{text-align:center;padding:26px 6px}
.gate .big{width:70px;height:70px;border-radius:50%;background:#eef2ff;border:2px solid #c7d2fe;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px}
.gate h2{font-size:19px;font-weight:800;margin-bottom:6px}
.gate p{color:var(--muted);font-size:13px;margin-bottom:18px}
.gate input{width:100%;max-width:320px;padding:13px 16px;border:1.5px solid var(--border);border-radius:12px;font-size:16px;font-family:inherit;color:var(--text);outline:none;text-align:center;transition:border .15s}
.gate input:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.gate button{display:inline-flex;align-items:center;gap:8px;margin-top:14px;padding:13px 30px;border:none;border-radius:12px;font-size:15px;font-weight:700;font-family:inherit;color:#fff;background:linear-gradient(135deg,var(--p),var(--pd));cursor:pointer;box-shadow:0 6px 16px rgba(99,102,241,.35);transition:transform .1s}
.gate button:active{transform:scale(.97)}
.gate .err{color:#dc2626;font-size:12px;font-weight:700;margin-top:8px;min-height:16px}
.who{display:flex;align-items:center;justify-content:center;gap:6px;font-size:12px;color:var(--muted);font-weight:600;margin-top:14px}
.who a{color:var(--pd);font-weight:700;text-decoration:none}
.footer{text-align:center;margin-top:26px;font-size:12px;color:#94a3b8;font-weight:600}
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

    <div class="card" id="stage"></div>

    <div class="footer">Live voting powered by <?php echo html_escape($company ?: 'Clientcarex'); ?></div>
</div>

<script>
var CODE       = <?php echo json_encode($poll->code); ?>;
var STATE_URL  = <?php echo json_encode(site_url('voting/voting_public/bstate/' . $poll->code)); ?>;
var VOTE_URL   = <?php echo json_encode(site_url('voting/voting_public/vote')); ?>;
var CSRF_NAME  = <?php echo json_encode($csrf_name); ?>;
var CSRF_HASH  = <?php echo json_encode($csrf_hash); ?>;
var COLLECT_NAMES = <?php echo !empty($poll->collect_names) ? 'true' : 'false'; ?>;
var state      = <?php echo json_encode($state); ?>;

/* ── anonymous per-device voter token (shared with the screen page) ── */
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

function mySel() {
    try { return JSON.parse(localStorage.getItem('voting_sel_' + CODE)) || {}; } catch (e) { return {}; }
}
function saveSel(qid, oid) {
    try {
        var s = mySel(); s[qid] = oid;
        localStorage.setItem('voting_sel_' + CODE, JSON.stringify(s));
    } catch (e) {}
}
function myName() {
    try { return localStorage.getItem('voting_name') || ''; } catch (e) { return ''; }
}
function saveName(n) {
    try { localStorage.setItem('voting_name', n); } catch (e) {}
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
        badge.innerHTML = '<span class="live-badge ended"><span class="dot"></span>ENDED</span>';
    } else {
        badge.innerHTML = '<span class="live-badge"><span class="dot"></span>LIVE</span>';
    }
}

function renderNameGate(focus) {
    stage.innerHTML = '<div class="gate"><div class="big">👋</div>' +
        '<h2>What\'s your name?</h2>' +
        '<p>The host is collecting names for this voting.</p>' +
        '<input type="text" id="name-input" maxlength="120" placeholder="Your name" autocomplete="name" value="' + esc(myName()) + '">' +
        '<div class="err" id="name-err"></div>' +
        '<button type="button" id="name-go">Join voting →</button></div>';
    var input = document.getElementById('name-input');
    var go = function() {
        var n = input.value.trim();
        if (!n) { document.getElementById('name-err').textContent = 'Please enter your name to continue.'; return; }
        saveName(n);
        renderedMode = null; // force rebuild of whatever state comes next
        render();
    };
    document.getElementById('name-go').addEventListener('click', go);
    input.addEventListener('keydown', function(e) { if (e.key === 'Enter') go(); });
    if (focus) input.focus();
}

function renderQuestion(rebuild) {
    var q = state.active;
    var sel = mySel()[q.id] || null;

    if (rebuild) {
        var h = '<span class="q-chip">QUESTION ' + q.number + ' / ' + q.of + '</span>';
        h += '<div class="q-text">' + esc(q.question) + '</div>';
        q.options.forEach(function(o) {
            var cls = 'opt' + (sel === o.id ? ' selected' : '') + (sel ? ' locked' : '');
            h += '<button type="button" class="' + cls + '" data-oid="' + o.id + '">' +
                '<span class="tick">✓</span><span class="label">' + esc(o.label) + '</span></button>';
        });
        h += '<div class="hint" id="hint"></div>';
        if (COLLECT_NAMES) {
            h += '<div class="who">Voting as <b>' + esc(myName()) + '</b> · <a href="#" id="who-change">change</a></div>';
        }
        stage.innerHTML = h;
        stage.querySelectorAll('.opt').forEach(function(btn) {
            btn.addEventListener('click', function() { vote(q.id, parseInt(btn.dataset.oid, 10)); });
        });
        var chg = document.getElementById('who-change');
        if (chg) chg.addEventListener('click', function(e) { e.preventDefault(); renderedMode = 'gate'; renderNameGate(true); });
    } else {
        q.options.forEach(function(o) {
            var btn = stage.querySelector('.opt[data-oid="' + o.id + '"]');
            if (!btn) return;
            btn.classList.toggle('selected', sel === o.id);
            btn.classList.toggle('locked', !!sel);
        });
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

function renderWaiting() {
    stage.innerHTML = '<div class="center"><div class="ring"></div><h2>Get ready…</h2>' +
        '<p>The host will open the next question shortly.<br>This page updates automatically.</p></div>';
}

function renderEnded() {
    stage.innerHTML = '<div class="center"><div class="big">🎉</div><h2>Voting has ended</h2>' +
        '<p>Thank you for participating!<br>Watch the presenter’s screen for the final results.</p></div>';
}

function render() {
    renderBadge();

    if (state.status === 'ended') {
        if (renderedMode !== 'ended') { renderEnded(); renderedMode = 'ended'; renderedQid = null; }
        return;
    }

    // Name gate first — voters must identify themselves before anything else
    if (COLLECT_NAMES && !myName()) {
        if (renderedMode !== 'gate') { renderNameGate(false); renderedMode = 'gate'; renderedQid = null; }
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
        renderWaiting();
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
    if (COLLECT_NAMES) fd.append('voter_name', myName());
    if (CSRF_NAME) fd.append(CSRF_NAME, CSRF_HASH);

    fetch(VOTE_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res && !res.success && res.name_required) {
                renderedMode = 'gate';
                renderNameGate(true);
                return;
            }
            if (res && res.state && res.state.status) { state = res.state; render(); }
        })
        .catch(function() {})
        .finally(function() { voting = false; });
}

/* ── live polling (result-free ballot state; vt rides along so the
     screen's "devices joined" counter knows this device is here) ── */
setInterval(function() {
    fetch(STATE_URL + '?vt=' + encodeURIComponent(VT), { cache: 'no-store', credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) { if (res && res.status) { state = res; render(); } })
        .catch(function() {});
}, 2500);

// Register this device immediately (don't wait for the first poll tick)
fetch(STATE_URL + '?vt=' + encodeURIComponent(VT), { cache: 'no-store', credentials: 'same-origin' }).catch(function() {});

render();
</script>
</body>
</html>
