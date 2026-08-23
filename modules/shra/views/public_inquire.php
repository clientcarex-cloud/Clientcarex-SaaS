<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Ad landing page — /inquire (Meta Ads + Google Ads traffic).
 * Mobile-first, one goal: a call-back request (form), a call or a WhatsApp tap.
 */
$academy  = get_option('shra_academy_name') ?: 'Stallion Horse Riding Academy';
$tagline  = get_option('shra_tagline');
$v        = function ($k, $d = '') use ($old) { return html_escape(isset($old[$k]) ? $old[$k] : $d); };
$has_err  = count($errors) > 0;
$kids     = array_values(array_filter($plans, function ($p) { return $p['audience'] === 'children'; }));
$adults   = array_values(array_filter($plans, function ($p) { return $p['audience'] === 'adults'; }));
$guest    = function ($list) { foreach ($list as $p) { if ($p['is_guest']) { return $p; } } return null; };
$kid_try  = $guest($kids);
$adult_try = $guest($adults);
$from_raw = null;
foreach ($plans as $p) { if (!$p['is_guest'] && $p['sessions'] > 0) { $ps = $p['total_raw'] / $p['sessions']; if ($from_raw === null || $ps < $from_raw) { $from_raw = $ps; } } }
$from     = $from_raw !== null ? shra_money($from_raw) : '';
$more_reels = array_slice($landing['reels'], 0, 6);
$phone_href = $landing['phone_digits'] !== '' ? 'tel:+' . preg_replace('/\D+/', '', get_option('shra_lead_phone_country')) . ltrim(shra_phone_norm($landing['phone']), '0') : '';
$faq = [
    ['Is horse riding safe for children?', 'Yes. Every lesson is one-on-one with a trained instructor who stays with the rider throughout. We start on calm, well-schooled horses at a walk and only progress when the rider is confident. Children from ' . (int) $landing['min_age'] . ' years can join.'],
    ['I have never ridden before — can I join?', 'Absolutely. Most of our riders start with zero experience. Your first session covers mounting, balance and control at a walk. Book a Guest Ride to try it before choosing a package.'],
    ['When are the lessons?', 'Weekend batches (Saturday & Sunday, morning and evening) are the most popular. Weekday slots are available on request — tell us what suits you and we will match a time.'],
    ['What should I wear?', 'Full-length trousers (jeans or track pants) and closed shoes — no sandals. Ask our team about safety gear when you book.'],
    ['Do I get a certificate?', 'Yes — riders who complete a package receive a certificate from ' . $academy . ' with a QR code for verification.'],
    ['Where is the academy?', ($landing['location'] ?: 'Hyderabad') . '. Book a visit and we will send you the exact location on WhatsApp.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($title); ?></title>
<meta name="description" content="Professional horse riding lessons for kids and adults in Hyderabad. Book a free visit to <?php echo html_escape($academy); ?>.">
<link rel="icon" href="<?php echo shra_logo_url(); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/_landing_track.php'; ?>
<style>
:root{--cream:#f6efe0;--cream-2:#fbf7ee;--sand:#e9d9b6;--ink:#1c1a17;--ink-2:#3a3530;--gold:#b8922e;--gold-2:#d4b45c;--brown:#5a3e22;--muted:#7a6f5e;--line:#e3d6b8;--red:#a8322d;--green:#2f4a1f;--wa:#25d366}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;scroll-padding-top:70px}
body{font-family:'Inter',system-ui,sans-serif;background:var(--cream);color:var(--ink);-webkit-font-smoothing:antialiased;line-height:1.5;padding-bottom:84px}
img{max-width:100%;display:block}
a{color:inherit}
.serif{font-family:'Cormorant Garamond',Georgia,serif}
.wrap{max-width:1080px;margin:0 auto;padding:0 18px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:15px 22px;border:0;border-radius:14px;font:inherit;font-size:16px;font-weight:700;cursor:pointer;text-decoration:none;transition:.15s;white-space:nowrap}
.btn:hover{transform:translateY(-1px);filter:brightness(1.05)}
.btn-gold{background:linear-gradient(135deg,var(--gold),var(--gold-2));color:#fff;box-shadow:0 10px 24px rgba(184,146,46,.35)}
.btn-wa{background:var(--wa);color:#fff;box-shadow:0 10px 24px rgba(37,211,102,.3)}
.btn-dark{background:var(--ink);color:var(--cream)}
.btn-ghost{background:#fff;color:var(--ink);border:1.5px solid var(--line)}
.btn-block{width:100%}
/* top bar */
.top{position:sticky;top:0;z-index:40;background:rgba(246,239,224,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.top .wrap{display:flex;align-items:center;gap:12px;height:62px}
.top img{width:40px;height:40px;border-radius:50%;box-shadow:0 0 0 2px var(--sand)}
.top .nm{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:19px;line-height:1.1}
.top .nm small{display:block;font-family:'Inter',sans-serif;font-weight:500;font-size:11px;color:var(--muted);letter-spacing:.3px}
.top .sp{flex:1}
.top .call{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:14px;text-decoration:none;background:var(--ink);color:var(--cream);padding:10px 16px;border-radius:999px}
.top .call i{color:var(--gold-2)}
/* hero */
.hero{position:relative;overflow:hidden;background:radial-gradient(120% 90% at 10% 0%,#fbf5e6 0%,var(--cream) 50%,#efe3c6 100%)}
.hero:before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'%3E%3Cpath d='M60 10l4 8 8-4-4 8 8 4-8 4 4 8-8-4-4 8-4-8-8 4 4-8-8-4 8-4-4-8 8 4z' fill='%23b8922e' fill-opacity='.06'/%3E%3C/svg%3E");pointer-events:none}
.hero .wrap{position:relative;display:grid;grid-template-columns:1.1fr .9fr;gap:40px;padding:46px 18px 40px;align-items:start}
.eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:var(--gold);margin-bottom:14px}
.eyebrow:before{content:'';width:28px;height:1px;background:var(--gold)}
h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:clamp(36px,5.2vw,58px);line-height:1.02;letter-spacing:-.5px}
h1 em{font-style:italic;color:var(--brown)}
.lead{font-size:17px;color:var(--ink-2);margin-top:16px;max-width:520px}
.offer{display:inline-flex;align-items:center;gap:10px;margin-top:18px;background:#fff;border:1px dashed var(--red);color:var(--red);border-radius:12px;padding:9px 12px 9px 9px;font-size:13.5px;font-weight:600}
.offer b{background:var(--red);color:#fff;border-radius:7px;padding:3px 9px;font-size:13px}
.cta{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}
.trust{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:24px;font-size:13.5px;color:var(--ink-2);font-weight:500}
.trust span{display:inline-flex;align-items:center;gap:7px}
.trust i{color:var(--gold);font-size:13px}
.proof{display:flex;align-items:center;gap:12px;margin-top:22px;padding-top:18px;border-top:1px solid var(--line);font-size:13.5px;color:var(--muted)}
.proof .av{display:flex}
.proof .av span{width:30px;height:30px;border-radius:50%;border:2px solid var(--cream);margin-left:-8px;background:linear-gradient(135deg,var(--gold-2),var(--brown));display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700}
.proof .av span:first-child{margin-left:0}
.proof b{color:var(--ink)}
/* form card */
.card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 28px 70px rgba(90,62,34,.16);overflow:hidden}
.card-head{padding:20px 24px 16px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#fff,var(--cream-2));display:flex;align-items:center;gap:14px}
.card-head .ic{width:44px;height:44px;border-radius:12px;background:var(--ink);color:var(--gold-2);display:inline-flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.card-head h2{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:24px;line-height:1.1}
.card-head p{color:var(--muted);font-size:13px;margin-top:2px}
.card-body{padding:20px 24px 22px}
.f{margin-bottom:12px}
.f label{display:block;font-size:12.5px;font-weight:600;color:var(--ink-2);margin-bottom:5px}
.f .req{color:var(--red)}
.f input,.f select,.f textarea{width:100%;border:1.5px solid var(--line);border-radius:12px;padding:13px 14px;font:inherit;font-size:16px;color:var(--ink);background:#fff;outline:none;transition:.15s;-webkit-appearance:none}
.f input:focus,.f select:focus,.f textarea:focus{border-color:var(--gold);box-shadow:0 0 0 4px rgba(184,146,46,.14)}
.f select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a6f5e' stroke-width='1.6' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.chips{display:flex;gap:8px}
.chips label{flex:1;text-align:center;border:1.5px solid var(--line);border-radius:12px;padding:11px 6px;font-size:13.5px;font-weight:600;cursor:pointer;background:#fff;color:var(--ink-2)}
.chips input{display:none}
.chips label:has(input:checked){background:var(--ink);border-color:var(--ink);color:var(--cream)}
.err{background:#fbeeee;border:1px solid #e8b9b6;color:var(--red);border-radius:12px;padding:10px 14px;margin-bottom:14px;font-size:13.5px}
.note-t{background:none;border:0;color:var(--brown);font:inherit;font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:12px}
.fine{font-size:12px;color:var(--muted);text-align:center;margin-top:12px;line-height:1.5}
.fine i{color:var(--green)}
.or{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:12px;margin:14px 0 10px}
.or:before,.or:after{content:'';flex:1;height:1px;background:var(--line)}
/* sections */
section{padding:52px 0}
.sec-h{text-align:center;max-width:640px;margin:0 auto 30px}
.sec-h .eyebrow:before{display:none}
.sec-h h2{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:clamp(30px,4vw,42px);line-height:1.05}
.sec-h p{color:var(--muted);margin-top:10px;font-size:15.5px}
/* reels */
.reels-sec{background:var(--ink);color:var(--cream);padding:44px 0 48px}
.reels-sec .sec-h h2{color:#fff}
.reels-sec .sec-h p{color:#b9ad97}
.reels{display:flex;gap:14px;overflow-x:auto;scroll-snap-type:x mandatory;padding:4px 18px 14px;margin:0 -18px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.reels::-webkit-scrollbar{display:none}
.reel{flex:0 0 250px;scroll-snap-align:start;aspect-ratio:9/16;border-radius:18px;overflow:hidden;background:#2a2621;position:relative;border:1px solid rgba(255,255,255,.08)}
.reel iframe{position:absolute;inset:0;width:100%;height:100%;border:0;background:#000}
.reel .ph{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;cursor:pointer;background:radial-gradient(80% 60% at 50% 35%,#4a3a24 0%,#2a2621 70%);color:#fff;text-align:center;padding:20px}
.reel .ph .pl{width:62px;height:62px;border-radius:50%;background:var(--gold);display:inline-flex;align-items:center;justify-content:center;font-size:22px;color:#fff;box-shadow:0 10px 30px rgba(184,146,46,.5);transition:.15s}
.reel .ph:hover .pl{transform:scale(1.06)}
.reel .ph b{font-size:14px}
.reel .ph small{font-size:12px;color:#b9ad97}
.ig-link{text-align:center;margin-top:8px}
.ig-link a{color:#fff;text-decoration:none;font-weight:600;font-size:14px;display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(255,255,255,.25);padding:10px 18px;border-radius:999px}
.ig-link a:hover{background:rgba(255,255,255,.08)}
/* benefits */
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.bn{background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px 20px}
.bn i{width:44px;height:44px;border-radius:12px;background:var(--cream);color:var(--gold);display:inline-flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:14px}
.bn h3{font-size:16px;font-weight:700;margin-bottom:6px}
.bn p{font-size:13.5px;color:var(--muted)}
/* pricing */
.pricing{background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.tabs{display:inline-flex;background:var(--cream);border:1px solid var(--line);border-radius:999px;padding:4px;margin:0 auto 22px}
.tabs button{border:0;background:none;font:inherit;font-weight:700;font-size:14px;padding:10px 22px;border-radius:999px;cursor:pointer;color:var(--muted)}
.tabs button.on{background:var(--ink);color:var(--cream)}
.try{display:flex;align-items:center;gap:14px;background:var(--cream-2);border:1px dashed var(--gold-2);border-radius:16px;padding:14px 18px;margin-bottom:16px}
.try i{color:var(--gold);font-size:20px}
.try b{display:block;font-size:15px}
.try small{color:var(--muted);font-size:13px}
.try .pr{margin-left:auto;text-align:right;white-space:nowrap}
.try .pr b{font-size:20px;font-family:'Cormorant Garamond',Georgia,serif}
.try .pr a{font-size:12.5px;color:var(--brown);font-weight:600}
.plans{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.plan{position:relative;background:var(--cream-2);border:1.5px solid var(--line);border-radius:18px;padding:20px 18px;display:flex;flex-direction:column}
.plan.hot{border-color:var(--gold);background:#fff;box-shadow:0 16px 40px rgba(184,146,46,.18)}
.plan .bd{position:absolute;top:-11px;left:16px;background:var(--gold);color:#fff;font-size:10.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:4px 10px;border-radius:999px}
.plan h3{font-size:17px;font-weight:700}
.plan .ss{font-size:12.5px;color:var(--muted);margin-top:2px}
.plan .pr{margin:14px 0 4px;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.plan .now{font-family:'Cormorant Garamond',Georgia,serif;font-size:30px;font-weight:700;line-height:1}
.plan .was{font-size:13px;color:var(--muted);text-decoration:line-through}
.plan .per{font-size:13px;color:var(--ink-2);margin-bottom:14px}
.plan .per b{color:var(--green)}
.plan .btn{margin-top:auto;padding:12px 14px;font-size:14px}
.tabpane{display:none}.tabpane.on{display:block}
/* steps */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;counter-reset:s}
.st{background:#fff;border:1px solid var(--line);border-radius:18px;padding:24px 20px;position:relative}
.st:before{counter-increment:s;content:counter(s);position:absolute;top:-14px;left:18px;width:30px;height:30px;border-radius:50%;background:var(--ink);color:var(--gold-2);font-weight:800;font-size:14px;display:inline-flex;align-items:center;justify-content:center}
.st h3{font-size:16px;font-weight:700;margin-bottom:6px}
.st p{font-size:13.5px;color:var(--muted)}
/* faq */
.faq{max-width:720px;margin:0 auto}
.faq details{background:#fff;border:1px solid var(--line);border-radius:14px;margin-bottom:10px}
.faq summary{cursor:pointer;list-style:none;padding:16px 18px;font-weight:600;font-size:15px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.faq summary::-webkit-details-marker{display:none}
.faq summary:after{content:'+';font-size:22px;color:var(--gold);font-weight:400;line-height:1}
.faq details[open] summary:after{content:'–'}
.faq details p{padding:0 18px 16px;font-size:14px;color:var(--ink-2)}
/* final cta */
.final{background:var(--ink);color:var(--cream);text-align:center;padding:56px 0}
.final h2{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:clamp(30px,4vw,44px);line-height:1.05;color:#fff}
.final p{color:#b9ad97;margin:12px auto 24px;max-width:520px;font-size:15.5px}
.final .cta{justify-content:center}
/* footer */
.foot{padding:26px 0 30px;text-align:center;font-size:12.5px;color:var(--muted)}
.foot .loc{font-weight:600;color:var(--ink-2);margin-bottom:4px}
.foot a{color:var(--brown)}
.shra-powered{display:inline-flex;align-items:center;gap:6px;text-decoration:none;opacity:.85;margin-top:12px}.shra-powered span{font-size:11px;color:#7e8c9d;font-weight:500}.shra-powered img{height:18px;width:auto}
/* sticky mobile bar */
.bar{position:fixed;left:0;right:0;bottom:0;z-index:50;display:none;gap:8px;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:rgba(255,255,255,.96);backdrop-filter:blur(10px);border-top:1px solid var(--line);box-shadow:0 -10px 30px rgba(90,62,34,.12)}
.bar .btn{flex:1;padding:13px 10px;font-size:14px;border-radius:12px}
.bar .btn.sq{flex:0 0 52px;padding:0;font-size:20px}
@media(max-width:900px){
  .hero .wrap{grid-template-columns:1fr;gap:26px;padding:30px 18px 34px}
  .grid{grid-template-columns:1fr 1fr}
  .plans{grid-template-columns:1fr 1fr}
  .steps{grid-template-columns:1fr}
  .bar{display:flex}
  .top .call span{display:none}
  .top .call{padding:10px 12px}
  .top .nm{font-size:16px}
}
@media(max-width:520px){
  .grid{grid-template-columns:1fr}
  .plans{grid-template-columns:1fr}
  .row{grid-template-columns:1fr}
  .cta .btn{flex:1}
  .card-body{padding:18px 18px 20px}
  .try{flex-wrap:wrap}.try .pr{margin-left:0;text-align:left;width:100%}
  .reel{flex-basis:78vw}
  .top .nm small{display:none}
  .top .wrap{height:56px}
  body{padding-bottom:90px}
}
@media(min-width:901px){body{padding-bottom:0}}
</style>
</head>
<body>

<header class="top">
    <div class="wrap">
        <img src="<?php echo shra_logo_url(); ?>" alt="">
        <div class="nm"><?php echo html_escape($academy); ?><?php if ($landing['location']) { ?><small><i class="fa-solid fa-location-dot"></i> <?php echo html_escape($landing['location']); ?></small><?php } ?></div>
        <div class="sp"></div>
        <?php if ($phone_href) { ?><a class="call" href="<?php echo $phone_href; ?>" onclick="shraTrack('Contact',{method:'call'})"><i class="fa-solid fa-phone"></i> <span>Call <?php echo html_escape($landing['phone']); ?></span></a><?php } ?>
    </div>
</header>

<section class="hero">
    <div class="wrap">
        <div>
            <div class="eyebrow">Horse riding lessons · Hyderabad</div>
            <h1>Learn to ride a horse — <em>with confidence.</em></h1>
            <p class="lead">Professional one-on-one riding lessons for children (<?php echo (int) $landing['min_age']; ?>+) and adults, on calm, well-schooled horses. Zero experience needed. Weekend batches available.</p>
            <?php if ($offer['active']) { ?><div class="offer"><b><?php echo $offer['percent'] + 0; ?>% OFF</b> <?php echo html_escape($offer['label'] ?: 'Limited-time offer on all packages'); ?><?php if ($offer['ends']) { ?> · ends <?php echo date('j M', strtotime($offer['ends'])); ?><?php } ?></div><?php } ?>
            <div class="cta">
                <a class="btn btn-gold" href="#form"><i class="fa-solid fa-calendar-check"></i> Book a free visit</a>
                <?php if ($landing['wa_link']) { ?><a class="btn btn-wa" href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" onclick="shraTrack('Contact',{method:'whatsapp'})"><i class="fa-brands fa-whatsapp"></i> WhatsApp us</a><?php } ?>
            </div>
            <div class="trust">
                <span><i class="fa-solid fa-shield-heart"></i> Safety-first, 1-on-1 lessons</span>
                <span><i class="fa-solid fa-child-reaching"></i> All ages · all levels</span>
                <span><i class="fa-solid fa-certificate"></i> Certificate on completion</span>
                <span><i class="fa-solid fa-calendar-days"></i> Sat &amp; Sun batches</span>
            </div>
            <div class="proof">
                <div class="av"><span>A</span><span>R</span><span>S</span><span>+</span></div>
                <div>Join <b>7,000+ followers</b> watching our riders grow <?php if ($landing['instagram']) { ?>· <a href="<?php echo html_escape($landing['instagram']); ?>" target="_blank" rel="noopener" style="color:var(--brown);font-weight:600">@stallionhorseriding</a><?php } ?></div>
            </div>
        </div>

        <div class="card" id="form">
            <div class="card-head">
                <div class="ic"><i class="fa-solid fa-horse-head"></i></div>
                <div><h2>Get a call back</h2><p>Packages, timings &amp; a free visit — we call you the same day.</p></div>
            </div>
            <div class="card-body">
                <?php if ($has_err) { ?><div class="err"><?php foreach ($errors as $e) { echo '<div>' . html_escape($e) . '</div>'; } ?></div><?php } ?>
                <form method="post" autocomplete="on" id="leadform" action="<?php echo site_url('inquire'); ?>#form">
                    <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                    <input type="hidden" name="ts" value="<?php echo $ts; ?>"><input type="hidden" name="sig" value="<?php echo $sig; ?>">
                    <?php foreach (['c', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'] as $k) { ?><input type="hidden" name="<?php echo $k; ?>" value="<?php echo html_escape($track[$k]); ?>"><?php } ?>
                    <div style="position:absolute;left:-5000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="f"><label>Your name <span class="req">*</span></label><input type="text" name="name" value="<?php echo $v('name'); ?>" required autocomplete="name" placeholder="Full name"></div>
                    <div class="f"><label>Mobile number <span class="req">*</span></label><input type="tel" name="phone" value="<?php echo $v('phone'); ?>" required inputmode="tel" autocomplete="tel" placeholder="10-digit mobile number"></div>
                    <div class="f"><label>Who will ride?</label><div class="chips">
                        <label><input type="radio" name="rider_for" value="child" <?php echo $v('rider_for', 'child') === 'child' ? 'checked' : ''; ?>><span>My child</span></label>
                        <label><input type="radio" name="rider_for" value="self" <?php echo $v('rider_for') === 'self' ? 'checked' : ''; ?>><span>Myself</span></label>
                        <label><input type="radio" name="rider_for" value="both" <?php echo $v('rider_for') === 'both' ? 'checked' : ''; ?>><span>Both</span></label>
                    </div></div>
                    <div class="row">
                        <div class="f"><label>Rider's age</label><input type="number" name="rider_age" value="<?php echo $v('rider_age'); ?>" min="2" max="90" inputmode="numeric" placeholder="e.g. 8"></div>
                        <div class="f"><label>Interested in</label><select name="package_id" id="pkgsel"><option value="">Not sure yet</option><?php foreach ($plans as $p) { ?><option value="<?php echo $p['id']; ?>" <?php echo (string) $v('package_id') === (string) $p['id'] ? 'selected' : ''; ?>><?php echo ($p['audience'] === 'children' ? 'Kids' : 'Adults') . ' · ' . html_escape($p['name']) . ($p['is_guest'] ? '' : ' · ' . $p['sessions'] . ' sessions'); ?></option><?php } ?></select></div>
                    </div>
                    <button type="button" class="note-t" id="notet" <?php echo $v('message') !== '' ? 'hidden' : ''; ?>><i class="fa-solid fa-plus"></i> Add a note (optional)</button>
                    <div class="f" id="notef" <?php echo $v('message') === '' ? 'hidden' : ''; ?>><label>Anything we should know?</label><textarea name="message" rows="2" placeholder="Preferred days, previous experience, questions…"><?php echo $v('message'); ?></textarea></div>
                    <input type="hidden" name="city" value="<?php echo $v('city'); ?>">
                    <button class="btn btn-gold btn-block" type="submit" id="subbtn"><i class="fa-solid fa-phone-volume"></i> Request a call back</button>
                    <div class="fine"><i class="fa-solid fa-lock"></i> No spam, no obligation. We only call to help you choose a package and book a visit.</div>
                    <?php if ($landing['wa_link']) { ?>
                    <div class="or">or</div>
                    <a class="btn btn-ghost btn-block" href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" onclick="shraTrack('Contact',{method:'whatsapp'})" style="color:#128c7e"><i class="fa-brands fa-whatsapp" style="font-size:20px"></i> Chat on WhatsApp</a>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if (count($more_reels)) { ?>
<section class="reels-sec">
    <div class="wrap">
        <div class="sec-h">
            <div class="eyebrow">Straight from the arena</div>
            <h2>See our riders in action</h2>
            <p>Real lessons, real riders — from first-timers to confident canters.</p>
        </div>
        <div class="reels">
            <?php foreach ($more_reels as $i => $rid) { ?>
            <div class="reel" data-reel="<?php echo html_escape($rid); ?>">
                <div class="ph" role="button" tabindex="0" aria-label="Play reel">
                    <span class="pl"><i class="fa-solid fa-play"></i></span>
                    <b>Watch reel</b>
                    <small><i class="fa-brands fa-instagram"></i> @stallionhorseriding</small>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php if ($landing['instagram']) { ?><div class="ig-link"><a href="<?php echo html_escape($landing['instagram']); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> More on Instagram</a></div><?php } ?>
    </div>
</section>
<?php } ?>

<section>
    <div class="wrap">
        <div class="sec-h">
            <div class="eyebrow">Why families choose us</div>
            <h2>More than a lesson — a skill for life</h2>
            <p>Horse riding builds balance, posture, focus and confidence. Our riders leave every session standing a little taller.</p>
        </div>
        <div class="grid">
            <div class="bn"><i class="fa-solid fa-user-shield"></i><h3>One-on-one instruction</h3><p>An instructor stays with each rider throughout the session — no crowded group classes.</p></div>
            <div class="bn"><i class="fa-solid fa-horse"></i><h3>Calm, trained horses</h3><p>Beginners start on our gentlest, best-schooled horses and progress at their own pace.</p></div>
            <div class="bn"><i class="fa-solid fa-chart-line"></i><h3>Structured progress</h3><p>Every session is logged. Riders move through levels and earn a certificate on completion.</p></div>
            <div class="bn"><i class="fa-solid fa-tree"></i><h3>Open-air, away from the city</h3><p><?php echo html_escape($landing['location'] ?: 'A green, open-air arena on the edge of Hyderabad'); ?> — fresh air, open space, zero screens.</p></div>
        </div>
    </div>
</section>

<?php if (count($plans)) { ?>
<section class="pricing" id="pricing">
    <div class="wrap">
        <div class="sec-h">
            <div class="eyebrow">Simple pricing</div>
            <h2>Packages<?php if ($from) { ?> from <?php echo $from; ?> / session<?php } ?></h2>
            <p>Try a single Guest Ride first, or save more with a longer package.<?php if ($offer['active']) { ?> <b style="color:var(--red)">Prices below include the <?php echo $offer['percent'] + 0; ?>% offer.</b><?php } ?></p>
            <?php if (count($kids) && count($adults)) { ?><div class="tabs"><button type="button" class="on" data-tab="kids">Kids</button><button type="button" data-tab="adults">Adults</button></div><?php } ?>
        </div>
        <?php foreach (['kids' => [$kids, $kid_try], 'adults' => [$adults, $adult_try]] as $key => $set) { list($list, $try) = $set; if (!count($list)) { continue; } ?>
        <div class="tabpane <?php echo $key === 'kids' || !count($kids) ? 'on' : ''; ?>" data-pane="<?php echo $key; ?>">
            <?php if ($try) { ?><div class="try"><i class="fa-solid fa-star"></i><div><b>Try it first — <?php echo html_escape($try['name']); ?></b><small>One <?php echo $try['duration_min']; ?>-minute session with an instructor. Perfect for a first visit.</small></div><div class="pr"><b><?php echo $try['total']; ?></b><?php if ($try['discount'] > 0) { ?> <span style="font-size:12px;color:var(--muted);text-decoration:line-through"><?php echo $try['price']; ?></span><?php } ?><br><a href="#form" data-pick="<?php echo $try['id']; ?>">Book a guest ride →</a></div></div><?php } ?>
            <div class="plans">
                <?php foreach ($list as $p) { if ($p['is_guest']) { continue; } ?>
                <div class="plan <?php echo $p['is_featured'] ? 'hot' : ''; ?>">
                    <?php if ($p['is_featured']) { ?><span class="bd">Best value</span><?php } ?>
                    <h3><?php echo html_escape($p['name']); ?></h3>
                    <div class="ss"><?php echo $p['sessions']; ?> sessions · <?php echo $p['duration_min']; ?> min each</div>
                    <div class="pr"><span class="now"><?php echo $p['total']; ?></span><?php if ($p['discount'] > 0) { ?><span class="was"><?php echo $p['price']; ?></span><?php } ?></div>
                    <div class="per"><b><?php echo $p['per_session_now']; ?></b> per session</div>
                    <a class="btn <?php echo $p['is_featured'] ? 'btn-gold' : 'btn-ghost'; ?>" href="#form" data-pick="<?php echo $p['id']; ?>">Enquire <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
</section>
<?php } ?>

<section>
    <div class="wrap">
        <div class="sec-h">
            <div class="eyebrow">How it works</div>
            <h2>From your first call to your first ride</h2>
        </div>
        <div class="steps">
            <div class="st"><h3>Request a call back</h3><p>Fill the 30-second form or WhatsApp us. Our team calls you the same day with packages and timings.</p></div>
            <div class="st"><h3>Visit the academy</h3><p>Come see the horses, meet the instructors and take a Guest Ride — usually on a Saturday or Sunday morning.</p></div>
            <div class="st"><h3>Pick a package &amp; start</h3><p>Choose the plan that fits, get your membership card and start your sessions. Certificate on completion.</p></div>
        </div>
    </div>
</section>

<section style="padding-top:0">
    <div class="wrap">
        <div class="sec-h"><div class="eyebrow">Good to know</div><h2>Questions parents ask us</h2></div>
        <div class="faq">
            <?php foreach ($faq as $i => $q) { ?><details <?php echo $i === 0 ? 'open' : ''; ?>><summary><?php echo html_escape($q[0]); ?></summary><p><?php echo html_escape($q[1]); ?></p></details><?php } ?>
        </div>
    </div>
</section>

<section class="final">
    <div class="wrap">
        <h2>Ready to see your rider smile?</h2>
        <p>Book a free visit<?php if ($offer['active']) { ?> and lock in the <?php echo $offer['percent'] + 0; ?>% offer before it ends<?php } ?>. Limited weekend slots each month.</p>
        <div class="cta">
            <a class="btn btn-gold" href="#form"><i class="fa-solid fa-calendar-check"></i> Book a free visit</a>
            <?php if ($phone_href) { ?><a class="btn btn-ghost" href="<?php echo $phone_href; ?>" onclick="shraTrack('Contact',{method:'call'})"><i class="fa-solid fa-phone"></i> Call <?php echo html_escape($landing['phone']); ?></a><?php } ?>
        </div>
    </div>
</section>

<footer class="foot">
    <div class="wrap">
        <div class="loc"><?php echo html_escape($academy); ?><?php if ($tagline) { ?> · <i><?php echo html_escape($tagline); ?></i><?php } ?></div>
        <?php if ($landing['location']) { ?><div><i class="fa-solid fa-location-dot"></i> <?php echo $landing['maps_url'] ? '<a href="' . html_escape($landing['maps_url']) . '" target="_blank" rel="noopener">' . html_escape($landing['location']) . '</a>' : html_escape($landing['location']); ?></div><?php } ?>
        <?php if (get_option('shra_contact_line')) { ?><div style="margin-top:4px"><?php echo html_escape(get_option('shra_contact_line')); ?></div><?php } ?>
        <div><?php echo shra_powered_by(); ?></div>
    </div>
</footer>

<div class="bar">
    <?php if ($phone_href) { ?><a class="btn btn-dark sq" href="<?php echo $phone_href; ?>" aria-label="Call" onclick="shraTrack('Contact',{method:'call'})"><i class="fa-solid fa-phone"></i></a><?php } ?>
    <?php if ($landing['wa_link']) { ?><a class="btn btn-wa" href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" onclick="shraTrack('Contact',{method:'whatsapp'})"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><?php } ?>
    <a class="btn btn-gold" href="#form"><i class="fa-solid fa-calendar-check"></i> Book a visit</a>
</div>

<script>
(function(){
    // Note toggle
    var nt=document.getElementById('notet'),nf=document.getElementById('notef');
    if(nt){nt.addEventListener('click',function(){nt.hidden=true;nf.hidden=false;nf.querySelector('textarea').focus();});}
    // Pricing tabs
    document.querySelectorAll('.tabs button').forEach(function(b){b.addEventListener('click',function(){
        document.querySelectorAll('.tabs button').forEach(function(x){x.classList.toggle('on',x===b);});
        document.querySelectorAll('.tabpane').forEach(function(p){p.classList.toggle('on',p.dataset.pane===b.dataset.tab);});
    });});
    // "Enquire" buttons pre-select the package in the form
    var sel=document.getElementById('pkgsel');
    document.querySelectorAll('[data-pick]').forEach(function(a){a.addEventListener('click',function(){
        if(sel){sel.value=a.dataset.pick;}
        var r=document.querySelector('input[name=rider_for][value='+(a.closest('[data-pane]')&&a.closest('[data-pane]').dataset.pane==='adults'?'self':'child')+']');
        if(r){r.checked=true;}
        shraTrack('ViewContent',{content_name:'package_'+a.dataset.pick});
    });});
    // Instagram reels — lazy, click-to-load (keeps the page light for ad traffic)
    function loadReel(el){
        if(el.dataset.loaded){return;}el.dataset.loaded='1';
        var f=document.createElement('iframe');
        f.src='https://www.instagram.com/reel/'+el.dataset.reel+'/embed/';
        f.setAttribute('allow','autoplay; encrypted-media');f.setAttribute('allowfullscreen','');f.loading='lazy';
        el.appendChild(f);
        var ph=el.querySelector('.ph');if(ph){ph.style.opacity='0';setTimeout(function(){ph.remove();},800);}
        shraTrack('ViewContent',{content_name:'reel_'+el.dataset.reel});
    }
    document.querySelectorAll('.reel .ph').forEach(function(ph){
        ph.addEventListener('click',function(){loadReel(ph.parentNode);});
        ph.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();loadReel(ph.parentNode);}});
    });
    // Auto-load the first reel when the strip scrolls into view
    var first=document.querySelector('.reel');
    if(first&&'IntersectionObserver' in window){
        new IntersectionObserver(function(en,io){en.forEach(function(e){if(e.isIntersecting){loadReel(first);io.disconnect();}});},{rootMargin:'200px'}).observe(first);
    }
    // Submit state + tracking
    var form=document.getElementById('leadform'),btn=document.getElementById('subbtn');
    if(form){form.addEventListener('submit',function(){btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Sending…';shraTrack('InitiateCheckout',{content_name:'inquire_form'});});}
    // Scroll back to the form after a validation error
    <?php if ($has_err) { ?>setTimeout(function(){document.getElementById('form').scrollIntoView({block:'start'});},50);<?php } ?>
})();
</script>
</body>
</html>
