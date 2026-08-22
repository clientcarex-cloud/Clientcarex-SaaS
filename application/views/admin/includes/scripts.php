<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php include_once APPPATH . 'views/admin/includes/helpers_bottom.php'; ?>

<?php hooks()->do_action('before_js_scripts_render'); ?>

<?= app_compile_scripts();

/**
 * Global function for custom field of type hyperlink
 */
echo get_custom_fields_hyperlink_js_function(); ?>
<?php
/**
 * Check for any alerts stored in session
 */
app_js_alerts();
?>
<?php
/**
 * Check pusher real time notifications
 */
if (get_option('pusher_realtime_notifications') == 1) { ?>
<script type="text/javascript">
    $(function() {
        // Enable pusher logging - don't include this in production
        // Pusher.logToConsole = true;
        <?php $pusher_options = hooks()->apply_filters('pusher_options', [['disableStats' => true]]);
    if (! isset($pusher_options['cluster']) && get_option('pusher_cluster') != '') {
        $pusher_options['cluster'] = get_option('pusher_cluster');
    }
    ?>
        var
            pusher_options = <?= json_encode($pusher_options); ?> ;
        var pusher = new Pusher(
            "<?= get_option('pusher_app_key'); ?>",
            pusher_options);
        var channel = pusher.subscribe(
            'notifications-channel-<?= get_staff_user_id(); ?>'
        );
        channel.bind('notification', function(data) {
            fetch_notifications();
        });
    });
</script>
<?php } ?>
<?php if (get_option('ccx_wpa_enabled') == '1') { ?>
<!-- PWA Service Worker Registration -->
<script src="<?= base_url('assets/js/pwa-register.js'); ?>"></script>
<?php } ?>
<script type="text/javascript">
// Sidebar active-state fallback: core main.js only highlights a menu item when
// the current URL EXACTLY equals a menu href, so module sub-pages
// (edit/view/other controllers) lose the highlight. When no exact match was
// found, activate the menu link sharing the most leading URL segments with the
// current page (must match at least one segment beyond the admin root).
$(function () {
    var $sideMenu = $('#side-menu');
    if (!$sideMenu.length || $sideMenu.find('li.active').not('.quick-links').length) {
        return;
    }

    function urlSegments(url) {
        return url
            .replace(/^https?:\/\/[^\/]+/i, '')
            .split('#')[0]
            .split('?')[0]
            .split('/')
            .filter(Boolean);
    }

    var currentSegments = urlSegments(location.href);
    var adminRootDepth = typeof admin_url !== 'undefined' ? urlSegments(admin_url).length : 1;

    var $best = null;
    var bestScore = 0;
    var bestExtra = Infinity;

    $sideMenu.find('li a[href]').each(function () {
        var raw = $(this).attr('href');
        if (!raw || raw === '#' || raw.indexOf('javascript:') === 0) {
            return;
        }

        var segments = urlSegments(this.href);
        var score = 0;
        while (
            score < segments.length &&
            score < currentSegments.length &&
            segments[score] === currentSegments[score]
        ) {
            score++;
        }

        // Must match beyond the admin root, otherwise the dashboard
        // link would "win" on every admin page
        if (score <= adminRootDepth) {
            return;
        }

        // Prefer deepest match; on ties prefer the href with the
        // fewest unmatched trailing segments (closest to exact prefix)
        var extra = segments.length - score;
        if (score > bestScore || (score === bestScore && extra < bestExtra)) {
            bestScore = score;
            bestExtra = extra;
            $best = $(this);
        }
    });

    if ($best) {
        $best.parents('li').not('.quick-links').addClass('active');
        $best.attr('aria-expanded', true);
        $best.parents('ul.nav-second-level')
            .addClass('in')
            .attr('aria-expanded', true);
        $best.parents('li').children('a[aria-expanded]').attr('aria-expanded', true);
    }
});
</script>
<?php app_admin_footer(); ?>