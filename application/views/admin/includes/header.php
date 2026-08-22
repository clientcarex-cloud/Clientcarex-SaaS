<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
/* ── Top Bar User Profile ── */
.topbar-user-profile > a.profile {
    display: flex !important;
    align-items: center;
    gap: 10px;
    height: 57px;
    padding: 0 12px !important;
    background: transparent !important;
    color: #374151 !important;
    transition: color 0.15s ease;
}
.topbar-user-profile > a.profile:hover,
.topbar-user-profile > a.profile:focus {
    color: #111827 !important;
}
.topbar-avatar {
    flex-shrink: 0;
    line-height: 0;
    position: relative;
}
.topbar-avatar .staff-profile-image-small {
    margin-left: 0 !important;
}
.topbar-user-meta {
    line-height: 1.25;
    text-align: left;
    position: relative;
}
.topbar-user-name {
    display: block;
    font-weight: 600;
    font-size: 13px;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.topbar-user-email {
    display: block;
    font-size: 12px;
    color: #6b7280;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.topbar-profile-menu {
    right: 0;
    left: auto;
    min-width: 240px;
}

/* Gradient ring around superadmin avatar */
.superadmin-avatar-ring {
    display: inline-flex;
    padding: 2px;
    border-radius: 50%;
    /* HealthO brand ring: navy → cyan → green (logo palette) */
    background: linear-gradient(135deg, #122B5C, #00B4D8, #2ECC71);
    flex-shrink: 0;
}
.superadmin-avatar-ring .staff-profile-image-small {
    border-radius: 50% !important;
    border: 2px solid #fff !important;
}

/* ── Profile Dropdown Menu ── */
.profile-dropdown-menu {
    padding: 4px 0 !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06) !important;
    border: 1px solid rgba(0,0,0,0.06) !important;
}
/* Force all list items to clear floats (fixes Language pull-left leak) */
.profile-dropdown-menu > li {
    clear: both !important;
    display: list-item !important;
    float: none !important;
}
.profile-dropdown-menu > li > a {
    padding: 6px 14px !important;
    font-size: 13px;
    color: #374151;
    transition: background 0.15s ease, color 0.15s ease;
    display: flex !important;
    align-items: center;
}
.profile-dropdown-menu > li > a:hover {
    background: #f3f4f6 !important;
    color: #111827;
}
.profile-dropdown-menu > li > a i {
    width: 16px;
    text-align: center;
    flex-shrink: 0;
}
.profile-dropdown-menu > .divider {
    clear: both !important;
    display: block !important;
    float: none !important;
    margin: 4px 12px !important;
    border-top: 1px solid #e5e7eb !important;
    height: 0;
    overflow: hidden;
}

/* Language submenu: open to the LEFT (dropdown sits at right screen edge) */
@media (min-width: 769px) {
    .topbar-profile-menu .dropdown-submenu > .dropdown-menu {
        left: auto;
        right: 100%;
        margin-left: 0;
        margin-right: -1px;
        border-radius: 6px 0 6px 6px;
    }
    .topbar-profile-menu .dropdown-submenu > a:after {
        margin-left: auto;
        margin-right: 0;
        margin-top: 0;
        border-width: 5px 5px 5px 0;
        border-color: transparent #9ca3af transparent transparent;
    }
    .topbar-profile-menu .dropdown-submenu:hover > a:after {
        border-right-color: #6b7280;
    }
}

/* Superadmin crown badge card in dropdown */
.superadmin-header-badge {
    padding: 6px 12px !important;
}
.superadmin-badge-card {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1E3D7B 0%, #122B5C 45%, #00B4D8 125%);
    box-shadow: 0 3px 8px rgba(18, 43, 92, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.superadmin-badge-card i {
    font-size: 13px;
    color: #fbbf24;
    filter: drop-shadow(0 0 3px rgba(251,191,36,0.5));
}

/* ── My Account Section in Dropdown ── */
.saas-account-header {
    clear: both !important;
    float: none !important;
    display: block !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    color: #9ca3af !important;
    padding: 6px 14px 3px !important;
}
.saas-account-item > a {
    font-size: 13px !important;
}
</style>
<div id="header">
    <button type="button"
        class="hide-menu tw-inline-flex tw-bg-transparent tw-border-0 tw-p-1 tw-mt-4 hover:tw-bg-neutral-600/10 tw-text-neutral-600 hover:tw-text-neutral-800 focus:tw-text-neutral-800 focus:tw-outline-none tw-rounded-md tw-mx-4 ltr:md:tw-ml-4 rtl:md:tw-mr-4 ltr:tw-float-left  rtl:tw-float-right">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="tw-h-4 tw-w-4 tw-text-current">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M2.25 18.003h19.5m-19.5-6h19.5m-19.5-6h19.5"></path>
        </svg>
    </button>
    <nav>
        <div class="tw-flex tw-justify-between">
            <div class="tw-overflow-hidden tw-shrink-0">
                <div id="logo"
                    class="tw-h-[57px] tw-hidden md:tw-flex tw-items-center [&_img]:tw-h-9 [&_img]:tw-w-auto">
                    <?php $logo = get_admin_header_logo_url(); ?>
                    <?php if (! $logo) { ?>
                    <a class="logo logo-text tw-text-2xl tw-font-semibold"
                        href="<?= hooks()->apply_filters('admin_header_logo_href', admin_url()); ?>">
                        <?= e(get_option('companyname')); ?>
                    </a>
                    <?php } else { ?>
                    <a class="logo"
                        href="<?= hooks()->apply_filters('admin_header_logo_href', admin_url()); ?>">
                        <img src="<?= e($logo); ?>"
                            class="img-responsive"
                            alt="<?= e(get_option('companyname')); ?>" />
                    </a>
                    <?php } ?>
                </div>
            </div>
            <div class="tw-flex tw-flex-1 sm:tw-flex-initial">
                <div id="top_search"
                    class="tw-inline-flex tw-relative dropdown sm:tw-ml-1.5 sm:tw-mr-3 tw-max-w-xl tw-flex-auto tw-group/top-search"
                    data-toggle="tooltip" data-placement="bottom"
                    data-title="<?= _l('search_by_tags'); ?>">
                    <input type="search" id="search_input"
                        class="ltr:tw-pr-4 ltr:tw-pl-9 rtl:tw-pr-9 rtl:tw-pl-4 tw-ml-1 tw-mt-2 focus:!tw-ring-0 tw-w-full !tw-placeholder-neutral-500 !tw-shadow-none tw-text-neutral-800 focus:!tw-placeholder-neutral-600 hover:!tw-placeholder-neutral-600 sm:tw-w-[350px] tw-h-[38px] tw-border-0 tw-border-solid !tw-border-white !tw-bg-neutral-100 !tw-rounded-lg"
                        placeholder="Search anything..."
                        autocomplete="off">
                    <div id="top_search_button" class="tw-absolute rtl:tw-right-2 ltr:tw-left-2 tw-top-2.5">
                        <button
                            class="tw-outline-none tw-border-0 tw-p-2 tw-text-neutral-400 group-focus-within/top-search:tw-text-neutral-600 tw-bg-transparent">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                    <div id="search_results">
                    </div>
                    <ul class="dropdown-menu search-results animated fadeIn search-history" id="search-history">
                    </ul>

                </div>
                <?php /* Removed: Quick Create (+) button from header
                <ul class="nav navbar-nav visible-md visible-lg">
                    ...quick create button...
                </ul>
                */ ?>
            </div>

            <div class="mobile-menu tw-shrink-0 ltr:tw-ml-4 rtl:tw-mr-4">
                <button type="button"
                    class="navbar-toggle visible-md visible-sm visible-xs mobile-menu-toggle collapsed tw-ml-1.5 tw-text-neutral-600 hover:tw-text-neutral-800"
                    data-toggle="collapse" data-target="#mobile-collapse" aria-expanded="false">
                    <i class="fa fa-chevron-down fa-lg"></i>
                </button>
                <ul class="mobile-icon-menu tw-inline-flex tw-mt-5">
                    <?php
               // To prevent not loading the timers twice
            if (is_mobile()) { ?>
                    <?php /* Removed: Notifications icon from mobile header
                    <li class="dropdown notifications-wrapper header-notifications tw-block ltr:tw-mr-3 rtl:tw-ml-3">
                        <?php $this->load->view('admin/includes/notifications'); ?>
                    </li>
                    */ ?>
                    <li class="header-timers ltr:tw-mr-1.5 rtl:tw-ml-1.5">
                        <a href="#" id="top-timers" class="dropdown-toggle top-timers tw-block tw-h-5 tw-w-5"
                            data-toggle="dropdown">
                            <i
                                class="fa-regular fa-clock fa-lg tw-text-neutral-500 group-hover:tw-text-neutral-800 tw-shrink-0<?= count($startedTimers) > 0 ? ' tw-animate-spin-slow' : ''; ?>"></i>
                            <span
                                class="tw-leading-none tw-px-1 tw-py-0.5 tw-text-xs bg-success tw-z-10 tw-absolute tw-rounded-full -tw-right-3 -tw-top-2 tw-min-w-[18px] tw-min-h-[18px] tw-inline-flex tw-items-center tw-justify-center icon-started-timers<?= $totalTimers = count($startedTimers) == 0 ? ' hide' : ''; ?>"><?= count($startedTimers); ?></span>
                        </a>
                        <ul class="dropdown-menu animated fadeIn started-timers-top width300" id="started-timers-top">
                            <?php $this->load->view('admin/tasks/started_timers', ['startedTimers' => $startedTimers]); ?>
                        </ul>
                    </li>
                    <?php } ?>
                </ul>
                <div class="mobile-navbar collapse" id="mobile-collapse" aria-expanded="false" style="height: 0px;"
                    role="navigation">
                    <ul class="nav navbar-nav">
                        <li class="header-my-profile"><a
                                href="<?= admin_url('profile'); ?>">
                                <?= _l('nav_my_profile'); ?>
                            </a>
                        </li>
                        <li class="header-my-timesheets"><a
                                href="<?= admin_url('staff/timesheets'); ?>">
                                <?= _l('my_timesheets'); ?>
                            </a>
                        </li>
                        <?php /* Removed: Edit Profile link (merged into My Profile page)
                        <li class="header-edit-profile"><a
                                href="<?= admin_url('staff/edit_profile'); ?>">
                                <?= _l('nav_edit_profile'); ?>
                            </a>
                        </li>
                        */ ?>
                        <?php if (is_admin()) { ?>
                        <li class="header-biz-profile"><a
                                href="<?= admin_url('biz_profile'); ?>">
                                Business Profile
                            </a>
                        </li>
                        <?php } ?>
                        <?php if (is_staff_member()) { ?>
                        <li class="header-newsfeed">
                            <a href="#" class="open_newsfeed mobile">
                                <?= _l('whats_on_your_mind'); ?>
                            </a>
                        </li>
                        <?php } ?>
                        <li class="header-logout">
                            <a href="#" onclick="logout(); return false;">
                                <?= _l('nav_logout'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <ul class="nav navbar-nav navbar-right -tw-mt-px">
                <?php do_action_deprecated('after_render_top_search', [], '3.0.0', 'admin_navbar_start'); ?>
                <?php hooks()->do_action('admin_navbar_start'); ?>
                <?php /* Removed: Settings link from header
                <?php if (staff_can('view', 'settings')) { ?>
                <li>
                    <a
                        href="<?= admin_url('settings'); ?>">
                        <?= _l('settings'); ?>
                    </a>
                </li>
                <?php } ?>
                */ ?>
                <?php /* Removed: Newsfeed (share) icon from header
                <?php if (is_staff_member()) { ?>
                <li class="icon header-newsfeed -tw-mr-1.5">
                    ...
                </li>
                <?php } ?>
                */ ?>

                <?php /* Removed: Todo (checkbox) icon from header
                <li class="icon header-todo">
                    ...
                </li>
                */ ?>

                <?php /* Removed: My Timesheets (timer clock) icon from header
                <li class="icon header-timers timer-button tw-relative ltr:tw-mr-1.5 rtl:tw-ml-1.5"
                    data-placement="bottom" data-toggle="tooltip"
                    data-title="<?= _l('my_timesheets'); ?>">
                    <a href="#" id="top-timers" class="top-timers !tw-px-0 tw-group" data-toggle="dropdown">
                        <span class="tw-inline-flex tw-items-center tw-justify-center tw-h-8 tw-w-9 -tw-mt-1.5">
                            <i
                                class="fa-regular fa-clock fa-lg tw-text-neutral-500 group-hover:tw-text-neutral-800 tw-shrink-0<?= count($startedTimers) > 0 ? ' tw-animate-spin-slow' : ''; ?>"></i>
                        </span>
                        <span
                            class="tw-leading-none tw-px-1 tw-py-0.5 tw-text-xs bg-success tw-z-10 tw-absolute tw-rounded-full -tw-right-1.5 tw-top-2 tw-min-w-[18px] tw-min-h-[18px] tw-inline-flex tw-items-center tw-justify-center icon-started-timers<?= $totalTimers = count($startedTimers) == 0 ? ' hide' : ''; ?>">
                            <?= count($startedTimers); ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu animated fadeIn started-timers-top width300" id="started-timers-top">
                        <?php $this->load->view('admin/tasks/started_timers', ['startedTimers' => $startedTimers]); ?>
                    </ul>
                </li>
                */ ?>

                <?php /* Removed: Notifications icon from header
                <li class="icon dropdown tw-relative tw-block notifications-wrapper header-notifications rtl:tw-ml-3"
                    data-toggle="tooltip"
                    title="<?= _l('nav_notifications'); ?>"
                    data-placement="bottom">
                    <?php $this->load->view('admin/includes/notifications'); ?>
                </li>
                */ ?>

                <?php $isSuperAdmin = is_admin(); ?>
                <li class="dropdown topbar-user-profile">
                    <a href="#"
                        class="dropdown-toggle profile"
                        data-toggle="dropdown" aria-expanded="false">
                        <span class="topbar-avatar <?= $isSuperAdmin ? 'superadmin-avatar-ring' : '' ?>">
                            <?= staff_profile_image($current_user->staffid, ['img', 'img-responsive', 'staff-profile-image-small']); ?>
                        </span>
                        <span class="topbar-user-meta hidden-xs hidden-sm">
                            <span class="topbar-user-name">
                                <?= get_staff_full_name(); ?>
                            </span>
                            <span class="topbar-user-email">
                                <?= get_staff()->email; ?>
                            </span>
                        </span>
                    </a>
                    <ul class="dropdown-menu profile-dropdown-menu topbar-profile-menu">
                        <?php if ($isSuperAdmin) { ?>
                        <li class="superadmin-header-badge">
                            <div class="superadmin-badge-card">
                                <i class="fa fa-crown"></i>
                                <span>Super Admin</span>
                            </div>
                        </li>
                        <li class="divider"></li>
                        <?php } ?>
                        <li class="header-my-profile"><a
                                href="<?= admin_url('profile'); ?>"><i class="fa fa-user tw-mr-2 tw-text-neutral-400"></i><?= _l('nav_my_profile'); ?></a>
                        </li>
                        <li class="header-my-timesheets"><a
                                href="<?= admin_url('staff/timesheets'); ?>"><i class="fa-regular fa-clock tw-mr-2 tw-text-neutral-400"></i><?= _l('my_timesheets'); ?></a>
                        </li>
                        <?php
                        // Customer Success — shown to ALL logged-in staff (not just super admins)
                        // when the SaaS client bridge is enabled on this tenant. Placed directly
                        // under My Timesheets. The link magic-auths into the Customer Success portal.
                        $showCustomerSuccess = function_exists('perfex_saas_tenant') && perfex_saas_tenant()
                            && function_exists('perfex_saas_tenant_is_enabled') && perfex_saas_tenant_is_enabled('client_bridge');
                        if ($showCustomerSuccess) {
                            $csHref   = admin_url('billing/my_account?redirect=clients/pro_tickets');
                            $csTarget = '';
                            $csTenant = perfex_saas_tenant();
                            $csCrossDomain = perfex_saas_tenant_is_enabled('cross_domain_bridge');
                            if (($csTenant->http_identification_type ?? '') === (defined('PERFEX_SAAS_TENANT_MODE_DOMAIN') ? PERFEX_SAAS_TENANT_MODE_DOMAIN : '') && !$csCrossDomain) {
                                $csTarget = ' target="_blank"';
                            }
                            // Unread badge value (super admins get a live count; other staff default to 0/hidden)
                            $csBadge = 0;
                            foreach (($GLOBALS['perfex_saas_profile_account_items'] ?? []) as $csItem) {
                                if (($csItem['slug'] ?? '') === 'saas_client_tickets' && isset($csItem['badge']['value']) && $csItem['badge']['value'] !== '') {
                                    $csBadge = $csItem['badge']['value'];
                                    break;
                                }
                            }
                        ?>
                        <li class="header-customer-success saas-account-item-saas_client_tickets"><a
                                href="<?= e($csHref); ?>"<?= $csTarget; ?>><i class="fa-solid fa-hand-holding-heart tw-mr-2 saas-cs-icon"></i><?= e(_l('perfex_saas_customer_success')); ?><span class="badge pull-right saas-support-badge" style="<?= (int) $csBadge > 0 || $csBadge === '99+' ? '' : 'display:none;'; ?>"><?= e($csBadge); ?></span></a>
                        </li>
                        <?php } ?>
                        <?php /* Removed: Edit Profile link (merged into My Profile page)
                        <li class="header-edit-profile"><a
                                href="<?= admin_url('staff/edit_profile'); ?>"><i class="fa fa-cog tw-mr-2 tw-text-neutral-400"></i><?= _l('nav_edit_profile'); ?>
                            </a>
                        </li>
                        */ ?>
                        <?php /* Removed: Language submenu from profile dropdown
                        <?php if (! is_language_disabled()) { ?>
                        <li class="dropdown-submenu pull-left header-languages">
                            <a href="#"
                                tabindex="-1"><i class="fa fa-globe tw-mr-2 tw-text-neutral-400"></i><?= _l('language'); ?></a>
                            <ul class="dropdown-menu dropdown-menu">
                                <li
                                    class="<?= $current_user->default_language == '' ? 'active' : ''; ?>">
                                    <a
                                        href="<?= admin_url('staff/change_language'); ?>">
                                        <?= _l('system_default_string'); ?>
                                    </a>
                                </li>
                                <?php foreach ($this->app->get_available_languages() as $user_lang) { ?>
                                <li
                                    class="<?= $current_user->default_language == $user_lang ? 'active' : ''; ?>">
                                    <a
                                        href="<?= admin_url('staff/change_language/' . $user_lang); ?>">
                                        <?= e(ucfirst($user_lang)); ?>
                                    </a>
                                    <?php } ?>
                            </ul>
                        </li>
                        <?php } ?>
                        */ ?>
                        <?php
                        // Render SaaS "My Account" items merged into profile dropdown
                        $saasAccountItems = $GLOBALS['perfex_saas_profile_account_items'] ?? [];

                        // Remove "Billing", "Company details" and "Customer Success" from the
                        // My Account section. Customer Success is now rendered above, directly
                        // under My Timesheets, for all staff.
                        $saasAccountItems = array_values(array_filter($saasAccountItems, function ($item) {
                            return !in_array($item['slug'] ?? '', ['saas_client_subscription', 'saas_client_company_info', 'saas_client_tickets'], true);
                        }));

                        // Move "Business Profile" into My Account as the 2nd item (after Overview) for super admins
                        if ($isSuperAdmin && !empty($saasAccountItems)) {
                            array_splice($saasAccountItems, 1, 0, [[
                                'slug' => 'biz_profile',
                                'name' => 'Business Profile',
                                'href' => admin_url('biz_profile'),
                                'icon' => 'fa fa-building',
                            ]]);
                        }

                        if (!empty($saasAccountItems)) { ?>
                        <li class="divider"></li>
                        <li class="dropdown-header saas-account-header">
                            <i class="fa fa-briefcase tw-mr-1"></i> <?= function_exists('_l') ? _l('perfex_saas_my_account') : 'My Account'; ?>
                        </li>
                        <?php foreach ($saasAccountItems as $accountItem) {
                            $itemIcon = $accountItem['icon'] ?? 'fa fa-angle-right';
                            $itemTarget = '';
                            // Check if cross-domain bridge requires target blank
                            if (function_exists('perfex_saas_tenant') && perfex_saas_tenant()) {
                                $tenant = perfex_saas_tenant();
                                $support_cross_domain = function_exists('perfex_saas_tenant_is_enabled') ? perfex_saas_tenant_is_enabled('cross_domain_bridge') : false;
                                if (($tenant->http_identification_type ?? '') === (defined('PERFEX_SAAS_TENANT_MODE_DOMAIN') ? PERFEX_SAAS_TENANT_MODE_DOMAIN : '') && !$support_cross_domain) {
                                    $itemTarget = ' target="_blank"';
                                }
                            }
                        ?>
                        <?php $itemSlug = $accountItem['slug'] ?? ''; ?>
                        <li class="saas-account-item saas-account-item-<?= e($itemSlug); ?>"><a
                                href="<?= e($accountItem['href']); ?>"<?= $itemTarget; ?>><i class="<?= e($itemIcon); ?> tw-mr-2 <?= $itemSlug === 'saas_client_tickets' ? 'saas-cs-icon' : 'tw-text-neutral-400'; ?>"></i><?= e(_l($accountItem['name'], '', false)); ?><?php
                                if ($itemSlug === 'saas_client_tickets') {
                                    $badgeVal = (isset($accountItem['badge']['value']) && $accountItem['badge']['value'] !== '') ? $accountItem['badge']['value'] : 0;
                                    ?><span class="badge pull-right saas-support-badge" style="<?= (int) $badgeVal > 0 || $badgeVal === '99+' ? '' : 'display:none;'; ?>"><?= e($badgeVal); ?></span><?php
                                } elseif (isset($accountItem['badge']['value']) && $accountItem['badge']['value'] !== '') {
                                    ?><span class="badge pull-right"><?= e($accountItem['badge']['value']); ?></span><?php
                                }
                                ?></a>
                        </li>
                        <?php } ?>
                        <?php } ?>
                        <li class="divider"></li>
                        <li class="header-logout">
                            <a href="#"
                                onclick="logout(); return false;"><i class="fa fa-sign-out-alt tw-mr-2 tw-text-neutral-400"></i><?= _l('nav_logout'); ?></a>
                        </li>
                    </ul>
                </li>

                <?php hooks()->do_action('admin_navbar_end'); ?>
            </ul>
        </div>
    </nav>
</div>