<?php defined('BASEPATH') or exit('No direct script access allowed');
init_head();
$o       = $order;
$late    = duns_is_overdue($o);
$can_ed  = duns_can('edit');
$track   = duns_public_url('status/' . $o->ref);
$staff_names = [];
foreach ($staff as $s) {
    $staff_names[(int) $s['staffid']] = trim($s['firstname'] . ' ' . $s['lastname']);
}
?>
<div id="wrapper">
<div class="content duns-wrap">
    <?php $this->load->view('admin/_nav'); ?>

    <div class="duns-head" style="margin-bottom:12px">
        <div>
            <a href="<?php echo admin_url('duns_support'); ?>" class="text-muted"><i class="fa-solid fa-arrow-left"></i> All orders</a>
            <h2 style="margin:6px 0 0;font-size:22px;font-weight:700"><?php echo html_escape($o->order_no); ?> · <?php echo html_escape($o->company_name); ?> <?php echo duns_status_badge($o->status); ?></h2>
        </div>
        <div class="duns-actions">
            <a class="btn btn-success" target="_blank" href="<?php echo html_escape(duns_wa_link($o->director_mobile, 'Hi ' . explode(' ', $o->director_name)[0] . ', this is about your DUNS order ' . $o->order_no . '.')); ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <a class="btn btn-default" href="mailto:<?php echo html_escape($o->director_email); ?>?subject=<?php echo rawurlencode('Your DUNS order ' . $o->order_no); ?>"><i class="fa-solid fa-envelope"></i> Email</a>
            <?php if (duns_can('delete')) { ?>
            <?php echo form_open(admin_url('duns_support/delete/' . $o->id), ['style' => 'display:inline', 'onsubmit' => "return confirm('Delete this order and its documents? The invoice (if any) is kept.');"]); ?>
                <button class="btn btn-danger"><i class="fa-solid fa-trash"></i></button>
            <?php echo form_close(); ?>
            <?php } ?>
        </div>
    </div>

    <div class="row">
        <!-- ═════ Left: details, documents, timeline ═════ -->
        <div class="col-md-8">
            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-building"></i> Company &amp; director</h4>
                    <?php if ($can_ed) { ?><a href="#" class="btn btn-default btn-sm" onclick="$('#duns-edit').toggle();$('#duns-view').toggle();return false;"><i class="fa-solid fa-pen"></i> Edit</a><?php } ?>
                </div>
                <div class="duns-card-body">
                    <dl class="duns-kv" id="duns-view">
                        <dt>Company</dt><dd><?php echo html_escape($o->company_name); ?></dd>
                        <dt>CIN / Reg. no.</dt><dd><?php echo $o->cin ? html_escape($o->cin) : '—'; ?></dd>
                        <dt>Director</dt><dd><?php echo html_escape($o->director_name); ?></dd>
                        <dt>Director email</dt><dd><a href="mailto:<?php echo html_escape($o->director_email); ?>"><?php echo html_escape($o->director_email); ?></a></dd>
                        <dt>Director mobile</dt><dd><a href="tel:<?php echo html_escape($o->director_mobile); ?>"><?php echo html_escape($o->director_mobile); ?></a></dd>
                        <dt>Work email (OTP)</dt><dd><a href="mailto:<?php echo html_escape($o->work_email); ?>"><?php echo html_escape($o->work_email); ?></a></dd>
                        <dt>Customer notes</dt><dd style="font-weight:400;white-space:pre-line"><?php echo $o->notes ? html_escape($o->notes) : '—'; ?></dd>
                        <dt>Assigned to</dt><dd><?php echo $o->assigned_to && isset($staff_names[(int) $o->assigned_to]) ? html_escape($staff_names[(int) $o->assigned_to]) : '—'; ?></dd>
                        <dt>Source</dt><dd style="font-weight:400"><?php
                            $src = array_filter([$o->utm_source, $o->utm_medium, $o->utm_campaign]);
                            echo count($src) ? html_escape(implode(' / ', $src)) : 'Direct';
                            echo $o->fbclid ? ' <span class="duns-badge duns-badge-blue">Meta click</span>' : '';
                        ?></dd>
                    </dl>
                    <?php if ($can_ed) { ?>
                    <div id="duns-edit" style="display:none">
                        <?php echo form_open(admin_url('duns_support/update/' . $o->id)); ?>
                        <div class="row">
                            <div class="col-md-8"><div class="form-group"><label>Company name</label><input name="company_name" class="form-control" value="<?php echo html_escape($o->company_name); ?>"></div></div>
                            <div class="col-md-4"><div class="form-group"><label>CIN</label><input name="cin" class="form-control" value="<?php echo html_escape($o->cin); ?>"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Director name</label><input name="director_name" class="form-control" value="<?php echo html_escape($o->director_name); ?>"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Director mobile</label><input name="director_mobile" class="form-control" value="<?php echo html_escape($o->director_mobile); ?>"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Director email</label><input type="email" name="director_email" class="form-control" value="<?php echo html_escape($o->director_email); ?>"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Work email</label><input type="email" name="work_email" class="form-control" value="<?php echo html_escape($o->work_email); ?>"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Assigned to</label>
                                <select name="assigned_to" class="form-control"><option value="">— Nobody —</option>
                                    <?php foreach ($staff_names as $sid => $sn) { ?><option value="<?php echo $sid; ?>" <?php echo (int) $o->assigned_to === $sid ? 'selected' : ''; ?>><?php echo html_escape($sn); ?></option><?php } ?>
                                </select></div></div>
                            <div class="col-md-12"><div class="form-group"><label>Customer notes</label><textarea name="notes" class="form-control" rows="2"><?php echo html_escape($o->notes); ?></textarea></div></div>
                        </div>
                        <button class="btn btn-primary">Save changes</button>
                        <?php echo form_close(); ?>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-folder-open"></i> Documents</h4></div>
                <div class="duns-card-body">
                    <?php if (!count($files)) { ?>
                        <p class="text-muted" style="margin:0">No documents uploaded yet.</p>
                    <?php } else { ?>
                    <ul class="duns-files">
                        <?php foreach ($files as $fl) { $is_pdf = strpos((string) $fl->mime, 'pdf') !== false; ?>
                        <li>
                            <span class="ic"><i class="fa-solid <?php echo $is_pdf ? 'fa-file-pdf' : 'fa-file-image'; ?>"></i></span>
                            <div class="nm"><b><?php echo html_escape($fl->original_name); ?></b>
                                <small><?php echo $fl->doc_type === 'coi' ? 'Certificate of Incorporation' : 'Other document'; ?> · <?php echo round($fl->size / 1024); ?> KB · <?php echo $fl->uploaded_by ? 'staff' : 'customer'; ?> · <?php echo duns_datetime($fl->created_at); ?></small></div>
                            <a class="btn btn-default btn-sm" target="_blank" href="<?php echo admin_url('duns_support/file/' . $fl->id); ?>"><i class="fa-solid fa-eye"></i> View</a>
                            <a class="btn btn-default btn-sm" href="<?php echo admin_url('duns_support/file/' . $fl->id . '?dl=1'); ?>"><i class="fa-solid fa-download"></i></a>
                            <?php if ($can_ed) { ?>
                            <?php echo form_open(admin_url('duns_support/delete_file/' . $fl->id), ['style' => 'display:inline', 'onsubmit' => "return confirm('Remove this document?');"]); ?><button class="btn btn-default btn-sm text-danger"><i class="fa-solid fa-trash"></i></button><?php echo form_close(); ?>
                            <?php } ?>
                        </li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                    <?php if ($can_ed) { ?>
                    <?php echo form_open_multipart(admin_url('duns_support/upload/' . $o->id), ['class' => 'duns-filters', 'style' => 'margin-top:14px']); ?>
                        <div class="form-group"><label>Add a document</label><input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                        <div class="form-group"><label>Type</label><select name="doc_type" class="form-control"><option value="coi">Certificate of Incorporation</option><option value="other">Other</option></select></div>
                        <button class="btn btn-default" style="height:34px"><i class="fa-solid fa-upload"></i> Upload</button>
                    <?php echo form_close(); ?>
                    <?php } ?>
                </div>
            </div>

            <div class="duns-card" id="timeline">
                <div class="duns-card-head"><h4><i class="fa-solid fa-clock-rotate-left"></i> Timeline &amp; notes</h4></div>
                <div class="duns-card-body">
                    <?php if ($can_ed) { ?>
                    <?php echo form_open(admin_url('duns_support/note/' . $o->id), ['style' => 'margin-bottom:16px']); ?>
                        <textarea name="message" class="form-control" rows="2" placeholder="Add an internal note (application ref, call summary, OTP received…)"></textarea>
                        <button class="btn btn-primary btn-sm" style="margin-top:8px"><i class="fa-solid fa-plus"></i> Add note</button>
                    <?php echo form_close(); ?>
                    <?php } ?>
                    <ul class="duns-timeline">
                        <?php foreach ($events as $ev) { ?>
                        <li class="t-<?php echo html_escape($ev->type); ?>">
                            <div class="msg"><?php echo html_escape($ev->message); ?></div>
                            <small><?php echo duns_datetime($ev->created_at); ?> · <?php echo $ev->staff_id ? html_escape(get_staff_full_name($ev->staff_id)) : 'System'; ?> · <?php echo ucfirst($ev->type); ?></small>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ═════ Right: deadline, delivery, status, payment ═════ -->
        <div class="col-md-4">
            <?php if ($o->status === 'completed' && $o->duns_number) { ?>
                <div class="duns-number"><small>DUNS number delivered</small><b><?php echo duns_format_number($o->duns_number); ?></b><span style="font-size:12.5px;color:#bbf7d0"><?php echo duns_datetime($o->completed_at); ?><?php echo $o->due_at && strtotime($o->completed_at) > strtotime($o->due_at) ? ' · after deadline' : ' · on time'; ?></span></div>
            <?php } elseif (duns_is_open($o)) { ?>
                <div class="duns-deadline<?php echo $late ? ' late' : ''; ?>">
                    <small><?php echo $late ? 'Overdue — refund risk' : 'Deliver by'; ?></small>
                    <b><?php echo duns_time_left($o); ?></b>
                    <span><?php echo duns_datetime($o->due_at); ?> · <?php echo duns_hours_label($o->delivery_hours); ?> plan</span>
                </div>
            <?php } ?>

            <?php if ($can_ed && in_array($o->status, ['paid', 'processing', 'completed'], true)) { ?>
            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-award"></i> <?php echo $o->status === 'completed' ? 'Update / resend DUNS' : 'Deliver DUNS number'; ?></h4></div>
                <div class="duns-card-body">
                    <?php echo form_open(admin_url('duns_support/deliver/' . $o->id)); ?>
                        <div class="form-group"><label>DUNS number (9 digits)</label>
                            <input name="duns_number" class="form-control input-lg" inputmode="numeric" maxlength="11" placeholder="12-345-6789" value="<?php echo $o->duns_number ? duns_format_number($o->duns_number) : ''; ?>" required style="letter-spacing:2px;font-weight:700"></div>
                        <div class="form-group"><label>Message to customer <span class="text-muted">(optional)</span></label>
                            <textarea name="note" class="form-control" rows="2" placeholder="e.g. It may take 24-48 hours to appear in Apple's lookup tool."></textarea></div>
                        <div class="checkbox"><input type="checkbox" id="dn-send_email" name="send_email" value="1" checked><label for="dn-send_email">Email it to <?php echo html_escape($o->director_email); ?><?php echo $o->work_email !== $o->director_email ? ' and the work email' : ''; ?></label></div>
                        <button class="btn btn-success btn-block"><i class="fa-solid fa-paper-plane"></i> <?php echo $o->status === 'completed' ? 'Save & resend' : 'Deliver & complete order'; ?></button>
                    <?php echo form_close(); ?>
                </div>
            </div>
            <?php } ?>

            <?php if ($can_ed) { ?>
            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-arrows-rotate"></i> Change status</h4></div>
                <div class="duns-card-body">
                    <?php echo form_open(admin_url('duns_support/status/' . $o->id)); ?>
                        <div class="form-group"><select name="status" class="form-control">
                            <?php foreach (duns_statuses() as $k => $s) { if ($k === 'completed') { continue; } ?>
                            <option value="<?php echo $k; ?>" <?php echo $o->status === $k ? 'selected' : ''; ?>><?php echo $s['label']; ?></option>
                            <?php } ?>
                        </select></div>
                        <div class="form-group"><input name="note" class="form-control" placeholder="Reason / note (optional)"></div>
                        <button class="btn btn-default btn-block">Update status</button>
                        <?php if ($o->status === 'pending_payment') { ?><p class="duns-help">Choosing "Paid — in queue" on an unpaid order records it as paid offline and starts the delivery clock now.</p><?php } ?>
                    <?php echo form_close(); ?>
                </div>
            </div>
            <?php } ?>

            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-indian-rupee-sign"></i> Payment</h4></div>
                <div class="duns-card-body">
                    <dl class="duns-kv" style="grid-template-columns:110px 1fr">
                        <dt>Plan</dt><dd><?php echo html_escape($o->plan_name); ?> · <?php echo duns_hours_label($o->delivery_hours); ?></dd>
                        <dt>Price</dt><dd><?php echo duns_money($o->amount); ?></dd>
                        <dt>Paid</dt><dd><?php echo duns_money($o->amount_paid); ?></dd>
                        <dt>Gateway</dt><dd><?php echo $o->gateway ? html_escape($o->gateway) : '—'; ?></dd>
                        <dt>Paid at</dt><dd><?php echo duns_datetime($o->paid_at); ?></dd>
                        <dt>Invoice</dt><dd><?php if ($invoice) { ?><a href="<?php echo admin_url('invoices/list_invoices/' . $invoice->id); ?>" target="_blank"><?php echo format_invoice_number($invoice->id); ?></a> <?php echo format_invoice_status($invoice->status, '', true); ?><?php } else { ?>—<?php } ?></dd>
                        <dt>Placed</dt><dd><?php echo duns_datetime($o->created_at); ?></dd>
                    </dl>
                </div>
            </div>

            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-link"></i> Customer links</h4></div>
                <div class="duns-card-body">
                    <div class="duns-help" style="margin:0 0 6px">Tracking page (share with the customer):</div>
                    <div class="duns-urlbox"><span style="flex:1"><?php echo html_escape($track); ?></span><a href="#" onclick="navigator.clipboard.writeText(<?php echo html_escape(json_encode($track)); ?>);alert_float('success','Copied');return false;"><i class="fa-regular fa-copy"></i></a></div>
                    <?php if ($o->status === 'pending_payment') { $pay = duns_public_url('pay/' . $o->ref); ?>
                    <div class="duns-help" style="margin:12px 0 6px">Payment link (send if they dropped off):</div>
                    <div class="duns-urlbox"><span style="flex:1"><?php echo html_escape($pay); ?></span><a href="#" onclick="navigator.clipboard.writeText(<?php echo html_escape(json_encode($pay)); ?>);alert_float('success','Copied');return false;"><i class="fa-regular fa-copy"></i></a></div>
                    <a class="btn btn-success btn-block" style="margin-top:10px" target="_blank" href="<?php echo html_escape(duns_wa_link($o->director_mobile, 'Hi ' . explode(' ', $o->director_name)[0] . ', your DUNS order ' . $o->order_no . ' is saved. Complete the payment here to start the ' . duns_hours_label($o->delivery_hours) . ' delivery: ' . $pay)); ?>"><i class="fa-brands fa-whatsapp"></i> Send payment link</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
