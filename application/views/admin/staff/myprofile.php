<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
/* ── HealthO Profile Page ── */
.hpf-wrap {
    max-width: 1080px;
    margin: 0 auto;
}
.hpf-hero {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.06);
    overflow: hidden;
    margin-bottom: 20px;
}
.hpf-hero-banner {
    height: 96px;
    /* HealthO brand: navy → cyan → green */
    background: linear-gradient(135deg, #122B5C 0%, #00B4D8 55%, #2ECC71 100%);
}
.hpf-hero-body {
    padding: 0 24px 18px;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
}
.hpf-avatar {
    margin-top: -44px;
    flex-shrink: 0;
    display: inline-flex;
    padding: 3px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 2px 8px rgba(16, 24, 40, 0.12);
}
.hpf-avatar img {
    width: 88px !important;
    height: 88px !important;
    border-radius: 50% !important;
    object-fit: cover;
    display: block;
}
.hpf-identity {
    flex: 1 1 260px;
    min-width: 0;
    padding-top: 8px;
}
.hpf-name {
    margin: 0 0 2px;
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
}
.hpf-meta {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
    display: flex;
    flex-wrap: wrap;
    gap: 4px 18px;
    align-items: center;
}
.hpf-meta a { color: #6b7280; }
.hpf-meta a:hover { color: #00B4D8; }
.hpf-meta i { margin-right: 6px; color: #9ca3af; }
.hpf-chips {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.hpf-chip {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    color: #122B5C;
    background: rgba(0, 180, 216, 0.10);
    border: 1px solid rgba(0, 180, 216, 0.25);
}
.hpf-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    padding-top: 8px;
    margin-left: auto;
}
.hpf-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #f3f4f6;
    color: #4b5563 !important;
    border: 1px solid #e5e7eb;
    transition: all 0.15s ease;
}
.hpf-icon-btn:hover {
    background: #122B5C;
    border-color: #122B5C;
    color: #fff !important;
    text-decoration: none;
}
/* ── Tabs ── */
.hpf-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 0 0 16px;
    padding: 4px;
    list-style: none;
    background: #f3f4f6;
    border-radius: 10px;
    width: fit-content;
}
.hpf-tabs > li > a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-decoration: none;
    transition: all 0.15s ease;
}
.hpf-tabs > li > a:hover { color: #111827; }
.hpf-tabs > li.active > a {
    background: #fff;
    color: #122B5C;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.10);
}
/* ── Cards / forms ── */
.hpf-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.06);
    margin-bottom: 20px;
}
.hpf-card-head {
    padding: 14px 24px;
    border-bottom: 1px solid #f0f1f3;
}
.hpf-card-head h4 {
    margin: 0;
    font-size: 14.5px;
    font-weight: 700;
    color: #111827;
}
.hpf-card-head p {
    margin: 2px 0 0;
    font-size: 12.5px;
    color: #9ca3af;
}
.hpf-card-body { padding: 20px 24px 8px; }
.hpf-card-foot {
    padding: 12px 24px;
    border-top: 1px solid #f0f1f3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.hpf-card .form-group label { font-weight: 600; color: #374151; }
.hpf-btn-primary {
    background: linear-gradient(135deg, #122B5C 0%, #1b4a8f 100%);
    border: none;
    color: #fff;
    padding: 8px 22px;
    border-radius: 8px;
    font-weight: 600;
}
.hpf-btn-primary:hover, .hpf-btn-primary:focus {
    background: linear-gradient(135deg, #0d2148 0%, #163d77 100%);
    color: #fff;
}
.hpf-avatar-edit {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
}
.hpf-avatar-edit img {
    width: 56px !important;
    height: 56px !important;
    border-radius: 50% !important;
    object-fit: cover;
}
.hpf-avatar-edit .hpf-remove-img {
    font-size: 12px;
    color: #ef4444;
}
@media (max-width: 640px) {
    .hpf-hero-body { padding: 0 16px 14px; }
    .hpf-actions { margin-left: 0; }
    .hpf-card-body, .hpf-card-head, .hpf-card-foot { padding-left: 16px; padding-right: 16px; }
}
</style>
<div id="wrapper">
    <div class="content">
        <div class="hpf-wrap">
            <?php hooks()->do_action('before_staff_myprofile'); ?>

            <?php if ($staff_p->active == 0) { ?>
            <div class="alert alert-danger text-center">
                <?= _l('staff_profile_inactive_account'); ?>
            </div>
            <?php } ?>

            <!-- Profile hero -->
            <div class="hpf-hero">
                <div class="hpf-hero-banner"></div>
                <div class="hpf-hero-body">
                    <span class="hpf-avatar">
                        <?= staff_profile_image($staff_p->staffid, ['img'], 'thumb'); ?>
                    </span>
                    <div class="hpf-identity">
                        <h3 class="hpf-name">
                            <?= e($staff_p->firstname . ' ' . $staff_p->lastname); ?>
                        </h3>
                        <p class="hpf-meta">
                            <span><i class="fa-regular fa-envelope"></i><a
                                    href="mailto:<?= e($staff_p->email); ?>"><?= e($staff_p->email); ?></a></span>
                            <?php if ($staff_p->phonenumber != '') { ?>
                            <span><i class="fa fa-phone"></i><?= e($staff_p->phonenumber); ?></span>
                            <?php } ?>
                            <?php if ($staff_p->last_activity && $staff_p->staffid != get_staff_user_id()) { ?>
                            <span><i class="fa-regular fa-clock"></i><?= _l('last_active'); ?>:
                                <span class="text-has-action" data-toggle="tooltip"
                                    data-title="<?= e(_dt($staff_p->last_activity)); ?>">
                                    <?= e(time_ago($staff_p->last_activity)); ?>
                                </span>
                            </span>
                            <?php } ?>
                        </p>
                        <?php if (count($staff_departments) > 0) { ?>
                        <div class="hpf-chips">
                            <?php foreach ($departments as $department) {
                                foreach ($staff_departments as $staff_department) {
                                    if ($staff_department['departmentid'] == $department['departmentid']) { ?>
                            <span class="hpf-chip"><?= e($staff_department['name']); ?></span>
                            <?php }
                                }
                            } ?>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="hpf-actions">
                        <?php if (! empty($staff_p->facebook)) { ?>
                        <a href="<?= e($staff_p->facebook); ?>" target="_blank" class="hpf-icon-btn">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <?php } ?>
                        <?php if (! empty($staff_p->linkedin)) { ?>
                        <a href="<?= e($staff_p->linkedin); ?>" target="_blank" class="hpf-icon-btn">
                            <i class="fa-brands fa-linkedin-in"></i>
                        </a>
                        <?php } ?>
                        <?php if (! empty($staff_p->skype)) { ?>
                        <a href="skype:<?= e($staff_p->skype); ?>" data-toggle="tooltip"
                            title="<?= e($staff_p->skype); ?>" target="_blank" class="hpf-icon-btn">
                            <i class="fa-brands fa-skype"></i>
                        </a>
                        <?php } ?>
                        <?php if ($staff_p->staffid != get_staff_user_id() && staff_can('edit', 'staff') && staff_can('view', 'staff')) { ?>
                        <a href="<?= admin_url('staff/member/' . $staff_p->staffid); ?>" class="hpf-icon-btn">
                            <i class="fa fa-pencil-square"></i>
                        </a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <?php if ($staff_p->staffid == get_staff_user_id() || is_admin()) { ?>
            <div class="hpf-card">
                <div class="hpf-card-body" style="padding-bottom: 20px;">
                    <?php $this->load->view('admin/staff/stats'); ?>
                </div>
            </div>
            <?php } ?>

            <?php if (isset($member) && $staff_p->staffid == get_staff_user_id()) { ?>
            <!-- Tabs -->
            <ul class="hpf-tabs" role="tablist">
                <li class="active">
                    <a href="#hpf_tab_details" data-toggle="tab"><i class="fa-regular fa-user"></i>
                        <?= _l('nav_edit_profile'); ?></a>
                </li>
                <li>
                    <a href="#hpf_tab_password" data-toggle="tab"><i class="fa fa-key"></i>
                        <?= _l('staff_edit_profile_change_your_password'); ?></a>
                </li>
                <li>
                    <a href="#hpf_tab_2fa" data-toggle="tab"><i class="fa fa-shield-halved"></i>
                        <?= _l('staff_two_factor_authentication'); ?></a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- ── Profile details ── -->
                <div class="tab-pane active" id="hpf_tab_details">
                    <?= form_open_multipart(admin_url('staff/edit_profile'), ['id' => 'staff_profile_table', 'autocomplete' => 'off']); ?>
                    <div class="hpf-card">
                        <div class="hpf-card-head">
                            <h4><?= _l('nav_edit_profile'); ?></h4>
                            <p><?= e($member->firstname . ' ' . $member->lastname); ?></p>
                        </div>
                        <div class="hpf-card-body">
                            <div class="hpf-avatar-edit">
                                <?= staff_profile_image($current_user->staffid, ['img'], 'thumb'); ?>
                                <div>
                                    <label for="profile_image" class="tw-mb-1"
                                        style="display:block;"><?= _l('staff_edit_profile_image'); ?></label>
                                    <input type="file" name="profile_image" id="profile_image">
                                    <?php if ($current_user->profile_image != null) { ?>
                                    <a href="<?= admin_url('staff/remove_staff_profile_image'); ?>"
                                        class="hpf-remove-img"><i class="fa fa-remove"></i></a>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="firstname"
                                            class="control-label"><?= _l('staff_add_edit_firstname'); ?></label>
                                        <input type="text" class="form-control" name="firstname"
                                            value="<?= e($member->firstname); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lastname"
                                            class="control-label"><?= _l('staff_add_edit_lastname'); ?></label>
                                        <input type="text" class="form-control" name="lastname"
                                            value="<?= e($member->lastname); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email"
                                            class="control-label"><?= _l('staff_add_edit_email'); ?></label>
                                        <input type="email"
                                            <?php if (staff_can('edit', 'staff')) { ?>
                                        name="email"
                                        <?php } else { ?> disabled="true"
                                        <?php } ?> class="form-control"
                                        value="<?= e($member->email); ?>"
                                        id="email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <?= render_input('phonenumber', 'staff_add_edit_phonenumber', $member->phonenumber); ?>
                                </div>
                            </div>
                            <div class="row">
                                <?php if (! is_language_disabled()) { ?>
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label for="default_language"
                                            class="control-label"><?= _l('localization_default_language'); ?></label>
                                        <select name="default_language" data-live-search="true" id="default_language"
                                            class="form-control selectpicker"
                                            data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                            <option value="">
                                                <?= _l('system_default_string'); ?>
                                            </option>
                                            <?php foreach ($this->app->get_available_languages() as $availableLanguage) {
                                                $selected = '';
                                                if ($member->default_language == $availableLanguage) {
                                                    $selected = 'selected';
                                                } ?>
                                            <option value="<?= e($availableLanguage); ?>"
                                                <?= e($selected); ?>>
                                                <?= e(ucfirst($availableLanguage)); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <?php } ?>
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label for="direction"><?= _l('document_direction'); ?></label>
                                        <select class="selectpicker"
                                            data-none-selected-text="<?= _l('system_default_string'); ?>"
                                            data-width="100%" name="direction" id="direction">
                                            <option value="" <?= empty($member->direction) ? ' selected' : '' ?>>
                                            </option>
                                            <option value="ltr" <?= $member->direction == 'ltr' ? ' selected' : '' ?>>
                                                LTR
                                            </option>
                                            <option value="rtl" <?= $member->direction == 'rtl' ? ' selected' : '' ?>>
                                                RTL
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <i class="fa-regular fa-circle-question" data-toggle="tooltip"
                                data-title="<?= _l('staff_email_signature_help'); ?>"></i>
                            <?= render_textarea('email_signature', 'settings_email_signature', $member->email_signature, ['data-entities-encode' => 'true']); ?>
                        </div>
                        <div class="hpf-card-foot">
                            <span></span>
                            <button type="submit" class="btn hpf-btn-primary">
                                <?= _l('submit'); ?>
                            </button>
                        </div>
                    </div>
                    <?= form_close(); ?>
                </div>

                <!-- ── Change password ── -->
                <div class="tab-pane" id="hpf_tab_password">
                    <?= form_open('admin/staff/change_password_profile', ['id' => 'staff_password_change_form']); ?>
                    <div class="hpf-card">
                        <div class="hpf-card-head">
                            <h4><?= _l('staff_edit_profile_change_your_password'); ?></h4>
                        </div>
                        <div class="hpf-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="oldpassword"
                                            class="control-label"><?= _l('staff_edit_profile_change_old_password'); ?></label>
                                        <input type="password" class="form-control" name="oldpassword"
                                            id="oldpassword">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="newpassword"
                                            class="control-label"><?= _l('staff_edit_profile_change_new_password'); ?></label>
                                        <input type="password" class="form-control" id="newpassword"
                                            name="newpassword">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="newpasswordr"
                                            class="control-label"><?= _l('staff_edit_profile_change_repeat_new_password'); ?></label>
                                        <input type="password" class="form-control" id="newpasswordr"
                                            name="newpasswordr">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="hpf-card-foot">
                            <span class="tw-text-sm tw-text-neutral-500">
                                <?php if ($member->last_password_change != null) { ?>
                                <?= _l('staff_add_edit_password_last_changed'); ?>:
                                <span class="text-has-action" data-toggle="tooltip"
                                    data-title="<?= e(_dt($member->last_password_change)); ?>">
                                    <?= e(time_ago($member->last_password_change)); ?>
                                </span>
                                <?php } ?>
                            </span>
                            <button type="submit" class="btn hpf-btn-primary"><?= _l('submit'); ?></button>
                        </div>
                    </div>
                    <?= form_close(); ?>
                </div>

                <!-- ── Two factor authentication ── -->
                <div class="tab-pane" id="hpf_tab_2fa">
                    <?= form_open('admin/staff/update_two_factor', ['id' => 'two_factor_auth_form']); ?>
                    <div class="hpf-card">
                        <div class="hpf-card-head">
                            <h4><?= _l('staff_two_factor_authentication'); ?></h4>
                        </div>
                        <div class="hpf-card-body">
                            <div class="radio radio-primary">
                                <input type="radio" id="two_factor_auth_disabled" name="two_factor_auth" value="off"
                                    class="custom-control-input"
                                    <?= ($current_user->two_factor_auth_enabled == 0) ? 'checked' : '' ?>>
                                <label class="custom-control-label"
                                    for="two_factor_auth_disabled"><?= _l('two_factor_authentication_disabed'); ?></label>
                            </div>
                            <?php if (is_email_template_active('two-factor-authentication')) { ?>
                            <div class="radio radio-primary">
                                <input type="radio" id="two_factor_auth_enabled" name="two_factor_auth" value="email"
                                    class="custom-control-input"
                                    <?= ($current_user->two_factor_auth_enabled == 1) ? 'checked' : '' ?>>
                                <label for="two_factor_auth_enabled">
                                    <i class="fa-regular fa-circle-question" data-placement="right"
                                        data-toggle="tooltip"
                                        data-title="<?= _l('two_factor_authentication_info'); ?>"></i>
                                    <?= _l('enable_two_factor_authentication'); ?>
                                </label>
                            </div>
                            <?php } ?>
                            <div class="radio radio-primary">
                                <input type="radio" id="google_two_factor_auth_enabled" name="two_factor_auth"
                                    value="google" class="custom-control-input"
                                    <?= ($current_user->two_factor_auth_enabled == 2) ? 'checked' : '' ?>>
                                <label class="custom-control-label"
                                    for="google_two_factor_auth_enabled"><?= _l('enable_google_two_factor_authentication'); ?></label>
                            </div>
                            <?php if (! extension_loaded('imagick')) { ?>
                            <div id="imagick_error" class="alert alert-danger mtop15" style="display: none;">
                                <i class="fa fa-exclamation-triangle"></i>
                                <strong>Error:</strong> The PHP imagick extension is required for Google Two-Factor
                                Authentication but is not installed on this server. Please contact your system
                                administrator to install the imagick extension.
                            </div>
                            <?php } ?>
                            <div id="qr_image" class="mtop30 card">
                            </div>
                        </div>
                        <div class="hpf-card-foot">
                            <span></span>
                            <button id="submit_2fa" type="submit" class="btn hpf-btn-primary">
                                <?= _l('submit'); ?>
                            </button>
                        </div>
                    </div>
                    <?= form_close(); ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        // Open a specific tab via URL hash (e.g. /admin/profile#hpf_tab_password)
        if (window.location.hash && $('.hpf-tabs a[href="' + window.location.hash + '"]').length) {
            $('.hpf-tabs a[href="' + window.location.hash + '"]').tab('show');
        }
        $('.hpf-tabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            if (history.replaceState) {
                history.replaceState(null, null, e.target.hash);
            }
        });

        <?php if (isset($member) && $staff_p->staffid == get_staff_user_id()) { ?>
        var qr_loaded = 0;
        var is_g2fa_enabled =
            "<?= $current_user->two_factor_auth_enabled ?>";
        var
            imagick_available = <?= extension_loaded('imagick') ? 'true' : 'false' ?> ;

        $('input[type=radio][name="two_factor_auth"]').change(function() {
            if (this.value == 'google') {
                if (!imagick_available) {
                    $('#imagick_error').show();
                    $('#submit_2fa').prop("disabled", true);
                    $('#qr_image').hide();
                    return;
                }

                $('#imagick_error').hide();

                if (is_g2fa_enabled == 2) {
                    $('#submit_2fa').prop("disabled", false);
                    return;
                }

                if (qr_loaded == 0) {
                    $('#qr_image').load(admin_url + 'authentication/get_qr', {}, function(response,
                        status) {
                        qr_loaded = 1;
                        $('#qr_image').show();
                    });
                } else {
                    $('#qr_image').show();
                }
                $('#submit_2fa').prop("disabled", true);
            } else {
                $('#imagick_error').hide();
                $('#qr_image').hide();
                $('#submit_2fa').prop("disabled", false);
            }
        });

        // Check initial state
        var selectedValue = $('input[name="two_factor_auth"]:checked').val();
        if (selectedValue === 'google' && !imagick_available) {
            $('#imagick_error').show();
        }

        // Prevent form submission if Google 2FA is selected but imagick is not available
        $('#two_factor_auth_form').on('submit', function(e) {
            var selectedValue = $('input[name="two_factor_auth"]:checked').val();
            if (selectedValue === 'google' && !imagick_available) {
                e.preventDefault();
                $('#imagick_error').show();
                return false;
            }
        });

        appValidateForm($('#staff_profile_table'), {
            firstname: 'required',
            lastname: 'required',
            email: 'required'
        });
        appValidateForm($('#staff_password_change_form'), {
            oldpassword: 'required',
            newpassword: 'required',
            newpasswordr: {
                equalTo: "#newpassword"
            }
        });
        appValidateForm($('#two_factor_auth_form'), {
            two_factor_auth: 'required'
        });
        <?php } ?>
    });
</script>
</body>

</html>
