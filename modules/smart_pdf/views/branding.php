<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$can_edit    = has_permission('smart_pdf', '', 'edit') || is_admin();
$is_template = !empty($template);
$form_action = $is_template ? admin_url('smart_pdf/branding/' . $template->id) : admin_url('smart_pdf/branding');
$override_on = $is_template ? !empty($branding['enabled']) : true;
?>
<style>
    .spdf-brand-wrap { margin-top: 10px; }
    .spdf-brand-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
    .spdf-brand-head h4 { margin: 0; font-weight: 700; color: #0e4d54; }
    .spdf-brand-head .text-muted { font-size: 12.5px; }
    .spdf-brand-head .tpl-chip { display: inline-block; background: #e8f0ef; color: #0e4d54; border-radius: 14px; padding: 2px 12px; font-size: 12px; font-weight: 600; margin-left: 6px; }

    .spdf-brand-panel { background: #fff; border: 1px solid #e4e7ea; border-radius: 8px; padding: 20px; }
    .spdf-brand-section { border-bottom: 1px solid #eef1f0; padding: 4px 0 18px; margin-bottom: 18px; }
    .spdf-brand-section:last-of-type { border-bottom: 0; margin-bottom: 0; }
    .spdf-brand-section > .legend { font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #0e4d54; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    .spdf-brand-section > .legend .fa { color: #b6923d; }

    .spdf-override-bar { background: #f7f9fb; border: 1px solid #dbe4e3; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
    .spdf-override-bar .spdf-switch { font-size: 14px; font-weight: 700; color: #0e4d54; }
    .spdf-override-bar p { margin: 8px 0 0 28px; font-size: 12px; color: #7a8089; }
    .spdf-brand-body.spdf-disabled { opacity: .45; pointer-events: none; filter: grayscale(.3); }

    .spdf-color-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .spdf-color-row label { width: 130px; margin: 0; font-size: 13px; color: #4b5158; font-weight: 600; }
    .spdf-color-pick { width: 40px; height: 34px; padding: 2px; border: 1px solid #dde3e8; border-radius: 6px; background: #fff; cursor: pointer; flex-shrink: 0; }
    .spdf-color-hex { width: 108px !important; text-transform: uppercase; font-family: monospace; }

    .spdf-field { margin-bottom: 15px; }
    .spdf-field > label { font-size: 13px; color: #4b5158; font-weight: 600; display: block; margin-bottom: 6px; }
    .spdf-range-row { display: flex; align-items: center; gap: 12px; }
    .spdf-range-row input[type=range] { flex: 1; }
    .spdf-range-val { width: 54px; text-align: right; font-family: monospace; font-size: 12.5px; color: #0e4d54; }

    .spdf-switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; margin: 0; }
    .spdf-switch input { margin: 0; }
    .spdf-radio-inline label { margin-right: 16px; font-weight: 600; font-size: 13px; color: #4b5158; }

    /* Logo Studio */
    .spdf-studio { background: #f8fafb; border: 1px dashed #cdd8dd; border-radius: 8px; padding: 14px; margin-top: 6px; }
    .spdf-studio .studio-grid { display: flex; gap: 16px; flex-wrap: wrap; }
    .spdf-studio .studio-controls { flex: 1 1 210px; }
    .spdf-checker { --c: #dfe6e9; background-color: #fff; background-image: linear-gradient(45deg, var(--c) 25%, transparent 25%), linear-gradient(-45deg, var(--c) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, var(--c) 75%), linear-gradient(-45deg, transparent 75%, var(--c) 75%); background-size: 14px 14px; background-position: 0 0, 0 7px, 7px -7px, -7px 0; }
    .spdf-studio .studio-out { flex: 0 0 150px; border: 1px solid #dde3e8; border-radius: 8px; height: 96px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .spdf-studio .studio-out img { max-height: 82px; max-width: 138px; object-fit: contain; }
    .spdf-studio .studio-out.on-dark { background: #0e4d54 !important; }
    .spdf-studio .dark-toggle { font-size: 11px; color: #7a8089; margin-top: 6px; text-align: center; cursor: pointer; }
    .spdf-swatch-btns .btn { margin-right: 6px; }

    .spdf-logo-preview { display: inline-flex; align-items: center; justify-content: center; height: 46px; min-width: 90px; padding: 6px 12px; background: #0e4d54; border-radius: 6px; margin-top: 8px; }
    .spdf-logo-preview img { max-height: 34px; max-width: 150px; object-fit: contain; }

    .spdf-brand-preview-col { position: sticky; top: 70px; }
    .spdf-brand-preview { background: #eef1f0; border: 1px solid #dfe4e3; border-radius: 8px; overflow: hidden; }
    .spdf-brand-preview .frame-hold { position: relative; width: 100%; overflow: hidden; }
    .spdf-brand-preview iframe { border: 0; transform-origin: top left; background: #eef1f0; }
    .spdf-brand-preview-bar { display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; background: #fff; border-bottom: 1px solid #eef1f0; font-size: 12px; color: #7a8089; }
    .spdf-brand-preview-bar .live { color: #16a34a; font-weight: 700; }
    .spdf-brand-actions { margin-top: 18px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="spdf-brand-head">
                    <div>
                        <h4>
                            <i class="fa fa-paint-brush"></i> <?php echo _l('smart_pdf_branding'); ?>
                            <?php if ($is_template) { ?><span class="tpl-chip"><i class="fa fa-file-text-o"></i> <?php echo html_escape($template->name); ?></span><?php } ?>
                        </h4>
                        <span class="text-muted"><?php echo $is_template ? _l('smart_pdf_branding_tpl_intro') : _l('smart_pdf_branding_intro'); ?></span>
                    </div>
                    <a href="<?php echo admin_url('smart_pdf'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> <?php echo _l('smart_pdf'); ?>
                    </a>
                </div>
            </div>
        </div>

        <?php echo form_open($form_action, ['id' => 'spdf-brand-form']); ?>
        <div class="row spdf-brand-wrap">
            <!-- ---------------- Controls ---------------- -->
            <div class="col-md-5">
                <div class="spdf-brand-panel">

                    <?php if ($is_template) { ?>
                        <div class="spdf-override-bar">
                            <label class="spdf-switch">
                                <input type="checkbox" name="branding_enabled" value="1" id="spdf-override-toggle"
                                    <?php echo $override_on ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <?php echo _l('smart_pdf_branding_tpl_enable'); ?>
                            </label>
                            <p><?php echo _l('smart_pdf_branding_tpl_enable_help'); ?></p>
                        </div>
                    <?php } ?>

                    <div class="spdf-brand-body<?php echo ($is_template && !$override_on) ? ' spdf-disabled' : ''; ?>" id="spdf-brand-body">

                    <!-- Colours -->
                    <div class="spdf-brand-section">
                        <div class="legend"><i class="fa fa-tint"></i> <?php echo _l('smart_pdf_brand_colours'); ?></div>
                        <?php
                        $colors = [
                            'primary' => _l('smart_pdf_brand_primary'),
                            'accent'  => _l('smart_pdf_brand_accent'),
                            'heading' => _l('smart_pdf_brand_heading'),
                            'text'    => _l('smart_pdf_brand_text'),
                        ];
                        foreach ($colors as $key => $label) { ?>
                            <div class="spdf-color-row">
                                <label><?php echo $label; ?></label>
                                <input type="color" class="spdf-color-pick" data-target="<?php echo $key; ?>"
                                    value="<?php echo html_escape($branding[$key]); ?>" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <input type="text" class="form-control spdf-color-hex spdf-brand-input" name="<?php echo $key; ?>"
                                    id="spdf-hex-<?php echo $key; ?>" value="<?php echo html_escape($branding[$key]); ?>"
                                    maxlength="7" <?php echo $can_edit ? '' : 'readonly'; ?>>
                            </div>
                        <?php } ?>
                        <div class="spdf-field" style="margin-top:14px;">
                            <label><?php echo _l('smart_pdf_brand_font'); ?></label>
                            <select class="form-control spdf-brand-input" name="font" id="spdf-font" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <?php foreach ($fonts as $val => $label) { ?>
                                    <option value="<?php echo html_escape($val); ?>" <?php echo $branding['font'] === $val ? 'selected' : ''; ?>>
                                        <?php echo html_escape($label); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <!-- Logo -->
                    <div class="spdf-brand-section">
                        <div class="legend"><i class="fa fa-image"></i> <?php echo _l('smart_pdf_brand_logo'); ?></div>

                        <div class="spdf-field">
                            <label class="spdf-switch">
                                <input type="checkbox" name="show_logo" value="1" id="spdf-show-logo"
                                    class="spdf-brand-input" <?php echo !empty($branding['show_logo']) ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <?php echo _l('smart_pdf_brand_show_logo'); ?>
                            </label>
                        </div>

                        <div class="spdf-field spdf-radio-inline">
                            <label style="display:block;margin-bottom:6px;"><?php echo _l('smart_pdf_brand_logo_source'); ?></label>
                            <label><input type="radio" name="logo_source" value="crm" class="spdf-brand-input"
                                <?php echo $branding['logo_source'] !== 'custom' ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>> <?php echo _l('smart_pdf_brand_logo_crm'); ?></label>
                            <label><input type="radio" name="logo_source" value="custom" class="spdf-brand-input"
                                <?php echo $branding['logo_source'] === 'custom' ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>> <?php echo _l('smart_pdf_brand_logo_custom'); ?></label>
                        </div>

                        <input type="hidden" name="logo_file" id="spdf-logo-file" value="<?php echo html_escape($branding['logo_file']); ?>">
                        <input type="file" id="spdf-logo-upload" accept="image/*" class="hide">

                        <!-- Logo Studio: upload / background removal / recolour -->
                        <div class="spdf-studio" id="spdf-studio">
                            <div class="studio-grid">
                                <div class="studio-controls">
                                    <button type="button" class="btn btn-default btn-sm" id="spdf-logo-upload-btn" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                        <i class="fa fa-upload"></i> <?php echo _l('smart_pdf_brand_logo_upload'); ?>
                                    </button>
                                    <span id="spdf-logo-upload-status" class="text-muted" style="margin-left:6px;font-size:11.5px;"></span>

                                    <div class="spdf-field" style="margin:12px 0 8px;">
                                        <label><?php echo _l('smart_pdf_studio_recolour'); ?></label>
                                        <div class="spdf-radio-inline spdf-swatch-btns">
                                            <label><input type="radio" name="_recolor" value="original" checked> <?php echo _l('smart_pdf_studio_original'); ?></label>
                                            <label><input type="radio" name="_recolor" value="white"> <?php echo _l('smart_pdf_studio_white'); ?></label>
                                            <label><input type="radio" name="_recolor" value="black"> <?php echo _l('smart_pdf_studio_black'); ?></label>
                                        </div>
                                    </div>

                                    <div class="spdf-field" style="margin-bottom:8px;">
                                        <label class="spdf-switch">
                                            <input type="checkbox" id="spdf-remove-bg"> <?php echo _l('smart_pdf_studio_remove_bg'); ?>
                                        </label>
                                    </div>
                                    <div class="spdf-field" id="spdf-tol-row" style="display:none;">
                                        <label><?php echo _l('smart_pdf_studio_tolerance'); ?></label>
                                        <div class="spdf-range-row">
                                            <input type="range" min="10" max="200" step="2" id="spdf-bg-tol" value="70">
                                            <span class="spdf-range-val" id="spdf-bg-tol-val">70</span>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-info btn-sm" id="spdf-studio-apply" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                        <i class="fa fa-check"></i> <?php echo _l('smart_pdf_studio_apply'); ?>
                                    </button>
                                </div>
                                <div>
                                    <div class="studio-out spdf-checker" id="spdf-studio-out">
                                        <img id="spdf-studio-img" src="" alt="" style="display:none;">
                                        <span class="text-muted" id="spdf-studio-empty" style="font-size:11px;"><?php echo _l('smart_pdf_studio_none'); ?></span>
                                    </div>
                                    <div class="dark-toggle" id="spdf-studio-darktoggle"><i class="fa fa-adjust"></i> <?php echo _l('smart_pdf_studio_on_dark'); ?></div>
                                </div>
                            </div>
                            <canvas id="spdf-studio-canvas" class="hide"></canvas>
                        </div>

                        <div class="spdf-field" style="margin-top:14px;">
                            <label><?php echo _l('smart_pdf_brand_logo_size'); ?></label>
                            <div class="spdf-range-row">
                                <input type="range" min="12" max="120" step="1" name="logo_size" id="spdf-logo-size"
                                    class="spdf-brand-input" value="<?php echo (int) $branding['logo_size']; ?>" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <span class="spdf-range-val"><span id="spdf-logo-size-val"><?php echo (int) $branding['logo_size']; ?></span>px</span>
                            </div>
                        </div>
                    </div>

                    <!-- Company name -->
                    <div class="spdf-brand-section">
                        <div class="legend"><i class="fa fa-font"></i> <?php echo _l('smart_pdf_brand_company_name'); ?></div>
                        <div class="spdf-field">
                            <label class="spdf-switch">
                                <input type="checkbox" name="show_name" value="1" id="spdf-show-name"
                                    class="spdf-brand-input" <?php echo !empty($branding['show_name']) ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <?php echo _l('smart_pdf_brand_show_name'); ?>
                            </label>
                            <p class="text-muted" style="margin:6px 0 0;font-size:11.5px;"><?php echo _l('smart_pdf_brand_name_help'); ?></p>
                        </div>
                        <div class="spdf-field">
                            <label><?php echo _l('smart_pdf_brand_name_size'); ?></label>
                            <div class="spdf-range-row">
                                <input type="range" min="10" max="48" step="1" name="name_size" id="spdf-name-size"
                                    class="spdf-brand-input" value="<?php echo (int) $branding['name_size']; ?>" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <span class="spdf-range-val"><span id="spdf-name-size-val"><?php echo (int) $branding['name_size']; ?></span>px</span>
                            </div>
                        </div>
                        <div class="spdf-field">
                            <label><?php echo _l('smart_pdf_brand_tagline'); ?></label>
                            <input type="text" class="form-control spdf-brand-input" name="tagline" id="spdf-tagline"
                                value="<?php echo html_escape($branding['tagline']); ?>" maxlength="120"
                                placeholder="<?php echo _l('smart_pdf_brand_tagline_ph'); ?>" <?php echo $can_edit ? '' : 'readonly'; ?>>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="spdf-brand-section">
                        <div class="legend"><i class="fa fa-minus"></i> <?php echo _l('smart_pdf_brand_footer'); ?></div>
                        <div class="spdf-field">
                            <textarea class="form-control spdf-brand-input" name="footer_text" id="spdf-footer" rows="2"
                                maxlength="400" placeholder="<?php echo _l('smart_pdf_brand_footer_ph'); ?>" <?php echo $can_edit ? '' : 'readonly'; ?>><?php echo html_escape($branding['footer_text']); ?></textarea>
                        </div>
                    </div>

                    </div><!-- /.spdf-brand-body -->

                    <?php if ($can_edit) { ?>
                        <div class="spdf-brand-actions">
                            <button type="submit" class="btn btn-info"><i class="fa fa-save"></i> <?php echo _l('smart_pdf_brand_save'); ?></button>
                            <?php if (!$is_template) { ?>
                                <a href="<?php echo admin_url('smart_pdf/reset_branding'); ?>" class="btn btn-default"
                                    onclick="return confirm('<?php echo _l('smart_pdf_brand_reset_confirm'); ?>');">
                                    <i class="fa fa-undo"></i> <?php echo _l('smart_pdf_brand_reset'); ?>
                                </a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- ---------------- Live preview ---------------- -->
            <div class="col-md-7">
                <div class="spdf-brand-preview-col">
                    <div class="spdf-brand-preview">
                        <div class="spdf-brand-preview-bar">
                            <span><i class="fa fa-eye"></i> <?php echo $is_template ? _l('smart_pdf_branding_tpl_preview') : _l('smart_pdf_brand_live_preview'); ?></span>
                            <span class="live"><i class="fa fa-circle" style="font-size:8px;"></i> <?php echo _l('smart_pdf_brand_live'); ?></span>
                        </div>
                        <div class="frame-hold" id="spdf-frame-hold">
                            <iframe id="spdf-brand-frame" src="<?php echo $preview_url; ?>"
                                title="<?php echo _l('smart_pdf_brand_live_preview'); ?>"></iframe>
                        </div>
                    </div>
                    <p class="text-muted" style="margin-top:10px;font-size:12px;">
                        <i class="fa fa-info-circle"></i> <?php echo $is_template ? _l('smart_pdf_branding_tpl_note') : _l('smart_pdf_brand_preview_note'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function () {
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        var crmLogoUrl = <?php echo json_encode($crm_logo_url); ?>;
        var customLogoUrl = <?php echo json_encode($custom_logo_url); ?>;
        var canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
        var isTemplateMode = <?php echo $is_template ? 'true' : 'false'; ?>;

        var frame = document.getElementById('spdf-brand-frame');
        var frameReady = false;

        function layoutFrame() {
            var hold = document.getElementById('spdf-frame-hold');
            if (!hold) { return; }
            var baseW = 794, baseH = 1123;
            var scale = hold.offsetWidth / baseW;
            frame.style.width = baseW + 'px';
            frame.style.height = baseH + 'px';
            frame.style.transform = 'scale(' + scale + ')';
            hold.style.height = (baseH * scale) + 'px';
        }
        layoutFrame();
        var rt = null;
        $(window).on('resize', function () { clearTimeout(rt); rt = setTimeout(layoutFrame, 120); });

        // ---- colour helpers (mirror the PHP tint) ----
        function h2r(h) { h = h.replace('#', ''); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; }
        function mix(hex, target, w) {
            var a = h2r(hex), b = h2r(target);
            var c = a.map(function (v, i) { return Math.round(v + (b[i] - v) * w); });
            return '#' + c.map(function (v) { return ('0' + Math.max(0, Math.min(255, v)).toString(16)).slice(-2); }).join('');
        }
        function tint(hex, w) { return mix(hex, '#ffffff', w); }
        function validHex(v) { return /^#[0-9a-fA-F]{6}$/.test(v); }

        function vals() {
            function hex(id, fb) { var v = $('#spdf-hex-' + id).val().trim(); return validHex(v) ? v : fb; }
            return {
                primary:   hex('primary', '#0e4d54'),
                accent:    hex('accent', '#b6923d'),
                heading:   hex('heading', '#0b2e33'),
                text:      hex('text', '#5f7275'),
                font:      $('#spdf-font').val(),
                show_logo: $('#spdf-show-logo').is(':checked'),
                logo_size: parseInt($('#spdf-logo-size').val(), 10) || 27,
                show_name: $('#spdf-show-name').is(':checked'),
                name_size: parseInt($('#spdf-name-size').val(), 10) || 25,
                tagline:   $('#spdf-tagline').val(),
                footer:    $('#spdf-footer').val()
            };
        }

        function buildCss(v) {
            var css = ':root{';
            css += '--teal:' + v.primary + ';--ink:' + v.heading + ';--gold:' + v.accent + ';--muted:' + v.text + ';';
            css += '--teal-soft:' + tint(v.primary, 0.90) + ';--gold-soft:' + tint(v.accent, 0.86) + ';--line:' + tint(v.primary, 0.82) + ';';
            css += '--brand-primary:' + v.primary + ';--brand-accent:' + v.accent + ';--brand-heading:' + v.heading + ';--brand-text:' + v.text + ';--brand-muted:' + v.text + ';';
            if (v.font) { css += '--brand-font:' + v.font + ';'; }
            css += '}';
            if (!v.show_logo) { css += '.brand-logo{display:none!important;}'; }
            else { css += '.brand-logo{height:' + v.logo_size + 'px!important;width:auto!important;max-width:none!important;}'; }
            if (!v.show_name) { css += '.brand-name,.brand-sub{display:none!important;}'; }
            else { css += '.brand-name{font-size:' + v.name_size + 'px!important;}'; }
            if (v.font) { css += 'body{font-family:' + v.font + ';}'; }
            return css;
        }

        function currentLogoUrl() {
            var useCustom = $('input[name=logo_source]:checked').val() === 'custom';
            var custom = $('#spdf-logo-file').val();
            if (useCustom && custom) {
                return $('#spdf-studio-img').attr('src') || customLogoUrl || crmLogoUrl;
            }
            return crmLogoUrl;
        }

        function apply() {
            if (!frameReady) { return; }
            var doc = frame.contentDocument;
            if (!doc || !doc.head) { return; }
            var v = vals();

            var st = doc.getElementById('spdf-brand-live');
            if (!st) { st = doc.createElement('style'); st.id = 'spdf-brand-live'; doc.head.appendChild(st); }
            st.textContent = buildCss(v);

            var img = doc.querySelector('.brand-logo');
            if (img) { var url = currentLogoUrl(); if (url) { img.setAttribute('src', url); } }

            // Tagline / footer text are only meaningful on the generic sample
            // (bundled templates hardcode their own sub-heading).
            if (!isTemplateMode) {
                var sub = doc.querySelector('.brand-sub');
                if (sub) { sub.textContent = v.tagline || 'Human Resources'; }
                var sheet = doc.querySelector('.sheet');
                if (sheet) {
                    var foot = sheet.querySelector('.brand-footer');
                    if (v.footer) {
                        if (!foot) { foot = doc.createElement('div'); foot.className = 'brand-footer'; sheet.appendChild(foot); }
                        foot.style.color = v.text; foot.style.fontSize = '11px'; foot.style.lineHeight = '1.6';
                        foot.innerHTML = v.footer.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }).replace(/\n/g, '<br>');
                    } else if (foot) { foot.parentNode.removeChild(foot); }
                }
            }
        }

        frame.addEventListener('load', function () { frameReady = true; layoutFrame(); apply(); });

        // ---- inputs ----
        $('.spdf-color-pick').on('input change', function () {
            $('#spdf-hex-' + $(this).data('target')).val($(this).val()).trigger('spdf');
        });
        $('.spdf-color-hex').on('keyup change', function () {
            var v = $(this).val().trim();
            if (validHex(v)) { $('.spdf-color-pick[data-target=' + this.name + ']').val(v.toLowerCase()); }
            apply();
        });
        $('#spdf-logo-size').on('input', function () { $('#spdf-logo-size-val').text(this.value); apply(); });
        $('#spdf-name-size').on('input', function () { $('#spdf-name-size-val').text(this.value); apply(); });
        $('.spdf-brand-input').on('change spdf', apply);
        $('#spdf-tagline, #spdf-footer').on('keyup', apply);
        $('input[name=logo_source]').on('change', function () { loadStudioBase(); apply(); });

        // ---- per-template override enable toggle ----
        $('#spdf-override-toggle').on('change', function () {
            $('#spdf-brand-body').toggleClass('spdf-disabled', !this.checked);
        });

        /* =================== Logo Studio (canvas) =================== */
        var studioImg = new Image();           // the current SOURCE (original) image
        studioImg.crossOrigin = 'anonymous';
        var studioLoaded = false;

        function loadStudioBase() {
            var useCustom = $('input[name=logo_source]:checked').val() === 'custom';
            var src = (useCustom && customLogoUrl) ? customLogoUrl : crmLogoUrl;
            if (!src) { studioLoaded = false; process(); return; }
            studioLoaded = false;
            studioImg.onload = function () { studioLoaded = true; process(); };
            studioImg.onerror = function () { studioLoaded = false; $('#spdf-studio-empty').show().text('<?php echo _l('smart_pdf_studio_load_err'); ?>'); $('#spdf-studio-img').hide(); };
            studioImg.src = src;
        }

        function colorDist(r, g, b, r2, g2, b2) {
            return Math.sqrt((r - r2) * (r - r2) + (g - g2) * (g - g2) + (b - b2) * (b - b2));
        }

        function process() {
            var out = document.getElementById('spdf-studio-img');
            if (!studioLoaded) { out.style.display = 'none'; $('#spdf-studio-empty').show(); return; }
            $('#spdf-studio-empty').hide();

            var canvas = document.getElementById('spdf-studio-canvas');
            var maxW = 600;
            var scale = Math.min(1, maxW / studioImg.naturalWidth);
            var w = Math.max(1, Math.round(studioImg.naturalWidth * scale));
            var h = Math.max(1, Math.round(studioImg.naturalHeight * scale));
            canvas.width = w; canvas.height = h;
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, w, h);
            ctx.drawImage(studioImg, 0, 0, w, h);

            var data;
            try { data = ctx.getImageData(0, 0, w, h); }
            catch (e) { out.src = studioImg.src; out.style.display = ''; return; }  // tainted -> show original
            var px = data.data;

            var removeBg = $('#spdf-remove-bg').is(':checked');
            var tol = parseInt($('#spdf-bg-tol').val(), 10) || 70;
            var recolor = $('input[name=_recolor]:checked').val();

            if (removeBg) {
                // background colour = average of the four corners
                function corner(x, y) { var i = (y * w + x) * 4; return [px[i], px[i + 1], px[i + 2]]; }
                var cs = [corner(0, 0), corner(w - 1, 0), corner(0, h - 1), corner(w - 1, h - 1)];
                var br = (cs[0][0] + cs[1][0] + cs[2][0] + cs[3][0]) / 4;
                var bg = (cs[0][1] + cs[1][1] + cs[2][1] + cs[3][1]) / 4;
                var bb = (cs[0][2] + cs[1][2] + cs[2][2] + cs[3][2]) / 4;
                for (var i = 0; i < px.length; i += 4) {
                    if (colorDist(px[i], px[i + 1], px[i + 2], br, bg, bb) <= tol) { px[i + 3] = 0; }
                }
            }
            if (recolor === 'white' || recolor === 'black') {
                var tv = recolor === 'white' ? 255 : 0;
                for (var j = 0; j < px.length; j += 4) {
                    if (px[j + 3] > 10) { px[j] = tv; px[j + 1] = tv; px[j + 2] = tv; }
                }
            }
            ctx.putImageData(data, 0, 0);
            out.src = canvas.toDataURL('image/png');
            out.style.display = '';
        }

        $('#spdf-remove-bg').on('change', function () { $('#spdf-tol-row').toggle(this.checked); process(); });
        $('#spdf-bg-tol').on('input', function () { $('#spdf-bg-tol-val').text(this.value); process(); });
        $('input[name=_recolor]').on('change', process);
        $('#spdf-studio-darktoggle').on('click', function () { $('#spdf-studio-out').toggleClass('on-dark spdf-checker'); });

        $('#spdf-logo-upload-btn').on('click', function () { $('#spdf-logo-upload').click(); });
        $('#spdf-logo-upload').on('change', function () {
            if (!this.files || !this.files.length || !canEdit) { return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                customLogoUrl = e.target.result;                 // uploaded original becomes the studio base
                $('input[name=logo_source][value=custom]').prop('checked', true);
                studioLoaded = false;
                studioImg.onload = function () { studioLoaded = true; process(); };
                studioImg.src = customLogoUrl;
                $('#spdf-logo-upload-status').text('<?php echo _l('smart_pdf_studio_ready'); ?>');
            };
            reader.readAsDataURL(this.files[0]);
        });

        // Apply the processed logo: upload the canvas PNG and set it as the
        // custom logo for this profile, then refresh the document preview.
        $('#spdf-studio-apply').on('click', function () {
            if (!canEdit) { return; }
            var canvas = document.getElementById('spdf-studio-canvas');
            if (!studioLoaded || !canvas.width) { $('#spdf-logo-upload-status').text('<?php echo _l('smart_pdf_studio_none'); ?>'); return; }
            var btn = $(this).prop('disabled', true);
            $('#spdf-logo-upload-status').text('<?php echo _l('smart_pdf_brand_uploading'); ?>');
            canvas.toBlob(function (blob) {
                var fd = new FormData();
                fd.append('logo', blob, 'brand-logo.png');
                fd.append(csrfName, csrfHash);
                $.ajax({ url: admin_url + 'smart_pdf/upload_brand_logo', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                    .done(function (resp) {
                        if (resp && resp[csrfName]) { csrfHash = resp[csrfName]; }
                        if (!resp || !resp.success) { $('#spdf-logo-upload-status').text(resp && resp.message ? resp.message : 'Error'); return; }
                        $('#spdf-logo-file').val(resp.file);
                        customLogoUrl = resp.url;
                        $('input[name=logo_source][value=custom]').prop('checked', true);
                        $('#spdf-logo-upload-status').text('<?php echo _l('smart_pdf_brand_upload_done'); ?>');
                        apply();
                    })
                    .fail(function () { $('#spdf-logo-upload-status').text('Error'); })
                    .always(function () { btn.prop('disabled', false); });
            }, 'image/png');
        });

        // init studio with the current logo
        loadStudioBase();
    });
</script>
