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

    private function makeAPICall($module, $func, $params = [], $version = 'uapi')
    {
        $url = "https://{$this->cpanelDomain}:{$this->cpanelPort}/execute/{$module}/{$func}";
        $headers = [
            "Authorization: Basic " . base64_encode($this->cpanelUsername . ":" . $this->cpanelPassword) . "\n\r"
        ];

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
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response_text = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response_text, true);

        $success = (int)($response['result']["status"][0] ?? ($response['status'] ?? 0)) === 1;
        if (!$success && $version !== 'uapi')
            $success = (int)($response['cpanelresult']['data'][0]['result'] ?? 0) === 1;

        if (!$success) {

            $error = $response['errors'] ?? ($response['cpanelresult']['error'] ?? (strpos($response_text, 'login') !== false ? 'The panel login is invalid.' : $response_text));
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


    /**
     * Resolve the document root to hand to cPanel.
     *
     * Callers read the document root from an option that is very often empty
     * (the settings screen only *displays* FCPATH as a placeholder, it does not
     * store it until the tab is saved). An empty string still overrides a PHP
     * default argument, so cPanel used to fall back to its own behaviour and
     * point each new subdomain at an empty ~/public_html/<slug> folder, which
     * serves a bare 404 instead of the CRM. Default to the current install.
     *
     * cPanel also resolves `dir` against the account home directory, so an
     * absolute server path (FCPATH is one) can be re-rooted as
     * /home/<account>/home/<account>/public_html. Strip the home directory
     * prefix and pass the home-relative path cPanel expects.
     *
     * @param string $dir
     * @return string
     */
    public static function normalizeDocumentRoot($dir)
    {
        $dir = rtrim(trim(str_replace('\\', '/', (string) $dir)), '/');

        if ($dir === '') {
            $dir = rtrim(str_replace('\\', '/', FCPATH), '/');
        }

        if (preg_match('~^/home\d*/[^/]+/(.+)$~', $dir, $matches)) {
            $dir = $matches[1];
        }

        return '/' . trim($dir, '/') . '/';
    }

    public function createSubdomain($subdomain, $rootdomain, $dir = '', $disallowdot = 1)
    {
        $params = [
            'domain' => $subdomain,
            'rootdomain' => $rootdomain,
            'dir' => self::normalizeDocumentRoot($dir),
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

    public function createAddonDomain($domain, $subdomain, $dir = '')
    {

        $params = [
            'newdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => self::normalizeDocumentRoot($dir),
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
