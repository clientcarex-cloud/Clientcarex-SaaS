<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * The Self-Training card. Rendered by shra_training_card() so it can be dropped
 * on to any home screen with a single echo.
 *
 * @var array  $overall  Shra_training_model::overall()
 * @var array  $modules  active modules
 * @var array  $stats    module_id => per-user stats
 * @var array  $cheer    [emoji, line]
 * @var array  $badges
 */
$pct   = (int) $overall['percent'];
$done  = $pct >= 100;
$next  = $overall['next'];
$r     = 38;
$circ  = 2 * M_PI * $r;
$earned = array_values(array_filter($badges, function ($b) { return $b['earned']; }));
?>
<div class="shra-tr-card">
    <div class="shra-tr-grid">
        <div class="shra-tr-main">
            <div class="shra-tr-eyebrow"><i class="fa-solid fa-graduation-cap"></i> Self-Training<?php if ((int) $overall['streak'] > 1) { ?> <span style="color:#f4b23c">· 🔥 <?php echo (int) $overall['streak']; ?>-day streak</span><?php } ?></div>
            <h3><?php echo $cheer[0]; ?> <?php echo $done ? 'You are a certified Stallion pitcher' : 'Become a Stallion sales pro'; ?></h3>
            <p class="shra-tr-cheer"><?php echo html_escape($cheer[1]); ?></p>

            <div class="shra-tr-bar"><span style="width:<?php echo max(2, $pct); ?>%"></span></div>

            <div class="shra-tr-facts">
                <span class="shra-tr-fact">📚 <b><?php echo (int) $overall['modules_done']; ?></b>/<?php echo (int) $overall['modules']; ?> modules</span>
                <span class="shra-tr-fact">📖 <b><?php echo (int) $overall['lessons_done']; ?></b>/<?php echo (int) $overall['lessons']; ?> lessons</span>
                <span class="shra-tr-fact">🧠 <b><?php echo (int) $overall['quizzes_passed']; ?></b>/<?php echo (int) $overall['quizzes']; ?> quizzes passed</span>
                <?php if (count($earned)) { ?>
                    <span class="shra-tr-fact"><?php echo implode(' ', array_map(function ($b) { return $b['emoji']; }, array_slice($earned, -4))); ?> <b><?php echo count($earned); ?></b> badges</span>
                <?php } ?>
            </div>

            <?php if (count($modules)) { ?>
            <div class="shra-tr-pips">
                <?php foreach ($modules as $m) {
                    $s = $stats[(int) $m->id] ?? ['complete' => false, 'started' => false, 'percent' => 0];
                    $cls = $s['complete'] ? 'done' : ($s['started'] ? 'doing' : '');
                ?>
                    <a class="shra-tr-pip <?php echo $cls; ?>" href="<?php echo shra_training_url('module/' . (int) $m->id); ?>" title="<?php echo html_escape($m->title . ' — ' . (int) $s['percent'] . '%'); ?>">
                        <?php echo $m->emoji; ?> <?php echo html_escape($m->title); ?><?php echo $s['complete'] ? ' ✓' : ''; ?>
                    </a>
                <?php } ?>
            </div>
            <?php } ?>
        </div>

        <div class="shra-tr-side">
            <div class="shra-ring" title="<?php echo $pct; ?>% of the course complete">
                <svg width="86" height="86" viewBox="0 0 86 86" aria-hidden="true">
                    <circle class="trk" cx="43" cy="43" r="<?php echo $r; ?>" fill="none" stroke-width="7"></circle>
                    <circle class="val" cx="43" cy="43" r="<?php echo $r; ?>" fill="none" stroke-width="7"
                            stroke-dasharray="<?php echo round($circ * $pct / 100, 1) . ' ' . round($circ, 1); ?>"></circle>
                </svg>
                <b><?php echo $pct; ?>%</b>
            </div>
            <?php if ($done) { ?>
                <a class="shra-tr-cta" href="<?php echo shra_training_url(); ?>"><i class="fa-solid fa-trophy"></i> Review the course</a>
            <?php } elseif ($next && $next['module']) { ?>
                <a class="shra-tr-cta" href="<?php echo shra_training_url('module/' . (int) $next['module']->id); ?>">
                    <i class="fa-solid fa-play"></i> <?php echo $next['started'] ? 'Continue' : 'Start'; ?>: <?php echo html_escape($next['module']->title); ?>
                </a>
            <?php } else { ?>
                <a class="shra-tr-cta" href="<?php echo shra_training_url(); ?>"><i class="fa-solid fa-play"></i> Open training</a>
            <?php } ?>
            <a class="shra-tr-cta ghost" href="<?php echo shra_training_url(); ?>">All modules</a>
        </div>
    </div>
</div>
