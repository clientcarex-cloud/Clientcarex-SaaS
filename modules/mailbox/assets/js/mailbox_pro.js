/* ═══════════════════════════════════════════════════════════════════════
   Mailbox — corporate screens
   Part A: settings   (#mbx-settings,  window.MBX_SETTINGS_BOOT)
   Part B: analytics  (#mbx-analytics, window.MBX_ANALYTICS_BOOT)
   Part C: audit      (#mbx-audit,     window.MBX_AUDIT_BOOT)

   Deliberately self-contained: the webmail app in mailbox.js never loads on
   these pages, so the small shared helpers are repeated rather than exported.
   ═══════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

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
                } else if (data[k] !== null && typeof data[k] === 'object') {
                    // Nested payloads (rule conditions/actions) go over as
                    // conditions[0][field] — exactly what CI expands back
                    // into a PHP array.
                    flatten(f, k, data[k]);
                } else {
                    f.append(k, data[k]);
                }
            });
            return f;
        })();

        var t = csrf();
        fd.append(t.name, t.hash);
        // CI strips the verified token from $_POST — keep the body non-empty.
        fd.append('mbx', '1');

        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(done)
            .catch(fail || function () {});
    }

    function flatten(fd, prefix, value) {
        if (Array.isArray(value)) {
            value.forEach(function (v, i) { flatten(fd, prefix + '[' + i + ']', v); });
        } else if (value !== null && typeof value === 'object') {
            Object.keys(value).forEach(function (k) { flatten(fd, prefix + '[' + k + ']', value[k]); });
        } else {
            fd.append(prefix, value === null || value === undefined ? '' : value);
        }
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

    function fmtMinutes(minutes, i18n) {
        if (minutes === null || minutes === undefined || isNaN(minutes)) { return '—'; }
        minutes = Math.round(+minutes);
        if (minutes < 60) { return minutes + ' ' + i18n.minutes; }
        var h = Math.floor(minutes / 60);
        if (h < 24) { return h + ' ' + i18n.hours + ' ' + (minutes % 60) + ' ' + i18n.minutes; }
        return Math.floor(h / 24) + 'd ' + (h % 24) + ' ' + i18n.hours;
    }

    function tabs(root) {
        var bar = root.querySelector('.mbx-tabs');
        if (!bar) { return; }
        bar.addEventListener('click', function (e) {
            var tab = e.target.closest('[data-tab]');
            if (!tab) { return; }
            bar.querySelectorAll('.mbx-tab').forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            root.querySelectorAll('.mbx-tabpage').forEach(function (p) {
                p.classList.toggle('active', p.dataset.page === tab.dataset.tab);
            });
        });
    }

    /* ═════════════════════════ Part A: settings ══════════════════════════ */
    var settingsRoot = document.getElementById('mbx-settings');
    if (settingsRoot && window.MBX_SETTINGS_BOOT) {
        var S = window.MBX_SETTINGS_BOOT, SU = S.urls, ST = S.i18n;
        var editor = document.getElementById('mbx-editor');

        tabs(settingsRoot);

        /* ── Global options ── */
        document.getElementById('mbx-settings-save').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            post(SU.save, new FormData(document.getElementById('mbx-settings-form')), function (res) {
                btn.disabled = false;
                toast(res.success ? ST.saved : (res.error || ST.loadError));
            }, function () {
                btn.disabled = false;
                toast(ST.loadError);
            });
        });

        /* ── Per-account policies ── */
        settingsRoot.addEventListener('click', function (e) {
            var save = e.target.closest('[data-save-policy]');
            if (!save) { return; }

            var card = save.closest('.mbx-account-policy');
            var payload = { account_id: card.dataset.account };

            card.querySelectorAll('[name]').forEach(function (field) {
                payload[field.name] = field.type === 'checkbox' ? (field.checked ? 1 : 0) : field.value;
            });

            save.disabled = true;
            post(SU.policySave, payload, function (res) {
                save.disabled = false;
                toast(res.success ? ST.saved : ST.loadError);
            }, function () {
                save.disabled = false;
                toast(ST.loadError);
            });
        });

        /* ── Modal shell ── */
        function openEditor(title, bodyHtml, onSave, onDelete) {
            editor.innerHTML =
                '<div class="mbx-modal">' +
                    '<div class="mbx-modal-head"><span>' + esc(title) + '</span>' +
                        '<button class="mbx-icon-btn" data-editor-close><i class="fa fa-xmark"></i></button></div>' +
                    '<div class="mbx-modal-body">' + bodyHtml + '</div>' +
                    '<div class="mbx-modal-foot">' +
                        '<button class="mbx-btn mbx-btn-primary" data-editor-save>' + esc(ST.save) + '</button>' +
                        (onDelete ? '<button class="mbx-btn mbx-btn-danger-light" data-editor-delete>' + esc(ST.del) + '</button>' : '') +
                        '<span class="mbx-composer-status" id="mbx-editor-status"></span>' +
                        '<button class="mbx-btn mbx-btn-light mbx-right" data-editor-close>' + esc(ST.cancel) + '</button>' +
                    '</div>' +
                '</div>';
            editor.style.display = 'flex';
            editor._onSave = onSave;
            editor._onDelete = onDelete;
        }

        function closeEditor() {
            editor.style.display = 'none';
            editor.innerHTML = '';
        }

        editor.addEventListener('click', function (e) {
            if (e.target === editor || e.target.closest('[data-editor-close]')) { closeEditor(); return; }
            if (e.target.closest('[data-editor-save]') && editor._onSave) { editor._onSave(); return; }
            if (e.target.closest('[data-editor-delete]') && editor._onDelete) { editor._onDelete(); }
        });

        function editorStatus(text) {
            var el = document.getElementById('mbx-editor-status');
            if (el) { el.textContent = text; }
        }

        function accountOptions(selected) {
            return '<option value="0">' + esc(ST.allAccounts) + '</option>' +
                S.accounts.map(function (a) {
                    return '<option value="' + a.id + '"' + (+selected === +a.id ? ' selected' : '') + '>' + esc(a.name) + '</option>';
                }).join('');
        }

        function staffOptions(selected) {
            return '<option value="0">— ' + esc(ST.unassigned) + ' —</option>' +
                S.staff.map(function (s) {
                    return '<option value="' + s.staffid + '"' + (+selected === +s.staffid ? ' selected' : '') + '>' + esc(s.full_name) + '</option>';
                }).join('');
        }

        /* ══════════════════════ Rules ══════════════════════ */
        function renderRules() {
            var body = document.querySelector('#mbx-rules-table tbody');
            if (!S.rules.length) {
                body.innerHTML = '<tr><td colspan="5" class="mbx-table-empty">' + esc(ST.noRules) + '</td></tr>';
                return;
            }
            body.innerHTML = S.rules.map(function (r) {
                return '<tr data-rule="' + r.id + '">' +
                    '<td><strong>' + esc(r.name) + '</strong></td>' +
                    '<td>' + esc(r.account_id ? (r.account_name || '') : ST.allAccounts) + '</td>' +
                    '<td class="mbx-num">' + r.hits + '</td>' +
                    '<td><button class="mbx-chip ' + (r.active ? 'mbx-chip-success' : 'mbx-chip-muted') + '" data-rule-toggle="' + r.id + '">' +
                        esc(r.active ? ST.enabled : ST.disabled) + '</button></td>' +
                    '<td class="mbx-num"><button class="mbx-icon-btn" data-rule-edit="' + r.id + '"><i class="fa fa-pen"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function conditionRow(condition) {
            condition = condition || { field: 'from_email', op: 'contains', value: '' };
            return '<div class="mbx-rule-row" data-condition>' +
                '<select data-c-field>' + S.fields.map(function (f) {
                    return '<option value="' + f + '"' + (condition.field === f ? ' selected' : '') + '>' + esc(ST.fields[f] || f) + '</option>';
                }).join('') + '</select>' +
                '<select data-c-op>' + S.operators.map(function (o) {
                    return '<option value="' + o + '"' + (condition.op === o ? ' selected' : '') + '>' + esc(ST.ops[o] || o) + '</option>';
                }).join('') + '</select>' +
                '<input type="text" data-c-value value="' + esc(condition.value || '') + '">' +
                '<button class="mbx-rule-x" data-remove-condition><i class="fa fa-xmark"></i></button>' +
                '</div>';
        }

        function ruleEditor(rule) {
            rule = rule || {
                id: 0, name: '', account_id: 0, match_type: 'all',
                conditions: [{ field: 'from_email', op: 'contains', value: '' }],
                actions: {}, active: 1, stop_processing: 0
            };
            var a = rule.actions || {};

            var html =
                '<div class="mbx-rule-row">' +
                    '<input type="text" id="mbx-r-name" placeholder="' + esc(ST.ruleName) + '" value="' + esc(rule.name) + '">' +
                    '<select id="mbx-r-account">' + accountOptions(rule.account_id) + '</select>' +
                    '<select id="mbx-r-match">' +
                        '<option value="all"' + (rule.match_type === 'all' ? ' selected' : '') + '>' + esc(ST.ruleMatchAll) + '</option>' +
                        '<option value="any"' + (rule.match_type === 'any' ? ' selected' : '') + '>' + esc(ST.ruleMatchAny) + '</option>' +
                    '</select>' +
                '</div>' +

                '<div class="mbx-card-title" style="margin-top:16px"><i class="fa fa-filter"></i> ' + esc(ST.ruleConditions) + '</div>' +
                '<div id="mbx-r-conditions">' + (rule.conditions || []).map(conditionRow).join('') + '</div>' +
                '<button class="mbx-btn mbx-btn-light mbx-btn-sm" id="mbx-r-add"><i class="fa fa-plus"></i> ' + esc(ST.ruleAddCond) + '</button>' +

                '<div class="mbx-card-title" style="margin-top:20px"><i class="fa fa-bolt"></i> ' + esc(ST.ruleActions) + '</div>' +
                '<div class="mbx-action-grid">' +
                    '<div><label class="mbx-setting-hint">' + esc(ST.actionLabel) + '</label>' +
                        '<select id="mbx-r-labels" multiple size="4" style="width:100%">' +
                        S.labels.map(function (l) {
                            var on = (a.labels || []).indexOf(+l.id) !== -1;
                            return '<option value="' + l.id + '"' + (on ? ' selected' : '') + '>' + esc(l.name) + '</option>';
                        }).join('') + '</select></div>' +
                    '<div><label class="mbx-setting-hint">' + esc(ST.actionAssign) + '</label>' +
                        '<select id="mbx-r-assign" style="width:100%">' + staffOptions(a.assign_to) + '</select>' +
                        '<label class="mbx-setting-hint" style="margin-top:8px;display:block">' + esc(ST.actionStatus) + '</label>' +
                        '<select id="mbx-r-status" style="width:100%">' +
                            '<option value="">—</option>' +
                            ['open', 'pending', 'closed'].map(function (s) {
                                var label = s === 'open' ? ST.statusOpen : (s === 'pending' ? ST.statusPending : ST.statusClosed);
                                return '<option value="' + s + '"' + (a.status === s ? ' selected' : '') + '>' + esc(label) + '</option>';
                            }).join('') + '</select></div>' +
                    '<div>' +
                        '<label class="mbx-check-row"><input type="checkbox" id="mbx-r-read"' + (a.mark_read ? ' checked' : '') + '> ' + esc(ST.actionMarkRead) + '</label>' +
                        '<label class="mbx-check-row"><input type="checkbox" id="mbx-r-star"' + (a.star ? ' checked' : '') + '> ' + esc(ST.actionStar) + '</label>' +
                        '<label class="mbx-check-row"><input type="checkbox" id="mbx-r-archive"' + (a.archive ? ' checked' : '') + '> ' + esc(ST.actionArchive) + '</label>' +
                        '<label class="mbx-check-row"><input type="checkbox" id="mbx-r-trash"' + (a.trash ? ' checked' : '') + '> ' + esc(ST.actionTrash) + '</label>' +
                    '</div>' +
                    '<div><label class="mbx-setting-hint">' + esc(ST.actionForward) + '</label>' +
                        '<input type="text" id="mbx-r-forward" style="width:100%" value="' + esc(a.forward_to || '') + '">' +
                        '<label class="mbx-setting-hint" style="margin-top:8px;display:block">' + esc(ST.actionNotify) + '</label>' +
                        '<select id="mbx-r-notify" style="width:100%">' + staffOptions(a.notify) + '</select></div>' +
                '</div>' +

                '<div style="margin-top:16px">' +
                    '<label class="mbx-check-row"><input type="checkbox" id="mbx-r-active"' + (rule.active ? ' checked' : '') + '> ' + esc(ST.enabled) + '</label>' +
                    '<label class="mbx-check-row"><input type="checkbox" id="mbx-r-stop"' + (rule.stop_processing ? ' checked' : '') + '> ' + esc(ST.ruleStop) + '</label>' +
                '</div>' +
                '<div style="margin-top:12px">' +
                    '<button class="mbx-btn mbx-btn-light mbx-btn-sm" id="mbx-r-test"><i class="fa fa-flask"></i> ' + esc(ST.ruleTest) + '</button>' +
                    '<span class="mbx-composer-status" id="mbx-r-test-result"></span>' +
                '</div>';

            function collect() {
                var conditions = [];
                document.querySelectorAll('#mbx-r-conditions [data-condition]').forEach(function (row) {
                    conditions.push({
                        field: row.querySelector('[data-c-field]').value,
                        op: row.querySelector('[data-c-op]').value,
                        value: row.querySelector('[data-c-value]').value
                    });
                });

                var labels = Array.prototype.slice.call(document.getElementById('mbx-r-labels').selectedOptions)
                    .map(function (o) { return o.value; });

                return {
                    id: rule.id,
                    name: document.getElementById('mbx-r-name').value,
                    account_id: document.getElementById('mbx-r-account').value,
                    match_type: document.getElementById('mbx-r-match').value,
                    active: document.getElementById('mbx-r-active').checked ? 1 : 0,
                    stop_processing: document.getElementById('mbx-r-stop').checked ? 1 : 0,
                    conditions: conditions,
                    actions: {
                        labels: labels,
                        assign_to: document.getElementById('mbx-r-assign').value,
                        status: document.getElementById('mbx-r-status').value,
                        mark_read: document.getElementById('mbx-r-read').checked ? 1 : 0,
                        star: document.getElementById('mbx-r-star').checked ? 1 : 0,
                        archive: document.getElementById('mbx-r-archive').checked ? 1 : 0,
                        trash: document.getElementById('mbx-r-trash').checked ? 1 : 0,
                        forward_to: document.getElementById('mbx-r-forward').value,
                        notify: document.getElementById('mbx-r-notify').value
                    }
                };
            }

            openEditor(rule.id ? rule.name : ST.newRule, html, function () {
                editorStatus('…');
                post(SU.ruleSave, collect(), function (res) {
                    if (!res.success) { editorStatus(res.error || ST.loadError); return; }
                    closeEditor();
                    window.location.reload();
                });
            }, rule.id ? function () {
                if (!confirm(ST.ruleDeleteConfirm)) { return; }
                post(SU.ruleDelete, { id: rule.id }, function () { window.location.reload(); });
            } : null);

            document.getElementById('mbx-r-add').addEventListener('click', function () {
                document.getElementById('mbx-r-conditions').insertAdjacentHTML('beforeend', conditionRow(null));
            });
            document.getElementById('mbx-r-conditions').addEventListener('click', function (e) {
                var remove = e.target.closest('[data-remove-condition]');
                if (!remove) { return; }
                var rows = document.querySelectorAll('#mbx-r-conditions [data-condition]');
                if (rows.length > 1) { remove.closest('[data-condition]').remove(); }
            });
            document.getElementById('mbx-r-test').addEventListener('click', function () {
                var result = document.getElementById('mbx-r-test-result');
                result.textContent = '…';
                var payload = collect();
                post(SU.ruleTest, {
                    account_id: payload.account_id,
                    match_type: payload.match_type,
                    conditions: payload.conditions
                }, function (res) {
                    result.textContent = (res.matches ? res.matches.length : 0) + ' ' + ST.ruleTestResult + ' ' + (res.scanned || 0);
                });
            });
        }

        document.getElementById('mbx-rules-table').addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-rule-toggle]');
            if (toggle) {
                post(SU.ruleToggle, { id: toggle.dataset.ruleToggle }, function (res) {
                    var rule = S.rules.filter(function (r) { return +r.id === +toggle.dataset.ruleToggle; })[0];
                    if (rule) { rule.active = res.active; }
                    renderRules();
                });
                return;
            }
            var edit = e.target.closest('[data-rule-edit]');
            if (edit) {
                var rule = S.rules.filter(function (r) { return +r.id === +edit.dataset.ruleEdit; })[0];
                if (rule) { ruleEditor(rule); }
            }
        });

        document.getElementById('mbx-rule-new').addEventListener('click', function () { ruleEditor(null); });

        /* ══════════════════════ Templates ══════════════════════ */
        function renderTemplates() {
            var body = document.querySelector('#mbx-templates-table tbody');
            if (!S.templates.length) {
                body.innerHTML = '<tr><td colspan="4" class="mbx-table-empty">' + esc(ST.noTemplates) + '</td></tr>';
                return;
            }
            body.innerHTML = S.templates.map(function (t) {
                return '<tr>' +
                    '<td><strong>' + esc(t.name) + '</strong></td>' +
                    '<td>' + esc(t.subject || '—') + '</td>' +
                    '<td class="mbx-num">' + (t.usage_count || 0) + '</td>' +
                    '<td class="mbx-num"><button class="mbx-icon-btn" data-template-edit="' + t.id + '"><i class="fa fa-pen"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function templateEditor(template) {
            template = template || { id: 0, name: '', subject: '', body_html: '', is_shared: 1, account_id: 0 };

            var html =
                '<div class="mbx-rule-row">' +
                    '<input type="text" id="mbx-t-name" placeholder="' + esc(ST.templateName) + '" value="' + esc(template.name) + '">' +
                    '<select id="mbx-t-account">' + accountOptions(template.account_id) + '</select>' +
                '</div>' +
                '<div class="mbx-rule-row"><input type="text" id="mbx-t-subject" placeholder="' + esc(ST.subject) + '" value="' + esc(template.subject || '') + '"></div>' +
                '<label class="mbx-setting-hint">' + esc(ST.templateBody) + '</label>' +
                '<div class="mbx-editor" id="mbx-t-body" contenteditable="true" style="min-height:200px">' + (template.body_html || '') + '</div>' +
                '<label class="mbx-check-row"><input type="checkbox" id="mbx-t-shared"' + (+template.is_shared ? ' checked' : '') + '> ' + esc(ST.templateShared) + '</label>';

            openEditor(template.id ? template.name : ST.newTemplate, html, function () {
                editorStatus('…');
                post(SU.templateSave, {
                    id: template.id,
                    name: document.getElementById('mbx-t-name').value,
                    subject: document.getElementById('mbx-t-subject').value,
                    body_html: document.getElementById('mbx-t-body').innerHTML,
                    is_shared: document.getElementById('mbx-t-shared').checked ? 1 : 0,
                    account_id: document.getElementById('mbx-t-account').value
                }, function (res) {
                    if (!res.success) { editorStatus(res.error || ST.loadError); return; }
                    window.location.reload();
                });
            }, template.id ? function () {
                if (!confirm(ST.templateDeleteConfirm)) { return; }
                post(SU.templateDelete, { id: template.id }, function () { window.location.reload(); });
            } : null);
        }

        document.getElementById('mbx-templates-table').addEventListener('click', function (e) {
            var edit = e.target.closest('[data-template-edit]');
            if (!edit) { return; }
            var template = S.templates.filter(function (t) { return +t.id === +edit.dataset.templateEdit; })[0];
            if (template) { templateEditor(template); }
        });
        document.getElementById('mbx-template-new').addEventListener('click', function () { templateEditor(null); });

        /* ══════════════════════ Labels ══════════════════════ */
        function renderLabelsTable() {
            var body = document.querySelector('#mbx-labels-table tbody');
            if (!S.labels.length) {
                body.innerHTML = '<tr><td colspan="4" class="mbx-table-empty">' + esc(ST.noLabels) + '</td></tr>';
                return;
            }
            body.innerHTML = S.labels.map(function (l) {
                var account = S.accounts.filter(function (a) { return +a.id === +l.account_id; })[0];
                return '<tr>' +
                    '<td><span class="mbx-tag" style="background:' + esc(l.color) + '1f;color:' + esc(l.color) + '">' + esc(l.name) + '</span></td>' +
                    '<td>' + esc(account ? account.name : ST.allAccounts) + '</td>' +
                    '<td class="mbx-num">' + (l.message_count || 0) + '</td>' +
                    '<td class="mbx-num"><button class="mbx-icon-btn" data-label-edit="' + l.id + '"><i class="fa fa-pen"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function labelEditor(label) {
            label = label || { id: 0, name: '', color: '#4f46e5', account_id: 0 };

            var html =
                '<div class="mbx-rule-row">' +
                    '<input type="text" id="mbx-l-name" placeholder="' + esc(ST.labelName) + '" value="' + esc(label.name) + '">' +
                    '<input type="color" id="mbx-l-color" value="' + esc(label.color) + '" title="' + esc(ST.labelColor) + '">' +
                    '<select id="mbx-l-account">' + accountOptions(label.account_id) + '</select>' +
                '</div>';

            openEditor(label.id ? label.name : ST.newLabel, html, function () {
                editorStatus('…');
                post(SU.labelSave, {
                    id: label.id,
                    name: document.getElementById('mbx-l-name').value,
                    color: document.getElementById('mbx-l-color').value,
                    account_id: document.getElementById('mbx-l-account').value
                }, function (res) {
                    if (!res.success) { editorStatus(res.error || ST.loadError); return; }
                    window.location.reload();
                });
            }, label.id ? function () {
                if (!confirm(ST.labelDeleteConfirm)) { return; }
                post(SU.labelDelete, { id: label.id }, function () { window.location.reload(); });
            } : null);
        }

        document.getElementById('mbx-labels-table').addEventListener('click', function (e) {
            var edit = e.target.closest('[data-label-edit]');
            if (!edit) { return; }
            var label = S.labels.filter(function (l) { return +l.id === +edit.dataset.labelEdit; })[0];
            if (label) { labelEditor(label); }
        });
        document.getElementById('mbx-label-new-admin').addEventListener('click', function () { labelEditor(null); });

        renderRules();
        renderTemplates();
        renderLabelsTable();
    }

    /* ═════════════════════════ Part B: analytics ═════════════════════════ */
    var analyticsRoot = document.getElementById('mbx-analytics');
    if (analyticsRoot && window.MBX_ANALYTICS_BOOT) {
        var A = window.MBX_ANALYTICS_BOOT, AT = A.i18n;

        function isoDaysAgo(days) {
            var d = new Date();
            d.setDate(d.getDate() - days);
            return d.toISOString().slice(0, 10);
        }

        function range() {
            var period = document.getElementById('mbx-a-period').value;
            if (period === 'custom') {
                return {
                    from: document.getElementById('mbx-a-from').value || isoDaysAgo(29),
                    to: document.getElementById('mbx-a-to').value || new Date().toISOString().slice(0, 10)
                };
            }
            return { from: isoDaysAgo(+period - 1), to: new Date().toISOString().slice(0, 10) };
        }

        function stat(label, value, sub, tone) {
            return '<div class="mbx-stat' + (tone ? ' mbx-stat-' + tone : '') + '">' +
                '<div class="mbx-stat-label">' + esc(label) + '</div>' +
                '<div class="mbx-stat-value">' + esc(value) + '</div>' +
                (sub ? '<div class="mbx-stat-sub">' + esc(sub) + '</div>' : '') +
                '</div>';
        }

        function meter(percent) {
            var tone = percent >= 90 ? '' : (percent >= 70 ? ' mbx-meter-warn' : ' mbx-meter-bad');
            return '<div class="mbx-meter' + tone + '"><span style="width:' + Math.max(0, Math.min(100, percent)) + '%"></span></div>';
        }

        function render(data) {
            var t = data.totals || {};
            var received = +t.received || 0;
            var answered = +t.answered || 0;
            var breached = +t.sla_breached || 0;
            var compliance = answered + breached > 0 ? Math.round(((answered - breached) / Math.max(1, answered + breached)) * 100) : 100;

            var stats =
                stat(AT.received, received, '', '') +
                stat(AT.sent, +t.sent || 0, '', '') +
                stat(AT.avgResponse, fmtMinutes(t.avg_response_minutes, AT), '', '') +
                stat(AT.openConv, +t.open_conv || 0, AT.unassigned + ': ' + (+t.unassigned || 0), (+t.unassigned > 0 ? 'warn' : 'ok'));

            if (data.sla && data.sla.enabled) {
                stats += stat(AT.slaCompliance, compliance + '%', AT.slaBreached + ': ' + breached, compliance >= 90 ? 'ok' : (compliance >= 70 ? 'warn' : 'bad'));
            }
            if (+t.scheduled > 0) {
                stats += stat(AT.scheduled, +t.scheduled, '', '');
            }
            document.getElementById('mbx-a-stats').innerHTML = stats;

            /* Volume bars — plain divs, no chart library to ship. */
            var daily = data.daily || [];
            var max = Math.max.apply(null, [1].concat(daily.map(function (d) {
                return Math.max(+d.received, +d.sent);
            })));

            document.getElementById('mbx-a-volume').innerHTML = daily.length
                ? '<div class="mbx-bars">' + daily.map(function (d) {
                    return '<div class="mbx-bar-group" title="' + esc(d.d + ' — ' + AT.received + ': ' + d.received + ', ' + AT.sent + ': ' + d.sent) + '">' +
                        '<div class="mbx-bar-stack">' +
                            '<div class="mbx-bar mbx-bar-in" style="height:' + ((+d.received / max) * 100) + '%"></div>' +
                            '<div class="mbx-bar mbx-bar-out" style="height:' + ((+d.sent / max) * 100) + '%"></div>' +
                        '</div>' +
                        '<div class="mbx-bar-label">' + esc(d.d.slice(5)) + '</div></div>';
                }).join('') + '</div>' +
                '<div class="mbx-legend"><span><i class="mbx-bar-in"></i>' + esc(AT.received) + '</span>' +
                '<span><i class="mbx-bar-out"></i>' + esc(AT.sent) + '</span></div>'
                : '<div class="mbx-table-empty">' + esc(AT.noData) + '</div>';

            /* By account */
            var accounts = data.by_account || [];
            document.getElementById('mbx-a-accounts').innerHTML = accounts.length
                ? '<thead><tr><th>' + esc(AT.account) + '</th><th class="mbx-num">' + esc(AT.received) + '</th>' +
                  '<th class="mbx-num">' + esc(AT.sent) + '</th><th class="mbx-num">' + esc(AT.avgResponse) + '</th>' +
                  '<th class="mbx-num">' + esc(AT.slaBreached) + '</th><th>' + esc(AT.slaCompliance) + '</th></tr></thead><tbody>' +
                  accounts.map(function (a) {
                      var ans = +a.answered || 0, br = +a.breached || 0;
                      var pct = ans + br > 0 ? Math.round(((ans - br) / Math.max(1, ans + br)) * 100) : 100;
                      return '<tr><td><strong>' + esc(a.name) + '</strong><br><span class="mbx-muted mbx-small">' + esc(a.email) + '</span></td>' +
                          '<td class="mbx-num">' + (+a.received || 0) + '</td>' +
                          '<td class="mbx-num">' + (+a.sent || 0) + '</td>' +
                          '<td class="mbx-num">' + esc(fmtMinutes(a.avg_response_minutes, AT)) + '</td>' +
                          '<td class="mbx-num">' + br + '</td>' +
                          '<td>' + meter(pct) + '</td></tr>';
                  }).join('') + '</tbody>'
                : '<tbody><tr><td class="mbx-table-empty">' + esc(AT.noData) + '</td></tr></tbody>';

            /* By staff */
            var staff = data.by_staff || [];
            document.getElementById('mbx-a-staff').innerHTML = staff.length
                ? '<thead><tr><th>' + esc(AT.staff) + '</th><th class="mbx-num">' + esc(AT.sent) + '</th>' +
                  '<th class="mbx-num">' + esc(AT.assigned) + '</th><th class="mbx-num">' + esc(AT.closed) + '</th>' +
                  '<th class="mbx-num">' + esc(AT.avgResponse) + '</th></tr></thead><tbody>' +
                  staff.map(function (s) {
                      return '<tr><td>' + esc(s.full_name) + '</td>' +
                          '<td class="mbx-num">' + (+s.sent || 0) + '</td>' +
                          '<td class="mbx-num">' + (+s.assigned || 0) + '</td>' +
                          '<td class="mbx-num">' + (+s.closed || 0) + '</td>' +
                          '<td class="mbx-num">' + esc(fmtMinutes(s.avg_response_minutes, AT)) + '</td></tr>';
                  }).join('') + '</tbody>'
                : '<tbody><tr><td class="mbx-table-empty">' + esc(AT.noData) + '</td></tr></tbody>';

            /* Top senders */
            var senders = data.top_senders || [];
            document.getElementById('mbx-a-senders').innerHTML = senders.length
                ? '<thead><tr><th>' + esc(AT.sender) + '</th><th class="mbx-num">' + esc(AT.count) + '</th></tr></thead><tbody>' +
                  senders.map(function (s) {
                      return '<tr><td>' + esc(s.from_name || '') + ' <span class="mbx-muted mbx-small">' + esc(s.from_email) + '</span></td>' +
                          '<td class="mbx-num">' + s.cnt + '</td></tr>';
                  }).join('') + '</tbody>'
                : '<tbody><tr><td class="mbx-table-empty">' + esc(AT.noData) + '</td></tr></tbody>';

            /* Busiest hours */
            var byHour = {};
            (data.by_hour || []).forEach(function (h) { byHour[+h.h] = +h.cnt; });
            var hourMax = Math.max.apply(null, [1].concat(Object.keys(byHour).map(function (k) { return byHour[k]; })));
            var hours = '';
            for (var h = 0; h < 24; h++) {
                var count = byHour[h] || 0;
                hours += '<div class="mbx-bar-group" title="' + h + ':00 — ' + count + '">' +
                    '<div class="mbx-bar-stack"><div class="mbx-bar mbx-bar-in" style="width:70%;max-width:26px;height:' + ((count / hourMax) * 100) + '%"></div></div>' +
                    '<div class="mbx-bar-label">' + h + '</div></div>';
            }
            document.getElementById('mbx-a-hours').innerHTML = '<div class="mbx-bars">' + hours + '</div>';
        }

        function load() {
            var r = range();
            var url = A.url + '?account=' + encodeURIComponent(document.getElementById('mbx-a-account').value) +
                '&from=' + r.from + '&to=' + r.to;
            get(url, render, function () { toast(AT.loadError); });
        }

        document.getElementById('mbx-a-period').addEventListener('change', function () {
            var custom = this.value === 'custom';
            document.getElementById('mbx-a-custom').style.display = custom ? 'flex' : 'none';
            document.getElementById('mbx-a-custom2').style.display = custom ? 'flex' : 'none';
            if (!custom) { load(); }
        });
        document.getElementById('mbx-a-account').addEventListener('change', load);
        document.getElementById('mbx-a-apply').addEventListener('click', load);

        load();
    }

    /* ═════════════════════════ Part C: audit ═════════════════════════════ */
    var auditRoot = document.getElementById('mbx-audit');
    if (auditRoot && window.MBX_AUDIT_BOOT) {
        var AU = window.MBX_AUDIT_BOOT, AUT = AU.i18n;
        var page = 1, totalPages = 1;

        function query() {
            return 'account_id=' + encodeURIComponent(document.getElementById('mbx-f-account').value) +
                '&staff_id=' + encodeURIComponent(document.getElementById('mbx-f-staff').value) +
                '&action=' + encodeURIComponent(document.getElementById('mbx-f-action').value) +
                '&from=' + encodeURIComponent(document.getElementById('mbx-f-from').value) +
                '&to=' + encodeURIComponent(document.getElementById('mbx-f-to').value) +
                '&search=' + encodeURIComponent(document.getElementById('mbx-f-search').value);
        }

        function load() {
            var body = document.querySelector('#mbx-audit-table tbody');
            body.innerHTML = '<tr><td colspan="7" class="mbx-table-empty"><i class="fa fa-circle-notch fa-spin"></i></td></tr>';

            get(AU.urls.data + '?page=' + page + '&' + query(), function (res) {
                totalPages = res.total_pages || 1;
                document.getElementById('mbx-audit-pager').textContent =
                    res.total ? (page + ' / ' + totalPages + ' — ' + res.total) : '';

                if (!res.rows || !res.rows.length) {
                    body.innerHTML = '<tr><td colspan="7" class="mbx-table-empty">' + esc(AUT.empty) + '</td></tr>';
                    return;
                }

                body.innerHTML = res.rows.map(function (r) {
                    return '<tr>' +
                        '<td class="mbx-muted mbx-small">' + esc(r.created_at) + '</td>' +
                        '<td>' + esc((r.staff_name || '').trim() || AUT.system) + '</td>' +
                        '<td><span class="mbx-tag">' + esc(r.action.replace(/_/g, ' ')) + '</span></td>' +
                        '<td>' + esc(r.account_name || '—') + '</td>' +
                        '<td>' + esc(r.subject || '—') + '</td>' +
                        '<td class="mbx-muted">' + esc(r.details || '') + '</td>' +
                        '<td class="mbx-muted mbx-small">' + esc(r.ip || '') + '</td>' +
                        '</tr>';
                }).join('');
            }, function () {
                body.innerHTML = '<tr><td colspan="7" class="mbx-table-empty">' + esc(AUT.loadError) + '</td></tr>';
            });
        }

        document.getElementById('mbx-f-apply').addEventListener('click', function () { page = 1; load(); });
        document.getElementById('mbx-f-reset').addEventListener('click', function () {
            ['mbx-f-account', 'mbx-f-staff', 'mbx-f-action', 'mbx-f-from', 'mbx-f-to', 'mbx-f-search']
                .forEach(function (id) { document.getElementById(id).value = ''; });
            page = 1;
            load();
        });
        document.getElementById('mbx-audit-prev').addEventListener('click', function () {
            if (page > 1) { page--; load(); }
        });
        document.getElementById('mbx-audit-next').addEventListener('click', function () {
            if (page < totalPages) { page++; load(); }
        });
        document.getElementById('mbx-audit-export').addEventListener('click', function () {
            window.location.href = AU.urls.csv + '?' + query();
        });

        load();
    }
})();
