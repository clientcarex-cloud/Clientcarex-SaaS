<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * This widget add subdomain and custom domain input field into Perfex CRM registeration form
 * (i.e /authentiaction/register form) when default package allows it. 
 */
if ($CI->router->fetch_class() !== 'authentication' || $CI->router->fetch_method() !== 'register' || is_client_logged_in()) return;

$package = [];

// Check if we have selected plan in session
$package_slug = $CI->session->{perfex_saas_route_id_prefix('plan')} ?? '';
if (!empty($package_slug)) {
    $CI->db->where('slug', $package_slug);
    $package = $CI->perfex_saas_model->packages()[0] ?? [];
} else {
    // Use the default package
    $CI->db->where('is_default', 1);
    $package = $CI->perfex_saas_model->packages()[0] ?? [];
}

if (empty($package)) return;

$package = (object)$package;
$can_use_subdomain = (int)$package->metadata->enable_subdomain && (int)perfex_saas_get_options('perfex_saas_enable_subdomain_input_on_signup_form');
$can_use_customdomain = (int)$package->metadata->enable_custom_domain && (int)perfex_saas_get_options('perfex_saas_enable_customdomain_input_on_signup_form');
?>

<?php if ($can_use_subdomain || $can_use_customdomain) : ?>

<!-- containter to hold the input fields -->
<div class="form-group mtop15 register-saas-info-group" style="display: none;">
    <!-- slug -->
    <?= $can_use_subdomain ? render_input('slug', _l('perfex_saas_register_form_subdomain_id') . perfex_saas_form_label_hint('perfex_saas_create_company_slug_hint', perfex_saas_get_saas_default_host()), '', 'text', ['maxlength' => PERFEX_SAAS_MAX_SLUG_LENGTH], [], "text-left tw-mb-4 ", '') : ''; ?>

    <!-- custom domain -->
    <?= $can_use_customdomain ? render_input('custom_domain', _l('perfex_saas_register_form_custom_domain') . perfex_saas_form_label_hint('perfex_saas_custom_domain_hint'), '', 'text', [], [], "text-left tw-mb-4", '') : ''; ?>

    <!-- package slug -->
    <input type="hidden" value="<?= $package->slug; ?>" name="<?= perfex_saas_route_id_prefix('plan');?>" />
</div>


<!-- Widget javascript -->
<script>
/**
 * This function modify the register form to include subdomain and custom domain input fields
 *
 * @return void
 */
function bindDomainInputToRegisterationForm() {
    // New form layout: move widget into the placeholder container
    var $placeholder = $(".ho-subdomain-placeholder");
    if ($placeholder.length) {
        $(".register-saas-info-group").appendTo($placeholder).show();
        $placeholder.show();
    } else {
        // Fallback for old layout
        $(".register-saas-info-group").insertAfter($(".register-company-group"));
        $(".register-saas-info-group").show();
    }
    // Bind slug auto-fill to company name input (handles honeypot field names)
    var companySelector = "input[name=company]";
    if (!$(companySelector).length) {
        companySelector = "input[name=companymjxw]";
    }
    bindAndListenToSlugInput(".register-saas-info-group", companySelector);
}

// Bind
setTimeout(bindDomainInputToRegisterationForm, 200);

// Backup call incase content not in DOM during the time out call
window.addEventListener("DOMContentLoaded", () => {
    if (!$("form .register-saas-info-group").length) bindDomainInputToRegisterationForm()
});
</script>

<?php endif; ?>

<!-- Always inject the package plan hidden field into the registration form via JS.
     This ensures ps_plan is included in the POST data even when subdomain/custom domain
     inputs are disabled (which skips the conditional block above). -->
<script>
(function() {
    var planName = "<?= perfex_saas_route_id_prefix('plan'); ?>";
    var planValue = "<?= e($package->slug); ?>";
    function injectPlanField() {
        var form = document.getElementById('register-form');
        if (form && !form.querySelector('input[name="' + planName + '"]')) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = planName;
            input.value = planValue;
            form.appendChild(input);
        }
    }
    // Try immediately, retry on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectPlanField);
    } else {
        injectPlanField();
    }
    // Backup with setTimeout in case form renders late
    setTimeout(injectPlanField, 300);
})();
</script>