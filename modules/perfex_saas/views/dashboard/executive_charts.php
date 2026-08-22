<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
$CI = &get_instance();

$exec = $CI->perfex_saas_model->dashboard_executive_charts();
$kpis = $exec['kpis'];

$CI->load->model('currencies_model');
$base_currency   = $CI->currencies_model->get_base_currency();
$currency_symbol = $base_currency->symbol ?? '';

$exec_palette = ['#03a9f4', '#84c529', '#ff9800', '#9c27b0', '#00bcd4', '#e91e63', '#8bc34a', '#3f51b5', '#ffc107', '#009688', '#795548', '#607d8b'];

// KPI tiles definition: [label, formatted value, css color class]
$kpi_tiles = [
    [_l('perfex_saas_kpi_mrr'), app_format_money($kpis['mrr'], $base_currency), 'text-success'],
    [_l('perfex_saas_kpi_arr'), app_format_money($kpis['arr'], $base_currency), 'text-success'],
    [_l('perfex_saas_kpi_arpu'), app_format_money($kpis['arpu'], $base_currency), 'text-info'],
    [_l('perfex_saas_kpi_active_tenants'), (int)$kpis['active_tenants'], 'text-info'],
    [_l('perfex_saas_kpi_outstanding'), app_format_money($kpis['outstanding'], $base_currency), 'text-warning'],
    [_l('perfex_saas_kpi_overdue'), app_format_money($kpis['overdue'], $base_currency), 'text-danger'],
];

// MRR by package
$mrr_labels = [];
$mrr_totals = [];
$mrr_bg     = [];
foreach ($exec['mrr_by_package'] as $i => $row) {
    $mrr_labels[] = $row->name;
    $mrr_totals[] = $row->mrr;
    $mrr_bg[]     = $exec_palette[$i % count($exec_palette)];
}

// Revenue by package - stacked datasets
$revenue_datasets = [];
foreach ($exec['revenue_by_package'] as $i => $set) {
    $revenue_datasets[] = [
        'label'                => $set['name'],
        'data'                 => $set['data'],
        'backgroundColor'      => $exec_palette[$i % count($exec_palette)],
        'hoverBackgroundColor' => $exec_palette[$i % count($exec_palette)],
    ];
}

// Receivables aging buckets
$aging_labels = [
    _l('perfex_saas_aging_not_due'),
    '1-30 ' . _l('perfex_saas_aging_days'),
    '31-60 ' . _l('perfex_saas_aging_days'),
    '61-90 ' . _l('perfex_saas_aging_days'),
    '90+ ' . _l('perfex_saas_aging_days'),
];
$aging_totals = array_values($exec['aging']);
$aging_bg     = ['#9e9e9e', '#ffc107', '#ff9800', '#fc2d42', '#d81b60'];

// Payment methods
$mode_labels = [];
$mode_totals = [];
$mode_bg     = [];
foreach ($exec['payment_modes'] as $i => $row) {
    $mode_labels[] = $row->name;
    $mode_totals[] = $row->total;
    $mode_bg[]     = $exec_palette[$i % count($exec_palette)];
}

// Top tenants
$tenant_labels = [];
$tenant_totals = [];
foreach ($exec['top_tenants'] as $row) {
    $tenant_labels[] = $row->name;
    $tenant_totals[] = round((float)$row->total, 2);
}

// DB schemes
$scheme_labels = [];
$scheme_totals = [];
$scheme_bg     = [];
foreach ($exec['db_schemes'] as $i => $row) {
    $scheme_labels[] = ucwords(str_replace('_', ' ', $row->scheme));
    $scheme_totals[] = (int)$row->total;
    $scheme_bg[]     = $exec_palette[$i % count($exec_palette)];
}
?>

<div class="col-md-12">
    <div class="panel_s" id="saas-executive-charts">
        <div class="panel-body">
            <p class="tw-font-medium tw-flex tw-items-center tw-mb-0 tw-space-x-1.5 rtl:tw-space-x-reverse tw-p-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="tw-w-6 tw-h-6 tw-text-neutral-500">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                </svg>
                <span class="tw-text-neutral-700">
                    <?= _l('perfex_saas_executive_analytics'); ?>
                </span>
            </p>

            <hr class="-tw-mx-3 tw-mt-3 tw-mb-6">

            <!-- KPI tiles -->
            <div class="row tw-flex tw-flex-wrap">
                <?php foreach ($kpi_tiles as $tile) { ?>
                    <div class="col-xs-6 col-sm-4 col-md-2 tw-mb-2">
                        <div class="top_stats_wrapper text-center">
                            <div class="tw-font-medium text-neutral-600 tw-text-xs tw-uppercase"><?= $tile[0]; ?></div>
                            <div class="tw-font-semibold tw-text-lg <?= $tile[2]; ?>"><?= $tile[1]; ?></div>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <hr class="tw-my-6">

            <div class="row">
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_revenue_by_package'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_revenue_by_package_chart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_mrr_by_package'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_mrr_by_package_chart"></canvas>
                    </div>
                </div>
            </div>

            <hr class="tw-my-6">

            <div class="row">
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_receivables_aging'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_receivables_aging_chart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_payment_methods'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_payment_methods_chart"></canvas>
                    </div>
                </div>
            </div>

            <hr class="tw-my-6">

            <div class="row">
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_top_tenants'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_top_tenants_chart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <p class="text-center tw-font-medium bold"><?= _l('perfex_saas_chart_db_schemes'); ?></p>
                    <div class="relative" style="height:260px">
                        <canvas class="chart" id="saas_db_schemes_chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    "use strict";
    window.addEventListener("DOMContentLoaded", function() {

        var saasExecSymbol = <?= json_encode($currency_symbol); ?>;
        var saasExecMonths = <?= json_encode($exec['labels']); ?>;

        function saasMoneyTick(value) {
            return saasExecSymbol + Number(value).toLocaleString();
        }

        function saasMoneyTooltip(tooltipItem, data) {
            var dataset = data.datasets[tooltipItem.datasetIndex];
            var label = dataset.label || data.labels[tooltipItem.index] || '';
            var value = dataset.data[tooltipItem.index];
            return label + ': ' + saasExecSymbol + Number(value).toLocaleString();
        }

        // 1. Revenue by package - stacked monthly bars
        var revenuePackageCanvas = $('#saas_revenue_by_package_chart');
        if (revenuePackageCanvas.length > 0) {
            new Chart(revenuePackageCanvas, {
                type: 'bar',
                data: {
                    labels: saasExecMonths,
                    datasets: <?= json_encode($revenue_datasets); ?>
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        xAxes: [{
                            stacked: true
                        }],
                        yAxes: [{
                            stacked: true,
                            ticks: {
                                beginAtZero: true,
                                callback: saasMoneyTick
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: saasMoneyTooltip
                        }
                    }
                }
            });
        }

        // 2. MRR by package
        var mrrCanvas = $('#saas_mrr_by_package_chart');
        if (mrrCanvas.length > 0) {
            new Chart(mrrCanvas, {
                type: 'horizontalBar',
                data: {
                    labels: <?= json_encode($mrr_labels); ?>,
                    datasets: [{
                        label: <?= json_encode(_l('perfex_saas_kpi_mrr')); ?>,
                        data: <?= json_encode($mrr_totals); ?>,
                        backgroundColor: <?= json_encode($mrr_bg); ?>,
                        hoverBackgroundColor: <?= json_encode($mrr_bg); ?>
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
                                callback: saasMoneyTick
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: saasMoneyTooltip
                        }
                    }
                }
            });
        }

        // 3. Receivables aging
        var agingCanvas = $('#saas_receivables_aging_chart');
        if (agingCanvas.length > 0) {
            new Chart(agingCanvas, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($aging_labels); ?>,
                    datasets: [{
                        label: <?= json_encode(_l('perfex_saas_kpi_outstanding')); ?>,
                        data: <?= json_encode($aging_totals); ?>,
                        backgroundColor: <?= json_encode($aging_bg); ?>,
                        hoverBackgroundColor: <?= json_encode($aging_bg); ?>
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    legend: {
                        display: false
                    },
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                callback: saasMoneyTick
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: saasMoneyTooltip
                        }
                    }
                }
            });
        }

        // 4. Payment methods split
        var modesCanvas = $('#saas_payment_methods_chart');
        if (modesCanvas.length > 0) {
            new Chart(modesCanvas, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($mode_labels); ?>,
                    datasets: [{
                        data: <?= json_encode($mode_totals); ?>,
                        backgroundColor: <?= json_encode($mode_bg); ?>,
                        hoverBackgroundColor: <?= json_encode($mode_bg); ?>
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
                                return data.labels[tooltipItem.index] + ': ' + saasExecSymbol + Number(value).toLocaleString();
                            }
                        }
                    }
                }
            });
        }

        // 5. Top tenants by revenue
        var topTenantsCanvas = $('#saas_top_tenants_chart');
        if (topTenantsCanvas.length > 0) {
            new Chart(topTenantsCanvas, {
                type: 'horizontalBar',
                data: {
                    labels: <?= json_encode($tenant_labels); ?>,
                    datasets: [{
                        label: <?= json_encode(_l('perfex_saas_chart_revenue_label')); ?>,
                        data: <?= json_encode($tenant_totals); ?>,
                        backgroundColor: '#03a9f4',
                        hoverBackgroundColor: '#0398dc'
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
                                callback: saasMoneyTick
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: saasMoneyTooltip
                        }
                    }
                }
            });
        }

        // 6. Tenants per DB scheme
        var schemesCanvas = $('#saas_db_schemes_chart');
        if (schemesCanvas.length > 0) {
            new Chart(schemesCanvas, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($scheme_labels); ?>,
                    datasets: [{
                        data: <?= json_encode($scheme_totals); ?>,
                        backgroundColor: <?= json_encode($scheme_bg); ?>,
                        hoverBackgroundColor: <?= json_encode($scheme_bg); ?>
                    }]
                },
                options: {
                    maintainAspectRatio: false
                }
            });
        }
    });
</script>
