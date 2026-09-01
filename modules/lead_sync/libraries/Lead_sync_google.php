<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Everything that talks to Google.
 *
 * Three ways in, in increasing order of privacy and setup effort:
 *
 *  public           the sheet is published (File → Share → Publish to web) or
 *                   shared as "anyone with the link". We hit the plain CSV
 *                   export endpoint — nothing to configure, no credentials.
 *  api_key          a restricted Google API key. Still needs link-sharing on
 *                   the sheet, but the key can be locked to the Sheets API.
 *  service_account  a service-account JSON key; the sheet is shared privately
 *                   with the robot's e-mail address. The sheet stays private
 *                   to the internet — the right choice for real lead data.
 *
 * All three come back through fetch() as the same shape: headers + rows.
 * Errors are returned as strings, never thrown, because every caller (cron,
 * webhook, the "Test connection" button) has to keep going.
 */
class Lead_sync_google
{
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const SCOPE     = 'https://www.googleapis.com/auth/spreadsheets.readonly';

    /* ═══════════════════════ Reading a pasted URL ═══════════════════════ */

    /**
     * Pull the spreadsheet id and tab gid out of whatever the manager pasted —
     * the address bar URL, a "publish to web" URL, or a bare id.
     *
     * @return array ['id' => string, 'gid' => string, 'published' => bool]
     */
    public static function parse_sheet_url($url)
    {
        $url = trim((string) $url);
        $out = ['id' => '', 'gid' => '', 'published' => false];

        if ($url === '') {
            return $out;
        }

        // Published-to-web URLs carry an opaque token, not the spreadsheet id
        if (preg_match('#/spreadsheets/d/e/([A-Za-z0-9_-]+)#', $url, $m)) {
            $out['id']        = $m[1];
            $out['published'] = true;
        } elseif (preg_match('#/spreadsheets/d/([A-Za-z0-9_-]{20,})#', $url, $m)) {
            $out['id'] = $m[1];
        } elseif (preg_match('/^[A-Za-z0-9_-]{20,}$/', $url)) {
            $out['id'] = $url; // a bare id
        }

        if (preg_match('/[#?&]gid=(\d+)/', $url, $m)) {
            $out['gid'] = $m[1];
        }

        return $out;
    }

    /** The CSV endpoint for a connection using no credentials. */
    public static function csv_url($connection)
    {
        $parts = self::parse_sheet_url($connection->sheet_url ?: $connection->spreadsheet_id);
        $id    = $parts['id'] ?: $connection->spreadsheet_id;
        $gid   = $connection->gid !== '' ? $connection->gid : $parts['gid'];

        if ($id === '') {
            return '';
        }

        if ($parts['published']) {
            $url = 'https://docs.google.com/spreadsheets/d/e/' . $id . '/pub?output=csv&single=true';

            return $gid !== '' ? $url . '&gid=' . rawurlencode($gid) : $url;
        }

        // A named tab with no gid is only reachable through the visualisation
        // endpoint, which accepts the tab by name; everything else uses export.
        if ($gid === '' && trim((string) $connection->tab_name) !== '') {
            return 'https://docs.google.com/spreadsheets/d/' . $id
                . '/gviz/tq?tqx=out:csv&headers=1&sheet=' . rawurlencode($connection->tab_name);
        }

        $url = 'https://docs.google.com/spreadsheets/d/' . $id . '/export?format=csv';

        return $gid !== '' ? $url . '&gid=' . rawurlencode($gid) : $url;
    }

    /* ═══════════════════════════ Fetching ═══════════════════════════ */

    /**
     * Read the connection's sheet.
     *
     * @return array ['ok' => bool, 'error' => string, 'headers' => array, 'rows' => array, 'source' => string]
     */
    public static function fetch($connection)
    {
        $mode = (string) $connection->auth_mode;

        if ($mode === 'api_key' || $mode === 'service_account') {
            return self::fetch_api($connection, $mode);
        }

        return self::fetch_csv($connection);
    }

    private static function fail($message)
    {
        return ['ok' => false, 'error' => $message, 'headers' => [], 'rows' => [], 'source' => ''];
    }

    /* ── Public CSV ─────────────────────────────────────────────────────── */

    private static function fetch_csv($connection)
    {
        $url = self::csv_url($connection);

        if ($url === '') {
            return self::fail('No Google Sheet link on this connection.');
        }

        $response = self::http_get($url);

        if (!$response['ok']) {
            return self::fail($response['error']);
        }

        // Google answers an unshared sheet with its sign-in page rather than a
        // 403, so the giveaway is the content type, not the status code.
        if (stripos($response['content_type'], 'text/html') !== false
            || stripos(substr($response['body'], 0, 400), '<!DOCTYPE html') !== false) {
            return self::fail('Google returned a sign-in page instead of the sheet. '
                . 'Open the sheet → Share → set "Anyone with the link" to Viewer, or switch this '
                . 'connection to a service account.');
        }

        if (trim($response['body']) === '') {
            return self::fail('The sheet came back empty.');
        }

        $parsed = Lead_sync_sheet::parse($response['body'], (bool) $connection->has_header);

        return [
            'ok'      => true,
            'error'   => '',
            'headers' => $parsed['headers'],
            'rows'    => $parsed['rows'],
            'source'  => $url,
        ];
    }

    /* ── Sheets API (key or service account) ────────────────────────────── */

    private static function fetch_api($connection, $mode)
    {
        $parts = self::parse_sheet_url($connection->sheet_url ?: $connection->spreadsheet_id);
        $id    = $parts['id'] ?: (string) $connection->spreadsheet_id;

        if ($id === '' || $parts['published']) {
            return self::fail('The Sheets API needs the normal sheet link (docs.google.com/spreadsheets/d/…), '
                . 'not a "published to web" link.');
        }

        $credentials = lead_sync_decrypt($connection->credentials);
        if (trim($credentials) === '') {
            return self::fail($mode === 'api_key'
                ? 'No API key saved on this connection.'
                : 'No service-account JSON saved on this connection.');
        }

        $auth = ['query' => [], 'headers' => []];
        if ($mode === 'api_key') {
            $auth['query']['key'] = trim($credentials);
        } else {
            $token = self::service_account_token($credentials);
            if (is_string($token)) {
                return self::fail($token);
            }
            $auth['headers'][] = 'Authorization: Bearer ' . $token['access_token'];
        }

        $tab = trim((string) $connection->tab_name);
        if ($tab === '') {
            $tab = self::first_tab_name($id, $auth);
            if ($tab === null) {
                return self::fail('Could not read the spreadsheet. Check that the sheet is shared with '
                    . (isset($auth['headers'][0]) ? 'the service account' : 'anyone with the link')
                    . ' and that the link is correct.');
            }
        }

        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($id)
            . '/values/' . rawurlencode($tab)
            . '?majorDimension=ROWS&valueRenderOption=FORMATTED_VALUE'
            . (count($auth['query']) ? '&' . http_build_query($auth['query']) : '');

        $response = self::http_get($url, $auth['headers']);
        if (!$response['ok']) {
            return self::fail($response['error']);
        }

        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return self::fail('Unreadable answer from the Sheets API.');
        }
        if (isset($json['error'])) {
            return self::fail('Google: ' . ($json['error']['message'] ?? 'request refused') . '.');
        }

        $values = $json['values'] ?? [];
        if (!count($values)) {
            return self::fail('The tab "' . $tab . '" is empty.');
        }

        $headers = [];
        if ($connection->has_header) {
            $headers = array_shift($values);
        }
        $parsed = Lead_sync_sheet::from_values($headers, $values);

        return [
            'ok'      => true,
            'error'   => '',
            'headers' => $parsed['headers'],
            'rows'    => $parsed['rows'],
            'source'  => 'sheets.googleapis.com/' . $id . '/' . $tab,
        ];
    }

    /** Name of the first tab, so a connection does not have to spell one out. */
    private static function first_tab_name($id, array $auth)
    {
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($id)
            . '?fields=sheets.properties.title'
            . (count($auth['query']) ? '&' . http_build_query($auth['query']) : '');

        $response = self::http_get($url, $auth['headers']);
        if (!$response['ok']) {
            return null;
        }

        $json = json_decode($response['body'], true);

        return $json['sheets'][0]['properties']['title'] ?? null;
    }

    /* ═══════════════════ Service-account access tokens ═══════════════════ */

    /**
     * Sign a JWT with the service account's private key and swap it for an
     * access token. Returns the token array, or an error string.
     *
     * Tokens live an hour; they are cached per key for the length of the
     * request, which is all a cron run or a webhook needs.
     */
    public static function service_account_token($json)
    {
        static $cache = [];

        $key = md5((string) $json);
        if (isset($cache[$key]) && $cache[$key]['expires_at'] > time() + 30) {
            return $cache[$key];
        }

        $account = json_decode((string) $json, true);
        if (!is_array($account) || empty($account['client_email']) || empty($account['private_key'])) {
            return 'That does not look like a service-account JSON key (no client_email / private_key in it).';
        }

        if (!function_exists('openssl_sign')) {
            return 'PHP is built without OpenSSL, so a service account cannot be signed here. '
                . 'Use a published-sheet or API-key connection instead.';
        }

        $now    = time();
        $header = self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim  = self::b64(json_encode([
            'iss'   => $account['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $account['token_uri'] ?? self::TOKEN_URL,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $signature = '';
        if (!openssl_sign($header . '.' . $claim, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            return 'The private key in that JSON could not be used to sign a request.';
        }

        $assertion = $header . '.' . $claim . '.' . self::b64($signature);
        $response  = self::http_post($account['token_uri'] ?? self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $assertion,
        ]);

        if (!$response['ok']) {
            return $response['error'];
        }

        $token = json_decode($response['body'], true);
        if (!is_array($token) || empty($token['access_token'])) {
            $detail = is_array($token) ? ($token['error_description'] ?? $token['error'] ?? '') : '';

            return 'Google refused the service account' . ($detail ? ': ' . $detail : '') . '.';
        }

        $token['expires_at'] = $now + (int) ($token['expires_in'] ?? 3600);
        $cache[$key]         = $token;

        return $token;
    }

    private static function b64($value)
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }

    /* ═══════════════════════════════ HTTP ═══════════════════════════════ */

    private static function http_get($url, array $headers = [])
    {
        return self::http($url, null, $headers);
    }

    private static function http_post($url, array $fields, array $headers = [])
    {
        return self::http($url, http_build_query($fields), $headers);
    }

    private static function http($url, $body = null, array $headers = [])
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'PHP cURL is not available on this server.', 'body' => '', 'status' => 0, 'content_type' => ''];
        }

        $timeout = max(5, (int) lead_sync_opt('lead_sync_http_timeout'));
        $handle  = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'ClientcareX-LeadSync/1.0',
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response     = curl_exec($handle);
        $status       = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $content_type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $error        = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            return ['ok' => false, 'error' => 'Could not reach Google: ' . $error, 'body' => '', 'status' => 0, 'content_type' => ''];
        }

        // The API answers errors with a JSON body worth reading, so 4xx is not
        // fatal here — only a status with nothing useful behind it is.
        if ($status >= 400 && stripos($content_type, 'json') === false) {
            $hint = $status === 404
                ? ' The sheet id may be wrong, or the sheet was deleted.'
                : ($status === 403 ? ' The sheet is not shared with this connection.' : '');

            return ['ok' => false, 'error' => 'Google answered HTTP ' . $status . '.' . $hint, 'body' => '', 'status' => $status, 'content_type' => $content_type];
        }

        return ['ok' => true, 'error' => '', 'body' => (string) $response, 'status' => $status, 'content_type' => $content_type];
    }
}
