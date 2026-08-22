<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php $active = 'jobs'; include __DIR__ . '/_nav.php'; ?>

            <?php
            $statuses = careers_job_statuses();
            $types    = careers_job_types();

            // Filters travel in the query string, so a filtered list is a URL a
            // recruiter can bookmark or share.
            $query = static function ($overrides) use ($filters) {
                $params = array_filter(array_merge($filters, $overrides), static function ($v) {
                    return $v !== '' && $v !== null;
                });

                return admin_url('careers/jobs' . (empty($params) ? '' : '?' . http_build_query($params)));
            };
            ?>

            <!-- ── Status tabs ── -->
            <div class="crs-tabs" style="margin-bottom:14px">
                <a href="<?= $query(['status' => '', 'page' => '']); ?>" class="crs-tab <?= empty($filters['status']) ? 'active' : ''; ?>">
                    All <span class="crs-tab-count"><?= (int) $counts['all']; ?></span>
                </a>
                <?php foreach ($statuses as $key => $status) { ?>
                    <a href="<?= $query(['status' => $key, 'page' => '']); ?>" class="crs-tab <?= ($filters['status'] ?? '') === $key ? 'active' : ''; ?>">
                        <?= $status['label']; ?> <span class="crs-tab-count"><?= (int) ($counts[$key] ?? 0); ?></span>
                    </a>
                <?php } ?>
            </div>

            <!-- ── Filters ── -->
            <form method="get" action="<?= admin_url('careers/jobs'); ?>" class="crs-filters">
                <?php if (!empty($filters['status'])) { ?>
                    <input type="hidden" name="status" value="<?= html_escape($filters['status']); ?>">
                <?php } ?>
                <input type="search" name="search" class="crs-input crs-search" placeholder="Search title, reference, skills…"
                       value="<?= html_escape((string) $filters['search']); ?>">

                <select name="type" class="crs-input" onchange="this.form.submit()">
                    <option value="">All types</option>
                    <?php foreach ($types as $key => $type) { ?>
                        <option value="<?= $key; ?>" <?= ($filters['type'] ?? '') === $key ? 'selected' : ''; ?>><?= $type['label']; ?></option>
                    <?php } ?>
                </select>

                <select name="department" class="crs-input" onchange="this.form.submit()">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $department) { ?>
                        <option value="<?= (int) $department->id; ?>" <?= (int) ($filters['department'] ?? 0) === (int) $department->id ? 'selected' : ''; ?>>
                            <?= html_escape($department->name); ?>
                        </option>
                    <?php } ?>
                </select>

                <select name="location" class="crs-input" onchange="this.form.submit()">
                    <option value="">All locations</option>
                    <?php foreach ($locations as $location) { ?>
                        <option value="<?= (int) $location->id; ?>" <?= (int) ($filters['location'] ?? 0) === (int) $location->id ? 'selected' : ''; ?>>
                            <?= html_escape($location->name); ?>
                        </option>
                    <?php } ?>
                </select>

                <select name="work_mode" class="crs-input" onchange="this.form.submit()">
                    <option value="">Any mode</option>
                    <?php foreach (careers_work_modes() as $key => $mode) { ?>
                        <option value="<?= $key; ?>" <?= ($filters['work_mode'] ?? '') === $key ? 'selected' : ''; ?>><?= $mode['label']; ?></option>
                    <?php } ?>
                </select>

                <span class="crs-filters-end">
                    <button type="submit" class="crs-btn"><i class="fa fa-filter"></i> Filter</button>
                    <a href="<?= admin_url('careers/jobs'); ?>" class="crs-btn crs-btn-icon" title="Clear"><i class="fa fa-times"></i></a>
                </span>
            </form>

            <!-- ── List ── -->
            <div class="crs-card">
                <div class="crs-card-body crs-tight">
                    <?php if (empty($jobs)) { ?>
                        <div class="crs-empty">
                            <i class="fa-solid fa-briefcase"></i>
                            <h4>No openings match this view</h4>
                            <p>Post a job, internship or apprenticeship and it appears on the careers website the moment you publish it.</p>
                            <?php if (careers_can('create')) { ?>
                                <a href="<?= admin_url('careers/job'); ?>" class="crs-btn crs-btn-primary" style="margin-top:14px"><i class="fa fa-plus"></i> New opening</a>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="crs-table-wrap">
                            <table class="crs-table">
                                <thead>
                                    <tr>
                                        <th>Opening</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th class="crs-right">Applications</th>
                                        <th class="crs-right">Views</th>
                                        <th>Deadline</th>
                                        <th class="crs-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($jobs as $job) {
                                    $type = careers_job_type_meta($job->job_type); ?>
                                    <tr>
                                        <td class="crs-td-main" style="min-width:250px">
                                            <a href="<?= admin_url('careers/job/' . $job->id); ?>"><?= html_escape($job->title); ?></a>
                                            <?php if ((int) $job->is_featured === 1) { ?>
                                                <i class="fa fa-star" style="color:#f59e0b" title="Featured"></i>
                                            <?php } ?>
                                            <?php if ((int) $job->is_urgent === 1) { ?>
                                                <span class="crs-badge" style="background:#fee2e2;color:#b91c1c">Urgent</span>
                                            <?php } ?>
                                            <div class="crs-td-sub">
                                                <?= html_escape($job->reference); ?>
                                                <?php if (!empty($job->department_name)) { ?> · <?= html_escape($job->department_name); ?><?php } ?>
                                                · <?= (int) $job->openings; ?> opening<?= (int) $job->openings === 1 ? '' : 's'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="crs-badge" style="background:<?= $type['color']; ?>18;color:<?= $type['color']; ?>">
                                                <i class="<?= $type['icon']; ?>"></i> <?= $type['label']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= html_escape(careers_location_text($job)) ?: '<span class="crs-muted">—</span>'; ?>
                                            <div class="crs-td-sub"><?= careers_work_mode_label($job->work_mode); ?></div>
                                        </td>
                                        <td>
                                            <span class="crs-badge" style="background:<?= careers_job_status_color($job->status); ?>18;color:<?= careers_job_status_color($job->status); ?>">
                                                <?= careers_job_status_label($job->status); ?>
                                            </span>
                                        </td>
                                        <td class="crs-right">
                                            <a href="<?= admin_url('careers/applications?job=' . $job->id); ?>" class="crs-chip">
                                                <?= (int) $job->applications_count; ?>
                                                <?php if ((int) $job->new_count > 0) { ?>
                                                    <span style="color:#b45309">+<?= (int) $job->new_count; ?> new</span>
                                                <?php } ?>
                                            </a>
                                        </td>
                                        <td class="crs-right crs-muted"><?= (int) $job->views; ?></td>
                                        <td class="crs-nowrap">
                                            <?php if (!empty($job->deadline) && $job->deadline !== '0000-00-00') {
                                                $overdue = $job->deadline < date('Y-m-d'); ?>
                                                <span style="<?= $overdue ? 'color:#dc2626;font-weight:700' : ''; ?>"><?= _d($job->deadline); ?></span>
                                            <?php } else { ?>
                                                <span class="crs-muted">Open ended</span>
                                            <?php } ?>
                                        </td>
                                        <td class="crs-right crs-nowrap">
                                            <?php if (careers_can('edit')) { ?>
                                                <?php if ($job->status === 'published') { ?>
                                                    <a href="<?= admin_url('careers/job_status/' . $job->id . '/paused'); ?>" class="crs-btn crs-btn-sm crs-btn-icon" title="Pause"><i class="fa fa-pause"></i></a>
                                                <?php } else { ?>
                                                    <a href="<?= admin_url('careers/job_status/' . $job->id . '/published'); ?>" class="crs-btn crs-btn-sm crs-btn-icon" title="Publish"><i class="fa fa-paper-plane"></i></a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($job->status === 'published') { ?>
                                                <button type="button" class="crs-btn crs-btn-sm crs-btn-icon" title="Copy public link"
                                                        data-crs-copy="<?= html_escape(careers_public_job_url($job)); ?>"><i class="fa fa-link"></i></button>
                                            <?php } ?>
                                            <a href="<?= admin_url('careers/job/' . $job->id); ?>" class="crs-btn crs-btn-sm crs-btn-icon" title="Edit"><i class="fa fa-pencil"></i></a>
                                            <?php if (careers_can('create')) { ?>
                                                <a href="<?= admin_url('careers/duplicate_job/' . $job->id); ?>" class="crs-btn crs-btn-sm crs-btn-icon" title="Duplicate"><i class="fa fa-copy"></i></a>
                                            <?php } ?>
                                            <?php if (careers_can('delete')) { ?>
                                                <a href="<?= admin_url('careers/delete_job/' . $job->id); ?>" class="crs-btn crs-btn-sm crs-btn-icon crs-btn-danger"
                                                   title="Delete" data-crs-confirm="Delete this opening and every application received for it? This cannot be undone."><i class="fa fa-trash"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($pages > 1) { ?>
                            <div class="crs-pager">
                                <span class="crs-pager-info">Page <?= (int) $page; ?> of <?= (int) $pages; ?> · <?= (int) $total; ?> openings</span>
                                <?php if ($page > 1) { ?>
                                    <a href="<?= $query(['page' => $page - 1]); ?>" class="crs-btn crs-btn-sm"><i class="fa fa-chevron-left"></i> Previous</a>
                                <?php } ?>
                                <?php if ($page < $pages) { ?>
                                    <a href="<?= $query(['page' => $page + 1]); ?>" class="crs-btn crs-btn-sm">Next <i class="fa fa-chevron-right"></i></a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
