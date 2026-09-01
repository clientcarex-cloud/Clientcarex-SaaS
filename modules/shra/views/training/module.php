<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'training'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $total    = count($lessons);
    $done_n   = count(array_filter($lessons, function ($l) use ($done) { return isset($done[(int) $l->id]); }));
    $has_quiz = $questions > 0;
    $best     = (int) $stats['best'];
    ?>

    <div class="shra-toolbar" style="justify-content:space-between">
        <h4 class="shra-title" style="margin:0"><?php echo $module->emoji; ?> <?php echo html_escape($module->title); ?></h4>
        <div>
            <a href="<?php echo shra_training_url(); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-arrow-left"></i> All modules</a>
            <?php if ($stats['started']) { ?>
                <a href="<?php echo shra_training_url('reset/' . (int) $module->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-confirm="Clear your progress on this module and start again?"><i class="fa fa-rotate-left"></i> Start over</a>
            <?php } ?>
            <?php if ($can_edit) { ?><a href="<?php echo shra_training_url('manage?module=' . (int) $module->id); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa fa-pen-to-square"></i> Edit</a><?php } ?>
        </div>
    </div>

    <div class="shra-tr-read" id="shra-tr" data-module="<?php echo (int) $module->id; ?>" data-pass="<?php echo (int) $module->pass_percent; ?>">

        <!-- ── Lesson list ─────────────────────────────────────── -->
        <div class="shra-tr-toc">
            <div class="shra-card">
                <div class="shra-card-head" style="padding:12px 16px">
                    <h4 style="font-size:17px">Lessons</h4>
                    <span class="thin"><span id="shra-tr-count"><?php echo $done_n; ?></span>/<?php echo $total; ?></span>
                </div>
                <div class="shra-card-body" style="padding:10px 14px 4px">
                    <div class="shra-progress"><span id="shra-tr-bar" style="width:<?php echo $total ? max(2, round($done_n / $total * 100)) : 2; ?>%"></span></div>
                </div>
                <div class="list">
                    <?php foreach ($lessons as $i => $l) { $ok = isset($done[(int) $l->id]); ?>
                        <a href="#" class="shra-tr-step <?php echo $ok ? 'ok' : ''; ?> <?php echo $i === 0 ? 'on' : ''; ?>"
                           data-step="<?php echo $i; ?>" data-lesson="<?php echo (int) $l->id; ?>">
                            <span class="n"><?php echo $ok ? '<i class="fa fa-check"></i>' : ($i + 1); ?></span>
                            <span class="t"><?php echo $l->emoji; ?> <?php echo html_escape($l->title); ?></span>
                        </a>
                    <?php } ?>
                    <?php if ($has_quiz) { ?>
                        <div class="shra-tr-quizstep">
                            <a href="#" class="shra-tr-step" id="shra-tr-quizstep" data-step="quiz">
                                <span class="n">🧠</span>
                                <span class="t">Quiz — <?php echo (int) $questions; ?> questions<?php if ($best > 0) { ?><br><small style="color:<?php echo $stats['passed'] ? 'var(--green)' : 'var(--red)'; ?>">best <?php echo $best; ?>% · pass mark <?php echo (int) $module->pass_percent; ?>%</small><?php } ?></span>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <?php if (count($modules) > 1) { ?>
            <div class="shra-card shra-mt">
                <div class="shra-card-head" style="padding:12px 16px"><h4 style="font-size:17px">Other modules</h4></div>
                <div class="list">
                    <?php foreach ($modules as $om) { if ((int) $om->id === (int) $module->id) { continue; }
                        $os = $all_stats[(int) $om->id] ?? ['complete' => false, 'percent' => 0]; ?>
                        <a href="<?php echo shra_training_url('module/' . (int) $om->id); ?>" class="shra-tr-step <?php echo $os['complete'] ? 'ok' : ''; ?>">
                            <span class="n"><?php echo $os['complete'] ? '<i class="fa fa-check"></i>' : (int) $os['percent']; ?></span>
                            <span class="t"><?php echo $om->emoji; ?> <?php echo html_escape($om->title); ?></span>
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- ── Reader ──────────────────────────────────────────── -->
        <div class="shra-tr-page">
            <?php foreach ($lessons as $i => $l) { $ok = isset($done[(int) $l->id]); ?>
                <div class="lesson <?php echo $i === 0 ? 'on' : ''; ?>" data-pane="<?php echo $i; ?>" data-lesson="<?php echo (int) $l->id; ?>">
                    <div class="shra-tr-lead">
                        <span class="em"><?php echo $l->emoji; ?></span>
                        <div>
                            <div class="of">Lesson <?php echo $i + 1; ?> of <?php echo $total; ?></div>
                            <h3><?php echo html_escape($l->title); ?></h3>
                        </div>
                    </div>

                    <?php if ($i === 0 && $module->intro) { ?>
                        <div class="shra-tr-call tip" style="margin-top:14px"><b>What this module gives you</b><br><?php echo html_escape($module->intro); ?></div>
                    <?php } ?>

                    <div class="shra-tr-body"><?php echo shra_training_render($l->body); ?></div>

                    <?php if ($l->takeaway) { ?>
                        <div class="shra-tr-key">
                            <span class="e">🔑</span>
                            <div><div class="k">Remember this</div><div class="v"><?php echo html_escape($l->takeaway); ?></div></div>
                        </div>
                    <?php } ?>

                    <div class="shra-tr-nav">
                        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-tr-prev <?php echo $i === 0 ? 'disabled' : ''; ?>><i class="fa fa-arrow-left"></i> Previous</button>
                        <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
                            <button type="button" class="shra-btn shra-btn-outline shra-btn-sm shra-tr-mark <?php echo $ok ? 'is-done' : ''; ?>" data-lesson="<?php echo (int) $l->id; ?>">
                                <i class="fa <?php echo $ok ? 'fa-circle-check' : 'fa-regular fa-circle'; ?>"></i> <span><?php echo $ok ? 'Completed' : 'Mark as read'; ?></span>
                            </button>
                            <button type="button" class="shra-btn shra-btn-primary shra-btn-sm" data-tr-next>
                                <?php echo $i === $total - 1 ? ($has_quiz ? 'Take the quiz 🧠' : 'Finish') : 'Next'; ?> <i class="fa fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (!$total) { ?>
                <div class="shra-empty"><i class="fa-solid fa-book-open"></i>This module has no lessons yet.</div>
            <?php } ?>

            <!-- ── Quiz ────────────────────────────────────────── -->
            <?php if ($has_quiz) { ?>
            <div class="lesson" data-pane="quiz">
                <div class="shra-quiz-wrap" id="shra-quiz">

                    <div id="shra-quiz-intro">
                        <div style="text-align:center;padding:8px 0 4px">
                            <div style="font-size:44px;line-height:1">🧠</div>
                            <h3 style="font-family:'Cormorant Garamond',Georgia,serif;font-size:27px;font-weight:700;margin:10px 0 6px">Ready for the quiz?</h3>
                            <p style="color:var(--muted);font-size:13.5px;max-width:430px;margin:0 auto;line-height:1.6">
                                <?php echo (int) min($questions, $module->quiz_count ?: $questions); ?> questions, drawn at random from <?php echo (int) $questions; ?>.
                                Score <b><?php echo (int) $module->pass_percent; ?>%</b> or more to pass. You get the explanation after every answer, and you can retake it as often as you like.
                            </p>
                            <?php if ($best > 0) { ?>
                                <p style="margin-top:12px"><span class="shra-badge <?php echo $stats['passed'] ? 'shra-badge-green' : 'shra-badge-red'; ?>">Your best: <?php echo $best; ?>%</span></p>
                            <?php } ?>
                            <?php if ($done_n < $total) { ?>
                                <div class="shra-tr-call warn" style="text-align:left;margin-top:18px">You still have <b><?php echo $total - $done_n; ?></b> lesson<?php echo $total - $done_n === 1 ? '' : 's'; ?> to read. You can take the quiz anyway — but the answers are all in there.</div>
                            <?php } ?>
                            <p style="margin-top:18px"><button type="button" class="shra-btn shra-btn-primary shra-btn-lg" id="shra-quiz-go"><i class="fa-solid fa-play"></i> Start the quiz</button></p>
                        </div>
                    </div>

                    <div id="shra-quiz-run" style="display:none">
                        <div class="shra-quiz-head">
                            <span class="lbl">Question <span id="shra-quiz-i">1</span> of <span id="shra-quiz-n">5</span></span>
                            <span class="lbl">Pass mark <?php echo (int) $module->pass_percent; ?>%</span>
                        </div>
                        <div class="shra-quiz-dots" id="shra-quiz-dots"></div>
                        <h3 class="shra-quiz-q" id="shra-quiz-q"></h3>
                        <div class="shra-quiz-opts" id="shra-quiz-opts"></div>
                        <div id="shra-quiz-fb"></div>
                        <div class="shra-quiz-foot"><button type="button" class="shra-btn shra-btn-primary" id="shra-quiz-next" style="display:none">Next <i class="fa fa-arrow-right"></i></button></div>
                    </div>

                    <div id="shra-quiz-result" style="display:none"></div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>

<script>
window.SHRA_TRAINING = {
    moduleId: <?php echo (int) $module->id; ?>,
    moduleTitle: <?php echo json_encode($module->title); ?>,
    urls: {
        lessonDone: '<?php echo shra_training_url('lesson_done'); ?>',
        quizStart:  '<?php echo shra_training_url('quiz_start/' . (int) $module->id); ?>',
        quizSubmit: '<?php echo shra_training_url('quiz_submit/' . (int) $module->id); ?>',
        home:       '<?php echo shra_training_url(); ?>'
    },
    lessons: <?php echo $total; ?>,
    hasQuiz: <?php echo $has_quiz ? 'true' : 'false'; ?>
};
</script>
<?php init_tail(); ?>
</body>
</html>
