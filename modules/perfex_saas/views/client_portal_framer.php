<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="tw-h-screen" style="height: 100vh;">
        <iframe class="tw-w-full tw-h-full" src="<?= $url; ?>"></iframe>
    </div>
</div>
<script>
window.onmessage = function(event) {

    if (event.data.message === "closedBridge") { // When client has not subscription again 
        let frameUrl = new URL(event.data.current_url ?? window.location.href);
        frameUrl.searchParams.set('paying_outstanding', '1');
        window.location.href = frameUrl.toString();
    }

    if (event.data.message === "home") {
        window.location.href = "<?= perfex_saas_default_base_url('?subscription'); ?>";
    }

    if (event.data.message === "openInParent") {
        window.location.href = event.data.value;
    }

    if (event.data.message === "navigated") {
        let destUrl = new URL(event.data.value ?? window.location.href);
        let currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('redirect', destUrl.pathname + destUrl.search);
        history.replaceState({}, '', currentUrl.toString());
    }
};
</script>



<?php init_tail(); ?>
</body>

</html>