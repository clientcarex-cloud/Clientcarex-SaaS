<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="ptk-wrap">

            <?php $active = 'settings'; include __DIR__ . '/_nav.php'; ?>

            <div class="ptk-grid ptk-grid-1-1">

                <!-- ── Existing templates ── -->
                <div class="ptk-card">
                    <h4 class="ptk-card-title">
                        <i class="fa fa-list-check"></i> <?= _l('pro_tickets_todo_templates'); ?>
                        <span class="ptk-pill"><?= count($templates); ?></span>
                    </h4>
                    <p class="ptk-small ptk-muted"><?= _l('pro_tickets_todo_templates_hint'); ?></p>

                    <?php if (empty($templates)): ?>
                        <div class="ptk-small ptk-muted"><?= _l('pro_tickets_todo_templates_none'); ?></div>
                    <?php else: ?>
                        <?php foreach ($templates as $tpl): ?>
                            <div style="display:flex;align-items:center;gap:8px;border:1px solid #e5e9f0;border-radius:10px;padding:10px 12px;margin-bottom:8px;<?= $editing && (int) $editing->id === (int) $tpl->id ? 'box-shadow:0 0 0 2px rgba(59,130,246,.35);' : ''; ?>">
                                <div style="flex:1;min-width:0;">
                                    <strong><?= html_escape($tpl->name); ?></strong>
                                    <span class="ptk-pill"><?= (int) $tpl->items_count; ?> <?= _l('pro_tickets_todo_template_items'); ?></span>
                                    <?php if (trim((string) $tpl->description) !== ''): ?>
                                        <div class="ptk-small ptk-muted"><?= html_escape($tpl->description); ?></div>
                                    <?php endif; ?>
                                    <div class="ptk-small ptk-muted"><?= html_escape(trim((string) $tpl->creator_name)); ?> · <?= html_escape(_dt($tpl->datecreated)); ?></div>
                                </div>
                                <a href="<?= admin_url('pro_tickets/todo_templates/' . (int) $tpl->id); ?>" class="ptk-btn ptk-btn-light ptk-btn-sm" title="<?= html_escape(_l('pro_tickets_todo_template_edit')); ?>"><i class="fa fa-pencil-alt"></i></a>
                                <?= form_open(admin_url('pro_tickets/delete_todo_template/' . (int) $tpl->id), ['style' => 'margin:0;', 'onsubmit' => "return confirm('" . html_escape(_l('pro_tickets_todo_template_delete_confirm')) . "');"]); ?>
                                    <button type="submit" class="ptk-btn ptk-btn-light ptk-btn-sm" title="<?= html_escape(_l('pro_tickets_todo_delete')); ?>"><i class="fa fa-trash" style="color:#dc2626;"></i></button>
                                <?= form_close(); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($editing): ?>
                        <a href="<?= admin_url('pro_tickets/todo_templates'); ?>" class="ptk-btn ptk-btn-light ptk-btn-sm"><i class="fa fa-plus"></i> <?= _l('pro_tickets_todo_template_new'); ?></a>
                    <?php endif; ?>
                </div>

                <!-- ── Editor ── -->
                <div class="ptk-card">
                    <h4 class="ptk-card-title">
                        <i class="fa <?= $editing ? 'fa-pencil-alt' : 'fa-plus'; ?>"></i>
                        <?= $editing ? _l('pro_tickets_todo_template_edit') : _l('pro_tickets_todo_template_new'); ?>
                    </h4>

                    <?= form_open(admin_url('pro_tickets/save_todo_template'), ['id' => 'ptk-tpl-form']); ?>
                        <input type="hidden" name="id" value="<?= $editing ? (int) $editing->id : 0; ?>">
                        <input type="hidden" name="items" id="ptk-tpl-items-json">

                        <div class="ptk-form-field">
                            <label><?= _l('pro_tickets_todo_template_name'); ?></label>
                            <input type="text" name="name" class="ptk-input" maxlength="191" required
                                   placeholder="<?= html_escape(_l('pro_tickets_todo_template_name_ph')); ?>"
                                   value="<?= html_escape($editing->name ?? ''); ?>">
                        </div>

                        <div class="ptk-form-field">
                            <label><?= _l('pro_tickets_todo_description'); ?></label>
                            <input type="text" name="description" class="ptk-input" maxlength="500"
                                   value="<?= html_escape($editing->description ?? ''); ?>">
                        </div>

                        <div class="ptk-form-field">
                            <label><?= _l('pro_tickets_todo_template_items'); ?></label>
                            <div id="ptk-tpl-items"></div>
                            <button type="button" class="ptk-btn ptk-btn-light ptk-btn-sm" id="ptk-tpl-add-item"><i class="fa fa-plus"></i> <?= _l('pro_tickets_todo_template_add_item'); ?></button>
                        </div>

                        <button type="submit" class="ptk-btn ptk-btn-primary"><i class="fa fa-check"></i> <?= _l('submit'); ?></button>
                    <?= form_close(); ?>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function ($) {
    'use strict';

    var PRIORITIES = {
        1: <?= json_encode(_l('pro_tickets_todo_low')); ?>,
        2: <?= json_encode(_l('pro_tickets_todo_medium')); ?>,
        3: <?= json_encode(_l('pro_tickets_todo_high')); ?>,
        4: <?= json_encode(_l('pro_tickets_todo_urgent')); ?>
    };
    var EXISTING = <?= json_encode($editing ? array_map(static function ($item) {
        return [
            'title'       => $item->title,
            'description' => (string) ($item->description ?? ''),
            'priority'    => (int) $item->priority,
        ];
    }, $editing->items) : []); ?>;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function addRow(item) {
        item = item || { title: '', description: '', priority: 2 };
        var opts = '';
        Object.keys(PRIORITIES).forEach(function (p) {
            opts += '<option value="' + p + '"' + (parseInt(item.priority, 10) === parseInt(p, 10) ? ' selected' : '') + '>' + esc(PRIORITIES[p]) + '</option>';
        });
        $('#ptk-tpl-items').append(
            '<div class="ptk-tpl-item" style="display:flex;gap:6px;margin-bottom:6px;align-items:flex-start;">' +
            '<span style="cursor:default;color:#94a3b8;padding-top:9px;"><i class="fa fa-grip-vertical"></i></span>' +
            '<div style="flex:1;">' +
            '<input type="text" class="ptk-input ptk-tpl-title" maxlength="500" placeholder="<?= html_escape(_l('pro_tickets_todo_title_ph')); ?>" value="' + esc(item.title) + '">' +
            '<input type="text" class="ptk-input ptk-tpl-desc" maxlength="1000" style="margin-top:4px;font-size:12px;" placeholder="<?= html_escape(_l('pro_tickets_todo_description_ph')); ?>" value="' + esc(item.description) + '">' +
            '</div>' +
            '<select class="ptk-select ptk-tpl-priority" style="width:110px;">' + opts + '</select>' +
            '<button type="button" class="ptk-btn ptk-btn-light ptk-btn-sm ptk-tpl-remove" title="<?= html_escape(_l('pro_tickets_todo_delete')); ?>"><i class="fa fa-xmark"></i></button>' +
            '</div>'
        );
    }

    if (EXISTING.length) {
        EXISTING.forEach(addRow);
    } else {
        addRow();
    }

    $('#ptk-tpl-add-item').on('click', function () {
        if ($('#ptk-tpl-items .ptk-tpl-item').length >= 50) { return; }
        addRow();
        $('#ptk-tpl-items .ptk-tpl-item:last .ptk-tpl-title').focus();
    });

    $('#ptk-tpl-items').on('click', '.ptk-tpl-remove', function () {
        $(this).closest('.ptk-tpl-item').remove();
    });

    $('#ptk-tpl-form').on('submit', function () {
        var items = [];
        $('#ptk-tpl-items .ptk-tpl-item').each(function () {
            var title = $.trim($(this).find('.ptk-tpl-title').val());
            if (!title) { return; }
            items.push({
                title: title,
                description: $.trim($(this).find('.ptk-tpl-desc').val()),
                priority: parseInt($(this).find('.ptk-tpl-priority').val(), 10) || 2
            });
        });
        if (!items.length) {
            alert(<?= json_encode(_l('pro_tickets_todo_template_invalid')); ?>);
            return false;
        }
        $('#ptk-tpl-items-json').val(JSON.stringify(items));
        return true;
    });
})(jQuery);
</script>
</body>
</html>
