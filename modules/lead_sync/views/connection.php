<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Connection editor — also the column-mapping screen.
 *
 * The mapping table is filled in over AJAX (lead_sync.js → admin/lead_sync/preview)
 * because reading someone else's Google Sheet on every page load would make this
 * screen as slow and as fragile as their internet connection. Until it answers,
 * the form simply carries no column_map field and the stored mapping is left
 * untouched — saving before the sheet has been read can never wipe it.
 */

$connection = $connection ?? null;
$id         = $connection ? (int) $connection->id : 0;
$mode       = $connection ? $connection->auth_mode : 'public';

// The robot's address, so the manager knows who to share the sheet with.
$service_email = '';
if ($connection && $connection->auth_mode === 'service_account') {
    $decoded = json_decode(lead_sync_decrypt($connection->credentials), true);
    $service_email = is_array($decoded) ? (string) ($decoded['client_email'] ?? '') : '';
}
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="lsy-wrap" data-connection-id="<?= $id; ?>">

            <?php $active = 'connections'; include __DIR__ . '/_nav.php'; ?>

            <?= form_open(admin_url('lead_sync/connection' . ($id ? '/' . $id : '')), ['id' => 'lsy-form']); ?>
            <div class="lsy-split">
                <div>
                    <!-- ── The sheet ─────────────────────────────────────── -->
                    <div class="lsy-card">
                        <div class="lsy-card-head">
                            <h3><i class="fa-solid fa-table"></i> The Google Sheet</h3>
                            <div class="lsy-card-actions">
                                <button type="button" class="lsy-btn lsy-btn-sm" id="lsy-test">
                                    <i class="fa fa-plug"></i> Test &amp; read columns
                                </button>
                            </div>
                        </div>
                        <div class="lsy-card-body">
                            <div class="lsy-grid-2">
                                <div class="lsy-field">
                                    <label class="lsy-label">Name this connection</label>
                                    <input type="text" name="name" class="lsy-input" required
                                           placeholder="Meta lead ads — Instagram"
                                           value="<?= html_escape($connection->name ?? ''); ?>">
                                    <div class="lsy-hint">Shown on the leads you import and in the history.</div>
                                </div>
                                <div class="lsy-field">
                                    <label class="lsy-label">Check the sheet every</label>
                                    <select name="interval_minutes" class="lsy-select">
                                        <?php foreach ([5 => '5 minutes', 10 => '10 minutes', 15 => '15 minutes', 30 => '30 minutes', 60 => 'hour', 180 => '3 hours', 720 => '12 hours', 1440 => 'day'] as $minutes => $label) { ?>
                                            <option value="<?= $minutes; ?>" <?= (int) ($connection->interval_minutes ?? 15) === $minutes ? 'selected' : ''; ?>><?= $label; ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="lsy-hint">Polling is the safety net. Set up instant delivery on the right and rows usually arrive in seconds.</div>
                                </div>
                            </div>

                            <div class="lsy-field">
                                <label class="lsy-label">Sheet link</label>
                                <input type="text" name="sheet_url" id="lsy-sheet-url" class="lsy-input" required
                                       placeholder="https://docs.google.com/spreadsheets/d/1AbC…/edit#gid=0"
                                       value="<?= html_escape($connection->sheet_url ?? ''); ?>">
                                <div class="lsy-hint">Copy it straight from the browser address bar while the sheet is open.</div>
                            </div>

                            <div class="lsy-grid-2">
                                <div class="lsy-field">
                                    <label class="lsy-label">Tab name <span class="lsy-muted">(optional)</span></label>
                                    <input type="text" name="tab_name" class="lsy-input" placeholder="Sheet1"
                                           value="<?= html_escape($connection->tab_name ?? ''); ?>">
                                    <div class="lsy-hint">Leave empty for the first tab.</div>
                                </div>
                                <div class="lsy-field">
                                    <label class="lsy-label">Tab gid <span class="lsy-muted">(optional)</span></label>
                                    <input type="text" name="gid" class="lsy-input" placeholder="0"
                                           value="<?= html_escape($connection->gid ?? ''); ?>">
                                    <div class="lsy-hint">Taken from the link automatically when it is there.</div>
                                </div>
                            </div>

                            <div class="lsy-field">
                                <label class="lsy-label">How should we read it?</label>
                                <select name="auth_mode" id="lsy-auth-mode" class="lsy-select">
                                    <option value="public" <?= $mode === 'public' ? 'selected' : ''; ?>>Shared link — no credentials (quickest)</option>
                                    <option value="service_account" <?= $mode === 'service_account' ? 'selected' : ''; ?>>Service account — the sheet stays private (recommended)</option>
                                    <option value="api_key" <?= $mode === 'api_key' ? 'selected' : ''; ?>>Google API key</option>
                                </select>
                            </div>

                            <div class="lsy-field" data-lsy-auth="public">
                                <div class="lsy-note">
                                    In the sheet: <strong>Share → General access → Anyone with the link → Viewer</strong>.
                                    Nothing else to set up. Bear in mind the sheet is then readable by anyone who has
                                    that link, so use a service account if the leads are sensitive.
                                </div>
                            </div>

                            <div class="lsy-field" data-lsy-auth="service_account">
                                <label class="lsy-label">Service account JSON key</label>
                                <textarea name="credentials" class="lsy-textarea" placeholder='{ "type": "service_account", "client_email": "…", "private_key": "…" }'></textarea>
                                <div class="lsy-hint">
                                    <?php if ($service_email !== '') { ?>
                                        Saved and in use. Share the sheet (Viewer) with
                                        <strong><?= html_escape($service_email); ?></strong>.
                                        Leave this box empty to keep the current key.
                                    <?php } else { ?>
                                        Google Cloud console → IAM &amp; Admin → Service accounts → Keys → Add key (JSON).
                                        Enable the <em>Google Sheets API</em> on the project, then share the sheet with the
                                        service account's e-mail address as a Viewer. The key is encrypted before it is stored.
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="lsy-field" data-lsy-auth="api_key">
                                <label class="lsy-label">API key</label>
                                <input type="text" name="credentials" class="lsy-input" placeholder="AIza…"
                                       autocomplete="off" value="">
                                <div class="lsy-hint">
                                    The sheet still has to be shared by link. Restrict the key to the Google Sheets API.
                                    <?= $connection && $connection->auth_mode === 'api_key' && $connection->credentials !== '' ? 'A key is already saved — leave this empty to keep it.' : ''; ?>
                                </div>
                            </div>

                            <div class="lsy-field">
                                <label class="lsy-check">
                                    <input type="checkbox" name="has_header" value="1" <?= ($connection->has_header ?? 1) ? 'checked' : ''; ?>>
                                    <span>The first row holds the column names</span>
                                </label>
                            </div>

                            <div id="lsy-test-result"></div>
                        </div>
                    </div>

                    <!-- ── Column mapping ────────────────────────────────── -->
                    <div class="lsy-card">
                        <div class="lsy-card-head">
                            <h3><i class="fa-solid fa-diagram-project"></i> Column mapping</h3>
                        </div>
                        <div class="lsy-card-body">
                            <p class="lsy-hint" style="margin-top:0">
                                Read the sheet above and every column is matched to a CRM field for you —
                                Meta's own export wording is already understood. Change anything that looks
                                wrong; your choices are remembered and re-applied on every later sync, even
                                if the campaign adds new questions.
                            </p>
                            <div id="lsy-map"><div class="lsy-map-status lsy-muted"><i class="fa fa-arrow-up"></i> Press “Test &amp; read columns” to load the sheet's columns.</div></div>
                        </div>
                    </div>

                    <!-- ── What happens to a row ─────────────────────────── -->
                    <div class="lsy-card">
                        <div class="lsy-card-head"><h3><i class="fa-solid fa-user-plus"></i> What happens to each new row</h3></div>
                        <div class="lsy-card-body">
                            <div class="lsy-grid-2">
                                <div class="lsy-field">
                                    <label class="lsy-label">Lead status</label>
                                    <select name="default_status" class="lsy-select">
                                        <?php foreach ($statuses as $status_id => $label) { ?>
                                            <option value="<?= $status_id; ?>" <?= (int) ($connection->default_status ?? 0) === $status_id ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="lsy-hint">Used unless a column in the sheet names a status.</div>
                                </div>
                                <div class="lsy-field">
                                    <label class="lsy-label">Lead source</label>
                                    <select name="default_source" class="lsy-select">
                                        <option value="0">— none —</option>
                                        <?php foreach ($sources as $source_id => $label) { ?>
                                            <option value="<?= $source_id; ?>" <?= (int) ($connection->default_source ?? 0) === $source_id ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="lsy-hint">A source named in the sheet wins, and is created if it is new.</div>
                                </div>
                            </div>

                            <div class="lsy-field">
                                <label class="lsy-label">Who gets the lead</label>
                                <select name="assign_mode" id="lsy-assign-mode" class="lsy-select">
                                    <option value="unassigned"  <?= ($connection->assign_mode ?? '') === 'unassigned' ? 'selected' : ''; ?>>Nobody — leave unassigned</option>
                                    <option value="fixed"       <?= ($connection->assign_mode ?? '') === 'fixed' ? 'selected' : ''; ?>>One agent</option>
                                    <option value="round_robin" <?= ($connection->assign_mode ?? '') === 'round_robin' ? 'selected' : ''; ?>>Share out in turn (round robin)</option>
                                    <option value="column"      <?= ($connection->assign_mode ?? '') === 'column' ? 'selected' : ''; ?>>Whoever the sheet names</option>
                                </select>
                            </div>

                            <div class="lsy-field" data-lsy-assign="fixed column">
                                <label class="lsy-label">Agent<span data-lsy-assign="column"> (fallback when the sheet names nobody)</span></label>
                                <select name="assign_to" class="lsy-select">
                                    <option value="0">— nobody —</option>
                                    <?php foreach ($staff as $staff_id => $full_name) { ?>
                                        <option value="<?= $staff_id; ?>" <?= (int) ($connection->assign_to ?? 0) === $staff_id ? 'selected' : ''; ?>><?= html_escape($full_name); ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="lsy-field" data-lsy-assign="round_robin">
                                <label class="lsy-label">Agents in the rotation</label>
                                <?php $pool = json_decode((string) ($connection->assign_pool ?? '[]'), true) ?: []; ?>
                                <select name="assign_pool[]" class="lsy-select" multiple size="6">
                                    <?php foreach ($staff as $staff_id => $full_name) { ?>
                                        <option value="<?= $staff_id; ?>" <?= in_array($staff_id, array_map('intval', $pool), true) ? 'selected' : ''; ?>><?= html_escape($full_name); ?></option>
                                    <?php } ?>
                                </select>
                                <div class="lsy-hint">Nothing selected means every active staff member.</div>
                            </div>

                            <div class="lsy-grid-2">
                                <div class="lsy-field">
                                    <label class="lsy-label">Tags to add</label>
                                    <input type="text" name="tags" class="lsy-input" placeholder="meta, instagram"
                                           value="<?= html_escape($connection->tags ?? ''); ?>">
                                    <div class="lsy-hint">Comma separated. Added on top of any tags column in the sheet.</div>
                                </div>
                                <div class="lsy-field">
                                    <label class="lsy-label">Ignore rows created before</label>
                                    <input type="date" name="skip_before" class="lsy-input"
                                           value="<?= html_escape((string) ($connection->skip_before ?? '')); ?>">
                                    <div class="lsy-hint">Stops a year of historic rows flooding in on the first sync.</div>
                                </div>
                            </div>

                            <div class="lsy-field">
                                <label class="lsy-label">Treat someone as already in the CRM when</label>
                                <select name="dedupe_by" class="lsy-select">
                                    <option value="phone"       <?= ($connection->dedupe_by ?? 'phone') === 'phone' ? 'selected' : ''; ?>>Their phone number already exists on a lead</option>
                                    <option value="phone_email" <?= ($connection->dedupe_by ?? '') === 'phone_email' ? 'selected' : ''; ?>>Their phone number or e-mail already exists</option>
                                    <option value="email"       <?= ($connection->dedupe_by ?? '') === 'email' ? 'selected' : ''; ?>>Their e-mail already exists</option>
                                    <option value="none"        <?= ($connection->dedupe_by ?? '') === 'none' ? 'selected' : ''; ?>>Never — import every row</option>
                                </select>
                                <div class="lsy-hint">
                                    Matching is on digits only, so “+91 98765 43210” and “09876543210” are the same person.
                                    A repeat enquiry is logged on the lead that already exists instead of becoming a second one.
                                </div>
                            </div>

                            <div class="lsy-field">
                                <label class="lsy-check">
                                    <input type="checkbox" name="active" value="1" <?= ($connection->active ?? 1) ? 'checked' : ''; ?>>
                                    <span><strong>Import from this sheet automatically</strong></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;margin-bottom:24px">
                        <button type="submit" class="lsy-btn lsy-btn-primary"><i class="fa fa-check"></i> Save connection</button>
                        <a href="<?= admin_url('lead_sync'); ?>" class="lsy-btn">Cancel</a>
                        <?php if ($id && lead_sync_can('edit')) { ?>
                            <a href="<?= admin_url('lead_sync/sync/' . $id); ?>" class="lsy-btn"><i class="fa fa-rotate"></i> Sync now</a>
                        <?php } ?>
                        <?php if ($id && lead_sync_can('delete')) { ?>
                            <a href="<?= admin_url('lead_sync/delete/' . $id); ?>" class="lsy-btn lsy-btn-danger _delete" style="margin-left:auto"><i class="fa fa-trash"></i> Delete</a>
                        <?php } ?>
                    </div>
                </div>

                <!-- ── Sidebar ──────────────────────────────────────────── -->
                <div>
                    <?php if ($id) { ?>
                        <div class="lsy-card">
                            <div class="lsy-card-head"><h3><i class="fa-solid fa-bolt"></i> Instant delivery</h3></div>
                            <div class="lsy-card-body">
                                <p class="lsy-hint" style="margin-top:0">
                                    Polling every <?= (int) $connection->interval_minutes; ?> minutes is enough to never lose a lead,
                                    but a lead answered in the first minute converts far better. Paste this script into the
                                    sheet and rows arrive within seconds of Meta writing them.
                                </p>

                                <label class="lsy-label">Webhook URL</label>
                                <div class="lsy-copy-row">
                                    <div class="lsy-url" id="lsy-webhook-url"><?= html_escape(lead_sync_webhook_url($connection->webhook_token)); ?></div>
                                    <button type="button" class="lsy-btn lsy-btn-sm" data-lsy-copy="#lsy-webhook-url"><i class="fa fa-copy"></i></button>
                                </div>
                                <div class="lsy-hint">Anyone holding this URL can add leads, so treat it as a password.</div>

                                <label class="lsy-label" style="margin-top:16px">Google Apps Script</label>
                                <pre class="lsy-code" id="lsy-apps-script"><?= html_escape(lead_sync_apps_script($connection->webhook_token, $connection->tab_name)); ?></pre>
                                <div class="lsy-copy-row">
                                    <button type="button" class="lsy-btn lsy-btn-sm" data-lsy-copy="#lsy-apps-script"><i class="fa fa-copy"></i> Copy script</button>
                                    <a href="<?= admin_url('lead_sync/regenerate_token/' . $id); ?>" class="lsy-btn lsy-btn-sm _delete">Issue a new URL</a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="lsy-card">
                        <div class="lsy-card-head"><h3><i class="fa-brands fa-meta"></i> Getting Meta leads into a sheet</h3></div>
                        <div class="lsy-card-body">
                            <ol class="lsy-steps lsy-small">
                                <li>Meta Business Suite → <strong>All tools → Instant Forms</strong>.</li>
                                <li>Open the form → <strong>Integrations → Google Sheets → Connect</strong>, and pick (or create) the sheet.</li>
                                <li>Meta writes a header row and one row per lead, including <em>created_time</em>, <em>full_name</em>, <em>phone_number</em>, <em>email</em> and every custom question.</li>
                                <li>Paste that sheet's link on the left. The column names Meta uses are recognised automatically.</li>
                            </ol>
                            <div class="lsy-note lsy-small" style="margin-top:14px">
                                No Meta integration? Zapier, Make and n8n all write leads to a sheet as well —
                                or POST them straight to the webhook URL above.
                            </div>
                        </div>
                    </div>

                    <?php if ($id && count($leads)) { ?>
                        <div class="lsy-card">
                            <div class="lsy-card-head"><h3><i class="fa-solid fa-users"></i> Recently imported</h3></div>
                            <div class="lsy-table-scroll">
                                <table class="lsy-table lsy-small">
                                    <tbody>
                                    <?php foreach ($leads as $lead) { ?>
                                        <tr>
                                            <td>
                                                <a href="<?= admin_url('leads/index/' . (int) $lead->id); ?>" class="lsy-strong"><?= html_escape($lead->name); ?></a>
                                                <div class="lsy-muted"><?= html_escape($lead->phonenumber ?: $lead->email); ?></div>
                                            </td>
                                            <td class="lsy-muted text-right"><?= html_escape(lead_sync_time_ago($lead->imported_at)); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?= form_close(); ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
