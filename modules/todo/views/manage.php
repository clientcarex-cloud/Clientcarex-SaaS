<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url('todo', 'assets/css/todo.css'); ?>?v=<?php echo time(); ?>">

<div id="wrapper">
  <div class="content" style="padding:0 !important;margin:0 !important;">

      <!-- ═══ Page Tabs ═══ -->
      <div class="rpt-page-tabs">
        <a href="#" class="rpt-page-tab active" data-tab="tasks" onclick="event.preventDefault();TodoApp.switchTab('tasks');">
          <i class="fa-solid fa-list-check"></i> Tasks
        </a>
        <a href="#" class="rpt-page-tab" data-tab="templates" onclick="event.preventDefault();TodoApp.switchTab('templates');">
          <i class="fa-solid fa-clipboard-list"></i> Templates
        </a>
        <?php if (is_admin()) { ?>
        <a href="<?php echo admin_url('todo/reports'); ?>" class="rpt-page-tab">
          <i class="fas fa-chart-bar"></i> Reports
        </a>
        <?php } ?>
      </div>

    <div id="todo-app">

      <!-- ═══ Stats ═══ -->
      <div class="todo-stats">
        <div class="todo-stat-card" data-filter-status="all">
          <div class="todo-stat-icon ts-total"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></div>
          <div class="todo-stat-info">
            <div class="todo-stat-value" id="stat-total"><?php echo $stats['total']; ?></div>
            <div class="todo-stat-label">Total</div>
          </div>
        </div>
        <div class="todo-stat-card" data-filter-status="0">
          <div class="todo-stat-icon ts-pending"><i class="far fa-clock"></i></div>
          <div class="todo-stat-info">
            <div class="todo-stat-value" id="stat-pending"><?php echo $stats['pending']; ?></div>
            <div class="todo-stat-label">Pending</div>
          </div>
        </div>
        <div class="todo-stat-card" data-filter-status="1">
          <div class="todo-stat-icon ts-progress"><i class="fas fa-sync-alt"></i></div>
          <div class="todo-stat-info">
            <div class="todo-stat-value" id="stat-progress"><?php echo $stats['in_progress']; ?></div>
            <div class="todo-stat-label">In Progress</div>
          </div>
        </div>
        <div class="todo-stat-card" data-filter-status="2">
          <div class="todo-stat-icon ts-done"><i class="fas fa-check-circle"></i></div>
          <div class="todo-stat-info">
            <div class="todo-stat-value" id="stat-done"><?php echo $stats['completed']; ?></div>
            <div class="todo-stat-label">Completed</div>
          </div>
        </div>
        <div class="todo-stat-card" data-filter-status="overdue">
          <div class="todo-stat-icon ts-overdue"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="todo-stat-info">
            <div class="todo-stat-value" id="stat-overdue"><?php echo $stats['overdue']; ?></div>
            <div class="todo-stat-label">Overdue</div>
          </div>
        </div>
        <div class="todo-stat-card" data-filter-status="due_today">
          <div class="todo-stat-icon ts-today"><i class="far fa-calendar-check"></i></div>
          <div class="todo-stat-info">
            <div class="todo-stat-value" id="stat-today"><?php echo $stats['due_today']; ?></div>
            <div class="todo-stat-label">Due Today</div>
          </div>
        </div>
      </div>

      <!-- ═══ Create Form (inline, hidden by default) ═══ -->
      <div id="todo-create-form" class="todo-create-form">
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Task Title *</label>
            <input class="todo-form-input" id="create-title" placeholder="What needs to be done?" />
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Description</label>
            <textarea class="todo-form-input" id="create-desc" rows="2" placeholder="Add details..."></textarea>
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group">
            <label class="todo-form-label">Category</label>
            <select class="todo-form-input" id="create-category">
              <option value="">No Category</option>
              <?php foreach ($categories as $cat) { ?>
                <option value="<?php echo $cat['id']; ?>"<?php echo (strtolower(trim($cat['name'])) === 'work') ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="todo-form-group">
            <label class="todo-form-label">Priority</label>
            <select class="todo-form-input" id="create-priority">
              <option value="1">Low</option>
              <option value="2">Medium</option>
              <option value="3" selected>High</option>
              <option value="4">Urgent</option>
            </select>
          </div>
          <div class="todo-form-group">
            <label class="todo-form-label">Due Date</label>
            <input type="date" class="todo-form-input" id="create-due-date" value="<?php echo date('Y-m-d'); ?>" />
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Assign To (Staff)</label>
            <select class="todo-form-input" id="create-assignees" multiple>
              <?php foreach ($staff_list as $s) { ?>
                <option value="<?php echo $s['staffid']; ?>"><?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Assign Roles</label>
            <select class="todo-form-input" id="create-roles" multiple>
              <?php foreach ($roles_list as $r) { ?>
                <option value="<?php echo $r['roleid']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
              <?php } ?>
            </select>
          </div>
        </div>

        <!-- Checklist Builder -->
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Checklist</label>
            <div class="todo-checklist-builder">
              <div id="create-checklist-list"></div>
              <button type="button" class="add-checklist-trigger" id="add-create-checklist">
                <i class="fas fa-plus-circle"></i> Add checklist item
              </button>
            </div>
          </div>
        </div>

        <div class="todo-form-row" style="justify-content:flex-end;gap:8px;">
          <button class="todo-btn todo-btn-sm todo-btn-ghost" onclick="$('#todo-create-form').removeClass('show');">Cancel</button>
          <button class="todo-btn todo-btn-sm todo-btn-primary" id="btn-save-task"><i class="fa fa-check"></i> Create Task</button>
        </div>
      </div>

      <!-- ═══ Layout: Sidebar + Tasks ═══ -->
      <div class="todo-layout">

        <!-- ── Sidebar ── -->
        <div class="todo-sidebar">

          <!-- Status Filters -->
          <div class="todo-filter-group">
            <div class="todo-sidebar-title">Status</div>
            <button class="todo-filter-btn active" data-status="all">
              <span><i class="fas fa-list"></i> All Tasks</span>
              <span class="filter-count"><?php echo $stats['total']; ?></span>
            </button>
            <button class="todo-filter-btn" data-status="my_tasks">
              <span><i class="fas fa-user"></i> My Tasks</span>
              <span class="filter-count" id="stat-my-tasks"><?php echo $stats['my_tasks']; ?></span>
            </button>
            <button class="todo-filter-btn" data-status="0">
              <span><i class="far fa-clock"></i> Pending</span>
              <span class="filter-count"><?php echo $stats['pending']; ?></span>
            </button>
            <button class="todo-filter-btn" data-status="1">
              <span><i class="fas fa-sync-alt"></i> In Progress</span>
              <span class="filter-count"><?php echo $stats['in_progress']; ?></span>
            </button>
            <button class="todo-filter-btn" data-status="2">
              <span><i class="fas fa-check"></i> Completed</span>
              <span class="filter-count"><?php echo $stats['completed']; ?></span>
            </button>
          </div>

          <!-- Priority Filters -->
          <div class="todo-filter-group">
            <div class="todo-sidebar-title">Priority</div>
            <button class="todo-filter-btn" data-priority="4">
              <span><i class="fas fa-bolt"></i> Urgent</span>
              <span class="filter-count" id="cnt-priority-4"><?php echo (int)($stats['priority_urgent'] ?? 0); ?></span>
            </button>
            <button class="todo-filter-btn" data-priority="3">
              <span><i class="fas fa-arrow-up"></i> High</span>
              <span class="filter-count" id="cnt-priority-3"><?php echo (int)($stats['priority_high'] ?? 0); ?></span>
            </button>
            <button class="todo-filter-btn" data-priority="2">
              <span><i class="fas fa-minus"></i> Medium</span>
              <span class="filter-count" id="cnt-priority-2"><?php echo (int)($stats['priority_medium'] ?? 0); ?></span>
            </button>
            <button class="todo-filter-btn" data-priority="1">
              <span><i class="fas fa-arrow-down"></i> Low</span>
              <span class="filter-count" id="cnt-priority-1"><?php echo (int)($stats['priority_low'] ?? 0); ?></span>
            </button>
          </div>

          <!-- Categories -->
          <div class="todo-filter-group">
            <div class="todo-sidebar-title">Categories</div>
            <?php
              $catCounts = isset($stats['cat_counts']) ? $stats['cat_counts'] : [];
              foreach ($categories as $cat) {
                $catId = (int)$cat['id'];
                $catCount = isset($catCounts[$catId]) ? (int)$catCounts[$catId] : 0;
            ?>
              <div class="todo-cat-item" data-cat-id="<?php echo $catId; ?>">
                <span class="todo-cat-dot" style="background:<?php echo $cat['color']; ?>"></span>
                <span class="todo-cat-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                <span class="filter-count todo-cat-count" id="cnt-cat-<?php echo $catId; ?>"><?php echo $catCount; ?></span>
                <span class="todo-cat-actions">
                  <button class="btn-delete-cat" data-cat-id="<?php echo $catId; ?>"><i class="fa fa-trash"></i></button>
                </span>
              </div>
            <?php } ?>

            <!-- Add Category Inline -->
            <div class="todo-add-cat-row">
              <input type="text" id="new-cat-name" placeholder="New category..." />
              <input type="color" id="new-cat-color" value="#6366f1" />
              <button id="btn-add-cat"><i class="fa fa-plus"></i></button>
            </div>
          </div>

        </div>

        <!-- ── Main Content ── -->
        <div class="todo-main">

          <!-- Toolbar: Search + Date Range + New Task -->
          <div class="todo-toolbar">
            <div class="todo-search-wrap">
              <i class="fa fa-search"></i>
              <input type="text" id="todo-search" placeholder="Search tasks..." />
            </div>
            <div class="todo-date-range">
              <input type="date" id="todo-date-from" class="todo-form-input todo-date-input" title="From date" />
              <span class="todo-date-sep">to</span>
              <input type="date" id="todo-date-to" class="todo-form-input todo-date-input" title="To date" />
            </div>
            <div class="todo-toolbar-actions">
              <div class="todo-tpl-dropdown">
                <button class="todo-btn todo-btn-ghost" id="btn-use-template"><i class="fa fa-clipboard-list"></i> Use Template</button>
                <div class="todo-tpl-dropdown-menu" id="tpl-dropdown-menu"></div>
              </div>
              <button class="todo-btn todo-btn-primary" id="btn-toggle-create">
                <i class="fa fa-plus"></i> New Task
              </button>
            </div>
          </div>

          <!-- Tasks List -->
          <div class="todo-tasks-list" id="todo-tasks-list">

            <?php if (empty($tasks)) { ?>
              <div class="todo-empty">
                <i class="fas fa-check-circle"></i>
                <h3>No tasks yet</h3>
                <p>Click "New Task" to create your first task!</p>
              </div>
            <?php } else {
              $priorityLabels  = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
              $priorityClasses = [1 => 'p-low', 2 => 'p-medium', 3 => 'p-high', 4 => 'p-urgent'];
              $statusLabels    = [0 => 'Pending', 1 => 'In Progress', 2 => 'Completed'];

              foreach ($tasks as $task) {
                $p = (int)($task['priority'] ?: 1);
                $isCompleted = (int)$task['status'] === 2;
                $cardClass = 'todo-task-card priority-' . $p . ($isCompleted ? ' completed' : '');
            ?>
              <div class="<?php echo $cardClass; ?>" data-task-id="<?php echo $task['id']; ?>" data-staff-name="<?php echo htmlspecialchars($task['staff_name'] ?? ''); ?>" data-due-date="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>">
                <div class="todo-task-top">
                  <div class="todo-task-check<?php echo $isCompleted ? ' checked' : ''; ?>"></div>
                  <div class="todo-task-content">
                    <div class="todo-task-title-row">
                      <div class="todo-task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                      <div class="todo-task-badges">
                        <?php if ($task['category_name']) {
                          $catColor = $task['category_color'] ?: '#6366f1';
                        ?>
                          <span class="todo-task-category-tag" style="background:<?php echo $catColor; ?>20;color:<?php echo $catColor; ?>;">
                            <i class="fa <?php echo $task['category_icon'] ?: 'fa-folder'; ?>"></i>
                            <?php echo htmlspecialchars($task['category_name']); ?>
                          </span>
                        <?php } ?>
                        <?php if ($task['due_date']) {
                          $today = new DateTime();
                          $today->setTime(0,0,0);
                          $due = new DateTime($task['due_date']);
                          $due->setTime(0,0,0);
                          $diff = (int)$today->diff($due)->format('%r%a');
                          $overdueClass = (!$isCompleted && $diff < 0) ? ' overdue' : '';
                          if ($diff === 0) { $dueLabel = 'Today'; }
                          elseif ($diff === 1) { $dueLabel = 'Tomorrow'; }
                          elseif ($diff < 0) { $dueLabel = _d($task['due_date']) . ' (Overdue)'; }
                          else { $dueLabel = _d($task['due_date']); }
                        ?>
                          <span class="todo-task-meta-item<?php echo $overdueClass; ?>">
                            <i class="fas fa-calendar-alt"></i> <?php echo $dueLabel; ?>
                          </span>
                        <?php } ?>
                        <span class="priority-badge <?php echo $priorityClasses[$p]; ?>"><?php echo $priorityLabels[$p]; ?></span>
                        <span class="status-badge s-<?php echo (int)$task['status']; ?>"><?php echo $statusLabels[(int)$task['status']]; ?></span>
                      </div>
                    </div>
                    <?php
                      // ── Score Indicator Strip ──
                      $scoreHtml = '';
                      if ($task['due_date']) {
                        $todayDt = new DateTime(); $todayDt->setTime(0,0,0);
                        $dueDt = new DateTime($task['due_date']); $dueDt->setTime(0,0,0);
                        $daysDiff = (int)$todayDt->diff($dueDt)->format('%r%a'); // positive = days left

                        if ($isCompleted) {
                          // Show earned score
                          $dateComp = isset($task['date_completed']) && $task['date_completed'] ? new DateTime($task['date_completed']) : $todayDt;
                          $dateComp->setTime(0,0,0);
                          $compDiff = (int)$dueDt->diff($dateComp)->format('%r%a'); // negative = early
                          $earned = 10; $label = '+10 completed';
                          if ($compDiff <= 0) {
                            $earlyDays = abs($compDiff);
                            if ($earlyDays >= 2) { $earned += 8; $label = '+18 early bonus!'; }
                            elseif ($earlyDays >= 1) { $earned += 5; $label = '+15 early bonus!'; }
                            if ($p >= 3) { $earned += 5; $label .= ' +priority'; }
                          } else {
                            $lateDays = $compDiff;
                            if ($lateDays <= 3) { $earned -= $lateDays; $label = '+' . $earned . ' (late -' . $lateDays . ')'; }
                            else { $earned -= 5; $label = '+' . $earned . ' (late penalty)'; }
                          }
                          $scoreHtml = '<div class="todo-score-strip score-earned"><i class="fas fa-star"></i> ' . $label . '</div>';
                        } elseif ($daysDiff < 0) {
                          // OVERDUE
                          $overdueDays = abs($daysDiff);
                          if ($overdueDays > 3) {
                            $scoreHtml = '<div class="todo-score-strip score-danger"><i class="fas fa-exclamation-triangle"></i> ' . $overdueDays . ' days overdue — penalty -5 + running -2/day</div>';
                          } else {
                            $scoreHtml = '<div class="todo-score-strip score-warning"><i class="fas fa-exclamation-circle"></i> ' . $overdueDays . ' day(s) overdue — complete now to limit penalty to -' . $overdueDays . '</div>';
                          }
                        } elseif ($daysDiff === 0) {
                          // DUE TODAY
                          $potential = 10 + ($p >= 3 ? 5 : 0);
                          $scoreHtml = '<div class="todo-score-strip score-today"><i class="fas fa-bolt"></i> Due today — complete now for +' . $potential . ' pts</div>';
                        } elseif ($daysDiff === 1) {
                          $potential = 10 + 5 + ($p >= 3 ? 5 : 0);
                          $scoreHtml = '<div class="todo-score-strip score-early"><i class="fas fa-rocket"></i> Complete today for +' . $potential . ' early bonus!</div>';
                        } elseif ($daysDiff >= 2) {
                          $potential = 10 + 5 + 3 + ($p >= 3 ? 5 : 0);
                          $scoreHtml = '<div class="todo-score-strip score-bonus"><i class="fas fa-trophy"></i> Complete today for +' . $potential . ' pts (max early bonus!)</div>';
                        }
                      }
                      echo $scoreHtml;
                    ?>
                    <?php if ($task['description']) { ?>
                      <div class="todo-task-desc"><?php echo htmlspecialchars($task['description']); ?></div>
                    <?php } ?>
                    <?php
                      $assignees = isset($task['assignees']) ? $task['assignees'] : [];
                      $assignedRoles = isset($task['assigned_roles']) ? $task['assigned_roles'] : [];
                      if (!empty($assignees) || !empty($assignedRoles)) {
                    ?>
                    <div class="todo-assignment-tags">
                      <?php foreach ($assignees as $a) { ?>
                        <span class="todo-assign-tag staff-tag"><i class="fa fa-user"></i> <?php echo htmlspecialchars($a['name']); ?></span>
                      <?php } ?>
                      <?php foreach ($assignedRoles as $r) { ?>
                        <span class="todo-assign-tag role-tag"><i class="fa fa-users"></i> <?php echo htmlspecialchars($r['name']); ?></span>
                      <?php } ?>
                    </div>
                    <?php } ?>
                  </div>
                  <button class="btn-copy-task" title="Copy for WhatsApp"><i class="fa fa-copy"></i></button>
                  <div class="todo-task-actions">
                    <button class="btn-edit-task" title="Edit"><i class="fa fa-pencil"></i></button>
                    <button class="btn-delete-task delete-btn" title="Delete"><i class="fa fa-trash"></i></button>
                  </div>
                </div>

                <?php
                  $clTotal = isset($task['checklist_total']) ? (int)$task['checklist_total'] : 0;
                  $clDone  = isset($task['checklist_done']) ? (int)$task['checklist_done'] : 0;
                  $clPct   = $clTotal > 0 ? round(($clDone / $clTotal) * 100) : 0;
                  $hasChecklist = !empty($task['checklist']);
                ?>
                <div class="todo-task-checklist">
                  <?php if ($hasChecklist) { ?>
                  <div class="todo-card-progress-bar"><div class="todo-card-progress-fill" style="width:<?php echo $clPct; ?>%"></div></div>
                  <div class="todo-task-checklist-items" data-task-id="<?php echo $task['id']; ?>">
                    <?php foreach ($task['checklist'] as $cl) {
                      $chk = (int)$cl['is_checked'] ? 'checked' : '';
                    ?>
                    <div class="todo-cl-item" data-cl-id="<?php echo $cl['id']; ?>">
                      <span class="todo-cl-drag-handle" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></span>
                      <div class="todo-cl-check <?php echo $chk; ?>" data-cl-id="<?php echo $cl['id']; ?>"></div>
                      <span class="todo-cl-text"><?php echo htmlspecialchars($cl['title']); ?></span>
                      <button class="todo-cl-delete" title="Remove"><i class="fa fa-times"></i></button>
                    </div>
                    <?php } ?>
                  <?php } else { ?>
                  <div class="todo-task-checklist-items" data-task-id="<?php echo $task['id']; ?>">
                  <?php } ?>
                    <div class="todo-cl-add-row">
                      <input type="text" placeholder="Add item..." />
                      <button class="btn-add-cl-item"><i class="fa fa-plus"></i></button>
                    </div>
                  </div>
                </div>

                <?php
                  $remarksList = isset($task['remarks_list']) ? $task['remarks_list'] : [];
                ?>
                <div class="todo-task-remarks" data-task-id="<?php echo $task['id']; ?>">
                  <div class="todo-remarks-list">
                    <?php foreach ($remarksList as $rm) { ?>
                    <div class="todo-remark-item" data-remark-id="<?php echo $rm['id']; ?>">
                      <div class="todo-remark-content">
                        <span class="todo-remark-text"><?php echo htmlspecialchars($rm['remark']); ?></span>
                        <span class="todo-remark-meta">
                          <span class="todo-remark-author"><?php echo htmlspecialchars($rm['staff_name'] ?? ''); ?></span>
                          <span class="todo-remark-time"><?php echo date('M j, g:i A', strtotime($rm['datecreated'])); ?></span>
                        </span>
                      </div>
                      <button class="todo-remark-delete" data-remark-id="<?php echo $rm['id']; ?>" title="Delete"><i class="fa fa-times"></i></button>
                    </div>
                    <?php } ?>
                  </div>
                  <div class="todo-remark-add-row">
                    <input type="text" class="todo-remark-input" placeholder="Add a remark..." />
                    <button class="todo-remark-add-btn" data-task-id="<?php echo $task['id']; ?>"><i class="fa fa-paper-plane"></i></button>
                  </div>
                </div>
              </div>
            <?php } } ?>

          </div>
        </div>

      </div><!-- /todo-layout -->

    </div><!-- /todo-app -->

    <!-- ═══ Templates Panel (hidden by default) ═══ -->
    <div id="todo-templates-panel" class="todo-templates-panel" style="display:none;">
      <div class="todo-panel-header">
        <h3><i class="fa fa-clipboard-list"></i> Task Templates & Recurring</h3>
        <button class="todo-btn todo-btn-primary todo-btn-sm" id="btn-toggle-tpl-form">
          <i class="fa fa-plus"></i> New Template
        </button>
      </div>

      <!-- Template Form -->
      <div id="todo-tpl-form" class="todo-create-form" style="margin:0 20px;">
        <input type="hidden" id="tpl-edit-id" value="" />
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Template Name *</label>
            <input class="todo-form-input" id="tpl-name" placeholder="e.g. Daily Stand-up Report" />
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Description</label>
            <textarea class="todo-form-input" id="tpl-desc" rows="2" placeholder="Task details..."></textarea>
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group">
            <label class="todo-form-label">Category</label>
            <select class="todo-form-input" id="tpl-category">
              <option value="">No Category</option>
              <?php foreach ($categories as $cat) { ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="todo-form-group">
            <label class="todo-form-label">Priority</label>
            <select class="todo-form-input" id="tpl-priority">
              <option value="1">Low</option>
              <option value="2">Medium</option>
              <option value="3" selected>High</option>
              <option value="4">Urgent</option>
            </select>
          </div>
          <div class="todo-form-group">
            <label class="todo-form-label">Due After (Days)</label>
            <input type="number" class="todo-form-input" id="tpl-due-days" min="0" placeholder="e.g. 3" />
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Assign To (Staff)</label>
            <select class="todo-form-input" id="tpl-assignees" multiple>
              <?php foreach ($staff_list as $s) { ?>
                <option value="<?php echo $s['staffid']; ?>"><?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Assign Roles</label>
            <select class="todo-form-input" id="tpl-roles" multiple>
              <?php foreach ($roles_list as $r) { ?>
                <option value="<?php echo $r['roleid']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group flex-1">
            <label class="todo-form-label">Checklist Items</label>
            <div class="todo-checklist-builder">
              <div id="tpl-checklist-list"></div>
              <button type="button" class="add-checklist-trigger" id="add-tpl-checklist">
                <i class="fas fa-plus-circle"></i> Add checklist item
              </button>
            </div>
          </div>
        </div>
        <div class="todo-form-row">
          <div class="todo-form-group">
            <label class="todo-form-label" style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" id="tpl-is-recurring" style="width:16px;height:16px;accent-color:var(--todo-accent);" />
              Enable Recurring
            </label>
          </div>
        </div>
        <div class="todo-form-row tpl-recurring-opts" style="display:none;">
          <div class="todo-form-group">
            <label class="todo-form-label">Repeat Every</label>
            <input type="number" class="todo-form-input" id="tpl-repeat-every" min="1" value="1" style="width:80px;" />
          </div>
          <div class="todo-form-group">
            <label class="todo-form-label">Frequency</label>
            <select class="todo-form-input" id="tpl-recurring-type">
              <option value="day">Day(s)</option>
              <option value="week">Week(s)</option>
              <option value="month">Month(s)</option>
            </select>
          </div>
        </div>
        <div class="todo-form-row" style="justify-content:flex-end;gap:8px;">
          <button class="todo-btn todo-btn-sm todo-btn-ghost" id="btn-cancel-tpl">Cancel</button>
          <button class="todo-btn todo-btn-sm todo-btn-primary" id="btn-save-tpl"><i class="fa fa-check"></i> <span>Save Template</span></button>
        </div>
      </div>

      <!-- Templates Grid -->
      <div class="todo-tpl-grid" id="todo-tpl-grid">
        <div class="todo-loading"><div class="spinner"></div> Loading templates...</div>
      </div>
    </div>

  </div>
</div>

<?php init_tail(); ?>

<script>
  // Pass CSRF and admin_url to JS
  var csrfData = {
    token_name: '<?php echo $this->security->get_csrf_token_name(); ?>',
    hash: '<?php echo $this->security->get_csrf_hash(); ?>'
  };
  var TODO_STAFF = <?php echo json_encode(array_map(function($s) { return ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']]; }, $staff_list)); ?>;
  var TODO_ROLES = <?php echo json_encode(array_map(function($r) { return ['id' => $r['roleid'], 'name' => $r['name']]; }, $roles_list)); ?>;
</script>
<script src="<?php echo module_dir_url('todo', 'assets/js/todo.js'); ?>?v=<?php echo time(); ?>"></script>
