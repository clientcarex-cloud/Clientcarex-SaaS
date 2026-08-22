<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .hkdbg-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 18px;
    }

    .hkdbg-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 4px;
    }

    .hkdbg-header-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: linear-gradient(135deg, #6366f1, #4338ca);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
    }

    .hkdbg-header h4 {
        margin: 0;
        font-weight: 700;
        color: #111827;
    }

    .hkdbg-header p {
        margin: 2px 0 0;
        color: #6b7280;
        font-size: 12.5px;
    }

    .hkdbg-input,
    select.hkdbg-input {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 13px;
        background: #fff;
    }

    .hkdbg-label {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 5px;
        display: block;
    }

    .hkdbg-btn {
        border: 0;
        border-radius: 8px;
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .hkdbg-btn-run {
        background: linear-gradient(135deg, #6366f1, #4338ca);
    }

    .hkdbg-btn-fire {
        background: linear-gradient(135deg, #ef4444, #b91c1c);
    }

    .hkdbg-btn[disabled] {
        opacity: .55;
        cursor: not-allowed;
    }

    .hkdbg-step {
        display: flex;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 6px;
        font-size: 12.5px;
        background: #f9fafb;
        border: 1px solid #f3f4f6;
    }

    .hkdbg-step .st-icon {
        flex: 0 0 18px;
        padding-top: 1px;
    }

    .hkdbg-step strong {
        color: #111827;
    }

    .hkdbg-step .st-details {
        color: #4b5563;
        margin-top: 2px;
        word-break: break-word;
    }

    .hkdbg-step.st-ok .st-icon { color: #10b981; }
    .hkdbg-step.st-fail { background: #fef2f2; border-color: #fecaca; }
    .hkdbg-step.st-fail .st-icon { color: #ef4444; }
    .hkdbg-step.st-warn { background: #fffbeb; border-color: #fde68a; }
    .hkdbg-step.st-warn .st-icon { color: #f59e0b; }
    .hkdbg-step.st-info .st-icon { color: #3b82f6; }

    .hkdbg-mapping {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 12px;
    }

    .hkdbg-mapping-head {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .hkdbg-chip {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .chip-channel { background: #eef2ff; color: #4338ca; }
    .chip-on { background: #ecfdf5; color: #047857; }
    .chip-off { background: #f3f4f6; color: #6b7280; }
    .chip-recipient { background: #fdf4ff; color: #a21caf; }

    .hkdbg-verdict {
        margin-top: 8px;
        padding: 9px 12px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
    }

    .hkdbg-verdict.v-ok { background: #ecfdf5; color: #047857; }
    .hkdbg-verdict.v-fail { background: #fef2f2; color: #b91c1c; }

    .hkdbg-logs-table {
        width: 100%;
        font-size: 12px;
        border-collapse: collapse;
    }

    .hkdbg-logs-table th {
        text-align: left;
        color: #6b7280;
        font-weight: 600;
        padding: 7px 8px;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .hkdbg-logs-table td {
        padding: 7px 8px;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
        vertical-align: top;
        word-break: break-word;
    }

    .hkdbg-status-pill {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        white-space: nowrap;
    }

    .sp-success { background: #ecfdf5; color: #047857; }
    .sp-bad { background: #fef2f2; color: #b91c1c; }
    .sp-warn { background: #fffbeb; color: #b45309; }

    .hkdbg-section-title {
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        margin: 0 0 12px;
    }

    .hkdbg-firenote {
        font-size: 11.5px;
        color: #9ca3af;
        margin-top: 8px;
    }
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="hkdbg-card">
                    <div class="hkdbg-header">
                        <div class="hkdbg-header-icon"><i class="fa fa-stethoscope"></i></div>
                        <div>
                            <h4>Hook Debugger</h4>
                            <p>Traces the full Omni Messaging pipeline for a hook — mapping → template → recipient → balance → API — and shows exactly where a send stops.</p>
                        </div>
                        <div style="margin-left:auto;">
                            <a href="<?php echo admin_url('sms_wa_email'); ?>" style="font-size:12.5px;"><i class="fa fa-arrow-left"></i> Back to Communication Hub</a>
                        </div>
                    </div>
                </div>

                <div class="hkdbg-card">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="hkdbg-label" for="dbg_hook">Hook</label>
                            <select id="dbg_hook" class="hkdbg-input">
                                <option value="">— Select a hook —</option>
                                <?php foreach ($hooks as $h) { ?>
                                    <option value="<?php echo html_escape($h['hook_key']); ?>">
                                        <?php echo html_escape($h['label'] . '  (' . $h['hook_key'] . ')'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="hkdbg-label" for="dbg_channel">Channel</label>
                            <?php // Only channels this staff member is permitted on —
                                  // "All channels" fans out over exactly this set. ?>
                            <select id="dbg_channel" class="hkdbg-input">
                                <option value="all">All channels</option>
                                <?php foreach (sms_wa_email_allowed_channels() as $dbg_ch => $dbg_label): ?>
                                    <option value="<?= $dbg_ch; ?>"><?= html_escape($dbg_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="hkdbg-label" for="dbg_email">Test Email (for recipient simulation)</label>
                            <input type="text" id="dbg_email" class="hkdbg-input" placeholder="e.g. you@example.com">
                        </div>
                        <div class="col-md-2">
                            <label class="hkdbg-label" for="dbg_mobile">Test Mobile</label>
                            <input type="text" id="dbg_mobile" class="hkdbg-input" placeholder="e.g. 9876543210">
                        </div>
                        <div class="col-md-2" style="padding-top:22px;">
                            <button type="button" id="dbg_run_btn" class="hkdbg-btn hkdbg-btn-run" style="width:100%; justify-content:center;">
                                <i class="fa fa-search"></i> Diagnose
                            </button>
                        </div>
                    </div>
                    <div class="hkdbg-firenote">
                        <i class="fa fa-info-circle"></i> Diagnose is a <strong>dry run</strong> — nothing is sent, no balance is used. Test Fire below sends real messages.
                    </div>
                </div>

                <div id="dbg_results" style="display:none;">

                    <div class="hkdbg-card" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div style="font-size:12.5px; color:#6b7280;">
                            <i class="fa fa-clipboard" style="color:#6366f1; margin-right:6px;"></i>
                            Copy the full diagnostic as text and share it to get the exact fixes.
                        </div>
                        <button type="button" id="dbg_copy_btn" class="hkdbg-btn hkdbg-btn-run">
                            <i class="fa fa-copy"></i> Copy Full Report
                        </button>
                    </div>

                    <div class="hkdbg-card">
                        <h5 class="hkdbg-section-title"><i class="fa fa-list-ol" style="color:#6366f1; margin-right:6px;"></i>Pipeline Checks</h5>
                        <div id="dbg_steps"></div>
                    </div>

                    <div class="hkdbg-card">
                        <h5 class="hkdbg-section-title"><i class="fa fa-sitemap" style="color:#6366f1; margin-right:6px;"></i>Channel Mappings Trace</h5>
                        <div id="dbg_mappings"><em style="color:#9ca3af; font-size:12.5px;">No mappings.</em></div>
                    </div>

                    <div class="hkdbg-card">
                        <h5 class="hkdbg-section-title"><i class="fa fa-history" style="color:#6366f1; margin-right:6px;"></i>Recent Trigger Logs (this hook)</h5>
                        <div style="overflow-x:auto;">
                            <table class="hkdbg-logs-table" id="dbg_logs_table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Channel</th>
                                        <th>Recipient</th>
                                        <th>Status</th>
                                        <th>Error / API message</th>
                                    </tr>
                                </thead>
                                <tbody id="dbg_logs_body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="hkdbg-card">
                        <h5 class="hkdbg-section-title"><i class="fa fa-bolt" style="color:#ef4444; margin-right:6px;"></i>Live Test Fire</h5>
                        <p style="font-size:12.5px; color:#6b7280;">
                            Fires <code>ccx_fire_hook()</code> for real with sample data and your test email/mobile as the patient contact.
                            Active mappings on this hook attempt to send — <strong>real messages go out and balance is used</strong>.
                            If a channel is selected above, <strong>only that channel fires</strong>; "All channels" fires every active mapping.
                            Template variables render as <code>[variable_name]</code> placeholders.
                        </p>
                        <button type="button" id="dbg_fire_btn" class="hkdbg-btn hkdbg-btn-fire">
                            <i class="fa fa-bolt"></i> Test Fire Now
                        </button>
                        <button type="button" id="dbg_smtp_btn" class="hkdbg-btn hkdbg-btn-run" style="margin-left:8px;">
                            <i class="fa fa-key"></i> Test SMTP Login
                        </button>
                        <span style="font-size:11.5px; color:#9ca3af; margin-left:8px;">
                            SMTP Login only checks the email API credentials against the mail server — no email is sent, no balance used.
                        </span>
                        <div id="dbg_smtp_result" style="margin-top:14px;"></div>
                        <div id="dbg_fire_result" style="margin-top:14px;"></div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    (function () {
        'use strict';

        var adminUrl = '<?php echo admin_url('sms_wa_email'); ?>';

        // Read the CSRF hash fresh from the cookie on EVERY request — if the
        // server regenerates tokens, the page-load hash goes stale after the
        // first AJAX call and every later POST would 403
        function csrf() {
            var d = {};
            var hash = csrfData.hash;
            var m = document.cookie.match(/(?:^|;\s*)csrf_cookie_name=([^;]+)/);
            if (m) {
                hash = decodeURIComponent(m[1]);
            }
            d[csrfData.token_name] = hash;
            return d;
        }

        function parseResp(resp) {
            if (typeof resp !== 'string') return resp;
            try {
                return JSON.parse(resp);
            } catch (e) {
                return null;
            }
        }

        function failMessage(xhr, what) {
            var msg = what + ' failed (HTTP ' + xhr.status + (xhr.statusText ? ' ' + xhr.statusText : '') + ')';
            var body = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            if (body) {
                msg += ' — ' + body.substring(0, 300);
            }
            return msg;
        }

        // Last diagnose + test-fire payloads, kept for the Copy Report button
        var lastReport = null;

        function esc(s) {
            return $('<div>').text(s == null ? '' : String(s)).html();
        }

        function stepIcon(status) {
            if (status === 'ok') return '<i class="fa fa-check-circle"></i>';
            if (status === 'fail') return '<i class="fa fa-times-circle"></i>';
            if (status === 'warn') return '<i class="fa fa-exclamation-triangle"></i>';
            return '<i class="fa fa-info-circle"></i>';
        }

        function renderStep(s) {
            return '<div class="hkdbg-step st-' + esc(s.status) + '">' +
                '<span class="st-icon">' + stepIcon(s.status) + '</span>' +
                '<span><strong>' + esc(s.label) + '</strong>' +
                (s.details ? '<div class="st-details">' + esc(s.details) + '</div>' : '') +
                '</span></div>';
        }

        function statusPill(status) {
            var cls = (status === 'success') ? 'sp-success' : (status === 'no_mapping' || status === 'no_recipient') ? 'sp-warn' : 'sp-bad';
            return '<span class="hkdbg-status-pill ' + cls + '">' + esc(status) + '</span>';
        }

        function renderLogs(logs, $tbody) {
            $tbody.empty();
            if (!logs || !logs.length) {
                $tbody.append('<tr><td colspan="5" style="color:#9ca3af;">No log entries.</td></tr>');
                return;
            }
            logs.forEach(function (l) {
                $tbody.append('<tr>' +
                    '<td style="white-space:nowrap;">' + esc(l.created_at) + '</td>' +
                    '<td>' + esc(l.channel) + '</td>' +
                    '<td>' + esc(l.recipient || '—') + '</td>' +
                    '<td>' + statusPill(l.status) + '</td>' +
                    '<td>' + esc(l.error_message || '—') + '</td>' +
                    '</tr>');
            });
        }

        function renderMappings(mappings) {
            var $wrap = $('#dbg_mappings').empty();
            if (!mappings || !mappings.length) {
                $wrap.html('<em style="color:#9ca3af; font-size:12.5px;">No mappings exist for this hook — nothing can send. Add one on the channel tab → Hooks panel.</em>');
                return;
            }
            mappings.forEach(function (m) {
                var html = '<div class="hkdbg-mapping">' +
                    '<div class="hkdbg-mapping-head">' +
                    '<span class="hkdbg-chip chip-channel">' + esc(m.channel) + '</span>' +
                    '<span class="hkdbg-chip ' + (m.active ? 'chip-on' : 'chip-off') + '">' + (m.active ? 'ACTIVE' : 'INACTIVE') + '</span>' +
                    '<span class="hkdbg-chip chip-recipient">to: ' + esc(m.recipient_type) + (m.recipient_value ? ' #' + esc(m.recipient_value) : '') + '</span>' +
                    '<span style="font-size:11px; color:#9ca3af;">mapping #' + m.mapping_id + '</span>' +
                    '</div>';
                m.checks.forEach(function (c) { html += renderStep(c); });
                html += '<div class="hkdbg-verdict v-' + (m.verdict_status === 'ok' ? 'ok' : 'fail') + '">' +
                    (m.verdict_status === 'ok' ? '<i class="fa fa-check"></i> ' : '<i class="fa fa-ban"></i> ') +
                    esc(m.verdict) + '</div></div>';
                $wrap.append(html);
            });
        }

        $('#dbg_run_btn').on('click', function () {
            var hook = $('#dbg_hook').val();
            if (!hook) { alert_float('warning', 'Select a hook first.'); return; }
            var $btn = $(this).prop('disabled', true);

            $.post(adminUrl + '/run_hook_debug', $.extend(csrf(), {
                hook_key: hook,
                channel: $('#dbg_channel').val(),
                test_email: $('#dbg_email').val(),
                test_mobile: $('#dbg_mobile').val()
            })).done(function (resp) {
                var data = parseResp(resp);
                if (!data) {
                    alert_float('danger', 'Diagnose returned invalid JSON — likely a PHP notice/error in the response: ' + String(resp).replace(/<[^>]*>/g, ' ').substring(0, 300));
                    return;
                }
                lastReport = {
                    hook: hook,
                    channel: $('#dbg_channel').val(),
                    test_email: $('#dbg_email').val(),
                    test_mobile: $('#dbg_mobile').val(),
                    data: data,
                    fire_logs: null
                };
                $('#dbg_results').show();
                var $steps = $('#dbg_steps').empty();
                (data.steps || []).forEach(function (s) { $steps.append(renderStep(s)); });
                renderMappings(data.mappings);
                renderLogs(data.logs, $('#dbg_logs_body'));
                $('#dbg_fire_result').empty();
            }).fail(function (xhr) {
                alert_float('danger', failMessage(xhr, 'Diagnose'));
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $('#dbg_fire_btn').on('click', function () {
            var hook = $('#dbg_hook').val();
            if (!hook) { alert_float('warning', 'Select a hook first.'); return; }
            if (!confirm('This will REALLY fire the hook — active mappings will send messages and use balance. Continue?')) return;
            var $btn = $(this).prop('disabled', true);

            $.ajax({
                url: adminUrl + '/debug_test_fire',
                type: 'POST',
                // A hanging SMTP send must not leave the button stuck forever
                timeout: 120000,
                data: $.extend(csrf(), {
                    hook_key: hook,
                    channel: $('#dbg_channel').val(),
                    test_email: $('#dbg_email').val(),
                    test_mobile: $('#dbg_mobile').val()
                })
            }).done(function (resp) {
                var data = parseResp(resp);
                if (!data) {
                    alert_float('danger', 'Test fire returned invalid JSON — likely a PHP notice/error in the response: ' + String(resp).replace(/<[^>]*>/g, ' ').substring(0, 300));
                    return;
                }
                if (!data.success) {
                    alert_float('danger', data.message || 'Test fire failed.');
                    return;
                }
                var html = '<div style="font-size:12.5px; font-weight:600; color:#111827; margin-bottom:8px;">Result — log rows produced by this fire:</div>' +
                    '<div style="overflow-x:auto;"><table class="hkdbg-logs-table"><thead><tr>' +
                    '<th>Time</th><th>Channel</th><th>Recipient</th><th>Status</th><th>Error / API message</th>' +
                    '</tr></thead><tbody id="dbg_fire_logs_body"></tbody></table></div>';
                $('#dbg_fire_result').html(html);
                renderLogs(data.logs, $('#dbg_fire_logs_body'));
                if (lastReport) {
                    lastReport.fire_logs = data.logs || [];
                }
                if (!data.logs || !data.logs.length) {
                    $('#dbg_fire_result').append('<div class="hkdbg-step st-warn" style="margin-top:8px;">' +
                        '<span class="st-icon"><i class="fa fa-exclamation-triangle"></i></span>' +
                        '<span><strong>Hook fired but produced no log rows.</strong>' +
                        '<div class="st-details">This usually means the mapping table is missing or a PHP error occurred mid-fire. Check the Perfex activity log.</div></span></div>');
                }
                alert_float('success', 'Hook fired — see the result rows.');
            }).fail(function (xhr, textStatus) {
                alert_float('danger', textStatus === 'timeout'
                    ? 'Test fire timed out after 120 seconds — an SMTP server is probably not responding. Check the trigger logs for the attempt.'
                    : failMessage(xhr, 'Test fire'));
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // ── SMTP credential probe ──
        $('#dbg_smtp_btn').on('click', function () {
            var $btn = $(this).prop('disabled', true);

            $.post(adminUrl + '/debug_smtp_check', $.extend(csrf(), {
                subtype: 'transactional'
            })).done(function (resp) {
                var data = parseResp(resp);
                if (!data) {
                    alert_float('danger', 'SMTP check returned invalid JSON: ' + String(resp).replace(/<[^>]*>/g, ' ').substring(0, 300));
                    return;
                }
                if (!data.success) {
                    alert_float('danger', data.message || 'SMTP check failed.');
                    return;
                }
                if (lastReport) {
                    lastReport.smtp_check = data;
                }
                var html = '<div style="font-size:12.5px; font-weight:600; color:#111827; margin-bottom:8px;">SMTP Login Check:</div>';
                (data.lines || []).forEach(function (l) {
                    var bad = /FAILED|DOES NOT DECRYPT|EMPTY!|CONNECT FAILED/.test(l);
                    var good = /AUTH SUCCESS|decrypts OK/.test(l);
                    html += '<div class="hkdbg-step ' + (bad ? 'st-fail' : (good ? 'st-ok' : 'st-info')) + '">' +
                        '<span class="st-icon">' + stepIcon(bad ? 'fail' : (good ? 'ok' : 'info')) + '</span>' +
                        '<span style="word-break:break-word;">' + esc(l) + '</span></div>';
                });
                if (data.transcript && data.transcript.length) {
                    html += '<div style="font-size:11px; color:#6b7280; margin-top:8px; background:#f9fafb; border:1px solid #f3f4f6; border-radius:8px; padding:10px; font-family:monospace; white-space:pre-wrap; word-break:break-word;">' +
                        esc(data.transcript.join('\n')) + '</div>';
                }
                $('#dbg_smtp_result').html(html);
                alert_float(data.auth_ok ? 'success' : 'warning', data.auth_ok ? 'SMTP login OK — credentials are correct.' : 'SMTP login failed — see details.');
            }).fail(function (xhr) {
                alert_float('danger', failMessage(xhr, 'SMTP check'));
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // ── Copy Full Report ──
        var STATUS_TAG = { ok: '[OK]  ', fail: '[FAIL]', warn: '[WARN]', info: '[INFO]' };

        function reportLine(c) {
            var tag = STATUS_TAG[c.status] || '[----]';
            var line = tag + ' ' + c.label + ': ' + (c.details || '');
            return line;
        }

        function logLines(logs) {
            if (!logs || !logs.length) {
                return ['  (no log entries)'];
            }
            return logs.map(function (l) {
                return '  ' + l.created_at + ' | ' + l.channel + ' | to: ' + (l.recipient || '—') +
                    ' | status: ' + l.status + (l.error_message ? ' | ' + l.error_message : '');
            });
        }

        function buildReportText() {
            if (!lastReport) return '';
            var r = lastReport;
            var d = r.data || {};
            var lines = [];

            lines.push('═══ OMNI MESSAGING — HOOK DEBUG REPORT ═══');
            lines.push('Generated : ' + new Date().toISOString().replace('T', ' ').substring(0, 19) + ' (browser time)');
            lines.push('Site      : ' + window.location.origin);
            lines.push('Hook      : ' + r.hook);
            lines.push('Channel   : ' + (r.channel || 'all'));
            lines.push('Test email: ' + (r.test_email || '(empty)') + ' | Test mobile: ' + (r.test_mobile || '(empty)'));
            lines.push('');

            lines.push('── PIPELINE CHECKS ──');
            (d.steps || []).forEach(function (s) { lines.push(reportLine(s)); });
            lines.push('');

            lines.push('── CHANNEL MAPPINGS TRACE ──');
            if (!d.mappings || !d.mappings.length) {
                lines.push('(no mappings exist for this hook)');
            } else {
                d.mappings.forEach(function (m) {
                    lines.push('▸ Mapping #' + m.mapping_id + ' | channel: ' + m.channel +
                        ' | ' + (m.active ? 'ACTIVE' : 'INACTIVE') +
                        ' | recipient: ' + m.recipient_type + (m.recipient_value ? ' #' + m.recipient_value : ''));
                    (m.checks || []).forEach(function (c) { lines.push('  ' + reportLine(c)); });
                    lines.push('  VERDICT: ' + m.verdict);
                    lines.push('');
                });
            }

            lines.push('── RECENT TRIGGER LOGS (this hook) ──');
            lines.push.apply(lines, logLines(d.logs));
            lines.push('');

            if (r.smtp_check) {
                lines.push('── SMTP LOGIN CHECK ──');
                (r.smtp_check.lines || []).forEach(function (l) { lines.push('  ' + l); });
                (r.smtp_check.transcript || []).forEach(function (l) { lines.push('    ' + l); });
                lines.push('');
            }

            if (r.fire_logs !== null) {
                lines.push('── LIVE TEST FIRE RESULT ──');
                lines.push.apply(lines, logLines(r.fire_logs));
                lines.push('');
            }

            lines.push('═══ END OF REPORT ═══');
            return lines.join('\n');
        }

        function copyText(text, onDone) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(onDone, function () { legacyCopy(text, onDone); });
            } else {
                legacyCopy(text, onDone);
            }
        }

        function legacyCopy(text, onDone) {
            var $ta = $('<textarea>').css({ position: 'fixed', opacity: 0 }).val(text).appendTo('body');
            $ta[0].select();
            try { document.execCommand('copy'); } catch (e) { /* ignore */ }
            $ta.remove();
            onDone();
        }

        $('#dbg_copy_btn').on('click', function () {
            if (!lastReport) {
                alert_float('warning', 'Run Diagnose first.');
                return;
            }
            var $btn = $(this);
            copyText(buildReportText(), function () {
                alert_float('success', 'Report copied to clipboard — paste it to share.');
                $btn.html('<i class="fa fa-check"></i> Copied!');
                setTimeout(function () {
                    $btn.html('<i class="fa fa-copy"></i> Copy Full Report');
                }, 2500);
            });
        });
    })();
</script>
</body>

</html>
