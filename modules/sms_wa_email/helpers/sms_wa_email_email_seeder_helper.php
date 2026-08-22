<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ═══════════════════════════════════════════════════════════════
 *  Omni Messaging — default EMAIL template seeder (hook-wise)
 * ═══════════════════════════════════════════════════════════════
 *
 * Fills the Email channel with one ready-to-use template per registered CCX
 * hook, so the Hooks panel is not a list of events with nothing to send.
 *
 * Three rules the whole file exists to enforce:
 *
 *   1. NOTHING IS DUPLICATED. A hook that already has an e-mail template
 *      mapped keeps it, untouched. A template whose title already matches the
 *      pack is reused as-is (so pressing the button twice is a no-op) and
 *      simply gets its mapping if it was missing one.
 *   2. EXISTING WORDING WINS. When the pack names a core template
 *      (Setup → Email Templates) and that row exists on this installation,
 *      its own subject and message are imported instead of ours — the admin
 *      may well have edited it. Core merge fields the hook cannot resolve are
 *      rewritten first (see 'core_map' in the pack); if anything unresolvable
 *      is left the import is abandoned rather than shipping a literal {tag}.
 *   3. NOTHING STARTS SENDING BY ITSELF. Seeded hook mappings are always
 *      written with active = 0. The admin turns on the ones they want from
 *      the Hooks panel.
 *
 * Entry points:
 *   sms_wa_email_seed_hook_email_templates()      — do it (returns a report)
 *   sms_wa_email_validate_hook_email_seed_pack()  — dev sanity check on tags
 */

/**
 * Load the seed pack.
 *
 * @return array hook_key => entry (see seeds/hook_email_templates.php)
 */
function sms_wa_email_hook_email_seed_pack()
{
    static $pack = null;

    if ($pack === null) {
        $file = __DIR__ . '/../seeds/hook_email_templates.php';
        $pack = is_file($file) ? include $file : [];
        if (!is_array($pack)) {
            $pack = [];
        }
    }

    return $pack;
}

/**
 * Variables a template for this hook may legally use: the hook's own list plus
 * the ones ccx_fire_hook() injects for every hook.
 *
 * @param  array $hook registry entry
 * @return array flat list of variable names
 */
function sms_wa_email_hook_allowed_tags($hook)
{
    $vars = isset($hook['variables']) ? (array) $hook['variables'] : [];

    // Injected by ccx_fire_hook()/_ccx_resolve_recipients() regardless of hook
    return array_unique(array_merge($vars, ['company', 'companyname', 'recipient_name', 'all_recipients']));
}

/**
 * Every {tag} in a string that this hook cannot resolve.
 *
 * @param  string $text
 * @param  array  $allowed_tags
 * @return array  unresolvable tag names
 */
function sms_wa_email_unresolvable_tags($text, $allowed_tags)
{
    preg_match_all('/\{([a-z0-9_]+)\}/i', (string) $text, $m);

    return array_values(array_unique(array_diff($m[1], $allowed_tags)));
}

/**
 * Try to reuse a core e-mail template (Setup → Email Templates) for a hook.
 *
 * Core templates speak their own merge fields, so the entry's 'core_map' is
 * applied first ({email_signature} → {companyname}, {ticket_public_url} →
 * {ticket_url}, …). If a tag the hook cannot resolve survives that rewrite,
 * null is returned and the caller falls back to the pack's own copy — a
 * half-resolved e-mail reaching a patient is worse than our wording.
 *
 * @param  array $entry        pack entry (needs core_slug, optional core_map)
 * @param  array $allowed_tags tags this hook can resolve
 * @return array|null          ['subject' => .., 'content' => .., 'name' => ..]
 */
function sms_wa_email_import_core_email_template($entry, $allowed_tags)
{
    if (empty($entry['core_slug'])) {
        return null;
    }

    $CI    = &get_instance();
    $table = db_prefix() . 'emailtemplates';

    if (!$CI->db->table_exists($table)) {
        return null;
    }

    // Prefer the row for the installation's own language, fall back to English
    // then to whatever exists — tenants run Perfex in many languages.
    $rows = $CI->db->where('slug', $entry['core_slug'])->get($table)->result();
    if (empty($rows)) {
        return null;
    }

    $locale = function_exists('get_option') ? (string) get_option('active_language') : '';
    $pick   = null;
    foreach ([$locale, 'english', ''] as $lang) {
        foreach ($rows as $row) {
            if ($lang === '' || strtolower((string) $row->language) === strtolower($lang)) {
                $pick = $row;
                break 2;
            }
        }
    }
    if (!$pick) {
        return null;
    }

    $subject = (string) $pick->subject;
    $content = (string) $pick->message;

    if (trim($content) === '') {
        return null;
    }

    // Core stores the body HTML-escaped when XSS filtering was on at save time
    if (stripos($content, '&lt;') !== false && stripos($content, '<') === false) {
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    }

    $map = isset($entry['core_map']) ? (array) $entry['core_map'] : [];
    foreach ($map as $core_tag => $replacement) {
        $subject = str_replace('{' . $core_tag . '}', $replacement, $subject);
        $content = str_replace('{' . $core_tag . '}', $replacement, $content);
    }

    if (sms_wa_email_unresolvable_tags($subject . ' ' . $content, $allowed_tags)) {
        return null;
    }

    return [
        'subject' => $subject !== '' ? $subject : $entry['subject'],
        'content' => $content,
        'name'    => (string) $pick->name,
    ];
}

/**
 * Seed / repair the Email channel's hook templates.
 *
 * @param  array $options
 *         create_mappings  bool  also map each template to its hook (inactive). Default true.
 *         prefer_core      bool  reuse a matching core e-mail template when one exists. Default true.
 * @return array report: created, reused, imported, mapped, skipped[], total_hooks
 */
function sms_wa_email_seed_hook_email_templates($options = [])
{
    $CI = &get_instance();

    $create_mappings = !isset($options['create_mappings']) || (bool) $options['create_mappings'];
    $prefer_core     = !isset($options['prefer_core']) || (bool) $options['prefer_core'];

    $tpl_table = db_prefix() . 'sms_wa_email_templates';
    $map_table = db_prefix() . 'ccx_hook_template_map';

    // Both tables are created by install.php; a fresh tenant can be missing
    // them if the module was activated before this file existed.
    if (!$CI->db->table_exists($tpl_table) || !$CI->db->table_exists($map_table)) {
        $installer = module_dir_path('sms_wa_email', 'install.php');
        if (is_file($installer)) {
            require_once($installer);
        }
    }
    if (!$CI->db->table_exists($tpl_table)) {
        return ['error' => 'The Omni Messaging templates table does not exist on this installation.'];
    }

    if (!function_exists('ccx_get_registered_hooks')) {
        $CI->load->helper('ccx_hooks_registry');
    }

    $hooks = function_exists('ccx_get_registered_hooks') ? ccx_get_registered_hooks() : [];
    $pack  = sms_wa_email_hook_email_seed_pack();

    $report = [
        'created'     => 0,
        'reused'      => 0,
        'imported'    => 0,
        'mapped'      => 0,
        'skipped'     => [],
        'total_hooks' => count($hooks),
        'details'     => [],
    ];

    if (empty($hooks) || empty($pack)) {
        return $report;
    }

    // Existing email templates by lower-cased title, for idempotency
    $existing_by_title = [];
    $email_template_count = 0;
    foreach ($CI->db->where('type', 'email')->get($tpl_table)->result() as $row) {
        $existing_by_title[mb_strtolower(trim($row->title))] = $row;
        $email_template_count++;
    }

    // Hooks that already have an email mapping keep whatever they point at
    $mapped_hooks = [];
    if ($CI->db->table_exists($map_table)) {
        foreach ($CI->db->where('channel', 'email')->get($map_table)->result() as $row) {
            $mapped_hooks[$row->hook_key] = true;
        }
    }

    $from_name = (string) get_option('companyname');
    $now       = date('Y-m-d H:i:s');

    foreach ($hooks as $hook) {
        $key = $hook['hook_key'];

        if (!isset($pack[$key])) {
            // A hook was added to the registry without a template in the pack
            $report['skipped'][] = $key . ' (no template in the seed pack)';
            continue;
        }

        $entry        = $pack[$key];
        $allowed_tags = sms_wa_email_hook_allowed_tags($hook);
        $title_key    = mb_strtolower(trim($entry['title']));

        // ── 1. Already mapped → leave the admin's setup completely alone ──
        if (isset($mapped_hooks[$key])) {
            $report['reused']++;
            $report['details'][$key] = ['action' => 'kept', 'title' => $entry['title']];
            continue;
        }

        // ── 2. Template with this title already exists → reuse that row ──
        if (isset($existing_by_title[$title_key])) {
            $template_id = (int) $existing_by_title[$title_key]->id;
            $report['reused']++;
            $report['details'][$key] = ['action' => 'reused', 'title' => $entry['title']];
        } else {
            // ── 3. Create it — core wording first, our copy otherwise ──
            $subject = $entry['subject'];
            $content = $entry['content'];
            $source  = 'pack';

            if ($prefer_core) {
                $core = sms_wa_email_import_core_email_template($entry, $allowed_tags);
                if ($core) {
                    $subject = $core['subject'];
                    $content = $core['content'];
                    $source  = 'core';
                }
            }

            $insert = [
                'type'            => 'email',
                'message_subtype' => 'transactional',
                'title'           => $entry['title'],
                'subject'         => $subject,
                'from_name'       => $from_name,
                'content'         => $content,
                'is_attachment'   => 0,
                'active'          => 1,
                // Mirror save_template(): the very first template of a channel
                // becomes its default.
                'is_default'      => $email_template_count === 0 ? 1 : 0,
                'created_at'      => $now,
            ];

            $CI->db->insert($tpl_table, $insert);
            $template_id = (int) $CI->db->insert_id();
            if (!$template_id) {
                $report['skipped'][] = $key . ' (template row could not be inserted)';
                continue;
            }

            $email_template_count++;
            $existing_by_title[$title_key] = (object) ['id' => $template_id];
            $report['created']++;
            if ($source === 'core') {
                $report['imported']++;
            }
            $report['details'][$key] = [
                'action' => 'created',
                'title'  => $entry['title'],
                'source' => $source,
            ];
        }

        // ── 4. Map it to the hook, switched OFF ──
        if (!$create_mappings || empty($entry['recipient']) || !$CI->db->table_exists($map_table)) {
            continue;
        }

        $recipient_type  = isset($entry['recipient']['type']) ? $entry['recipient']['type'] : 'patient';
        $recipient_value = $recipient_type === 'patient'
            ? null
            : (isset($entry['recipient']['value']) ? $entry['recipient']['value'] : null);

        $CI->db->insert($map_table, [
            'hook_key'        => $key,
            'channel'         => 'email',
            'template_id'     => $template_id,
            'recipient_type'  => $recipient_type,
            'recipient_value' => $recipient_value,
            // Deliberately inactive — see the file header.
            'active'          => 0,
            'created_at'      => $now,
        ]);

        $mapped_hooks[$key] = true;
        $report['mapped']++;
    }

    return $report;
}

/**
 * Dev sanity check: assert that every pack entry belongs to a registered hook
 * and only uses tags that hook can actually resolve. Surfaced by the Hook
 * Debugger so a new hook variable rename cannot silently ship a literal {tag}.
 *
 * @return array problems (empty array = pack is clean)
 */
function sms_wa_email_validate_hook_email_seed_pack()
{
    if (!function_exists('ccx_get_registered_hooks')) {
        get_instance()->load->helper('ccx_hooks_registry');
    }

    $hooks    = function_exists('ccx_get_registered_hooks') ? ccx_get_registered_hooks() : [];
    $pack     = sms_wa_email_hook_email_seed_pack();
    $problems = [];
    $by_key   = [];

    foreach ($hooks as $hook) {
        $by_key[$hook['hook_key']] = $hook;
    }

    foreach ($pack as $key => $entry) {
        if (!isset($by_key[$key])) {
            // Gated hooks (requires_module) legitimately disappear when their
            // module is off — that is not a pack error.
            continue;
        }

        foreach (['title', 'subject', 'content'] as $field) {
            if (empty($entry[$field])) {
                $problems[] = $key . ': missing ' . $field;
            }
        }

        $allowed = sms_wa_email_hook_allowed_tags($by_key[$key]);
        $unknown = sms_wa_email_unresolvable_tags(
            (isset($entry['subject']) ? $entry['subject'] : '') . ' ' . (isset($entry['content']) ? $entry['content'] : ''),
            $allowed
        );
        if ($unknown) {
            $problems[] = $key . ': unknown tag(s) {' . implode('}, {', $unknown) . '}';
        }

        if (!empty($entry['recipient']['value'])
            && preg_match('/^\{(.+)\}$/', $entry['recipient']['value'], $m)
            && !in_array($m[1], $allowed, true)) {
            $problems[] = $key . ': recipient tag {' . $m[1] . '} is not a variable of this hook';
        }
    }

    foreach ($by_key as $key => $hook) {
        if (!isset($pack[$key])) {
            $problems[] = $key . ': registered hook has no template in the seed pack';
        }
    }

    return $problems;
}
