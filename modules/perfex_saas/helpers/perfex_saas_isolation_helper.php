<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Staging / test DB isolation guard.
 *
 * healtho.pro (production) and a test copy such as healtho.tech live on the
 * same WHM, so they share one MySQL server at "localhost". A master DB copied
 * from production still holds every tenant's encrypted DSN — production DB
 * name, user and password — and APP_ENC_KEY is the same, so the copy decrypts
 * them and happily connects to the LIVE tenant databases.
 *
 * When isolation is active, any DSN that points at a production database
 * (cPanel prefix of the production account, e.g. "healtho_") is never used:
 * it is remapped to the same name under the staging account's prefix and the
 * staging master credentials. If the master DB itself is production while
 * isolation is active, the request is stopped outright.
 *
 * Isolation is active when ANY of these is true (production never matches):
 *   - CCX_DB_ISOLATION=1 in the env / .env.local
 *   - the request host is listed in CCX_STAGING_HOSTS
 *   - APP_BASE_URL's host is listed in CCX_STAGING_HOSTS (covers cron / CLI)
 *   - the master DB name is NOT under the production cPanel prefix
 *
 * Env knobs (all optional):
 *   CCX_PRODUCTION_DB_NAME    production master DB name   (default healtho_pro)
 *   CCX_PRODUCTION_DB_PREFIX  production cPanel DB prefix (default: derived, "healtho_")
 *   CCX_STAGING_DB_PREFIX     staging cPanel DB prefix    (default: derived from staging master DB)
 *
 * This file is loaded from perfex_saas_core_helper.php, before CI is ready —
 * only plain PHP here.
 */

if (!function_exists('ccx_iso_env')) {
    function ccx_iso_env(string $key, string $default = ''): string
    {
        if (function_exists('app_env_value')) {
            $v = app_env_value($key, null);
        } else {
            $v = getenv($key);
            if ($v === false) $v = $_ENV[$key] ?? ($_SERVER[$key] ?? null);
        }
        $v = is_string($v) ? trim($v) : '';
        return $v === '' ? $default : $v;
    }

    /** cPanel DB prefix of a name: "healtho_pro" → "healtho_" (cPanel usernames never contain "_"). */
    function ccx_iso_prefix_of(string $dbname): string
    {
        $pos = strpos($dbname, '_');
        return $pos === false ? '' : substr($dbname, 0, $pos + 1);
    }

    function ccx_iso_production_db_name(): string
    {
        return ccx_iso_env('CCX_PRODUCTION_DB_NAME', 'healtho_pro');
    }

    function ccx_iso_production_prefix(): string
    {
        return ccx_iso_env('CCX_PRODUCTION_DB_PREFIX', ccx_iso_prefix_of(ccx_iso_production_db_name()));
    }

    function ccx_iso_master_db_name(): string
    {
        return defined('APP_DB_NAME_DEFAULT') ? (string) APP_DB_NAME_DEFAULT : (defined('APP_DB_NAME') ? (string) APP_DB_NAME : '');
    }

    function ccx_iso_master_db_user(): string
    {
        return defined('APP_DB_USERNAME_DEFAULT') ? (string) APP_DB_USERNAME_DEFAULT : (defined('APP_DB_USERNAME') ? (string) APP_DB_USERNAME : '');
    }

    function ccx_iso_staging_hosts(): array
    {
        $raw = ccx_iso_env('CCX_STAGING_HOSTS');
        $list = array_filter(array_map('trim', explode(',', strtolower($raw))));
        $out = [];
        foreach ($list as $h) {
            $out[] = $h;
            if (strpos($h, 'www.') === 0) $out[] = substr($h, 4);
        }
        return array_values(array_unique($out));
    }

    function ccx_iso_normalize_host(string $host): string
    {
        $host = strtolower(trim(explode(':', $host, 2)[0]));
        return strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
    }

    /** True when a DB name or user belongs to the production cPanel account. */
    function ccx_iso_is_production_name(string $name): bool
    {
        $name = strtolower(trim($name));
        if ($name === '') return false;
        $prefix = strtolower(ccx_iso_production_prefix());
        if ($prefix === '') {
            return $name === strtolower(ccx_iso_production_db_name());
        }
        // "healtho_*" databases/users, or the bare cPanel account user "healtho"
        return strpos($name, $prefix) === 0 || $name === rtrim($prefix, '_');
    }

    function ccx_iso_master_is_production(): bool
    {
        return ccx_iso_is_production_name(ccx_iso_master_db_name())
            || ccx_iso_is_production_name(ccx_iso_master_db_user());
    }

    function ccx_iso_staging_prefix(): string
    {
        $explicit = ccx_iso_env('CCX_STAGING_DB_PREFIX');
        if ($explicit !== '') return $explicit;
        if (ccx_iso_master_is_production()) return '';
        return ccx_iso_prefix_of(ccx_iso_master_db_name());
    }

    /** Why isolation is active (empty array = not active = production behaviour). */
    function ccx_iso_reasons(): array
    {
        static $reasons = null;
        if ($reasons !== null) return $reasons;

        $reasons = [];
        $flag = strtolower(ccx_iso_env('CCX_DB_ISOLATION'));
        if (in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
            $reasons[] = 'CCX_DB_ISOLATION is on';
        }

        $staging = ccx_iso_staging_hosts();
        if (!empty($staging)) {
            $req = ccx_iso_normalize_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($req !== '' && in_array($req, $staging, true)) {
                $reasons[] = 'request host "' . $req . '" is in CCX_STAGING_HOSTS';
            }
            $base = defined('APP_BASE_URL_DEFAULT') ? (string) APP_BASE_URL_DEFAULT : ccx_iso_env('APP_BASE_URL');
            $baseHost = ccx_iso_normalize_host((string) parse_url($base, PHP_URL_HOST));
            if ($baseHost !== '' && in_array($baseHost, $staging, true)) {
                $reasons[] = 'APP_BASE_URL host "' . $baseHost . '" is in CCX_STAGING_HOSTS';
            }
        }

        $master = ccx_iso_master_db_name();
        if ($master !== '' && !ccx_iso_master_is_production()) {
            $reasons[] = 'master DB "' . $master . '" is not a production database';
        }

        return $reasons;
    }

    function ccx_iso_enabled(): bool
    {
        return !empty(ccx_iso_reasons());
    }

    function ccx_iso_record(string $context, string $from, string $to): void
    {
        $key = $context . '|' . $from;
        if (!isset($GLOBALS['ccx_iso_events'][$key])) {
            $GLOBALS['ccx_iso_events'][$key] = ['context' => $context, 'from' => $from, 'to' => $to];
            @error_log('[CCX DB isolation] ' . $context . ': production DB "' . $from . '" ' . ($to === '' ? 'BLOCKED' : 'remapped to "' . $to . '"'));
        }
    }

    /**
     * Decide what a DSN becomes under isolation, without connecting.
     * Returns ['status' => ok|remapped|blocked|off, 'dsn' => array, 'reason' => string].
     */
    function ccx_iso_resolve_dsn(array $dsn): array
    {
        $db   = (string) ($dsn['dbname'] ?? '');
        $user = (string) ($dsn['user'] ?? '');

        if (!ccx_iso_enabled()) {
            return ['status' => 'off', 'dsn' => $dsn, 'reason' => 'isolation not active (production)'];
        }
        if (!ccx_iso_is_production_name($db) && !ccx_iso_is_production_name($user)) {
            return ['status' => 'ok', 'dsn' => $dsn, 'reason' => 'not a production database'];
        }

        $stgPrefix  = ccx_iso_staging_prefix();
        $prodPrefix = ccx_iso_production_prefix();
        if ($stgPrefix === '' || strtolower($stgPrefix) === strtolower($prodPrefix)) {
            return ['status' => 'blocked', 'dsn' => $dsn, 'reason' => 'points at production and no staging DB prefix is known'];
        }

        if (ccx_iso_is_production_name($db) && $prodPrefix !== '' && stripos($db, $prodPrefix) === 0) {
            $newDb = $stgPrefix . substr($db, strlen($prodPrefix));
        } elseif (ccx_iso_is_production_name($db)) {
            $newDb = ccx_iso_master_db_name();
        } else {
            $newDb = $db; // only the user was production — keep name, swap credentials
        }

        // Never carry production credentials: use the staging master login.
        $out = $dsn;
        $out['dbname']   = $newDb;
        $out['host']     = defined('APP_DB_HOSTNAME_DEFAULT') ? APP_DB_HOSTNAME_DEFAULT : ($dsn['host'] ?? 'localhost');
        $out['user']     = ccx_iso_master_db_user();
        $out['password'] = defined('APP_DB_PASSWORD_DEFAULT') ? APP_DB_PASSWORD_DEFAULT : (defined('APP_DB_PASSWORD') ? APP_DB_PASSWORD : '');

        return ['status' => 'remapped', 'dsn' => $out, 'reason' => 'production DB remapped to staging'];
    }

    /**
     * Apply the guard to a DSN that is about to be connected to.
     * Throws when the DSN would reach production and cannot be remapped.
     */
    function ccx_iso_guard_dsn($dsn, string $context = 'connection')
    {
        if (!is_array($dsn) || empty($dsn['dbname'])) return $dsn;

        $r = ccx_iso_resolve_dsn($dsn);
        if ($r['status'] === 'remapped') {
            ccx_iso_record($context, (string) $dsn['dbname'], (string) $r['dsn']['dbname']);
            return $r['dsn'];
        }
        if ($r['status'] === 'blocked') {
            ccx_iso_record($context, (string) $dsn['dbname'], '');
            throw new \Exception('CCX DB isolation: refused to connect to production database "' . $dsn['dbname'] . '" from a staging/test environment.');
        }
        return $dsn;
    }

    /**
     * Stop the request when isolation is active but the master DB itself is
     * production (e.g. the shared .env with production credentials was deployed
     * to the test server). Called once from middleware_hooks.php.
     */
    function ccx_iso_enforce_master(): void
    {
        if (!ccx_iso_enabled() || !ccx_iso_master_is_production()) return;

        $files = $GLOBALS['app_env_loaded_files'] ?? [];
        $msg = 'This is a staging/test environment (' . implode('; ', ccx_iso_reasons()) . '), '
            . 'but its master database is the PRODUCTION database "' . ccx_iso_master_db_name() . '" '
            . '(user "' . ccx_iso_master_db_user() . '"). The request was stopped so production data is not touched. '
            . 'Env files loaded: ' . (empty($files) ? 'none' : implode(', ', array_map('basename', $files))) . '. '
            . 'Fix: create .env.local next to .env on this server with APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, '
            . 'APP_DB_NAME and APP_BASE_URL of THIS server.';
        @error_log('[CCX DB isolation] ' . $msg);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . PHP_EOL);
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><meta charset="utf-8"><title>Blocked: production database</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:760px;margin:60px auto;padding:24px;border:2px solid #b91c1c;border-radius:8px;background:#fef2f2;color:#7f1d1d">'
            . '<h2 style="margin-top:0">Blocked: staging is connected to the production database</h2>'
            . '<p style="line-height:1.6">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></div>';
        exit;
    }

    /** Snapshot for the CCX DB status panel. */
    function ccx_iso_status(): array
    {
        return [
            'enabled'            => ccx_iso_enabled(),
            'reasons'            => ccx_iso_reasons(),
            'request_host'       => ccx_iso_normalize_host((string) ($_SERVER['HTTP_HOST'] ?? '')),
            'base_url'           => defined('APP_BASE_URL_DEFAULT') ? APP_BASE_URL_DEFAULT : '',
            'staging_hosts'      => ccx_iso_staging_hosts(),
            'env_files'          => $GLOBALS['app_env_loaded_files'] ?? [],
            'master_db'          => ccx_iso_master_db_name(),
            'master_user'        => ccx_iso_master_db_user(),
            'master_is_prod'     => ccx_iso_master_is_production(),
            'production_db'      => ccx_iso_production_db_name(),
            'production_prefix'  => ccx_iso_production_prefix(),
            'staging_prefix'     => ccx_iso_staging_prefix(),
            'events'             => array_values($GLOBALS['ccx_iso_events'] ?? []),
        ];
    }
}
