/* ═══════════════════════════════════════════════════════════════════════
   Pro Tickets — module JS (vanilla, no dependencies beyond Chart.js which
   is only loaded on the dashboard page).
   ═══════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* ── CSRF (Perfex exposes the current pair on window.csrfData) ──────── */
    function csrf() {
        if (typeof window.csrfData !== 'undefined' && window.csrfData.token_name) {
            return { name: window.csrfData.token_name, hash: window.csrfData.hash };
        }
        return null;
    }

    function post(url, data) {
        var body = new FormData();
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
        var token = csrf();
        if (token) { body.append(token.name, token.hash); }
        // CodeIgniter strips the CSRF token from $_POST after verifying it, so a
        // body carrying only the token arrives empty and trips controller guards
        // like `if (!$this->input->post())`. Always send a sentinel field.
        body.append('_ptk_ajax', '1');
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); });
    }

    function toast(message, type) {
        if (typeof window.alert_float === 'function') {
            window.alert_float(type || 'success', message);
        }
    }

    /* ── Dashboard charts ────────────────────────────────────────────────── */
    function initDashboard() {
        var data = window.PTK_DASH;
        if (!data || typeof Chart === 'undefined') { return; }

        var PALETTE = ['#4f46e5', '#0ea5e9', '#16a34a', '#d97706', '#dc2626', '#8b5cf6', '#0d9488', '#64748b'];
        var GRID = { color: 'rgba(100,116,139,.12)', zeroLineColor: 'rgba(100,116,139,.25)' };

        var trendEl = document.getElementById('ptk-chart-trend');
        if (trendEl) {
            new Chart(trendEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.trend.labels,
                    datasets: [
                        {
                            label: 'Opened',
                            data: data.trend.opened,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79,70,229,.08)',
                            borderWidth: 2,
                            pointRadius: 2.5,
                            lineTension: .35
                        },
                        {
                            label: 'Solved',
                            data: data.trend.solved,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22,163,74,.08)',
                            borderWidth: 2,
                            pointRadius: 2.5,
                            lineTension: .35
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    scales: {
                        yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: GRID }],
                        xAxes: [{ gridLines: { display: false } }]
                    }
                }
            });
        }

        var statusEl = document.getElementById('ptk-chart-status');
        if (statusEl && data.by_status.length) {
            new Chart(statusEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: data.by_status.map(function (r) { return r.name; }),
                    datasets: [{
                        data: data.by_status.map(function (r) { return r.cnt; }),
                        backgroundColor: data.by_status.map(function (r, i) { return r.color || PALETTE[i % PALETTE.length]; }),
                        borderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutoutPercentage: 62,
                    legend: { position: 'bottom', labels: { boxWidth: 12 } }
                }
            });
        }

        var priorityEl = document.getElementById('ptk-chart-priority');
        if (priorityEl && data.by_priority.length) {
            new Chart(priorityEl.getContext('2d'), {
                type: 'horizontalBar',
                data: {
                    labels: data.by_priority.map(function (r) { return r.name; }),
                    datasets: [{
                        data: data.by_priority.map(function (r) { return r.cnt; }),
                        backgroundColor: PALETTE.slice(0, data.by_priority.length),
                        barThickness: 22
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: {
                        xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: GRID }],
                        yAxes: [{ gridLines: { display: false } }]
                    }
                }
            });
        }

        var csatEl = document.getElementById('ptk-chart-csat');
        if (csatEl && data.csat) {
            // 1★ → 5★, red through amber to green.
            var CSAT_COLORS = ['#dc2626', '#f97316', '#d97706', '#65a30d', '#16a34a'];
            new Chart(csatEl.getContext('2d'), {
                type: 'horizontalBar',
                data: {
                    labels: data.csat.labels.map(function (name, i) {
                        return (i + 1) + '★ ' + name;
                    }),
                    datasets: [{
                        data: data.csat.counts,
                        backgroundColor: CSAT_COLORS,
                        barThickness: 22
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: {
                        xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: GRID }],
                        yAxes: [{ gridLines: { display: false } }]
                    }
                }
            });
        }

        var deptEl = document.getElementById('ptk-chart-department');
        if (deptEl && data.by_department.length) {
            new Chart(deptEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.by_department.map(function (r) { return r.name; }),
                    datasets: [{
                        data: data.by_department.map(function (r) { return r.cnt; }),
                        backgroundColor: PALETTE.slice(0, data.by_department.length),
                        barThickness: 26
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: {
                        yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: GRID }],
                        xAxes: [{ gridLines: { display: false } }]
                    }
                }
            });
        }
    }

    /* ── List: clickable rows ────────────────────────────────────────────── */
    function initList() {
        document.querySelectorAll('.ptk-table-hover tr[data-href]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a')) { return; }
                window.location = row.getAttribute('data-href');
            });
        });
    }

    /* ── Kanban drag & drop ──────────────────────────────────────────────── */
    function initKanban() {
        var board = document.getElementById('ptk-board');
        if (!board || !window.PTK_KANBAN) { return; }
        if (board.getAttribute('data-can-edit') !== '1') { return; }

        var dragged = null;

        board.querySelectorAll('.ptk-kcard').forEach(function (card) {
            card.addEventListener('dragstart', function () {
                dragged = card;
                setTimeout(function () { card.classList.add('dragging'); }, 0);
            });
            card.addEventListener('dragend', function () {
                card.classList.remove('dragging');
                dragged = null;
            });
        });

        board.querySelectorAll('.ptk-col').forEach(function (col) {
            col.addEventListener('dragover', function (e) {
                e.preventDefault();
                col.classList.add('drag-over');
            });
            col.addEventListener('dragleave', function () {
                col.classList.remove('drag-over');
            });
            col.addEventListener('drop', function (e) {
                e.preventDefault();
                col.classList.remove('drag-over');
                if (!dragged) { return; }

                var card = dragged;
                var fromCol = card.closest('.ptk-col');
                if (fromCol === col) { return; }

                col.querySelector('.ptk-col-body').prepend(card);
                updateColCounts(board);

                post(window.PTK_KANBAN.moveUrl, {
                    ticket_id: card.getAttribute('data-ticket'),
                    status: col.getAttribute('data-status')
                }).then(function (res) {
                    if (!res.success) {
                        fromCol.querySelector('.ptk-col-body').prepend(card);
                        updateColCounts(board);
                        toast('Could not move the ticket', 'danger');
                    }
                }).catch(function () {
                    fromCol.querySelector('.ptk-col-body').prepend(card);
                    updateColCounts(board);
                    toast('Could not move the ticket', 'danger');
                });
            });
        });

        function updateColCounts(boardEl) {
            boardEl.querySelectorAll('.ptk-col').forEach(function (col) {
                col.querySelector('.ptk-col-count').textContent = col.querySelectorAll('.ptk-kcard').length;
            });
        }
    }

    /* ── Ticket detail ───────────────────────────────────────────────────── */
    function initTicketTabs() {
        var tabs = document.getElementById('ptk-ttabs');
        if (!tabs) { return; }
        var buttons = Array.prototype.slice.call(tabs.querySelectorAll('.ptk-ttab'));
        var panels  = Array.prototype.slice.call(document.querySelectorAll('.ptk-ttab-panel'));
        var storeKey = (tabs.getAttribute('data-store') || 'ptk-ticket-tab') + ':' + window.location.pathname;

        function activate(name, remember) {
            var matched = false;
            buttons.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-panel') === name); });
            panels.forEach(function (p) {
                var on = p.getAttribute('data-panel') === name;
                p.classList.toggle('active', on);
                if (on) { matched = true; }
            });
            if (!matched) { return false; }
            if (remember) {
                try { localStorage.setItem(storeKey, name); } catch (e) { /* ignore */ }
                if (window.history && history.replaceState) {
                    history.replaceState(null, '', '#' + name);
                }
            }
            return true;
        }

        buttons.forEach(function (b) {
            b.addEventListener('click', function () { activate(b.getAttribute('data-panel'), true); });
        });

        // Restore: URL hash wins, then last remembered tab, else the default (already active).
        var initial = (window.location.hash || '').replace('#', '');
        if (!initial) {
            try { initial = localStorage.getItem(storeKey) || ''; } catch (e) { initial = ''; }
        }
        if (initial) { activate(initial, false); }
    }

    function initTicketModals() {
        var modal = document.getElementById('ptk-todo-modal');
        var openBtn = document.getElementById('ptk-todo-open');
        if (!modal || !openBtn) { return; }

        function open() {
            modal.hidden = false;
            var first = modal.querySelector('input[name="title"]');
            if (first) { setTimeout(function () { first.focus(); }, 30); }
        }
        function close() { modal.hidden = true; }

        openBtn.addEventListener('click', open);
        modal.querySelectorAll('[data-ptk-modal-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) { close(); }
        });
    }

    // Transfer ticket: department + one of ITS members in one action. The
    // member list follows the department selection (window.PTK_DEPT_STAFF).
    function initTransferModal() {
        var modal   = document.getElementById('ptk-transfer-modal');
        var openBtn = document.getElementById('ptk-transfer-open');
        if (!modal || !openBtn) { return; }

        var body    = document.getElementById('ptk-transfer-body');
        var deptSel = document.getElementById('ptk-transfer-department');
        var memSel  = document.getElementById('ptk-transfer-member');
        var emptyEl = document.getElementById('ptk-transfer-empty');
        var submit  = document.getElementById('ptk-transfer-submit');
        var map     = window.PTK_DEPT_STAFF || {};
        var busy    = false;

        function fillMembers(deptId, preselect) {
            var members = map[deptId] || [];
            memSel.innerHTML = '';
            var none = document.createElement('option');
            none.value = '0';
            none.textContent = memSel.getAttribute('data-unassigned') || '—';
            memSel.appendChild(none);
            members.forEach(function (m) {
                var opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                if (preselect && String(preselect) === String(m.id)) { opt.selected = true; }
                memSel.appendChild(opt);
            });
            emptyEl.hidden = members.length > 0;
        }

        function open() {
            deptSel.value = body.getAttribute('data-department');
            fillMembers(deptSel.value, body.getAttribute('data-assigned'));
            modal.hidden = false;
        }
        function close() { modal.hidden = true; }

        openBtn.addEventListener('click', open);
        modal.querySelectorAll('[data-ptk-modal-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) { close(); }
        });

        deptSel.addEventListener('change', function () {
            // Keep the current assignee selected when they also belong to the
            // newly chosen department; otherwise fall back to unassigned.
            fillMembers(deptSel.value, memSel.value);
        });

        submit.addEventListener('click', function () {
            if (busy) { return; }
            busy = true;
            submit.disabled = true;
            post(body.getAttribute('data-url'), {
                department: deptSel.value,
                assigned: memSel.value
            }).then(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    busy = false;
                    submit.disabled = false;
                    toast(res.message || 'Could not transfer', 'danger');
                }
            }).catch(function () {
                busy = false;
                submit.disabled = false;
                toast('Could not transfer', 'danger');
            });
        });
    }

    /* ── Composer helpers (shared by the reply box and the new-ticket box) ──
       The composer both screens use is the same control: a predefined-message
       strip welded to a TinyMCE editor. Which editor it drives is carried by
       the strip's data-target, so nothing here is hard-wired to one page. */

    // Editor id the strip drives, e.g. "#ptk-reply-message" → "ptk-reply-message".
    function composerTargetId(el) {
        var target = (el && el.getAttribute('data-target')) || '#ptk-reply-message';
        return target.replace(/^#/, '');
    }

    function composerEditor(id) {
        return (typeof tinymce !== 'undefined') ? tinymce.get(id) : null;
    }

    function composerHtmlOf(id) {
        var editor = composerEditor(id);
        if (editor) { return editor.getContent(); }
        var textarea = document.getElementById(id);
        return textarea ? textarea.value : '';
    }

    function setComposerHtml(id, html) {
        var editor = composerEditor(id);
        if (editor) { editor.setContent(html); return; }
        var textarea = document.getElementById(id);
        if (textarea) { textarea.value = html; }
    }

    // Save the composed message as a reusable predefined message.
    function initCannedSave() {
        var toggle  = document.getElementById('ptk-canned-save-toggle');
        var row     = document.getElementById('ptk-canned-save-row');
        if (!toggle || !row) { return; }

        var nameIn  = document.getElementById('ptk-canned-name');
        var confirm = document.getElementById('ptk-canned-save-confirm');
        var cancel  = document.getElementById('ptk-canned-save-cancel');
        var select  = document.getElementById('ptk-canned');
        var busy    = false;

        function composerHtml() {
            return composerHtmlOf(composerTargetId(row));
        }

        function setOpen(open) {
            row.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { nameIn.focus(); } else { nameIn.value = ''; }
        }

        toggle.addEventListener('click', function () { setOpen(row.hidden); });
        cancel.addEventListener('click', function () { setOpen(false); toggle.focus(); });
        nameIn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); confirm.click(); }
            if (e.key === 'Escape') { e.preventDefault(); setOpen(false); toggle.focus(); }
        });

        confirm.addEventListener('click', function () {
            var name = nameIn.value.trim();
            var html = composerHtml().trim();
            if (!name || !html) {
                toast(row.getAttribute('data-missing') || 'Type a message and a template name first', 'warning');
                return;
            }
            if (busy) { return; }
            busy = true;
            confirm.disabled = true;
            post(row.getAttribute('data-url'), { name: name, message: html }).then(function (res) {
                busy = false;
                confirm.disabled = false;
                if (res.success) {
                    // Make the new template available in the dropdown right away.
                    if (select) {
                        var opt = document.createElement('option');
                        opt.value = res.id;
                        opt.textContent = res.name;
                        select.appendChild(opt);
                    }
                    setOpen(false);
                    toast(row.getAttribute('data-saved') || 'Template saved', 'success');
                } else {
                    toast(res.message || 'Could not save', 'danger');
                }
            }).catch(function () {
                busy = false;
                confirm.disabled = false;
                toast('Could not save', 'danger');
            });
        });
    }

    /* Merge tags the browser resolves. Everything that is known server-side
       (agent, role, company, date) arrives already filled in; what is left are
       the tags that depend on a form still being filled in — the requester and
       the subject on the new-ticket screen. Tag names are matched loosely
       ({Agent Name} = {agent_name}), and a tag with nothing to fill it stays
       visible so the agent can complete it by hand. */
    var TAG_SLOTS = {
        name: 'name', clientname: 'name', customername: 'name',
        contactname: 'name', requestername: 'name',
        subject: 'subject', ticketsubject: 'subject'
    };

    function applyLiveTags(html, values) {
        return String(html).replace(/\{\s*([A-Za-z][A-Za-z0-9 _-]{0,30})\s*\}/g, function (match, raw) {
            var slot = TAG_SLOTS[raw.toLowerCase().replace(/[^a-z]/g, '')];
            var value = slot ? values[slot] : '';
            if (!value) { return match; }
            var div = document.createElement('div');
            div.textContent = value;
            return div.innerHTML;
        });
    }

    // Insert a predefined message into the composer the strip points at.
    // window.ptkLiveTags (set by the new-ticket form) supplies the values that
    // only the browser knows; the reply composer gets them from the server.
    function initCannedInsert() {
        var canned = document.getElementById('ptk-canned');
        if (!canned) { return; }

        canned.addEventListener('change', function () {
            if (!canned.value) { return; }

            var targetId = composerTargetId(canned);
            var ticket   = canned.getAttribute('data-ticket');
            var url      = canned.getAttribute('data-url') + '/' + canned.value
                + (ticket ? '?ticket_id=' + encodeURIComponent(ticket) : '');

            fetch(url, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    var html = res.message || '';
                    if (typeof window.ptkLiveTags === 'function') {
                        html = applyLiveTags(html, window.ptkLiveTags());
                    }

                    var editor = composerEditor(targetId);
                    if (editor) {
                        // Predefined messages are stored as HTML — insert as-is.
                        editor.insertContent(html);
                        editor.focus();
                    } else {
                        var textarea = document.getElementById(targetId);
                        if (!textarea) { return; }
                        var tmp = document.createElement('div');
                        tmp.innerHTML = html.replace(/<br\s*\/?>(\n)?/gi, '\n').replace(/<\/p>/gi, '\n\n');
                        var text = tmp.textContent.trim();
                        textarea.value = textarea.value ? textarea.value + '\n\n' + text : text;
                        textarea.focus();
                    }
                    canned.value = '';
                });
        });
    }

    // Attachment picker label — shows the picked filenames in place of the hint.
    function initAttachLabel() {
        var attachInput = document.getElementById('ptk-attach-input');
        var label       = document.getElementById('ptk-attach-names');
        if (!attachInput || !label) { return; }

        var hint = label.textContent;
        attachInput.addEventListener('change', function () {
            var names = Array.prototype.map.call(attachInput.files, function (f) { return f.name; });
            label.textContent = names.length ? names.join(', ') : hint;
        });
    }

    // Recompute the to-do progress header + bar from the current checkbox states.
    function refreshTodoProgress() {
        var progress = document.querySelector('.ptk-todo-progress');
        var boxes    = document.querySelectorAll('.ptk-todo-toggle');
        if (!progress || !boxes.length) { return; }

        var total = boxes.length;
        var done  = 0;
        boxes.forEach(function (b) { if (b.checked) { done++; } });
        var pct = total ? Math.round((done / total) * 100) : 0;

        progress.setAttribute('data-pct', pct);

        var headSpan = progress.querySelector('.ptk-todo-progress-head span');
        if (headSpan) { headSpan.textContent = headSpan.textContent.replace(/\d+/, done); }

        var strong = progress.querySelector('.ptk-todo-progress-head strong');
        if (strong) {
            strong.textContent = pct + '%';
            strong.classList.toggle('ptk-todo-progress-done', pct === 100);
        }

        var bar = progress.querySelector('.ptk-todo-progress-bar');
        if (bar) {
            bar.style.width = pct + '%';
            bar.classList.toggle('is-complete', pct === 100);
        }
    }

    /* ── @mention picker (reply composer + internal notes) ────────────────── */

    // "@" at the start of a word, followed by up to two name words. Anchored to
    // the caret, so the query is whatever sits between the "@" and the cursor.
    var MENTION_TRIGGER = /(?:^|[\s (\[>])@([\wÀ-ÖØ-öø-ÿ.'-]*(?:[ ][\wÀ-ÖØ-öø-ÿ.'-]*)?)$/;
    // Keys the open menu owns — a keyup for one of them must not re-query.
    var MENTION_KEYS = { ArrowDown: 1, ArrowUp: 1, Enter: 1, Tab: 1, Escape: 1 };

    function escHtml(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function initMentions() {
        var STAFF = window.PTK_MENTIONS || [];
        if (!STAFF.length) { return; }

        var menu   = null;
        var items  = [];
        var active = 0;
        var ctx    = null; // where the current "@…" sits; null while closed

        function isOpen() { return !!(menu && !menu.hidden); }

        function close() {
            if (menu) { menu.hidden = true; }
            ctx   = null;
            items = [];
        }

        function buildMenu() {
            if (menu) { return menu; }
            menu = document.createElement('div');
            menu.className = 'ptk-mention-menu';
            menu.hidden = true;
            document.body.appendChild(menu);
            // mousedown, not click: clicking must not blur the composer first,
            // otherwise the caret (and with it the insert position) is gone.
            menu.addEventListener('mousedown', function (e) {
                var row = e.target.closest ? e.target.closest('.ptk-mention-item') : null;
                if (!row) { return; }
                e.preventDefault();
                pick(parseInt(row.getAttribute('data-index'), 10));
            });
            return menu;
        }

        // Name/e-mail search — prefix hits rank above mid-word hits.
        function match(query) {
            var q = query.toLowerCase().replace(/\s+/g, ' ').trim();
            if (!q) { return STAFF.slice(0, 8); }

            var starts = [], contains = [];
            STAFF.forEach(function (member) {
                var name = member.name.toLowerCase();
                var mail = (member.email || '').toLowerCase();
                if (name.indexOf(q) === 0 || mail.indexOf(q) === 0) { starts.push(member); }
                else if (name.indexOf(q) > -1 || mail.indexOf(q) > -1) { contains.push(member); }
            });

            return starts.concat(contains).slice(0, 8);
        }

        function highlight(index) {
            active = index;
            menu.querySelectorAll('.ptk-mention-item').forEach(function (row, i) {
                row.classList.toggle('is-active', i === index);
            });
            var row = menu.querySelector('.ptk-mention-item.is-active');
            if (row && row.scrollIntoView) { row.scrollIntoView({ block: 'nearest' }); }
        }

        function open(list, rect) {
            var m = buildMenu();
            items  = list;
            active = 0;
            m.innerHTML = list.map(function (member, i) {
                return '<div class="ptk-mention-item' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '">'
                    + '<span class="ptk-mention-avatar">' + escHtml(member.name.charAt(0).toUpperCase()) + '</span>'
                    + '<span class="ptk-mention-name">' + escHtml(member.name) + '</span>'
                    + (member.email ? '<span class="ptk-mention-mail">' + escHtml(member.email) + '</span>' : '')
                    + '</div>';
            }).join('');
            m.hidden = false;

            // Below the caret, flipped above when it would fall off screen.
            var top = rect.bottom + window.pageYOffset + 4;
            if (rect.bottom + m.offsetHeight + 12 > window.innerHeight) {
                top = rect.top + window.pageYOffset - m.offsetHeight - 4;
            }
            m.style.top  = Math.max(4, top) + 'px';
            m.style.left = Math.max(8, Math.min(rect.left + window.pageXOffset,
                window.innerWidth - m.offsetWidth - 12)) + 'px';
        }

        function refresh(context, rect) {
            if (!context || !rect) { close(); return; }
            var list = match(context.query);
            if (!list.length) { close(); return; }
            ctx = context;
            open(list, rect);
        }

        function pick(index) {
            var member = items[index];
            if (!member || !ctx) { close(); return; }
            var chip = '@' + member.name;

            if (ctx.type === 'textarea') {
                var ta    = ctx.el;
                var value = ta.value;
                var caret = ctx.start + chip.length + 1;
                ta.value = value.slice(0, ctx.start) + chip + ' ' + value.slice(ctx.end);
                ta.focus();
                ta.setSelectionRange(caret, caret);
            } else {
                var ed  = ctx.ed;
                var rng = ed.dom.createRng();
                rng.setStart(ctx.node, ctx.start);
                rng.setEnd(ctx.node, ctx.end);
                ed.selection.setRng(rng);
                // data-mention-id resolves the tag exactly; the visible "@Name"
                // is what the server falls back to if the editor drops the span.
                // The colour is inline so the chip survives into the e-mail copy.
                // A temporary id lets us locate the span we just inserted so the
                // caret can be parked in a plain text node *after* it. Without
                // this, Chrome's contenteditable keeps extending the chip's colour
                // and bold onto whatever the user types next.
                var uid = ed.dom.uniqueId();
                ed.insertContent('<span id="' + uid + '" class="ptk-mention" data-mention-id="'
                    + member.id + '" style="color:#4f46e5;font-weight:600;">'
                    + escHtml(chip) + '</span>&nbsp;');

                var span = ed.dom.get(uid);
                if (span) {
                    ed.dom.setAttrib(span, 'id', null);
                    // Guarantee an unstyled text node right after the chip and
                    // park the caret just past its leading space, so typing
                    // resumes as normal text outside the coloured mention span.
                    var after = span.nextSibling;
                    var ch = (after && after.nodeType === 3) ? after.nodeValue.charAt(0) : null;
                    if (!after || after.nodeType !== 3) {
                        after = ed.getDoc().createTextNode(' ');
                        ed.dom.insertAfter(after, span);
                    } else if (ch !== ' ' && ch !== ' ') {
                        after.nodeValue = ' ' + after.nodeValue;
                    }
                    var caretRng = ed.dom.createRng();
                    caretRng.setStart(after, 1);
                    caretRng.setEnd(after, 1);
                    ed.selection.setRng(caretRng);
                }
                ed.focus();
            }
            close();
        }

        function onKeyDown(e) {
            if (!isOpen()) { return; }
            if (e.key === 'ArrowDown')      { e.preventDefault(); highlight((active + 1) % items.length); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); highlight((active - 1 + items.length) % items.length); }
            else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); pick(active); }
            else if (e.key === 'Escape')    { e.preventDefault(); close(); }
        }

        function bindTextarea(ta) {
            ta.addEventListener('keydown', onKeyDown);
            ta.addEventListener('keyup', function (e) {
                if (isOpen() && MENTION_KEYS[e.key]) { return; }
                var pos = ta.selectionStart;
                if (pos !== ta.selectionEnd) { close(); return; }
                var found = MENTION_TRIGGER.exec(ta.value.slice(0, pos));
                if (!found) { close(); return; }
                var query = found[1] || '';
                var rect  = ta.getBoundingClientRect();
                refresh(
                    { type: 'textarea', el: ta, query: query, start: pos - query.length - 1, end: pos },
                    { top: rect.top, bottom: rect.bottom, left: rect.left }
                );
            });
            ta.addEventListener('blur', function () { setTimeout(close, 150); });
        }

        // Caret rectangle in page coordinates — the editor lives in an iframe,
        // so its own offset has to be added to the range rect.
        function editorRect(ed) {
            var frame = ed.iframeElement ? ed.iframeElement.getBoundingClientRect() : null;
            if (!frame) { return null; }

            var rect = null;
            try { rect = ed.selection.getRng().getBoundingClientRect(); } catch (err) { rect = null; }
            if (!rect || (!rect.top && !rect.left && !rect.height)) {
                // Collapsed ranges report an empty rect in some browsers.
                return { top: frame.top + 24, bottom: frame.top + 44, left: frame.left + 12 };
            }

            return { top: rect.top + frame.top, bottom: rect.bottom + frame.top, left: rect.left + frame.left };
        }

        function bindEditor(ed) {
            ed.on('keydown', onKeyDown);
            ed.on('keyup', function (e) {
                if (isOpen() && MENTION_KEYS[e.key]) { return; }
                var rng = ed.selection.getRng();
                if (!rng || !rng.collapsed || !rng.startContainer || rng.startContainer.nodeType !== 3) {
                    close();
                    return;
                }
                var found = MENTION_TRIGGER.exec(rng.startContainer.data.slice(0, rng.startOffset));
                if (!found) { close(); return; }
                var query = found[1] || '';
                refresh({
                    type:  'editor',
                    ed:    ed,
                    node:  rng.startContainer,
                    query: query,
                    start: rng.startOffset - query.length - 1,
                    end:   rng.startOffset
                }, editorRect(ed));
            });
            ed.on('blur', function () { setTimeout(close, 150); });
        }

        // init_editor() boots TinyMCE asynchronously — wait for the instance.
        function whenEditor(id, callback) {
            var tries = 0;
            (function poll() {
                var ed = (typeof tinymce !== 'undefined') ? tinymce.get(id) : null;
                if (ed) {
                    if (ed.initialized) { callback(ed); } else { ed.on('init', function () { callback(ed); }); }
                    return;
                }
                if (++tries < 60) { setTimeout(poll, 150); }
            })();
        }

        var replyBox = document.getElementById('ptk-reply-message');
        if (replyBox) {
            if (typeof init_editor === 'function') { whenEditor('ptk-reply-message', bindEditor); }
            else { bindTextarea(replyBox); }
        }
        document.querySelectorAll('.ptk-note-form .ptk-textarea').forEach(bindTextarea);

        window.addEventListener('scroll', function () { if (isOpen()) { close(); } }, true);
        window.addEventListener('resize', function () { if (isOpen()) { close(); } });
    }

    function initTicket() {
        // Rich-text reply composer — reuse the CRM's TinyMCE initializer so the
        // editor matches core ticket replies (toolbar, paste-image upload, etc.).
        var replyBox = document.getElementById('ptk-reply-message');
        if (replyBox && typeof init_editor === 'function') {
            init_editor('#ptk-reply-message', {
                height: 220,
                min_height: 220,
                menubar: false,
                toolbar: 'bold italic underline | forecolor | bullist numlist | link image | removeformat | code',
            });
        }

        // Inline property auto-save.
        var props = document.querySelector('.ptk-props');
        if (props && props.getAttribute('data-can-edit') === '1') {
            props.querySelectorAll('.ptk-prop').forEach(function (select) {
                select.addEventListener('change', function () {
                    post(props.getAttribute('data-url'), {
                        field: select.getAttribute('data-field'),
                        value: select.value
                    }).then(function (res) {
                        if (res.success) {
                            select.classList.add('ptk-prop-saved');
                            setTimeout(function () { select.classList.remove('ptk-prop-saved'); }, 1200);
                            // Status / assignment changes affect header badges & SLA — refresh.
                            if (select.getAttribute('data-field') === 'status') {
                                window.location.reload();
                            }
                            // Assignment only moves the header chip — no reload needed.
                            if (select.getAttribute('data-field') === 'assigned') {
                                var chip = document.getElementById('ptk-owner-agent');
                                var opt  = select.options[select.selectedIndex];
                                if (chip && opt) {
                                    var username = opt.getAttribute('data-username') || '';
                                    var nameEl   = chip.querySelector('[data-ptk-agent-name]');
                                    if (nameEl) { nameEl.textContent = username || opt.textContent; }
                                    chip.setAttribute('title', username
                                        ? opt.textContent + ' · ' + username
                                        : opt.textContent);
                                }
                            }
                        } else {
                            toast('Could not save', 'danger');
                        }
                    }).catch(function () { toast('Could not save', 'danger'); });
                });
            });
        }

        // Watch / unwatch button.
        var watchBtn = document.getElementById('ptk-watch-btn');
        if (watchBtn) {
            watchBtn.addEventListener('click', function () {
                post(watchBtn.getAttribute('data-url'), {}).then(function (res) {
                    if (res.success) { window.location.reload(); }
                });
            });
        }

        // Watcher management.
        var watchers = document.getElementById('ptk-watchers');
        if (watchers) {
            watchers.querySelectorAll('.ptk-watcher-remove').forEach(function (removeIcon) {
                removeIcon.addEventListener('click', function () {
                    post(watchers.getAttribute('data-url'), {
                        staff_id: removeIcon.closest('.ptk-watcher').getAttribute('data-staff')
                    }).then(function (res) {
                        if (res.success) { window.location.reload(); }
                    });
                });
            });
            var addSelect = document.getElementById('ptk-watcher-add');
            if (addSelect) {
                addSelect.addEventListener('change', function () {
                    if (!addSelect.value) { return; }
                    post(watchers.getAttribute('data-url'), { staff_id: addSelect.value })
                        .then(function (res) {
                            if (res.success) { window.location.reload(); }
                        });
                });
            }
        }

        // Requester mobile — reveal/hide the masked number in place.
        document.querySelectorAll('.ptk-mobile-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.parentNode.querySelector('.ptk-mobile-value');
                if (!value) { return; }
                var shown = btn.getAttribute('aria-pressed') === 'true';
                value.textContent = shown
                    ? value.getAttribute('data-masked')
                    : value.getAttribute('data-full');
                btn.setAttribute('aria-pressed', shown ? 'false' : 'true');
                btn.setAttribute('title', btn.getAttribute(shown ? 'data-show' : 'data-hide'));
                var icon = btn.querySelector('i');
                if (icon) { icon.className = shown ? 'fa fa-eye' : 'fa fa-eye-slash'; }
            });
        });

        // CC management (same-company contacts on the requester card).
        var ccBox = document.getElementById('ptk-cc');
        if (ccBox) {
            ccBox.querySelectorAll('.ptk-cc-remove').forEach(function (removeIcon) {
                removeIcon.addEventListener('click', function () {
                    post(ccBox.getAttribute('data-url'), {
                        email: removeIcon.closest('.ptk-watcher').getAttribute('data-email'),
                        cc_action: 'remove'
                    }).then(function (res) {
                        if (res.success) { window.location.reload(); }
                    });
                });
            });
            var ccAdd = document.getElementById('ptk-cc-add');
            if (ccAdd) {
                ccAdd.addEventListener('change', function () {
                    if (!ccAdd.value) { return; }
                    post(ccBox.getAttribute('data-url'), { email: ccAdd.value, cc_action: 'add' })
                        .then(function (res) {
                            if (res.success) { window.location.reload(); }
                        });
                });
            }
        }

        // To-do checklist — toggle a task complete/pending (updates the bar live).
        document.querySelectorAll('.ptk-todo-toggle').forEach(function (box) {
            box.addEventListener('change', function () {
                var wanted = box.checked;
                box.disabled = true;
                post(box.getAttribute('data-url'), {}).then(function (res) {
                    box.disabled = false;
                    if (res && res.success) {
                        box.checked = (typeof res.completed !== 'undefined') ? !!res.completed : wanted;
                        var row = box.closest('.ptk-todo');
                        if (row) { row.classList.toggle('ptk-todo-done', box.checked); }
                        refreshTodoProgress();
                    } else {
                        box.checked = !wanted; // revert on failure
                        toast('Could not update task', 'danger');
                    }
                }).catch(function () {
                    box.disabled = false;
                    box.checked = !wanted;
                    toast('Could not update task', 'danger');
                });
            });
        });

        // To-do checklist — delete a task.
        document.querySelectorAll('.ptk-todo-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm(window.ptkTodoDeleteConfirm || 'Delete this to-do task?')) { return; }
                btn.disabled = true;
                post(btn.getAttribute('data-url'), {}).then(function (res) {
                    if (res.success) { window.location.reload(); } else { btn.disabled = false; }
                });
            });
        });

        // Attach an account-less ticket to a tenant / customer.
        var linkClient = document.getElementById('ptk-link-client');
        if (linkClient) {
            var linkSelect = document.getElementById('ptk-link-client-select');
            linkSelect.addEventListener('change', function () {
                if (!linkSelect.value) { return; }
                linkSelect.disabled = true;
                post(linkClient.getAttribute('data-url'), { key: linkSelect.value }).then(function (res) {
                    if (res.success) {
                        window.location.reload();
                    } else {
                        linkSelect.disabled = false;
                        linkSelect.value = '';
                        toast(res.message || 'Could not link', 'danger');
                    }
                }).catch(function () {
                    linkSelect.disabled = false;
                    toast('Could not link', 'danger');
                });
            });
        }

        // Smart PDF module — pick a template, get its fields prefilled from
        // the ticket, submit to a new tab (preview or print document).
        var pdfSelect = document.getElementById('ptk-pdf-template');
        if (pdfSelect) {
            var pdfFields = document.getElementById('ptk-pdf-fields');
            var pdfActions = document.getElementById('ptk-pdf-actions');

            pdfSelect.addEventListener('change', function () {
                pdfFields.innerHTML = '';
                pdfActions.style.display = 'none';
                if (!pdfSelect.value) { return; }
                fetch(pdfSelect.getAttribute('data-url') + '/' + pdfSelect.value
                        + '?ticket_id=' + pdfSelect.getAttribute('data-ticket'), { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (!res.success) { return; }

                        if (!res.fields.length) {
                            var none = document.createElement('div');
                            none.className = 'ptk-small ptk-muted';
                            none.textContent = pdfFields.getAttribute('data-empty');
                            pdfFields.appendChild(none);
                        }

                        res.fields.forEach(function (field) {
                            var wrap = document.createElement('div');
                            wrap.className = 'ptk-form-field';

                            var label = document.createElement('label');
                            label.textContent = field.label + (field.required == 1 ? ' *' : '');
                            wrap.appendChild(label);

                            var input;
                            if (field.type === 'textarea') {
                                input = document.createElement('textarea');
                                input.rows = 3;
                                input.className = 'ptk-textarea';
                                input.value = field.value || '';
                            } else if (field.type === 'select') {
                                input = document.createElement('select');
                                input.className = 'ptk-select';
                                if (field.required != 1) { input.appendChild(new Option('', '')); }
                                (field.options || '').split(',').forEach(function (option) {
                                    option = option.trim();
                                    if (option !== '') { input.appendChild(new Option(option, option)); }
                                });
                                if (field.value) { input.value = field.value; }
                            } else {
                                input = document.createElement('input');
                                input.className = 'ptk-input';
                                input.type = ['number', 'date', 'time', 'email'].indexOf(field.type) !== -1 ? field.type : 'text';
                                input.value = field.value || '';
                            }
                            input.name = 'tags[' + field.tag + ']';
                            if (field.required == 1) { input.required = true; }
                            wrap.appendChild(input);
                            pdfFields.appendChild(wrap);
                        });

                        pdfActions.style.display = '';
                    });
            });
        }
    }

    /* ── New ticket form ─────────────────────────────────────────────────── */
    function initNewTicket() {
        var form = document.getElementById('ptk-new-form');
        if (!form) { return; }

        // Rich-text message composer — same TinyMCE setup as the ticket reply box
        // so the initial message is authored the same way as replies.
        var messageBox = document.getElementById('ptk-new-message');
        if (messageBox && typeof init_editor === 'function') {
            init_editor('#ptk-new-message', {
                height: 260,
                min_height: 260,
                menubar: false,
                toolbar: 'bold italic underline | forecolor | bullist numlist | link image | removeformat | code',
            });
        }

        /* ── Live merge tags ──────────────────────────────────────────────────
           The requester and the subject are only known once the agent has
           filled them in, so the template keeps {Name} / {Subject} until then.
           They are resolved when a predefined message is inserted, and any tag
           still sitting in the box is refreshed whenever the pick changes. */
        function primaryRequesterName() {
            var chip = document.querySelector('#ptk-req-chips .ptk-req-chip .ptk-chip-name');
            if (chip) { return chip.textContent.trim(); }
            var manual = document.getElementById('ptk-req-name');
            return manual ? manual.value.trim() : '';
        }

        window.ptkLiveTags = function () {
            var subject = document.getElementById('ptk-new-subject');
            return {
                name: primaryRequesterName(),
                subject: subject ? subject.value.trim() : ''
            };
        };

        // Fill the tags left in the composer, without touching anything else.
        function refreshLiveTags() {
            var values = window.ptkLiveTags();
            if (!values.name && !values.subject) { return; }

            var html = composerHtmlOf('ptk-new-message');
            if (!html || html.indexOf('{') === -1) { return; }

            var filled = applyLiveTags(html, values);
            if (filled !== html) { setComposerHtml('ptk-new-message', filled); }
        }

        var subjectInput = document.getElementById('ptk-new-subject');
        if (subjectInput) { subjectInput.addEventListener('blur', refreshLiveTags); }

        /* ── Requester picker ─────────────────────────────────────────────────
           Tenant-first flow (SaaS master): pick a tenant, then one of its
           active staff — first pick is the requester, further picks are CC.
           Legacy flow (no tenants): ONE smart search across contacts & staff. */
        var reqWrap = document.getElementById('ptk-requester');
        var input = document.getElementById('ptk-req-q');
        var results = document.getElementById('ptk-req-results');
        var tenantSel = document.getElementById('ptk-req-tenant');
        var staffBox = document.getElementById('ptk-req-staff-box');
        var staffFilter = document.getElementById('ptk-req-staff-filter');
        var staffList = document.getElementById('ptk-req-staff-list');
        var staffStatus = document.getElementById('ptk-req-staff-status');
        var selectedBox = document.getElementById('ptk-req-selected');
        var chips = document.getElementById('ptk-req-chips');
        var reqHidden = document.getElementById('ptk-req-hidden');
        var manualBox = document.getElementById('ptk-req-manual');
        var manualToggle = document.getElementById('ptk-req-manual-toggle');
        var nameInput = document.getElementById('ptk-req-name');
        var emailInput = document.getElementById('ptk-req-email');
        var searchUrl = reqWrap ? reqWrap.getAttribute('data-search-url') : '';
        var tenantStaffUrl = reqWrap ? reqWrap.getAttribute('data-tenant-staff-url') : '';
        var customerContactsUrl = reqWrap ? reqWrap.getAttribute('data-customer-contacts-url') : '';
        var selected = [];
        var tenantStaff = []; // active staff of the currently selected tenant
        var timer = null;

        function esc(s) {
            var div = document.createElement('div');
            div.textContent = s == null ? '' : String(s);
            return div.innerHTML;
        }

        // Tenant staff ids only collide across tenants, so the slug is part of
        // the identity for those picks.
        function personKey(p) { return p.type + ':' + (p.slug || '') + ':' + p.id; }
        function isPicked(p) { return selected.some(function (s) { return personKey(s) === personKey(p); }); }

        function hiddenField(name, value) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = name; i.value = value;
            return i;
        }

        function clearManual(hide) {
            if (nameInput) { nameInput.value = ''; }
            if (emailInput) { emailInput.value = ''; }
            if (hide && manualBox) { manualBox.style.display = 'none'; }
            if (hide && manualToggle) { manualToggle.style.display = ''; }
        }

        function addPerson(p) {
            if (isPicked(p)) { return; }
            selected.push(p);
            clearManual(true); // a picked person is the requester; manual entry no longer applies
            renderSelected();
        }

        function renderSelected() {
            chips.innerHTML = '';
            reqHidden.innerHTML = '';
            selectedBox.style.display = selected.length ? '' : 'none';
            var pickInput = input || staffFilter;
            if (pickInput) { pickInput.classList.toggle('ptk-contact-picked', selected.length > 0); }

            var primary = selected[0] || null;
            var primaryContactId = (primary && primary.type === 'contact') ? primary.id : null;
            loadContactCalls(primaryContactId);
            loadContactEmails(primaryContactId);

            selected.forEach(function (row, idx) {
                // Hidden fields describing the pick for the backend: the first
                // is the requester (tenant staff, contactid or staff_id); the
                // rest are CC e-mails.
                if (idx === 0) {
                    if (row.type === 'tenant_staff') {
                        reqHidden.appendChild(hiddenField('tenant_slug', row.slug));
                        reqHidden.appendChild(hiddenField('tenant_staff_id', row.id));
                    } else if (row.type === 'contact') { reqHidden.appendChild(hiddenField('contactid', row.id)); }
                    else if (row.type === 'staff') { reqHidden.appendChild(hiddenField('staff_id', row.id)); }
                } else if (row.email) {
                    reqHidden.appendChild(hiddenField('cc_emails[]', row.email));
                }

                var isStaff = row.type === 'staff' || row.type === 'tenant_staff';
                var icon = isStaff ? 'fa-user-tie' : 'fa-user';
                var roleLabel = idx === 0 ? 'Requester' : 'CC';
                var typeBadge = row.type === 'tenant_staff' ? (row.company || 'Tenant staff')
                    : (row.type === 'staff' ? 'Staff' : (row.company || 'Contact'));
                var typeIcon = row.type === 'tenant_staff' ? 'fa-hospital'
                    : (row.type === 'staff' ? 'fa-id-badge' : 'fa-building');

                var chip = document.createElement('div');
                chip.className = 'ptk-contact-chip ptk-req-chip';
                chip.setAttribute('data-label', row.name + (row.email ? ' <' + row.email + '>' : ''));
                chip.innerHTML =
                    '<span class="ptk-req-role ptk-req-role-' + (idx === 0 ? 'to' : 'cc') + '">' + esc(roleLabel) + '</span>' +
                    '<span class="ptk-chip-name"><i class="fa ' + icon + '"></i> ' + esc(row.name) + '</span>' +
                    '<span class="ptk-small ptk-muted ptk-req-chip-email">' + esc(row.email || '') + '</span>' +
                    '<span class="ptk-small ptk-muted ptk-req-chip-type"><i class="fa ' + typeIcon + '"></i> ' + esc(typeBadge) + '</span>';

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'ptk-chip-remove';
                remove.innerHTML = '&times;';
                remove.addEventListener('click', function () {
                    selected = selected.filter(function (s) { return personKey(s) !== personKey(row); });
                    renderSelected();
                });
                chip.appendChild(remove);
                chips.appendChild(chip);
            });

            // Keep the tenant staff list's "Added" markers in sync with the chips.
            if (tenantStaff.length) { renderStaffList(staffFilter ? staffFilter.value : ''); }

            // A requester is now known — fill any {Name} left in the message.
            refreshLiveTags();
        }

        if (nameInput) { nameInput.addEventListener('blur', refreshLiveTags); }

        if (manualToggle) {
            manualToggle.addEventListener('click', function () {
                // Manual name & email replaces any picked people.
                selected = [];
                renderSelected();
                if (manualBox) { manualBox.style.display = ''; }
                manualToggle.style.display = 'none';
                if (nameInput) { nameInput.focus(); }
            });
        }

        /* ── Keyboard navigation for the picker dropdowns ─────────────────────
           ArrowUp/ArrowDown move a highlight over the selectable rows, Enter
           activates the highlighted row (rows carry their action as a click
           listener), Escape closes the list. opts.open (re)opens the list on
           ArrowDown when closed; opts.onEnterIdle runs when Enter is pressed
           with nothing highlighted. */
        function listKeyNav(inputEl, listEl, opts) {
            if (!inputEl || !listEl) { return; }
            opts = opts || {};

            function selectableRows() {
                return Array.prototype.filter.call(
                    listEl.querySelectorAll('.ptk-contact-result'),
                    function (el) {
                        return !el.classList.contains('ptk-muted')
                            && !el.classList.contains('ptk-req-result-picked');
                    }
                );
            }
            function activeRow() { return listEl.querySelector('.ptk-req-result-active'); }
            function setActive(el) {
                var cur = activeRow();
                if (cur) { cur.classList.remove('ptk-req-result-active'); }
                if (el) {
                    el.classList.add('ptk-req-result-active');
                    if (el.scrollIntoView) { el.scrollIntoView({ block: 'nearest' }); }
                }
            }

            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (listEl.classList.contains('open')) {
                        e.preventDefault();
                        listEl.classList.remove('open');
                    }
                    return;
                }

                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!listEl.classList.contains('open')) {
                        if (opts.open) { opts.open(); }
                        return;
                    }
                    var items = selectableRows();
                    if (!items.length) { return; }
                    var idx = items.indexOf(activeRow());
                    if (e.key === 'ArrowDown') {
                        setActive(idx === -1 ? items[0] : items[Math.min(idx + 1, items.length - 1)]);
                    } else {
                        setActive(idx <= 0 ? null : items[idx - 1]);
                    }
                    return;
                }

                if (e.key === 'Enter') {
                    var cur = activeRow();
                    if (listEl.classList.contains('open') && cur) {
                        e.preventDefault();
                        cur.click();
                    } else if (opts.onEnterIdle) {
                        opts.onEnterIdle(e);
                    }
                }
            });
        }

        /* ── Tenant-first flow: tenant search → active staff list ──────────── */

        // Searchable tenant/customer combo: the visible input filters both
        // lists; picking an entry sets the hidden select ("t:<slug>" tenant,
        // "c:<userid>" customer) and fires its change event, which drives the
        // people loader below.
        var tenantQ = document.getElementById('ptk-req-tenant-q');
        var tenantList = document.getElementById('ptk-req-tenant-list');
        var TENANT_LIST_CAP = 50; // rows shown per group before "keep typing"
        var tenantOptions = [];
        if (tenantSel) {
            Array.prototype.forEach.call(tenantSel.options, function (opt) {
                if (!opt.value) { return; }
                tenantOptions.push({
                    key: opt.value,
                    type: opt.value.indexOf('t:') === 0 ? 'tenant' : 'customer',
                    slug: opt.value.indexOf('t:') === 0 ? opt.value.slice(2) : '',
                    name: opt.text
                });
            });
        }

        function filterTenants(query) {
            var q = (query || '').trim().toLowerCase();
            return tenantOptions.filter(function (t) {
                return !q || (t.name + ' ' + t.slug).toLowerCase().indexOf(q) !== -1;
            });
        }

        function renderTenantList(query) {
            if (!tenantList) { return []; }
            tenantList.innerHTML = '';
            var rows = filterTenants(query);

            function appendGroup(label, icon, list) {
                if (!list.length) { return; }
                var head = document.createElement('div');
                head.className = 'ptk-req-group-label';
                head.innerHTML = '<i class="fa ' + icon + '"></i> ' + esc(label) +
                    ' <span class="ptk-req-group-count">' + list.length + '</span>';
                tenantList.appendChild(head);

                list.slice(0, TENANT_LIST_CAP).forEach(function (t) {
                    var isTenant = t.type === 'tenant';
                    var item = document.createElement('div');
                    item.className = 'ptk-contact-result ptk-req-result';
                    item.innerHTML = '<span class="ptk-req-result-main"><strong>' + esc(t.name) + '</strong>' +
                        (t.slug ? '<span class="ptk-small ptk-muted">' + esc(t.slug) + '</span>' : '') + '</span>' +
                        (isTenant
                            ? '<span class="ptk-req-tag ptk-req-tag-staff"><i class="fa fa-hospital"></i> Tenant</span>'
                            : '<span class="ptk-req-tag ptk-req-tag-contact"><i class="fa fa-building"></i> Customer</span>');
                    item.addEventListener('click', function () { pickTenant(t); });
                    tenantList.appendChild(item);
                });

                if (list.length > TENANT_LIST_CAP) {
                    var more = document.createElement('div');
                    more.className = 'ptk-contact-result ptk-muted';
                    more.textContent = '+' + (list.length - TENANT_LIST_CAP) + ' more — keep typing to narrow down.';
                    tenantList.appendChild(more);
                }
            }

            appendGroup('Tenants', 'fa-hospital', rows.filter(function (t) { return t.type === 'tenant'; }));
            appendGroup('Customers', 'fa-building', rows.filter(function (t) { return t.type === 'customer'; }));

            if (!rows.length) {
                var none = document.createElement('div');
                none.className = 'ptk-contact-result ptk-muted';
                none.textContent = 'No tenants or customers match “' + (query || '').trim() + '”.';
                tenantList.appendChild(none);
            }

            return rows;
        }

        function pickTenant(t) {
            if (tenantQ) {
                tenantQ.value = t.name;
                tenantQ.classList.add('ptk-contact-picked');
            }
            if (tenantList) { tenantList.classList.remove('open'); }
            if (tenantSel && tenantSel.value !== t.key) {
                tenantSel.value = t.key;
                tenantSel.dispatchEvent(new Event('change'));
            }
        }

        if (tenantQ && tenantSel) {
            tenantQ.addEventListener('input', function () {
                // Editing the text invalidates the current pick.
                if (tenantSel.value) {
                    tenantSel.value = '';
                    tenantSel.dispatchEvent(new Event('change'));
                }
                tenantQ.classList.remove('ptk-contact-picked');
                renderTenantList(tenantQ.value);
                if (tenantList) { tenantList.classList.add('open'); }
            });
            tenantQ.addEventListener('focus', function () {
                // With a tenant already picked, show the full list again so
                // switching tenants is one click.
                renderTenantList(tenantSel.value ? '' : tenantQ.value);
                if (tenantList) { tenantList.classList.add('open'); }
            });
            listKeyNav(tenantQ, tenantList, {
                open: function () {
                    renderTenantList(tenantSel.value ? '' : tenantQ.value);
                    tenantList.classList.add('open');
                },
                onEnterIdle: function (e) {
                    // No highlight: pick the sole match, otherwise open the list.
                    e.preventDefault();
                    var rows = filterTenants(tenantSel.value ? '' : tenantQ.value);
                    if (rows.length === 1) { pickTenant(rows[0]); }
                    else { renderTenantList(tenantQ.value); tenantList.classList.add('open'); }
                }
            });
        }

        function staffStatusText(text) {
            if (!staffStatus) { return; }
            staffStatus.textContent = text || '';
            staffStatus.style.display = text ? '' : 'none';
        }

        // Render the (filtered) staff of the selected tenant into the dropdown.
        function renderStaffList(query) {
            if (!staffList) { return; }
            staffList.innerHTML = '';
            var q = (query || '').trim().toLowerCase();
            var rows = tenantStaff.filter(function (r) {
                return !q || (r.name + ' ' + r.email).toLowerCase().indexOf(q) !== -1;
            });

            rows.forEach(function (row) {
                var picked = isPicked(row);
                var tag = row.type === 'contact'
                    ? '<span class="ptk-req-tag ptk-req-tag-contact"><i class="fa fa-user"></i> Contact</span>'
                    : '<span class="ptk-req-tag ptk-req-tag-staff"><i class="fa fa-id-badge"></i> Staff</span>';
                var item = document.createElement('div');
                item.className = 'ptk-contact-result ptk-req-result' + (picked ? ' ptk-req-result-picked' : '');
                item.innerHTML = '<span class="ptk-req-result-main"><strong>' + esc(row.name) + '</strong>' +
                    '<span class="ptk-small ptk-muted">' + esc(row.email || '') +
                    (row.phone ? ' · ' + esc(row.phone) : '') + '</span></span>' +
                    (picked
                        ? '<span class="ptk-req-tag ptk-req-tag-staff"><i class="fa fa-check"></i> Added</span>'
                        : tag);
                if (!picked) {
                    item.addEventListener('click', function () {
                        addPerson(row);
                        staffFilter.value = '';
                        renderStaffList('');
                        staffFilter.focus();
                    });
                }
                staffList.appendChild(item);
            });

            if (!rows.length) {
                var none = document.createElement('div');
                none.className = 'ptk-contact-result ptk-muted';
                none.textContent = tenantStaff.length
                    ? 'No staff match “' + (query || '').trim() + '”.'
                    : (staffStatus ? staffStatus.getAttribute('data-none') : '');
                staffList.appendChild(none);
            }
        }

        if (tenantSel) {
            tenantSel.addEventListener('change', function () {
                var key = tenantSel.value;
                tenantStaff = [];
                renderStaffList('');
                if (staffList) { staffList.classList.remove('open'); }

                if (!key) {
                    if (staffBox) { staffBox.style.display = 'none'; }
                    staffStatusText('');
                    return;
                }

                if (staffBox) { staffBox.style.display = ''; }
                if (staffFilter) { staffFilter.value = ''; }
                staffStatusText(staffStatus ? staffStatus.getAttribute('data-loading') : '');

                // "t:<slug>" → tenant staff (cross-DB); "c:<userid>" → the
                // customer's contacts. Both endpoints return the same row shape.
                var isTenant = key.indexOf('t:') === 0;
                var sourceName = tenantSel.options[tenantSel.selectedIndex].text;
                var url = isTenant
                    ? tenantStaffUrl + '?slug=' + encodeURIComponent(key.slice(2))
                    : customerContactsUrl + '?userid=' + encodeURIComponent(key.slice(2));

                fetch(url, { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (rows) {
                        if (tenantSel.value !== key) { return; } // stale response
                        if (!Array.isArray(rows)) {
                            staffStatusText((rows && rows.error) || 'Could not load people');
                            return;
                        }
                        tenantStaff = rows.map(function (r) {
                            return isTenant
                                ? { type: 'tenant_staff', id: r.id, slug: key.slice(2),
                                    name: r.name, email: r.email, phone: r.phone, company: sourceName }
                                : { type: 'contact', id: r.id, userid: r.userid,
                                    name: r.name, email: r.email, phone: r.phone, company: sourceName };
                        });
                        staffStatusText(tenantStaff.length
                            ? (staffStatus ? staffStatus.getAttribute('data-count').replace('%d', tenantStaff.length) : '')
                            : (staffStatus ? staffStatus.getAttribute('data-none') : ''));
                        renderStaffList('');
                        if (tenantStaff.length && staffList) { staffList.classList.add('open'); }
                        if (staffFilter) { staffFilter.focus(); }
                    })
                    .catch(function () { staffStatusText('Could not load people'); });
            });
        }

        if (staffFilter) {
            staffFilter.addEventListener('input', function () {
                renderStaffList(staffFilter.value);
                if (staffList) { staffList.classList.add('open'); }
            });
            staffFilter.addEventListener('focus', function () {
                if (tenantStaff.length || staffFilter.value.trim() !== '') {
                    renderStaffList(staffFilter.value);
                    if (staffList) { staffList.classList.add('open'); }
                }
            });
            listKeyNav(staffFilter, staffList, {
                open: function () {
                    renderStaffList(staffFilter.value);
                    if (staffList) { staffList.classList.add('open'); }
                },
                onEnterIdle: function (e) {
                    // Never submit the form from the filter box; with the filter
                    // narrowed to a single not-yet-picked person, Enter adds them.
                    e.preventDefault();
                    var q = staffFilter.value.trim().toLowerCase();
                    var rows = tenantStaff.filter(function (r) {
                        return !isPicked(r) && (!q || (r.name + ' ' + r.email).toLowerCase().indexOf(q) !== -1);
                    });
                    if (rows.length === 1) {
                        addPerson(rows[0]);
                        staffFilter.value = '';
                        renderStaffList('');
                    }
                }
            });
        }

        /* ── Legacy flow: ONE smart search across contacts & staff ─────────── */

        if (input) { input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { results.classList.remove('open'); return; }
            timer = setTimeout(function () {
                fetch(searchUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (rows) {
                        rows = (rows || []).filter(function (r) { return !isPicked(r); });
                        results.innerHTML = '';

                        // Render one result row (contact or staff) into the dropdown.
                        function appendResult(row) {
                            var badge = row.type === 'staff'
                                ? '<span class="ptk-req-tag ptk-req-tag-staff"><i class="fa fa-id-badge"></i> Staff</span>'
                                : '<span class="ptk-req-tag ptk-req-tag-contact"><i class="fa fa-building"></i> ' + esc(row.company || 'Contact') + '</span>';
                            var item = document.createElement('div');
                            item.className = 'ptk-contact-result ptk-req-result';
                            item.innerHTML = '<span class="ptk-req-result-main"><strong>' + esc(row.name) + '</strong>' +
                                '<span class="ptk-small ptk-muted">' + esc(row.email || '') +
                                (row.phone ? ' · ' + esc(row.phone) : '') + '</span></span>' + badge;
                            item.addEventListener('click', function () {
                                addPerson(row);
                                input.value = '';
                                input.focus();
                                results.classList.remove('open');
                            });
                            results.appendChild(item);
                        }

                        // Group the mixed result set into labelled sections so the
                        // tenant's own staff are never buried under a long contact
                        // list — staff are shown first, then clients & contacts.
                        function appendGroup(label, icon, list) {
                            if (!list.length) { return; }
                            var head = document.createElement('div');
                            head.className = 'ptk-req-group-label';
                            head.innerHTML = '<i class="fa ' + icon + '"></i> ' + esc(label) +
                                ' <span class="ptk-req-group-count">' + list.length + '</span>';
                            results.appendChild(head);
                            list.forEach(appendResult);
                        }

                        var staff = rows.filter(function (r) { return r.type === 'staff'; });
                        var contacts = rows.filter(function (r) { return r.type !== 'staff'; });

                        appendGroup('Staff members', 'fa-id-badge', staff);
                        appendGroup('Clients & contacts', 'fa-building', contacts);

                        if (!rows.length) {
                            var none = document.createElement('div');
                            none.className = 'ptk-contact-result ptk-muted';
                            none.textContent = 'No matches — use “Enter a name & email” below.';
                            results.appendChild(none);
                        }
                        results.classList.add('open');
                    });
            }, 250);
        }); }

        // Arrow-key selection on the legacy smart-search results too. Enter
        // with nothing highlighted keeps its default (form submit → validation).
        if (input && results) { listKeyNav(input, results, {}); }

        document.addEventListener('click', function (e) {
            if (results && !e.target.closest('#ptk-req-search')) { results.classList.remove('open'); }
            if (staffList && !e.target.closest('#ptk-req-staff-box')) { staffList.classList.remove('open'); }
            if (tenantList && !e.target.closest('#ptk-req-tenant-box')) { tenantList.classList.remove('open'); }
        });

        // Sidebar companion cards (Caller / Mailbox): reset to the "select a
        // contact" hint, or show the module's "none found" text after a fetch.
        function sideCardReset(listId, countId, emptyId, loaded, itemCount) {
            var list = document.getElementById(listId);
            var count = document.getElementById(countId);
            var empty = document.getElementById(emptyId);
            if (!loaded) { list.innerHTML = ''; }
            if (count) { count.textContent = String(itemCount || 0); }
            if (empty) {
                empty.textContent = empty.getAttribute(loaded ? 'data-none' : 'data-hint');
                empty.style.display = itemCount ? 'none' : '';
            }
        }

        // Caller module — recent calls of the primary selected contact.
        var callsBox = document.getElementById('ptk-contact-calls');
        function loadContactCalls(contactid) {
            if (!callsBox) { return; }
            if (!contactid) { sideCardReset('ptk-contact-calls-list', 'ptk-contact-calls-count', 'ptk-contact-calls-empty', false, 0); return; }
            fetch(callsBox.getAttribute('data-url') + '?contactid=' + encodeURIComponent(contactid), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (calls) {
                    var list = document.getElementById('ptk-contact-calls-list');
                    list.innerHTML = '';
                    sideCardReset('ptk-contact-calls-list', 'ptk-contact-calls-count', 'ptk-contact-calls-empty', true, calls.length);
                    calls.forEach(function (call) {
                        var mins = Math.floor(call.duration / 60), secs = call.duration % 60;
                        var row = document.createElement('div');
                        row.className = 'ptk-call';
                        row.innerHTML = '<span class="ptk-call-icon ptk-call-' + esc(call.call_type) + '"><i class="fa ' +
                            (call.call_type === 'incoming' ? 'fa-arrow-down' : (call.call_type === 'outgoing' ? 'fa-arrow-up' : 'fa-phone-slash')) + '"></i></span>' +
                            '<div class="ptk-call-body"><div class="ptk-call-top"><span>' +
                            esc(call.call_type.charAt(0).toUpperCase() + call.call_type.slice(1)) +
                            (call.duration > 0 ? ' · ' + mins + ':' + (secs < 10 ? '0' : '') + secs : '') + '</span></div>' +
                            '<div class="ptk-small ptk-muted">' + esc(call.phone) + ' · ' + esc(call.call_date) +
                            (call.staff ? ' · ' + esc(call.staff) : '') + '</div></div>';
                        list.appendChild(row);
                    });
                });
        }

        // Mailbox module — latest emails with the primary selected contact.
        var emailsBox = document.getElementById('ptk-contact-emails');
        function loadContactEmails(contactid) {
            if (!emailsBox) { return; }
            if (!contactid) { sideCardReset('ptk-contact-emails-list', 'ptk-contact-emails-count', 'ptk-contact-emails-empty', false, 0); return; }
            fetch(emailsBox.getAttribute('data-url') + '?contactid=' + encodeURIComponent(contactid), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (emails) {
                    var list = document.getElementById('ptk-contact-emails-list');
                    list.innerHTML = '';
                    sideCardReset('ptk-contact-emails-list', 'ptk-contact-emails-count', 'ptk-contact-emails-empty', true, emails.length);
                    emails.forEach(function (mail) {
                        var row = document.createElement('div');
                        row.className = 'ptk-email';
                        row.innerHTML = '<span class="ptk-email-icon ptk-email-' + (mail.outgoing ? 'outgoing' : 'incoming') + '"><i class="fa fa-arrow-' + (mail.outgoing ? 'up' : 'down') + '"></i></span>' +
                            '<div class="ptk-email-body"><div class="ptk-email-top">' +
                            '<span class="ptk-email-subject" title="' + esc(mail.subject) + '">' + esc(mail.subject || '—') + '</span>' +
                            (mail.attach ? '<i class="fa fa-paperclip ptk-muted"></i>' : '') + '</div>' +
                            (mail.snippet ? '<div class="ptk-email-snippet ptk-small ptk-muted">' + esc(mail.snippet) + '</div>' : '') +
                            '<div class="ptk-small ptk-muted">' + esc(mail.from) + ' · ' + esc(mail.date) +
                            ' · <i class="fa fa-inbox"></i> ' + esc(mail.account) + '</div></div>';
                        list.appendChild(row);
                    });
                });
        }

        // Follow-up todo fields toggle.
        var todoCheck = document.getElementById('ptk-create-todo');
        if (todoCheck) {
            todoCheck.addEventListener('change', function () {
                document.getElementById('ptk-create-todo-fields').style.display = todoCheck.checked ? '' : 'none';
            });
        }

        // Require a requester, and a non-empty message, before submit.
        form.addEventListener('submit', function (e) {
            // A requester is either a picked person (contact or staff) or a
            // manually entered e-mail.
            var manualEmail = emailInput ? emailInput.value.trim() : '';
            if (!selected.length && manualEmail === '') {
                e.preventDefault();
                if (tenantSel) {
                    if (!tenantSel.value) { if (tenantQ) { tenantQ.focus(); } } else if (staffFilter) { staffFilter.focus(); }
                    toast('Add a requester — select a tenant and pick a staff member, or enter a name & email', 'warning');
                } else {
                    if (input) { input.focus(); }
                    toast('Add a requester — search for a person, or enter a name & email', 'warning');
                }
                return;
            }

            // Flush the editor into the textarea, then check it actually has text
            // (TinyMCE leaves empty markup like "<p><br></p>" that must not count).
            var editor = (typeof tinymce !== 'undefined') ? tinymce.get('ptk-new-message') : null;
            if (editor) { editor.save(); }
            var msgEl = document.getElementById('ptk-new-message');
            var msgText = editor
                ? (editor.getContent({ format: 'text' }) || '').trim()
                : (msgEl ? msgEl.value.trim() : '');
            if (!msgText) {
                e.preventDefault();
                if (editor) { editor.focus(); } else if (msgEl) { msgEl.focus(); }
                toast('Please enter a message', 'warning');
            }
        });
    }

    /* ── Settings ────────────────────────────────────────────────────────── */
    function initSettings() {
        // SLA edit buttons populate the form.
        document.querySelectorAll('.ptk-sla-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sla = JSON.parse(btn.getAttribute('data-sla'));
                document.getElementById('ptk-sla-id').value = sla.id;
                document.getElementById('ptk-sla-name').value = sla.name;
                document.getElementById('ptk-sla-department').value = sla.department_id;
                document.getElementById('ptk-sla-priority').value = sla.priority_id;
                document.getElementById('ptk-sla-frt').value = sla.frt_hours;
                document.getElementById('ptk-sla-res').value = sla.res_hours;
                document.getElementById('ptk-sla-escalate').value = sla.escalate_to;
                document.getElementById('ptk-sla-active').checked = !!sla.active;
                document.getElementById('ptk-sla-sort').value = sla.sort_order;
                document.getElementById('ptk-sla-form-title').textContent = document.getElementById('ptk-sla-form-title').getAttribute('data-edit-label') || 'Edit Policy';
                document.getElementById('ptk-sla-reset').style.display = '';
                document.getElementById('ptk-sla-name').focus();
            });
        });

        var reset = document.getElementById('ptk-sla-reset');
        if (reset) {
            reset.addEventListener('click', function () {
                var form = document.getElementById('ptk-sla-form');
                form.reset();
                document.getElementById('ptk-sla-id').value = '';
                reset.style.display = 'none';
            });
        }

        // Run automation now.
        var runBtn = document.getElementById('ptk-run-automation');
        if (runBtn) {
            runBtn.addEventListener('click', function () {
                runBtn.disabled = true;
                post(runBtn.getAttribute('data-url'), {}).then(function (res) {
                    runBtn.disabled = false;
                    if (res.success) { toast(runBtn.getAttribute('data-done'), 'success'); }
                }).catch(function () { runBtn.disabled = false; });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDashboard();
        initList();
        initKanban();
        initTicketTabs();
        initTicketModals();
        initTransferModal();
        initCannedSave();
        initCannedInsert();
        initAttachLabel();
        initTicket();
        initMentions();
        initNewTicket();
        initSettings();
    });
})();
