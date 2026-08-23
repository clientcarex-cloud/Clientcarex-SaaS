<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Thank-you page after the /inquire landing form — fires the ad conversion tags. */
$academy    = get_option('shra_academy_name') ?: 'Stallion Horse Riding Academy';
$conversion = true;
$phone_href = $landing['phone_digits'] !== '' ? 'tel:+' . preg_replace('/\D+/', '', get_option('shra_lead_phone_country')) . ltrim(shra_phone_norm($landing['phone']), '0') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($title); ?></title>
<link rel="icon" href="<?php echo shra_logo_url(); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include __DIR__ . '/_landing_track.php'; ?>
<style>
:root{--cream:#f6efe0;--cream-2:#fbf7ee;--sand:#e9d9b6;--ink:#1c1a17;--ink-2:#3a3530;--gold:#b8922e;--gold-2:#d4b45c;--brown:#5a3e22;--muted:#7a6f5e;--line:#e3d6b8;--green:#2f4a1f;--wa:#25d366}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:radial-gradient(120% 80% at 50% 0%,#fbf5e6 0%,var(--cream) 55%,#efe3c6 100%);min-height:100vh;color:var(--ink);padding:28px 16px 40px;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;align-items:center}
.brand{text-align:center;margin-bottom:18px}
.brand img{width:72px;height:72px;border-radius:50%;box-shadow:0 0 0 4px var(--cream),0 0 0 5px var(--sand);margin:0 auto}
.brand h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:24px;margin-top:10px}
.card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 24px 60px rgba(90,62,34,.14);max-width:520px;width:100%;overflow:hidden}
.head{padding:30px 26px 22px;text-align:center;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#fff,var(--cream-2))}
.tick{width:68px;height:68px;border-radius:50%;background:var(--green);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:14px;box-shadow:0 12px 30px rgba(47,74,31,.3)}
.head h2{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:32px;line-height:1.05}
.head p{color:var(--muted);margin-top:8px;font-size:15px}
.body{padding:22px 26px 26px}
.next{display:grid;gap:10px;margin-bottom:18px}
.next div{display:flex;gap:12px;align-items:flex-start;font-size:14px;color:var(--ink-2)}
.next i{width:30px;height:30px;border-radius:9px;background:var(--cream);color:var(--gold);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;margin-top:-2px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:15px 20px;border:0;border-radius:14px;font:inherit;font-size:16px;font-weight:700;cursor:pointer;text-decoration:none;margin-bottom:10px}
.btn-wa{background:var(--wa);color:#fff;box-shadow:0 10px 24px rgba(37,211,102,.3)}
.btn-ghost{background:#fff;color:var(--ink);border:1.5px solid var(--line)}
.hint{font-size:12.5px;color:var(--muted);text-align:center;margin-top:6px}
.foot{text-align:center;font-size:12px;color:var(--muted);margin-top:18px}
.shra-powered{display:inline-flex;align-items:center;gap:6px;text-decoration:none;opacity:.85}.shra-powered span{font-size:11px;color:#7e8c9d;font-weight:500}.shra-powered img{height:18px;width:auto}
</style>
</head>
<body>
    <div class="brand"><img src="<?php echo shra_logo_url(); ?>" alt=""><h1><?php echo html_escape($academy); ?></h1></div>
    <div class="card">
        <div class="head">
            <div class="tick"><i class="fa fa-check"></i></div>
            <h2>You're on the list!</h2>
            <p>Thank you — one of our team will call you shortly to answer your questions and book your visit.</p>
        </div>
        <div class="body">
            <div class="next">
                <div><i class="fa-solid fa-phone"></i><span><b>Expect our call</b> — we call back the same day during academy hours. Please save our number so you don't miss it<?php if ($landing['phone']) { ?>: <b><?php echo html_escape($landing['phone']); ?></b><?php } ?>.</span></div>
                <div><i class="fa-solid fa-calendar-check"></i><span><b>Pick a visit slot</b> — Saturday and Sunday mornings are the most popular; we'll find one that suits you.</span></div>
                <div><i class="fa-solid fa-shirt"></i><span><b>On the day</b> — wear full-length trousers and closed shoes.</span></div>
            </div>
            <?php if ($landing['wa_link']) { ?><a class="btn btn-wa" href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" onclick="shraTrack('Contact',{method:'whatsapp'})"><i class="fa-brands fa-whatsapp" style="font-size:20px"></i> WhatsApp us now</a><?php } ?>
            <?php if ($phone_href) { ?><a class="btn btn-ghost" href="<?php echo $phone_href; ?>" onclick="shraTrack('Contact',{method:'call'})"><i class="fa-solid fa-phone"></i> Call <?php echo html_escape($landing['phone']); ?></a><?php } ?>
            <?php if ($landing['instagram']) { ?><div class="hint">Meanwhile, see our riders on <a href="<?php echo html_escape($landing['instagram']); ?>" target="_blank" rel="noopener" style="color:var(--brown);font-weight:600">Instagram <i class="fa-brands fa-instagram"></i></a></div><?php } ?>
        </div>
    </div>
    <div class="foot"><?php echo html_escape(get_option('shra_contact_line') ?: $academy); ?><br><?php echo shra_powered_by(); ?></div>
</body>
</html>
