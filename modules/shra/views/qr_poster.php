<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.shra-poster{max-width:520px;margin:0 auto;background:#f6efe0;border:2px solid #b8922e;outline:6px solid #f6efe0;outline-offset:-10px;border-radius:6px;padding:44px 36px;text-align:center;position:relative}
.shra-poster .logo{width:110px;height:110px;border-radius:50%;box-shadow:0 0 0 4px #f6efe0,0 0 0 5px #e9d9b6}
.shra-poster h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:30px;margin:16px 0 2px;color:#1c1a17;line-height:1.1}
.shra-poster .tag{font-family:'Cormorant Garamond',Georgia,serif;font-style:italic;color:#7a6f5e;font-size:15px}
.shra-poster .div{display:flex;align-items:center;gap:8px;justify-content:center;margin:18px 0}
.shra-poster .div:before,.shra-poster .div:after{content:'';width:70px;height:1px;background:#b8922e}
.shra-poster .div i{color:#b8922e;font-size:9px}
.shra-poster h2{font-family:'Cormorant Garamond',Georgia,serif;font-size:24px;font-weight:700;margin:0 0 6px;color:#1c1a17}
.shra-poster p{color:#3a3530;font-size:13.5px;margin:0 0 16px;line-height:1.5}
.shra-poster .qr{width:248px;height:248px;margin:0 auto;background:#fff;padding:14px;border-radius:14px;border:1px solid #e3d6b8;display:flex;align-items:center;justify-content:center}
.shra-poster .qr svg{width:220px;height:220px;display:block}
.shra-poster .url{font-family:ui-monospace,Menlo,monospace;font-size:11px;color:#7a6f5e;margin-top:14px;word-break:break-all}
.shra-poster .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:22px;position:relative}
.shra-poster .steps:before{content:'';position:absolute;left:16%;right:16%;top:30px;border-top:2px dashed #d4b45c;z-index:0}
.shra-poster .step{position:relative;z-index:1;text-align:center}
.shra-poster .step .ic{width:60px;height:60px;border-radius:50%;margin:0 auto 10px;background:#1c1a17;color:#f6efe0;display:flex;align-items:center;justify-content:center;font-size:22px;box-shadow:0 0 0 4px #f6efe0,0 0 0 5px #b8922e}
.shra-poster .step .n{position:absolute;top:-6px;left:calc(50% + 18px);width:22px;height:22px;border-radius:50%;background:#b8922e;color:#fff;font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;border:2px solid #f6efe0}
.shra-poster .step b{display:block;font-family:'Cormorant Garamond',Georgia,serif;font-size:17px;font-weight:700;color:#1c1a17;line-height:1.15}
.shra-poster .step small{display:block;font-size:11px;color:#7a6f5e;margin-top:3px;line-height:1.35}
.shra-poster .powered{margin-top:24px;padding-top:16px;border-top:1px solid #e3d6b8}
.shra-poster .powered .shra-powered img{height:20px;width:auto;border-radius:0;box-shadow:none}
@media print{
  body *{visibility:hidden}
  .shra-poster,.shra-poster *{visibility:visible}
  .shra-poster{position:absolute;left:0;right:0;top:20px;margin:auto;outline:none;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .shra-poster a[href]:after,.shra-poster abbr[title]:after{content:"" !important}
  .shra-poster .step .ic,.shra-poster .step .n{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  #wrapper{margin:0!important}
}
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
        <img class="logo" src="<?php echo shra_logo_url(); ?>" alt="">
        <h1><?php echo html_escape(get_option('shra_academy_name')); ?></h1>
        <div class="tag"><?php echo html_escape(get_option('shra_tagline')); ?></div>
        <div class="div"><i class="fa-solid fa-diamond"></i></div>
        <h2>Become a rider</h2>
        <p>Scan to fill the membership form on your phone.<br>Takes two minutes — your membership card is ready instantly.</p>
        <div class="qr"><?php echo $svg; ?></div>
        <div class="url"><?php echo html_escape($url); ?></div>
        <div class="steps">
            <div class="step"><div class="ic"><i class="fa-solid fa-qrcode"></i></div><span class="n">1</span><b>Scan &amp; register</b><small>Two minutes on your phone</small></div>
            <div class="step"><div class="ic"><i class="fa-solid fa-ticket"></i></div><span class="n">2</span><b>Pick a riding plan</b><small>At the reception desk</small></div>
            <div class="step"><div class="ic"><i class="fa-solid fa-horse"></i></div><span class="n">3</span><b>Ride!</b><small>First come, first served</small></div>
        </div>
        <div class="powered"><?php echo shra_powered_by(); ?></div>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
