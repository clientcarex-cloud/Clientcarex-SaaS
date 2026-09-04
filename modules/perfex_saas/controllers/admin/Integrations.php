<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Integrations extends AdminController
{
    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Recover a posted value exactly as the browser sent it.
     *
     * config.php sets global_xss_filtering = true, so CI has already run
     * xss_clean() over $_POST by the time a controller sees it. That rewrites
     * things like "&", "<" and entity-looking sequences - harmless for a domain
     * name, silently destructive for a cPanel password. Reading php://input
     * gives us the untouched value so we can both test with it and tell the
     * admin when filtering changed it.
     *
     * @param string $key setting key inside settings[...]
     * @return string|null null when the raw body could not be read
     */
    protected function raw_posted_setting($key)
    {
        static $raw = null;

        if ($raw === null) {
            $body = file_get_contents('php://input');
            $raw  = [];
            if (is_string($body) && $body !== '') {
                parse_str($body, $raw);
            }
        }

        if (!isset($raw['settings']) || !is_array($raw['settings'])) {
            return null;
        }

        return array_key_exists($key, $raw['settings']) ? (string) $raw['settings'][$key] : null;
    }

    public function test_cpanel()
    {
        $config = $this->input->post('settings', true);

        $step        = 'reading the submitted settings';
        $diagnostics = [];
        $notes       = [];

        try {

            if (empty($config)) {
                throw new \Exception(_l('perfex_saas_empty_data'), 1);
            }

            $this->load->library(PERFEX_SAAS_MODULE_NAME . '/integrations/cpanel_api');
            if (!function_exists('random_string')) {
                $this->load->helper('string');
            }

            $username = trim((string) ($config['perfex_saas_cpanel_username'] ?? ''));
            $password = (string) ($config['perfex_saas_cpanel_password'] ?? '');
            $domain   = (string) ($config['perfex_saas_cpanel_login_domain'] ?? '');
            $port     = (string) ($config['perfex_saas_cpanel_port'] ?? '');

            // Test with the password the browser actually sent, not the
            // xss_clean()'d copy - see raw_posted_setting().
            $raw_password = $this->raw_posted_setting('perfex_saas_cpanel_password');
            if ($raw_password !== null && $raw_password !== $password) {
                $notes[] = 'The password was altered by the XSS filter before it reached this test (' . strlen($password) . ' chars after filtering vs ' . strlen($raw_password) . ' sent). The unfiltered value was used for this test, but the SAVED password is filtered the same way - if this test passes and live deployments still fail, that filtering is your bug.';
                $password = $raw_password;
            }

            if ($username === '') {
                throw new \Exception('No cPanel username was submitted.', 1);
            }
            if ($password === '') {
                throw new \Exception('No cPanel password was submitted. Re-type it in the form before testing - the field is not repopulated from a failed save.', 1);
            }
            if ($domain === '') {
                throw new \Exception('No cPanel login domain was submitted.', 1);
            }

            $db_prefix = $config['perfex_saas_cpanel_db_prefix'] ?? '';
            $db_prefix = empty($db_prefix) ? $username : $db_prefix;
            $prefix = (empty($db_prefix) ? '' : $db_prefix . '_') . PERFEX_SAAS_MODULE_NAME_SHORT . '_';

            /** @var Cpanel_api $cpanel */
            $cpanel = $this->cpanel_api->init(
                $username,
                $password,
                $domain,
                $port,
                $prefix
            );

            // Warn about the settings that most often produce a login page
            // instead of an API response.
            $host = $cpanel->normalizedHost();
            $used_port = $cpanel->normalizedPort();

            if ($host !== trim($domain)) {
                $notes[] = 'The login domain "' . $domain . '" was normalised to "' . $host . '". Store the bare hostname to avoid ambiguity.';
            }
            if (in_array($used_port, ['2087', '2086'], true)) {
                $notes[] = 'Port ' . $used_port . ' is WHM, not cPanel. This integration speaks the cPanel UAPI, which listens on 2083 (2082 without SSL).';
            }
            if (strtolower($username) === 'root') {
                $notes[] = 'The username is "root". WHM/root credentials do not authenticate against the cPanel UAPI - use the cPanel account user that owns the hosting account.';
            }
            if (strpos($username, '@') !== false) {
                $notes[] = 'The username looks like an email address. cPanel expects the account user name, not an email login.';
            }

            // An empty document root makes cPanel invent /public_html/<slug>, so
            // the created vhost never reaches this installation. Mirror the
            // fallback used when tenants are deployed.
            $root_dir = trim((string) get_option('perfex_saas_cpanel_document_root'));
            $root_dir = $root_dir === '' ? FCPATH : $root_dir;
            $primarydomain = $config['perfex_saas_cpanel_primary_domain'] ?? '';

            //test creating subdomain and database and its removal
            $slug = 'test' . date('ymd');

            $db_password = random_string('alnum', 16);
            $db_user = $cpanel->addPrefix($slug);
            $db_name = $cpanel->addPrefix($slug);

            // Authenticate first, on a read-only call. When the credentials are
            // the problem this reports it against a harmless endpoint instead
            // of blaming whichever create/delete happened to run first.
            $step = 'authenticating against ' . $host . ':' . $used_port . ' as "' . $username . '" (Quota::get_local_quota_info)';
            $cpanel->ping();
            $diagnostics[] = $cpanel->lastDiagnostics;

            // try to delete if already created
            try {
                $cpanel->deleteDatabase($db_name);
                $cpanel->deleteDatabaseUser($db_user);
            } catch (\Throwable $th) {
                //throw $th;
            }

            $step = 'creating test database "' . $db_name . '"';
            $cpanel->createDatabase($db_name);

            $step = 'creating test database user "' . $db_user . '"';
            $cpanel->createDatabaseUser($db_user, $db_password);

            $step = 'granting privileges on "' . $db_name . '" to "' . $db_user . '"';
            $cpanel->setDatabaseUserPrivileges($db_user, $db_name);

            $step = 'removing test database "' . $db_name . '"';
            $cpanel->deleteDatabase($db_name);

            $step = 'removing test database user "' . $db_user . '"';
            $cpanel->deleteDatabaseUser($db_user);


            // If addon domain enabled
            $alias_enabled = (int)($config['perfex_saas_cpanel_enable_addondomain'] ?? 0);
            if ($alias_enabled) {
                try {
                    $cpanel->deleteSubdomain($slug, $primarydomain);
                } catch (\Throwable $th) {
                    //throw $th;
                }
                $step = 'creating test subdomain "' . $slug . '.' . $primarydomain . '"';
                $cpanel->createSubdomain($slug, $primarydomain, $root_dir);

                $step = 'removing test subdomain "' . $slug . '.' . $primarydomain . '"';
                $cpanel->deleteSubdomain($slug, $primarydomain);
            }

            echo json_encode([
                'status' => 'success',
                'message' => _l('perfex_saas_integration_connection_success'),
                'details' => empty($notes) ? '' : "Warnings:\n - " . implode("\n - ", $notes),
            ]);
            exit;
        } catch (\Throwable $th) {

            $detail = 'Failed while ' . $step . '.' . "\n\n" . $th->getMessage();

            if (!empty($notes)) {
                $detail .= "\n\nAlso worth checking:\n - " . implode("\n - ", $notes);
            }

            $detail .= "\n\n" . 'Thrown by ' . str_replace(FCPATH, '', $th->getFile()) . ':' . $th->getLine();

            log_message('error', 'perfex_saas cpanel test failed: ' . str_replace("\n", ' | ', $detail));

            echo json_encode([
                'status' => 'danger',
                'message' => 'cPanel test failed while ' . $step . '.',
                'details' => $detail,
                'diagnostics' => isset($this->cpanel_api) ? $this->cpanel_api->lastDiagnostics : [],
                'trace' => $th->getTraceAsString()
                // A panel login page can carry latin-1 bytes; without the
                // substitute flag json_encode() returns false and the browser
                // gets an empty body instead of the diagnosis.
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
    }

    public function test_plesk()
    {
        $config = $this->input->post('settings', true);

        try {

            if (empty($config)) {
                throw new \Exception(_l('perfex_saas_empty_data'), 1);
            }

            $this->load->library(PERFEX_SAAS_MODULE_NAME . '/integrations/plesk_api');
            if (!function_exists('random_string')) {
                $this->load->helper('string');
            }

            $prefix = PERFEX_SAAS_MODULE_NAME_SHORT . '_';

            /** @var Plesk_api $plesk */
            $plesk = $this->plesk_api->init(
                $config['perfex_saas_plesk_host'],
                $config['perfex_saas_plesk_primary_domain'],
                $config['perfex_saas_plesk_username'],
                $config['perfex_saas_plesk_password'],
                $prefix
            );

            $app_base_host = perfex_saas_get_saas_default_host();
            $primarydomain = $config['perfex_saas_plesk_primary_domain'];

            //test creating subdomain and database and its removal
            $slug = 'test-' . PERFEX_SAAS_MODULE_NAME_SHORT . '-' . date('y');
            $subdomain = $slug . '.' . $app_base_host;
            if (perfex_saas_host_is_local($app_base_host, true))
                $subdomain = $slug . '.' . $primarydomain;

            $db_password = random_string('alnum', 16);
            $db_user = $plesk->addPrefix($slug);
            $db_name = $plesk->addPrefix($slug);

            // Test database and user
            try {
                $plesk->deleteDatabase($db_name);
            } catch (\Throwable $th) {
            }
            $plesk->createDatabaseWithUser($db_user, $db_password, $db_name);
            $plesk->deleteDatabase($db_name);

            // Test alias creation
            $alias_enabled = (int)($config['perfex_saas_plesk_enable_aliasdomain'] ?? 0);
            if ($alias_enabled) {
                try {
                    $plesk->deleteSiteAlias($subdomain);
                } catch (\Throwable $th) {
                }
                $plesk->createSiteAlias($subdomain);
                $plesk->deleteSiteAlias($subdomain);
            }

            echo json_encode([
                'status' => 'success',
                'message' => _l('perfex_saas_integration_connection_success')
            ]);
            exit;
        } catch (\Throwable $th) {

            echo json_encode([
                'status' => 'danger',
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            exit;
        }
    }

    public function test_mysql_root()
    {
        $config = $this->input->post('settings', true);

        try {

            if (empty($config)) {
                throw new \Exception(_l('perfex_saas_empty_data'), 1);
            }

            $this->load->library(PERFEX_SAAS_MODULE_NAME . '/integrations/mysql_root_api');
            if (!function_exists('random_string')) {
                $this->load->helper('string');
            }

            $prefix = PERFEX_SAAS_MODULE_NAME_SHORT . '_';

            /** @var Mysql_root_api $mysql_root */
            $mysql_root = $this->mysql_root_api->init(
                $config['perfex_saas_mysql_root_username'],
                $config['perfex_saas_mysql_root_password'],
                $config['perfex_saas_mysql_root_host'],
                $config['perfex_saas_mysql_root_port'],
                $prefix
            );

            // Test db create and removal
            $separateUserEnabled = $config['perfex_saas_mysql_root_enable_separate_user'] == '1';
            $mysql_root->testConnection($separateUserEnabled);

            echo json_encode([
                'status' => 'success',
                'message' => _l('perfex_saas_integration_connection_success')
            ]);
            exit;
        } catch (\Throwable $th) {

            echo json_encode([
                'status' => 'danger',
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            exit;
        }
    }
}
