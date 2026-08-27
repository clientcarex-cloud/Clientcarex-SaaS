<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Real-time "new customer reply" toast notifications for Pro Tickets staff.
 * Mirrors the tenant-side Customer Success notifier, but reads the LOCAL DB and
 * links to the admin ticket view. Encode seed items defensively so a bad byte
 * in a reply snippet can never break the injected script.
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
.topbar-user-profile { position: relative; }
.ptkn-profile-badge {
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
.ptkn-toast-wrap {
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
.ptkn-toast {
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
.ptkn-toast.in { transform: translateX(0); opacity: 1; }
.ptkn-toast.out { transform: translateX(24px); opacity: 0; }
.ptkn-toast:hover { box-shadow: 0 16px 38px rgba(15, 23, 42, .22); }
.ptkn-toast-head { display: flex; align-items: center; gap: 9px; margin-bottom: 6px; }
.ptkn-toast-ico {
    flex: 0 0 auto; width: 30px; height: 30px; border-radius: 9px;
    background: #eef2ff; color: #4f46e5;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.ptkn-toast-title { font-size: 12px; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; color: #4f46e5; line-height: 1.2; }
.ptkn-toast-time { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.ptkn-toast-close { margin-left: auto; flex: 0 0 auto; width: 22px; height: 22px; border-radius: 6px; border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 14px; line-height: 1; }
.ptkn-toast-close:hover { background: #f1f5f9; color: #475569; }
.ptkn-toast-subject { font-size: 14px; font-weight: 600; color: #0f172a; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.ptkn-toast-from { font-size: 12px; color: #4f46e5; font-weight: 600; margin-top: 1px; }
.ptkn-toast-snippet { font-size: 12.5px; color: #64748b; line-height: 1.45; margin-top: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ptkn-toast-cta { font-size: 11.5px; color: #4f46e5; font-weight: 600; margin-top: 7px; }
.ptkn-toast-bar { position: absolute; left: 0; bottom: 0; height: 3px; width: 100%; background: #4f46e5; opacity: .55; transform-origin: left center; animation: ptknBar 5s linear forwards; }
.ptkn-toast.paused .ptkn-toast-bar { animation-play-state: paused; }
@keyframes ptknBar { from { transform: scaleX(1); } to { transform: scaleX(0); } }
</style>
<script>
(function () {
    var ENDPOINT     = "<?= $endpoint; ?>";
    var TICKET_BASE  = "<?= $ticket_base; ?>";
    var INITIAL      = <?= (int) $initial; ?>;
    var INITIAL_ITEMS = <?= $items_json; ?>;
    var SEEN_KEY = 'ptkn_seen';
    var POLL_MS  = 15000;
    var DISMISS_MS = 5000;
    var MAX_TOASTS = 3;

    var L = {
        title: "<?= addslashes(_l('pro_tickets_notify_title')); ?>",
        newReply: "<?= addslashes(_l('pro_tickets_notify_new_reply')); ?>",
        view: "<?= addslashes(_l('pro_tickets_notify_view')); ?>",
        from: "<?= addslashes(_l('pro_tickets_notify_from')); ?>",
        more: "<?= addslashes(_l('pro_tickets_notify_more')); ?>"
    };

    function DBG() { try { console.log.apply(console, ['[pro-tickets-notify]'].concat([].slice.call(arguments))); } catch (e) {} }
    function ticketUrl(id) { return TICKET_BASE + id; }

    /* ── Badge on the staff profile avatar ──────────────────────────── */
    function ensureProfileBadge() {
        var prof = document.querySelector('.topbar-user-profile');
        if (!prof) return null;
        var pb = prof.querySelector('.ptkn-profile-badge');
        if (!pb) { pb = document.createElement('span'); pb.className = 'ptkn-profile-badge'; prof.appendChild(pb); }
        return pb;
    }
    function setBadges(count) {
        var pb = ensureProfileBadge();
        if (pb) { pb.textContent = count > 99 ? '99+' : count; pb.style.display = count > 0 ? '' : 'none'; }
    }

    /* ── Toasts ─────────────────────────────────────────────────────── */
    function wrap() {
        var w = document.querySelector('.ptkn-toast-wrap');
        if (!w) { w = document.createElement('div'); w.className = 'ptkn-toast-wrap'; document.body.appendChild(w); }
        return w;
    }
    function dismiss(el) {
        if (!el || el._gone) return;
        el._gone = true;
        el.classList.add('out');
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
    }
    function buildToast(opts) {
        var t = document.createElement('div');
        t.className = 'ptkn-toast';

        var head = document.createElement('div');
        head.className = 'ptkn-toast-head';
        var ico = document.createElement('div');
        ico.className = 'ptkn-toast-ico';
        ico.innerHTML = '<i class="fa-solid fa-comment-dots"></i>';
        var meta = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'ptkn-toast-title';
        title.textContent = opts.title || L.newReply;
        meta.appendChild(title);
        if (opts.time) { var tm = document.createElement('div'); tm.className = 'ptkn-toast-time'; tm.textContent = opts.time; meta.appendChild(tm); }
        var close = document.createElement('button');
        close.className = 'ptkn-toast-close'; close.type = 'button'; close.innerHTML = '&times;';
        close.addEventListener('click', function (e) { e.stopPropagation(); dismiss(t); });
        head.appendChild(ico); head.appendChild(meta); head.appendChild(close);
        t.appendChild(head);

        if (opts.subject) { var sub = document.createElement('div'); sub.className = 'ptkn-toast-subject'; sub.textContent = opts.subject; t.appendChild(sub); }
        if (opts.from)    { var fr = document.createElement('div');  fr.className = 'ptkn-toast-from';    fr.textContent = L.from + ' ' + opts.from; t.appendChild(fr); }
        if (opts.snippet) { var sn = document.createElement('div');  sn.className = 'ptkn-toast-snippet'; sn.textContent = opts.snippet; t.appendChild(sn); }

        var cta = document.createElement('div'); cta.className = 'ptkn-toast-cta'; cta.textContent = L.view + ' →'; t.appendChild(cta);
        var bar = document.createElement('div'); bar.className = 'ptkn-toast-bar'; t.appendChild(bar);

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

        var all = wrap().querySelectorAll('.ptkn-toast:not(.out)');
        if (all.length > MAX_TOASTS + 1) { dismiss(all[0]); }
        return t;
    }

    var NOTIF_ICON = (function () { try { var l = document.querySelector("link[rel*='icon']"); return l ? l.href : ''; } catch (e) { return ''; } })();
    function browserNotify(title, body, url) {
        if (!('Notification' in window)) { return; }
        if (Notification.permission === 'default') { try { Notification.requestPermission(); } catch (e) {} }
        if (Notification.permission !== 'granted') { DBG('desktop notification blocked, permission =', Notification.permission); return; }
        var opts = { body: body, tag: 'ptkn-' + url, renotify: true };
        if (NOTIF_ICON) { opts.icon = NOTIF_ICON; }
        function wire(n) { if (!n) return; n.onclick = function () { try { window.focus(); } catch (e) {} window.location.href = url; try { n.close(); } catch (e) {} }; setTimeout(function () { try { n.close(); } catch (e) {} }, 8000); }
        try {
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready
                    .then(function (reg) { return reg.showNotification(title, opts); })
                    .then(function () { DBG('desktop notification shown (sw)'); })
                    .catch(function () { try { wire(new Notification(title, opts)); } catch (e) {} });
            } else {
                wire(new Notification(title, opts));
                DBG('desktop notification shown (direct)');
            }
        } catch (e) { DBG('desktop notification error', e); }
    }
    window.__proTicketsTestNotify = function () { browserNotify(L.title + ' · test', 'This is a test desktop notification.', ENDPOINT); };

    function notifyNew(fresh) {
        if (!fresh.length) return;
        var shown = fresh.slice(0, MAX_TOASTS);
        shown.forEach(function (it) {
            buildToast({
                title: L.newReply,
                subject: it.subject || ('#' + it.id),
                from: it.from,
                snippet: it.snippet,
                time: it.time,
                url: ticketUrl(it.id)
            });
            browserNotify(L.newReply + ' · #' + it.id, (it.from ? it.from + ': ' : '') + (it.snippet || ''), ticketUrl(it.id));
        });
        var extra = fresh.length - shown.length;
        if (extra > 0) {
            buildToast({ title: L.title, subject: L.more.replace('%s', extra), from: '', snippet: '', time: '', url: TICKET_BASE.replace(/\/ticket\/$/, '') });
        }
    }

    /* ── Seen-state diffing ─────────────────────────────────────────── */
    function loadSeen() { try { return JSON.parse(sessionStorage.getItem(SEEN_KEY)) || {}; } catch (e) { return {}; } }
    function saveSeen(m) { try { sessionStorage.setItem(SEEN_KEY, JSON.stringify(m)); } catch (e) {} }

    function apply(count, items, allowNotify) {
        items = items || [];
        var seen = loadSeen();
        var fresh = [];
        var current = {};
        items.forEach(function (it) {
            var key = String(it.id);
            current[key] = 1;
            if (!(key in seen) || String(seen[key]) < String(it.stamp)) { fresh.push(it); }
            seen[key] = it.stamp;
        });
        Object.keys(seen).forEach(function (k) { if (!current[k]) delete seen[k]; });
        saveSeen(seen);
        setBadges(count);
        if (allowNotify) { notifyNew(fresh); }
    }

    var polling = false;
    function poll() {
        if (polling) { return; }
        polling = true;
        fetch(ENDPOINT, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { if (!r.ok) { return null; } return r.text(); })
            .then(function (txt) {
                polling = false;
                if (!txt) { return; }
                var d; try { d = JSON.parse(txt); } catch (e) { DBG('non-JSON body:', String(txt).slice(0, 120)); return; }
                if (!d || typeof d.count === 'undefined') { return; }
                DBG('count =', d.count, 'items =', (d.items || []).length);
                apply(parseInt(d.count, 10) || 0, d.items, true);
            })
            .catch(function (e) { polling = false; DBG('poll error', e); });
    }
    window.__proTicketsPoll  = poll;
    window.__proTicketsState = function () { return { endpoint: ENDPOINT, initial: INITIAL, initialItems: INITIAL_ITEMS, seen: loadSeen() }; };

    function init() {
        DBG('init', { initial: INITIAL, items: INITIAL_ITEMS.length, endpoint: ENDPOINT });
        apply(INITIAL, INITIAL_ITEMS, false);
        if (window.Notification && Notification.permission === 'default') {
            document.addEventListener('click', function once() { try { Notification.requestPermission(); } catch (e) {} document.removeEventListener('click', once); });
        }
        setTimeout(poll, 2500);
        // Hidden tabs don't poll — a staff member with the CRM parked in a
        // background tab all day would otherwise keep hitting the server every
        // 15s for a badge nobody can see. The visibilitychange handler below
        // polls immediately when the tab comes back, so nothing is missed.
        setInterval(function () { if (!document.hidden) { poll(); } }, POLL_MS);
        var lastFocusPoll = 0;
        document.addEventListener('visibilitychange', function () { if (!document.hidden && Date.now() - lastFocusPoll > 5000) { lastFocusPoll = Date.now(); poll(); } });
        window.addEventListener('focus', function () { if (Date.now() - lastFocusPoll > 5000) { lastFocusPoll = Date.now(); poll(); } });
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})();
</script>
