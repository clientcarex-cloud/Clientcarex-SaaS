<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap" data-crs-application="<?= (int) $application->id; ?>">

            <?php
            $active = 'applications';
            include __DIR__ . '/_nav.php';

            $stages  = careers_stages();
            $answers = $application->answers ? json_decode($application->answers, true) : [];
            $initial = mb_strtoupper(mb_substr(trim($application->name), 0, 1));
            ?>

            <div class="crs-split">
                <div>
                    <!-- ── Candidate ── -->
                    <div class="crs-card">
                        <div class="crs-card-body">
                            <div class="crs-profile">
                                <div class="crs-avatar"><?= html_escape($initial ?: '?'); ?></div>
                                <div style="flex:1;min-width:220px">
                                    <h2 class="crs-profile-name">
                                        <?= html_escape($application->name); ?>
                                        <button type="button" class="crs-star <?= (int) $application->is_starred === 1 ? 'on' : ''; ?>"
                                                data-crs-star="<?= (int) $application->id; ?>"><i class="fa fa-star"></i></button>
                                    </h2>
                                    <div class="crs-profile-role">
                                        Applied for <strong><?= html_escape((string) $application->job_title); ?></strong>
                                        <?php if ($application->department_name) { ?> · <?= html_escape($application->department_name); ?><?php } ?>
                                        · <?= careers_time_ago($application->created_at); ?>
                                    </div>
                                    <div class="crs-chips">
                                        <span class="crs-chip"><i class="fa fa-hashtag"></i> <?= html_escape($application->reference); ?></span>
                                        <a class="crs-chip" href="mailto:<?= html_escape($application->email); ?>"><i class="fa fa-envelope-o"></i> <?= html_escape($application->email); ?></a>
                                        <?php if ($application->phone) { ?>
                                            <a class="crs-chip" href="tel:<?= html_escape(preg_replace('/[^0-9+]/', '', $application->phone)); ?>"><i class="fa fa-phone"></i> <?= html_escape($application->phone); ?></a>
                                        <?php } ?>
                                        <?php if ($application->linkedin_url) { ?>
                                            <a class="crs-chip" href="<?= html_escape($application->linkedin_url); ?>" target="_blank" rel="noopener nofollow"><i class="fa fa-linkedin"></i> LinkedIn</a>
                                        <?php } ?>
                                        <?php if ($application->portfolio_url) { ?>
                                            <a class="crs-chip" href="<?= html_escape($application->portfolio_url); ?>" target="_blank" rel="noopener nofollow"><i class="fa fa-link"></i> Portfolio</a>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="crs-right">
                                    <?php if ($resume) { ?>
                                        <a href="<?= admin_url('careers/resume/' . $application->id); ?>" target="_blank" rel="noopener" class="crs-btn crs-btn-primary">
                                            <i class="fa fa-file-text-o"></i> Resume
                                        </a>
                                    <?php } else { ?>
                                        <span class="crs-chip">No resume attached</span>
                                    <?php } ?>
                                    <div style="margin-top:10px">
                                        <span class="crs-stars" data-crs-rating-for="<?= (int) $application->id; ?>">
                                            <?php for ($star = 1; $star <= 5; $star++) { ?>
                                                <button type="button" class="crs-star <?= $star <= (int) $application->rating ? 'on' : ''; ?>" data-crs-rate="<?= $star; ?>">
                                                    <i class="fa fa-star"></i>
                                                </button>
                                            <?php } ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="crs-divider"></div>

                            <!-- Stage switcher: one click per stage, with the
                                 candidate email opt-in right next to it. -->
                            <div class="crs-flex-between" style="margin-bottom:10px">
                                <label class="crs-label" style="margin:0">Pipeline stage</label>
                                <label class="crs-check" style="padding:0">
                                    <input type="checkbox" id="crs-notify-candidate" <?= careers_opt_bool('careers_stage_email_enabled') ? 'checked' : ''; ?>>
                                    <span class="crs-muted" style="font-size:12px">Email the candidate on change</span>
                                </label>
                            </div>
                            <div class="crs-stage-bar" data-crs-stage-bar="<?= (int) $application->id; ?>">
                                <?php foreach ($stages as $key => $stage) { ?>
                                    <button type="button" class="crs-stage-btn <?= $application->stage === $key ? 'active' : ''; ?>"
                                            data-crs-set-stage="<?= $key; ?>"
                                            style="<?= $application->stage === $key ? 'background:' . $stage['color'] : 'color:' . $stage['color']; ?>">
                                        <?= $stage['label']; ?>
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- ── Details ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Candidate details</h3></div>
                        <div class="crs-card-body">
                            <div class="crs-dl">
                                <?php
                                $details = [
                                    'Current company'     => $application->current_company,
                                    'Current designation' => $application->current_designation,
                                    'Total experience'    => $application->total_experience !== null ? careers_trim_number($application->total_experience) . ' years' : '',
                                    'Current location'    => $application->current_location,
                                    'Current CTC'         => $application->current_ctc,
                                    'Expected CTC'        => $application->expected_ctc,
                                    'Notice period'       => $application->notice_period,
                                    'Source'              => $application->source,
                                ];
                                foreach ($details as $label => $detail) { ?>
                                    <div class="crs-dl-item">
                                        <div class="crs-dl-label"><?= $label; ?></div>
                                        <div class="crs-dl-value"><?= $detail !== '' && $detail !== null ? html_escape((string) $detail) : '<span class="crs-muted">—</span>'; ?></div>
                                    </div>
                                <?php } ?>
                            </div>

                            <?php if (!empty($application->cover_letter)) { ?>
                                <div class="crs-divider"></div>
                                <div class="crs-dl-label">Cover letter</div>
                                <div class="crs-tl-body" style="margin-top:6px"><?= html_escape($application->cover_letter); ?></div>
                            <?php } ?>

                            <?php if (!empty($answers)) { ?>
                                <div class="crs-divider"></div>
                                <div class="crs-dl-label" style="margin-bottom:8px">Screening answers</div>
                                <?php foreach ($answers as $answer) { ?>
                                    <div style="margin-bottom:10px">
                                        <div style="font-size:12.5px;color:var(--crs-soft);font-weight:600"><?= html_escape((string) ($answer['q'] ?? '')); ?></div>
                                        <div style="font-size:13.5px;color:var(--crs-ink)"><?= html_escape((string) ($answer['a'] ?? '')) ?: '<span class="crs-muted">No answer</span>'; ?></div>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- ── Interviews ── -->
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Interviews</h3>
                            <div class="crs-card-actions">
                                <button type="button" class="crs-btn crs-btn-sm" data-crs-toggle="#crs-interview-form"><i class="fa fa-plus"></i> Schedule</button>
                            </div>
                        </div>
                        <div class="crs-card-body">
                            <?php if (empty($interviews)) { ?>
                                <p class="crs-muted" style="margin:0">No interviews scheduled yet.</p>
                            <?php } else { ?>
                                <?php foreach ($interviews as $interview) {
                                    $status = careers_interview_statuses()[$interview->status] ?? ['label' => $interview->status, 'color' => '#64748b']; ?>
                                    <div class="crs-flex" style="padding:10px 0;border-bottom:1px solid var(--crs-line);gap:12px">
                                        <div style="flex:1;min-width:0">
                                            <strong><?= html_escape($interview->title); ?></strong>
                                            <span class="crs-badge" style="background:<?= $status['color']; ?>18;color:<?= $status['color']; ?>"><?= $status['label']; ?></span>
                                            <div class="crs-td-sub">
                                                <?= _dt($interview->scheduled_at); ?> · <?= (int) $interview->duration; ?> min ·
                                                <?= careers_interview_modes()[$interview->mode]['label'] ?? $interview->mode; ?>
                                                <?php if ($interview->meeting_link) { ?>
                                                    · <a href="<?= html_escape($interview->meeting_link); ?>" target="_blank" rel="noopener">Join link</a>
                                                <?php } ?>
                                            </div>
                                            <?php if ($interview->feedback) { ?>
                                                <div class="crs-tl-body" style="margin-top:6px"><?= html_escape($interview->feedback); ?></div>
                                            <?php } ?>
                                        </div>
                                        <?php if (careers_can('delete')) { ?>
                                            <a href="<?= admin_url('careers/delete_interview/' . $interview->id); ?>" class="crs-btn crs-btn-sm crs-btn-icon crs-btn-danger"
                                               data-crs-confirm="Cancel this interview?"><i class="fa fa-times"></i></a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            <?php } ?>

                            <div id="crs-interview-form" hidden style="margin-top:14px">
                                <?= form_open(admin_url('careers/save_interview')); ?>
                                <input type="hidden" name="application_id" value="<?= (int) $application->id; ?>">
                                <div class="crs-grid-3">
                                    <div class="crs-field">
                                        <label class="crs-label">Title</label>
                                        <input type="text" name="title" class="crs-input" value="Interview" maxlength="191">
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Date &amp; time *</label>
                                        <input type="datetime-local" name="scheduled_at" class="crs-input" required>
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Duration (min)</label>
                                        <input type="number" name="duration" class="crs-input" value="45" min="5" max="480">
                                    </div>
                                </div>
                                <div class="crs-grid-3">
                                    <div class="crs-field">
                                        <label class="crs-label">Round</label>
                                        <input type="number" name="round" class="crs-input" value="<?= count($interviews) + 1; ?>" min="1" max="20">
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Mode</label>
                                        <select name="mode" class="crs-input">
                                            <?php foreach (careers_interview_modes() as $key => $mode) { ?>
                                                <option value="<?= $key; ?>"><?= $mode['label']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Meeting link</label>
                                        <input type="url" name="meeting_link" class="crs-input" placeholder="https://meet…">
                                    </div>
                                </div>
                                <div class="crs-grid-2">
                                    <div class="crs-field">
                                        <label class="crs-label">Location / room</label>
                                        <input type="text" name="location" class="crs-input" maxlength="500">
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Interviewers</label>
                                        <select name="interviewers[]" class="crs-input" multiple size="4">
                                            <?php foreach ($staff as $member) { ?>
                                                <option value="<?= (int) $member->staffid; ?>"><?= html_escape($member->full_name); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <label class="crs-check">
                                    <input type="checkbox" name="notify_candidate" value="1" checked>
                                    <span>Email the candidate the schedule, and remind everyone 24 hours before</span>
                                </label>
                                <button type="submit" class="crs-btn crs-btn-primary"><i class="fa fa-check"></i> Save interview</button>
                                <?= form_close(); ?>
                            </div>
                        </div>
                    </div>

                    <!-- ── Timeline ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Activity</h3></div>
                        <div class="crs-card-body">
                            <?php if (careers_can('edit')) { ?>
                                <?= form_open(admin_url('careers/add_note')); ?>
                                <input type="hidden" name="application_id" value="<?= (int) $application->id; ?>">
                                <div class="crs-field">
                                    <textarea name="note" class="crs-input" rows="2" required
                                              placeholder="Add an internal note — screening thoughts, call summary, next step…"></textarea>
                                </div>
                                <button type="submit" class="crs-btn crs-btn-sm crs-btn-primary"><i class="fa fa-comment-o"></i> Add note</button>
                                <?= form_close(); ?>
                                <div class="crs-divider"></div>
                            <?php } ?>

                            <div class="crs-timeline">
                                <?php foreach ($activity as $entry) {
                                    $icons = ['note' => 'fa-comment', 'stage' => 'fa-exchange', 'email' => 'fa-envelope',
                                        'interview' => 'fa-calendar', 'system' => 'fa-bolt']; ?>
                                    <div class="crs-tl-item">
                                        <div class="crs-tl-dot"><i class="fa <?= $icons[$entry->type] ?? 'fa-circle'; ?>"></i></div>
                                        <div class="crs-tl-head">
                                            <?= $entry->staff_name ? html_escape($entry->staff_name) : 'System'; ?> ·
                                            <span title="<?= _dt($entry->created_at); ?>"><?= careers_time_ago($entry->created_at); ?></span>
                                            <?php if ($entry->type === 'note' && careers_can('delete')) { ?>
                                                <a href="<?= admin_url('careers/delete_note/' . $entry->id . '/' . $application->id); ?>"
                                                   class="crs-muted" data-crs-confirm="Delete this note?" style="margin-left:6px"><i class="fa fa-trash-o"></i></a>
                                            <?php } ?>
                                        </div>
                                        <div class="crs-tl-body"><?= html_escape((string) $entry->content); ?></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════ Sidebar ══════════════════ -->
                <div>
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Ownership</h3></div>
                        <div class="crs-card-body">
                            <div class="crs-field">
                                <label class="crs-label">Assigned to</label>
                                <select class="crs-input" data-crs-field="assigned_to">
                                    <option value="0">Unassigned</option>
                                    <?php foreach ($staff as $member) { ?>
                                        <option value="<?= (int) $member->staffid; ?>" <?= (int) $application->assigned_to === (int) $member->staffid ? 'selected' : ''; ?>>
                                            <?= html_escape($member->full_name); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="crs-field">
                                <label class="crs-label">Tags</label>
                                <input type="text" class="crs-input" data-crs-field="tags" value="<?= html_escape($application->tags); ?>"
                                       placeholder="e.g. strong-fit, relocate">
                                <div class="crs-hint">Comma separated. Searchable from the applications table.</div>
                            </div>
                        </div>
                    </div>

                    <?php if (careers_can('edit')) { ?>
                        <div class="crs-card">
                            <div class="crs-card-head"><h3>Email candidate</h3></div>
                            <div class="crs-card-body">
                                <?= form_open(admin_url('careers/email_candidate')); ?>
                                <input type="hidden" name="application_id" value="<?= (int) $application->id; ?>">
                                <div class="crs-field">
                                    <input type="text" name="subject" class="crs-input" placeholder="Subject" required
                                           value="Regarding your application for <?= html_escape((string) $application->job_title); ?>">
                                </div>
                                <div class="crs-field">
                                    <textarea name="message" class="crs-input" rows="5" required placeholder="Write your message…"></textarea>
                                </div>
                                <button type="submit" class="crs-btn crs-btn-primary crs-btn-sm"><i class="fa fa-paper-plane-o"></i> Send</button>
                                <div class="crs-hint">Sent with your CRM branding and logged on the activity timeline.</div>
                                <?= form_close(); ?>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ($job) { ?>
                        <div class="crs-card">
                            <div class="crs-card-head"><h3>The opening</h3></div>
                            <div class="crs-card-body">
                                <div style="font-weight:700;color:var(--crs-ink);margin-bottom:6px">
                                    <a href="<?= admin_url('careers/job/' . $job->id); ?>" style="color:inherit"><?= html_escape($job->title); ?></a>
                                </div>
                                <div class="crs-chips" style="margin-bottom:10px">
                                    <span class="crs-chip"><?= careers_job_type_label($job->job_type); ?></span>
                                    <?php if (careers_location_text($job)) { ?>
                                        <span class="crs-chip"><i class="fa fa-map-marker"></i> <?= html_escape(careers_location_text($job)); ?></span>
                                    <?php } ?>
                                    <span class="crs-chip"><?= careers_work_mode_label($job->work_mode); ?></span>
                                </div>
                                <a href="<?= admin_url('careers/applications?job=' . $job->id); ?>" class="crs-btn crs-btn-sm">
                                    <?= (int) $job->applications_count; ?> applications
                                </a>
                                <a href="<?= admin_url('careers/pipeline?job=' . $job->id); ?>" class="crs-btn crs-btn-sm">Pipeline</a>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if (!empty($others)) { ?>
                        <div class="crs-card">
                            <div class="crs-card-head"><h3>Also applied for</h3></div>
                            <div class="crs-card-body">
                                <?php foreach ($others as $other) { ?>
                                    <div class="crs-flex-between" style="padding:7px 0;border-bottom:1px solid var(--crs-line)">
                                        <a href="<?= admin_url('careers/application/' . $other->id); ?>" style="font-size:13px;font-weight:600">
                                            <?= html_escape((string) $other->job_title); ?>
                                        </a>
                                        <span class="crs-badge" style="background:<?= careers_stage_color($other->stage); ?>18;color:<?= careers_stage_color($other->stage); ?>">
                                            <?= careers_stage_label($other->stage); ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Record</h3></div>
                        <div class="crs-card-body">
                            <div class="crs-dl">
                                <div class="crs-dl-item">
                                    <div class="crs-dl-label">Applied on</div>
                                    <div class="crs-dl-value"><?= _dt($application->created_at); ?></div>
                                </div>
                                <div class="crs-dl-item">
                                    <div class="crs-dl-label">Last activity</div>
                                    <div class="crs-dl-value"><?= $application->last_activity_at ? careers_time_ago($application->last_activity_at) : '—'; ?></div>
                                </div>
                                <?php if ($application->utm) { ?>
                                    <div class="crs-dl-item">
                                        <div class="crs-dl-label">Campaign</div>
                                        <div class="crs-dl-value" style="font-size:12px"><?= html_escape($application->utm); ?></div>
                                    </div>
                                <?php } ?>
                                <div class="crs-dl-item">
                                    <div class="crs-dl-label">IP address</div>
                                    <div class="crs-dl-value crs-muted" style="font-size:12px"><?= html_escape($application->ip_address) ?: '—'; ?></div>
                                </div>
                            </div>

                            <?php if (careers_can('delete')) { ?>
                                <div class="crs-divider"></div>
                                <a href="<?= admin_url('careers/delete_application/' . $application->id); ?>" class="crs-btn crs-btn-sm crs-btn-danger"
                                   data-crs-confirm="Delete this application and its resume permanently?">
                                    <i class="fa fa-trash"></i> Delete application
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
