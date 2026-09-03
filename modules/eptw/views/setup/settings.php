<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'settings'; include __DIR__ . '/_setup_nav.php'; ?>

            <?= form_open(admin_url('eptw/eptw_setup')); ?>
            <div class="eptw-split">
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-hashtag"></i> Permit numbering</h3></div>
                        <div class="eptw-card-body">
                            <div class="eptw-field">
                                <label class="eptw-label">Number format</label>
                                <input name="eptw_number_format" class="eptw-input eptw-mono" value="<?= html_escape(eptw_opt('eptw_number_format')); ?>">
                                <div class="eptw-hint">Tokens: <code>{PROJECT}</code> <code>{AREA}</code> <code>{TYPE}</code> <code>{YEAR}</code> <code>{YY}</code> <code>{MONTH}</code> <code>{SERIAL}</code>. Example with the current settings: <b class="eptw-mono"><?= html_escape($sample); ?></b></div>
                            </div>
                            <div class="eptw-grid-2">
                                <div class="eptw-field">
                                    <label class="eptw-label">Serial digits</label>
                                    <input type="number" min="2" max="8" name="eptw_serial_padding" class="eptw-input" value="<?= html_escape(eptw_opt('eptw_serial_padding')); ?>">
                                </div>
                                <div class="eptw-field">
                                    <label class="eptw-label">Serial restarts</label>
                                    <select name="eptw_serial_scope" class="eptw-select">
                                        <?php foreach (['global' => 'Never (one running number)', 'year' => 'Every year', 'project_year' => 'Per project, every year', 'type_year' => 'Per permit type, every year', 'project_type_year' => 'Per project and type, every year'] as $k => $l) { ?>
                                            <option value="<?= $k; ?>" <?= eptw_opt('eptw_serial_scope') === $k ? 'selected' : ''; ?>><?= $l; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="eptw-note info"><b>The permit number is the control.</b> It is minted only when the PTW Coordinator issues the permit, and never reused.</div>
                        </div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-gears"></i> Workflow</h3></div>
                        <div class="eptw-card-body">
                            <div class="eptw-grid-2">
                                <div class="eptw-field">
                                    <label class="eptw-label">Warn about expiry (hours before end)</label>
                                    <input type="number" min="1" max="72" name="eptw_expiring_hours" class="eptw-input" value="<?= html_escape(eptw_opt('eptw_expiring_hours')); ?>">
                                </div>
                                <div class="eptw-field">
                                    <label class="eptw-label">Max extensions per permit (0 = unlimited)</label>
                                    <input type="number" min="0" max="20" name="eptw_max_extensions" class="eptw-input" value="<?= html_escape(eptw_opt('eptw_max_extensions')); ?>">
                                </div>
                            </div>
                            <label class="eptw-check"><input type="checkbox" name="eptw_auto_activate" value="1" <?= eptw_opt('eptw_auto_activate') === '1' ? 'checked' : ''; ?>> <span><b>Start issued permits automatically</b> when the planned start time arrives (otherwise the coordinator presses "Start work").</span></label>
                            <label class="eptw-check"><input type="checkbox" name="eptw_simops_enabled" value="1" <?= eptw_opt('eptw_simops_enabled') === '1' ? 'checked' : ''; ?>> <span><b>SIMOPS conflict detection</b> — same area, overlapping time, conflicting permit types (rules under SIMOPS rules).</span></label>
                            <label class="eptw-check"><input type="checkbox" name="eptw_email_notifications" value="1" <?= eptw_opt('eptw_email_notifications') === '1' ? 'checked' : ''; ?>> <span><b>Email notifications</b> in addition to the in-app bell (uses the CRM's SMTP settings).</span></label>
                        </div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-folder-open"></i> Closure documents</h3></div>
                        <div class="eptw-card-body">
                            <div class="eptw-hint" style="margin:0 0 10px">A permit closed without these is "Closed – documents pending" until they are uploaded.</div>
                            <?php $req = eptw_required_doc_types(); ?>
                            <div class="eptw-hazard-grid">
                                <?php foreach (eptw_document_types() as $k => $l) { ?>
                                    <label class="eptw-check" style="margin:0;padding:6px 10px;border:1px solid var(--e-line);border-radius:10px"><input type="checkbox" name="eptw_required_docs[]" value="<?= $k; ?>" <?= in_array($k, $req, true) ? 'checked' : ''; ?>> <span><?= html_escape($l); ?></span></label>
                                <?php } ?>
                            </div>
                            <div class="eptw-field" style="margin-top:14px">
                                <label class="eptw-label">Max upload size (MB)</label>
                                <input type="number" min="1" max="100" name="eptw_max_upload_mb" class="eptw-input" value="<?= html_escape(eptw_opt('eptw_max_upload_mb')); ?>" style="max-width:160px">
                            </div>
                        </div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-camera"></i> Camera policy (client default)</h3></div>
                        <div class="eptw-card-body">
                            <?php foreach (eptw_camera_modes() as $k => $l) { ?>
                                <label class="eptw-check"><input type="radio" name="eptw_camera_mode" value="<?= $k; ?>" <?= eptw_opt('eptw_camera_mode') === $k ? 'checked' : ''; ?>> <span><?= html_escape($l); ?></span></label>
                            <?php } ?>
                            <div class="eptw-hint">Each project can override this under Projects &amp; areas.</div>
                        </div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-building"></i> Identity</h3></div>
                        <div class="eptw-card-body">
                            <div class="eptw-field">
                                <label class="eptw-label">Company name on permits</label>
                                <input name="eptw_company_name" class="eptw-input" value="<?= html_escape(eptw_opt('eptw_company_name')); ?>" placeholder="<?= html_escape(get_option('companyname')); ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="eptw-btn eptw-btn-primary eptw-btn-lg"><i class="fa fa-check"></i> Save settings</button>
                </div>

                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> Getting started</h3></div>
                        <div class="eptw-card-body eptw-small">
                            <ol style="padding-left:18px;line-height:1.8;margin:0">
                                <li><a href="<?= admin_url('eptw/eptw_setup/projects'); ?>">Projects &amp; areas</a> — replace the placeholders with your real projects and zones.</li>
                                <li><a href="<?= admin_url('eptw/eptw_setup/contractors'); ?>">Contractors</a> you work with.</li>
                                <li><a href="<?= admin_url('eptw/eptw_setup/team'); ?>">Team &amp; roles</a> — who is Engineer, HSE, Area Authority, Coordinator, Manager.</li>
                                <li><a href="<?= admin_url('eptw/eptw_setup/types'); ?>">Permit types</a> — the 17 V3 templates are loaded; adjust hazards and controls if your standard differs.</li>
                                <li><a href="<?= admin_url('eptw/eptw_setup/import'); ?>">Import</a> your existing Excel register so history is in one place.</li>
                            </ol>
                            <p class="eptw-muted" style="margin:12px 0 0">Reminders, auto-start and expiry warnings run on the CRM cron. Last pass: <b><?= html_escape(eptw_time_ago(get_option('eptw_last_cron'))); ?></b>.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?= form_close(); ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
