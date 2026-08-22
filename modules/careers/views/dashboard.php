<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php $active = 'dashboard'; include __DIR__ . '/_nav.php'; ?>

            <?php
            $kpi    = $dashboard['kpi'];
            $days   = $dashboard['days'];
            $series = $dashboard['series'];
            $stages = careers_stages();

            // One scale for both series so the bars stay comparable day to day.
            $peak = 1;
            foreach ($series as $point) {
                $peak = max($peak, $point['applications'], $point['views']);
            }
            ?>

            <!-- ── Range switch ── -->
            <div class="crs-flex-between" style="margin-bottom:14px">
                <div class="crs-legend">
                    <span><i class="fa fa-circle" style="color:var(--crs-primary)"></i> Applications</span>
                    <span><i class="fa fa-circle" style="color:#cbd5e1"></i> Page views</span>
                </div>
                <div class="crs-flex">
                    <?php foreach ([7, 30, 90] as $range) { ?>
                        <a href="<?= admin_url('careers?days=' . $range); ?>"
                           class="crs-btn crs-btn-sm <?= $days === $range ? 'active' : ''; ?>"><?= $range; ?>d</a>
                    <?php } ?>
                </div>
            </div>

            <!-- ── KPI strip ── -->
            <div class="crs-kpis">
                <a class="crs-kpi" href="<?= admin_url('careers/jobs?status=published'); ?>">
                    <div class="crs-kpi-value"><?= (int) $kpi['published']; ?></div>
                    <div class="crs-kpi-label">Live openings</div>
                    <div class="crs-kpi-sub"><?= (int) $kpi['draft']; ?> draft · <?= (int) $kpi['total_jobs']; ?> total</div>
                    <i class="fa-solid fa-briefcase crs-kpi-icon"></i>
                </a>
                <a class="crs-kpi <?= $kpi['new_apps'] ? 'crs-kpi-warn' : ''; ?>" href="<?= admin_url('careers/applications?stage=new'); ?>">
                    <div class="crs-kpi-value" data-crs-new-count><?= (int) $kpi['new_apps']; ?></div>
                    <div class="crs-kpi-label">Awaiting review</div>
                    <div class="crs-kpi-sub"><?= (int) $kpi['total_apps']; ?> applications all time</div>
                    <i class="fa-solid fa-inbox crs-kpi-icon"></i>
                </a>
                <div class="crs-kpi">
                    <div class="crs-kpi-value"><?= (int) $kpi['period_apps']; ?></div>
                    <div class="crs-kpi-label">Applications · <?= (int) $days; ?>d</div>
                    <div class="crs-kpi-sub"><?= (int) $kpi['views']; ?> job page views</div>
                    <i class="fa-solid fa-file-lines crs-kpi-icon"></i>
                </div>
                <div class="crs-kpi">
                    <div class="crs-kpi-value"><?= $kpi['conversion'] !== null ? $kpi['conversion'] . '%' : '—'; ?></div>
                    <div class="crs-kpi-label">View → apply</div>
                    <div class="crs-kpi-sub">Share of visitors who applied</div>
                    <i class="fa-solid fa-percent crs-kpi-icon"></i>
                </div>
                <a class="crs-kpi" href="<?= admin_url('careers/interviews'); ?>">
                    <div class="crs-kpi-value"><?= (int) $kpi['upcoming_interviews']; ?></div>
                    <div class="crs-kpi-label">Interviews ahead</div>
                    <div class="crs-kpi-sub"><?= (int) $kpi['interviews']; ?> at interview stage</div>
                    <i class="fa-regular fa-calendar-check crs-kpi-icon"></i>
                </a>
                <a class="crs-kpi crs-kpi-good" href="<?= admin_url('careers/applications?stage=hired'); ?>">
                    <div class="crs-kpi-value"><?= (int) $kpi['hired']; ?></div>
                    <div class="crs-kpi-label">Hired</div>
                    <div class="crs-kpi-sub"><?= $kpi['time_to_hire'] !== null ? $kpi['time_to_hire'] . ' days avg. to hire' : 'No hires in range'; ?></div>
                    <i class="fa-solid fa-user-check crs-kpi-icon"></i>
                </a>
            </div>

            <div class="crs-split">
                <div>
                    <!-- ── Trend ── -->
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Applications &amp; views — last <?= (int) $days; ?> days</h3>
                        </div>
                        <div class="crs-card-body">
                            <?php if ($kpi['period_apps'] === 0 && $kpi['views'] === 0) { ?>
                                <div class="crs-empty">
                                    <i class="fa-solid fa-chart-column"></i>
                                    <h4>Nothing to chart yet</h4>
                                    <p>Publish an opening and the traffic it earns will show up here.</p>
                                </div>
                            <?php } else { ?>
                                <div class="crs-chart">
                                    <?php foreach ($series as $point) { ?>
                                        <div class="crs-bar-col">
                                            <div class="crs-bar-tip">
                                                <?= _d($point['date']); ?> — <?= (int) $point['applications']; ?> applied, <?= (int) $point['views']; ?> views
                                            </div>
                                            <div class="crs-bar crs-bar-views" style="height:<?= max(1, round(($point['views'] / $peak) * 62)); ?>%"></div>
                                            <div class="crs-bar" style="height:<?= max(1, round(($point['applications'] / $peak) * 62)); ?>%"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                                <div class="crs-chart-axis">
                                    <?php foreach ($series as $index => $point) {
                                        $step = max(1, (int) floor(count($series) / 10)); ?>
                                        <span><?= $index % $step === 0 ? date('d M', strtotime($point['date'])) : ''; ?></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- ── Recent applications ── -->
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Latest applications</h3>
                            <div class="crs-card-actions">
                                <a href="<?= admin_url('careers/applications'); ?>" class="crs-btn crs-btn-sm">View all</a>
                            </div>
                        </div>
                        <div class="crs-card-body crs-tight">
                            <?php if (empty($dashboard['recent'])) { ?>
                                <div class="crs-empty">
                                    <i class="fa-solid fa-inbox"></i>
                                    <h4>No applications yet</h4>
                                    <p>They will land here the moment someone applies on the careers page.</p>
                                </div>
                            <?php } else { ?>
                                <div class="crs-table-wrap">
                                    <table class="crs-table">
                                        <thead>
                                            <tr><th>Candidate</th><th>Applied for</th><th>Stage</th><th>Received</th></tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($dashboard['recent'] as $application) { ?>
                                            <tr>
                                                <td class="crs-td-main">
                                                    <a href="<?= admin_url('careers/application/' . $application->id); ?>"><?= html_escape($application->name); ?></a>
                                                    <div class="crs-td-sub"><?= html_escape($application->email); ?></div>
                                                </td>
                                                <td><?= html_escape((string) $application->job_title); ?>
                                                    <div class="crs-td-sub"><?= html_escape((string) $application->department_name); ?></div>
                                                </td>
                                                <td>
                                                    <span class="crs-badge" style="background:<?= careers_stage_color($application->stage); ?>18;color:<?= careers_stage_color($application->stage); ?>">
                                                        <?= careers_stage_label($application->stage); ?>
                                                    </span>
                                                </td>
                                                <td class="crs-nowrap crs-muted"><?= careers_time_ago($application->created_at); ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div>
                    <!-- ── Pipeline funnel ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Pipeline</h3></div>
                        <div class="crs-card-body">
                            <?php
                            $funnel_peak = 1;
                            foreach ($dashboard['stages'] as $count) {
                                $funnel_peak = max($funnel_peak, (int) $count);
                            }
                            ?>
                            <div class="crs-funnel">
                                <?php foreach ($stages as $key => $stage) {
                                    $count = (int) ($dashboard['stages'][$key] ?? 0); ?>
                                    <div class="crs-funnel-row">
                                        <div class="crs-funnel-label"><?= $stage['label']; ?></div>
                                        <div class="crs-funnel-track">
                                            <div class="crs-funnel-fill" style="width:<?= round(($count / $funnel_peak) * 100); ?>%;background:<?= $stage['color']; ?>"></div>
                                        </div>
                                        <div class="crs-funnel-value"><?= $count; ?></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- ── Upcoming interviews ── -->
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Next interviews</h3>
                            <div class="crs-card-actions">
                                <a href="<?= admin_url('careers/interviews'); ?>" class="crs-btn crs-btn-sm">All</a>
                            </div>
                        </div>
                        <div class="crs-card-body">
                            <?php if (empty($dashboard['upcoming'])) { ?>
                                <p class="crs-muted" style="margin:0">Nothing scheduled.</p>
                            <?php } else { ?>
                                <?php foreach (array_slice($dashboard['upcoming'], 0, 6) as $interview) { ?>
                                    <div class="crs-flex" style="padding:9px 0;border-bottom:1px solid var(--crs-line)">
                                        <div style="flex:1;min-width:0">
                                            <div style="font-weight:700;color:var(--crs-ink);font-size:13px">
                                                <a href="<?= admin_url('careers/application/' . $interview->application_id); ?>" style="color:inherit"><?= html_escape((string) $interview->candidate_name); ?></a>
                                            </div>
                                            <div class="crs-td-sub"><?= html_escape((string) $interview->job_title); ?> · <?= html_escape($interview->title); ?></div>
                                        </div>
                                        <div class="crs-right crs-nowrap" style="font-size:12px">
                                            <div style="font-weight:700;color:var(--crs-ink)"><?= date('d M', strtotime($interview->scheduled_at)); ?></div>
                                            <div class="crs-muted"><?= date('H:i', strtotime($interview->scheduled_at)); ?></div>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- ── Top openings ── -->
                    <div class="crs-card">
                        <div class="crs-card-head"><h3>Most applied for</h3></div>
                        <div class="crs-card-body">
                            <?php if (empty($dashboard['top_jobs'])) { ?>
                                <p class="crs-muted" style="margin:0">No live openings yet.</p>
                            <?php } else { ?>
                                <?php foreach ($dashboard['top_jobs'] as $job) { ?>
                                    <div class="crs-flex" style="padding:9px 0;border-bottom:1px solid var(--crs-line)">
                                        <div style="flex:1;min-width:0">
                                            <div style="font-weight:700;font-size:13px">
                                                <a href="<?= admin_url('careers/job/' . $job->id); ?>" style="color:var(--crs-ink)"><?= html_escape($job->title); ?></a>
                                            </div>
                                            <div class="crs-td-sub"><?= careers_job_type_label($job->job_type); ?> · <?= (int) $job->views; ?> views</div>
                                        </div>
                                        <a href="<?= admin_url('careers/applications?job=' . $job->id); ?>" class="crs-chip"><?= (int) $job->applications_count; ?></a>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- ── Sources ── -->
                    <?php if (!empty($dashboard['sources'])) { ?>
                        <div class="crs-card">
                            <div class="crs-card-head"><h3>Where they came from</h3></div>
                            <div class="crs-card-body">
                                <?php foreach ($dashboard['sources'] as $source) { ?>
                                    <div class="crs-flex-between" style="padding:6px 0">
                                        <span class="crs-chip"><?= html_escape($source->source ?: 'unknown'); ?></span>
                                        <strong><?= (int) $source->c; ?></strong>
                                    </div>
                                <?php } ?>
                            </div>
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
