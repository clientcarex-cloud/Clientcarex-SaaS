<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * This is a common class for managing magic authentications
 */
class Authentication extends ClientsController
{
    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Method to login into an instance magically from the client dashboard.
     * It create auto login cookie (used by perfex core) and redirect to the company admin address.
     * Perfex pick the cookie and authorized. The cookie is localized to the company address only and inserted into db using the instance context.
     * Also when retrieving the cookie from db, the db_simple_query restrict the selection to the instance.
     *
     * @param string $slug
     * @return void
     */
    public function magic_auth($slug, $urlmode = 'path')
    {
        // Ensure we have an authenticated client
        if (!is_client_logged_in() || perfex_saas_is_tenant()) {
            perfex_saas_show_tenant_error(_l('perfex_saas_permission_denied'), _l('perfex_saas_authentication_required_for_magic_login'), 404);
        }

        $company = $this->perfex_saas_model->get_entity_by_slug('companies', $slug, 'parse_company');
        if (!$company) {
            perfex_saas_show_tenant_error(_l('perfex_saas_permission_denied'), _l('perfex_saas_page_not_found'), 404);
        }

        // Ensure the company belongs to the logged in client
        if ($company->clientid !== get_client_user_id()) {
            perfex_saas_show_tenant_error(_l('perfex_saas_permission_denied'), '');
        }

        // Ensure the contact have access to login
        if (!perfex_saas_contact_can_magic_auth($company)) {
            perfex_saas_show_tenant_error(_l('perfex_saas_permission_denied'), '');
        }

        $auth_code = perfex_saas_generate_magic_auth_code($company->clientid);

        $support_custom_domain_magic_login = get_option('perfex_saas_enable_cross_domain_bridge') == "1";
        $links = perfex_saas_tenant_base_url($company, '', 'all');

        // On non-production hosts (detected via .env), force path-mode magic auth
        // and rebuild the path URL from the current request origin to stay on this server.
        $is_staging_host = $this->should_force_path_magic_auth();
        if ($is_staging_host) {
            $urlmode = 'path';
        }

        if ($urlmode == 'all' || !isset($links[$urlmode])) {
            $urlmode = 'subdomain';
            if (!empty($links['custom_domain']) && $support_custom_domain_magic_login)
                $urlmode = 'custom_domain';
        }

        // Rebuild path URL from the current server origin to ensure the redirect
        // stays on this server and doesn't follow APP_BASE_URL_DEFAULT to another host.
        if ($is_staging_host && $urlmode === 'path') {
            $current_origin = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
            $slug = !empty($company->slug) ? perfex_saas_clean_slug($company->slug, 'url') : '';
            $tenant_sig = perfex_saas_tenant_url_signature($slug);
            $links['path'] = rtrim($current_origin, '/') . '/' . $tenant_sig . '/';
        }

        // Improve experience in newly created instance on cpanel/plesk that might not have subdomain SSL fully setup.
        if ((time() - strtotime($company->created_at)) < 60 * 10 && $urlmode != 'path') { // if created less than 20min
            $package = $this->perfex_saas_model->get_company_invoice($company->clientid);
            if (!empty($package->db_scheme) && in_array($package->db_scheme, ['cpanel', 'plesk'])) {

                $can_optimize = false;

                if ($package->db_scheme === 'cpanel')
                    $can_optimize = perfex_saas_cpanel_enable_addondomain();

                if ($package->db_scheme === 'plesk')
                    $can_optimize = perfex_saas_plesk_enable_aliasdomain();

                $integration_mode = get_option('perfex_saas_' . $package->db_scheme . '_addondomain_mode');
                if ($can_optimize && $integration_mode) {
                    if (!in_array($integration_mode, ['all', 'subdomain']))
                        $can_optimize = false;
                }

                if ($can_optimize)
                    $urlmode = 'path';
            }
        }

        $redirect = empty($links[$urlmode]) ? $links['path'] : $links[$urlmode];

        $query = 'billing/my_account/magic_auth?auth_code=' . urlencode($auth_code);
        if (!empty($redirect_query = $this->input->get('redirect', true)))
            $query .= '&redirect=' . urlencode($redirect_query);

        // Propagate CCX view-only flag through magic auth chain
        if ($this->session->userdata('ccx_view_only_mode')) {
            $query .= '&ccx_view_only=1';
        } else {
            // Explicitly signal edit mode to clear any stale view-only state on tenant
            $query .= '&ccx_view_only=0';
        }

        return redirect($redirect . $query);
    }

    /**
     * Force path-based magic auth when the current host differs from the
     * configured production base URL. This covers staging servers automatically
     * without any hardcoded domain list — driven entirely by .env values.
     */
    private function should_force_path_magic_auth(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }

        $host = explode(':', $host, 2)[0];

        // Method 1: Explicit staging hosts from env
        if (function_exists('ccx_runtime_staging_hosts')) {
            $stagingHosts = ccx_runtime_staging_hosts();
            if (!empty($stagingHosts) && in_array($host, $stagingHosts, true)) {
                return true;
            }
        }

        // Method 2: If current host doesn't match APP_BASE_URL_DEFAULT host,
        // we're on a non-production server (staging/dev) — force path mode.
        $defaultHost = strtolower((string) parse_url(APP_BASE_URL_DEFAULT, PHP_URL_HOST));
        $defaultHost = ltrim($defaultHost, 'www.');
        $checkHost   = ltrim($host, 'www.');

        return $checkHost !== '' && $defaultHost !== '' && $checkHost !== $defaultHost;
    }

    /**
     * Authenticate a tenant admin into the Saas client portal.
     *
     * This method signs in a tenant admin into the Saas client portal, allowing them to access client-specific features from the instance.
     *
     * @return void
     */
    public function client_magic_auth()
    {

        try {
            // Check if the user is a tenant or if the client bridge is not enabled
            if (perfex_saas_is_tenant() || get_option('perfex_saas_enable_client_bridge') !== "1") {
                throw new \Exception(_l('perfex_saas_permission_denied'), 1);
            }

            // Determine the redirect URL or use a default if not provided
            $redirect = $this->input->get('redirect', true) ?? 'clients/my_account';

            // Read unfiltered — the token is authenticated by decryption, and XSS
            // cleaning would only risk mangling the ciphertext.
            $actor = perfex_saas_parse_portal_actor_token($this->input->get('actor'));

            // If the client is already logged in with a magic code, redirect
            if (is_client_logged_in() && $this->session->has_userdata('magic_code')) {
                $this->store_portal_actor($actor, get_client_user_id());

                return redirect($redirect);
            }

            // Validate and authorize the magic authentication code
            $clientid = perfex_saas_validate_and_authorize_magic_auth_code();
            $contact = perfex_saas_get_primary_contact($clientid);

            if (!$contact) {
                throw new \Exception(_l('perfex_saas_error_finding_primary_contact'), 1);
            }

            // Sign in as a client in Saas (i.e., superclient)
            login_as_client($contact->userid);
            $this->store_portal_actor($actor, $clientid);
            $user_data = [
                'magic_auth'       => [
                    'cross_domain' => (int)$this->input->get('cross_domain', true),
                    'source_url' => $this->input->get('source_url', true)
                ],
            ];
            $this->session->set_userdata($user_data);

            return redirect($redirect);
        } catch (\Throwable $th) {
            perfex_saas_show_tenant_error(_l('perfex_saas_authentication_error'), $th->getMessage());
        }
    }

    /**
     * Remember WHICH tenant staff member is behind this portal session.
     *
     * Everyone arriving over the bridge is signed in as the customer's primary
     * contact, so this is the only trace of the real person; modules that record
     * authorship (Pro Tickets) read it back from the session. Always (re)written
     * — including to null — so one bridge hop can never inherit the identity of
     * the previous one, and tagged with the customer it belongs to so a stale
     * entry can never be applied to somebody else's portal session.
     *
     * @param array|null $actor
     * @param int|string $clientid
     * @return void
     */
    private function store_portal_actor($actor, $clientid)
    {
        if (is_array($actor)) {
            $actor['clientid'] = (int) $clientid;
        }

        $this->session->set_userdata('perfex_saas_portal_actor', $actor);
    }

    /**
     * Auto-magic login into the current tenant instance as an admin using a magic code set from another instance.
     *
     * This method allows switching to another instance from the current one by automatically signing in as a tenant admin.
     *
     * @return void
     */
    public function tenant_admin_magic_auth()
    {
        try {

            // Check if the user is not a tenant or if instance switching is not enabled
            if (!perfex_saas_is_tenant()) {
                throw new \Exception(_l('perfex_saas_permission_denied'), 1);
            }

            // Determine the redirect URL or use a default if not provided
            $redirect = $this->input->get('redirect', true) ?? '';

            // If the user is already an admin, redirect to the admin dashboard
            if (is_admin()) {
                return redirect(admin_url($redirect));
            }

            // Validate and authorize the magic authentication code
            $clientid = perfex_saas_validate_and_authorize_magic_auth_code();

            // Ensure that the client matches the current tenant instance
            if ((int)perfex_saas_tenant()->clientid !== $clientid) {
                throw new \Exception(_l('perfex_saas_permission_denied'), 1);
            }

            // Sign in as the current tenant instance's admin
            perfex_saas_tenant_admin_autologin();

            // Propagate CCX view-only mode into tenant via cookie
            $ccx_vo = $this->input->get('ccx_view_only');
            if ($ccx_vo === '1' || $ccx_vo === '0') {
                $this->load->helper('cookie');
                set_cookie([
                    'name'     => 'ccx_view_only',
                    'value'    => $ccx_vo,
                    'expire'   => 300, // 5 minutes — picked up by hook on first admin load
                    'path'     => '/',
                    'httponly'  => true,
                ]);
            }

            $redirect .=  (empty($redirect) ? '?' : '&') . '_magic_auth_session';
            return redirect(admin_url($redirect));
        } catch (\Throwable $th) {
            perfex_saas_show_tenant_error(_l('perfex_saas_authentication_error'), $th->getMessage());
        }
    }
}