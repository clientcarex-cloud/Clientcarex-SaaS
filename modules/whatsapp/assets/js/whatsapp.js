/* WhatsApp (Official Cloud API) — admin UI */
(function () {
    'use strict';

    if (typeof WAPI === 'undefined') return;

    /* ── CSRF helper (mirrors Perfex global behaviour) ── */
    function csrf() {
        var d = { name: WAPI.csrf.name, hash: WAPI.csrf.hash };
        if (typeof window.csrfData !== 'undefined' && window.csrfData.token_name) {
            d.name = window.csrfData.token_name;
            d.hash = window.csrfData.hash;
        }
        return d;
    }
    function refreshCsrf(newHash) {
        if (!newHash) return;
        WAPI.csrf.hash = newHash;
        if (typeof window.csrfData !== 'undefined') window.csrfData.hash = newHash;
    }

    function notify(msg, type) {
        if (typeof alert_float === 'function') {
            alert_float(type || 'success', msg);
        } else {
            console.log(msg);
        }
    }

    /**
     * Failure notice that carries the cause and the fix. `res` is any JSON
     * reply with {message, title, hint, action} — the action becomes a button
     * that actually resolves the problem.
     */
    function notifyFailure(res) {
        var msg = res.message || 'Request failed';
        notify(msg, 'danger');
        if (!res.hint && !res.title) return;

        var $box = $('#wapi-error-notice');
        if (!$box.length) {
            $box = $('<div id="wapi-error-notice" class="wapi-alert wapi-alert-danger wapi-alert-floating"></div>');
            $('.wapi-wrap').prepend($box);
        }
        $box.html(
            '<i class="fa fa-triangle-exclamation"></i><div>' +
            '<button class="wapi-alert-close" onclick="$(this).closest(\'.wapi-alert\').remove()">&times;</button>' +
            '<strong>' + esc(res.title || 'Message not sent') + '</strong>' +
            '<p>' + esc(res.hint || msg) + '</p>' +
            '<p class="wapi-muted wapi-alert-raw">' + esc(msg) + '</p>' +
            actionButton(res.action) + '</div>'
        );
        $('html, body').animate({ scrollTop: 0 }, 200);
    }

    /** Map a hint action to the control that fixes it. */
    function actionButton(action) {
        var map = {
            register: '<button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiOpenRegisterFirstUnregistered()"><i class="fa fa-key"></i> Register number</button>',
            reconnect: '<a href="' + WAPI.urls.connect + '" class="wapi-btn wapi-btn-primary wapi-btn-sm"><i class="fa-brands fa-facebook"></i> Reconnect account</a>',
            refresh: '<button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiRefreshNumbers()"><i class="fa fa-rotate"></i> Refresh numbers</button>',
            templates: '<button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiSyncTemplates()"><i class="fa fa-rotate"></i> Sync templates</button>',
            template: '<button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiSyncTemplates()"><i class="fa fa-rotate"></i> Re-sync templates</button>',
            credentials: '<button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="$(\'.wapi-tab[data-tab=overview]\').click()">Open provider console</button>',
            connect: '<a href="' + WAPI.urls.connect + '" class="wapi-btn wapi-btn-primary wapi-btn-sm"><i class="fa-brands fa-facebook"></i> Connect WhatsApp</a>',
            quality: '<a href="https://business.facebook.com/wa/manage/phone-numbers/" target="_blank" rel="noopener" class="wapi-btn wapi-btn-light wapi-btn-sm"><i class="fa fa-arrow-up-right-from-square"></i> Open WhatsApp Manager</a>',
            billing: '<a href="https://business.facebook.com/billing_hub/payment_settings" target="_blank" rel="noopener" class="wapi-btn wapi-btn-light wapi-btn-sm"><i class="fa fa-arrow-up-right-from-square"></i> Billing settings</a>',
            verify: '<a href="https://business.facebook.com/wa/manage/phone-numbers/" target="_blank" rel="noopener" class="wapi-btn wapi-btn-light wapi-btn-sm"><i class="fa fa-arrow-up-right-from-square"></i> Verify in WhatsApp Manager</a>',
            live: '<a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener" class="wapi-btn wapi-btn-light wapi-btn-sm"><i class="fa fa-arrow-up-right-from-square"></i> Meta App dashboard</a>'
        };
        return map[action] || '';
    }

    function post(url, data, cb) {
        data = data || {};
        var c = csrf();
        data[c.name] = c.hash;
        data._wapi = 1; // sentinel — CI strips the CSRF token after verifying
        $.ajax({
            url: url, type: 'POST', data: data, dataType: 'json',
            success: function (res) {
                if (res && res.csrf_hash) refreshCsrf(res.csrf_hash);
                if (cb) cb(res || {});
            },
            error: function () { notify('Request failed. Please try again.', 'danger'); if (cb) cb({ success: false }); }
        });
    }

    function get(url, data, cb) {
        $.ajax({
            url: url, type: 'GET', data: data || {}, dataType: 'json',
            success: function (res) {
                if (res && res.csrf_hash) refreshCsrf(res.csrf_hash);
                if (cb) cb(res || {});
            },
            error: function () { if (cb) cb({ success: false }); }
        });
    }

    /** Multipart POST (file uploads) — jQuery must not touch the body. */
    function postForm(url, formEl, cb) {
        postFD(url, new FormData(formEl), cb);
    }

    /** Same, from an already-built FormData. */
    function postFD(url, fd, cb) {
        var c = csrf();
        fd.append(c.name, c.hash);
        fd.append('_wapi', 1);
        $.ajax({
            url: url, type: 'POST', data: fd, dataType: 'json',
            processData: false, contentType: false,
            success: function (res) {
                if (res && res.csrf_hash) refreshCsrf(res.csrf_hash);
                if (cb) cb(res || {});
            },
            error: function () { notify('Upload failed. Please try again.', 'danger'); if (cb) cb({ success: false }); }
        });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function reload() { window.location.reload(); }

    /** Tab-wise permission check — mirrors the PHP gates. */
    function can(tab) { return !!(WAPI.can && WAPI.can[tab]); }
    window.wapiCan = can;

    /* ═══════════════ Tabs ═══════════════ */

    var chatThreadTimer = null, chatMsgTimer = null, campaignTimer = null;

    /**
     * Inbox refresh cadence.
     *
     * The thread poll runs on EVERY tab of the module, not just the Inbox —
     * otherwise the unread badge froze at whatever the page render found and
     * only caught up once you opened the Inbox. Reading the inbox deserves a
     * tight loop; sitting on Overview only needs the badge to stay honest.
     */
    var THREAD_POLL_OPEN = 6000, THREAD_POLL_BACKGROUND = 12000;

    function inboxTabOpen() { return $('.wapi-tab[data-tab="inbox"]').hasClass('active'); }

    /** (Re)arm the thread poll at the cadence the current tab deserves. */
    function startThreadPolling() {
        if (!can('inbox')) return;
        if (chatThreadTimer) clearInterval(chatThreadTimer);
        chatThreadTimer = setInterval(function () {
            // A browser tab in the background is nobody's real-time view — the
            // global notifier keeps watch there and we refresh on focus.
            if (document.hidden) return;
            wapiLoadThreads(false);
        }, inboxTabOpen() ? THREAD_POLL_OPEN : THREAD_POLL_BACKGROUND);
    }

    $(document).on('click', '.wapi-tab', function () {
        var tab = $(this).data('tab');
        $('.wapi-tab').removeClass('active');
        $(this).addClass('active');
        $('.wapi-panel').removeClass('active');
        $('.wapi-panel[data-panel="' + tab + '"]').addClass('active');

        stopMsgPolling();
        if (tab === 'inbox') {
            wapiLoadThreads(true);
            if (currentChat.phone) { window.wapiActiveThread = currentChat.phone; startMsgPolling(); }
        } else {
            // Off the inbox, every arrival is worth announcing again.
            window.wapiActiveThread = null;
        }
        startThreadPolling();

        if (tab === 'bulk') {
            startCampaignPolling();
        } else if (campaignTimer) {
            clearInterval(campaignTimer); campaignTimer = null;
        }
        // Profile is read live from Meta — fetch on first open only.
        if (tab === 'profile' && !profileLoaded) {
            wapiLoadProfile();
        }
    });

    function stopMsgPolling() {
        if (chatMsgTimer) { clearInterval(chatMsgTimer); chatMsgTimer = null; }
    }

    /**
     * Push hook for the global notifier (views/_notify_script.php): it already
     * polls the unread feed on every admin page, so when it lands first we take
     * its count straight away instead of waiting for our own next tick.
     *
     * @param count total unread
     * @param fresh items the notifier had not seen before
     */
    window.wapiOnInboxPing = function (count, items, fresh) {
        updateUnreadBadge(parseInt(count, 10) || 0);
        if (!fresh || !fresh.length) return;
        wapiLoadThreads(false);
        if (currentChat.phone) wapiLoadMessages(false);
    };

    // Coming back to the browser tab should show current state immediately.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden || !can('inbox')) return;
        wapiLoadThreads(false);
        if (inboxTabOpen() && currentChat.phone) wapiLoadMessages(false);
    });

    /* ═══════════════ Connect banners (query params) ═══════════════ */

    $(function () {
        var q = new URLSearchParams(window.location.search);
        if (q.get('whatsapp_connected')) notify('WhatsApp connected successfully!', 'success');
        if (q.get('whatsapp_error')) notify(q.get('whatsapp_error'), 'danger');
        if (q.get('whatsapp_warn')) notify(q.get('whatsapp_warn'), 'warning');
        if (q.get('whatsapp_connected') || q.get('whatsapp_error') || q.get('whatsapp_warn')) {
            window.history.replaceState({}, '', window.location.pathname);
        }
        populateTemplateSelects();
        if (can('overview') && WAPI.series) wapiRenderChart(WAPI.series);

        // Run the connection self-test as soon as the page settles. A stale
        // cache also re-pulls each number's live state from Meta.
        if (WAPI.hasConnection) {
            if (can('overview')) {
                setTimeout(function () { wapiLoadDiagnostics(!!WAPI.healthStale); }, 400);
            }
            // Prime the inbox too, so the unread badge is right without having
            // to open the tab first — then keep it live from whichever tab the
            // user actually sits on.
            if (can('inbox')) {
                setTimeout(function () { wapiLoadThreads(false); }, 700);
                startThreadPolling();
            }
        }

        // A restricted role may not have Overview — open whichever tab is first.
        if (WAPI.firstTab && WAPI.firstTab !== 'overview') {
            $('.wapi-tab[data-tab="' + WAPI.firstTab + '"]').trigger('click');
        }

        // Arriving from a notification (?tab=inbox&thread=…): open that tab and
        // queue the thread, which openPendingThread() picks up once the list
        // has rendered. The query string is then dropped so a refresh is clean.
        var wanted = q.get('tab');
        if (wanted && can(wanted)) {
            pendingThread = q.get('thread');
            $('.wapi-tab[data-tab="' + wanted + '"]').trigger('click');
            window.history.replaceState({}, '', window.location.pathname);
        }
        // Provider console: probe the webhook wiring on load.
        if ($('#wapi-webhook-report').length) {
            setTimeout(wapiWebhookCheck, 1000);
        }
    });

    /* ═══════════════ Overview / connection ═══════════════ */

    window.wapiSaveCredentials = function (e) {
        e.preventDefault();
        post(WAPI.urls.save_credentials, formData('#wapi-credentials-form'), function (r) {
            notify(r.message || 'Saved', r.success ? 'success' : 'danger');
            // Saving alone changes nothing on Meta's side or in the registry —
            // offer the push that actually makes the new app take effect.
            if (r.success) wapiResyncApp(true);
        });
        return false;
    };

    /**
     * Push the stored credentials through the whole system.
     *
     * @param afterSave true when it follows a save, so the user is asked first
     *                  rather than having a long Graph sweep start on its own.
     */
    window.wapiResyncApp = function (afterSave) {
        var $box = $('#wapi-resync-report');
        if (!$box.length) return;

        if (afterSave && !confirm('Credentials saved.\n\nRe-point the system at this Meta App now? It re-registers the webhook and checks every connected account\'s stored token against the new app.')) {
            return;
        }

        $box.html('<div class="wapi-diag-loading"><i class="fa fa-circle-notch fa-spin"></i> ' +
            'Re-pointing the system at the stored credentials — this can take a moment per connected account…</div>');

        post(WAPI.urls.resync_app, {}, function (r) {
            if (!r.report) {
                $box.html('<div class="wapi-diag-loading">Sync unavailable.</div>');
                notify(r.message || 'Sync failed', 'danger');
                return;
            }
            $box.html(renderResyncReport(r.report));
            notify(r.message || 'Done', r.success ? (r.report.counts.stale ? 'warning' : 'success') : 'danger');
            // The console itself (App ID, webhook state, tenant rows) is rendered
            // server-side, so bring it in line with what the sync just wrote.
            if (typeof wapiWebhookCheck === 'function') wapiWebhookCheck();
        });
    };

    /** Same soft pill the PHP whatsapp_badge() helper renders. */
    function softBadge(color, label) {
        return '<span class="wapi-badge" style="background:' + color + '18;color:' + color +
            ';border:1px solid ' + color + '40">' + esc(label) + '</span>';
    }

    function renderResyncReport(rep) {
        var html = '<div class="wapi-checks">';
        (rep.steps || []).forEach(function (s) {
            html += checkRow(s.state, s.label, s.detail).html;
        });
        html += '</div>';

        var tenants = rep.tenants || [];
        if (tenants.length) {
            html += '<table class="wapi-table wapi-resync-table"><thead><tr>' +
                '<th>Account</th><th>WABA</th><th>State</th><th>What the sync found</th></tr></thead><tbody>';
            tenants.forEach(function (t) {
                var badge = t.state === 'ok'
                    ? softBadge('#16a34a', 'On new app')
                    : (t.state === 'stale' ? softBadge('#ef4444', 'Reconnect required')
                                           : softBadge('#f59e0b', 'Partly synced'));
                html += '<tr><td><strong>' + esc(t.slug) + '</strong></td><td>' + esc(t.waba || '—') + '</td>' +
                    '<td>' + badge + '</td><td>' + esc(t.detail || '') + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        if (rep.counts && rep.counts.stale) {
            html += '<div class="wapi-alert wapi-alert-warn"><i class="fa fa-triangle-exclamation"></i><div>' +
                '<strong>' + rep.counts.stale + ' account(s) must connect again</strong>' +
                '<p>Their stored token was issued by the previous Meta App. Tokens cannot be moved between apps, ' +
                'so each of those accounts has to click <em>Connect with Facebook</em> once — they now see that warning ' +
                'on their own WhatsApp page under Health &amp; diagnostics.</p></div></div>';
        }
        return html;
    }
    window.wapiDisconnect = function () {
        if (!confirm('Disconnect WhatsApp? Messaging stops until you reconnect.')) return;
        post(WAPI.urls.disconnect, {}, function (r) { notify(r.message, 'success'); setTimeout(reload, 700); });
    };
    window.wapiMasterDisconnect = function (slug) {
        if (!confirm('Detach the WhatsApp connection for tenant "' + slug + '"?')) return;
        post(WAPI.urls.master_disconnect + encodeURIComponent(slug), {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger'); setTimeout(reload, 700);
        });
    };
    window.wapiRefreshNumbers = function () {
        notify('Refreshing numbers…', 'warning');
        post(WAPI.urls.refresh_numbers, {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger'); setTimeout(reload, 900);
        });
    };
    window.wapiSetDefault = function (id) {
        post(WAPI.urls.set_default_number, { phone_number_id: id }, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger'); setTimeout(reload, 600);
        });
    };

    /* ═══════════ Shared number — provider console (master) ═══════════ */

    /** Re-render just the tenant table, so a save never reloads the page. */
    function reloadSharedTable() {
        get(WAPI.urls.shared_console, {}, function (r) {
            if (r.success && r.html) $('#wapi-shared-table').html(r.html);
        });
    }

    window.wapiSaveSharedSettings = function (e) {
        e.preventDefault();
        post(WAPI.urls.shared_settings, formData('#wapi-shared-settings-form'), function (r) {
            notify(r.message || 'Saved', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 700);
        });
        return false;
    };

    /** Open the configure modal for a tenant ('' = add a new one). */
    window.wapiSharedGrant = function (slug) {
        get(WAPI.urls.shared_modal + encodeURIComponent(slug || ''), {}, function (r) {
            if (!r.success) { notify(r.message || 'Could not load', 'danger'); return; }
            $('#wapi-shared-modal-body').html(r.html);
            openModal('wapi-shared-modal');
        });
    };

    window.wapiSaveSharedGrant = function (e) {
        e.preventDefault();
        var data = formData('#wapi-shared-form');
        // Unchecked boxes are absent from serializeArray(), but the server has
        // to hear "off" explicitly — otherwise a cleared permission survives.
        ['enabled', 'allow_send', 'allow_bulk', 'allow_hooks'].forEach(function (k) {
            data[k] = $('#wapi-shared-form [name="' + k + '"]').is(':checked') ? 1 : 0;
        });
        if (!data['templates[]']) data['templates[]'] = [];

        post(WAPI.urls.save_shared_grant, data, function (r) {
            notify(r.message || 'Saved', r.success ? 'success' : 'danger');
            if (r.success) { wapiCloseModal('wapi-shared-modal'); reloadSharedTable(); }
        });
        return false;
    };

    window.wapiSharedToggle = function (slug) {
        post(WAPI.urls.toggle_shared + encodeURIComponent(slug), {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) reloadSharedTable();
        });
    };

    window.wapiSharedRemove = function (slug) {
        if (!confirm('Stop sharing our WhatsApp number with "' + slug + '"? They will lose WhatsApp sending unless they connect their own account.')) return;
        post(WAPI.urls.delete_shared + encodeURIComponent(slug), {}, function (r) {
            notify(r.message || 'Removed', r.success ? 'success' : 'danger');
            if (r.success) reloadSharedTable();
        });
    };

    window.wapiSharedTemplateMode = function () {
        var all = $('#wapi-shared-form [name="template_mode"]:checked').val() === 'all';
        $('#wapi-shared-template-list').toggle(!all);
    };

    window.wapiSharedTplAll = function (on) {
        $('#wapi-shared-template-list input[type="checkbox"]').prop('checked', !!on);
    };

    /** Tenant side: pull the provider's allowlisted templates now. */
    window.wapiSharedSync = function () {
        notify('Refreshing shared templates…', 'warning');
        post(WAPI.urls.shared_sync, {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 700);
        });
    };

    /* ═══════════════ Number health & registration ═══════════════ */

    window.wapiCheckNumbers = function () {
        notify('Checking numbers with Meta…', 'warning');
        post(WAPI.urls.check_numbers, {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'warning');
            wapiLoadDiagnostics(false);
        });
    };

    window.wapiOpenRegister = function (id, phone) {
        var $f = $('#wapi-register-form');
        $f[0].reset();
        $f.find('[name="phone_number_id"]').val(id);
        $('#wapi-register-phone').text(phone || id);
        openModal('wapi-register-modal');
        setTimeout(function () { $f.find('[name="pin"]').focus(); }, 150);
    };

    /** Used by the "Register number" button on an error notice. */
    window.wapiOpenRegisterFirstUnregistered = function () {
        var $btn = $('.wapi-numbers-table [onclick^="wapiOpenRegister("]').first();
        if ($btn.length) { $btn.click(); return; }
        notify('No unregistered number found — run a status check first.', 'warning');
    };

    window.wapiRegisterNumber = function (e) {
        e.preventDefault();
        var $f = $('#wapi-register-form');
        var pin = ($f.find('[name="pin"]').val() || '').replace(/\D+/g, '');
        if (pin.length !== 6) { notify('Enter a 6-digit PIN.', 'warning'); return false; }

        var $submit = $f.find('button[type="submit"]').prop('disabled', true);
        post(WAPI.urls.register_number, {
            phone_number_id: $f.find('[name="phone_number_id"]').val(),
            pin: pin
        }, function (r) {
            $submit.prop('disabled', false);
            if (r.success) {
                notify(r.message || 'Number registered', 'success');
                wapiCloseModal('wapi-register-modal');
                setTimeout(reload, 900);
            } else {
                notifyFailure(r);
            }
        });
        return false;
    };

    window.wapiNumberDetails = function (id) {
        get(WAPI.urls.number_details + encodeURIComponent(id), {}, function (r) {
            if (!r.success) { notify(r.message || 'Could not load number', 'danger'); return; }
            $('#wapi-number-modal-body').html(r.html);
            openModal('wapi-number-modal');
        });
    };

    /* ═══════════════ Diagnostics ═══════════════ */

    window.wapiLoadDiagnostics = function (forceRefresh) {
        var $box = $('#wapi-diagnostics');
        if (!$box.length) return;
        $box.html('<div class="wapi-diag-loading"><i class="fa fa-circle-notch fa-spin"></i> Running checks…</div>');

        get(WAPI.urls.diagnostics, forceRefresh ? { refresh: 1 } : {}, function (r) {
            if (!r.success) {
                $box.html('<div class="wapi-diag-loading">Checks unavailable.</div>');
                return;
            }
            renderChecks($box, r.checks || []);
            renderNumberCells(r.numbers || []);
            renderFailures(r.failures || []);
        });
    };

    function renderChecks($box, checks) {
        if (!checks.length) { $box.html(''); return; }
        var icons = { ok: 'fa-circle-check', warn: 'fa-triangle-exclamation', fail: 'fa-circle-xmark' };
        var html = '<div class="wapi-checks">';
        checks.forEach(function (c) {
            html += '<div class="wapi-check wapi-check-' + esc(c.state) + '">' +
                '<i class="fa ' + (icons[c.state] || 'fa-circle') + '"></i>' +
                '<div class="wapi-check-body"><strong>' + esc(c.label) + '</strong>' +
                '<p>' + esc(c.detail) + '</p>' +
                (c.state !== 'ok' ? actionButton(c.action) : '') +
                '</div></div>';
        });
        $box.html(html + '</div>');
        setPanelSummary('#wapi-diag-summary', checks.map(function (c) { return c.state; }));
    }

    /**
     * Headline for a collapsed panel — the point of collapsing by default is
     * that you still see at a glance whether you need to open it.
     */
    function setPanelSummary(sel, states) {
        var fails = states.filter(function (s) { return s === 'fail'; }).length;
        var warns = states.filter(function (s) { return s === 'warn'; }).length;
        var $el = $(sel);
        if (!$el.length) return;

        if (fails) {
            $el.attr('class', 'wapi-summary-fail')
               .html('<i class="fa fa-circle-xmark"></i> ' + fails + (fails === 1 ? ' issue' : ' issues') +
                     (warns ? ', ' + warns + ' warning' + (warns === 1 ? '' : 's') : ''));
        } else if (warns) {
            $el.attr('class', 'wapi-summary-warn')
               .html('<i class="fa fa-triangle-exclamation"></i> ' + warns + ' warning' + (warns === 1 ? '' : 's'));
        } else {
            $el.attr('class', 'wapi-summary-ok').html('<i class="fa fa-circle-check"></i> All checks passed');
        }
    }

    /** Patch the live status cells in place — no full page reload needed. */
    function renderNumberCells(numbers) {
        var active = 0;
        numbers.forEach(function (n) {
            if (n.registered === 1) active++;
            var $row = $('[data-number-row="' + n.phone_number_id + '"]');
            if (!$row.length) return;
            $row.find('[data-cell="status"]').html(n.status_html);
            $row.find('[data-cell="health"]').html(n.health_html);
            $row.find('[data-cell="quality"]').html(n.quality_html);
            $row.find('[data-cell="tier"]').text(n.tier);
            $row.find('[data-cell="checked"]').text(n.checked);
            $row.toggleClass('wapi-row-danger', n.registered !== 1);

            // Offer the fix inline as soon as a number is confirmed unregistered.
            if (n.registered !== 1 && !$row.find('[onclick^="wapiOpenRegister("]').length) {
                $row.find('.wapi-row-actions').prepend(
                    '<button class="wapi-btn wapi-btn-primary wapi-btn-sm" title="Register number" ' +
                    'onclick="wapiOpenRegister(\'' + esc(n.phone_number_id) + '\', \'' + esc(n.display || '') + '\')">' +
                    '<i class="fa fa-key"></i></button>');
            }
        });

        if (numbers.length) {
            var $stat = $('#wapi-stat-active');
            if ($stat.length) {
                $stat.contents().filter(function () { return this.nodeType === 3; }).remove();
                $stat.prepend(document.createTextNode(String(active)));
                $stat.toggleClass('wapi-stat-danger', active < numbers.length);
            }
        }
    }

    function renderFailures(failures) {
        var $box = $('#wapi-failures');
        if (!$box.length) return;
        if (!failures.length) { $box.html(''); return; }

        var html = '<h4 class="wapi-h4"><i class="fa fa-bug"></i> Recent send failures (7 days)</h4>' +
            '<div class="wapi-table-scroll"><table class="wapi-table"><thead><tr>' +
            '<th>Cause</th><th>Code</th><th>Count</th><th>Last</th><th class="wapi-ta-r"></th>' +
            '</tr></thead><tbody>';
        failures.forEach(function (f) {
            html += '<tr><td><strong>' + esc(f.title) + '</strong>' +
                (f.hint ? '<br><small class="wapi-muted">' + esc(f.hint) + '</small>' : '') + '</td>' +
                '<td>' + (f.error_code ? '<code class="wapi-code">' + esc(f.error_code) + '</code>' : '<span class="wapi-muted">—</span>') + '</td>' +
                '<td><span class="wapi-badge wapi-badge-soft">' + esc(f.total) + '</span></td>' +
                '<td class="wapi-muted"><small>' + esc((f.last_at || '').substring(0, 16)) + '</small></td>' +
                '<td class="wapi-ta-r">' + actionButton(f.action) + '</td></tr>';
        });
        $box.html(html + '</tbody></table></div>');
    }
    /* ═══════════════ Activity chart ═══════════════ */

    var chartSeries = [];

    /** "Nice" axis maximum so gridline labels are round numbers. */
    function niceMax(v) {
        if (v <= 5) return 5;
        var pow = Math.pow(10, Math.floor(Math.log(v) / Math.LN10));
        var n = v / pow;
        var step = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10;
        return step * pow;
    }

    function fmt(n) { return (n || 0).toLocaleString(); }

    window.wapiRenderChart = function (series) {
        chartSeries = series || [];
        var $chart = $('#wapi-chart');
        if (!$chart.length) return;

        var totals = { sent: 0, incoming: 0, failed: 0, outgoing: 0 };
        var peak = 0, peakDay = null;

        chartSeries.forEach(function (d) {
            totals.sent += d.sent; totals.incoming += d.incoming;
            totals.failed += d.failed; totals.outgoing += d.outgoing;
            var t = d.sent + d.incoming + d.failed;
            if (t > peak) { peak = t; peakDay = d; }
        });

        var max = niceMax(peak || 1);

        // ── KPI strip ──
        var rate = totals.outgoing > 0
            ? Math.round((totals.outgoing - totals.failed) / totals.outgoing * 1000) / 10 : null;
        var kpis =
            kpi('Sent', fmt(totals.sent), '#128c7e') +
            kpi('Received', fmt(totals.incoming), '#25d366') +
            kpi('Failed', fmt(totals.failed), totals.failed ? '#ef4444' : '#9ca3af') +
            kpi('Success rate', rate === null ? '—' : rate + '%', rate === null ? '#9ca3af' : (rate >= 95 ? '#16a34a' : rate >= 85 ? '#f59e0b' : '#ef4444')) +
            kpi('Busiest day', peak ? (peakDay.label + ' · ' + fmt(peak)) : '—', '#6b7280');
        $('#wapi-chart-kpis').html(kpis);

        // ── Y axis + gridlines (4 bands) ──
        var ticks = [], grid = '';
        for (var i = 4; i >= 0; i--) {
            ticks.push('<span>' + fmt(Math.round(max * i / 4)) + '</span>');
            grid += '<div class="wapi-grid-line"></div>';
        }
        $('#wapi-chart-yaxis').html(ticks.join(''));
        $('#wapi-chart-grid').html(grid);

        // ── Columns ──
        // Thin the x labels so 30/90-day ranges stay readable.
        var every = chartSeries.length > 45 ? 10 : chartSeries.length > 20 ? 3 : 1;
        var bars = '', xlabels = '';

        chartSeries.forEach(function (d, i) {
            var total = d.sent + d.incoming + d.failed;
            var seg = function (v, cls) {
                return v > 0 ? '<div class="wapi-chart-seg ' + cls + '" style="height:' + (v / max * 100) + '%"></div>' : '';
            };
            bars += '<div class="wapi-chart-col' + (total === 0 ? ' is-empty' : '') + '" data-i="' + i + '">' +
                        '<div class="wapi-chart-stack">' +
                            seg(d.failed, 'wapi-seg-failed') +
                            seg(d.incoming, 'wapi-seg-in') +
                            seg(d.sent, 'wapi-seg-out') +
                        '</div>' +
                    '</div>';
            xlabels += '<span class="wapi-x-label">' +
                (i % every === 0 || i === chartSeries.length - 1 ? esc(d.label) : '') + '</span>';
        });

        $chart.html(bars);
        $('#wapi-chart-xaxis').html(xlabels);
    };

    function kpi(label, value, color) {
        return '<div class="wapi-kpi"><span class="wapi-kpi-value" style="color:' + color + '">' + esc(value) + '</span>' +
               '<span class="wapi-kpi-label">' + esc(label) + '</span></div>';
    }

    // ── Tooltip: follows the cursor, flips near the right edge ──
    $(document).on('mouseenter', '.wapi-chart-col', function (e) {
        var d = chartSeries[$(this).data('i')];
        if (!d) return;
        var total = d.sent + d.incoming + d.failed;
        $('#wapi-chart-tip').html(
            '<div class="wapi-tip-head">' + esc(d.label) + ' <span class="wapi-muted">' + esc(d.dow || '') + '</span></div>' +
            tipRow('#128c7e', 'Sent', d.sent) +
            tipRow('#25d366', 'Received', d.incoming) +
            (d.failed ? tipRow('#ef4444', 'Failed', d.failed) : '') +
            '<div class="wapi-tip-total">Total <strong>' + fmt(total) + '</strong></div>'
        ).show();
        positionTip(e);
    });

    $(document).on('mousemove', '.wapi-chart-col', positionTip);
    $(document).on('mouseleave', '.wapi-chart-col', function () { $('#wapi-chart-tip').hide(); });

    function positionTip(e) {
        var $tip = $('#wapi-chart-tip'), $wrap = $tip.parent();
        if (!$tip.length || !$wrap.length) return;
        var w = $wrap.width(), x = e.pageX - $wrap.offset().left, tw = $tip.outerWidth();
        // Flip left when the tooltip would overflow the plot — the old native
        // title got clipped at the right edge of the card.
        $tip.css({ left: Math.max(0, Math.min(x + 14, w - tw - 4)) + 'px', top: '8px' });
    }

    function tipRow(color, label, value) {
        return '<div class="wapi-tip-row"><i class="wapi-dot" style="background:' + color + '"></i>' +
               esc(label) + '<strong>' + fmt(value) + '</strong></div>';
    }

    $(document).on('change', '#wapi-chart-range', function () {
        var days = $(this).val();
        $('#wapi-chart').addClass('is-loading');
        get(WAPI.urls.activity, { days: days }, function (r) {
            $('#wapi-chart').removeClass('is-loading');
            if (r.success) wapiRenderChart(r.series);
        });
    });

    /* ═══════════════ Webhook health (provider console) ═══════════════ */

    window.wapiWebhookCheck = function () {
        var $box = $('#wapi-webhook-report');
        if (!$box.length) return;
        $box.html('<div class="wapi-diag-loading"><i class="fa fa-circle-notch fa-spin"></i> Probing the webhook endpoint…</div>');

        get(WAPI.urls.webhook_check, {}, function (r) {
            if (!r.success) { $box.html('<div class="wapi-diag-loading">Check unavailable.</div>'); return; }
            $box.html(renderWebhookReport(r.report || {}));
        });
    };

    function renderWebhookReport(w) {
        var rows = [], states = [];

        // 1. Is our endpoint reachable and does it answer the challenge?
        var st = w.self_test || {};
        rows.push(checkRow(st.ok ? 'ok' : 'fail', 'Endpoint reachability',
            (st.detail || '') + (st.code ? ' (HTTP ' + st.code + ')' : '')));

        // 2. What has Meta actually got registered?
        if (w.subscription_error) {
            rows.push(checkRow('fail', 'App subscription', 'Could not read the app subscription: ' + w.subscription_error));
        } else if (!w.registered) {
            rows.push(checkRow('fail', 'App subscription',
                'The Meta App has no whatsapp_business_account webhook registered at all. Nothing will ever be delivered. Use "Register webhook" below.'));
        } else {
            rows.push(checkRow(w.url_matches ? 'ok' : 'fail', 'Registered callback URL',
                w.url_matches
                    ? 'Matches this server: ' + w.callback_url
                    : 'Meta will POST to “' + (w.callback_url || '(none)') + '” but this server expects “' + w.expected_url +
                      '”. Events are going somewhere else — use "Register webhook" to correct it.'));

            rows.push(checkRow(w.active ? 'ok' : 'warn', 'Subscription active',
                w.active ? 'The subscription is active.' : 'Meta has marked the subscription inactive, usually after repeated delivery failures.'));

            var missing = w.missing_fields || [];
            rows.push(checkRow(missing.length ? 'fail' : 'ok', 'Subscribed fields',
                missing.length
                    ? 'Not subscribed to: ' + missing.join(', ') + '. Without “messages” no incoming message is ever delivered.'
                    : 'Subscribed to ' + (w.fields || []).join(', ') + '.'));
        }

        // 3. Have we actually been called?
        if (w.rejected_at && (!w.last_at || w.rejected_at >= w.last_at)) {
            rows.push(checkRow('fail', 'Delivery history',
                'Meta reached this server at ' + w.rejected_at + ' but the signature failed — the stored App Secret does not match the app sending events.'));
        } else if (!w.last_at) {
            rows.push(checkRow('warn', 'Delivery history',
                'No webhook has been recorded yet. Note this counter only started when webhook tracking was added, so it is only meaningful once someone messages you again.'));
        } else {
            rows.push(checkRow('ok', 'Delivery history', w.count + ' event(s) received, most recently ' + w.last_at + '.'));
        }

        rows.forEach(function (r) { states.push(r.state); });
        setPanelSummary('#wapi-webhook-summary', states);
        return '<div class="wapi-checks">' + rows.map(function (r) { return r.html; }).join('') + '</div>';
    }

    function checkRow(state, label, detail) {
        var icons = { ok: 'fa-circle-check', warn: 'fa-triangle-exclamation', fail: 'fa-circle-xmark' };
        return {
            state: state,
            html: '<div class="wapi-check wapi-check-' + state + '"><i class="fa ' + icons[state] + '"></i>' +
                '<div class="wapi-check-body"><strong>' + esc(label) + '</strong><p>' + esc(detail) + '</p></div></div>'
        };
    }

    window.wapiWebhookFix = function () {
        if (!confirm('Register this server as the webhook callback on your Meta App? Meta will verify the URL before accepting it.')) return;
        notify('Registering webhook with Meta…', 'warning');
        post(WAPI.urls.webhook_fix, {}, function (r) {
            if (r.success) { notify(r.message || 'Done', 'success'); wapiWebhookCheck(); }
            else { notifyFailure(r); wapiWebhookCheck(); }
        });
    };

    /* ═══════════════ Business profile (branding) ═══════════════ */

    var profileLoaded = false;

    function profileNumberId() { return $('#wapi-profile-number').val() || ''; }

    window.wapiLoadProfile = function () {
        var id = profileNumberId();
        if (!id) return;
        var $f = $('#wapi-profile-form');
        $f.find('[name="phone_number_id"]').val(id);

        get(WAPI.urls.profile + encodeURIComponent(id), {}, function (r) {
            if (!r.success) { notifyFailure(r); return; }
            profileLoaded = true;
            var p = r.profile || {};

            $f.find('[name="about"]').val(p.about || '');
            $f.find('[name="description"]').val(p.description || '');
            $f.find('[name="address"]').val(p.address || '');
            $f.find('[name="email"]').val(p.email || '');
            $f.find('[name="vertical"]').val(p.vertical || 'UNDEFINED');
            $f.find('[name="website_1"]').val((p.websites || [])[0] || '');
            $f.find('[name="website_2"]').val((p.websites || [])[1] || '');

            // Existing picture, if Meta has one.
            var $av = $('#wapi-pp-avatar');
            if (p.profile_picture_url) {
                $av.html('<img src="' + esc(p.profile_picture_url) + '" alt="">');
            } else {
                $av.html('<i class="fa fa-building"></i>');
            }

            $('#wapi-pp-name').text(r.display_name || '—');
            $('#wapi-pp-phone').text(r.display_phone_number || '');

            // Display-name review state.
            var ns = r.name_status || {};
            $('#wapi-name-status').html(ns.label && ns.label !== '—'
                ? '<span class="wapi-badge" title="' + esc(ns.detail || '') + '" style="background:' + esc(ns.color) +
                  '14;color:' + esc(ns.color) + ';border:1px solid ' + esc(ns.color) + '40">' + esc(ns.label) + '</span>'
                : '');
            $('#wapi-name-form [name="display_name"]').attr('placeholder', r.display_name || 'Your business name');

            wapiProfileChanged();
        });
    };

    /** Mirror the form into the customer-facing preview card. */
    window.wapiProfileChanged = function () {
        var $f = $('#wapi-profile-form');
        var v = function (n) { return ($f.find('[name="' + n + '"]').val() || '').trim(); };

        $('#wapi-pp-about').text(v('about'));
        setPreviewRow('desc', v('description'));
        setPreviewRow('addr', v('address'));
        setPreviewRow('email', v('email'));

        var web = [v('website_1'), v('website_2')].filter(Boolean).join(' · ');
        setPreviewRow('web', web);

        var vert = $f.find('[name="vertical"] option:selected').text();
        setPreviewRow('vert', vert && vert.indexOf('not set') === -1 ? vert : '');

        $f.find('.wapi-counter').each(function () {
            var name = $(this).data('for');
            var max = $(this).text().split('/')[1];
            $(this).text(v(name).length + '/' + max);
            $(this).toggleClass('wapi-counter-full', v(name).length >= parseInt(max, 10));
        });
    };

    function setPreviewRow(key, value) {
        $('#wapi-pp-row-' + key).toggle(!!value);
        $('#wapi-pp-' + key).text(value || '');
    }

    window.wapiPreviewPicture = function (input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            notify('The image must be 5 MB or smaller.', 'danger');
            input.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                if (img.width < 192 || img.height < 192) {
                    notify('The image must be at least 192 × 192 pixels.', 'danger');
                    input.value = '';
                    return;
                }
                if (Math.abs(img.width - img.height) > Math.max(2, img.width * 0.02)) {
                    notify('Use a square image — WhatsApp crops it to a circle.', 'warning');
                }
                $('#wapi-pp-avatar').html('<img src="' + e.target.result + '" alt="">');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    };

    window.wapiSaveProfile = function (e) {
        e.preventDefault();
        var form = document.getElementById('wapi-profile-form');
        $(form).find('[name="phone_number_id"]').val(profileNumberId());

        var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
        postForm(WAPI.urls.save_profile, form, function (r) {
            $btn.prop('disabled', false);
            if (r.success) {
                notify(r.message || 'Profile saved', 'success');
                $('#wapi-profile-picture').val('');
                wapiLoadProfile();
            } else {
                notifyFailure(r);
            }
        });
        return false;
    };

    window.wapiRequestDisplayName = function (e) {
        e.preventDefault();
        var name = ($('#wapi-name-form [name="display_name"]').val() || '').trim();
        if (name.length < 3) { notify('Enter the business name you want to display.', 'warning'); return false; }
        if (!confirm('Submit "' + name + '" to Meta for review? The current name stays live until it is approved.')) return false;

        post(WAPI.urls.request_name, { phone_number_id: profileNumberId(), display_name: name }, function (r) {
            if (r.success) { notify(r.message || 'Submitted', 'success'); wapiLoadProfile(); }
            else { notifyFailure(r); }
        });
        return false;
    };

    $(document).on('change', '#wapi-profile-number', function () { wapiLoadProfile(); });

    /* ═══════════════ Provider billing (shared credit line) ═══════════════ */

    window.wapiSaveBilling = function (e) {
        e.preventDefault();
        var data = formData('#wapi-billing-form');
        data.share_credit_line = $('#wapi-billing-form [name="share_credit_line"]').is(':checked') ? 1 : '';
        post(WAPI.urls.save_billing, data, function (r) {
            notify(r.message || 'Saved', r.success ? 'success' : 'danger');
            // Blank the secret field again — it is never rendered back.
            $('#wapi-billing-form [name="system_user_token"]').val('');
            if (r.success) setTimeout(reload, 900);
        });
        return false;
    };

    window.wapiLoadCreditLines = function () {
        notify('Loading credit lines from Meta…', 'warning');
        $('#wapi-credit-diagnosis').remove();

        get(WAPI.urls.credit_lines, {}, function (r) {
            if (!r.success) { notifyFailure(r); return; }
            var $sel = $('#wapi-credit-line-select').empty();

            if (!r.lines || !r.lines.length) {
                $sel.append('<option value="">No credit line found on this business</option>');
                renderCreditDiagnosis(r.diagnosis);
                return;
            }
            r.lines.forEach(function (l) {
                var label = (l.legal_entity_name || l.id) +
                    (l.credit_available_amount ? ' · ' + l.credit_available_amount + ' available' : '');
                $sel.append('<option value="' + esc(l.id) + '"' + (l.id === r.selected ? ' selected' : '') + '>' + esc(label) + '</option>');
            });
            notify(r.lines.length + ' credit line(s) loaded — pick one and save.', 'success');
        });
    };

    /** Explain an empty credit-line list precisely, with the next action. */
    function renderCreditDiagnosis(d) {
        d = d || { reason: 'no_credit_line', businesses: [] };
        var body = '', tone = 'wapi-alert-warn', title = '';

        if (d.reason === 'token_invalid') {
            title = 'That system user token is not working';
            body = '<p>Meta rejected it: <span class="wapi-alert-raw">' + esc(d.detail || '') + '</span></p>' +
                '<p>Generate a fresh <strong>System User</strong> token in Business settings → Users → System users, ' +
                'grant it <code>business_management</code> and set the expiry to <strong>Never</strong>.</p>';
            tone = 'wapi-alert-danger';
        } else if (d.reason === 'business_unreadable') {
            title = 'That Business ID could not be read with this token';
            body = '<p>Meta said: <span class="wapi-alert-raw">' + esc(d.detail || '') + '</span></p>' +
                '<p>The ID is probably not your business portfolio (an App ID, WABA ID or Ad Account ID will not work), ' +
                'or the token belongs to a different business.</p>';
            tone = 'wapi-alert-danger';
        } else if (d.reason === 'not_verified') {
            title = 'Your business is not verified yet';
            body = '<p><strong>' + esc((d.business && d.business.name) || '') + '</strong> shows verification status ' +
                '<code>' + esc(d.detail || 'unknown') + '</code>. Meta only issues credit lines to verified businesses.</p>' +
                '<p>Finish <strong>Business verification</strong> in Security Centre, then come back to this step.</p>' +
                '<a href="https://business.facebook.com/settings/security" target="_blank" rel="noopener" class="wapi-btn wapi-btn-primary wapi-btn-sm">' +
                '<i class="fa fa-arrow-up-right-from-square"></i> Open Security Centre</a>';
        } else {
            title = 'No credit line is allocated to this business';
            body = '<p>' + (d.business && d.business.name ? '<strong>' + esc(d.business.name) + '</strong> was found and is readable, but no ' : 'No ') +
                '<strong>monthly invoicing credit line</strong> is allocated to it or shared with it.</p>' +
                '<p><strong>If you are still a Tech Provider, this is expected, not a fault.</strong> Meta only gives credit lines to ' +
                '<strong>Solution Partners</strong> — clients onboarded by a Tech Provider must add their own payment method. ' +
                'To change that you either upgrade to Solution Partner, or run a Multi-Partner Solution with one who shares their credit line ' +
                'with the tenants you onboard. Step 2 of the guide covers both.</p>' +
                '<p>Nothing is broken meanwhile: leave sharing off and your tenants keep paying Meta with their own card.</p>' +
                '<a href="https://whatsappbusiness.com/partners/become-a-partner/" target="_blank" rel="noopener" class="wapi-btn wapi-btn-primary wapi-btn-sm">' +
                '<i class="fa fa-arrow-up-right-from-square"></i> Apply as a partner</a>' +
                '<a href="https://business.facebook.com/billing_hub/accounts" target="_blank" rel="noopener" class="wapi-btn wapi-btn-light wapi-btn-sm">' +
                '<i class="fa fa-arrow-up-right-from-square"></i> Credit lines page</a>';
        }

        // Businesses this token can actually see — usually reveals a pasted-in
        // wrong id straight away.
        if (d.businesses && d.businesses.length) {
            body += '<p class="wapi-biz-head">This token can see these business portfolios:</p><ul class="wapi-biz-list">';
            d.businesses.forEach(function (b) {
                var same = d.business && b.id === d.business.id;
                body += '<li><code class="wapi-code">' + esc(b.id) + '</code> ' + esc(b.name || '') +
                    (b.verification_status ? ' <span class="wapi-muted">· ' + esc(b.verification_status) + '</span>' : '') +
                    (same ? ' <span class="wapi-badge wapi-badge-soft">in use</span>'
                          : ' <button type="button" class="wapi-btn wapi-btn-ghost wapi-btn-sm" onclick="wapiUseBusinessId(\'' + esc(b.id) + '\')">Use this</button>') +
                    '</li>';
            });
            body += '</ul>';
        }

        $('<div id="wapi-credit-diagnosis" class="wapi-alert ' + tone + '">' +
          '<i class="fa fa-circle-info"></i><div><strong>' + esc(title) + '</strong>' + body + '</div></div>')
            .insertAfter($('#wapi-credit-line-select').closest('.wapi-field'));
    }

    window.wapiUseBusinessId = function (id) {
        $('#wapi-billing-form [name="business_id"]').val(id);
        notify('Business ID filled in — save, then load credit lines again.', 'success');
    };

    window.wapiRefreshUsage = function () {
        var days = $('#wapi-usage-days').val() || 30;
        notify('Pulling usage and cost from Meta…', 'warning');
        post(WAPI.urls.refresh_usage, { days: days }, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'warning');
            setTimeout(reload, 1000);
        });
    };

    window.wapiShareCredit = function (slug) {
        if (!confirm('Attach "' + slug + '" to your WhatsApp credit line? Meta will bill you for their messaging from now on.')) return;
        post(WAPI.urls.share_credit + encodeURIComponent(slug), {}, function (r) {
            if (r.success) { notify(r.message || 'Done', 'success'); setTimeout(reload, 900); }
            else { notifyFailure(r); }
        });
    };

    window.wapiCopy = function (btn, text) {
        navigator.clipboard.writeText(text).then(function () {
            var orig = btn.innerHTML; btn.innerHTML = '<i class="fa fa-check"></i>';
            setTimeout(function () { btn.innerHTML = orig; }, 1200);
        });
    };

    /* ═══════════════ Templates ═══════════════ */

    function populateTemplateSelects() {
        $('.wapi-template-select').each(function () {
            var $sel = $(this);
            $sel.empty().append('<option value="">— select template —</option>');
            (WAPI.templates || []).forEach(function (t, i) {
                $sel.append('<option value="' + i + '">' + esc(t.name) + ' (' + esc(t.language) + ')' + (t.vars > 0 ? ' · ' + t.vars + ' vars' : '') + '</option>');
            });
        });
    }

    function selectedTemplate($sel) {
        var i = parseInt($sel.val(), 10);
        return (isNaN(i) || !WAPI.templates[i]) ? null : WAPI.templates[i];
    }

    window.wapiSyncTemplates = function () {
        notify('Syncing templates from Meta…', 'warning');
        post(WAPI.urls.sync_templates, {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 900);
        });
    };
    /* ═══════════════ Template composer ═══════════════ */

    /**
     * The composer is a two-pane form: the fields on the left, a live WhatsApp
     * bubble on the right. Everything the recipient will see is rendered from
     * the form's current values on every keystroke, with the same pre-flight
     * checks Meta applies — a rejection that can be predicted here costs a
     * keystroke to fix instead of a review cycle.
     */
    var TPL_MAX = { header: 60, body: 1024, footer: 60 };

    /** The template being edited (null while creating) — the source of the
     *  facts the form cannot express: a media header and approved buttons. */
    var tplEditing = null;

    /* ── WhatsApp markup → HTML, preview only ──
     *
     * WhatsApp itself is permissive: a delimiter pair holds as long as the
     * content does not start or end with a space, whatever sits outside it.
     * Anchoring on the surrounding punctuation instead (the old approach) quietly
     * dropped the formatting on everyday text — a *₹500* followed by a slash, a
     * quoted "*word*", a *Bold*run-on —
     * and could never nest, so *_bold italic_* came out half-applied. This scans
     * the text instead of pattern-matching it, which nests for free, and covers
     * the block formats WhatsApp added later: lists, quotes and inline code.
     */

    var WA_TAGS   = { '*': 'strong', '_': 'em', '~': 's' };
    var WA_BULLET = /^[ \t]*[*-][ \t]+(.*)$/;
    var WA_NUMBER = /^[ \t]*(\d{1,3})[.)][ \t]+(.*)$/;
    var WA_QUOTE  = /^[ \t]*>[ \t]?(.*)$/;

    /** Index of the delimiter that closes the one at `open`, or -1 if none does. */
    function waClosingIndex(text, ch, open) {
        var next = text.charAt(open + 1);
        // "* foo*" and "**" open nothing — WhatsApp needs real content.
        if (next === '' || next === ch || /\s/.test(next)) return -1;
        for (var j = open + 2; j < text.length; j++) {
            if (text.charAt(j) === ch && !/\s/.test(text.charAt(j - 1))) return j;
        }
        return -1;
    }

    /** Bold/italic/strike, nested to any depth; everything else is escaped text. */
    function waInline(text) {
        var out = '', i = 0;
        while (i < text.length) {
            var ch = text.charAt(i), tag = WA_TAGS[ch];
            var close = tag ? waClosingIndex(text, ch, i) : -1;
            if (close > -1) {
                out += '<' + tag + '>' + waInline(text.slice(i + 1, close)) + '</' + tag + '>';
                i = close + 1;
            } else {
                out += esc(ch);
                i++;
            }
        }
        return out;
    }

    function waListItems(items) {
        return items.map(function (t) { return '<li>' + waInline(t) + '</li>'; }).join('');
    }

    /** Line-level formats — a run of like lines becomes one list or quote block. */
    function waBlocks(src) {
        var lines = src.split('\n'), parts = [], i = 0, m, items;
        while (i < lines.length) {
            if ((m = lines[i].match(WA_BULLET))) {
                items = [];
                do { items.push(m[1]); i++; } while (i < lines.length && (m = lines[i].match(WA_BULLET)));
                parts.push([true, '<ul class="wapi-fmt-list">' + waListItems(items) + '</ul>']);
            } else if ((m = lines[i].match(WA_NUMBER))) {
                var start = parseInt(m[1], 10);
                items = [];
                do { items.push(m[2]); i++; } while (i < lines.length && (m = lines[i].match(WA_NUMBER)));
                parts.push([true, '<ol class="wapi-fmt-list" start="' + start + '">' + waListItems(items) + '</ol>']);
            } else if ((m = lines[i].match(WA_QUOTE))) {
                items = [];
                do { items.push(waInline(m[1])); i++; } while (i < lines.length && (m = lines[i].match(WA_QUOTE)));
                parts.push([true, '<div class="wapi-fmt-quote">' + items.join('<br>') + '</div>']);
            } else {
                parts.push([false, waInline(lines[i])]);
                i++;
            }
        }
        // The bubble is white-space:pre-wrap, so only keep the newline between two
        // plain lines — a block element breaks the line itself, and the separator
        // would show up as a stray empty line under it. A blank line touching a
        // block has nothing left to break, so it becomes the <br> it stood for.
        var out = '', prevBlock = true;
        for (var k = 0; k < parts.length; k++) {
            var isBlock = parts[k][0];
            var blank   = !isBlock && parts[k][1] === '' &&
                ((k > 0 && parts[k - 1][0]) || (k + 1 < parts.length && parts[k + 1][0]));
            if (blank) { out += '<br>'; prevBlock = true; continue; }
            if (k > 0 && !isBlock && !prevBlock) out += '\n';
            out += parts[k][1];
            prevBlock = isBlock;
        }
        return out;
    }

    // Private-use character — code spans are lifted out behind it so nothing
    // inside them is formatted, then put back once everything else is done.
    var WA_MARK = String.fromCharCode(0xE000);

    function waFormat(text) {
        var src  = String(text == null ? '' : text).replace(/\r\n?/g, '\n').split(WA_MARK).join('');
        var kept = [];
        var hold = function (html) { return WA_MARK + (kept.push(html) - 1) + WA_MARK; };

        src = src.replace(/```([\s\S]*?)```/g, function (m, c) {
            return hold('<code class="wapi-fmt-mono">' + esc(c) + '</code>');
        });
        src = src.replace(/`([^`\n]+)`/g, function (m, c) {
            return hold('<code class="wapi-fmt-code">' + esc(c) + '</code>');
        });

        return waBlocks(src).replace(new RegExp(WA_MARK + '(\\d+)' + WA_MARK, 'g'), function (m, n) {
            return kept[+n];
        });
    }

    /**
     * Swap {{n}} for its sample value, or keep the placeholder visible when
     * there is nothing to fill it with — either way it is chipped, so the
     * variable positions stay obvious in the preview.
     */
    function tplFillVars(html, samples, withSamples) {
        return html.replace(/\{\{\s*(\d+)\s*\}\}/g, function (m, n) {
            var value = samples[parseInt(n, 10) - 1];
            value = value == null ? '' : String(value).trim();
            return withSamples && value !== ''
                ? '<span class="wapi-var wapi-var-filled">' + esc(value) + '</span>'
                : '<span class="wapi-var">{{' + n + '}}</span>';
        });
    }

    /** Sorted unique {{n}} indexes in a piece of text. */
    function tplVarIndexes(text) {
        var found = String(text || '').match(/\{\{\s*\d+\s*\}\}/g) || [];
        var seen = {}, out = [];
        found.forEach(function (raw) {
            var n = parseInt(raw.replace(/[^\d]/g, ''), 10);
            if (!seen[n]) { seen[n] = 1; out.push(n); }
        });
        return out.sort(function (a, b) { return a - b; });
    }

    function tplField(name) { return $('#wapi-template-form').find('[name="' + name + '"]'); }
    function tplVal(name) { return String(tplField(name).val() || ''); }

    /** Whether the header field is a live text header (an edit can lock it). */
    function tplHasTextHeader() { return !(tplEditing && tplEditing.media_header); }

    function tplSampleValues() {
        return $('#wapi-template-samples-rows').find('[name="samples[]"]').map(function () {
            return $(this).val() || '';
        }).get();
    }

    /* ── Sample-value inputs — one per {{n}} ── */

    /**
     * Rebuild the sample rows for the variables currently in the text, keeping
     * whatever was already typed for the same position. These values are what
     * Meta reviews the template against, so a real-looking sample is the
     * difference between an approval and an INVALID_FORMAT rejection.
     */
    function tplSyncSamples() {
        var body     = tplVal('body_text');
        var header   = tplHasTextHeader() ? tplVal('header_text') : '';
        var indexes  = tplVarIndexes(body);
        var count    = indexes.length ? Math.max.apply(null, indexes) : 0;
        var hasHdr   = tplVarIndexes(header).length > 0;

        var kept = tplSampleValues();
        var keptHeader = $('#wapi-template-header-sample').val() || '';

        var html = '';
        if (hasHdr) {
            html += sampleRow('header_sample', 'Header {{1}}', keptHeader, 'e.g. Apollo Clinic');
        }
        for (var i = 1; i <= count; i++) {
            html += sampleRow('samples[]', 'Body {{' + i + '}}', kept[i - 1] || '', 'e.g. ' + sampleGuess(i));
        }
        $('#wapi-template-samples-rows').html(html);
        $('#wapi-template-samples').toggle(hasHdr || count > 0);
    }

    function sampleRow(name, label, value, placeholder) {
        var id = name === 'header_sample' ? ' id="wapi-template-header-sample"' : '';
        return '<div class="wapi-tplsample">' +
            '<span class="wapi-var">' + esc(label) + '</span>' +
            '<input type="text"' + id + ' name="' + name + '" value="' + esc(value) + '" placeholder="' + esc(placeholder) + '" autocomplete="off">' +
            '</div>';
    }

    /** Plausible sample text so the inputs are not staring at an empty box. */
    function sampleGuess(i) {
        var guesses = ['Ramesh Kumar', '12 Aug, 10:30 AM', 'Dr. Mehta', 'INV-2043', '₹1,250'];
        return guesses[(i - 1) % guesses.length];
    }

    /* ── Live preview ── */

    function tplMediaHeaderBox(format) {
        var f = String(format || 'IMAGE').toUpperCase();
        var icon = f === 'VIDEO' ? 'fa-video' : (f === 'DOCUMENT' ? 'fa-file-lines' : (f === 'LOCATION' ? 'fa-location-dot' : 'fa-image'));
        return '<div class="wapi-tpl-preview-media"><i class="fa ' + icon + '"></i> ' +
            esc(f.charAt(0) + f.slice(1).toLowerCase()) + ' header</div>';
    }

    function tplClock() {
        var d = new Date();
        return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    }

    function renderTemplatePreview() {
        var withSamples = $('#wapi-template-preview-samples').is(':checked');
        var samples     = tplSampleValues();
        var header      = tplVal('header_text').trim();
        var body        = tplVal('body_text');
        var footer      = tplVal('footer_text').trim();

        var html = '<div class="wapi-tpl-preview"><div class="wapi-bubble-row wapi-bubble-in"><div class="wapi-bubble">';

        if (!tplHasTextHeader()) {
            html += tplMediaHeaderBox(tplEditing.header_format);
        } else if (header !== '') {
            html += '<div class="wapi-tpl-preview-header">' +
                tplFillVars(waFormat(header), [$('#wapi-template-header-sample').val() || ''], withSamples) + '</div>';
        }

        html += '<div class="wapi-bubble-text">' + (body.trim() === ''
            ? '<span class="wapi-tpl-preview-ph">Your message will appear here as the patient sees it.</span>'
            : tplFillVars(waFormat(body), samples, withSamples)) + '</div>';

        if (footer !== '') {
            html += '<div class="wapi-tpl-preview-footer">' + esc(footer) + '</div>';
        }
        html += '<div class="wapi-bubble-meta">' + tplClock() + ' <i class="fa fa-check-double wapi-read"></i></div>';
        html += '</div></div>';

        // Buttons are never authored here — an edit carries the approved ones
        // through untouched, so the preview shows them as read-only facts.
        var buttons = (tplEditing && tplEditing.button_defs) || [];
        if (buttons.length) {
            html += '<div class="wapi-tpl-preview-buttons">';
            buttons.forEach(function (b) {
                var icon = b.type === 'URL' ? 'fa-arrow-up-right-from-square'
                    : (b.type === 'PHONE_NUMBER' ? 'fa-phone' : (b.type === 'COPY_CODE' ? 'fa-copy' : 'fa-reply'));
                html += '<span class="wapi-tpl-preview-btn"><i class="fa ' + icon + '"></i> ' + esc(b.text || b.type) + '</span>';
            });
            html += '</div>';
        }

        $('#wapi-template-live-preview').html(html + '</div>');
    }

    /* ── Counters, pre-flight checks ── */

    function tplCount(id, value, max) {
        var len = String(value || '').length;
        $('#wapi-count-' + id).text(len + '/' + max).toggleClass('wapi-count-over', len > max);
    }

    /**
     * Every rule Meta would bounce the template on, checked before it is sent.
     * Blocking issues disable submit; warnings are things that pass review but
     * usually should not.
     */
    function tplChecks() {
        var bad = [], warn = [], ok = [];
        var edit    = !!(tplEditing && tplEditing.id);
        var name    = tplVal('name').trim();
        var body    = tplVal('body_text').trim();
        var header  = tplHasTextHeader() ? tplVal('header_text').trim() : '';
        var indexes = tplVarIndexes(body);
        var count   = indexes.length;

        // ── Name ──
        if (!edit) {
            if (name === '') {
                bad.push('Give the template a name.');
            } else if (!/^[a-z0-9_]+$/.test(name)) {
                bad.push('The name may only use lowercase letters, numbers and underscores.');
            } else if (($.inArray(name + '|' + tplVal('language').toLowerCase(), (WAPI.templateKeys || [])) !== -1)) {
                bad.push('A template named "' + name + '" already exists in this language.');
            } else {
                ok.push('Name is available.');
            }
        }

        // ── Body ──
        if (body === '') {
            bad.push('The body cannot be empty.');
        } else if (body.length > TPL_MAX.body) {
            bad.push('The body is ' + body.length + ' characters — the limit is ' + TPL_MAX.body + '.');
        }

        // ── Variables ── numbering is fatal; the rest is review guidance Meta
        // usually enforces, so it warns instead of blocking a valid edit.
        if (count > 0) {
            var sequential = indexes.every(function (n, i) { return n === i + 1; });
            if (!sequential) {
                bad.push('Variables must run from {{1}} to {{' + count + '}} with no gaps — renumber them.');
            }
            if (/^\{\{\s*\d+\s*\}\}/.test(body) || /\{\{\s*\d+\s*\}\}$/.test(body)) {
                warn.push('The body starts or ends with a variable — Meta usually rejects that. Add wording around it.');
            }
            if (/\}\}\s*\{\{/.test(body)) {
                warn.push('Two variables sit next to each other — Meta usually rejects that. Put wording between them.');
            }
            var missing = tplSampleValues().filter(function (v) { return String(v).trim() === ''; }).length;
            if (missing > 0) {
                warn.push(missing + ' sample value' + (missing > 1 ? 's are' : ' is') + ' empty — Meta reviews generic placeholders more harshly.');
            } else if (sequential) {
                ok.push(count + ' variable' + (count > 1 ? 's' : '') + ' with sample values.');
            }
        }
        if (tplVarIndexes(header).length > 1) {
            bad.push('A text header takes at most one variable.');
        }
        if (header !== '' && tplVarIndexes(header).length && $.trim($('#wapi-template-header-sample').val() || '') === '') {
            warn.push('The header variable has no sample value.');
        }

        // ── Content quality ── the two rejections that actually recur.
        if (body !== '' && body.length < 20) {
            warn.push('Very short bodies are often rejected as low quality.');
        }
        if (tplVal('category') === 'UTILITY' && /\b(offer|discount|sale|free|% off|book now|limited)\b/i.test(body)) {
            warn.push('This reads promotional but is filed under Utility — Meta may reject it as the wrong category.');
        }
        if (body.length <= TPL_MAX.body && body !== '') {
            ok.push('Body fits Meta\'s ' + TPL_MAX.body + '-character limit.');
        }

        return { bad: bad, warn: warn, ok: ok };
    }

    function renderTemplateChecks() {
        var c = tplChecks();
        var html = '<div class="wapi-tpllint-title"><i class="fa fa-clipboard-check"></i> Pre-flight checks</div><ul>';

        c.bad.forEach(function (m) { html += lintLine('bad', 'fa-circle-xmark', m); });
        c.warn.forEach(function (m) { html += lintLine('warn', 'fa-triangle-exclamation', m); });
        if (!c.bad.length) {
            (c.ok.length ? c.ok : ['Ready to submit.']).forEach(function (m) { html += lintLine('ok', 'fa-circle-check', m); });
        }
        html += '</ul>';

        if (!c.bad.length) {
            html += '<p class="wapi-tpllint-foot">Meta usually reviews a new template within a few minutes. ' +
                'It can be used for sending only once it is approved.</p>';
        }
        $('#wapi-template-lint').html(html);

        $('#wapi-template-submit')
            .prop('disabled', c.bad.length > 0)
            .attr('title', c.bad.length ? c.bad[0] : '');
        $('#wapi-template-foot-note').text(c.bad.length
            ? c.bad.length + ' issue' + (c.bad.length > 1 ? 's' : '') + ' to fix'
            : '');
    }

    function lintLine(kind, icon, message) {
        return '<li class="wapi-lint-' + kind + '"><i class="fa ' + icon + '"></i> ' + esc(message) + '</li>';
    }

    var CATEGORY_HINT = {
        MARKETING: 'Offers, announcements, anything promotional. Needs opt-in and is billed at the marketing rate.',
        UTILITY:   'Follows up on a specific transaction — appointment reminders, reports ready, payment receipts.'
    };

    /** One pass over everything that depends on the form's current values. */
    function refreshTemplateComposer() {
        tplCount('header', tplVal('header_text'), TPL_MAX.header);
        tplCount('body', tplVal('body_text'), TPL_MAX.body);
        tplCount('footer', tplVal('footer_text'), TPL_MAX.footer);
        $('#wapi-template-category-hint').text(CATEGORY_HINT[tplVal('category')] || '');
        renderTemplatePreview();
        renderTemplateChecks();
    }

    /**
     * The template modal is dual-mode: `t` null means create, otherwise it
     * prefills an edit. Name and language are immutable at Meta, and the
     * category is frozen while a template is approved — the disabled inputs
     * are also skipped by formData(), so they never reach the server.
     */
    function fillTemplateForm(t) {
        var $f = $('#wapi-template-form');
        var edit = !!t;
        tplEditing = t || null;
        $f[0].reset();

        $f.find('[name="id"]').val(edit ? t.id : '');
        $('#wapi-template-modal-title').text(edit ? 'Edit template — ' + t.name : 'New Template');
        $('#wapi-template-submit-label').text(edit
            ? (t.status === 'REJECTED' ? 'Fix & resubmit' : 'Save & resubmit')
            : 'Submit');

        $f.find('[name="name"]').val(edit ? t.name : '').prop('readonly', edit);
        $f.find('[name="language"]').val(edit ? t.language : 'en').prop('disabled', edit);
        $f.find('[name="category"]')
            .val(edit ? (t.category || 'MARKETING') : 'MARKETING')
            .prop('disabled', edit && !t.can_change_category);

        // A media header cannot be rebuilt from a text field — lock it and say so.
        $f.find('[name="header_text"]').val(edit ? t.header_text : '').prop('disabled', edit && t.media_header);
        $('#wapi-template-header-note').toggle(!!(edit && t.media_header));

        $f.find('[name="body_text"]').val(edit ? t.body_text : '');
        $f.find('[name="footer_text"]').val(edit ? t.footer_text : '');

        $('#wapi-template-edit-note').html(edit ? templateEditNote(t) : '');
        $('#wapi-template-name-hint').text(edit ? 'The name and language are fixed at Meta.' : '');
        $('#wapi-template-preview-samples').prop('checked', true);

        // Samples come back from the approved template, so an edit starts from
        // the values Meta already reviewed.
        // Cleared first — otherwise the previous template's samples would be
        // carried over as "kept" values into a fresh form.
        $('#wapi-template-samples-rows').empty();
        tplSampleSig = null;
        tplSyncSamplesIfChanged();
        if (edit) {
            $('#wapi-template-samples-rows').find('[name="samples[]"]').each(function (i) {
                if ((t.samples || [])[i]) $(this).val(t.samples[i]);
            });
            if (t.header_sample) $('#wapi-template-header-sample').val(t.header_sample);
        }
        refreshTemplateComposer();
    }

    function templateEditNote(t) {
        var html = '';
        if (t.status === 'REJECTED' && t.rejected_label) {
            html += '<div class="wapi-alert wapi-alert-danger"><i class="fa fa-circle-exclamation"></i><div>' +
                '<strong>Rejected — ' + esc(t.rejected_label) + '</strong><p>' + esc(t.rejected_hint) + '</p></div></div>';
        } else if (t.status === 'PAUSED') {
            html += '<div class="wapi-alert wapi-alert-warn"><i class="fa fa-pause"></i><div>' +
                '<strong>Paused by Meta</strong><p>Make the message more relevant to recipients, then resubmit.</p></div></div>';
        }

        var keeps = [];
        if (t.media_header) keeps.push('the ' + esc((t.header_format || '').toLowerCase()) + ' header');
        if (t.buttons && t.buttons.length) keeps.push(t.buttons.length + ' button(s): ' + esc(t.buttons.join(', ')));

        var lines = [];
        if (keeps.length) lines.push('Kept unchanged: ' + keeps.join(' and ') + '.');
        lines.push('Name and language cannot be changed.');
        if (!t.can_change_category) lines.push('Meta does not allow a category change while a template is approved.');
        lines.push('Saving sends the template back to Meta for review.');

        return html + '<div class="wapi-alert wapi-alert-info"><i class="fa fa-circle-info"></i><div>' +
            '<strong>Editing an existing template</strong><p>' + lines.join(' ') + '</p></div></div>';
    }

    window.wapiOpenTemplateModal = function () {
        fillTemplateForm(null);
        openModal('wapi-template-modal');
        $('#wapi-template-form').find('[name="name"]').focus();
    };

    /* ── Composer wiring ── */

    // Rebuilding the sample rows on every keystroke would be wasteful, so it
    // only happens when the set of variables actually changed.
    var tplSampleSig = null;

    function tplSyncSamplesIfChanged() {
        var sig = tplVarIndexes(tplVal('body_text')).join(',') +
            '|' + (tplHasTextHeader() ? tplVarIndexes(tplVal('header_text')).length : 0);
        if (sig === tplSampleSig) return;
        tplSampleSig = sig;
        tplSyncSamples();
    }

    $(document).on('input', '#wapi-template-form [name="body_text"], #wapi-template-form [name="header_text"], #wapi-template-form [name="footer_text"]', function () {
        tplSyncSamplesIfChanged();
        refreshTemplateComposer();
    });
    $(document).on('input', '#wapi-template-samples-rows input', refreshTemplateComposer);
    $(document).on('change', '#wapi-template-form [name="category"], #wapi-template-form [name="language"], #wapi-template-preview-samples', refreshTemplateComposer);

    // Meta only accepts lowercase letters, digits and underscores in a name —
    // slugify as it is typed instead of failing after the round-trip.
    $(document).on('input', '#wapi-template-form [name="name"]', function () {
        var el = this, pos = el.selectionStart;
        var slug = el.value.toLowerCase().replace(/[^a-z0-9_]+/g, '_');
        if (slug !== el.value) {
            el.value = slug;
            el.setSelectionRange(pos, pos);
        }
        renderTemplateChecks();
    });

    /** Wrap the current body selection in a WhatsApp formatting token. */
    $(document).on('click', '.wapi-tplbar-btn[data-wrap]', function () {
        var token = $(this).data('wrap');
        var el    = tplField('body_text')[0];
        var start = el.selectionStart, end = el.selectionEnd;
        var text  = el.value.slice(start, end) || 'text';

        el.value = el.value.slice(0, start) + token + text + token + el.value.slice(end);
        el.focus();
        el.setSelectionRange(start + token.length, start + token.length + text.length);
        refreshTemplateComposer();
    });

    /** Append the next {{n}} at the cursor. */
    $(document).on('click', '#wapi-template-add-var', function () {
        var el      = tplField('body_text')[0];
        var indexes = tplVarIndexes(el.value);
        var next    = (indexes.length ? Math.max.apply(null, indexes) : 0) + 1;
        var token   = '{{' + next + '}}';
        var at      = el.selectionStart;

        el.value = el.value.slice(0, at) + token + el.value.slice(el.selectionEnd);
        el.focus();
        el.setSelectionRange(at + token.length, at + token.length);

        tplSyncSamplesIfChanged();
        refreshTemplateComposer();
    });

    /* View drawer — also the jump-off point for Edit. */
    var tplDetailsId = 0;

    window.wapiTemplateDetails = function (id) {
        get(WAPI.urls.template_details + id, {}, function (r) {
            if (!r.success) { notify(r.message || 'Could not load template', 'danger'); return; }
            var t = r.template;
            tplDetailsId = t.id;
            $('#wapi-template-details-title').text(t.name + ' (' + t.language + ')');
            $('#wapi-template-details-body').html(r.html);
            $('#wapi-template-details-edit').toggle(!!t.editable);
            $('#wapi-template-details-edit-label').text(t.status === 'REJECTED' ? 'Fix & resubmit' : 'Edit template');
            openModal('wapi-template-details-modal');
        });
    };

    window.wapiEditTemplateFromDetails = function () {
        if (tplDetailsId) wapiEditTemplate(tplDetailsId);
    };

    window.wapiEditTemplate = function (id) {
        get(WAPI.urls.template_details + id, {}, function (r) {
            if (!r.success) { notify(r.message || 'Could not load template', 'danger'); return; }
            if (!r.template.editable) {
                notify(r.template.edit_block || 'This template cannot be edited right now.', 'warning');
                return;
            }
            fillTemplateForm(r.template);
            wapiCloseModal('wapi-template-details-modal');
            openModal('wapi-template-modal');
        });
    };

    window.wapiSaveTemplate = function (e) {
        e.preventDefault();

        var checks = tplChecks();
        if (checks.bad.length) {
            notify(checks.bad[0], 'warning');
            renderTemplateChecks();
            return false;
        }

        var data  = formData('#wapi-template-form');
        var url   = data.id ? WAPI.urls.update_template : WAPI.urls.create_template;
        var $btn  = $('#wapi-template-submit').prop('disabled', true);
        var label = $('#wapi-template-submit-label').text();
        $('#wapi-template-submit-label').text('Submitting to Meta…');

        post(url, data, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) { setTimeout(reload, 900); return; }
            $btn.prop('disabled', false);
            $('#wapi-template-submit-label').text(label);
        });
        return false;
    };
    window.wapiDeleteTemplate = function (name) {
        if (!confirm('Delete template "' + name + '" from Meta? All its languages are removed.')) return;
        post(WAPI.urls.delete_template, { name: name }, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 800);
        });
    };

    /* ═══════════════ Single send ═══════════════ */

    /**
     * The send mode. On the provider's shared number there is no radio pair —
     * the form carries a hidden mode=template, because free text cannot be
     * sent on a number this account does not own.
     */
    function sendMode() {
        var $f = $('#wapi-send-form');
        return $f.find('input[name="mode"]:checked').val()
            || $f.find('input[type="hidden"][name="mode"]').val()
            || 'text';
    }

    window.wapiSendModeChanged = function () {
        var mode = sendMode();
        $('#wapi-send-text-field').toggle(mode === 'text');
        $('#wapi-send-template-fields').toggle(mode === 'template');
    };
    window.wapiSendTemplateChanged = function (sel) {
        var t = selectedTemplate($(sel));
        $('#wapi-send-params-field').toggle(!!(t && t.vars > 0));
        $('#wapi-send-header-media-field').toggle(!!(t && t.media == 1));
        $('#wapi-send-template-preview').html(t ? waFormat(t.body) : '');
    };
    window.wapiSendSingle = function (e) {
        e.preventDefault();
        var $f = $('#wapi-send-form');
        var mode = sendMode();
        var data = {
            to: $f.find('[name="to"]').val(),
            mode: mode,
            phone_number_id: $f.find('[name="phone_number_id"]').val() || ''
        };
        if (mode === 'template') {
            var t = selectedTemplate($f.find('[name="template"]'));
            if (!t) { notify('Select a template', 'warning'); return false; }
            data.template_name = t.name;
            data.template_language = t.language;
            data.params = $f.find('[name="params"]').val() || '';
            data.header_media_url = $f.find('[name="header_media_url"]').val() || '';
        } else {
            data.message = $f.find('[name="message"]').val();
        }
        post(WAPI.urls.send_single, data, function (r) {
            if (r.success) {
                notify(r.message || 'Sent', 'success');
                $f.find('[name="message"]').val('');
            } else {
                // Window-closed replies carry their own explanation; steer the
                // user to the template composer rather than a bare error.
                if (r.window_closed) r.action = 'template';
                notifyFailure(r);
            }
        });
        return false;
    };

    /* ═══════════════ Chat inbox ═══════════════ */

    var currentChat = { phone: null, name: null, windowOpen: false };

    window.wapiLoadThreads = function (showSpinner) {
        var $box = $('#wapi-chat-threads');
        if (showSpinner) $box.html('<div class="wapi-empty wapi-empty-sm"><p><i class="fa fa-circle-notch fa-spin"></i></p></div>');

        get(WAPI.urls.chat_threads, {
            scope: $('#wapi-thread-scope').val() || 'conversations',
            search: $('#wapi-thread-search').val() || ''
        }, function (r) {
            if (!r.success) {
                $box.html('<div class="wapi-empty wapi-empty-sm"><p>Could not load conversations.</p></div>');
                return;
            }
            // An empty list used to bail out silently, leaving the initial
            // placeholder up forever — explain what is actually going on.
            if (!r.threads || !r.threads.length) {
                $box.html(emptyInboxHtml(r.summary || {}, r.scope));
                updateUnreadBadge(0);
                return;
            }

            var html = '', unread = 0;
            r.threads.forEach(function (t) {
                var name = String(t.contact_name || t.phone || '');
                var phone = String(t.phone);
                var n = parseInt(t.unread_count, 10) || 0;
                unread += n;
                var active = String(currentChat.phone) === phone ? ' active' : '';

                html += '<div class="wapi-thread' + active + '" data-phone="' + esc(phone) + '" data-name="' + esc(name) + '">' +
                    '<div class="wapi-thread-avatar">' + esc((name.charAt(0) || '?').toUpperCase()) + '</div>' +
                    '<div class="wapi-thread-body">' +
                        '<div class="wapi-thread-top"><strong>' + esc(name) + '</strong>' +
                            '<span class="wapi-thread-time">' + esc(t.last_ago || '') + '</span>' +
                        '</div>' +
                        '<div class="wapi-thread-last">' +
                            threadPreviewIcon(t) + esc(threadPreviewText(t)) +
                        '</div>' +
                    '</div>' +
                    (n > 0 ? '<span class="wapi-count-badge">' + n + '</span>' : '') +
                    '</div>';
            });
            $box.html(html);
            updateUnreadBadge(unread);
            openPendingThread();
        });
    };

    /**
     * Deep link from a notification: ?tab=inbox&thread=<phone>.
     *
     * Opened from the thread list when the number is in it (so the header gets
     * the contact's real name), otherwise straight by phone — a thread hidden
     * by the current scope or search must still be reachable from a toast.
     */
    var pendingThread = null;
    function openPendingThread() {
        if (!pendingThread) return;
        var phone = pendingThread;
        pendingThread = null;
        var $row = $('.wapi-thread[data-phone="' + phone + '"]');
        if ($row.length) { $row.trigger('click'); } else { openChat(phone, phone); }
    }

    /** Preview text that still says something when the body is empty. */
    function threadPreviewText(t) {
        if (t.last_message) return String(t.last_message).replace(/\s+/g, ' ').substring(0, 60);
        if (t.last_media_id) return '[' + (t.last_type || 'media') + ']';
        if (t.last_status === 'failed') return 'Send failed';
        return '[' + (t.last_type || 'message') + ']';
    }

    function threadPreviewIcon(t) {
        if (t.last_direction !== 'outgoing') return '';
        if (t.last_status === 'failed') return '<i class="fa fa-triangle-exclamation wapi-failed"></i> ';
        if (t.last_campaign_id) return '<i class="fa fa-bullhorn"></i> ';
        var read = t.last_status === 'read';
        return '<i class="fa ' + (t.last_status === 'delivered' || read ? 'fa-check-double' : 'fa-check') +
               (read ? ' wapi-read' : '') + '"></i> ';
    }

    /** Say why the inbox is empty rather than showing a dead placeholder. */
    function emptyInboxHtml(s, scope) {
        var search = $('#wapi-thread-search').val();
        if (search) {
            return '<div class="wapi-empty wapi-empty-sm"><p>No conversation matches “' + esc(search) + '”.</p></div>';
        }
        var total = parseInt(s.total, 10) || 0;
        var campaign = parseInt(s.campaign, 10) || 0;

        // Everything logged is a campaign blast, which this view hides.
        if (total > 0 && scope !== 'all' && campaign >= total) {
            return '<div class="wapi-empty wapi-empty-sm"><p>' + campaign + ' campaign message(s) logged, but no direct conversations yet.</p>' +
                '<button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiShowAllThreads()">Show campaign sends</button></div>';
        }
        if (total === 0 && !s.webhook_at) {
            return '<div class="wapi-empty wapi-empty-sm"><p>No messages yet — and Meta has never delivered a webhook to this server, ' +
                'so incoming messages cannot arrive.</p>' +
                '<button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="$(\'.wapi-tab[data-tab=overview]\').click(); wapiLoadDiagnostics(false);">Run diagnostics</button></div>';
        }
        return '<div class="wapi-empty wapi-empty-sm"><p>No conversations yet. Incoming WhatsApp messages appear here.</p></div>';
    }

    window.wapiShowAllThreads = function () {
        $('#wapi-thread-scope').val('all');
        wapiLoadThreads(true);
    };

    function updateUnreadBadge(n) {
        var $b = $('#wapi-unread-badge');
        if (n > 0) {
            if (!$b.length) $('.wapi-tab[data-tab="inbox"]').append('<span class="wapi-count-badge" id="wapi-unread-badge">' + n + '</span>');
            else $b.text(n).show();
        } else if ($b.length) {
            $b.hide();
        }
    }

    var threadSearchTimer = null;
    $(document).on('input', '#wapi-thread-search', function () {
        clearTimeout(threadSearchTimer);
        threadSearchTimer = setTimeout(function () { wapiLoadThreads(false); }, 300);
    });
    $(document).on('change', '#wapi-thread-scope', function () { wapiLoadThreads(true); });

    $(document).on('click', '.wapi-thread', function () {
        // .attr() not .data() — jQuery coerces an all-digit phone into a Number,
        // which then loses its identity as a string key.
        openChat($(this).attr('data-phone'), $(this).attr('data-name'));
    });

    function openChat(phone, name) {
        currentChat.phone = phone;
        currentChat.name = name;
        currentChat.tplOpen = false;
        $('#wapi-chat-tpl-toggle').removeClass('active');
        wapiCancelAttach();
        // Read by the global notifier so it stays quiet about the thread the
        // user is already looking at.
        window.wapiActiveThread = phone;
        $('.wapi-thread').removeClass('active');
        $('.wapi-thread[data-phone="' + phone + '"]').addClass('active');
        $('#wapi-chat-header').show();
        $('#wapi-chat-name').text(name || phone);
        $('#wapi-chat-phone').text(' · ' + phone);
        $('#wapi-chat-input').toggle(!!WAPI.canSend);
        // Instant feedback: blank the previous thread and show a loader while
        // the history request is in flight, and drop the render cache so the
        // response always paints even if its HTML matches the cached value.
        $('#wapi-chat-messages')
            .removeData('lastHtml')
            .html('<div class="wapi-chat-placeholder"><p><i class="fa fa-circle-notch fa-spin"></i></p></div>');
        wapiLoadMessages(true);
        startMsgPolling();
    }

    function startMsgPolling() {
        if (chatMsgTimer) clearInterval(chatMsgTimer);
        chatMsgTimer = setInterval(function () { wapiLoadMessages(false); }, 3000);
    }

    function statusIcon(m) {
        if (m.direction !== 'outgoing') return '';
        var map = { queued: 'fa-clock', accepted: 'fa-check', sent: 'fa-check', delivered: 'fa-check-double', read: 'fa-check-double wapi-read', failed: 'fa-triangle-exclamation wapi-failed' };
        return '<i class="fa ' + (map[m.status] || 'fa-check') + '"></i>';
    }

    window.wapiLoadMessages = function (scroll) {
        if (!currentChat.phone) return;
        var requestedPhone = currentChat.phone;
        get(WAPI.urls.chat_messages, { phone: requestedPhone }, function (r) {
            // The user may have switched threads while this request was in
            // flight — a late reply for the old thread must not paint over
            // (or race ahead of) the one that is now open.
            if (String(currentChat.phone) !== String(requestedPhone)) return;
            if (!r.success) {
                // First load failed — say so instead of spinning forever; the
                // 3-second poll keeps retrying in the background.
                if (!$('#wapi-chat-messages').data('lastHtml')) {
                    $('#wapi-chat-messages').html('<div class="wapi-chat-placeholder"><p>Could not load messages — retrying…</p></div>');
                }
                return;
            }
            currentChat.windowOpen = !!r.window_open;
            var lastIn = r.contact && r.contact.last_incoming_at;
            $('#wapi-chat-window').html(r.window_open
                ? '<span class="wapi-badge wapi-badge-ok" title="They messaged you within the last 24 hours, so free text is allowed."><i class="fa fa-comment"></i> Window open</span>'
                : '<span class="wapi-badge wapi-badge-warn" title="' +
                    (lastIn ? 'Last inbound message: ' + esc(lastIn) + '. Free text is only allowed for 24 hours after their last message.'
                            : 'This contact has never messaged you. The 24-hour window only opens after they message you first.') +
                    '"><i class="fa fa-clock"></i> Template only</span>');
            // Text + attachments live inside the open window; the template
            // composer is forced when it is closed and opt-in (toolbar toggle)
            // while it is open — templates are always allowed to go out.
            $('#wapi-chat-text-row').toggle(r.window_open);
            $('#wapi-chat-template-row').toggle(!r.window_open || !!currentChat.tplOpen);
            $('#wapi-window-note').toggle(!r.window_open);
            if (!r.window_open) {
                $('#wapi-chat-attach-bar').hide();
                populateChatTemplateSelect();
                $('#wapi-window-reason').html(lastIn
                    ? 'They last messaged you <strong>' + esc(lastIn) + '</strong>, so the 24-hour free-text window has expired. Send an approved template to reopen it.'
                    : 'This contact has <strong>never messaged you</strong>. WhatsApp only allows free text for 24 hours after the customer writes to you — start with an approved template.');
            }

            var $box = $('#wapi-chat-messages');
            var atBottom = $box[0] && ($box[0].scrollHeight - $box[0].scrollTop - $box[0].clientHeight < 60);
            var html = '';
            (r.messages || []).forEach(function (m) {
                var cls = m.direction === 'incoming' ? 'in' : (m.is_bot_reply == 1 ? 'bot' : 'out');
                var body = esc(m.body || '');
                if (m.media_id) body = mediaBubbleHtml(m, body);
                if (!body && !m.media_id) body = '<em>[' + esc(m.type) + ']</em>';
                html += '<div class="wapi-bubble-row wapi-bubble-' + cls + '"><div class="wapi-bubble">' +
                    (cls === 'bot' ? '<span class="wapi-bot-tag"><i class="fa fa-robot"></i></span>' : '') +
                    '<div class="wapi-bubble-text">' + body + '</div>' +
                    '<div class="wapi-bubble-meta">' + esc((m.created_at || '').substring(5, 16)) + ' ' + statusIcon(m) + '</div>' +
                    '</div></div>';
            });
            // Only touch the DOM when something changed — a 3-second poll that
            // rewrites identical HTML restarts playing media and refetches
            // every inline image.
            if ($box.data('lastHtml') !== html) {
                $box.data('lastHtml', html);
                $box.html(html || '<div class="wapi-chat-placeholder"><p>No messages yet.</p></div>');
            }
            if (scroll || atBottom) $box.scrollTop($box[0].scrollHeight);
        });
    };

    /** Inline preview for a media message; falls back to a download link. */
    function mediaBubbleHtml(m, captionHtml) {
        var url = WAPI.urls.media + m.id;
        var body;
        if (m.type === 'image' || m.type === 'sticker') {
            body = '<a href="' + url + '" target="_blank"><img class="wapi-bubble-media" src="' + url + '" alt="" loading="lazy"></a>';
        } else if (m.type === 'video') {
            body = '<video class="wapi-bubble-media" controls preload="metadata" src="' + url + '"></video>';
        } else if (m.type === 'audio') {
            body = '<audio class="wapi-bubble-audio" controls preload="none" src="' + url + '"></audio>';
        } else {
            body = '<a href="' + url + '" target="_blank" class="wapi-media-link"><i class="fa fa-paperclip"></i> ' + esc(m.type) + '</a>';
        }
        if (captionHtml) body += '<div class="wapi-bubble-caption">' + captionHtml + '</div>';
        return body;
    }

    function populateChatTemplateSelect() {
        var $sel = $('#wapi-chat-template');
        if ($sel.children().length > 1) return;
        $sel.empty().append('<option value="">— select template —</option>');
        (WAPI.templates || []).forEach(function (t, i) {
            $sel.append('<option value="' + i + '">' + esc(t.name) + ' (' + esc(t.language) + ')' + (t.vars > 0 ? ' · ' + t.vars + ' vars' : '') + '</option>');
        });
    }
    $(document).on('change', '#wapi-chat-template', function () {
        var t = selectedTemplate($(this));
        $('#wapi-chat-template-params').toggle(!!(t && t.vars > 0));
        $('#wapi-chat-template-media').toggle(!!(t && t.media == 1));
        $('#wapi-chat-template-preview').html(t ? waFormat(t.body) : '');
    });

    /** Template composer inside an open window — opt-in via the toolbar toggle. */
    window.wapiToggleChatTemplate = function () {
        currentChat.tplOpen = !currentChat.tplOpen;
        $('#wapi-chat-tpl-toggle').toggleClass('active', currentChat.tplOpen);
        if (currentChat.tplOpen) populateChatTemplateSelect();
        $('#wapi-chat-template-row').toggle(!currentChat.windowOpen || currentChat.tplOpen);
        $('#wapi-window-note').toggle(!currentChat.windowOpen);
    };

    window.wapiSendChat = function () {
        var text = $('#wapi-chat-textarea').val().trim();
        if (!text || !currentChat.phone) return;
        $('#wapi-chat-textarea').val('');
        post(WAPI.urls.send_chat, { to: currentChat.phone, mode: 'text', message: text }, function (r) {
            if (!r.success) notifyFailure(r);
            wapiLoadMessages(true);
        });
    };
    $(document).on('keydown', '#wapi-chat-textarea', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); wapiSendChat(); }
    });

    window.wapiSendChatTemplate = function () {
        var t = selectedTemplate($('#wapi-chat-template'));
        if (!t || !currentChat.phone) { notify('Select a template', 'warning'); return; }
        var headerMedia = $('#wapi-chat-template-media').val() || '';
        if (t.media == 1 && !headerMedia.trim()) {
            notify('This template has a media header — paste a public image/video/document URL first.', 'warning');
            return;
        }
        // Lock the button while the request is in flight — a double click on a
        // billable template send must never fire twice.
        var $btn = $('#wapi-chat-tpl-send');
        if ($btn.prop('disabled')) return;
        $btn.prop('disabled', true).html('<i class="fa fa-circle-notch fa-spin"></i>');

        // Dispose optimistically — the Meta round-trip can take seconds, and a
        // composer that lingers invites a second click. Values are captured
        // first and restored below only if the send fails.
        var params = $('#wapi-chat-template-params').val() || '';
        $('#wapi-chat-template-params').val('');
        $('#wapi-chat-template-media').val('');
        var wasOpen = currentChat.tplOpen;
        currentChat.tplOpen = false;
        $('#wapi-chat-tpl-toggle').removeClass('active');
        if (currentChat.windowOpen) $('#wapi-chat-template-row').hide();

        post(WAPI.urls.send_chat, {
            to: currentChat.phone, mode: 'template',
            template_name: t.name, template_language: t.language,
            params: params,
            header_media_url: headerMedia
        }, function (r) {
            $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i>');
            if (r.success) {
                notify(r.message || 'Sent', 'success');
            } else {
                // Bring the composer back exactly as it was so the user can
                // fix the problem and retry without retyping anything.
                $('#wapi-chat-template-params').val(params);
                $('#wapi-chat-template-media').val(headerMedia);
                currentChat.tplOpen = wasOpen;
                $('#wapi-chat-tpl-toggle').toggleClass('active', wasOpen);
                $('#wapi-chat-template-row').toggle(!currentChat.windowOpen || wasOpen);
                notifyFailure(r);
            }
            wapiLoadMessages(true);
        });
    };

    /* ── Inbox attachments (image / video / audio / document) ── */

    var pendingAttachment = null;

    $(document).on('change', '#wapi-chat-file', function () {
        var f = this.files && this.files[0];
        this.value = ''; // allow re-picking the same file
        if (!f) return;
        if (!currentChat.windowOpen) {
            notify('Attachments need an open 24-hour window — send a template first.', 'warning');
            return;
        }
        pendingAttachment = f;
        $('#wapi-chat-attach-name').text(f.name);
        $('#wapi-chat-attach-size').text(formatBytes(f.size));
        $('#wapi-chat-attach-bar').show();
        $('#wapi-chat-attach-caption').val('').focus();
    });

    window.wapiCancelAttach = function () {
        pendingAttachment = null;
        $('#wapi-chat-attach-bar').hide();
        $('#wapi-chat-attach-caption').val('');
    };

    $(document).on('keydown', '#wapi-chat-attach-caption', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); wapiSendChatMedia(); }
    });

    window.wapiSendChatMedia = function () {
        if (!pendingAttachment || !currentChat.phone) return;
        var file    = pendingAttachment;
        var caption = $('#wapi-chat-attach-caption').val() || '';
        var fd = new FormData();
        fd.append('file', file);
        fd.append('to', currentChat.phone);
        fd.append('caption', caption);

        // Dispose the bar immediately — the upload to Meta can take a while
        // and a lingering bar invites a second click. Restored on failure.
        wapiCancelAttach();
        notify('Sending attachment…', 'warning');

        postFD(WAPI.urls.send_chat_media, fd, function (r) {
            if (r.success) {
                notify(r.message || 'Sent', 'success');
            } else {
                pendingAttachment = file;
                $('#wapi-chat-attach-name').text(file.name);
                $('#wapi-chat-attach-size').text(formatBytes(file.size));
                $('#wapi-chat-attach-caption').val(caption);
                $('#wapi-chat-attach-bar').show();
                notifyFailure(r);
            }
            wapiLoadMessages(true);
        });
    };

    function formatBytes(n) {
        n = parseInt(n, 10) || 0;
        if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
        if (n >= 1024) return Math.round(n / 1024) + ' KB';
        return n + ' B';
    }

    /* ═══════════════ Bulk campaigns ═══════════════ */

    window.wapiOpenCampaignModal = function () {
        populateTemplateSelects();
        openModal('wapi-campaign-modal');
    };

    window.wapiCampaignTemplateChanged = function (sel) {
        var t = selectedTemplate($(sel));
        var $params = $('#wapi-campaign-params');
        $params.empty();
        $('#wapi-campaign-template-preview').html(t ? waFormat(t.body) : '');
        $('#wapi-campaign-header-media').toggle(!!(t && t.media));
        if (!t || t.vars < 1) return;
        for (var i = 1; i <= t.vars; i++) {
            $params.append(
                '<div class="wapi-field wapi-param-row" data-param="' + i + '">' +
                '<label>{{' + i + '}}</label>' +
                '<div class="wapi-param-controls">' +
                '<select class="wapi-param-mode">' +
                '<option value="static">Static text</option>' +
                '<option value="name">Recipient name</option>' +
                '<option value="phone">Recipient phone</option>' +
                '</select>' +
                '<input type="text" class="wapi-param-value" placeholder="Value for {{' + i + '}}">' +
                '</div></div>');
        }
    };
    $(document).on('change', '.wapi-param-mode', function () {
        $(this).closest('.wapi-param-controls').find('.wapi-param-value').toggle($(this).val() === 'static');
    });

    window.wapiCampaignSourceChanged = function (sel) {
        $('#wapi-manual-numbers-field').toggle($(sel).val() === 'manual');
        $('#wapi-preview-result').text('');
    };

    window.wapiPreviewRecipients = function () {
        var $f = $('#wapi-campaign-form');
        post(WAPI.urls.recipients_preview, {
            source: $f.find('[name="source"]').val(),
            manual_numbers: $f.find('[name="manual_numbers"]').val()
        }, function (r) {
            if (r.success) $('#wapi-preview-result').text(r.count + ' recipient(s) after dedupe & opt-outs');
        });
    };

    function collectParams() {
        var params = [];
        $('#wapi-campaign-params .wapi-param-row').each(function () {
            var mode = $(this).find('.wapi-param-mode').val();
            if (mode === 'static') {
                params.push({ mode: 'static', value: $(this).find('.wapi-param-value').val() || '' });
            } else {
                params.push({ mode: 'merge', value: mode });
            }
        });
        return params;
    }

    window.wapiCreateCampaign = function (e) {
        e.preventDefault();
        var $f = $('#wapi-campaign-form');
        var t = selectedTemplate($f.find('[name="template"]'));
        if (!t) { notify('Select an approved template', 'warning'); return false; }
        var data = formData('#wapi-campaign-form');
        delete data.template;
        data.template_name = t.name;
        data.template_language = t.language;
        data.params = JSON.stringify(collectParams());
        post(WAPI.urls.create_campaign, data, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 900);
        });
        return false;
    };

    window.wapiCampaignAction = function (id, action) {
        post(WAPI.urls.campaign_action + id, { action: action }, function (r) {
            if (r.success) setTimeout(reload, 500);
            else notify(r.message || 'Failed', 'danger');
        });
    };
    window.wapiDeleteCampaign = function (id) {
        if (!confirm('Delete this campaign and its recipient log?')) return;
        post(WAPI.urls.delete_campaign + id, {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 700);
        });
    };
    window.wapiRunQueue = function () {
        notify('Processing queue…', 'warning');
        post(WAPI.urls.run_queue, {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 900);
        });
    };

    window.wapiCampaignDetails = function (id) {
        get(WAPI.urls.campaign_details + id, {}, function (r) {
            if (!r.success) return;
            var c = r.campaign;
            $('#wapi-campaign-details-title').text(c.name);
            var html = '<div class="wapi-details-summary">' +
                '<span>Total: <strong>' + c.total_count + '</strong></span>' +
                '<span>Sent: <strong>' + c.sent_count + '</strong></span>' +
                '<span>Delivered: <strong>' + c.delivered_count + '</strong></span>' +
                '<span>Read: <strong>' + c.read_count + '</strong></span>' +
                '<span class="wapi-danger-text">Failed: <strong>' + c.failed_count + '</strong></span></div>';
            html += '<div class="wapi-details-table-wrap"><table class="wapi-table"><thead><tr><th>Phone</th><th>Name</th><th>Status</th><th>Error</th></tr></thead><tbody>';
            (r.recipients || []).forEach(function (rc) {
                html += '<tr><td><code class="wapi-code">' + esc(rc.phone) + '</code></td><td>' + esc(rc.name || '') + '</td>' +
                    '<td>' + esc(rc.status) + '</td><td class="wapi-danger-text"><small>' + esc(rc.error || '') + '</small></td></tr>';
            });
            html += '</tbody></table></div>';
            $('#wapi-campaign-details-body').html(html);
            openModal('wapi-campaign-details-modal');
        });
    };

    function startCampaignPolling() {
        if (campaignTimer) clearInterval(campaignTimer);
        campaignTimer = setInterval(function () {
            $('[data-campaign-poll]').each(function () {
                var $row = $(this);
                var id = $row.data('campaign-row');
                get(WAPI.urls.campaign_status + id, {}, function (r) {
                    if (!r.success) return;
                    var c = r.campaign;
                    $row.find('[data-campaign-sent]').text(c.sent_count);
                    $row.find('[data-campaign-delivered]').text(c.delivered_count);
                    $row.find('[data-campaign-read]').text(c.read_count);
                    $row.find('[data-campaign-failed]').text(c.failed_count);
                    var pct = c.total_count > 0 ? Math.round((parseInt(c.sent_count, 10) + parseInt(c.failed_count, 10)) / c.total_count * 100) : 0;
                    $row.find('.wapi-progress-bar').css('width', pct + '%');
                });
            });
        }, 6000);
    }

    /* ═══════════════ Bot ═══════════════ */

    window.wapiRuleTriggerChanged = function (sel) {
        var isKeyword = $(sel).val() === 'keyword';
        $('#wapi-rule-keywords-field, #wapi-rule-match-field').toggle(isKeyword);
    };

    window.wapiOpenRule = function (id) {
        var $f = $('#wapi-rule-form');
        $f[0].reset();
        $f.find('[name="id"]').val('');
        $('#wapi-rule-modal-title').text(id ? 'Edit Rule' : 'New Rule');
        if (id) {
            get(WAPI.urls.get_rule + id, {}, function (r) {
                if (!r.success || !r.rule) return;
                var rule = r.rule;
                $f.find('[name="id"]').val(rule.id);
                $f.find('[name="name"]').val(rule.name);
                $f.find('[name="priority"]').val(rule.priority);
                $f.find('[name="trigger_type"]').val(rule.trigger_type);
                $f.find('[name="match_type"]').val(rule.match_type);
                $f.find('[name="keywords"]').val(rule.keywords);
                $f.find('[name="response_text"]').val(rule.response_text);
                $f.find('[name="enabled"]').prop('checked', rule.enabled == 1);
                wapiRuleTriggerChanged($f.find('[name="trigger_type"]')[0]);
                openModal('wapi-rule-modal');
            });
        } else {
            wapiRuleTriggerChanged($f.find('[name="trigger_type"]')[0]);
            openModal('wapi-rule-modal');
        }
    };

    window.wapiSaveRule = function (e) {
        e.preventDefault();
        post(WAPI.urls.save_rule, formData('#wapi-rule-form'), function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 700);
        });
        return false;
    };
    window.wapiToggleRule = function (id, enabled) {
        post(WAPI.urls.toggle_rule + id, { enabled: enabled }, function () {
            notify(enabled ? 'Rule enabled' : 'Rule disabled', 'success');
        });
    };
    window.wapiDeleteRule = function (id) {
        if (!confirm('Delete this rule?')) return;
        post(WAPI.urls.delete_rule + id, {}, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            if (r.success) setTimeout(reload, 600);
        });
    };
    window.wapiSaveBotSettings = function (e) {
        e.preventDefault();
        var data = formData('#wapi-bot-settings-form');
        data['days[]'] = [];
        $('#wapi-bot-settings-form [name="days[]"]:checked').each(function () { data['days[]'].push($(this).val()); });
        post(WAPI.urls.save_bot_settings, data, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
        });
        return false;
    };

    /* ═══════════════ Contacts / settings ═══════════════ */

    window.wapiToggleOptout = function (phone, opted) {
        post(WAPI.urls.toggle_optout, { phone: phone, opted_out: opted }, function () {
            notify(opted ? 'Contact opted out of campaigns' : 'Contact opted back in', 'success');
        });
    };
    window.wapiSaveSettings = function (e) {
        e.preventDefault();
        post(WAPI.urls.save_settings, { default_country_code: $(e.target).find('[name="default_country_code"]').val() }, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
        });
        return false;
    };

    /* ═══════════════ Inbox notifications ═══════════════ */

    window.wapiNotifyRecipientsChanged = function (sel) {
        $('#wapi-notify-staff-field').toggle($(sel).val() === 'selected');
    };

    window.wapiSaveNotifySettings = function (e) {
        e.preventDefault();
        var $f = $(e.target);
        var staff = [];
        $f.find('[name="staff[]"]:checked').each(function () { staff.push($(this).val()); });

        post(WAPI.urls.save_notify, {
            enabled:    $f.find('[name="enabled"]').is(':checked') ? 1 : 0,
            toast:      $f.find('[name="toast"]').is(':checked') ? 1 : 0,
            desktop:    $f.find('[name="desktop"]').is(':checked') ? 1 : 0,
            sound:      $f.find('[name="sound"]').is(':checked') ? 1 : 0,
            bell:       $f.find('[name="bell"]').is(':checked') ? 1 : 0,
            recipients: $f.find('[name="recipients"]').val(),
            throttle:   $f.find('[name="throttle"]').val(),
            staff:      staff.join(',')
        }, function (r) {
            notify(r.message || 'Done', r.success ? 'success' : 'danger');
            // The notifier reads its switches at render time — reload so the
            // page the user is on starts behaving the way they just asked,
            // landing back on Settings rather than the default tab.
            if (r.success) {
                setTimeout(function () {
                    window.location.href = window.location.pathname + '?tab=settings';
                }, 800);
            }
        });
        return false;
    };

    /**
     * Fire a sample toast / desktop notification / chime. Handled by the
     * injected notifier; if it is not on the page the switches are all off.
     */
    window.wapiTestNotification = function () {
        if (typeof window.wapiNotifyTest === 'function') {
            window.wapiNotifyTest();
        } else {
            notify('Notifications are switched off — enable at least one channel, save, then test.', 'warning');
        }
    };

    /* ═══════════════ Modal / form utilities ═══════════════ */

    function openModal(id) { $('#' + id).addClass('open'); }
    window.wapiCloseModal = function (id) { $('#' + id).removeClass('open'); };
    $(document).on('click', '.wapi-modal', function (e) {
        if (e.target === this) $(this).removeClass('open');
    });

    function formData(sel) {
        var data = {};
        $(sel).serializeArray().forEach(function (f) {
            if (f.name.slice(-2) === '[]') {
                if (!data[f.name]) data[f.name] = [];
                data[f.name].push(f.value);
            } else {
                data[f.name] = f.value;
            }
        });
        return data;
    }
})();
