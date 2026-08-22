<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-shield" style="color:#0ea5e9;"></i>
                        <?php echo _l('ccx_security_2fa_setup'); ?>
                        <span class="label" style="background:<?php echo (!empty($is_tenant)) ? '#6366f1' : '#10b981'; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <a href="<?php echo admin_url('ccx_security'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($already_setup) && $already_setup): ?>
        <!-- ─── Already Set Up ─── -->
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="panel_s" style="text-align:center; padding:40px;">
                    <div style="width:80px;height:80px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                        <i class="fa fa-check-circle" style="font-size:40px;color:#10b981;"></i>
                    </div>
                    <h3 class="tw-font-bold" style="color:#10b981;">Two-Factor Authentication is Active</h3>
                    <p class="text-muted" style="margin:15px 0;">
                        Your account has been protected with 2FA since <strong><?php echo date('M d, Y', strtotime($setup_date)); ?></strong>.
                    </p>
                    <div style="margin-top:25px;">
                        <a href="<?php echo admin_url('ccx_security/disable_2fa/' . $staff_id); ?>"
                            class="btn btn-danger"
                            onclick="return confirm('This will disable 2FA for this account.\n\nIMPORTANT: You must also manually delete the old entry from your authenticator app (Google Authenticator / Authy).');">
                            <i class="fa fa-times"></i> Disable 2FA
                        </a>
                        <a href="<?php echo admin_url('ccx_security/setup_2fa/' . $staff_id) . '?reset=1'; ?>" class="btn btn-default" style="margin-left:10px;"
                            onclick="return confirm('This will generate a new QR code and secret key.\n\nIMPORTANT: You must also manually delete the old entry from your authenticator app (Google Authenticator / Authy) before scanning the new one.');">
                            <i class="fa fa-refresh"></i> Reset &amp; Reconfigure
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ─── Setup Flow ─── -->
        <div class="row">
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body" style="padding:30px;">
                        <h4 class="tw-font-semibold tw-mb-4">
                            <span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:#0ea5e9;color:#fff;align-items:center;justify-content:center;font-size:14px;font-weight:700;margin-right:8px;">1</span>
                            Scan QR Code
                        </h4>
                        <p class="text-muted" style="margin-bottom:20px;">
                            Scan this QR code with your authenticator app (Google Authenticator, Authy, or Microsoft Authenticator).
                        </p>
                        <div style="text-align:center; padding:20px; background:#f8fafc; border-radius:12px; border:2px dashed #e2e8f0;">
                            <?php echo $qr_code; ?>
                        </div>
                        <div style="margin-top:20px; padding:15px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">
                            <label class="text-muted" style="font-size:11px; margin-bottom:5px; display:block;">Manual Entry Key</label>
                            <code style="font-size:14px; letter-spacing:2px; font-weight:600; word-break:break-all; color:#0369a1;"><?php echo $secret; ?></code>
                        </div>

                        <hr style="margin:25px 0;">

                        <h4 class="tw-font-semibold tw-mb-4">
                            <span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:#0ea5e9;color:#fff;align-items:center;justify-content:center;font-size:14px;font-weight:700;margin-right:8px;">2</span>
                            Verify Code
                        </h4>
                        <p class="text-muted" style="margin-bottom:15px;">
                            Enter the 6-digit code from your authenticator app to complete setup.
                        </p>
                        <?php echo form_open(admin_url('ccx_security/verify_2fa_setup')); ?>
                            <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
                            <div class="form-group">
                                <input type="text" name="code" class="form-control" placeholder="000000"
                                    maxlength="6" pattern="[0-9]{6}" required autocomplete="off"
                                    style="font-size:24px; text-align:center; letter-spacing:8px; font-weight:700; padding:12px;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" style="padding:12px; font-size:15px;">
                                <i class="fa fa-check"></i> Verify & Activate 2FA
                            </button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body" style="padding:30px;">
                        <h4 class="tw-font-semibold tw-mb-4">
                            <span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:#f59e0b;color:#fff;align-items:center;justify-content:center;font-size:14px;font-weight:700;margin-right:8px;">3</span>
                            Save Recovery Codes
                        </h4>
                        <div style="padding:15px; background:#fffbeb; border-radius:8px; border:1px solid #fede68; margin-bottom:20px;">
                            <i class="fa fa-exclamation-triangle text-warning"></i>
                            <strong>Important:</strong> Save these backup codes in a secure location. Each code can only be used once to regain access if you lose your authenticator device.
                        </div>
                        <div style="background:#f8fafc; border-radius:8px; padding:20px; border:1px solid #e2e8f0;">
                            <div class="row">
                                <?php foreach ($recovery_codes as $i => $code): ?>
                                <div class="col-xs-6" style="margin-bottom:8px;">
                                    <code style="font-size:13px; font-weight:600; display:block; padding:6px 10px; background:#fff; border-radius:4px; border:1px solid #e2e8f0; text-align:center;">
                                        <?php echo $i + 1; ?>. <?php echo $code; ?>
                                    </code>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" class="btn btn-default btn-block" style="margin-top:15px;" onclick="ccx_copy_codes()">
                            <i class="fa fa-copy"></i> Copy All Codes
                        </button>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body" style="padding:20px;">
                        <h5 class="tw-font-semibold"><i class="fa fa-info-circle text-primary"></i> Compatible Apps</h5>
                        <ul class="text-muted" style="font-size:13px; line-height:2;">
                            <li><strong>Google Authenticator</strong> — iOS & Android</li>
                            <li><strong>Authy</strong> — iOS, Android, Desktop</li>
                            <li><strong>Microsoft Authenticator</strong> — iOS & Android</li>
                            <li><strong>1Password</strong> — All platforms</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php init_tail(); ?>

<!-- QR Code library (client-side, no external API) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
// Initialize QR code rendering
(function() {
    var container = document.getElementById('ccx_qr_container');
    if (container) {
        var data = container.getAttribute('data-qr');
        if (data) {
            new QRCode(container, {
                text: data,
                width: 250,
                height: 250,
                colorDark: '#1e293b',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    }
})();

function ccx_copy_codes() {
    var codes = <?php echo json_encode(isset($recovery_codes) ? $recovery_codes : []); ?>;
    var text = "CCX Security Recovery Codes\n" + "Generated: <?php echo date('Y-m-d H:i'); ?>\n\n";
    codes.forEach(function(code, i) {
        text += (i + 1) + ". " + code + "\n";
    });
    text += "\nKeep these codes in a safe place.";

    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert_float('success', 'Recovery codes copied to clipboard!');
        });
    } else {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert_float('success', 'Recovery codes copied to clipboard!');
    }
}
</script>

