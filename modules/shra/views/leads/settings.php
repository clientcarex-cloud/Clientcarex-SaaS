<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; ?>
    <div class="shra-toolbar" style="justify-content:space-between">
        <h4 class="shra-title" style="margin:0">Lead settings</h4>
        <div><a href="<?php echo admin_url('shra/shra_leads/import'); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa fa-file-arrow-up"></i> Import leads</a> <a href="<?php echo admin_url('shra/shra_leads/team'); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-ranking-star"></i> Team</a></div>
    </div>

    <?php echo form_open(admin_url('shra/shra_leads/settings')); ?>
    <div class="shra-profile" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)">
        <div>
            <div class="shra-card">
                <div class="shra-card-head"><h4><i class="fa fa-sliders" style="color:var(--gold)"></i> Rules</h4></div>
                <div class="shra-card-body">
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>First-call SLA (minutes)</label><input type="number" name="shra_lead_sla_minutes" class="form-control" value="<?php echo (int) get_option('shra_lead_sla_minutes'); ?>" min="5"></div></div>
                        <div class="col-sm-4"><div class="form-group"><label>Stale after (days)</label><input type="number" name="shra_lead_stale_days" class="form-control" value="<?php echo (int) get_option('shra_lead_stale_days'); ?>" min="1"></div></div>
                        <div class="col-sm-4"><div class="form-group"><label>Renewal credit (months)</label><input type="number" name="shra_lead_repeat_credit_months" class="form-control" value="<?php echo (int) get_option('shra_lead_repeat_credit_months'); ?>" min="0"><div class="help">0 = only the first bill is credited</div></div></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>Phone country code</label><input type="text" name="shra_lead_phone_country" class="form-control" value="<?php echo html_escape(get_option('shra_lead_phone_country')); ?>"></div></div>
                        <div class="col-sm-8" style="padding-top:26px">
                            <label style="font-weight:500;margin-right:14px"><input type="checkbox" name="shra_lead_auto_assign" value="1" <?php echo get_option('shra_lead_auto_assign') == '1' ? 'checked' : ''; ?>> Auto-assign (round robin)</label>
                            <label style="font-weight:500;margin-right:14px"><input type="checkbox" name="shra_lead_manager_digest" value="1" <?php echo get_option('shra_lead_manager_digest') == '1' ? 'checked' : ''; ?>> Daily manager digest</label>
                            <label style="font-weight:500"><input type="checkbox" name="shra_lead_public_enabled" value="1" <?php echo get_option('shra_lead_public_enabled') == '1' ? 'checked' : ''; ?>> Public inquiry form</label>
                        </div>
                    </div>
                    <div class="form-group"><label>Round-robin pool <span class="help" style="display:inline">(none ticked = all agents)</span></label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap"><?php foreach ($all_agents as $a) { ?><label class="shra-pill" style="cursor:pointer;<?php echo !$a->active ? 'opacity:.5' : ''; ?>"><input type="checkbox" name="pool[]" value="<?php echo $a->staffid; ?>" <?php echo in_array((int) $a->staffid, $pool) ? 'checked' : ''; ?>> <?php echo html_escape($a->full_name); ?></label><?php } ?></div>
                    </div>
                    <div class="form-group"><label>Visit slots (one per line)</label><textarea name="shra_lead_visit_slots" class="form-control" rows="5"><?php echo html_escape(get_option('shra_lead_visit_slots')); ?></textarea></div>
                    <div class="form-group"><label>Lost reasons (one per line)</label><textarea name="shra_lead_lost_reasons" class="form-control" rows="5"><?php echo html_escape(get_option('shra_lead_lost_reasons')); ?></textarea></div>
                    <div class="form-group"><label>WhatsApp templates <span class="help" style="display:inline">Title|Message — {name} {agent} {academy} {visit}</span></label><textarea name="shra_lead_wa_templates" class="form-control" rows="5"><?php echo html_escape(get_option('shra_lead_wa_templates')); ?></textarea></div>
                </div>
            </div>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-rocket" style="color:var(--gold)"></i> Ad landing page <span class="help" style="display:inline;font-weight:400">&nbsp;<a href="<?php echo site_url('inquire'); ?>" target="_blank"><?php echo site_url('inquire'); ?> <i class="fa fa-external-link"></i></a></span></h4></div>
                <div class="shra-card-body">
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>Call / WhatsApp number</label><input type="text" name="shra_lead_landing_phone" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_phone')); ?>" placeholder="9908480010"></div></div>
                        <div class="col-sm-5"><div class="form-group"><label>Location line</label><input type="text" name="shra_lead_landing_location" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_location')); ?>" placeholder="The Wilderness Retreat, Kokapet"></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Minimum rider age</label><input type="number" name="shra_lead_landing_min_age" class="form-control" value="<?php echo (int) get_option('shra_lead_landing_min_age'); ?>" min="1"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><div class="form-group"><label>Google Maps link</label><input type="url" name="shra_lead_landing_maps_url" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_maps_url')); ?>" placeholder="https://maps.app.goo.gl/…"></div></div>
                        <div class="col-sm-6"><div class="form-group"><label>Instagram profile URL</label><input type="url" name="shra_lead_landing_instagram" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_instagram')); ?>"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-5"><div class="form-group"><label>Map search text</label><input type="text" name="shra_lead_landing_map_query" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_map_query')); ?>" placeholder="The Wilderness Retreat, Kokapet"><div class="help">Used to pin the map when no embed URL is pasted</div></div></div>
                        <div class="col-sm-7"><div class="form-group"><label>Google Maps embed URL <span class="help" style="display:inline">(optional — Google Maps → Share → Embed a map → copy)</span></label><input type="text" name="shra_lead_landing_map_embed" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_map_embed')); ?>" placeholder='https://www.google.com/maps/embed?pb=… (or paste the whole <iframe>)'></div></div>
                    </div>
                    <div class="form-group"><label>Instagram reels to show <span class="help" style="display:inline">(one per line — reel URL or ID; first one is the hero video)</span></label><textarea name="shra_lead_landing_reels" class="form-control" rows="4"><?php echo html_escape(get_option('shra_lead_landing_reels')); ?></textarea></div>
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>Meta Pixel ID</label><input type="text" name="shra_lead_meta_pixel_id" class="form-control" value="<?php echo html_escape(get_option('shra_lead_meta_pixel_id')); ?>" placeholder="1234567890"><div class="help">Fires PageView, Contact (call / WhatsApp taps) and Lead (form sent)</div></div></div>
                        <div class="col-sm-4"><div class="form-group"><label>Google Ads tag ID</label><input type="text" name="shra_lead_gads_id" class="form-control" value="<?php echo html_escape(get_option('shra_lead_gads_id')); ?>" placeholder="AW-123456789"></div></div>
                        <div class="col-sm-4"><div class="form-group"><label>Google Ads conversion label</label><input type="text" name="shra_lead_gads_label" class="form-control" value="<?php echo html_escape(get_option('shra_lead_gads_label')); ?>" placeholder="AbC-D_efG-h12_34-567"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>GA4 measurement ID <span class="help" style="display:inline">(optional)</span></label><input type="text" name="shra_lead_ga4_id" class="form-control" value="<?php echo html_escape(get_option('shra_lead_ga4_id')); ?>" placeholder="G-XXXXXXX"></div></div>
                        <div class="col-sm-8"><div class="form-group"><label>Ad URL format</label><div class="help" style="margin-top:8px;line-height:1.7">Meta: <code><?php echo site_url('inquire'); ?>?utm_source=facebook&amp;utm_medium=cpc&amp;utm_campaign=aug-kids</code><br>Google: <code><?php echo site_url('inquire'); ?>?utm_source=google&amp;utm_medium=cpc&amp;utm_campaign=search-brand</code><br>Lead source is set automatically (Google / Facebook / Instagram); add <code>&amp;pkg=ID</code> to pre-select a package.</div></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <div class="shra-card">
                <div class="shra-card-head"><h4><i class="fa fa-bullhorn" style="color:var(--gold)"></i> Sources &amp; monthly spend</h4></div>
                <div class="shra-card-body">
                    <?php foreach ($sources as $s) { ?>
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px"><span style="flex:1"><?php echo html_escape($s->name); ?></span><input type="number" name="source_cost[<?php echo $s->id; ?>]" class="form-control" style="width:130px" value="<?php echo $s->monthly_cost + 0; ?>" placeholder="₹ / month"></div>
                    <?php } ?>
                    <div style="display:flex;gap:10px;align-items:center;margin-top:10px"><input type="text" name="new_source" class="form-control" placeholder="Add a source…"></div>
                </div>
            </div>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-bullseye" style="color:var(--gold)"></i> Targets</h4><input type="month" name="targets_month" class="form-control" style="width:auto" value="<?php echo $month; ?>" onchange="location.href='<?php echo admin_url('shra/shra_leads/settings?month='); ?>'+this.value"></div>
                <div class="shra-table-wrap"><table class="shra-table">
                    <thead><tr><th>Agent</th><th>Calls</th><th>Visits</th><th>Revenue</th></tr></thead>
                    <tbody><?php foreach ($agents as $a) { $t = $targets[(int) $a->staffid] ?? null; ?>
                        <tr><td><?php echo html_escape($a->full_name); ?></td>
                            <td><input type="number" name="t[<?php echo $a->staffid; ?>][calls]" class="form-control" style="width:90px" value="<?php echo $t ? (int) $t->calls_target : ''; ?>"></td>
                            <td><input type="number" name="t[<?php echo $a->staffid; ?>][visits]" class="form-control" style="width:90px" value="<?php echo $t ? (int) $t->visits_target : ''; ?>"></td>
                            <td><input type="number" name="t[<?php echo $a->staffid; ?>][revenue]" class="form-control" style="width:120px" value="<?php echo $t ? $t->revenue_target + 0 : ''; ?>"></td></tr>
                    <?php } ?></tbody>
                </table></div>
            </div>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-file-arrow-up" style="color:var(--gold)"></i> Import leads</h4></div>
                <div class="shra-card-body shra-qr">
                    <div style="flex:1;min-width:200px"><div class="help" style="margin-top:0">Upload a Facebook / Instagram lead export, a Google Form sheet or any CSV. The importer works out what each column is, shows you a preview, skips numbers you already have, and remembers the layout for next time.</div></div>
                    <a href="<?php echo admin_url('shra/shra_leads/import'); ?>" class="shra-btn shra-btn-gold"><i class="fa fa-file-arrow-up"></i> Import leads</a>
                </div>
            </div>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-qrcode" style="color:var(--gold)"></i> Public inquiry QR</h4></div>
                <div class="shra-card-body shra-qr">
                    <div style="width:140px;height:140px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:8px"><?php echo $inquire_qr; ?></div>
                    <div style="flex:1;min-width:200px"><div class="shra-qr-url"><?php echo html_escape($inquire_url); ?></div><div class="help">Print on flyers, Instagram bio, Google listing. Every scan becomes a lead with source <b>Website QR</b>, auto-assigned by round robin.</div></div>
                </div>
            </div>
        </div>
    </div>
    <div style="margin-top:16px"><button class="shra-btn shra-btn-primary"><i class="fa fa-save"></i> Save settings</button></div>
    <?php echo form_close(); ?>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
