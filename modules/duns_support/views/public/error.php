<?php defined('BASEPATH') or exit('No direct script access allowed');
$noindex = true;
include __DIR__ . '/_head.php';
?>
<body>
<div class="page">
<?php include __DIR__ . '/_topbar.php'; ?>
<div class="narrow">
    <div class="card center">
        <div class="big-ic bad"><i class="fa-solid fa-circle-exclamation"></i></div>
        <h1><?php echo html_escape($title); ?></h1>
        <p class="sub"><?php echo html_escape($message); ?></p>
        <div class="help-row" style="justify-content:center">
            <a class="btn btn-primary" href="<?php echo duns_public_url(); ?>">Back to home</a>
            <?php if ($landing['wa_link'] !== '') { ?><a class="btn btn-wa" href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp us</a><?php } ?>
        </div>
    </div>
</div>
</div>
</body>
</html>
