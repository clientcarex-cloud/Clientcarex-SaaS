<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
$CI = &get_instance();

$charts_data = $CI->perfex_saas_model->dashboard_advanced_charts();

$CI->load->model('currencies_model');
$base_currency   = $CI->currencies_model->get_base_currency();
$currency_symbol = $base_currency->symbol ?? '';

// Fixed color per company status
$status_colors = [
    'active'         => '#84c529',
    'pending'        => '#ff9800',
    'deploying'      => '#03a9f4',
    'inactive'       => '#9e9e9e',
    'disabled'       => '#fc2d42',
    'banned'         => '#d81b60',
    'pending-delete' => '#795548',
];

$status_labels = [];
$status_totals = [];
$status_bg     = [];
foreach ($charts_data['statuses'] as $status_row) {
    $status_labels[] = ucfirst($status_row->status);
    $status_totals[] = (int)$status_row->total;
    $status_bg[]     = $status_colors[$status_row->status] ?? '#607d8b';
}

// Palette cycled across packages
$package_palette = ['#03a9f4', '#84c529', '#ff9800', '#9c27b0', '#00bcd4', '#e91e63', '#8bc34a', '#3f51b5', '#ffc107', '#009688'];
$package_labels  = [];
$package_totals  = [];
$package_bg      = [];
foreach ($charts_data['packages'] as $i => $package_row) {
    $package_labels[] = $package_row->name;
    $package_totals[] = (int)$package_row->total;
    $package_bg[]     = $package_palette[$i % count($package_palette)];
}
?>

<div class="col-md-12">
    <div class="panel_s" id="saas-analytics-charts">
        <div class="panel-body">
            <p class="tw-font-medium tw-flex tw-items-center tw-mb-0 tw-space-x-1.5 rtl:tw-space-x-reverse tw-p-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="tw-w-6 tw-h-6 tw-text-neutral-500">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                <span class="tw-text-neutral-700">
                    <?= _l('perfex_saas_dashboard_analytics'); ?>
                </span>
            </p>

            <hr class="-tw-mx-3 tw-mt-3 tw-mb-6">

            <div class="row">
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_tenant_growth'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_tenant_growth_chart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_revenue'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_revenue_chart"></canvas>
                    </div>
                </div>
            </div>

            <hr class="tw-my-6">

            <div class="row">
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_company_status'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_company_status_chart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_package_distribution'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_package_distribution_chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    "use strict";
    window.addEventListener("DOMContentLoaded", function() {

        var saasChartLabels = <?= json_encode($charts_data['labels']); ?>;
        var saasCurrencySymbol = <?= json_encode($currency_symbol); ?>;

        // 1. Tenant growth - monthly signups (bars) + cumulative total (line)
        var growthCanvas = $('#saas_tenant_growth_chart');
        if (growthCanvas.length > 0) {
            new Chart(growthCanvas, {
                type: 'bar',
                data: {
                    labels: saasChartLabels,
                    datasets: [{
                            type: 'line',
                            label: <?= json_encode(_l('perfex_saas_chart_total_tenants')); ?>,
                            data: <?= json_encode($charts_data['cumulative']); ?>,
                            borderColor: '#84c529',
                            backgroundColor: 'rgba(132, 197, 41, 0.08)',
                            pointBackgroundColor: '#84c529',
                            borderWidth: 2,
                            fill: true,
                            lineTension: 0.3,
                            yAxisID: 'y-total'
                        },
                        {
                            label: <?= json_encode(_l('perfex_saas_chart_new_tenants')); ?>,
                            data: <?= json_encode($charts_data['signups']); ?>,
                            backgroundColor: 'rgba(3, 169, 244, 0.65)',
                            hoverBackgroundColor: 'rgba(3, 169, 244, 0.85)',
                            yAxisID: 'y-new'
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                                id: 'y-new',
                                position: 'left',
                                ticks: {
                                    beginAtZero: true,
                                    callback: function(value) {
                                        return value % 1 === 0 ? value : null;
                                    }
                                }
                            },
                            {
                                id: 'y-total',
                                position: 'right',
                                gridLines: {
                                    drawOnChartArea: false
                                },
                                ticks: {
                                    beginAtZero: true,
                                    callback: function(value) {
                                        return value % 1 === 0 ? value : null;
                                    }
                                }
                            }
                        ]
                    }
                }
            });
        }

        // 2. Revenue collected on SaaS package invoices
        var revenueCanvas = $('#saas_revenue_chart');
        if (revenueCanvas.length > 0) {
            new Chart(revenueCanvas, {
                type: 'line',
                data: {
                    labels: saasChartLabels,
                    datasets: [{
                        label: <?= json_encode(_l('perfex_saas_chart_revenue_label')); ?>,
                        data: <?= json_encode($charts_data['revenue']); ?>,
                        borderColor: '#84c529',
                        backgroundColor: 'rgba(132, 197, 41, 0.15)',
                        pointBackgroundColor: '#84c529',
                        borderWidth: 2,
                        fill: true,
                        lineTension: 0.3
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                callback: function(value) {
                                    return saasCurrencySymbol + value.toLocaleString();
                                }
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var label = data.datasets[tooltipItem.datasetIndex].label || '';
                                return label + ': ' + saasCurrencySymbol + Number(tooltipItem.yLabel).toLocaleString();
                            }
                        }
                    }
                }
            });
        }

        // 3. Tenants by status
        var statusCanvas = $('#saas_company_status_chart');
        if (statusCanvas.length > 0) {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($status_labels); ?>,
                    datasets: [{
                        data: <?= json_encode($status_totals); ?>,
                        backgroundColor: <?= json_encode($status_bg); ?>,
                        hoverBackgroundColor: <?= json_encode($status_bg); ?>
                    }]
                },
                options: {
                    maintainAspectRatio: false
                }
            });
        }

        // 4. Tenants per package
        var packageCanvas = $('#saas_package_distribution_chart');
        if (packageCanvas.length > 0) {
            new Chart(packageCanvas, {
                type: 'horizontalBar',
                data: {
                    labels: <?= json_encode($package_labels); ?>,
                    datasets: [{
                        label: <?= json_encode(_l('perfex_saas_chart_tenants')); ?>,
                        data: <?= json_encode($package_totals); ?>,
                        backgroundColor: <?= json_encode($package_bg); ?>,
                        hoverBackgroundColor: <?= json_encode($package_bg); ?>
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    legend: {
                        display: false
                    },
                    scales: {
                        xAxes: [{
                            ticks: {
                                beginAtZero: true,
                                callback: function(value) {
                                    return value % 1 === 0 ? value : null;
                                }
                            }
                        }]
                    }
                }
            });
        }
    });
</script>
