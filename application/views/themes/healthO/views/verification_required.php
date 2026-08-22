<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<style>
.ho-verify-wrapper {
    max-width: 480px;
    margin: 40px auto;
    padding: 40px 32px;
    background: #FFF;
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.08);
    text-align: center;
    font-family: 'Inter', -apple-system, sans-serif;
}
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
.ho-verify-icon {
    width: 72px; height: 72px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #F0FDFA, #CCFBF1);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.ho-verify-icon i { font-size: 32px; color: #0D9488; }
.ho-verify-title {
    font-size: 22px; font-weight: 700; color: #1E293B; margin-bottom: 8px;
}
.ho-verify-subtitle {
    font-size: 14px; color: #64748B; line-height: 1.6; margin-bottom: 28px;
}
.ho-verify-subtitle strong { color: #0F172A; font-weight: 600; }
.ho-verify-inputs {
    display: flex; justify-content: center; gap: 10px; margin-bottom: 24px;
}
.ho-verify-inputs input {
    width: 48px; height: 54px; text-align: center;
    font-size: 22px; font-weight: 700; font-family: 'Inter', monospace;
    color: #1E293B; background: #F8FAFC;
    border: 1.5px solid #E2E8F0; border-radius: 12px;
    outline: none; transition: all 0.2s ease;
}
.ho-verify-inputs input:focus {
    border-color: #0D9488; background: #FFF;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1); transform: scale(1.05);
}
.ho-verify-inputs input.filled { border-color: #0D9488; background: #F0FDFA; }
.ho-verify-inputs input.err { border-color: #EF4444; background: #FFF5F5; animation: shake 0.4s ease; }
@keyframes shake {
    0%,100% { transform: translateX(0); }
    20% { transform: translateX(-4px); } 40% { transform: translateX(4px); }
    60% { transform: translateX(-4px); } 80% { transform: translateX(4px); }
}
.ho-verify-msg { font-size: 13px; min-height: 20px; margin-bottom: 16px; }
.ho-verify-msg.error { color: #EF4444; }
.ho-verify-msg.success { color: #0D9488; }
.ho-verify-btn {
    width: 100%; padding: 14px; border: none; border-radius: 12px;
    background: linear-gradient(135deg, #0D9488, #0EA5E9); color: #FFF;
    font-size: 15px; font-weight: 600; font-family: 'Inter', sans-serif;
    cursor: pointer; transition: all 0.2s ease;
}
.ho-verify-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(13,148,136,0.3); }
.ho-verify-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.ho-verify-resend {
    font-size: 13px; color: #64748B; margin-top: 20px;
}
.ho-verify-resend a { color: #0D9488; font-weight: 600; text-decoration: none; cursor: pointer; }
.ho-verify-resend a:hover { text-decoration: underline; }
.ho-verify-resend .cd { color: #0D9488; font-weight: 600; }
.ho-verify-success {
    display: none; width: 80px; height: 80px; margin: 0 auto 20px;
    background: linear-gradient(135deg, #0D9488, #0EA5E9); border-radius: 50%;
    align-items: center; justify-content: center;
}
.ho-verify-success i { font-size: 36px; color: #FFF; }
</style>

<div class="ho-verify-wrapper" id="verify-form-area">
    <div class="ho-verify-icon"><i class="fa fa-shield-alt"></i></div>
    <div class="ho-verify-title">Verify Your Account</div>
    <div class="ho-verify-subtitle">
        We've sent a 4-digit verification code to your registered mobile number &amp; email address.
        <br>Please enter it below.
    </div>
    <div class="ho-verify-inputs" id="v-inputs">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-i="0" autocomplete="one-time-code" autofocus>
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-i="1">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-i="2">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-i="3">
    </div>
    <div class="ho-verify-msg" id="v-msg"></div>
    <button class="ho-verify-btn" id="v-btn"><i class="fa fa-shield"></i> Verify OTP</button>
    <div class="ho-verify-resend">
        Didn't receive the code?
        <a href="javascript:void(0)" id="v-resend">Resend OTP</a>
        <span id="v-cd" style="display:none;">Resend in <span class="cd" id="v-cd-val">60</span>s</span>
    </div>
</div>

<div class="ho-verify-wrapper" id="verify-success-area" style="display:none;">
    <div class="ho-verify-success" style="display:flex;"><i class="fa fa-check"></i></div>
    <div class="ho-verify-title" style="color:#0D9488;">Account Verified!</div>
    <div class="ho-verify-subtitle">Redirecting you to your dashboard…</div>
</div>

<script>
(function() {
    var $d = $('#v-inputs input');
    var $m = $('#v-msg'), $b = $('#v-btn');
    var cn = '<?= $this->security->get_csrf_token_name(); ?>';
    var ch = '<?= $this->security->get_csrf_hash(); ?>';

    $d.on('input', function() {
        var v = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(v);
        if (v) {
            $(this).addClass('filled').removeClass('err');
            var i = parseInt($(this).data('i'));
            if (i < 3) $d.eq(i + 1).focus();
        } else { $(this).removeClass('filled'); }
        $m.text('').removeClass('error success');
    });
    $d.on('keydown', function(e) {
        var i = parseInt($(this).data('i'));
        if (e.key === 'Backspace' && !$(this).val() && i > 0) $d.eq(i-1).val('').removeClass('filled').focus();
        if (e.key === 'ArrowLeft' && i > 0) { e.preventDefault(); $d.eq(i-1).focus(); }
        if (e.key === 'ArrowRight' && i < 3) { e.preventDefault(); $d.eq(i+1).focus(); }
    });
    $d.first().on('paste', function(e) {
        e.preventDefault();
        var t = (e.originalEvent.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
        for (var i = 0; i < 4 && i < t.length; i++) $d.eq(i).val(t[i]).addClass('filled');
        if (t.length >= 4) $d.eq(3).focus();
    });

    function doVerify() {
        var otp = '';
        $d.each(function() { otp += $(this).val(); });
        if (otp.length !== 4) {
            $m.text('Please enter all 4 digits.').addClass('error');
            $d.filter(function() { return !$(this).val(); }).addClass('err');
            return;
        }
        $b.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying…');
        var p = { otp: otp }; p[cn] = ch;
        $.post('<?= site_url("verification/verify_otp"); ?>', p, function(r) {
            if (r.csrf_token) ch = r.csrf_token;
            if (r.success) {
                $m.text(r.message).addClass('success').removeClass('error');
                $('#verify-form-area').fadeOut(300, function() { $('#verify-success-area').fadeIn(300); });
                setTimeout(function() { window.location.href = r.redirect || '<?= site_url("clients"); ?>'; }, 2000);
            } else {
                $m.text(r.message).addClass('error').removeClass('success');
                $d.addClass('err'); setTimeout(function() { $d.removeClass('err'); }, 600);
                $b.prop('disabled', false).html('<i class="fa fa-shield"></i> Verify OTP');
                if (r.expired) { $d.val('').removeClass('filled'); $d.first().focus(); }
            }
        }, 'json').fail(function() {
            $m.text('Network error.').addClass('error');
            $b.prop('disabled', false).html('<i class="fa fa-shield"></i> Verify OTP');
        });
    }
    $b.on('click', doVerify);
    $d.last().on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); doVerify(); }});

    // Resend
    var $rl = $('#v-resend'), $cd = $('#v-cd'), $cv = $('#v-cd-val'), ct = null;
    function startCd(s) {
        var r = s; $rl.hide(); $cd.show(); $cv.text(r);
        if (ct) clearInterval(ct);
        ct = setInterval(function() { r--; $cv.text(r); if (r <= 0) { clearInterval(ct); $cd.hide(); $rl.show(); }}, 1000);
    }
    $rl.on('click', function() {
        var p = {}; p[cn] = ch; $rl.text('Sending…');
        $.post('<?= site_url("verification/resend_otp"); ?>', p, function(r) {
            if (r.csrf_token) ch = r.csrf_token;
            if (r.success) {
                $m.text(r.message).addClass('success').removeClass('error');
                $d.val('').removeClass('filled err'); $d.first().focus();
                startCd(r.cooldown || 60);
            } else {
                $m.text(r.message).addClass('error').removeClass('success');
                if (r.cooldown) startCd(r.cooldown);
            }
            $rl.text('Resend OTP');
        }, 'json').fail(function() { $m.text('Network error.').addClass('error'); $rl.text('Resend OTP'); });
    });
})();
</script>