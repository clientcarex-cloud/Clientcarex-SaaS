<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'training'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $pct  = (int) $overall['percent'];
    $r    = 38;
    $circ = 2 * M_PI * $r;
    ?>

    <div class="shra-toolbar" style="justify-content:space-between">
        <h4 class="shra-title" style="margin:0">🎓 Self-Training <span class="thin">· your own sales course</span></h4>
        <div>
            <a href="<?php echo shra_home_url(); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
            <?php if ($can_edit) { ?><a href="<?php echo shra_training_url('manage'); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa fa-pen-to-square"></i> Edit course</a><?php } ?>
        </div>
    </div>

    <!-- ── Where you stand ─────────────────────────────────────── -->
    <div class="shra-tr-hero">
        <div class="shra-ring on-cream" style="width:104px;height:104px">
            <svg width="104" height="104" viewBox="0 0 86 86" aria-hidden="true">
                <circle class="trk" cx="43" cy="43" r="<?php echo $r; ?>" fill="none" stroke-width="7"></circle>
                <circle class="val" cx="43" cy="43" r="<?php echo $r; ?>" fill="none" stroke-width="7"
                        stroke-dasharray="<?php echo round($circ * $pct / 100, 1) . ' ' . round($circ, 1); ?>"></circle>
            </svg>
            <b style="font-size:22px"><?php echo $pct; ?>%</b>
        </div>
        <div class="txt">
            <h2><?php echo $cheer[0]; ?> <?php echo html_escape($cheer[1]); ?></h2>
            <p>
                <?php echo (int) $overall['modules_done']; ?> of <?php echo (int) $overall['modules']; ?> modules complete ·
                <?php echo (int) $overall['lessons_done']; ?>/<?php echo (int) $overall['lessons']; ?> lessons read ·
                <?php echo (int) $overall['quizzes_passed']; ?>/<?php echo (int) $overall['quizzes']; ?> quizzes passed
                <?php if ((int) $overall['streak'] > 1) { ?> · 🔥 <?php echo (int) $overall['streak']; ?>-day streak<?php } ?>
            </p>
            <?php if ($overall['next'] && $overall['next']['module']) { ?>
                <p style="margin-top:11px">
                    <a class="shra-btn shra-btn-primary shra-btn-sm" href="<?php echo shra_training_url('module/' . (int) $overall['next']['module']->id); ?>">
                        <i class="fa-solid fa-play"></i> <?php echo $overall['next']['started'] ? 'Continue' : 'Start'; ?>: <?php echo html_escape($overall['next']['module']->title); ?>
                    </a>
                </p>
            <?php } ?>
        </div>
    </div>

    <?php if (!count($modules)) { ?>
        <div class="shra-card"><div class="shra-empty"><i class="fa-solid fa-graduation-cap"></i>
            No training modules yet.<?php if ($can_edit) { ?><br><a href="<?php echo shra_training_url('manage'); ?>">Build the course</a> or <a href="<?php echo shra_training_url('restore_defaults'); ?>">install the default Stallion sales course</a>.<?php } ?>
        </div></div>
    <?php } else { ?>

    <!-- ── The modules ─────────────────────────────────────────── -->
    <div class="shra-tr-mods">
        <?php foreach ($modules as $m) {
            $s   = $stats[(int) $m->id] ?? ['percent' => 0, 'done' => 0, 'lessons' => 0, 'quiz' => 0, 'passed' => false, 'best' => 0, 'complete' => false, 'started' => false];
            $ls  = $lessons[(int) $m->id] ?? [];
        ?>
            <a class="shra-tr-mod <?php echo $s['complete'] ? 'is-done' : ''; ?>" href="<?php echo shra_training_url('module/' . (int) $m->id); ?>">
                <?php if ($s['complete']) { ?><span class="ribbon">✓ Done</span>
                <?php } elseif ($s['started']) { ?><span class="ribbon locked">In progress</span><?php } ?>
                <div class="top">
                    <span class="em"><?php echo $m->emoji; ?></span>
                    <div style="min-width:0">
                        <h4><?php echo html_escape($m->title); ?></h4>
                        <?php if ($m->tagline) { ?><p class="tag"><?php echo html_escape(strtr($m->tagline, ['{academy}' => get_option('shra_academy_name') ?: 'the academy'])); ?></p><?php } ?>
                    </div>
                </div>
                <div class="mid">
                    <div class="meta">
                        <span>📖 <?php echo count($ls); ?> lesson<?php echo count($ls) === 1 ? '' : 's'; ?></span>
                        <?php if ($s['quiz']) { ?><span>🧠 <?php echo (int) $s['quiz']; ?> questions</span><?php } ?>
                        <?php if ($s['best'] > 0) { ?><span style="color:<?php echo $s['passed'] ? 'var(--green)' : 'var(--red)'; ?>">🎯 best <?php echo (int) $s['best']; ?>%</span><?php } ?>
                        <span>⏱️ ~<?php echo max(3, count($ls) * 3); ?> min</span>
                    </div>
                    <div class="shra-progress"><span style="width:<?php echo max(2, (int) $s['percent']); ?>%"></span></div>
                </div>
                <div class="foot">
                    <span class="pct"><?php echo (int) $s['done']; ?>/<?php echo count($ls); ?> read<?php echo $s['quiz'] ? ($s['passed'] ? ' · quiz passed ✓' : ' · quiz pending') : ''; ?></span>
                    <span class="go"><?php echo $s['complete'] ? 'Review' : ($s['started'] ? 'Continue' : 'Start'); ?> <i class="fa fa-arrow-right"></i></span>
                </div>
            </a>
        <?php } ?>
    </div>

    <?php } ?>

    <div class="row shra-mt">
        <!-- ── Badges ─────────────────────────────────────────── -->
        <div class="col-md-<?php echo count($board) ? '7' : '12'; ?>">
            <div class="shra-card">
                <div class="shra-card-head"><h4>🏅 Your badges</h4><span class="thin"><?php echo count(array_filter($badges, function ($b) { return $b['earned']; })); ?> of <?php echo count($badges); ?> earned</span></div>
                <div class="shra-card-body">
                    <div class="shra-tr-badges">
                        <?php foreach ($badges as $b) { ?>
                            <div class="shra-tr-badge <?php echo $b['earned'] ? 'earned' : ''; ?>" title="<?php echo html_escape($b['hint']); ?>">
                                <div class="e"><?php echo $b['emoji']; ?></div>
                                <div class="n"><?php echo html_escape($b['name']); ?></div>
                                <div class="h"><?php echo $b['earned'] ? 'Earned' : html_escape($b['hint']); ?></div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <?php if (count($recent)) { ?>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4>🧠 Your last quiz attempts</h4></div>
                <div class="shra-table-wrap"><table class="shra-table"><tbody>
                    <?php $mnames = []; foreach ($modules as $m) { $mnames[(int) $m->id] = $m->emoji . ' ' . $m->title; } ?>
                    <?php foreach ($recent as $a) { ?>
                        <tr>
                            <td><span class="strong"><?php echo html_escape($mnames[(int) $a->module_id] ?? 'Module'); ?></span><span class="sub"><?php echo shra_datetime($a->created_at); ?></span></td>
                            <td class="num strong"><?php echo (int) $a->correct; ?>/<?php echo (int) $a->total; ?><span class="sub"><?php echo $a->passed ? '<span class="shra-badge shra-badge-green">Passed ' . (int) $a->percent . '%</span>' : '<span class="shra-badge shra-badge-red">' . (int) $a->percent . '%</span>'; ?></span></td>
                        </tr>
                    <?php } ?>
                </tbody></table></div>
            </div>
            <?php } ?>
        </div>

        <!-- ── Team board (managers only) ──────────────────────── -->
        <?php if (count($board)) { ?>
        <div class="col-md-5">
            <div class="shra-card">
                <div class="shra-card-head"><h4>🏆 Team progress</h4><span class="thin">who is match-fit</span></div>
                <div class="shra-table-wrap"><table class="shra-table">
                    <tbody>
                    <?php foreach ($board as $i => $b) { ?>
                        <tr<?php echo (int) $b->staffid === (int) get_staff_user_id() ? ' style="background:var(--cream-2)"' : ''; ?>>
                            <td style="width:34px" class="num"><?php echo $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '<span class="sub">' . ($i + 1) . '</span>')); ?></td>
                            <td>
                                <span class="strong"><?php echo html_escape($b->name); ?><?php echo (int) $b->staffid === (int) get_staff_user_id() ? ' <span class="shra-badge shra-badge-gold">You</span>' : ''; ?></span>
                                <span class="sub"><?php echo (int) $b->modules_done; ?>/<?php echo (int) $b->modules; ?> modules · <?php echo (int) $b->quizzes; ?> quizzes<?php echo $b->streak > 1 ? ' · 🔥' . (int) $b->streak : ''; ?></span>
                                <div class="shra-progress" style="margin-top:5px"><span style="width:<?php echo max(2, (int) $b->percent); ?>%"></span></div>
                            </td>
                            <td class="num strong" style="width:52px"><?php echo (int) $b->percent; ?>%</td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table></div>
            </div>
        </div>
        <?php } ?>
    </div>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
