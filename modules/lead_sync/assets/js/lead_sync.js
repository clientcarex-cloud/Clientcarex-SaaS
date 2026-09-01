/**
 * Lead Sync — connection editor.
 *
 * Two jobs: show only the fields the chosen options need (and *disable* the
 * hidden ones, so a hidden empty "API key" box can never overwrite a saved
 * service-account key on submit), and read the sheet over AJAX to build the
 * column-mapping table.
 */
(function () {
    'use strict';

    var wrap = document.querySelector('.lsy-wrap');
    if (!wrap) {
        return;
    }

    var form = document.getElementById('lsy-form');

    /* ── Conditional field groups ───────────────────────────────────────── */

    function toggleGroups(attribute, current) {
        wrap.querySelectorAll('[' + attribute + ']').forEach(function (group) {
            var shown = group.getAttribute(attribute).split(' ').indexOf(current) !== -1;

            group.style.display = shown ? '' : 'none';
            // A hidden field must not post. Otherwise the empty box belonging to
            // the mode you did not pick silently wipes the value you did save.
            group.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !shown;
            });
        });
    }

    var authMode = document.getElementById('lsy-auth-mode');
    if (authMode) {
        var syncAuth = function () { toggleGroups('data-lsy-auth', authMode.value); };
        authMode.addEventListener('change', syncAuth);
        syncAuth();
    }

    var assignMode = document.getElementById('lsy-assign-mode');
    if (assignMode) {
        var syncAssign = function () { toggleGroups('data-lsy-assign', assignMode.value); };
        assignMode.addEventListener('change', syncAssign);
        syncAssign();
    }

    /* ── Copy buttons ───────────────────────────────────────────────────── */

    wrap.querySelectorAll('[data-lsy-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.querySelector(button.getAttribute('data-lsy-copy'));
            if (!target) {
                return;
            }

            var text = target.textContent;
            var done = function () {
                var original = button.innerHTML;
                button.innerHTML = '<i class="fa fa-check"></i> Copied';
                setTimeout(function () { button.innerHTML = original; }, 1600);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done);
                return;
            }

            // http:// origins and older browsers get no async clipboard.
            var scratch = document.createElement('textarea');
            scratch.value = text;
            document.body.appendChild(scratch);
            scratch.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* nothing sensible to do */ }
            document.body.removeChild(scratch);
        });
    });

    /* ── Read the sheet and build the mapping table ─────────────────────── */

    var testButton = document.getElementById('lsy-test');
    var resultBox  = document.getElementById('lsy-test-result');
    var mapBox     = document.getElementById('lsy-map');

    if (!testButton || !form) {
        return;
    }

    function notice(kind, html) {
        resultBox.innerHTML = '<div class="lsy-note ' + kind + '" style="margin-top:14px">' + html + '</div>';
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    testButton.addEventListener('click', function () {
        var url = document.getElementById('lsy-sheet-url');
        if (!url || url.value.trim() === '') {
            notice('bad', 'Paste the Google Sheet link first.');
            return;
        }

        var original = testButton.innerHTML;
        testButton.disabled = true;
        testButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Reading…';
        mapBox.innerHTML = '<div class="lsy-map-status lsy-muted"><i class="fa fa-spinner fa-spin"></i> Reading the sheet…</div>';

        var payload = {
            sheet_url:  url.value,
            tab_name:   (form.querySelector('[name="tab_name"]') || {}).value || '',
            gid:        (form.querySelector('[name="gid"]') || {}).value || '',
            auth_mode:  authMode ? authMode.value : 'public',
            has_header: form.querySelector('[name="has_header"]').checked ? 1 : 0
        };

        // The visible credential box for the chosen mode, if the manager typed one.
        var credentials = form.querySelector('[data-lsy-auth="' + payload.auth_mode + '"] [name="credentials"]');
        if (credentials && credentials.value.trim() !== '') {
            payload.credentials = credentials.value;
        }

        // csrfData is printed by the core; $.ajaxSetup merges it for plain objects.
        $.post(
            admin_url + 'lead_sync/preview/' + (wrap.getAttribute('data-connection-id') || 0),
            payload
        ).done(function (response) {
            if (!response || !response.ok) {
                notice('bad', '<strong>Could not read the sheet.</strong><br>' + escapeHtml((response && response.error) || 'Unknown error.'));
                mapBox.innerHTML = '<div class="lsy-map-status lsy-muted">Nothing to map yet.</div>';
                return;
            }

            notice('', '<strong>Sheet read.</strong> ' + response.total + ' row' + (response.total === 1 ? '' : 's')
                + ' found, <strong>' + response.new_rows + '</strong> of them not imported yet.');
            renderMap(response);
        }).fail(function () {
            notice('bad', 'The request failed. Check that you are still signed in and try again.');
            mapBox.innerHTML = '<div class="lsy-map-status lsy-muted">Nothing to map yet.</div>';
        }).always(function () {
            testButton.disabled = false;
            testButton.innerHTML = original;
        });
    });

    function renderMap(data) {
        mapBox.innerHTML = '';

        if (!data.headers.length) {
            mapBox.innerHTML = '<div class="lsy-map-status lsy-muted">The sheet has no columns.</div>';
            return;
        }

        var table = document.createElement('table');
        table.className = 'lsy-table lsy-map';
        table.innerHTML = '<thead><tr><th>Column in the sheet</th><th>Becomes</th><th>Example</th></tr></thead>';

        var body = document.createElement('tbody');

        data.headers.forEach(function (header, index) {
            var row = document.createElement('tr');

            var name = document.createElement('td');
            name.innerHTML = '<span class="lsy-strong">' + escapeHtml(data.labels[index] || header) + '</span>';
            row.appendChild(name);

            var choice = document.createElement('td');
            var select = document.createElement('select');
            select.className = 'lsy-select';
            // Built as a DOM node rather than markup so a column called
            // What's your budget? cannot break the form field name.
            select.name = 'column_map[' + header + ']';

            Object.keys(data.targets).forEach(function (key) {
                var option = document.createElement('option');
                option.value = key;
                option.textContent = data.targets[key];
                if (data.map[index] === key) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            choice.appendChild(select);
            row.appendChild(choice);

            var sample = document.createElement('td');
            sample.className = 'lsy-sample';
            var values = [];
            data.rows.forEach(function (sheetRow) {
                var value = sheetRow[index];
                if (value && values.length < 2 && values.indexOf(value) === -1) {
                    values.push(value);
                }
            });
            sample.textContent = values.join('  ·  ');
            row.appendChild(sample);

            body.appendChild(row);
        });

        table.appendChild(body);

        var scroll = document.createElement('div');
        scroll.className = 'lsy-table-scroll';
        scroll.appendChild(table);
        mapBox.appendChild(scroll);

        var hint = document.createElement('div');
        hint.className = 'lsy-hint';
        hint.innerHTML = 'Anything left on <em>Notes (prefixed with the column name)</em> is still kept — '
            + 'it goes into the lead\'s description as “Question: answer”, so nothing the person typed is lost.';
        mapBox.appendChild(hint);
    }
}());
