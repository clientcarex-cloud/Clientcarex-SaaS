<?php defined('BASEPATH') or exit('No direct script access allowed');

$day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$week_offs = array_filter(explode(',', get_option('hr_default_week_off')), 'strlen');
$role_map  = json_decode(get_option('hr_role_week_off'), true) ?: [];
?>
<?php init_head(); ?>
<style>
    .hr-settings-tabs .nav-pills > li > a { color:#334155; border-radius:6px; padding:10px 14px; margin-bottom:2px; font-weight:500; }
    .hr-settings-tabs .nav-pills > li > a:hover { background:#f1f5f9; }
    .hr-settings-tabs .nav-pills > li.active > a,
    .hr-settings-tabs .nav-pills > li.active > a:focus,
    .hr-settings-tabs .nav-pills > li.active > a:hover { background:#0ea5e9; color:#fff; }
    .hr-settings-tabs .nav-pills > li > a > i { width:20px; text-align:center; margin-right:8px; }
    .hr-settings-tabs .tab-pane > h4.bold:first-child { margin-top:0; }
    @media (min-width:992px){ .hr-settings-nav { position:sticky; top:15px; } }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 class="bold" style="margin:0;">HR Settings</h4>
                            <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open(admin_url('hr/settings')); ?>
                        <div class="row hr-settings-tabs">
                            <!-- ============================ Vertical tab rail ============================ -->
                            <div class="col-md-3">
                                <ul class="nav nav-pills nav-stacked hr-settings-nav" role="tablist">
                                    <li class="active"><a href="#tab_general" data-toggle="tab"><i class="fa fa-cog"></i> General</a></li>
                                    <li><a href="#tab_attendance" data-toggle="tab"><i class="fa fa-calendar-check-o"></i> Attendance &amp; Week Offs</a></li>
                                    <li><a href="#tab_biometric" data-toggle="tab"><i class="fa fa-fingerprint"></i> Biometric Machine</a></li>
                                    <li><a href="#tab_field" data-toggle="tab"><i class="fa fa-location-dot"></i> Field Attendance</a></li>
                                    <li><a href="#tab_leave" data-toggle="tab"><i class="fa fa-gavel"></i> Leave</a></li>
                                    <li><a href="#tab_selfservice" data-toggle="tab"><i class="fa fa-user-circle-o"></i> Self-Service</a></li>
                                    <li><a href="#tab_integrations" data-toggle="tab"><i class="fa fa-video-camera"></i> Interview Integrations</a></li>
                                    <?php if (!empty($smart_pdf_available)) { ?>
                                        <li><a href="#tab_templates" data-toggle="tab"><i class="fa fa-file-pdf-o"></i> Document Templates</a></li>
                                    <?php } ?>
                                </ul>
                            </div>

                            <!-- ============================ Tab panes ============================ -->
                            <div class="col-md-9">
                                <div class="tab-content">

                                    <!-- ------------------------------------------------- General -->
                                    <div class="tab-pane active" id="tab_general">
                                        <h4 class="bold"><i class="fa fa-cog text-info"></i> General</h4>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Employee Code Prefix</label>
                                                    <input type="text" name="hr_employee_code_prefix" class="form-control" value="<?php echo html_escape(get_option('hr_employee_code_prefix')); ?>">
                                                    <small class="text-muted">Used when auto-creating employee profiles, e.g. EMP-0042.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Leave Year Starts In</label>
                                                    <select name="hr_leave_year_start_month" class="form-control">
                                                        <?php for ($m = 1; $m <= 12; $m++) { ?>
                                                            <option value="<?php echo $m; ?>" <?php echo get_option('hr_leave_year_start_month') == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                    <small class="text-muted">January for calendar-year leave, April for fiscal-year leave.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Payroll Per-Day Basis (for loss of pay)</label>
                                                    <select name="hr_payroll_lop_basis" class="form-control">
                                                        <option value="calendar" <?php echo get_option('hr_payroll_lop_basis') === 'calendar' ? 'selected' : ''; ?>>Calendar days in month (28-31)</option>
                                                        <option value="fixed30" <?php echo get_option('hr_payroll_lop_basis') === 'fixed30' ? 'selected' : ''; ?>>Fixed 30 days</option>
                                                        <option value="working" <?php echo get_option('hr_payroll_lop_basis') === 'working' ? 'selected' : ''; ?>>Working days (minus week offs &amp; holidays)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Salary Payment Day <i class="fa fa-money text-success"></i></label>
                                                    <?php $gspd = get_option('hr_salary_payment_day'); $gspd = ($gspd === '' ? '1' : $gspd); ?>
                                                    <select name="hr_salary_payment_day" class="form-control">
                                                        <option value="0" <?php echo (string) $gspd === '0' ? 'selected' : ''; ?>>Last day of month</option>
                                                        <?php for ($d = 1; $d <= 31; $d++) { ?>
                                                            <option value="<?php echo $d; ?>" <?php echo (string) $gspd === (string) $d ? 'selected' : ''; ?>><?php echo hr_pay_day_label($d); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                    <small class="text-muted">Company-wide pay day. Can be overridden per employee on their Salary tab. Drives the pre-payday dashboard reminder.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Payslip</label>
                                                    <div class="checkbox checkbox-primary" style="margin-top:4px;">
                                                        <input type="checkbox" id="hr_payslip_show_logo" name="hr_payslip_show_logo" value="1" <?php echo get_option('hr_payslip_show_logo') !== '0' ? 'checked' : ''; ?>>
                                                        <label for="hr_payslip_show_logo"><strong>Show company logo on payslips</strong></label>
                                                    </div>
                                                    <small class="text-muted">Prints your company logo at the top of each payslip. Uses the logo from <em>Setup &rarr; Settings &rarr; General</em>.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Document Expiry Alert (days ahead)</label>
                                                    <input type="number" min="1" max="365" name="hr_doc_expiry_alert_days" class="form-control" value="<?php echo html_escape(get_option('hr_doc_expiry_alert_days')); ?>">
                                                    <small class="text-muted">Licenses/registrations expiring within this window appear on the HR dashboard. Also the lead time for the <em>Document Expiring</em> messaging hook.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label><i class="fa fa-birthday-cake text-danger"></i> Birthday Wishes</label>
                                                    <div class="checkbox checkbox-primary" style="margin-top:4px;">
                                                        <input type="checkbox" id="hr_birthday_wishes_enabled" name="hr_birthday_wishes_enabled" value="1" <?php echo get_option('hr_birthday_wishes_enabled') !== '0' ? 'checked' : ''; ?>>
                                                        <label for="hr_birthday_wishes_enabled"><strong>Wish employees on their birthday</strong></label>
                                                    </div>
                                                    <small class="text-muted">Shows a birthday greeting on the HR dashboard and sends every other staff member a notification to wish the celebrant. Fires once per day.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Probation Ending Alert (days ahead)</label>
                                                    <input type="number" min="0" max="365" name="hr_probation_alert_days" class="form-control" value="<?php echo html_escape(get_option('hr_probation_alert_days') === '' ? '7' : get_option('hr_probation_alert_days')); ?>">
                                                    <small class="text-muted">Lead time for the <em>Probation Ending Soon</em> messaging hook — it fires exactly this many days before an employee's probation end date. Set 0 to switch it off.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ------------------------------------------------- Attendance & Week Offs -->
                                    <div class="tab-pane" id="tab_attendance">
                                        <h4 class="bold"><i class="fa fa-calendar-check-o text-info"></i> Attendance &amp; Weekly Offs</h4>
                                        <div class="form-group">
                                            <label>Default Weekly Off Days (all roles without an override)</label><br>
                                            <?php foreach ($day_names as $i => $day) { ?>
                                                <label class="checkbox-inline"><input type="checkbox" name="hr_default_week_off[]" value="<?php echo $i; ?>" <?php echo in_array((string) $i, $week_offs) ? 'checked' : ''; ?>> <?php echo substr($day, 0, 3); ?></label>
                                            <?php } ?>
                                            <br><small class="text-muted">Fallback for roles without a role-wise override below. Used in the monthly register highlight and "working days" payroll basis.</small>
                                        </div>

                                        <hr class="hr-panel-heading" />
                                        <div class="form-group">
                                            <label>Role-wise Weekly Off Days</label>
                                            <p class="text-muted" style="margin:0 0 8px;">Tick <strong>Override</strong> for a role to give it its own weekly offs (e.g. Doctors off on Sunday, Housekeeping off on Wednesday). Roles without an override follow the global default above. Overriding with no days ticked means that role gets no weekly off.</p>
                                            <div class="table-responsive">
                                                <table class="table table-striped" style="max-width:800px;">
                                                    <thead>
                                                        <tr>
                                                            <th>Role</th>
                                                            <th class="text-center">Override</th>
                                                            <?php foreach ($day_names as $day) { ?>
                                                                <th class="text-center"><?php echo substr($day, 0, 3); ?></th>
                                                            <?php } ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($roles as $role) {
                                                            $rid        = $role['roleid'];
                                                            $overridden = array_key_exists((string) $rid, $role_map);
                                                            $role_days  = $overridden ? array_filter(explode(',', $role_map[(string) $rid]), 'strlen') : []; ?>
                                                            <tr>
                                                                <td><?php echo html_escape($role['name']); ?></td>
                                                                <td class="text-center">
                                                                    <input type="checkbox" name="role_override[<?php echo $rid; ?>]" value="1" class="role-wo-override" data-role="<?php echo $rid; ?>" <?php echo $overridden ? 'checked' : ''; ?>>
                                                                </td>
                                                                <?php foreach ($day_names as $i => $day) { ?>
                                                                    <td class="text-center">
                                                                        <input type="checkbox" name="role_week_off[<?php echo $rid; ?>][]" value="<?php echo $i; ?>" class="role-wo-day role-wo-day-<?php echo $rid; ?>"
                                                                            <?php echo in_array((string) $i, $role_days) ? 'checked' : ''; ?> <?php echo $overridden ? '' : 'disabled'; ?>>
                                                                    </td>
                                                                <?php } ?>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ------------------------------------------------- Biometric Machine -->
                                    <div class="tab-pane" id="tab_biometric">
                                        <a name="attendance-machine"></a>
                                        <h4 class="bold"><i class="fa fa-fingerprint text-info"></i> Biometric Attendance Machine <span class="text-muted" style="font-weight:normal;font-size:13px;">(e-timeoffice)</span></h4>
                                        <p class="text-muted" style="margin-bottom:12px;">Connect your <strong>e-timeoffice</strong> cloud account so employee punches are pulled automatically and turned into daily attendance. Employees are matched by their machine code — set a <em>Machine Employee Code</em> on the employee profile, or leave it blank to match on Employee Code.</p>

                                        <div class="panel-body" style="border:1px solid #e5e7eb;border-radius:8px;margin-top:10px;">
                                            <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                <input type="checkbox" id="hr_etime_enabled" name="hr_etime_enabled" value="1" <?php echo get_option('hr_etime_enabled') === '1' ? 'checked' : ''; ?>>
                                                <label for="hr_etime_enabled"><strong>Enable e-timeoffice integration</strong></label>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4"><div class="form-group"><label>Corporate ID</label>
                                                    <input type="text" id="hr_etime_corporate_id" name="hr_etime_corporate_id" class="form-control" value="<?php echo html_escape(get_option('hr_etime_corporate_id')); ?>" autocomplete="off"></div></div>
                                                <div class="col-md-4"><div class="form-group"><label>Username</label>
                                                    <input type="text" id="hr_etime_username" name="hr_etime_username" class="form-control" value="<?php echo html_escape(get_option('hr_etime_username')); ?>" autocomplete="off"></div></div>
                                                <div class="col-md-4"><div class="form-group"><label>Password</label>
                                                    <input type="password" id="hr_etime_password" name="hr_etime_password" class="form-control" autocomplete="new-password" placeholder="<?php echo get_option('hr_etime_password') !== '' ? '•••••• (leave blank to keep)' : ''; ?>"></div></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6"><div class="form-group"><label>API Base URL</label>
                                                    <input type="text" name="hr_etime_base_url" class="form-control" value="<?php echo html_escape(get_option('hr_etime_base_url') ?: 'https://api.etimeoffice.com/api/'); ?>">
                                                    <span class="text-muted" style="font-size:11px;">Default: https://api.etimeoffice.com/api/</span></div></div>
                                                <div class="col-md-6" style="padding-top:26px;">
                                                    <button type="button" class="btn btn-info" id="hr_etime_test"><i class="fa fa-plug"></i> Test Connection</button>
                                                    <span id="hr_etime_test_result" style="margin-left:8px;font-size:13px;"></span>
                                                </div>
                                            </div>

                                            <hr style="margin:12px 0;" />
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_etime_auto_sync" name="hr_etime_auto_sync" value="1" <?php echo get_option('hr_etime_auto_sync') === '1' ? 'checked' : ''; ?>>
                                                        <label for="hr_etime_auto_sync">Auto-sync via cron</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">Pulls recent punches automatically (about hourly).</span>
                                                </div>
                                                <div class="col-md-2"><div class="form-group"><label>Sync window (days)</label>
                                                    <input type="number" min="1" max="31" name="hr_etime_sync_window_days" class="form-control" value="<?php echo (int) (get_option('hr_etime_sync_window_days') ?: 2); ?>"></div></div>
                                                <div class="col-md-3"><div class="form-group"><label>Half-day under (minutes)</label>
                                                    <input type="number" min="0" max="1440" name="hr_etime_halfday_minutes" class="form-control" value="<?php echo (int) get_option('hr_etime_halfday_minutes'); ?>">
                                                    <span class="text-muted" style="font-size:11px;">0 = never auto half-day.</span></div></div>
                                                <div class="col-md-3" style="padding-top:26px;">
                                                    <div class="checkbox checkbox-warning" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_etime_overwrite_manual" name="hr_etime_overwrite_manual" value="1" <?php echo get_option('hr_etime_overwrite_manual') === '1' ? 'checked' : ''; ?>>
                                                        <label for="hr_etime_overwrite_manual">Overwrite manual entries</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">Off = never touch hand-marked days.</span>
                                                </div>
                                            </div>
                                            <p style="margin:6px 0 0;">
                                                <a href="<?php echo admin_url('hr/punches'); ?>" class="btn btn-default btn-sm"><i class="fa fa-list"></i> View Punch Log</a>
                                                <a href="<?php echo admin_url('hr/attendance'); ?>" class="btn btn-default btn-sm"><i class="fa fa-calendar-check-o"></i> Go to Attendance</a>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- --------------------------------------- Field attendance -->
                                    <div class="tab-pane" id="tab_field">
                                        <a name="field-attendance"></a>
                                        <?php $fcfg = hr_field_settings(); ?>
                                        <h4 class="bold"><i class="fa fa-location-dot text-info"></i> Field Attendance <span class="text-muted" style="font-weight:normal;font-size:13px;">(punch in/out from outside the office)</span></h4>
                                        <p class="text-muted" style="margin-bottom:12px;">
                                            Lets employees mark attendance from their own phone or laptop while on field duty, a client
                                            visit or working from home — capturing the device <strong>location</strong> and a
                                            <strong>live camera selfie</strong> as proof. Punches appear under
                                            <a href="<?php echo admin_url('hr/field_attendance'); ?>">HR &rarr; Field Attendance</a>;
                                            employees punch from <em>My HR &rarr; Field Punch</em>.
                                        </p>

                                        <div class="panel-body" style="border:1px solid #e5e7eb;border-radius:8px;margin-top:10px;">
                                            <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                <input type="checkbox" id="hr_field_enabled" name="hr_field_enabled" value="1" <?php echo $fcfg['enabled'] ? 'checked' : ''; ?>>
                                                <label for="hr_field_enabled"><strong>Enable field attendance</strong></label>
                                            </div>
                                            <span class="text-muted" style="font-size:11px;">Off = the Field Punch screen and its menus are hidden for everyone.</span>

                                            <hr style="margin:12px 0;" />
                                            <h5 class="bold" style="margin-top:0;">What the employee must provide</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_field_require_location" name="hr_field_require_location" value="1" <?php echo $fcfg['require_location'] ? 'checked' : ''; ?>>
                                                        <label for="hr_field_require_location">Require GPS location</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">Punch is blocked if the browser denies location.</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_field_require_photo" name="hr_field_require_photo" value="1" <?php echo $fcfg['require_photo'] ? 'checked' : ''; ?>>
                                                        <label for="hr_field_require_photo">Require live photo</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">A selfie taken by the camera at the moment of punching.</span>
                                                </div>
                                                <div class="col-md-4"><div class="form-group"><label>Max GPS accuracy (metres)</label>
                                                    <input type="number" min="0" max="10000" name="hr_field_max_accuracy_m" class="form-control" value="<?php echo (int) $fcfg['max_accuracy_m']; ?>">
                                                    <span class="text-muted" style="font-size:11px;">0 = accept any accuracy. e.g. 100 rejects vague fixes.</span></div></div>
                                            </div>
                                            <p class="text-muted" style="font-size:11px;margin:4px 0 0;">
                                                <i class="fa fa-lock"></i> Photos can only be captured live on the punch screen — gallery
                                                or file uploads are not accepted, so an old or borrowed picture cannot be submitted.
                                                The camera needs the site to be opened over <strong>https</strong>.
                                            </p>

                                            <hr style="margin:12px 0;" />
                                            <h5 class="bold" style="margin-top:0;">Geofence</h5>
                                            <div class="row">
                                                <div class="col-md-4"><div class="form-group"><label>Outside every saved location</label>
                                                    <select name="hr_field_geofence_mode" class="form-control">
                                                        <option value="off" <?php echo $fcfg['geofence_mode'] === 'off' ? 'selected' : ''; ?>>Allow — do not check (field staff move around)</option>
                                                        <option value="warn" <?php echo $fcfg['geofence_mode'] === 'warn' ? 'selected' : ''; ?>>Allow but flag as out-of-range</option>
                                                        <option value="block" <?php echo $fcfg['geofence_mode'] === 'block' ? 'selected' : ''; ?>>Block the punch</option>
                                                    </select>
                                                    <span class="text-muted" style="font-size:11px;">Every punch always records its distance to the nearest location.</span></div></div>
                                                <div class="col-md-8" style="padding-top:26px;">
                                                    <a href="<?php echo admin_url('hr/field_sites'); ?>" class="btn btn-default"><i class="fa fa-map-pin"></i> Manage work locations</a>
                                                    <span class="text-muted" style="font-size:11px;margin-left:6px;">With no location saved, geofencing simply does not apply.</span>
                                                </div>
                                            </div>

                                            <hr style="margin:12px 0;" />
                                            <h5 class="bold" style="margin-top:0;">Approval &amp; attendance</h5>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_field_auto_approve" name="hr_field_auto_approve" value="1" <?php echo $fcfg['auto_approve'] ? 'checked' : ''; ?>>
                                                        <label for="hr_field_auto_approve">Auto-approve punches</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">Off = each punch waits for HR review.</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_field_update_attendance" name="hr_field_update_attendance" value="1" <?php echo $fcfg['update_attendance'] ? 'checked' : ''; ?>>
                                                        <label for="hr_field_update_attendance">Update daily attendance</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">First punch = check-in, last = check-out.</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="checkbox checkbox-warning" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_field_overwrite_manual" name="hr_field_overwrite_manual" value="1" <?php echo $fcfg['overwrite_manual'] ? 'checked' : ''; ?>>
                                                        <label for="hr_field_overwrite_manual">Overwrite manual/machine days</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">Off = never touch a hand-marked or biometric day.</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_field_notify_reviewers" name="hr_field_notify_reviewers" value="1" <?php echo $fcfg['notify_reviewers'] ? 'checked' : ''; ?>>
                                                        <label for="hr_field_notify_reviewers">Notify reviewers</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">Only for punches awaiting approval.</span>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-3"><div class="form-group"><label>Half-day under (minutes)</label>
                                                    <input type="number" min="0" max="1440" name="hr_field_halfday_minutes" class="form-control" value="<?php echo (int) $fcfg['halfday_minutes']; ?>">
                                                    <span class="text-muted" style="font-size:11px;">0 = never auto half-day.</span></div></div>
                                                <div class="col-md-3"><div class="form-group"><label>Minimum gap between punches (minutes)</label>
                                                    <input type="number" min="0" max="120" name="hr_field_min_gap_minutes" class="form-control" value="<?php echo (int) $fcfg['min_gap_minutes']; ?>">
                                                    <span class="text-muted" style="font-size:11px;">Stops accidental double taps.</span></div></div>
                                                <div class="col-md-6" style="padding-top:26px;">
                                                    <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                        <input type="checkbox" id="hr_field_reverse_geocode" name="hr_field_reverse_geocode" value="1" <?php echo $fcfg['reverse_geocode'] ? 'checked' : ''; ?>>
                                                        <label for="hr_field_reverse_geocode">Look up a street address for each punch</label>
                                                    </div>
                                                    <span class="text-muted" style="font-size:11px;">The employee's browser asks OpenStreetMap to name the coordinates. Leave off to keep coordinates private to your server.</span>
                                                </div>
                                            </div>
                                            <p style="margin:6px 0 0;">
                                                <a href="<?php echo admin_url('hr/field_attendance'); ?>" class="btn btn-default btn-sm"><i class="fa fa-list"></i> Field Punch Log</a>
                                                <a href="<?php echo admin_url('hr/myhr/field_punch'); ?>" class="btn btn-default btn-sm"><i class="fa fa-location-crosshairs"></i> Try the punch screen</a>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- ------------------------------------------------- Leave -->
                                    <div class="tab-pane" id="tab_leave">
                                        <h4 class="bold"><i class="fa fa-gavel text-info"></i> Leave Application Rules</h4>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Minimum advance notice (days)</label>
                                                    <input type="number" min="0" max="60" name="hr_leave_min_notice_days" class="form-control" value="<?php echo (int) hr_leave_min_notice_days(); ?>">
                                                    <small class="text-muted"><strong>1</strong> = employees cannot apply same-day (must apply from tomorrow). <strong>0</strong> = same-day allowed. Emergency leave types are always exempt.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="form-group" style="padding-top:26px;">
                                                    <div class="checkbox checkbox-primary">
                                                        <input type="checkbox" id="hr_leave_allow_backdated" name="hr_leave_allow_backdated" value="1" <?php echo get_option('hr_leave_allow_backdated') === '1' ? 'checked' : ''; ?>>
                                                        <label for="hr_leave_allow_backdated">Allow employees to apply for past dates</label>
                                                    </div>
                                                    <small class="text-muted">Off (recommended): the start date cannot be earlier than today. Mark a leave type as <strong>Emergency (same-day)</strong> under <em>Leave Types</em> to let employees apply it on the same day — HR can also add such leave for staff at any time.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="hr-panel-heading" />
                                        <h4 class="bold"><i class="fa fa-check-square-o text-info"></i> Leave Approval Workflow</h4>
                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" id="hr_leave_multilevel_enabled" name="hr_leave_multilevel_enabled" value="1" <?php echo get_option('hr_leave_multilevel_enabled') === '1' ? 'checked' : ''; ?>>
                                            <label for="hr_leave_multilevel_enabled"><strong>Enable multi-level leave approval</strong> (TL &rarr; Manager &rarr; &hellip;)</label>
                                        </div>
                                        <p class="text-muted" style="margin:0 0 10px;">Add approval levels in order. Each level is approved by a <strong>specific user</strong> (recommended when a role has many people), <strong>anyone in a role</strong>, or <strong>Anyone</strong> (any leave approver). Once a level approves, the request moves to the next; the final level confirms the leave. Admins can approve or force-approve any level. When disabled, a single approval confirms leave (classic behaviour).</p>
                                        <?php
                                        $chain = hr_leave_chain();
                                        if (empty($chain)) {
                                            // show any saved-but-disabled chain so it isn't lost visually
                                            $raw = json_decode(get_option('hr_leave_approval_chain'), true);
                                            if (is_array($raw)) {
                                                foreach ($raw as $lvl) {
                                                    $t = $lvl['type'] ?? (isset($lvl['role']) && (int) $lvl['role'] > 0 ? 'role' : 'any');
                                                    $chain[] = ['type' => $t, 'user' => (int) ($lvl['user'] ?? 0), 'role' => (int) ($lvl['role'] ?? 0), 'label' => (string) ($lvl['label'] ?? '')];
                                                }
                                            }
                                        }
                                        ?>
                                        <div id="approval_chain_wrap" style="max-width:820px;">
                                            <table class="table" style="margin-bottom:8px;">
                                                <thead><tr><th style="width:55px;">Level</th><th style="width:150px;">Approver</th><th>Who</th><th>Label (optional)</th><th style="width:45px;"></th></tr></thead>
                                                <tbody id="approval_chain_body">
                                                    <?php
                                                    $render_row = function ($idx, $lvl) use ($roles, $staff_members) {
                                                        $type = $lvl['type'] ?? 'any';
                                                        $uid  = (string) ($lvl['user'] ?? 0);
                                                        $rid  = (string) ($lvl['role'] ?? 0); ?>
                                                        <tr class="chain-row">
                                                            <td><span class="chain-level-no"><?php echo $idx + 1; ?></span></td>
                                                            <td>
                                                                <select name="chain_type[]" class="form-control chain-type">
                                                                    <option value="user" <?php echo $type === 'user' ? 'selected' : ''; ?>>Specific user</option>
                                                                    <option value="role" <?php echo $type === 'role' ? 'selected' : ''; ?>>Anyone in role</option>
                                                                    <option value="any" <?php echo $type === 'any' ? 'selected' : ''; ?>>Anyone (any approver)</option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select name="chain_user[]" class="form-control chain-user" style="<?php echo $type === 'user' ? '' : 'display:none;'; ?>">
                                                                    <option value="0">— select user —</option>
                                                                    <?php foreach ($staff_members as $s) { ?>
                                                                        <option value="<?php echo (int) $s['staffid']; ?>" <?php echo $uid === (string) $s['staffid'] ? 'selected' : ''; ?>><?php echo html_escape(trim($s['firstname'] . ' ' . $s['lastname'])); ?></option>
                                                                    <?php } ?>
                                                                </select>
                                                                <select name="chain_role[]" class="form-control chain-role" style="<?php echo $type === 'role' ? '' : 'display:none;'; ?>">
                                                                    <?php foreach ($roles as $r) { ?>
                                                                        <option value="<?php echo (int) $r['roleid']; ?>" <?php echo $rid === (string) $r['roleid'] ? 'selected' : ''; ?>><?php echo html_escape($r['name']); ?></option>
                                                                    <?php } ?>
                                                                </select>
                                                                <span class="chain-any text-muted" style="<?php echo $type === 'any' ? '' : 'display:none;'; ?>">Any leave approver</span>
                                                            </td>
                                                            <td><input type="text" name="chain_label[]" class="form-control" value="<?php echo html_escape($lvl['label'] ?? ''); ?>" placeholder="e.g. Team Lead"></td>
                                                            <td><button type="button" class="btn btn-danger btn-icon chain-remove"><i class="fa fa-remove"></i></button></td>
                                                        </tr>
                                                    <?php };
                                                    if (empty($chain)) {
                                                        $render_row(0, ['type' => 'user', 'user' => 0, 'role' => 0, 'label' => '']);
                                                    } else {
                                                        foreach ($chain as $i => $lvl) {
                                                            $render_row($i, $lvl);
                                                        }
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                            <button type="button" class="btn btn-default btn-sm" id="chain_add"><i class="fa fa-plus"></i> Add Level</button>
                                        </div>
                                    </div>

                                    <!-- ------------------------------------------------- Self-Service -->
                                    <div class="tab-pane" id="tab_selfservice">
                                        <h4 class="bold" style="margin-top:0;"><i class="fa fa-user-circle-o text-info"></i> Employee Self-Service</h4>
                                        <p class="text-muted">
                                            Employees with the <strong>My Portal (Self-Service)</strong> permission can view their own
                                            attendance, leave, payslips and profile under <strong>My HR</strong>. The settings below are the
                                            <strong>global defaults</strong>. You can override them per role, and per employee (on the
                                            employee's profile page). Precedence: <em>employee &gt; role &gt; global</em>.
                                            Salary, department, joining date and statutory IDs can never be self-edited.
                                        </p>
                                        <?php $enabled_self = array_flip(hr_self_editable_fields()); ?>
                                        <label class="bold" style="display:block;margin-bottom:6px;">Self-editable profile fields (global)</label>
                                        <div class="row">
                                            <?php foreach (hr_self_field_options() as $slug => $label) { ?>
                                                <div class="col-md-4 col-sm-6">
                                                    <div class="checkbox checkbox-primary">
                                                        <input type="checkbox" id="self_<?php echo $slug; ?>" name="hr_self_editable_fields[]" value="<?php echo $slug; ?>" <?php echo isset($enabled_self[$slug]) ? 'checked' : ''; ?>>
                                                        <label for="self_<?php echo $slug; ?>"><?php echo html_escape($label); ?></label>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>

                                        <div class="checkbox checkbox-primary" style="margin-top:8px;">
                                            <input type="checkbox" id="hr_required_documents_enabled" name="hr_required_documents_enabled" value="1" <?php echo get_option('hr_required_documents_enabled') !== '0' ? 'checked' : ''; ?>>
                                            <label for="hr_required_documents_enabled"><strong>Enable required-documents checklist</strong> (globally)</label>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <label class="bold">Required documents checklist (global)</label>
                                                    <textarea name="hr_required_documents" class="form-control" rows="8"><?php echo html_escape(get_option('hr_required_documents')); ?></textarea>
                                                    <small class="text-muted">One document per line. Shown on <strong>My HR ▸ My Profile</strong> for upload &amp; HR verification.
                                                        Start a line with <code>*</code> to make that document <span style="color:#dc2626;font-weight:700;">mandatory</span> (shown in red / compulsory in ESS).
                                                        Un-tick the box above to hide the checklist without losing this list.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="hr-panel-heading" />
                                        <h4 class="bold"><i class="fa fa-users text-info"></i> Role-wise overrides</h4>
                                        <p class="text-muted">Tick a role to give it its own self-service settings. Un-ticked roles use the global defaults above.</p>
                                        <?php $ess_role_cfg = hr_ess_role_config();
                                        foreach ($roles as $r) {
                                            $rid = (int) $r['roleid'];
                                            $rcfg = $ess_role_cfg[(string) $rid] ?? null;
                                            $ron  = !empty($rcfg['override']); ?>
                                            <div class="panel panel-default" style="margin-bottom:10px;">
                                                <div class="panel-heading" style="background:#f8fafc;">
                                                    <div class="checkbox checkbox-primary" style="margin:0;">
                                                        <input type="checkbox" id="ess_role_override_<?php echo $rid; ?>" name="ess_role_override[<?php echo $rid; ?>]" value="1" <?php echo $ron ? 'checked' : ''; ?> onchange="document.getElementById('ess_role_body_<?php echo $rid; ?>').style.display = this.checked ? 'block' : 'none';">
                                                        <label for="ess_role_override_<?php echo $rid; ?>"><strong><?php echo html_escape($r['name']); ?></strong> — use custom self-service settings</label>
                                                    </div>
                                                </div>
                                                <div class="panel-body" id="ess_role_body_<?php echo $rid; ?>" style="<?php echo $ron ? '' : 'display:none;'; ?>">
                                                    <?php echo get_instance()->load->view('hr/_ess_config_block', ['prefix' => 'ess_role', 'id' => $rid, 'cfg' => $rcfg], true); ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>

                                    <!-- ------------------------------------------------- Interview Integrations -->
                                    <div class="tab-pane" id="tab_integrations">
                                        <a name="interview-integrations"></a>
                                        <h4 class="bold"><i class="fa fa-video-camera text-info"></i> Interview Integrations</h4>
                                        <p class="text-muted" style="margin-bottom:12px;">Connect Zoom and/or Google Meet so interview meeting links are generated automatically when you schedule an interview. Leave a provider disabled to paste meeting links manually.</p>

                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" id="hr_interview_send_invites" name="hr_interview_send_invites" value="1" <?php echo get_option('hr_interview_send_invites') !== '0' ? 'checked' : ''; ?>>
                                            <label for="hr_interview_send_invites">Email the meeting invite to the candidate &amp; interviewers when scheduling</label>
                                        </div>

                                        <div class="panel-body" style="border:1px solid #e5e7eb;border-radius:8px;margin-top:10px;">
                                            <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                <input type="checkbox" id="hr_zoom_enabled" name="hr_zoom_enabled" value="1" <?php echo get_option('hr_zoom_enabled') === '1' ? 'checked' : ''; ?>>
                                                <label for="hr_zoom_enabled"><strong>Zoom</strong> (Server-to-Server OAuth)</label>
                                            </div>
                                            <p class="text-muted" style="font-size:12px;">Create a <em>Server-to-Server OAuth</em> app at marketplace.zoom.us, add the <code>meeting:write:admin</code> scope, and paste its credentials below.</p>
                                            <div class="row">
                                                <div class="col-md-4"><div class="form-group"><label>Account ID</label>
                                                    <input type="text" name="hr_zoom_account_id" class="form-control" value="<?php echo html_escape(get_option('hr_zoom_account_id')); ?>"></div></div>
                                                <div class="col-md-4"><div class="form-group"><label>Client ID</label>
                                                    <input type="text" name="hr_zoom_client_id" class="form-control" value="<?php echo html_escape(get_option('hr_zoom_client_id')); ?>"></div></div>
                                                <div class="col-md-4"><div class="form-group"><label>Client Secret</label>
                                                    <input type="password" name="hr_zoom_client_secret" class="form-control" autocomplete="new-password" placeholder="<?php echo get_option('hr_zoom_client_secret') !== '' ? '•••••• (leave blank to keep)' : ''; ?>"></div></div>
                                                <div class="col-md-6"><div class="form-group"><label>Host email (Zoom user that hosts the meetings)</label>
                                                    <input type="email" name="hr_zoom_host_email" class="form-control" value="<?php echo html_escape(get_option('hr_zoom_host_email')); ?>" placeholder="hr@yourhospital.com"></div></div>
                                            </div>
                                        </div>

                                        <div class="panel-body" style="border:1px solid #e5e7eb;border-radius:8px;margin-top:10px;">
                                            <div class="checkbox checkbox-primary" style="margin-top:0;">
                                                <input type="checkbox" id="hr_google_meet_enabled" name="hr_google_meet_enabled" value="1" <?php echo get_option('hr_google_meet_enabled') === '1' ? 'checked' : ''; ?>>
                                                <label for="hr_google_meet_enabled"><strong>Google Meet</strong> (Google Workspace service account)</label>
                                            </div>
                                            <p class="text-muted" style="font-size:12px;">Create a service account in Google Cloud, enable the Calendar API, grant it <em>domain-wide delegation</em> for scope <code>https://www.googleapis.com/auth/calendar.events</code>, then paste its JSON key and the Workspace user to impersonate (whose calendar hosts the events).</p>
                                            <div class="row">
                                                <div class="col-md-6"><div class="form-group"><label>Impersonation email (Workspace user)</label>
                                                    <input type="email" name="hr_google_impersonate_email" class="form-control" value="<?php echo html_escape(get_option('hr_google_impersonate_email')); ?>" placeholder="hr@yourhospital.com"></div></div>
                                            </div>
                                            <div class="form-group"><label>Service account JSON key <?php echo get_option('hr_google_sa_json') !== '' ? '<span class="text-success">(saved — paste again to replace)</span>' : ''; ?></label>
                                                <textarea name="hr_google_sa_json" class="form-control" rows="4" placeholder='{ "type": "service_account", ... }'></textarea></div>
                                        </div>
                                    </div>

                                    <?php if (!empty($smart_pdf_available)) { ?>
                                        <!-- ------------------------------------------------- Document Templates -->
                                        <div class="tab-pane" id="tab_templates">
                                            <h4 class="bold" style="margin-top:0;"><i class="fa fa-file-pdf-o text-info"></i> Document Templates</h4>
                                            <hr class="hr-panel-heading" />
                                            <p class="text-muted" style="margin-bottom:12px;">
                                                Install the ready-made premium HR document templates — appointment &amp; offer letters,
                                                relieving &amp; final settlement letters, ID cards, experience/internship certificates,
                                                agreements, policies, Form 16 and more — into Smart PDF. You can then print them per
                                                employee from the <strong>Print Document</strong> button on any employee profile.
                                                Templates that already exist are skipped.
                                            </p>
                                            <a href="<?php echo admin_url('smart_pdf/install_hr_templates'); ?>" class="btn btn-info"
                                                onclick="return confirm('<?php echo _l('smart_pdf_install_hr_confirm'); ?>');">
                                                <i class="fa fa-download"></i> <?php echo _l('smart_pdf_install_hr'); ?>
                                            </a>
                                            <a href="<?php echo admin_url('smart_pdf/install_hr_templates?overwrite=1'); ?>" class="btn btn-warning"
                                                onclick="return confirm('<?php echo _l('smart_pdf_reinstall_hr_confirm'); ?>');">
                                                <i class="fa fa-refresh"></i> <?php echo _l('smart_pdf_reinstall_hr'); ?>
                                            </a>
                                            <a href="<?php echo admin_url('smart_pdf'); ?>" class="btn btn-default">
                                                <i class="fa fa-cog"></i> Manage Templates (<?php echo (int) $smart_pdf_tpl_count; ?>)
                                            </a>
                                            <p class="text-muted" style="margin-top:10px;font-size:12px;">
                                                <i class="fa fa-info-circle"></i> Use <strong><?php echo _l('smart_pdf_reinstall_hr'); ?></strong>
                                                after a design change (e.g. a new logo) to refresh templates you already installed.
                                            </p>
                                        </div>
                                    <?php } ?>

                                </div><!-- /.tab-content -->

                                <hr class="hr-panel-heading" />
                                <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> <?php echo _l('submit'); ?></button>
                                <span class="text-muted" style="margin-left:8px;font-size:12px;">Saves every tab.</span>
                            </div><!-- /.col-md-9 -->
                        </div><!-- /.hr-settings-tabs -->
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        $('.role-wo-override').on('change', function () {
            var days = $('.role-wo-day-' + $(this).data('role'));
            days.prop('disabled', !this.checked);
            if (!this.checked) { days.prop('checked', false); }
        });

        // ---- Leave approval chain builder ----
        function renumberChain() {
            $('#approval_chain_body .chain-row').each(function (i) {
                $(this).find('.chain-level-no').text(i + 1);
            });
        }
        function syncChainRow($row) {
            var t = $row.find('.chain-type').val();
            $row.find('.chain-user').toggle(t === 'user');
            $row.find('.chain-role').toggle(t === 'role');
            $row.find('.chain-any').toggle(t === 'any');
        }
        $('#approval_chain_body').on('change', '.chain-type', function () {
            syncChainRow($(this).closest('.chain-row'));
        });
        $('#chain_add').on('click', function () {
            var $last = $('#approval_chain_body .chain-row').last();
            if (!$last.length) { return; }
            var $row = $last.clone();
            $row.find('input[name="chain_label[]"]').val('');
            $row.find('.chain-type').val('user');
            $row.find('.chain-user').val('0');
            syncChainRow($row);
            $('#approval_chain_body').append($row);
            renumberChain();
        });
        $('#approval_chain_body').on('click', '.chain-remove', function () {
            var $row = $(this).closest('.chain-row');
            if ($('#approval_chain_body .chain-row').length <= 1) {
                $row.find('input[name="chain_label[]"]').val('');
                $row.find('.chain-type').val('user');
                $row.find('.chain-user').val('0');
                syncChainRow($row);
                return;
            }
            $row.remove();
            renumberChain();
        });

        // ---- Vertical tabs: deep-link + bootstrap-select fix ----
        // Keep legacy in-page anchors (#attendance-machine, #interview-integrations)
        // working by mapping them to the tab that now contains them.
        var anchorToTab = {
            'attendance-machine': 'tab_biometric',
            'interview-integrations': 'tab_integrations',
            'field-attendance': 'tab_field'
        };
        var hash = (window.location.hash || '').replace('#', '');
        if (hash) {
            var target = anchorToTab[hash] || ($('#' + hash).hasClass('tab-pane') ? hash : null);
            if (target) { $('.hr-settings-tabs a[href="#' + target + '"]').tab('show'); }
        }
        // selectpickers inside a hidden pane render at 0 width until their tab is
        // shown the first time — refresh them on reveal.
        $('.hr-settings-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $($(e.target).attr('href')).find('.selectpicker').selectpicker('refresh');
        });

        // ---- e-timeoffice: Test Connection ----
        $('#hr_etime_test').on('click', function () {
            var btn = $(this);
            var out = $('#hr_etime_test_result').html('<i class="fa fa-spinner fa-spin"></i> Testing…').css('color', '#64748b');
            btn.prop('disabled', true);
            $.post('<?php echo admin_url('hr/etime_test'); ?>', {
                base_url: $('input[name="hr_etime_base_url"]').val(),
                corporate_id: $('#hr_etime_corporate_id').val(),
                username: $('#hr_etime_username').val(),
                password: $('#hr_etime_password').val()
            }).done(function (r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                out.html((r.success ? '<i class="fa fa-check-circle"></i> ' : '<i class="fa fa-times-circle"></i> ') + $('<div>').text(r.message).html())
                    .css('color', r.success ? '#16a34a' : '#dc2626');
            }).fail(function () {
                out.html('<i class="fa fa-times-circle"></i> Request failed.').css('color', '#dc2626');
            }).always(function () {
                btn.prop('disabled', false);
            });
        });
    });
</script>
