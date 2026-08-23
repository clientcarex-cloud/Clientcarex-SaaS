<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--cream:#f6efe0;--cream-2:#fbf7ee;--sand:#e9d9b6;--ink:#1c1a17;--ink-2:#3a3530;--gold:#b8922e;--gold-2:#d4b45c;--brown:#5a3e22;--muted:#7a6f5e;--line:#e3d6b8;--red:#a8322d;--green:#2f4a1f}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:radial-gradient(120% 80% at 50% 0%,#fbf5e6 0%,var(--cream) 55%,#efe3c6 100%);min-height:100vh;color:var(--ink);padding:22px 14px 50px;-webkit-font-smoothing:antialiased}
.wrap{max-width:620px;margin:0 auto}
.brand{text-align:center;margin-bottom:18px}
.brand img{width:92px;height:92px;border-radius:50%;box-shadow:0 0 0 4px var(--cream),0 0 0 5px var(--sand)}
.brand h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:27px;margin-top:12px;line-height:1.1}
.brand .tag{font-family:'Cormorant Garamond',Georgia,serif;font-style:italic;color:var(--muted);font-size:15px;margin-top:3px}
.div{display:flex;align-items:center;gap:8px;justify-content:center;margin:14px 0 18px}
.div:before,.div:after{content:'';width:60px;height:1px;background:var(--gold)}
.div i{color:var(--gold);font-size:8px}
.card{background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 24px 60px rgba(90,62,34,.12);overflow:hidden}
.card-head{padding:22px 26px 18px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#fff,var(--cream-2))}
.card-head h2{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:26px}
.card-head p{color:var(--muted);font-size:13.5px;margin-top:4px;line-height:1.5}
.card-body{padding:22px 26px}
.sec{font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:var(--gold);font-weight:700;margin:20px 0 10px;display:flex;align-items:center;gap:10px}
.sec:after{content:'';flex:1;height:1px;background:var(--line)}
.sec:first-child{margin-top:0}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:520px){.row{grid-template-columns:1fr}}
.f{margin-bottom:12px}
.f label{display:block;font-size:12.5px;font-weight:600;color:var(--ink-2);margin-bottom:5px}
.f label .req{color:var(--red)}
.f input,.f select,.f textarea{width:100%;border:1.5px solid var(--line);border-radius:12px;padding:12px 13px;font:inherit;font-size:15px;color:var(--ink);background:#fff;outline:none;transition:.15s;-webkit-appearance:none}
.f input:focus,.f select:focus,.f textarea:focus{border-color:var(--gold);box-shadow:0 0 0 4px rgba(184,146,46,.14)}
.f select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a6f5e' stroke-width='1.6' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}
.hint{font-size:12px;color:var(--muted);margin-top:5px}
.chips{display:flex;gap:8px;flex-wrap:wrap}
.chips label{flex:1;min-width:90px;text-align:center;border:1.5px solid var(--line);border-radius:12px;padding:11px 10px;font-size:13.5px;font-weight:600;cursor:pointer;background:#fff;color:var(--ink-2)}
.chips input{display:none}
.chips input:checked+span{color:var(--cream)}
.chips label:has(input:checked){background:var(--ink);border-color:var(--ink);color:var(--cream)}
.guardian{background:var(--cream-2);border:1px dashed var(--gold-2);border-radius:14px;padding:14px 14px 4px;margin-bottom:12px}
.guardian .note{font-size:12.5px;color:var(--brown);margin-bottom:10px;font-weight:600}
.terms{max-height:190px;overflow:auto;font-size:12.5px;line-height:1.6;color:var(--ink-2);background:var(--cream-2);border:1px solid var(--line);border-radius:12px;padding:12px 14px;white-space:pre-line}
.accept{display:flex;gap:12px;align-items:flex-start;margin-top:12px;padding:12px 14px;border:1.5px solid var(--line);border-radius:12px;cursor:pointer}
.accept input{width:20px;height:20px;margin-top:1px;accent-color:var(--ink);flex-shrink:0}
.accept div{font-size:13.5px;line-height:1.5}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px 20px;border:0;border-radius:14px;font:inherit;font-size:16px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,var(--gold),var(--gold-2));color:#fff;box-shadow:0 10px 24px rgba(184,146,46,.35);transition:.15s;text-decoration:none}
.btn:hover{transform:translateY(-1px);filter:brightness(1.04)}
.btn.dark{background:var(--ink);box-shadow:0 10px 24px rgba(28,26,23,.25)}
.btn.ghost{background:#fff;color:var(--ink);border:1.5px solid var(--line);box-shadow:none}
.err{background:#fbeeee;border:1px solid #e8b9b6;color:var(--red);border-radius:12px;padding:12px 14px;font-size:13.5px;margin-bottom:16px}
.err ul{margin:4px 0 0 18px}
.foot{text-align:center;font-size:12px;color:var(--muted);margin-top:18px}
.kv{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px}
.kv .k{font-size:10.5px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);font-weight:600}
.kv .v{font-size:14px;font-weight:600;margin-top:2px}
.badge{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase}
.badge.ok{background:#e7efe0;color:var(--green)}
.badge.bad{background:#f8e3e2;color:var(--red)}
.badge.gold{background:#f6ecd2;color:var(--brown)}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <img src="<?php echo shra_logo_url(); ?>" alt="">
        <h1><?php echo html_escape(get_option('shra_academy_name')); ?></h1>
        <div class="tag"><?php echo html_escape(get_option('shra_tagline')); ?></div>
        <div class="div"><i class="fa-solid fa-diamond"></i></div>
    </div>
