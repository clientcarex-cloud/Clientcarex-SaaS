<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php
            $active = 'interviews';
            include __DIR__ . '/_nav.php';

            $modes    = careers_interview_modes();
            $statuses = careers_interview_statuses();

            // Interviewer ids are stored comma-wrapped; this maps them back to names.
            $staff_names = [];
            foreach ($staff as $member) {
                $staff_names[(int) $member->staffid] = $member->full_name;
            }
            ?>

            <form method="get" action="<?= admin_url('careers/interviews'); ?>" class="crs-filters">
                <a href="<?= admin_url('careers/interviews?view=upcoming'); ?>" class="crs-btn <?= $view === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
                <a href="<?= admin_url('careers/interviews?view=past'); ?>" class="crs-btn <?= $view === 'past' ? 'active' : ''; ?>">All / past</a>
                <input type="hidden" name="view" value="<?= html_escape($view); ?>">
                <?= render_date_range_input('from', 'to', (string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''), ['label' => 'Interview date', 'allow_future' => true]); ?>
                <span class="crs-filters-end">
                    <button type="submit" class="crs-btn"><i class="fa fa-filter"></i> Filter</button>
                    <a href="<?= admin_url('careers/interviews'); ?>" class="crs-btn crs-btn-icon" title="Clear"><i class="fa fa-times"></i></a>
                </span>
            </form>

            <div class="crs-card">
                <div class="crs-card-body crs-tight">
                    <?php if (empty($interviews)) { ?>
                        <div class="crs-empty">
                            <i class="fa-regular fa-calendar-check"></i>
                            <h4>No interviews in this view</h4>
                            <p>Schedule one from any candidate's page — the candidate and every interviewer are emailed automatically.</p>
                        </div>
                    <?php } else { ?>
                        <div class="crs-table-wrap">
                            <table class="crs-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Candidate</th>
                                        <th>Opening</th>
                                        <th>Round</th>
                                        <th>Mode</th>
                                        <th>Interviewers</th>
                                        <th>Status</th>
                                        <th class="crs-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($interviews as $interview) {
                                    $status = $statuses[$interview->status] ?? ['label' => $interview->status, 'color' => '#64748b'];
                                    $ids    = array_filter(array_map('intval', explode(',', (string) $interview->interviewers)));
                                    $names  = array_map(static function ($id) use ($staff_names) {
                                        return $staff_names[$id] ?? ('#' . $id);
                                    }, $ids);
                                    ?>
                                    <tr>
                                        <td class="crs-nowrap crs-td-main">
                                            <?= date('d M Y', strtotime($interview->scheduled_at)); ?>
                                            <div class="crs-td-sub"><?= date('H:i', strtotime($interview->scheduled_at)); ?> · <?= (int) $interview->duration; ?> min</div>
                                        </td>
                                        <td>
                                            <a href="<?= admin_url('careers/application/' . $interview->application_id); ?>"><?= html_escape((string) $interview->candidate_name); ?></a>
                                            <div class="crs-td-sub"><?= html_escape((string) $interview->candidate_email); ?></div>
                                        </td>
                                        <td><?= html_escape((string) $interview->job_title); ?></td>
                                        <td class="crs-nowrap">#<?= (int) $interview->round; ?> · <?= html_escape($interview->title); ?></td>
                                        <td class="crs-nowrap">
                                            <?= $modes[$interview->mode]['label'] ?? $interview->mode; ?>
                                            <?php if ($interview->meeting_link) { ?>
                                                <a href="<?= html_escape($interview->meeting_link); ?>" target="_blank" rel="noopener" title="Join"><i class="fa fa-external-link"></i></a>
                                            <?php } ?>
                                        </td>
                                        <td><?= $names ? html_escape(implode(', ', $names)) : '<span class="crs-muted">—</span>'; ?></td>
                                        <td>
                                            <span class="crs-badge" style="background:<?= $status['color']; ?>18;color:<?= $status['color']; ?>"><?= $status['label']; ?></span>
                                            <?php if ((int) $interview->rating > 0) { ?>
                                                <div class="crs-td-sub" style="color:#f59e0b"><?= str_repeat('★', (int) $interview->rating); ?></div>
                                            <?php } ?>
                                        </td>
                                        <td class="crs-right crs-nowrap">
                                            <button type="button" class="crs-btn crs-btn-sm" data-crs-toggle="#crs-outcome-<?= (int) $interview->id; ?>">Outcome</button>
                                            <?php if (careers_can('delete')) { ?>
                                                <a href="<?= admin_url('careers/delete_interview/' . $interview->id); ?>" class="crs-btn crs-btn-sm crs-btn-icon crs-btn-danger"
                                                   data-crs-confirm="Cancel this interview?"><i class="fa fa-times"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <tr id="crs-outcome-<?= (int) $interview->id; ?>" hidden>
                                        <td colspan="8" style="background:var(--crs-bg)">
                                            <?= form_open(admin_url('careers/save_interview')); ?>
                                            <input type="hidden" name="id" value="<?= (int) $interview->id; ?>">
                                            <input type="hidden" name="application_id" value="<?= (int) $interview->application_id; ?>">
                                            <input type="hidden" name="title" value="<?= html_escape($interview->title); ?>">
                                            <input type="hidden" name="round" value="<?= (int) $interview->round; ?>">
                                            <input type="hidden" name="mode" value="<?= html_escape($interview->mode); ?>">
                                            <input type="hidden" name="meeting_link" value="<?= html_escape($interview->meeting_link); ?>">
                                            <input type="hidden" name="location" value="<?= html_escape($interview->location); ?>">
                                            <input type="hidden" name="duration" value="<?= (int) $interview->duration; ?>">
                                            <input type="hidden" name="scheduled_at" value="<?= html_escape($interview->scheduled_at); ?>">
                                            <?php foreach ($ids as $id) { ?>
                                                <input type="hidden" name="interviewers[]" value="<?= (int) $id; ?>">
                                            <?php } ?>

                                            <div class="crs-grid-3">
                                                <div class="crs-field">
                                                    <label class="crs-label">Status</label>
                                                    <select name="status" class="crs-input">
                                                        <?php foreach ($statuses as $key => $option) { ?>
                                                            <option value="<?= $key; ?>" <?= $interview->status === $key ? 'selected' : ''; ?>><?= $option['label']; ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="crs-field">
                                                    <label class="crs-label">Rating</label>
                                                    <select name="rating" class="crs-input">
                                                        <option value="0">Not rated</option>
                                                        <?php for ($star = 1; $star <= 5; $star++) { ?>
                                                            <option value="<?= $star; ?>" <?= (int) $interview->rating === $star ? 'selected' : ''; ?>><?= str_repeat('★', $star); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="crs-field" style="display:flex;align-items:flex-end">
                                                    <button type="submit" class="crs-btn crs-btn-primary"><i class="fa fa-check"></i> Save outcome</button>
                                                </div>
                                            </div>
                                            <div class="crs-field">
                                                <label class="crs-label">Feedback</label>
                                                <textarea name="feedback" class="crs-input" rows="3"><?= html_escape((string) $interview->feedback); ?></textarea>
                                            </div>
                                            <?= form_close(); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
