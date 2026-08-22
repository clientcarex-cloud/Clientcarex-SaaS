<?php
defined('BASEPATH') or exit('No direct script access allowed');
?>

<div class="horizontal-scrollable-tabs panel-full-width-tabs">
    <div class="scroller arrow-left tw-mt-px"><i class="fa fa-angle-left"></i></div>
    <div class="scroller arrow-right tw-mt-px"><i class="fa fa-angle-right"></i></div>
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <?php
            // Define tab data as an array for better maintainability
            $tabs = [
                ['id' => 'general', 'label' => _l('settings_group_general')],
                ['id' => 'tenants_seed', 'label' => _l('perfex_saas_tenants_seed')],
                ['id' => 'modules', 'label' => _l('perfex_saas_settings_modules')],
                ['id' => 'services', 'label' => _l('perfex_saas_settings_services')],
                ['id' => 'integrations', 'label' => _l('perfex_saas_settings_integrations')],
                ['id' => 'api', 'label' => _l('perfex_saas_api')],
                ['id' => 'miscellaneous', 'label' => _l('perfex_saas_settings_miscellaneous')],
                ['id' => 'demo_instance', 'label' => _l('perfex_saas_demo_instance')],
            ];

            $tabs = hooks()->apply_filters('perfex_saas_settings_tab', $tabs);

            // Loop through the tabs to generate navigation
            foreach ($tabs as $tab) {
                $isActive = ($tab['id'] === 'general') ? 'active' : '';
            ?>
            <li role="presentation" class="<?php echo $isActive; ?>">
                <a href="#<?php echo $tab['id']; ?>" aria-controls="<?php echo $tab['id']; ?>" role="tab"
                    data-toggle="tab">
                    <?php echo $tab['label']; ?>
                </a>
            </li>
            <?php
            }
            ?>
        </ul>
    </div>
</div>

<div class="tab-content mtop30">
    <?php
    // Loop through the tab content files
    foreach ($tabs as $tab) {
        $tabId = $tab['id'];
        $tabFile = !empty($tab['file']) ? $tab['file'] : __DIR__ . '/' . $tabId . '.php';
        $isActive = ($tabId === 'general') ? 'active' : '';
    ?>
    <div role="tabpanel" class="tab-pane <?php echo $isActive; ?>" id="<?php echo $tabId; ?>">

        <?php

            hooks()->do_action('perfex_saas_before_settings_tab', $tab);

            if (file_exists($tabFile)) {
                require($tabFile);
            }

            hooks()->do_action('perfex_saas_after_settings_tab', $tab);
            ?>
    </div>
    <?php
    }
    ?>
</div>