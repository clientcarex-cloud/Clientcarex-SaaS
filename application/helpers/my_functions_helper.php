<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('before_admin_login_form_close', 'ccx_login_footer_hook');

function ccx_login_footer_hook()
{
    $CI = &get_instance();
    $viewPath = FCPATH . 'modules/ccx_login/views/login_footer.php';

    if (file_exists($viewPath)) {
        // We can't use $CI->load->view because the module might not be active/registered
        // So we include it manually to ensure it works 100% without activation

        // Extract variables if passed (none in this case, but good practice)
        extract([]);

        // Start output buffering to capture the view content
        ob_start();
        include($viewPath);
        echo ob_get_clean();
    }
}

hooks()->add_action('app_admin_authentication_head', 'ccx_login_frontend_assets');

function ccx_login_frontend_assets()
{
    $CI = &get_instance();
    $posters = ccx_get_master_option('ccx_login_posters');
    $posters_data = $posters ? json_decode($posters, true) : [];
    $posters_json = json_encode($posters_data);

    // SaaS Global Logic for Base URL
    $base_url = base_url('modules/ccx_login/uploads/');
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        $base_url = perfex_saas_default_base_url('modules/ccx_login/uploads/');
    }

    $split_ratio = ccx_get_master_option('ccx_login_split_ratio');
    if (!$split_ratio)
        $split_ratio = 40;

    $background_size = ccx_get_master_option('ccx_login_background_size');
    if (!$background_size)
        $background_size = 'cover';

    $poster_padding = ccx_get_master_option('ccx_login_poster_padding');
    if (!$poster_padding) $poster_padding = '0px';

    $poster_bg = ccx_get_master_option('ccx_login_poster_bg');
    if (!$poster_bg) $poster_bg = '#f3f4f6';

    echo <<<HTML
    <style>
        body.login_admin {
            display: flex !important;
            height: 100vh !important;
            padding: 0 0 64px 0 !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            background: {$poster_bg} !important; 
            justify-content: flex-start !important;
            align-items: stretch !important;
        }
        
        body.login_admin .ho-login-wrapper {
            width: {$split_ratio}% !important; /* Dynamic split ratio */
            min-width: 450px;
            max-width: 100%;
            flex-shrink: 0;
            margin: 0 !important;
            padding: 2rem !important;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            position: relative;
            z-index: 20;
            background: transparent !important;
            box-shadow: none !important;
            align-items: center; /* Center content horizontally */
        }
        
        /* Constrain the actual form and logo width */
        body.login_admin .ho-login-wrapper > *,
        body.login_admin .ho-login-card, 
        body.login_admin .company-logo {
            width: 100%;
            max-width: 420px; /* Fixed max width for the login box */
        }

        /* Adjust branding inside form */
        body.login_admin .company-logo {
            margin-bottom: 2rem;
        }

        #ccx-login-ads {
            flex-grow: 1;
            height: 100% !important;
            background-color: transparent !important;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ccx-ad-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding: {$poster_padding};
            box-sizing: border-box;
            background-origin: content-box;
            background-clip: content-box;
            background-size: {$background_size};
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .ccx-ad-slide.active {
            opacity: 1;
        }

        /* Footer adjustments */
        .ccx-login-footer-container {
            width: 100%;
            padding: 1rem 3rem;
            position: fixed; 
            bottom: 0;
            left: 0;
            z-index: 100;
            background: transparent;
            pointer-events: none; /* Let clicks pass through transparent areas if any */
        }
        .ccx-login-footer-container > * {
            pointer-events: auto; /* Re-enable clicks on children */
        }
        
        @media (max-width: 768px) {
            body.login_admin {
                flex-direction: column !important;
                overflow: auto !important;
                padding-bottom: 0 !important;
            }
            body.login_admin .ho-login-wrapper {
                width: 100% !important;
                height: auto !important;
                padding-top: 3rem !important;
            }
            #ccx-login-ads {
                display: none; /* Hide ads on mobile or move to bottom */
            }
            .ccx-login-footer-container {
                width: 100%;
                position: relative;
            }
        }

        /* Slider Controls CSS */
        .ccx-slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            cursor: pointer;
            z-index: 10;
            transition: background 0.3s;
            user-select: none;
        }

        .ccx-slider-arrow:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .ccx-slider-arrow.prev {
            left: 20px;
        }

        .ccx-slider-arrow.next {
            right: 20px;
        }

        .ccx-slider-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .ccx-dot {
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
        }

        .ccx-dot.active {
            background: white;
            transform: scale(1.2);
        }

    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.body.classList.contains('login_admin')) return;

            var adsContainer = document.createElement('div');
            adsContainer.id = 'ccx-login-ads';
            document.body.appendChild(adsContainer);

            var posters = {$posters_json};
            var baseUrl = '{$base_url}';

            if (posters.length > 0) {
                // Filter visible posters
                var visiblePosters = posters.filter(function(p) {
                    return p.visible !== false; // Default to true if undefined
                });

                if (visiblePosters.length > 0) {
                    visiblePosters.forEach(function(poster, index) {
                        var slide = document.createElement('div');
                        slide.className = 'ccx-ad-slide' + (index === 0 ? ' active' : '');
                        slide.style.backgroundImage = 'url(' + baseUrl + poster.filename + ')';
                        adsContainer.appendChild(slide);
                    });

                    if (visiblePosters.length > 1) {
                        // Create Dots Container
                        var dotsContainer = document.createElement('div');
                        dotsContainer.className = 'ccx-slider-dots';
                        adsContainer.appendChild(dotsContainer);

                        // Create Navigation Arrows
                        var prevBtn = document.createElement('div');
                        prevBtn.className = 'ccx-slider-arrow prev';
                        prevBtn.innerHTML = '&#10094;';
                        adsContainer.appendChild(prevBtn);

                        var nextBtn = document.createElement('div');
                        nextBtn.className = 'ccx-slider-arrow next';
                        nextBtn.innerHTML = '&#10095;';
                        adsContainer.appendChild(nextBtn);

                        // Populate dots
                        visiblePosters.forEach(function(_, index) {
                            var dot = document.createElement('div');
                            dot.className = 'ccx-dot' + (index === 0 ? ' active' : '');
                            dot.addEventListener('click', function() {
                                goToSlide(index);
                            });
                            dotsContainer.appendChild(dot);
                        });

                        var currentSlide = 0;
                        var slideInterval;

                        function updateSlides() {
                            var slides = adsContainer.querySelectorAll('.ccx-ad-slide');
                            var dots = dotsContainer.querySelectorAll('.ccx-dot');
                            
                            slides.forEach(function(slide, index) {
                                slide.classList.toggle('active', index === currentSlide);
                            });
                            
                            dots.forEach(function(dot, index) {
                                dot.classList.toggle('active', index === currentSlide);
                            });
                        }

                        function nextSlide() {
                            currentSlide = (currentSlide + 1) % visiblePosters.length;
                            updateSlides();
                        }

                        function prevSlide() {
                            currentSlide = (currentSlide - 1 + visiblePosters.length) % visiblePosters.length;
                            updateSlides();
                        }

                        function goToSlide(index) {
                            currentSlide = index;
                            updateSlides();
                            resetInterval();
                        }

                        function resetInterval() {
                            clearInterval(slideInterval);
                            slideInterval = setInterval(nextSlide, 5000);
                        }

                        // Event Listeners for arrows
                        nextBtn.addEventListener('click', function() {
                            nextSlide();
                            resetInterval();
                        });

                        prevBtn.addEventListener('click', function() {
                            prevSlide();
                            resetInterval();
                        });

                        // Start auto-play
                        resetInterval();
                    }
                } else {
                     // Default placeholder if all ads hidden
                     adsContainer.style.background = 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)';
                     adsContainer.innerHTML = '<div style="color:white; font-size: 2rem; font-weight: bold; opacity: 0.8;">Welcome to ClientcareX</div>';
                }
            } else {
                // Default placeholder if no ads
                 adsContainer.style.background = 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)';
                 adsContainer.innerHTML = '<div style="color:white; font-size: 2rem; font-weight: bold; opacity: 0.8;">Welcome to ClientcareX</div>';
            }
            
            // Move footer into form wrapper or keep fixed at bottom left
            // The CSS targets fixed bottom left with width, so it should be fine.
        });
    </script>
HTML;

}

hooks()->add_action('clients_authentication_constructor', 'ccx_disable_client_login');

function ccx_disable_client_login($controller)
{
    if (ccx_get_master_option('ccx_disable_customer_login') == 1) {
        $method = $controller->router->fetch_method();
        if ($method !== 'logout') {

            // Allow registration when a SaaS package plan link is used (ps_plan parameter).
            // Without this, users cannot sign up via package share links.
            if ($method === 'register') {
                $CI = &get_instance();
                $plan_id = function_exists('perfex_saas_route_id_prefix')
                    ? perfex_saas_route_id_prefix('plan')
                    : 'ps_plan';
                $has_plan = !empty($CI->input->post_get($plan_id, true))
                         || !empty($CI->session->{$plan_id});
                if ($has_plan) {
                    return; // Allow registration to proceed
                }
            }

            redirect(admin_url('authentication'));
        }
    }
}

function ccx_get_master_option($name)
{
    // Check if SaaS module is active and we are in a tenant context
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        // We need to fetch the option from the master database
        $row = perfex_saas_raw_query_row("SELECT value FROM " . perfex_saas_master_db_prefix() . "options WHERE name = '$name'", [], true);
        if ($row) {
            return $row->value;
        }
        return '';
    }

    // Default behavior for master or non-SaaS
    return get_option($name);
}

// --- Custom Global Footer ---
function ccx_inject_global_footer() {
    $current_year = date('Y');
    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var footerEl = document.createElement("div");
        footerEl.className = "ccx-global-footer";
        footerEl.innerHTML = "&copy; ' . $current_year . ' Healthocare Private Limited. All rights reserved.";
        footerEl.style.cssText = "padding: 15px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0; background: transparent; margin-top: 30px; width: 100%; clear: both;";
        
        // Find the main content container to inject it naturally at the bottom
        var adminContent = document.querySelector("#wrapper .content");
        var custContainer = document.querySelector(".clients-wrapper") || document.querySelector("#wrapper");
        
        if (adminContent) {
            adminContent.appendChild(footerEl);
        } else if (custContainer) {
            custContainer.appendChild(footerEl);
        } else {
            document.body.appendChild(footerEl);
        }
    });
    </script>
    ';
}
hooks()->add_action('app_admin_footer', 'ccx_inject_global_footer');
hooks()->add_action('app_customers_footer', 'ccx_inject_global_footer');

// --- TinyMCE Font Size + Default Color Override (PHP-rendered, cache-proof) ---
// Intercepts EVERY tinymce.init on the page, including module views that pass
// their own content_style (fonts only), so no editor falls back to TinyMCE's
// washed-out default grey (#222f3e). The black rule is prepended, so a color a
// module or user explicitly sets still wins.
function ccx_tinymce_font_size_override() {
    echo '
    <script>
    (function() {
        if (typeof tinymce === "undefined") return;
        var _origInit = tinymce.init;
        var _blackCss =
            "body," +
            "body p,body div,body td,body th,body li," +
            "body h1,body h2,body h3,body h4,body h5,body h6{color:#000000;}";
        tinymce.init = function(settings) {
            if (settings) {
                settings.font_size_formats = "1px 2px 3px 4px 5px 6px 7px 8px 9px 10px 11px 12px 13px 14px 15px 16px 17px 18px 19px 20px";
                settings.content_style = _blackCss + (settings.content_style || "");
            }
            return _origInit.call(this, settings);
        };
    })();
    </script>
    ';
}
hooks()->add_action('app_admin_footer', 'ccx_tinymce_font_size_override');
hooks()->add_action('app_customers_footer', 'ccx_tinymce_font_size_override');

/**
 * Display value for a payment receipt's date.
 *
 * tblinvoicepaymentrecords has two date columns and they mean different things:
 *   - `date`         the payment date. This is what the printed receipt, the
 *                    dashboard, core Reports and the shift report filters all use,
 *                    and it is what Payment Modify edits.
 *   - `daterecorded` the row insertion timestamp (an audit field).
 *
 * The receipt history modals used to print `daterecorded` under a column headed
 * "Date", so correcting a receipt date looked like it had not saved. This returns
 * the payment date carrying the recorded clock time, so the day is truthful and
 * the time of collection is still visible.
 *
 * @param  array|object $payment row from tblinvoicepaymentrecords
 * @return string
 */
function pm_receipt_datetime($payment)
{
    $payment = (array) $payment;

    $date = isset($payment['date']) ? $payment['date'] : '';

    if ($date === '' || $date === null || $date === '0000-00-00') {
        // Nothing usable to show the day from - fall back to the audit stamp
        return isset($payment['daterecorded']) ? _dt($payment['daterecorded']) : '';
    }

    // `date` is DATE only, so borrow the time of day from daterecorded
    $recorded = isset($payment['daterecorded']) ? $payment['daterecorded'] : '';
    $time     = ($recorded !== '' && $recorded !== null && strtotime($recorded) !== false)
        ? date('H:i:s', strtotime($recorded))
        : '';

    return $time !== '' ? _dt(substr($date, 0, 10) . ' ' . $time) : _d($date);
}
