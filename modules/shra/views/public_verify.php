<?php defined('BASEPATH') or exit('No direct script access allowed'); include __DIR__ . '/_public_head.php'; ?>
    <div class="card">
        <div class="card-head" style="text-align:center">
            <?php if (!$rider) { ?>
                <span class="badge bad">Not found</span><h2 style="margin-top:8px">Unknown rider</h2><p>This code does not match any registered rider of the academy.</p>
            <?php } elseif ($certificate_no !== '' && !$cert) { ?>
                <span class="badge bad">Not verified</span><h2 style="margin-top:8px">Certificate not found</h2><p>Certificate <?php echo html_escape($certificate_no); ?> is not on record for this rider.</p>
            <?php } elseif ($cert) { ?>
                <span class="badge ok">Verified certificate</span><h2 style="margin-top:8px"><?php echo html_escape($rider->full_name); ?></h2><p>Completed the <?php echo html_escape($cert->package_name); ?> course (<?php echo (int) $cert->sessions_total; ?> sessions) on <?php echo _d($cert->completed_at ?: $cert->certificate_issued_at); ?>.</p>
            <?php } else { ?>
                <span class="badge <?php echo $rider->status === 'active' ? 'ok' : 'bad'; ?>"><?php echo $rider->status === 'active' ? 'Verified member' : 'Inactive member'; ?></span><h2 style="margin-top:8px"><?php echo html_escape($rider->full_name); ?></h2><p><?php echo $rider->rider_type === 'learner' ? 'Registered learner of the academy.' : 'Registered guest rider.'; ?></p>
            <?php } ?>
        </div>
        <?php if ($rider) { ?>
        <div class="card-body">
            <div class="kv">
                <div><div class="k">Rider no.</div><div class="v"><?php echo html_escape($rider->rider_no); ?></div></div>
                <?php if ($rider->membership_no) { ?><div><div class="k">Membership no.</div><div class="v"><?php echo html_escape($rider->membership_no); ?></div></div><?php } ?>
                <div><div class="k">Riding level</div><div class="v"><?php echo html_escape($rider->riding_level); ?></div></div>
                <div><div class="k">Member since</div><div class="v"><?php echo _d($rider->membership_issued_at ?: $rider->created_at); ?></div></div>
                <?php if ($cert) { ?><div><div class="k">Certificate no.</div><div class="v"><?php echo html_escape($cert->certificate_no); ?></div></div><div><div class="k">Issued</div><div class="v"><?php echo _d($cert->certificate_issued_at); ?></div></div><?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
<?php include __DIR__ . '/_public_foot.php'; ?>
