/**
 * Careers — admin behaviour.
 *
 * Vanilla JS on purpose: the admin panel loads jQuery in the footer, and this
 * file is injected on the same hook, so nothing here may assume $ exists.
 *
 * The module base URL is derived from the current path rather than an
 * admin_url() JS variable, because installations with a secret admin prefix
 * (CUSTOM_ADMIN_URL) do not serve the same prefix to every page.
 */
(function () {
    'use strict';

    var path = window.location.pathname;
    var cut  = path.indexOf('/careers');

    if (cut === -1) {
        return;
    }

    var BASE = path.substring(0, cut) + '/careers/';

    /* ────────────────────────────── helpers ────────────────────────────── */

    function $(selector, scope) { return (scope || document).querySelector(selector); }
    function $$(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }

    /** POST a form-encoded body, carrying the CSRF token when one exists. */
    function post(endpoint, data) {
        var body = new FormData();

        Object.keys(data).forEach(function (key) {
            var value = data[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) { body.append(key + '[]', item); });
            } else {
                body.append(key, value);
            }
        });

        if (window.csrfData && window.csrfData.token_name) {
            body.append(window.csrfData.token_name, window.csrfData.hash);
        }

        return fetch(BASE + endpoint, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) { return response.json(); });
    }

    function toast(html, icon) {
        var host = $('#crs-toast-host');

        if (!host) {
            host = document.createElement('div');
            host.className = 'crs-toast-host';
            host.id = 'crs-toast-host';
            document.body.appendChild(host);
        }

        var el = document.createElement('div');
        el.className = 'crs-toast';
        el.innerHTML = '<i class="fa ' + (icon || 'fa-check-circle') + '"></i><div>' + html + '</div>';
        host.appendChild(el);

        setTimeout(function () {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 320);
        }, 6000);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ───────────────────────── generic interactions ────────────────────── */

    document.addEventListener('click', function (event) {
        // Confirm before a destructive link is followed.
        var confirmEl = event.target.closest('[data-crs-confirm]');
        if (confirmEl && !window.confirm(confirmEl.getAttribute('data-crs-confirm'))) {
            event.preventDefault();
            return;
        }

        // Copy-to-clipboard buttons.
        var copyEl = event.target.closest('[data-crs-copy]');
        if (copyEl) {
            event.preventDefault();
            var text = copyEl.getAttribute('data-crs-copy');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { toast('Copied to clipboard'); });
            } else {
                var helper = document.createElement('textarea');
                helper.value = text;
                document.body.appendChild(helper);
                helper.select();
                try { document.execCommand('copy'); toast('Copied to clipboard'); } catch (e) { /* nothing to do */ }
                helper.remove();
            }
            return;
        }

        // Inline show/hide panels (interview form, inline edit rows…).
        var toggleEl = event.target.closest('[data-crs-toggle]');
        if (toggleEl) {
            event.preventDefault();
            var target = $(toggleEl.getAttribute('data-crs-toggle'));
            if (target) {
                target.hidden = !target.hidden;
                if (!target.hidden) {
                    var firstInput = target.querySelector('input, select, textarea');
                    if (firstInput) { firstInput.focus(); }
                }
            }
            return;
        }

        // Job editor tabs.
        var tabEl = event.target.closest('[data-crs-tab]');
        if (tabEl) {
            event.preventDefault();
            var key = tabEl.getAttribute('data-crs-tab');
            $$('[data-crs-tab]').forEach(function (tab) { tab.classList.toggle('active', tab === tabEl); });
            $$('[data-crs-pane]').forEach(function (pane) {
                pane.classList.toggle('active', pane.getAttribute('data-crs-pane') === key);
            });
        }
    });

    /* ───────────────────────────── job editor ──────────────────────────── */

    var addQuestion = $('#crs-add-question');

    if (addQuestion) {
        addQuestion.addEventListener('click', function () {
            var host     = $('#crs-questions');
            var template = $('#crs-question-template');
            if (!host || !template) { return; }

            // Index by a counter that never reuses a number, so removing a row
            // mid-list cannot make two rows share a name.
            var index = host.getAttribute('data-next') || host.children.length;
            host.setAttribute('data-next', parseInt(index, 10) + 1);

            var markup = template.innerHTML.replace(/__i__/g, index);
            var holder = document.createElement('div');
            holder.innerHTML = markup.trim();
            host.appendChild(holder.firstChild);
        });
    }

    document.addEventListener('click', function (event) {
        var removeEl = event.target.closest('[data-crs-remove-question]');
        if (removeEl) {
            var row = removeEl.closest('[data-crs-qrow]');
            if (row) { row.remove(); }
        }
    });

    // Live slug preview while typing a title on a new opening.
    var titleInput = $('#crs-title');
    var slugPreview = $('#crs-slug-preview');

    if (titleInput && slugPreview) {
        titleInput.addEventListener('input', function () {
            var slugField = document.querySelector('input[name="slug"]');
            if (slugField && slugField.value.trim() !== '') { return; }

            slugPreview.textContent = titleInput.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'your-job-title';
        });
    }

    /* ─────────────────────── stars, stages, inline edits ───────────────── */

    // Star toggle (the single star next to a candidate's name).
    document.addEventListener('click', function (event) {
        var starEl = event.target.closest('[data-crs-star]');
        if (!starEl) { return; }

        var id  = starEl.getAttribute('data-crs-star');
        var on  = !starEl.classList.contains('on');
        starEl.classList.toggle('on', on);

        post('update_application', { id: id, field: 'is_starred', value: on ? 1 : 0 });
    });

    // Rating widget — clicking the third star sets 3, clicking it again clears.
    document.addEventListener('click', function (event) {
        var rateEl = event.target.closest('[data-crs-rate]');
        if (!rateEl) { return; }

        var host = rateEl.closest('[data-crs-rating-for]');
        if (!host) { return; }

        var id      = host.getAttribute('data-crs-rating-for');
        var stars   = $$('[data-crs-rate]', host);
        var picked  = parseInt(rateEl.getAttribute('data-crs-rate'), 10);
        var current = stars.filter(function (s) { return s.classList.contains('on'); }).length;
        var value   = picked === current ? 0 : picked;

        stars.forEach(function (star, index) { star.classList.toggle('on', index < value); });

        post('update_application', { id: id, field: 'rating', value: value });
    });

    // Sidebar inline fields (owner, tags) save on change / blur.
    $$('[data-crs-field]').forEach(function (field) {
        var wrap = field.closest('[data-crs-application]');
        var id   = wrap ? wrap.getAttribute('data-crs-application') : null;
        if (!id) { return; }

        var save = function () {
            post('update_application', {
                id: id,
                field: field.getAttribute('data-crs-field'),
                value: field.value
            }).then(function (response) {
                if (response && response.success) { toast('Saved'); }
            });
        };

        field.addEventListener('change', save);
    });

    /**
     * Stage changes. The candidate email is opt-in: the checkbox on the detail
     * page decides, and the table's dropdown never mails anyone.
     */
    function changeStage(id, stage, notify, jobId) {
        return post('set_stage', {
            id: id,
            stage: stage,
            notify: notify ? 1 : 0,
            job_id: jobId || 0
        });
    }

    $$('[data-crs-stage]').forEach(function (select) {
        select.addEventListener('change', function () {
            var id = select.getAttribute('data-crs-stage');

            changeStage(id, select.value, false).then(function (response) {
                if (response && response.success) {
                    select.style.color = response.color;
                    toast('Moved to <strong>' + escapeHtml(response.label) + '</strong>');
                } else if (response && response.message) {
                    toast(escapeHtml(response.message), 'fa-exclamation-circle');
                }
            });
        });
    });

    document.addEventListener('click', function (event) {
        var stageBtn = event.target.closest('[data-crs-set-stage]');
        if (!stageBtn) { return; }

        var bar = stageBtn.closest('[data-crs-stage-bar]');
        if (!bar) { return; }

        var id     = bar.getAttribute('data-crs-stage-bar');
        var stage  = stageBtn.getAttribute('data-crs-set-stage');
        var notify = $('#crs-notify-candidate') && $('#crs-notify-candidate').checked;
        var reason = '';

        if (stage === 'rejected') {
            reason = window.prompt('Optional internal note on why this candidate was not selected:', '') || '';
        }

        post('set_stage', { id: id, stage: stage, notify: notify ? 1 : 0, reason: reason }).then(function (response) {
            if (!response || !response.success) {
                if (response && response.message) { toast(escapeHtml(response.message), 'fa-exclamation-circle'); }
                return;
            }

            $$('[data-crs-set-stage]', bar).forEach(function (button) {
                var active = button === stageBtn;
                button.classList.toggle('active', active);
                button.setAttribute('style', active
                    ? 'background:' + response.color + ';color:#fff;border-color:transparent'
                    : button.getAttribute('style').replace(/background:[^;]+;?/, ''));
            });

            toast('Moved to <strong>' + escapeHtml(response.label) + '</strong>'
                + (response.emailed ? ' — candidate emailed' : ''));

            // A stage change writes a timeline entry; reload so it is visible.
            setTimeout(function () { window.location.reload(); }, 900);
        });
    });

    /* ────────────────────────── applications table ─────────────────────── */

    var checkAll = $('#crs-check-all');
    var bulkBar  = $('#crs-bulk-bar');

    function selectedIds() {
        return $$('.crs-row-check:checked').map(function (box) { return box.value; });
    }

    function syncBulkBar() {
        if (!bulkBar) { return; }

        var ids = selectedIds();
        bulkBar.hidden = ids.length === 0;
        var counter = $('#crs-bulk-count');
        if (counter) { counter.textContent = ids.length; }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            $$('.crs-row-check').forEach(function (box) { box.checked = checkAll.checked; });
            syncBulkBar();
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('crs-row-check')) { syncBulkBar(); }
    });

    var bulkClear = $('#crs-bulk-clear');
    if (bulkClear) {
        bulkClear.addEventListener('click', function () {
            $$('.crs-row-check').forEach(function (box) { box.checked = false; });
            if (checkAll) { checkAll.checked = false; }
            syncBulkBar();
        });
    }

    var bulkApply = $('#crs-bulk-apply');
    if (bulkApply) {
        bulkApply.addEventListener('click', function () {
            var action = $('#crs-bulk-action').value;
            var ids    = selectedIds();

            if (!action || ids.length === 0) { return; }

            if (action === 'delete' && !window.confirm('Delete ' + ids.length + ' application(s) and their resumes permanently?')) {
                return;
            }

            bulkApply.disabled = true;

            post('bulk_action', { bulk_action: action, ids: ids }).then(function (response) {
                bulkApply.disabled = false;
                if (response && response.success) {
                    toast(response.done + ' application(s) updated');
                    window.location.reload();
                }
            });
        });
    }

    /* ──────────────────────────── kanban board ─────────────────────────── */

    var board = $('#crs-board');

    if (board) {
        var dragged = null;

        board.addEventListener('dragstart', function (event) {
            var card = event.target.closest('[data-crs-card]');
            if (!card) { return; }

            dragged = card;
            card.classList.add('crs-dragging');
            event.dataTransfer.effectAllowed = 'move';
            // Firefox refuses to start a drag without payload on the transfer.
            event.dataTransfer.setData('text/plain', card.getAttribute('data-crs-card'));
        });

        board.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('crs-dragging'); }
            $$('.crs-col', board).forEach(function (col) { col.classList.remove('crs-drag-over'); });
            dragged = null;
        });

        board.addEventListener('dragover', function (event) {
            var column = event.target.closest('[data-crs-col]');
            if (!column || !dragged) { return; }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            column.classList.add('crs-drag-over');
        });

        board.addEventListener('dragleave', function (event) {
            var column = event.target.closest('[data-crs-col]');
            if (column && !column.contains(event.relatedTarget)) {
                column.classList.remove('crs-drag-over');
            }
        });

        board.addEventListener('drop', function (event) {
            var column = event.target.closest('[data-crs-col]');
            if (!column || !dragged) { return; }

            event.preventDefault();
            column.classList.remove('crs-drag-over');

            var card  = dragged;
            var from  = card.closest('[data-crs-col]');
            var stage = column.getAttribute('data-crs-col');

            if (from === column) { return; }

            column.querySelector('.crs-col-body').appendChild(card);

            changeStage(card.getAttribute('data-crs-card'), stage, false, board.getAttribute('data-crs-job'))
                .then(function (response) {
                    if (!response || !response.success) {
                        // Put the card back where it came from — the server is
                        // the source of truth, not the drop.
                        from.querySelector('.crs-col-body').appendChild(card);
                        toast('Could not move the candidate', 'fa-exclamation-circle');
                        return;
                    }

                    if (response.counts) {
                        Object.keys(response.counts).forEach(function (key) {
                            var badge = board.querySelector('[data-crs-count="' + key + '"]');
                            if (badge) { badge.textContent = response.counts[key]; }
                        });
                    }

                    toast('Moved to <strong>' + escapeHtml(response.label) + '</strong>');
                });
        });
    }

    /* ─────────────────────── live new-application poller ───────────────── */

    // Only polls while the tab is visible, so a forgotten background tab costs
    // nothing. Seeded with "now" so the first tick never replays old rows.
    var lastSeen = new Date().toISOString().slice(0, 19).replace('T', ' ');
    var seenIds  = {};

    function poll() {
        if (document.hidden) { return; }

        fetch(BASE + 'live?since=' + encodeURIComponent(lastSeen), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.success) { return; }

                lastSeen = data.now;

                $$('[data-crs-new-badge]').forEach(function (badge) { badge.textContent = data.new_total; });
                $$('[data-crs-new-count]').forEach(function (counter) { counter.textContent = data.new_total; });

                (data.items || []).forEach(function (item) {
                    if (seenIds[item.id]) { return; }
                    seenIds[item.id] = true;

                    toast('<strong>' + escapeHtml(item.name) + '</strong> applied for '
                        + escapeHtml(item.job_title || 'an opening')
                        + '<br><a href="' + item.url + '">Open application</a>', 'fa-user-plus');
                });
            })
            .catch(function () { /* a dropped poll is not worth reporting */ });
    }

    setInterval(poll, 25000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { poll(); }
    });
})();
