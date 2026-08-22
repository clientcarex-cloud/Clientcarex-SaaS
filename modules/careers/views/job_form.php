<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php
            $active = 'jobs';
            include __DIR__ . '/_nav.php';

            $is_edit  = $job !== null;
            $value    = static function ($field, $default = '') use ($job) {
                if ($job === null) {
                    return $default;
                }

                return $job->{$field} === null ? $default : $job->{$field};
            };
            $fields = careers_job_form_fields($job ?: (object) ['form_fields' => null]);
            ?>

            <?php
            // form_open() rather than a bare <form>: it carries the CSRF token
            // on installations that switch APP_CSRF_PROTECTION on.
            echo form_open(admin_url('careers/job' . ($is_edit ? '/' . $job->id : '')), ['id' => 'crs-job-form']);
            ?>

                <div class="crs-card">
                    <div class="crs-card-head">
                        <h3><?= $is_edit ? 'Edit opening' : 'New opening'; ?></h3>
                        <?php if ($is_edit) { ?>
                            <span class="crs-chip"><?= html_escape($job->reference); ?></span>
                            <span class="crs-badge" style="background:<?= careers_job_status_color($job->status); ?>18;color:<?= careers_job_status_color($job->status); ?>">
                                <?= careers_job_status_label($job->status); ?>
                            </span>
                        <?php } ?>
                        <div class="crs-card-actions">
                            <a href="<?= admin_url('careers/jobs'); ?>" class="crs-btn">Cancel</a>
                            <?php if ($is_edit && $job->status === 'published') { ?>
                                <a href="<?= html_escape(careers_public_job_url($job)); ?>" target="_blank" rel="noopener" class="crs-btn">
                                    <i class="fa fa-external-link"></i> View live
                                </a>
                            <?php } ?>
                            <button type="submit" class="crs-btn crs-btn-primary"><i class="fa fa-check"></i> Save opening</button>
                        </div>
                    </div>

                    <div class="crs-card-body">
                        <div class="crs-editor-tabs" data-crs-tabs>
                            <button type="button" class="crs-editor-tab active" data-crs-tab="details">Details</button>
                            <button type="button" class="crs-editor-tab" data-crs-tab="description">Description</button>
                            <button type="button" class="crs-editor-tab" data-crs-tab="compensation">Compensation</button>
                            <button type="button" class="crs-editor-tab" data-crs-tab="apply">Application form</button>
                            <button type="button" class="crs-editor-tab" data-crs-tab="publishing">Publishing &amp; SEO</button>
                        </div>

                        <!-- ══════════════════════ Details ══════════════════════ -->
                        <div class="crs-pane active" data-crs-pane="details">
                            <div class="crs-field">
                                <label class="crs-label" for="crs-title">Title *</label>
                                <input type="text" id="crs-title" name="title" class="crs-input" required maxlength="191"
                                       placeholder="e.g. Senior Full-Stack Engineer" value="<?= html_escape($value('title')); ?>">
                            </div>

                            <div class="crs-grid-3">
                                <div class="crs-field">
                                    <label class="crs-label">Opening type *</label>
                                    <select name="job_type" class="crs-input">
                                        <?php foreach (careers_job_types() as $key => $type) { ?>
                                            <option value="<?= $key; ?>" <?= $value('job_type', 'full_time') === $key ? 'selected' : ''; ?>><?= $type['label']; ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="crs-hint">Internships and apprenticeships get their own filter on the website.</div>
                                </div>

                                <div class="crs-field">
                                    <label class="crs-label">Department</label>
                                    <select name="department_id" class="crs-input">
                                        <option value="0">— None —</option>
                                        <?php foreach ($departments as $department) { ?>
                                            <option value="<?= (int) $department->id; ?>" <?= (int) $value('department_id') === (int) $department->id ? 'selected' : ''; ?>>
                                                <?= html_escape($department->name); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="crs-field">
                                    <label class="crs-label">Work mode</label>
                                    <select name="work_mode" class="crs-input">
                                        <?php foreach (careers_work_modes() as $key => $mode) { ?>
                                            <option value="<?= $key; ?>" <?= $value('work_mode', 'onsite') === $key ? 'selected' : ''; ?>><?= $mode['label']; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="crs-grid-2">
                                <div class="crs-field">
                                    <label class="crs-label">Location</label>
                                    <select name="location_id" class="crs-input">
                                        <option value="0">— None —</option>
                                        <?php foreach ($locations as $location) { ?>
                                            <option value="<?= (int) $location->id; ?>" <?= (int) $value('location_id') === (int) $location->id ? 'selected' : ''; ?>>
                                                <?= html_escape($location->name); ?><?= $location->city ? ' — ' . html_escape($location->city) : ''; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <div class="crs-hint">Locations carry the address Google needs for job structured data.</div>
                                </div>

                                <div class="crs-field">
                                    <label class="crs-label">Location label (overrides the above)</label>
                                    <input type="text" name="location_text" class="crs-input" maxlength="191"
                                           placeholder="e.g. Hyderabad / Remote" value="<?= html_escape($value('location_text')); ?>">
                                </div>
                            </div>

                            <div class="crs-grid-3">
                                <div class="crs-field">
                                    <label class="crs-label">Experience from (years)</label>
                                    <input type="number" step="0.5" min="0" max="50" name="experience_min" class="crs-input"
                                           value="<?= html_escape((string) $value('experience_min')); ?>" placeholder="0">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Experience to (years)</label>
                                    <input type="number" step="0.5" min="0" max="50" name="experience_max" class="crs-input"
                                           value="<?= html_escape((string) $value('experience_max')); ?>" placeholder="5">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Number of openings</label>
                                    <input type="number" min="1" max="999" name="openings" class="crs-input" value="<?= (int) $value('openings', 1); ?>">
                                </div>
                            </div>

                            <div class="crs-grid-3">
                                <div class="crs-field">
                                    <label class="crs-label">Education</label>
                                    <input type="text" name="education" class="crs-input" maxlength="255"
                                           placeholder="e.g. B.Tech / B.E. in Computer Science" value="<?= html_escape($value('education')); ?>">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Duration (months)</label>
                                    <input type="number" min="1" max="120" name="duration_months" class="crs-input"
                                           value="<?= html_escape((string) $value('duration_months')); ?>" placeholder="For internships / contracts">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Hiring manager</label>
                                    <select name="hiring_manager" class="crs-input">
                                        <option value="0">— None —</option>
                                        <?php foreach ($staff as $member) { ?>
                                            <option value="<?= (int) $member->staffid; ?>" <?= (int) $value('hiring_manager') === (int) $member->staffid ? 'selected' : ''; ?>>
                                                <?= html_escape($member->full_name); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <div class="crs-hint">Also receives the new-application alerts.</div>
                                </div>
                            </div>

                            <div class="crs-field">
                                <label class="crs-label">Key skills</label>
                                <input type="text" name="skills" class="crs-input" maxlength="1000"
                                       placeholder="PHP, MySQL, JavaScript, REST APIs" value="<?= html_escape($value('skills')); ?>">
                                <div class="crs-hint">Comma separated — shown as chips on the job card.</div>
                            </div>

                            <div class="crs-field">
                                <label class="crs-label">Short summary</label>
                                <textarea name="summary" class="crs-input" maxlength="500" rows="2"
                                          placeholder="One or two lines shown on the listing card and in search results."><?= html_escape($value('summary')); ?></textarea>
                            </div>
                        </div>

                        <!-- ═══════════════════ Description ═══════════════════ -->
                        <div class="crs-pane" data-crs-pane="description">
                            <div class="crs-field">
                                <label class="crs-label">About the role</label>
                                <?= render_textarea('description', '', $value('description'), [], [], '', 'tinymce'); ?>
                            </div>
                            <div class="crs-field">
                                <label class="crs-label">What you will do</label>
                                <?= render_textarea('responsibilities', '', $value('responsibilities'), [], [], '', 'tinymce'); ?>
                            </div>
                            <div class="crs-field">
                                <label class="crs-label">What we are looking for</label>
                                <?= render_textarea('requirements', '', $value('requirements'), [], [], '', 'tinymce'); ?>
                            </div>
                            <div class="crs-field">
                                <label class="crs-label">What we offer</label>
                                <?= render_textarea('benefits', '', $value('benefits'), [], [], '', 'tinymce'); ?>
                            </div>
                        </div>

                        <!-- ══════════════════ Compensation ═══════════════════ -->
                        <div class="crs-pane" data-crs-pane="compensation">
                            <div class="crs-grid-2">
                                <div class="crs-field">
                                    <label class="crs-label">Salary from</label>
                                    <input type="number" step="1" min="0" name="salary_min" class="crs-input" value="<?= html_escape((string) $value('salary_min')); ?>">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Salary to</label>
                                    <input type="number" step="1" min="0" name="salary_max" class="crs-input" value="<?= html_escape((string) $value('salary_max')); ?>">
                                </div>
                            </div>

                            <div class="crs-grid-3">
                                <div class="crs-field">
                                    <label class="crs-label">Currency</label>
                                    <input type="text" name="salary_currency" class="crs-input" maxlength="10"
                                           value="<?= html_escape($value('salary_currency', careers_opt('careers_default_currency'))); ?>">
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Period</label>
                                    <select name="salary_period" class="crs-input">
                                        <?php foreach (careers_salary_periods() as $key => $label) { ?>
                                            <option value="<?= $key; ?>" <?= $value('salary_period', 'year') === $key ? 'selected' : ''; ?>><?= ucfirst($label); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Stipend (internships)</label>
                                    <input type="text" name="stipend" class="crs-input" maxlength="120"
                                           placeholder="e.g. ₹15,000 / month" value="<?= html_escape($value('stipend')); ?>">
                                </div>
                            </div>

                            <label class="crs-check">
                                <input type="checkbox" name="salary_hidden" value="1" <?= (int) $value('salary_hidden') === 1 ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Do not show compensation on the website</strong>
                                    <div class="crs-hint">The range stays on record here for internal reference.</div>
                                </span>
                            </label>
                        </div>

                        <!-- ═════════════════ Application form ════════════════ -->
                        <div class="crs-pane" data-crs-pane="apply">
                            <div class="crs-grid-2">
                                <div class="crs-field">
                                    <label class="crs-label">How candidates apply</label>
                                    <select name="apply_mode" class="crs-input" id="crs-apply-mode">
                                        <option value="internal" <?= $value('apply_mode', 'internal') === 'internal' ? 'selected' : ''; ?>>On our careers page (recommended)</option>
                                        <option value="external" <?= $value('apply_mode') === 'external' ? 'selected' : ''; ?>>Redirect to an external link</option>
                                    </select>
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">External application URL</label>
                                    <input type="url" name="external_url" class="crs-input" maxlength="500"
                                           placeholder="https://…" value="<?= html_escape($value('external_url')); ?>">
                                </div>
                            </div>

                            <div class="crs-divider"></div>

                            <label class="crs-label">Fields on the apply form</label>
                            <div class="crs-grid-3">
                                <?php foreach (careers_optional_form_fields() as $key => $meta) { ?>
                                    <label class="crs-check">
                                        <input type="checkbox" name="form_fields[<?= $key; ?>]" value="1" <?= !empty($fields[$key]) ? 'checked' : ''; ?>>
                                        <span><?= $meta['label']; ?></span>
                                    </label>
                                <?php } ?>
                            </div>
                            <div class="crs-hint">Name and email are always collected. The resume field also obeys the module-wide setting.</div>

                            <div class="crs-divider"></div>

                            <div class="crs-flex-between" style="margin-bottom:10px">
                                <label class="crs-label" style="margin:0">Screening questions</label>
                                <button type="button" class="crs-btn crs-btn-sm" id="crs-add-question"><i class="fa fa-plus"></i> Add question</button>
                            </div>

                            <div id="crs-questions">
                                <?php foreach ($questions as $index => $question) { ?>
                                    <div class="crs-qrow" data-crs-qrow>
                                        <input type="text" class="crs-input" maxlength="500" placeholder="Question"
                                               name="questions[<?= $index; ?>][question]" value="<?= html_escape($question->question); ?>">
                                        <select class="crs-input" name="questions[<?= $index; ?>][type]">
                                            <?php foreach (careers_question_types() as $key => $label) { ?>
                                                <option value="<?= $key; ?>" <?= $question->type === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="text" class="crs-input" placeholder="Choices, comma separated"
                                               name="questions[<?= $index; ?>][options]" value="<?= html_escape($question->options); ?>">
                                        <input type="text" class="crs-input" placeholder="Auto-reject if answer is…"
                                               name="questions[<?= $index; ?>][knockout_answer]" value="<?= html_escape($question->knockout_answer); ?>">
                                        <div class="crs-flex">
                                            <label class="crs-check" style="padding:0" title="Required">
                                                <input type="checkbox" name="questions[<?= $index; ?>][required]" value="1" <?= (int) $question->required === 1 ? 'checked' : ''; ?>>
                                            </label>
                                            <button type="button" class="crs-btn crs-btn-sm crs-btn-icon crs-btn-danger" data-crs-remove-question><i class="fa fa-times"></i></button>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="crs-hint">The checkbox marks a question as required. "Auto-reject" moves an exactly matching answer straight to Not Selected.</div>
                        </div>

                        <!-- ═══════════════ Publishing &amp; SEO ════════════════ -->
                        <div class="crs-pane" data-crs-pane="publishing">
                            <div class="crs-grid-3">
                                <div class="crs-field">
                                    <label class="crs-label">Status</label>
                                    <select name="status" class="crs-input">
                                        <?php foreach (careers_job_statuses() as $key => $status) { ?>
                                            <option value="<?= $key; ?>" <?= $value('status', 'draft') === $key ? 'selected' : ''; ?>><?= $status['label']; ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="crs-hint">Only <strong>Published</strong> reaches the website.</div>
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Apply by</label>
                                    <input type="date" name="deadline" class="crs-input"
                                           value="<?= (!empty($job->deadline) && $job->deadline !== '0000-00-00') ? $job->deadline : ''; ?>">
                                    <div class="crs-hint">Closes itself the day after this date.</div>
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Sort order</label>
                                    <input type="number" name="sort_order" class="crs-input" value="<?= (int) $value('sort_order'); ?>">
                                    <div class="crs-hint">Lower shows first.</div>
                                </div>
                            </div>

                            <div class="crs-grid-2">
                                <label class="crs-check">
                                    <input type="checkbox" name="is_featured" value="1" <?= (int) $value('is_featured') === 1 ? 'checked' : ''; ?>>
                                    <span><strong>Feature this opening</strong><div class="crs-hint">Pinned to the top of the careers page.</div></span>
                                </label>
                                <label class="crs-check">
                                    <input type="checkbox" name="is_urgent" value="1" <?= (int) $value('is_urgent') === 1 ? 'checked' : ''; ?>>
                                    <span><strong>Mark as urgent</strong><div class="crs-hint">Adds an "Urgent hiring" badge.</div></span>
                                </label>
                            </div>

                            <div class="crs-divider"></div>

                            <div class="crs-field">
                                <label class="crs-label">URL slug</label>
                                <input type="text" name="slug" class="crs-input" maxlength="191"
                                       placeholder="auto-generated from the title" value="<?= html_escape($value('slug')); ?>">
                                <div class="crs-hint">
                                    Public link: <code><?= html_escape(rtrim((string) careers_opt('careers_site_url'), '/') ?: '[set the careers page URL in Settings]'); ?>/<span id="crs-slug-preview"><?= html_escape($value('slug', 'your-job-title')); ?></span></code>
                                </div>
                            </div>

                            <div class="crs-field">
                                <label class="crs-label">SEO title</label>
                                <input type="text" name="seo_title" class="crs-input" maxlength="191" value="<?= html_escape($value('seo_title')); ?>">
                            </div>
                            <div class="crs-field">
                                <label class="crs-label">SEO description</label>
                                <textarea name="seo_description" class="crs-input" maxlength="500" rows="2"><?= html_escape($value('seo_description')); ?></textarea>
                                <div class="crs-hint">Left empty, the summary is used. Google Jobs reads the full description regardless.</div>
                            </div>

                            <?php if ($is_edit) { ?>
                                <div class="crs-divider"></div>
                                <div class="crs-dl">
                                    <div class="crs-dl-item">
                                        <div class="crs-dl-label">Published</div>
                                        <div class="crs-dl-value"><?= $job->published_at ? _dt($job->published_at) : '—'; ?></div>
                                    </div>
                                    <div class="crs-dl-item">
                                        <div class="crs-dl-label">Views</div>
                                        <div class="crs-dl-value"><?= (int) $job->views; ?></div>
                                    </div>
                                    <div class="crs-dl-item">
                                        <div class="crs-dl-label">Applications</div>
                                        <div class="crs-dl-value">
                                            <a href="<?= admin_url('careers/applications?job=' . $job->id); ?>"><?= (int) $job->applications_count; ?></a>
                                        </div>
                                    </div>
                                    <div class="crs-dl-item">
                                        <div class="crs-dl-label">Created</div>
                                        <div class="crs-dl-value"><?= _dt($job->created_at); ?></div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="crs-card-head" style="border-top:1px solid var(--crs-line);border-bottom:0">
                        <div class="crs-card-actions" style="margin-left:auto">
                            <a href="<?= admin_url('careers/jobs'); ?>" class="crs-btn">Cancel</a>
                            <button type="submit" class="crs-btn crs-btn-primary"><i class="fa fa-check"></i> Save opening</button>
                        </div>
                    </div>
                </div>
            <?= form_close(); ?>

            <!-- Blueprint for a new screening-question row; cloned by careers.js. -->
            <template id="crs-question-template">
                <div class="crs-qrow" data-crs-qrow>
                    <input type="text" class="crs-input" maxlength="500" placeholder="Question" name="questions[__i__][question]">
                    <select class="crs-input" name="questions[__i__][type]">
                        <?php foreach (careers_question_types() as $key => $label) { ?>
                            <option value="<?= $key; ?>"><?= $label; ?></option>
                        <?php } ?>
                    </select>
                    <input type="text" class="crs-input" placeholder="Choices, comma separated" name="questions[__i__][options]">
                    <input type="text" class="crs-input" placeholder="Auto-reject if answer is…" name="questions[__i__][knockout_answer]">
                    <div class="crs-flex">
                        <label class="crs-check" style="padding:0" title="Required">
                            <input type="checkbox" name="questions[__i__][required]" value="1">
                        </label>
                        <button type="button" class="crs-btn crs-btn-sm crs-btn-icon crs-btn-danger" data-crs-remove-question><i class="fa fa-times"></i></button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
