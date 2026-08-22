<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Representative sample document for the Branding Center live preview.
 * Full HTML doc using the SAME design-system classes/vars as the bundled
 * templates, so branding_style_block() (injected before </head>) retints it
 * exactly like a real document. $b = current branding profile.
 */
$CI     = &get_instance();
$clinic = get_option('invoice_company_name');
if ($clinic == '') {
    $clinic = get_option('companyname');
}
$logo_url = $CI->smart_pdf_model->branding_logo_url($b);
if ($logo_url === '') {
    // Transparent pixel so a company with no logo yet doesn't show a broken icon.
    $logo_url = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
}
$tagline  = $b['tagline'] !== '' ? $b['tagline'] : 'Human Resources';
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Branding preview</title>
<style>
  :root{
    --ink:#0b2e33; --teal:#0e4d54; --teal-soft:#e8f0ef;
    --gold:#b6923d; --gold-soft:#f3ead5;
    --muted:#5f7275; --line:#dbe4e3; --paper:#ffffff;
  }
  *{box-sizing:border-box;}
  html,body{margin:0; padding:0; background:#eef1f0;}
  body{font-family:'Source Sans 3',Arial,Helvetica,sans-serif; color:var(--ink); font-size:12.5px; line-height:1.7;}
  .sheet{
    width:210mm; min-height:297mm; margin:16px auto; background:var(--paper);
    padding:20mm 20mm 15mm; box-shadow:0 6px 30px rgba(11,46,51,.14); position:relative;
  }
  .letterhead{display:flex; align-items:center; justify-content:space-between; gap:18px;}
  .brand{display:flex; align-items:center; gap:14px;}
  .brand-logo{height:27px; width:auto; max-width:105px; object-fit:contain; flex-shrink:0;}
  .brand-name{font-family:'Playfair Display',serif; font-size:25px; font-weight:700; color:var(--ink); line-height:1.1;}
  .brand-sub{font-size:10.5px; letter-spacing:2.5px; text-transform:uppercase; color:var(--gold); margin-top:3px;}
  .doc-meta{text-align:right; font-size:11px; color:var(--muted);}
  .doc-meta b{color:var(--ink);}
  .rule{height:3px; border:0; margin:14px 0 6px;
    background:linear-gradient(90deg,var(--teal) 0%,var(--teal) 62%,var(--gold) 62%,var(--gold) 100%);}
  .rule-thin{height:1px; background:var(--line); border:0; margin:0 0 20px;}
  .doc-title{text-align:center; margin:10px 0 22px;}
  .doc-title .kicker{font-size:10.5px; letter-spacing:4px; text-transform:uppercase; color:var(--gold); display:block; margin-bottom:6px;}
  .doc-title h1{font-family:'Playfair Display',serif; font-size:26px; font-weight:700; letter-spacing:1px; margin:0; color:var(--teal);}
  p{margin:0 0 12px;}
  strong{color:var(--ink);}
  .highlight{color:var(--teal); font-weight:600;}
  .terms-card{background:var(--teal-soft); border:1px solid var(--line); border-left:4px solid var(--teal);
    border-radius:6px; padding:14px 16px; margin:16px 0;}
  .terms-card h3{margin:0 0 10px; font-size:12px; letter-spacing:1.5px; text-transform:uppercase; color:var(--teal);}
  table.kv{width:100%; border-collapse:collapse; font-size:12px;}
  table.kv td{padding:6px 8px; border-bottom:1px solid var(--line);}
  table.kv td.k{color:var(--muted); width:42%;}
  table.kv td.v{color:var(--ink); font-weight:600;}
  .badge{display:inline-block; background:var(--gold-soft); color:var(--gold); border:1px solid var(--gold);
    border-radius:20px; padding:3px 12px; font-size:10.5px; font-weight:700; letter-spacing:1px; text-transform:uppercase;}
  .sign{margin-top:34px; display:flex; justify-content:space-between; gap:30px;}
  .sign .line{border-top:1.5px solid var(--ink); padding-top:6px; width:45%; font-size:11px; color:var(--muted);}
  .brand-footer{margin-top:24px; border-top:1px solid var(--line); padding-top:10px;}
</style>
</head>
<body>
  <div class="sheet">
    <div class="letterhead">
      <div class="brand">
        <img class="brand-logo" src="<?php echo html_escape($logo_url); ?>" alt="">
        <div>
          <div class="brand-name"><?php echo html_escape($clinic ?: 'Your Company'); ?></div>
          <div class="brand-sub"><?php echo html_escape($tagline); ?></div>
        </div>
      </div>
      <div class="doc-meta">
        <div><b>Document No:</b> DOC-00042</div>
        <div><b>Date:</b> <?php echo _d(date('Y-m-d')); ?></div>
      </div>
    </div>
    <hr class="rule"><hr class="rule-thin">

    <div class="doc-title">
      <span class="kicker">Sample Document</span>
      <h1>Letter of Appointment</h1>
    </div>

    <p>Dear <strong>Sample Employee</strong>,</p>
    <p>We are <span class="highlight">delighted to confirm</span> your appointment. This live preview shows exactly
       how your colours, logo and company name appear across <strong>every</strong> Smart PDF document — letters,
       certificates, ID cards, agreements and policies.</p>

    <div class="terms-card">
      <h3>Appointment Details</h3>
      <table class="kv">
        <tr><td class="k">Designation</td><td class="v">Senior Consultant</td></tr>
        <tr><td class="k">Department</td><td class="v">Cardiology</td></tr>
        <tr><td class="k">Date of Joining</td><td class="v"><?php echo _d(date('Y-m-d')); ?></td></tr>
        <tr><td class="k">Status</td><td class="v"><span class="badge">Confirmed</span></td></tr>
      </table>
    </div>

    <p>We look forward to a long and rewarding association. Please sign below to confirm your acceptance of the
       terms outlined in this letter.</p>

    <div class="sign">
      <div class="line">Authorised Signatory</div>
      <div class="line">Employee Signature</div>
    </div>

    <?php if ($b['footer_text'] !== '') { ?>
      <div class="brand-footer" style="color:<?php echo $b['text']; ?>;font-size:11px;line-height:1.6;">
        <?php echo nl2br(html_escape($b['footer_text'])); ?>
      </div>
    <?php } ?>
  </div>
</body>
</html>
