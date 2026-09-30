<?php defined('BASEPATH') or exit('No direct script access allowed');
init_head();
$o  = function ($k) { return html_escape((string) get_option($k)); };
$on = function ($k) { return get_option($k) == '1' ? 'checked' : ''; };
$use_master = get_option('duns_pay_use_master') == '1';
?>
<div id="wrapper">
<div class="content duns-wrap">
    <?php $this->load->view('admin/_nav'); ?>
    <?php echo form_open(admin_url('duns_support/settings')); ?>
    <div class="row">
        <div class="col-md-7">
            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-bullhorn"></i> Landing page</h4></div>
                <div class="duns-card-body">
                    <div class="duns-help" style="margin:0 0 6px">Use this URL in your Meta / Google ads:</div>
                    <div class="duns-urlbox" style="margin-bottom:14px"><span style="flex:1"><?php echo duns_public_url(); ?></span><a href="<?php echo duns_public_url(); ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
                    <div class="checkbox"><input type="checkbox" id="dn-duns_landing_enabled" name="duns_landing_enabled" value="1" <?php echo $on('duns_landing_enabled'); ?>><label for="dn-duns_landing_enabled">Landing page is live</label></div>
                    <div class="form-group"><label>Brand name</label><input name="duns_brand_name" class="form-control" value="<?php echo $o('duns_brand_name'); ?>"><div class="duns-help">Shown in the header, footer and emails. The logo is your company logo from Settings → General.</div></div>
                    <div class="form-group"><label>Headline</label><input name="duns_hero_title" class="form-control" value="<?php echo $o('duns_hero_title'); ?>"><div class="duns-help">Ending it with "in as little as 1 hour" gets that part highlighted.</div></div>
                    <div class="form-group"><label>Sub-headline</label><textarea name="duns_hero_subtitle" class="form-control" rows="3"><?php echo $o('duns_hero_subtitle'); ?></textarea></div>
                    <div class="form-group"><label>Terms &amp; refund policy URL <span class="text-muted">(optional)</span></label><input name="duns_terms_url" class="form-control" value="<?php echo $o('duns_terms_url'); ?>" placeholder="https://…/refund"></div>
                </div>
            </div>

            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-credit-card"></i> Online payment</h4></div>
                <div class="duns-card-body">
                    <div class="checkbox"><input type="checkbox" id="dn-duns_pay_enabled" name="duns_pay_enabled" value="1" <?php echo $on('duns_pay_enabled'); ?>><label for="dn-duns_pay_enabled">Take payment online after the order form</label></div>
                    <div class="duns-help">An invoice is raised when the buyer starts the checkout; the order moves to "Paid — in queue" and the delivery clock starts the moment the gateway confirms the money. Gateways are set up in <a href="<?php echo admin_url('settings?group=payment_gateways'); ?>">Settings → Payment Gateways</a><?php echo $is_tenant ? ' (or on the master account)' : ''; ?>.</div>
                    <?php if ($is_tenant) { ?>
                    <div class="checkbox"><input type="checkbox" id="dn-duns_pay_use_master" name="duns_pay_use_master" value="1" <?php echo $use_master ? 'checked' : ''; ?>><label for="dn-duns_pay_use_master">Use the master account's gateway credentials</label></div>
                    <?php } ?>
                    <?php if (!count($gateways)) { ?>
                        <div class="alert alert-warning" style="margin-top:10px">No payment gateway is registered on this installation.</div>
                    <?php } else { ?>
                    <table class="duns-table" style="margin-top:10px">
                        <thead><tr><th style="width:34px"></th><th>Gateway</th><th>Status</th><th>Currencies</th></tr></thead>
                        <tbody>
                        <?php foreach ($gateways as $id => $g) { ?>
                            <tr>
                                <td><input type="checkbox" name="duns_pay_gateways[]" value="<?php echo html_escape($id); ?>" <?php echo $g['selected'] ? 'checked' : ''; ?>></td>
                                <td><b><?php echo html_escape($g['name']); ?></b> <?php if ($g['test_mode']) { ?><span class="duns-badge duns-badge-amber">Test mode</span><?php } ?>
                                    <?php if ($g['selected'] && !isset($usable[$id]) && $g['active'] && $g['configured']) { ?><div class="duns-help">Does not accept <?php echo html_escape(get_base_currency()->name); ?> — it will not be offered.</div><?php } ?></td>
                                <td><?php echo !$g['configured'] ? '<span class="duns-badge duns-badge-muted">Not configured</span>' : (!$g['active'] ? '<span class="duns-badge duns-badge-red">Off</span>' : '<span class="duns-badge duns-badge-green">Active</span>'); ?></td>
                                <td><?php echo html_escape($g['currencies'] ?: '—'); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <div class="duns-help" style="margin-top:8px"><?php echo count($usable)
                        ? 'Buyers will see: <b>' . html_escape(implode(', ', array_column($usable, 'name'))) . '</b>.'
                        : '<span class="text-danger">No gateway is ready — tick one that is active, configured and accepts ' . html_escape(get_base_currency()->name) . '. Until then orders are saved and your team follows up for payment.</span>'; ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-headset"></i> Contact on the page</h4></div>
                <div class="duns-card-body">
                    <div class="form-group"><label>WhatsApp number</label><input name="duns_support_whatsapp" class="form-control" value="<?php echo $o('duns_support_whatsapp'); ?>" placeholder="+91 98765 43210"><div class="duns-help">Adds the floating WhatsApp button and "Chat on WhatsApp" links.</div></div>
                    <div class="form-group"><label>Phone</label><input name="duns_support_phone" class="form-control" value="<?php echo $o('duns_support_phone'); ?>"></div>
                    <div class="form-group"><label>Support email</label><input type="email" name="duns_support_email" class="form-control" value="<?php echo $o('duns_support_email'); ?>"></div>
                </div>
            </div>

            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-bell"></i> Notifications</h4></div>
                <div class="duns-card-body">
                    <div class="form-group"><label>Email new paid orders to</label><textarea name="duns_notify_emails" class="form-control" rows="2" placeholder="ops@company.com, owner@company.com"><?php echo $o('duns_notify_emails'); ?></textarea><div class="duns-help">Comma separated. Admins and staff with DUNS Support access also get an in-app notification.</div></div>
                    <div class="checkbox"><input type="checkbox" id="dn-duns_email_customer" name="duns_email_customer" value="1" <?php echo $on('duns_email_customer'); ?>><label for="dn-duns_email_customer">Email the customer a payment confirmation with their tracking link</label></div>
                </div>
            </div>

            <div class="duns-card">
                <div class="duns-card-head"><h4><i class="fa-solid fa-chart-line"></i> Ad tracking</h4></div>
                <div class="duns-card-body">
                    <div class="form-group"><label>Meta Pixel ID</label><input name="duns_meta_pixel_id" class="form-control" value="<?php echo $o('duns_meta_pixel_id'); ?>" placeholder="123456789012345"></div>
                    <div class="duns-help" style="margin:-6px 0 12px">Fires PageView &amp; ViewContent (landing), InitiateCheckout &amp; Lead (form), AddPaymentInfo (checkout) and <b>Purchase with value</b> once per paid order — optimise your Meta campaign for Purchase.</div>
                    <div class="form-group"><label>GA4 measurement ID</label><input name="duns_ga4_id" class="form-control" value="<?php echo $o('duns_ga4_id'); ?>" placeholder="G-XXXXXXX"></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Google Ads ID</label><input name="duns_gads_id" class="form-control" value="<?php echo $o('duns_gads_id'); ?>" placeholder="AW-XXXXXXX"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Conversion label</label><input name="duns_gads_label" class="form-control" value="<?php echo $o('duns_gads_label'); ?>"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="duns-card" id="templates">
        <div class="duns-card-head"><h4><i class="fa-solid fa-envelope-open-text"></i> Email templates</h4></div>
        <div class="duns-card-body">
            <p class="duns-help" style="margin-top:0">Pre-filled on the order page, where staff can still edit each email before sending. Placeholders: <code><?php echo html_escape(implode(' ', duns_placeholders())); ?></code>. Every email gets a "Track your order" button linking to the customer's order page.</p>
            <div class="row">
                <div class="col-md-6">
                    <div class="duns-sec" style="margin-top:6px">DUNS delivery (order completed)</div>
                    <div class="form-group"><label>Subject</label><input name="duns_tpl_delivered_subject" class="form-control" value="<?php echo html_escape(duns_template('delivered_subject')); ?>"></div>
                    <div class="form-group"><label>Message</label><textarea name="duns_tpl_delivered_body" class="form-control" rows="12"><?php echo html_escape(duns_template('delivered_body')); ?></textarea></div>
                </div>
                <div class="col-md-6">
                    <div class="duns-sec" style="margin-top:6px">General message (Send email button)</div>
                    <div class="form-group"><label>Subject</label><input name="duns_tpl_message_subject" class="form-control" value="<?php echo html_escape(duns_template('message_subject')); ?>"></div>
                    <div class="form-group"><label>Message</label><textarea name="duns_tpl_message_body" class="form-control" rows="12"><?php echo html_escape(duns_template('message_body')); ?></textarea></div>
                </div>
            </div>
            <p class="duns-help">Clear a field and save to go back to the default wording.</p>
        </div>
    </div>
    <button class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
    <?php echo form_close(); ?>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
