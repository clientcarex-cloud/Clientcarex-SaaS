<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content-page tw-p-0">
        <div style="height: calc(100vh - 50px); position: relative; display: flex; flex-direction: column;">

            <?php if (!empty($show_support_pin)) : ?>
            <!-- Support PIN — read it out to the provider's support agent to grant
                 them temporary "Tenant Access" on their console. -->
            <div class="psp-bar" id="psp-bar">
                <div class="psp-left">
                    <span class="psp-ico"><i class="fa fa-shield-halved" aria-hidden="true"></i></span>
                    <div class="psp-copy">
                        <strong>Support PIN</strong>
                        <span id="psp-hint">Share this PIN with your support agent to allow temporary access to your account settings.</span>
                    </div>
                </div>
                <div class="psp-right">
                    <span class="psp-verified" id="psp-verified" style="display:none;"><i class="fa fa-user-check"></i> <span id="psp-verified-txt"></span></span>
                    <span class="psp-pin" id="psp-pin" style="display:none;"></span>
                    <span class="psp-exp" id="psp-exp" style="display:none;"></span>
                    <button type="button" class="btn btn-primary btn-sm" id="psp-generate">
                        <i class="fa fa-key"></i> <span id="psp-generate-txt">Generate PIN</span>
                    </button>
                </div>
            </div>
            <style>
                .psp-bar { position:relative; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
                           padding:12px 18px; margin:12px 14px 4px; border-radius:14px;
                           background:linear-gradient(120deg,#eef2ff 0%,#f5f3ff 50%,#faf5ff 100%);
                           border:1.5px solid #c7d2fe; animation:pspGlow 2.6s ease-in-out infinite; }
                @keyframes pspGlow {
                    0%,100% { border-color:#c7d2fe; }
                    50%     { border-color:#818cf8; }
                }
                .psp-bar::before { content:''; position:absolute; left:0; top:10px; bottom:10px; width:4px;
                                   border-radius:0 4px 4px 0; background:linear-gradient(#4f46e5,#7c3aed); }
                .psp-left { display:flex; align-items:center; gap:12px; min-width:0; padding-left:6px; }
                .psp-ico { width:38px; height:38px; border-radius:11px; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff;
                           display:inline-flex; align-items:center; justify-content:center; font-size:17px; flex:0 0 auto; }
                .psp-copy { display:flex; flex-direction:column; line-height:1.3; min-width:0; }
                .psp-copy strong { font-size:14px; color:#312e81; letter-spacing:.2px; display:inline-flex; align-items:center; gap:8px; }
                .psp-copy strong::after { content:'Action needed'; font-size:9.5px; font-weight:800; letter-spacing:.6px; text-transform:uppercase;
                                          color:#fff; background:linear-gradient(135deg,#4f46e5,#7c3aed); padding:2px 8px; border-radius:999px; }
                .psp-copy span { font-size:12px; color:#4c4f69; }
                .psp-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
                .psp-pin { font-size:22px; font-weight:800; letter-spacing:7px; color:#312e81; background:#fff;
                           border:1.5px dashed #a5b4fc; border-radius:10px; padding:5px 16px; font-family:monospace; }
                .psp-exp { font-size:11.5px; color:#6d28d9; font-weight:700; }
                .psp-verified { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700;
                                color:#166534; background:#dcfce7; border-radius:999px; padding:5px 12px; }
            </style>
            <?php endif; ?>

            <div style="flex: 1 1 auto; position: relative;">
                <iframe id="psf-frame" class="tw-w-full tw-h-full" style="border:none; position:absolute; inset:0;" src="<?= $url; ?>"></iframe>
                <?php if (!empty($newtab_url)) : ?>
                <!-- Shown only when the embed doesn't render (e.g. a stray
                     X-Frame-Options/CSP from a layer outside the app blocks the
                     cross-subdomain iframe) so the portal stays reachable. -->
                <div id="psf-blocked" style="display:none; position:absolute; left:50%; bottom:24px; transform:translateX(-50%); align-items:center; gap:12px; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 8px 24px rgba(15,23,42,.12); padding:10px 16px; z-index:5; max-width:calc(100% - 32px);">
                    <span style="font-size:13px; color:#334155;"><?= _l('perfex_saas_portal_frame_blocked'); ?></span>
                    <a href="<?= $newtab_url; ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm" style="white-space:nowrap;">
                        <i class="fa fa-arrow-up-right-from-square"></i> <?= _l('perfex_saas_portal_open_new_tab'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
var psfBridgeLoaded = false;

window.onmessage = function(event) {
    if (event.data.message === "closedBridge") {
        window.location.href = window.location.href.split('?')[0] + '?paying_outstanding';
    }

    if (event.data.message === "home") {
        window.location.href = "<?= perfex_saas_default_base_url('?subscription'); ?>";
    }

    if (event.data.message === "openInParent") {
        window.location.href = event.data.value;
    }

    // The framed master portal announces itself once rendered (see
    // client_tenant_bridge.php). Until that arrives we cannot tell a healthy
    // cross-origin frame from one the browser refused to render.
    if (event.data.message === "bridgeLoaded") {
        psfBridgeLoaded = true;
        var blocked = document.getElementById('psf-blocked');
        if (blocked) blocked.style.display = 'none';
    }
};

// If the portal never announces itself, the embed was almost certainly blocked
// (stray X-Frame-Options/CSP, challenge page, network failure) — surface the
// open-in-new-tab fallback instead of a dead grey rectangle.
setTimeout(function () {
    var blocked = document.getElementById('psf-blocked');
    if (blocked && !psfBridgeLoaded) blocked.style.display = 'flex';
}, 6000);
</script>

<?php if (!empty($show_support_pin)) : ?>
<script>
(function () {
    'use strict';

    var api      = admin_url + 'billing/support_pin';
    var pinEl    = document.getElementById('psp-pin');
    var expEl    = document.getElementById('psp-exp');
    var verEl    = document.getElementById('psp-verified');
    var verTxt   = document.getElementById('psp-verified-txt');
    var genBtn   = document.getElementById('psp-generate');
    var genTxt   = document.getElementById('psp-generate-txt');
    var expiresAt = 0, tickTimer = null;

    function fmt(sec) {
        sec = Math.max(0, sec);
        return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
    }

    function tick() {
        var left = Math.round(expiresAt - Date.now() / 1000);
        if (left <= 0) {
            render({ success: true, has_pin: false, verified: false });
            return;
        }
        expEl.textContent = 'expires in ' + fmt(left);
    }

    function render(d) {
        if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }

        if (d && d.has_pin) {
            pinEl.textContent = d.pin;
            pinEl.style.display = '';
            expEl.style.display = '';
            genTxt.textContent = 'Regenerate';
            expiresAt = Date.now() / 1000 + d.expires_in;
            tick();
            tickTimer = setInterval(tick, 1000);
        } else {
            pinEl.style.display = 'none';
            expEl.style.display = 'none';
            genTxt.textContent = 'Generate PIN';
        }

        if (d && d.verified) {
            verTxt.textContent = (d.verified_by_name ? d.verified_by_name + ' has access' : 'Agent access active')
                + ' · ' + Math.max(1, Math.ceil(d.access_remaining / 60)) + 'm left';
            verEl.style.display = '';
        } else {
            verEl.style.display = 'none';
        }
    }

    function status() {
        fetch(api, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
        .then(function (r) { return r.json(); })
        .then(render)
        .catch(function () {});
    }

    genBtn.addEventListener('click', function () {
        genBtn.disabled = true;

        var body = new URLSearchParams();
        body.append('action', 'generate');
        if (typeof csrfData !== 'undefined') {
            body.append(csrfData.token_name, csrfData.hash);
        }

        fetch(api, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            genBtn.disabled = false;
            if (d && d.success) { render(d); }
            else if (typeof alert_float === 'function') { alert_float('danger', (d && d.message) || 'Could not generate PIN'); }
        })
        .catch(function () { genBtn.disabled = false; });
    });

    status();
    // Keep the "agent has access" badge live while the page is open.
    setInterval(status, 30000);
})();
</script>
<?php endif; ?>

<?php init_tail(); ?>
</body>

</html>
