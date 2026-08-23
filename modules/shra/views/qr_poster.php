<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.shra-poster{max-width:520px;margin:0 auto;background:#f6efe0;border:2px solid #b8922e;outline:6px solid #f6efe0;outline-offset:-10px;border-radius:6px;padding:44px 36px;text-align:center;position:relative}
.shra-poster img{width:110px;height:110px;border-radius:50%;box-shadow:0 0 0 4px #f6efe0,0 0 0 5px #e9d9b6}
.shra-poster h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:30px;margin:16px 0 2px;color:#1c1a17;line-height:1.1}
.shra-poster .tag{font-family:'Cormorant Garamond',Georgia,serif;font-style:italic;color:#7a6f5e;font-size:15px}
.shra-poster .div{display:flex;align-items:center;gap:8px;justify-content:center;margin:18px 0}
.shra-poster .div:before,.shra-poster .div:after{content:'';width:70px;height:1px;background:#b8922e}
.shra-poster .div i{color:#b8922e;font-size:9px}
.shra-poster h2{font-family:'Cormorant Garamond',Georgia,serif;font-size:24px;font-weight:700;margin:0 0 6px;color:#1c1a17}
.shra-poster p{color:#3a3530;font-size:13.5px;margin:0 0 16px;line-height:1.5}
.shra-poster .qr{display:inline-block;background:#fff;padding:14px;border-radius:14px;border:1px solid #e3d6b8}
.shra-poster .qr svg{width:220px;height:220px;display:block}
.shra-poster .url{font-family:ui-monospace,Menlo,monospace;font-size:11px;color:#7a6f5e;margin-top:14px;word-break:break-all}
.shra-poster .steps{display:flex;gap:10px;justify-content:center;margin-top:18px}
.shra-poster .steps span{flex:1;background:#fff;border:1px solid #e3d6b8;border-radius:10px;padding:8px 6px;font-size:11px;color:#3a3530;font-weight:600}
.shra-poster .steps b{display:block;font-family:'Cormorant Garamond',Georgia,serif;font-size:20px;color:#b8922e}
@media print{body *{visibility:hidden}.shra-poster,.shra-poster *{visibility:visible}.shra-poster{position:absolute;left:0;right:0;top:20px;margin:auto;outline:none}#wrapper{margin:0!important}}
</style>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = ''; include __DIR__ . '/_nav.php'; ?>
    <div class="shra-toolbar" style="justify-content:center">
        <button class="shra-btn shra-btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print poster</button>
        <button class="shra-btn shra-btn-outline shra-copy" data-copy="<?php echo html_escape($url); ?>"><i class="fa fa-link"></i> Copy link</button>
        <a href="<?php echo html_escape($url); ?>" target="_blank" class="shra-btn shra-btn-outline"><i class="fa fa-external-link"></i> Open form</a>
    </div>
    <div class="shra-poster">
        <img src="<?php echo shra_logo_url(); ?>" alt="">
        <h1><?php echo html_escape(get_option('shra_academy_name')); ?></h1>
        <div class="tag"><?php echo html_escape(get_option('shra_tagline')); ?></div>
        <div class="div"><i class="fa-solid fa-diamond"></i></div>
        <h2>Become a rider</h2>
        <p>Scan to fill the membership form on your phone.<br>Takes two minutes — your membership card is ready instantly.</p>
        <div class="qr"><?php echo $svg; ?></div>
        <div class="url"><?php echo html_escape($url); ?></div>
        <div class="steps"><span><b>1</b>Scan &amp; register</span><span><b>2</b>Pick a riding plan at the desk</span><span><b>3</b>Ride — first come, first served</span></div>
    </div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
