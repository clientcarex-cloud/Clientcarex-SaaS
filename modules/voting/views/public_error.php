<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($title); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:radial-gradient(900px 420px at 85% -8%,#e0e7ff 0%,transparent 60%),linear-gradient(160deg,#eef2ff 0%,#f8fafc 45%,#f1f5f9 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#0f172a}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:44px 36px;max-width:440px;text-align:center;box-shadow:0 20px 50px rgba(15,23,42,.10)}
.icon{width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#eef2ff,#f5f3ff);border:1px solid #e0e7ff;display:flex;align-items:center;justify-content:center;font-size:34px;margin:0 auto 18px}
h1{font-size:20px;font-weight:800;margin-bottom:10px}
p{font-size:14px;line-height:1.6;color:#64748b}
</style>
</head>
<body>
<div class="card">
    <div class="icon">🗳️</div>
    <h1><?php echo html_escape($title); ?></h1>
    <p><?php echo html_escape($message); ?></p>
</div>
</body>
</html>
