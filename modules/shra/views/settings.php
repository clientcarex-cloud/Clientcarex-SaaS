<?php defined('BASEPATH') or exit('No direct script access allowed'); $o = function ($k) { return html_escape(get_option($k)); }; ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'settings'; include __DIR__ . '/_nav.php'; ?>

    <?php echo form_open_multipart(admin_url('shra/settings')); ?>
    <div class="row">
        <div class="col-md-7">
            <div class="shra-card"><div class="shra-card-head"><h4>Branding</h4></div><div class="shra-card-body">
                <div class="row">
                    <div class="col-md-7"><div class="form-group"><label>Academy name</label><input type="text" name="shra_academy_name" class="form-control" value="<?php echo $o('shra_academy_name'); ?>"></div></div>
                    <div class="col-md-5"><div class="form-group"><label>Tagline</label><input type="text" name="shra_tagline" class="form-control" value="<?php echo $o('shra_tagline'); ?>"></div></div>
                </div>
                <div class="form-group"><label>Contact line (PDF footer)</label><input type="text" name="shra_contact_line" class="form-control" value="<?php echo $o('shra_contact_line'); ?>" placeholder="Phone · email · address"></div>
                <div class="row">
                    <div class="col-md-6"><div class="form-group"><label>Chief instructor (certificate signature)</label><input type="text" name="shra_chief_instructor" class="form-control" value="<?php echo $o('shra_chief_instructor'); ?>"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>Director (certificate signature)</label><input type="text" name="shra_director" class="form-control" value="<?php echo $o('shra_director'); ?>"></div></div>
                </div>
                <div class="form-group"><label>Logo (PNG / JPG, square, for PDFs &amp; screens)</label>
                    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
                        <img src="<?php echo shra_logo_url(); ?>" style="width:72px;height:72px;border-radius:50%;border:1px solid var(--line)" alt="">
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg">
                        <?php if (get_option('shra_logo')) { ?><label style="font-weight:500"><input type="checkbox" name="remove_logo" value="1"> Remove uploaded logo</label><?php } ?>
                    </div>
                    <div class="help">Without an upload the built-in horseshoe mark is used. Upload the real academy logo as a PNG for the premium PDFs.</div>
                </div>
            </div></div>

            <div class="shra-card shra-mt"><div class="shra-card-head"><h4>Terms &amp; conditions</h4></div><div class="shra-card-body">
                <textarea name="shra_terms" class="form-control" rows="12"><?php echo $o('shra_terms'); ?></textarea>
                <div class="help">Shown on the self-registration form. A parent / guardian accepts on behalf of riders under the minor age.</div>
            </div></div>
        </div>

        <div class="col-md-5">
            <div class="shra-card"><div class="shra-card-head"><h4>Offer</h4></div><div class="shra-card-body">
                <label style="font-weight:600;display:flex;gap:8px;align-items:center"><input type="checkbox" name="shra_offer_active" value="1" <?php echo get_option('shra_offer_active') == '1' ? 'checked' : ''; ?>> Offer is active (applied automatically at billing)</label>
                <div class="row" style="margin-top:10px">
                    <div class="col-xs-4"><div class="form-group"><label>Discount %</label><input type="number" step="0.01" min="0" max="100" name="shra_offer_percent" class="form-control" value="<?php echo $o('shra_offer_percent'); ?>"></div></div>
                    <div class="col-xs-8"><div class="form-group"><label>Label</label><input type="text" name="shra_offer_label" class="form-control" value="<?php echo $o('shra_offer_label'); ?>"></div></div>
                </div>
                <div class="form-group"><label>Ends on (optional)</label><input type="date" name="shra_offer_ends" class="form-control" value="<?php echo $o('shra_offer_ends'); ?>"></div>
            </div></div>

            <div class="shra-card shra-mt"><div class="shra-card-head"><h4>Riders &amp; courses</h4></div><div class="shra-card-body">
                <div class="form-group"><label>Minor age (children pricing &amp; guardian consent below this age)</label><input type="number" min="1" max="30" name="shra_minor_age" class="form-control" value="<?php echo $o('shra_minor_age'); ?>"></div>
                <div class="form-group"><label>Riding levels (one per line)</label><textarea name="shra_riding_levels" class="form-control" rows="5"><?php echo $o('shra_riding_levels'); ?></textarea></div>
                <label style="font-weight:600;display:flex;gap:8px;align-items:center"><input type="checkbox" name="shra_auto_certificate" value="1" <?php echo get_option('shra_auto_certificate') == '1' ? 'checked' : ''; ?>> Issue the certificate automatically when the last session is marked</label>
            </div></div>

            <div class="shra-card shra-mt"><div class="shra-card-head"><h4>Self-registration link</h4><a href="<?php echo admin_url('shra/qr'); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa-solid fa-qrcode"></i> Poster</a></div><div class="shra-card-body">
                <div class="shra-qr-url"><?php echo html_escape(shra_join_url()); ?></div>
                <div class="help" style="margin-top:8px">Static link — print it once, it never changes.</div>
            </div></div>

            <?php if (shra_leads_can('manage')) { ?>
            <div class="shra-card shra-mt"><div class="shra-card-head"><h4>Import leads</h4></div><div class="shra-card-body">
                <div class="help" style="margin-top:0">Facebook / Instagram lead exports, Google Form sheets or any CSV. The importer reads the columns itself, shows a preview, and skips numbers that are already leads.</div>
                <a href="<?php echo admin_url('shra/shra_leads/import'); ?>" class="shra-btn shra-btn-gold shra-btn-block shra-mt"><i class="fa fa-file-arrow-up"></i> Import leads</a>
                <a href="<?php echo admin_url('shra/shra_leads/settings'); ?>" class="shra-btn shra-btn-outline shra-btn-block shra-mt"><i class="fa fa-sliders"></i> Lead settings &amp; targets</a>
            </div></div>
            <?php } ?>

            <button type="submit" class="shra-btn shra-btn-primary shra-btn-block shra-mt"><i class="fa fa-check"></i> Save settings</button>
        </div>
    </div>
    <?php echo form_close(); ?>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
