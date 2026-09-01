<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content shra-tr-edit">
    <?php $shra_active = 'training'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $open = $open ?: (count($modules) ? (int) $modules[0]->id : 0);
    $cur  = null;
    foreach ($modules as $m) {
        if ((int) $m->id === $open) { $cur = $m; }
    }
    ?>

    <div class="shra-toolbar" style="justify-content:space-between">
        <h4 class="shra-title" style="margin:0">✏️ Course editor <span class="thin">· Self-Training</span></h4>
        <div>
            <a href="<?php echo shra_training_url(); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-arrow-left"></i> Back to the course</a>
            <a href="<?php echo shra_training_url('restore_defaults'); ?>" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-confirm="Re-install any default module that has been deleted? Modules you have edited are left untouched."><i class="fa fa-rotate-left"></i> Restore missing defaults</a>
        </div>
    </div>

    <div class="shra-tr-call tip" style="max-width:none">
        <b>Lessons use a tiny, safe markup</b> — <code>## Heading</code>, <code>### Sub-heading</code>, <code>- bullet</code>, <code>1. numbered</code>, <code>&gt; tip</code>, <code>!&gt; watch-out</code>, <code>» a line the agent says out loud</code>, <code>**bold**</code>, <code>*italic*</code>, <code>---</code> for a rule.
        <div style="margin-top:8px"><b>Live data tokens</b> — click one to copy. These pull the academy's real prices, trainers and settings into the lesson every time it is opened, so the course never goes stale:</div>
        <div class="shra-tr-tokens">
            <?php foreach ($tokens as $t) { ?><code data-copy="<?php echo html_escape($t); ?>"><?php echo html_escape($t); ?></code><?php } ?>
        </div>
    </div>

    <div class="row">
        <!-- ── Module list ─────────────────────────────────────── -->
        <div class="col-md-4">
            <div class="shra-card">
                <div class="shra-card-head"><h4>Modules</h4><span class="thin"><?php echo count($modules); ?></span></div>
                <div class="list" style="padding:6px">
                    <?php foreach ($modules as $m) { ?>
                        <a href="<?php echo shra_training_url('manage?module=' . (int) $m->id); ?>" class="shra-tr-step <?php echo (int) $m->id === $open ? 'on' : ''; ?>">
                            <span class="n"><?php echo $m->emoji; ?></span>
                            <span class="t"><?php echo html_escape($m->title); ?><?php echo (int) $m->active ? '' : ' <span class="shra-badge shra-badge-muted">Off</span>'; ?>
                                <br><small style="font-weight:400;opacity:.75"><?php echo count($lessons[(int) $m->id] ?? []); ?> lessons · <?php echo count($qs[(int) $m->id] ?? []); ?> questions</small></span>
                        </a>
                    <?php } ?>
                    <?php if (!count($modules)) { ?><div class="shra-empty" style="padding:24px"><i class="fa-solid fa-book"></i>No modules yet.</div><?php } ?>
                </div>
            </div>

            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4>New module</h4></div>
                <div class="shra-card-body">
                    <?php echo form_open(shra_training_url('save_module')); ?>
                        <div class="row">
                            <div class="col-xs-4"><div class="form-group"><label>Emoji</label><input type="text" name="emoji" class="form-control" value="📘" maxlength="8"></div></div>
                            <div class="col-xs-8"><div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" placeholder="e.g. Handling difficult callers" required></div></div>
                        </div>
                        <div class="form-group"><label>One-line tagline</label><input type="text" name="tagline" class="form-control"></div>
                        <div class="row">
                            <div class="col-xs-6"><div class="form-group"><label>Pass mark %</label><input type="number" name="pass_percent" class="form-control" value="70" min="0" max="100"></div></div>
                            <div class="col-xs-6"><div class="form-group"><label>Questions per attempt</label><input type="number" name="quiz_count" class="form-control" value="8" min="0"><div class="help">0 = ask them all</div></div></div>
                        </div>
                        <input type="hidden" name="active" value="1">
                        <button class="shra-btn shra-btn-primary shra-btn-block"><i class="fa fa-plus"></i> Add module</button>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>

        <!-- ── The module being edited ─────────────────────────── -->
        <div class="col-md-8">
        <?php if (!$cur) { ?>
            <div class="shra-card"><div class="shra-empty"><i class="fa-solid fa-hand-point-left"></i>Pick a module on the left, or add one.</div></div>
        <?php } else { ?>

            <div class="shra-card">
                <div class="shra-card-head"><h4><?php echo $cur->emoji; ?> <?php echo html_escape($cur->title); ?></h4>
                    <a href="<?php echo shra_training_url('delete_module/' . (int) $cur->id); ?>" class="shra-btn shra-btn-danger shra-btn-sm" data-shra-confirm="Delete this module with all its lessons, questions and everyone's progress on it? This cannot be undone."><i class="fa fa-trash"></i> Delete module</a>
                </div>
                <div class="shra-card-body">
                    <?php echo form_open(shra_training_url('save_module/' . (int) $cur->id)); ?>
                        <div class="row">
                            <div class="col-sm-2"><div class="form-group"><label>Emoji</label><input type="text" name="emoji" class="form-control" value="<?php echo html_escape($cur->emoji); ?>" maxlength="8"></div></div>
                            <div class="col-sm-7"><div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" value="<?php echo html_escape($cur->title); ?>" required></div></div>
                            <div class="col-sm-3"><div class="form-group"><label>Order</label><input type="number" name="sort_order" class="form-control" value="<?php echo (int) $cur->sort_order; ?>"></div></div>
                        </div>
                        <div class="form-group"><label>Tagline</label><input type="text" name="tagline" class="form-control" value="<?php echo html_escape($cur->tagline); ?>"></div>
                        <div class="form-group"><label>Intro <span class="help" style="display:inline">shown as a callout on the first lesson</span></label><textarea name="intro" class="form-control" rows="3"><?php echo html_escape($cur->intro); ?></textarea></div>
                        <div class="row">
                            <div class="col-sm-3"><div class="form-group"><label>Pass mark %</label><input type="number" name="pass_percent" class="form-control" value="<?php echo (int) $cur->pass_percent; ?>" min="0" max="100"></div></div>
                            <div class="col-sm-3"><div class="form-group"><label>Questions / attempt</label><input type="number" name="quiz_count" class="form-control" value="<?php echo (int) $cur->quiz_count; ?>" min="0"></div></div>
                            <div class="col-sm-3"><div class="form-group"><label>Icon class</label><input type="text" name="icon" class="form-control" value="<?php echo html_escape($cur->icon); ?>"></div></div>
                            <div class="col-sm-3" style="padding-top:27px"><label style="font-weight:500"><input type="checkbox" name="active" value="1" <?php echo (int) $cur->active ? 'checked' : ''; ?>> Visible to staff</label></div>
                        </div>
                        <button class="shra-btn shra-btn-primary"><i class="fa fa-check"></i> Save module</button>
                    <?php echo form_close(); ?>
                </div>
            </div>

            <!-- Lessons -->
            <div class="shra-card shra-mt" id="lessons">
                <div class="shra-card-head"><h4>📖 Lessons</h4><span class="thin"><?php echo count($lessons[(int) $cur->id] ?? []); ?></span></div>
                <div class="shra-card-body">
                    <?php foreach (($lessons[(int) $cur->id] ?? []) as $l) { ?>
                        <div class="shra-tr-erow">
                            <div class="grow">
                                <div class="t"><?php echo $l->emoji; ?> <?php echo html_escape($l->title); ?> <?php echo (int) $l->active ? '' : '<span class="shra-badge shra-badge-muted">Off</span>'; ?></div>
                                <div class="s"><?php echo html_escape(shra_training_excerpt($l->body, 130)); ?></div>
                            </div>
                            <div style="flex:none;white-space:nowrap">
                                <button type="button" class="shra-ic" data-toggle-edit="#tr-l-<?php echo (int) $l->id; ?>" title="Edit"><i class="fa fa-pen"></i></button>
                                <a href="<?php echo shra_training_url('delete_lesson/' . (int) $l->id); ?>" class="shra-ic" data-shra-confirm="Delete this lesson?" title="Delete"><i class="fa fa-trash"></i></a>
                            </div>
                        </div>
                        <div id="tr-l-<?php echo (int) $l->id; ?>" style="display:none;padding:8px 0 16px;border-bottom:1px solid var(--line)">
                            <?php echo form_open(shra_training_url('save_lesson/' . (int) $l->id)); ?>
                                <input type="hidden" name="module_id" value="<?php echo (int) $cur->id; ?>">
                                <div class="row">
                                    <div class="col-sm-2"><div class="form-group"><label>Emoji</label><input type="text" name="emoji" class="form-control" value="<?php echo html_escape($l->emoji); ?>" maxlength="8"></div></div>
                                    <div class="col-sm-8"><div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" value="<?php echo html_escape($l->title); ?>" required></div></div>
                                    <div class="col-sm-2"><div class="form-group"><label>Order</label><input type="number" name="sort_order" class="form-control" value="<?php echo (int) $l->sort_order; ?>"></div></div>
                                </div>
                                <div class="form-group"><label>Body</label><textarea name="body" class="form-control" rows="14" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px"><?php echo html_escape($l->body); ?></textarea></div>
                                <div class="form-group"><label>Key takeaway</label><input type="text" name="takeaway" class="form-control" value="<?php echo html_escape($l->takeaway); ?>"></div>
                                <label style="font-weight:500;margin-right:14px"><input type="checkbox" name="active" value="1" <?php echo (int) $l->active ? 'checked' : ''; ?>> Visible</label>
                                <button class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa fa-check"></i> Save lesson</button>
                            <?php echo form_close(); ?>
                        </div>
                    <?php } ?>

                    <div style="margin-top:14px">
                        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-toggle-edit="#tr-l-new"><i class="fa fa-plus"></i> Add a lesson</button>
                        <div id="tr-l-new" style="display:none;margin-top:14px">
                            <?php echo form_open(shra_training_url('save_lesson')); ?>
                                <input type="hidden" name="module_id" value="<?php echo (int) $cur->id; ?>">
                                <div class="row">
                                    <div class="col-sm-2"><div class="form-group"><label>Emoji</label><input type="text" name="emoji" class="form-control" value="📖" maxlength="8"></div></div>
                                    <div class="col-sm-10"><div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div></div>
                                </div>
                                <div class="form-group"><label>Body</label><textarea name="body" class="form-control" rows="12" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px" placeholder="## A heading&#10;- a bullet&#10;&gt; a tip&#10;» something the agent says out loud&#10;{packages}"></textarea></div>
                                <div class="form-group"><label>Key takeaway</label><input type="text" name="takeaway" class="form-control"></div>
                                <input type="hidden" name="active" value="1">
                                <button class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa fa-plus"></i> Add lesson</button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions -->
            <div class="shra-card shra-mt" id="quiz">
                <div class="shra-card-head"><h4>🧠 Quiz questions</h4><span class="thin"><?php echo count($qs[(int) $cur->id] ?? []); ?> · <?php echo (int) $cur->quiz_count ?: 'all'; ?> asked per attempt</span></div>
                <div class="shra-card-body">
                    <?php foreach (($qs[(int) $cur->id] ?? []) as $q) { ?>
                        <div class="shra-tr-erow">
                            <div class="grow">
                                <div class="t"><?php echo html_escape($q->question); ?> <?php echo (int) $q->active ? '' : '<span class="shra-badge shra-badge-muted">Off</span>'; ?></div>
                                <div class="s">✅ <?php echo html_escape($q->options_arr[(int) $q->correct] ?? '—'); ?><?php echo $q->explanation ? ' — ' . html_escape($q->explanation) : ''; ?></div>
                            </div>
                            <div style="flex:none;white-space:nowrap">
                                <button type="button" class="shra-ic" data-toggle-edit="#tr-q-<?php echo (int) $q->id; ?>" title="Edit"><i class="fa fa-pen"></i></button>
                                <a href="<?php echo shra_training_url('delete_question/' . (int) $q->id); ?>" class="shra-ic" data-shra-confirm="Delete this question?" title="Delete"><i class="fa fa-trash"></i></a>
                            </div>
                        </div>
                        <div id="tr-q-<?php echo (int) $q->id; ?>" style="display:none;padding:8px 0 16px;border-bottom:1px solid var(--line)">
                            <?php echo form_open(shra_training_url('save_question/' . (int) $q->id)); ?>
                                <input type="hidden" name="module_id" value="<?php echo (int) $cur->id; ?>">
                                <div class="form-group"><label>Question</label><input type="text" name="question" class="form-control" value="<?php echo html_escape($q->question); ?>" required></div>
                                <label>Answers <span class="help" style="display:inline">tick the right one; leave a box empty to drop that answer</span></label>
                                <?php for ($i = 0; $i < max(4, count($q->options_arr)); $i++) { ?>
                                    <div style="display:flex;gap:9px;align-items:center;margin-bottom:7px">
                                        <input type="radio" name="correct" value="<?php echo $i; ?>" <?php echo (int) $q->correct === $i ? 'checked' : ''; ?> style="margin:0">
                                        <input type="text" name="options[]" class="form-control" style="margin:0" value="<?php echo html_escape($q->options_arr[$i] ?? ''); ?>">
                                    </div>
                                <?php } ?>
                                <div class="form-group" style="margin-top:12px"><label>Explanation <span class="help" style="display:inline">shown after they answer, right or wrong</span></label><input type="text" name="explanation" class="form-control" value="<?php echo html_escape($q->explanation); ?>"></div>
                                <label style="font-weight:500;margin-right:14px"><input type="checkbox" name="active" value="1" <?php echo (int) $q->active ? 'checked' : ''; ?>> Visible</label>
                                <button class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa fa-check"></i> Save question</button>
                            <?php echo form_close(); ?>
                        </div>
                    <?php } ?>

                    <div style="margin-top:14px">
                        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-toggle-edit="#tr-q-new"><i class="fa fa-plus"></i> Add a question</button>
                        <div id="tr-q-new" style="display:none;margin-top:14px">
                            <?php echo form_open(shra_training_url('save_question')); ?>
                                <input type="hidden" name="module_id" value="<?php echo (int) $cur->id; ?>">
                                <div class="form-group"><label>Question</label><input type="text" name="question" class="form-control" required></div>
                                <label>Answers <span class="help" style="display:inline">tick the right one</span></label>
                                <?php for ($i = 0; $i < 4; $i++) { ?>
                                    <div style="display:flex;gap:9px;align-items:center;margin-bottom:7px">
                                        <input type="radio" name="correct" value="<?php echo $i; ?>" <?php echo $i === 0 ? 'checked' : ''; ?> style="margin:0">
                                        <input type="text" name="options[]" class="form-control" style="margin:0" placeholder="Answer <?php echo $i + 1; ?>">
                                    </div>
                                <?php } ?>
                                <div class="form-group" style="margin-top:12px"><label>Explanation</label><input type="text" name="explanation" class="form-control"></div>
                                <input type="hidden" name="active" value="1">
                                <button class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa fa-plus"></i> Add question</button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php } ?>
        </div>
    </div>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>

<script>
(function ($) {
    $(document).on('click', '[data-toggle-edit]', function () {
        $($(this).data('toggle-edit')).slideToggle(140);
    });
    // Click a token chip to copy it into the clipboard.
    $(document).on('click', '.shra-tr-tokens code', function () {
        var t = $(this).data('copy');
        var $ta = $('<textarea>').val(t).css({ position: 'fixed', top: '-1000px', opacity: 0 }).appendTo('body');
        $ta[0].select();
        try { document.execCommand('copy'); if (window.alert_float) { alert_float('success', t + ' copied'); } } catch (e) {}
        $ta.remove();
    });
})(jQuery);
</script>
<?php init_tail(); ?>
</body>
</html>
