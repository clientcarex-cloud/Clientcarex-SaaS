<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Organization</title>
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
            border-radius: 50%;
            background: #F8FAFC;
            padding: 4px;
            border: 2px solid #E2E8F0;
        }

        .ho-login-header h1 {
            font-size: 22px;
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

        /* Tenant List */
        .tenant-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .tenant-card {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 16px;
        }

        .company-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #818cf8, #e879f9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .company-details h3 {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .company-details p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .company-action {
            color: var(--text-secondary);
            transition: color 0.3s;
        }

        .company-card:hover .company-action {
            color: var(--primary);
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            text-align: center;
            width: 100%;
        }

        .back-link:hover {
            color: var(--text-primary);
        }

        @media (max-width: 480px) {
            .selection-container {
                border-radius: 20px;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <div class="selection-container">
        <div class="header-area">
            <h2>Select Company</h2>
            <p>You have access to multiple accounts.</p>
        </div>

        <div class="company-list">
            <?php foreach($matches as $match): 
                $company = $match['company'];
                $initial = strtoupper(substr($company->name, 0, 1));
            ?>
            <a href="<?php echo site_url('unified_login/process_selection/' . $company->slug); ?>" class="company-card">
                <div class="company-info">
                    <div class="company-avatar">
                        <?php echo $initial; ?>
                    </div>
                    <div class="company-details">
                        <h3><?php echo htmlspecialchars($company->name); ?></h3>
                        <p><?php echo htmlspecialchars($match['staff_name']); ?></p>
                    </div>
                </div>
                <div class="company-action">
                    <i class="fa fa-chevron-right"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <a href="<?php echo site_url('login'); ?>" class="back-link">
            <i class="fa fa-arrow-left"></i> Back to Login
        </a>
    </div>
</body>
</html>
