<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CCX Leads — Independent New Lead Form (Perfex CRM Standard Modal)
 * Fields rendered in 3-column layout based on saved field_layout.
 */

// Helper to check field visibility, label, required status
if (!function_exists('ccx_field')) {
    function ccx_field($slug, $field_settings, $default_label = '')
    {
        $setting = ['active' => 1, 'label' => $default_label, 'required' => 0];
        if (isset($field_settings[$slug])) {
            $s = $field_settings[$slug];
            $setting['active'] = isset($s['active']) ? (int) $s['active'] : 1;
            $setting['label'] = !empty($s['label']) ? $s['label'] : $default_label;
            $setting['required'] = isset($s['required']) ? (int) $s['required'] : 0;
        }
        return $setting;
    }
}

if (!function_exists('ccx_label')) {
    function ccx_label($for, $text, $required = false)
    {
        $req = $required ? ' <small class="req text-danger">*</small>' : '';
        return '<label for="' . $for . '" class="control-label">' . e($text) . $req . '</label>';
    }
}

$fs = isset($field_settings) ? $field_settings : [];
$layout = isset($field_layout) ? $field_layout : ['1' => [], '2' => [], '3' => []];

// Build a lookup of slug → column for standard fields
$slug_to_col = [];
foreach (['1', '2', '3'] as $cn) {
    if (!isset($layout[$cn]))
        continue;
    foreach ($layout[$cn] as $idx => $fi) {
        if ($fi['type'] === 'standard') {
            $slug_to_col[$fi['slug']] = ['col' => $cn, 'order' => $idx];
        }
    }
}

// Helper: get fields for a column (only standard field slugs)
function ccx_col_slugs($layout, $col)
{
    $slugs = [];
    if (isset($layout[$col])) {
        foreach ($layout[$col] as $fi) {
            if ($fi['type'] === 'standard') {
                $slugs[] = $fi['slug'];
            }
        }
    }
    return $slugs;
}

// Helper: check if a column has any custom fields
function ccx_col_has_custom($layout, $col)
{
    if (isset($layout[$col])) {
        foreach ($layout[$col] as $fi) {
            if ($fi['type'] === 'custom')
                return true;
        }
    }
    return false;
}
?>

<?php
// ── Rendering helper for a standard field by slug ──
function ccx_render_new_field($slug, $fs, $statuses, $sources, $members, $base_currency)
{
    $labels = [
        'status' => 'lead_add_edit_status',
        'source' => 'lead_add_edit_source',
        'assigned' => 'lead_add_edit_assigned',
        'name' => 'lead_add_edit_name',
        'title' => 'lead_title',
        'email' => 'lead_add_edit_email',
        'website' => 'lead_website',
        'phonenumber' => 'lead_add_edit_phonenumber',
        'lead_value' => 'lead_value',
        'company' => 'lead_company',
        'address' => 'lead_address',
        'city' => 'lead_city',
        'state' => 'lead_state',
        'country' => 'lead_country',
        'zip' => 'lead_zip',
        'description' => 'lead_description',
        'is_public' => 'lead_public',
    ];
    $default_label = isset($labels[$slug]) ? _l($labels[$slug]) : $slug;
    $f = ccx_field($slug, $fs, $default_label);
    if (!$f['active'])
        return;

    switch ($slug) {
        case 'status':
            ?>
            <div class="select-placeholder form-group" app-field-wrapper="status">
                <?= ccx_label('status', $f['label'], $f['required']); ?>
                <select id="status" name="status" class="selectpicker" data-live-search="true" data-width="100%"
                    data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" <?= $f['required'] ? ' required' : ''; ?>>
                    <option value=""></option>
                    <?php
                    $default_status = get_option('leads_default_status');
                    foreach ($statuses as $s) {
                        $sel = ($s['id'] == $default_status) ? ' selected' : '';
                        echo '<option value="' . $s['id'] . '" data-content="<span class=\'lead-status-' . $s['id'] . ' label\' style=\'color:' . $s['color'] . ';border:1px solid ' . adjust_hex_brightness($s['color'], 0.4) . ';background:' . adjust_hex_brightness($s['color'], 0.04) . '\'>' . e($s['name']) . '</span>"' . $sel . '>' . e($s['name']) . '</option>';
                    } ?>
                </select>
            </div>
            <?php break;

        case 'source':
            ?>
            <div class="select-placeholder form-group" app-field-wrapper="source">
                <?= ccx_label('source', $f['label'], $f['required']); ?>
                <select id="source" name="source" class="selectpicker" data-live-search="true" data-width="100%"
                    data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" <?= $f['required'] ? ' required' : ''; ?>>
                    <option value=""></option>
                    <?php
                    $default_source = get_option('leads_default_source');
                    foreach ($sources as $s) {
                        $sel = ($s['id'] == $default_source) ? ' selected' : '';
                        echo '<option value="' . $s['id'] . '"' . $sel . '>' . e($s['name']) . '</option>';
                    } ?>
                </select>
            </div>
            <?php break;

        case 'assigned':
            ?>
            <div class="select-placeholder form-group" app-field-wrapper="assigned">
                <?= ccx_label('assigned', $f['label'], $f['required']); ?>
                <select id="assigned" name="assigned" class="selectpicker" data-live-search="true" data-width="100%"
                    data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" <?= $f['required'] ? ' required' : ''; ?>>
                    <option value=""></option>
                    <?php
                    $current_staff = get_staff_user_id();
                    foreach ($members as $m) {
                        $sel = ($m['staffid'] == $current_staff) ? ' selected' : '';
                        echo '<option value="' . $m['staffid'] . '"' . $sel . '>' . e($m['firstname'] . ' ' . $m['lastname']) . '</option>';
                    } ?>
                </select>
            </div>
            <?php break;

        case 'title':
            // Title is now merged into the Name field — skip standalone rendering
            break;

        case 'name':
            // Merged Title (prefix dropdown) + Name input
            $title_f = ccx_field('title', $fs, _l('lead_title'));
            ?>
            <div class="form-group" app-field-wrapper="name">
                <?= ccx_label('name', $f['label'], $f['required']); ?>
                <div class="input-group">
                    <?php if ($title_f['active']) { ?>
                        <div class="input-group-addon" style="padding:0;border:none;background:transparent;">
                            <select name="title" id="title" class="selectpicker" data-width="auto">
                                <option value="">--</option>
                                <option value="Mr." selected>Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Miss">Miss</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>
                    <?php } ?>
                    <input type="text" id="name" name="name" class="form-control" value="" placeholder="Full Name" <?= $f['required'] ? ' required' : ''; ?>>
                </div>
            </div>
            <?php break;

        case 'phonenumber':
            ?>
            <div class="form-group" app-field-wrapper="phonenumber">
                <?= ccx_label('phonenumber', $f['label'], $f['required']); ?>
                <div class="input-group">
                    <div class="input-group-addon" style="padding:0;border:none;background:transparent;">
                        <select name="phone_country_code" id="phone_country_code" class="selectpicker" data-live-search="true" data-width="auto">
                            <option value="" data-content="🌐 Code">Code</option>
                            <option value="+91" data-content="🇮🇳 +91" selected>+91 IN</option>
                            <option value="+93" data-content="🇦🇫 +93">+93 AF</option>
                            <option value="+355" data-content="🇦🇱 +355">+355 AL</option>
                            <option value="+213" data-content="🇩🇿 +213">+213 DZ</option>
                            <option value="+376" data-content="🇦🇩 +376">+376 AD</option>
                            <option value="+244" data-content="🇦🇴 +244">+244 AO</option>
                            <option value="+54" data-content="🇦🇷 +54">+54 AR</option>
                            <option value="+374" data-content="🇦🇲 +374">+374 AM</option>
                            <option value="+61" data-content="🇦🇺 +61">+61 AU</option>
                            <option value="+43" data-content="🇦🇹 +43">+43 AT</option>
                            <option value="+994" data-content="🇦🇿 +994">+994 AZ</option>
                            <option value="+973" data-content="🇧🇭 +973">+973 BH</option>
                            <option value="+880" data-content="🇧🇩 +880">+880 BD</option>
                            <option value="+375" data-content="🇧🇾 +375">+375 BY</option>
                            <option value="+32" data-content="🇧🇪 +32">+32 BE</option>
                            <option value="+55" data-content="🇧🇷 +55">+55 BR</option>
                            <option value="+1" data-content="🇺🇸 +1">+1 US/CA</option>
                            <option value="+86" data-content="🇨🇳 +86">+86 CN</option>
                            <option value="+57" data-content="🇨🇴 +57">+57 CO</option>
                            <option value="+20" data-content="🇪🇬 +20">+20 EG</option>
                            <option value="+33" data-content="🇫🇷 +33">+33 FR</option>
                            <option value="+49" data-content="🇩🇪 +49">+49 DE</option>
                            <option value="+30" data-content="🇬🇷 +30">+30 GR</option>
                            <option value="+852" data-content="🇭🇰 +852">+852 HK</option>
                            <option value="+62" data-content="🇮🇩 +62">+62 ID</option>
                            <option value="+98" data-content="🇮🇷 +98">+98 IR</option>
                            <option value="+964" data-content="🇮🇶 +964">+964 IQ</option>
                            <option value="+353" data-content="🇮🇪 +353">+353 IE</option>
                            <option value="+972" data-content="🇮🇱 +972">+972 IL</option>
                            <option value="+39" data-content="🇮🇹 +39">+39 IT</option>
                            <option value="+81" data-content="🇯🇵 +81">+81 JP</option>
                            <option value="+962" data-content="🇯🇴 +962">+962 JO</option>
                            <option value="+254" data-content="🇰🇪 +254">+254 KE</option>
                            <option value="+965" data-content="🇰🇼 +965">+965 KW</option>
                            <option value="+60" data-content="🇲🇾 +60">+60 MY</option>
                            <option value="+52" data-content="🇲🇽 +52">+52 MX</option>
                            <option value="+31" data-content="🇳🇱 +31">+31 NL</option>
                            <option value="+64" data-content="🇳🇿 +64">+64 NZ</option>
                            <option value="+234" data-content="🇳🇬 +234">+234 NG</option>
                            <option value="+47" data-content="🇳🇴 +47">+47 NO</option>
                            <option value="+968" data-content="🇴🇲 +968">+968 OM</option>
                            <option value="+92" data-content="🇵🇰 +92">+92 PK</option>
                            <option value="+63" data-content="🇵🇭 +63">+63 PH</option>
                            <option value="+48" data-content="🇵🇱 +48">+48 PL</option>
                            <option value="+351" data-content="🇵🇹 +351">+351 PT</option>
                            <option value="+974" data-content="🇶🇦 +974">+974 QA</option>
                            <option value="+7" data-content="🇷🇺 +7">+7 RU</option>
                            <option value="+966" data-content="🇸🇦 +966">+966 SA</option>
                            <option value="+65" data-content="🇸🇬 +65">+65 SG</option>
                            <option value="+27" data-content="🇿🇦 +27">+27 ZA</option>
                            <option value="+82" data-content="🇰🇷 +82">+82 KR</option>
                            <option value="+34" data-content="🇪🇸 +34">+34 ES</option>
                            <option value="+94" data-content="🇱🇰 +94">+94 LK</option>
                            <option value="+46" data-content="🇸🇪 +46">+46 SE</option>
                            <option value="+41" data-content="🇨🇭 +41">+41 CH</option>
                            <option value="+66" data-content="🇹🇭 +66">+66 TH</option>
                            <option value="+90" data-content="🇹🇷 +90">+90 TR</option>
                            <option value="+380" data-content="🇺🇦 +380">+380 UA</option>
                            <option value="+971" data-content="🇦🇪 +971">+971 AE</option>
                            <option value="+44" data-content="🇬🇧 +44">+44 UK</option>
                            <option value="+84" data-content="🇻🇳 +84">+84 VN</option>
                        </select>
                    </div>
                    <input type="text" id="phonenumber" name="phonenumber" class="form-control" value="" placeholder="Phone Number"
                        <?= $f['required'] ? ' required' : ''; ?>>
                </div>
            </div>
            <?php break;

        case 'lead_value':
            ?>
            <div class="form-group" app-field-wrapper="lead_value">
                <?= ccx_label('lead_value', $f['label'], $f['required']); ?>
                <div class="input-group" data-toggle="tooltip" title="<?= _l('lead_value_tooltip'); ?>">
                    <input type="number" class="form-control" name="lead_value" id="lead_value" value="" <?= $f['required'] ? ' required' : ''; ?>>
                    <div class="input-group-addon"><?= e($base_currency->symbol); ?></div>
                </div>
            </div>
            <?php break;

        case 'address':
            ?>
            <div class="form-group" app-field-wrapper="address">
                <?= ccx_label('address', $f['label'], $f['required']); ?>
                <textarea id="address" name="address" class="form-control" rows="2" <?= $f['required'] ? ' required' : ''; ?>></textarea>
            </div>
            <?php break;

        case 'country':
            $countries = get_all_countries();
            $customer_default_country = get_option('customer_default_country');
            ?>
            <div class="select-placeholder form-group" app-field-wrapper="country">
                <?= ccx_label('country', $f['label'], $f['required']); ?>
                <select id="country" name="country" class="selectpicker" data-live-search="true" data-width="100%"
                    data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" <?= $f['required'] ? ' required' : ''; ?>>
                    <option value=""></option>
                    <?php foreach ($countries as $c) {
                        $sel = ($c['country_id'] == $customer_default_country) ? ' selected' : '';
                        echo '<option value="' . $c['country_id'] . '"' . $sel . '>' . e($c['short_name']) . '</option>';
                    } ?>
                </select>
            </div>
            <?php break;

        case 'description':
            ?>
            <div class="form-group" app-field-wrapper="description">
                <?= ccx_label('description', $f['label'], $f['required']); ?>
                <textarea id="description" name="description" class="form-control" rows="3"
                    <?= $f['required'] ? ' required' : ''; ?>></textarea>
            </div>
            <?php break;

        case 'is_public':
            // Rendered in modal footer instead
            break;

        default:
            // Simple text field (email, website, city, state, zip, company)
            ?>
            <div class="form-group" app-field-wrapper="<?= $slug; ?>">
                <?= ccx_label($slug, $f['label'], $f['required']); ?>
                <input type="text" id="<?= $slug; ?>" name="<?= $slug; ?>" class="form-control" value="" <?= $f['required'] ? ' required' : ''; ?>>
            </div>
            <?php break;
    }
}
?>

<!-- modal-xxl override for Bootstrap 3 (Perfex CRM) -->
<style>
    #ccx-lead-modal .modal-dialog.modal-xxl {
        width: 95vw !important;
        max-width: 1800px !important;
        margin: 30px auto !important;
    }
    #ccx-lead-modal .modal-body {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }
    #ccx-lead-modal .modal-content > .modal-footer {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        text-align: left !important;
        padding: 15px 20px !important;
    }
    .ccx-new-lead-section-heading {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 15px;
        margin-top: 5px;
    }
    .ccx-new-lead-section-heading i {
        margin-right: 6px;
        color: #8e8e8e;
    }
    .ccx-calllog-section {
        background: #f9f9f9;
        border: 1px solid #e8e8e8;
        border-radius: 4px;
        padding: 15px 20px;
        margin-top: 10px;
    }
</style>

<!-- ═══════════ MODAL HEADER (Perfex Standard) ═══════════ -->
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <h4 class="modal-title">
        <?= _l('add_new', _l('lead_lowercase')); ?>
    </h4>
</div>

<!-- ═══════════ MODAL BODY (Perfex Standard) ═══════════ -->
<div class="modal-body">
    <?= form_open(admin_url('ccx_leads/save_lead'), ['id' => 'ccx_new_lead_form']); ?>

    <?php
    // ==================== SECTION 1 — Lead Classification ====================
    $col1_slugs = ccx_col_slugs($layout, '1');
    if (!empty($col1_slugs)) { ?>
        <div class="row">
            <?php foreach ($col1_slugs as $slug) { ?>
                <div class="col-md-4">
                    <?php ccx_render_new_field($slug, $fs, $statuses, $sources, $members, $base_currency); ?>
                </div>
            <?php } ?>
        </div>
        <hr class="hr-panel-separator" />
    <?php } ?>

    <!-- ==================== Contact Details + Call Log — Side by Side ==================== -->
    <div class="row">
        <!-- ── Left: Contact Information ── -->
        <div class="col-md-7">
            <div class="row">
                <?php
                $col2_slugs = ccx_col_slugs($layout, '2');
                ?>
                <div class="col-md-6">
                    <?php foreach ($col2_slugs as $slug) {
                        ccx_render_new_field($slug, $fs, $statuses, $sources, $members, $base_currency);
                    } ?>
                    <?php
                    if (ccx_col_has_custom($layout, '2')) {
                        echo render_custom_fields('leads', false);
                    }
                    ?>
                </div>
                <?php
                $col3_slugs = ccx_col_slugs($layout, '3');
                ?>
                <div class="col-md-6">
                    <?php foreach ($col3_slugs as $slug) {
                        ccx_render_new_field($slug, $fs, $statuses, $sources, $members, $base_currency);
                    } ?>
                    <?php
                    if (ccx_col_has_custom($layout, '3') && !ccx_col_has_custom($layout, '2')) {
                        echo render_custom_fields('leads', false);
                    }
                    ?>
                </div>
            </div>
            <?php
            if (!ccx_col_has_custom($layout, '2') && !ccx_col_has_custom($layout, '3')) {
                $cf_html = render_custom_fields('leads', false);
                if (trim($cf_html) != '') {
                    echo '<div class="row"><div class="col-md-12">' . $cf_html . '</div></div>';
                }
            }
            ?>
        </div>

        <!-- ── Right: Call Log ── -->
        <div class="col-md-5">
            <input type="hidden" name="add_call_log" value="1">
            <div class="ccx-calllog-section">
                <div class="ccx-new-lead-section-heading" style="margin-top:0;">
                    <i class="fa fa-phone"></i> Call Log
                </div>
                <div class="form-group">
                    <label for="call_log_description" class="control-label">Call Notes</label>
                    <textarea id="call_log_description" name="call_log_description" class="form-control" rows="3"
                        placeholder="Enter call notes..."></textarea>
                </div>
                <?= render_datetime_input(
                    'call_log_contact_date',
                    'lead_add_edit_datecontacted',
                    _dt(date('Y-m-d H:i:s')),
                    ['data-date-end-date' => date('Y-m-d')]
                ); ?>
                <div class="form-group">
                    <div class="radio radio-primary">
                        <input type="radio" name="call_log_contacted" id="call_log_contacted_yes" value="yes" checked>
                        <label for="call_log_contacted_yes"><?= _l('lead_add_edit_contacted_this_lead'); ?></label>
                    </div>
                    <div class="radio radio-primary">
                        <input type="radio" name="call_log_contacted" id="call_log_contacted_no" value="no">
                        <label for="call_log_contacted_no"><?= _l('lead_not_contacted'); ?></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?= form_close(); ?>
</div>

<!-- ═══════════ MODAL FOOTER (Perfex Standard) ═══════════ -->
<?php $pub_f = ccx_field('is_public', $fs, _l('lead_public')); ?>
<div class="modal-footer" style="display:flex !important;justify-content:space-between !important;align-items:center;">
    <div>
        <?php if ($pub_f['active']) { ?>
        <div class="checkbox checkbox-primary" style="margin:0;">
            <input type="checkbox" name="is_public" id="ccx_lead_public" form="ccx_new_lead_form">
            <label for="ccx_lead_public"><?= e($pub_f['label']); ?></label>
        </div>
        <?php } ?>
    </div>
    <div>
        <button type="button" class="btn btn-default" data-dismiss="modal">
            <?= _l('close'); ?>
        </button>
        <button type="submit" form="ccx_new_lead_form" class="btn btn-primary ccx-new-lead-save-btn">
            <i class="fa fa-check"></i> <?= _l('submit'); ?>
        </button>
    </div>
</div>

<script>
// Reinitialize selectpickers after modal content loads
(function(){
    if (typeof init_selectpicker === 'function') {
        init_selectpicker();
    }
})();
</script>