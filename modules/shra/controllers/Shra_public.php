<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public rider self-registration (QR) — no authentication.
 *
 *   /join                                 membership form (learners) — static, printed on the QR poster
 *   /join/pay/{rider_no}/{sig}            online checkout (full or part payment)
 *   /join/done/{rider_no}/{sig}           success + membership PDF
 *   /join/pdf/{rider_no}/{sig}            membership PDF download
 *   /join/verify/{rider_no}[/{certificate_no}]   QR verification
 *
 * The private option shra_public_token is only used to sign links.
 */
class Shra_public extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shra/shra_model');
        $this->load->helper('shra/shra');
        // The public pages take traffic before any staff member opens the admin panel,
        // so they run the schema self-heal too — it is a no-op once the version matches.
        if (function_exists('shra_maybe_upgrade_schema')) {
            shra_maybe_upgrade_schema();
        }
    }

    private function error($title, $message)
    {
        $this->load->view('public_error', ['title' => $title, 'message' => $message]);
    }

    private function brand()
    {
        return [
            'name'             => get_option('shra_academy_name') ?: 'Stallion Horse Riding Academy',
            'tagline'          => get_option('shra_tagline'),
            'logo_path'        => shra_logo_pdf_path(),
            'contact'          => get_option('shra_contact_line'),
            'chief_instructor' => get_option('shra_chief_instructor') ?: 'Chief Instructor',
            'director'         => get_option('shra_director') ?: 'Director',
            'powered_by_logo'  => shra_powered_by_logo_path(),
        ];
    }

    public function register($action = '', $a = '', $b = '')
    {
        if ($action === 'done') {
            return $this->done($a, $b);
        }
        if ($action === 'pay') {
            return $this->pay($a, $b);
        }
        if ($action === 'pdf') {
            return $this->pdf($a, $b);
        }
        if ($action === 'verify') {
            return $this->verify($a, $b);
        }

        $packages = $this->shra_model->get_packages(true);
        $plans    = [];
        foreach ($packages as $pk) {
            $q       = $this->shra_model->quote($pk);
            $plans[] = [
                'id'           => (int) $pk->id,
                'name'         => $pk->name,
                'audience'     => $pk->audience,
                'sessions'     => (int) $pk->sessions,
                'duration_min' => (int) $pk->duration_min,
                'is_guest'     => (int) $pk->is_guest,
                'is_featured'  => (int) $pk->is_featured,
                'per_session'  => shra_money($pk->per_session),
                'price'        => shra_money($pk->price),
                'total'        => shra_money($q['total']),
                'discount'     => $q['discount_percent'] + 0,
            ];
        }

        $data = [
            'title'     => 'Join — ' . get_option('shra_academy_name'),
            'levels'    => shra_riding_levels(),
            'terms'     => get_option('shra_terms'),
            'plans'     => $plans,
            'offer'     => shra_offer(),
            'minor_age' => (int) get_option('shra_minor_age'),
            'errors'    => [],
            'old'       => [],
        ];

        if ($this->input->post()) {
            $post   = $this->input->post(null, true);
            $type   = ($post['rider_type'] ?? '') === 'guest' ? 'guest' : 'learner';
            $errors = $this->validate($post, $type);

            if (!count($errors)) {
                $post['rider_type']     = $type;
                $post['terms_accepted'] = 1;
                $post['status']         = 'active';
                // Batch & start date: a preference, never a held seat — see shra_fcfs_note().
                // Guest rides carry neither: the form hides them and the desk fixes the timing.
                $post['preferred_start_date'] = $type === 'guest' ? null : shra_start_date($post['preferred_start_date'] ?? '');
                $post['preferred_batch']      = $type === 'guest' ? null : shra_batch_key($post['preferred_batch'] ?? '');
                unset($post['csrf_token_name']);

                // Plan chosen on the form — must be an active package of the right kind
                $pkg = !empty($post['package_id']) ? $this->shra_model->get_package((int) $post['package_id']) : null;
                $post['preferred_package_id'] = ($pkg && $pkg->active && (int) $pkg->is_guest === ($type === 'guest' ? 1 : 0)) ? $pkg->id : null;

                $id = $this->shra_model->add_rider($post, 'self');
                if ($id) {
                    $rider = $this->shra_model->get_rider($id);
                    $sig   = shra_sign($rider->rider_no);
                    // A plan was chosen and the academy takes money online — collect it now
                    $step  = ($rider->preferred_package_id && count(shra_pay_gateways())) ? 'pay' : 'done';

                    // Until money arrives this registration is also a lead: capture()
                    // round-robin assigns it and notifies the agent, who follows up if
                    // the payment never happens. Once paid, billing marks the lead won;
                    // if it stays unpaid the cron reclaims the rider row and only the
                    // lead remains. A lead capture that fails must never block the join.
                    $this->load->model('shra/shra_leads_model');
                    $src = $this->db->where('name', 'Website QR')->get(db_prefix() . 'leads_sources')->row();
                    $res = $this->shra_leads_model->capture([
                        'name'                 => $rider->full_name,
                        'phone'                => $rider->mobile,
                        'email'                => (string) $rider->email,
                        'rider_for'            => $rider->is_minor ? 'child' : 'self',
                        'rider_age'            => shra_age($rider->dob),
                        'interest_package_id'  => (int) $rider->preferred_package_id,
                        'preferred_start_date' => $rider->preferred_start_date,
                        'preferred_batch'      => $rider->preferred_batch,
                        'address'              => (string) $rider->address,
                        'source'               => $src ? (int) $src->id : 0,
                        'description'          => 'Registered on the join page (' . ($type === 'guest' ? 'guest ride' : 'membership')
                            . ($pkg ? ' · ' . $pkg->name : ' · no plan chosen') . '). '
                            . ($step === 'pay' ? 'Online payment pending — becomes a rider once paid.' : 'No online payment taken — collect at the desk.'),
                    ], 'join_form');
                    if (!is_string($res)) {
                        $this->shra_leads_model->link_rider((int) $res['lead_id'], $rider);
                    }
                    redirect(site_url('join/' . $step . '/' . $rider->rider_no . '/' . $sig));
                }
                $errors[] = 'We could not save your registration. Please try again.';
            }

            $data['errors'] = $errors;
            $data['old']    = $post;
        }

        $this->load->view('public_register', $data);
    }

    /* ═══════════════════════ Public inquiry (leads) ═══════════════════════ */

    /**
     * Ad landing page (Meta Ads / Google Ads) + public inquiry form.
     *
     *   /inquire            landing page → lead (source from utm_source, round-robin assigned)
     *   /inquire/done       thank-you page — fires the Meta Pixel "Lead" + Google Ads conversion
     *
     * Query params carried through the form: c (campaign short code), utm_source, utm_medium,
     * utm_campaign, utm_content, gclid, fbclid, pkg (pre-select a package id).
     */
    public function inquire($action = '')
    {
        if (get_option('shra_lead_public_enabled') !== '1') {
            return $this->error('Inquiries closed', 'Please call the academy directly.');
        }
        if ($action === 'done') {
            return $this->load->view('public_inquire_success', [
                'title'   => 'Thank you — ' . get_option('shra_academy_name'),
                'landing' => $this->landing(),
            ]);
        }

        $this->load->model('shra/shra_leads_model');
        $packages = $this->shra_model->get_packages(true);
        $plans    = [];
        foreach ($packages as $pk) {
            $q       = $this->shra_model->quote($pk);
            $plans[] = [
                'id'           => (int) $pk->id,
                'name'         => $pk->name,
                'audience'     => $pk->audience,
                'sessions'     => (int) $pk->sessions,
                'duration_min' => (int) $pk->duration_min,
                'is_guest'     => (int) $pk->is_guest,
                'is_featured'  => (int) $pk->is_featured,
                'per_session'  => shra_money($pk->per_session),
                'per_session_now' => shra_money($pk->sessions > 0 ? $q['total'] / $pk->sessions : $q['total']),
                'price'        => shra_money($pk->price),
                'total'        => shra_money($q['total']),
                'total_raw'    => (float) $q['total'],
                'discount'     => $q['discount_percent'] + 0,
            ];
        }

        $get  = $this->input->get(null, true) ?: [];
        $data = [
            'title'    => 'Learn Horse Riding in Hyderabad — ' . get_option('shra_academy_name'),
            'packages' => $packages,
            'plans'    => $plans,
            'offer'    => shra_offer(),
            'landing'  => $this->landing(),
            'errors'   => [],
            'old'      => ['package_id' => (int) ($get['pkg'] ?? 0)],
            'ts'       => time(),
            'track'    => $this->tracking_from($get),
            // Booking straight from the ad landing page, when SHRA settings allow it
            'pay'      => shra_pay_settings(),
            'can_pay'  => count(shra_pay_gateways()) > 0,
            'bookable' => [],
        ];
        if ($data['can_pay']) {
            // What the "Book & pay now" button shows for each plan in the dropdown
            foreach ($plans as $pl) {
                if ($pl['total_raw'] > 0) {
                    $data['bookable'][(string) $pl['id']] = ['total' => $pl['total'], 'guest' => (bool) $pl['is_guest']];
                }
            }
        }
        $data['sig'] = shra_sign('inquire|' . $data['ts']);

        if ($this->input->post()) {
            $post   = $this->input->post(null, true);
            $errors = [];
            // Anti-spam: honeypot, signed timestamp (3 s .. 3 h), per-IP rate limit
            if (!empty($post['website'])) {
                $errors[] = 'Submission rejected.';
            }
            $ts = (int) ($post['ts'] ?? 0);
            if (!shra_verify_sign('inquire|' . $ts, (string) ($post['sig'] ?? '')) || time() - $ts < 3 || time() - $ts > 10800) {
                $errors[] = 'The form expired — please try again.';
            }
            // Per-IP cap, configurable in lead settings — blank falls back to 5/hour, 0 switches it off
            $limit = get_option('shra_lead_rate_limit');
            $limit = ($limit === '' || $limit === false || $limit === null) ? 5 : (int) $limit;
            if ($limit > 0) {
                $ip    = $this->input->ip_address();
                $count = $this->db->where('ip', $ip)->where('event_type', 'created')->where('created_at >=', date('Y-m-d H:i:s', time() - 3600))->count_all_results(db_prefix() . 'shra_lead_events');
                if ($count >= $limit) {
                    $errors[] = 'Too many inquiries from this connection. Please call us instead.';
                }
            }
            if (trim((string) ($post['name'] ?? '')) === '') {
                $errors[] = 'Please enter your name.';
            }
            if (!shra_phone_valid((string) ($post['phone'] ?? ''))) {
                $errors[] = 'Please enter a valid mobile number.';
            }
            // Age is optional, but when given it must be a real rider's age
            $age = trim((string) ($post['rider_age'] ?? ''));
            if ($age !== '' && (!ctype_digit($age) || (int) $age < $data['landing']['min_age'] || (int) $age > 90)) {
                $errors[] = "The rider's age must be between " . $data['landing']['min_age'] . ' and 90.';
            }

            if (!count($errors)) {
                $track = $this->tracking_from($post);
                $desc  = trim((string) ($post['message'] ?? ''));
                if ($track['line'] !== '') {
                    $desc .= ($desc !== '' ? "\n\n" : '') . 'Ad tracking: ' . $track['line'];
                }
                $res = $this->shra_leads_model->capture([
                    'name'                => $post['name'],
                    'phone'               => $post['phone'],
                    'rider_for'           => $post['rider_for'] ?? 'self',
                    'rider_age'           => $post['rider_age'] ?? '',
                    'interest_package_id' => (int) ($post['package_id'] ?? 0),
                    'preferred_start_date' => $post['preferred_start_date'] ?? '',
                    'preferred_batch'     => $post['preferred_batch'] ?? '',
                    'city'                => $post['city'] ?? '',
                    'source'              => $this->lead_source_id($track),
                    'description'         => $desc,
                    'campaign'            => $track['campaign'],
                ], 'public_form');
                // Duplicates are attached to the original lead silently — the visitor still sees a thank-you
                if (!is_string($res)) {
                    // "Book & pay now" carries the visitor on to the checkout instead;
                    // the lead is captured either way, so nothing is lost if they drop
                    // out — including when no plan was picked, which just leaves the
                    // enquiry for the team to call back on.
                    $pkg_id = (int) ($post['package_id'] ?? 0);
                    if (($post['action'] ?? '') === 'book' && $pkg_id && count(shra_pay_gateways())) {
                        $err = $this->book_from_lead((int) $res['lead_id'], $pkg_id);
                        if ($err !== '') {
                            $errors[] = $err;
                        }
                    }
                    if (!count($errors)) {
                        redirect(site_url('inquire/done'));
                    }
                } else {
                    $errors[] = $res;
                }
            }
            $data['errors'] = $errors;
            $data['old']    = $post;
            $data['track']  = $this->tracking_from($post);
        }

        $this->load->view('public_inquire', $data);
    }

    /**
     * Turn a freshly captured lead into a rider and send them to the /join checkout.
     * Redirects on success; returns a message the inquiry form can show on failure.
     *
     * @return string '' when the visitor has been redirected to the checkout
     */
    private function book_from_lead($lead_id, $package_id)
    {
        $package = $this->shra_model->get_package($package_id);
        if (!$package || !$package->active) {
            return 'That plan is not available any more — we will call you about the others.';
        }

        $rider_id = $this->shra_leads_model->convert_to_rider($lead_id, [
            'rider_type' => $package->is_guest ? 'guest' : 'learner',
            'package_id' => (int) $package->id,
            'source'     => 'self',
            // A returning guest who buys a course becomes a member
            'promote'    => true,
        ]);
        if (is_string($rider_id)) {
            return $rider_id;
        }

        $rider = $this->shra_model->get_rider($rider_id);
        if (!$rider) {
            return 'We could not open your booking. Please call us instead.';
        }

        redirect(site_url('join/pay/' . $rider->rider_no . '/' . shra_sign($rider->rider_no)));
    }

    /** Landing-page settings (phone, reels, pixels) with sensible fallbacks. */
    private function landing()
    {
        $phone = trim((string) get_option('shra_lead_landing_phone'));
        if ($phone === '' && preg_match('/(\+?\d[\d\s-]{7,}\d)/', (string) get_option('shra_contact_line'), $m)) {
            $phone = $m[1];
        }
        $reels = array_values(array_filter(array_map(function ($l) {
            $l = trim($l);
            if (preg_match('~instagram\.com/(?:[^/]+/)?(?:reel|p)/([A-Za-z0-9_-]+)~', $l, $m)) {
                return $m[1];
            }

            return preg_match('/^[A-Za-z0-9_-]{5,}$/', $l) ? $l : null;
        }, preg_split('/[\r\n,]+/', (string) get_option('shra_lead_landing_reels')))));

        $out = [
            'phone'       => $phone,
            'phone_digits' => preg_replace('/\D+/', '', $phone),
            'wa_link'     => $phone !== '' ? shra_wa_link($phone, 'Hi! I saw your ad and I\'m interested in horse riding lessons at ' . get_option('shra_academy_name') . '. Please share the packages and visit timings.') : '',
            'location'    => trim((string) get_option('shra_lead_landing_location')),
            'maps_url'    => trim((string) get_option('shra_lead_landing_maps_url')),
            'instagram'   => trim((string) get_option('shra_lead_landing_instagram')),
            'reels'       => $reels,
            'meta_pixel'  => preg_replace('/\D+/', '', (string) get_option('shra_lead_meta_pixel_id')),
            'gads_id'     => trim((string) get_option('shra_lead_gads_id')),
            'gads_label'  => trim((string) get_option('shra_lead_gads_label')),
            'ga4_id'      => trim((string) get_option('shra_lead_ga4_id')),
            'min_age'     => (int) (get_option('shra_lead_landing_min_age') ?: 5),
        ];
        // Map: a pasted "Share → Embed a map" URL wins; otherwise a keyless search embed of the location line
        $embed = trim((string) get_option('shra_lead_landing_map_embed'));
        if (preg_match('/<iframe[^>]+src="([^"]+)"/i', $embed, $m)) {
            $embed = html_entity_decode($m[1]);
        }
        if ($embed !== '' && !preg_match('~^https://(www\.)?google\.[a-z.]+/maps~i', $embed)) {
            $embed = '';
        }
        $query = trim((string) get_option('shra_lead_landing_map_query')) ?: $out['location'];
        $out['map_embed'] = $embed !== '' ? $embed : ($query !== '' ? 'https://www.google.com/maps?q=' . rawurlencode($query) . '&z=14&output=embed' : '');
        if ($out['maps_url'] === '' && $query !== '') {
            $out['maps_url'] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
        }
        $ig = $this->instagram_feed($out['instagram']);
        $out['ig_handle']    = $ig['handle'];
        $out['ig_followers'] = $ig['followers'];
        $out['ig_posts']     = $ig['posts'];
        $out['latest_reels'] = $ig['reels'];

        return $out;
    }

    /**
     * Latest reels from the academy's public Instagram profile (no login / token needed).
     * Cached in an option for 6 h; a stale cache is served if Instagram is unreachable;
     * an empty result means the view falls back to the manually configured reel list.
     */
    private function instagram_feed($profile_url, $ttl = 21600)
    {
        $handle = '';
        if (preg_match('~instagram\.com/([A-Za-z0-9_.]+)~', (string) $profile_url, $m)) {
            $handle = $m[1];
        }
        $empty = ['handle' => $handle, 'followers' => 0, 'posts' => 0, 'reels' => []];
        if ($handle === '') {
            return $empty;
        }

        $cache = json_decode((string) get_option('shra_lead_ig_cache'), true);
        $fresh = is_array($cache) && ($cache['handle'] ?? '') === $handle && (int) ($cache['v'] ?? 0) === 2 && time() - (int) ($cache['ts'] ?? 0) < $ttl;
        if ($fresh) {
            return $cache['data'] + $empty;
        }

        $data = $this->instagram_fetch($handle);
        if ($data !== null) {
            update_option('shra_lead_ig_cache', json_encode(['handle' => $handle, 'v' => 2, 'ts' => time(), 'data' => $data]));

            return $data + $empty;
        }
        if (is_array($cache) && ($cache['handle'] ?? '') === $handle && (int) ($cache['v'] ?? 0) === 2) {
            // Instagram unreachable — serve the stale copy, but retry again in 15 min rather than hammering
            $cache['ts'] = time() - $ttl + 900;
            update_option('shra_lead_ig_cache', json_encode($cache));

            return $cache['data'] + $empty;
        }

        return $empty;
    }

    private function instagram_fetch($handle)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init('https://www.instagram.com/api/v1/users/web_profile_info/?username=' . rawurlencode($handle));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['x-ig-app-id: 936619743392459', 'Accept: */*', 'Accept-Language: en-US,en;q=0.9'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$body) {
            return null;
        }
        $j    = json_decode($body, true);
        $user = $j['data']['user'] ?? null;
        if (!$user) {
            return null;
        }

        $reels = [];
        foreach ((array) ($user['edge_owner_to_timeline_media']['edges'] ?? []) as $e) {
            $n = $e['node'] ?? [];
            if (empty($n['is_video']) || empty($n['shortcode'])) {
                continue;
            }
            $caption = '';
            foreach ((array) ($n['edge_media_to_caption']['edges'] ?? []) as $c) {
                $caption = trim((string) ($c['node']['text'] ?? ''));
                break;
            }
            $reels[] = [
                'id'      => $n['shortcode'],
                'thumb'   => (string) ($n['thumbnail_src'] ?? $n['display_url'] ?? ''),
                'views'   => (int) ($n['video_view_count'] ?? $n['video_play_count'] ?? 0),
                'likes'   => (int) ($n['edge_liked_by']['count'] ?? $n['edge_media_preview_like']['count'] ?? 0),
                'taken'   => (int) ($n['taken_at_timestamp'] ?? 0),
                'caption' => mb_substr(trim(preg_replace(['/[#@][\w.]+/u', '/\s+/'], ['', ' '], $caption)), 0, 140),
            ];
        }
        usort($reels, function ($a, $b) { return $b['taken'] - $a['taken']; });
        $reels = array_slice($reels, 0, 12);

        // fbcdn thumbnail URLs are signed + short-lived and often refuse browser hotlinks,
        // so copy them into uploads/shra/ig/ and serve them from our own domain.
        $dir = FCPATH . 'uploads/shra/ig/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $keep = [];
        foreach ($reels as &$r) {
            $file   = preg_replace('/[^A-Za-z0-9_-]/', '', $r['id']) . '.jpg';
            $keep[] = $file;
            if (!is_file($dir . $file) && $r['thumb'] !== '') {
                $img = $this->instagram_get($r['thumb']);
                if ($img !== null && strlen($img) > 1000 && @imagecreatefromstring($img) !== false) {
                    file_put_contents($dir . $file, $img);
                }
            }
            $r['thumb'] = is_file($dir . $file) ? base_url('uploads/shra/ig/' . $file) . '?v=' . filemtime($dir . $file) : '';
        }
        unset($r);
        // Drop thumbnails of reels that are no longer in the latest set
        foreach ((array) glob($dir . '*.jpg') as $f) {
            if (!in_array(basename($f), $keep, true)) {
                @unlink($f);
            }
        }

        return [
            'handle'    => $handle,
            'followers' => (int) ($user['edge_followed_by']['count'] ?? 0),
            'posts'     => (int) ($user['edge_owner_to_timeline_media']['count'] ?? 0),
            'reels'     => $reels,
        ];
    }

    /** Small GET helper for Instagram CDN assets. */
    private function instagram_get($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code === 200 && $body ? $body : null;
    }

    /** Normalise the ad-tracking params from GET (landing) or POST (hidden fields). */
    private function tracking_from(array $in)
    {
        $t = [];
        foreach (['c', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'] as $k) {
            $t[$k] = substr(trim((string) ($in[$k] ?? '')), 0, 120);
        }
        $t['campaign'] = substr($t['c'] !== '' ? $t['c'] : $t['utm_campaign'], 0, 80);

        $parts = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
            if ($t[$k] !== '') {
                $parts[] = substr($k, 4) . '=' . $t[$k];
            }
        }
        if ($t['gclid'] !== '') {
            $parts[] = 'gclid=' . $t['gclid'];
        }
        if ($t['fbclid'] !== '') {
            $parts[] = 'fbclid';
        }
        $t['line'] = implode(' · ', $parts);

        return $t;
    }

    /** Map utm_source / click ids onto the Perfex lead sources seeded by the module. */
    private function lead_source_id(array $t)
    {
        $src  = strtolower($t['utm_source'] . ' ' . $t['utm_medium']);
        $name = 'Website QR';
        if ($t['gclid'] !== '' || strpos($src, 'google') !== false || strpos($src, 'adwords') !== false) {
            $name = 'Google';
        } elseif (strpos($src, 'ig') === 0 || strpos($src, 'instagram') !== false) {
            $name = 'Instagram';
        } elseif ($t['fbclid'] !== '' || strpos($src, 'fb') !== false || strpos($src, 'facebook') !== false || strpos($src, 'meta') !== false) {
            $name = 'Facebook';
        }
        $row = $this->db->where('name', $name)->get(db_prefix() . 'leads_sources')->row();
        if (!$row && $name !== 'Website QR') {
            $row = $this->db->where('name', 'Website QR')->get(db_prefix() . 'leads_sources')->row();
        }

        return $row ? (int) $row->id : 0;
    }

    private function validate(array $p, $type = 'learner')
    {
        $e = [];
        $learner = $type === 'learner';
        if (trim((string) ($p['full_name'] ?? '')) === '') {
            $e[] = 'Please enter the rider\'s full name.';
        }
        $mobile = preg_replace('/\D+/', '', (string) ($p['mobile'] ?? ''));
        if (strlen($mobile) < 8) {
            $e[] = 'Please enter a valid mobile number.';
        }
        if (empty($p['dob'])) {
            $e[] = 'Please enter the date of birth.';
        } elseif (strtotime($p['dob']) === false || strtotime($p['dob']) > time()) {
            $e[] = 'The date of birth is not valid.';
        }
        if (!empty($p['email']) && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
            $e[] = 'The email address is not valid.';
        }
        if (empty($p['gender'])) {
            $e[] = 'Please select a gender.';
        }
        if ($learner && trim((string) ($p['address'] ?? '')) === '') {
            $e[] = 'Please enter the full address.';
        }
        $minor = !empty($p['dob']) && shra_is_minor($p['dob']);
        if ($minor && trim((string) ($p['guardian_name'] ?? '')) === '') {
            $e[] = 'The rider is under ' . get_option('shra_minor_age') . ' — a parent / guardian name is required.';
        }
        if (empty($p['terms_accepted'])) {
            $e[] = $minor ? 'The parent / guardian must accept the terms & conditions on the rider\'s behalf.' : 'Please accept the terms & conditions.';
        }
        if ($minor && trim((string) ($p['terms_accepted_by'] ?? '')) === '') {
            $e[] = 'Please type the guardian\'s name to accept the terms on the rider\'s behalf.';
        }

        // Duplicate guard — same name + mobile already on file (guests may return; learners should not re-register)
        $existing = $this->shra_model->find_rider_by_mobile($mobile);
        if ($existing && $learner && mb_strtolower(trim($existing->full_name)) === mb_strtolower(trim((string) ($p['full_name'] ?? '')))) {
            $e[] = 'A rider with this name and mobile number is already registered (' . $existing->rider_no . '). Please ask the reception desk.';
        }

        return $e;
    }

    /* ═══════════════════ Online payment (full / part) ═══════════════════
     * Which gateways appear here, and whether a part payment is allowed at all,
     * come from SHRA settings (admin/shra/settings). The gateways themselves are
     * configured once on the SaaS master account — see shra.php.
     */

    private function pay($rider_no, $sig)
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        if (!$rider || !shra_verify_sign($rider_no, $sig)) {
            return $this->error('Not found', 'We could not find this registration.');
        }

        $pay      = shra_pay_settings();
        $gateways = shra_pay_gateways();
        $package  = $rider->preferred_package;
        $done_url = site_url('join/done/' . $rider_no . '/' . $sig);

        // Nothing to collect: no plan, no gateway, or the rider already paid
        $checkout = $this->shra_model->join_checkout_for_rider($rider->id);
        if (!$package || !count($gateways) || ($checkout && $checkout->status === 'paid')) {
            redirect($done_url);
        }

        $quote = $this->shra_model->quote($package);
        $total = (float) $quote['total'];
        $min   = shra_pay_min_amount($total);

        $data = [
            'title'    => 'Payment — ' . get_option('shra_academy_name'),
            'rider'    => $rider,
            'package'  => $package,
            'quote'    => $quote,
            'total'    => $total,
            'min'      => $min,
            'pay'      => $pay,
            'gateways' => $gateways,
            'done_url' => $done_url,
            'errors'   => [],
            'old'      => [],
        ];

        if ($this->input->post()) {
            $post    = $this->input->post(null, true);
            $gateway = (string) ($post['gateway'] ?? '');
            $partial = $pay['partial'] && ($post['kind'] ?? 'full') === 'partial';
            $amount  = $partial ? (float) str_replace(',', '', (string) ($post['amount'] ?? '0')) : $total;
            $errors  = [];

            if (!isset($gateways[$gateway])) {
                $errors[] = 'Please choose how you would like to pay.';
            }
            if ($amount < $min - 0.009) {
                $errors[] = 'The smallest amount you can pay now is ' . shra_money($min) . '.';
            }

            if (!count($errors)) {
                $res = $this->shra_model->create_join_invoice($rider->id, $package->id, $amount, $gateway);
                if (!is_string($res)) {
                    return $this->hand_off($res, $gateway);
                }
                $errors[] = $res;
            }

            $data['errors'] = $errors;
            $data['old']    = $post;
        }

        $this->load->view('public_pay', $data);
    }

    /**
     * Hand the rider over to the gateway. process_payment() ends the request
     * itself (the gateway redirects or prints its checkout), so anything after
     * the call means the gateway never started.
     */
    private function hand_off(array $checkout, $gateway)
    {
        $this->load->model('payments_model');

        // Perfex replaces a part payment with the whole balance when the global
        // "allow the payment amount to be modified" setting is off. How much the
        // rider pays is SHRA's decision, so put our figure back — this filter runs
        // after that override, on the way into the gateway.
        $amount = (float) $checkout['amount'];
        hooks()->add_filter('before_process_gateway_func', function ($data) use ($amount) {
            $data['amount'] = $amount;

            return $data;
        }, 99);

        $this->payments_model->process_payment([
            'paymentmode' => $gateway,
            'amount'      => $amount,
            'invoiceid'   => $checkout['invoice_id'],
        ], $checkout['invoice_id']);

        return $this->error('Payment unavailable', 'We could not open the payment page just now. Please try again, or pay at the reception desk.');
    }

    private function done($rider_no, $sig)
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        if (!$rider || !shra_verify_sign($rider_no, $sig)) {
            return $this->error('Not found', 'We could not find this registration.');
        }

        $plan = $rider->preferred_package ? $this->shra_model->quote($rider->preferred_package) : null;

        // What, if anything, the rider paid online
        $checkout = $this->shra_model->join_checkout_for_rider($rider->id);
        $paid     = 0.0;
        if ($checkout) {
            $paid = (float) $this->db->select_sum('amount')->where('invoiceid', (int) $checkout->invoice_id)
                ->get(db_prefix() . 'invoicepaymentrecords')->row()->amount;
        }

        $this->load->view('public_success', [
            'title'    => 'Welcome to the academy',
            'rider'    => $rider,
            'plan'     => $plan,
            'paid'     => round($paid, 2),
            'due'      => $checkout ? round(max(0, (float) $checkout->total - $paid), 2) : null,
            'checkout' => $checkout,
            // A checkout that was started but never paid can be picked up again
            'pay_url'  => ($checkout && $checkout->status !== 'paid' && count(shra_pay_gateways()))
                ? site_url('join/pay/' . $rider->rider_no . '/' . $sig) : null,
            'pdf_url'  => $rider->rider_type === 'learner' ? site_url('join/pdf/' . $rider->rider_no . '/' . $sig) : null,
        ]);
    }

    private function pdf($rider_no, $sig)
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        if (!$rider || !shra_verify_sign($rider_no, $sig) || $rider->rider_type !== 'learner') {
            return $this->error('Not found', 'We could not find this membership.');
        }

        require_once(module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_pdf.php'));
        $pdf = new Shra_pdf($this->brand(), 'P');
        $arr = (array) $rider;
        $arr['qr_text'] = shra_verify_url($rider->rider_no);
        $pdf->membership($arr);
        $pdf->Output('Membership-' . ($rider->membership_no ?: $rider->rider_no) . '.pdf', 'D');
    }

    /** QR verification — shows that a membership / certificate is genuine. */
    private function verify($rider_no, $certificate_no = '')
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        $cert  = null;
        if ($rider && $certificate_no !== '') {
            $cert = $this->db->where('rider_id', $rider->id)->where('certificate_no', $certificate_no)
                ->get(db_prefix() . 'shra_enrollments')->row();
        }

        $this->load->view('public_verify', [
            'title'          => 'Verification',
            'rider'          => $rider,
            'cert'           => $cert,
            'certificate_no' => $certificate_no,
        ]);
    }
}
