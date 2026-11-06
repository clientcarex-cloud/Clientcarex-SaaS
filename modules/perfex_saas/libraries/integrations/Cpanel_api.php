<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cpanel_api
{
    private $cpanelUsername;
    private $cpanelPassword;
    private $cpanelDomain;
    private $cpanelPort;
    private $prefix;

    /**
     * Requested authentication mode: auto|password|token
     *
     * @var string
     */
    private $authMode = 'auto';

    /**
     * The authentication mode that was confirmed to work, so we stop probing.
     *
     * @var string|null
     */
    private $resolvedAuthMode = null;

    public $mainDomain;
    public $throwException;

    public function __construct()
    {
    }
    public function init($cpanelUsername, $cpanelPassword, $cpanelDomain, $cpanelPort = "2083", $prefix = '', $throwException = true, $authMode = 'auto')
    {
        // Pasted credentials (API tokens especially) often carry stray whitespace, which cPanel rejects as a bad login
        $this->cpanelUsername = trim((string) $cpanelUsername);
        $this->cpanelPassword = trim((string) $cpanelPassword);
        $this->cpanelDomain = $cpanelDomain;
        $this->cpanelPort = $cpanelPort;
        $this->throwException = $throwException;
        $this->prefix = $prefix;
        $this->setAuthMode($authMode);
        return $this;
    }

    public function setThrowException($throwException)
    {
        $this->throwException = $throwException;
    }

    /**
     * Set how the credential provided as password should be sent to cPanel.
     *
     * @param string $authMode auto|password|token
     * @return $this
     */
    public function setAuthMode($authMode = 'auto')
    {
        $authMode = strtolower(trim((string) $authMode));
        $this->authMode         = in_array($authMode, ['auto', 'password', 'token']) ? $authMode : 'auto';
        $this->resolvedAuthMode = $this->authMode === 'auto' ? null : $this->authMode;
        return $this;
    }

    /**
     * The authentication mode currently in use.
     *
     * @return string password|token
     */
    public function getAuthMode()
    {
        return $this->resolvedAuthMode ?? $this->guessAuthMode();
    }

    /**
     * cPanel API tokens are long uppercase alphanumeric strings, passwords rarely are.
     * Used only to decide which scheme to try first when the mode is auto.
     *
     * @return string password|token
     */
    private function guessAuthMode()
    {
        return preg_match('/^[A-Z0-9]{16,}$/', (string) $this->cpanelPassword) ? 'token' : 'password';
    }

    /**
     * Authentication modes to attempt, in order.
     *
     * @return array
     */
    private function authModesToTry()
    {
        if (!empty($this->resolvedAuthMode)) return [$this->resolvedAuthMode];
        if ($this->authMode !== 'auto') return [$this->authMode];

        $preferred = $this->guessAuthMode();
        return [$preferred, $preferred === 'token' ? 'password' : 'token'];
    }

    /**
     * Build the Authorization header for the given mode.
     *
     * cPanel accepts either Basic auth (password) or its own token scheme:
     * Authorization: cpanel username:APITOKEN
     *
     * @param string $mode
     * @return string
     */
    private function authorizationHeader($mode)
    {
        if ($mode === 'token') {
            return 'Authorization: cpanel ' . $this->cpanelUsername . ':' . $this->cpanelPassword;
        }

        return 'Authorization: Basic ' . base64_encode($this->cpanelUsername . ':' . $this->cpanelPassword);
    }

    /**
     * Detect a rejected credential so we can retry with the other scheme.
     *
     * @param int $httpCode
     * @param string $responseText
     * @param array|null $response
     * @return bool
     */
    private function isAuthFailure($httpCode, $responseText, $response)
    {
        if (in_array((int) $httpCode, [401, 403])) return true;

        $haystack = strtolower((string) $responseText);
        if ($haystack === '') return false;

        foreach (['access denied', 'authentication', 'invalid login', 'login attempt', 'wrong username', 'the login is invalid', 'permission denied'] as $needle) {
            if (strpos($haystack, $needle) !== false) return true;
        }

        // The cPanel login page is served (as html) when the credential is not accepted on the API endpoints
        return strpos($haystack, '<html') !== false && strpos($haystack, 'login') !== false;
    }

    private function makeAPICall($module, $func, $params = [], $version = 'uapi')
    {
        $url = "https://{$this->cpanelDomain}:{$this->cpanelPort}/execute/{$module}/{$func}";

        if ($version !== 'uapi') {
            $url = "https://{$this->cpanelDomain}:{$this->cpanelPort}/json-api/cpanel?cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module={$module}&cpanel_jsonapi_func={$func}";
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

        $modes         = $this->authModesToTry();
        $response      = null;
        $response_text = '';
        $error         = '';
        $success       = false;

        foreach ($modes as $mode) {

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [$this->authorizationHeader($mode)]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $response_text = curl_exec($ch);
            $curl_error    = curl_error($ch);
            $http_code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response_text === false) {
                $error = empty($curl_error) ? 'Could not reach the panel.' : $curl_error;
                // A transport failure is not a credential problem, no point retrying the other scheme
                break;
            }

            $response = json_decode($response_text, true);

            $success = (int)($response['result']["status"][0] ?? ($response['status'] ?? 0)) === 1;
            if (!$success && $version !== 'uapi')
                $success = (int)($response['cpanelresult']['data'][0]['result'] ?? 0) === 1;

            if ($success) {
                $this->resolvedAuthMode = $mode;
                break;
            }

            $auth_failure = $this->isAuthFailure($http_code, $response_text, $response);

            $error = $response['errors'] ?? ($response['cpanelresult']['error'] ?? ($auth_failure ? 'The panel login is invalid. Check the username and the password/API token, and that the API token has the required permissions.' : $response_text));

            // Only the credential scheme is worth retrying, anything else is a real API error
            if (!$auth_failure) break;
        }

        if (!$success) {

            if (is_array($error))
                $error = implode(". ",  $error);

            if ($this->throwException) {
                throw new \Exception($error, 1);
            } else {
                log_message('error', $error);
            }
        }
        return $response;
    }

    private function makeAPICallv2($module, $func, $params = [])
    {
        return $this->makeAPICall($module, $func, $params, 'v2');
    }

    public function getDiskQuotas()
    {
        return $this->makeAPICall('Quota', 'get_local_quota_info');
    }

    /** Override the name prefix (e.g. '' to use exact, already-prefixed names). Reset by init(). */
    public function setPrefix($prefix)
    {
        $this->prefix = (string) $prefix;
        return $this;
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

    public function setDatabaseUserPassword($databaseUser, $databasePassword, $dbType = 'Mysql')
    {
        $params = [
            'user' => $this->addPrefix($databaseUser),
            'password' => $databasePassword,
        ];
        return $this->makeAPICall($dbType, 'set_password', $params);
    }

    public function listDatabases($dbType = 'Mysql')
    {
        return $this->makeAPICall($dbType, 'list_databases');
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
