/**
 * ePTW — module front-end.
 *
 * Everything is data-attribute driven so the views stay plain HTML:
 *   [data-eptw-modal]      opens a modal from a <template>
 *   [data-eptw-act]        posts a workflow action (with confirm)
 *   .eptw-seg              segmented yes / no / n.a. controls
 *   #eptw-form             the permit form (cascading areas, hazard
 *                          suggestions, SIMOPS preview, validity default)
 *   [data-eptw-chart]      Chart.js render from JSON in the attribute
 */
(function () {
    'use strict';

    var wrap = document.querySelector('.eptw-wrap');
    if (!wrap) {
        return;
    }

    var adminUrl = wrap.getAttribute('data-admin-url') || '';
    var csrf     = (typeof csrfData !== 'undefined') ? csrfData : null;

    /* ── Helpers ─────────────────────────────────────────────────────── */

    function toast(message, bad) {
        var el = document.getElementById('eptw-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'eptw-toast';
            el.className = 'eptw-toast';
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.className = 'eptw-toast show' + (bad ? ' bad' : '');
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.className = 'eptw-toast'; }, bad ? 6000 : 3200);
    }

    function post(url, data, done) {
        var fd = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData) && data) {
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        }
        // jQuery's global CSRF default is dropped for FormData bodies; add it ourselves.
        if (csrf && !fd.has(csrf.token_name)) {
            fd.append(csrf.token_name, csrf.hash);
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            if (!res) {
                done({ ok: false, message: xhr.status === 419 ? 'Session expired — reload the page.' : 'Request failed (' + xhr.status + ').' });
                return;
            }
            done(res);
        };
        xhr.onerror = function () { done({ ok: false, message: 'Network error.' }); };
        xhr.send(fd);
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ── Segmented controls ──────────────────────────────────────────── */

    function syncSeg(seg) {
        seg.querySelectorAll('label').forEach(function (label) {
            var input = label.querySelector('input');
            label.classList.toggle('on', !!(input && input.checked));
        });
        var row = seg.closest('.eptw-check-row');
        if (row) {
            var checked = seg.querySelector('input:checked');
            row.classList.toggle('show-remark', !!checked && checked.value !== 'yes');
        }
    }
    function initSegs(root) {
        (root || wrap).querySelectorAll('.eptw-seg').forEach(function (seg) {
            syncSeg(seg);
            seg.addEventListener('change', function () { syncSeg(seg); });
        });
    }
    initSegs();

    /* ── Modals ──────────────────────────────────────────────────────── */

    var back = document.createElement('div');
    back.className = 'eptw-modal-back';
    document.body.appendChild(back);

    function closeModal() {
        back.classList.remove('open');
        back.innerHTML = '';
    }
    back.addEventListener('click', function (e) { if (e.target === back) { closeModal(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeModal(); } });

    function openModal(html, onOpen) {
        back.innerHTML = html;
        back.classList.add('open');
        var modal = back.querySelector('.eptw-modal');
        back.querySelectorAll('[data-eptw-close]').forEach(function (b) { b.addEventListener('click', closeModal); });
        initSegs(back);
        initSignature(back);
        var first = back.querySelector('input:not([type=hidden]), textarea, select');
        if (first) { setTimeout(function () { first.focus(); }, 50); }
        if (onOpen) { onOpen(modal); }

        var form = back.querySelector('form[data-eptw-ajax]');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = form.querySelector('[type=submit]');
                if (btn) { btn.disabled = true; }
                var fd = new FormData(form);
                var pad = form.querySelector('[data-eptw-sig-canvas]');
                if (pad && pad._pad && !pad._pad.isEmpty()) {
                    fd.set('signature', pad._pad.toDataURL('image/png'));
                }
                post(form.getAttribute('action'), fd, function (res) {
                    if (btn) { btn.disabled = false; }
                    if (res.ok) {
                        toast(res.message || 'Done');
                        closeModal();
                        setTimeout(function () { window.location = res.redirect || window.location.href; }, 400);
                    } else {
                        toast(res.message || 'Failed', true);
                    }
                });
            });
        }
    }

    wrap.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-eptw-modal]');
        if (!trigger) {
            return;
        }
        e.preventDefault();
        var tpl = document.getElementById(trigger.getAttribute('data-eptw-modal'));
        if (!tpl) {
            return;
        }
        openModal(tpl.innerHTML, function (modal) {
            // Prefill from data-* on the trigger: data-fill-<name>="value"
            Array.prototype.forEach.call(trigger.attributes, function (attr) {
                if (attr.name.indexOf('data-fill-') === 0) {
                    var field = modal.querySelector('[name="' + attr.name.substring(10) + '"]');
                    if (field) { field.value = attr.value; }
                }
            });
        });
    });

    /* ── Direct workflow actions (with confirm) ──────────────────────── */

    wrap.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-eptw-act]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        var msg = btn.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            return;
        }
        var data = {};
        Array.prototype.forEach.call(btn.attributes, function (attr) {
            if (attr.name.indexOf('data-field-') === 0) {
                data[attr.name.substring(11)] = attr.value;
            }
        });
        btn.disabled = true;
        post(btn.getAttribute('data-eptw-act'), data, function (res) {
            btn.disabled = false;
            toast(res.message || (res.ok ? 'Done' : 'Failed'), !res.ok);
            if (res.ok) {
                setTimeout(function () { window.location = res.redirect || window.location.href; }, 400);
            }
        });
    });

    /* ── Signature pad ───────────────────────────────────────────────── */

    function initSignature(root) {
        root.querySelectorAll('[data-eptw-sig-canvas]').forEach(function (canvas) {
            if (typeof SignaturePad === 'undefined' || canvas._pad) {
                return;
            }
            var resize = function () {
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                if (canvas._pad) { canvas._pad.clear(); }
            };
            resize();
            canvas._pad = new SignaturePad(canvas, { penColor: '#0f172a', minWidth: 0.8, maxWidth: 2.2 });
            var clear = canvas.parentNode.querySelector('[data-eptw-sig-clear]');
            if (clear) {
                clear.addEventListener('click', function (e) { e.preventDefault(); canvas._pad.clear(); });
            }
        });
    }
    initSignature(wrap);

    /* ── Sub-tabs on the permit page ─────────────────────────────────── */

    var subtabs = wrap.querySelectorAll('.eptw-subtab[data-pane]');
    if (subtabs.length) {
        var activate = function (name) {
            subtabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-pane') === name); });
            wrap.querySelectorAll('.eptw-pane').forEach(function (p) { p.classList.toggle('active', p.id === 'pane-' + name); });
            try { history.replaceState(null, '', '#' + name); } catch (e) { /* ignore */ }
        };
        // Tabs, plus any in-page link that names a pane (e.g. "upload them under Documents").
        wrap.querySelectorAll('[data-pane]').forEach(function (t) {
            t.addEventListener('click', function (e) { e.preventDefault(); activate(t.getAttribute('data-pane')); });
        });
        var hash = (window.location.hash || '').replace('#', '');
        if (hash && wrap.querySelector('#pane-' + hash)) {
            activate(hash);
        }
    }

    /* ── Copy buttons ────────────────────────────────────────────────── */

    wrap.querySelectorAll('[data-eptw-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = button.getAttribute('data-eptw-copy');
            var done = function () { toast('Copied: ' + text); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done);
                return;
            }
            var scratch = document.createElement('textarea');
            scratch.value = text;
            document.body.appendChild(scratch);
            scratch.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* nothing sensible */ }
            document.body.removeChild(scratch);
        });
    });

    /* ── Register: filters auto-submit ───────────────────────────────── */

    var filterForm = document.getElementById('eptw-filters');
    if (filterForm) {
        filterForm.querySelectorAll('select, input[type=date]').forEach(function (el) {
            el.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    /* ── Upload: drop zone ───────────────────────────────────────────── */

    wrap.querySelectorAll('.eptw-dropzone').forEach(function (zone) {
        var input = zone.querySelector('input[type=file]');
        var form  = zone.closest('form');
        var label = zone.querySelector('[data-eptw-files]');
        if (!input) {
            return;
        }
        ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('over'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('over'); }); });
        zone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
        input.addEventListener('change', function () {
            if (label) {
                label.textContent = input.files.length ? Array.prototype.map.call(input.files, function (f) { return f.name; }).join(', ') : '';
            }
        });
        if (form && form.hasAttribute('data-eptw-ajax-upload')) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!input.files.length) { toast('Choose a file first.', true); return; }
                var btn = form.querySelector('[type=submit]');
                if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
                post(form.getAttribute('action'), new FormData(form), function (res) {
                    if (btn) { btn.disabled = false; btn.textContent = 'Upload'; }
                    toast(res.message || (res.ok ? 'Uploaded' : 'Upload failed'), !res.ok);
                    if (res.ok) { setTimeout(function () { window.location.hash = 'documents'; window.location.reload(); }, 500); }
                });
            });
        }
    });

    /* ── Charts ──────────────────────────────────────────────────────── */

    if (typeof Chart !== 'undefined') {
        var palette = ['#0f766e', '#2563eb', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#65a30d', '#db2777', '#4f46e5', '#f97316', '#334155', '#14b8a6'];
        wrap.querySelectorAll('[data-eptw-chart]').forEach(function (canvas) {
            var cfg;
            try { cfg = JSON.parse(canvas.getAttribute('data-eptw-chart')); } catch (e) { return; }
            var colors = cfg.colors && cfg.colors.length ? cfg.colors : cfg.labels.map(function (_, i) { return palette[i % palette.length]; });
            var options = { responsive: true, maintainAspectRatio: false, legend: { display: cfg.type === 'doughnut', position: 'right', labels: { boxWidth: 10, fontSize: 11 } } };
            var datasets;
            if (cfg.type === 'line') {
                datasets = cfg.series.map(function (s, i) {
                    return { label: s.label, data: s.data, borderColor: palette[i], backgroundColor: palette[i] + '22', fill: true, lineTension: 0.3, pointRadius: 2 };
                });
                options.legend = { display: true, position: 'top', labels: { boxWidth: 10, fontSize: 11 } };
                options.scales = { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }], xAxes: [{ ticks: { maxTicksLimit: 8 } }] };
            } else {
                datasets = [{ data: cfg.data, backgroundColor: colors, borderWidth: 0 }];
                if (cfg.type === 'horizontalBar' || cfg.type === 'bar') {
                    options.scales = cfg.type === 'horizontalBar'
                        ? { xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }], yAxes: [{ gridLines: { display: false } }] }
                        : { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }], xAxes: [{ gridLines: { display: false } }] };
                }
            }
            new Chart(canvas.getContext('2d'), { type: cfg.type, data: { labels: cfg.labels, datasets: datasets }, options: options });
        });
    }

    /* ── Permit form ─────────────────────────────────────────────────── */

    var form = document.getElementById('eptw-form');
    if (form) {
        var typeId    = form.getAttribute('data-type-id');
        var validity  = parseInt(form.getAttribute('data-validity-hours') || '12', 10);
        var permitId  = form.getAttribute('data-permit-id') || '0';
        var project   = form.querySelector('[name=project_id]');
        var area      = form.querySelector('[name=area_id]');
        var start     = form.querySelector('[name=start_at]');
        var end       = form.querySelector('[name=end_at]');
        var desc      = form.querySelector('[name=work_description]');
        var title     = form.querySelector('[name=work_title]');
        var sugBar    = document.getElementById('eptw-suggest');
        var simBox    = document.getElementById('eptw-simops-preview');
        var endTouched = !!(end && end.value);

        // Cascading areas.
        if (project && area) {
            project.addEventListener('change', function () {
                area.innerHTML = '<option value="">Loading…</option>';
                var xhr = new XMLHttpRequest();
                xhr.open('GET', adminUrl + 'eptw/areas_json/' + project.value, true);
                xhr.onload = function () {
                    var res = null;
                    try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
                    area.innerHTML = '<option value="">— choose area / zone —</option>';
                    if (res && res.areas) {
                        res.areas.forEach(function (a) {
                            var o = document.createElement('option');
                            o.value = a.id;
                            o.textContent = a.name + ' (' + a.code + ')' + (a.shared ? ' · shared' : '');
                            area.appendChild(o);
                        });
                    }
                    simops();
                };
                xhr.send();
            });
        }

        // Default end = start + template validity, until the user edits it.
        if (start && end) {
            end.addEventListener('input', function () { endTouched = true; });
            start.addEventListener('change', function () {
                if (!start.value) { return; }
                if (!endTouched || !end.value) {
                    var d = new Date(start.value);
                    if (!isNaN(d.getTime())) {
                        d.setHours(d.getHours() + validity);
                        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                        end.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                    }
                }
                simops();
                showDuration();
            });
            end.addEventListener('change', function () { simops(); showDuration(); });
        }
        function showDuration() {
            var out = document.getElementById('eptw-duration');
            if (!out || !start || !end || !start.value || !end.value) { return; }
            var h = (new Date(end.value) - new Date(start.value)) / 36e5;
            out.textContent = h > 0 ? 'Validity ' + (Math.round(h * 10) / 10) + ' h' + (h > validity ? ' — longer than the template default of ' + validity + ' h' : '') : 'End must be after start';
            out.className = 'eptw-hint' + (h <= 0 || h > validity ? ' text-warning' : '');
        }
        showDuration();

        // Hazard suggestions from the description.
        var suggest = debounce(function () {
            if (!typeId || !desc) { return; }
            var text = (desc.value || '') + ' ' + (title ? title.value : '');
            if (text.trim().length < 6) { if (sugBar) { sugBar.style.display = 'none'; } return; }
            post(adminUrl + 'eptw/suggest', { permit_type_id: typeId, work_description: text, area_id: area ? area.value : 0 }, function (res) {
                if (!res.ok) { return; }
                wrap.querySelectorAll('.eptw-hazard.suggested, .eptw-check-row.suggested').forEach(function (el) { el.classList.remove('suggested'); });
                var applied = 0;
                (res.hazard_keys || []).forEach(function (k) {
                    var row = form.querySelector('[data-hazard-key="' + k + '"]');
                    if (!row) { return; }
                    row.classList.add('suggested');
                    var yes = row.querySelector('input[value=yes]');
                    var no  = row.querySelector('input[value=no]');
                    if (yes && !(no && no.checked && no.hasAttribute('data-user'))) {
                        yes.checked = true;
                        syncSeg(yes.closest('.eptw-seg'));
                        applied++;
                    }
                });
                (res.control_keys || []).forEach(function (k) {
                    var row = form.querySelector('[data-control-key="' + k + '"]');
                    if (row) { row.classList.add('suggested'); }
                });
                if (sugBar) {
                    var chips = '';
                    (res.extra_hazards || []).forEach(function (h) { chips += '<span class="eptw-chip warn"><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(h) + '</span>'; });
                    (res.ppe || []).slice(0, 8).forEach(function (p) { chips += '<span class="eptw-chip ok"><i class="fa-solid fa-vest"></i> ' + esc(p) + '</span>'; });
                    sugBar.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> <b>Smart suggestions</b> from “' + esc((res.matched || []).join(', ') || 'the template') + '”: '
                        + applied + ' hazard(s) pre-ticked, ' + (res.control_keys || []).length + ' control(s) highlighted. ' + chips
                        + (res.extra_hazards && res.extra_hazards.length ? ' <button type="button" class="eptw-btn eptw-btn-sm" data-eptw-add-hazards>Add extra hazards</button>' : '')
                        + (res.ppe && res.ppe.length ? ' <button type="button" class="eptw-btn eptw-btn-sm" data-eptw-add-ppe>Use PPE list</button>' : '');
                    sugBar.style.display = '';
                    sugBar._extra = res.extra_hazards || [];
                    sugBar._ppe = res.ppe || [];
                }
            });
        }, 600);
        if (desc) { desc.addEventListener('input', suggest); }
        if (title) { title.addEventListener('input', suggest); }
        if (desc && desc.value.trim().length >= 6 && !permitId.match(/^[1-9]/)) { suggest(); }

        // Remember an explicit "No" so a later suggestion does not flip it.
        form.querySelectorAll('.eptw-hazard input[type=radio]').forEach(function (r) {
            r.addEventListener('change', function () { r.setAttribute('data-user', '1'); });
        });

        wrap.addEventListener('click', function (e) {
            if (e.target.closest('[data-eptw-add-hazards]') && sugBar) {
                var box = form.querySelector('[name=extra_hazards]');
                if (box) {
                    var have = box.value.split(/\n/).map(function (s) { return s.trim(); }).filter(Boolean);
                    sugBar._extra.forEach(function (h) { if (have.indexOf(h) === -1) { have.push(h); } });
                    box.value = have.join('\n');
                    toast('Extra hazards added');
                }
            }
            if (e.target.closest('[data-eptw-add-ppe]') && sugBar) {
                var ppe = form.querySelector('[name=ppe]');
                if (ppe) {
                    var list = ppe.value.split(/\n/).map(function (s) { return s.trim(); }).filter(Boolean);
                    sugBar._ppe.forEach(function (p) { if (list.indexOf(p) === -1) { list.push(p); } });
                    ppe.value = list.join('\n');
                    toast('PPE list updated');
                }
            }
            var all = e.target.closest('[data-eptw-all-controls]');
            if (all) {
                e.preventDefault();
                var v = all.getAttribute('data-eptw-all-controls');
                form.querySelectorAll('.eptw-check-row input[value=' + v + ']').forEach(function (r) { r.checked = true; syncSeg(r.closest('.eptw-seg')); });
            }
        });

        // SIMOPS preview.
        var simops = debounce(function () {
            if (!simBox || !area || !area.value || !start || !start.value) { return; }
            post(adminUrl + 'eptw/simops_preview', {
                permit_id: permitId, project_id: project ? project.value : 0, area_id: area.value, permit_type_id: typeId,
                start_at: start.value, end_at: end ? end.value : ''
            }, function (res) {
                if (!res.ok) { return; }
                if (!res.conflicts.length) {
                    simBox.innerHTML = '<div class="eptw-note"><i class="fa-solid fa-circle-check"></i> <b>SIMOPS:</b> no conflicting permits in this area for this window.</div>';
                    return;
                }
                var html = '';
                res.conflicts.forEach(function (c) {
                    html += '<li><b>' + esc(c.description) + '</b> — <a href="' + c.url + '" target="_blank">' + esc(c.permit_no || 'draft') + '</a> (' + esc(c.type_name) + ', ' + esc(c.window) + ') <span class="eptw-badge ' + (c.severity === 'block' ? 'bad' : 'warn') + '">' + (c.severity === 'block' ? 'blocks' : 'warning') + '</span></li>';
                });
                simBox.innerHTML = '<div class="eptw-note ' + (res.conflicts.some(function (c) { return c.severity === 'block'; }) ? 'bad' : 'warn') + '"><i class="fa-solid fa-triangle-exclamation"></i> <b>SIMOPS:</b> ' + res.conflicts.length + ' conflicting permit(s) in this area and time window. A blocking conflict will put this permit on hold when the number is issued.<ul>' + html + '</ul></div>';
            });
        }, 400);
        if (area) { area.addEventListener('change', simops); }
        simops();

        // Which button was pressed → what happens after save.
        form.querySelectorAll('[data-after]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.querySelector('[name=after]').value = btn.getAttribute('data-after');
            });
        });
    }

    /* ── Setup: control sections editor ──────────────────────────────── */

    var sections = document.getElementById('eptw-ctrl-sections');
    if (sections) {
        var addBtn = document.getElementById('eptw-add-section');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var div = document.createElement('div');
                div.className = 'eptw-ctrl-section';
                div.innerHTML = '<input class="eptw-input" name="control_title[]" placeholder="Section title (e.g. Control measures)">'
                    + '<textarea class="eptw-textarea" name="control_items[]" placeholder="One control item per line"></textarea>'
                    + '<button type="button" class="eptw-btn eptw-btn-sm eptw-btn-danger" data-eptw-remove-section><i class="fa fa-times"></i> Remove section</button>';
                sections.appendChild(div);
            });
        }
        sections.addEventListener('click', function (e) {
            var rm = e.target.closest('[data-eptw-remove-section]');
            if (rm) { rm.closest('.eptw-ctrl-section').remove(); }
        });
    }

    /* ── Toolbox talk: add attendee rows ─────────────────────────────── */

    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-eptw-add-worker]');
        if (!add) { return; }
        e.preventDefault();
        var tbody = document.getElementById('eptw-workers');
        if (!tbody) { return; }
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input class="eptw-input" name="worker_name[]" placeholder="Worker name"></td><td><input class="eptw-input" name="worker_id[]" placeholder="ID / badge no."></td>';
        tbody.appendChild(tr);
        tr.querySelector('input').focus();
    });
})();
