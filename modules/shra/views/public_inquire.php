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
$batches  = shra_batches();
$batch_line = implode(' · ', array_map(function ($b) { return $b['label'] . ' ' . $b['time']; }, $batches));
$live     = !empty($landing['latest_reels']);
$reels    = $live ? array_slice($landing['latest_reels'], 0, 8) : array_map(function ($id) { return ['id' => $id, 'thumb' => '', 'views' => 0, 'likes' => 0, 'taken' => 0, 'caption' => '']; }, array_slice($landing['reels'], 0, 6));
$fmt      = function ($n) { return $n >= 1000000 ? round($n / 1000000, 1) . 'M' : ($n >= 1000 ? round($n / 1000, $n >= 10000 ? 0 : 1) . 'K' : (string) $n); };
$ago      = function ($t) { $d = max(0, time() - $t); if ($d < 86400) { return 'today'; } if ($d < 604800) { return floor($d / 86400) . 'd ago'; } if ($d < 2592000) { return floor($d / 604800) . 'w ago'; } return date('M Y', $t); };
$faq = [
    ['Is horse riding safe for children?', 'Yes. Every lesson is one-on-one with a trained instructor who stays with the rider throughout. We start on calm, well-schooled horses at a walk and only progress when the rider is confident. Children from ' . (int) $landing['min_age'] . ' years can join.'],
    ['I have never ridden before — can I join?', 'Absolutely. Most of our riders start with zero experience. Your first session covers mounting, balance and control at a walk. Book a Guest Ride to try it before choosing a package.'],
    ['When are the lessons?', 'There are two batches every day — ' . $batch_line . '. Pick the one that suits you on the booking form and tell us the date you want to start. Seats in each batch go first come, first served.'],
    ['What should I wear?', 'Full-length trousers (jeans or track pants) and closed shoes — no sandals. Ask our team about safety gear when you book.'],
    ['Do I get a certificate?', 'Yes — riders who complete a package receive a certificate from ' . $academy . ' with a QR code for verification.'],
    ['Where is the academy?', ($landing['location'] ?: 'Hyderabad') . '. The exact location and directions are on the map below.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($title); ?></title>
<meta name="description" content="Professional horse riding lessons for kids and adults in Hyderabad. <?php echo $can_pay ? 'Book and pay for your session online at ' . html_escape($academy) . '.' : 'Book a free visit to ' . html_escape($academy) . '.'; ?>">
<link rel="icon" href="<?php echo shra_logo_url(); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/_landing_track.php'; ?>
<style>
:root{--cream:#f6efe0;--cream-2:#fbf7ee;--sand:#e9d9b6;--ink:#1c1a17;--ink-2:#3a3530;--gold:#b8922e;--gold-2:#d4b45c;--brown:#5a3e22;--muted:#7a6f5e;--line:#e3d6b8;--red:#a8322d;--green:#2f4a1f}
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
.hero{position:relative;overflow:hidden;background:#161109;color:var(--cream)}
.hero:before{content:'';position:absolute;inset:0;background:
  url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'%3E%3Cpath d='M60 10l4 8 8-4-4 8 8 4-8 4 4 8-8-4-4 8-4-8-8 4 4-8-8-4 8-4-4-8 8 4z' fill='%23d4b45c' fill-opacity='.07'/%3E%3C/svg%3E"),
  radial-gradient(65% 60% at 85% 0%,rgba(184,146,46,.30) 0%,rgba(184,146,46,0) 60%),
  radial-gradient(55% 50% at 0% 100%,rgba(90,62,34,.55) 0%,rgba(90,62,34,0) 65%),
  linear-gradient(160deg,#1e1810 0%,#161109 55%,#231a0d 100%);pointer-events:none}
.hero .wrap{position:relative;z-index:1;display:grid;grid-template-columns:1.05fr .95fr;gap:44px;padding:50px 18px 54px;align-items:center}
.eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:var(--gold);margin-bottom:14px}
.hero-badge{display:inline-flex;align-items:center;gap:9px;background:rgba(212,180,92,.12);border:1px solid rgba(212,180,92,.45);color:var(--gold-2);border-radius:999px;padding:7px 14px;font-size:11.5px;font-weight:800;letter-spacing:1.3px;text-transform:uppercase;margin-bottom:18px}
.hero-badge .dot{width:8px;height:8px;border-radius:50%;background:#57c95c;animation:shraPing 1.6s ease-out infinite}
h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:clamp(38px,5.4vw,64px);line-height:1.02;letter-spacing:-.5px;color:#fff}
h1 em{font-style:italic;background:linear-gradient(100deg,var(--gold-2) 0%,#f3dc92 45%,var(--gold) 100%);-webkit-background-clip:text;background-clip:text;color:transparent}
.lead{font-size:17.5px;color:#d9cdb2;margin-top:16px;max-width:520px}
.offer{position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:10px;margin-top:18px;background:rgba(168,50,45,.16);border:1px dashed #d4655f;color:#ffb3aa;border-radius:12px;padding:9px 12px 9px 9px;font-size:13.5px;font-weight:600}
.offer b{background:var(--red);color:#fff;border-radius:7px;padding:3px 9px;font-size:13px}
.offer:after{content:'';position:absolute;top:0;bottom:0;width:40%;left:-60%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.15),transparent);animation:shraSweep 2.8s ease-in-out infinite}
.cta{display:flex;gap:10px;margin-top:24px;flex-wrap:wrap}
.hero .cta .btn{font-size:16.5px;padding:16px 26px}
.btn-pulse{animation:shraPulse 2.2s ease-out infinite}
.hero .btn-ghost{background:rgba(255,255,255,.05);color:var(--cream);border:1.5px solid rgba(246,239,224,.25)}
.hero .btn-ghost:hover{background:rgba(255,255,255,.1)}
.times{display:flex;align-items:center;gap:9px;margin-top:16px;font-size:13.5px;color:#cbbf9f;font-weight:600}
.times i{color:var(--gold-2)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(108px,1fr));gap:16px 18px;margin-top:26px;padding-top:22px;border-top:1px solid rgba(246,239,224,.14)}
.stats b{display:block;font-family:'Cormorant Garamond',Georgia,serif;font-size:27px;font-weight:700;color:var(--gold-2);line-height:1.05}
.stats small{display:block;font-size:12px;color:#b7ab90;margin-top:4px;font-weight:600;line-height:1.35}
.proof{display:flex;align-items:center;gap:12px;margin-top:20px;font-size:13.5px;color:#b7ab90}
.proof .av{display:flex}
.proof .av span{width:30px;height:30px;border-radius:50%;border:2px solid #2a2116;margin-left:-8px;background:linear-gradient(135deg,var(--gold-2),var(--brown));display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700}
.proof .av span:first-child{margin-left:0}
.proof b{color:#fff}
.proof .stars{color:var(--gold-2);letter-spacing:2.5px;font-size:12.5px}
@keyframes shraPing{0%{box-shadow:0 0 0 0 rgba(87,201,92,.55)}70%{box-shadow:0 0 0 9px rgba(87,201,92,0)}100%{box-shadow:0 0 0 0 rgba(87,201,92,0)}}
@keyframes shraPulse{0%{box-shadow:0 10px 24px rgba(184,146,46,.35),0 0 0 0 rgba(212,180,92,.55)}70%{box-shadow:0 10px 24px rgba(184,146,46,.35),0 0 0 16px rgba(212,180,92,0)}100%{box-shadow:0 10px 24px rgba(184,146,46,.35),0 0 0 0 rgba(212,180,92,0)}}
@keyframes shraSweep{0%{left:-60%}55%{left:120%}100%{left:120%}}
@media(prefers-reduced-motion:reduce){.hero-badge .dot,.btn-pulse,.offer:after{animation:none}}
/* form card */
.card{background:#fff;border-radius:20px;box-shadow:0 30px 80px rgba(0,0,0,.5);overflow:hidden}
.card:before{content:'';display:block;height:5px;background:linear-gradient(90deg,var(--gold),var(--gold-2),var(--gold))}
.card-head{padding:20px 22px 12px;display:flex;align-items:flex-start;gap:12px}
.card-head h2{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:26px;line-height:1.1;color:var(--ink)}
.card-head p{color:var(--muted);font-size:13px;margin-top:3px}
.card-head .sec{margin-left:auto;flex-shrink:0;display:inline-flex;align-items:center;gap:6px;background:var(--cream);color:var(--brown);border-radius:999px;padding:6px 11px;font-size:11.5px;font-weight:800;white-space:nowrap}
.card-body{padding:6px 22px 22px;color:var(--ink)}
.more{border:1.5px dashed var(--line);border-radius:12px;background:var(--cream-2);margin-bottom:12px}
.more summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:9px;padding:12px 14px;font-size:13px;font-weight:700;color:var(--brown)}
.more summary::-webkit-details-marker{display:none}
.more summary .opt{font-weight:500;color:var(--muted)}
.more summary .ch{margin-left:auto;color:var(--gold);transition:.2s}
.more[open] summary .ch{transform:rotate(45deg)}
.more .inner{padding:2px 14px 14px}
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
.chips label small{display:block;font-size:11px;font-weight:600;opacity:.72;margin-top:2px;letter-spacing:.2px}
.fcfs{display:flex;gap:8px;align-items:flex-start;background:#f6ecd2;border:1px solid #e6d4a6;color:var(--brown);border-radius:10px;padding:9px 11px;font-size:12px;font-weight:600;line-height:1.45;margin:0 0 12px}
.fcfs i{margin-top:2px}
.err{background:#fbeeee;border:1px solid #e8b9b6;color:var(--red);border-radius:12px;padding:10px 14px;margin:8px 0 14px;font-size:13.5px}
.fine{font-size:12px;color:var(--muted);text-align:center;margin-top:12px;line-height:1.5}
.fine i{color:var(--green)}
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
.reel .ph img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.reel .ph .sh{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 35%,rgba(0,0,0,.75) 100%)}
.reel .ph .pl,.reel .ph .meta,.reel .ph .cap{position:relative;z-index:1}
.reel .ph .meta{position:absolute;left:14px;right:14px;bottom:14px;display:flex;gap:12px;font-size:12.5px;font-weight:600;color:#fff;text-align:left}
.reel .ph .meta i{color:var(--gold-2);margin-right:2px}
.reel .ph .meta .ago{margin-left:auto;color:#b9ad97;font-weight:500}
.reel .ph .cap{position:absolute;left:14px;right:14px;bottom:40px;font-size:12px;color:#e6dcc6;text-align:left;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
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
.try .pr b{font-size:18px;font-family:'Inter',system-ui,sans-serif;letter-spacing:-.2px}
.try .pr a{font-size:12.5px;color:var(--brown);font-weight:600}
.plans{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.plan{position:relative;background:var(--cream-2);border:1.5px solid var(--line);border-radius:18px;padding:20px 18px;display:flex;flex-direction:column}
.plan.hot{border-color:var(--gold);background:#fff;box-shadow:0 16px 40px rgba(184,146,46,.18)}
.plan .bd{position:absolute;top:-11px;left:16px;background:var(--gold);color:#fff;font-size:10.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:4px 10px;border-radius:999px}
.plan h3{font-size:17px;font-weight:700}
.plan .ss{font-size:12.5px;color:var(--muted);margin-top:2px}
.plan .pr{margin:14px 0 4px;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.plan .now{font-family:'Inter',system-ui,sans-serif;font-size:24px;font-weight:700;line-height:1;letter-spacing:-.3px}
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
/* map */
.map-sec{background:#fff;border-top:1px solid var(--line)}
.map{display:grid;grid-template-columns:1fr 1.4fr;gap:24px;align-items:stretch}
.map .info{background:var(--cream-2);border:1px solid var(--line);border-radius:18px;padding:26px 24px;display:flex;flex-direction:column}
.map .info h3{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:26px;line-height:1.1}
.map .info .addr{font-size:15px;color:var(--ink-2);margin-top:8px;line-height:1.5}
.map .info ul{list-style:none;margin:18px 0 22px;display:grid;gap:10px;font-size:14px;color:var(--ink-2)}
.map .info li{display:flex;gap:10px;align-items:flex-start}
.map .info li i{color:var(--gold);width:18px;text-align:center;margin-top:3px}
.map .info .btn{margin-top:auto}
.map .frame{border-radius:18px;overflow:hidden;border:1px solid var(--line);min-height:340px;background:var(--cream);position:relative}
.map .frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
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
  .hero .wrap{grid-template-columns:1fr;gap:30px;padding:34px 18px 38px}
  .stats{grid-template-columns:repeat(2,1fr)}
  .grid{grid-template-columns:1fr 1fr}
  .plans{grid-template-columns:1fr 1fr}
  .steps{grid-template-columns:1fr}
  .map{grid-template-columns:1fr}
  .map .frame{min-height:280px}
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
        <a class="call" href="#form"><i class="fa-solid fa-bolt"></i> <span>Book now</span></a>
    </div>
</header>

<section class="hero">
    <div class="wrap">
        <div>
            <div class="hero-badge"><span class="dot"></span> Admissions open · Hyderabad</div>
            <h1>Learn to ride a horse. <em>Start this week.</em></h1>
            <p class="lead">One-on-one riding lessons for children (<?php echo (int) $landing['min_age']; ?>+) and adults on calm, well-schooled horses. Zero experience needed — <?php echo $can_pay ? 'book online in 30 seconds and your place is confirmed' : 'book your visit in 30 seconds'; ?>.</p>
            <?php if ($offer['active']) { ?><div class="offer"><b><?php echo $offer['percent'] + 0; ?>% OFF</b> <?php echo html_escape($offer['label'] ?: 'Limited-time offer on all packages'); ?><?php if ($offer['ends']) { ?> · ends <?php echo date('j M', strtotime($offer['ends'])); ?><?php } ?></div><?php } ?>
            <div class="cta">
                <a class="btn btn-gold btn-pulse" href="#form"><i class="fa-solid fa-bolt"></i> <?php echo $can_pay ? 'Book my ride now' : 'Book my ride'; ?></a>
                <a class="btn btn-ghost" href="#pricing"><i class="fa-solid fa-tags"></i> See packages</a>
            </div>
            <div class="times"><i class="fa-solid fa-clock"></i> <?php echo html_escape($batch_line); ?> · 7 days a week</div>
            <div class="stats">
                <div><b>1-on-1</b><small>instructor with every rider</small></div>
                <div><b><?php echo (int) $landing['min_age']; ?>+ kids</b><small>&amp; adults, all levels</small></div>
                <?php if ($from) { ?><div><b><?php echo $from; ?></b><small>per session onwards</small></div><?php } ?>
                <div><b><i class="fa-solid fa-certificate" style="font-size:22px"></i></b><small>certificate on completion</small></div>
            </div>
            <div class="proof">
                <div class="av"><span>A</span><span>R</span><span>S</span><span>+</span></div>
                <div><span class="stars">★★★★★</span><br>Join <b><?php echo $landing['ig_followers'] > 0 ? $fmt(floor($landing['ig_followers'] / 100) * 100) . '+' : '7,000+'; ?> followers</b> watching our riders grow <?php if ($landing['instagram']) { ?>· <a href="<?php echo html_escape($landing['instagram']); ?>" target="_blank" rel="noopener" style="color:var(--gold-2);font-weight:600">@<?php echo html_escape($landing['ig_handle'] ?: 'stallionhorseriding'); ?></a><?php } ?></div>
            </div>
        </div>

        <div class="card" id="form">
            <div class="card-head">
                <div><h2><?php echo $can_pay ? 'Book your ride' : 'Get a call back'; ?></h2><p><?php echo $can_pay ? 'Pay online — your place is confirmed instantly.' : 'We call you the same day with packages &amp; timings.'; ?></p></div>
                <span class="sec"><i class="fa-regular fa-clock"></i> 30 SEC</span>
            </div>
            <div class="card-body">
                <?php if ($has_err) { ?><div class="err"><?php foreach ($errors as $e) { echo '<div>' . html_escape($e) . '</div>'; } ?></div><?php } ?>
                <form method="post" autocomplete="on" id="leadform" action="<?php echo site_url('inquire'); ?>#form">
                    <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                    <input type="hidden" name="ts" value="<?php echo $ts; ?>"><input type="hidden" name="sig" value="<?php echo $sig; ?>">
                    <?php foreach (['c', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'] as $k) { ?><input type="hidden" name="<?php echo $k; ?>" value="<?php echo html_escape($track[$k]); ?>"><?php } ?>
                    <div style="position:absolute;left:-5000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="f"><label>Your name <span class="req">*</span></label><input type="text" name="name" value="<?php echo $v('name'); ?>" required autocomplete="name" placeholder="Full name"></div>
                    <div class="f"><label>Mobile number <span class="req">*</span></label><input type="tel" name="phone" id="phone" value="<?php echo $v('phone'); ?>" required inputmode="numeric" autocomplete="tel-national" maxlength="10" pattern="[6-9][0-9]{9}" title="Enter a 10-digit Indian mobile number" placeholder="10-digit mobile number"></div>
                    <div class="f"><label>Who will ride?</label><div class="chips">
                        <label><input type="radio" name="rider_for" value="self" <?php echo $v('rider_for', 'self') === 'self' ? 'checked' : ''; ?>><span>Myself</span></label>
                        <label><input type="radio" name="rider_for" value="child" <?php echo $v('rider_for') === 'child' ? 'checked' : ''; ?>><span>My child</span></label>
                        <label><input type="radio" name="rider_for" value="both" <?php echo $v('rider_for') === 'both' ? 'checked' : ''; ?>><span>Both</span></label>
                    </div></div>
                    <div class="f"><label><?php echo $can_pay ? 'Choose your plan' : 'Interested in'; ?></label><select name="package_id" id="pkgsel"><option value=""><?php echo $can_pay ? 'Choose a plan…' : 'Not sure yet'; ?></option><?php foreach ($plans as $p) { ?><option value="<?php echo $p['id']; ?>" data-aud="<?php echo $p['audience']; ?>" <?php echo (string) $v('package_id') === (string) $p['id'] ? 'selected' : ''; ?>><?php echo ($p['audience'] === 'children' ? 'Kids' : 'Adults') . ' · ' . html_escape($p['name']) . ($p['is_guest'] ? '' : ' · ' . $p['sessions'] . ' sessions'); ?></option><?php } ?></select></div>
                    <?php $more_open = $v('rider_age') !== '' || $v('preferred_start_date') !== '' || $v('preferred_batch') !== '' || $v('message') !== ''; ?>
                    <details class="more" <?php echo $more_open ? 'open' : ''; ?>>
                        <summary><i class="fa-solid fa-sliders"></i> Age, start date &amp; timing <span class="opt">(optional)</span><i class="fa-solid fa-plus ch"></i></summary>
                        <div class="inner">
                            <div class="row">
                                <div class="f"><label>Rider's age</label><input type="number" name="rider_age" value="<?php echo $v('rider_age'); ?>" min="<?php echo (int) $landing['min_age']; ?>" max="90" inputmode="numeric" title="Riders must be <?php echo (int) $landing['min_age']; ?> years or older" placeholder="e.g. 8"></div>
                                <div class="f"><label>Start date</label><input type="date" name="preferred_start_date" value="<?php echo $v('preferred_start_date'); ?>" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+6 months')); ?>"></div>
                            </div>
                            <div class="f"><label>Class timing</label><div class="chips">
                                <?php foreach ($batches as $bk => $b) { ?><label><input type="radio" name="preferred_batch" value="<?php echo $bk; ?>" <?php echo $v('preferred_batch') === $bk ? 'checked' : ''; ?>><span><?php echo html_escape($b['label']); ?><small><?php echo html_escape($b['time']); ?></small></span></label><?php } ?>
                            </div></div>
                            <div class="fcfs"><i class="fa-solid fa-user-clock"></i> <span><?php echo html_escape(shra_fcfs_note()); ?></span></div>
                            <div class="f" style="margin-bottom:0"><label>Anything we should know?</label><textarea name="message" rows="2" placeholder="Preferred days, previous experience, questions…"><?php echo $v('message'); ?></textarea></div>
                        </div>
                    </details>
                    <input type="hidden" name="city" value="<?php echo $v('city'); ?>">
                    <?php if ($can_pay) { ?>
                    <?php /* The intent rides in a hidden field: a submit button's own
                            name/value is not reported by every browser (nor by a
                            scripted .click()), and this is now the only submit. */ ?>
                    <input type="hidden" name="action" value="book">
                    <button class="btn btn-gold btn-block" type="submit" id="bookbtn"><i class="fa-solid fa-lock"></i> <span id="booklbl">Choose a plan to continue</span></button>
                    <div class="fine"><i class="fa-solid fa-lock"></i> Secure payment. Your place is confirmed the moment you pay<?php echo $pay['partial'] ? ' — or pay a part now and the balance at the desk' : ''; ?>.</div>
                    <?php } else { ?>
                    <?php /* No gateway is live — the page falls back to collecting the enquiry. */ ?>
                    <button class="btn btn-gold btn-block" type="submit" id="subbtn"><i class="fa-solid fa-phone-volume"></i> Request a call back</button>
                    <div class="fine"><i class="fa-solid fa-lock"></i> No spam, no obligation. We only call to help you choose a package and book a visit.</div>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if (count($reels)) { ?>
<section class="reels-sec" id="reels">
    <div class="wrap">
        <div class="sec-h">
            <div class="eyebrow"><i class="fa-brands fa-instagram"></i> <?php echo $live ? 'Latest from Instagram' : 'Straight from the arena'; ?></div>
            <h2>See our riders in action</h2>
            <p>Real lessons, real riders — from first-timers to confident canters.<?php if ($live) { ?> Updated automatically from <a href="<?php echo html_escape($landing['instagram']); ?>" target="_blank" rel="noopener" style="color:#fff;font-weight:600">@<?php echo html_escape($landing['ig_handle']); ?></a>.<?php } ?></p>
        </div>
        <div class="reels">
            <?php foreach ($reels as $r) { ?>
            <div class="reel" data-reel="<?php echo html_escape($r['id']); ?>">
                <div class="ph" role="button" tabindex="0" aria-label="Play reel">
                    <?php if ($r['thumb'] !== '') { ?><img src="<?php echo html_escape($r['thumb']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()"><?php } ?>
                    <div class="sh"></div>
                    <span class="pl"><i class="fa-solid fa-play"></i></span>
                    <div class="meta">
                        <?php if ($r['views'] > 0) { ?><span><i class="fa-solid fa-play"></i> <?php echo $fmt($r['views']); ?></span><?php } ?>
                        <?php if ($r['likes'] > 0) { ?><span><i class="fa-solid fa-heart"></i> <?php echo $fmt($r['likes']); ?></span><?php } ?>
                        <?php if ($r['taken'] > 0) { ?><span class="ago"><?php echo $ago($r['taken']); ?></span><?php } ?>
                    </div>
                    <?php if ($r['caption'] !== '') { ?><div class="cap"><?php echo html_escape($r['caption']); ?></div><?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
        <div class="ig-link">
            <a href="<?php echo html_escape($landing['instagram'] ?: 'https://www.instagram.com/'); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> Follow<?php if ($landing['ig_followers'] > 0) { ?> · <?php echo $fmt($landing['ig_followers']); ?> followers<?php } ?></a>
        </div>
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
            <?php if (count($kids) && count($adults)) { ?><div class="tabs"><button type="button" class="on" data-tab="adults">Adults</button><button type="button" data-tab="kids">Kids</button></div><?php } ?>
        </div>
        <?php foreach (['adults' => [$adults, $adult_try], 'kids' => [$kids, $kid_try]] as $key => $set) { list($list, $try) = $set; if (!count($list)) { continue; } ?>
        <div class="tabpane <?php echo $key === 'adults' || !count($adults) ? 'on' : ''; ?>" data-pane="<?php echo $key; ?>">
            <?php if ($try) { ?><div class="try"><i class="fa-solid fa-star"></i><div><b>Try it first — <?php echo html_escape($try['name']); ?></b><small>One <?php echo $try['duration_min']; ?>-minute session with an instructor. Perfect for a first visit.</small></div><div class="pr"><b><?php echo $try['total']; ?></b><?php if ($try['discount'] > 0) { ?> <span style="font-size:12px;color:var(--muted);text-decoration:line-through"><?php echo $try['price']; ?></span><?php } ?><br><a href="#form" data-pick="<?php echo $try['id']; ?>">Book a guest ride →</a></div></div><?php } ?>
            <div class="plans">
                <?php foreach ($list as $p) { if ($p['is_guest']) { continue; } ?>
                <div class="plan <?php echo $p['is_featured'] ? 'hot' : ''; ?>">
                    <?php if ($p['is_featured']) { ?><span class="bd">Best value</span><?php } ?>
                    <h3><?php echo html_escape($p['name']); ?></h3>
                    <div class="ss"><?php echo $p['sessions']; ?> sessions · <?php echo $p['duration_min']; ?> min each</div>
                    <div class="pr"><span class="now"><?php echo $p['total']; ?></span><?php if ($p['discount'] > 0) { ?><span class="was"><?php echo $p['price']; ?></span><?php } ?></div>
                    <div class="per"><b><?php echo $p['per_session_now']; ?></b> per session</div>
                    <a class="btn <?php echo $p['is_featured'] ? 'btn-gold' : 'btn-ghost'; ?>" href="#form" data-pick="<?php echo $p['id']; ?>"><?php echo $can_pay ? 'Book now' : 'Enquire'; ?> <i class="fa-solid fa-arrow-right"></i></a>
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
            <h2><?php echo $can_pay ? 'From booking to your first ride' : 'From your first call to your first ride'; ?></h2>
        </div>
        <div class="steps">
            <?php if ($can_pay) { ?>
            <div class="st"><h3>Book &amp; pay online</h3><p>Pick a plan, enter your details and pay securely. Your place is confirmed straight away — no waiting for a call back.</p></div>
            <?php } else { ?>
            <div class="st"><h3>Request a call back</h3><p>Fill the 30-second form and our team calls you the same day with packages and timings.</p></div>
            <?php } ?>
            <div class="st"><h3>Visit the academy</h3><p>Come see the horses, meet the instructors and take a Guest Ride — any day except Monday, 6–9 AM or 4–6 PM.</p></div>
            <div class="st"><h3>Pick a package &amp; start</h3><p>Choose the plan that fits, get your membership card and start your sessions. Certificate on completion.</p></div>
        </div>
    </div>
</section>

<?php if ($landing['map_embed'] !== '') { ?>
<section class="map-sec" id="location">
    <div class="wrap">
        <div class="sec-h">
            <div class="eyebrow">Find us</div>
            <h2>Just outside the city, easy to reach</h2>
            <p>A green, open-air academy <?php echo $landing['location'] ? 'at ' . html_escape($landing['location']) : 'in Hyderabad'; ?>.</p>
        </div>
        <div class="map">
            <div class="info">
                <h3><?php echo html_escape($academy); ?></h3>
                <?php if ($landing['location']) { ?><div class="addr"><i class="fa-solid fa-location-dot" style="color:var(--gold)"></i> <?php echo html_escape($landing['location']); ?></div><?php } ?>
                <ul>
                    <li><i class="fa-solid fa-clock"></i><span><b>Visits:</b> all days except Monday, 6–9 AM &amp; 4–6 PM — <?php echo $can_pay ? 'book online and your slot is confirmed straight away' : 'book a slot and we confirm the timing with you'; ?>.</span></li>
                    <?php if ($landing['phone']) { ?><li><i class="fa-solid fa-phone"></i><span><b>Reception:</b> <b style="color:var(--brown)"><?php echo html_escape($landing['phone']); ?></b></span></li><?php } ?>
                    <li><i class="fa-solid fa-car"></i><span>Tap <b>Get directions</b> for the easiest route from wherever you are.</span></li>
                </ul>
                <a class="btn btn-dark" href="<?php echo html_escape($landing['maps_url']); ?>" target="_blank" rel="noopener" onclick="shraTrack('FindLocation')"><i class="fa-solid fa-diamond-turn-right"></i> Get directions</a>
            </div>
            <div class="frame">
                <iframe src="<?php echo html_escape($landing['map_embed']); ?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" title="Map to <?php echo html_escape($academy); ?>"></iframe>
            </div>
        </div>
    </div>
</section>
<?php } ?>

<section>
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
        <p><?php echo $can_pay ? 'Book your session in under a minute' : 'Book a free visit'; ?><?php if ($offer['active']) { ?> and lock in the <?php echo $offer['percent'] + 0; ?>% offer before it ends<?php } ?>. Limited slots each week.</p>
        <div class="cta">
            <a class="btn btn-gold" href="#form"><i class="fa-solid fa-bolt"></i> Book your ride</a>
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
    <a class="btn btn-gold" href="#form"><i class="fa-solid fa-bolt"></i> <?php echo $can_pay ? 'Book &amp; pay now' : 'Book a visit'; ?></a>
</div>

<script>
(function(){
    // Pricing tabs
    document.querySelectorAll('.tabs button').forEach(function(b){b.addEventListener('click',function(){
        document.querySelectorAll('.tabs button').forEach(function(x){x.classList.toggle('on',x===b);});
        document.querySelectorAll('.tabpane').forEach(function(p){p.classList.toggle('on',p.dataset.pane===b.dataset.tab);});
    });});
    // "Enquire" buttons pre-select the package in the form
    var sel=document.getElementById('pkgsel');
    document.querySelectorAll('[data-pick]').forEach(function(a){a.addEventListener('click',function(){
        var r=document.querySelector('input[name=rider_for][value='+(a.closest('[data-pane]')&&a.closest('[data-pane]').dataset.pane==='adults'?'self':'child')+']');
        if(r){r.checked=true;}
        filterPlans();
        if(sel){sel.value=a.dataset.pick;}
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
    // No thumbnails (manual fallback list) — auto-load the first reel when the strip scrolls into view
    var first=document.querySelector('.reel');
    if(first&&!first.querySelector('img')&&'IntersectionObserver' in window){
        new IntersectionObserver(function(en,io){en.forEach(function(e){if(e.isIntersecting){loadReel(first);io.disconnect();}});},{rootMargin:'200px'}).observe(first);
    }
    // Mobile: digits only, strip a pasted +91 / 0 prefix, cap at 10
    var ph=document.getElementById('phone');
    if(ph){ph.addEventListener('input',function(){var d=ph.value.replace(/\D+/g,'');if(d.length>10&&d.indexOf('91')===0){d=d.slice(2);}d=d.replace(/^0+/,'');ph.value=d.slice(0,10);});}
    // Checkout button — carries the price of whichever plan is selected
    var bookable=<?php echo json_encode($bookable, JSON_FORCE_OBJECT); ?>;
    var bk=document.getElementById('bookbtn'),bl=document.getElementById('booklbl');
    function plan(){return (bk&&sel)?bookable[sel.value]:null;}
    function syncBook(){
        if(!bk){return;}
        var p=plan();
        bl.textContent=p?((p.guest?'Book this guest ride — ':'Book & pay now — ')+p.total):'Choose a plan to continue';
    }
    if(sel){sel.addEventListener('change',syncBook);}
    document.querySelectorAll('[data-pick]').forEach(function(a){a.addEventListener('click',syncBook);});
    // The plan list follows "Who will ride?" — kids plans for a child, adult plans for myself, everything for both
    var allPlans=sel?Array.prototype.slice.call(sel.options):[];
    function filterPlans(){
        if(!sel){return;}
        var r=document.querySelector('input[name=rider_for]:checked');
        var want=!r||r.value==='both'?null:(r.value==='child'?'children':'adults');
        var keep=sel.value;
        sel.innerHTML='';
        allPlans.forEach(function(o){if(!o.value||!want||o.dataset.aud===want){sel.appendChild(o);}});
        sel.value=keep;
        if(sel.value!==keep){sel.value='';}
        syncBook();
    }
    document.querySelectorAll('input[name=rider_for]').forEach(function(r){r.addEventListener('change',filterPlans);});
    // A plan arriving pre-selected (ad link ?pkg= or a validation re-render) flips the chip
    // to its audience rather than being filtered out of the list
    if(sel&&sel.value){
        var pre=sel.options[sel.selectedIndex];
        if(pre&&pre.dataset.aud){
            var need=pre.dataset.aud==='children'?'child':'self';
            var cur=document.querySelector('input[name=rider_for]:checked');
            if(cur&&cur.value!=='both'&&cur.value!==need){
                var pr=document.querySelector('input[name=rider_for][value='+need+']');
                if(pr){pr.checked=true;}
            }
        }
    }
    filterPlans();
    // Submit state + tracking
    var form=document.getElementById('leadform'),btn=document.getElementById('subbtn');
    if(form){form.addEventListener('submit',function(e){
        var b=e.submitter||btn||bk;
        // Nothing to charge for yet — send them to the plan picker instead of the gateway
        if(b===bk&&!plan()){
            e.preventDefault();
            sel.focus();
            sel.scrollIntoView({block:'center'});
            return;
        }
        // Booking skips the thank-you page, so the Lead event has to fire here
        if(b===bk){shraTrack('Lead',{content_name:'inquire_book'});}
        b.disabled=true;
        b.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> '+(b===bk?'Opening the payment page…':'Sending…');
        shraTrack('InitiateCheckout',{content_name:b===bk?'inquire_book':'inquire_form'});
    });}
    // Scroll back to the form after a validation error
    <?php if ($has_err) { ?>setTimeout(function(){document.getElementById('form').scrollIntoView({block:'start'});},50);<?php } ?>
})();
</script>
</body>
</html>
