<?php

defined('BASEPATH') or exit('No direct script access allowed');
/**
 * This file is used to hide the default modules tabs in the lead profile modal if they are disabled for the tenant.
 * The tabs are hidden using CSS and removed from the DOM using JavaScript after a delay to ensure they are not visible to the user.
 */
hooks()->add_action('lead_modal_profile_bottom', function () {
    if (perfex_saas_is_tenant()) {
        $disabled_core_modules  = perfex_saas_tenant_disabled_default_modules();

        $selectors = [];

        if (in_array('proposals', $disabled_core_modules)) {
            $selectors[] = '[aria-controls="tab_proposals_leads"]';
        }

        if (in_array('tasks', $disabled_core_modules)) {
            $selectors[] = '[aria-controls="tab_tasks_leads"]';
        }

        if (!empty($selectors)) {
            $selectors = implode(',', $selectors);
            echo '<style>
                    li:has(' . $selectors . ') {
                        display: none !important;
                     }
            </style> 
            <script>
                setTimeout(function() {
                    $("' . addslashes($selectors) . '").parent("li").remove();
                }, 3000);
            </script>';
        }
    }
});