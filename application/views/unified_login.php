<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title : 'Unified Portal'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-height: 48px;
            width: auto;
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

        .ho-login-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: #64748B;
        }

        .ho-login-link a {
            color: #0D9488;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .ho-login-link a:hover {
            color: #0F766E;
            text-decoration: underline;
        }

        .ho-terms-text {
            text-align: center;
            font-size: 12.5px; 
            color: #94A3B8;
            margin-top: 16px; 
            line-height: 1.5;
        }
        .ho-terms-text a {
            color: #0D9488; 
            font-weight: 500;
            text-decoration: none;
        }
        .ho-terms-text a:hover { 
            text-decoration: underline; 
            color: #0F766E; 
        }

        /* Tenant List */
        .tenant-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
            max-height: 320px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .tenant-card {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .tenant-card:hover {
            border-color: #0D9488;
            background: #FFF;
            box-shadow: 0 4px 12px rgba(13,148,136,0.08);
            transform: translateY(-2px);
        }

        .tenant-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .tenant-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0D9488, #0EA5E9);
            color: #FFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .tenant-details h3 {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: 600;
            color: #1E293B;
        }

        .tenant-details p {
            margin: 0;
            font-size: 12px;
            color: #64748B;
        }

        .tenant-card i.fa-chevron-right {
            color: #94A3B8;
            font-size: 14px;
            transition: transform 0.2s;
        }

        .tenant-card:hover i.fa-chevron-right {
            color: #0D9488;
            transform: translateX(4px);
        }

        .ho-btn-back {
            width: 100%;
            height: 50px;
            border: 1.5px solid #E2E8F0;
            border-radius: 14px;
            background: #FFF;
            color: #64748B;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }

        .ho-btn-back:hover {
            border-color: #CBD5E1;
            background: #F8FAFC;
            color: #1E293B;
        }

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
</head>
<body class="login">

    <div class="ho-login-wrapper">
        <div class="ho-login-card">
            
            <div class="ho-login-header">
                <div class="ho-logo">
                    <?php echo get_dark_company_logo(); ?>
                </div>
                <?php if (!empty($matches)) { ?>
                    <h1>Welcome Back</h1>
                    <p>Select an organization to continue</p>
                <?php } else { ?>
                    <h1>Welcome Back</h1>
                    <p>Sign in to your HealthO Pro Staff Portal</p>
                <?php } ?>
            </div>

            <div class="ho-login-body">
                <?php $alertclass = "";
                if($this->session->flashdata('message-success')){
                    $alertclass = "alert-success";
                } else if ($this->session->flashdata('message-danger')){
                    $alertclass = "alert-danger";
                }
                ?>
                <?php if($this->session->flashdata('message-success') || $this->session->flashdata('message-danger')){ ?>
                    <div class="alert <?php echo $alertclass; ?>">
                        <i class="fa <?php echo $alertclass == 'alert-danger' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                        <?php 
                        echo $this->session->flashdata('message-success') 
                            ? $this->session->flashdata('message-success') 
                            : $this->session->flashdata('message-danger'); 
                        ?>
                    </div>
                <?php } ?>

                <?php if (!empty($matches)) { ?>
                    <div class="tenant-list">
                        <?php foreach ($matches as $match): ?>
                            <a href="<?php echo site_url('unified_login/process_selection/' . $match['company']->slug); ?>" class="tenant-card">
                                <div class="tenant-info">
                                    <div class="tenant-icon">
                                        <i class="fa fa-building"></i>
                                    </div>
                                    <div class="tenant-details">
                                        <h3><?php echo htmlspecialchars($match['company']->name); ?></h3>
                                        <p><?php echo htmlspecialchars($match['company']->slug); ?>.healtho.pro</p>
                                    </div>
                                </div>
                                <i class="fa fa-chevron-right"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <a href="<?php echo site_url('unified_login/clear_selection'); ?>" class="ho-btn-back">
                        <i class="fa fa-arrow-left"></i> Sign in with a different account
                    </a>
                <?php } else { ?>
                    <form action="<?php echo site_url('unified_login/authenticate'); ?>" method="POST" id="loginForm">
                        <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                        
                        <div class="ho-field">
                            <label for="email">Your Email</label>
                            <div style="position: relative;">
                                <input type="email" class="ho-input" id="email" name="email" required autocomplete="email" placeholder="name@company.com">
                                <i class="fa fa-envelope input-icon" style="top: 16px;"></i>
                            </div>
                        </div>

                        <div class="ho-field">
                            <label for="password">Password</label>
                            <div style="position: relative;">
                                <input type="password" class="ho-input" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                                <i class="fa fa-lock input-icon" style="top: 16px;"></i>
                            </div>
                        </div>

                        <div class="ho-checkbox-group" style="margin-bottom: 24px;">
                            <input type="checkbox" id="remember" name="remember" value="1">
                            <label for="remember">Remember me on this device</label>
                        </div>

                        <button type="submit" class="ho-btn-primary" id="loginBtn">
                            <span class="btn-text">Sign In Securely</span>
                            <div class="spinner" id="btnSpinner"></div>
                        </button>

                        <div class="ho-terms-text" id="ho-terms-text">
                            By logging in, you accept our
                            <a href="<?php echo terms_url(); ?>" target="_blank">Terms & Conditions</a> and
                            <a href="<?php echo site_url('privacy-policy'); ?>" target="_blank">Privacy Policy</a>.
                        </div>

                        <div class="ho-login-link">
                            Don't have an account?
                            <a href="<?php echo site_url('authentication/register?ps_plan=startup-lims'); ?>">Sign Up</a>
                        </div>
                    </form>
                <?php } ?>
            </div>

        </div>
        
        <div class="ho-copyright">
            &copy; <?php echo date('Y'); ?> <?php echo get_option('companyname'); ?>. All rights reserved.
        </div>
    </div>

    <style>
        .ho-copyright {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #94A3B8;
            font-weight: 500;
        }
    </style>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const text = btn.querySelector('.btn-text');
            const spinner = document.getElementById('btnSpinner');
            
            text.style.display = 'none';
            spinner.style.display = 'block';
            btn.style.pointerEvents = 'none';
        });
    </script>
</body>
</html>
