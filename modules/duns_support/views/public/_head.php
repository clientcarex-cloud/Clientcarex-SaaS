<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * <head> for every public page + ad tags.
 * Expects $title, $landing; optional $description, $events = [[name, params], …]
 * (Meta standard events, mirrored to GA4/Google Ads), $noindex.
 */
$events      = $events ?? [];
$description = $description ?? 'Get a valid D-U-N-S number for Google Play Console, Apple Developer and more — delivered to your email in 1, 6 or 12 hours. 100% money-back guarantee.';
$gtag_ids    = array_values(array_filter([$landing['gads_id'], $landing['ga4_id']]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo html_escape($title); ?></title>
<meta name="description" content="<?php echo html_escape($description); ?>">
<?php if (!empty($noindex)) { ?><meta name="robots" content="noindex, nofollow"><?php } ?>
<meta name="theme-color" content="#0a1733">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo html_escape($title); ?>">
<meta property="og:description" content="<?php echo html_escape($description); ?>">
<?php if ($landing['logo'] !== '') { ?><meta property="og:image" content="<?php echo html_escape($landing['logo']); ?>"><?php } ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?php echo module_dir_url(DUNS_MODULE_NAME, 'assets/css/duns_public.css') . '?v=' . DUNS_MODULE_VERSION; ?>">
<?php if (count($gtag_ids)) { ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo html_escape($gtag_ids[0]); ?>"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());
<?php foreach ($gtag_ids as $id) { ?>gtag('config',<?php echo json_encode($id); ?>);<?php } ?>
</script>
<?php } ?>
<?php if ($landing['meta_pixel'] !== '') { ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init',<?php echo json_encode($landing['meta_pixel']); ?>);fbq('track','PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?php echo $landing['meta_pixel']; ?>&ev=PageView&noscript=1"></noscript>
<?php } ?>
<script>
/* dunsTrack('Lead', {value:…}) — Meta standard event + GA4 event; a no-op when no tags are set */
window.dunsTrack=function(ev,p){p=p||{};try{if(window.fbq){fbq('track',ev,p);}if(window.gtag){var map={Purchase:'purchase',Lead:'generate_lead',InitiateCheckout:'begin_checkout',AddPaymentInfo:'add_payment_info',ViewContent:'view_item',Contact:'contact'};gtag('event',map[ev]||ev.toLowerCase(),p);}}catch(e){}};
<?php foreach ($events as $ev) { ?>
dunsTrack(<?php echo json_encode($ev[0]); ?>,<?php echo json_encode((object) ($ev[1] ?? [])); ?>);
<?php if ($ev[0] === 'Purchase' && $landing['gads_id'] !== '' && $landing['gads_label'] !== '' ) { ?>
if(window.gtag){gtag('event','conversion',<?php echo json_encode(['send_to' => $landing['gads_id'] . '/' . $landing['gads_label'], 'value' => $ev[1]['value'] ?? 0, 'currency' => $ev[1]['currency'] ?? '', 'transaction_id' => $ev[1]['order_id'] ?? '']); ?>);}
<?php } ?>
<?php } ?>
</script>
</head>
