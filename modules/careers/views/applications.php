<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php
            $active = 'applications';
            include __DIR__ . '/_nav.php';

            $stages = careers_stages();

            $query = static function ($overrides) use ($filters) {
                $params = array_filter(array_merge($filters, $overrides), static function ($v) {
                    return $v !== '' && $v !== null && $v !== 0;
                });

                return admin_url('careers/applications' . (empty($params) ? '' : '?' . http_build_query($params)));
            };
            ?>

            <!-- ── Stage tabs ── -->
            <div class="crs-tabs" style="margin-bottom:14px">
                <a href="<?= $query(['stage' => '', 'page' => '']); ?>" class="crs-tab <?= empty($filters['stage']) ? 'active' : ''; ?>">
                    All <span class="crs-tab-count"><?= array_sum($stage_counts); ?></span>
                </a>
                <?php foreach ($stages as $key => $stage) { ?>
                    <a href="<?= $query(['stage' => $key, 'page' => '']); ?>" class="crs-tab <?= ($filters['stage'] ?? '') === $key ? 'active' : ''; ?>">
                        <?= $stage['label']; ?> <span class="crs-tab-count"><?= (int) ($stage_counts[$key] ?? 0); ?></span>
                    </a>
                <?php } ?>
            </div>

            <!-- ── Filters ── -->
            <form method="get" action="<?= admin_url('careers/applications'); ?>" class="crs-filters">
                <?php if (!empty($filters['stage'])) { ?>
                    <input type="hidden" name="stage" value="<?= html_escape($filters['stage']); ?>">
                <?php } ?>

                <input type="search" name="search" class="crs-input crs-search" placeholder="Name, email, phone, reference, company…"
                       value="<?= html_escape((string) $filters['search']); ?>">

                <select name="job" class="crs-input" onchange="this.form.submit()">
                    <option value="">All openings</option>
                    <?php foreach ($jobs as $job) { ?>
                        <option value="<?= (int) $job->id; ?>" <?= (int) $filters['job'] === (int) $job->id ? 'selected' : ''; ?>>
                            <?= html_escape($job->title); ?>
                        </option>
                    <?php } ?>
                </select>

                <select name="rating" class="crs-input" onchange="this.form.submit()">
                    <option value="">Any rating</option>
                    <?php for ($star = 5; $star >= 1; $star--) { ?>
                        <option value="<?= $star; ?>" <?= (int) $filters['rating'] === $star ? 'selected' : ''; ?>><?= $star; ?>★ and up</option>
                    <?php } ?>
                </select>

                <select name="assigned" class="crs-input" onchange="this.form.submit()">
                    <option value="">Anyone</option>
                    <?php foreach ($staff as $member) { ?>
                        <option value="<?= (int) $member->staffid; ?>" <?= (int) $filters['assigned'] === (int) $member->staffid ? 'selected' : ''; ?>>
                            <?= html_escape($member->full_name); ?>
                        </option>
                    <?php } ?>
                </select>

                <?= render_date_range_input('from', 'to', (string) $filters['from'], (string) $filters['to'], ['label' => 'Applied between']); ?>

                <span class="crs-filters-end">
                    <button type="submit" class="crs-btn"><i class="fa fa-filter"></i> Filter</button>
                    <a href="<?= $query(['starred' => $filters['starred'] ? '' : 1]); ?>" class="crs-btn crs-btn-icon <?= $filters['starred'] ? 'active' : ''; ?>" title="Starred only">
                        <i class="fa fa-star"></i>
                    </a>
                    <a href="<?= admin_url('careers/applications'); ?>" class="crs-btn crs-btn-icon" title="Clear"><i class="fa fa-times"></i></a>
                    <a href="<?= str_replace('careers/applications', 'careers/export', $query([])); ?>" class="crs-btn"><i class="fa fa-download"></i> Export CSV</a>
                </span>
            </form>

            <!-- ── Bulk bar (revealed by careers.js on first selection) ── -->
            <div class="crs-card" id="crs-bulk-bar" hidden>
                <div class="crs-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 18px">
                    <strong><span id="crs-bulk-count">0</span> selected</strong>
                    <select class="crs-input" id="crs-bulk-action" style="max-width:220px">
                        <option value="">Move to stage…</option>
                        <?php foreach ($stages as $key => $stage) { ?>
                            <option value="<?= $key; ?>"><?= $stage['label']; ?></option>
                        <?php } ?>
                        <?php if (careers_can('delete')) { ?>
                            <option value="delete">Delete permanently</option>
                        <?php } ?>
                    </select>
                    <button type="button" class="crs-btn crs-btn-primary crs-btn-sm" id="crs-bulk-apply">Apply</button>
                    <button type="button" class="crs-btn crs-btn-sm" id="crs-bulk-clear">Clear</button>
                </div>
            </div>

            <!-- ── Table ── -->
            <div class="crs-card">
                <div class="crs-card-body crs-tight">
                    <?php if (empty($applications)) { ?>
                        <div class="crs-empty">
                            <i class="fa-solid fa-inbox"></i>
                            <h4>No applications in this view</h4>
                            <p>Applications submitted on the careers website land here in real time.</p>
                        </div>
                    <?php } else { ?>
                        <div class="crs-table-wrap">
                            <table class="crs-table" id="crs-applications-table">
                                <thead>
                                    <tr>
                                        <th style="width:34px"><input type="checkbox" id="crs-check-all"></th>
                                        <th>Candidate</th>
                                        <th>Applied for</th>
                                        <th>Experience</th>
                                        <th>Stage</th>
                                        <th>Rating</th>
                                        <th>Owner</th>
                                        <th>Applied</th>
                                        <th class="crs-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($applications as $application) { ?>
                                    <tr data-crs-row="<?= (int) $application->id; ?>">
                                        <td><input type="checkbox" class="crs-row-check" value="<?= (int) $application->id; ?>"></td>
                                        <td class="crs-td-main" style="min-width:220px">
                                            <button type="button" class="crs-star <?= (int) $application->is_starred === 1 ? 'on' : ''; ?>"
                                                    data-crs-star="<?= (int) $application->id; ?>" title="Star">
                                                <i class="fa fa-star"></i>
                                            </button>
                                            <a href="<?= admin_url('careers/application/' . $application->id); ?>"><?= html_escape($application->name); ?></a>
                                            <div class="crs-td-sub">
                                                <?= html_escape($application->email); ?><?= $application->phone ? ' · ' . html_escape($application->phone) : ''; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= html_escape((string) $application->job_title); ?>
                                            <div class="crs-td-sub"><?= html_escape($application->reference); ?></div>
                                        </td>
                                        <td class="crs-nowrap">
                                            <?= $application->total_experience !== null ? careers_trim_number($application->total_experience) . ' yrs' : '<span class="crs-muted">—</span>'; ?>
                                            <?php if ($application->current_company) { ?>
                                                <div class="crs-td-sub"><?= html_escape($application->current_company); ?></div>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <select class="crs-input crs-stage-select" data-crs-stage="<?= (int) $application->id; ?>"
                                                    style="min-width:130px;font-weight:700;color:<?= careers_stage_color($application->stage); ?>">
                                                <?php foreach ($stages as $key => $stage) { ?>
                                                    <option value="<?= $key; ?>" <?= $application->stage === $key ? 'selected' : ''; ?>><?= $stage['label']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td>
                                            <span class="crs-stars" data-crs-rating-for="<?= (int) $application->id; ?>">
                                                <?php for ($star = 1; $star <= 5; $star++) { ?>
                                                    <button type="button" class="crs-star <?= $star <= (int) $application->rating ? 'on' : ''; ?>" data-crs-rate="<?= $star; ?>">
                                                        <i class="fa fa-star"></i>
                                                    </button>
                                                <?php } ?>
                                            </span>
                                        </td>
                                        <td class="crs-nowrap">
                                            <?= $application->assigned_name ? html_escape($application->assigned_name) : '<span class="crs-muted">Unassigned</span>'; ?>
                                        </td>
                                        <td class="crs-nowrap crs-muted" title="<?= _dt($application->created_at); ?>">
                                            <?= careers_time_ago($application->created_at); ?>
                                        </td>
                                        <td class="crs-right crs-nowrap">
                                            <?php if ($application->resume_file) { ?>
                                                <a href="<?= admin_url('careers/resume/' . $application->id); ?>" target="_blank" rel="noopener"
                                                   class="crs-btn crs-btn-sm crs-btn-icon" title="Resume"><i class="fa fa-file-text-o"></i></a>
                                            <?php } ?>
                                            <a href="mailto:<?= html_escape($application->email); ?>" class="crs-btn crs-btn-sm crs-btn-icon" title="Email"><i class="fa fa-envelope-o"></i></a>
                                            <a href="<?= admin_url('careers/application/' . $application->id); ?>" class="crs-btn crs-btn-sm">Open</a>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="crs-pager">
                            <span class="crs-pager-info">Page <?= (int) $page; ?> of <?= (int) $pages; ?> · <?= (int) $total; ?> applications</span>
                            <?php if ($page > 1) { ?>
                                <a href="<?= $query(['page' => $page - 1]); ?>" class="crs-btn crs-btn-sm"><i class="fa fa-chevron-left"></i> Previous</a>
                            <?php } ?>
                            <?php if ($page < $pages) { ?>
                                <a href="<?= $query(['page' => $page + 1]); ?>" class="crs-btn crs-btn-sm">Next <i class="fa fa-chevron-right"></i></a>
                            <?php } ?>
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
