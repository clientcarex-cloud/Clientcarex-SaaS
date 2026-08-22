<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php $active = 'settings'; include __DIR__ . '/_nav.php'; ?>

            <?= form_open(admin_url('careers/settings')); ?>
            <div class="crs-split">
                <div>
                    <!-- ── Website ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Careers website</h3></div>
                        <div class="crs-card-body">
                            <p class="crs-muted" style="margin-top:0">
                                Openings reach your website through the embed code below — there is no API key
                                and nothing to configure on the web server.
                            </p>

                            <div class="crs-field">
                                <label class="crs-label">Public careers page URL</label>
                                <input type="url" name="careers_site_url" class="crs-input" placeholder="https://clientcarex.com/careers"
                                       value="<?= html_escape((string) careers_opt('careers_site_url')); ?>">
                                <div class="crs-hint">Used for the "view live" links here and for the job links in candidate emails.</div>
                            </div>

                            <div class="crs-field">
                                <label class="crs-label">Company name shown to candidates</label>
                                <input type="text" name="careers_company_name" class="crs-input"
                                       value="<?= html_escape((string) careers_opt('careers_company_name')); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ── Embed widget ── -->
                    <?php
                    $embed_js      = site_url('careers/careers_embed/js');
                    $embed_snippet = '<!-- Clientcarex Careers — live openings -->' . "\n"
                        . '<div data-careers-embed></div>' . "\n"
                        . '<script src="' . $embed_js . '" async></script>';
                    $embed_iframe  = '<iframe src="' . site_url('careers/careers_embed/page')
                        . '" style="width:100%;height:900px;border:0" title="Careers"></iframe>';
                    ?>
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Embed on any website</h3>
                            <span class="crs-badge" style="background:#ecfdf5;color:#047857">No API key needed</span>
                        </div>
                        <div class="crs-card-body">
                            <p class="crs-muted" style="margin-top:0">
                                Paste this wherever the openings should appear — your website, a landing page,
                                WordPress, anywhere. It renders the live list, the job details and a working
                                apply form with CV upload, and updates itself as you publish or close openings.
                            </p>

                            <div class="crs-field">
                                <label class="crs-label">Embed code</label>
                                <textarea class="crs-input" rows="3" readonly onclick="this.select()"
                                          style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px"><?= html_escape($embed_snippet); ?></textarea>
                                <button type="button" class="crs-btn crs-btn-sm crs-btn-primary" style="margin-top:8px"
                                        data-crs-copy="<?= html_escape($embed_snippet); ?>"><i class="fa fa-copy"></i> Copy embed code</button>
                                <a href="<?= site_url('careers/careers_embed/page'); ?>" target="_blank" rel="noopener"
                                   class="crs-btn crs-btn-sm" style="margin-top:8px"><i class="fa fa-external-link"></i> Preview</a>
                            </div>

                            <div class="crs-hint" style="margin-bottom:14px">
                                <strong>Options</strong> — add any of these to the <code>&lt;div&gt;</code>:
                                <code>data-department="Engineering"</code>,
                                <code>data-type="internship"</code>,
                                <code>data-limit="5"</code>,
                                <code>data-layout="grid"</code>,
                                <code>data-theme="dark"</code>,
                                <code>data-accent="#00B4D8"</code>,
                                <code>data-filters="0"</code>,
                                <code>data-heading="We are hiring"</code>.
                                Several widgets can live on one page — a department-specific list on one, everything on another.
                            </div>

                            <div class="crs-field">
                                <label class="crs-label">Prefer an iframe?</label>
                                <div class="crs-copy-row">
                                    <input type="text" class="crs-input" readonly value="<?= html_escape($embed_iframe); ?>">
                                    <button type="button" class="crs-btn crs-btn-icon" data-crs-copy="<?= html_escape($embed_iframe); ?>" title="Copy"><i class="fa fa-copy"></i></button>
                                </div>
                                <div class="crs-hint">Same widget on its own page. Individual roles are also shareable at <code><?= site_url('careers/careers_embed/job/{slug}'); ?></code>.</div>
                            </div>

                            <div class="crs-divider"></div>

                            <label class="crs-check">
                                <input type="checkbox" name="careers_embed_enabled" value="1" <?= careers_opt_bool('careers_embed_enabled') ? 'checked' : ''; ?>>
                                <span><strong>Allow the embed widget</strong>
                                    <div class="crs-hint">Switch off to disable every embed instantly, wherever it is pasted.</div></span>
                            </label>

                            <div class="crs-field">
                                <label class="crs-label">Restrict to these domains</label>
                                <input type="text" name="careers_embed_domains" class="crs-input"
                                       placeholder="clientcarex.com, careers.partner.com — leave empty to allow any site"
                                       value="<?= html_escape((string) careers_opt('careers_embed_domains')); ?>">
                                <div class="crs-hint">Only the openings you publish are ever exposed, so leaving this empty is safe.</div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Applications ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Applications</h3></div>
                        <div class="crs-card-body">
                            <label class="crs-check">
                                <input type="checkbox" name="careers_allow_public_apply" value="1" <?= careers_opt_bool('careers_allow_public_apply') ? 'checked' : ''; ?>>
                                <span><strong>Accept applications from the website</strong>
                                    <div class="crs-hint">Switch off to keep the openings visible but close intake entirely.</div></span>
                            </label>

                            <label class="crs-check">
                                <input type="checkbox" name="careers_resume_required" value="1" <?= careers_opt_bool('careers_resume_required') ? 'checked' : ''; ?>>
                                <span><strong>Require a resume upload</strong></span>
                            </label>

                            <div class="crs-grid-3">
                                <div class="crs-field">
                                    <label class="crs-label">Max resume size (MB)</label>
                                    <input type="number" name="careers_max_resume_mb" class="crs-input" min="1" max="25"
                                           value="<?= (int) careers_opt('careers_max_resume_mb'); ?>">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Allowed file types</label>
                                    <input type="text" name="careers_allowed_ext" class="crs-input"
                                           value="<?= html_escape((string) careers_opt('careers_allowed_ext')); ?>">
                                    <div class="crs-hint">Comma separated extensions.</div>
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Block repeat applications for (days)</label>
                                    <input type="number" name="careers_dedupe_days" class="crs-input" min="0" max="365"
                                           value="<?= (int) careers_opt('careers_dedupe_days'); ?>">
                                    <div class="crs-hint">0 disables the check.</div>
                                </div>
                            </div>

                            <div class="crs-grid-3">
                                <div class="crs-field">
                                    <label class="crs-label">Default currency</label>
                                    <input type="text" name="careers_default_currency" class="crs-input" maxlength="10"
                                           value="<?= html_escape((string) careers_opt('careers_default_currency')); ?>">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Default country</label>
                                    <input type="text" name="careers_default_country" class="crs-input" maxlength="120"
                                           value="<?= html_escape((string) careers_opt('careers_default_country')); ?>">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Delete not-selected candidates after (days)</label>
                                    <input type="number" name="careers_retention_days" class="crs-input" min="0" max="3650"
                                           value="<?= (int) careers_opt('careers_retention_days'); ?>">
                                    <div class="crs-hint">0 keeps them forever. Resumes are deleted with the record.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <!-- ── Notifications ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Notifications</h3></div>
                        <div class="crs-card-body">
                            <label class="crs-check">
                                <input type="checkbox" name="careers_ack_enabled" value="1" <?= careers_opt_bool('careers_ack_enabled') ? 'checked' : ''; ?>>
                                <span><strong>Acknowledge every application</strong>
                                    <div class="crs-hint">A branded confirmation email to the candidate, with their reference number.</div></span>
                            </label>

                            <label class="crs-check">
                                <input type="checkbox" name="careers_admin_notify" value="1" <?= careers_opt_bool('careers_admin_notify') ? 'checked' : ''; ?>>
                                <span><strong>Alert the team on every application</strong>
                                    <div class="crs-hint">Resume attached, sent to the addresses below and the opening's hiring manager.</div></span>
                            </label>

                            <label class="crs-check">
                                <input type="checkbox" name="careers_stage_email_enabled" value="1" <?= careers_opt_bool('careers_stage_email_enabled') ? 'checked' : ''; ?>>
                                <span><strong>Allow stage-change emails</strong>
                                    <div class="crs-hint">Shortlisted, interview, offer, hired and not-selected. Still opt-in per change.</div></span>
                            </label>

                            <label class="crs-check">
                                <input type="checkbox" name="careers_alerts_enabled" value="1" <?= careers_opt_bool('careers_alerts_enabled') ? 'checked' : ''; ?>>
                                <span><strong>Accept job-alert subscriptions</strong></span>
                            </label>

                            <div class="crs-field" style="margin-top:12px">
                                <label class="crs-label">Notify these addresses</label>
                                <input type="text" name="careers_notify_emails" class="crs-input"
                                       placeholder="hr@company.com, talent@company.com"
                                       value="<?= html_escape((string) careers_opt('careers_notify_emails')); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ── Automation ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Automation</h3></div>
                        <div class="crs-card-body">
                            <label class="crs-check">
                                <input type="checkbox" name="careers_auto_close_expired" value="1" <?= careers_opt_bool('careers_auto_close_expired') ? 'checked' : ''; ?>>
                                <span><strong>Close openings past their deadline</strong>
                                    <div class="crs-hint">They already stop showing on the website; this keeps the CRM list honest.</div></span>
                            </label>
                            <div class="crs-hint" style="margin-top:10px">
                                Interview reminders go out 24 hours ahead and retention rules are applied on the same cron run.
                                Last run: <strong><?= careers_opt('careers_last_cron') ? _dt(careers_opt('careers_last_cron')) : 'never'; ?></strong>.
                            </div>
                        </div>
                    </div>

                    <div class="crs-card">
                        <div class="crs-card-body">
                            <button type="submit" class="crs-btn crs-btn-primary" style="width:100%;justify-content:center">
                                <i class="fa fa-check"></i> Save settings
                            </button>
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
