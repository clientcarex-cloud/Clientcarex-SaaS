<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
body {
    overflow: hidden;
}
.ccx-2fa-container {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 0;
    padding: 20px;
    background: rgba(15, 23, 42, 0.4);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    overflow-y: auto;
}
.ccx-2fa-card {
    width: 100%;
    max-width: 440px;
    /* auto top margin + auto bottom margin on the sign-out row below = vertically
       centered, but still scrollable instead of clipped on short viewports */
    margin-top: auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
    overflow: hidden;
}
.ccx-2fa-header {
    background: linear-gradient(135deg, #0ea5e9, #3b82f6);
    padding: 30px;
    text-align: center;
    color: #fff;
}
.ccx-2fa-header i {
    font-size: 36px;
    margin-bottom: 12px;
    display: block;
    opacity: 0.9;
}
.ccx-2fa-header h3 {
    margin: 0;
    font-weight: 700;
    font-size: 20px;
}
.ccx-2fa-header p {
    margin: 8px 0 0;
    opacity: 0.85;
    font-size: 13px;
}
.ccx-2fa-body {
    padding: 30px;
}
.ccx-2fa-input {
    font-size: 28px !important;
    text-align: center !important;
    letter-spacing: 10px !important;
    font-weight: 700 !important;
    padding: 15px !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 10px !important;
    transition: border-color 0.2s !important;
}
.ccx-2fa-input:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15) !important;
}
.ccx-2fa-submit {
    width: 100%;
    padding: 14px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 10px;
    margin-top: 20px;
    background: linear-gradient(135deg, #0ea5e9, #3b82f6);
    border: none;
    color: #fff;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}
.ccx-2fa-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59,130,246,0.35);
}
.ccx-2fa-recovery {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
}
.ccx-2fa-recovery a {
    color: #64748b;
    font-size: 13px;
    text-decoration: none;
}
.ccx-2fa-recovery a:hover {
    color: #3b82f6;
}
#recovery_form {
    display: none;
    margin-top: 15px;
}
</style>

<div id="wrapper">
    <div class="content">
        <div class="ccx-2fa-container">
            <div class="ccx-2fa-card">
                <div class="ccx-2fa-header">
                    <i class="fa fa-shield"></i>
                    <h3>Two-Factor Authentication</h3>
                    <p>Enter the 6-digit code from your authenticator app</p>
                    <?php if (!empty($tenant_name)): ?>
                    <div style="margin-top:10px;display:inline-block;background:rgba(255,255,255,0.2);padding:4px 14px;border-radius:20px;font-size:12px;">
                        <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>"></i>
                        <?php echo htmlspecialchars($tenant_name); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="ccx-2fa-body">
                    <?php echo form_open(admin_url('ccx_security/verify_2fa_login'), ['id' => 'totp_form']); ?>
                        <input type="hidden" name="use_recovery" value="0" id="use_recovery_flag">
                        <div class="form-group">
                            <input type="text" name="code" class="form-control ccx-2fa-input" id="totp_code"
                                placeholder="000000" maxlength="6" pattern="[0-9A-Za-z\-]{6,14}"
                                required autocomplete="one-time-code" autofocus>
                        </div>
                        <button type="submit" class="ccx-2fa-submit">
                            <i class="fa fa-check"></i> Verify
                        </button>
                    <?php echo form_close(); ?>

                    <div class="ccx-2fa-recovery">
                        <a href="javascript:void(0)" onclick="ccx_toggle_recovery()">
                            <i class="fa fa-key"></i> Use a recovery code instead
                        </a>
                        <div id="recovery_form">
                            <?php echo form_open(admin_url('ccx_security/verify_2fa_login')); ?>
                                <input type="hidden" name="use_recovery" value="1">
                                <div class="form-group">
                                    <input type="text" name="code" class="form-control" placeholder="XXXX-XXXX-XXXX"
                                        style="text-align:center; font-size:16px; letter-spacing:2px;" autocomplete="off">
                                </div>
                                <button type="submit" class="btn btn-default btn-block">
                                    <i class="fa fa-key"></i> Verify Recovery Code
                                </button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div style="text-align:center; margin:20px 0 auto;">
                <a href="<?php echo admin_url('authentication/logout'); ?>" style="color:rgba(255,255,255,0.85); font-size:13px;">
                    <i class="fa fa-sign-out"></i> Sign out
                </a>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
// This page holds no unsaved work — core main.js attaches areYouSure() to every
// form, and the JS auto-submit below bypasses its cleanup, which makes Chrome
// show "Leave site? Changes you made may not be saved." Disable it here.
$(function() {
    $('form').removeClass('dirty').off('.areYouSure');
    $(window).off('beforeunload');
    window.onbeforeunload = null;
});

function ccx_toggle_recovery() {
    var form = document.getElementById('recovery_form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
// Auto-submit when 6 digits entered
document.getElementById('totp_code').addEventListener('input', function() {
    if (this.value.length === 6 && /^\d{6}$/.test(this.value)) {
        document.getElementById('totp_form').submit();
    }
});
</script>
