<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <?php
                    // Quota variables for easy access
                    $qi = isset($quota_info) ? $quota_info : ['is_saas' => false, 'quota' => -1, 'usage' => 0, 'is_unlimited' => true, 'limit_reached' => false, 'upgrade_url' => '#'];
                    $show_quota = $qi['is_saas'] && !$qi['is_unlimited'];
                    $usage_percent = ($show_quota && $qi['quota'] > 0) ? min(round(($qi['usage'] / $qi['quota']) * 100), 100) : 0;
                    $quota_color = $usage_percent < 50 ? '#10b981' : ($usage_percent > 90 ? '#ef4444' : '#f59e0b');
                ?>
                <div class="team-header <?php echo ($show_quota && $qi['limit_reached']) ? 'team-header--limit' : ''; ?>">
                    <div class="team-header__top">
                        <h4>Team Members</h4>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (has_permission('team', '', 'create')) { ?>
                                <?php if ($qi['limit_reached']) { ?>
                                    <span class="add-member-btn add-member-btn--disabled" title="Users limit reached — upgrade your plan to add more members">
                                        <i class="fa fa-lock"></i> Add Member
                                    </span>
                                <?php } else { ?>
                                    <a href="<?php echo admin_url('team/member'); ?>" class="add-member-btn">
                                        <i class="fa fa-plus"></i> Add Member
                                    </a>
                                <?php } ?>
                            <?php } ?>
                            <?php if (has_permission('team_roles', '', 'view')) { ?>
                                <a href="<?php echo admin_url('team/roles'); ?>" class="add-member-btn"
                                    style="background:rgba(255,255,255,.18); color:#fff; border:1px solid rgba(255,255,255,.25); backdrop-filter:blur(10px);">
                                    Manage Roles
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                    <?php if ($show_quota) { ?>
                    <div class="team-header__quota">
                        <div class="team-header__quota-info">
                            <div class="team-header__quota-icon" style="background: <?php echo $quota_color; ?>20; color: <?php echo $quota_color; ?>;">
                                <i class="fa fa-users"></i>
                            </div>
                            <div class="team-header__quota-text">
                                <span class="team-header__quota-label">Users</span>
                                <span class="team-header__quota-count">
                                    <strong><?php echo $qi['usage']; ?></strong> / <?php echo $qi['quota']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="team-header__quota-progress-wrap">
                            <div class="team-header__quota-progress">
                                <div class="team-header__quota-progress-fill" style="width: <?php echo $usage_percent; ?>%; background: <?php echo $quota_color; ?>;"></div>
                            </div>
                            <span class="team-header__quota-percent" style="color: <?php echo $quota_color; ?>;"><?php echo $usage_percent; ?>%</span>
                        </div>
                        <a href="<?php echo $qi['upgrade_url']; ?>" class="team-header__quota-upgrade" target="_blank">
                            <i class="fa fa-arrow-circle-up"></i> Upgrade Plan
                        </a>
                    </div>
                    <?php if ($qi['limit_reached']) { ?>
                    <div class="team-header__warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <span>Your current plan allows <strong><?php echo $qi['quota']; ?> user<?php echo $qi['quota'] != 1 ? 's' : ''; ?></strong>. To add more team members, upgrade your plan or purchase additional user slots.</span>
                    </div>
                    <?php } ?>
                    <?php } ?>
                </div>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php
                        $table_data = [
                            _l('staff_dt_name'),
                            _l('staff_dt_email'),
                            _l('role'),
                            _l('staff_dt_last_Login'),
                            _l('staff_dt_active'),
                        ];
                        $custom_fields = get_custom_fields('staff', ['show_on_table' => 1]);
                        foreach ($custom_fields as $field) {
                            array_push($table_data, [
                                'name' => $field['name'],
                                'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1],
                            ]);
                        }
                        render_datatable($table_data, 'team');
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php if (is_admin()) { ?>
    <a href="<?php echo admin_url('team/visibility_settings'); ?>" class="btn btn-info btn-icon"
        style="position: fixed; bottom: 30px; right: 30px; z-index: 9999; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <i class="fa fa-cog fa-lg"></i>
    </a>
<?php } ?>
<?php init_tail(); ?>

<?php if (is_admin()) { ?>
<!-- Login as Staff Modal -->
<div class="modal fade" id="loginAsStaffModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content" style="border:none; border-radius:20px; overflow:hidden; box-shadow:0 25px 60px rgba(99,102,241,.2);">
            <div class="las-modal-header">
                <div class="las-modal-icon">
                    <i class="fa fa-user-secret"></i>
                </div>
                <div>
                    <h4 class="las-modal-title">Login as Staff</h4>
                    <p class="las-modal-subtitle">Open in an <strong>incognito window</strong> for a separate session</p>
                </div>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:.8; text-shadow:none; font-size:24px; position:absolute; top:16px; right:20px;">&times;</button>
            </div>
            <div class="modal-body" style="padding:28px;">
                <div id="lasLoading" style="text-align:center; padding:30px 0;">
                    <i class="fa fa-spinner fa-spin fa-2x" style="color:#6366f1;"></i>
                    <p style="margin-top:12px; color:#6b7280; font-weight:500;">Generating secure login link...</p>
                </div>
                <div id="lasResult" style="display:none;">
                    <div class="las-staff-badge" id="lasStaffName"></div>
                    <div class="las-url-box">
                        <label style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#9ca3af; font-weight:700; margin-bottom:8px; display:block;">One-Time Login URL</label>
                        <div style="display:flex; gap:8px; align-items:stretch;">
                            <input type="text" id="lasUrl" readonly class="form-control" style="font-family:monospace; font-size:12px; background:#f8f9ff; border:1.5px solid #e5e7eb; border-radius:10px; flex:1; padding:10px 14px;">
                            <button type="button" onclick="copyLasUrl()" class="las-copy-btn" id="lasCopyBtn" title="Copy URL">
                                <i class="fa fa-copy"></i>
                            </button>
                        </div>
                        <p style="margin-top:10px; font-size:12px; color:#f59e0b; display:flex; align-items:center; gap:6px;">
                            <i class="fa fa-clock-o"></i> This link expires in <strong>5 minutes</strong> and can only be used once.
                        </p>
                    </div>

                    <!-- Incognito Steps -->
                    <div class="las-steps">
                        <div class="las-step">
                            <div class="las-step-num">1</div>
                            <div class="las-step-text">
                                Open an <strong>Incognito Window</strong>
                                <span class="las-shortcut" id="lasShortcut"></span>
                            </div>
                        </div>
                        <div class="las-step">
                            <div class="las-step-num">2</div>
                            <div class="las-step-text">Paste the copied URL in the address bar</div>
                        </div>
                        <div class="las-step">
                            <div class="las-step-num">3</div>
                            <div class="las-step-text">Press <strong>Enter</strong> — you'll be logged in automatically</div>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="button" onclick="copyLasUrl()" class="las-open-btn" id="lasCopyMainBtn">
                            <i class="fa fa-clipboard"></i> Copy Link
                        </button>
                        <button type="button" data-dismiss="modal" class="las-close-btn">
                            Close
                        </button>
                    </div>
                </div>
                <div id="lasError" style="display:none;">
                    <div style="text-align:center; padding:20px 0;">
                        <i class="fa fa-exclamation-triangle fa-2x" style="color:#ef4444;"></i>
                        <p id="lasErrorMsg" style="margin-top:12px; color:#ef4444; font-weight:600;"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script>
    $(function () {
        initDataTable('.table-team', window.location.href);
    });

    <?php if (is_admin()) { ?>
    var _lasCurrentUrl = '';
    var _isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
    $(function() {
        $('#lasShortcut').text(_isMac ? '⌘ + Shift + N' : 'Ctrl + Shift + N');
    });

    function loginAsStaff(el) {
        var staffId = $(el).data('staff-id');

        // Reset modal state
        $('#lasLoading').show();
        $('#lasResult').hide();
        $('#lasError').hide();
        $('#lasCopyMainBtn').html('<i class="fa fa-clipboard"></i> Copy Link');
        $('#loginAsStaffModal').modal('show');

        $.ajax({
            url: admin_url + 'team/login_as_staff/' + staffId,
            type: 'POST',
            dataType: 'json',
            data: {
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            success: function(response) {
                $('#lasLoading').hide();
                if (response.success) {
                    _lasCurrentUrl = response.login_url;
                    $('#lasStaffName').html('<i class="fa fa-user-circle"></i> ' + response.staff_name);
                    $('#lasUrl').val(response.login_url);
                    $('#lasOpenLink').attr('href', response.login_url);
                    $('#lasResult').show();
                    // Auto-copy to clipboard
                    _copyToClipboard(response.login_url);
                } else {
                    $('#lasErrorMsg').text(response.message || 'Failed to generate login link.');
                    $('#lasError').show();
                }
            },
            error: function() {
                $('#lasLoading').hide();
                $('#lasErrorMsg').text('An error occurred. Please try again.');
                $('#lasError').show();
            }
        });
    }

    function _copyToClipboard(text) {
        try {
            navigator.clipboard.writeText(text).catch(function() { _fallbackCopy(text); });
        } catch(e) { _fallbackCopy(text); }
    }
    function _fallbackCopy(text) {
        var t = document.createElement('textarea');
        t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.appendChild(t); t.select();
        document.execCommand('copy'); document.body.removeChild(t);
    }

    function copyLasUrl() {
        var url = $('#lasUrl').val();
        if (!url) return;
        _copyToClipboard(url);

        var $btn = $('#lasCopyBtn');
        var $mainBtn = $('#lasCopyMainBtn');
        $btn.addClass('las-copy-btn--success').html('<i class="fa fa-check"></i>');
        $mainBtn.addClass('las-copied').html('<i class="fa fa-check"></i> Copied!');
        setTimeout(function() {
            $btn.removeClass('las-copy-btn--success').html('<i class="fa fa-copy"></i>');
            $mainBtn.removeClass('las-copied').html('<i class="fa fa-clipboard"></i> Copy Link');
        }, 2000);
    }
    <?php } ?>
</script>