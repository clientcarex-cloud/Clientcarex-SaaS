<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
/* Theme palettes — every filled surface below is drawn with inline SVG so the
   printed page matches the preview even when "print backgrounds" is off. */
.shra-poster[data-theme="classic"]{--bg:#f6efe0;--edge:#b8922e;--ink:#1c1a17;--muted:#7a6f5e;--accent:#b8922e;--disc:#1c1a17;--disc-fg:#f6efe0;--line:#e3d6b8;--badge-fg:#fff;--card-line:#e3d6b8}
.shra-poster[data-theme="midnight"]{--bg:#171512;--edge:#d4af37;--ink:#f3ecd9;--muted:#a89c7f;--accent:#d4af37;--disc:#d4af37;--disc-fg:#171512;--line:#3a352a;--badge-fg:#171512;--card-line:#d4af37}
.shra-poster[data-theme="emerald"]{--bg:#0f3d2e;--edge:#c9a24b;--ink:#f4efdf;--muted:#a9c4b3;--accent:#c9a24b;--disc:#c9a24b;--disc-fg:#0f3d2e;--line:#2c5a48;--badge-fg:#0f3d2e;--card-line:#c9a24b}
.shra-poster[data-theme="navy"]{--bg:#132743;--edge:#c8a951;--ink:#efe9d6;--muted:#93a5bf;--accent:#c8a951;--disc:#c8a951;--disc-fg:#132743;--line:#2b4569;--badge-fg:#132743;--card-line:#c8a951}
.shra-poster[data-theme="ivory"]{--bg:#ffffff;--edge:#141414;--ink:#141414;--muted:#6b6b6b;--accent:#141414;--disc:#141414;--disc-fg:#ffffff;--line:#e6e6e6;--badge-fg:#fff;--card-line:#141414}
.shra-poster[data-theme="rose"]{--bg:#faf1ec;--edge:#b76e79;--ink:#2b2024;--muted:#8d7378;--accent:#b76e79;--disc:#2b2024;--disc-fg:#faf1ec;--line:#ecd8d3;--badge-fg:#fff;--card-line:#ecd8d3}

.shra-poster{max-width:520px;margin:0 auto;border:2px solid var(--edge);border-radius:8px;padding:44px 36px;text-align:center;position:relative;color:var(--ink)}
.shra-poster>.bg{position:absolute;inset:0;width:100%;height:100%;z-index:0}
.shra-poster>.frame{position:absolute;inset:10px;border:1px solid var(--edge);border-radius:4px;opacity:.55;pointer-events:none;z-index:1}
.shra-poster>*:not(.bg):not(.frame){position:relative;z-index:2}
.shra-poster .logo{width:110px;height:110px;border-radius:50%;border:2px solid var(--accent);padding:4px}
.shra-poster h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:30px;margin:16px 0 2px;color:var(--ink);line-height:1.1}
.shra-poster .tag{font-family:'Cormorant Garamond',Georgia,serif;font-style:italic;color:var(--muted);font-size:15px}
.shra-poster .div{display:flex;align-items:center;gap:8px;justify-content:center;margin:18px 0}
.shra-poster .div:before,.shra-poster .div:after{content:'';width:70px;height:0;border-top:1px solid var(--accent)}
.shra-poster .div i{color:var(--accent);font-size:9px}
.shra-poster h2{font-family:'Cormorant Garamond',Georgia,serif;font-size:24px;font-weight:700;margin:0 0 6px;color:var(--ink)}
.shra-poster p{color:var(--ink);opacity:.85;font-size:13.5px;margin:0 0 16px;line-height:1.5}
.shra-poster .qr{width:248px;height:248px;margin:0 auto;position:relative;border:1px solid var(--card-line);border-radius:14px}
.shra-poster .qr>.card{position:absolute;inset:0;width:100%;height:100%}
.shra-poster .qr>.in{position:absolute;inset:14px}
.shra-poster .qr>.in svg{width:100%;height:100%;display:block}
.shra-poster .url{font-family:ui-monospace,Menlo,monospace;font-size:11px;color:var(--muted);margin-top:14px;word-break:break-all}
.shra-poster .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:22px;position:relative}
.shra-poster .steps:before{content:'';position:absolute;left:16%;right:16%;top:33px;border-top:2px dashed var(--accent);z-index:0}
.shra-poster .step{position:relative;z-index:1;text-align:center}
.shra-poster .step .ic{width:68px;height:68px;margin:0 auto 8px;position:relative}
.shra-poster .step .ic>svg{position:absolute;inset:0;width:100%;height:100%}
.shra-poster .step .ic>i{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--disc-fg)}
.shra-poster .step .n{position:absolute;top:-4px;left:calc(50% + 20px);width:22px;height:22px;display:flex;align-items:center;justify-content:center}
.shra-poster .step .n>svg{position:absolute;inset:0;width:100%;height:100%}
.shra-poster .step .n>em{position:relative;font-style:normal;font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:14px;color:var(--badge-fg)}
.shra-poster .step b{display:block;font-family:'Cormorant Garamond',Georgia,serif;font-size:17px;font-weight:700;color:var(--ink);line-height:1.15}
.shra-poster .step small{display:block;font-size:11px;color:var(--muted);margin-top:3px;line-height:1.35}
.shra-poster .powered{margin-top:24px;padding-top:16px;border-top:1px solid var(--line)}
.shra-poster .powered .shra-powered{color:var(--muted)}
.shra-poster .powered .shra-powered img{height:27px;width:auto;border-radius:0;box-shadow:none;image-rendering:-webkit-optimize-contrast}
.shra-poster[data-theme="midnight"] .powered .shra-powered img,
.shra-poster[data-theme="emerald"] .powered .shra-powered img,
.shra-poster[data-theme="navy"] .powered .shra-powered img{background:#fff;padding:2px 6px;border-radius:3px}

/* Theme picker */
.shra-qr-themes{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:0 auto 18px;max-width:520px}
.shra-qr-themes button{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:999px;border:1px solid #ddd4c2;background:#fff;font-size:12px;font-weight:600;color:#3a3530;cursor:pointer;transition:border-color .15s,box-shadow .15s}
.shra-qr-themes button:hover{border-color:#b8922e}
.shra-qr-themes button.active{border-color:#b8922e;box-shadow:0 0 0 1px #b8922e}
.shra-qr-themes .sw{display:inline-flex;width:26px;height:14px;border-radius:7px;overflow:hidden;border:1px solid rgba(0,0,0,.15)}
.shra-qr-themes .sw i{flex:1}

@media print{
  body *{visibility:hidden}
  .shra-poster,.shra-poster *{visibility:visible}
  .shra-poster{position:absolute;left:0;right:0;top:20px;margin:auto}
  .shra-poster,.shra-poster *{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important}
  .shra-poster a[href]:after,.shra-poster abbr[title]:after{content:"" !important}
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
    <div class="shra-qr-themes" id="shraQrThemes">
        <button type="button" data-theme="classic"><span class="sw"><i style="background:#f6efe0"></i><i style="background:#b8922e"></i></span>Heritage</button>
        <button type="button" data-theme="midnight"><span class="sw"><i style="background:#171512"></i><i style="background:#d4af37"></i></span>Midnight</button>
        <button type="button" data-theme="emerald"><span class="sw"><i style="background:#0f3d2e"></i><i style="background:#c9a24b"></i></span>Emerald</button>
        <button type="button" data-theme="navy"><span class="sw"><i style="background:#132743"></i><i style="background:#c8a951"></i></span>Royal Navy</button>
        <button type="button" data-theme="ivory"><span class="sw"><i style="background:#fff"></i><i style="background:#141414"></i></span>Ivory</button>
        <button type="button" data-theme="rose"><span class="sw"><i style="background:#faf1ec"></i><i style="background:#b76e79"></i></span>Rosé</button>
    </div>
    <div class="shra-poster" id="shraPoster" data-theme="classic">
        <svg class="bg" aria-hidden="true"><rect width="100%" height="100%" rx="6" fill="var(--bg)"/></svg>
        <div class="frame"></div>
        <img class="logo" src="<?php echo shra_logo_url(); ?>" alt="">
        <h1><?php echo html_escape(get_option('shra_academy_name')); ?></h1>
        <div class="tag"><?php echo html_escape(get_option('shra_tagline')); ?></div>
        <div class="div"><i class="fa-solid fa-diamond"></i></div>
        <h2>Become a rider</h2>
        <p>Scan to fill the membership form on your phone.<br>Takes two minutes — your membership card is ready instantly.</p>
        <div class="qr">
            <svg class="card" aria-hidden="true"><rect width="100%" height="100%" rx="13" fill="#fff"/></svg>
            <div class="in"><?php echo $svg; ?></div>
        </div>
        <div class="url"><?php echo html_escape($url); ?></div>
        <div class="steps">
            <?php
            $steps = [
                ['fa-qrcode', 'Scan &amp; register', 'Two minutes on your phone'],
                ['fa-ticket', 'Pick a riding plan', 'At the reception desk'],
                ['fa-horse', 'Ride!', 'First come, first served'],
            ];
            foreach ($steps as $i => $s) { ?>
            <div class="step">
                <div class="ic">
                    <svg viewBox="0 0 68 68" aria-hidden="true"><circle cx="34" cy="34" r="33" fill="none" stroke="var(--accent)" stroke-width="1.5"/><circle cx="34" cy="34" r="28" fill="var(--disc)"/></svg>
                    <i class="fa-solid <?php echo $s[0]; ?>"></i>
                </div>
                <span class="n"><svg viewBox="0 0 22 22" aria-hidden="true"><circle cx="11" cy="11" r="10" fill="var(--accent)"/></svg><em><?php echo $i + 1; ?></em></span>
                <b><?php echo $s[1]; ?></b><small><?php echo $s[2]; ?></small>
            </div>
            <?php } ?>
        </div>
        <div class="powered"><?php echo shra_powered_by(); ?></div>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<script>
(function () {
    var poster  = document.getElementById('shraPoster');
    var picker  = document.getElementById('shraQrThemes');
    var chips   = picker.querySelectorAll('button[data-theme]');
    var KEY     = 'shra_qr_theme';

    function apply(theme, save) {
        if (!picker.querySelector('button[data-theme="' + theme + '"]')) { theme = 'classic'; }
        poster.setAttribute('data-theme', theme);
        chips.forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-theme') === theme); });
        if (save) { try { localStorage.setItem(KEY, theme); } catch (e) {} }
    }

    chips.forEach(function (c) {
        c.addEventListener('click', function () { apply(c.getAttribute('data-theme'), true); });
    });

    var saved = null;
    try { saved = new URLSearchParams(location.search).get('theme') || localStorage.getItem(KEY); } catch (e) {}
    apply(saved || 'classic', false);
})();
</script>
<?php init_tail(); ?>
</body>
</html>
