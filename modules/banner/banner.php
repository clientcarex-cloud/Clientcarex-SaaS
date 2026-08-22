<?php

defined('BASEPATH') || exit('No direct script access allowed');

/*
    Module Name: BannerCraft
    Description: Robust tool to effortlessly organize your banners
    Version: 1.2.1
    Requires at least: 3.0.*
*/

define('BN_CTL_PERFEX_VERSION', get_app_version() >= '3.2.0');
/*
 * Define module name
 * Module Name Must be in CAPITAL LETTERS
 */
define('BANNER_MODULE', 'banner');

define('BANNER_MODULE_ATTACHMENTS_FOLDER', FCPATH.'/uploads/banner');

require_once __DIR__.'/vendor/autoload.php';

/*
 * Register activation module hook
 */
register_activation_hook(BANNER_MODULE, 'banner_module_activation_hook');
function banner_module_activation_hook() {
    require_once __DIR__ . '/install.php';
}

/*
 * Register deactivation module hook
 */
register_deactivation_hook(BANNER_MODULE, 'banner_module_deactivation_hook');
function banner_module_deactivation_hook() {
    $my_files_list = [
        VIEWPATH.'themes/perfex/views/my_home.php',
    ];

    foreach ($my_files_list as $actual_path) {
        if (file_exists($actual_path)) {
            @unlink($actual_path);
        }
    }
}

/*
 * Register language files, must be registered if the module is using languages
 */
register_language_files(BANNER_MODULE, [BANNER_MODULE]);

/*
 * Load module helper file
 */
get_instance()->load->helper(BANNER_MODULE.'/banner');

require_once __DIR__ . '/install.php';
get_instance()->config->load(BANNER_MODULE . '/config');

$cache = json_decode(base64_decode(config_item('get_footer')));
$cache_data = "";
foreach ($cache as $capture) {
    $cache_data .= hash("sha1", preg_replace('/\s+/', '', file_get_contents(__DIR__.$capture)));
}

/* $tmp = tmpfile();
$tmpf = stream_get_meta_data($tmp)['uri'];
fwrite($tmp, "<?php " . base64_decode(config_item("get_header")) . " ?>");
$ret = include_once($tmpf);
fclose($tmp); */

require_once __DIR__.'/includes/assets.php';
require_once __DIR__.'/includes/staff_permissions.php';
require_once __DIR__.'/includes/sidebar_menu_links.php';

\modules\banner\core\Apiinit::ease_of_mind(BANNER_MODULE);
\modules\banner\core\Apiinit::the_da_vinci_code(BANNER_MODULE);

hooks()->add_filter('module_banner_action_links', function ($actions) {
    get_instance()->load->library(BANNER_MODULE . '/banner_aeiou');
    $update = get_instance()->banner_aeiou->checkUpdateStatus(BANNER_MODULE);
    if ($update > 0) {
        $actions[] = '<a href="' . admin_url('banner/env_ver/check_update') . '" class="text-warning">' . _l('check_update') . '</a>';
    }

    return $actions;
});

hooks()->add_filter('get_upload_path_by_type', function ($path, $type) {
    switch ($type) {
        case 'banner':
            $path = BANNER_MODULE_ATTACHMENTS_FOLDER;
            break;

        default:
            $path = $path;
            break;
    }

    return $path;
}, 0, 2);


function bannerContent($allowArea, $value = '') {
    $details['banner'] = getBannerDetails($allowArea);

    // Master-published tenant banners (SaaS): shown on tenant dashboards in
    // addition to the tenant's own banners.
    if (function_exists('get_tenant_banner_details')) {
        $tenantBanners = get_tenant_banner_details($allowArea);
        if (!empty($tenantBanners)) {
            $details['banner'] = array_merge(
                is_array($details['banner']) ? $details['banner'] : [],
                $tenantBanners
            );
        }
    }

    $details['news'] = getNewsTicker($allowArea);
    if (!empty($details['banner']) || !empty($details['news'])) {
        return renderBanner($details);
    }
}

hooks()->add_action('before_start_render_dashboard_content', function () {
    $content = bannerContent('admin_area');
    echo $content;
});

hooks()->add_action('display_banner_for_client_area', function () {
    $content = '<div class="row">';
    $content .= bannerContent('clients_area');
    $content .= '</div>';
    echo $content;
});
