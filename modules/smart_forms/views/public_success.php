<?php defined('BASEPATH') or exit('No direct script access allowed');

$company = get_option('companyname');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--p:#6366f1;--pd:#4f46e5;--ok:#10b981;--warn:#f59e0b;--text:#1e293b;--muted:#64748b;--border:#e2e8f0}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 40%,#f1f5f9 100%);min-height:100vh;color:var(--text);display:flex;align-items:center;justify-content:center;padding:24px 16px}
.card{background:#fff;border:1px solid var(--border);border-radius:20px;box-shadow:0 20px 50px rgba(15,23,42,.1);max-width:520px;width:100%;padding:44px 36px;text-align:center}
.check{width:84px;height:84px;margin:0 auto 22px;border-radius:50%;background:#ecfdf5;display:flex;align-items:center;justify-content:center;animation:pop .5s cubic-bezier(.175,.885,.32,1.4)}
.check i{font-size:40px;color:var(--ok)}
@keyframes pop{0%{transform:scale(0)}100%{transform:scale(1)}}
h1{font-size:24px;font-weight:800;margin-bottom:10px}
.msg{font-size:14.5px;color:var(--muted);line-height:1.65}
.fb{margin-top:30px;border-top:1px solid var(--border);padding-top:26px}
.fb h3{font-size:15px;font-weight:700;margin-bottom:16px}
.emojis{display:flex;justify-content:center;gap:12px;margin-bottom:16px}
.emoji{width:54px;height:54px;border:2px solid var(--border);border-radius:50%;background:#fff;font-size:26px;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;filter:grayscale(.6)}
.emoji:hover{transform:scale(1.15);filter:none;border-color:var(--p)}
.emoji.sel{transform:scale(1.15);filter:none;border-color:var(--p);background:#eef2ff;box-shadow:0 0 0 4px rgba(99,102,241,.15)}
.fb textarea{width:100%;border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;font-size:13.5px;font-family:inherit;min-height:70px;resize:vertical;outline:none;display:none;margin-bottom:12px}
.fb textarea:focus{border-color:var(--p);box-shadow:0 0 0 4px rgba(99,102,241,.12)}
.fb-send{display:none;border:none;border-radius:12px;padding:12px 28px;background:linear-gradient(135deg,var(--p),var(--pd));color:#fff;font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 8px 20px rgba(99,102,241,.3)}
.fb-send:disabled{opacity:.6;cursor:not-allowed}
.fb-done{display:none;color:var(--ok);font-weight:700;font-size:14.5px;padding:8px 0}
.redirect-note{margin-top:22px;font-size:12.5px;color:#94a3b8}
.footer{position:fixed;bottom:14px;left:0;right:0;text-align:center;font-size:12px;color:#94a3b8}
</style>
</head>
<body>
<div class="card">
    <div class="check"><i class="fa-solid fa-check"></i></div>
    <h1><?php echo html_escape($title); ?></h1>
    <p class="msg"><?php echo nl2br(html_escape($form->success_message ?: 'Your response has been recorded successfully.')); ?></p>

    <?php if (!empty($form->collect_feedback)) { ?>
        <div class="fb" id="sf-fb">
            <h3><?php echo html_escape($form->feedback_prompt ?: 'How was your experience filling this form?'); ?></h3>
            <div class="emojis" id="sf-emojis">
                <button class="emoji" data-rating="1" title="Very poor">😞</button>
                <button class="emoji" data-rating="2" title="Poor">😕</button>
                <button class="emoji" data-rating="3" title="Okay">😐</button>
                <button class="emoji" data-rating="4" title="Good">🙂</button>
                <button class="emoji" data-rating="5" title="Excellent">🤩</button>
            </div>
            <textarea id="sf-fb-comment" maxlength="500" placeholder="Anything we could do better? (optional)"></textarea>
            <button class="fb-send" id="sf-fb-send">Send feedback</button>
            <div class="fb-done" id="sf-fb-done"><i class="fa-solid fa-heart" style="margin-right:6px"></i>Thanks for the feedback!</div>
        </div>
    <?php } ?>

    <?php if (!empty($form->redirect_url)) { ?>
        <div class="redirect-note" id="sf-redirect-note"></div>
    <?php } ?>
</div>
<div class="footer"><?php echo html_escape($company ?: ''); ?></div>

<script>
(function () {
    var rating = 0;
    var sent = false;
    var endpoint = <?php echo json_encode(site_url('smart_forms/smart_forms_public/quick_feedback')); ?>;
    var payloadBase = {
        token: <?php echo json_encode($form->share_token); ?>,
        sid: <?php echo json_encode((string) $submission_id); ?>,
        sig: <?php echo json_encode($sig); ?>
    };
    var redirectUrl = <?php echo json_encode(!empty($form->redirect_url) ? $form->redirect_url : ''); ?>;
    var hasFeedback = <?php echo !empty($form->collect_feedback) ? 'true' : 'false'; ?>;

    function startRedirect(delay) {
        if (!redirectUrl) return;
        var note = document.getElementById('sf-redirect-note');
        var secs = Math.round(delay / 1000);
        if (note) {
            var tick = setInterval(function () {
                secs--;
                if (secs <= 0) { clearInterval(tick); return; }
                note.textContent = 'Redirecting in ' + secs + 's…';
            }, 1000);
            note.textContent = 'Redirecting in ' + secs + 's…';
        }
        setTimeout(function () { window.location.href = redirectUrl; }, delay);
    }

    if (hasFeedback) {
        document.querySelectorAll('#sf-emojis .emoji').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#sf-emojis .emoji').forEach(function (b) { b.classList.remove('sel'); });
                btn.classList.add('sel');
                rating = parseInt(btn.getAttribute('data-rating'), 10);
                document.getElementById('sf-fb-comment').style.display = 'block';
                document.getElementById('sf-fb-send').style.display = 'inline-block';
            });
        });

        document.getElementById('sf-fb-send').addEventListener('click', function () {
            if (sent || !rating) return;
            var btn = this;
            btn.disabled = true;

            var fd = new FormData();
            Object.keys(payloadBase).forEach(function (k) { fd.append(k, payloadBase[k]); });
            fd.append('rating', rating);
            fd.append('comment', document.getElementById('sf-fb-comment').value);

            fetch(endpoint, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .catch(function () { return { success: true }; })
                .then(function () {
                    sent = true;
                    document.getElementById('sf-emojis').style.pointerEvents = 'none';
                    document.getElementById('sf-fb-comment').style.display = 'none';
                    btn.style.display = 'none';
                    document.getElementById('sf-fb-done').style.display = 'block';
                    startRedirect(2500);
                });
        });

        // If the user never rates, still honor a configured redirect after a while
        startRedirectIfIdle();
    } else {
        startRedirect(3000);
    }

    function startRedirectIfIdle() {
        if (!redirectUrl) return;
        setTimeout(function () { if (!rating) startRedirect(3000); }, 20000);
    }
})();
</script>
</body>
</html>
