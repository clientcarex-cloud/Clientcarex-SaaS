<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
/**
 * Branded, shareable pricing brochure (internal use).
 *
 * @var array  $matrix   ['groups'=>.., 'periods'=>.., 'matrix'=>..]
 * @var object $currency base currency
 */
$matrix_groups = $matrix['groups'];
$periods       = $matrix['periods'];
$grid          = $matrix['matrix'];

// ---- Branding pulled from company settings ----
$company_name = get_option('companyname');
$logo_file    = get_option('company_logo');
if (!$logo_file) $logo_file = get_option('company_logo_dark');
$logo_url     = $logo_file ? base_url('uploads/company/' . $logo_file) : '';

$addr_parts = array_filter([
    trim((string)get_option('invoice_company_address')),
    trim((string)get_option('invoice_company_city')),
    trim((string)get_option('company_state')),
    trim((string)get_option('invoice_company_postal_code')),
]);
$company_address = implode(', ', $addr_parts);
$company_phone   = trim((string)get_option('invoice_company_phonenumber'));
$company_website = site_url();

// ---- Summary ----
$total_plans  = 0;
$active_plans = 0;
foreach ($matrix_groups as $gid => $gname) {
    foreach (($grid[$gid] ?? []) as $plans) {
        foreach ($plans as $p) {
            $total_plans++;
            if (!empty($p->status)) $active_plans++;
        }
    }
}

// Render a single limit value as a number or "Unlimited"
$fmt_users = function ($p) {
    $v = isset($p->metadata->limitations->staff) ? (int)$p->metadata->limitations->staff : -1;
    return $v < 0 ? _l('perfex_saas_pricing_plans_feat_users_unlimited') : _l('perfex_saas_pricing_plans_feat_users', $v);
};
?>
<div id="wrapper">
    <div class="content">

        <!-- Action bar (not part of the printed/shared brochure) -->
        <div class="pp-no-print tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-4">
            <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans'); ?>" class="btn btn-default">
                <i class="fa fa-arrow-left tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_back_to_manager'); ?>
            </a>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
                <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/export_excel'); ?>" class="btn btn-success">
                    <i class="fa fa-file-excel-o tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_export_excel'); ?>
                </a>
                <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/export_pdf'); ?>" class="btn btn-danger">
                    <i class="fa fa-file-pdf-o tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_export_pdf'); ?>
                </a>
                <button type="button" class="btn btn-primary" onclick="ppPrintBrochure();">
                    <i class="fa fa-print tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_print'); ?>
                </button>
            </div>
        </div>

        <!-- Filters (not printed) -->
        <div class="pp-no-print pp-toolbar">
            <ul class="pp-filter" id="pp-period-filter">
                <li class="active"><a href="#" data-period="all"><?= _l('perfex_saas_pricing_plans_all_periods'); ?></a></li>
                <?php foreach ($periods as $alias => $label) : ?>
                <li><a href="#" data-period="<?= e($alias); ?>"><?= e($label); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <label class="pp-switch">
                <input type="checkbox" id="pp-only-active"> <?= _l('perfex_saas_pricing_plans_only_active'); ?>
            </label>
        </div>

        <!-- ===== Brochure sheet ===== -->
        <div id="pp-report" class="pp-sheet">

            <header class="pp-hero">
                <div class="pp-hero-brand">
                    <?php if ($logo_url) : ?>
                    <img src="<?= $logo_url; ?>" alt="<?= e($company_name); ?>" class="pp-logo"
                        onerror="this.style.display='none';var f=document.getElementById('pp-brand-fallback');if(f)f.style.display='block';">
                    <div id="pp-brand-fallback" class="pp-brand-name" style="display:none;"><?= e($company_name); ?></div>
                    <?php elseif ($company_name) : ?>
                    <div class="pp-brand-name"><?= e($company_name); ?></div>
                    <?php endif; ?>
                </div>
                <h1 class="pp-title"><?= _l('perfex_saas_pricing_plans_brochure_title'); ?></h1>
                <p class="pp-subtitle"><?= _l('perfex_saas_pricing_plans_brochure_subtitle'); ?></p>
                <div class="pp-hero-accent"></div>
            </header>

            <?php if (!$total_plans) : ?>
            <div class="pp-empty"><?= _l('perfex_saas_pricing_plans_no_data'); ?></div>
            <?php endif; ?>

            <?php foreach ($matrix_groups as $gid => $gname) : ?>
            <?php
                $group_plans = [];
                foreach (($grid[$gid] ?? []) as $period_alias => $plans) {
                    foreach ($plans as $p) {
                        $p->_period_alias = $period_alias;
                        $p->_period_label = $periods[$period_alias] ?? $period_alias;
                        $group_plans[] = $p;
                    }
                }
                if (empty($group_plans)) continue;
            ?>
            <section class="pp-section">
                <h2 class="pp-section-title"><span><?= e($gname); ?></span></h2>

                <div class="pp-grid">
                    <?php foreach ($group_plans as $p) : ?>
                    <?php
                        $is_featured = !empty($p->is_default);
                        $is_active   = !empty($p->status);
                        $modules     = is_array($p->modules ?? null) ? count($p->modules) : 0;
                        $trial       = (int)$p->trial_period;
                    ?>
                    <article class="pp-card<?= $is_featured ? ' is-featured' : ''; ?><?= $is_active ? '' : ' is-inactive'; ?>"
                        data-period="<?= e($p->_period_alias); ?>" data-active="<?= $is_active ? '1' : '0'; ?>">

                        <?php if ($is_featured) : ?>
                        <div class="pp-ribbon"><?= _l('perfex_saas_pricing_plans_recommended'); ?></div>
                        <?php endif; ?>
                        <?php if (!$is_active) : ?>
                        <span class="pp-inactive-tag"><?= _l('perfex_saas_pricing_plans_inactive'); ?></span>
                        <?php endif; ?>

                        <div class="pp-period"><?= e($p->_period_label); ?></div>
                        <h3 class="pp-name"><?= e($p->name); ?></h3>

                        <div class="pp-price">
                            <?php if ((float)$p->price > 0) : ?>
                            <span class="pp-amount"><?= app_format_money($p->price, $currency); ?></span>
                            <span class="pp-per">/ <?= e($p->_period_label); ?></span>
                            <?php else : ?>
                            <span class="pp-amount"><?= _l('perfex_saas_pricing_plans_free'); ?></span>
                            <?php endif; ?>
                        </div>

                        <ul class="pp-features">
                            <li><i class="fa fa-check"></i> <?= $fmt_users($p); ?></li>
                            <li><i class="fa fa-check"></i> <?= _l('perfex_saas_pricing_plans_feat_modules', $modules); ?></li>
                            <?php if ($trial > 0) : ?>
                            <li><i class="fa fa-check"></i> <?= _l('perfex_saas_pricing_plans_feat_trial', $trial); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($p->metadata->enable_subdomain)) : ?>
                            <li><i class="fa fa-check"></i> <?= _l('perfex_saas_pricing_plans_feat_subdomain'); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($p->metadata->enable_custom_domain)) : ?>
                            <li><i class="fa fa-check"></i> <?= _l('perfex_saas_pricing_plans_feat_custom_domain'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>

            <footer class="pp-footer">
                <?php if ($company_name) : ?><div class="pp-foot-name"><?= e($company_name); ?></div><?php endif; ?>
                <div class="pp-foot-contact">
                    <?php if ($company_address) : ?><span><i class="fa fa-map-marker"></i> <?= e($company_address); ?></span><?php endif; ?>
                    <?php if ($company_phone) : ?><span><i class="fa fa-phone"></i> <?= e($company_phone); ?></span><?php endif; ?>
                    <?php if ($company_website) : ?><span><i class="fa fa-globe"></i> <?= e($company_website); ?></span><?php endif; ?>
                </div>
                <div class="pp-foot-meta"><?= _l('perfex_saas_pricing_plans_generated_on'); ?>: <?= _dt(date('Y-m-d H:i')); ?></div>
            </footer>

        </div>
    </div>
</div>

<?php init_tail(); ?>
<style id="pp-brochure-style">
:root {
    --pp-accent: #4f46e5;
    --pp-accent-2: #06b6d4;
    --pp-ink: #1f2937;
    --pp-muted: #6b7280;
    --pp-line: #eceef3;
}

/* Keep brand colours/gradients when printing */
.pp-sheet,
.pp-sheet * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

.pp-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.pp-filter {
    list-style: none;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 4px;
    margin: 0;
    background: #f1f3f9;
    border-radius: 999px;
}

.pp-filter li a {
    display: block;
    padding: 6px 16px;
    border-radius: 999px;
    color: var(--pp-muted);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all .15s ease;
}

.pp-filter li.active a {
    background: #fff;
    color: var(--pp-accent);
    box-shadow: 0 1px 3px rgba(16, 24, 40, .12);
}

.pp-switch {
    font-size: 13px;
    color: var(--pp-muted);
    font-weight: 600;
    margin: 0;
    cursor: pointer;
}

/* ---- The brochure sheet ---- */
.pp-sheet {
    max-width: 1180px;
    margin: 0 auto;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 12px 40px rgba(16, 24, 40, .08);
    overflow: hidden;
    color: var(--pp-ink);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.pp-hero {
    position: relative;
    text-align: center;
    padding: 48px 24px 40px;
    background:
        radial-gradient(1200px 300px at 50% -120px, rgba(79, 70, 229, .10), rgba(255, 255, 255, 0)),
        linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
}

.pp-logo {
    max-height: 64px;
    max-width: 260px;
    margin: 0 auto 18px;
    display: block;
}

.pp-brand-name {
    font-size: 26px;
    font-weight: 800;
    letter-spacing: .3px;
    margin-bottom: 14px;
    background: linear-gradient(90deg, var(--pp-accent), var(--pp-accent-2));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.pp-title {
    font-size: 34px;
    font-weight: 800;
    margin: 0 0 8px;
    color: var(--pp-ink);
}

.pp-subtitle {
    font-size: 15px;
    color: var(--pp-muted);
    margin: 0;
}

.pp-hero-accent {
    width: 64px;
    height: 4px;
    border-radius: 4px;
    margin: 22px auto 0;
    background: linear-gradient(90deg, var(--pp-accent), var(--pp-accent-2));
}

.pp-empty {
    text-align: center;
    color: var(--pp-muted);
    padding: 48px;
}

.pp-section {
    padding: 8px 36px 16px;
}

.pp-section-title {
    text-align: center;
    font-size: 19px;
    font-weight: 700;
    color: var(--pp-ink);
    margin: 28px 0 22px;
    position: relative;
}

.pp-section-title span {
    position: relative;
    padding-bottom: 10px;
    display: inline-block;
}

.pp-section-title span:after {
    content: "";
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
    width: 40px;
    height: 3px;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--pp-accent), var(--pp-accent-2));
}

.pp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.pp-card {
    position: relative;
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid var(--pp-line);
    border-radius: 18px;
    padding: 24px 22px;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.pp-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 40px rgba(16, 24, 40, .12);
    border-color: rgba(79, 70, 229, .35);
}

.pp-card.is-featured {
    border-color: transparent;
    box-shadow: 0 0 0 2px var(--pp-accent), 0 18px 44px rgba(79, 70, 229, .18);
}

.pp-card.is-inactive {
    opacity: .6;
}

.pp-ribbon {
    position: absolute;
    top: 14px;
    right: -2px;
    background: linear-gradient(90deg, var(--pp-accent), var(--pp-accent-2));
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 6px 0 0 6px;
    box-shadow: 0 4px 10px rgba(79, 70, 229, .35);
}

.pp-inactive-tag {
    position: absolute;
    top: 14px;
    left: 14px;
    background: #f3f4f6;
    color: #9ca3af;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 6px;
}

.pp-period {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
    color: var(--pp-accent);
    margin-bottom: 6px;
}

.pp-name {
    font-size: 19px;
    font-weight: 700;
    margin: 0 0 14px;
    color: var(--pp-ink);
}

.pp-price {
    display: flex;
    align-items: baseline;
    gap: 6px;
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--pp-line);
}

.pp-amount {
    font-size: 30px;
    font-weight: 800;
    color: var(--pp-ink);
    line-height: 1;
}

.pp-per {
    font-size: 13px;
    color: var(--pp-muted);
    font-weight: 600;
}

.pp-features {
    list-style: none;
    margin: 0;
    padding: 0;
}

.pp-features li {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13.5px;
    color: #374151;
    padding: 5px 0;
}

.pp-features li i {
    color: #10b981;
    font-size: 12px;
}

.pp-footer {
    text-align: center;
    padding: 28px 24px 36px;
    margin-top: 16px;
    border-top: 1px solid var(--pp-line);
    background: #fafbff;
}

.pp-foot-name {
    font-weight: 700;
    color: var(--pp-ink);
    margin-bottom: 6px;
}

.pp-foot-contact {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 18px;
    color: var(--pp-muted);
    font-size: 13px;
}

.pp-foot-contact i {
    color: var(--pp-accent);
    margin-right: 4px;
}

.pp-foot-meta {
    margin-top: 12px;
    font-size: 11px;
    color: #9ca3af;
}

.pp-card.pp-hidden {
    display: none;
}

@media print {
    body * {
        visibility: hidden;
    }

    #pp-report,
    #pp-report * {
        visibility: visible;
    }

    #pp-report {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none;
        border-radius: 0;
    }

    .pp-no-print {
        display: none !important;
    }

    .pp-card {
        page-break-inside: avoid;
        box-shadow: none !important;
    }

    .pp-card.pp-hidden {
        display: flex !important;
    }
}
</style>
<script>
"use strict";

/**
 * Print the brochure straight from the HTML by rendering only #pp-report into a
 * clean, off-screen iframe (with the page's stylesheets + brand colours forced on).
 * This avoids the admin chrome and the browser stripping background graphics.
 */
function ppPrintBrochure() {
    var report = document.getElementById('pp-report');
    if (!report) { window.print(); return; }

    var links = '';
    document.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) {
        links += l.outerHTML;
    });

    var styleEl = document.getElementById('pp-brochure-style');
    var brochureCss = styleEl ? styleEl.innerHTML : '';

    var extraCss =
        '@page{margin:12mm;}' +
        'html,body{background:#fff;margin:0;padding:0;visibility:visible !important;}' +
        // The iframe holds only the brochure, so cancel the page-print isolation rules
        // (absolute positioning there would clip multi-page output to a single page).
        '#pp-report{position:static !important;left:auto !important;top:auto !important;' +
        'box-shadow:none !important;border-radius:0 !important;max-width:100% !important;margin:0 auto;visibility:visible !important;}' +
        '#pp-report *{visibility:visible !important;}' +
        '*{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;}';

    var docHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' +
        (document.title || 'Pricing Plans') + '</title>' + links +
        '<style>' + brochureCss + '</style><style>' + extraCss + '</style></head><body>' +
        report.outerHTML + '</body></html>';

    var iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(iframe);

    var idoc = iframe.contentWindow.document;
    idoc.open();
    idoc.write(docHtml);
    idoc.close();

    var printed = false;
    function go() {
        if (printed) return;
        printed = true;
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) { /* ignore */ }
        setTimeout(function () {
            if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
        }, 2000);
    }
    iframe.onload = function () { setTimeout(go, 400); };
    setTimeout(go, 1200); // fallback if onload doesn't fire after document.write
}

(function () {

    function ppApplyFilters() {
        var period = $('#pp-period-filter li.active a').data('period');
        period = period ? String(period) : 'all';
        var onlyActive = $('#pp-only-active').is(':checked');

        $('.pp-card').each(function () {
            var $c = $(this);
            var matchPeriod = (period === 'all' || String($c.data('period')) === period);
            var matchActive = (!onlyActive || String($c.data('active')) === '1');
            $c.toggleClass('pp-hidden', !(matchPeriod && matchActive));
        });

        // Hide a whole section when it has no visible cards
        $('.pp-section').each(function () {
            var visible = $(this).find('.pp-card').not('.pp-hidden').length;
            $(this).toggle(visible > 0);
        });
    }

    $(function () {
        $('#pp-period-filter a').on('click', function (e) {
            e.preventDefault();
            $('#pp-period-filter li').removeClass('active');
            $(this).closest('li').addClass('active');
            ppApplyFilters();
        });
        $('#pp-only-active').on('change', ppApplyFilters);
    });
})();
</script>
</body>

</html>
