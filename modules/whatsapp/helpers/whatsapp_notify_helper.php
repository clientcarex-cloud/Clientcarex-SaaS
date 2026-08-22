<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WhatsApp — inbox arrival notifications.
 *
 * Two independent channels, both driven by the same tenant option
 * (`whatsapp_notify_settings`):
 *
 *   BELL     server side. Every inbound message that survives the throttle
 *            writes a Perfex in-app notification (+ pusher) for each staff
 *            member allowed to open the Inbox tab. Written from the webhook /
 *            ingest request, i.e. in TENANT context — see _ingest_inbound().
 *
 *   BROWSER  client side. views/_notify_script.php is injected on EVERY admin
 *            page and polls whatsapp/unread_inbox, raising a toast, a desktop
 *            notification and a chime for anything it has not seen yet.
 *
 * The bell is throttled per contact so a customer typing five lines in a row
 * does not bury the notification dropdown; the browser channel is diffed by
 * message id instead, so the inbox still reacts to every single message.
 */

if (!function_exists('whatsapp_notify_defaults')) {
    function whatsapp_notify_defaults()
    {
        return [
            'enabled'     => 1,       // master switch for both channels
            'toast'       => 1,       // in-page toast card
            'desktop'     => 1,       // browser / OS notification
            'sound'       => 1,       // chime on arrival
            'bell'        => 1,       // Perfex notification dropdown (+ pusher)
            'recipients'  => 'inbox', // inbox = everyone holding the Inbox capability
            'staff'       => [],      // explicit staff ids when recipients = selected
            'throttle'    => 90,      // seconds between bell entries for one contact
        ];
    }
}

if (!function_exists('whatsapp_notify_settings')) {
    /** Stored settings merged over the defaults. */
    function whatsapp_notify_settings()
    {
        $raw = get_option('whatsapp_notify_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $out   = array_merge(whatsapp_notify_defaults(), is_array($saved) ? $saved : []);

        foreach (['enabled', 'toast', 'desktop', 'sound', 'bell'] as $flag) {
            $out[$flag] = !empty($out[$flag]) ? 1 : 0;
        }
        $out['recipients'] = $out['recipients'] === 'selected' ? 'selected' : 'inbox';
        $out['staff']      = array_values(array_unique(array_map('intval', (array) $out['staff'])));
        // A throttle under 10s defeats the purpose; above an hour it looks broken.
        $out['throttle']   = min(3600, max(10, (int) $out['throttle']));

        return $out;
    }
}

if (!function_exists('whatsapp_notify_save_settings')) {
    function whatsapp_notify_save_settings($data)
    {
        $clean = [
            'enabled'    => !empty($data['enabled']) ? 1 : 0,
            'toast'      => !empty($data['toast']) ? 1 : 0,
            'desktop'    => !empty($data['desktop']) ? 1 : 0,
            'sound'      => !empty($data['sound']) ? 1 : 0,
            'bell'       => !empty($data['bell']) ? 1 : 0,
            'recipients' => ($data['recipients'] ?? '') === 'selected' ? 'selected' : 'inbox',
            'staff'      => array_values(array_filter(array_map('intval', (array) ($data['staff'] ?? [])))),
            'throttle'   => min(3600, max(10, (int) ($data['throttle'] ?? 90))),
        ];
        update_option('whatsapp_notify_settings', json_encode($clean));

        return $clean;
    }
}

if (!function_exists('whatsapp_notify_inbox_staff')) {
    /**
     * Active staff who may open the Inbox tab, as [['id'=>3,'name'=>'…'], …].
     *
     * Admins always qualify; everyone else needs the `inbox` capability. One
     * query rather than a staff_can() round-trip per member — this also runs
     * from the webhook, where no staff session exists.
     */
    function whatsapp_notify_inbox_staff()
    {
        static $cache = null;

        if ($cache === null) {
            $CI    = &get_instance();
            $p     = db_prefix();
            $cache = [];

            $rows = $CI->db->query(
                "SELECT s.staffid, s.firstname, s.lastname
                 FROM `{$p}staff` s
                 WHERE s.active = 1
                   AND (s.admin = 1 OR EXISTS (
                        SELECT 1 FROM `{$p}staff_permissions` sp
                        WHERE sp.staff_id = s.staffid AND sp.feature = ? AND sp.capability = ?))
                 ORDER BY s.firstname ASC",
                [WHATSAPP_MODULE_NAME, 'inbox']
            )->result();

            foreach ($rows as $row) {
                $name = trim(preg_replace('/\s+/', ' ', $row->firstname . ' ' . $row->lastname));
                $cache[] = ['id' => (int) $row->staffid, 'name' => $name !== '' ? $name : ('#' . (int) $row->staffid)];
            }
        }

        return $cache;
    }
}

if (!function_exists('whatsapp_notify_staff_ids')) {
    /** Staff ids that actually receive a bell entry, honouring the recipient mode. */
    function whatsapp_notify_staff_ids()
    {
        $settings = whatsapp_notify_settings();
        $allowed  = array_column(whatsapp_notify_inbox_staff(), 'id');

        if ($settings['recipients'] === 'selected' && !empty($settings['staff'])) {
            // Never notify someone who cannot open the inbox, even if selected.
            $allowed = array_values(array_intersect($allowed, $settings['staff']));
        }

        return $allowed;
    }
}

if (!function_exists('whatsapp_notify_snippet')) {
    /**
     * One-line preview of an inbound message. Media arrives with no body, so
     * fall back to a type label rather than an empty notification.
     *
     * Translation is best-effort: this also runs from the webhook, where the
     * module language file was never loaded (register_language_files only
     * hooks the admin/client language loaders), so a missing string falls back
     * to the English label instead of leaking a raw lang key.
     */
    function whatsapp_notify_snippet($body, $type = 'text')
    {
        $body = trim(preg_replace('/\s+/', ' ', (string) $body));

        if ($body === '') {
            $labels = [
                'image'    => ['📷 ', 'wapi_notify_media_image', 'Photo'],
                'video'    => ['🎬 ', 'wapi_notify_media_video', 'Video'],
                'audio'    => ['🎤 ', 'wapi_notify_media_audio', 'Voice message'],
                'document' => ['📄 ', 'wapi_notify_media_document', 'Document'],
                'sticker'  => ['🌟 ', 'wapi_notify_media_sticker', 'Sticker'],
                'contacts' => ['👤 ', 'wapi_notify_media_contact', 'Contact card'],
                'location' => ['📍 ', 'wapi_notify_media_location', 'Location'],
            ];
            $label = $labels[$type] ?? ['', 'wapi_notify_media_generic', 'Sent an attachment'];
            $text  = _l($label[1], '', false);
            $body  = $label[0] . ($text === '' || $text === $label[1] ? $label[2] : $text);
        }

        if (function_exists('mb_substr')) {
            return mb_strlen($body) > 140 ? mb_substr($body, 0, 137) . '…' : $body;
        }

        return strlen($body) > 140 ? substr($body, 0, 137) . '…' : $body;
    }
}

if (!function_exists('whatsapp_notify_throttled')) {
    /**
     * True when this contact already produced a bell entry inside the throttle
     * window. The stamp map is kept in one option row (pruned to the last 200
     * contacts) — cheaper than a table for something this short-lived.
     */
    function whatsapp_notify_throttled($phone, $window)
    {
        $raw  = get_option('whatsapp_notify_last');
        $map  = $raw ? json_decode($raw, true) : [];
        $map  = is_array($map) ? $map : [];
        $now  = time();
        $last = (int) ($map[$phone] ?? 0);

        if ($last && ($now - $last) < $window) {
            return true;
        }

        $map[$phone] = $now;
        // Drop stamps older than a day, then hard-cap the map.
        $map = array_filter($map, function ($ts) use ($now) {
            return ($now - (int) $ts) < 86400;
        });
        if (count($map) > 200) {
            arsort($map);
            $map = array_slice($map, 0, 200, true);
        }
        update_option('whatsapp_notify_last', json_encode($map));

        return false;
    }
}

if (!function_exists('whatsapp_notify_inbound')) {
    /**
     * Fan an inbound WhatsApp message out to the notification bell.
     *
     * Called from Whatsapp_model::_ingest_inbound(), i.e. inside the webhook /
     * ingest request. Never throws: a notification problem must not cost us the
     * 200 OK that stops Meta from re-delivering the message.
     *
     * @param string $phone E.164 number the message came from
     * @param string $name  WhatsApp profile / contact name (may be empty)
     * @param string $body  message text
     * @param string $type  text|image|document|…
     */
    function whatsapp_notify_inbound($phone, $name, $body, $type = 'text')
    {
        try {
            $settings = whatsapp_notify_settings();
            if (!$settings['enabled'] || !$settings['bell']) {
                return;
            }

            $staff_ids = whatsapp_notify_staff_ids();
            if (empty($staff_ids)) {
                return;
            }

            if (whatsapp_notify_throttled($phone, $settings['throttle'])) {
                return;
            }

            $from    = trim((string) $name) !== '' ? trim((string) $name) : $phone;
            $snippet = whatsapp_notify_snippet($body, $type);
            $link    = 'whatsapp?tab=inbox&thread=' . rawurlencode($phone);

            $notified = [];
            foreach ($staff_ids as $staff_id) {
                $ok = add_notification([
                    'description'     => 'wapi_notify_new_message',
                    'touserid'        => (int) $staff_id,
                    'fromcompany'     => 1,
                    'fromuserid'      => 0,
                    'link'            => $link,
                    'additional_data' => serialize([$from, $snippet]),
                ]);
                if ($ok) {
                    $notified[] = (int) $staff_id;
                }
            }

            if (!empty($notified) && function_exists('pusher_trigger_notification')) {
                pusher_trigger_notification($notified);
            }
        } catch (Throwable $e) {
            log_activity('WhatsApp inbox notification failed: ' . $e->getMessage());
        }
    }
}
