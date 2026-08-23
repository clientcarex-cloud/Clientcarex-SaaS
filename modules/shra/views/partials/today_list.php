<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (!count($today)) { ?>
    <div class="shra-empty" style="padding:36px"><i class="fa-solid fa-horse"></i>No sessions marked on this day yet.</div>
<?php } else { ?>
    <div class="shra-table-wrap"><table class="shra-table">
        <thead><tr><th>#</th><th>Rider</th><th>Package</th><th>Session</th><th>Trainer</th><th>Horse</th><th>Time</th><th></th></tr></thead>
        <tbody>
        <?php $i = count($today); foreach ($today as $a) { ?>
            <tr>
                <td class="shra-muted"><?php echo $i--; ?></td>
                <td><a href="<?php echo admin_url('shra/rider/' . $a->rider_id); ?>" class="strong"><?php echo html_escape($a->full_name); ?></a><span class="sub"><?php echo html_escape($a->rider_no); ?><?php echo $a->rider_type === 'guest' ? ' · Guest' : ''; ?></span></td>
                <td><?php echo html_escape($a->package_name); ?></td>
                <td><?php echo (int) $a->session_no; ?> / <?php echo (int) $a->sessions_total; ?><?php echo $a->session_no >= $a->sessions_total ? ' <span class="shra-badge shra-badge-gold">Done</span>' : ''; ?></td>
                <td><?php echo html_escape($a->trainer_name ?: '—'); ?></td>
                <td><?php echo html_escape($a->horse_name ?: '—'); ?></td>
                <td><?php echo $a->session_time ? date('h:i A', strtotime($a->session_time)) : '—'; ?></td>
                <td style="text-align:right"><button type="button" class="shra-btn shra-btn-outline shra-btn-sm shra-undo" data-id="<?php echo $a->id; ?>" title="Undo"><i class="fa fa-rotate-left"></i></button></td>
            </tr>
        <?php } ?>
        </tbody>
    </table></div>
<?php } ?>
