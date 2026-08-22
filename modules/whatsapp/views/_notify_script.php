<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Live "new WhatsApp message" notifier, injected on EVERY admin page so an
 * inbound message is noticed without the Inbox tab being open.
 *
 * Three surfaces, each individually switchable in WhatsApp → Settings:
 *   toast    — card stack in the top-right, click to open the thread
 *   desktop  — OS notification (needs the one-time browser permission)
 *   sound    — short WebAudio chime (no asset file, so nothing to 404)
 *
 * Seed items are encoded defensively: a stray byte in a message body must
 * never break the injected script and take the whole admin page with it.
 */
$items_json = json_encode(
    array_values($initial_items ?? []),
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
if ($items_json === false) {
    $items_json = '[]';
}
?>
<style>
.wanotif-menu-badge {
    display: inline-block;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    margin-left: 6px;
    border-radius: 999px;
    background: #25d366;
    color: #05341a;
    font-size: 11px;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
    vertical-align: middle;
}
.wanotif-wrap {
    position: fixed;
    top: 74px;
    right: 22px;
    z-index: 2147483000;
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 350px;
    max-width: calc(100vw - 44px);
    pointer-events: none;
}
.wanotif-toast {
    pointer-events: auto;
    position: relative;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e7e9ee;
    border-left: 4px solid #25d366;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .16);
    padding: 13px 14px 15px;
    cursor: pointer;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
    transform: translateX(24px);
    opacity: 0;
    transition: transform .28s cubic-bezier(.22,1,.36,1), opacity .28s;
}
.wanotif-toast.in { transform: translateX(0); opacity: 1; }
.wanotif-toast.out { transform: translateX(24px); opacity: 0; }
.wanotif-toast:hover { box-shadow: 0 16px 38px rgba(15, 23, 42, .22); }
.wanotif-head { display: flex; align-items: center; gap: 9px; margin-bottom: 6px; }
.wanotif-ico {
    flex: 0 0 auto; width: 30px; height: 30px; border-radius: 9px;
    background: #e7f9ee; color: #128c7e;
    display: flex; align-items: center; justify-content: center; font-size: 15px;
}
.wanotif-title { font-size: 12px; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; color: #128c7e; line-height: 1.2; }
.wanotif-time { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.wanotif-close { margin-left: auto; flex: 0 0 auto; width: 22px; height: 22px; border-radius: 6px; border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 14px; line-height: 1; }
.wanotif-close:hover { background: #f1f5f9; color: #475569; }
.wanotif-from { font-size: 14px; font-weight: 600; color: #0f172a; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.wanotif-phone { font-size: 12px; color: #128c7e; font-weight: 600; margin-top: 1px; }
.wanotif-snippet { font-size: 12.5px; color: #64748b; line-height: 1.45; margin-top: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.wanotif-cta { font-size: 11.5px; color: #128c7e; font-weight: 600; margin-top: 7px; }
.wanotif-bar { position: absolute; left: 0; bottom: 0; height: 3px; width: 100%; background: #25d366; opacity: .55; transform-origin: left center; animation: wanotifBar 6s linear forwards; }
.wanotif-toast.paused .wanotif-bar { animation-play-state: paused; }
@keyframes wanotifBar { from { transform: scaleX(1); } to { transform: scaleX(0); } }
</style>
<script>
(function () {
    var ENDPOINT   = "<?php echo $endpoint; ?>";
    var INBOX_URL  = "<?php echo $inbox_url; ?>";
    var INITIAL    = <?php echo (int) $initial; ?>;
    var INITIAL_ITEMS = <?php echo $items_json; ?>;
    var OPT = {
        toast:   <?php echo !empty($settings['toast']) ? 'true' : 'false'; ?>,
        desktop: <?php echo !empty($settings['desktop']) ? 'true' : 'false'; ?>,
        sound:   <?php echo !empty($settings['sound']) ? 'true' : 'false'; ?>
    };
    var SEEN_KEY   = 'wanotif_seen';
    var POLL_MS    = 15000;
    var DISMISS_MS = 6000;
    var MAX_TOASTS = 3;

    var L = {
        title:   "<?php echo addslashes(_l('wapi_notify_title')); ?>",
        newMsg:  "<?php echo addslashes(_l('wapi_notify_new_message_short')); ?>",
        view:    "<?php echo addslashes(_l('wapi_notify_open_chat')); ?>",
        more:    "<?php echo addslashes(_l('wapi_notify_more')); ?>"
    };

    function DBG() { try { console.log.apply(console, ['[whatsapp-notify]'].concat([].slice.call(arguments))); } catch (e) {} }
    function threadUrl(phone) { return INBOX_URL + '?tab=inbox&thread=' + encodeURIComponent(phone); }

    /* ── Badge on the sidebar WhatsApp item ─────────────────────────── */
    function menuLink() {
        // aside.php renders the item as li.menu-item-<slug>; fall back to the
        // href so a themed or re-ordered sidebar still gets the badge.
        return document.querySelector('.menu-item-whatsapp-module > a')
            || document.querySelector('#whatsapp-module a')
            || document.querySelector('#side-menu a[href="' + INBOX_URL + '"]');
    }
    function setBadge(count) {
        var link = menuLink();
        if (!link) { return; }
        var b = link.querySelector('.wanotif-menu-badge');
        if (!b) {
            b = document.createElement('span');
            b.className = 'wanotif-menu-badge';
            link.appendChild(b);
        }
        b.textContent = count > 99 ? '99+' : count;
        b.style.display = count > 0 ? '' : 'none';
    }

    /* ── Chime (WebAudio — nothing to download, nothing to 404) ─────── */
    var audioCtx = null;
    function chime() {
        if (!OPT.sound) { return; }
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { return; }
            if (!audioCtx) { audioCtx = new Ctx(); }
            if (audioCtx.state === 'suspended') { audioCtx.resume(); }
            // Two short rising notes — recognisable without being shrill.
            [[880, 0], [1174.66, 0.13]].forEach(function (n) {
                var osc = audioCtx.createOscillator();
                var gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = n[0];
                var t0 = audioCtx.currentTime + n[1];
                gain.gain.setValueAtTime(0.0001, t0);
                gain.gain.exponentialRampToValueAtTime(0.22, t0 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.22);
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.start(t0); osc.stop(t0 + 0.24);
            });
        } catch (e) { DBG('chime failed', e); }
    }

    /* ── Toasts ─────────────────────────────────────────────────────── */
    function wrap() {
        var w = document.querySelector('.wanotif-wrap');
        if (!w) { w = document.createElement('div'); w.className = 'wanotif-wrap'; document.body.appendChild(w); }
        return w;
    }
    function dismiss(el) {
        if (!el || el._gone) { return; }
        el._gone = true;
        el.classList.add('out');
        setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 300);
    }
    function buildToast(opts) {
        var t = document.createElement('div');
        t.className = 'wanotif-toast';

        var head = document.createElement('div');
        head.className = 'wanotif-head';
        var ico = document.createElement('div');
        ico.className = 'wanotif-ico';
        ico.innerHTML = '<i class="fa-brands fa-whatsapp"></i>';
        var meta = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'wanotif-title';
        title.textContent = opts.title || L.newMsg;
        meta.appendChild(title);
        if (opts.time) { var tm = document.createElement('div'); tm.className = 'wanotif-time'; tm.textContent = opts.time; meta.appendChild(tm); }
        var close = document.createElement('button');
        close.className = 'wanotif-close'; close.type = 'button'; close.innerHTML = '&times;';
        close.addEventListener('click', function (e) { e.stopPropagation(); dismiss(t); });
        head.appendChild(ico); head.appendChild(meta); head.appendChild(close);
        t.appendChild(head);

        if (opts.from)    { var fr = document.createElement('div'); fr.className = 'wanotif-from';    fr.textContent = opts.from; t.appendChild(fr); }
        if (opts.phone)   { var ph = document.createElement('div'); ph.className = 'wanotif-phone';   ph.textContent = '+' + opts.phone; t.appendChild(ph); }
        if (opts.snippet) { var sn = document.createElement('div'); sn.className = 'wanotif-snippet'; sn.textContent = opts.snippet; t.appendChild(sn); }

        var cta = document.createElement('div'); cta.className = 'wanotif-cta'; cta.textContent = L.view + ' →'; t.appendChild(cta);
        var bar = document.createElement('div'); bar.className = 'wanotif-bar'; t.appendChild(bar);

        var timer;
        function arm() { timer = setTimeout(function () { dismiss(t); }, DISMISS_MS); }
        function pause() { clearTimeout(timer); t.classList.add('paused'); }
        function resume() { t.classList.remove('paused'); arm(); }
        t.addEventListener('mouseenter', pause);
        t.addEventListener('mouseleave', resume);
        t.addEventListener('click', function () { window.location.href = opts.url; });

        wrap().appendChild(t);
        requestAnimationFrame(function () { t.classList.add('in'); });
        arm();

        var all = wrap().querySelectorAll('.wanotif-toast:not(.out)');
        if (all.length > MAX_TOASTS + 1) { dismiss(all[0]); }
        return t;
    }

    var NOTIF_ICON = (function () { try { var l = document.querySelector("link[rel*='icon']"); return l ? l.href : ''; } catch (e) { return ''; } })();
    function browserNotify(title, body, url) {
        if (!OPT.desktop || !('Notification' in window)) { return; }
        if (Notification.permission === 'default') { try { Notification.requestPermission(); } catch (e) {} }
        if (Notification.permission !== 'granted') { DBG('desktop notification blocked, permission =', Notification.permission); return; }
        var opts = { body: body, tag: 'wanotif-' + url, renotify: true };
        if (NOTIF_ICON) { opts.icon = NOTIF_ICON; }
        function wire(n) {
            if (!n) { return; }
            n.onclick = function () { try { window.focus(); } catch (e) {} window.location.href = url; try { n.close(); } catch (e) {} };
            setTimeout(function () { try { n.close(); } catch (e) {} }, 8000);
        }
        try {
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready
                    .then(function (reg) { return reg.showNotification(title, opts); })
                    .catch(function () { try { wire(new Notification(title, opts)); } catch (e) {} });
            } else {
                wire(new Notification(title, opts));
            }
        } catch (e) { DBG('desktop notification error', e); }
    }

    /**
     * The thread that is open in front of the user right now (set by the
     * module UI). Announcing a message the user is literally reading would be
     * noise, so those items are skipped — the chat pane already appends them.
     */
    function isOpenThread(phone) {
        return typeof window.wapiActiveThread !== 'undefined'
            && window.wapiActiveThread !== null
            && String(window.wapiActiveThread) === String(phone)
            && !document.hidden;
    }

    function notifyNew(fresh) {
        fresh = fresh.filter(function (it) { return !isOpenThread(it.phone); });
        if (!fresh.length) { return; }

        chime();
        var shown = OPT.toast ? fresh.slice(0, MAX_TOASTS) : [];
        shown.forEach(function (it) {
            buildToast({
                title: L.newMsg,
                from: it.name || ('+' + it.phone),
                phone: it.name ? it.phone : '',
                snippet: it.snippet,
                time: it.time,
                url: threadUrl(it.phone)
            });
        });
        fresh.slice(0, MAX_TOASTS).forEach(function (it) {
            browserNotify(L.newMsg + ' · ' + (it.name || ('+' + it.phone)), it.snippet || '', threadUrl(it.phone));
        });

        var extra = fresh.length - shown.length;
        if (OPT.toast && extra > 0) {
            buildToast({ title: L.title, from: L.more.replace('%s', extra), snippet: '', time: '', url: INBOX_URL + '?tab=inbox' });
        }
    }

    /* ── Seen-state diffing ─────────────────────────────────────────── */
    function loadSeen() { try { return JSON.parse(sessionStorage.getItem(SEEN_KEY)) || {}; } catch (e) { return {}; } }
    function saveSeen(m) { try { sessionStorage.setItem(SEEN_KEY, JSON.stringify(m)); } catch (e) {} }

    function apply(count, items, allowNotify) {
        items = items || [];
        var seen = loadSeen();
        var fresh = [];
        items.forEach(function (it) {
            var key = String(it.id);
            if (!(key in seen)) { fresh.push(it); }
            seen[key] = 1;
        });
        // Keep the map bounded — ids only ever grow, so drop the oldest.
        var keys = Object.keys(seen);
        if (keys.length > 300) {
            keys.sort(function (a, b) { return a - b; }).slice(0, keys.length - 300)
                .forEach(function (k) { delete seen[k]; });
        }
        saveSeen(seen);
        setBadge(count);

        // Hand the same result to the module UI when it is on screen, so the
        // Inbox badge and thread list react to this poll instead of waiting
        // for their own next tick.
        if (typeof window.wapiOnInboxPing === 'function') {
            try { window.wapiOnInboxPing(count, items, allowNotify ? fresh : []); } catch (e) { DBG('ping failed', e); }
        }

        if (allowNotify) { notifyNew(fresh); }
    }

    var polling = false;
    function poll() {
        if (polling) { return; }
        polling = true;
        fetch(ENDPOINT, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (txt) {
                polling = false;
                if (!txt) { return; }
                var d; try { d = JSON.parse(txt); } catch (e) { DBG('non-JSON body:', String(txt).slice(0, 120)); return; }
                if (!d || typeof d.count === 'undefined') { return; }
                apply(parseInt(d.count, 10) || 0, d.items, true);
            })
            .catch(function (e) { polling = false; DBG('poll error', e); });
    }

    /** Console/debug hooks + the Settings tab's "Test notification" button. */
    window.wapiNotifyPoll = poll;
    window.wapiNotifyTest = function () {
        chime();
        buildToast({
            title: L.newMsg,
            from: '<?php echo addslashes(_l('wapi_notify_test_from')); ?>',
            phone: '',
            snippet: '<?php echo addslashes(_l('wapi_notify_test_body')); ?>',
            time: '',
            url: INBOX_URL + '?tab=inbox'
        });
        browserNotify(L.title, '<?php echo addslashes(_l('wapi_notify_test_body')); ?>', INBOX_URL + '?tab=inbox');
    };

    function init() {
        // Seed the badge + seen-map without announcing history on first load.
        apply(INITIAL, INITIAL_ITEMS, false);

        if (OPT.desktop && window.Notification && Notification.permission === 'default') {
            document.addEventListener('click', function once() {
                try { Notification.requestPermission(); } catch (e) {}
                document.removeEventListener('click', once);
            });
        }

        setTimeout(poll, 3000);
        // Hidden tabs don't poll — focusPoll() below fires the moment the tab
        // is visible again, so a background tab costs the server nothing.
        setInterval(function () { if (!document.hidden) { poll(); } }, POLL_MS);

        var lastFocusPoll = 0;
        function focusPoll() {
            if (Date.now() - lastFocusPoll > 5000) { lastFocusPoll = Date.now(); poll(); }
        }
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { focusPoll(); } });
        window.addEventListener('focus', focusPoll);
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})();
</script>
