<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $this->load->view('authentication/includes/head.php'); ?>

<style>
    body.login {
        background: #F0F4F8 !important;
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0;
        padding: 20px;
    }

    * {
        box-sizing: border-box;
    }

    .ho-login-wrapper {
        width: 100%;
        max-width: 480px;
    }

    .ho-login-card {
        background: #FFFFFF;
        border-radius: 24px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.04);
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.04);
        animation: hoSlideUp 0.5s ease-out;
        position: relative;
    }

    .ho-login-card::before {
        content: '';
        display: block;
        height: 5px;
        background: linear-gradient(135deg, #0D9488, #0EA5E9, #6366F1);
        border-radius: 24px 24px 0 0;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
    }

    .ho-login-header {
        text-align: center;
        padding: 40px 36px 0;
    }

    .ho-login-header .ho-logo {
        margin-bottom: 24px;
    }

    .ho-login-header .ho-logo img {
        max-height: 96px;
        width: auto;
        max-width: 100%;
    }

    .ho-login-header h1 {
        font-size: 26px;
        font-weight: 700;
        color: #1E293B;
        margin: 0 0 8px;
        letter-spacing: -0.3px;
    }

    .ho-login-header p {
        font-size: 14px;
        color: #64748B;
        margin: 0;
        font-weight: 400;
    }

    .ho-login-body {
        padding: 32px 36px;
    }

    /* ── Field ── */
    .ho-field {
        margin-bottom: 20px;
        position: relative;
    }

    .ho-field label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        margin-bottom: 8px;
        letter-spacing: -0.1px;
    }

    /* ── Input ── */
    .ho-input {
        width: 100%;
        height: 48px;
        padding: 0 16px 0 44px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        color: #1E293B;
        background: #F8FAFC;
        border: 1.5px solid #E2E8F0;
        border-radius: 12px;
        outline: none;
        transition: all 0.2s ease;
        box-shadow: none;
    }

    .ho-input::placeholder {
        color: #94A3B8;
        font-weight: 400;
    }

    .ho-input:focus {
        border-color: #0D9488;
        background: #FFF;
        box-shadow: 0 0 0 3px rgba(13,148,136,0.1);
    }

    .input-icon {
        position: absolute;
        left: 16px;
        top: 42px; /* label height (approx) + padding */
        color: #94A3B8;
        font-size: 15px;
        transition: color 0.2s;
    }

    .ho-input:focus + .input-icon {
        color: #0D9488;
    }

    /* Alerts */
    .alert {
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-danger {
        background: #FFF5F5;
        color: #EF4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .alert-success {
        background: #F0FDFA;
        color: #0D9488;
        border: 1px solid rgba(13, 148, 136, 0.2);
    }

    /* Button */
    .ho-btn-primary {
        width: 100%;
        height: 50px;
        border: none;
        border-radius: 14px;
        background: linear-gradient(135deg, #0D9488 0%, #0EA5E9 100%);
        color: #FFF;
        font-size: 15px;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 12px;
    }

    .ho-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 24px rgba(13,148,136,0.35);
    }

    .ho-btn-primary:active {
        transform: translateY(0);
    }

    /* Checkboxes & Links */
    .ho-checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
    }

    .ho-checkbox-group input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: #0D9488;
        cursor: pointer;
        margin: 0;
    }

    .ho-checkbox-group label {
        font-size: 13px;
        color: #475569;
        font-weight: 400;
        cursor: pointer;
        margin: 0;
        display: inline-block;
    }

    .ho-checkbox-group a {
        color: #0D9488;
        text-decoration: none;
        font-weight: 500;
    }

    .ho-checkbox-group a:hover {
        text-decoration: underline;
        color: #0F766E;
    }

    .ho-copyright {
        text-align: center;
        margin-top: 24px;
        font-size: 13px;
        color: #94A3B8;
        font-weight: 500;
    }

    /* Spinner */
    .spinner {
        display: none;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @keyframes hoSlideUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 480px) {
        .ho-login-card {
            border-radius: 20px;
        }
        .ho-login-header {
            padding: 32px 24px 0;
        }
        .ho-login-body {
            padding: 24px;
        }
    }
</style>

<body class="login login_admin">

    <div class="ho-login-wrapper">
        <div class="ho-login-card">
            
            <div class="ho-login-header">
                <div class="ho-logo company-logo" style="padding: 0;">
                    <?php get_dark_company_logo(); ?>
                </div>
                <h1><?= _l('admin_auth_login_heading'); ?></h1>
                <p><?= _l('welcome_back_sign_in'); ?></p>
            </div>

            <div class="ho-login-body">
                
                <?php $this->load->view('authentication/includes/alerts'); ?>

                <?= form_open($this->uri->uri_string(), ['id' => 'loginForm']); ?>

                <?= validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>

                <?php hooks()->do_action('after_admin_login_form_start'); ?>

                <div class="ho-field">
                    <label for="email"><?= _l('admin_auth_login_email'); ?></label>
                    <div style="position: relative;">
                        <input type="email" class="ho-input" id="email" name="email" required autofocus="1">
                        <svg class="input-icon" style="top: 14px; width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <div class="ho-field">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <label for="password" style="margin-bottom: 0;"><?= _l('admin_auth_login_password'); ?></label>
                        <a href="<?= admin_url('authentication/forgot_password'); ?>" style="font-size: 13px; color: #0D9488; font-weight: 500; text-decoration: none;">
                            <?= _l('admin_auth_login_fp'); ?>
                        </a>
                    </div>
                    <div style="position: relative;">
                        <input type="password" class="ho-input" id="password" name="password" required>
                        <svg class="input-icon" style="top: 14px; width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                </div>

                <?php if (show_recaptcha()) { ?>
                <div class="g-recaptcha" data-sitekey="<?= get_option('recaptcha_site_key'); ?>" style="margin-bottom: 20px;">
                </div>
                <?php } ?>

                <div class="ho-checkbox-group" style="margin-bottom: 24px;">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember"><?= _l('admin_auth_login_remember_me'); ?></label>
                </div>

                <button type="submit" class="ho-btn-primary" id="loginBtn">
                    <span class="btn-text"><?= _l('admin_auth_login_button'); ?></span>
                    <div class="spinner" id="btnSpinner"></div>
                </button>

                <?php hooks()->do_action('before_admin_login_form_close'); ?>

                <?= form_close(); ?>
            </div>

        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const text = btn.querySelector('.btn-text');
            const spinner = document.getElementById('btnSpinner');
            
            if(text && spinner) {
                text.style.display = 'none';
                spinner.style.display = 'block';
                btn.style.pointerEvents = 'none';
            }
        });
    </script>
</body>
</html>