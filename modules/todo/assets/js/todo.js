/**
 * TODO Module — Single-Page JS Engine
 * Handles all CRUD, filters, checklist, and inline editing
 */
var TodoApp = (function() {
    'use strict';

    var BASE_URL   = typeof admin_url !== 'undefined' ? admin_url : '';
    var CSRF_NAME  = typeof csrfData !== 'undefined' ? csrfData.token_name : '';
    var CSRF_HASH  = typeof csrfData !== 'undefined' ? csrfData.hash : '';
    var currentFilter = { status: 'all', category_id: '', priority: '', search: '', date_from: '', date_to: '', special: '' };
    var debounceTimer = null;

    function init() {
        bindEvents();
        bindTemplateEvents();
        bindAutoCapitalize();
        // Initialize checklist drag-to-reorder on page load
        initChecklistSortable();
        // Auto-switch tab from URL hash (e.g. #templates)
        if (window.location.hash === '#templates') {
            switchTab('templates');
        }
    }

    function bindEvents() {
        // Toggle create form
        $(document).on('click', '#btn-toggle-create', function() {
            $('#todo-create-form').toggleClass('show');
            if ($('#todo-create-form').hasClass('show')) {
                $('#create-title').focus();
            }
        });

        // Add checklist item in create form
        $(document).on('click', '#add-create-checklist', function() {
            addChecklistRow('#create-checklist-list');
        });
        $(document).on('click', '.remove-checklist-btn', function() {
            $(this).closest('.todo-checklist-item-row').remove();
        });

        // Enter key on checklist input in create form — auto-add new row
        $(document).on('keydown', '#create-checklist-list .todo-checklist-item-row input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addChecklistRow('#create-checklist-list');
            }
        });

        // Submit new task
        $(document).on('click', '#btn-save-task', function() {
            saveNewTask();
        });
        $(document).on('keydown', '#create-title', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); saveNewTask(); }
        });

        // Filters
        $(document).on('click', '.todo-filter-btn[data-status]', function() {
            $('.todo-filter-btn[data-status]').removeClass('active');
            $(this).addClass('active');
            currentFilter.status = $(this).data('status');
            currentFilter.special = '';
            // Sync stat cards
            $('.todo-stat-card').removeClass('active');
            loadTasks();
        });
        $(document).on('click', '.todo-filter-btn[data-priority]', function() {
            var p = $(this).data('priority');
            if (currentFilter.priority == p) {
                currentFilter.priority = '';
                $(this).removeClass('active');
            } else {
                $('.todo-filter-btn[data-priority]').removeClass('active');
                $(this).addClass('active');
                currentFilter.priority = p;
            }
            loadTasks();
        });
        $(document).on('click', '.todo-cat-item[data-cat-id]', function() {
            var cid = $(this).data('cat-id');
            if (currentFilter.category_id == cid) {
                currentFilter.category_id = '';
                $(this).removeClass('active');
            } else {
                $('.todo-cat-item').removeClass('active');
                $(this).addClass('active');
                currentFilter.category_id = cid;
            }
            loadTasks();
        });

        // Search
        $(document).on('input', '#todo-search', function() {
            clearTimeout(debounceTimer);
            var val = $(this).val();
            debounceTimer = setTimeout(function() {
                currentFilter.search = val;
                loadTasks();
            }, 350);
        });

        // Date range filter
        $(document).on('change', '#todo-date-from, #todo-date-to', function() {
            currentFilter.date_from = $('#todo-date-from').val();
            currentFilter.date_to   = $('#todo-date-to').val();
            loadTasks();
        });

        // Stat card click — filter by status
        $(document).on('click', '.todo-stat-card[data-filter-status]', function() {
            var filterVal = $(this).data('filter-status') + '';

            // Toggle: if already active, reset to all
            if ($(this).hasClass('active')) {
                filterVal = 'all';
            }

            // Update stat card active state
            $('.todo-stat-card').removeClass('active');
            if (filterVal !== 'all') {
                $(this).addClass('active');
            }

            // Handle special filters (overdue, due_today)
            currentFilter.special = '';
            if (filterVal === 'overdue' || filterVal === 'due_today') {
                currentFilter.status = 'all';
                currentFilter.special = filterVal;
            } else {
                currentFilter.status = filterVal;
            }

            // Sync sidebar status buttons
            $('.todo-filter-btn[data-status]').removeClass('active');
            if (['0','1','2','all','my_tasks'].indexOf(filterVal) !== -1) {
                $('.todo-filter-btn[data-status="' + filterVal + '"]').addClass('active');
            }

            loadTasks();
        });

        // Toggle task complete
        $(document).on('click', '.todo-task-check', function(e) {
            e.stopPropagation();
            var $card = $(this).closest('.todo-task-card');
            var id = $card.data('task-id');
            var newStatus = $(this).hasClass('checked') ? 0 : 2;
            toggleTask(id, newStatus);
        });

        // Delete task
        $(document).on('click', '.btn-delete-task', function(e) {
            e.stopPropagation();
            var id = $(this).closest('.todo-task-card').data('task-id');
            if (confirm('Delete this task?')) {
                deleteTask(id);
            }
        });

        // Copy task progress to clipboard (WhatsApp-friendly)
        $(document).on('click', '.btn-copy-task', function(e) {
            e.stopPropagation();
            var $card = $(this).closest('.todo-task-card');
            var $btn = $(this);
            copyTaskToClipboard($card, $btn);
        });

        // Edit task
        $(document).on('click', '.btn-edit-task', function(e) {
            e.stopPropagation();
            var $card = $(this).closest('.todo-task-card');
            openEditForm($card);
        });

        // Checklist toggle on card (collapse/expand)
        $(document).on('click', '.todo-task-checklist-progress', function() {
            $(this).closest('.todo-task-checklist').find('.todo-task-checklist-items').toggleClass('collapsed');
        });

        // Toggle checklist item on card
        $(document).on('click', '.todo-cl-check', function(e) {
            e.stopPropagation();
            var $item = $(this);
            var clId = $item.data('cl-id');
            var newState = $item.hasClass('checked') ? 0 : 1;
            toggleChecklistItem(clId, newState, $item);
        });

        // Add checklist item on card
        $(document).on('click', '.btn-add-cl-item', function() {
            var $row = $(this).closest('.todo-cl-add-row');
            var $input = $row.find('input');
            var title = $input.val().trim();
            var taskId = $(this).closest('.todo-task-card').data('task-id');
            if (title) {
                addChecklistItemToTask(taskId, title, $row);
                $input.val('').focus();
            }
        });
        $(document).on('keydown', '.todo-cl-add-row input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('.todo-cl-add-row').find('.btn-add-cl-item').click();
            }
        });

        // Delete checklist item on card
        $(document).on('click', '.todo-cl-delete', function(e) {
            e.stopPropagation();
            var $item = $(this).closest('.todo-cl-item');
            var clId = $item.find('.todo-cl-check').data('cl-id');
            deleteChecklistItem(clId, $item);
        });

        // Double-click to edit checklist item text (inline edit + auto-save)
        $(document).on('dblclick', '.todo-cl-text', function(e) {
            e.stopPropagation();
            var $span = $(this);
            if ($span.hasClass('editing')) return; // already in edit mode

            var currentText = $span.text().trim();
            var clId = $span.closest('.todo-cl-item').data('cl-id');

            $span.addClass('editing');
            var $input = $('<input type="text" class="todo-cl-inline-edit" />')
                .val(currentText)
                .css({
                    'font-size': '12.5px',
                    'flex': '1',
                    'background': 'var(--todo-surface-2)',
                    'border': '1px solid var(--todo-accent)',
                    'border-radius': '6px',
                    'padding': '4px 8px',
                    'color': 'var(--todo-text)',
                    'outline': 'none',
                    'font-family': 'inherit',
                    'box-shadow': '0 0 0 2px var(--todo-accent-glow)',
                });

            $span.replaceWith($input);
            $input.focus().select();

            // Save on blur
            $input.on('blur', function() {
                var newText = $(this).val().trim();
                if (!newText) newText = currentText; // prevent empty

                var $newSpan = $('<span class="todo-cl-text"></span>').text(newText);
                $(this).replaceWith($newSpan);

                // Auto-save if changed
                if (newText !== currentText) {
                    ajaxPost('ajax_update_checklist_title', { id: clId, title: newText }, function(res) {
                        // silently saved
                    });
                }
            });

            // Save on Enter, cancel on Escape
            $input.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).blur();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    var $newSpan = $('<span class="todo-cl-text"></span>').text(currentText);
                    $(this).replaceWith($newSpan);
                }
            });
        });

        // Add category
        $(document).on('click', '#btn-add-cat', function() {
            var name  = $('#new-cat-name').val().trim();
            var color = $('#new-cat-color').val();
            if (name) addCategory(name, color);
        });
        $(document).on('keydown', '#new-cat-name', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#btn-add-cat').click(); }
        });

        // Delete category
        $(document).on('click', '.btn-delete-cat', function(e) {
            e.stopPropagation();
            var id = $(this).data('cat-id');
            if (confirm('Delete this category? Tasks will be uncategorized.')) {
                deleteCategory(id);
            }
        });

        // Remarks: add
        $(document).on('click', '.todo-remark-add-btn', function() {
            var $section = $(this).closest('.todo-task-remarks');
            var taskId = $(this).data('task-id');
            var $input = $section.find('.todo-remark-input');
            var text = $input.val().trim();
            if (text) {
                addRemark(taskId, text, $section);
                $input.val('').focus();
            }
        });
        $(document).on('keydown', '.todo-remark-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('.todo-task-remarks').find('.todo-remark-add-btn').click();
            }
        });

        // Remarks: delete
        $(document).on('click', '.todo-remark-delete', function(e) {
            e.stopPropagation();
            var $item = $(this).closest('.todo-remark-item');
            var remarkId = $(this).data('remark-id');
            deleteRemark(remarkId, $item);
        });
    }

    // ── AJAX Helpers ──
    function ajaxPost(endpoint, data, callback) {
        data[CSRF_NAME] = CSRF_HASH;
        $.ajax({
            url: BASE_URL + 'todo/' + endpoint,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                // Update CSRF hash for next request
                if (res && res.csrf_hash) { CSRF_HASH = res.csrf_hash; }
                callback(res);
            },
            error: function(xhr) {
                // Try to extract new CSRF from response
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.csrf_hash) CSRF_HASH = r.csrf_hash;
                } catch(e) {}
                console.error('AJAX Error:', endpoint, xhr.statusText);
            }
        });
    }

    // ── Load Tasks via AJAX ──
    function loadTasks() {
        var $list = $('#todo-tasks-list');
        $list.html('<div class="todo-loading"><div class="spinner"></div>Loading...</div>');

        ajaxPost('ajax_get_tasks', currentFilter, function(res) {
            if (res.success) {
                renderTasks(res.tasks);
                updateStats(res.stats);
            }
        });
    }

    // ── Render Tasks ──
    function renderTasks(tasks) {
        var $list = $('#todo-tasks-list');
        $list.empty();

        if (!tasks || tasks.length === 0) {
            $list.html(
                '<div class="todo-empty">' +
                    '<i class="fas fa-check-circle"></i>' +
                    '<h3>No tasks found</h3>' +
                    '<p>Create a new task to get started!</p>' +
                '</div>'
            );
            return;
        }

        var priorityLabels = {1:'Low',2:'Medium',3:'High',4:'Urgent'};
        var priorityClasses = {1:'p-low',2:'p-medium',3:'p-high',4:'p-urgent'};
        var statusLabels = {0:'Pending',1:'In Progress',2:'Completed'};

        tasks.forEach(function(task) {
            var isCompleted = parseInt(task.status) === 2;
            var p = parseInt(task.priority) || 1;

            // Due date display
            var dueDateHtml = '';
            if (task.due_date) {
                var today = new Date(); today.setHours(0,0,0,0);
                var due = new Date(task.due_date + 'T00:00:00'); 
                var diff = Math.floor((due - today) / 86400000);
                var overdueClass = '';
                var dueLabel = formatDate(task.due_date);
                if (!isCompleted && diff < 0) { overdueClass = ' overdue'; dueLabel += ' (Overdue)'; }
                else if (diff === 0) { dueLabel = 'Today'; }
                else if (diff === 1) { dueLabel = 'Tomorrow'; }
                dueDateHtml = '<span class="todo-task-meta-item' + overdueClass + '"><i class="fas fa-calendar-alt"></i> ' + dueLabel + '</span>';
            }

            // Category tag
            var catHtml = '';
            if (task.category_name) {
                catHtml = '<span class="todo-task-category-tag" style="background:' + hexToRgba(task.category_color, 0.15) + ';color:' + task.category_color + ';">' +
                    '<i class="fa ' + (task.category_icon || 'fa-folder') + '"></i> ' + escHtml(task.category_name) + '</span>';
            }

            // Checklist section — always shown with add-item row
            var clHtml = '<div class="todo-task-checklist">';
            if (task.checklist && task.checklist.length > 0) {
                var total = parseInt(task.checklist_total) || 0;
                var done  = parseInt(task.checklist_done) || 0;
                var pct   = total > 0 ? Math.round((done / total) * 100) : 0;

                clHtml += '<div class="todo-card-progress-bar"><div class="todo-card-progress-fill" style="width:' + pct + '%"></div></div>';
                clHtml += '<div class="todo-task-checklist-items" data-task-id="' + task.id + '">';

                task.checklist.forEach(function(cl) {
                    var chk = parseInt(cl.is_checked) ? 'checked' : '';
                    clHtml += '<div class="todo-cl-item" data-cl-id="' + cl.id + '">' +
                        '<span class="todo-cl-drag-handle" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></span>' +
                        '<div class="todo-cl-check ' + chk + '" data-cl-id="' + cl.id + '"></div>' +
                        '<span class="todo-cl-text">' + escHtml(cl.title) + '</span>' +
                        '<button class="todo-cl-delete" title="Remove"><i class="fa fa-times"></i></button>' +
                    '</div>';
                });
            } else {
                clHtml += '<div class="todo-task-checklist-items" data-task-id="' + task.id + '">';
            }

            clHtml += '<div class="todo-cl-add-row">' +
                    '<input type="text" placeholder="Add item..." />' +
                    '<button class="btn-add-cl-item"><i class="fa fa-plus"></i></button>' +
                '</div>' +
                '</div></div>';

            var remarksList = task.remarks_list || [];
            var remarksHtml = '<div class="todo-task-remarks" data-task-id="' + task.id + '">' +
                '<div class="todo-remarks-list">';
            remarksList.forEach(function(rm) {
                remarksHtml += '<div class="todo-remark-item" data-remark-id="' + rm.id + '">' +
                    '<div class="todo-remark-content">' +
                        '<span class="todo-remark-text">' + escHtml(rm.remark) + '</span>' +
                        '<span class="todo-remark-meta">' +
                            '<span class="todo-remark-author">' + escHtml(rm.staff_name || '') + '</span>' +
                            '<span class="todo-remark-time">' + formatDateTime(rm.datecreated) + '</span>' +
                        '</span>' +
                    '</div>' +
                    '<button class="todo-remark-delete" data-remark-id="' + rm.id + '" title="Delete"><i class="fa fa-times"></i></button>' +
                '</div>';
            });
            remarksHtml += '</div>' +
                '<div class="todo-remark-add-row">' +
                    '<input type="text" class="todo-remark-input" placeholder="Add a remark..." />' +
                    '<button class="todo-remark-add-btn" data-task-id="' + task.id + '"><i class="fa fa-paper-plane"></i></button>' +
                '</div>' +
            '</div>';

            var html = '<div class="todo-task-card priority-' + p + (isCompleted ? ' completed' : '') + '" data-task-id="' + task.id + '" data-staff-name="' + escAttr(task.staff_name || '') + '" data-due-date="' + escAttr(task.due_date || '') + '">' +
                '<div class="todo-task-top">' +
                    '<div class="todo-task-check' + (isCompleted ? ' checked' : '') + '"></div>' +
                    '<div class="todo-task-content">' +
                        '<div class="todo-task-title-row">' +
                            '<div class="todo-task-title">' + escHtml(task.title) + '</div>' +
                            '<div class="todo-task-badges">' +
                                catHtml +
                                dueDateHtml +
                                '<span class="priority-badge ' + priorityClasses[p] + '">' + priorityLabels[p] + '</span>' +
                                '<span class="status-badge s-' + task.status + '">' + statusLabels[task.status] + '</span>' +
                            '</div>' +
                        '</div>' +
                        buildScoreStrip(task, isCompleted, p) +
                        (task.description ? '<div class="todo-task-desc">' + escHtml(task.description) + '</div>' : '');

                    // Assignment tags
                    var assignees = task.assignees || [];
                    var assignedRoles = task.assigned_roles || [];
                    if (assignees.length > 0 || assignedRoles.length > 0) {
                        html += '<div class="todo-assignment-tags">';
                        assignees.forEach(function(a) {
                            html += '<span class="todo-assign-tag staff-tag"><i class="fa fa-user"></i> ' + escHtml(a.name) + '</span>';
                        });
                        assignedRoles.forEach(function(r) {
                            html += '<span class="todo-assign-tag role-tag"><i class="fa fa-users"></i> ' + escHtml(r.name) + '</span>';
                        });
                        html += '</div>';
                    }

                    html += '</div>' +
                    '<button class="btn-copy-task" title="Copy for WhatsApp"><i class="fa fa-copy"></i></button>' +
                    '<div class="todo-task-actions">' +
                        '<button class="btn-edit-task" title="Edit"><i class="fa fa-pencil"></i></button>' +
                        '<button class="btn-delete-task delete-btn" title="Delete"><i class="fa fa-trash"></i></button>' +
                    '</div>' +
                '</div>' +
                clHtml +
                remarksHtml +
            '</div>';

            $list.append(html);
        });

        // Initialize sortable on all checklist containers
        initChecklistSortable();
    }

    // ── Initialize jQuery UI Sortable on checklist items ──
    function initChecklistSortable() {
        $('.todo-task-checklist-items').each(function() {
            var $container = $(this);
            if ($container.data('ui-sortable')) return; // already initialized
            $container.sortable({
                items: '.todo-cl-item',
                handle: '.todo-cl-drag-handle',
                axis: 'y',
                tolerance: 'pointer',
                placeholder: 'todo-cl-sortable-placeholder',
                cursor: 'grabbing',
                opacity: 0.85,
                update: function(event, ui) {
                    var order = [];
                    $container.find('.todo-cl-item').each(function() {
                        order.push($(this).data('cl-id'));
                    });
                    // Save new order via AJAX
                    var data = {};
                    order.forEach(function(id, idx) {
                        data['order[' + idx + ']'] = id;
                    });
                    ajaxPost('ajax_reorder_checklist', data, function(res) {
                        // silently saved
                    });
                }
            });
        });
    }

    // ── Build Score Indicator Strip for a task ──
    function buildScoreStrip(task, isCompleted, priority) {
        if (!task.due_date) return '';

        var today = new Date(); today.setHours(0,0,0,0);
        var due = new Date(task.due_date + 'T00:00:00');
        var daysDiff = Math.floor((due - today) / 86400000); // positive = days left

        if (isCompleted) {
            // Show earned score
            var compDate = task.date_completed ? new Date(task.date_completed.substring(0, 10) + 'T00:00:00') : today;
            var compDiff = Math.floor((due - compDate) / 86400000); // positive = completed early
            var earned = 10, label = '+10 completed';
            if (compDiff >= 0) {
                if (compDiff >= 2) { earned += 8; label = '+18 early bonus!'; }
                else if (compDiff >= 1) { earned += 5; label = '+15 early bonus!'; }
                if (priority >= 3) { earned += 5; label += ' +priority'; }
            } else {
                var lateDays = Math.abs(compDiff);
                if (lateDays <= 3) { earned -= lateDays; label = '+' + earned + ' (late -' + lateDays + ')'; }
                else { earned -= 5; label = '+' + earned + ' (late penalty)'; }
            }
            return '<div class="todo-score-strip score-earned"><i class="fas fa-star"></i> ' + label + '</div>';
        }

        if (daysDiff < 0) {
            var overdueDays = Math.abs(daysDiff);
            if (overdueDays > 3) {
                return '<div class="todo-score-strip score-danger"><i class="fas fa-exclamation-triangle"></i> ' + overdueDays + ' days overdue — penalty -5 + running -2/day</div>';
            }
            return '<div class="todo-score-strip score-warning"><i class="fas fa-exclamation-circle"></i> ' + overdueDays + ' day(s) overdue — complete now to limit penalty to -' + overdueDays + '</div>';
        }

        if (daysDiff === 0) {
            var pts = 10 + (priority >= 3 ? 5 : 0);
            return '<div class="todo-score-strip score-today"><i class="fas fa-bolt"></i> Due today — complete now for +' + pts + ' pts</div>';
        }

        if (daysDiff === 1) {
            var pts = 10 + 5 + (priority >= 3 ? 5 : 0);
            return '<div class="todo-score-strip score-early"><i class="fas fa-rocket"></i> Complete today for +' + pts + ' early bonus!</div>';
        }

        if (daysDiff >= 2) {
            var pts = 10 + 5 + 3 + (priority >= 3 ? 5 : 0);
            return '<div class="todo-score-strip score-bonus"><i class="fas fa-trophy"></i> Complete today for +' + pts + ' pts (max early bonus!)</div>';
        }

        return '';
    }

    // ── Update Stats ──
    function updateStats(stats) {
        if (!stats) return;
        $('#stat-total').text(stats.total);
        $('#stat-pending').text(stats.pending);
        $('#stat-progress').text(stats.in_progress);
        $('#stat-done').text(stats.completed);
        $('#stat-overdue').text(stats.overdue);
        $('#stat-today').text(stats.due_today);

        // Update sidebar status counts
        $('.todo-filter-btn[data-status="all"] .filter-count').text(stats.total);
        $('.todo-filter-btn[data-status="my_tasks"] .filter-count').text(stats.my_tasks);
        $('.todo-filter-btn[data-status="0"] .filter-count').text(stats.pending);
        $('.todo-filter-btn[data-status="1"] .filter-count').text(stats.in_progress);
        $('.todo-filter-btn[data-status="2"] .filter-count').text(stats.completed);

        // Update sidebar priority counts
        $('#cnt-priority-4').text(stats.priority_urgent || 0);
        $('#cnt-priority-3').text(stats.priority_high || 0);
        $('#cnt-priority-2').text(stats.priority_medium || 0);
        $('#cnt-priority-1').text(stats.priority_low || 0);

        // Update sidebar category counts
        if (stats.cat_counts) {
            $('.todo-cat-count').text('0'); // reset all
            $.each(stats.cat_counts, function(catId, cnt) {
                $('#cnt-cat-' + catId).text(cnt);
            });
        }
    }

    // ── Save New Task ──
    function saveNewTask() {
        var title = $('#create-title').val().trim();
        if (!title) { $('#create-title').focus(); return; }

        var data = {
            title:       title,
            description: $('#create-desc').val().trim(),
            category_id: $('#create-category').val(),
            priority:    $('#create-priority').val(),
            due_date:    $('#create-due-date').val(),
        };

        // Gather assignees
        var assigneeIds = $('#create-assignees').val() || [];
        assigneeIds.forEach(function(id, i) {
            data['assignee_ids[' + i + ']'] = id;
        });
        // Gather roles
        var roleIds = $('#create-roles').val() || [];
        roleIds.forEach(function(id, i) {
            data['role_ids[' + i + ']'] = id;
        });

        // Gather checklist
        var cl = [];
        $('#create-checklist-list .todo-checklist-item-row input').each(function() {
            var v = $(this).val().trim();
            if (v) cl.push(v);
        });
        if (cl.length > 0) {
            data['checklist[]'] = cl;
        }

        ajaxPost('ajax_add_task', data, function(res) {
            if (res.success) {
                // Reset form with smart defaults
                $('#create-title').val('');
                $('#create-desc').val('');
                // Re-select "Work" category
                var $workOpt = $('#create-category option').filter(function() {
                    return $(this).text().trim().toLowerCase() === 'work';
                });
                $('#create-category').val($workOpt.length ? $workOpt.val() : '');
                $('#create-priority').val('3');
                $('#create-due-date').val(todayDate());
                $('#create-checklist-list').empty();
                $('#create-assignees').val([]);
                $('#create-roles').val([]);
                loadTasks();
                refreshCategories();
            }
        });
    }

    // ── Toggle Task ──
    function toggleTask(id, status) {
        ajaxPost('ajax_toggle_task', { id: id, status: status }, function(res) {
            if (res.success) loadTasks();
        });
    }

    // ── Delete Task ──
    function deleteTask(id) {
        ajaxPost('ajax_delete_task', { id: id }, function(res) {
            if (res.success) loadTasks();
        });
    }

    // ── Edit Form Inline ──
    function openEditForm($card) {
        // Close any existing edit form
        $('.todo-edit-form').remove();
        // Restore any previously hidden cards
        $('.todo-task-card:hidden').show();

        var taskId = $card.data('task-id');
        var title  = $card.find('.todo-task-title').text();
        var desc   = $card.find('.todo-task-desc').text() || '';
        var status = $card.hasClass('completed') ? 2 : ($card.find('.todo-task-meta-item:last').text().trim() === 'In Progress' ? 1 : 0);

        // Get current priority from class
        var priority = 1;
        [1,2,3,4].forEach(function(p) { if ($card.hasClass('priority-' + p)) priority = p; });

        // Gather existing checklist items from the card
        var existingChecklist = [];
        $card.find('.todo-cl-item').each(function() {
            var clText = $(this).find('.todo-cl-text').text().trim();
            var clChecked = $(this).find('.todo-cl-check').hasClass('checked') ? 1 : 0;
            if (clText) {
                existingChecklist.push({ title: clText, is_checked: clChecked });
            }
        });

        // Build category options from sidebar
        var catOptions = '<option value="">No Category</option>';
        $('.todo-cat-item[data-cat-id]').each(function() {
            var cid = $(this).data('cat-id');
            var cname = $(this).find('.todo-cat-name').text();
            catOptions += '<option value="' + cid + '">' + cname + '</option>';
        });

        // Priority pills
        var prPills = '';
        var prLabels = {1:'Low',2:'Medium',3:'High',4:'Urgent'};
        [1,2,3,4].forEach(function(p) {
            prPills += '<button type="button" class="priority-pill' + (p == priority ? ' active' : '') + '" data-p="' + p + '">' + prLabels[p] + '</button>';
        });

        // Status pills
        var stPills = '';
        var stLabels = {0:'Pending',1:'In Progress',2:'Completed'};
        [0,1,2].forEach(function(s) {
            stPills += '<button type="button" class="status-pill' + (s == status ? ' active' : '') + '" data-s="' + s + '">' + stLabels[s] + '</button>';
        });

        // Build checklist rows from existing items
        var clRows = '';
        existingChecklist.forEach(function(cl) {
            clRows += '<div class="todo-checklist-item-row">' +
                '<label style="display:flex;align-items:center;cursor:pointer;margin:0;">' +
                    '<input type="checkbox" class="edit-cl-check" ' + (cl.is_checked ? 'checked' : '') + ' style="margin-right:8px;width:16px;height:16px;accent-color:var(--todo-success);" />' +
                '</label>' +
                '<input type="text" class="edit-cl-title" value="' + escAttr(cl.title) + '" placeholder="Checklist item..." />' +
                '<button type="button" class="remove-checklist-btn"><i class="fa fa-times"></i></button>' +
            '</div>';
        });

        // Build assignment selects
        var existingAssigneeIds = [];
        $card.find('.todo-assign-tag.staff-tag').each(function() {
            var name = $(this).text().trim();
            // match by name from TODO_STAFF
            (typeof TODO_STAFF !== 'undefined' ? TODO_STAFF : []).forEach(function(s) {
                if (s.name === name) existingAssigneeIds.push(String(s.id));
            });
        });
        var existingRoleIds = [];
        $card.find('.todo-assign-tag.role-tag').each(function() {
            var name = $(this).text().trim();
            (typeof TODO_ROLES !== 'undefined' ? TODO_ROLES : []).forEach(function(r) {
                if (r.name === name) existingRoleIds.push(String(r.id));
            });
        });

        var staffOpts = '';
        (typeof TODO_STAFF !== 'undefined' ? TODO_STAFF : []).forEach(function(s) {
            var sel = existingAssigneeIds.indexOf(String(s.id)) !== -1 ? ' selected' : '';
            staffOpts += '<option value="' + s.id + '"' + sel + '>' + escHtml(s.name) + '</option>';
        });
        var roleOpts = '';
        (typeof TODO_ROLES !== 'undefined' ? TODO_ROLES : []).forEach(function(r) {
            var sel = existingRoleIds.indexOf(String(r.id)) !== -1 ? ' selected' : '';
            roleOpts += '<option value="' + r.id + '"' + sel + '>' + escHtml(r.name) + '</option>';
        });

        var editHtml = '<div class="todo-edit-form" data-edit-id="' + taskId + '">' +
            '<div class="todo-form-row">' +
                '<div class="todo-form-group flex-1">' +
                    '<label class="todo-form-label">Title</label>' +
                    '<input class="todo-form-input edit-title" value="' + escAttr(title) + '" />' +
                '</div>' +
            '</div>' +
            '<div class="todo-form-row">' +
                '<div class="todo-form-group flex-1">' +
                    '<label class="todo-form-label">Description</label>' +
                    '<textarea class="todo-form-input edit-desc" rows="2">' + escHtml(desc) + '</textarea>' +
                '</div>' +
            '</div>' +
            '<div class="todo-form-row">' +
                '<div class="todo-form-group">' +
                    '<label class="todo-form-label">Category</label>' +
                    '<select class="todo-form-input edit-category">' + catOptions + '</select>' +
                '</div>' +
                '<div class="todo-form-group">' +
                    '<label class="todo-form-label">Due Date</label>' +
                    '<input type="date" class="todo-form-input edit-due-date" />' +
                '</div>' +
            '</div>' +
            '<div class="todo-form-row">' +
                '<div class="todo-form-group">' +
                    '<label class="todo-form-label">Priority</label>' +
                    '<div class="priority-pills edit-priority-pills">' + prPills + '</div>' +
                '</div>' +
                '<div class="todo-form-group">' +
                    '<label class="todo-form-label">Status</label>' +
                    '<div class="status-pills edit-status-pills">' + stPills + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="todo-form-row">' +
                '<div class="todo-form-group flex-1">' +
                    '<label class="todo-form-label">Assign To (Staff)</label>' +
                    '<select class="todo-form-input edit-assignees" multiple>' + staffOpts + '</select>' +
                '</div>' +
                '<div class="todo-form-group flex-1">' +
                    '<label class="todo-form-label">Assign Roles</label>' +
                    '<select class="todo-form-input edit-roles" multiple>' + roleOpts + '</select>' +
                '</div>' +
            '</div>' +
            // Checklist builder section
            '<div class="todo-form-row">' +
                '<div class="todo-form-group flex-1">' +
                    '<label class="todo-form-label">Checklist</label>' +
                    '<div class="todo-checklist-builder">' +
                        '<div class="edit-checklist-list">' + clRows + '</div>' +
                        '<button type="button" class="add-checklist-trigger btn-add-edit-checklist">' +
                            '<i class="fas fa-plus-circle"></i> Add checklist item' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="todo-form-row" style="justify-content:flex-end;gap:8px;">' +
                '<button class="todo-btn todo-btn-sm todo-btn-ghost btn-cancel-edit">Cancel</button>' +
                '<button class="todo-btn todo-btn-sm todo-btn-primary btn-save-edit"><i class="fa fa-check"></i> Save</button>' +
            '</div>' +
        '</div>';

        $card.hide().after(editHtml);

        // Priority pill click
        $(document).off('click.editPriority').on('click.editPriority', '.edit-priority-pills .priority-pill', function() {
            $('.edit-priority-pills .priority-pill').removeClass('active');
            $(this).addClass('active');
        });
        // Status pill click
        $(document).off('click.editStatus').on('click.editStatus', '.edit-status-pills .status-pill', function() {
            $('.edit-status-pills .status-pill').removeClass('active');
            $(this).addClass('active');
        });

        // Add checklist item in edit form
        $(document).off('click.addEditCl').on('click.addEditCl', '.btn-add-edit-checklist', function() {
            var row = '<div class="todo-checklist-item-row">' +
                '<label style="display:flex;align-items:center;cursor:pointer;margin:0;">' +
                    '<input type="checkbox" class="edit-cl-check" style="margin-right:8px;width:16px;height:16px;accent-color:var(--todo-success);" />' +
                '</label>' +
                '<input type="text" class="edit-cl-title" placeholder="Checklist item..." />' +
                '<button type="button" class="remove-checklist-btn"><i class="fa fa-times"></i></button>' +
            '</div>';
            $(this).closest('.todo-checklist-builder').find('.edit-checklist-list').append(row);
            $(this).closest('.todo-checklist-builder').find('.edit-cl-title:last').focus();
        });

        // Enter key on checklist input in edit form — auto-add new row
        $(document).off('keydown.editClEnter').on('keydown.editClEnter', '.edit-checklist-list .edit-cl-title', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('.todo-checklist-builder').find('.btn-add-edit-checklist').trigger('click');
            }
        });

        // Cancel
        $(document).off('click.cancelEdit').on('click.cancelEdit', '.btn-cancel-edit', function() {
            var $form = $(this).closest('.todo-edit-form');
            $form.prev('.todo-task-card').show();
            $form.remove();
        });

        // Save
        $(document).off('click.saveEdit').on('click.saveEdit', '.btn-save-edit', function() {
            var $form = $(this).closest('.todo-edit-form');
            var editId = $form.data('edit-id');
            var editData = {
                id:          editId,
                title:       $form.find('.edit-title').val().trim(),
                description: $form.find('.edit-desc').val().trim(),
                category_id: $form.find('.edit-category').val(),
                due_date:    $form.find('.edit-due-date').val(),
                priority:    $form.find('.edit-priority-pills .priority-pill.active').data('p'),
                status:      $form.find('.edit-status-pills .status-pill.active').data('s'),
            };

            // Gather assignees
            var selAssignees = $form.find('.edit-assignees').val() || [];
            selAssignees.forEach(function(id, i) {
                editData['assignee_ids[' + i + ']'] = id;
            });
            if (selAssignees.length === 0) editData['assignee_ids'] = '';

            // Gather roles
            var selRoles = $form.find('.edit-roles').val() || [];
            selRoles.forEach(function(id, i) {
                editData['role_ids[' + i + ']'] = id;
            });
            if (selRoles.length === 0) editData['role_ids'] = '';

            // Gather checklist items from edit form
            var clItems = [];
            $form.find('.edit-checklist-list .todo-checklist-item-row').each(function() {
                var clTitle   = $(this).find('.edit-cl-title').val().trim();
                var clChecked = $(this).find('.edit-cl-check').is(':checked') ? 1 : 0;
                if (clTitle) {
                    clItems.push({ title: clTitle, is_checked: clChecked });
                }
            });

            // Send checklist as indexed array with title/is_checked
            if (clItems.length > 0) {
                clItems.forEach(function(item, idx) {
                    editData['checklist[' + idx + '][title]'] = item.title;
                    editData['checklist[' + idx + '][is_checked]'] = item.is_checked;
                });
            } else {
                // Send empty array to clear checklist
                editData['checklist'] = '';
            }

            ajaxPost('ajax_update_task', editData, function(res) {
                if (res.success) {
                    $form.remove();
                    loadTasks();
                }
            });
        });
    }

    // ── Checklist Operations ──
    function toggleChecklistItem(id, newState, $el) {
        $el.toggleClass('checked');
        ajaxPost('ajax_toggle_checklist', { id: id, is_checked: newState }, function(res) {
            // Update progress bar
            var $checklist = $el.closest('.todo-task-checklist');
            var total = $checklist.find('.todo-cl-check').length;
            var done  = $checklist.find('.todo-cl-check.checked').length;
            var pct   = total > 0 ? Math.round((done / total) * 100) : 0;
            updateChecklistProgress($checklist, total, done, pct);
        });
    }

    function addChecklistItemToTask(taskId, title, $row) {
        ajaxPost('ajax_add_checklist_item', { task_id: taskId, title: title }, function(res) {
            if (res.success) {
                var newItem = '<div class="todo-cl-item" data-cl-id="' + res.id + '">' +
                    '<span class="todo-cl-drag-handle" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></span>' +
                    '<div class="todo-cl-check" data-cl-id="' + res.id + '"></div>' +
                    '<span class="todo-cl-text">' + escHtml(title) + '</span>' +
                    '<button class="todo-cl-delete" title="Remove"><i class="fa fa-times"></i></button>' +
                '</div>';
                $row.before(newItem);

                // Update progress
                var $checklist = $row.closest('.todo-task-checklist');
                var total = $checklist.find('.todo-cl-check').length;
                var done  = $checklist.find('.todo-cl-check.checked').length;
                var pct   = total > 0 ? Math.round((done / total) * 100) : 0;
                updateChecklistProgress($checklist, total, done, pct);

                // Refresh sortable so new item is draggable
                var $container = $row.closest('.todo-task-checklist-items');
                if ($container.data('ui-sortable')) {
                    $container.sortable('refresh');
                } else {
                    initChecklistSortable();
                }
            }
        });
    }

    function deleteChecklistItem(id, $item) {
        ajaxPost('ajax_delete_checklist_item', { id: id }, function(res) {
            if (res.success) {
                var $checklist = $item.closest('.todo-task-checklist');
                $item.remove();
                var total = $checklist.find('.todo-cl-check').length;
                var done  = $checklist.find('.todo-cl-check.checked').length;
                var pct   = total > 0 ? Math.round((done / total) * 100) : 0;
                updateChecklistProgress($checklist, total, done, pct);
            }
        });
    }

    // Helper: update or create/remove progress bar in checklist
    function updateChecklistProgress($checklist, total, done, pct) {
        var $bar = $checklist.find('.todo-card-progress-bar');
        if (total > 0) {
            if ($bar.length === 0) {
                // Create progress bar before the checklist items
                $checklist.find('.todo-task-checklist-items').before(
                    '<div class="todo-card-progress-bar"><div class="todo-card-progress-fill" style="width:' + pct + '%"></div></div>'
                );
            } else {
                $bar.find('.todo-card-progress-fill').css('width', pct + '%');
            }
        } else {
            // No items left — remove the progress bar
            $bar.remove();
        }
    }

    // ── Category Operations ──
    function addCategory(name, color) {
        ajaxPost('ajax_add_category', { name: name, color: color }, function(res) {
            if (res.success) {
                $('#new-cat-name').val('');
                refreshCategories();
                // Also refresh category dropdown in create form
                var c = res.category;
                $('#create-category').append('<option value="' + c.id + '">' + escHtml(c.name) + '</option>');
            }
        });
    }

    function deleteCategory(id) {
        ajaxPost('ajax_delete_category', { id: id }, function(res) {
            if (res.success) {
                $('[data-cat-id="' + id + '"]').remove();
                $('#create-category option[value="' + id + '"]').remove();
                if (currentFilter.category_id == id) {
                    currentFilter.category_id = '';
                }
                loadTasks();
            }
        });
    }

    function refreshCategories() {
        // Reload page to refresh sidebar categories
        // In a full SPA this would be AJAX, but for simplicity we reload the task list
        loadTasks();
    }

    // ── Checklist builder in create form ──
    function addChecklistRow(container) {
        var row = '<div class="todo-checklist-item-row">' +
            '<input type="text" placeholder="Checklist item..." />' +
            '<button type="button" class="remove-checklist-btn"><i class="fa fa-times"></i></button>' +
        '</div>';
        $(container).append(row);
        $(container).find('input:last').focus();
    }

    // ── Copy Task to Clipboard (WhatsApp-friendly) ──
    function copyTaskToClipboard($card, $btn) {
        var title  = $card.find('.todo-task-title').text().trim();
        var desc   = $card.find('.todo-task-desc').text().trim();

        // Category
        var category = $card.find('.todo-task-category-tag').text().trim();

        // Priority from card class
        var priority = 1;
        var priorityEmojis = {1:'🟢 Low', 2:'🔵 Medium', 3:'🟠 High', 4:'🔴 Urgent'};
        [1,2,3,4].forEach(function(p) { if ($card.hasClass('priority-' + p)) priority = p; });

        // Status
        var statusText = $card.find('.status-badge').text().trim();
        var statusEmojis = {'Pending':'🕐 Pending', 'In Progress':'🔄 In Progress', 'Completed':'✅ Completed'};
        var statusLine = statusEmojis[statusText] || ('📋 ' + statusText);

        // Due date
        var dueText = $card.find('.todo-task-meta-item').text().trim();
        var isOverdue = $card.find('.todo-task-meta-item.overdue').length > 0;

        // Checklist items
        var checklistItems = [];
        var clTotal = 0, clDone = 0;
        $card.find('.todo-cl-item').each(function() {
            var itemText = $(this).find('.todo-cl-text').text().trim();
            var isDone = $(this).find('.todo-cl-check').hasClass('checked');
            if (itemText) {
                checklistItems.push({ text: itemText, done: isDone });
                clTotal++;
                if (isDone) clDone++;
            }
        });

        // ── Build WhatsApp-friendly text ──
        var lines = [];

        // Header
        lines.push('📌 *' + title + '*');
        if (desc) {
            lines.push(desc);
        }
        lines.push('');

        // Meta details
        if (category) {
            lines.push('🏷️ Category: ' + category);
        }
        // Created by
        var staffName = $card.attr('data-staff-name') || '';
        if (staffName) {
            lines.push('👤 Created by: ' + staffName);
        }
        // Assigned staff
        var assignedStaff = [];
        $card.find('.todo-assign-tag.staff-tag').each(function() {
            assignedStaff.push($(this).text().trim());
        });
        if (assignedStaff.length > 0) {
            lines.push('👥 Assigned to: ' + assignedStaff.join(', '));
        }
        // Assigned roles
        var assignedRoles = [];
        $card.find('.todo-assign-tag.role-tag').each(function() {
            assignedRoles.push($(this).text().trim());
        });
        if (assignedRoles.length > 0) {
            lines.push('🏢 Roles: ' + assignedRoles.join(', '));
        }
        lines.push('⚡ Priority: ' + priorityEmojis[priority]);
        lines.push('📊 Status: ' + statusLine);
        if (dueText) {
            lines.push((isOverdue ? '⚠️' : '📅') + ' Due: ' + dueText);
        }

        // Overdue alert with extra days
        if (isOverdue) {
            var todayD = new Date(); todayD.setHours(0,0,0,0);
            var rawDue = $card.attr('data-due-date') || '';
            var daysDiff = 0;
            if (rawDue) {
                var parsedDue = new Date(rawDue + 'T00:00:00');
                daysDiff = Math.floor((todayD - parsedDue) / 86400000);
            }
            if (daysDiff > 0) {
                lines.push('');
                lines.push('🚨 *OVERDUE by ' + daysDiff + ' day' + (daysDiff > 1 ? 's' : '') + '!* ⏰');
                lines.push('⚠️ This task needs immediate attention!');
            }
        }

        // Remarks (multiple)
        var remarkItems = [];
        $card.find('.todo-remark-item').each(function() {
            var txt = $(this).find('.todo-remark-text').text().trim();
            var author = $(this).find('.todo-remark-author').text().trim();
            var time = $(this).find('.todo-remark-time').text().trim();
            if (txt) remarkItems.push({ text: txt, author: author, time: time });
        });
        if (remarkItems.length > 0) {
            lines.push('');
            lines.push('━━━━━━━━━━━━━━━━━━━━');
            lines.push('💬 *Remarks (' + remarkItems.length + '):*');
            remarkItems.forEach(function(rm) {
                var meta = '';
                if (rm.author) meta += rm.author;
                if (rm.time) meta += (meta ? ' • ' : '') + rm.time;
                lines.push('● ' + rm.text + (meta ? ' _(' + meta + ')_' : ''));
            });
        }

        // Checklist
        if (checklistItems.length > 0) {
            var pct = Math.round((clDone / clTotal) * 100);
            lines.push('');
            lines.push('━━━━━━━━━━━━━━━━━━━━');
            lines.push('*Checklist Progress: ' + clDone + '/' + clTotal + ' (' + pct + '%)*');

            // Visual progress bar
            var filled = Math.round(pct / 10);
            var empty = 10 - filled;
            var bar = '';
            for (var i = 0; i < filled; i++) bar += '🟩';
            for (var j = 0; j < empty; j++) bar += '⬜';
            lines.push(bar + ' ' + pct + '%');
            lines.push('');

            checklistItems.forEach(function(item) {
                lines.push((item.done ? '✅' : '⬜') + ' ' + (item.done ? '~' + item.text + '~' : item.text));
            });
        }

        lines.push('');
        lines.push('━━━━━━━━━━━━━━━━━━━━');

        // Timestamp
        var now = new Date();
        var timeStr = now.toLocaleDateString('en-US', {day:'numeric',month:'short',year:'numeric'}) + ' ' +
                      now.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit',hour12:true});
        lines.push('🕐 Report generated: ' + timeStr);

        var fullText = lines.join('\n');

        // Copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(fullText).then(function() {
                showCopyFeedback($btn);
            }).catch(function() {
                fallbackCopy(fullText, $btn);
            });
        } else {
            fallbackCopy(fullText, $btn);
        }
    }

    function fallbackCopy(text, $btn) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopyFeedback($btn);
        } catch(e) {
            alert('Failed to copy. Please copy manually.');
        }
        document.body.removeChild(ta);
    }

    function showCopyFeedback($btn) {
        // Change icon briefly
        var $icon = $btn.find('i');
        $icon.removeClass('fa-copy').addClass('fa-check');
        $btn.addClass('copy-success');

        // Show toast notification
        var $toast = $('<div class="todo-copy-toast">✅ Copied to clipboard!</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.addClass('show'); }, 10);
        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() { $toast.remove(); }, 300);
        }, 2000);

        // Reset button after delay
        setTimeout(function() {
            $icon.removeClass('fa-check').addClass('fa-copy');
            $btn.removeClass('copy-success');
        }, 1500);
    }

    // ── Remarks Operations ──
    function addRemark(taskId, text, $section) {
        ajaxPost('ajax_add_remark', { task_id: taskId, remark: text }, function(res) {
            if (res.success && res.remark) {
                var rm = res.remark;
                var html = '<div class="todo-remark-item" data-remark-id="' + rm.id + '" style="animation:todoSlideDown .3s ease;">' +
                    '<div class="todo-remark-content">' +
                        '<span class="todo-remark-text">' + escHtml(rm.remark) + '</span>' +
                        '<span class="todo-remark-meta">' +
                            '<span class="todo-remark-author">' + escHtml(rm.staff_name || '') + '</span>' +
                            '<span class="todo-remark-time">' + formatDateTime(rm.datecreated) + '</span>' +
                        '</span>' +
                    '</div>' +
                    '<button class="todo-remark-delete" data-remark-id="' + rm.id + '" title="Delete"><i class="fa fa-times"></i></button>' +
                '</div>';
                $section.find('.todo-remarks-list').prepend(html);
            }
        });
    }

    function deleteRemark(remarkId, $item) {
        ajaxPost('ajax_delete_remark', { id: remarkId }, function(res) {
            if (res.success) {
                $item.fadeOut(200, function() { $(this).remove(); });
            }
        });
    }

    // ── Utilities ──
    function formatDateTime(dtStr) {
        if (!dtStr) return '';
        var d = new Date(dtStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dtStr;
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var h = d.getHours(), m = d.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        var min = ('0' + m).slice(-2);
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + h + ':' + min + ' ' + ampm;
    }

    /**
     * Auto-capitalize: first letter of the first word only
     */
    function ucfirst(str) {
        if (!str) return str;
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    function bindAutoCapitalize() {
        var sel = [
            '#create-title', '#create-desc',
            '.edit-title', '.edit-desc',
            '#tpl-name', '#tpl-desc',
            '.todo-remark-input',
            '.todo-checklist-item-row input[type="text"]',
            '.todo-cl-add-row input[type="text"]',
            '.edit-cl-title'
        ].join(',');
        $(document).on('blur', sel, function() {
            var v = $(this).val();
            if (v && v.length > 0) {
                $(this).val(ucfirst(v));
            }
        });
    }
    function escHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
    function escAttr(str) {
        if (!str) return '';
        return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function hexToRgba(hex, alpha) {
        if (!hex) return 'rgba(99,102,241,' + alpha + ')';
        hex = hex.replace('#', '');
        var r = parseInt(hex.substring(0,2), 16);
        var g = parseInt(hex.substring(2,4), 16);
        var b = parseInt(hex.substring(4,6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }
    function formatDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr + 'T00:00:00');
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }
    function todayDate() {
        var d = new Date();
        var m = ('0' + (d.getMonth() + 1)).slice(-2);
        var day = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + m + '-' + day;
    }

    // ═══════════════════════════════════════════
    //  TEMPLATES SYSTEM
    // ═══════════════════════════════════════════

    function switchTab(tab) {
        if (tab === 'templates') {
            $('#todo-app').hide();
            $('#todo-templates-panel').show();
            $('.rpt-page-tab[data-tab="tasks"]').removeClass('active');
            $('.rpt-page-tab[data-tab="templates"]').addClass('active');
            loadTemplates();
        } else {
            $('#todo-templates-panel').hide();
            $('#todo-app').show();
            $('.rpt-page-tab[data-tab="templates"]').removeClass('active');
            $('.rpt-page-tab[data-tab="tasks"]').addClass('active');
        }
    }

    function bindTemplateEvents() {
        // Toggle template form
        $(document).on('click', '#btn-toggle-tpl-form', function() {
            resetTplForm();
            $('#todo-tpl-form').toggleClass('show');
        });
        $(document).on('click', '#btn-cancel-tpl', function() {
            $('#todo-tpl-form').removeClass('show');
            resetTplForm();
        });

        // Recurring checkbox
        $(document).on('change', '#tpl-is-recurring', function() {
            $('.tpl-recurring-opts').toggle($(this).is(':checked'));
        });

        // Add checklist in template form
        $(document).on('click', '#add-tpl-checklist', function() {
            addChecklistRow('#tpl-checklist-list');
        });

        // Save template
        $(document).on('click', '#btn-save-tpl', function() { saveTpl(); });

        // Use Template dropdown toggle
        $(document).on('click', '#btn-use-template', function(e) {
            e.stopPropagation();
            var $dd = $(this).closest('.todo-tpl-dropdown');
            $dd.toggleClass('open');
            if ($dd.hasClass('open')) loadTplDropdown();
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.todo-tpl-dropdown').length) {
                $('.todo-tpl-dropdown').removeClass('open');
            }
        });

        // Use template from dropdown
        $(document).on('click', '.todo-tpl-dd-item', function() {
            var tplId = $(this).data('tpl-id');
            ajaxPost('ajax_use_template', { template_id: tplId }, function(res) {
                if (res.success) {
                    $('.todo-tpl-dropdown').removeClass('open');
                    loadTasks();
                    showToast('Task created from template!');
                }
            });
        });

        // Use template from card
        $(document).on('click', '.btn-use-tpl', function() {
            var tplId = $(this).closest('.todo-tpl-card').data('tpl-id');
            ajaxPost('ajax_use_template', { template_id: tplId }, function(res) {
                if (res.success) {
                    showToast('Task created from template!');
                }
            });
        });

        // Edit template
        $(document).on('click', '.btn-edit-tpl', function() {
            var tplId = $(this).closest('.todo-tpl-card').data('tpl-id');
            editTpl(tplId);
        });

        // Delete template
        $(document).on('click', '.btn-del-tpl', function() {
            if (!confirm('Delete this template?')) return;
            var tplId = $(this).closest('.todo-tpl-card').data('tpl-id');
            ajaxPost('ajax_delete_template', { id: tplId }, function(res) {
                if (res.success) loadTemplates();
            });
        });

        // Toggle active/inactive
        $(document).on('click', '.btn-toggle-tpl', function() {
            var $card = $(this).closest('.todo-tpl-card');
            var tplId = $card.data('tpl-id');
            var newActive = $card.hasClass('inactive') ? 1 : 0;
            ajaxPost('ajax_update_template', { id: tplId, is_active: newActive, name: $card.find('.todo-tpl-card-title').text().trim() }, function(res) {
                if (res.success) loadTemplates();
            });
        });
    }

    function loadTemplates() {
        ajaxPost('ajax_get_templates', {}, function(res) {
            if (res.success) renderTemplates(res.templates);
        });
    }

    function renderTemplates(templates) {
        var $grid = $('#todo-tpl-grid');
        $grid.empty();

        if (!templates || templates.length === 0) {
            $grid.html('<div class="todo-empty" style="grid-column:1/-1;"><i class="fas fa-clipboard-list"></i><h3>No templates yet</h3><p>Create a template to quickly generate tasks or set up recurring schedules.</p></div>');
            return;
        }

        var prLabels = {1:'Low', 2:'Medium', 3:'High', 4:'Urgent'};
        var recLabels = {day:'Daily', week:'Weekly', month:'Monthly'};

        templates.forEach(function(tpl) {
            var badges = '';
            if (tpl.is_recurring == 1) {
                var freq = (tpl.repeat_every > 1 ? 'Every ' + tpl.repeat_every + ' ' : '') + (recLabels[tpl.recurring_type] || tpl.recurring_type);
                badges += '<span class="todo-tpl-badge recurring"><i class="fa fa-sync-alt"></i> ' + freq + '</span>';
            }
            badges += '<span class="todo-tpl-badge ' + (tpl.is_active == 1 ? 'active-badge' : 'paused-badge') + '">' + (tpl.is_active == 1 ? 'Active' : 'Paused') + '</span>';

            var meta = '';
            if (tpl.category_name) meta += '<span class="todo-tpl-meta-tag"><i class="fa fa-tag"></i> ' + escHtml(tpl.category_name) + '</span>';
            meta += '<span class="todo-tpl-meta-tag"><i class="fa fa-flag"></i> ' + prLabels[tpl.priority || 1] + '</span>';
            if (tpl.due_days) meta += '<span class="todo-tpl-meta-tag"><i class="fa fa-calendar"></i> Due in ' + tpl.due_days + ' days</span>';

            var checklist = [];
            try { checklist = JSON.parse(tpl.checklist_json || '[]'); } catch(e) {}
            if (checklist.length > 0) meta += '<span class="todo-tpl-meta-tag"><i class="fa fa-check-square"></i> ' + checklist.length + ' items</span>';

            var schedule = '';
            if (tpl.is_recurring == 1) {
                schedule = '<div class="todo-tpl-card-schedule"><i class="fa fa-clock"></i> ';
                if (tpl.next_run_date) schedule += 'Next: ' + formatDate(tpl.next_run_date);
                if (tpl.last_recurring_date) schedule += ' &middot; Last: ' + formatDate(tpl.last_recurring_date);
                schedule += '</div>';
            }

            var html = '<div class="todo-tpl-card' + (tpl.is_active != 1 ? ' inactive' : '') + '" data-tpl-id="' + tpl.id + '">' +
                '<div class="todo-tpl-card-header">' +
                    '<div class="todo-tpl-card-title">' + escHtml(tpl.name) + '</div>' +
                    '<div class="todo-tpl-card-badges">' + badges + '</div>' +
                '</div>' +
                (tpl.description ? '<div class="todo-tpl-card-desc">' + escHtml(tpl.description) + '</div>' : '') +
                '<div class="todo-tpl-card-meta">' + meta + '</div>' +
                schedule +
                '<div class="todo-tpl-card-actions">' +
                    '<button class="btn-use-tpl" title="Create task now"><i class="fa fa-play"></i> Use</button>' +
                    '<button class="btn-edit-tpl" title="Edit"><i class="fa fa-pencil"></i> Edit</button>' +
                    '<button class="btn-toggle-tpl" title="' + (tpl.is_active == 1 ? 'Pause' : 'Activate') + '"><i class="fa fa-' + (tpl.is_active == 1 ? 'pause' : 'play') + '"></i></button>' +
                    '<button class="btn-del-tpl" title="Delete"><i class="fa fa-trash"></i></button>' +
                '</div>' +
            '</div>';

            $grid.append(html);
        });
    }

    function loadTplDropdown() {
        ajaxPost('ajax_get_templates', {}, function(res) {
            var $menu = $('#tpl-dropdown-menu');
            $menu.empty();
            if (!res.success || !res.templates || res.templates.length === 0) {
                $menu.html('<div class="todo-tpl-dd-empty">No templates available.<br>Create one in the Templates tab.</div>');
                return;
            }
            res.templates.forEach(function(tpl) {
                if (tpl.is_active != 1) return;
                var badge = tpl.is_recurring == 1 ? '<span class="tpl-dd-badge recurring"><i class="fa fa-sync-alt"></i></span>' : '';
                $menu.append('<div class="todo-tpl-dd-item" data-tpl-id="' + tpl.id + '"><span class="tpl-dd-name">' + escHtml(tpl.name) + '</span>' + badge + '</div>');
            });
            if ($menu.find('.todo-tpl-dd-item').length === 0) {
                $menu.html('<div class="todo-tpl-dd-empty">No active templates available.</div>');
            }
        });
    }

    function saveTpl() {
        var name = $('#tpl-name').val().trim();
        if (!name) { $('#tpl-name').focus(); return; }

        // Gather checklist
        var cl = [];
        $('#tpl-checklist-list .todo-checklist-item-row input[type="text"]').each(function() {
            var v = $(this).val().trim();
            if (v) cl.push(v);
        });

        var data = {
            name: name,
            description: $('#tpl-desc').val().trim(),
            category_id: $('#tpl-category').val(),
            priority: $('#tpl-priority').val(),
            due_days: $('#tpl-due-days').val() || '',
            checklist_json: JSON.stringify(cl),
            assignee_ids_json: JSON.stringify($('#tpl-assignees').val() || []),
            role_ids_json: JSON.stringify($('#tpl-roles').val() || []),
            is_recurring: $('#tpl-is-recurring').is(':checked') ? 1 : 0,
            recurring_type: $('#tpl-recurring-type').val(),
            repeat_every: $('#tpl-repeat-every').val() || 1,
        };

        var editId = $('#tpl-edit-id').val();
        if (editId) {
            data.id = editId;
            ajaxPost('ajax_update_template', data, function(res) {
                if (res.success) {
                    resetTplForm();
                    $('#todo-tpl-form').removeClass('show');
                    loadTemplates();
                }
            });
        } else {
            ajaxPost('ajax_add_template', data, function(res) {
                if (res.success) {
                    resetTplForm();
                    $('#todo-tpl-form').removeClass('show');
                    loadTemplates();
                }
            });
        }
    }

    function editTpl(tplId) {
        ajaxPost('ajax_get_templates', {}, function(res) {
            if (!res.success) return;
            var tpl = null;
            res.templates.forEach(function(t) { if (t.id == tplId) tpl = t; });
            if (!tpl) return;

            resetTplForm();
            $('#tpl-edit-id').val(tpl.id);
            $('#tpl-name').val(tpl.name);
            $('#tpl-desc').val(tpl.description || '');
            $('#tpl-category').val(tpl.category_id || '');
            $('#tpl-priority').val(tpl.priority || 1);
            $('#tpl-due-days').val(tpl.due_days || '');

            // Assignees
            try { var aIds = JSON.parse(tpl.assignee_ids_json || '[]'); $('#tpl-assignees').val(aIds); } catch(e) {}
            // Roles
            try { var rIds = JSON.parse(tpl.role_ids_json || '[]'); $('#tpl-roles').val(rIds); } catch(e) {}

            // Checklist
            try {
                var cl = JSON.parse(tpl.checklist_json || '[]');
                cl.forEach(function(item) {
                    var row = '<div class="todo-checklist-item-row">' +
                        '<input type="text" value="' + escAttr(item) + '" placeholder="Checklist item..." />' +
                        '<button type="button" class="remove-checklist-btn"><i class="fa fa-times"></i></button>' +
                    '</div>';
                    $('#tpl-checklist-list').append(row);
                });
            } catch(e) {}

            // Recurring
            if (tpl.is_recurring == 1) {
                $('#tpl-is-recurring').prop('checked', true);
                $('.tpl-recurring-opts').show();
                $('#tpl-repeat-every').val(tpl.repeat_every || 1);
                $('#tpl-recurring-type').val(tpl.recurring_type || 'day');
            }

            $('#btn-save-tpl span').text('Update Template');
            $('#todo-tpl-form').addClass('show');
            window.scrollTo({ top: $('#todo-tpl-form').offset().top - 80, behavior: 'smooth' });
        });
    }

    function resetTplForm() {
        $('#tpl-edit-id').val('');
        $('#tpl-name').val('');
        $('#tpl-desc').val('');
        $('#tpl-category').val('');
        $('#tpl-priority').val('3');
        $('#tpl-due-days').val('');
        $('#tpl-checklist-list').empty();
        $('#tpl-assignees').val([]);
        $('#tpl-roles').val([]);
        $('#tpl-is-recurring').prop('checked', false);
        $('.tpl-recurring-opts').hide();
        $('#tpl-repeat-every').val(1);
        $('#tpl-recurring-type').val('day');
        $('#btn-save-tpl span').text('Save Template');
    }

    function showToast(msg) {
        var $existing = $('.todo-copy-toast');
        $existing.remove();
        var $toast = $('<div class="todo-copy-toast">' + msg + '</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.css({ opacity: 1, transform: 'translateX(-50%) translateY(0)' }); }, 10);
        setTimeout(function() { $toast.css({ opacity: 0, transform: 'translateX(-50%) translateY(20px)' }); setTimeout(function() { $toast.remove(); }, 300); }, 2500);
    }

    return { init: init, loadTasks: loadTasks, switchTab: switchTab };
})();

$(function() {
    TodoApp.init();
});
