<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Ad tracking tags for the public landing page. Included in <head>.
 * Expects $landing (from Shra_public::landing()) and optionally $conversion = true on the thank-you page.
 */
$conversion = !empty($conversion);
$gtag_ids   = array_values(array_filter([$landing['gads_id'], $landing['ga4_id']]));
?>
<?php if (count($gtag_ids)) { ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo html_escape($gtag_ids[0]); ?>"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());
<?php foreach ($gtag_ids as $id) { ?>gtag('config','<?php echo html_escape($id); ?>');<?php } ?>
<?php if ($conversion && $landing['gads_id'] !== '' && $landing['gads_label'] !== '') { ?>
gtag('event','conversion',{'send_to':'<?php echo html_escape($landing['gads_id'] . '/' . $landing['gads_label']); ?>'});
<?php } ?>
<?php if ($conversion) { ?>gtag('event','generate_lead',{'event_category':'inquire'});<?php } ?>
</script>
<?php } ?>
<?php if ($landing['meta_pixel'] !== '') { ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','<?php echo $landing['meta_pixel']; ?>');fbq('track','PageView');
<?php if ($conversion) { ?>fbq('track','Lead');<?php } ?>
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo $landing['meta_pixel']; ?>&ev=<?php echo $conversion ? 'Lead' : 'PageView'; ?>&noscript=1"></noscript>
<?php } ?>
<script>
/* shraTrack('Contact'|'Lead'|...) — safe no-op when no tags are configured */
window.shraTrack=function(ev,params){try{if(window.fbq){fbq('track',ev,params||{});}if(window.gtag){gtag('event',ev.toLowerCase(),params||{});}}catch(e){}};
</script>
