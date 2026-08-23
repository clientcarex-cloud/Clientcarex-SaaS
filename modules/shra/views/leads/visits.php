<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'visits'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $today = date('Y-m-d');
    $tabs  = [[$today, 'Today']];
    if ($weekend['sat'] !== $today) { $tabs[] = [$weekend['sat'], 'Sat ' . date('d M', strtotime($weekend['sat']))]; }
    if ($weekend['sun'] !== $today) { $tabs[] = [$weekend['sun'], 'Sun ' . date('d M', strtotime($weekend['sun']))]; }
    $custom = !in_array($date, array_column($tabs, 0));
    $total  = 0; foreach ($groups as $g) { $total += count($g); }
    ?>
    <div class="shra-toolbar" style="justify-content:space-between">
        <div class="shra-seg" style="margin:0">
            <?php foreach ($tabs as $t) { ?><label><input type="radio" name="vd" <?php echo $date === $t[0] ? 'checked' : ''; ?> onclick="location.href='<?php echo admin_url('shra/shra_leads/visits?date=' . $t[0]); ?>'"><span><?php echo $t[1]; ?> <b><?php echo (int) ($counts[$t[0]] ?? 0); ?></b></span></label><?php } ?>
            <label><input type="radio" name="vd" <?php echo $custom ? 'checked' : ''; ?>><span><input type="date" value="<?php echo $date; ?>" onchange="location.href='<?php echo admin_url('shra/shra_leads/visits?date='); ?>'+this.value" style="border:0;background:transparent;font:inherit"></span></label>
        </div>
        <div class="shra-toolbar" style="margin:0">
            <div class="shra-search" style="min-width:260px"><i class="fa fa-search"></i><input type="text" id="shra-visit-q" class="form-control" placeholder="Walk-in? Search phone / name" autocomplete="off"></div>
            <div id="shra-visit-hit" style="display:none"></div>
        </div>
    </div>

    <h4 class="shra-title" style="margin:6px 0 14px"><?php echo date('l, d M Y', strtotime($date)); ?> <span class="thin">· <?php echo $total; ?> expected visitor<?php echo $total == 1 ? '' : 's'; ?></span></h4>

    <?php if (!$total) { ?>
        <div class="shra-card"><div class="shra-empty"><i class="fa fa-calendar-check"></i>No visits scheduled for this day.<br>Agents book visits from the Log-call / <i class="fa fa-calendar-plus"></i> button on a lead.</div></div>
    <?php } else { foreach ($groups as $slot => $rows) { ?>
        <div class="shra-card" style="margin-bottom:14px">
            <div class="shra-card-head"><h4><i class="fa fa-clock" style="color:var(--gold)"></i> <?php echo html_escape(shra_slot($slot)); ?></h4><span class="shra-pill"><?php echo count($rows); ?></span></div>
            <div class="shra-lead-list"><?php foreach ($rows as $l) { include __DIR__ . '/partials/lead_card.php'; } ?></div>
        </div>
    <?php } } ?>

    <?php if (count($no_shows)) { ?>
    <div class="shra-card" style="margin-top:18px">
        <div class="shra-card-head"><h4><i class="fa fa-user-slash" style="color:var(--red)"></i> Past visits never marked</h4><span class="shra-pill"><?php echo count($no_shows); ?></span></div>
        <div class="help" style="padding:8px 22px 0">Mark <b>Arrived</b> if they did come, or <b>No-show</b> so the agent re-books. Nothing stays in limbo.</div>
        <div class="shra-lead-list"><?php foreach ($no_shows as $l) { include __DIR__ . '/partials/lead_card.php'; } ?></div>
    </div>
    <?php } ?>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
<script>
$(function () {
    // Walk-in search: phone → existing lead (mark arrived) or offer a new lead with source Walk-in
    var t = null;
    $('#shra-visit-q').on('input', function () {
        var v = $.trim($(this).val()); clearTimeout(t);
        if (v.replace(/\D/g, '').length < 8) { $('#shra-visit-hit').hide(); return; }
        t = setTimeout(function () {
            $.getJSON(<?php echo json_encode(admin_url('shra/shra_leads/check_phone')); ?>, { phone: v }, function (r) {
                if (r.exists) {
                    $('#shra-visit-hit').html('<div class="shra-alert shra-alert-warn" style="display:flex;gap:10px;align-items:center"><span>Lead <a href="' + r.url + '"><b>' + SHRA.esc(r.name) + '</b></a> · ' + SHRA.esc(r.stage) + ' · ' + SHRA.esc(r.agent) + '</span><button type="button" class="shra-btn shra-btn-primary shra-btn-sm" data-shra-act="visited" data-lead="' + r.id + '"><i class="fa fa-person-walking"></i> Arrived</button></div>').show();
                } else {
                    $('#shra-visit-hit').html('<div class="shra-alert shra-alert-ok" style="display:flex;gap:10px;align-items:center"><span>Not a lead yet.</span><button type="button" class="shra-btn shra-btn-gold shra-btn-sm" id="shra-walkin-add"><i class="fa fa-plus"></i> Add as walk-in</button></div>').show();
                }
            });
        }, 250);
    });
    $(document).on('click', '#shra-walkin-add', function () {
        $('[data-shra-lead-add]').first().trigger('click');
        var $m = $('#shra-lead-add');
        setTimeout(function () {
            $m.find('[name=phone]').val($('#shra-visit-q').val()).trigger('input');
            $m.find('[name=mark_visited]').val('1');
            $m.find('[name=source] option').filter(function () { return /walk/i.test($(this).text()); }).prop('selected', true);
            $m.find('[name=name]').focus();
        }, 350);
    });
    // When a visited action succeeds on a walk-in hit, reload to show them on the board
    SHRA.onLeadUpdated = function () { if ($('#shra-visit-hit').is(':visible')) { location.reload(); } };
});
</script>
</body>
</html>
