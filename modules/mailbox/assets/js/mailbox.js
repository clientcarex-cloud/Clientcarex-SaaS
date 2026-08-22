/* ═══════════════════════════════════════════════════════════════════════
   Mailbox — module JS
   Part 1: webmail app  (#mbx-app,      window.MBX_BOOT)
   Part 2: account mgr  (#mbx-accounts, window.MBX_ADMIN_BOOT)

   The corporate screens (settings / analytics / audit) live in
   mailbox_pro.js and share nothing but the CSS.
   ═══════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* ── CSRF (Perfex exposes the current pair on window.csrfData) ──────── */
    function csrf() {
        if (typeof window.csrfData !== 'undefined' && window.csrfData.token_name) {
            return { name: window.csrfData.token_name, hash: window.csrfData.hash };
        }
        var m = document.cookie.match(/csrf_token_name=([^;]+)/);
        return { name: 'csrf_token_name', hash: m ? decodeURIComponent(m[1]) : '' };
    }

    function post(url, data, done, fail) {
        var fd = data instanceof FormData ? data : (function () {
            var f = new FormData();
            Object.keys(data || {}).forEach(function (k) {
                if (Array.isArray(data[k])) {
                    data[k].forEach(function (v) { f.append(k + '[]', v); });
                } else {
                    f.append(k, data[k]);
                }
            });
            return f;
        })();
        var t = csrf();
        fd.append(t.name, t.hash);
        // CodeIgniter removes the CSRF token from $_POST once it has verified
        // it, so a body carrying nothing else arrives as an empty POST and
        // trips the `if (!$this->input->post())` guards. This sentinel keeps
        // every request non-empty.
        fd.append('mbx', '1');

        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(done)
            .catch(fail || function () {});
    }

    function get(url, done, fail) {
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(done)
            .catch(fail || function () {});
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function toast(msg) {
        var el = document.getElementById('mbx-toast');
        if (!el) { return; }
        el.textContent = msg;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.style.display = 'none'; }, 3200);
    }

    var ACCOUNT_COLORS = ['#4f46e5', '#0891b2', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#db2777', '#0d9488'];
    function accountColor(id) { return ACCOUNT_COLORS[id % ACCOUNT_COLORS.length]; }

    function fmtDate(str) {
        if (!str) { return ''; }
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) { return str; }
        var now = new Date();
        if (d.toDateString() === now.toDateString()) {
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        if (d.getFullYear() === now.getFullYear()) {
            return d.toLocaleDateString([], { day: 'numeric', month: 'short' });
        }
        return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function fmtDateTime(str) {
        if (!str) { return ''; }
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) { return str; }
        return d.toLocaleDateString([], { day: 'numeric', month: 'short' }) + ', ' +
            d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function initials(name) {
        var parts = String(name || '?').trim().split(/\s+/);
        return ((parts[0] || '?').charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
    }

    function parseAddr(json) {
        try {
            return (JSON.parse(json || '[]') || []).map(function (a) {
                return a.name ? a.name + ' <' + a.email + '>' : a.email;
            });
        } catch (e) { return []; }
    }
    function parseAddrEmails(json) {
        try {
            return (JSON.parse(json || '[]') || []).map(function (a) { return a.email; });
        } catch (e) { return []; }
    }

    /* ═════════════════════════ WEBMAIL APP ═══════════════════════════════ */
    var app = document.getElementById('mbx-app');
    if (app && window.MBX_BOOT) {
        var B = window.MBX_BOOT, U = B.urls, T = B.i18n;
        var state = {
            accounts: [], counts: {}, signatures: {},
            account: 'all', folder: 'inbox', search: '', page: 1, totalPages: 1,
            rows: [], openId: 0, openMsg: null, selected: {},
            // Corporate layer
            labels: [], templates: [], staff: [], staffId: 0,
            features: { status: true, presence: true, sla: false, schedule: true, undo_seconds: 0, can_manage: false },
            label: 0, status: '', rowLabels: {}, rowNotes: {}
        };

        /* ── Layout: stretch the app to the bottom of the viewport ──────────
           A fixed `100vh - 140px` guess left the reading pane short (and the
           whole page scrolling on top of it). Measure the real offset instead
           so the pane always ends at the window bottom, on any screen. */
        function fitHeight() {
            if (window.innerWidth <= 700) { app.style.removeProperty('--mbx-app-h'); return; }
            var top = app.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop || 0);
            var h = window.innerHeight - top - 18;   // 18px breathing room below
            h = Math.max(420, Math.round(h));
            app.style.setProperty('--mbx-app-h', h + 'px');

            // Trim whatever the theme's own bottom padding still pushes past
            // the fold, so the page itself never gets a second scrollbar.
            var over = document.documentElement.scrollHeight - window.innerHeight;
            if (over > 0 && h - over >= 420) {
                app.style.setProperty('--mbx-app-h', (h - over) + 'px');
            }
        }

        var fitTimer;
        window.addEventListener('resize', function () {
            clearTimeout(fitTimer);
            fitTimer = setTimeout(function () { fitHeight(); refitOpenBody(); }, 150);
        });
        window.addEventListener('load', fitHeight);
        fitHeight();

        /* ── Generic anchored popover ────────────────────────────────────
           One element reused for every menu (assign, label, status, schedule,
           templates, search help) so only ever one is open. */
        var popEl = document.getElementById('mbx-pop');

        function closePop() {
            if (popEl) { popEl.style.display = 'none'; popEl.innerHTML = ''; popEl._onPick = null; }
        }

        function pop(anchor, html, onPick) {
            if (!popEl) { return; }
            popEl.innerHTML = html;
            popEl.style.display = 'block';
            popEl._onPick = onPick || null;

            var rect = anchor.getBoundingClientRect();
            var top = rect.bottom + window.pageYOffset + 6;
            var left = rect.left + window.pageXOffset;

            // Keep it on screen — menus near the right edge would overflow.
            popEl.style.top = top + 'px';
            popEl.style.left = left + 'px';
            var box = popEl.getBoundingClientRect();
            if (box.right > window.innerWidth - 12) {
                popEl.style.left = Math.max(12, window.innerWidth - box.width - 12 + window.pageXOffset) + 'px';
            }
            if (box.bottom > window.innerHeight - 12) {
                popEl.style.top = Math.max(12 + window.pageYOffset, rect.top + window.pageYOffset - box.height - 6) + 'px';
            }
            var focusable = popEl.querySelector('input, select, textarea');
            if (focusable) { focusable.focus(); }
        }

        document.addEventListener('click', function (e) {
            if (!popEl || popEl.style.display === 'none') { return; }
            if (popEl.contains(e.target) || e.target.closest('[data-pop-anchor]')) { return; }
            closePop();
        });

        if (popEl) {
            popEl.addEventListener('click', function (e) {
                var item = e.target.closest('[data-pick]');
                if (item && popEl._onPick) { popEl._onPick(item.dataset.pick, item, e); }
            });
        }

        /* ── Sidebar: accounts ── */
        function renderAccounts() {
            var html = '<button class="mbx-account-item' + (state.account === 'all' ? ' active' : '') + '" data-account="all">' +
                '<span class="mbx-account-dot" style="background:#94a3b8"></span>' +
                '<span class="mbx-ellipsis">' + esc(T.allAccounts) + '</span></button>';
            state.accounts.forEach(function (a) {
                html += '<button class="mbx-account-item' + (String(state.account) === String(a.id) ? ' active' : '') + '" data-account="' + a.id + '" title="' + esc(a.email) + '">' +
                    '<span class="mbx-account-dot" style="background:' + accountColor(+a.id) + '"></span>' +
                    '<span class="mbx-ellipsis">' + esc(a.name) + '</span></button>';
            });
            document.getElementById('mbx-account-list').innerHTML = html;
        }

        /* ── Sidebar: labels ── */
        function renderLabels() {
            var el = document.getElementById('mbx-label-list');
            if (!el) { return; }

            if (!state.labels.length) {
                el.innerHTML = '<div class="mbx-labels-empty">' + esc(T.noLabels) + '</div>';
                return;
            }

            el.innerHTML = state.labels.map(function (l) {
                return '<a class="mbx-label-item' + (+state.label === +l.id ? ' active' : '') + '" data-label="' + l.id + '">' +
                    '<span class="mbx-label-dot" style="background:' + esc(l.color) + '"></span>' +
                    '<span class="mbx-ellipsis">' + esc(l.name) + '</span>' +
                    (+l.message_count ? '<span class="mbx-badge mbx-badge-soft">' + l.message_count + '</span>' : '') +
                    (state.features.can_manage
                        ? '<button class="mbx-label-edit" data-label-edit="' + l.id + '" title="' + esc(T.edit) + '"><i class="fa fa-pen"></i></button>'
                        : '') +
                    '</a>';
            }).join('');
        }

        function renderBadges() {
            var sum = { inbox_unread: 0, drafts: 0, scheduled: 0, mine: 0, unassigned: 0, overdue: 0 };
            Object.keys(state.counts).forEach(function (id) {
                if (state.account === 'all' || String(state.account) === String(id)) {
                    Object.keys(sum).forEach(function (k) { sum[k] += state.counts[id][k] || 0; });
                }
            });

            var map = {
                'mbx-badge-inbox': sum.inbox_unread,
                'mbx-badge-drafts': sum.drafts,
                'mbx-badge-scheduled': sum.scheduled,
                'mbx-badge-mine': sum.mine,
                'mbx-badge-unassigned': sum.unassigned,
                'mbx-badge-overdue': sum.overdue
            };
            Object.keys(map).forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.textContent = map[id] > 0 ? map[id] : ''; }
            });
        }

        /* Shared-inbox and SLA views only make sense when those features are
           switched on, so hide the rest of the sidebar rather than showing
           dead links. */
        function applyFeatureVisibility() {
            document.querySelectorAll('.mbx-folder-shared').forEach(function (el) {
                el.style.display = state.features.status ? '' : 'none';
            });
            var sla = document.querySelector('.mbx-folder-sla');
            if (sla) { sla.style.display = state.features.sla ? '' : 'none'; }

            var scheduled = document.querySelector('[data-folder="scheduled"]');
            if (scheduled) { scheduled.style.display = state.features.schedule || state.features.undo_seconds > 0 ? '' : 'none'; }

            var scheduleBtn = document.getElementById('mbx-c-schedule');
            if (scheduleBtn) { scheduleBtn.style.display = state.features.schedule ? '' : 'none'; }

            var statusBtn = document.getElementById('mbx-bulk-status');
            if (statusBtn) { statusBtn.style.display = state.features.status ? '' : 'none'; }

            var newLabel = document.getElementById('mbx-label-new');
            if (newLabel) { newLabel.style.display = state.features.can_manage ? '' : 'none'; }

            updateFacetVisibility();
        }

        function updateFacetVisibility() {
            var facets = document.getElementById('mbx-facets');
            if (!facets) { return; }
            var relevant = ['inbox', 'mine', 'unassigned', 'overdue'].indexOf(state.folder) !== -1;
            facets.style.display = (state.features.status && relevant) ? 'flex' : 'none';
        }

        /* ── Message list ── */
        function loadList(silent) {
            var rowsEl = document.getElementById('mbx-rows');
            if (!silent) { rowsEl.innerHTML = '<div class="mbx-rows-empty"><i class="fa fa-circle-notch fa-spin"></i></div>'; }

            var url = U.messages + '?account=' + encodeURIComponent(state.account) +
                '&folder=' + state.folder + '&page=' + state.page +
                '&label=' + (state.label || 0) +
                '&status=' + encodeURIComponent(state.status) +
                '&search=' + encodeURIComponent(state.search);

            get(url, function (res) {
                state.rows = res.rows || [];
                state.totalPages = res.total_pages || 1;
                state.counts = res.counts || state.counts;
                state.rowLabels = res.labels || {};
                state.rowNotes = res.notes || {};
                state.selected = {};
                document.getElementById('mbx-select-all').checked = false;
                document.getElementById('mbx-bulk').style.display = 'none';
                renderBadges();
                renderRows(res.total || 0);
            }, function () {
                rowsEl.innerHTML = '<div class="mbx-rows-empty"><i class="fa fa-triangle-exclamation"></i>' + esc(T.loadError) + '</div>';
            });
        }

        function labelChips(id) {
            var labels = state.rowLabels[id] || [];
            if (!labels.length) { return ''; }
            return labels.map(function (l) {
                return '<span class="mbx-tag" style="background:' + esc(l.color) + '1f;color:' + esc(l.color) + '">' + esc(l.name) + '</span>';
            }).join('');
        }

        function statusDot(status) {
            if (!state.features.status || !status) { return ''; }
            var label = status === 'open' ? T.statusOpen : (status === 'pending' ? T.statusPending : T.statusClosed);
            return '<span class="mbx-status-dot mbx-status-' + esc(status) + '" title="' + esc(label) + '"></span>';
        }

        function slaBadge(m) {
            if (!state.features.sla || m.folder !== 'inbox') { return ''; }
            if (+m.sla_breached && !m.first_reply_at) {
                return '<span class="mbx-tag mbx-tag-danger"><i class="fa fa-clock"></i> ' + esc(T.slaBreached) + '</span>';
            }
            if (m.sla_due_at && !m.first_reply_at) {
                return '<span class="mbx-tag mbx-tag-muted" title="' + esc(T.slaDue + ': ' + fmtDateTime(m.sla_due_at)) + '"><i class="fa fa-hourglass-half"></i> ' + esc(fmtDate(m.sla_due_at)) + '</span>';
            }
            return '';
        }

        function renderRows(total) {
            var el = document.getElementById('mbx-rows');
            if (!state.rows.length) {
                el.innerHTML = '<div class="mbx-rows-empty"><i class="fa-regular fa-folder-open"></i><strong>' +
                    esc(T.emptyTitle) + '</strong><br>' + esc(T.emptySub) + '</div>';
            } else {
                el.innerHTML = state.rows.map(function (m) {
                    var outgoing = m.folder === 'sent' || m.folder === 'drafts' || m.folder === 'scheduled';
                    var who = outgoing
                        ? (T.to + ': ' + (parseAddr(m.to_emails).join(', ') || '—'))
                        : (m.from_name || m.from_email || '—');
                    var acc = state.accounts.filter(function (a) { return String(a.id) === String(m.account_id); })[0];
                    var assignee = (m.assigned_name || '').trim();
                    var notes = state.rowNotes[m.id] || 0;

                    var meta = labelChips(m.id) + slaBadge(m);
                    if (m.folder === 'scheduled' && m.scheduled_at) {
                        meta += '<span class="mbx-tag mbx-tag-info"><i class="fa fa-clock"></i> ' + esc(fmtDateTime(m.scheduled_at)) + '</span>';
                    }
                    if (m.send_error) {
                        meta += '<span class="mbx-tag mbx-tag-danger" title="' + esc(m.send_error) + '"><i class="fa fa-triangle-exclamation"></i> ' + esc(T.sendFailed) + '</span>';
                    }
                    if (+m.legal_hold) {
                        meta += '<span class="mbx-tag mbx-tag-hold"><i class="fa fa-lock"></i> ' + esc(T.legalHold) + '</span>';
                    }
                    if (state.account === 'all' && acc) {
                        meta += '<span class="mbx-row-account" style="background:' + accountColor(+acc.id) + '22;color:' + accountColor(+acc.id) + '">' + esc(acc.name) + '</span>';
                    }

                    return '<div class="mbx-row ' + (+m.is_read ? 'read' : 'unread') + (state.openId === +m.id ? ' active' : '') + '" data-id="' + m.id + '">' +
                        '<span class="mbx-row-check"><input type="checkbox" data-check="' + m.id + '"></span>' +
                        '<button class="mbx-row-star' + (+m.is_starred ? ' on' : '') + '" data-star="' + m.id + '"><i class="fa' + (+m.is_starred ? '' : '-regular') + ' fa-star"></i></button>' +
                        '<div class="mbx-row-main">' +
                            '<div class="mbx-row-top">' +
                                statusDot(m.conv_status) +
                                '<span class="mbx-row-from">' + esc(who) + '</span>' +
                                (notes ? '<span class="mbx-note-count" title="' + esc(T.internalNotes) + '"><i class="fa fa-note-sticky"></i>' + notes + '</span>' : '') +
                                (+m.has_attachments ? '<i class="fa fa-paperclip mbx-muted" style="font-size:11px"></i>' : '') +
                                '<span class="mbx-row-date">' + esc(fmtDate(m.folder === 'scheduled' ? m.scheduled_at : m.message_date)) + '</span>' +
                            '</div>' +
                            '<div class="mbx-row-subject">' + esc(m.subject || T.noSubject) + '</div>' +
                            '<div class="mbx-row-snippet">' + esc(m.snippet || '') + '</div>' +
                            (meta || assignee
                                ? '<div class="mbx-row-meta">' + meta +
                                    (assignee ? '<span class="mbx-assignee" title="' + esc(T.assignedTo + ': ' + assignee) + '">' + esc(initials(assignee)) + '</span>' : '') +
                                  '</div>'
                                : '') +
                        '</div></div>';
                }).join('');
            }
            document.getElementById('mbx-pager-label').textContent = total ? (state.page + ' / ' + state.totalPages) : '';
        }

        /* ── Reading pane ── */
        function openMessage(id) {
            state.openId = +id;
            // Decrement the unread badge from the list row's pre-open state.
            var listRow = state.rows.filter(function (m) { return String(m.id) === String(id); })[0];
            if (listRow && !+listRow.is_read && listRow.folder === 'inbox') {
                listRow.is_read = 1;
                var c = state.counts[listRow.account_id];
                if (c) { c.inbox_unread = Math.max(0, (c.inbox_unread || 0) - 1); }
                renderBadges();
            }
            app.classList.add('mbx-reading');
            var inner = document.getElementById('mbx-read-inner');
            document.getElementById('mbx-read-empty').style.display = 'none';
            inner.style.display = 'block';
            inner.innerHTML ='<div class="mbx-rows-empty"><i class="fa fa-circle-notch fa-spin"></i></div>';
            inner.scrollTop = 0;

            get(U.message + '/' + id, function (res) {
                var m = res.message;
                if (!m) { inner.innerHTML = '<div class="mbx-rows-empty">' + esc(T.loadError) + '</div>'; return; }
                state.openMsg = m;

                // Update list row read state.
                var row = document.querySelector('.mbx-row[data-id="' + id + '"]');
                if (row) { row.classList.remove('unread'); row.classList.add('read', 'active'); }
                document.querySelectorAll('.mbx-row.active').forEach(function (r) {
                    if (r !== row) { r.classList.remove('active'); }
                });

                var outgoing = m.folder === 'sent' || m.folder === 'drafts' || m.folder === 'scheduled';
                var initial = ((outgoing ? m.from_name : (m.from_name || m.from_email)) || '?').charAt(0).toUpperCase();
                var toLine = parseAddr(m.to_emails).join(', ');
                var ccLine = parseAddr(m.cc_emails).join(', ');

                var html =
                    '<div class="mbx-read-toolbar">' + readToolbar(m) + '</div>' +
                    presenceHtml(res.presence || []) +
                    workBar(m, res.labels || []) +
                    '<h3 class="mbx-read-subject">' + esc(m.subject || T.noSubject) + '</h3>' +
                    '<div class="mbx-read-head">' +
                        '<div class="mbx-read-avatar">' + esc(initial) + '</div>' +
                        '<div class="mbx-read-who">' +
                            '<strong>' + esc(m.from_name || m.from_email) + '</strong> ' +
                            '<span class="mbx-addr">&lt;' + esc(m.from_email) + '&gt;</span>' +
                            (m.sent_by > 0 && m.sent_by_name ? ' <span class="mbx-chip mbx-chip-muted mbx-chip-xs">' + esc(T.sentBy) + ' ' + esc(m.sent_by_name) + '</span>' : '') +
                            crmChip(res.crm, m) +
                            '<div class="mbx-addr">' + esc(T.to) + ': ' + esc(toLine || '—') +
                            (ccLine ? '<br>' + esc(T.cc) + ': ' + esc(ccLine) : '') + '</div>' +
                        '</div>' +
                        '<span class="mbx-read-date">' + esc(m.folder === 'scheduled' ? (T.scheduledFor + ' ' + fmtDateTime(m.scheduled_at)) : (m.message_date || '')) + '</span>' +
                    '</div>' +
                    bodyHtml(m) +
                    attachmentsHtml(m) +
                    notesHtml(res.notes || []) +
                    threadHtml(res.thread || []);

                inner.innerHTML = html;
                inner.scrollTop = 0;
                sizeBodyFrame(inner);
                startPresence(m);
            }, function () {
                inner.innerHTML = '<div class="mbx-rows-empty">' + esc(T.loadError) + '</div>';
            });
        }

        function readToolbar(m) {
            var b = function (act, icon, label, danger) {
                return '<button class="mbx-btn mbx-btn-light mbx-btn-sm' + (danger ? ' mbx-btn-danger-light' : '') + '" data-msg-act="' + act + '" data-msg-id="' + m.id + '">' +
                    '<i class="fa ' + icon + '"></i> ' + esc(label) + '</button>';
            };
            var html = '';

            if (m.folder === 'drafts') {
                html += b('editdraft', 'fa-pen', T.compose);
                html += b('delete', 'fa-trash-can', T.deleteForever, true);
                return html;
            }
            if (m.folder === 'scheduled') {
                html += b('cancelschedule', 'fa-pen-to-square', T.cancelSchedule);
                html += b('delete', 'fa-trash-can', T.deleteForever, true);
                return html;
            }

            html += b('reply', 'fa-reply', T.reply);
            html += b('replyall', 'fa-reply-all', T.replyAll);
            html += b('forward', 'fa-share', T.forward);
            if (m.folder === 'inbox') { html += b('archive', 'fa-box-archive', T.archive); }
            if (m.folder === 'archive') { html += b('inbox', 'fa-inbox', T.toInbox); }
            if (m.folder === 'trash') {
                html += b('restore', 'fa-rotate-left', T.restore);
                html += b('delete', 'fa-trash-can', T.deleteForever, true);
            } else {
                html += b('trash', 'fa-trash-can', T.trash, true);
            }
            html += b('unread', 'fa-envelope', T.markUnread);
            if (B.isSuper) {
                html += b('hold', +m.legal_hold ? 'fa-lock-open' : 'fa-lock', +m.legal_hold ? T.legalHoldOff : T.legalHoldOn);
            }
            return html;
        }

        /* The shared-inbox strip: owner, status, labels, CRM conversion. */
        function workBar(m, labels) {
            if (m.folder === 'drafts' || m.folder === 'scheduled') { return ''; }

            var assignee = (m.assigned_name || '').trim();
            var html = '<div class="mbx-workbar">';

            html += '<button class="mbx-work-btn" data-pop-anchor data-work="assign">' +
                '<i class="fa fa-user-check"></i> ' +
                (assignee ? esc(assignee) : esc(T.unassigned)) +
                '</button>';

            if (state.features.status) {
                var status = m.conv_status || 'open';
                var statusLabel = status === 'open' ? T.statusOpen : (status === 'pending' ? T.statusPending : T.statusClosed);
                html += '<button class="mbx-work-btn mbx-work-status mbx-status-' + esc(status) + '" data-pop-anchor data-work="status">' +
                    statusDot(status) + esc(statusLabel) + '</button>';
            }

            html += '<button class="mbx-work-btn" data-pop-anchor data-work="label"><i class="fa fa-tag"></i> ' + esc(T.label) + '</button>';

            if (m.rel_id > 0) {
                html += '<span class="mbx-chip mbx-chip-muted mbx-chip-xs">' + esc(T.convertedTo) + ' ' + esc(m.rel_type) + ' #' + m.rel_id + '</span>';
            } else {
                html += '<button class="mbx-work-btn" data-pop-anchor data-work="convert"><i class="fa fa-wand-magic-sparkles"></i> ' + esc(T.convert) + '</button>';
            }

            if (labels.length) {
                html += '<span class="mbx-work-labels">' + labels.map(function (l) {
                    return '<span class="mbx-tag" style="background:' + esc(l.color) + '1f;color:' + esc(l.color) + '">' + esc(l.name) +
                        '<button class="mbx-tag-x" data-remove-label="' + l.id + '">&times;</button></span>';
                }).join('') + '</span>';
            }

            return html + '</div>';
        }

        function crmChip(crm, m) {
            if (!crm) { return ''; }
            return ' <a class="mbx-chip mbx-chip-info mbx-chip-xs" href="' + esc(crm.url) + '" target="_blank" rel="noopener">' +
                '<i class="fa fa-address-card"></i> ' + esc(crm.label) + '</a>';
        }

        function presenceHtml(others) {
            if (!state.features.presence || !others.length) { return ''; }
            var replying = others.filter(function (o) { return o.state === 'replying'; });
            var who = (replying.length ? replying : others).map(function (o) { return o.full_name; }).join(', ');
            var verb = replying.length ? T.replyingNow : T.viewingNow;
            return '<div class="mbx-presence' + (replying.length ? ' mbx-presence-hot' : '') + '">' +
                '<i class="fa fa-eye"></i> <strong>' + esc(who) + '</strong> ' + esc(verb) +
                (replying.length ? ' — ' + esc(T.collision) : '') + '</div>';
        }

        function notesHtml(notes) {
            var list = notes.length
                ? notes.map(function (n) {
                    var own = +n.staff_id === +state.staffId;
                    return '<div class="mbx-note">' +
                        '<span class="mbx-note-avatar">' + esc(initials(n.staff_name)) + '</span>' +
                        '<div class="mbx-note-main">' +
                            '<div class="mbx-note-head"><strong>' + esc(n.staff_name || '—') + '</strong>' +
                            '<span class="mbx-muted mbx-small">' + esc(fmtDateTime(n.created_at)) + '</span>' +
                            (own || B.isSuper ? '<button class="mbx-note-del" data-note-del="' + n.id + '"><i class="fa fa-trash-can"></i></button>' : '') +
                            '</div>' +
                            '<div class="mbx-note-body">' + esc(n.note).replace(/\n/g, '<br>') + '</div>' +
                        '</div></div>';
                }).join('')
                : '<div class="mbx-muted mbx-small mbx-note-empty">' + esc(T.noNotes) + '</div>';

            return '<div class="mbx-notes" id="mbx-notes">' +
                '<div class="mbx-thread-title"><i class="fa fa-note-sticky"></i> ' + esc(T.internalNotes) +
                ' <span class="mbx-muted mbx-small">— ' + esc(T.internalHint) + '</span></div>' +
                '<div class="mbx-note-list">' + list + '</div>' +
                '<div class="mbx-note-compose">' +
                    '<textarea id="mbx-note-input" rows="2" placeholder="' + esc(T.mentionHint) + '"></textarea>' +
                    '<button class="mbx-btn mbx-btn-light mbx-btn-sm" id="mbx-note-add"><i class="fa fa-plus"></i> ' + esc(T.addNote) + '</button>' +
                '</div></div>';
        }

        function bodyHtml(m) {
            if (m.body_html && m.body_html.trim() !== '') {
                // Untrusted mail HTML runs inside a sandboxed iframe instead of
                // the admin DOM. `allow-scripts` is deliberately NOT granted, so
                // no mail JS can ever run — which is what makes `allow-same-origin`
                // safe here, and it is required so we can read the document height
                // and grow the frame to the full mail (without it the height can
                // never be measured and long mail gets clipped).
                // NEVER add allow-scripts to this list.
                var doc = '<!doctype html><html><head><meta charset="utf-8">' +
                    '<style>html,body{height:auto!important;min-height:0!important}' +
                    'body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;font-size:13.5px;line-height:1.6;color:#0f172a;margin:8px;word-break:break-word}' +
                    'img{max-width:100%;height:auto}</style>' +
                    '<base target="_blank"></head><body>' + m.body_html + '</body></html>';
                return '<iframe class="mbx-read-bodyframe" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" srcdoc="' + doc.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '"></iframe>';
            }
            return '<div class="mbx-read-plain">' + esc(m.body_plain || '') + '</div>';
        }

        function sizeBodyFrame(scope) {
            var frame = scope.querySelector('.mbx-read-bodyframe');
            if (!frame) { return; }

            // Grow the frame to the full height of the mail so the reading pane
            // scrolls the message instead of the frame clipping it. Measured
            // repeatedly because images and layout tables settle late.
            function fit() {
                try {
                    // A hidden pane (mobile breakpoint) measures as ~0 — never
                    // let that collapse a correctly sized frame.
                    if (!frame.offsetWidth) { return; }
                    var d = frame.contentDocument;
                    if (!d || !d.body) { return; }
                    // Collapse first: mail built with height:100% tables reports
                    // only the frame's current height otherwise, which is exactly
                    // how a long mail ends up cut off.
                    frame.style.height = '60px';
                    var h = Math.max(
                        d.body.scrollHeight, d.body.offsetHeight,
                        d.documentElement ? d.documentElement.scrollHeight : 0,
                        d.documentElement ? d.documentElement.offsetHeight : 0
                    );
                    frame.style.height = Math.max(h + 24, 260) + 'px';
                } catch (e) { /* unreadable — CSS min-height + frame scrollbar */ }
            }
            frame._mbxFit = fit;

            function watch() {
                fit();
                [60, 250, 700, 1600, 3000].forEach(function (ms) { setTimeout(fit, ms); });
                try {
                    // Late-loading remote images are the usual reason a first
                    // measurement comes back short.
                    Array.prototype.forEach.call(frame.contentDocument.images || [], function (img) {
                        if (!img.complete) {
                            img.addEventListener('load', fit);
                            img.addEventListener('error', fit);
                        }
                    });
                } catch (e) { /* unreadable */ }
            }

            frame.addEventListener('load', watch);
            // srcdoc frames can finish parsing before the listener is attached.
            try {
                if (frame.contentDocument && frame.contentDocument.readyState === 'complete') { watch(); }
            } catch (e) { /* unreadable */ }
        }

        // A width change reflows the mail, so re-measure the open message.
        function refitOpenBody() {
            var frame = document.querySelector('#mbx-read-inner .mbx-read-bodyframe');
            if (frame && frame._mbxFit) { frame._mbxFit(); }
        }

        function attachmentsHtml(m) {
            if (!m.attachments || !m.attachments.length) { return ''; }
            return '<div class="mbx-read-atts"><div class="mbx-thread-title">' + esc(T.attachments) + '</div>' +
                m.attachments.map(function (a) {
                    var kb = a.size > 1048576 ? (a.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(a.size / 1024)) + ' KB';
                    return '<a class="mbx-att" href="' + U.attachment + '/' + a.id + '"><i class="fa fa-paperclip"></i> ' +
                        esc(a.file_name) + ' <span class="mbx-muted">' + kb + '</span></a>';
                }).join('') + '</div>';
        }

        function threadHtml(thread) {
            if (!thread.length) { return ''; }
            return '<div class="mbx-thread"><div class="mbx-thread-title">' + esc(T.threadEarlier) + ' (' + thread.length + ')</div>' +
                thread.map(function (t) {
                    return '<div class="mbx-thread-item" data-open="' + t.id + '">' +
                        '<i class="fa ' + (t.folder === 'sent' ? 'fa-paper-plane' : 'fa-envelope') + ' mbx-muted"></i>' +
                        '<strong>' + esc(t.from_name || t.from_email) + '</strong>' +
                        '<span class="mbx-muted mbx-ellipsis">' + esc(t.snippet || '') + '</span>' +
                        '<span class="mbx-row-date">' + esc(fmtDate(t.message_date)) + '</span></div>';
                }).join('') + '</div>';
        }

        /* ── Collision detection heartbeat ──────────────────────────────── */
        var presenceTimer = null;

        function startPresence(m) {
            stopPresence();
            if (!state.features.presence || !m || m.folder === 'drafts') { return; }

            presenceTimer = setInterval(function () {
                if (state.openId !== +m.id) { stopPresence(); return; }
                var replying = composer.style.display !== 'none' && +cState.replyToId === +m.id;
                post(U.presence, { message_id: m.id, state: replying ? 'replying' : 'viewing' }, function (res) {
                    var host = document.querySelector('#mbx-read-inner .mbx-presence');
                    var html = presenceHtml(res.others || []);
                    if (host) {
                        if (html) { host.outerHTML = html; } else { host.remove(); }
                    } else if (html) {
                        var bar = document.querySelector('#mbx-read-inner .mbx-read-toolbar');
                        if (bar) { bar.insertAdjacentHTML('afterend', html); }
                    }
                });
            }, 20000);
        }

        function stopPresence() {
            if (presenceTimer) { clearInterval(presenceTimer); presenceTimer = null; }
        }

        function leaveConversation() {
            stopPresence();
            if (state.openMsg && state.features.presence) {
                post(U.presence, { message_id: state.openMsg.id, state: 'gone' }, function () {});
            }
            state.openMsg = null;
        }

        /* ── Bulk / single actions ── */
        function doAction(ids, action, then) {
            if (action === 'delete' && !confirm(T.deleteConfirm)) { return; }
            post(U.action, { ids: ids, do: action }, function (res) {
                if (res && res.blocked > 0 && res.error) { toast(res.error); }
                if (then) { then(); }
                loadList(true);
            });
        }

        function selectedIds() {
            return Object.keys(state.selected).filter(function (k) { return state.selected[k]; });
        }

        /* Ids the next action applies to: the ticked rows, or the open one. */
        function targetIds() {
            var ids = selectedIds();
            return ids.length ? ids : (state.openId ? [String(state.openId)] : []);
        }

        /* ── Menus: assign / status / label / convert ────────────────────── */
        function assignMenu(anchor, ids) {
            var html = '<div class="mbx-pop-title">' + esc(T.assign) + '</div>' +
                '<button class="mbx-pop-item" data-pick="' + state.staffId + '"><i class="fa fa-user"></i> ' + esc(T.assignToMe) + '</button>' +
                '<button class="mbx-pop-item" data-pick="0"><i class="fa fa-user-slash"></i> ' + esc(T.unassigned) + '</button>' +
                '<div class="mbx-pop-sep"></div>' +
                state.staff.map(function (s) {
                    return '<button class="mbx-pop-item" data-pick="' + s.staffid + '">' +
                        '<span class="mbx-assignee">' + esc(initials(s.full_name)) + '</span> ' + esc(s.full_name) + '</button>';
                }).join('');

            pop(anchor, html, function (staffId) {
                closePop();
                post(U.assign, { ids: ids, staff_id: staffId }, function (res) {
                    if (!res.success && res.error) { toast(res.error); return; }
                    if (state.openId) { openMessage(state.openId); }
                    loadList(true);
                });
            });
        }

        function statusMenu(anchor, ids) {
            var options = [['open', T.statusOpen], ['pending', T.statusPending], ['closed', T.statusClosed]];
            var html = '<div class="mbx-pop-title">' + esc(T.status) + '</div>' +
                options.map(function (o) {
                    return '<button class="mbx-pop-item" data-pick="' + o[0] + '">' + statusDot(o[0]) + esc(o[1]) + '</button>';
                }).join('');

            pop(anchor, html, function (status) {
                closePop();
                post(U.setStatus, { ids: ids, status: status }, function () {
                    if (state.openId) { openMessage(state.openId); }
                    loadList(true);
                });
            });
        }

        function labelMenu(anchor, ids) {
            if (!state.labels.length) {
                pop(anchor, '<div class="mbx-pop-empty">' + esc(T.noLabels) + '</div>');
                return;
            }
            var html = '<div class="mbx-pop-title">' + esc(T.labels) + '</div>' +
                state.labels.map(function (l) {
                    return '<button class="mbx-pop-item" data-pick="' + l.id + '">' +
                        '<span class="mbx-label-dot" style="background:' + esc(l.color) + '"></span>' + esc(l.name) + '</button>';
                }).join('');

            pop(anchor, html, function (labelId) {
                closePop();
                post(U.applyLabel, { ids: ids, label_id: labelId, on: 1 }, function () {
                    if (state.openId) { openMessage(state.openId); }
                    loadList(true);
                    refreshLabels();
                });
            });
        }

        function convertMenu(anchor, messageId) {
            var html = '<div class="mbx-pop-title">' + esc(T.convert) + '</div>' +
                '<button class="mbx-pop-item" data-pick="lead"><i class="fa fa-filter"></i> ' + esc(T.convertLead) + '</button>' +
                '<button class="mbx-pop-item" data-pick="ticket"><i class="fa fa-life-ring"></i> ' + esc(T.convertTicket) + '</button>';

            pop(anchor, html, function (target) {
                closePop();
                post(U.convert, { message_id: messageId, target: target }, function (res) {
                    if (!res.success) { toast(res.error || T.loadError); return; }
                    toast(T.convertedTo + ' ' + target + ' #' + res.id);
                    window.open(res.url, '_blank');
                    openMessage(messageId);
                });
            });
        }

        function labelEditor(anchor, label) {
            var accountOptions = '<option value="0">' + esc(T.labelAllAccounts) + '</option>' +
                state.accounts.map(function (a) {
                    return '<option value="' + a.id + '"' + (label && +label.account_id === +a.id ? ' selected' : '') + '>' + esc(a.name) + '</option>';
                }).join('');

            var html = '<div class="mbx-pop-title">' + esc(label ? T.label : T.newLabel) + '</div>' +
                '<div class="mbx-pop-form">' +
                    '<label>' + esc(T.labelName) + '</label>' +
                    '<input type="text" id="mbx-label-name" value="' + esc(label ? label.name : '') + '">' +
                    '<label>' + esc(T.labelColor) + '</label>' +
                    '<input type="color" id="mbx-label-color" value="' + esc(label ? label.color : '#4f46e5') + '">' +
                    '<label>' + esc(T.labelScope) + '</label>' +
                    '<select id="mbx-label-account">' + accountOptions + '</select>' +
                    '<div class="mbx-pop-actions">' +
                        '<button class="mbx-btn mbx-btn-primary mbx-btn-sm" data-pick="save">' + esc(T.save) + '</button>' +
                        (label ? '<button class="mbx-btn mbx-btn-danger-light mbx-btn-sm" data-pick="delete">' + esc(T.del) + '</button>' : '') +
                    '</div>' +
                '</div>';

            pop(anchor, html, function (action) {
                if (action === 'delete') {
                    if (!confirm(T.labelDeleteConfirm)) { return; }
                    post(U.labelDelete, { id: label.id }, function () {
                        closePop();
                        if (+state.label === +label.id) { state.label = 0; }
                        refreshLabels();
                        loadList(true);
                    });
                    return;
                }

                post(U.labelSave, {
                    id: label ? label.id : 0,
                    name: document.getElementById('mbx-label-name').value,
                    color: document.getElementById('mbx-label-color').value,
                    account_id: document.getElementById('mbx-label-account').value
                }, function (res) {
                    if (!res.success) { toast(res.error || T.loadError); return; }
                    closePop();
                    refreshLabels();
                });
            });
        }

        function refreshLabels() {
            get(U.labels, function (res) {
                state.labels = res.labels || [];
                renderLabels();
            });
        }

        function searchHelp(anchor) {
            var html = '<div class="mbx-pop-title">' + esc(T.searchHelp) + '</div>' +
                '<div class="mbx-pop-hint">' + esc(T.searchHelpHint) + '</div>' +
                (B.operators || []).map(function (op) {
                    return '<button class="mbx-pop-item mbx-pop-code" data-pick="' + esc(op) + '"><code>' + esc(op) + '</code></button>';
                }).join('');

            pop(anchor, html, function (op) {
                var input = document.getElementById('mbx-search');
                input.value = (input.value.trim() + ' ' + op).trim();
                input.focus();
                closePop();
            });
        }

        /* ── Composer ── */
        var composer = document.getElementById('mbx-composer');
        var cFiles = [];                // File objects pending upload
        var cState = { draftId: 0, replyToId: 0, dirty: false, scheduleAt: '', dlpAck: false };

        function composerAccountSelect() {
            var sel = document.getElementById('mbx-c-account');
            sel.innerHTML = state.accounts.map(function (a) {
                return '<option value="' + a.id + '">' + esc(a.name) + ' — ' + esc(a.email) + '</option>';
            }).join('');
            if (state.account !== 'all') { sel.value = String(state.account); }
        }

        function openComposer(preset) {
            preset = preset || {};
            composerAccountSelect();
            composer.style.display = 'flex';
            composer.classList.remove('minimized');
            cFiles = [];
            renderComposerFiles();
            cState.draftId = preset.draftId || 0;
            cState.replyToId = preset.replyToId || 0;
            cState.dirty = false;
            cState.scheduleAt = '';
            cState.dlpAck = false;
            document.getElementById('mbx-dlp-warning').style.display = 'none';

            if (preset.account) { document.getElementById('mbx-c-account').value = String(preset.account); }
            document.getElementById('mbx-c-to').value = preset.to || '';
            document.getElementById('mbx-c-cc').value = preset.cc || '';
            document.getElementById('mbx-c-bcc').value = '';
            document.getElementById('mbx-row-cc').style.display = preset.cc ? 'flex' : 'none';
            document.getElementById('mbx-row-bcc').style.display = 'none';
            document.getElementById('mbx-c-subject').value = preset.subject || '';
            document.getElementById('mbx-c-status').textContent = '';
            updateSendButton();

            var body = preset.body;
            if (body === undefined) {
                var accId = document.getElementById('mbx-c-account').value;
                var sig = state.signatures[accId] || '';
                body = '<br><br>' + (sig ? '<div>-- <br>' + sig.replace(/\n/g, '<br>') + '</div>' : '');
            }
            document.getElementById('mbx-c-body').innerHTML = body;
            document.getElementById('mbx-c-to').focus();
        }

        function quoteBlock(m) {
            var inner = m.body_html && m.body_html.trim() !== '' ? m.body_html : esc(m.body_plain || '').replace(/\n/g, '<br>');
            return '<br><br><blockquote>' +
                'On ' + esc(m.message_date || '') + ', ' + esc(m.from_name || m.from_email) + ' wrote:<br>' + inner +
                '</blockquote>';
        }

        function openReply(m, all) {
            var to = m.folder === 'sent' ? parseAddrEmails(m.to_emails).join(', ') : (m.reply_to || m.from_email);
            var cc = '';
            if (all) {
                var mine = accountEmail(m.account_id);
                cc = parseAddrEmails(m.to_emails).concat(parseAddrEmails(m.cc_emails))
                    .filter(function (e) { return e && e !== to && e !== mine; })
                    .filter(function (e, i, arr) { return arr.indexOf(e) === i; })
                    .join(', ');
            }
            openComposer({
                account: m.account_id,
                to: to,
                cc: cc,
                subject: (/^re:/i.test(m.subject || '') ? m.subject : 'Re: ' + (m.subject || '')),
                body: '<br><br>' + signatureFor(m.account_id) + quoteBlock(m),
                replyToId: m.id
            });
        }

        function openForward(m) {
            openComposer({
                account: m.account_id,
                subject: (/^fwd?:/i.test(m.subject || '') ? m.subject : 'Fwd: ' + (m.subject || '')),
                body: '<br><br>' + signatureFor(m.account_id) + quoteBlock(m),
                replyToId: m.id
            });
        }

        function openDraft(m) {
            openComposer({
                account: m.account_id,
                to: parseAddrEmails(m.to_emails).join(', '),
                cc: parseAddrEmails(m.cc_emails).join(', '),
                subject: m.subject === null ? '' : m.subject,
                body: m.body_html || '',
                draftId: m.id
            });
        }

        function signatureFor(accId) {
            var sig = state.signatures[accId] || '';
            return sig ? '<div>-- <br>' + sig.replace(/\n/g, '<br>') + '</div>' : '';
        }

        function accountEmail(id) {
            var a = state.accounts.filter(function (x) { return String(x.id) === String(id); })[0];
            return a ? a.email : '';
        }

        function renderComposerFiles() {
            document.getElementById('mbx-c-attachments').innerHTML = cFiles.map(function (f, i) {
                return '<span class="mbx-c-att"><i class="fa fa-paperclip"></i> ' + esc(f.name) +
                    ' <button type="button" data-rmfile="' + i + '"><i class="fa fa-xmark"></i></button></span>';
            }).join('');
        }

        function updateSendButton() {
            var btn = document.getElementById('mbx-c-send');
            if (cState.scheduleAt) {
                btn.innerHTML = '<i class="fa fa-clock"></i> ' + esc(T.sendLater) + ' — ' + esc(fmtDateTime(cState.scheduleAt));
                btn.classList.add('mbx-btn-scheduled');
            } else {
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> ' + esc(T.send || 'Send');
                btn.classList.remove('mbx-btn-scheduled');
            }
        }

        function composePayload() {
            var fd = new FormData();
            fd.append('account_id', document.getElementById('mbx-c-account').value);
            fd.append('to', document.getElementById('mbx-c-to').value);
            fd.append('cc', document.getElementById('mbx-c-cc').value);
            fd.append('bcc', document.getElementById('mbx-c-bcc').value);
            fd.append('subject', document.getElementById('mbx-c-subject').value);
            fd.append('body', document.getElementById('mbx-c-body').innerHTML);
            fd.append('draft_id', cState.draftId);
            fd.append('reply_to_id', cState.replyToId);
            if (cState.scheduleAt) { fd.append('schedule_at', cState.scheduleAt); }
            if (cState.dlpAck) { fd.append('dlp_ack', '1'); }
            cFiles.forEach(function (f) { fd.append('attachments[]', f, f.name); });
            return fd;
        }

        function showDlpWarning(hits) {
            var el = document.getElementById('mbx-dlp-warning');
            el.style.display = 'block';
            el.innerHTML = '<i class="fa fa-shield-halved"></i> <strong>' + esc(T.dlpWarning) + ':</strong> ' +
                hits.map(function (h) { return '<code>' + esc(h) + '</code>'; }).join(', ') +
                ' <button class="mbx-btn mbx-btn-light mbx-btn-sm" id="mbx-dlp-ack">' + esc(T.dlpSendAnyway) + '</button>';
        }

        function sendMail() {
            var btn = document.getElementById('mbx-c-send');
            var status = document.getElementById('mbx-c-status');
            btn.disabled = true;
            status.textContent = T.sending;

            post(U.send, composePayload(), function (res) {
                btn.disabled = false;

                if (!res.success) {
                    if (res.dlp && !res.dlp.blocked) {
                        status.textContent = '';
                        showDlpWarning(res.dlp.hits || []);
                        return;
                    }
                    status.textContent = res.error || T.loadError;
                    return;
                }

                composer.style.display = 'none';
                cState.dlpAck = false;

                if (res.queued && res.scheduled) {
                    toast(T.scheduleQueued + ' — ' + fmtDateTime(res.scheduled_at));
                } else if (res.queued) {
                    holdWithUndo(res.id, res.undo_seconds);
                } else {
                    toast(T.sentOk);
                }
                loadList(true);
            }, function () {
                btn.disabled = false;
                status.textContent = T.loadError;
            });
        }

        /* Undo-send: the message is already queued server side, so all the
           browser does is count down and then ask the server to flush it. If
           the tab dies the cron tick sends it anyway. */
        var undoTimer = null;

        function holdWithUndo(messageId, seconds) {
            var el = document.getElementById('mbx-toast');
            var left = Math.max(1, seconds || 1);
            clearInterval(undoTimer);

            function paint() {
                el.innerHTML = esc(T.sendingIn) + ' ' + left + 's ' +
                    '<button class="mbx-toast-btn" id="mbx-undo-btn">' + esc(T.undo) + '</button>';
                el.style.display = 'block';
            }
            paint();

            undoTimer = setInterval(function () {
                left--;
                if (left > 0) { paint(); return; }

                clearInterval(undoTimer);
                el.style.display = 'none';
                el.onclick = null;
                post(U.dispatch, { account: state.account }, function () {
                    toast(T.sentOk);
                    loadList(true);
                });
            }, 1000);

            el.onclick = function (e) {
                if (!e.target.closest('#mbx-undo-btn')) { return; }
                clearInterval(undoTimer);
                el.style.display = 'none';
                el.onclick = null;
                post(U.cancelSchedule, { ids: [messageId] }, function () {
                    toast(T.sendUndone);
                    loadList(true);
                });
            };
        }

        function saveDraft(silent) {
            var fd = composePayload();
            fd.delete('schedule_at');
            post(U.draft, fd, function (res) {
                if (res.success) {
                    cState.draftId = res.id;
                    cState.dirty = false;
                    if (!silent) { toast(T.draftSaved); }
                    loadList(true);
                }
            });
        }

        /* ── Send later menu ── */
        function scheduleMenu(anchor) {
            function slot(date) {
                var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
                return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) +
                    ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
            }

            var now = new Date();
            var inHour = new Date(now.getTime() + 3600000);
            var tomorrow = new Date(now.getTime() + 86400000); tomorrow.setHours(9, 0, 0, 0);
            var monday = new Date(now.getTime());
            monday.setDate(monday.getDate() + ((8 - monday.getDay()) % 7 || 7));
            monday.setHours(9, 0, 0, 0);

            var html = '<div class="mbx-pop-title">' + esc(T.sendLater) + '</div>' +
                '<button class="mbx-pop-item" data-pick="' + slot(inHour) + '"><i class="fa fa-hourglass-half"></i> +1h — ' + esc(fmtDateTime(slot(inHour))) + '</button>' +
                '<button class="mbx-pop-item" data-pick="' + slot(tomorrow) + '"><i class="fa fa-sun"></i> ' + esc(fmtDateTime(slot(tomorrow))) + '</button>' +
                '<button class="mbx-pop-item" data-pick="' + slot(monday) + '"><i class="fa fa-calendar-days"></i> ' + esc(fmtDateTime(slot(monday))) + '</button>' +
                '<div class="mbx-pop-sep"></div>' +
                '<div class="mbx-pop-form">' +
                    '<label>' + esc(T.schedulePick) + '</label>' +
                    '<input type="datetime-local" id="mbx-schedule-input">' +
                    '<div class="mbx-pop-actions">' +
                        '<button class="mbx-btn mbx-btn-primary mbx-btn-sm" data-pick="custom">' + esc(T.save) + '</button>' +
                        (cState.scheduleAt ? '<button class="mbx-btn mbx-btn-light mbx-btn-sm" data-pick="clear">' + esc(T.cancel) + '</button>' : '') +
                    '</div>' +
                '</div>';

            pop(anchor, html, function (value) {
                if (value === 'clear') { cState.scheduleAt = ''; }
                else if (value === 'custom') {
                    var raw = document.getElementById('mbx-schedule-input').value;
                    if (!raw) { return; }
                    cState.scheduleAt = raw.replace('T', ' ');
                } else {
                    cState.scheduleAt = value;
                }
                closePop();
                updateSendButton();
            });
        }

        /* ── Canned responses ── */
        function templateMenu(anchor) {
            if (!state.templates.length) {
                pop(anchor, '<div class="mbx-pop-empty">' + esc(T.noTemplates) + '</div>');
                return;
            }

            var html = '<div class="mbx-pop-title">' + esc(T.templates) + '</div>' +
                state.templates.map(function (t) {
                    return '<button class="mbx-pop-item" data-pick="' + t.id + '">' +
                        '<i class="fa fa-bolt"></i> <span class="mbx-ellipsis">' + esc(t.name) + '</span>' +
                        (+t.is_shared ? '' : ' <span class="mbx-chip mbx-chip-muted mbx-chip-xs">' + esc(T.templatePrivate) + '</span>') +
                        '</button>';
                }).join('');

            pop(anchor, html, function (id) {
                closePop();
                var accountId = document.getElementById('mbx-c-account').value;
                var to = document.getElementById('mbx-c-to').value.split(',')[0].trim();

                get(U.templateUse + '/' + id +
                    '?contact_email=' + encodeURIComponent(to) +
                    '&contact_name=' + encodeURIComponent(to.split('@')[0] || '') +
                    '&subject=' + encodeURIComponent(document.getElementById('mbx-c-subject').value) +
                    '&account_email=' + encodeURIComponent(accountEmail(accountId)), function (res) {
                    if (!res.success) { return; }
                    var subjectEl = document.getElementById('mbx-c-subject');
                    if (res.subject && !subjectEl.value.trim()) { subjectEl.value = res.subject; }
                    var body = document.getElementById('mbx-c-body');
                    body.innerHTML = res.body + body.innerHTML;
                    cState.dirty = true;
                });
            });
        }

        /* ── Recipient autocomplete ─────────────────────────────────────── */
        var acTimer = null, acBox = null;

        function closeAutocomplete() {
            if (acBox) { acBox.remove(); acBox = null; }
        }

        function autocomplete(input) {
            var value = input.value;
            var token = value.split(/[,;]/).pop().trim();

            if (token.length < 2) { closeAutocomplete(); return; }

            clearTimeout(acTimer);
            acTimer = setTimeout(function () {
                get(U.contacts + '?q=' + encodeURIComponent(token), function (res) {
                    var results = res.results || [];
                    closeAutocomplete();
                    if (!results.length) { return; }

                    acBox = document.createElement('div');
                    acBox.className = 'mbx-ac';
                    acBox.innerHTML = results.map(function (r) {
                        return '<button type="button" class="mbx-ac-item" data-email="' + esc(r.email) + '">' +
                            '<span class="mbx-assignee">' + esc(initials(r.name || r.email)) + '</span>' +
                            '<span class="mbx-ac-main"><strong>' + esc(r.name || r.email) + '</strong>' +
                            '<span class="mbx-muted mbx-small">' + esc(r.email) + '</span></span>' +
                            '<span class="mbx-chip mbx-chip-muted mbx-chip-xs">' + esc(r.source) + '</span></button>';
                    }).join('');

                    input.parentElement.appendChild(acBox);

                    acBox.addEventListener('click', function (e) {
                        var item = e.target.closest('[data-email]');
                        if (!item) { return; }
                        var parts = input.value.split(/[,;]/);
                        parts[parts.length - 1] = ' ' + item.dataset.email;
                        input.value = parts.join(',').replace(/^\s+/, '') + ', ';
                        closeAutocomplete();
                        input.focus();
                    });
                });
            }, 250);
        }

        /* ── Sync ── */
        function syncNow(force) {
            var icon = document.querySelector('#mbx-refresh i');
            icon.classList.add('fa-spin');
            post(U.sync, { account: state.account, force: force ? 1 : 0 }, function (res) {
                icon.classList.remove('fa-spin');
                if (res.imported > 0) { toast(res.imported + ' ' + T.newMail); }
                if (res.errors && res.errors.length) { toast(res.errors[0]); }
                loadList(true);
            }, function () { icon.classList.remove('fa-spin'); });
        }

        /* ── Events ── */
        document.getElementById('mbx-account-list').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-account]');
            if (!btn) { return; }
            state.account = btn.dataset.account === 'all' ? 'all' : +btn.dataset.account;
            state.page = 1;
            renderAccounts();
            renderBadges();
            loadList();
        });

        document.getElementById('mbx-folders').addEventListener('click', function (e) {
            var link = e.target.closest('[data-folder]');
            if (!link) { return; }
            document.querySelectorAll('#mbx-folders .mbx-folder').forEach(function (f) { f.classList.remove('active'); });
            document.querySelectorAll('#mbx-label-list .mbx-label-item').forEach(function (f) { f.classList.remove('active'); });
            link.classList.add('active');
            state.folder = link.dataset.folder;
            state.label = 0;
            state.page = 1;
            leaveConversation();
            app.classList.remove('mbx-reading');
            state.openId = 0;
            updateFacetVisibility();
            loadList();
        });

        document.getElementById('mbx-label-list').addEventListener('click', function (e) {
            var editBtn = e.target.closest('[data-label-edit]');
            if (editBtn) {
                e.stopPropagation();
                var label = state.labels.filter(function (l) { return String(l.id) === editBtn.dataset.labelEdit; })[0];
                if (label) { labelEditor(editBtn, label); }
                return;
            }

            var item = e.target.closest('[data-label]');
            if (!item) { return; }
            document.querySelectorAll('#mbx-folders .mbx-folder').forEach(function (f) { f.classList.remove('active'); });
            state.label = +item.dataset.label;
            state.folder = 'inbox';
            state.page = 1;
            renderLabels();
            updateFacetVisibility();
            loadList();
        });

        var labelNewBtn = document.getElementById('mbx-label-new');
        if (labelNewBtn) {
            labelNewBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                labelEditor(labelNewBtn, null);
            });
        }

        document.getElementById('mbx-facets').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-status]');
            if (!btn) { return; }
            document.querySelectorAll('#mbx-facets .mbx-facet').forEach(function (f) { f.classList.remove('active'); });
            btn.classList.add('active');
            state.status = btn.dataset.status;
            state.page = 1;
            loadList();
        });

        document.getElementById('mbx-rows').addEventListener('click', function (e) {
            var star = e.target.closest('[data-star]');
            if (star) {
                e.stopPropagation();
                var on = star.classList.toggle('on');
                star.innerHTML = '<i class="fa' + (on ? '' : '-regular') + ' fa-star"></i>';
                doAction([star.dataset.star], on ? 'star' : 'unstar');
                return;
            }
            var check = e.target.closest('[data-check]');
            if (check) {
                e.stopPropagation();
                state.selected[check.dataset.check] = check.checked;
                document.getElementById('mbx-bulk').style.display = selectedIds().length ? 'flex' : 'none';
                return;
            }
            var row = e.target.closest('.mbx-row');
            if (row) {
                var msg = state.rows.filter(function (m) { return String(m.id) === row.dataset.id; })[0];
                if (msg && msg.folder === 'drafts') {
                    get(U.message + '/' + msg.id, function (res) { if (res.message) { openDraft(res.message); } });
                } else {
                    openMessage(row.dataset.id);
                }
            }
        });

        document.getElementById('mbx-select-all').addEventListener('change', function () {
            var on = this.checked;
            document.querySelectorAll('#mbx-rows [data-check]').forEach(function (c) {
                c.checked = on;
                state.selected[c.dataset.check] = on;
            });
            document.getElementById('mbx-bulk').style.display = on ? 'flex' : 'none';
        });

        document.getElementById('mbx-bulk').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-bulk]');
            var ids = selectedIds();
            if (btn) {
                if (ids.length) { doAction(ids, btn.dataset.bulk); }
                return;
            }
            if (!ids.length) { return; }

            if (e.target.closest('#mbx-bulk-assign')) { assignMenu(e.target.closest('button'), ids); }
            if (e.target.closest('#mbx-bulk-label')) { labelMenu(e.target.closest('button'), ids); }
            if (e.target.closest('#mbx-bulk-status')) { statusMenu(e.target.closest('button'), ids); }
        });

        document.getElementById('mbx-read-pane').addEventListener('click', function (e) {
            /* Work bar menus */
            var work = e.target.closest('[data-work]');
            if (work) {
                e.stopPropagation();
                var ids = [String(state.openId)];
                if (work.dataset.work === 'assign') { assignMenu(work, ids); }
                if (work.dataset.work === 'status') { statusMenu(work, ids); }
                if (work.dataset.work === 'label') { labelMenu(work, ids); }
                if (work.dataset.work === 'convert') { convertMenu(work, state.openId); }
                return;
            }

            var removeLabel = e.target.closest('[data-remove-label]');
            if (removeLabel) {
                post(U.applyLabel, { ids: [state.openId], label_id: removeLabel.dataset.removeLabel, on: 0 }, function () {
                    openMessage(state.openId);
                    refreshLabels();
                });
                return;
            }

            /* Internal notes */
            if (e.target.closest('#mbx-note-add')) {
                var input = document.getElementById('mbx-note-input');
                var text = input.value.trim();
                if (!text) { return; }
                post(U.noteAdd, {
                    message_id: state.openId,
                    note: text,
                    mentions: mentionedStaffIds(text)
                }, function (res) {
                    if (!res.success) { toast(res.error || T.loadError); return; }
                    input.value = '';
                    var host = document.getElementById('mbx-notes');
                    if (host) { host.outerHTML = notesHtml(res.notes || []); }
                    loadList(true);
                });
                return;
            }

            var noteDel = e.target.closest('[data-note-del]');
            if (noteDel) {
                if (!confirm(T.noteDeleteConfirm)) { return; }
                post(U.noteDelete, { id: noteDel.dataset.noteDel }, function () {
                    get(U.notes + '/' + state.openId, function (res) {
                        var host = document.getElementById('mbx-notes');
                        if (host) { host.outerHTML = notesHtml(res.notes || []); }
                    });
                });
                return;
            }

            var open = e.target.closest('[data-open]');
            if (open) { openMessage(open.dataset.open); return; }

            var act = e.target.closest('[data-msg-act]');
            if (!act) { return; }
            var id = act.dataset.msgId;

            if (['reply', 'replyall', 'forward', 'editdraft'].indexOf(act.dataset.msgAct) !== -1) {
                get(U.message + '/' + id, function (res) {
                    if (!res.message) { return; }
                    if (act.dataset.msgAct === 'reply') { openReply(res.message, false); }
                    if (act.dataset.msgAct === 'replyall') { openReply(res.message, true); }
                    if (act.dataset.msgAct === 'forward') { openForward(res.message); }
                    if (act.dataset.msgAct === 'editdraft') { openDraft(res.message); }
                });
                return;
            }

            if (act.dataset.msgAct === 'cancelschedule') {
                post(U.cancelSchedule, { ids: [id] }, function () {
                    get(U.message + '/' + id, function (res) {
                        if (res.message) { openDraft(res.message); }
                        loadList(true);
                    });
                });
                return;
            }

            if (act.dataset.msgAct === 'hold') {
                var on = state.openMsg && +state.openMsg.legal_hold ? 0 : 1;
                post(U.legalHold, { ids: [id], on: on }, function () {
                    openMessage(id);
                    loadList(true);
                });
                return;
            }

            doAction([id], act.dataset.msgAct, function () {
                leaveConversation();
                app.classList.remove('mbx-reading');
                document.getElementById('mbx-read-inner').style.display = 'none';
                document.getElementById('mbx-read-empty').style.display = 'block';
                state.openId = 0;
            });
        });

        /**
         * "@Firstname Lastname" in a note → the staff ids to notify. Matching
         * on the known assignable staff keeps it simple and avoids a lookup.
         */
        function mentionedStaffIds(text) {
            var lower = text.toLowerCase();
            return state.staff.filter(function (s) {
                var name = (s.full_name || '').trim().toLowerCase();
                if (!name) { return false; }
                return lower.indexOf('@' + name) !== -1 ||
                    lower.indexOf('@' + name.split(' ')[0]) !== -1;
            }).map(function (s) { return s.staffid; });
        }

        var searchTimer;
        document.getElementById('mbx-search').addEventListener('input', function () {
            clearTimeout(searchTimer);
            var v = this.value;
            searchTimer = setTimeout(function () {
                state.search = v.trim();
                state.page = 1;
                loadList();
            }, 350);
        });

        document.getElementById('mbx-search-help').addEventListener('click', function (e) {
            e.stopPropagation();
            searchHelp(this);
        });

        document.getElementById('mbx-refresh').addEventListener('click', function () { syncNow(true); });
        document.getElementById('mbx-prev').addEventListener('click', function () {
            if (state.page > 1) { state.page--; loadList(); }
        });
        document.getElementById('mbx-next').addEventListener('click', function () {
            if (state.page < state.totalPages) { state.page++; loadList(); }
        });

        /* composer events */
        document.getElementById('mbx-compose-open').addEventListener('click', function () { openComposer(); });
        document.getElementById('mbx-composer-min').addEventListener('click', function (e) {
            e.stopPropagation();
            composer.classList.toggle('minimized');
        });
        document.querySelector('.mbx-composer-head').addEventListener('click', function () {
            composer.classList.remove('minimized');
        });
        document.getElementById('mbx-composer-close').addEventListener('click', function (e) {
            e.stopPropagation();
            if (cState.dirty) { saveDraft(true); }
            composer.style.display = 'none';
        });
        document.getElementById('mbx-c-discard').addEventListener('click', function () {
            if (cState.draftId > 0) {
                // Discarding a saved draft removes it without the
                // delete-forever confirmation — it never left the composer.
                post(U.action, { ids: [cState.draftId], do: 'delete' }, function () { loadList(true); });
            }
            composer.style.display = 'none';
        });
        document.getElementById('mbx-c-send').addEventListener('click', sendMail);
        document.getElementById('mbx-c-draft').addEventListener('click', function () { saveDraft(false); });
        document.getElementById('mbx-c-schedule').addEventListener('click', function (e) {
            e.stopPropagation();
            scheduleMenu(this);
        });
        document.getElementById('mbx-c-template').addEventListener('click', function (e) {
            e.stopPropagation();
            templateMenu(this);
        });
        document.getElementById('mbx-show-cc').addEventListener('click', function () {
            document.getElementById('mbx-row-cc').style.display = 'flex';
        });
        document.getElementById('mbx-show-bcc').addEventListener('click', function () {
            document.getElementById('mbx-row-bcc').style.display = 'flex';
        });
        document.getElementById('mbx-composer-body').addEventListener('input', function () { cState.dirty = true; });
        document.getElementById('mbx-dlp-warning').addEventListener('click', function (e) {
            if (!e.target.closest('#mbx-dlp-ack')) { return; }
            cState.dlpAck = true;
            this.style.display = 'none';
            sendMail();
        });
        ['mbx-c-to', 'mbx-c-cc', 'mbx-c-bcc'].forEach(function (id) {
            var input = document.getElementById(id);
            input.addEventListener('input', function () { autocomplete(this); });
            input.addEventListener('blur', function () { setTimeout(closeAutocomplete, 200); });
        });
        document.querySelector('.mbx-editor-tools').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-cmd]');
            if (!btn) { return; }
            var cmd = btn.dataset.cmd;
            if (cmd === 'createLink') {
                var url = prompt('URL:', 'https://');
                if (url) { document.execCommand(cmd, false, url); }
            } else {
                document.execCommand(cmd, false, null);
            }
            document.getElementById('mbx-c-body').focus();
        });
        document.getElementById('mbx-c-files').addEventListener('change', function () {
            Array.prototype.slice.call(this.files).forEach(function (f) {
                if (f.size <= 15 * 1024 * 1024) { cFiles.push(f); }
            });
            this.value = '';
            renderComposerFiles();
            cState.dirty = true;
        });
        document.getElementById('mbx-c-attachments').addEventListener('click', function (e) {
            var rm = e.target.closest('[data-rmfile]');
            if (rm) { cFiles.splice(+rm.dataset.rmfile, 1); renderComposerFiles(); }
        });

        /* ── Keyboard shortcuts ─────────────────────────────────────────── */
        var shortcutsModal = document.getElementById('mbx-shortcuts');

        function toggleShortcuts(show) {
            shortcutsModal.style.display = show ? 'flex' : 'none';
        }

        document.getElementById('mbx-shortcuts-open').addEventListener('click', function () { toggleShortcuts(true); });
        shortcutsModal.addEventListener('click', function (e) {
            if (e.target === shortcutsModal || e.target.closest('[data-close-modal]')) { toggleShortcuts(false); }
        });

        function typingInto(el) {
            if (!el) { return false; }
            return el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable;
        }

        function moveSelection(delta) {
            if (!state.rows.length) { return; }
            var index = state.rows.findIndex(function (m) { return +m.id === state.openId; });
            var next = Math.min(state.rows.length - 1, Math.max(0, (index === -1 ? -1 : index) + delta));
            openMessage(state.rows[next].id);
            var row = document.querySelector('.mbx-row[data-id="' + state.rows[next].id + '"]');
            if (row) { row.scrollIntoView({ block: 'nearest' }); }
        }

        document.addEventListener('keydown', function (e) {
            // Ctrl/Cmd+Enter sends from inside the composer — the one shortcut
            // that must work while typing.
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && composer.style.display !== 'none') {
                e.preventDefault();
                sendMail();
                return;
            }
            if (e.ctrlKey || e.metaKey || e.altKey || typingInto(document.activeElement)) { return; }

            var key = e.key;
            var open = state.openId;

            switch (key) {
                case 'c': e.preventDefault(); openComposer(); break;
                case '/': e.preventDefault(); document.getElementById('mbx-search').focus(); break;
                case '?': e.preventDefault(); toggleShortcuts(shortcutsModal.style.display === 'none'); break;
                case 'j': e.preventDefault(); moveSelection(1); break;
                case 'k': e.preventDefault(); moveSelection(-1); break;
                case 'Escape':
                    closePop();
                    if (shortcutsModal.style.display !== 'none') { toggleShortcuts(false); }
                    break;
                default: break;
            }

            if (!open) { return; }

            switch (key) {
                case 'r':
                case 'a':
                case 'f':
                    e.preventDefault();
                    get(U.message + '/' + open, function (res) {
                        if (!res.message) { return; }
                        if (key === 'r') { openReply(res.message, false); }
                        if (key === 'a') { openReply(res.message, true); }
                        if (key === 'f') { openForward(res.message); }
                    });
                    break;
                case 'e': e.preventDefault(); doAction([open], 'archive', closeReading); break;
                case '#': e.preventDefault(); doAction([open], 'trash', closeReading); break;
                case 'u': e.preventDefault(); doAction([open], 'unread', closeReading); break;
                case 's':
                    e.preventDefault();
                    var row = state.rows.filter(function (m) { return +m.id === open; })[0];
                    doAction([open], row && +row.is_starred ? 'unstar' : 'star');
                    break;
                case 'y':
                    e.preventDefault();
                    var anchor = document.querySelector('#mbx-read-inner [data-work="assign"]');
                    if (anchor) { assignMenu(anchor, [String(open)]); }
                    break;
                case 'n':
                    e.preventDefault();
                    var note = document.getElementById('mbx-note-input');
                    if (note) { note.focus(); note.scrollIntoView({ block: 'center' }); }
                    break;
                case 'x':
                    e.preventDefault();
                    if (state.features.status) {
                        post(U.setStatus, { ids: [open], status: 'closed' }, function () {
                            openMessage(open);
                            loadList(true);
                        });
                    }
                    break;
                default: break;
            }
        });

        function closeReading() {
            leaveConversation();
            app.classList.remove('mbx-reading');
            document.getElementById('mbx-read-inner').style.display = 'none';
            document.getElementById('mbx-read-empty').style.display = 'block';
            state.openId = 0;
        }

        /* ── Boot ── */
        get(U.bootstrap, function (res) {
            state.accounts = res.accounts || [];
            state.counts = res.counts || {};
            state.signatures = res.signatures || {};
            state.labels = res.labels || [];
            state.templates = res.templates || [];
            state.staff = res.staff || [];
            state.staffId = res.staff_id || 0;
            state.features = res.features || state.features;

            renderAccounts();
            renderLabels();
            renderBadges();
            applyFeatureVisibility();
            fitHeight();     // banners/sidebar are final by now
            loadList();

            // Deep link from a notification: ?open=<message id>
            var deepLink = (window.location.search.match(/[?&]open=(\d+)/) || [])[1];
            if (deepLink) { openMessage(deepLink); }

            // Background: server-side throttled sync + list refresh.
            syncNow(false);
            // An IMAP pull for a mailbox nobody is watching is pure cost —
            // hold off while the tab is hidden and sync as soon as it returns.
            setInterval(function () { if (!document.hidden) { syncNow(false); } }, 90000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) { syncNow(false); }
            });
        });
    }

    /* ═══════════════════ ACCOUNT MANAGER + WIZARD ════════════════════════ */
    var adminRoot = document.getElementById('mbx-accounts');
    if (adminRoot && window.MBX_ADMIN_BOOT) {
        var AB = window.MBX_ADMIN_BOOT, AU = AB.urls, AT = AB.i18n;
        var wizard = document.getElementById('mbx-wizard');
        var form = document.getElementById('mbx-wizard-form');
        var step = 1, maxStep = 5;

        function setVal(name, value) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) { return; }
            if (el.type === 'checkbox') { el.checked = !!+value; } else { el.value = value == null ? '' : value; }
        }
        function getVal(name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) { return ''; }
            return el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value;
        }

        function goStep(n) {
            step = Math.min(Math.max(n, 1), maxStep);
            wizard.querySelectorAll('.mbx-wpage').forEach(function (p) {
                p.style.display = +p.dataset.page === step ? 'block' : 'none';
            });
            wizard.querySelectorAll('.mbx-wstep').forEach(function (s) {
                var num = +s.dataset.step;
                s.classList.toggle('active', num === step);
                s.classList.toggle('done', num < step);
            });
            document.getElementById('mbx-wizard-back').style.display = step > 1 ? 'inline-flex' : 'none';
            document.getElementById('mbx-wizard-next').style.display = step < maxStep ? 'inline-flex' : 'none';
            document.getElementById('mbx-wizard-finish').style.display = step === maxStep ? 'inline-flex' : 'none';
        }

        function applyPreset(key) {
            var p = AB.presets[key];
            if (!p) { return; }
            form.querySelector('[name="provider"]').value = key;
            wizard.querySelectorAll('.mbx-provider').forEach(function (b) {
                b.classList.toggle('active', b.dataset.provider === key);
            });
            setVal('smtp_host', p.smtp.host);
            setVal('smtp_port', p.smtp.port);
            setVal('smtp_encryption', p.smtp.encryption);
            setVal('imap_host', p.imap.host);
            setVal('imap_port', p.imap.port);
            setVal('imap_encryption', p.imap.encryption);
            setVal('imap_sent_folder', p.sent_folder);

            var guide = document.getElementById('mbx-guide');
            guide.style.display = 'block';
            document.getElementById('mbx-guide-steps').innerHTML = (p.steps || []).map(function (s) {
                return '<li>' + esc(s) + '</li>';
            }).join('');
            var appPw = document.getElementById('mbx-guide-apppw');
            if (p.app_password_url) {
                appPw.style.display = 'inline-flex';
                appPw.href = p.app_password_url;
            } else {
                appPw.style.display = 'none';
            }
        }

        function openWizard(account, assigned) {
            form.reset();
            form.querySelector('[name="id"]').value = account ? account.id : 0;
            document.getElementById('mbx-wizard-title').textContent = account ? AT.editTitle : AT.connectTitle;
            document.getElementById('mbx-test-smtp-result').textContent = '';
            document.getElementById('mbx-test-smtp-result').className = 'mbx-test-result';
            document.getElementById('mbx-test-imap-result').textContent = '';
            document.getElementById('mbx-test-imap-result').className = 'mbx-test-result';
            document.getElementById('mbx-folder-list').innerHTML = '';
            document.getElementById('mbx-guide').style.display = 'none';
            wizard.querySelectorAll('.mbx-provider').forEach(function (b) { b.classList.remove('active'); });
            wizard.querySelectorAll('.mbx-pw-keep').forEach(function (el) { el.style.display = account ? 'block' : 'none'; });
            form.querySelectorAll('[name="staff_ids[]"]').forEach(function (c) {
                c.checked = assigned ? assigned.indexOf(+c.value) !== -1 : false;
            });
            setVal('active', account ? account.active : 1);
            setVal('imap_folder', account ? account.imap_folder : 'INBOX');
            setVal('smtp_port', account ? account.smtp_port : 465);
            setVal('imap_port', account ? account.imap_port : 993);

            if (account) {
                ['name', 'email', 'from_name', 'signature', 'smtp_host', 'smtp_encryption', 'smtp_username',
                 'imap_host', 'imap_encryption', 'imap_username', 'imap_sent_folder'].forEach(function (f) {
                    setVal(f, account[f]);
                });
                setVal('imap_validate_cert', account.imap_validate_cert);
                if (account.provider && AB.presets[account.provider]) {
                    form.querySelector('[name="provider"]').value = account.provider;
                    wizard.querySelectorAll('.mbx-provider').forEach(function (b) {
                        b.classList.toggle('active', b.dataset.provider === account.provider);
                    });
                }
            }

            goStep(account ? 2 : 1);
            wizard.style.display = 'flex';
        }

        function testFormData() {
            var fd = new FormData();
            ['id', 'email', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
             'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password'].forEach(function (f) {
                fd.append(f, getVal(f));
            });
            fd.append('imap_validate_cert', getVal('imap_validate_cert') ? 1 : 0);
            return fd;
        }

        function runTest(kind) {
            var result = document.getElementById('mbx-test-' + kind + '-result');
            result.className = 'mbx-test-result';
            result.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i> ' + esc(AT.testing);

            post(kind === 'smtp' ? AU.testSmtp : AU.testImap, testFormData(), function (res) {
                if (res.success) {
                    result.className = 'mbx-test-result ok';
                    result.innerHTML = '<i class="fa fa-circle-check"></i> ' + esc(AT.testOk);
                    if (kind === 'imap' && res.folders && res.folders.length) {
                        var dl = document.getElementById('mbx-folder-options');
                        dl.innerHTML = res.folders.map(function (f) { return '<option value="' + esc(f) + '">'; }).join('');
                        document.getElementById('mbx-folder-list').innerHTML =
                            '<span class="mbx-folder-chips-label">' + esc(AT.foldersFound) + ' — click to use as Sent folder:</span>' +
                            res.folders.map(function (f) {
                                return '<span class="mbx-chip mbx-chip-muted" data-usefolder="' + esc(f) + '">' + esc(f) + '</span>';
                            }).join('');
                    }
                } else {
                    result.className = 'mbx-test-result fail';
                    result.innerHTML = '<i class="fa fa-circle-xmark"></i> ' + esc(AT.testFailed) + ': ' + esc(res.error || '');
                    if (res.debug && res.debug.length) {
                        result.innerHTML +=
                            '<details class="mbx-test-debug"><summary>' + esc(AT.testDebug) + '</summary><pre>' +
                            res.debug.map(esc).join('\n') + '</pre></details>';
                    }
                }
            }, function () {
                result.className = 'mbx-test-result fail';
                result.textContent = AT.loadError;
            });
        }

        function saveAccount() {
            var status = document.getElementById('mbx-wizard-status');
            var btn = document.getElementById('mbx-wizard-finish');
            btn.disabled = true;
            status.textContent = '…';

            var fd = new FormData(form);
            // Unchecked staff boxes / active toggle need explicit handling:
            // FormData skips them, the backend treats a missing staff_ids as
            // "no change" — so always send at least an empty marker.
            if (!fd.has('staff_ids[]')) { fd.append('staff_ids[]', ''); }
            if (!getVal('active')) { fd.append('active', ''); }

            post(AU.save, fd, function (res) {
                btn.disabled = false;
                if (res.success) {
                    toast(AT.saved);
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    status.textContent = res.error || AT.loadError;
                }
            }, function () {
                btn.disabled = false;
                status.textContent = AT.loadError;
            });
        }

        /* wizard events */
        document.getElementById('mbx-wizard-open').addEventListener('click', function () { openWizard(null, null); });
        document.getElementById('mbx-wizard-close').addEventListener('click', function () { wizard.style.display = 'none'; });
        document.getElementById('mbx-wizard-back').addEventListener('click', function () { goStep(step - 1); });
        document.getElementById('mbx-wizard-next').addEventListener('click', function () {
            if (step === 2) {
                var email = getVal('email').trim();
                if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
                    form.querySelector('[name="email"]').focus();
                    return;
                }
                if (!getVal('name').trim()) { setVal('name', email.split('@')[0]); }
                // Sensible default for custom providers: mail.domain.com
                if (!getVal('smtp_host')) {
                    var dom = email.split('@')[1];
                    setVal('smtp_host', 'mail.' + dom);
                    setVal('imap_host', 'mail.' + dom);
                }
            }
            goStep(step + 1);
        });
        document.getElementById('mbx-wizard-finish').addEventListener('click', saveAccount);
        wizard.querySelector('.mbx-provider-grid').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-provider]');
            if (btn) { applyPreset(btn.dataset.provider); }
        });
        document.getElementById('mbx-test-smtp').addEventListener('click', function () { runTest('smtp'); });
        document.getElementById('mbx-test-imap').addEventListener('click', function () { runTest('imap'); });
        document.getElementById('mbx-folder-list').addEventListener('click', function (e) {
            var chip = e.target.closest('[data-usefolder]');
            if (chip) { setVal('imap_sent_folder', chip.dataset.usefolder); toast(chip.dataset.usefolder); }
        });
        wizard.addEventListener('click', function (e) {
            var toggle = e.target.closest('.mbx-pw-toggle');
            if (toggle) {
                var input = toggle.parentElement.querySelector('input');
                input.type = input.type === 'password' ? 'text' : 'password';
            }
        });
        document.getElementById('mbx-staff-search').addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('.mbx-staff-item').forEach(function (item) {
                item.style.display = !q || item.dataset.name.indexOf(q) !== -1 ? 'flex' : 'none';
            });
        });

        /* account card actions */
        document.getElementById('mbx-account-grid').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-act]');
            if (!btn) { return; }
            var card = btn.closest('.mbx-account-card');
            var id = card.dataset.id;

            if (btn.dataset.act === 'edit') {
                get(AU.get + '/' + id, function (res) {
                    if (res.account) { openWizard(res.account, res.assigned || []); }
                });
            }
            if (btn.dataset.act === 'sync') {
                var icon = btn.querySelector('i');
                icon.classList.add('fa-spin');
                post(AU.sync, { account: id, force: 1 }, function (res) {
                    icon.classList.remove('fa-spin');
                    toast(res.errors && res.errors.length ? res.errors[0] : AT.synced + ' (+' + res.imported + ')');
                }, function () { icon.classList.remove('fa-spin'); });
            }
            if (btn.dataset.act === 'delete') {
                if (!confirm(AT.deleteConfirm)) { return; }
                post(AU.del, { id: id }, function (res) {
                    if (res.success) { card.remove(); }
                });
            }
        });
    }
})();
