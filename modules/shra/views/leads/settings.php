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
                    <div class="form-group"><label>Payment methods <span class="help" style="display:inline">(one per line — offered when an agent takes an advance on a call)</span></label><textarea name="shra_lead_payment_methods" class="form-control" rows="4"><?php echo html_escape(get_option('shra_lead_payment_methods')); ?></textarea></div>
                    <div class="form-group"><label>WhatsApp templates <span class="help" style="display:inline">Title|Message — {name} {agent} {academy} {visit} {location} {start} {batch} {batches}</span></label><textarea name="shra_lead_wa_templates" class="form-control" rows="5"><?php echo html_escape(get_option('shra_lead_wa_templates')); ?></textarea></div>
                    <div class="form-group"><label>Copy-button message <span class="help" style="display:inline">Behind the <i class="fa fa-copy"></i> next to WhatsApp — same placeholders</span></label><textarea name="shra_lead_wa_copy_msg" class="form-control" rows="6"><?php echo html_escape(shra_lead_wa_copy_msg()); ?></textarea></div>
                    <div class="form-group"><label>Master pitch <span class="help" style="display:inline">Top item under the <i class="fa fa-copy"></i> — everything in one short message</span></label><textarea name="shra_lead_wa_master_msg" class="form-control" rows="6"><?php echo html_escape(shra_lead_wa_master_msg()); ?></textarea></div>
                    <div class="form-group"><label>Offer message <span class="help" style="display:inline">Listed under the <i class="fa fa-copy"></i> only while an offer runs (SHRA settings) — {offer} becomes the live offer line in every message<?php $__o = shra_lead_wa_offer_line(); echo $__o !== '' ? '; now: ' . html_escape($__o) : '; no offer running now'; ?></span></label><textarea name="shra_lead_wa_offer_msg" class="form-control" rows="5"><?php echo html_escape(shra_lead_wa_offer_msg()); ?></textarea></div>
                    <div class="form-group"><label>Copy-button share messages <span class="help" style="display:inline">Listed under the <i class="fa fa-copy"></i> with the full pitch — Title|Message, \n for a new line, adds {maps} {self_booking} {join} {batches} to the placeholders</span></label><textarea name="shra_lead_wa_links" class="form-control" rows="5"><?php echo html_escape(get_option('shra_lead_wa_links') ?: implode("\n", array_map(function ($l) { return $l['title'] . '|' . str_replace("\n", '\n', $l['text']); }, shra_lead_wa_links()))); ?></textarea></div>
                </div>
            </div>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-rocket" style="color:var(--gold)"></i> Ad landing page <span class="help" style="display:inline;font-weight:400">&nbsp;<a href="<?php echo site_url('inquire'); ?>" target="_blank"><?php echo site_url('inquire'); ?> <i class="fa fa-external-link"></i></a></span></h4></div>
                <div class="shra-card-body">
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>Call / WhatsApp number</label><input type="text" name="shra_lead_landing_phone" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_phone')); ?>" placeholder="+91 77300 34313"></div></div>
                        <div class="col-sm-5"><div class="form-group"><label>Location line</label><input type="text" name="shra_lead_landing_location" class="form-control" value="<?php echo html_escape(get_option('shra_lead_landing_location')); ?>" placeholder="The Wilderness Retreat, Kokapet"></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Minimum rider age</label><input type="number" name="shra_lead_landing_min_age" class="form-control" value="<?php echo (int) get_option('shra_lead_landing_min_age'); ?>" min="1"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>Max inquiries per hour, per connection</label><input type="number" name="shra_lead_rate_limit" class="form-control" value="<?php echo html_escape(get_option('shra_lead_rate_limit')); ?>" min="0" placeholder="5"><span class="help">Anti-spam limit per IP address. Blank = 5 per hour. Set 0 to switch it off temporarily (e.g. while testing the form) — remember to turn it back on.</span></div></div>
                        <div class="col-sm-4"><div class="form-group"><label>Unpaid join registration becomes a lead after (minutes)</label><input type="number" name="shra_join_reclaim_minutes" class="form-control" value="<?php echo html_escape(get_option('shra_join_reclaim_minutes')); ?>" min="0" placeholder="2880"><span class="help">Everyone who registers on the join page is also captured as an assigned lead. If no payment arrives (online or at the desk) within this window, the rider entry is removed and only the lead remains. Blank = 2880 min (48 h). Set 0 to keep unpaid registrations as riders. The check runs with the cron, so timing is accurate to ~10 minutes.</span></div></div>
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
            <?php
            /* ── Targets ────────────────────────────────────────────────────────
             * Targets belong to the calling-agent role only. The calculator above
             * the table turns money into activity: cost × ROI (or a revenue goal)
             * ÷ average value per join, walked back up the funnel through the
             * rates the desk is actually converting at, then split across the
             * agents on the roster. Everything recalculates as the manager types;
             * "Fill" writes the result into the rows, which stay editable. */
            $pkg_prices = array_filter(array_map(function ($p) { return (float) $p->price; }, $packages));
            $avg_pkg    = count($pkg_prices) ? round(array_sum($pkg_prices) / count($pkg_prices)) : 0;
            $agent_cost = 0;
            foreach ($target_agents as $a) { $agent_cost += (float) ($targets[(int) $a->staffid]->cost ?? 0); }
            // Saved assumption wins; then what was measured; then a starter estimate, flagged as one.
            $seed = [
                'cost'     => $calc['cost'] ?: ($agent_cost + $ad_spend),
                'roi'      => $calc['roi'] ?: 3,
                'revenue'  => $calc['revenue'],
                'avg_deal' => $calc['avg_deal'] ?: ($baseline['avg_deal'] ?: $avg_pkg),
                'book'     => $calc['book'] ?: ($baseline['book'] ?: 25),
                'show'     => $calc['show'] ?: ($baseline['show'] ?: 65),
                'close'    => $calc['close'] ?: ($baseline['close'] ?: 35),
            ];
            // Sub-line under each rate input: the measured figure, or a warning that it is a guess.
            $hint = function ($key, $suffix) use ($baseline) {
                if (!empty($baseline['measured'][$key])) {
                    $v = $baseline[$key];
                    return '<span title="' . html_escape($baseline['window']) . '">Live: ' . ($suffix === '%' ? ($v + 0) . '%' : shra_money($v)) . '</span>';
                }
                return '<span style="color:var(--red)">No data yet — estimate</span>';
            };
            $days_in   = (int) date('t', strtotime($month . '-01'));
            $days_done = $month === date('Y-m') ? (int) date('j') : ($month < date('Y-m') ? $days_in : 0);
            ?>
            <div class="shra-card shra-mt" id="shra-targets" data-agents="<?php echo count($target_agents); ?>">
                <input type="hidden" name="targets_role" value="<?php echo (int) $agent_role_id; ?>">
                <div class="shra-card-head">
                    <h4><i class="fa fa-bullseye" style="color:var(--gold)"></i> Targets <span class="help" style="display:inline;font-weight:400">· calling agents only</span></h4>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <select name="shra_lead_agent_role_id" class="form-control" style="width:auto" title="Whose targets these are">
                            <?php foreach ($roles as $r) { ?><option value="<?php echo (int) $r->roleid; ?>" <?php echo (int) $r->roleid === (int) $agent_role_id ? 'selected' : ''; ?>><?php echo html_escape($r->name); ?></option><?php } ?>
                        </select>
                        <input type="month" name="targets_month" class="form-control" style="width:auto" value="<?php echo $month; ?>" onchange="location.href='<?php echo admin_url('shra/shra_leads/settings?month='); ?>'+this.value">
                    </div>
                </div>
                <?php if (!count($target_agents)) { ?>
                <div class="shra-card-body">
                    <div class="shra-empty" style="padding:34px 20px"><i class="fa fa-user-tie"></i>
                        Nobody is in the <b><?php echo html_escape(($rr = array_filter($roles, function ($r) use ($agent_role_id) { return (int) $r->roleid === (int) $agent_role_id; })) ? reset($rr)->name : 'SHRA Calling Agent'); ?></b> role yet.<br>
                        <span class="help">Put your callers in that role under <a href="<?php echo admin_url('staff'); ?>">Setup → Staff</a>, or pick the role you use above and save.</span>
                    </div>
                </div>
                <?php } else { ?>
                <div class="shra-card-body shra-card-cream" style="padding:16px 22px">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px">
                        <b style="font-size:11px;letter-spacing:1.3px;text-transform:uppercase;color:var(--gold)">Target calculator</b>
                        <div class="shra-seg">
                            <label><input type="radio" name="calc[mode]" value="revenue" class="tc-mode" <?php echo $calc['mode'] !== 'roi' ? 'checked' : ''; ?>><span>From revenue goal</span></label>
                            <label><input type="radio" name="calc[mode]" value="roi" class="tc-mode" <?php echo $calc['mode'] === 'roi' ? 'checked' : ''; ?>><span>From cost × ROI</span></label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4"><div class="form-group"><label>Monthly cost</label><input type="number" min="0" step="1" name="calc[cost]" id="tc-cost" class="form-control tc-in" value="<?php echo $seed['cost'] > 0 ? $seed['cost'] + 0 : ''; ?>" placeholder="0"><div class="help">Agents <?php echo shra_money($agent_cost); ?> + ad spend <?php echo shra_money($ad_spend); ?></div></div></div>
                        <div class="col-sm-4" id="tc-roi-wrap"><div class="form-group"><label>Target ROI (×)</label><input type="number" min="0" step="0.1" name="calc[roi]" id="tc-roi" class="form-control tc-in" value="<?php echo $seed['roi'] + 0; ?>"><div class="help">Revenue ÷ cost</div></div></div>
                        <div class="col-sm-4" id="tc-rev-wrap"><div class="form-group"><label>Revenue goal</label><input type="number" min="0" step="1" name="calc[revenue]" id="tc-revenue" class="form-control tc-in" value="<?php echo $seed['revenue'] > 0 ? $seed['revenue'] + 0 : ''; ?>" placeholder="0"><div class="help">For the whole month, all agents</div></div></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-3"><div class="form-group"><label>Value per join</label><input type="number" min="0" step="1" name="calc[avg_deal]" id="tc-deal" class="form-control tc-in" value="<?php echo $seed['avg_deal'] > 0 ? $seed['avg_deal'] + 0 : ''; ?>" placeholder="0"><div class="help"><?php echo $hint('avg_deal', ''); ?></div></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Call → visit booked</label><input type="number" min="0" max="100" step="0.1" name="calc[book]" id="tc-book" class="form-control tc-in" value="<?php echo $seed['book'] + 0; ?>"><div class="help"><?php echo $hint('book', '%'); ?></div></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Visit show-up</label><input type="number" min="0" max="100" step="0.1" name="calc[show]" id="tc-show" class="form-control tc-in" value="<?php echo $seed['show'] + 0; ?>"><div class="help"><?php echo $hint('show', '%'); ?></div></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Visit → join</label><input type="number" min="0" max="100" step="0.1" name="calc[close]" id="tc-close" class="form-control tc-in" value="<?php echo $seed['close'] + 0; ?>"><div class="help"><?php echo $hint('close', '%'); ?></div></div></div>
                    </div>
                    <div class="shra-tc-out" id="tc-out">
                        <div><span>Revenue goal</span><b id="tc-o-rev">—</b></div>
                        <div><span>Joins needed</span><b id="tc-o-joins">—</b></div>
                        <div><span>Visits attended</span><b id="tc-o-attend">—</b></div>
                        <div><span>Visits booked</span><b id="tc-o-book">—</b></div>
                        <div><span>Calls</span><b id="tc-o-calls">—</b></div>
                        <div><span>Cost per join</span><b id="tc-o-cpa">—</b></div>
                        <div><span>Margin</span><b id="tc-o-margin">—</b></div>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
                        <button type="button" class="shra-btn shra-btn-gold shra-btn-sm" id="tc-fill"><i class="fa fa-wand-magic-sparkles"></i> Fill all <?php echo count($target_agents); ?> agents</button>
                        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" id="tc-live"><i class="fa fa-rotate"></i> Reset rates to live</button>
                        <span class="help" id="tc-split" style="margin:0"></span>
                    </div>
                </div>
                <div class="shra-table-wrap"><table class="shra-table" id="tc-table">
                    <thead><tr><th>Agent</th><th class="num">Cost / mo</th><th class="num">Calls</th><th class="num">Visits</th><th class="num">Revenue</th><th></th></tr></thead>
                    <tbody><?php foreach ($target_agents as $a) { $sid = (int) $a->staffid; $t = $targets[$sid] ?? null; $l = $live[$sid] ?? null;
                        $pc = $t && $t->revenue_target > 0 && $l ? min(100, round($l->revenue / $t->revenue_target * 100)) : null; ?>
                        <tr data-staff="<?php echo $sid; ?>">
                            <td><div class="strong"><?php echo html_escape($a->full_name); ?></div>
                                <span class="sub"><?php if ($l) { echo (int) $l->won . ' joined · ' . shra_money($l->revenue) . ($l->roi !== null ? ' · ROI ' . $l->roi . '×' : '') . ($l->cpa !== null ? ' · ' . shra_money($l->cpa) . '/join' : ''); } else { echo 'No activity yet'; } ?></span></td>
                            <td class="num"><input type="number" min="0" step="1" name="t[<?php echo $sid; ?>][cost]" class="form-control tc-row tc-cost" style="width:110px" value="<?php echo $t ? $t->cost + 0 : ''; ?>" placeholder="0"></td>
                            <td class="num"><input type="number" min="0" step="1" name="t[<?php echo $sid; ?>][calls]" class="form-control tc-row tc-calls" style="width:90px" value="<?php echo $t ? (int) $t->calls_target : ''; ?>" placeholder="0">
                                <span class="sub"><?php echo $l ? (int) $l->calls . ' so far' : '—'; ?></span></td>
                            <td class="num"><input type="number" min="0" step="1" name="t[<?php echo $sid; ?>][visits]" class="form-control tc-row tc-visits" style="width:90px" value="<?php echo $t ? (int) $t->visits_target : ''; ?>" placeholder="0">
                                <span class="sub"><?php echo $l ? (int) $l->visits_booked . ' booked' : '—'; ?></span></td>
                            <td class="num"><input type="number" min="0" step="1" name="t[<?php echo $sid; ?>][revenue]" class="form-control tc-row tc-revenue" style="width:120px" value="<?php echo $t ? $t->revenue_target + 0 : ''; ?>" placeholder="0">
                                <span class="sub"><?php echo $l ? shra_money($l->revenue) . ($pc !== null ? ' · ' . $pc . '%' : '') : '—'; ?></span>
                                <?php if ($pc !== null) { ?><div class="shra-progress" style="margin-top:4px"><span style="width:<?php echo $pc; ?>%;background:<?php echo $pc >= 100 ? 'var(--green)' : 'var(--gold)'; ?>"></span></div><?php } ?></td>
                            <td class="num"><button type="button" class="shra-btn shra-btn-outline shra-btn-sm tc-fill-row" title="Fill this agent from the calculator"><i class="fa fa-bolt"></i></button></td>
                        </tr>
                    <?php } ?></tbody>
                    <tfoot><tr style="background:var(--cream)">
                        <td class="strong">Team total<span class="sub"><?php echo $days_done; ?> of <?php echo $days_in; ?> days elapsed</span></td>
                        <td class="num strong" id="tt-cost">—</td><td class="num strong" id="tt-calls">—</td><td class="num strong" id="tt-visits">—</td><td class="num strong" id="tt-revenue">—</td><td></td>
                    </tr></tfoot>
                </table></div>
                <?php } ?>
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
<script>
/* Target calculator — money in, activity out, recalculated on every keystroke.
   Cost × ROI (or a straight revenue goal) ÷ value per join gives the joins the
   month needs; the funnel rates walk that back up to visits and calls, and the
   result is split evenly across the calling agents on the roster. Filling a row
   only writes the inputs — nothing is saved until "Save settings". */
(function () {
    var root = document.getElementById('shra-targets');
    if (!root) { return; }
    var agents = parseInt(root.getAttribute('data-agents'), 10) || 0;
    if (!agents) { return; }
    var SYM  = <?php echo json_encode(get_base_currency()->symbol); ?>;
    // What the desk is measured at (starter estimates where nothing is measured yet).
    var LIVE = <?php echo json_encode([
        'avg_deal' => ($baseline['avg_deal'] ?: $avg_pkg) + 0,
        'book'     => ($baseline['book'] ?: 25) + 0,
        'show'     => ($baseline['show'] ?: 65) + 0,
        'close'    => ($baseline['close'] ?: 35) + 0,
    ]); ?>;
    var el   = function (id) { return document.getElementById(id); };
    var num  = function (id) { var v = parseFloat((el(id) || {}).value); return isFinite(v) && v > 0 ? v : 0; };
    var fmt  = function (n) { return Math.round(n).toLocaleString(); };
    var cash = function (n) { return (n < 0 ? '-' : '') + SYM + fmt(Math.abs(n)); };
    var mode = function () { var m = root.querySelector('.tc-mode:checked'); return m ? m.value : 'revenue'; };

    function plan() {
        var cost  = num('tc-cost'),
            goal  = mode() === 'roi' ? cost * num('tc-roi') : num('tc-revenue'),
            deal  = num('tc-deal'),
            book  = num('tc-book') / 100,
            show  = num('tc-show') / 100,
            close = num('tc-close') / 100,
            joins = deal > 0 ? Math.ceil(goal / deal) : 0,
            att   = close > 0 ? Math.ceil(joins / close) : 0,
            bkd   = show > 0 ? Math.ceil(att / show) : 0,
            calls = book > 0 ? Math.ceil(bkd / book) : 0;

        return {cost: cost, goal: goal, joins: joins, attend: att, booked: bkd, calls: calls,
                cpa: joins > 0 ? cost / joins : 0, margin: goal - cost};
    }

    function put(id, text, cls) {
        var n = el(id);
        if (!n) { return; }
        n.textContent = text;
        n.className   = cls || '';
    }

    function draw() {
        var p = plan(), ok = p.goal > 0 && p.calls > 0;
        el('tc-roi-wrap').classList.toggle('tc-dim', mode() !== 'roi');
        el('tc-rev-wrap').classList.toggle('tc-dim', mode() === 'roi');
        put('tc-o-rev', p.goal > 0 ? cash(p.goal) : '—');
        put('tc-o-joins', p.joins || '—');
        put('tc-o-attend', p.attend || '—');
        put('tc-o-book', p.booked || '—');
        put('tc-o-calls', p.calls || '—');
        put('tc-o-cpa', p.cpa > 0 ? cash(p.cpa) : '—');
        put('tc-o-margin', p.cost > 0 && p.goal > 0 ? cash(p.margin) : '—', p.cost > 0 && p.goal > 0 ? (p.margin >= 0 ? 'pos' : 'neg') : '');
        el('tc-split').textContent = ok
            ? 'Per agent (÷ ' + agents + '): ' + fmt(Math.ceil(p.calls / agents)) + ' calls · ' + fmt(Math.ceil(p.booked / agents)) + ' visits · ' + cash(Math.ceil(p.goal / agents)) + ' revenue · ' + cash(Math.ceil(p.cost / agents)) + ' cost'
            : 'Fill in the cost or revenue goal, the value per join and the three rates to see the plan.';
        el('tc-fill').disabled = !ok;
    }

    function fill(row) {
        var p = plan();
        if (!(p.goal > 0 && p.calls > 0)) { return; }
        var set = function (r, sel, v) { var i = r.querySelector(sel); if (i) { i.value = v; } };
        set(row, '.tc-cost', Math.ceil(p.cost / agents));
        set(row, '.tc-calls', Math.ceil(p.calls / agents));
        set(row, '.tc-visits', Math.ceil(p.booked / agents));
        set(row, '.tc-revenue', Math.ceil(p.goal / agents));
    }

    function totals() {
        [['tc-cost', 'tt-cost', 1], ['tc-calls', 'tt-calls', 0], ['tc-visits', 'tt-visits', 0], ['tc-revenue', 'tt-revenue', 1]].forEach(function (t) {
            var sum = 0;
            root.querySelectorAll('.' + t[0]).forEach(function (i) { var v = parseFloat(i.value); if (isFinite(v)) { sum += v; } });
            el(t[1]).textContent = sum > 0 ? (t[2] ? cash(sum) : fmt(sum)) : '—';
        });
    }

    root.querySelectorAll('.tc-in, .tc-mode').forEach(function (i) {
        i.addEventListener('input', draw);
        i.addEventListener('change', draw);
    });
    root.querySelectorAll('.tc-row').forEach(function (i) { i.addEventListener('input', totals); });
    el('tc-fill').addEventListener('click', function () {
        root.querySelectorAll('#tc-table tbody tr').forEach(fill);
        totals();
    });
    root.querySelectorAll('.tc-fill-row').forEach(function (b) {
        b.addEventListener('click', function () { fill(b.closest('tr')); totals(); });
    });
    el('tc-live').addEventListener('click', function () {
        el('tc-deal').value  = LIVE.avg_deal;
        el('tc-book').value  = LIVE.book;
        el('tc-show').value  = LIVE.show;
        el('tc-close').value = LIVE.close;
        draw();
    });
    draw();
    totals();
})();
</script>
<?php init_tail(); ?>
</body>
</html>
