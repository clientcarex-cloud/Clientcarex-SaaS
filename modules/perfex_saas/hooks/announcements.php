<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$is_tenant) {

    /**
     * Add show announcement to SaaS settings.
     */
    hooks()->add_action('perfex_saas_after_settings_tab', 'perfex_saas_render_announcement_settings');

    /**
     * Path client alerts file to show independed on navigationEnabled status.
     */
    hooks()->add_action('perfex_saas_after_installer_run', 'perfex_saas_patch_client_template_alerts');
}


if ($is_tenant) {
    hooks()->add_action('before_start_render_dashboard_content', function () {
        // Read and show notifications from super admin for clients
        perfex_saas_render_tenant_dashboard_annoucements();
    }, 1);

    /**
     * Handle the announcement dismissal.
     * This will hijack the dismisal query with dismiss_tenant_announcement param
     * and dismiss on super admin for the given client
     */
    hooks()->add_action('admin_init', function () {
        $CI = &get_instance();
        if ($CI->input->get('dismiss_tenant_announcement')) {

            $id = (int) $CI->input->get('id');
            $contact_id = (int)$CI->input->get('contact_id');

            $table = perfex_saas_master_db_prefix() . 'dismissed_announcements';
            $sql = "INSERT INTO `$table` (`announcementid`, `userid`, `staff`) VALUES ($id, $contact_id, 0)";
            perfex_saas_raw_query($sql, []);

            redirect(admin_url());
            exit;
        }
    });
}

/**
 * Ensure notification is shown and independent on navigation enabled status.
 * 
 * Author potentially made mistake by include $navigationEnabled as clause to render 
 * notification on the client portal which should not be.
 * 
 * @todo This should be reported to CRM author for permanent fix and resolution.
 * @return void
 */
function perfex_saas_patch_client_template_alerts()
{
    $file = VIEWPATH . '/themes/' . get_option('clients_default_theme') . '/' . 'template_parts/alerts.php';
    replace_in_file($file, 'is_client_logged_in() && $navigationEnabled', 'is_client_logged_in()');
}


if (!function_exists('perfex_saas_tenant_dashboard_annoucements')) {

    /**
     * Helper function to get all announcements for user
     * @param  boolean $staff Is this client or staff
     * @return array
     */
    function perfex_saas_tenant_dashboard_annoucements()
    {
        if (perfex_saas_tenant_get_super_option('perfex_saas_show_client_announcements_on_tenant') != '1')
            return;

        $CI = &get_instance();
        $CI->db->select();


        $client_id = perfex_saas_tenant()->clientid ?? 0;
        if (!$client_id) {
            return [];
        }

        $dbprefix = perfex_saas_master_db_prefix();

        $sql = "SELECT c.id FROM " . $dbprefix . "contacts c WHERE c.userid = :client_id AND c.is_primary = 1 LIMIT 1";
        $contact = perfex_saas_raw_query_row($sql, [], true, false, [':client_id' => $client_id]);
        $contact_id = $contact->id;

        $sql = "
            SELECT a.*
            FROM " . $dbprefix . "announcements a
            WHERE a.showtousers = 1
            AND a.announcementid NOT IN (
                SELECT da.announcementid
                FROM " . $dbprefix . "dismissed_announcements da
                WHERE da.staff = 0
                AND da.userid = $contact_id
            )
            ORDER BY a.dateadded DESC
        ";

        $announcements = perfex_saas_raw_query($sql, [], true, false, null, false, false);
        return [$announcements, $contact];
    }
}

/**
 * Render the saas client announcement on the tenant instance admin dashboard.
 *
 * @return void
 */
function perfex_saas_render_tenant_dashboard_annoucements()
{
    list($_announcements, $contact) = perfex_saas_tenant_dashboard_annoucements();
    if (empty($_announcements) || empty($contact)) return;

    if (!is_staff_member()) return;
?>
<div class="col-lg-12 tw-mt-1.5">
    <div>
        <?php foreach ($_announcements as $__announcement) {
                if (!$__announcement) continue;
                $__announcement = (array)$__announcement;
            ?>
        <div class="alert alert-info alert-dismissible announcement tc-content tw-mb-3" role="alert">
            <a href="<?= admin_url('misc/dismiss_announcement/0?dismiss_tenant_announcement=1&id=' . $__announcement['announcementid'] . '&contact_id=' . $contact->id); ?>"
                class="alert-link pull-right" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </a>

            <h4 class="alert-title tw-mb-0 tw-flex tw-items-center tw-space-x-2">
                <i class="fa-solid fa-bullhorn"></i>
                <span><?= _l('announcement'); ?>
                    -</span>
                <span class="tw-text-xs tw-font-medium">
                    <?= _dt($__announcement['dateadded']); ?>
                </span>
            </h4>
            <?php if ($__announcement['showname'] == 1) { ?>
            <p class="tw-text-sm !tw-my-0 !-tw-mb-1.5">
                <?= e(_l('announcement_from')) . ' <span class="tw-font-medium">' . e($__announcement['userid']) . '</span>'; ?>
            </p>
            <?php } ?>
            <hr />
            <h4 class="alert-title">
                <?= e($__announcement['name']); ?>
            </h4>
            <div class="[&>p:last-child]:tw-mb-0">
                <?= check_for_links($__announcement['message']); ?>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<?php
}

/**
 * Render the Setting UI for the option to toggle this hook
 *
 * @param array $tab
 * @return void
 */
function perfex_saas_render_announcement_settings($tab)
{
    if ($tab['id'] === 'general') {
        // render settings option allowing showing client notification on tenant dashboard
        $key = 'perfex_saas_show_client_announcements_on_tenant';
        render_yes_no_option($key, $key);

    ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var $targetGroup = $('input[name="settings[perfex_saas_enable_client_menu_in_interim_pages]"]').closest(
        '.form-group');
    var $moveGroup = $('input[name="settings[perfex_saas_show_client_announcements_on_tenant]"]').closest(
        '.form-group');

    // Move the group after target
    $moveGroup.insertAfter($targetGroup);
});
</script>
<?php
    }
}