<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Encode the seed items defensively: a single bad byte in a reply snippet would
 * make json_encode() return false and emit `INITIAL_ITEMS = ;` — a JS syntax
 * error that would kill the whole script (and thus real-time updates). Substitute
 * invalid UTF-8 and fall back to an empty array.
 */
$items_json = json_encode(
    array_values($initial_items ?? []),
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
if ($items_json === false) {
    $items_json = '[]';
}
$feedback_json = json_encode(
    array_values($initial_feedback ?? []),
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
if ($feedback_json === false) {
    $feedback_json = '[]';
}
?>
<style>
/* Profile-button unread bubble */
.topbar-user-profile { position: relative; }
.saas-support-profile-badge {
    position: absolute;
    top: 2px;
    left: 30px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
    box-shadow: 0 0 0 2px #fff;
    z-index: 5;
    pointer-events: none;
}
/* Customer Success menu-item icon — gradient fill + gentle attention pulse */
.saas-cs-icon {
    display: inline-block;
    background: linear-gradient(135deg, #6366f1 0%, #ec4899 55%, #f43f5e 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: #ec4899; /* fallback for non-clip browsers */
    animation: saasCsPulse 2.4s ease-in-out infinite;
    transform-origin: center;
}
@keyframes saasCsPulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.16); }
}
@media (prefers-reduced-motion: reduce) {
    .saas-cs-icon { animation: none; }
}

/* Support menu-item badge */
.saas-support-badge {
    background: #dc2626 !important;
    color: #fff !important;
    margin-left: 8px;
    margin-top: 1px;
    vertical-align: middle;
}

/* ── Modern real-time toast ─────────────────────────────────────────── */
.ptks-toast-wrap {
    position: fixed;
    top: 74px;
    right: 22px;
    z-index: 2147483000;
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 360px;
    max-width: calc(100vw - 44px);
    pointer-events: none;
}
.ptks-toast {
    pointer-events: auto;
    position: relative;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e7e9ee;
    border-left: 4px solid #4f46e5;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .16);
    padding: 13px 14px 15px;
    cursor: pointer;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
    transform: translateX(24px);
    opacity: 0;
    transition: transform .28s cubic-bezier(.22,1,.36,1), opacity .28s;
}
.ptks-toast.in { transform: translateX(0); opacity: 1; }
.ptks-toast.out { transform: translateX(24px); opacity: 0; }
.ptks-toast:hover { box-shadow: 0 16px 38px rgba(15, 23, 42, .22); }
.ptks-toast-head {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 6px;
}
.ptks-toast-ico {
    flex: 0 0 auto;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}
.ptks-toast-title {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: #4f46e5;
    line-height: 1.2;
}
.ptks-toast-time { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.ptks-toast-close {
    margin-left: auto;
    flex: 0 0 auto;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
}
.ptks-toast-close:hover { background: #f1f5f9; color: #475569; }
.ptks-toast-subject {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.ptks-toast-snippet {
    font-size: 12.5px;
    color: #64748b;
    line-height: 1.45;
    margin-top: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.ptks-toast-cta { font-size: 11.5px; color: #4f46e5; font-weight: 600; margin-top: 7px; }
.ptks-toast-bar {
    position: absolute;
    left: 0; bottom: 0;
    height: 3px;
    width: 100%;
    background: #4f46e5;
    opacity: .55;
    transform-origin: left center;
    animation: ptksBar 5s linear forwards;
}
/* Feedback ("rate our support") variant — amber, star icon, sticks around
   longer than a reply toast (no auto-dismiss bar animation shortening). */
.ptks-toast-fb { border-left-color: #f59e0b; }
.ptks-toast-fb .ptks-toast-ico { background: #fef3c7; color: #d97706; }
.ptks-toast-fb .ptks-toast-title { color: #b45309; }
.ptks-toast-fb .ptks-toast-cta { color: #b45309; }
.ptks-toast-fb .ptks-toast-bar { background: #f59e0b; animation-duration: 12s; }
.ptks-toast.paused .ptks-toast-bar { animation-play-state: paused; }
@keyframes ptksBar { from { transform: scaleX(1); } to { transform: scaleX(0); } }
</style>
<script>
(function () {
    var ENDPOINT     = "<?= $endpoint; ?>";
    var MYACCOUNT    = "<?= $myaccount; ?>";
    var INITIAL      = <?= (int) $initial; ?>;
    var INITIAL_ITEMS = <?= $items_json; ?>;
    var INITIAL_FEEDBACK = <?= $feedback_json; ?>;
    var SEEN_KEY = 'saas_support_seen';
    var FB_KEY   = 'saas_support_fb_notified';
    var CNT_KEY  = 'saas_support_last';
    var POLL_MS  = 15000;
    var DISMISS_MS = 5000;
    var FB_DISMISS_MS = 12000;
    var MAX_TOASTS = 3;

    var L = {
        title: "<?= addslashes(_l('perfex_saas_support_badge_title')); ?>",
        newReply: "<?= addslashes(_l('perfex_saas_support_new_reply_title')); ?>",
        view: "<?= addslashes(_l('perfex_saas_support_view_ticket')); ?>",
        more: "<?= addslashes(_l('perfex_saas_support_more_replies')); ?>",
        closedTitle: "<?= addslashes(_l('perfex_saas_support_closed_title')); ?>",
        closedText: "<?= addslashes(_l('perfex_saas_support_closed_text')); ?>",
        rateNow: "<?= addslashes(_l('perfex_saas_support_rate_now')); ?>"
    };

    function ticketUrl(id) { return MYACCOUNT + '?redirect=clients/pro_tickets/ticket/' + id; }
    function listUrl()     { return MYACCOUNT + '?redirect=clients/pro_tickets'; }

    /* ── Badges ─────────────────────────────────────────────────────── */
    function ensureProfileBadge() {
        var prof = document.querySelector('.topbar-user-profile');
        if (!prof) return null;
        var pb = prof.querySelector('.saas-support-profile-badge');
        if (!pb) { pb = document.createElement('span'); pb.className = 'saas-support-profile-badge'; prof.appendChild(pb); }
        return pb;
    }
    function ensureSupportBadge() {
        var sb = document.querySelector('.saas-support-badge');
        if (!sb) {
            var link = document.querySelector('.saas-account-item-saas_client_tickets > a');
            if (link) { sb = document.createElement('span'); sb.className = 'badge pull-right saas-support-badge'; link.appendChild(sb); }
        }
        return sb;
    }
    function setBadges(count) {
        var txt = count > 99 ? '99+' : count;
        var sb = ensureSupportBadge();
        if (sb) { sb.textContent = txt; sb.style.display = count > 0 ? '' : 'none'; }
        var pb = ensureProfileBadge();
        if (pb) { pb.textContent = txt; pb.style.display = count > 0 ? '' : 'none'; }
    }

    /* ── Toasts ─────────────────────────────────────────────────────── */
    function wrap() {
        var w = document.querySelector('.ptks-toast-wrap');
        if (!w) { w = document.createElement('div'); w.className = 'ptks-toast-wrap'; document.body.appendChild(w); }
        return w;
    }
    function dismiss(el) {
        if (!el || el._gone) return;
        el._gone = true;
        el.classList.add('out');
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
    }
    function buildToast(opts) {
        // opts: { title, subject, snippet, time, url, variant, icon, cta, duration }
        var t = document.createElement('div');
        t.className = 'ptks-toast' + (opts.variant ? ' ptks-toast-' + opts.variant : '');

        var head = document.createElement('div');
        head.className = 'ptks-toast-head';

        var ico = document.createElement('div');
        ico.className = 'ptks-toast-ico';
        ico.innerHTML = '<i class="fa-solid ' + (opts.icon || 'fa-headset') + '"></i>';

        var meta = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'ptks-toast-title';
        title.textContent = opts.title || L.newReply;
        meta.appendChild(title);
        if (opts.time) {
            var tm = document.createElement('div');
            tm.className = 'ptks-toast-time';
            tm.textContent = opts.time;
            meta.appendChild(tm);
        }

        var close = document.createElement('button');
        close.className = 'ptks-toast-close';
        close.type = 'button';
        close.innerHTML = '&times;';
        close.addEventListener('click', function (e) { e.stopPropagation(); dismiss(t); });

        head.appendChild(ico); head.appendChild(meta); head.appendChild(close);
        t.appendChild(head);

        if (opts.subject) {
            var sub = document.createElement('div');
            sub.className = 'ptks-toast-subject';
            sub.textContent = opts.subject;
            t.appendChild(sub);
        }
        if (opts.snippet) {
            var sn = document.createElement('div');
            sn.className = 'ptks-toast-snippet';
            sn.textContent = opts.snippet;
            t.appendChild(sn);
        }

        var cta = document.createElement('div');
        cta.className = 'ptks-toast-cta';
        cta.textContent = (opts.cta || L.view) + ' →';
        t.appendChild(cta);

        var bar = document.createElement('div');
        bar.className = 'ptks-toast-bar';
        t.appendChild(bar);

        // Auto-dismiss with hover-pause
        var lifetime = opts.duration || DISMISS_MS;
        var timer;
        function arm() { timer = setTimeout(function () { dismiss(t); }, lifetime); }
        function pause() { clearTimeout(timer); t.classList.add('paused'); }
        function resume() { t.classList.remove('paused'); arm(); }
        t.addEventListener('mouseenter', pause);
        t.addEventListener('mouseleave', resume);

        t.addEventListener('click', function () { window.location.href = opts.url; });

        wrap().appendChild(t);
        requestAnimationFrame(function () { t.classList.add('in'); });
        arm();

        // Trim overflow of stacked toasts
        var all = wrap().querySelectorAll('.ptks-toast:not(.out)');
        if (all.length > MAX_TOASTS + 1) { dismiss(all[0]); }

        return t;
    }
    // Use the site's own favicon as the notification icon when available.
    var NOTIF_ICON = (function () {
        try { var l = document.querySelector("link[rel*='icon']"); return l ? l.href : ''; } catch (e) { return ''; }
    })();

    function browserNotify(title, body, url) {
        if (!('Notification' in window)) { DBG('Notification API unavailable'); return; }
        if (Notification.permission === 'default') {
            try { Notification.requestPermission(); } catch (e) {}
        }
        if (Notification.permission !== 'granted') { DBG('desktop notification blocked, permission =', Notification.permission); return; }

        var opts = { body: body, tag: 'saas-support-' + url, renotify: true };
        if (NOTIF_ICON) { opts.icon = NOTIF_ICON; }

        function wire(n) {
            if (!n) { return; }
            n.onclick = function () { try { window.focus(); } catch (e) {} window.location.href = url; try { n.close(); } catch (e) {} };
            setTimeout(function () { try { n.close(); } catch (e) {} }, 8000);
        }

        try {
            // Prefer a ServiceWorker notification when one controls the page
            // (required on some platforms); otherwise fall back to the direct API.
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready
                    .then(function (reg) { return reg.showNotification(title, opts); })
                    .then(function () { DBG('desktop notification shown (sw)'); })
                    .catch(function (e) {
                        DBG('sw notification failed, trying direct', e);
                        try { wire(new Notification(title, opts)); DBG('desktop notification shown (direct)'); }
                        catch (er) { DBG('new Notification failed', er); }
                    });
            } else {
                wire(new Notification(title, opts));
                DBG('desktop notification shown (direct)');
            }
        } catch (e) {
            DBG('desktop notification error', e);
        }
    }

    // Manual test hook for the debug page / console.
    window.__saasSupportTestNotify = function () {
        browserNotify(L.title + ' · test', 'This is a test desktop notification.', listUrl());
    };
    function notifyNew(fresh) {
        if (!fresh.length) return;
        var shown = fresh.slice(0, MAX_TOASTS);
        shown.forEach(function (it) {
            buildToast({
                title: L.newReply,
                subject: it.subject || ('#' + it.id),
                snippet: it.snippet,
                time: it.time,
                url: ticketUrl(it.id)
            });
            browserNotify(L.title + ' · #' + it.id, (it.subject ? it.subject + ' — ' : '') + (it.snippet || ''), ticketUrl(it.id));
        });
        var extra = fresh.length - shown.length;
        if (extra > 0) {
            buildToast({
                title: L.title,
                subject: L.more.replace('%s', extra),
                snippet: '',
                time: '',
                url: listUrl()
            });
        }
    }

    /* ── Feedback ("rate our support") notifications ────────────────────
       A ticket closed by provider staff with no rating yet should notify the
       tenant ONCE, wherever they are in the admin — on page load if it is
       already pending, or within one poll tick when it happens live. The
       notified-map lives in localStorage (persists across pages/sessions) so
       the toast doesn't repeat on every page; a re-close after reopen has a
       fresh resolved_at stamp so it notifies again. */
    function loadFbSeen() {
        try { return JSON.parse(localStorage.getItem(FB_KEY)) || {}; } catch (e) { return {}; }
    }
    function saveFbSeen(m) { try { localStorage.setItem(FB_KEY, JSON.stringify(m)); } catch (e) {} }

    function applyFeedback(list) {
        list = list || [];
        var seen = loadFbSeen();
        var current = {};
        var fresh = [];

        list.forEach(function (it) {
            var key = String(it.id);
            current[key] = 1;
            if (!(key in seen) || String(seen[key]) < String(it.stamp)) {
                fresh.push(it);
            }
            seen[key] = it.stamp;
        });
        // Rated (or reopened) tickets drop out of the pending list — forget
        // them so the map stays small.
        Object.keys(seen).forEach(function (k) { if (!current[k]) delete seen[k]; });
        saveFbSeen(seen);

        fresh.slice(0, MAX_TOASTS).forEach(function (it) {
            buildToast({
                variant: 'fb',
                icon: 'fa-star',
                title: L.closedTitle,
                subject: it.subject || ('#' + it.id),
                snippet: L.closedText,
                time: it.time,
                cta: L.rateNow,
                duration: FB_DISMISS_MS,
                url: ticketUrl(it.id)
            });
            browserNotify(L.closedTitle + ' · #' + it.id, (it.subject ? it.subject + ' — ' : '') + L.closedText, ticketUrl(it.id));
        });
    }

    /* ── Seen-state diffing ─────────────────────────────────────────── */
    function loadSeen() {
        try { return JSON.parse(sessionStorage.getItem(SEEN_KEY)) || {}; } catch (e) { return {}; }
    }
    function saveSeen(m) { try { sessionStorage.setItem(SEEN_KEY, JSON.stringify(m)); } catch (e) {} }

    function apply(count, items, allowNotify) {
        items = items || [];
        var seen = loadSeen();
        var fresh = [];
        var current = {};

        items.forEach(function (it) {
            var key = String(it.id);
            current[key] = 1;
            if (!(key in seen) || String(seen[key]) < String(it.stamp)) {
                fresh.push(it);
            }
            seen[key] = it.stamp;
        });
        // Drop tickets that are no longer unread so a future reply re-notifies.
        Object.keys(seen).forEach(function (k) { if (!current[k]) delete seen[k]; });
        saveSeen(seen);

        setBadges(count);
        try { sessionStorage.setItem(CNT_KEY, count); } catch (e) {}
        if (allowNotify) { notifyNew(fresh); }
    }

    function DBG() {
        try { console.log.apply(console, ['[saas-support]'].concat([].slice.call(arguments))); } catch (e) {}
    }

    var polling = false;
    function poll() {
        if (polling) { return; }
        polling = true;
        DBG('poll →', ENDPOINT);
        fetch(ENDPOINT, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                DBG('poll response', r.status, r.headers.get('content-type'));
                if (!r.ok) { return null; }
                // Parse the body regardless of Content-Type: a proxy (Cloudflare)
                // or an output hook can alter/strip it, which would otherwise make
                // us discard a valid JSON payload.
                return r.text();
            })
            .then(function (txt) {
                polling = false;
                if (!txt) { return; }
                var d;
                try { d = JSON.parse(txt); }
                catch (e) { DBG('non-JSON body (first 120 chars):', String(txt).slice(0, 120)); return; }
                if (!d || typeof d.count === 'undefined') { DBG('unexpected payload', d); return; }
                DBG('count =', d.count, 'items =', (d.items || []).length, 'feedback =', (d.feedback || []).length);
                apply(parseInt(d.count, 10) || 0, d.items, true);
                applyFeedback(d.feedback);
            })
            .catch(function (e) { polling = false; DBG('poll error', e); });
    }

    // Expose manual controls for diagnostics from the browser console.
    window.__saasSupportPoll  = poll;
    window.__saasSupportState = function () { return { endpoint: ENDPOINT, initial: INITIAL, initialItems: INITIAL_ITEMS, initialFeedback: INITIAL_FEEDBACK, seen: loadSeen(), fbNotified: loadFbSeen() }; };

    function init() {
        DBG('init', { initial: INITIAL, items: INITIAL_ITEMS.length, feedback: INITIAL_FEEDBACK.length, endpoint: ENDPOINT });
        // Seed the "seen" set from the current unread tickets WITHOUT notifying,
        // so existing unread never toasts on load — only replies that arrive
        // afterwards do.
        apply(INITIAL, INITIAL_ITEMS, false);
        // Feedback prompts DO notify on load (once per ticket, localStorage-
        // deduped) — a close that happened while the tenant was offline should
        // still surface the "how did we do?" ask on their next page.
        applyFeedback(INITIAL_FEEDBACK);

        if (window.Notification && Notification.permission === 'default') {
            document.addEventListener('click', function once() {
                try { Notification.requestPermission(); } catch (e) {}
                document.removeEventListener('click', once);
            });
        }

        setTimeout(poll, 2500);
        // Hidden tabs don't poll — this runs on every tenant admin page, so a
        // parked background tab per staff member across 14 tenants adds up to a
        // constant query stream for a badge nobody is looking at. The
        // visibilitychange handler below polls the instant the tab is back.
        setInterval(function () { if (!document.hidden) { poll(); } }, POLL_MS);

        // Poll immediately when the tab regains focus — makes replies feel
        // instant when switching back from the ticket window.
        var lastFocusPoll = 0;
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && Date.now() - lastFocusPoll > 5000) {
                lastFocusPoll = Date.now();
                poll();
            }
        });
        window.addEventListener('focus', function () {
            if (Date.now() - lastFocusPoll > 5000) { lastFocusPoll = Date.now(); poll(); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
