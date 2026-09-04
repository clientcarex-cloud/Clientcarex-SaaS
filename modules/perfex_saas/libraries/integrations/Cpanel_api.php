<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cpanel_api
{
    private $cpanelUsername;
    private $cpanelPassword;
    private $cpanelDomain;
    private $cpanelPort;
    private $prefix;
    public $mainDomain;
    public $throwException;

    /**
     * Diagnostics for the last API call. Never contains the password or the
     * Authorization header - it is rendered straight into the admin UI.
     *
     * @var array
     */
    public $lastDiagnostics = [];

    /**
     * Human readable label of the call currently being made, used so a failure
     * can say WHICH cPanel call broke (e.g. "Mysql::create_database").
     *
     * @var string
     */
    public $lastCall = '';

    public function __construct()
    {
    }
    public function init($cpanelUsername, $cpanelPassword, $cpanelDomain, $cpanelPort = "2083", $prefix = '', $throwException = true)
    {
        $this->cpanelUsername = $cpanelUsername;
        $this->cpanelPassword = $cpanelPassword;
        $this->cpanelDomain = $cpanelDomain;
        $this->cpanelPort = $cpanelPort;
        $this->throwException = $throwException;
        $this->prefix = $prefix;
        return $this;
    }

    public function setThrowException($throwException)
    {
        $this->throwException = $throwException;
    }

    /**
     * The login domain as it will actually be dialled.
     *
     * Admins routinely paste "https://host/cpanel" or a trailing slash into the
     * setting; left as-is that produces a URL cPanel answers with its login
     * page, which used to surface as the meaningless "The panel login is
     * invalid."
     *
     * @return string
     */
    public function normalizedHost()
    {
        $host = trim((string) $this->cpanelDomain);

        if (strpos($host, '://') !== false) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }

        $host = trim($host, "/ \t\n\r\0\x0B");

        // Drop any path/port the admin appended e.g. "host:2083/cpanel"
        $host = explode('/', $host)[0];

        return $host;
    }

    /**
     * @return string
     */
    public function normalizedPort()
    {
        $port = trim((string) $this->cpanelPort);
        return $port === '' ? '2083' : $port;
    }

    private function makeAPICall($module, $func, $params = [], $version = 'uapi')
    {
        $host = $this->normalizedHost();
        $port = $this->normalizedPort();

        $this->lastCall = $module . '::' . $func;

        $url = "https://{$host}:{$port}/execute/{$module}/{$func}";
        $headers = [
            'Authorization: Basic ' . base64_encode($this->cpanelUsername . ':' . $this->cpanelPassword),
            'Accept: application/json',
        ];

        if ($version !== 'uapi') {
            $url = "https://{$host}:{$port}/json-api/cpanel?cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module={$module}&cpanel_jsonapi_func={$func}";
        }

        $paramsString = "";
        if (!empty($params)) {
            $paramsString = $version === 'uapi' ? "?" : "&";
            $paramsLinear = [];
            foreach ($params as $key => $value) {
                $paramsLinear[] = "$key=$value";
            }
            $paramsString .= implode('&', $paramsLinear);
        }

        $url .= $paramsString;
        $responseHeaders = [];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $line = trim($header);
            if ($line !== '') {
                $responseHeaders[] = $line;
            }
            return strlen($header);
        });

        $response_text = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        $curl_info  = curl_getinfo($ch);
        curl_close($ch);

        $response_text = $response_text === false ? '' : (string) $response_text;
        $response = json_decode($response_text, true);
        $is_json = json_last_error() === JSON_ERROR_NONE && is_array($response);

        $this->lastDiagnostics = [
            'call'            => $this->lastCall,
            'api_version'     => $version,
            'endpoint'        => $this->maskUrl($url),
            'login_domain'    => $host,
            'port'            => $port,
            'username'        => $this->cpanelUsername,
            'password_set'    => $this->cpanelPassword === '' || $this->cpanelPassword === null ? 'no' : 'yes (' . strlen((string) $this->cpanelPassword) . ' chars)',
            'http_code'       => (int) ($curl_info['http_code'] ?? 0),
            'content_type'    => $curl_info['content_type'] ?? '',
            'primary_ip'      => $curl_info['primary_ip'] ?? '',
            'total_time'      => round((float) ($curl_info['total_time'] ?? 0), 2) . 's',
            'curl_errno'      => $curl_errno,
            'curl_error'      => $curl_error,
            'redirect_to'     => $this->headerValue($responseHeaders, 'location'),
            'www_authenticate' => $this->headerValue($responseHeaders, 'www-authenticate'),
            'response_is_json' => $is_json ? 'yes' : 'no',
            'response_bytes'  => strlen($response_text),
            'response_excerpt' => $this->excerpt($response_text),
        ];

        if (!$is_json) {
            $response = [];
        }

        $success = (int)($response['result']["status"][0] ?? ($response['status'] ?? 0)) === 1;
        if (!$success && $version !== 'uapi')
            $success = (int)($response['cpanelresult']['data'][0]['result'] ?? 0) === 1;

        $this->lastDiagnostics['result'] = $success ? 'success' : 'failed';

        if (!$success) {

            $error = $this->buildErrorMessage($response, $response_text, $is_json);

            if ($this->throwException) {
                throw new \Exception($error, 1);
            } else {
                log_message('error', $error);
            }
        }
        return $response;
    }

    /**
     * Turn a failed call into something that names the actual cause instead of
     * guessing "invalid login" whenever the word "login" appears in the body.
     *
     * @param array $response  Decoded JSON body (empty when the body was not JSON)
     * @param string $body     Raw response body
     * @param bool $is_json
     * @return string
     */
    private function buildErrorMessage($response, $body, $is_json)
    {
        $d      = $this->lastDiagnostics;
        $code   = (int) $d['http_code'];
        $reason = '';
        $hints  = [];

        // 1. The request never completed - transport level failure.
        if ($d['curl_errno']) {
            $reason = 'Could not reach the cPanel API (cURL error ' . $d['curl_errno'] . ': ' . $d['curl_error'] . ').';

            switch ($d['curl_errno']) {
                case 6: // CURLE_COULDNT_RESOLVE_HOST
                    $hints[] = 'The login domain "' . $d['login_domain'] . '" does not resolve from this server. Check the "cPanel login domain" setting - it must be a bare hostname, with no https:// and no /cpanel path.';
                    break;
                case 7: // CURLE_COULDNT_CONNECT
                    $hints[] = 'Port ' . $d['port'] . ' on ' . $d['login_domain'] . ' refused the connection or is firewalled. cPanel listens on 2083 (2082 without SSL); 2087/2086 are WHM, not cPanel.';
                    break;
                case 28: // CURLE_OPERATION_TIMEDOUT
                    $hints[] = 'The connection timed out. The host firewall (CSF/cPHulk) is most likely dropping traffic from this server on port ' . $d['port'] . '.';
                    break;
                case 35:
                case 51:
                case 60: // SSL problems
                    $hints[] = 'TLS verification failed. The certificate presented on port ' . $d['port'] . ' is self-signed, expired, or the CA bundle on this server is out of date.';
                    break;
                default:
                    $hints[] = 'Outbound HTTPS from this server to ' . $d['login_domain'] . ':' . $d['port'] . ' is failing before cPanel ever sees the credentials.';
            }
        }
        // 2. cPanel answered, and answered with an auth failure.
        elseif ($code === 401) {
            $reason = 'cPanel rejected the credentials (HTTP 401 Unauthorized).';
            $hints[] = 'The username or password is wrong for this panel, OR Basic authentication is not usable on this account.';
            $hints[] = 'Two-factor authentication on the cPanel account blocks Basic auth - disable 2FA for this user, or use an API token instead.';
            $hints[] = 'Confirm the username is the cPanel account user (not WHM root, not an email address).';
        } elseif ($code === 403) {
            $reason = 'cPanel accepted the request but refused it (HTTP 403 Forbidden).';
            $hints[] = 'This is normally cPHulk / host access control blocking this server\'s IP' . ($d['primary_ip'] ? ' (' . $d['primary_ip'] . ')' : '') . ', or the cPanel account lacking the feature/privilege for ' . $d['call'] . '.';
        }
        // 3. Redirected to the login form - the classic "invalid login" case.
        elseif (in_array($code, [301, 302, 303, 307, 308], true) || stripos((string) $d['redirect_to'], 'login') !== false) {
            $reason = 'cPanel redirected the API call to its login page (HTTP ' . $code . ($d['redirect_to'] ? ' -> ' . $d['redirect_to'] : '') . ').';
            $hints[] = 'The session was not authenticated, so the panel bounced the call to the login form. Check the password, and that the login domain points directly at cPanel and not at a proxy, Cloudflare, or a "/cpanel" redirect.';
        }
        // 4. HTML came back where JSON was expected.
        elseif (!$is_json) {
            $looks_like_login = stripos($body, 'login') !== false;
            $reason = 'cPanel returned ' . ($body === '' ? 'an empty response' : ($looks_like_login ? 'its HTML login page' : 'a non-JSON response')) . ' (HTTP ' . $code . ', content-type ' . ($d['content_type'] ?: 'unknown') . ').';

            if ($code === 404) {
                $hints[] = 'The endpoint does not exist on that host. ' . $d['endpoint'] . ' is a cPanel (port 2083) URL - if the port is a WHM port, or the host is behind a proxy that rewrites paths, this is what you get.';
            } elseif ($looks_like_login) {
                $hints[] = 'The panel served the login form instead of running ' . $d['call'] . ', which means the Basic auth header was not accepted.';
                $hints[] = 'Verify the password by pasting it into the cPanel login form for user "' . $d['username'] . '" on https://' . $d['login_domain'] . ':' . $d['port'] . '/.';
                $hints[] = 'If the password contains &, <, >, or quotes, re-enter it - some proxies and filters mangle those characters in transit.';
            } else {
                $hints[] = 'Something in front of cPanel (Cloudflare, ModSecurity, a WAF, a captive portal) answered instead of cPanel itself.';
            }
        }
        // 5. Proper JSON, but the API reported an error.
        else {
            $apiError = $response['errors'] ?? ($response['cpanelresult']['error'] ?? ($response['error'] ?? null));
            if (is_array($apiError)) {
                $apiError = implode('. ', $apiError);
            }

            $reason = 'cPanel executed ' . $d['call'] . ' and returned an error: ' . ($apiError !== null && $apiError !== '' ? $apiError : 'no error text supplied (HTTP ' . $code . ').');
        }

        $lines   = [];
        $lines[] = $reason;
        $lines[] = '';
        $lines[] = 'Request:  ' . $d['endpoint'];
        $lines[] = 'Host:     ' . $d['login_domain'] . ':' . $d['port'] . ($d['primary_ip'] ? ' (resolved ' . $d['primary_ip'] . ')' : '');
        $lines[] = 'User:     ' . $d['username'] . ' | password ' . $d['password_set'];
        $lines[] = 'HTTP:     ' . ($code ?: 'no response') . ' ' . ($d['content_type'] ?: '') . ' in ' . $d['total_time'];

        if ($d['curl_errno']) {
            $lines[] = 'cURL:     ' . $d['curl_errno'] . ' ' . $d['curl_error'];
        }
        if ($d['redirect_to']) {
            $lines[] = 'Location: ' . $d['redirect_to'];
        }
        if ($d['www_authenticate']) {
            $lines[] = 'Auth hdr: ' . $d['www_authenticate'];
        }
        if ($d['response_excerpt'] !== '') {
            $lines[] = 'Body:     ' . $d['response_excerpt'];
        }

        if (!empty($hints)) {
            $lines[] = '';
            $lines[] = 'Likely fix:';
            foreach ($hints as $hint) {
                $lines[] = ' - ' . $hint;
            }
        }

        $message = implode("\n", $lines);
        $this->lastDiagnostics['message'] = $message;

        return $message;
    }

    /**
     * @param array $headers
     * @param string $name
     * @return string
     */
    private function headerValue($headers, $name)
    {
        foreach ($headers as $header) {
            if (stripos($header, $name . ':') === 0) {
                return trim(substr($header, strlen($name) + 1));
            }
        }
        return '';
    }

    /**
     * A readable, single line excerpt of the body - HTML stripped so a login
     * page shows its text rather than 40KB of markup.
     *
     * @param string $body
     * @param int $length
     * @return string
     */
    private function excerpt($body, $length = 400)
    {
        // strip_tags() keeps the CONTENT of <script>/<style>, which buries the
        // one useful line of a cPanel login page under a wall of CSS.
        $clean = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $body);
        $text  = trim(preg_replace('/\s+/', ' ', strip_tags((string) $clean)));

        if ($text === '') {
            return '';
        }

        return strlen($text) > $length ? substr($text, 0, $length) . ' ...' : $text;
    }

    /**
     * Passwords are never in the query string today, but keep the URL safe to
     * echo into the admin UI regardless.
     *
     * @param string $url
     * @return string
     */
    private function maskUrl($url)
    {
        if ($this->cpanelPassword === '' || $this->cpanelPassword === null) {
            return $url;
        }

        return str_replace([(string) $this->cpanelPassword, rawurlencode((string) $this->cpanelPassword)], '***', $url);
    }

    private function makeAPICallv2($module, $func, $params = [])
    {
        return $this->makeAPICall($module, $func, $params, 'v2');
    }

    /**
     * Cheapest authenticated call there is - used to prove the credentials work
     * before any create/delete is attempted.
     *
     * @return array
     */
    public function ping()
    {
        return $this->makeAPICall('Quota', 'get_local_quota_info');
    }

    public function getDiskQuotas()
    {
        return $this->makeAPICall('Quota', 'get_local_quota_info');
    }

    public function addPrefix($text)
    {
        if (empty($this->prefix)) return $text;

        $text = str_starts_with($text, $this->prefix) ? $text : $this->prefix . $text;
        return $text;
    }

    public function createRandomDatabaseAndUser($prefix, $dbType = 'Mysql')
    {
        $params = [
            'prefix' => $prefix
        ];
        return $this->makeAPICall($dbType, 'setup_db_and_user', $params);
    }

    public function createDatabase($databaseName, $dbType = 'Mysql', $prefixSize = 8)
    {
        $params = [
            'name' => $this->addPrefix($databaseName),
            'prefix-size' => $prefixSize
        ];
        return $this->makeAPICall($dbType, 'create_database', $params);
    }

    public function deleteDatabase($databaseName, $dbType = 'Mysql')
    {

        $params = [
            'name' => $this->addPrefix($databaseName)
        ];
        return $this->makeAPICall($dbType, 'delete_database', $params);
    }

    public function createDatabaseUser($databaseUser, $databasePassword, $dbType = 'Mysql', $prefixSize = 8)
    {
        $params = [
            'name' => $this->addPrefix($databaseUser),
            'password' => $databasePassword,
            'prefix-size' => $prefixSize
        ];
        return $this->makeAPICall($dbType, 'create_user', $params);
    }

    public function deleteDatabaseUser($databaseUser, $dbType = 'Mysql')
    {
        $params = [
            'name' => $this->addPrefix($databaseUser)
        ];
        return $this->makeAPICall($dbType, 'delete_user', $params);
    }

    public function setDatabaseUserPrivileges($databaseUser, $databaseName, $privileges = 'ALL%20PRIVILEGES', $dbType = 'Mysql')
    {
        $params = [
            'user' => $this->addPrefix($databaseUser),
            'database' => $this->addPrefix($databaseName),
            'privileges' => $privileges
        ];

        return $this->makeAPICall($dbType, 'set_privileges_on_database', $params);
    }


    public function createSubdomain($subdomain, $rootdomain, $dir = '/public_html/', $disallowdot = 1)
    {
        $params = [
            'domain' => $subdomain,
            'rootdomain' => $rootdomain,
            'dir' => $dir,
            'disallowdot' => $disallowdot
        ];

        return $this->makeAPICall('SubDomain', 'addsubdomain', $params);
    }

    public function deleteSubdomain($subdomain, $rootdomain)
    {
        $params = [
            'domain' => $subdomain . '.' . $rootdomain,
        ];

        return $this->makeAPICallv2('SubDomain', 'delsubdomain', $params);
    }

    public function createAddonDomain($domain, $subdomain, $dir = '/public_html/')
    {

        $params = [
            'newdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => $dir,
        ];

        return $this->makeAPICallv2('AddonDomain', 'addaddondomain', $params);
    }

    public function deleteAddonDomain($domain, $subdomain, $rootdomain)
    {
        $params = [
            'domain' => $domain,
            'subdomain' => $subdomain . '_' . $rootdomain,
        ];

        return $this->makeAPICallv2('AddonDomain', 'deladdondomain', $params);
    }

    public function autoSSL()
    {

        return $this->makeAPICall('SSL', 'start_autossl_check');
    }

    public function generateSSL($domain)
    {
        $params = [
            'city' => 'Houston',
            'country' => 'US',
            'company' => 'cPanel',
            'state' => 'HT',
            'host' => $domain,
            'email' => 'webmaster@' . $domain
        ];

        return $this->makeAPICallv2('SSL', 'gencrt', $params);
    }
}
