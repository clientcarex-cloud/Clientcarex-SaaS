<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<script>
"use strict";
/**
 * Handle single poral toast notification bridging.
 * When backend set toast or notifications, it got lost after we redirect to single portal bridge.
 * Below code transfer the notification to be properly shown after bridge view.
 * 
 * @todo The POST request (i.e instance removal ) notification seem to be auto transfer even after rediction but not 
 * toast after a GET request. So this is more helpul for GET request cross toasts message.
 */
<?php
    $current_view_alert_messages = function () {
        $alertclass = get_alert_class();
        if ($alertclass == '') return;

        $alert_message = '';
        $alert = get_instance()->session->flashdata('message-' . $alertclass);
        if (is_array($alert)) {
            foreach ($alert as $alert_data) {
                $alert_message .= '<span>' . $alert_data . '</span><br />';
            }
        } else {
            $alert_message .= $alert;
        }
        return ['class' => $alertclass, 'message' => $alert_message];
    };
    ?>
class SaasSinglePortalNotificationBrigde {

    static storageKey = 'last-error';

    static saveNotes() {
        let lastViewError = <?= json_encode($current_view_alert_messages()); ?>;
        sessionStorage.removeItem(this.storageKey);
        if (lastViewError?.message) {
            sessionStorage.setItem(this.storageKey, JSON.stringify(lastViewError));
        }
    }

    static alertSavedNotes() {
        let lastViewError = sessionStorage.getItem(this.storageKey);

        if (lastViewError) {
            lastViewError = JSON.parse(lastViewError);
        }

        sessionStorage.removeItem(this.storageKey);
        if (lastViewError?.class) {
            document.addEventListener("DOMContentLoaded", function() {
                $(".ps-alert-container").html(
                        `<div class="alert alert-${lastViewError.class}">${lastViewError.message}</div>`
                    )
                    .insertBefore('#greeting');
                alert_float(lastViewError.class, lastViewError.message);
            });
        }
    }
}
</script>