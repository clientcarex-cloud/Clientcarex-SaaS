<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * This file shows the user of demo (either the tenant demo or when using the whole installation as demo)
 * the seconds left to reset. It shows when it min minutes to reset.
 */

hooks()->add_action('app_admin_footer', 'perfex_saas_demo_will_reset_notification');
hooks()->add_action('app_customers_footer', 'perfex_saas_demo_will_reset_notification');

function perfex_saas_demo_will_reset_notification()
{
    $reset_time = null;
    $reset_type = null;

    // Tenant demo instance logic
    if (perfex_saas_is_tenant() && perfex_saas_tenant_is_demo_instance()) {
        $remaining_seconds = perfex_saas_demo_seconds_until_reset();
        if ($remaining_seconds !== null) {
            $reset_time = time() + $remaining_seconds;
            $reset_type = 'tenant';
        }
    }

    // Global demo site logic (using hourly reset)
    if (defined('SAAS_DEMO_SITE')) {
        $remaining_seconds = perfex_saas_seconds_until_next_hour();
        if ($remaining_seconds !== null) {
            $reset_time = time() + $remaining_seconds;
            $reset_type = 'global';
        }
    }

    // Nothing to show → exit
    if (!$reset_time) {
        return;
    }
?>

<div id="demo-reset-container" class="tw-flex tw-100 tw-justify-center" style="display: none;">
    <div id="demo-reset-banner" style="position: fixed; justify-self:center; top: 0px; padding: 10px; max-width: 45%;"
        class="alert alert-warning tw-font-medium">
        <?php echo _l('perfex_saas_demo_reset_banner_message', '<span id="demo-timer" class="text-danger tw-font-bold"></span>'); ?>
    </div>
</div>

<script>
'use strict';
(function() {

    // Server time when reset will occur (Unix timestamp in seconds)
    var resetTime = <?php echo (int)$reset_time; ?>;
    var container = document.getElementById('demo-reset-container');
    var timerSpan = document.getElementById('demo-timer');

    // Threshold: show banner when 10 minutes or less remain
    var showThresholdSeconds = 10 * 60;
    var intervalId = null;
    var currentInterval = null;

    function getOptimalInterval(remaining) {
        // When banner is hidden (more than 10 minutes), check every minute
        if (remaining > showThresholdSeconds) {
            return 60000; // 1 minute
        }

        // Final minute: update every second for dramatic effect
        return 1000; // 1 second
    }

    function stopTimer() {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
            currentInterval = null;
        }
    }

    function updateTimer() {
        // Calculate remaining seconds based on server reset time
        var now = Math.floor(Date.now() / 1000);
        var remaining = resetTime - now;

        // Stop timer if time has passed
        if (remaining < 0) {
            //container.style.display = 'none';
            stopTimer();
            return;
        }

        // Hide container if more than threshold
        if (remaining > showThresholdSeconds) {
            container.style.display = 'none';

            // Adjust interval for hidden state
            var optimalInterval = getOptimalInterval(remaining);
            if (currentInterval !== optimalInterval) {
                stopTimer();
                intervalId = setInterval(updateTimer, optimalInterval);
                currentInterval = optimalInterval;
            }
            return;
        }

        // Show container and update timer
        container.style.display = 'flex';

        var minutesLeft = Math.floor(remaining / 60);
        var secondsLeft = remaining % 60;

        timerSpan.textContent =
            minutesLeft + 'm ' + (secondsLeft < 10 ? '0' : '') + secondsLeft + 's';

        // Adjust interval based on remaining time
        var optimalInterval = getOptimalInterval(remaining);
        if (currentInterval !== optimalInterval) {
            stopTimer();
            intervalId = setInterval(updateTimer, optimalInterval);
            currentInterval = optimalInterval;
        }
    }

    // Initial update
    updateTimer();

    // Start with appropriate interval if timer wasn't stopped
    if (intervalId === null) {
        var now = Math.floor(Date.now() / 1000);
        var remaining = resetTime - now;
        if (remaining >= 0) {
            var optimalInterval = getOptimalInterval(remaining);
            intervalId = setInterval(updateTimer, optimalInterval);
            currentInterval = optimalInterval;
        }
    }

})();
</script>

<?php } ?>