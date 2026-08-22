<?php
/**
 * Channel Statistics Row
 * 
 * Shows 4 mini stat cards: Credits Left, Total Sent, Sent Today, Success Rate
 * 
 * Expected variables:
 *   $stats_channel       - channel key for $channel_stats lookup (e.g. 'sms', 'whatsapp', 'email')
 *   $stats_color         - hex color for accent (e.g. '#6366f1')
 *   $channel_stats       - array from controller [channel => [sent_total, sent_today, sent_success, sent_failed]]
 *   $allocations         - (optional) allocation object with {prefix}_promo_count and {prefix}_trans_count
 *   $stats_alloc_prefix  - (optional) allocation column prefix (e.g. 'sms', 'whatsapp', 'aicall')
 */

$_stats = isset($channel_stats[$stats_channel]) ? $channel_stats[$stats_channel] : [
    'sent_total' => 0, 'sent_today' => 0, 'sent_success' => 0, 'sent_failed' => 0
];

// Credits remaining = sum of promo + trans count from allocations (if available)
$_credits_left = '—';
if (isset($allocations) && $allocations && isset($stats_alloc_prefix)) {
    $promo_col = $stats_alloc_prefix . '_promo_count';
    $trans_col = $stats_alloc_prefix . '_trans_count';
    $promo_val = isset($allocations->$promo_col) ? (int) $allocations->$promo_col : 0;
    $trans_val = isset($allocations->$trans_col) ? (int) $allocations->$trans_col : 0;
    $_credits_left = number_format($promo_val + $trans_val);
}

$_success_rate = $_stats['sent_total'] > 0
    ? round(($_stats['sent_success'] / $_stats['sent_total']) * 100, 1)
    : 0;

$_rate_color = $_success_rate >= 90 ? '#22c55e' : ($_success_rate >= 70 ? '#f59e0b' : '#ef4444');
if ($_stats['sent_total'] === 0) $_rate_color = '#94a3b8';
?>
<div class="ccx-stats-row" style="--stat-accent: <?= $stats_color; ?>;">
    <div class="ccx-stat-card">
        <div class="stat-icon" style="background: <?= $stats_color; ?>18; color: <?= $stats_color; ?>;">
            <i class="fa fa-database"></i>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= $_credits_left; ?></div>
            <div class="stat-label">Credits Left</div>
        </div>
    </div>
    <div class="ccx-stat-card">
        <div class="stat-icon" style="background: <?= $stats_color; ?>18; color: <?= $stats_color; ?>;">
            <i class="fa fa-paper-plane"></i>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($_stats['sent_total']); ?></div>
            <div class="stat-label">Total Sent</div>
        </div>
    </div>
    <div class="ccx-stat-card">
        <div class="stat-icon" style="background: <?= $stats_color; ?>18; color: <?= $stats_color; ?>;">
            <i class="fa fa-calendar-check-o"></i>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($_stats['sent_today']); ?></div>
            <div class="stat-label">Sent Today</div>
        </div>
    </div>
    <div class="ccx-stat-card">
        <div class="stat-icon" style="background: <?= $_rate_color; ?>18; color: <?= $_rate_color; ?>;">
            <i class="fa fa-check-circle"></i>
        </div>
        <div class="stat-body">
            <div class="stat-value" style="color: <?= $_rate_color; ?>;"><?= $_stats['sent_total'] > 0 ? $_success_rate . '%' : '—'; ?></div>
            <div class="stat-label">Success Rate</div>
        </div>
    </div>
</div>
