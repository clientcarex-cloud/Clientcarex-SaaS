<?php defined('BASEPATH') or exit('No direct script access allowed'); include __DIR__ . '/_public_head.php'; ?>
    <div class="card">
        <div class="card-head" style="text-align:center"><span class="badge bad"><?php echo html_escape($title); ?></span><h2 style="margin-top:10px"><?php echo html_escape($message); ?></h2></div>
        <div class="card-body" style="text-align:center"><p class="hint">Please contact the academy desk for help.</p></div>
    </div>
<?php include __DIR__ . '/_public_foot.php'; ?>
