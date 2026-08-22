<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
    // Quota info for new member creation check
    $qi = isset($quota_info) ? $quota_info : ['is_saas' => false, 'quota' => -1, 'usage' => 0, 'is_unlimited' => true, 'limit_reached' => false, 'upgrade_url' => '#'];
    $is_new_member = !isset($member);
    $show_limit_block = $is_new_member && $qi['limit_reached'];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if ($show_limit_block) { ?>
                <!-- Quota Limit Block — replaces the creation form -->
                <div class="team-header">
                    <h4><?php echo $title; ?></h4>
                    <a href="<?php echo admin_url('team'); ?>" class="btn-cancel">
                        <i class="fa fa-arrow-left"></i> Back to List
                    </a>
                </div>

                <div class="team-limit-block">
                    <div class="team-limit-block__icon">
                        <i class="fa fa-user-lock"></i>
                    </div>
                    <h3 class="team-limit-block__title">Users Limit Reached</h3>
                    <p class="team-limit-block__desc">
                        Your current plan allows a maximum of <strong><?php echo $qi['quota']; ?> users</strong>.
                        You currently have <strong><?php echo $qi['usage']; ?></strong> user<?php echo $qi['usage'] != 1 ? 's' : ''; ?>.
                    </p>
                    <div class="team-limit-block__stats">
                        <div class="team-limit-block__stat">
                            <span class="team-limit-block__stat-value"><?php echo $qi['usage']; ?></span>
                            <span class="team-limit-block__stat-label">Current Users</span>
                        </div>
                        <div class="team-limit-block__stat-divider"></div>
                        <div class="team-limit-block__stat">
                            <span class="team-limit-block__stat-value"><?php echo $qi['quota']; ?></span>
                            <span class="team-limit-block__stat-label">Max Allowed</span>
                        </div>
                    </div>
                    <div class="team-limit-block__actions">
                        <a href="<?php echo $qi['upgrade_url']; ?>" class="team-limit-block__btn-upgrade" target="_blank">
                            <i class="fa fa-arrow-circle-up"></i> Upgrade Plan
                        </a>
                        <a href="<?php echo admin_url('team'); ?>" class="team-limit-block__btn-back">
                            <i class="fa fa-arrow-left"></i> Back to Team
                        </a>
                    </div>
                    <p class="team-limit-block__hint">
                        <i class="fa fa-info-circle"></i>
                        You can increase your users limit from your <a href="<?php echo $qi['upgrade_url']; ?>" target="_blank">Account & Billing</a> page.
                    </p>
                </div>
                <?php } else { ?>
                <!-- Normal member form -->
                <?php echo form_open_multipart($this->uri->uri_string(), ['class' => 'staff-form', 'autocomplete' => 'off']); ?>
                <div class="member">
                    <?php if (isset($member)) { ?>
                        <input type="hidden" name="isedit" value="yes">
                        <input type="hidden" name="memberid" value="<?php echo $member->staffid; ?>">
                    <?php } ?>
                </div>

                <div class="team-header">
                    <h4><?php echo $title; ?></h4>
                    <a href="<?php echo admin_url('team'); ?>" class="btn-cancel">
                        <i class="fa fa-arrow-left"></i> Back to List
                    </a>
                </div>

                <div class="team-form-layout">
                    <!-- Left Column: Personal Info -->
                    <div class="form-section">
                        <h5 class="form-title">Personal Information</h5>

                        <div class="profile-upload-container">
                            <div class="profile-preview">
                                <?php if (isset($member) && $member->profile_image) { ?>
                                    <?php echo staff_profile_image($member->staffid, ['img', 'img-responsive']); ?>
                                <?php } else { ?>
                                    <div
                                        style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.7);">
                                        <i class="fa fa-user fa-3x"></i>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label for="profile_image"
                                    class="profile-image" style="font-weight:600; color:#4f46e5; font-size:13px;"><i class="fa fa-camera" style="margin-right:6px;"></i><?= _l('staff_edit_profile_image'); ?></label>
                                <input type="file" name="profile_image" class="form-control" id="profile_image">
                            </div>
                        </div>

                        <?php $value = (isset($member) ? $member->firstname : ''); ?>
                        <?php $attrs = (isset($member) ? [] : ['autofocus' => true]); ?>
                        <?php echo render_input('firstname', 'staff_add_edit_firstname', $value, 'text', $attrs); ?>

                        <?php $value = (isset($member) ? $member->lastname : ''); ?>
                        <?php echo render_input('lastname', 'staff_add_edit_lastname', $value); ?>

                        <?php $value = (isset($member) ? $member->email : ''); ?>
                        <?php echo render_input('email', 'staff_add_edit_email', $value, 'email', ['autocomplete' => 'off']); ?>

                        <?php $value = (isset($member) ? $member->phonenumber : ''); ?>
                        <?php echo render_input('phonenumber', 'staff_add_edit_phonenumber', $value); ?>

                        <div class="form-group">
                            <label for="password"
                                class="control-label"><?php echo _l('staff_add_edit_password'); ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control password" name="password" autocomplete="off">
                                <span class="input-group-addon">
                                    <a href="#password" class="show_password"
                                        onclick="showPassword('password'); return false;"><i class="fa fa-eye"></i></a>
                                </span>
                                <span class="input-group-addon">
                                    <a href="#" class="generate_password"
                                        onclick="generatePassword(this);return false;"><i class="fa fa-refresh"></i></a>
                                </span>
                            </div>
                            <?php if (isset($member)) { ?>
                                <p style="margin-top:6px; font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px;"><i class="fa fa-info-circle" style="color:#a5b4fc;"></i><?php echo _l('staff_add_edit_password_note'); ?></p>
                            <?php } ?>
                        </div>

                        <!-- Share Credentials Card -->
                        <div class="cred-card" id="credentialsCard">
                            <div class="cred-card__header">
                                <div class="cred-card__icon-wrap">
                                    <i class="fa fa-key"></i>
                                </div>
                                <div>
                                    <div class="cred-card__title">Login Credentials</div>
                                    <div class="cred-card__subtitle">Share access details with this team member</div>
                                </div>
                            </div>
                            <div class="cred-card__body" id="credentialsPreview">
                                <div class="cred-card__empty">
                                    <i class="fa fa-info-circle"></i>
                                    Fill in email &amp; password to preview
                                </div>
                            </div>
                            <div class="cred-card__footer">
                                <button type="button" class="cred-card__btn" id="btnCopyCredentials" onclick="copyCredentials()">
                                    <i class="fa fa-copy"></i>
                                    <span>Copy to Clipboard</span>
                                </button>
                            </div>
                        </div>

                        <?php if (false) { // Administrator option hidden as per request ?>
                            <hr />
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="administrator" id="administrator" <?php echo (isset($member) && ($member->admin == 1)) ? 'checked' : ''; ?>>
                                <label for="administrator"><?php echo _l('staff_add_edit_administrator'); ?></label>
                            </div>
                        <?php } elseif (isset($member) && $member->admin == 1) { ?>
                            <input type="hidden" name="administrator" value="1">
                        <?php } ?>

                        <div class="form-group">
                            <?php if (count($departments) > 0) { ?>
                                <label for="departments"><?= _l('staff_add_edit_departments'); ?></label>
                                <div class="checkbox checkbox-primary pull-right">
                                    <input type="checkbox" id="select_all_departments">
                                    <label for="select_all_departments"><?= _l('select_all'); ?></label>
                                </div>
                                <div class="clearfix"></div>
                            <?php } ?>
                            <div class="row">
                                <?php foreach ($departments as $department) { ?>
                                    <div class="col-md-6">
                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" id="dep_<?= $department['departmentid']; ?>"
                                                name="departments[]" value="<?= $department['departmentid']; ?>"
                                                <?= (isset($member) && isset($staff_departments) && in_array($department['departmentid'], array_column($staff_departments, 'departmentid'))) ? 'checked' : ''; ?>>
                                            <label
                                                for="dep_<?= $department['departmentid']; ?>"><?= $department['name']; ?></label>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Roles & Permissions -->
                    <div class="form-section">
                        <h5 class="form-title">Roles & Permissions</h5>

                        <?php
                        $selected = '';
                        if (isset($member)) {
                            foreach ($roles as $role) {
                                if ($member->role == $role['roleid']) {
                                    $selected = $role['roleid'];
                                    break;
                                }
                            }
                        } elseif (isset($preset_role)) {
                            $selected = $preset_role;
                        }
                        ?>
                        <?php echo render_select('role', $roles, ['roleid', 'name'], 'staff_add_edit_role', $selected); ?>

                        <div class="form-group mtop15">
                            <label for="max_discount" class="control-label"><i class="fa fa-percent" style="margin-right:6px; color:#f59e0b; font-size:11px;"></i>Max Discount Limit (%)</label>
                            <div class="input-group">
                                <input type="number" name="max_discount" id="max_discount" class="form-control"
                                    value="<?php echo (isset($member) && isset($member->max_discount) && $member->max_discount !== null) ? $member->max_discount : ''; ?>"
                                    placeholder="100" min="0" max="100" step="0.01">
                                <span class="input-group-addon">%</span>
                            </div>
                            <p style="margin-top:6px; font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px;">
                                <i class="fa fa-info-circle" style="color:#a5b4fc;"></i>
                                Maximum discount this staff member can apply on visit billing. Leave empty for no limit (100%).
                            </p>
                        </div>

                        <div class="clearfix mtop15"></div>
                        <h5 class="mbot15"><?php echo _l('staff_add_edit_permissions'); ?></h5>

                        <?php
                        // Reuse the team module's own permissions view (not the core admin/staff/permissions
                        // which has a hard-coded exclusion list that strips many permissions)
                        $permissions_data = [
                            'funcData' => ['staff_id' => isset($member) ? $member->staffid : null],
                            'member'   => isset($member) ? $member : null,
                        ];
                        // Also pass the role object so role-level permissions are shown correctly
                        if (isset($member) && $member->role) {
                            $this->load->model('roles_model');
                            $permissions_data['role'] = $this->roles_model->get($member->role);
                        }
                        $this->load->view('team/permissions', $permissions_data);
                        ?>
                    </div>
                </div>

                <div class="team-actions">
                    <?php if (isset($member)) { ?>
                        <?php if (has_permission('team', '', 'create') || has_permission('team', '', 'edit')) { ?>
                            <button type="submit" class="btn-save"><?php echo _l('submit'); ?></button>
                        <?php } ?>
                    <?php } else { ?>
                        <?php if (has_permission('team', '', 'create')) { ?>
                            <button type="submit" class="btn-save"><?php echo _l('submit'); ?></button>
                        <?php } ?>
                    <?php } ?>
                </div>

                <?php echo form_close(); ?>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        // Init roles/permissions logic from core
        $('select[name="role"]').on('change', function () {
            var roleid = $(this).val();
            init_roles_permissions(roleid, true);
        });

        $('input[name="administrator"]').on('change', function () {
            var checked = $(this).prop('checked');
            var isNotStaffMember = $('.is-not-staff'); // This class might not be used here but keeping logic consistent
            if (checked == true) {
                $('.roles').find('input').prop('disabled', true).prop('checked', false);
            } else {
                $('.roles').find('.capability').not('[data-not-applicable="true"]').prop('disabled', false)
            }
        });

        // Initialize permissions
        init_roles_permissions();

        // Validation
        appValidateForm($('.staff-form'), {
            firstname: 'required',
            lastname: 'required',
            email: {
                required: true,
                email: true,
                remote: {
                    url: admin_url + "misc/staff_email_exists",
                    type: 'post',
                    data: {
                        email: function () {
                            return $('input[name="email"]').val();
                        },
                        memberid: function () {
                            return $('input[name="memberid"]').val(); // Need to ensure memberid is in form if edit
                        }
                    }
                }
            },
            password: {
                required: {
                    depends: function (element) {
                        return ($('input[name="isedit"]').length == 0) ? true : false
                    }
                }
            }
        });

        // Select All Departments
        $('#select_all_departments').on('change', function () {
            var checked = $(this).prop('checked');
            $('input[name="departments[]"]').prop('checked', checked);
        });
    });

    function getCredentialData() {
        var name = $.trim($('input[name="firstname"]').val() + ' ' + $('input[name="lastname"]').val());
        var email = $('input[name="email"]').val();
        var pwd = $('input[name="password"]').val();
        var loginUrl = '<?php echo site_url("admin"); ?>';
        return { name: name, email: email, pwd: pwd, loginUrl: loginUrl };
    }

    function buildCredentialText(d) {
        return 'Hi ' + d.name + '! 👋\n'
            + 'Your account is ready. Here are your login details:\n\n'
            + '🔗 ' + d.loginUrl + '\n'
            + '📧 ' + d.email + '\n'
            + '🔑 ' + d.pwd;
    }

    function credRow(icon, label, value, cls) {
        cls = cls || '';
        return '<div class="cred-row">'
            + '<div class="cred-row__icon ' + cls + '"><i class="fa ' + icon + '"></i></div>'
            + '<div class="cred-row__content">'
            + '<div class="cred-row__label">' + label + '</div>'
            + '<div class="cred-row__value">' + (value || '<span class="cred-row__placeholder">—</span>') + '</div>'
            + '</div></div>';
    }

    function refreshCredentialsPreview() {
        var d = getCredentialData();
        var $preview = $('#credentialsPreview');
        var $card = $('#credentialsCard');

        if (!d.email && !d.pwd) {
            $card.removeClass('cred-card--active');
            $preview.html('<div class="cred-card__empty"><i class="fa fa-info-circle"></i> Fill in email & password to preview</div>');
            return;
        }

        $card.addClass('cred-card--active');
        var maskedPwd = d.pwd ? '&bull;'.repeat(d.pwd.length) : '';
        var html = '<div class="cred-card__greeting">Hi <strong>' + (d.name || '...') + '</strong> 👋 — your account is ready!</div>'
            + '<div class="cred-rows">'
            + credRow('fa-link', 'Login URL', d.loginUrl, 'cred-row__icon--link')
            + credRow('fa-envelope', 'Email', d.email, 'cred-row__icon--email')
            + credRow('fa-lock', 'Password', maskedPwd, 'cred-row__icon--pwd')
            + '</div>';
        $preview.html(html);
    }

    // Live preview updates
    $('input[name="firstname"], input[name="lastname"], input[name="email"], input[name="password"]').on('input change', function () {
        refreshCredentialsPreview();
    });

    // Refresh after generate password
    var _origGenPwd = window.generatePassword;
    if (_origGenPwd) {
        window.generatePassword = function (el) {
            _origGenPwd(el);
            setTimeout(refreshCredentialsPreview, 100);
        };
    }

    function copyCredentials() {
        var d = getCredentialData();
        if (!d.email || !d.pwd) {
            alert('Please fill in email and password first.');
            return;
        }
        var text = buildCredentialText(d);
        var $btn = $('#btnCopyCredentials');
        navigator.clipboard.writeText(text).then(function () {
            $btn.addClass('cred-card__btn--success').html('<i class="fa fa-check"></i><span>Copied!</span>');
            setTimeout(function () {
                $btn.removeClass('cred-card__btn--success').html('<i class="fa fa-copy"></i><span>Copy to Clipboard</span>');
            }, 2000);
        }).catch(function () {
            var t = document.createElement('textarea');
            t.value = text; document.body.appendChild(t); t.select();
            document.execCommand('copy'); document.body.removeChild(t);
            $btn.addClass('cred-card__btn--success').html('<i class="fa fa-check"></i><span>Copied!</span>');
            setTimeout(function () {
                $btn.removeClass('cred-card__btn--success').html('<i class="fa fa-copy"></i><span>Copy to Clipboard</span>');
            }, 2000);
        });
    }
</script>