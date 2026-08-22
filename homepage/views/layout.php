<?php
/** @var array $page  @var string $content */
$canonical = SITE_URL . '/' . ($page['route'] === '' ? '' : $page['route']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page['title']) ?></title>
<meta name="description" content="<?= e($page['description']) ?>">
<?php if ($page['noindex'] ?? false): ?>
<meta name="robots" content="noindex">
<?php endif ?>
<link rel="canonical" href="<?= e($canonical) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= SITE_NAME ?>">
<meta property="og:title" content="<?= e($page['title']) ?>">
<meta property="og:description" content="<?= e($page['description']) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= SITE_URL ?>/assets/img/ClientcareX-Logo.png">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= asset('assets/img/favicon-32x32.png') ?>" sizes="32x32">
<link rel="apple-touch-icon" href="<?= asset('assets/img/favicon-32x32.png') ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<script>document.documentElement.classList.add("js");</script>
</head>
<body>
<a class="skip-link" href="#content">Skip to content</a>
<?php part('topbar') ?>
<?php part('header', ['nav' => $page['nav']]) ?>

<main id="content">
<?= $content ?>
</main>
<?php part('footer') ?>

<script src="<?= asset('assets/js/main.js') ?>" defer></script>
</body>
</html>
