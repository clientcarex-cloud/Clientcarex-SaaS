<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php
            $active = 'pipeline';
            include __DIR__ . '/_nav.php';

            $stages = careers_stages();
            ?>

            <form method="get" action="<?= admin_url('careers/pipeline'); ?>" class="crs-filters">
                <select name="job" class="crs-input" onchange="this.form.submit()" style="min-width:260px">
                    <option value="">All openings</option>
                    <?php foreach ($jobs as $job) { ?>
                        <option value="<?= (int) $job->id; ?>" <?= (int) $job_id === (int) $job->id ? 'selected' : ''; ?>>
                            <?= html_escape($job->title); ?> (<?= (int) $job->applications_count; ?>)
                        </option>
                    <?php } ?>
                </select>
                <span class="crs-muted"><i class="fa fa-hand-pointer-o"></i> Drag a card between columns to move the candidate.</span>
                <div style="margin-left:auto" class="crs-flex">
                    <a href="<?= admin_url('careers/applications' . ($job_id ? '?job=' . (int) $job_id : '')); ?>" class="crs-btn crs-btn-sm">
                        <i class="fa fa-list"></i> Table view
                    </a>
                </div>
            </form>

            <div class="crs-board" id="crs-board" data-crs-job="<?= (int) $job_id; ?>">
                <?php foreach (careers_board_stages() as $stage_key) {
                    $stage = $stages[$stage_key];
                    $rows  = $columns[$stage_key]; ?>
                    <div class="crs-col" data-crs-col="<?= $stage_key; ?>">
                        <div class="crs-col-head">
                            <span class="crs-dot" style="background:<?= $stage['color']; ?>"></span>
                            <?= $stage['label']; ?>
                            <span class="crs-col-count" data-crs-count="<?= $stage_key; ?>"><?= (int) ($counts[$stage_key] ?? 0); ?></span>
                        </div>
                        <div class="crs-col-body">
                            <?php foreach ($rows as $application) { ?>
                                <div class="crs-kcard" draggable="true" data-crs-card="<?= (int) $application->id; ?>">
                                    <div class="crs-kcard-name">
                                        <a href="<?= admin_url('careers/application/' . $application->id); ?>"><?= html_escape($application->name); ?></a>
                                    </div>
                                    <div class="crs-kcard-role"><?= html_escape((string) $application->job_title); ?></div>
                                    <div class="crs-kcard-meta">
                                        <?php if ((int) $application->rating > 0) { ?>
                                            <span style="color:#f59e0b"><?= str_repeat('★', (int) $application->rating); ?></span>
                                        <?php } ?>
                                        <?php if ($application->total_experience !== null) { ?>
                                            <span><?= careers_trim_number($application->total_experience); ?> yrs</span>
                                        <?php } ?>
                                        <?php if ($application->resume_file) { ?>
                                            <a href="<?= admin_url('careers/resume/' . $application->id); ?>" target="_blank" rel="noopener" title="Resume"><i class="fa fa-file-text-o"></i></a>
                                        <?php } ?>
                                        <span style="margin-left:auto"><?= careers_time_ago($application->created_at); ?></span>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if (count($rows) >= $limit && (int) ($counts[$stage_key] ?? 0) > $limit) { ?>
                                <div class="crs-kcard-more">
                                    Showing the <?= (int) $limit; ?> most recent of <?= (int) $counts[$stage_key]; ?> —
                                    <a href="<?= admin_url('careers/applications?stage=' . $stage_key . ($job_id ? '&job=' . (int) $job_id : '')); ?>">open the table</a>
                                </div>
                            <?php } elseif (empty($rows)) { ?>
                                <div class="crs-kcard-more">Nothing here yet</div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <p class="crs-muted" style="margin-top:10px;font-size:12.5px">
                Rejected, on-hold and withdrawn candidates stay off the board —
                find them in the <a href="<?= admin_url('careers/applications'); ?>">applications table</a>.
            </p>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
