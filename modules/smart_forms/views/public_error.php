<?php defined('BASEPATH') or exit('No direct script access allowed');

$company = get_option('companyname');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--warn:#f59e0b;--text:#1e293b;--muted:#64748b;--border:#e2e8f0}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 40%,#f1f5f9 100%);min-height:100vh;color:var(--text);display:flex;align-items:center;justify-content:center;padding:24px 16px}
.card{background:#fff;border:1px solid var(--border);border-radius:20px;box-shadow:0 20px 50px rgba(15,23,42,.1);max-width:480px;width:100%;padding:44px 36px;text-align:center}
.icon{width:80px;height:80px;margin:0 auto 20px;border-radius:50%;background:#fffbeb;display:flex;align-items:center;justify-content:center}
.icon i{font-size:36px;color:var(--warn)}
h1{font-size:22px;font-weight:800;margin-bottom:10px}
p{font-size:14.5px;color:var(--muted);line-height:1.65}
.footer{position:fixed;bottom:14px;left:0;right:0;text-align:center;font-size:12px;color:#94a3b8}
</style>
</head>
<body>
<div class="card">
    <div class="icon"><i class="fa-solid fa-circle-exclamation"></i></div>
    <h1><?php echo html_escape($title); ?></h1>
    <p><?php echo html_escape($message); ?></p>
</div>
<div class="footer"><?php echo html_escape($company ?: ''); ?></div>
</body>
</html>
