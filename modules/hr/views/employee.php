<?php defined('BASEPATH') or exit('No direct script access allowed');

$active_tab = $this->input->get('tab') ?: 'profile';
$can_edit   = has_permission('hr_employees', '', 'edit') || is_admin();
$sel        = function ($a, $b) { return (string) $a === (string) $b ? ' selected' : ''; };

$hr_img_url = staff_profile_image_url($employee['staffid'], 'small');
$hr_has_img = strpos($hr_img_url, 'user-placeholder') === false;
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;">
                            <div style="margin-right:15px;">
                                <?php if ($can_edit) { ?>
                                    <div class="hr-avatar-wrap" id="hr-avatar-wrap" title="Change photo" onclick="hrOpenPhotoModal();">
                                        <img src="<?php echo $hr_img_url; ?>" id="hr-avatar-img" class="hr-avatar-img" alt="">
                                        <span class="hr-avatar-overlay"><i class="fa fa-camera"></i></span>
                                    </div>
                                <?php } else {
                                    echo staff_profile_image($employee['staffid'], ['staff-profile-image-small']);
                                } ?>
                            </div>
                            <div>
                                <h4 style="margin:0;font-weight:700;"><?php echo html_escape($employee['firstname'] . ' ' . $employee['lastname']); ?></h4>
                                <span class="text-muted">
                                    <?php echo html_escape($employee['employee_code']); ?>
                                    &middot; <?php echo html_escape($employee['email']); ?>
                                    <?php if ($employee['phonenumber']) { echo ' &middot; ' . html_escape($employee['phonenumber']); } ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <a href="<?php echo admin_url('hr/employees'); ?>" class="btn btn-default mright5"><i class="fa fa-arrow-left"></i> Back to Employees</a>
                            <?php if ($employee['status'] === 'on_notice') { ?>
                                <span class="label label-warning" style="border-radius:15px;padding:6px 16px;">On Notice</span>
                            <?php } elseif ($employee['status'] === 'exited') { ?>
                                <span class="label label-danger" style="border-radius:15px;padding:6px 16px;">Exited</span>
                            <?php } else { ?>
                                <span class="label label-success" style="border-radius:15px;padding:6px 16px;">Active</span>
                            <?php } ?>
                            <?php
                            // "CRM Profile" opens the Team module's member screen — Team is the
                            // replacement for the core Staff screen. Core staff is used only as a
                            // fallback on installs where the Team module is not active.
                            $hr_team_on  = is_dir(FCPATH . 'modules/team') && get_instance()->app_modules->is_active('team');
                            $hr_crm_show = $hr_team_on
                                ? (is_admin() || has_permission('team', '', 'view') || has_permission('team', '', 'create') || has_permission('team', '', 'edit'))
                                : (is_admin() || has_permission('staff', '', 'view'));
                            if ($hr_crm_show) { ?>
                                <a href="<?php echo admin_url(($hr_team_on ? 'team/member/' : 'staff/member/') . $employee['staffid']); ?>" class="btn btn-default mleft5"><i class="fa fa-user"></i> CRM Profile</a>
                            <?php } ?>
                            <?php if (!empty($smart_pdf_groups)) { ?>
                                <style>
                                    .hr-print-menu { min-width: 300px; max-width: 340px; max-height: 66vh; overflow-y: auto; padding: 8px 0; text-align: left; }
                                    .hr-print-menu .hr-pm-search { padding: 4px 12px 8px; }
                                    .hr-print-menu .hr-pm-search input { width: 100%; height: 32px; border: 1px solid #dde3e8; border-radius: 6px; padding: 0 10px; font-size: 13px; }
                                    .hr-print-menu .dropdown-header { display: flex; align-items: center; gap: 8px; padding: 6px 14px 4px; font-size: 11px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: #0e4d54; }
                                    .hr-print-menu .dropdown-header .hr-pm-count { margin-left: auto; background: #e8f0ef; color: #0e4d54; border-radius: 10px; padding: 1px 8px; font-size: 10px; font-weight: 600; letter-spacing: 0; }
                                    .hr-print-menu .dropdown-header .fa { color: #b6923d; }
                                    .hr-print-menu > li > a { display: flex; align-items: center; gap: 9px; text-align: left !important; white-space: normal !important; padding: 7px 16px 7px 22px !important; line-height: 1.3 !important; font-size: 13px; color: #4b5158; }
                                    .hr-print-menu > li > a:hover, .hr-print-menu > li > a:focus { background: #f4f8fb; color: #0e4d54; }
                                    .hr-print-menu > li > a .fa { width: 15px; text-align: center; color: #8a97a3; flex-shrink: 0; }
                                    .hr-print-menu > li > a:hover .fa { color: #0e4d54; }
                                    .hr-print-menu .divider { margin: 7px 0; }
                                    .hr-print-menu .hr-pm-empty { padding: 10px 16px; color: #98a4b0; font-size: 12px; display: none; }
                                </style>
                                <div class="btn-group mleft5">
                                    <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa fa-print"></i> Print Document <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right hr-print-menu" id="hr-print-menu">
                                        <li class="hr-pm-search"><input type="text" id="hr-pm-filter" placeholder="Search document..." onclick="event.stopPropagation();" autocomplete="off"></li>
                                        <?php $first = true;
                                        foreach ($smart_pdf_groups as $g) { ?>
                                            <?php if (!$first) { ?><li class="divider hr-pm-row"></li><?php } $first = false; ?>
                                            <li class="dropdown-header hr-pm-head">
                                                <i class="fa <?php echo $g['icon']; ?>"></i> <?php echo html_escape($g['label']); ?>
                                                <span class="hr-pm-count"><?php echo count($g['items']); ?></span>
                                            </li>
                                            <?php foreach ($g['items'] as $t) { ?>
                                                <li class="hr-pm-row hr-pm-item" data-name="<?php echo html_escape(mb_strtolower($t['name'])); ?>">
                                                    <a href="#" class="hr-spdf-generate" data-template="<?php echo (int) $t['id']; ?>">
                                                        <i class="fa fa-file-text-o"></i> <span><?php echo html_escape($t['name']); ?></span>
                                                    </a>
                                                </li>
                                            <?php } ?>
                                        <?php } ?>
                                        <li class="hr-pm-empty">No document matches your search.</li>
                                        <li class="divider"></li>
                                        <li>
                                            <a href="<?php echo admin_url('smart_pdf'); ?>"><i class="fa fa-cog"></i> Manage Templates</a>
                                        </li>
                                    </ul>
                                </div>
                                <script>
                                    (function () {
                                        // Open the Smart PDF generate popup in-place (no redirect),
                                        // pre-filled with this employee's details.
                                        var printMenu = document.getElementById('hr-print-menu');
                                        if (printMenu) {
                                            printMenu.addEventListener('click', function (e) {
                                                var trigger = e.target.closest('.hr-spdf-generate');
                                                if (!trigger) { return; }
                                                e.preventDefault();
                                                if (typeof window.smartPdfGenerate === 'function') {
                                                    window.smartPdfGenerate(trigger.getAttribute('data-template'), { employee: <?php echo (int) $employee['staffid']; ?> });
                                                }
                                            });
                                        }

                                        var input = document.getElementById('hr-pm-filter');
                                        if (!input) { return; }
                                        input.addEventListener('keyup', function () {
                                            var q = this.value.toLowerCase().trim();
                                            var menu = document.getElementById('hr-print-menu');
                                            var items = menu.querySelectorAll('.hr-pm-item');
                                            var shown = 0;
                                            items.forEach(function (li) {
                                                var match = li.getAttribute('data-name').indexOf(q) !== -1;
                                                li.style.display = match ? '' : 'none';
                                                if (match) { shown++; }
                                            });
                                            // Hide a category header + its preceding divider when it has no visible items
                                            menu.querySelectorAll('.hr-pm-head').forEach(function (head) {
                                                var visible = false, n = head.nextElementSibling;
                                                while (n && n.classList.contains('hr-pm-item')) {
                                                    if (n.style.display !== 'none') { visible = true; break; }
                                                    n = n.nextElementSibling;
                                                }
                                                head.style.display = (q === '' || visible) ? '' : 'none';
                                            });
                                            menu.querySelectorAll('.divider.hr-pm-row').forEach(function (d) {
                                                d.style.display = (q === '') ? '' : 'none';
                                            });
                                            menu.querySelector('.hr-pm-empty').style.display = (shown === 0) ? 'block' : 'none';
                                        });
                                    })();
                                </script>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <style>
                            /* Employee tab strip. Twelve tabs no longer fit on one line at most
                               widths, and Bootstrap's nav-tabs wrap into a broken second row, so
                               the strip scrolls horizontally instead and carries its own arrows. */
                            .hr-tabs-wrap { position: relative; border-bottom: 1px solid #e3e8ee; }
                            .hr-emp-tabs { display: flex; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; margin: 0; padding: 0; border-bottom: 0; scroll-behavior: smooth; scrollbar-width: none; -ms-overflow-style: none; }
                            .hr-emp-tabs::-webkit-scrollbar { display: none; }
                            .hr-emp-tabs > li { float: none; flex: 0 0 auto; margin: 0; }
                            .hr-emp-tabs > li > a { display: flex; align-items: center; gap: 7px; white-space: nowrap; margin: 0; padding: 11px 15px; border: 0; border-bottom: 2px solid transparent; border-radius: 0; background: transparent; color: #6b7280; font-size: 13px; font-weight: 600; transition: color .15s ease, border-color .15s ease, background .15s ease; }
                            .hr-emp-tabs > li > a:hover, .hr-emp-tabs > li > a:focus { background: #f4f8fb; color: #0e4d54; border-color: transparent; }
                            .hr-emp-tabs > li.active > a, .hr-emp-tabs > li.active > a:hover, .hr-emp-tabs > li.active > a:focus { border: 0; border-bottom: 2px solid #0e4d54; background: transparent; color: #0e4d54; }
                            .hr-emp-tabs > li > a .fa { width: 14px; text-align: center; font-size: 13px; opacity: .7; }
                            .hr-emp-tabs > li.active > a .fa { opacity: 1; }
                            .hr-tab-count { display: inline-block; min-width: 19px; padding: 0 6px; border-radius: 9px; background: #eef2f5; color: #7b8794; font-size: 11px; line-height: 18px; font-weight: 700; text-align: center; }
                            .hr-emp-tabs > li.active > a .hr-tab-count { background: #0e4d54; color: #fff; }
                            /* Scroll arrows: only shown while the strip actually overflows */
                            .hr-tabs-arrow { display: none; position: absolute; top: 0; bottom: 1px; width: 32px; padding: 0; border: 0; z-index: 3; background: #fff; color: #6b7280; font-size: 16px; }
                            .hr-tabs-arrow:hover { color: #0e4d54; }
                            .hr-tabs-arrow.hr-tabs-prev { left: 0; box-shadow: 7px 0 9px -5px rgba(16, 42, 67, .25); }
                            .hr-tabs-arrow.hr-tabs-next { right: 0; box-shadow: -7px 0 9px -5px rgba(16, 42, 67, .25); }
                            .hr-tabs-wrap.is-overflowing .hr-tabs-arrow { display: block; }
                            .hr-tabs-wrap.at-start .hr-tabs-prev, .hr-tabs-wrap.at-end .hr-tabs-next { display: none; }
                            @media (max-width: 767px) { .hr-emp-tabs > li > a { padding: 10px 12px; font-size: 12px; } }
                        </style>
                        <div class="hr-tabs-wrap at-start" id="hr-tabs-wrap">
                            <button type="button" class="hr-tabs-arrow hr-tabs-prev" aria-label="Scroll tabs left"><i class="fa fa-angle-left"></i></button>
                            <ul class="nav nav-tabs hr-emp-tabs" role="tablist">
                                <li class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>"><a href="#tab_profile" data-toggle="tab"><i class="fa fa-id-badge"></i> HR Profile</a></li>
                                <li class="<?php echo $active_tab === 'bank' ? 'active' : ''; ?>"><a href="#tab_bank" data-toggle="tab"><i class="fa fa-university"></i> Bank &amp; Statutory</a></li>
                                <?php if ($can_payroll) { ?>
                                    <li class="<?php echo $active_tab === 'salary' ? 'active' : ''; ?>"><a href="#tab_salary" data-toggle="tab"><i class="fa fa-money-bill"></i> Salary Structure</a></li>
                                <?php } ?>
                                <li class="<?php echo $active_tab === 'documents' ? 'active' : ''; ?>"><a href="#tab_documents" data-toggle="tab"><i class="fa fa-folder-open"></i> Documents <span class="hr-tab-count"><?php echo count($documents); ?></span></a></li>
                                <li class="<?php echo $active_tab === 'family' ? 'active' : ''; ?>"><a href="#tab_family" data-toggle="tab"><i class="fa fa-users"></i> Family &amp; Nominees <span class="hr-tab-count"><?php echo count($family) + count($nominees); ?></span></a></li>
                                <li class="<?php echo $active_tab === 'leave' ? 'active' : ''; ?>"><a href="#tab_leave" data-toggle="tab"><i class="fa fa-calendar-minus"></i> Leave</a></li>
                                <li class="<?php echo $active_tab === 'attendance' ? 'active' : ''; ?>"><a href="#tab_attendance" data-toggle="tab"><i class="fa fa-clock"></i> Attendance &amp; Shift</a></li>
                                <li class="<?php echo $active_tab === 'appraisals' ? 'active' : ''; ?>"><a href="#tab_appraisals" data-toggle="tab"><i class="fa fa-star"></i> Appraisals <span class="hr-tab-count"><?php echo count($appraisals); ?></span></a></li>
                                <li class="<?php echo $active_tab === 'krakpi' ? 'active' : ''; ?>"><a href="#tab_krakpi" data-toggle="tab"><i class="fa fa-bullseye"></i> KRA &amp; KPI <span class="hr-tab-count"><?php echo count($kras) + count($kpis); ?></span></a></li>
                                <?php if (!empty($can_memos)) { ?>
                                    <li class="<?php echo $active_tab === 'memos' ? 'active' : ''; ?>"><a href="#tab_memos" data-toggle="tab"><i class="fa fa-file-lines"></i> Memos <span class="hr-tab-count"><?php echo count($memos); ?></span></a></li>
                                <?php } ?>
                                <?php if (!empty($can_onboarding)) { ?>
                                    <li class="<?php echo $active_tab === 'onboarding' ? 'active' : ''; ?>"><a href="#tab_onboarding" data-toggle="tab"><i class="fa fa-clipboard"></i> Onboarding</a></li>
                                <?php } ?>
                                <?php if ($can_edit) { ?>
                                    <li class="<?php echo $active_tab === 'selfservice' ? 'active' : ''; ?>"><a href="#tab_selfservice" data-toggle="tab"><i class="fa fa-user-circle"></i> Self-Service</a></li>
                                <?php } ?>
                            </ul>
                            <button type="button" class="hr-tabs-arrow hr-tabs-next" aria-label="Scroll tabs right"><i class="fa fa-angle-right"></i></button>
                        </div>
                        <script>
                            // Tab strip overflow: show the arrows only while the strip is wider
                            // than its container, and keep the active tab in view. Vanilla JS —
                            // jQuery only loads in the admin footer, below this script.
                            (function () {
                                var wrap = document.getElementById('hr-tabs-wrap');
                                if (!wrap) { return; }
                                var strip = wrap.querySelector('.hr-emp-tabs');
                                var step  = 240;

                                function update() {
                                    var over = (strip.scrollWidth - strip.clientWidth) > 2;
                                    wrap.classList[over ? 'add' : 'remove']('is-overflowing');
                                    wrap.classList[strip.scrollLeft <= 1 ? 'add' : 'remove']('at-start');
                                    wrap.classList[(strip.scrollLeft + strip.clientWidth) >= (strip.scrollWidth - 1) ? 'add' : 'remove']('at-end');
                                }
                                function scrollBy(delta) {
                                    if (strip.scrollBy) { strip.scrollBy({ left: delta, behavior: 'smooth' }); }
                                    else { strip.scrollLeft += delta; }
                                }

                                strip.addEventListener('scroll', update);
                                window.addEventListener('resize', update);
                                wrap.querySelector('.hr-tabs-prev').addEventListener('click', function () { scrollBy(-step); });
                                wrap.querySelector('.hr-tabs-next').addEventListener('click', function () { scrollBy(step); });

                                // Land on the tab the page opened with, even when it sits off-screen.
                                var active = strip.querySelector('li.active');
                                if (active && (active.offsetLeft + active.offsetWidth) > strip.clientWidth) {
                                    strip.scrollLeft = active.offsetLeft - 24;
                                }
                                update();
                                // Icon webfonts land after this runs and change the strip width.
                                window.setTimeout(update, 400);
                            })();
                        </script>

                        <div class="tab-content mtop15">

                            <!-- ============================== HR PROFILE -->
                            <div class="tab-pane <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" id="tab_profile">
                                <?php echo form_open(admin_url('hr/save_employee/' . $employee['staffid'])); ?>
                                <h4 class="bold">Employment</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Employee Code</label>
                                            <input type="text" name="employee_code" class="form-control" value="<?php echo html_escape($employee['employee_code']); ?>"></div>
                                        <?php if (hr_etime_enabled()) { ?>
                                            <div class="form-group"><label>Machine Employee Code <small class="text-muted">(biometric)</small></label>
                                                <input type="text" name="device_empcode" class="form-control" value="<?php echo html_escape($employee['device_empcode'] ?? ''); ?>" placeholder="e.g. 0001 — blank = use Employee Code; comma-separate multiple"></div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Department</label>
                                            <select name="department_id" class="selectpicker" data-width="100%" data-live-search="true">
                                                <option value=""></option>
                                                <?php foreach ($departments as $d) { ?>
                                                    <option value="<?php echo $d['departmentid']; ?>"<?php echo $sel($employee['department_id'], $d['departmentid']); ?>><?php echo html_escape($d['name']); ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Designation
                                            <?php if ($can_edit) { ?>
                                                <a href="#" id="hr-add-designation-btn" data-toggle="modal" data-target="#hr-add-designation-modal" title="Add new designation" style="margin-left:4px;"><i class="fa fa-plus-circle"></i></a>
                                            <?php } ?>
                                            </label>
                                            <select name="designation_id" id="hr-designation-select" class="selectpicker" data-width="100%" data-live-search="true">
                                                <option value="" data-role-id=""></option>
                                                <?php foreach ($designations as $dg) { ?>
                                                    <option value="<?php echo $dg['id']; ?>" data-role-id="<?php echo (int) ($dg['role_id'] ?? 0); ?>"<?php echo $sel($employee['designation_id'], $dg['id']); ?>><?php echo html_escape($dg['name']); ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Role <small class="text-muted" title="Staff permission role (from Designation, editable)"><i class="fa fa-link"></i></small></label>
                                            <select name="role" id="hr-role-select" class="selectpicker" data-width="100%" data-live-search="true">
                                                <option value=""></option>
                                                <?php foreach ($roles as $r) { ?>
                                                    <option value="<?php echo $r['roleid']; ?>"<?php echo $sel($employee['role'], $r['roleid']); ?>><?php echo html_escape($r['name']); ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Employment Type</label>
                                            <select name="employment_type" class="selectpicker" data-width="100%">
                                                <?php foreach (['permanent' => 'Permanent', 'contract' => 'Contract', 'probation' => 'Probation', 'consultant' => 'Consultant', 'part_time' => 'Part Time', 'intern' => 'Intern'] as $k => $v) { ?>
                                                    <option value="<?php echo $k; ?>"<?php echo $sel($employee['employment_type'], $k); ?>><?php echo $v; ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Date of Joining</label>
                                            <input type="date" name="date_of_joining" class="form-control" value="<?php echo $employee['date_of_joining']; ?>"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Probation End</label>
                                            <input type="date" name="probation_end" class="form-control" value="<?php echo $employee['probation_end']; ?>"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Work Location / Branch</label>
                                            <input type="text" name="work_location" class="form-control" value="<?php echo html_escape($employee['work_location']); ?>"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Reporting To</label>
                                            <select name="reporting_to" class="selectpicker" data-width="100%" data-live-search="true">
                                                <option value=""></option>
                                                <?php foreach ($staff_members as $sm) {
                                                    if ($sm['staffid'] == $employee['staffid']) { continue; } ?>
                                                    <option value="<?php echo $sm['staffid']; ?>"<?php echo $sel($employee['reporting_to'], $sm['staffid']); ?>><?php echo html_escape($sm['firstname'] . ' ' . $sm['lastname']); ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                </div>

                                <h4 class="bold mtop15">Personal</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Date of Birth</label>
                                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo $employee['date_of_birth']; ?>"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Gender</label>
                                            <select name="gender" class="selectpicker" data-width="100%">
                                                <option value=""></option>
                                                <?php foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $k => $v) { ?>
                                                    <option value="<?php echo $k; ?>"<?php echo $sel($employee['gender'], $k); ?>><?php echo $v; ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Blood Group</label>
                                            <select name="blood_group" class="selectpicker" data-width="100%">
                                                <option value=""></option>
                                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg) { ?>
                                                    <option value="<?php echo $bg; ?>"<?php echo $sel($employee['blood_group'], $bg); ?>><?php echo $bg; ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Marital Status</label>
                                            <select name="marital_status" class="selectpicker" data-width="100%">
                                                <option value=""></option>
                                                <?php foreach (['single' => 'Single', 'married' => 'Married', 'other' => 'Other'] as $k => $v) { ?>
                                                    <option value="<?php echo $k; ?>"<?php echo $sel($employee['marital_status'], $k); ?>><?php echo $v; ?></option>
                                                <?php } ?>
                                            </select></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Father / Spouse Name</label>
                                            <input type="text" name="father_name" class="form-control" value="<?php echo html_escape($employee['father_name']); ?>"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Personal Email</label>
                                            <input type="email" name="personal_email" class="form-control" value="<?php echo html_escape($employee['personal_email']); ?>"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Alternate Phone</label>
                                            <input type="text" name="alt_phone" class="form-control" value="<?php echo html_escape($employee['alt_phone']); ?>"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group"><label>Qualifications</label>
                                            <input type="text" name="qualifications" class="form-control" value="<?php echo html_escape($employee['qualifications']); ?>" placeholder="e.g. MBBS, MD; GNM; B.Sc Nursing"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group"><label>Present Address</label>
                                            <textarea name="present_address" class="form-control" rows="2"><?php echo html_escape($employee['present_address']); ?></textarea></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group"><label>Permanent Address</label>
                                            <textarea name="permanent_address" class="form-control" rows="2"><?php echo html_escape($employee['permanent_address']); ?></textarea></div>
                                    </div>
                                </div>

                                <h4 class="bold mtop15">Emergency Contact</h4>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group"><label>Name</label>
                                            <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo html_escape($employee['emergency_contact_name']); ?>"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group"><label>Relation</label>
                                            <input type="text" name="emergency_contact_relation" class="form-control" value="<?php echo html_escape($employee['emergency_contact_relation']); ?>"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group"><label>Phone</label>
                                            <input type="text" name="emergency_contact_phone" class="form-control" value="<?php echo html_escape($employee['emergency_contact_phone']); ?>"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group"><label>HR Notes</label>
                                            <textarea name="notes" class="form-control" rows="2"><?php echo html_escape($employee['notes']); ?></textarea></div>
                                    </div>
                                </div>
                                <?php if ($can_edit) { ?>
                                    <button type="submit" class="btn btn-primary">Save HR Profile</button>
                                <?php } ?>
                                <?php echo form_close(); ?>
                            </div>

                            <!-- ============================== BANK -->
                            <div class="tab-pane <?php echo $active_tab === 'bank' ? 'active' : ''; ?>" id="tab_bank">
                                <?php echo form_open(admin_url('hr/save_employee/' . $employee['staffid'])); ?>
                                <input type="hidden" name="back_tab" value="bank">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group"><label>Bank Name</label>
                                            <input type="text" name="bank_name" class="form-control" value="<?php echo html_escape($employee['bank_name']); ?>"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group"><label>Branch</label>
                                            <input type="text" name="bank_branch" class="form-control" value="<?php echo html_escape($employee['bank_branch']); ?>"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group"><label>Account Number</label>
                                            <input type="text" name="bank_account_no" class="form-control" value="<?php echo html_escape($employee['bank_account_no']); ?>"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group"><label>IFSC / Swift Code</label>
                                            <input type="text" name="bank_ifsc" class="form-control" value="<?php echo html_escape($employee['bank_ifsc']); ?>"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group"><label>Aadhaar Number</label>
                                            <input type="text" name="aadhaar_number" id="hr-aadhaar-input" class="form-control" inputmode="numeric" maxlength="14"
                                                value="<?php echo html_escape(hr_format_aadhaar($employee['aadhaar_number'] ?? '')); ?>" placeholder="1234 5678 9012">
                                            <small class="text-muted" id="hr-aadhaar-hint">12 digits. Spaces are ignored.</small></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group"><label>PAN Number</label>
                                            <input type="text" name="pan_number" class="form-control" value="<?php echo html_escape($employee['pan_number']); ?>"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group"><label>National ID / Passport / SSN <small class="text-muted">(other than Aadhaar)</small></label>
                                            <input type="text" name="national_id" class="form-control" value="<?php echo html_escape($employee['national_id']); ?>"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group"><label>PF / UAN Number</label>
                                            <input type="text" name="pf_uan" class="form-control" value="<?php echo html_escape($employee['pf_uan']); ?>"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group"><label>ESI Number</label>
                                            <input type="text" name="esi_number" class="form-control" value="<?php echo html_escape($employee['esi_number']); ?>"></div>
                                    </div>
                                </div>
                                <?php if ($can_edit) { ?>
                                    <button type="submit" class="btn btn-primary">Save Bank &amp; Statutory</button>
                                <?php } ?>
                                <?php echo form_close(); ?>
                                <script>
                                    // Aadhaar: digits only, grouped 4-4-4 as you type. Vanilla JS —
                                    // jQuery only loads in the admin footer, after this point.
                                    (function () {
                                        var input = document.getElementById('hr-aadhaar-input');
                                        if (!input) { return; }
                                        var hint = document.getElementById('hr-aadhaar-hint');
                                        function render() {
                                            var digits = input.value.replace(/\D/g, '').slice(0, 12);
                                            input.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
                                            if (!hint) { return; }
                                            if (digits.length === 0) {
                                                hint.textContent = '12 digits. Spaces are ignored.';
                                                hint.className = 'text-muted';
                                            } else if (digits.length === 12 && /^[2-9]/.test(digits)) {
                                                hint.textContent = '✓ Looks like a valid Aadhaar.';
                                                hint.className = 'text-success';
                                            } else {
                                                hint.textContent = digits.length + '/12 digits entered.';
                                                hint.className = 'text-danger';
                                            }
                                        }
                                        input.addEventListener('input', render);
                                        render();
                                    })();
                                </script>
                            </div>

                            <!-- ============================== SALARY -->
                            <?php if ($can_payroll) { ?>
                                <div class="tab-pane <?php echo $active_tab === 'salary' ? 'active' : ''; ?>" id="tab_salary">
                                    <style>
                                        #salary_components_table .sal-disabled td { opacity:.45; }
                                        #salary_components_table .sal-disabled .label { opacity:.6; }
                                    </style>
                                    <?php
                                    $can_edit_pay = has_permission('hr_payroll', '', 'edit') || is_admin();
                                    $has_structure = count($structure) > 0;
                                    $emp_pay_day   = isset($employee['salary_payment_day']) ? $employee['salary_payment_day'] : null;
                                    ?>
                                    <?php echo form_open(admin_url('hr/save_salary_structure/' . $employee['staffid'])); ?>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Basic Salary (Monthly)</label>
                                                <input type="number" step="0.01" min="0" name="basic_salary" class="form-control" value="<?php echo $employee['basic_salary']; ?>" <?php echo $can_edit_pay ? '' : 'readonly'; ?>>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Salary Payment Day <i class="fa fa-question-circle text-muted" data-toggle="tooltip" title="The day of the month this employee is paid. Choose 'Use global default' to follow the company-wide setting."></i></label>
                                                <select name="salary_payment_day" class="form-control" <?php echo $can_edit_pay ? '' : 'disabled'; ?>>
                                                    <option value="" <?php echo ($emp_pay_day === null || $emp_pay_day === '') ? 'selected' : ''; ?>>Use global default (<?php echo hr_pay_day_label($global_pay_day); ?>)</option>
                                                    <option value="0" <?php echo ((string) $emp_pay_day === '0') ? 'selected' : ''; ?>>Last day of month</option>
                                                    <?php for ($d = 1; $d <= 31; $d++) { ?>
                                                        <option value="<?php echo $d; ?>" <?php echo ((string) $emp_pay_day === (string) $d) ? 'selected' : ''; ?>><?php echo hr_pay_day_label($d); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <table class="table table-striped" id="salary_components_table">
                                        <thead>
                                            <tr>
                                                <?php if ($can_edit_pay) { ?><th style="width:70px;" class="text-center">On</th><?php } ?>
                                                <th>Component</th><th>Type</th><th>Calculation</th><th style="width:200px;">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($components as $c) {
                                                $enabled = $has_structure ? array_key_exists($c['id'], $structure) : true;
                                                $val     = $has_structure ? ($structure[$c['id']] ?? '') : $c['default_value']; ?>
                                                <tr class="sal-comp-row <?php echo $enabled ? '' : 'sal-disabled'; ?>"
                                                    data-type="<?php echo $c['type']; ?>" data-calc="<?php echo $c['calc_type']; ?>">
                                                    <?php if ($can_edit_pay) { ?>
                                                        <td class="text-center">
                                                            <input type="checkbox" class="sal-comp-toggle" <?php echo $enabled ? 'checked' : ''; ?>>
                                                        </td>
                                                    <?php } ?>
                                                    <td><?php echo html_escape($c['name']); ?></td>
                                                    <td><span class="label label-<?php echo $c['type'] === 'earning' ? 'success' : 'danger'; ?>"><?php echo ucfirst($c['type']); ?></span></td>
                                                    <td><?php echo $c['calc_type'] === 'percent_basic' ? '% of Basic' : 'Fixed amount'; ?></td>
                                                    <td>
                                                        <div class="input-group">
                                                            <input type="number" step="0.01" min="0" name="component[<?php echo $c['id']; ?>]" class="form-control sal-comp-value" value="<?php echo $val; ?>" <?php echo ($can_edit_pay && $enabled) ? '' : ($can_edit_pay ? 'disabled' : 'readonly'); ?>>
                                                            <span class="input-group-addon"><?php echo $c['calc_type'] === 'percent_basic' ? '%' : get_base_currency()->symbol; ?></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                    <p class="text-muted">Turn a component <strong>On/Off</strong> to include or exclude it for this employee. Percent components are calculated on the basic salary. Leaving a value blank excludes that component.</p>

                                    <?php if ($can_edit_pay) { ?>
                                        <button type="submit" class="btn btn-primary">Save Salary Structure</button>
                                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#add_component_modal"><i class="fa fa-plus"></i> Add Component</button>
                                    <?php } ?>
                                    <?php echo form_close(); ?>

                                    <?php if (!empty($salary_preview)) {
                                        $sp   = $salary_preview;
                                        $cur  = get_base_currency();
                                        $sym  = $cur->symbol; ?>
                                        <div class="panel_s" style="margin-top:22px;border:1px solid #e2e8f0;">
                                            <div class="panel-body">
                                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                                    <h4 class="bold" style="margin:0;"><i class="fa fa-calculator text-info"></i> Salary Calculation — <?php echo date('F Y'); ?></h4>
                                                    <span class="label label-<?php echo $sp['days_until'] <= 3 ? 'warning' : 'default'; ?>">
                                                        <i class="fa fa-calendar"></i> Pay date: <?php echo _d($sp['pay_date']); ?>
                                                        <?php if ($sp['days_until'] >= 0) { echo ' (' . ($sp['days_until'] === 0 ? 'today' : 'in ' . $sp['days_until'] . 'd') . ')'; } ?>
                                                    </span>
                                                </div>
                                                <hr class="hr-panel-heading" />
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <table class="table table-condensed" style="margin-bottom:6px;">
                                                            <tr><td>Basic</td><td class="text-right bold"><?php echo $sym . ' ' . number_format($sp['basic'], 2); ?></td></tr>
                                                            <tr><td>Earnings (allowances)</td><td class="text-right"><?php echo $sym . ' ' . number_format($sp['earn_sum'], 2); ?></td></tr>
                                                            <tr style="border-top:2px solid #e2e8f0;"><td class="bold">Gross</td><td class="text-right bold"><?php echo $sym . ' ' . number_format($sp['gross'], 2); ?></td></tr>
                                                            <tr><td>Deductions</td><td class="text-right text-danger">− <?php echo $sym . ' ' . number_format($sp['ded_sum'], 2); ?></td></tr>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;margin-bottom:12px;">
                                                            <div class="text-muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Total pay on payday (full month)</div>
                                                            <div style="font-size:26px;font-weight:800;color:#15803d;"><?php echo $sym . ' ' . number_format($sp['net_full'], 2); ?></div>
                                                            <div class="text-muted" style="font-size:12px;">Gross − deductions, assuming full attendance.</div>
                                                        </div>
                                                        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;">
                                                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                                                <div class="text-muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Earned so far — by present days</div>
                                                                <select id="sal_proration_mode" class="form-control input-sm" style="width:auto;">
                                                                    <option value="prorated">Prorate deductions</option>
                                                                    <option value="fullded">Full deductions</option>
                                                                </select>
                                                            </div>
                                                            <div id="sal_earned_value" style="font-size:26px;font-weight:800;color:#1d4ed8;"
                                                                 data-prorated="<?php echo $sp['current_net_prorated']; ?>"
                                                                 data-fullded="<?php echo $sp['current_net_fullded']; ?>">
                                                                <?php echo $sym . ' ' . number_format($sp['current_net_prorated'], 2); ?>
                                                            </div>
                                                            <div class="text-muted" style="font-size:12px;">
                                                                <?php echo $sp['payable_so_far']; ?> of <?php echo $sp['basis_days']; ?> payable days
                                                                <?php if ($sp['lop_so_far'] > 0) { ?>· <?php echo $sp['lop_so_far']; ?> LOP day(s)<?php } ?>
                                                                · <?php echo $sym . ' ' . number_format($sp['per_day'], 2); ?>/day
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <p class="text-muted" style="font-size:11px;margin:6px 0 0;"><i class="fa fa-info-circle"></i> Live estimate from this month's attendance to date; the finalized figure comes from the monthly Payroll run.</p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>

                                <?php if (has_permission('hr_payroll', '', 'edit') || is_admin()) { ?>
                                <!-- Add salary component (global) -->
                                <div class="modal fade" id="add_component_modal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <?php echo form_open(admin_url('hr/save_salary_component')); ?>
                                        <input type="hidden" name="return_staff_id" value="<?php echo (int) $employee['staffid']; ?>">
                                        <input type="hidden" name="is_active" value="1">
                                        <input type="hidden" name="sort_order" value="100">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                <h4 class="modal-title">Add Salary Component</h4>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted">New components are added to the company-wide list and become available for every employee.</p>
                                                <div class="form-group"><label>Name <small class="req text-danger">*</small></label>
                                                    <input type="text" name="name" class="form-control" required></div>
                                                <div class="row">
                                                    <div class="col-md-6"><div class="form-group"><label>Type</label>
                                                        <select name="type" class="form-control">
                                                            <option value="earning">Earning (allowance)</option>
                                                            <option value="deduction">Deduction</option>
                                                        </select></div></div>
                                                    <div class="col-md-6"><div class="form-group"><label>Calculation</label>
                                                        <select name="calc_type" class="form-control">
                                                            <option value="fixed">Fixed amount</option>
                                                            <option value="percent_basic">% of Basic</option>
                                                        </select></div></div>
                                                </div>
                                                <div class="form-group"><label>Default Value</label>
                                                    <input type="number" step="0.01" min="0" name="default_value" class="form-control" value="0"></div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                                                <button type="submit" class="btn btn-primary">Add Component</button>
                                            </div>
                                        </div>
                                        <?php echo form_close(); ?>
                                    </div>
                                </div>
                                <?php } ?>
                            <?php } ?>

                            <!-- ============================== DOCUMENTS -->
                            <div class="tab-pane <?php echo $active_tab === 'documents' ? 'active' : ''; ?>" id="tab_documents">
                                <?php
                                // Required-documents checklist compliance for this employee.
                                if (!empty($ess_required)) {
                                    // newest status per doc_type ($documents is id DESC → first wins)
                                    $submitted_types = [];
                                    foreach ($documents as $sd) {
                                        if (!isset($submitted_types[$sd['doc_type']])) {
                                            $submitted_types[$sd['doc_type']] = $sd['status'] ?? 'submitted';
                                        }
                                    }
                                    ?>
                                    <div class="panel panel-default" style="border-radius:8px;">
                                        <div class="panel-body" style="padding:12px 15px;">
                                            <strong><i class="fa fa-list-ul"></i> Required Documents Checklist</strong>
                                            <small class="text-muted"> — items marked <span style="color:#dc2626;font-weight:700;">*</span> are mandatory. This reflects what the employee sees in ESS.</small>
                                            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                                                <?php foreach ($ess_required as $rd) {
                                                    $st = $submitted_types[$rd['name']] ?? null;
                                                    if ($st === 'verified') { $cls = 'success'; $ic = 'fa-check-circle'; $txt = 'Verified'; }
                                                    elseif ($st === 'submitted') { $cls = 'info'; $ic = 'fa-clock-o'; $txt = 'Under review'; }
                                                    elseif ($st === 'rejected') { $cls = 'danger'; $ic = 'fa-times-circle'; $txt = 'Rejected'; }
                                                    else { $cls = $rd['mandatory'] ? 'danger' : 'default'; $ic = 'fa-circle-o'; $txt = 'Pending'; }
                                                    ?>
                                                    <span class="label label-<?php echo $st ? $cls : 'default'; ?>" style="<?php echo (!$st && $rd['mandatory']) ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;' : ''; ?>font-weight:600;">
                                                        <i class="fa <?php echo $ic; ?>"></i> <?php echo html_escape($rd['name']); ?><?php if ($rd['mandatory']) { ?> <span style="color:#dc2626;">*</span><?php } ?> · <?php echo $txt; ?>
                                                    </span>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                                <?php if ($can_edit) { ?>
                                    <?php echo form_open_multipart(admin_url('hr/upload_document/' . $employee['staffid'])); ?>
                                    <div class="row">
                                        <div class="col-md-3"><input type="text" name="title" class="form-control" placeholder="Document title" required></div>
                                        <div class="col-md-2">
                                            <select name="doc_type" class="form-control">
                                                <?php foreach (['ID Proof', 'Address Proof', 'Degree / Certificate', 'Medical Registration', 'License', 'Contract / Offer Letter', 'Experience Letter', 'Vaccination', 'Other'] as $t) { ?>
                                                    <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2"><input type="date" name="issue_date" class="form-control" title="Issue date"></div>
                                        <div class="col-md-2"><input type="date" name="expiry_date" class="form-control" title="Expiry date"></div>
                                        <div class="col-md-2"><input type="file" name="document" required></div>
                                        <div class="col-md-1"><button type="submit" class="btn btn-primary btn-block">Upload</button></div>
                                    </div>
                                    <?php echo form_close(); ?>
                                    <hr>
                                <?php } ?>
                                <?php
                                $doc_status_meta = [
                                    'verified'  => ['label' => 'Verified', 'cls' => 'success'],
                                    'submitted' => ['label' => 'Pending review', 'cls' => 'warning'],
                                    'rejected'  => ['label' => 'Rejected', 'cls' => 'danger'],
                                ];
                                ?>
                                <table class="table table-striped">
                                    <thead><tr><th>Title</th><th>Type</th><th>Source</th><th>Status</th><th>Issued</th><th>Expires</th><th>Uploaded</th><th class="text-right">Actions</th></tr></thead>
                                    <tbody>
                                        <?php if (!count($documents)) { ?>
                                            <tr><td colspan="8" class="text-muted">No documents uploaded.</td></tr>
                                        <?php }
                                        foreach ($documents as $doc) {
                                            $expiring = $doc['expiry_date'] && strtotime($doc['expiry_date']) < strtotime('+30 days');
                                            $source   = $doc['source'] ?? 'hr';
                                            $status   = $doc['status'] ?? 'verified';
                                            $sm       = $doc_status_meta[$status] ?? ['label' => ucfirst($status), 'cls' => 'default']; ?>
                                            <tr>
                                                <td><?php echo html_escape($doc['title']); ?></td>
                                                <td><?php echo html_escape($doc['doc_type']); ?></td>
                                                <td><?php echo $source === 'employee' ? '<span class="label label-info">Employee</span>' : '<span class="label label-default">HR</span>'; ?></td>
                                                <td><span class="label label-<?php echo $sm['cls']; ?>"><?php echo $sm['label']; ?></span>
                                                    <?php if ($status === 'rejected' && !empty($doc['review_note'])) { ?><br><small class="text-muted"><?php echo html_escape($doc['review_note']); ?></small><?php } ?>
                                                </td>
                                                <td><?php echo $doc['issue_date'] ? _d($doc['issue_date']) : '-'; ?></td>
                                                <td><?php echo $doc['expiry_date'] ? ('<span class="' . ($expiring ? 'text-danger bold' : '') . '">' . _d($doc['expiry_date']) . '</span>') : '-'; ?></td>
                                                <td><?php echo _dt($doc['created_at']); ?></td>
                                                <td class="text-right">
                                                    <?php
                                                    $doc_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                                    $doc_previewable = in_array($doc_ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif']);
                                                    ?>
                                                    <?php if ($doc_previewable) { ?>
                                                        <button type="button" class="btn btn-info btn-icon hr-doc-preview" title="Preview"
                                                            data-id="<?php echo (int) $doc['id']; ?>" data-ext="<?php echo $doc_ext; ?>"
                                                            data-title="<?php echo html_escape($doc['title']); ?>"><i class="fa fa-eye"></i></button>
                                                    <?php } ?>
                                                    <a href="<?php echo admin_url('hr/download_document/' . $doc['id']); ?>" class="btn btn-default btn-icon" title="Download"><i class="fa fa-download"></i></a>
                                                    <?php if (($can_edit) && $source === 'employee' && $status !== 'verified') { ?>
                                                        <a href="<?php echo admin_url('hr/verify_document/' . $doc['id'] . '/verified'); ?>" class="btn btn-success btn-icon" title="Verify"><i class="fa fa-check"></i></a>
                                                    <?php } ?>
                                                    <?php if (($can_edit) && $source === 'employee' && $status !== 'rejected') { ?>
                                                        <button type="button" class="btn btn-warning btn-icon" title="Reject" onclick="hrRejectDoc(<?php echo (int) $doc['id']; ?>)"><i class="fa fa-times"></i></button>
                                                    <?php } ?>
                                                    <?php if (has_permission('hr_employees', '', 'delete') || is_admin()) { ?>
                                                        <a href="<?php echo admin_url('hr/delete_document/' . $doc['id']); ?>" class="btn btn-danger btn-icon _delete" title="Delete"><i class="fa fa-remove"></i></a>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                <?php if ($can_edit) { ?>
                                    <?php echo form_open(admin_url('hr/verify_document/0/rejected'), ['id' => 'hr_reject_doc_form', 'style' => 'display:none;']); ?>
                                    <input type="hidden" name="note" value="">
                                    <?php echo form_close(); ?>
                                    <script>
                                        function hrRejectDoc(id) {
                                            var note = prompt('Reason for rejection (shown to the employee):', '');
                                            if (note === null) { return; }
                                            var f = document.getElementById('hr_reject_doc_form');
                                            f.action = '<?php echo admin_url('hr/verify_document/'); ?>' + id + '/rejected';
                                            f.querySelector('[name=note]').value = note;
                                            f.submit();
                                        }
                                    </script>
                                <?php } ?>

                                <!-- Document live-preview modal (PDF / image, no download needed) -->
                                <div class="modal fade" id="hr-doc-preview-modal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                <h4 class="modal-title"><i class="fa fa-eye"></i> <span id="hr-doc-preview-title"></span></h4>
                                            </div>
                                            <div class="modal-body" style="padding:0;background:#525659;position:relative;">
                                                <div id="hr-doc-toolbar" style="display:none;position:absolute;top:10px;left:50%;transform:translateX(-50%);z-index:5;background:rgba(20,25,28,.82);border-radius:8px;padding:5px 7px;align-items:center;gap:3px;">
                                                    <button type="button" class="btn btn-xs btn-default" data-act="zoomout" title="Zoom out"><i class="fa fa-search-minus"></i></button>
                                                    <span id="hr-doc-zoom" style="color:#fff;font-size:12px;min-width:46px;text-align:center;">100%</span>
                                                    <button type="button" class="btn btn-xs btn-default" data-act="zoomin" title="Zoom in"><i class="fa fa-search-plus"></i></button>
                                                    <span style="display:inline-block;width:1px;height:18px;background:rgba(255,255,255,.25);margin:0 3px;"></span>
                                                    <button type="button" class="btn btn-xs btn-default" data-act="rotl" title="Rotate left"><i class="fa fa-rotate-left"></i></button>
                                                    <button type="button" class="btn btn-xs btn-default" data-act="rotr" title="Rotate right"><i class="fa fa-rotate-right"></i></button>
                                                    <span style="display:inline-block;width:1px;height:18px;background:rgba(255,255,255,.25);margin:0 3px;"></span>
                                                    <button type="button" class="btn btn-xs btn-default" data-act="reset" title="Reset"><i class="fa fa-refresh"></i></button>
                                                </div>
                                                <div id="hr-doc-stage" style="height:78vh;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                                                    <iframe id="hr-doc-preview-frame" style="width:100%;height:78vh;border:0;display:none;background:#fff;" title="Document preview"></iframe>
                                                    <img id="hr-doc-preview-img" src="" alt="" style="max-width:100%;max-height:78vh;object-fit:contain;display:none;transform-origin:center center;cursor:grab;user-select:none;-webkit-user-drag:none;">
                                                    <div id="hr-doc-preview-msg" style="display:none;color:#fff;padding:40px;text-align:center;">
                                                        <i class="fa fa-file-o" style="font-size:40px;opacity:.6;"></i>
                                                        <p style="margin-top:12px;">This file type can't be previewed in the browser. Please download it instead.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a id="hr-doc-preview-download" href="#" class="btn btn-info pull-left"><i class="fa fa-download"></i> Download</a>
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    // Vanilla JS: this runs BEFORE init_tail (jQuery loads in the admin
                                    // footer), so bind natively and defer any jQuery/Bootstrap use until
                                    // it exists. URLs are embedded from PHP (admin_url JS var is footer-only).
                                    (function () {
                                        var previewBase  = '<?php echo admin_url('hr/preview_document/'); ?>';
                                        var downloadBase = '<?php echo admin_url('hr/download_document/'); ?>';
                                        var imgExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                                        function el(id) { return document.getElementById(id); }
                                        function whenJQ(cb) { if (window.jQuery) { cb(window.jQuery); } else { setTimeout(function () { whenJQ(cb); }, 150); } }

                                        // --- image zoom / rotate / pan state ---
                                        var zoom = 1, rot = 0, tx = 0, ty = 0;
                                        function applyTransform() {
                                            var img = el('hr-doc-preview-img');
                                            img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) rotate(' + rot + 'deg) scale(' + zoom + ')';
                                            el('hr-doc-zoom').textContent = Math.round(zoom * 100) + '%';
                                        }
                                        function resetTransform() { zoom = 1; rot = 0; tx = 0; ty = 0; applyTransform(); }

                                        document.addEventListener('click', function (e) {
                                            var btn = e.target.closest ? e.target.closest('.hr-doc-preview') : null;
                                            if (!btn) { return; }
                                            e.preventDefault();
                                            var id = btn.getAttribute('data-id');
                                            var ext = (btn.getAttribute('data-ext') || '').toLowerCase();

                                            el('hr-doc-preview-title').textContent = btn.getAttribute('data-title') || 'Document';
                                            el('hr-doc-preview-download').setAttribute('href', downloadBase + id);

                                            var frame = el('hr-doc-preview-frame'), img = el('hr-doc-preview-img'), msg = el('hr-doc-preview-msg');
                                            frame.style.display = 'none'; frame.setAttribute('src', 'about:blank');
                                            img.style.display = 'none'; img.setAttribute('src', '');
                                            msg.style.display = 'none';
                                            resetTransform();

                                            var url = previewBase + id;
                                            var isImg = imgExt.indexOf(ext) !== -1;
                                            if (ext === 'pdf') { frame.setAttribute('src', url); frame.style.display = ''; }
                                            else if (isImg) { img.setAttribute('src', url); img.style.display = ''; }
                                            else { msg.style.display = ''; }
                                            // Zoom/rotate toolbar only for images (PDFs have the browser's own viewer).
                                            el('hr-doc-toolbar').style.display = isImg ? 'flex' : 'none';

                                            whenJQ(function ($) { $('#hr-doc-preview-modal').modal('show'); });
                                        });

                                        // Toolbar buttons
                                        el('hr-doc-toolbar').addEventListener('click', function (e) {
                                            var b = e.target.closest ? e.target.closest('[data-act]') : null;
                                            if (!b) { return; }
                                            switch (b.getAttribute('data-act')) {
                                                case 'zoomin':  zoom = Math.min(8, zoom * 1.25); break;
                                                case 'zoomout': zoom = Math.max(0.2, zoom / 1.25); break;
                                                case 'rotl':    rot -= 90; break;
                                                case 'rotr':    rot += 90; break;
                                                case 'reset':   zoom = 1; rot = 0; tx = 0; ty = 0; break;
                                            }
                                            applyTransform();
                                        });

                                        // Mouse-wheel zoom over the image
                                        el('hr-doc-stage').addEventListener('wheel', function (e) {
                                            if (el('hr-doc-preview-img').style.display === 'none') { return; }
                                            e.preventDefault();
                                            zoom = e.deltaY < 0 ? Math.min(8, zoom * 1.12) : Math.max(0.2, zoom / 1.12);
                                            applyTransform();
                                        }, { passive: false });

                                        // Drag to pan
                                        var dragging = false, sx = 0, sy = 0;
                                        el('hr-doc-preview-img').addEventListener('mousedown', function (e) {
                                            dragging = true; sx = e.clientX - tx; sy = e.clientY - ty;
                                            this.style.cursor = 'grabbing'; e.preventDefault();
                                        });
                                        document.addEventListener('mousemove', function (e) {
                                            if (!dragging) { return; }
                                            tx = e.clientX - sx; ty = e.clientY - sy; applyTransform();
                                        });
                                        document.addEventListener('mouseup', function () {
                                            if (dragging) { dragging = false; el('hr-doc-preview-img').style.cursor = 'grab'; }
                                        });

                                        // Free the iframe when the modal closes so a large PDF stops rendering.
                                        whenJQ(function ($) {
                                            $('#hr-doc-preview-modal').on('hidden.bs.modal', function () {
                                                el('hr-doc-preview-frame').setAttribute('src', 'about:blank');
                                                el('hr-doc-preview-img').setAttribute('src', '');
                                            });
                                        });
                                    })();
                                </script>
                            </div>

                            <!-- ============================== FAMILY & NOMINEES -->
                            <div class="tab-pane <?php echo $active_tab === 'family' ? 'active' : ''; ?>" id="tab_family">
                                <?php
                                $hr_relations  = hr_relation_options();
                                $hr_purposes   = hr_nominee_purposes();
                                $can_delete_hr = has_permission('hr_employees', '', 'delete') || is_admin();
                                // Age at today, used for the "Minor" flag (a minor nominee needs a guardian).
                                $hr_age = function ($dob) {
                                    if (empty($dob) || $dob === '0000-00-00') {
                                        return null;
                                    }
                                    $d = date_create($dob);

                                    return $d ? (int) $d->diff(new DateTime('today'))->y : null;
                                };
                                ?>

                                <!-- ---------------------------------------- Family -->
                                <div class="clearfix">
                                    <h4 class="bold pull-left" style="margin-top:0;">Family Details
                                        <small class="text-muted">— dependants, insurance &amp; ESI records</small>
                                    </h4>
                                    <?php if ($can_edit) { ?>
                                        <button type="button" class="btn btn-primary btn-sm pull-right hr-family-add"><i class="fa fa-plus"></i> Add Family Member</button>
                                    <?php } ?>
                                </div>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th><th>Relation</th><th>Date of Birth</th><th>Gender</th>
                                            <th>Occupation</th><th>Phone</th><th>Aadhaar</th><th></th>
                                            <?php if ($can_edit || $can_delete_hr) { ?><th class="text-right">Actions</th><?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!count($family)) { ?>
                                            <tr><td colspan="<?php echo ($can_edit || $can_delete_hr) ? 9 : 8; ?>" class="text-muted">No family members recorded.</td></tr>
                                        <?php }
                                        foreach ($family as $fm) {
                                            $age = $hr_age($fm['date_of_birth']); ?>
                                            <tr>
                                                <td class="bold"><?php echo html_escape($fm['name']); ?>
                                                    <?php if (!empty($fm['blood_group'])) { ?><span class="text-muted" style="font-size:11px;"> · <?php echo html_escape($fm['blood_group']); ?></span><?php } ?>
                                                    <?php if (!empty($fm['notes'])) { ?><br><small class="text-muted"><?php echo html_escape($fm['notes']); ?></small><?php } ?>
                                                </td>
                                                <td><?php echo html_escape((string) $fm['relation']) ?: '—'; ?></td>
                                                <td><?php echo $fm['date_of_birth'] ? _d($fm['date_of_birth']) . ($age !== null ? ' <span class="text-muted">(' . $age . 'y)</span>' : '') : '—'; ?></td>
                                                <td><?php echo $fm['gender'] ? ucfirst(html_escape($fm['gender'])) : '—'; ?></td>
                                                <td><?php echo html_escape((string) $fm['occupation']) ?: '—'; ?></td>
                                                <td><?php echo html_escape((string) $fm['phone']) ?: '—'; ?></td>
                                                <td><?php echo $fm['aadhaar_number'] ? html_escape(hr_format_aadhaar($fm['aadhaar_number'])) : '—'; ?></td>
                                                <td>
                                                    <?php if (!empty($fm['is_dependent'])) { ?><span class="label label-info">Dependent</span> <?php } ?>
                                                    <?php if (!empty($fm['is_emergency_contact'])) { ?><span class="label label-warning">Emergency</span><?php } ?>
                                                </td>
                                                <?php if ($can_edit || $can_delete_hr) { ?>
                                                    <td class="text-right">
                                                        <?php if ($can_edit) { ?>
                                                            <button type="button" class="btn btn-default btn-icon hr-family-edit" data-id="<?php echo (int) $fm['id']; ?>" title="Edit"><i class="fa fa-pencil"></i></button>
                                                        <?php } ?>
                                                        <?php if ($can_delete_hr) { ?>
                                                            <a href="<?php echo admin_url('hr/delete_family_member/' . $employee['staffid'] . '/' . $fm['id']); ?>" class="btn btn-danger btn-icon _delete" title="Delete"><i class="fa fa-remove"></i></a>
                                                        <?php } ?>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>

                                <!-- --------------------------------------- Nominees -->
                                <div class="clearfix mtop25">
                                    <h4 class="bold pull-left" style="margin-top:0;">Nominees
                                        <small class="text-muted">— PF, ESI, gratuity &amp; insurance nominations</small>
                                    </h4>
                                    <?php if ($can_edit) { ?>
                                        <button type="button" class="btn btn-primary btn-sm pull-right hr-nominee-add"><i class="fa fa-plus"></i> Add Nominee</button>
                                    <?php } ?>
                                </div>
                                <?php if (!empty($nominee_share_totals)) { ?>
                                    <div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:6px;">
                                        <?php foreach ($nominee_share_totals as $scheme => $total) {
                                            $label = $hr_purposes[$scheme]['label'] ?? ucfirst($scheme);
                                            $ok    = abs(round($total, 2) - 100) < 0.01; ?>
                                            <span class="label label-<?php echo $ok ? 'success' : 'danger'; ?>" style="font-weight:600;">
                                                <i class="fa <?php echo $ok ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
                                                <?php echo html_escape($label); ?>: <?php echo rtrim(rtrim(number_format((float) $total, 2), '0'), '.'); ?>%
                                            </span>
                                        <?php } ?>
                                        <small class="text-muted" style="align-self:center;">Shares should total 100% per scheme.</small>
                                    </div>
                                <?php } ?>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th><th>Relation</th><th>Nominated For</th><th>Share</th>
                                            <th>Date of Birth</th><th>Phone</th><th>Aadhaar</th><th>Guardian (if minor)</th>
                                            <?php if ($can_edit || $can_delete_hr) { ?><th class="text-right">Actions</th><?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!count($nominees)) { ?>
                                            <tr><td colspan="<?php echo ($can_edit || $can_delete_hr) ? 9 : 8; ?>" class="text-muted">No nominees recorded.</td></tr>
                                        <?php }
                                        foreach ($nominees as $nm) {
                                            $age   = $hr_age($nm['date_of_birth']);
                                            $minor = ($age !== null && $age < 18);
                                            $pm    = $hr_purposes[$nm['nominee_for']] ?? ['label' => ucfirst($nm['nominee_for']), 'class' => 'default']; ?>
                                            <tr>
                                                <td class="bold"><?php echo html_escape($nm['name']); ?>
                                                    <?php if ($minor) { ?><span class="label label-warning">Minor</span><?php } ?>
                                                    <?php if (!empty($nm['address'])) { ?><br><small class="text-muted"><?php echo html_escape($nm['address']); ?></small><?php } ?>
                                                    <?php if (!empty($nm['notes'])) { ?><br><small class="text-muted"><?php echo html_escape($nm['notes']); ?></small><?php } ?>
                                                </td>
                                                <td><?php echo html_escape((string) $nm['relation']) ?: '—'; ?></td>
                                                <td><span class="label label-<?php echo $pm['class']; ?>"><?php echo html_escape($pm['label']); ?></span></td>
                                                <td class="bold"><?php echo rtrim(rtrim(number_format((float) $nm['share_percent'], 2), '0'), '.'); ?>%</td>
                                                <td><?php echo $nm['date_of_birth'] ? _d($nm['date_of_birth']) . ($age !== null ? ' <span class="text-muted">(' . $age . 'y)</span>' : '') : '—'; ?></td>
                                                <td><?php echo html_escape((string) $nm['phone']) ?: '—'; ?></td>
                                                <td><?php echo $nm['aadhaar_number'] ? html_escape(hr_format_aadhaar($nm['aadhaar_number'])) : '—'; ?></td>
                                                <td>
                                                    <?php if (!empty($nm['guardian_name'])) { ?>
                                                        <?php echo html_escape($nm['guardian_name']); ?>
                                                        <?php if (!empty($nm['guardian_relation'])) { ?><br><small class="text-muted"><?php echo html_escape($nm['guardian_relation']); ?></small><?php } ?>
                                                    <?php } elseif ($minor) { ?>
                                                        <span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Guardian required</span>
                                                    <?php } else { ?>—<?php } ?>
                                                </td>
                                                <?php if ($can_edit || $can_delete_hr) { ?>
                                                    <td class="text-right">
                                                        <?php if ($can_edit) { ?>
                                                            <button type="button" class="btn btn-default btn-icon hr-nominee-edit" data-id="<?php echo (int) $nm['id']; ?>" title="Edit"><i class="fa fa-pencil"></i></button>
                                                        <?php } ?>
                                                        <?php if ($can_delete_hr) { ?>
                                                            <a href="<?php echo admin_url('hr/delete_nominee/' . $employee['staffid'] . '/' . $nm['id']); ?>" class="btn btn-danger btn-icon _delete" title="Delete"><i class="fa fa-remove"></i></a>
                                                        <?php } ?>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>

                                <?php if ($can_edit) { ?>
                                    <!-- Family member modal (add / edit) -->
                                    <div class="modal fade" id="hr-family-modal" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <?php echo form_open(admin_url('hr/save_family_member/' . $employee['staffid'])); ?>
                                                <input type="hidden" name="id" value="0">
                                                <div class="modal-header">
                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                    <h4 class="modal-title"><i class="fa fa-users"></i> <span class="hr-fm-title">Add Family Member</span></h4>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Name <span class="text-danger">*</span></label>
                                                                <input type="text" name="name" class="form-control" required></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Relation</label>
                                                                <select name="relation" class="form-control">
                                                                    <option value=""></option>
                                                                    <?php foreach ($hr_relations as $rel) { ?>
                                                                        <option value="<?php echo html_escape($rel); ?>"><?php echo html_escape($rel); ?></option>
                                                                    <?php } ?>
                                                                </select></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Date of Birth</label>
                                                                <input type="date" name="date_of_birth" class="form-control"></div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="form-group"><label>Gender</label>
                                                                <select name="gender" class="form-control">
                                                                    <option value=""></option>
                                                                    <option value="female">Female</option>
                                                                    <option value="male">Male</option>
                                                                    <option value="other">Other</option>
                                                                </select></div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="form-group"><label>Blood Group</label>
                                                                <select name="blood_group" class="form-control">
                                                                    <option value=""></option>
                                                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg) { ?>
                                                                        <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
                                                                    <?php } ?>
                                                                </select></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="form-group"><label>Occupation</label>
                                                                <input type="text" name="occupation" class="form-control"></div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group"><label>Phone</label>
                                                                <input type="text" name="phone" class="form-control"></div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group"><label>Aadhaar Number</label>
                                                                <input type="text" name="aadhaar_number" class="form-control hr-aadhaar-field" inputmode="numeric" maxlength="14" placeholder="1234 5678 9012"></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="checkbox checkbox-primary">
                                                                <input type="checkbox" name="is_dependent" value="1" id="hr-fm-dependent">
                                                                <label for="hr-fm-dependent">Dependent (insurance / ESI)</label>
                                                            </div>
                                                            <div class="checkbox checkbox-primary">
                                                                <input type="checkbox" name="is_emergency_contact" value="1" id="hr-fm-emergency">
                                                                <label for="hr-fm-emergency">Can be contacted in an emergency</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="form-group"><label>Notes</label>
                                                                <textarea name="notes" class="form-control" rows="2"></textarea></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </div>
                                                <?php echo form_close(); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Nominee modal (add / edit) -->
                                    <div class="modal fade" id="hr-nominee-modal" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <?php echo form_open(admin_url('hr/save_nominee/' . $employee['staffid'])); ?>
                                                <input type="hidden" name="id" value="0">
                                                <div class="modal-header">
                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                    <h4 class="modal-title"><i class="fa fa-user-plus"></i> <span class="hr-nm-title">Add Nominee</span></h4>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Name <span class="text-danger">*</span></label>
                                                                <input type="text" name="name" class="form-control" required></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Relation</label>
                                                                <select name="relation" class="form-control">
                                                                    <option value=""></option>
                                                                    <?php foreach ($hr_relations as $rel) { ?>
                                                                        <option value="<?php echo html_escape($rel); ?>"><?php echo html_escape($rel); ?></option>
                                                                    <?php } ?>
                                                                </select></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="form-group"><label>Nominated For</label>
                                                                <select name="nominee_for" class="form-control">
                                                                    <?php foreach ($hr_purposes as $k => $p) { ?>
                                                                        <option value="<?php echo $k; ?>"><?php echo html_escape($p['label']); ?></option>
                                                                    <?php } ?>
                                                                </select></div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group"><label>Share (%)</label>
                                                                <input type="number" name="share_percent" class="form-control" min="0" max="100" step="0.01" value="100"></div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group"><label>Date of Birth</label>
                                                                <input type="date" name="date_of_birth" class="form-control"></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Phone</label>
                                                                <input type="text" name="phone" class="form-control"></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Aadhaar Number</label>
                                                                <input type="text" name="aadhaar_number" class="form-control hr-aadhaar-field" inputmode="numeric" maxlength="14" placeholder="1234 5678 9012"></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group"><label>Address</label>
                                                                <textarea name="address" class="form-control" rows="2"></textarea></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Guardian Name <small class="text-muted">(if nominee is a minor)</small></label>
                                                                <input type="text" name="guardian_name" class="form-control"></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group"><label>Guardian Relation</label>
                                                                <input type="text" name="guardian_relation" class="form-control"></div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="form-group"><label>Notes</label>
                                                                <textarea name="notes" class="form-control" rows="2"></textarea></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </div>
                                                <?php echo form_close(); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                        // Family / nominee add-edit modals. Vanilla JS with a jQuery wait:
                                        // jQuery + Bootstrap load in the admin footer, after this script.
                                        (function () {
                                            var family   = <?php echo json_encode($family, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                                            var nominees = <?php echo json_encode($nominees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

                                            function whenJQ(cb) {
                                                if (window.jQuery) { cb(window.jQuery); } else { setTimeout(function () { whenJQ(cb); }, 150); }
                                            }
                                            function byId(rows, id) {
                                                for (var i = 0; i < rows.length; i++) {
                                                    if (String(rows[i].id) === String(id)) { return rows[i]; }
                                                }
                                                return null;
                                            }
                                            function groupAadhaar(v) {
                                                var digits = String(v || '').replace(/\D/g, '').slice(0, 12);
                                                return digits.replace(/(\d{4})(?=\d)/g, '$1 ');
                                            }
                                            // Reset a modal form, then fill it from a row (null = add mode).
                                            function fill(modalId, titleSel, addLabel, editLabel, row, defaults) {
                                                var modal = document.getElementById(modalId);
                                                var form  = modal.querySelector('form');
                                                form.reset();
                                                modal.querySelector(titleSel).textContent = row ? editLabel : addLabel;
                                                var values = row || (defaults || {});
                                                Object.keys(values).forEach(function (key) {
                                                    var field = form.querySelector('[name="' + key + '"]');
                                                    if (!field) { return; }
                                                    var val = values[key];
                                                    if (field.type === 'checkbox') {
                                                        field.checked = (String(val) === '1');
                                                    } else if (key === 'aadhaar_number') {
                                                        field.value = groupAadhaar(val);
                                                    } else {
                                                        field.value = (val === null || val === undefined) ? '' : val;
                                                    }
                                                });
                                                form.querySelector('[name="id"]').value = row ? row.id : 0;
                                                whenJQ(function ($) { $('#' + modalId).modal('show'); });
                                            }

                                            document.addEventListener('click', function (e) {
                                                if (!e.target.closest) { return; }
                                                var t;
                                                if ((t = e.target.closest('.hr-family-add'))) {
                                                    fill('hr-family-modal', '.hr-fm-title', 'Add Family Member', 'Edit Family Member', null);
                                                } else if ((t = e.target.closest('.hr-family-edit'))) {
                                                    fill('hr-family-modal', '.hr-fm-title', 'Add Family Member', 'Edit Family Member', byId(family, t.getAttribute('data-id')));
                                                } else if ((t = e.target.closest('.hr-nominee-add'))) {
                                                    fill('hr-nominee-modal', '.hr-nm-title', 'Add Nominee', 'Edit Nominee', null, { share_percent: 100, nominee_for: 'general' });
                                                } else if ((t = e.target.closest('.hr-nominee-edit'))) {
                                                    fill('hr-nominee-modal', '.hr-nm-title', 'Add Nominee', 'Edit Nominee', byId(nominees, t.getAttribute('data-id')));
                                                }
                                            });

                                            // Group Aadhaar inputs inside the modals as they are typed.
                                            document.addEventListener('input', function (e) {
                                                if (e.target && e.target.classList && e.target.classList.contains('hr-aadhaar-field')) {
                                                    e.target.value = groupAadhaar(e.target.value);
                                                }
                                            });
                                        })();
                                    </script>
                                <?php } ?>
                            </div>

                            <!-- ============================== LEAVE -->
                            <div class="tab-pane <?php echo $active_tab === 'leave' ? 'active' : ''; ?>" id="tab_leave">
                                <h4 class="bold">Leave Balance <?php echo date('Y'); ?></h4>
                                <table class="table table-striped">
                                    <thead><tr><th>Leave Type</th><th>Allocated</th><th>Carried Fwd</th><th>Used</th><th>Balance</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($leave_types as $lt) {
                                            $alloc = $allocations[$employee['staffid']][$lt['id']] ?? $lt['days_per_year'];
                                            $cf    = $carried[$employee['staffid']][$lt['id']] ?? 0;
                                            $used  = $leave_used[$employee['staffid']][$lt['id']] ?? 0; ?>
                                            <tr>
                                                <td><i class="fa <?php echo hr_leave_type_icon($lt); ?>" style="color:<?php echo html_escape($lt['color']); ?>;"></i> <?php echo html_escape($lt['name']); ?> <span class="text-muted" style="font-size:11px;">(<?php echo html_escape($lt['code']); ?>)</span></td>
                                                <td><?php echo (float) $alloc; ?></td>
                                                <td><?php echo $cf > 0 ? '<span style="color:#0891b2;">+' . (float) $cf . '</span>' : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo (float) $used; ?></td>
                                                <td class="bold"><?php echo (float) $alloc + (float) $cf - (float) $used; ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                <h4 class="bold mtop15">Leave History</h4>
                                <table class="table table-striped">
                                    <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Reason</th></tr></thead>
                                    <tbody>
                                        <?php if (!count($leaves)) { ?>
                                            <tr><td colspan="6" class="text-muted">No leave requests.</td></tr>
                                        <?php }
                                        $status_labels = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'default'];
                                        foreach ($leaves as $lr) { ?>
                                            <tr>
                                                <td><?php echo html_escape($lr['type_name']); ?></td>
                                                <td><?php echo _d($lr['from_date']); ?></td>
                                                <td><?php echo _d($lr['to_date']); ?></td>
                                                <td><?php echo (float) $lr['days']; ?></td>
                                                <td><span class="label label-<?php echo $status_labels[$lr['status']] ?? 'default'; ?>"><?php echo ucfirst($lr['status']); ?></span></td>
                                                <td><?php echo html_escape($lr['reason']); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- ============================== ATTENDANCE -->
                            <div class="tab-pane <?php echo $active_tab === 'attendance' ? 'active' : ''; ?>" id="tab_attendance">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h4 class="bold">Current Shift</h4>
                                        <?php if ($shift) { ?>
                                            <p><strong><?php echo html_escape($shift['name']); ?></strong> &middot;
                                                <?php echo date('g:i A', strtotime($shift['start_time'])) . ' - ' . date('g:i A', strtotime($shift['end_time'])); ?>
                                                <span class="text-muted">(grace <?php echo (int) $shift['grace_minutes']; ?> min)</span></p>
                                        <?php } else { ?>
                                            <p class="text-muted">No shift assigned and no default shift configured.</p>
                                        <?php } ?>
                                        <a href="<?php echo admin_url('hr/shifts'); ?>" class="btn btn-default btn-sm">Manage shifts &amp; roster</a>
                                    </div>
                                    <div class="col-md-6">
                                        <h4 class="bold">This Month (<?php echo date('F Y'); ?>)</h4>
                                        <table class="table table-striped">
                                            <tbody>
                                                <?php foreach (hr_attendance_statuses() as $key => $st) {
                                                    $cnt = isset($attendance_summary[$key]) ? (int) $attendance_summary[$key]['cnt'] : 0; ?>
                                                    <tr>
                                                        <td><span class="label" style="background:<?php echo $st['color']; ?>;color:#fff;"><?php echo $st['short']; ?></span> <?php echo $st['label']; ?></td>
                                                        <td class="bold"><?php echo $cnt; ?></td>
                                                    </tr>
                                                <?php } ?>
                                                <tr>
                                                    <td>Late arrivals</td>
                                                    <td class="bold"><?php echo isset($attendance_summary['present']) ? (int) $attendance_summary['present']['late_cnt'] : 0; ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <a href="<?php echo admin_url('hr/attendance_register?month=' . date('n') . '&year=' . date('Y')); ?>" class="btn btn-default btn-sm">Open monthly register</a>
                                    </div>
                                </div>
                            </div>

                            <!-- ============================== APPRAISALS -->
                            <div class="tab-pane <?php echo $active_tab === 'appraisals' ? 'active' : ''; ?>" id="tab_appraisals">
                                <?php if ($can_edit) { ?>
                                    <a href="<?php echo admin_url('hr/appraisal?staff_id=' . $employee['staffid']); ?>" class="btn btn-primary mbot15"><i class="fa fa-plus"></i> New Appraisal</a>
                                <?php } ?>
                                <table class="table table-striped">
                                    <thead><tr><th>Period</th><th>Reviewer</th><th>Overall Rating</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                                    <tbody>
                                        <?php if (!count($appraisals)) { ?>
                                            <tr><td colspan="5" class="text-muted">No appraisals recorded.</td></tr>
                                        <?php }
                                        foreach ($appraisals as $ap) { ?>
                                            <tr>
                                                <td><?php echo ($ap['period_from'] ? _d($ap['period_from']) : '?') . ' - ' . ($ap['period_to'] ? _d($ap['period_to']) : '?'); ?></td>
                                                <td><?php echo html_escape(trim(($ap['rev_first'] ?? '') . ' ' . ($ap['rev_last'] ?? ''))) ?: '-'; ?></td>
                                                <td><span class="bold"><?php echo (float) $ap['overall_rating']; ?></span> / 5</td>
                                                <td><span class="label label-<?php echo $ap['status'] === 'completed' ? 'success' : 'default'; ?>"><?php echo ucfirst($ap['status']); ?></span></td>
                                                <td class="text-right"><a href="<?php echo admin_url('hr/appraisal/' . $ap['id']); ?>" class="btn btn-default btn-icon"><i class="fa fa-pencil"></i></a></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- ============================== KRA & KPI -->
                            <div class="tab-pane <?php echo $active_tab === 'krakpi' ? 'active' : ''; ?>" id="tab_krakpi">
                                <?php echo get_instance()->load->view('hr/_kra_kpi_tab', [
                                    'employee'       => $employee,
                                    'kras'           => $kras,
                                    'kpis'           => $kpis,
                                    'kra_kpi_totals' => $kra_kpi_totals,
                                    'can_edit'       => $can_edit,
                                    'can_delete'     => has_permission('hr_employees', '', 'delete') || is_admin(),
                                ], true); ?>
                            </div>

                            <?php if (!empty($can_memos)) {
                                $memo_types = hr_memo_types();
                                $memo_sev   = hr_memo_severities();
                                $memo_st    = hr_memo_statuses();
                            ?>
                                <!-- ============================== MEMOS -->
                                <div class="tab-pane <?php echo $active_tab === 'memos' ? 'active' : ''; ?>" id="tab_memos">
                                    <?php if (has_permission('hr_memos', '', 'create') || is_admin()) { ?>
                                        <a href="<?php echo admin_url('hr/memos'); ?>" class="btn btn-danger mbot15"><i class="fa fa-file-signature"></i> Issue / manage memos</a>
                                    <?php } ?>
                                    <table class="table table-striped">
                                        <thead><tr><th>Type</th><th>Subject</th><th>Severity</th><th>Issued</th><th>Status</th><th class="text-right"></th></tr></thead>
                                        <tbody>
                                            <?php if (!count($memos)) { ?>
                                                <tr><td colspan="6" class="text-muted">No memos on record. 🎉</td></tr>
                                            <?php }
                                            foreach ($memos as $m) {
                                                $mt = $memo_types[$m['memo_type']] ?? $memo_types['other'];
                                                $ms = $memo_sev[$m['severity']] ?? $memo_sev['medium'];
                                                $mst = $memo_st[$m['status']] ?? $memo_st['issued'];
                                            ?>
                                                <tr>
                                                    <td><i class="fa <?php echo $mt['icon']; ?>" style="color:<?php echo $mt['color']; ?>;"></i> <?php echo html_escape($mt['label']); ?></td>
                                                    <td><?php echo html_escape($m['subject']); ?></td>
                                                    <td><span class="label label-<?php echo $ms['class']; ?>"><?php echo html_escape($ms['label']); ?></span></td>
                                                    <td style="font-size:12px;"><?php echo $m['created_at'] ? _d($m['created_at']) : '—'; ?></td>
                                                    <td><span class="label label-<?php echo $mst['class']; ?>"><?php echo html_escape($mst['label']); ?></span></td>
                                                    <td class="text-right"><a href="<?php echo admin_url('hr/memo/' . $m['id']); ?>" class="btn btn-default btn-icon"><i class="fa fa-eye"></i></a></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>

                            <?php if (!empty($can_onboarding)) {
                                $onb_phases = hr_onboarding_phases();
                                $onb_stat   = hr_onboarding_item_statuses();
                            ?>
                                <!-- ============================== ONBOARDING -->
                                <div class="tab-pane <?php echo $active_tab === 'onboarding' ? 'active' : ''; ?>" id="tab_onboarding">
                                    <?php if (!$onboarding) { ?>
                                        <p class="text-muted">No onboarding record for this employee.</p>
                                        <?php if (has_permission('hr_onboarding', '', 'create') || is_admin()) { ?>
                                            <a href="<?php echo admin_url('hr/onboarding'); ?>" class="btn btn-success"><i class="fa fa-clipboard-check"></i> Start onboarding</a>
                                        <?php } ?>
                                    <?php } else {
                                        $ot = count($onboarding_items);
                                        $od = count(array_filter($onboarding_items, function ($i) { return $i['status'] !== 'pending'; }));
                                        $op = $ot ? round($od / $ot * 100) : 0;
                                    ?>
                                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                            <div style="flex:1;min-width:200px;">
                                                <div class="progress" style="height:20px;margin:0;">
                                                    <div class="progress-bar progress-bar-success" style="width:<?php echo $op; ?>%;line-height:20px;"><?php echo $op; ?>% (<?php echo $od; ?>/<?php echo $ot; ?>)</div>
                                                </div>
                                            </div>
                                            <a href="<?php echo admin_url('hr/onboarding_board/' . $onboarding['id']); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-right"></i> Open board</a>
                                        </div>
                                        <table class="table" style="margin-top:12px;">
                                            <tbody>
                                                <?php foreach ($onboarding_items as $it) {
                                                    $stm = $onb_stat[$it['status']] ?? $onb_stat['pending'];
                                                    $ph  = $onb_phases[$it['phase']] ?? ['label' => $it['phase']];
                                                ?>
                                                    <tr>
                                                        <td style="width:30px;"><i class="fa <?php echo $stm['icon']; ?>" style="color:<?php echo $stm['color']; ?>;"></i></td>
                                                        <td><?php echo html_escape($it['title']); ?> <span class="text-muted" style="font-size:11px;">· <?php echo html_escape($ph['label']); ?></span></td>
                                                        <td style="font-size:11px;text-align:right;" class="text-muted"><?php echo $it['due_date'] ? _d($it['due_date']) : ''; ?></td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <?php if ($can_edit) {
                                $emp_ess = hr_ess_employee_config();
                                $ecfg    = $emp_ess[(string) $employee['staffid']] ?? null;
                                $eon     = !empty($ecfg['override']); ?>
                                <div class="tab-pane <?php echo $active_tab === 'selfservice' ? 'active' : ''; ?>" id="tab_selfservice">
                                    <p class="text-muted">
                                        Override the Employee Self-Service settings for <strong><?php echo html_escape(trim($employee['firstname'] . ' ' . $employee['lastname'])); ?></strong>.
                                        When off, this employee follows their <strong>role</strong> settings (or the global default).
                                        This takes precedence over both.
                                    </p>
                                    <?php echo form_open(admin_url('hr/save_ess_override/' . $employee['staffid'])); ?>
                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" id="ess_emp_override_<?php echo (int) $employee['staffid']; ?>" name="ess_emp_override[<?php echo (int) $employee['staffid']; ?>]" value="1" <?php echo $eon ? 'checked' : ''; ?> onchange="document.getElementById('ess_emp_body').style.display = this.checked ? 'block' : 'none';">
                                            <label for="ess_emp_override_<?php echo (int) $employee['staffid']; ?>"><strong>Use custom self-service settings for this employee</strong></label>
                                        </div>
                                        <div id="ess_emp_body" style="<?php echo $eon ? '' : 'display:none;'; ?>margin-top:10px;">
                                            <?php echo get_instance()->load->view('hr/_ess_config_block', ['prefix' => 'ess_emp', 'id' => (int) $employee['staffid'], 'cfg' => $ecfg], true); ?>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Override</button>
                                    <?php echo form_close(); ?>
                                </div>
                            <?php } ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<?php if ($can_edit) { ?>
<!-- Inline "add designation" modal (opened from the + next to the Designation field) -->
<div class="modal fade" id="hr-add-designation-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><i class="fa fa-tags"></i> Add Designation</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="hr-new-designation-name">Designation name</label>
                    <input type="text" id="hr-new-designation-name" class="form-control" placeholder="e.g. Staff Nurse">
                </div>
                <div class="form-group">
                    <label for="hr-new-designation-dept">Department <small class="text-muted">(optional)</small></label>
                    <select id="hr-new-designation-dept" class="form-control">
                        <option value="">Any department</option>
                        <?php foreach ($departments as $d) { ?>
                            <option value="<?php echo $d['departmentid']; ?>"><?php echo html_escape($d['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="hr-new-designation-role">Mapped Role <small class="text-muted">(optional)</small></label>
                    <select id="hr-new-designation-role" class="form-control">
                        <option value="">No role</option>
                        <?php foreach ($roles as $r) { ?>
                            <option value="<?php echo $r['roleid']; ?>"><?php echo html_escape($r['name']); ?></option>
                        <?php } ?>
                    </select>
                    <small class="text-muted">Choosing this designation for an employee will pre-fill their Role.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="hr-save-designation-btn"><i class="fa fa-check"></i> Add Designation</button>
            </div>
        </div>
    </div>
</div>
<script>
$(function () {
    // ---- Salary tab: component enable/disable ----
    $('#salary_components_table').on('change', '.sal-comp-toggle', function () {
        var $row = $(this).closest('tr');
        var on   = this.checked;
        $row.toggleClass('sal-disabled', !on);
        $row.find('.sal-comp-value').prop('disabled', !on);
    });

    // ---- Salary calculation card: proration mode switch ----
    $('#sal_proration_mode').on('change', function () {
        var $v  = $('#sal_earned_value');
        var raw = this.value === 'fullded' ? $v.data('fullded') : $v.data('prorated');
        var num = parseFloat(raw) || 0;
        var sym = '<?php echo isset($salary_preview) ? get_base_currency()->symbol : ''; ?>';
        $v.text(sym + ' ' + num.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    });

    var $modal    = $('#hr-add-designation-modal');
    var $name     = $('#hr-new-designation-name');
    var $dept     = $('#hr-new-designation-dept');
    var $roleMap  = $('#hr-new-designation-role');
    var $save     = $('#hr-save-designation-btn');
    var $select   = $('#hr-designation-select');
    var $roleSel  = $('#hr-role-select');

    // Designation -> Role mapping: when a designation with a mapped role is
    // picked, pre-fill the (still editable) Role dropdown.
    $select.on('changed.bs.select change', function () {
        var roleId = $select.find('option:selected').attr('data-role-id');
        if (roleId && roleId !== '0' && roleId !== '') {
            $roleSel.selectpicker('val', String(roleId));
        }
    });

    $modal.on('shown.bs.modal', function () { $name.trigger('focus'); });

    $name.on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); $save.trigger('click'); } });

    $save.on('click', function () {
        var name = $.trim($name.val());
        if (!name) { $name.trigger('focus'); return; }
        $save.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        var data = { name: name, department_id: $dept.val(), role_id: $roleMap.val() };
        if (typeof csrfData !== 'undefined') { data[csrfData.token_name] = csrfData.hash; }
        $.post('<?php echo admin_url('hr/add_designation_inline'); ?>', data, function (res) {
            $save.prop('disabled', false).html('<i class="fa fa-check"></i> Add Designation');
            if (res && res.success) {
                var newRole = res.role_id ? String(res.role_id) : '';
                $select.append($('<option>', { value: res.id, text: res.name, 'data-role-id': newRole || '0' }));
                $select.selectpicker('refresh');
                $select.selectpicker('val', String(res.id));
                if (newRole) { $roleSel.selectpicker('val', newRole); }
                $name.val('');
                $dept.val('');
                $roleMap.val('');
                $modal.modal('hide');
                if (typeof alert_float === 'function') { alert_float('success', 'Designation added.'); }
            } else {
                alert((res && res.message) ? res.message : 'Could not add designation.');
            }
        }, 'json').fail(function () {
            $save.prop('disabled', false).html('<i class="fa fa-check"></i> Add Designation');
            alert('Could not add designation. Please try again.');
        });
    });
});
</script>

<link href="<?php echo module_dir_url('hr', 'assets/css/cropper.min.css'); ?>?v=<?php echo $this->app_scripts->core_version(); ?>" rel="stylesheet" type="text/css" />
<style>
    /* Clickable header avatar with hover overlay */
    .hr-avatar-wrap { position: relative; width: 64px; height: 64px; border-radius: 50%; overflow: hidden; cursor: pointer; background: #eef1f4; box-shadow: 0 0 0 2px #fff, 0 0 0 3px #e3e8ee; }
    .hr-avatar-img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .hr-avatar-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #fff; background: rgba(14,77,84,.55); opacity: 0; transition: opacity .15s ease; font-size: 18px; }
    .hr-avatar-wrap:hover .hr-avatar-overlay { opacity: 1; }

    /* Photo editor modal */
    #hr-photo-modal .modal-body { padding: 0; }
    .hr-pe-body { display: flex; flex-wrap: wrap; }
    .hr-pe-stage { flex: 1 1 320px; min-width: 280px; background: #1f2a30; padding: 12px; }
    .hr-pe-stage .hr-pe-canvas { max-height: 340px; }
    .hr-pe-canvas img { max-width: 100%; display: block; }
    .hr-pe-empty { display: flex; align-items: center; justify-content: center; flex-direction: column; height: 300px; color: #9fb0b6; text-align: center; }
    .hr-pe-empty i { font-size: 42px; margin-bottom: 12px; }
    .hr-pe-side { flex: 0 0 220px; padding: 18px; border-left: 1px solid #e6ebef; }
    .hr-pe-preview-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #8a97a3; margin-bottom: 10px; text-align: center; }
    .hr-pe-preview { width: 140px; height: 140px; border-radius: 50%; overflow: hidden; margin: 0 auto 6px; background: #eef1f4; box-shadow: 0 0 0 4px #fff, 0 0 0 5px #e3e8ee; }
    .hr-pe-preview img { display: block; max-width: none; }
    .hr-pe-preview-sm { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; margin: 0 auto 14px; background: #eef1f4; box-shadow: 0 0 0 2px #fff, 0 0 0 3px #e3e8ee; }
    .hr-pe-zoom-row { display: flex; align-items: center; gap: 8px; margin: 14px 0 6px; }
    .hr-pe-zoom-row input[type=range] { flex: 1; }
    .hr-pe-tools { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
    .hr-pe-tools .btn { flex: 1; }
    .hr-pe-hint { font-size: 11px; color: #98a4b0; text-align: center; margin-top: 12px; line-height: 1.4; }
    @media (max-width: 600px) { .hr-pe-side { flex: 1 1 100%; border-left: 0; border-top: 1px solid #e6ebef; } }
</style>

<div class="modal fade" id="hr-photo-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><i class="fa fa-camera"></i> Employee Photo</h4>
            </div>
            <div class="modal-body">
                <div class="hr-pe-body">
                    <div class="hr-pe-stage">
                        <div class="hr-pe-canvas" id="hr-pe-canvas">
                            <img id="hr-pe-image" src="" alt="" style="display:none;">
                            <div class="hr-pe-empty" id="hr-pe-empty">
                                <i class="fa fa-image"></i>
                                <div>Choose a photo to start.<br>Drag to reposition, scroll or use the slider to zoom.</div>
                            </div>
                        </div>
                    </div>
                    <div class="hr-pe-side">
                        <div class="hr-pe-preview-label">Live preview</div>
                        <div class="hr-pe-preview"><div id="hr-pe-preview" style="width:100%;height:100%;"></div></div>
                        <div class="hr-pe-preview-sm"><div id="hr-pe-preview-sm" style="width:100%;height:100%;"></div></div>

                        <div class="hr-pe-zoom-row">
                            <i class="fa fa-picture-o" style="font-size:11px;color:#98a4b0;"></i>
                            <input type="range" id="hr-pe-zoom" min="0" max="1" step="0.01" value="0" disabled>
                            <i class="fa fa-picture-o" style="font-size:16px;color:#98a4b0;"></i>
                        </div>
                        <div class="hr-pe-tools">
                            <button type="button" class="btn btn-default btn-sm" id="hr-pe-rotate-l" title="Rotate left" disabled><i class="fa fa-rotate-left"></i></button>
                            <button type="button" class="btn btn-default btn-sm" id="hr-pe-rotate-r" title="Rotate right" disabled><i class="fa fa-rotate-right"></i></button>
                            <button type="button" class="btn btn-default btn-sm" id="hr-pe-reset" title="Reset" disabled><i class="fa fa-refresh"></i></button>
                        </div>
                        <div class="hr-pe-hint">JPG or PNG, square crop.<br>Output is centred in a circle everywhere.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <label class="btn btn-default btn-sm" style="margin:0;">
                        <i class="fa fa-folder-open-o"></i> <span id="hr-pe-choose-label">Choose photo</span>
                        <input type="file" id="hr-pe-file" accept="image/jpeg,image/png" style="display:none;">
                    </label>
                    <?php if ($hr_has_img) { ?>
                        <a href="<?php echo admin_url('hr/remove_employee_image/' . (int) $employee['staffid']); ?>"
                           class="btn btn-link btn-sm text-danger _delete"
                           style="color:#c0392b;">Remove current photo</a>
                    <?php } ?>
                </div>
                <div>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="hr-pe-save" disabled><i class="fa fa-check"></i> Save Photo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo module_dir_url('hr', 'assets/js/cropper.min.js'); ?>?v=<?php echo $this->app_scripts->core_version(); ?>"></script>
<script>
(function () {
    var HR_EMP_ID = <?php echo (int) $employee['staffid']; ?>;
    var HR_HAS_IMG = <?php echo $hr_has_img ? 'true' : 'false'; ?>;
    // Preload the higher-res thumb (320px) into the editor so re-cropping an
    // existing photo stays sharp.
    var HR_IMG_URL = <?php echo json_encode(staff_profile_image_url($employee['staffid'], 'thumb')); ?>;

    var cropper = null;
    var $img    = document.getElementById('hr-pe-image');
    var $empty  = document.getElementById('hr-pe-empty');
    var $zoom   = document.getElementById('hr-pe-zoom');
    var $save   = document.getElementById('hr-pe-save');
    var $file   = document.getElementById('hr-pe-file');
    var minZoom = 0, maxZoom = 3;

    window.hrOpenPhotoModal = function () {
        $('#hr-photo-modal').modal('show');
        // Pre-load the current photo so HR can simply re-crop / re-zoom it.
        if (HR_HAS_IMG && !cropper) {
            loadImage(HR_IMG_URL + (HR_IMG_URL.indexOf('?') > -1 ? '&' : '?') + 'cb=' + HR_EMP_ID);
        }
    };

    function setTools(enabled) {
        ['hr-pe-rotate-l', 'hr-pe-rotate-r', 'hr-pe-reset'].forEach(function (id) {
            document.getElementById(id).disabled = !enabled;
        });
        $zoom.disabled = !enabled;
        $save.disabled = !enabled;
    }

    function destroyCropper() {
        if (cropper) { cropper.destroy(); cropper = null; }
    }

    function loadImage(src) {
        destroyCropper();
        $empty.style.display = 'none';
        $img.style.display = 'block';
        $img.src = src;
        cropper = new Cropper($img, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            background: false,
            movable: true,
            zoomable: true,
            guides: false,
            center: true,
            preview: '#hr-pe-preview, #hr-pe-preview-sm',
            minCropBoxWidth: 80,
            minCropBoxHeight: 80,
            ready: function () {
                setTools(true);
                // Map the slider onto the natural..zoomed range of this image.
                var data = cropper.getImageData();
                minZoom = data.width / data.naturalWidth;
                maxZoom = minZoom * 4;
                $zoom.min = minZoom;
                $zoom.max = maxZoom;
                $zoom.step = (maxZoom - minZoom) / 100;
                $zoom.value = minZoom;
            },
            zoom: function (e) {
                // Keep the slider in sync with wheel / pinch zoom.
                var ratio = e.detail.ratio;
                if (ratio < minZoom) { e.preventDefault(); return; }
                $zoom.value = Math.min(Math.max(ratio, minZoom), maxZoom);
            }
        });
    }

    $file.addEventListener('change', function () {
        var f = this.files && this.files[0];
        if (!f) { return; }
        if (!/image\/(jpeg|png)/.test(f.type)) {
            alert_float('warning', 'Please choose a JPG or PNG image.');
            return;
        }
        document.getElementById('hr-pe-choose-label').textContent = 'Change photo';
        var reader = new FileReader();
        reader.onload = function (ev) { loadImage(ev.target.result); };
        reader.readAsDataURL(f);
        this.value = '';
    });

    $zoom.addEventListener('input', function () {
        if (cropper) { cropper.zoomTo(parseFloat(this.value)); }
    });
    document.getElementById('hr-pe-rotate-l').addEventListener('click', function () { if (cropper) cropper.rotate(-90); });
    document.getElementById('hr-pe-rotate-r').addEventListener('click', function () { if (cropper) cropper.rotate(90); });
    document.getElementById('hr-pe-reset').addEventListener('click', function () {
        if (cropper) { cropper.reset(); $zoom.value = minZoom; }
    });

    $save.addEventListener('click', function () {
        if (!cropper) { return; }
        var canvas = cropper.getCroppedCanvas({
            width: 400, height: 400,
            imageSmoothingEnabled: true, imageSmoothingQuality: 'high',
            fillColor: '#fff'
        });
        if (!canvas) { return; }
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
        canvas.toBlob(function (blob) {
            var fd = new FormData();
            fd.append('profile_image', blob, 'employee_' + HR_EMP_ID + '.jpg');
            if (typeof csrfData !== 'undefined') {
                fd.append(csrfData['token_name'], csrfData['hash']);
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', admin_url + 'hr/save_employee_image/' + HR_EMP_ID);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function () {
                var res = {};
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status === 200 && res.success) {
                    var bust = res.image + (res.image.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now();
                    document.getElementById('hr-avatar-img').src = bust;
                    $('.staff-profile-image-small').attr('src', bust);
                    alert_float('success', res.message || 'Photo updated.');
                    $('#hr-photo-modal').modal('hide');
                } else {
                    alert_float('danger', (res && res.message) || 'Upload failed.');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Save Photo';
            };
            xhr.onerror = function () {
                alert_float('danger', 'Upload failed.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Save Photo';
            };
            xhr.send(fd);
        }, 'image/jpeg', 0.92);
    });

    // Tidy up when the modal closes so re-opening starts clean.
    $('#hr-photo-modal').on('hidden.bs.modal', function () {
        destroyCropper();
        $img.style.display = 'none';
        $empty.style.display = 'flex';
        setTools(false);
    });
})();
</script>
<?php } ?>

<?php
// Smart PDF "Print Document" popup — embedded so it opens in-place with the
// employee pre-filled, instead of redirecting to the Smart PDF module.
if (!empty($smart_pdf_groups)) {
    $CI_spdf = &get_instance();
    if ($CI_spdf->app_modules->is_active('smart_pdf')
        && file_exists(module_dir_path('smart_pdf', 'views/_generate_modal.php'))) {
        $CI_spdf->load->view('smart_pdf/_generate_modal');
    }
}
?>
