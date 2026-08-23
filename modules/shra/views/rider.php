<?php defined('BASEPATH') or exit('No direct script access allowed'); $r = $rider; ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'riders'; include __DIR__ . '/_nav.php'; ?>

    <div class="shra-profile">
        <div>
            <div class="shra-card">
                <div class="shra-card-body" style="text-align:center">
                    <span class="shra-avatar" style="width:72px;height:72px;font-size:30px"><?php echo strtoupper(mb_substr($r->full_name, 0, 1)); ?></span>
                    <h3 class="serif" style="margin:12px 0 2px;font-weight:700;font-size:26px"><?php echo html_escape($r->full_name); ?></h3>
                    <div class="shra-muted" style="font-size:12.5px"><?php echo html_escape($r->rider_no); ?><?php echo $r->membership_no ? ' · ' . html_escape($r->membership_no) : ''; ?></div>
                    <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                        <?php echo $r->rider_type === 'guest' ? '<span class="shra-badge shra-badge-gold">Guest rider</span>' : '<span class="shra-badge shra-badge-ink">Learner</span>'; ?>
                        <span class="shra-badge shra-badge-muted"><?php echo html_escape($r->riding_level); ?></span>
                        <?php echo shra_status_badge($r->status); ?>
                        <?php if ($r->is_minor) { ?><span class="shra-badge shra-badge-gold">Minor</span><?php } ?>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:center;margin-top:16px;flex-wrap:wrap">
                        <?php if (shra_can_billing()) { ?><a href="<?php echo admin_url('shra/billing?rider=' . $r->id); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-cash-register"></i> Bill</a><?php } ?>
                        <?php if (shra_can_attendance()) { ?><a href="<?php echo admin_url('shra/attendance?rider=' . $r->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa-solid fa-clipboard-check"></i> Session</a><?php } ?>
                        <?php if ($r->rider_type === 'learner') { ?><a href="<?php echo admin_url('shra/membership_pdf/' . $r->id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa-solid fa-id-card"></i> Membership PDF</a><?php } ?>
                        <?php if (shra_can('edit')) { ?><a href="<?php echo admin_url('shra/rider_form/' . $r->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-pen"></i></a><?php } ?>
                        <?php if (shra_can('delete')) { ?><a href="<?php echo admin_url('shra/delete_rider/' . $r->id); ?>" data-shra-confirm="Delete this rider and all their enrollments & attendance? Invoices are kept." class="shra-btn shra-btn-danger shra-btn-sm"><i class="fa fa-trash"></i></a><?php } ?>
                    </div>
                </div>
                <div class="shra-card-body" style="border-top:1px solid var(--line)">
                    <div class="shra-kv">
                        <div><div class="k">Mobile</div><div class="v"><?php echo html_escape($r->mobile); ?></div></div>
                        <div><div class="k">Email</div><div class="v"><?php echo html_escape($r->email ?: '—'); ?></div></div>
                        <div><div class="k">Gender</div><div class="v"><?php echo ucfirst((string) $r->gender) ?: '—'; ?></div></div>
                        <div><div class="k">Date of birth</div><div class="v"><?php echo $r->dob ? _d($r->dob) . ($r->age !== null ? ' (' . $r->age . ' yrs)' : '') : '—'; ?></div></div>
                        <div><div class="k">Place of birth</div><div class="v"><?php echo html_escape($r->place_of_birth ?: '—'); ?></div></div>
                        <div><div class="k">Status</div><div class="v"><?php echo ucfirst((string) $r->marital_status) ?: '—'; ?></div></div>
                        <div class="full"><div class="k">Address</div><div class="v"><?php echo nl2br(html_escape($r->address ?: '—')); ?></div></div>
                        <div><div class="k">Guardian</div><div class="v"><?php echo html_escape($r->guardian_name ?: '—'); ?></div></div>
                        <div><div class="k">Relationship</div><div class="v"><?php echo html_escape($r->guardian_relationship ?: '—'); ?></div></div>
                        <div class="full"><div class="k">Terms &amp; conditions</div><div class="v"><?php echo $r->terms_accepted ? '<i class="fa fa-check" style="color:var(--green)"></i> Accepted by ' . html_escape($r->terms_accepted_by) . ($r->terms_accepted_at ? ' on ' . _dt($r->terms_accepted_at) : '') : '<span style="color:var(--red)">Not accepted</span>'; ?></div></div>
                        <div><div class="k">Registered</div><div class="v"><?php echo _dt($r->created_at); ?></div></div>
                        <div><div class="k">Source</div><div class="v"><?php echo $r->source === 'self' ? 'Self (QR)' : 'Desk'; ?></div></div>
                        <?php if ($r->client_id) { ?><div class="full"><div class="k">CRM customer</div><div class="v"><a href="<?php echo admin_url('clients/client/' . $r->client_id); ?>">Open customer profile →</a></div></div><?php } ?>
                        <?php if ($r->notes) { ?><div class="full"><div class="k">Notes</div><div class="v" style="font-weight:400"><?php echo nl2br(html_escape($r->notes)); ?></div></div><?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="shra-card">
                <div class="shra-card-head"><h4>Packages &amp; bills</h4></div>
                <?php if (!count($enrollments)) { ?>
                    <div class="shra-empty" style="padding:30px"><i class="fa-solid fa-ticket"></i>No package billed yet.</div>
                <?php } else { ?>
                <div class="shra-table-wrap"><table class="shra-table">
                    <thead><tr><th>Package</th><th>Progress</th><th>Paid</th><th>Invoice</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($enrollments as $e) { $pct = $e->sessions_total ? round($e->sessions_used / $e->sessions_total * 100) : 0; ?>
                        <tr>
                            <td class="strong"><?php echo html_escape($e->package_name); ?> <span class="shra-muted" style="font-weight:400">· <?php echo $e->audience; ?></span><span class="sub"><?php echo html_escape($e->enrollment_no); ?> · <?php echo _d($e->created_at); ?><?php echo $e->expires_at ? ' · valid till ' . _d($e->expires_at) : ''; ?></span></td>
                            <td style="min-width:140px"><?php echo (int) $e->sessions_used; ?> / <?php echo (int) $e->sessions_total; ?><div class="shra-progress" style="margin-top:5px"><span style="width:<?php echo $pct; ?>%"></span></div></td>
                            <td class="num"><?php echo shra_money($e->paid_amount); ?><?php echo $e->discount_percent > 0 ? '<span class="sub">' . ($e->discount_percent + 0) . '% off</span>' : ''; ?></td>
                            <td><?php echo $e->invoice_id ? '<a href="' . admin_url('invoices/list_invoices/' . $e->invoice_id) . '">' . html_escape(format_invoice_number($e->invoice_id)) . '</a>' : '—'; ?></td>
                            <td><?php echo shra_status_badge($e->status); ?><?php echo $e->certificate_no ? '<span class="sub">' . html_escape($e->certificate_no) . '</span>' : ''; ?></td>
                            <td style="white-space:nowrap;text-align:right">
                                <?php if ($e->certificate_no) { ?><a href="<?php echo admin_url('shra/certificate_pdf/' . $e->id); ?>" target="_blank" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-award"></i> Certificate</a>
                                <?php } elseif (!$e->is_guest && $e->status === 'completed' && shra_can('edit')) { ?><a href="<?php echo admin_url('shra/certificate/' . $e->id); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-award"></i> Issue certificate</a>
                                <?php } elseif (!$e->is_guest && $e->status === 'active' && shra_can('edit')) { ?><a href="<?php echo admin_url('shra/complete/' . $e->id); ?>" data-shra-confirm="Mark this course as completed and issue the certificate?" class="shra-btn shra-btn-outline shra-btn-sm">Complete course</a><?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table></div>
                <?php } ?>
            </div>

            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4>Class sessions</h4><span class="shra-pill"><?php echo count($attendance); ?> attended</span></div>
                <?php if (!count($attendance)) { ?>
                    <div class="shra-empty" style="padding:30px"><i class="fa-solid fa-horse"></i>No sessions yet.</div>
                <?php } else { ?>
                <div class="shra-table-wrap"><table class="shra-table">
                    <thead><tr><th>Date</th><th>Package</th><th>Session</th><th>Trainer</th><th>Horse</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($attendance as $a) { ?>
                        <tr>
                            <td><?php echo _d($a->session_date); ?><span class="sub"><?php echo $a->session_time ? date('h:i A', strtotime($a->session_time)) : ''; ?></span></td>
                            <td><?php echo html_escape($a->package_name); ?></td>
                            <td><?php echo (int) $a->session_no; ?> / <?php echo (int) $a->sessions_total; ?></td>
                            <td><?php echo html_escape($a->trainer_name ?: '—'); ?></td>
                            <td><?php echo html_escape($a->horse_name ?: '—'); ?></td>
                            <td class="shra-muted"><?php echo html_escape($a->notes ?: ''); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table></div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
