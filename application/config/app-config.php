<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('app_env_value')) {
	/**
	 * Read environment values from runtime vars first, then from .env.
	 */
	function app_env_value($key, $default = null)
	{
		static $envLoaded = false;
		static $envMap = [];

		if (!$envLoaded) {
			$envLoaded = true;

			$candidates = [
				FCPATH . '.env',
				dirname(FCPATH) . '/.env',
			];

			foreach ($candidates as $envFile) {
				if (!is_readable($envFile)) {
					continue;
				}

				$lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if (!is_array($lines)) {
					continue;
				}

				foreach ($lines as $line) {
					$line = trim((string) $line);
					if ($line === '' || str_starts_with($line, '#')) {
						continue;
					}

					if (str_starts_with($line, 'export ')) {
						$line = trim(substr($line, 7));
					}

					$parts = explode('=', $line, 2);
					if (count($parts) !== 2) {
						continue;
					}

					$name = trim($parts[0]);
					$value = trim($parts[1]);
					if ($name === '') {
						continue;
					}

					if (
						strlen($value) >= 2
						&& (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
					) {
						$quote = $value[0];
						$value = substr($value, 1, -1);
						if ($quote === '"') {
							$value = stripcslashes($value);
						}
					}

					$envMap[$name] = $value;

					if (getenv($name) === false && !isset($_ENV[$name]) && !isset($_SERVER[$name])) {
						putenv($name . '=' . $value);
						$_ENV[$name] = $value;
						$_SERVER[$name] = $value;
					}
				}

				break;
			}
		}

		$runtime = getenv($key);
		if ($runtime !== false && $runtime !== null && $runtime !== '') {
			return $runtime;
		}

		if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
			return $_ENV[$key];
		}

		if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
			return $_SERVER[$key];
		}

		if (array_key_exists($key, $envMap) && $envMap[$key] !== '') {
			return $envMap[$key];
		}

		return $default;
	}
}

if (!function_exists('app_env_bool')) {
	function app_env_bool($key, $default = false)
	{
		$value = app_env_value($key, null);
		if ($value === null) {
			return (bool) $default;
		}

		$normalized = strtolower(trim((string) $value));
		if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}
		if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return (bool) $default;
	}
}
/*
* --------------------------------------------------------------------------
* Base Site URL
* --------------------------------------------------------------------------
*
* URL to your CodeIgniter root. Typically this will be your base URL,
* WITH a trailing slash:
*
*   http://example.com/
*
* If this is not set then CodeIgniter will try guess the protocol, domain
* and path to your installation. However, you should always configure this
* explicitly and never rely on auto-guessing, especially in production
* environments.
*
*/
define('APP_BASE_URL_DEFAULT', app_env_value('APP_BASE_URL_DEFAULT', app_env_value('APP_BASE_URL', 'https://healtho.pro/')));

/*
* --------------------------------------------------------------------------
* Encryption Key
* IMPORTANT: Do not change this ever!
* --------------------------------------------------------------------------
*
* If you use the Encryption class, you must set an encryption key.
* See the user guide for more info.
*
* http://codeigniter.com/user_guide/libraries/encryption.html
*
* Auto added on install
*/
define('APP_ENC_KEY', app_env_value('APP_ENC_KEY', '6fe10c0e0a3bb0654a50654a5f1fd556'));

/**
 * Database Credentials
 * The hostname of your database server
 */
define('APP_DB_HOSTNAME_DEFAULT', app_env_value('APP_DB_HOSTNAME_DEFAULT', app_env_value('APP_DB_HOSTNAME', 'localhost')));

/**
 * The username used to connect to the database
 */
define('APP_DB_USERNAME_DEFAULT', app_env_value('APP_DB_USERNAME_DEFAULT', app_env_value('APP_DB_USERNAME', 'healtho_pro')));

/**
 * The password used to connect to the database
 */
define('APP_DB_PASSWORD_DEFAULT', app_env_value('APP_DB_PASSWORD_DEFAULT', app_env_value('APP_DB_PASSWORD', '}ruz;b*35qw)#?SJ')));

/**
 * The name of the database you want to connect to
 */
define('APP_DB_NAME_DEFAULT', app_env_value('APP_DB_NAME_DEFAULT', app_env_value('APP_DB_NAME', 'healtho_pro')));

/**
 * @since  2.3.0
 * Database charset
 */
define('APP_DB_CHARSET', app_env_value('APP_DB_CHARSET', 'utf8mb4'));

/**
 * @since  2.3.0
 * Database collation
 */
define('APP_DB_COLLATION', app_env_value('APP_DB_COLLATION', 'utf8mb4_unicode_ci'));

/**
 *
 * Session handler driver
 * By default the database driver will be used.
 *
 * For files session use this config:
 * define('SESS_DRIVER', 'files');
 * define('SESS_SAVE_PATH', NULL);
 * In case you are having problem with the SESS_SAVE_PATH consult with your hosting provider to set "session.save_path" value to php.ini
 *
 */
define('SESS_DRIVER', app_env_value('SESS_DRIVER', 'database'));
define('SESS_SAVE_PATH', app_env_value('SESS_SAVE_PATH', 'sessions'));
define('APP_SESSION_COOKIE_SAME_SITE_DEFAULT', app_env_value('APP_SESSION_COOKIE_SAME_SITE_DEFAULT', app_env_value('APP_SESSION_COOKIE_SAME_SITE', 'Lax')));

/**
 * Enables CSRF Protection
 */
define('APP_CSRF_PROTECTION', app_env_bool('APP_CSRF_PROTECTION', true));//perfex-saas:start:app-config.php
//dont remove/change above line
require_once(FCPATH.'modules/perfex_saas/config/app-config.php');
//dont remove/change below line
//perfex-saas:end:app-config.php