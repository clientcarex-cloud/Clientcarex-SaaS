<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Smart_pdf_model extends App_Model
{
    /**
     * key => canonical tag name for every known smart tag (system, branding,
     * patient, employee). Built lazily by canonical_tag_map().
     *
     * @var array|null
     */
    protected $canonical_map = null;

    /**
     * staff id => resolved profile picture URL ('' = none), per request.
     *
     * @var array
     */
    protected $staff_photo_cache = [];

    /**
     * How much text around a {{tag}} is inspected to decide whether it sits in
     * markup, CSS or rendered text. Big enough to see the surrounding tags,
     * small enough to stay cheap on a 400KB design-tool export.
     */
    const TAG_CONTEXT_WINDOW = 4000;

    public function __construct()
    {
        parent::__construct();

        // Self-healing: make sure the tables (and later columns, e.g. the
        // employee_id added for the HR integration) exist even if the module
        // was activated before this version added them.
        if (!$this->db->table_exists(db_prefix() . 'smart_pdf_templates')
            || !$this->db->table_exists(db_prefix() . 'smart_pdf_generations')
            || !$this->db->field_exists('employee_id', db_prefix() . 'smart_pdf_generations')
            || !$this->db->field_exists('branding', db_prefix() . 'smart_pdf_templates')) {
            $CI = &get_instance();
            require(module_dir_path('smart_pdf', 'install.php'));
        }
    }

    /**
     * Get single template
     *
     * @param int $id
     * @return object|null
     */
    public function get($id)
    {
        return $this->db->where('id', $id)
            ->get(db_prefix() . 'smart_pdf_templates')
            ->row();
    }

    /**
     * All templates for the card grid, with generation counters.
     *
     * @return array
     */
    public function get_all()
    {
        $t = db_prefix() . 'smart_pdf_templates';
        $g = db_prefix() . 'smart_pdf_generations';

        $this->db->select($t . '.*');
        $this->db->select('(SELECT COUNT(*) FROM ' . $g . ' WHERE ' . $g . '.template_id = ' . $t . '.id) as generations_count');
        $this->db->from($t);
        $this->db->order_by($t . '.active', 'desc');
        $this->db->order_by($t . '.name', 'asc');

        return $this->db->get()->result_array();
    }

    /**
     * Add new template
     *
     * @param array $data
     * @return int insert id
     */
    public function add($data)
    {
        $this->db->insert(db_prefix() . 'smart_pdf_templates', $data);

        return $this->db->insert_id();
    }

    /**
     * Update template
     *
     * @param array $data
     * @param int $id
     * @return bool
     */
    public function update($data, $id)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'smart_pdf_templates', $data);

        return $this->db->affected_rows() >= 0;
    }

    /**
     * Delete template and its generation history
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'smart_pdf_templates');

        $deleted = $this->db->affected_rows() > 0;

        if ($deleted) {
            $this->db->where('template_id', $id);
            $this->db->delete(db_prefix() . 'smart_pdf_generations');
        }

        return $deleted;
    }

    /**
     * Duplicate an existing template.
     *
     * @param int $id
     * @return int|false new template id
     */
    public function clone_template($id)
    {
        $template = $this->get($id);
        if (!$template) {
            return false;
        }

        return $this->add([
            'name'        => $template->name . ' (' . _l('smart_pdf_copy') . ')',
            'description' => $template->description,
            'content'     => $template->content,
            'fields'      => $template->fields,
            'paper_size'  => $template->paper_size,
            'orientation' => $template->orientation,
            'active'      => 0,
        ]);
    }

    /**
     * Install the bundled sample templates that ship in
     * modules/smart_pdf/sample_templates/<subdir>/*.html into this tenant's
     * template library. Each file's <title> becomes the template name; tags
     * are auto-detected via sync_fields exactly like a manual import.
     *
     * By default templates whose name already exists are skipped (idempotent).
     * With $overwrite = true, an existing template of the same name has its
     * HTML content re-applied from the bundled file (so design changes such as
     * the company logo take effect). Its per-tag configuration is preserved
     * where the tags still exist (sync_fields merges the stored fields), and
     * its active flag, name and id are kept so generation history survives.
     *
     * @param string $subdir e.g. 'hr'
     * @param bool $overwrite re-apply content to existing templates
     * @return array ['installed' => int, 'updated' => int, 'skipped' => int, 'files' => int]
     */
    public function install_sample_templates($subdir = 'hr', $overwrite = false)
    {
        $subdir = preg_replace('/[^a-z0-9_\-]/i', '', $subdir);
        $dir    = module_dir_path('smart_pdf', 'sample_templates/' . $subdir . '/');

        if (!is_dir($dir)) {
            return ['installed' => 0, 'updated' => 0, 'skipped' => 0, 'files' => 0];
        }

        $files = glob($dir . '*.html');
        if (!is_array($files)) {
            $files = [];
        }

        // Existing name (case-insensitive) => id, so re-running never
        // duplicates and overwrite can target the right row.
        $existing = [];
        foreach ($this->db->select('id, name')->get(db_prefix() . 'smart_pdf_templates')->result() as $row) {
            $existing[mb_strtolower(trim($row->name))] = (int) $row->id;
        }

        $installed = 0;
        $updated   = 0;
        $skipped   = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false || trim($content) === '') {
                continue;
            }

            $name = $this->extract_title($content);
            if ($name === '') {
                $name = ucwords(str_replace(['-', '_'], ' ', pathinfo($file, PATHINFO_FILENAME)));
            }
            $key = mb_strtolower(trim($name));

            if (isset($existing[$key])) {
                if (!$overwrite) {
                    $skipped++;
                    continue;
                }

                // Re-apply the bundled HTML but keep the user's per-tag config
                // for tags that still exist (merged via sync_fields).
                $current        = $this->get($existing[$key]);
                $current_fields = $current ? $this->get_fields($current) : [];
                $fields         = $this->sync_fields($content, $current_fields);

                $this->update([
                    'content' => $content,
                    'fields'  => json_encode($fields, JSON_UNESCAPED_UNICODE),
                ], $existing[$key]);

                $updated++;
                continue;
            }

            // Landscape / card templates declare their own @page size; the
            // stored paper flags only drive the card-thumbnail aspect, so
            // portrait A4 is a safe default for these full-HTML documents.
            $fields = $this->sync_fields($content, []);

            $new_id = $this->add([
                'name'        => $name,
                'description' => 'HR document template (bundled)',
                'content'     => $content,
                'fields'      => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'paper_size'  => 'A4',
                'orientation' => 'P',
                'active'      => 1,
            ]);

            $existing[$key] = (int) $new_id;
            $installed++;
        }

        return ['installed' => $installed, 'updated' => $updated, 'skipped' => $skipped, 'files' => count($files)];
    }

    /**
     * Extract the <title> text from an HTML document, for naming a template.
     *
     * @param string $html
     * @return string
     */
    protected function extract_title($html)
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    /**
     * Counters for the manage page header cards.
     *
     * @return array
     */
    public function get_stats()
    {
        $templates = db_prefix() . 'smart_pdf_templates';
        $generations = db_prefix() . 'smart_pdf_generations';

        return [
            'total'             => (int) $this->db->count_all($templates),
            'active'            => (int) $this->db->where('active', 1)->count_all_results($templates),
            'generations'       => (int) $this->db->count_all($generations),
            'generations_month' => (int) $this->db->where('created_at >=', date('Y-m-01 00:00:00'))->count_all_results($generations),
        ];
    }

    /**
     * Tags that are filled automatically at generate time and are never
     * prompted in the generate popup.
     *
     * @return array tag names
     */
    public function system_tag_names()
    {
        // Raw (HTML-valued) branding / staff tags are also auto-filled and must
        // never be prompted for in the setup screen, so include them here too.
        return array_merge(
            array_keys($this->get_system_tags('PREVIEW')),
            array_keys($this->raw_tag_values())
        );
    }

    /**
     * System tag values, resolved at generate time.
     *
     * @param string $document_no
     * @return array tag => value
     */
    public function get_system_tags($document_no = '', $branding = null)
    {
        $address_parts = array_filter(array_map('trim', [
            (string) get_option('invoice_company_address'),
            (string) get_option('invoice_company_city'),
            (string) get_option('company_state'),
            (string) get_option('invoice_company_postal_code'),
        ]), function ($part) {
            return $part !== '';
        });

        $clinic_name = get_option('invoice_company_name');
        if ($clinic_name == '') {
            $clinic_name = get_option('companyname');
        }

        // 1x1 transparent GIF so templates that reference the logo/favicon
        // never render a broken-image icon when the company has not uploaded
        // one. Used inside <img src="{{clinic_logo_url}}"> / watermark.
        $blank_pixel = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        // Prefer the dark-background logo (same order as pdf_logo_url()); it is
        // the light-coloured mark most tenants upload for documents/print.
        $logo_url = $this->company_asset_url('company_logo_dark');
        if ($logo_url === '') {
            $logo_url = $this->company_asset_url('company_logo');
        }
        $favicon_url = $this->company_asset_url('favicon');

        // Per-tenant branding profile (colours / name / tagline / footer). The
        // logo, show/hide and sizing are applied through the injected
        // branding_style_block(); these are the plain-text values templates can
        // reference directly (e.g. inline styles, footer text).
        $b          = $branding ?: $this->get_branding();
        $brand_logo = $this->branding_logo_url($b);
        if ($brand_logo === '') {
            $brand_logo = $logo_url;
        }

        // Photo of the staff member generating the document — for "prepared by"
        // blocks, signature strips and issued-by panels. Blank pixel when the
        // user has no profile picture, so the <img> never breaks.
        $staff_photo = $this->staff_photo_url(get_staff_user_id());

        return [
            'current_date'       => _d(date('Y-m-d')),
            'current_time'       => get_option('time_format') == '24' ? date('H:i') : date('g:i A'),
            'current_datetime'   => _dt(date('Y-m-d H:i:s')),
            'staff_name'         => get_staff_full_name(get_staff_user_id()),
            'staff_photo_url'    => $staff_photo !== '' ? $staff_photo : $blank_pixel,
            'clinic_name'        => $clinic_name,
            'clinic_address'     => implode(', ', $address_parts),
            'clinic_phone'       => get_option('invoice_company_phonenumber'),
            'clinic_logo_url'    => $brand_logo !== '' ? $brand_logo : $blank_pixel,
            'clinic_favicon_url' => $favicon_url !== '' ? $favicon_url : $blank_pixel,
            'document_no'        => $document_no,
            // Branding text values
            'brand_primary'      => $b['primary'],
            'brand_accent'       => $b['accent'],
            'brand_heading'      => $b['heading'],
            'brand_text'         => $b['text'],
            'brand_name'         => $clinic_name,
            'brand_tagline'      => $b['tagline'],
            'brand_footer_text'  => $b['footer_text'],
        ];
    }

    /**
     * Absolute URL of an uploaded company asset (logo / favicon) for the
     * current tenant, or '' if the option is unset or the file is missing.
     * Mirrors how staff_profile_image_url() resolves SaaS upload paths.
     *
     * @param string $option_key e.g. company_logo_dark, company_logo, favicon
     * @return string
     */
    protected function company_asset_url($option_key)
    {
        return $this->uploaded_asset_url((string) get_option($option_key));
    }

    /**
     * Profile picture URL of a staff member, or '' when they have none.
     *
     * staff_profile_image_url() falls back to the generic user placeholder when
     * the file is missing; a stock avatar has no business on a printed document,
     * so that fallback is treated as "no photo" here. The largest generated size
     * is preferred (320px thumb) because documents print at ~300dpi, with the
     * 96px small variant as a fallback for older uploads.
     *
     * @param int $staff_id
     * @return string
     */
    public function staff_photo_url($staff_id)
    {
        $staff_id = (int) $staff_id;
        if (!$staff_id || !function_exists('staff_profile_image_url')) {
            return '';
        }

        // Each lookup costs a query; the history list and the tag resolvers ask
        // for the same people repeatedly, so remember the answers.
        if (array_key_exists($staff_id, $this->staff_photo_cache)) {
            return $this->staff_photo_cache[$staff_id];
        }

        $placeholder = base_url('assets/images/user-placeholder.jpg');
        $photo       = '';

        foreach (['thumb', 'small'] as $size) {
            $url = staff_profile_image_url($staff_id, $size);
            if ($url !== '' && $url !== $placeholder) {
                $photo = $url;
                break;
            }
        }

        return $this->staff_photo_cache[$staff_id] = $photo;
    }

    /**
     * Raw (HTML-valued) tags for the staff member generating the document.
     * {{staff_photo}} drops in a ready-made round portrait; it renders as
     * nothing when the user has not uploaded a picture, so a "prepared by"
     * block degrades to just the name instead of showing a broken image.
     *
     * @return array tag => HTML
     */
    public function staff_raw_tags()
    {
        $url = $this->staff_photo_url(get_staff_user_id());

        $img = '';
        if ($url !== '') {
            $img = '<img class="staff-photo" src="' . html_escape($url) . '" alt=""'
                . ' style="height:64px;width:64px;object-fit:cover;border-radius:50%;">';
        }

        return ['staff_photo' => $img];
    }

    /**
     * Every raw (unescaped, server-built HTML) tag: branding + staff.
     * Kept as one list so system_tag_names() and merge() never drift apart.
     *
     * @param array|null $b branding profile
     * @return array tag => HTML
     */
    public function raw_tag_values($b = null)
    {
        return array_merge($this->branding_raw_tags($b), $this->staff_raw_tags());
    }

    /**
     * Absolute URL of a file stored in the tenant company uploads directory
     * (SaaS-aware), or '' if the filename is empty or the file is missing.
     * Shared by company_asset_url() and the branding custom-logo resolver.
     *
     * @param string $file filename only (no path)
     * @return string
     */
    /**
     * Public resolver for a company-uploads filename (used after the branding
     * logo upload to hand the URL back to the browser).
     *
     * @param string $file
     * @return string
     */
    public function asset_url($file)
    {
        return $this->uploaded_asset_url($file);
    }

    protected function uploaded_asset_url($file)
    {
        $file = (string) $file;
        if ($file === '') {
            return '';
        }

        if (function_exists('perfex_saas_get_upload_path_by_type')) {
            $rel = perfex_saas_get_upload_path_by_type('company', false) . $file;
        } else {
            $rel = 'uploads/company/' . $file;
        }

        $exists = @file_exists($rel)
            || (defined('FCPATH') && @file_exists(FCPATH . $rel))
            || @file_exists(get_upload_path_by_type('company') . $file);

        if (!$exists) {
            return '';
        }

        return base_url($rel);
    }

    /* =====================================================================
     * Per-tenant branding (Branding Center)
     *
     * One JSON profile per tenant (option `smart_pdf_branding`) drives the
     * colours, logo (show/hide/size/source) and company-name display for EVERY
     * generated document. It is applied two ways, neither of which requires
     * editing individual templates:
     *   1. branding_style_block() injects a <style> into each document head. It
     *      overrides the shared design-system CSS vars the bundled templates
     *      declare (--teal/--ink/--gold/--muted) plus generic --brand-* aliases,
     *      and toggles/sizes the .brand-logo / .brand-name letterhead classes.
     *   2. text + raw branding {{tags}} let custom/blank templates opt in
     *      ({{brand_header}}, {{brand_logo}}, {{brand_primary}}, ...).
     * ===================================================================== */

    /**
     * Sensible defaults matching the bundled deep-teal + gold design system.
     *
     * @return array
     */
    public function default_branding()
    {
        return [
            'primary'     => '#0e4d54',
            'accent'      => '#b6923d',
            'heading'     => '#0b2e33',
            'text'        => '#5f7275',
            'font'        => '',        // '' = keep each template's own fonts
            'show_logo'   => 1,
            'logo_source' => 'crm',     // crm | custom
            'logo_file'   => '',        // uploaded filename when logo_source=custom
            'logo_size'   => 27,        // letterhead logo height in px
            'show_name'   => 1,
            'name_size'   => 25,        // company-name font size in px
            'tagline'     => '',
            'footer_text' => '',
        ];
    }

    /**
     * Font-family stacks offered in the Branding Center. Empty = template font.
     * Playfair Display / Source Sans 3 are already loaded by bundled templates.
     *
     * @return array value => label
     */
    public function branding_font_choices()
    {
        return [
            ''                                   => _l('smart_pdf_brand_font_default'),
            'Arial, Helvetica, sans-serif'       => 'Arial / Helvetica',
            "'Source Sans 3', Arial, sans-serif" => 'Source Sans',
            'Georgia, serif'                     => 'Georgia',
            "'Playfair Display', Georgia, serif" => 'Playfair Display',
            "'Times New Roman', Times, serif"    => 'Times New Roman',
            'Roboto, Arial, sans-serif'          => 'Roboto',
            "'Courier New', monospace"           => 'Courier',
        ];
    }

    /**
     * Current branding profile (defaults merged with the saved option).
     *
     * @return array
     */
    public function get_branding()
    {
        $saved = json_decode((string) get_option('smart_pdf_branding'), true);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($this->default_branding(), $saved);
    }

    /**
     * Validate + persist a submitted branding profile. Returns the stored array.
     *
     * @param array $data raw POST
     * @return array
     */
    public function save_branding($data)
    {
        $out = $this->sanitize_branding($data);
        update_option('smart_pdf_branding', json_encode($out, JSON_UNESCAPED_UNICODE));

        return $out;
    }

    /**
     * Validate a submitted branding payload into a clean profile array.
     * Shared by the global save and the per-template override save.
     *
     * @param array $data raw POST
     * @return array
     */
    public function sanitize_branding($data)
    {
        $d    = $this->default_branding();
        $data = is_array($data) ? $data : [];

        return [
            'primary'     => $this->sanitize_hex($data['primary'] ?? '', $d['primary']),
            'accent'      => $this->sanitize_hex($data['accent'] ?? '', $d['accent']),
            'heading'     => $this->sanitize_hex($data['heading'] ?? '', $d['heading']),
            'text'        => $this->sanitize_hex($data['text'] ?? '', $d['text']),
            'font'        => array_key_exists((string) ($data['font'] ?? ''), $this->branding_font_choices()) ? (string) $data['font'] : '',
            'show_logo'   => !empty($data['show_logo']) ? 1 : 0,
            'logo_source' => (($data['logo_source'] ?? 'crm') === 'custom') ? 'custom' : 'crm',
            'logo_file'   => preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($data['logo_file'] ?? '')),
            'logo_size'   => min(120, max(12, (int) ($data['logo_size'] ?? $d['logo_size']))),
            'show_name'   => !empty($data['show_name']) ? 1 : 0,
            'name_size'   => min(48, max(10, (int) ($data['name_size'] ?? $d['name_size']))),
            'tagline'     => trim((string) ($data['tagline'] ?? '')),
            'footer_text' => trim((string) ($data['footer_text'] ?? '')),
        ];
    }

    /**
     * Reset global branding to the shipped defaults.
     */
    public function reset_branding()
    {
        update_option('smart_pdf_branding', json_encode($this->default_branding(), JSON_UNESCAPED_UNICODE));
    }

    /* ---- Per-template branding override -------------------------------- */

    /**
     * Decode a template's stored branding override, or null when the template
     * has none / it is disabled.
     *
     * @param object|array $template
     * @return array|null profile array (no 'enabled' key) or null
     */
    public function template_branding_override($template)
    {
        if (is_object($template)) {
            $raw = isset($template->branding) ? $template->branding : null;
        } elseif (is_array($template)) {
            $raw = isset($template['branding']) ? $template['branding'] : null;
        } else {
            return null;
        }

        $ov = json_decode((string) $raw, true);
        if (!is_array($ov) || empty($ov['enabled'])) {
            return null;
        }

        // Merge the stored keys over the current defaults so profiles saved
        // before a new field existed still resolve cleanly.
        unset($ov['enabled']);

        return array_merge($this->default_branding(), array_intersect_key($ov, $this->default_branding()));
    }

    /**
     * The branding actually applied to one template: its override when enabled,
     * otherwise the global profile.
     *
     * @param object|array|null $template
     * @return array
     */
    public function effective_branding($template = null)
    {
        $override = $template ? $this->template_branding_override($template) : null;

        return $override ?: $this->get_branding();
    }

    /**
     * The per-template branding editor state: the override when set, otherwise
     * the global profile as a starting point. `enabled` reflects whether an
     * override is currently active.
     *
     * @param object|array $template
     * @return array profile + 'enabled'
     */
    public function get_template_branding($template)
    {
        $override = $this->template_branding_override($template);
        if ($override) {
            $override['enabled'] = 1;

            return $override;
        }

        $global            = $this->get_branding();
        $global['enabled'] = 0;

        return $global;
    }

    /**
     * Persist a per-template branding override. When the enable flag is off the
     * override is cleared (the template falls back to global branding).
     *
     * @param int $template_id
     * @param array $data raw POST (includes `branding_enabled`)
     * @return void
     */
    public function save_template_branding($template_id, $data)
    {
        $enabled = !empty($data['branding_enabled']);

        if (!$enabled) {
            $this->db->where('id', (int) $template_id)
                ->update(db_prefix() . 'smart_pdf_templates', ['branding' => null]);

            return;
        }

        $profile            = $this->sanitize_branding($data);
        $profile['enabled'] = 1;

        $this->db->where('id', (int) $template_id)
            ->update(db_prefix() . 'smart_pdf_templates', [
                'branding' => json_encode($profile, JSON_UNESCAPED_UNICODE),
            ]);
    }

    protected function sanitize_hex($value, $fallback)
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    /**
     * Mix $hex toward $target (both #rrggbb) by weight 0..1.
     */
    protected function mix_hex($hex, $target, $weight)
    {
        $h = ltrim($hex, '#');
        $t = ltrim($target, '#');
        if (strlen($h) !== 6 || strlen($t) !== 6) {
            return $hex;
        }
        $weight = max(0, min(1, (float) $weight));
        $c = [];
        for ($i = 0; $i < 3; $i++) {
            $a   = hexdec(substr($h, $i * 2, 2));
            $bb  = hexdec(substr($t, $i * 2, 2));
            $c[] = (int) round($a + ($bb - $a) * $weight);
        }

        return sprintf('#%02x%02x%02x', $c[0], $c[1], $c[2]);
    }

    protected function tint($hex, $weight)
    {
        return $this->mix_hex($hex, '#ffffff', $weight);
    }

    /**
     * Resolve the document logo URL for a branding profile: the uploaded
     * custom logo when chosen, otherwise the CRM company logo (dark first).
     *
     * @param array|null $b
     * @return string '' when nothing usable
     */
    public function branding_logo_url($b = null)
    {
        $b = $b ?: $this->get_branding();

        if (($b['logo_source'] ?? 'crm') === 'custom' && !empty($b['logo_file'])) {
            $url = $this->uploaded_asset_url($b['logo_file']);
            if ($url !== '') {
                return $url;
            }
        }

        $logo = $this->company_asset_url('company_logo_dark');
        if ($logo === '') {
            $logo = $this->company_asset_url('company_logo');
        }

        return $logo;
    }

    /**
     * The <style> block injected into every generated document. Overrides the
     * bundled design-system palette vars + generic --brand-* aliases, and
     * toggles/sizes the letterhead logo + company name. All values are already
     * sanitized (hex / whitelisted font / int), so no escaping is needed.
     *
     * @param array|null $b
     * @return string
     */
    public function branding_style_block($b = null)
    {
        $b = $b ?: $this->get_branding();

        $primary   = $b['primary'];
        $accent    = $b['accent'];
        $heading   = $b['heading'];
        $text      = $b['text'];
        $teal_soft = $this->tint($primary, 0.90);
        $gold_soft = $this->tint($accent, 0.86);
        $line      = $this->tint($primary, 0.82);

        $css  = ':root{';
        // Bundled design-system variables (later :root wins over the template's)
        $css .= '--teal:' . $primary . ';--ink:' . $heading . ';--gold:' . $accent . ';--muted:' . $text . ';';
        $css .= '--teal-soft:' . $teal_soft . ';--gold-soft:' . $gold_soft . ';--line:' . $line . ';';
        // Generic aliases for custom / blank templates
        $css .= '--brand-primary:' . $primary . ';--brand-primary-dark:' . $heading . ';--brand-accent:' . $accent . ';';
        $css .= '--brand-heading:' . $heading . ';--brand-text:' . $text . ';--brand-muted:' . $text . ';';
        if ($b['font'] !== '') {
            $css .= '--brand-font:' . $b['font'] . ';';
        }
        $css .= '}';

        // Letterhead controls — bundled templates use .brand-logo/.brand-name/.brand-sub
        if (empty($b['show_logo'])) {
            $css .= '.brand-logo{display:none!important;}';
        } else {
            $css .= '.brand-logo{height:' . (int) $b['logo_size'] . 'px!important;width:auto!important;max-width:none!important;}';
        }
        if (empty($b['show_name'])) {
            $css .= '.brand-name,.brand-sub{display:none!important;}';
        } else {
            $css .= '.brand-name{font-size:' . (int) $b['name_size'] . 'px!important;}';
        }
        if ($b['font'] !== '') {
            $css .= 'body{font-family:' . $b['font'] . ';}';
        }

        return '<style id="spdf-branding">' . $css . '</style>';
    }

    /**
     * HTML-valued branding tags for turnkey use in custom / blank templates.
     * These are replaced WITHOUT html-escaping in merge() because they are
     * trusted server-built markup (user-supplied parts inside are escaped).
     *
     * @param array|null $b
     * @return array tag => HTML
     */
    public function branding_raw_tags($b = null)
    {
        $b = $b ?: $this->get_branding();

        $clinic = get_option('invoice_company_name');
        if ($clinic == '') {
            $clinic = get_option('companyname');
        }

        $logo = '';
        if (!empty($b['show_logo'])) {
            $url = $this->branding_logo_url($b);
            if ($url !== '') {
                $logo = '<img class="brand-logo" src="' . html_escape($url) . '" alt="" style="height:' . (int) $b['logo_size'] . 'px;width:auto;object-fit:contain;">';
            }
        }

        $name_html = '';
        if (!empty($b['show_name'])) {
            $name_html = '<div class="brand-name" style="font-size:' . (int) $b['name_size'] . 'px;color:' . $b['heading'] . ';font-weight:700;line-height:1.1;">' . html_escape($clinic) . '</div>';
            if ($b['tagline'] !== '') {
                $name_html .= '<div class="brand-sub" style="font-size:10.5px;letter-spacing:2px;text-transform:uppercase;color:' . $b['accent'] . ';margin-top:3px;">' . html_escape($b['tagline']) . '</div>';
            }
        }

        $header = '<div class="brand" style="display:flex;align-items:center;gap:14px;">' . $logo . '<div>' . $name_html . '</div></div>';

        $footer = '';
        if ($b['footer_text'] !== '') {
            $footer = '<div class="brand-footer" style="color:' . $b['text'] . ';font-size:11px;line-height:1.6;">' . nl2br(html_escape($b['footer_text'])) . '</div>';
        }

        return [
            'brand_logo'   => $logo,
            'brand_header' => $header,
            'brand_footer' => $footer,
        ];
    }

    /**
     * Patient tags that can be auto-filled from a selected patient in the
     * generate popup. They are still regular editable fields.
     *
     * @return array tag names
     */
    public function patient_tag_names()
    {
        return [
            'patient_name',
            'patient_mr_number',
            'patient_age',
            'patient_gender',
            'patient_mobile',
            'patient_email',
            'patient_address',
            'patient_dob',
            'patient_uid',
            'patient_visit_code',
        ];
    }

    /**
     * Search patients by name, mobile number or MR number.
     *
     * Patients are Perfex clients enriched by the patients module tables;
     * both are queried directly (LEFT JOIN) so this works without loading
     * the patients module model.
     *
     * @param string $q
     * @return array
     */
    public function search_patients($q)
    {
        if (!$this->db->table_exists(db_prefix() . 'patients_extra')) {
            return [];
        }

        $c  = db_prefix() . 'clients';
        $pe = db_prefix() . 'patients_extra';

        $this->db->select($c . '.userid, ' . $c . '.company as name, ' . $c . '.phonenumber, '
            . $pe . '.mr_number, ' . $pe . '.mobile_number, ' . $pe . '.age, ' . $pe . '.gender');
        $this->db->from($c);
        $this->db->join($pe, $pe . '.patient_id = ' . $c . '.userid', 'left');
        $this->db->group_start();
        $this->db->like($c . '.company', $q);
        $this->db->or_like($c . '.phonenumber', $q);
        $this->db->or_like($pe . '.mobile_number', $q);
        $this->db->or_like($pe . '.mr_number', $q);
        $this->db->group_end();
        $this->db->order_by($c . '.userid', 'desc');
        $this->db->limit(10);

        return $this->db->get()->result_array();
    }

    /**
     * Resolve the patient_* tag values for one patient.
     *
     * @param int $patient_id
     * @return array tag => value
     */
    public function get_patient_tag_values($patient_id)
    {
        $c  = db_prefix() . 'clients';
        $pe = db_prefix() . 'patients_extra';
        $nt = db_prefix() . 'name_titles';

        $has_extra  = $this->db->table_exists($pe);
        $has_titles = $this->db->table_exists($nt);

        $this->db->select($c . '.userid, ' . $c . '.company as full_name, ' . $c . '.phonenumber, ' . $c . '.address');
        $this->db->select('(SELECT email FROM ' . db_prefix() . 'contacts WHERE userid=' . $c . '.userid AND is_primary=1 LIMIT 1) as email');
        if ($has_extra) {
            $this->db->select($pe . '.mr_number, ' . $pe . '.mobile_number, ' . $pe . '.age, ' . $pe . '.gender, ' . $pe . '.dob, ' . $pe . '.uid_no');
            if ($this->db->field_exists('age_unit', $pe)) {
                $this->db->select($pe . '.age_unit');
            }
            $this->db->select('(SELECT visit_code FROM ' . db_prefix() . 'visits WHERE patient_id=' . $c . '.userid ORDER BY created_at DESC LIMIT 1) as visit_code');
        }
        if ($has_extra && $has_titles) {
            $this->db->select($nt . '.name as title');
        }

        $this->db->from($c);
        if ($has_extra) {
            $this->db->join($pe, $pe . '.patient_id = ' . $c . '.userid', 'left');
            if ($has_titles) {
                $this->db->join($nt, $nt . '.id = ' . $pe . '.title_id', 'left');
            }
        }
        $this->db->where($c . '.userid', (int) $patient_id);

        $patient = $this->db->get()->row();
        if (!$patient) {
            return [];
        }

        $name = trim((isset($patient->title) && $patient->title != '' ? $patient->title . ' ' : '') . $patient->full_name);

        $age = isset($patient->age) && $patient->age !== null && $patient->age !== '' ? (string) $patient->age : '';
        if ($age !== '' && !empty($patient->age_unit)) {
            $age .= ' ' . $patient->age_unit;
        }

        $mobile = !empty($patient->phonenumber) ? $patient->phonenumber : (isset($patient->mobile_number) ? (string) $patient->mobile_number : '');

        return [
            'patient_name'       => $name,
            'patient_mr_number'  => isset($patient->mr_number) ? (string) $patient->mr_number : '',
            'patient_age'        => $age,
            'patient_gender'     => isset($patient->gender) ? (string) $patient->gender : '',
            'patient_mobile'     => $mobile,
            'patient_email'      => (string) $patient->email,
            'patient_address'    => (string) $patient->address,
            'patient_dob'        => !empty($patient->dob) ? _d($patient->dob) : '',
            'patient_uid'        => isset($patient->uid_no) ? (string) $patient->uid_no : '',
            'patient_visit_code' => isset($patient->visit_code) ? (string) $patient->visit_code : '',
        ];
    }

    /**
     * Employee tags that can be auto-filled from a selected employee (staff)
     * in the generate popup - the HR counterpart of the patient_* tags. They
     * are still regular editable fields. Used for ID cards, offer/experience
     * letters, certificates, agreements, etc.
     *
     * @return array tag names
     */
    public function employee_tag_names()
    {
        return [
            'employee_name',
            'employee_code',
            'employee_designation',
            'employee_department',
            'employee_email',
            'employee_mobile',
            'employee_doj',
            'employee_dob',
            'employee_gender',
            'employee_blood_group',
            'employee_father_name',
            'employee_qualifications',
            'employee_employment_type',
            'employee_work_location',
            'employee_address',
            'employee_permanent_address',
            'employee_national_id',
            'employee_aadhaar',
            'employee_pan',
            'employee_bank_name',
            'employee_bank_account',
            'employee_bank_ifsc',
            'employee_pf_uan',
            'employee_esi',
            'employee_emergency_contact',
            'employee_reporting_to',
            'employee_basic_salary',
            'employee_photo_url',
        ];
    }

    /**
     * Search employees (staff with an HR profile) by name, employee code or
     * email for the generate-popup autofill.
     *
     * The HR module keeps its profile rows in tblhr_employees keyed by
     * staff_id; both are queried directly (LEFT JOIN) with table_exists guards
     * so this degrades gracefully when the HR module is not installed.
     *
     * @param string $q
     * @return array
     */
    public function search_employees($q)
    {
        $s  = db_prefix() . 'staff';
        $e  = db_prefix() . 'hr_employees';
        $dg = db_prefix() . 'hr_designations';

        $has_hr = $this->db->table_exists($e);

        $this->db->select($s . '.staffid, ' . $s . '.email');
        $this->db->select('CONCAT(' . $s . '.firstname, " ", ' . $s . '.lastname) as name', false);
        if ($has_hr) {
            $this->db->select($e . '.employee_code');
            $this->db->select($dg . '.name as designation');
        }
        $this->db->from($s);
        if ($has_hr) {
            $this->db->join($e, $e . '.staff_id = ' . $s . '.staffid', 'left');
            $this->db->join($dg, $dg . '.id = ' . $e . '.designation_id', 'left');
        }
        $this->db->where($s . '.is_not_staff', 0);
        $this->db->group_start();
        $this->db->like($s . '.firstname', $q);
        $this->db->or_like($s . '.lastname', $q);
        $this->db->or_like($s . '.email', $q);
        if ($has_hr) {
            $this->db->or_like($e . '.employee_code', $q);
        }
        $this->db->group_end();
        $this->db->order_by($s . '.firstname', 'asc');
        $this->db->limit(10);

        return $this->db->get()->result_array();
    }

    /**
     * Resolve the employee_* tag values for one employee (staff id).
     *
     * Salary is only exposed to users who can view HR payroll data so the
     * generate popup cannot become a back door around the hr_payroll gate.
     *
     * @param int $staff_id
     * @return array tag => value
     */
    public function get_employee_tag_values($staff_id)
    {
        $staff_id = (int) $staff_id;

        $s  = db_prefix() . 'staff';
        $e  = db_prefix() . 'hr_employees';
        $dg = db_prefix() . 'hr_designations';
        $dp = db_prefix() . 'departments';

        $has_hr = $this->db->table_exists($e);
        // Aadhaar arrived in a later HR schema version - only select it when the
        // column is actually there so an older HR install keeps working.
        $has_aadhaar = $has_hr && $this->db->field_exists('aadhaar_number', $e);

        $this->db->select($s . '.staffid, ' . $s . '.firstname, ' . $s . '.lastname, ' . $s . '.email, ' . $s . '.phonenumber');
        if ($has_aadhaar) {
            $this->db->select($e . '.aadhaar_number');
        }
        if ($has_hr) {
            $this->db->select($e . '.employee_code, ' . $e . '.employment_type, ' . $e . '.work_location, '
                . $e . '.date_of_joining, ' . $e . '.date_of_birth, ' . $e . '.gender, ' . $e . '.blood_group, '
                . $e . '.father_name, ' . $e . '.qualifications, ' . $e . '.present_address, ' . $e . '.permanent_address, '
                . $e . '.national_id, ' . $e . '.pan_number, ' . $e . '.bank_name, ' . $e . '.bank_account_no, '
                . $e . '.bank_ifsc, ' . $e . '.pf_uan, ' . $e . '.esi_number, ' . $e . '.basic_salary, '
                . $e . '.emergency_contact_name, ' . $e . '.emergency_contact_phone, ' . $e . '.reporting_to');
            $this->db->select($dg . '.name as designation');
            $this->db->select($dp . '.name as department');
            $this->db->select('(SELECT CONCAT(rs.firstname, " ", rs.lastname) FROM ' . $s . ' rs WHERE rs.staffid = ' . $e . '.reporting_to) as reporting_to_name', false);
        }
        $this->db->from($s);
        if ($has_hr) {
            $this->db->join($e, $e . '.staff_id = ' . $s . '.staffid', 'left');
            $this->db->join($dg, $dg . '.id = ' . $e . '.designation_id', 'left');
            $this->db->join($dp, $dp . '.departmentid = ' . $e . '.department_id', 'left');
        }
        $this->db->where($s . '.staffid', $staff_id);

        $emp = $this->db->get()->row();
        if (!$emp) {
            return [];
        }

        $name = trim($emp->firstname . ' ' . $emp->lastname);

        $emergency = '';
        if ($has_hr) {
            $emergency = trim((string) $emp->emergency_contact_name);
            if (!empty($emp->emergency_contact_phone)) {
                $emergency = trim($emergency . ' (' . $emp->emergency_contact_phone . ')');
            }
        }

        $values = [
            'employee_name'       => $name,
            'employee_email'      => (string) $emp->email,
            'employee_mobile'     => (string) $emp->phonenumber,
            'employee_photo_url'  => staff_profile_image_url($staff_id),
        ];

        if ($has_hr) {
            $values['employee_code']              = (string) $emp->employee_code;
            $values['employee_designation']       = (string) $emp->designation;
            $values['employee_department']        = (string) $emp->department;
            $values['employee_doj']               = !empty($emp->date_of_joining) ? _d($emp->date_of_joining) : '';
            $values['employee_dob']               = !empty($emp->date_of_birth) ? _d($emp->date_of_birth) : '';
            $values['employee_gender']            = $emp->gender ? ucfirst($emp->gender) : '';
            $values['employee_blood_group']       = (string) $emp->blood_group;
            $values['employee_father_name']       = (string) $emp->father_name;
            $values['employee_qualifications']    = (string) $emp->qualifications;
            $values['employee_employment_type']   = $emp->employment_type ? ucwords(str_replace('_', ' ', $emp->employment_type)) : '';
            $values['employee_work_location']     = (string) $emp->work_location;
            $values['employee_address']           = (string) $emp->present_address;
            $values['employee_permanent_address'] = (string) $emp->permanent_address;
            $values['employee_national_id']       = (string) $emp->national_id;
            $values['employee_aadhaar']           = $has_aadhaar && function_exists('hr_format_aadhaar')
                ? hr_format_aadhaar($emp->aadhaar_number) : ($has_aadhaar ? (string) $emp->aadhaar_number : '');
            $values['employee_pan']               = (string) $emp->pan_number;
            $values['employee_bank_name']         = (string) $emp->bank_name;
            $values['employee_bank_account']      = (string) $emp->bank_account_no;
            $values['employee_bank_ifsc']         = (string) $emp->bank_ifsc;
            $values['employee_pf_uan']            = (string) $emp->pf_uan;
            $values['employee_esi']               = (string) $emp->esi_number;
            $values['employee_emergency_contact'] = $emergency;
            $values['employee_reporting_to']      = (string) $emp->reporting_to_name;
        }

        // Salary is sensitive - only fill it for users allowed to see payroll.
        if (has_permission('hr_payroll', '', 'view') || is_admin()) {
            $values['employee_basic_salary'] = ($has_hr && $emp->basic_salary !== null) ? (string) $emp->basic_salary : '';
        }

        return $values;
    }

    /* =====================================================================
     * "Who is generating" auto-fill
     *
     * Marketing / sales style templates name their placeholders after the ROLE
     * rather than the CRM entity: {{AgentName}}, {{AgentPhoto}},
     * {{AgentDesignation}}, {{HospitalName}}... They are still ordinary
     * editable inputs, but there is no reason to type them by hand — the agent
     * IS the logged-in staff member and the hospital IS this tenant. The map
     * below resolves those names (matched by tag OR label, canonical key) so
     * the generate popup opens pre-filled and the user only overrides when the
     * document is for somebody else.
     * ===================================================================== */

    /**
     * The recommended spellings of the auto-filled role tags, for the editor's
     * Smart Tags panel. Many synonyms resolve to the same value (see
     * autofill_values()); these are the ones worth advertising.
     *
     * @return array tag names
     */
    public function agent_tag_names()
    {
        return [
            'agent_name',
            'agent_photo',
            'agent_designation',
            'agent_department',
            'agent_phone',
            'agent_email',
            'hospital_name',
            'hospital_address',
            'hospital_phone',
            'hospital_logo',
            'today_date',
        ];
    }

    /**
     * Auto-fill suggestions for one staff member (default: the logged-in user)
     * and this tenant's company, keyed by canonical tag key.
     *
     * Only names that unambiguously mean "the person generating" or "our
     * organisation" are listed. Ambiguous ones ({{name}}, {{photo}},
     * {{address}}) are deliberately left out: they usually belong to the
     * patient / lead the document is ABOUT, and a wrong pre-fill on a printed
     * document is worse than an empty box.
     *
     * @param int|null $staff_id
     * @return array key => value
     */
    public function autofill_values($staff_id = null)
    {
        $staff_id = $staff_id === null ? get_staff_user_id() : (int) $staff_id;

        $values = [];
        $add    = function (array $names, $value) use (&$values) {
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            foreach ($names as $name) {
                $key = $this->tag_key($name);
                if ($key !== '' && !isset($values[$key])) {
                    $values[$key] = $value;
                }
            }
        };

        /* ---- The agent = the staff member generating the document ---- */
        $staff = $staff_id ? $this->get_employee_tag_values($staff_id) : [];

        $add([
            'agent', 'agent_name', 'executive', 'executive_name', 'counsellor_name', 'counselor_name',
            'consultant_name', 'representative_name', 'rep_name', 'salesperson_name', 'sales_person_name',
            'sales_executive', 'advisor_name', 'coordinator_name', 'staff_name', 'prepared_by',
            'prepared_by_name', 'issued_by', 'generated_by', 'created_by',
        ], $staff_id ? get_staff_full_name($staff_id) : '');

        $add([
            'agent_photo', 'agent_photo_url', 'agent_image', 'agent_image_url', 'agent_pic',
            'agent_picture', 'agent_avatar', 'staff_photo_url', 'staff_image', 'staff_picture',
            'executive_photo', 'counsellor_photo', 'consultant_photo', 'prepared_by_photo',
        ], $this->staff_photo_url($staff_id));

        $add([
            'agent_designation', 'agent_role', 'agent_position', 'agent_title', 'staff_designation',
            'staff_role', 'executive_designation', 'consultant_designation', 'prepared_by_designation',
        ], isset($staff['employee_designation']) ? $staff['employee_designation'] : '');

        $add([
            'agent_department', 'staff_department', 'executive_department',
        ], isset($staff['employee_department']) ? $staff['employee_department'] : '');

        $add([
            'agent_phone', 'agent_mobile', 'agent_contact', 'agent_contact_number', 'agent_number',
            'agent_phone_number', 'agent_whatsapp', 'staff_phone', 'staff_mobile', 'executive_phone',
            'consultant_phone',
        ], isset($staff['employee_mobile']) ? $staff['employee_mobile'] : '');

        $add([
            'agent_email', 'agent_email_address', 'agent_mail', 'staff_email', 'executive_email',
            'consultant_email',
        ], isset($staff['employee_email']) ? $staff['employee_email'] : '');

        $add([
            'agent_code', 'agent_id', 'staff_code',
        ], isset($staff['employee_code']) ? $staff['employee_code'] : '');

        /* ---- The organisation = this tenant ---- */
        $system = $this->get_system_tags('');

        $add([
            'hospital', 'hospital_name', 'clinic', 'clinic_name', 'company', 'company_name',
            'organization', 'organization_name', 'organisation', 'organisation_name', 'center_name',
            'centre_name', 'institute_name', 'institution_name', 'business_name', 'brand_name',
            'lab_name', 'practice_name',
        ], $system['clinic_name']);

        $add([
            'hospital_address', 'clinic_address', 'company_address', 'center_address', 'centre_address',
            'office_address', 'organization_address', 'organisation_address',
        ], $system['clinic_address']);

        $add([
            'hospital_phone', 'hospital_contact', 'hospital_number', 'clinic_phone', 'clinic_contact',
            'company_phone', 'company_contact', 'office_phone',
        ], $system['clinic_phone']);

        $add([
            'hospital_logo', 'hospital_logo_url', 'clinic_logo', 'clinic_logo_url', 'company_logo',
            'company_logo_url', 'brand_logo_url', 'logo', 'logo_url',
        ], $this->branding_logo_url());

        $add([
            'hospital_tagline', 'clinic_tagline', 'company_tagline', 'tagline', 'slogan',
        ], $system['brand_tagline']);

        /* ---- Dates ---- */
        $add([
            'date', 'today', 'today_date', 'todays_date', 'print_date', 'generated_date',
            'issue_date', 'issued_on', 'document_date',
        ], $system['current_date']);

        return $values;
    }

    /**
     * The auto-fill value for one field, matched by its tag first and then by
     * its label — so {{AgentName}} and a tag named {{a1}} labelled "Agent Name"
     * both resolve. '' when nothing sensible can be suggested.
     *
     * @param array $field
     * @param array|null $autofill result of autofill_values() (built when omitted)
     * @return string
     */
    public function field_autofill_value($field, $autofill = null)
    {
        $autofill = $autofill === null ? $this->autofill_values() : $autofill;

        $value = '';
        foreach ([isset($field['tag']) ? $field['tag'] : '', isset($field['label']) ? $field['label'] : ''] as $candidate) {
            $key = $this->tag_key($candidate);
            if ($key !== '' && isset($autofill[$key])) {
                $value = $autofill[$key];
                break;
            }
        }

        // The map carries dates in the tenant's display format (that is what a
        // text field should show), but a `date` input only accepts ISO — and it
        // silently discards anything else. merge() formats it back with _d().
        if ($value !== '' && isset($field['type']) && $field['type'] === 'date' && $value === _d(date('Y-m-d'))) {
            return date('Y-m-d');
        }

        return $value;
    }

    /* =====================================================================
     * Tag identity
     *
     * The same placeholder is rarely spelled the same way twice across a
     * document library: {{patient_name}}, {{Patient Name}}, {{PATIENT-NAME}}
     * and {{ patientName }} all mean one thing. Everything downstream —
     * detection, the setup rows, the generate popup, autofill and the final
     * merge — therefore keys off tag_key() instead of the literal spelling, so
     * one input fills every variant in the document.
     * ===================================================================== */

    /**
     * Canonical identity of a {{tag}} name: lowercase, letters and digits only.
     *
     * @param string $tag
     * @return string '' when the name carries no usable characters
     */
    public function tag_key($tag)
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $tag)));
    }

    /**
     * key => canonical name for every smart tag the module knows (system,
     * branding, patient, employee). A detected variant whose key is in here
     * adopts the official name, so {{Patient Name}} behaves exactly like
     * {{patient_name}} — including the patient/employee autofill.
     *
     * @return array
     */
    public function canonical_tag_map()
    {
        if ($this->canonical_map === null) {
            $map = [];
            foreach (array_merge($this->system_tag_names(), $this->patient_tag_names(), $this->employee_tag_names()) as $name) {
                $key = $this->tag_key($name);
                if ($key !== '') {
                    $map[$key] = $name;
                }
            }
            $this->canonical_map = $map;
        }

        return $this->canonical_map;
    }

    /**
     * Group the {{tag}} placeholders found in HTML content by canonical key.
     *
     * Double curly braces are used so CSS blocks in imported HTML files
     * never collide with the tag syntax.
     *
     * @param string $html
     * @return array key => ['tag' => primary name, 'aliases' => other spellings, 'count' => occurrences]
     *               in order of first appearance
     */
    public function extract_tag_groups($html)
    {
        $groups = [];

        if (empty($html)) {
            return $groups;
        }

        $canonical = $this->canonical_tag_map();

        if (preg_match_all('/\{\{\s*([A-Za-z0-9_][A-Za-z0-9_\- ]*?)\s*\}\}/', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $key = $this->tag_key($raw);
                if ($key === '') {
                    continue;
                }

                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        // A known smart tag always wins the group name so the
                        // autofill and system-tag lookups recognise it.
                        'tag'     => isset($canonical[$key]) ? $canonical[$key] : $raw,
                        'aliases' => [],
                        'count'   => 0,
                    ];
                }

                $groups[$key]['count']++;

                if ($raw !== $groups[$key]['tag'] && !in_array($raw, $groups[$key]['aliases'], true)) {
                    $groups[$key]['aliases'][] = $raw;
                }
            }
        }

        return $groups;
    }

    /**
     * Distinct tags in HTML content, one per canonical key.
     *
     * @param string $html
     * @return array of tag names in order of appearance
     */
    public function extract_tags($html)
    {
        $tags = [];
        foreach ($this->extract_tag_groups($html) as $group) {
            $tags[] = $group['tag'];
        }

        return $tags;
    }

    /**
     * Build the final field configuration for a template.
     *
     * The submitted order is preserved (fields can be re-ordered in the
     * setup form), newly detected tags are appended, and system tags are
     * excluded because they are filled automatically.
     *
     * Tags that differ only in spelling ({{doctor name}} / {{Doctor_Name}})
     * collapse into a SINGLE field: one input at generate time fills every
     * occurrence of every variant. The extra spellings ride along in the
     * field's `aliases` so the setup screen can show what got merged.
     *
     * @param string $content template HTML
     * @param array $submitted_fields decoded fields JSON from the setup form
     * @return array
     */
    public function sync_fields($content, $submitted_fields)
    {
        $system_keys = [];
        foreach ($this->system_tag_names() as $name) {
            $system_keys[$this->tag_key($name)] = true;
        }

        $groups = $this->extract_tag_groups($content);
        foreach (array_keys($groups) as $key) {
            if (isset($system_keys[$key])) {
                unset($groups[$key]);
            }
        }

        $fields = [];
        $done   = [];

        if (is_array($submitted_fields)) {
            foreach ($submitted_fields as $field) {
                if (!is_array($field) || !isset($field['tag'])) {
                    continue;
                }
                $tag = trim($field['tag']);
                $key = $this->tag_key($tag);
                if (!preg_match('/^[A-Za-z0-9_][A-Za-z0-9_\- ]*$/', $tag)
                    || $key === '' || isset($done[$key]) || isset($system_keys[$key])) {
                    continue;
                }
                if (isset($groups[$key])) {
                    // Keep the user's configuration but adopt the group's
                    // primary name, so a config saved under an old spelling
                    // keeps working after the HTML changes.
                    $fields[]   = $this->normalize_field($groups[$key]['tag'], $field, false, $groups[$key]);
                    $done[$key] = true;
                } elseif (!empty($field['manual'])) {
                    // Manually added tags stay configured even if not (yet)
                    // present in the HTML.
                    $fields[]   = $this->normalize_field($tag, $field, true);
                    $done[$key] = true;
                }
            }
        }

        foreach ($groups as $key => $group) {
            if (!isset($done[$key])) {
                $fields[] = $this->normalize_field($group['tag'], [], false, $group);
            }
        }

        return $fields;
    }

    /**
     * Normalize one field configuration entry.
     *
     * @param string $tag
     * @param array $field submitted config for this tag
     * @param bool $manual user-added tag not detected in the content
     * @param array|null $group detection group (other spellings + occurrences)
     * @return array
     */
    protected function normalize_field($tag, $field, $manual = false, $group = null)
    {
        $type = isset($field['type']) ? $field['type'] : 'text';
        if (!in_array($type, ['text', 'textarea', 'number', 'date', 'time', 'email', 'select'])) {
            $type = 'text';
        }

        // Other spellings of this tag found in the HTML. Purely informational
        // (the merge matches by key, not by this list) but shown in the setup
        // screen so it is obvious which placeholders share one input.
        $aliases = [];
        $source  = is_array($group) && isset($group['aliases']) ? $group['aliases'] : (isset($field['aliases']) ? $field['aliases'] : []);
        if (is_array($source)) {
            foreach ($source as $alias) {
                $alias = trim((string) $alias);
                if ($alias !== '' && $alias !== $tag && preg_match('/^[A-Za-z0-9_][A-Za-z0-9_\- ]*$/', $alias)
                    && !in_array($alias, $aliases, true)) {
                    $aliases[] = $alias;
                }
            }
        }

        return [
            'tag'         => $tag,
            'label'       => (isset($field['label']) && trim($field['label']) !== '') ? trim($field['label']) : ucwords(str_replace(['_', '-'], ' ', $tag)),
            'type'        => $type,
            'options'     => isset($field['options']) ? trim($field['options']) : '',
            'default'     => isset($field['default']) ? $field['default'] : '',
            'required'    => !empty($field['required']) ? 1 : 0,
            'manual'      => $manual ? 1 : 0,
            'ignored'     => !empty($field['ignored']) ? 1 : 0,
            'aliases'     => $aliases,
            'occurrences' => is_array($group) && isset($group['count']) ? (int) $group['count'] : (int) (isset($field['occurrences']) ? $field['occurrences'] : 0),
        ];
    }

    /**
     * Decode the stored fields JSON of a template.
     *
     * @param object $template
     * @return array
     */
    public function get_fields($template)
    {
        if (!$template || empty($template->fields)) {
            return [];
        }

        $fields = json_decode($template->fields, true);

        return is_array($fields) ? $fields : [];
    }

    /**
     * Merge submitted values and system tags into the template content.
     *
     * Values are escaped so free text cannot break the document markup;
     * textarea values keep their line breaks and date values are shown in
     * the application date format.
     *
     * @param object $template
     * @param array $values tag => raw submitted value
     * @param string $document_no value for the {{document_no}} system tag
     * @return string merged HTML
     */
    public function merge($template, $values, $document_no = '')
    {
        $content = $template->content;
        $fields  = $this->get_fields($template);

        // Branding this template actually uses (its override when enabled,
        // otherwise the global profile) — feeds the logo/colour system tags.
        $branding = $this->effective_branding($template);

        // Submitted values may be keyed by any spelling of the tag (an older
        // generation being reused, another module's prefill, a hand-built
        // integration) — index them by canonical key like everything else.
        $submitted = [];
        if (is_array($values)) {
            foreach ($values as $tag => $value) {
                $key = $this->tag_key($tag);
                if ($key !== '') {
                    $submitted[$key] = $value;
                }
            }
        }

        // key => final replacement string. Built once, applied in a single pass
        // so EVERY spelling of a tag resolves to the same value.
        $map = [];
        // Keys holding a picture URL: when such a tag sits in TEXT position
        // instead of inside an <img src="...">, the URL is rendered as an
        // actual image (see replace_tags()).
        $image_keys = [];

        foreach ($fields as $field) {
            // Ignored tags are template artifacts the user dismissed in the
            // setup screen (e.g. {{ showCost }} / {{ true }} that imported
            // design-tool HTML uses for its own component logic). Leave them
            // byte-for-byte intact so the imported document behaves exactly as
            // it does when the file is opened directly - substituting them
            // (even with an empty string) breaks that logic and hides sections.
            if (!empty($field['ignored'])) {
                continue;
            }

            $key = $this->tag_key($field['tag']);
            if ($key === '') {
                continue;
            }

            $value = array_key_exists($key, $submitted) ? $submitted[$key] : '';
            $value = is_scalar($value) ? (string) $value : '';

            $default = isset($field['default']) ? (string) $field['default'] : '';
            if ($value === '' && $default !== '') {
                $value = $default;
            }

            $type = isset($field['type']) ? $field['type'] : 'text';

            if ($type == 'date' && $value !== '') {
                $value = _d($value);
            }

            $value = html_escape($value);

            if ($type == 'textarea') {
                $value = nl2br($value);
            }

            $map[$key] = $value;

            if ($this->is_picture_value($value)
                && ($this->is_picture_tag($field['tag']) || $this->is_picture_tag(isset($field['label']) ? $field['label'] : ''))) {
                $image_keys[$key] = true;
            }
        }

        // System tags win over a field of the same name — they are filled
        // automatically and are never asked for in the setup screen.
        foreach ($this->get_system_tags($document_no, $branding) as $tag => $value) {
            $key       = $this->tag_key($tag);
            $map[$key] = html_escape($value);

            if ($this->is_picture_tag($tag) && $this->is_picture_value($map[$key])) {
                $image_keys[$key] = true;
            }
        }

        // Raw tags ({{brand_header}}, {{brand_logo}}, {{brand_footer}},
        // {{staff_photo}}) are trusted server-built HTML — inserted WITHOUT
        // escaping so the markup renders (user-supplied parts inside were
        // already escaped).
        foreach ($this->raw_tag_values($branding) as $tag => $value) {
            $map[$this->tag_key($tag)] = $value;
        }

        // Raw tags already carry finished markup, so they are never re-wrapped.

        // Leftover {{ ... }} tokens are intentionally preserved. Imported HTML
        // (design-tool exports, brochures, styled forms) uses double-brace
        // tokens for its own component logic - e.g. <sc-if value="{{ showCost }}"
        // hint-placeholder-val="{{ true }}"> wrapping a whole section. Tokens
        // that match nothing in the map are left byte-for-byte intact, because
        // blanking those corrupts the document and makes entire tables /
        // sections disappear in the preview and print. Anything the user really
        // wants filled is a configured field or a system tag and is in the map;
        // genuinely orphaned tokens are rare and harmless left as text.

        return $this->replace_tags($content, $map, $image_keys);
    }

    /**
     * Does this tag/label name a picture? Used to decide whether a bare URL
     * dropped in text position should become an <img>.
     *
     * @param string $name
     * @return bool
     */
    protected function is_picture_tag($name)
    {
        return (bool) preg_match('/(photo|image|img|logo|picture|pic|avatar|favicon|signature|sign|stamp|qr|barcode)/i', (string) $name);
    }

    /**
     * Does this (already escaped) value look like something an <img src> can
     * load? Absolute URL, root-relative path or a data: image.
     *
     * @param string $value
     * @return bool
     */
    protected function is_picture_value($value)
    {
        $value = trim((string) $value);
        if ($value === '' || strpos($value, '<') !== false) {
            return false;
        }

        return (bool) preg_match('#^(https?:)?//#i', $value)
            || strpos($value, 'data:image/') === 0
            || (bool) preg_match('#^/[^/\s]#', $value);
    }

    /**
     * Is the token at $offset in a TEXT position — i.e. between two tags,
     * where a bare URL would print as a string of characters — rather than in
     * an attribute (`src="HERE"`), a CSS `url(HERE)` or a script string?
     *
     * The test is deliberately LOCAL rather than document-wide, because the
     * document is not always plain HTML: design-tool exports ship the real
     * markup as a JSON string inside <script type="…/template">, escaped as
     *   …line-height:1.3\">{{ agentPhoto }}</div>
     * A document-wide "are we inside a <script>?" rule answers "yes" there and
     * misses every placeholder in the actual design — which is exactly what an
     * imported brochure looks like. Neighbouring characters, on the other hand,
     * read the same whether or not the markup is escaped.
     *
     * @param string $content
     * @param int $offset
     * @param int $length length of the {{token}}
     * @return bool
     */
    protected function is_text_position($content, $offset, $length)
    {
        // 1. Wrapped in quotes = a value, not text: alt="{{x}}" in HTML, and
        //    agentPhoto: '{{agent_photo}}' in a script's props.
        $prev = $offset > 0 ? substr($content, $offset - 1, 1) : '';
        $next = substr($content, $offset + $length, 1);
        if ($prev === '"' || $prev === "'" || $next === '"' || $next === "'") {
            return false;
        }

        // 2. CSS url(...) — background:url({{logo}}) must stay a bare URL.
        if (stripos(substr($content, max(0, $offset - 8), min(8, $offset)), 'url(') !== false) {
            return false;
        }

        // 3. Between tags? Nearest angle bracket behind must be a '>' (end of
        //    the opening tag) and the nearest ahead a '<' (start of the next).
        //    A window keeps this cheap on big documents.
        $back   = substr($content, max(0, $offset - self::TAG_CONTEXT_WINDOW), min($offset, self::TAG_CONTEXT_WINDOW));
        $ahead  = substr($content, $offset + $length, self::TAG_CONTEXT_WINDOW);
        $open   = strrpos($back, '<');
        $close  = strrpos($back, '>');
        if ($close === false || ($open !== false && $open > $close)) {
            return false;
        }
        $next_open  = strpos($ahead, '<');
        $next_close = strpos($ahead, '>');
        if ($next_open === false || ($next_close !== false && $next_close < $next_open)) {
            return false;
        }

        // 4. Inside a stylesheet. In a serialised payload the closing tag is
        //    escaped (<\/style>, </style>), so match those spellings too.
        $style_open = strripos($back, '<style');
        if ($style_open !== false) {
            $style_close = -1;
            foreach (['</style', '<\\/style', '<\\u002Fstyle'] as $needle) {
                $at = strripos($back, $needle);
                if ($at !== false && $at > $style_close) {
                    $style_close = $at;
                }
            }
            if ($style_close < $style_open) {
                return false;
            }
        }

        return true;
    }

    /**
     * The <img> used when a picture tag is merged in text position. Sized to
     * fit whatever box the template put it in, and it inherits the container's
     * rounding so a circular avatar frame stays circular.
     *
     * Attribute values are written UNQUOTED (valid HTML5) so the tag can also
     * be dropped inside a JS/JSON string payload, where a `"` would terminate
     * the string and take the whole bundled document down with it. When the URL
     * cannot be written unquoted, quoted markup is used instead — but only if
     * the surrounding text is not such an escaped payload.
     *
     * @param string $url already escaped
     * @param bool $escaped_payload the token sits inside a JS/JSON string
     * @return string|null null = cannot inject safely here
     */
    protected function auto_image_tag($url, $escaped_payload = false)
    {
        $style = 'max-width:100%;max-height:100%;object-fit:cover;border-radius:inherit';

        // HTML5 unquoted attribute values may not contain whitespace, quotes,
        // =, <, > or a backtick.
        if (preg_match('/^[^\s"\'<>=`]+$/', $url)) {
            // No alt="": even an empty quoted value would break a string payload.
            return '<img src=' . $url . ' class=spdf-auto-img style=' . $style . '>';
        }

        if ($escaped_payload) {
            return null;
        }

        return '<img src="' . $url . '" alt="" class="spdf-auto-img" style="' . $style . ';">';
    }

    /**
     * Does the text around $offset look like escaped markup inside a string
     * (\" attributes, / closings)? Such a payload cannot take a raw quote.
     *
     * @param string $content
     * @param int $offset
     * @return bool
     */
    protected function in_escaped_payload($content, $offset)
    {
        $window = substr($content, max(0, $offset - self::TAG_CONTEXT_WINDOW), min($offset, self::TAG_CONTEXT_WINDOW));

        return strpos($window, '\\"') !== false || stripos($window, '\\u002F') !== false;
    }

    /**
     * Merge a template with sample values for live previews / thumbnails:
     * field defaults where configured, [Label] otherwise, real system tags.
     *
     * @param object $template
     * @return string merged HTML
     */
    public function sample_merge($template)
    {
        $values = [];
        foreach ($this->get_fields($template) as $field) {
            $values[$field['tag']] = $field['default'] !== '' ? $field['default'] : '[' . $field['label'] . ']';
        }

        return $this->merge($template, $values, _l('smart_pdf_preview_document_no'));
    }

    /**
     * Replace every known {{tag}} in the content in ONE pass, matching each
     * token by canonical key — so {{doctor_name}}, {{Doctor Name}} and
     * {{DOCTOR-NAME}} all receive the same value. Tokens with no entry in the
     * map are returned untouched (see the note in merge()).
     *
     * Rebuilt manually from offset captures (rather than
     * preg_replace_callback) so $ and \ inside the values stay literal AND so
     * each hit knows WHERE it sits: a picture URL landing in text position is
     * turned into an <img>, while the same tag inside `src="..."` stays a
     * plain URL.
     *
     * @param string $content
     * @param array $map canonical key => replacement (already escaped as needed)
     * @param array $image_keys keys whose value is a picture URL
     * @return string
     */
    protected function replace_tags($content, $map, $image_keys = [])
    {
        if (empty($map) || $content === '' || $content === null) {
            return $content;
        }

        if (!preg_match_all('/\{\{\s*([A-Za-z0-9_][A-Za-z0-9_\- ]*?)\s*\}\}/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $out    = '';
        $cursor = 0;

        foreach ($matches[0] as $i => $match) {
            $token  = $match[0];
            $offset = $match[1];
            $key    = $this->tag_key($matches[1][$i][0]);

            if (!array_key_exists($key, $map)) {
                continue; // unknown token: leave it byte-for-byte intact
            }

            $value = $map[$key];

            if ($value !== '' && isset($image_keys[$key])
                && $this->is_text_position($content, $offset, strlen($token))) {
                $img = $this->auto_image_tag($value, $this->in_escaped_payload($content, $offset));
                if ($img !== null) {
                    $value = $img;
                }
            }

            $out .= substr($content, $cursor, $offset - $cursor) . $value;
            $cursor = $offset + strlen($token);
        }

        return $out . substr($content, $cursor);
    }

    /**
     * Replace one {{tag}} (and every other spelling of it) in the content.
     *
     * @param string $content
     * @param string $tag
     * @param string $value already escaped
     * @return string
     */
    protected function replace_tag($content, $tag, $value)
    {
        $key = $this->tag_key($tag);

        return $key === '' ? $content : $this->replace_tags($content, [$key => $value]);
    }

    /**
     * Log one generation for the audit history.
     *
     * @param int $template_id
     * @param array $values tag => value as submitted
     * @param string $mode print|pdf
     * @param int|null $patient_id
     * @param int|null $employee_id staff id the document was generated for
     * @return int generation id
     */
    public function add_generation($template_id, $values, $mode, $patient_id = null, $employee_id = null)
    {
        $this->db->insert(db_prefix() . 'smart_pdf_generations', [
            'template_id' => $template_id,
            'staff_id'    => get_staff_user_id(),
            'patient_id'  => $patient_id ?: null,
            'employee_id' => $employee_id ?: null,
            'mode'        => $mode == 'pdf' ? 'pdf' : 'print',
            'values'      => json_encode($values, JSON_UNESCAPED_UNICODE),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    /**
     * Generation history of one template, newest first.
     *
     * @param int $template_id
     * @param int $limit
     * @return array
     */
    public function get_generations($template_id, $limit = 50)
    {
        $g = db_prefix() . 'smart_pdf_generations';
        $s = db_prefix() . 'staff';
        $c = db_prefix() . 'clients';

        $this->db->select($g . '.id, ' . $g . '.mode, ' . $g . '.created_at, ' . $g . '.patient_id, ' . $g . '.employee_id, ' . $g . '.staff_id');
        $this->db->select('CONCAT(' . $s . '.firstname, " ", ' . $s . '.lastname) as staff_name', false);
        $this->db->select($c . '.company as patient_name');
        $this->db->select('CONCAT(emp.firstname, " ", emp.lastname) as employee_name', false);
        $this->db->from($g);
        $this->db->join($s, $s . '.staffid = ' . $g . '.staff_id', 'left');
        $this->db->join($c, $c . '.userid = ' . $g . '.patient_id', 'left');
        $this->db->join($s . ' emp', 'emp.staffid = ' . $g . '.employee_id', 'left');
        $this->db->where($g . '.template_id', (int) $template_id);
        $this->db->order_by($g . '.id', 'desc');
        $this->db->limit($limit);

        return $this->db->get()->result_array();
    }

    /**
     * Get one generation row (for the Reuse action).
     *
     * @param int $id
     * @return object|null
     */
    public function get_generation($id)
    {
        return $this->db->where('id', (int) $id)
            ->get(db_prefix() . 'smart_pdf_generations')
            ->row();
    }
}
