<?php
/**
 * TEMPORARY DIAGNOSTIC — tenant subdomain routing.
 *
 * Answers one question first: does a tenant subdomain
 * (e.g. https://shra.clientcarex.com/) actually reach THIS folder?
 *
 *   • Page renders on the subdomain  → the subdomain reaches the CRM, and
 *     the fault is in app/DB config. Read the checks below.
 *   • Apache's own "Not Found" on the subdomain, while the same URL works on
 *     the master domain → the subdomain never reaches this folder at all.
 *     Nothing in this repository can fix that; the subdomain's DocumentRoot
 *     (or the missing wildcard vhost) has to be corrected in cPanel/Plesk.
 *
 * Usage:  https://<host>/saas-debug.php?key=<ACCESS_KEY>
 *         &host=shra.clientcarex.com   ← optional, resolve another host from
 *                                        the master domain
 *
 * DELETE THIS FILE once tenant subdomains are working.
 */

declare(strict_types=1);

/** Change this, or set CCX_DEBUG_KEY in .env, before uploading. */
const ACCESS_KEY = 'ccx-subdomain-7Qb3Rk92';

const COMPANIES_TABLE_SUFFIX = 'perfex_saas_companies';

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('X-Robots-Tag: noindex, nofollow', true);
header('Content-Type: text/html; charset=utf-8');

/* ---------------------------------------------------------------- .env --- */

/**
 * Minimal .env reader — the same file application/config/app-config.php uses,
 * parsed independently so this page still works when the CRM cannot boot.
 */
function env_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [];
    foreach ([__DIR__ . '/.env', dirname(__DIR__) . '/.env'] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        foreach ((array) @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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
            $value = trim($parts[1]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $map[trim($parts[0])] = $value;
        }
        $map['__FILE__'] = $file;
        break;
    }

    return $map;
}

function env(string $key, string $default = ''): string
{
    $map = env_map();
    foreach ([$key, str_replace('_DEFAULT', '', $key)] as $candidate) {
        if (isset($map[$candidate]) && $map[$candidate] !== '') {
            return $map[$candidate];
        }
        $runtime = getenv($candidate);
        if (is_string($runtime) && $runtime !== '') {
            return $runtime;
        }
    }

    return $default;
}

/* --------------------------------------------------------------- gate --- */

$expected = env('CCX_DEBUG_KEY', ACCESS_KEY);
if (($_GET['key'] ?? '') !== $expected) {
    http_response_code(404);
    exit('Not found');
}

/* ------------------------------------------------------------- helpers --- */

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalise_host(string $host): string
{
    $host = strtolower(trim($host));
    if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
        $host = (string) parse_url($host, PHP_URL_HOST);
    }
    $host = explode(':', $host, 2)[0];

    return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
}

$rows = [];
function row(string $status, string $label, string $value, string $note = ''): void
{
    global $rows;
    $rows[] = compact('status', 'label', 'value', 'note');
}

/* ------------------------------------------------- 1. where does this run - */

$here    = realpath(__DIR__) ?: __DIR__;
$docroot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$sameRoot = $docroot !== '' && rtrim($docroot, '/') === rtrim($here, '/');

row($sameRoot ? 'ok' : 'warn', 'DOCUMENT_ROOT', $docroot === '' ? '(empty)' : $docroot,
    $sameRoot
        ? 'The vhost serving this request points at the CRM folder.'
        : 'This vhost serves ' . $docroot . ' but the CRM lives in ' . $here .
          '. If that is the tenant subdomain, THIS is the fault — repoint its DocumentRoot.');

row('info', 'This file', $here . '/saas-debug.php');
row('info', 'Server', (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'));

foreach (['index.php', '.htaccess', 'application', 'modules/perfex_saas'] as $required) {
    $path = $here . '/' . $required;
    row(file_exists($path) ? 'ok' : 'fail', 'Present: ' . $required, file_exists($path) ? 'yes' : 'MISSING');
}

$modules = function_exists('apache_get_modules') ? apache_get_modules() : null;
if ($modules === null) {
    row('info', 'mod_rewrite', 'cannot be read from PHP under this SAPI',
        'Use the rewrite probe link at the bottom of this page instead.');
} else {
    $has = in_array('mod_rewrite', $modules, true);
    row($has ? 'ok' : 'fail', 'mod_rewrite', $has ? 'loaded' : 'NOT loaded',
        $has ? '' : 'Without it every clean URL, /admin/ included, 404s at Apache level.');
}

/* ------------------------------------------------------ 2. host analysis - */

$currentHost = normalise_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
$testedHost  = normalise_host((string) ($_GET['host'] ?? $currentHost));
$baseUrl     = env('APP_BASE_URL_DEFAULT', env('APP_BASE_URL', ''));
$masterHost  = normalise_host($baseUrl);

row('info', 'Request host', $currentHost === '' ? '(none)' : $currentHost);
row('info', 'Host being resolved', $testedHost === '' ? '(none)' : $testedHost,
    $testedHost === $currentHost ? '' : 'Overridden with ?host=');
row($masterHost === '' ? 'fail' : 'ok', 'APP_BASE_URL_DEFAULT host', $masterHost === '' ? '(unset)' : $masterHost,
    $masterHost === '' ? 'Set APP_BASE_URL_DEFAULT in .env — tenant detection is derived from it.' : '');
row(isset(env_map()['__FILE__']) ? 'ok' : 'warn', '.env file', env_map()['__FILE__'] ?? 'not found');

$slug = '';
$mode = 'unknown — APP_BASE_URL_DEFAULT is not readable';
if ($masterHost !== '' && $testedHost !== '') {
    $mode = 'master';
    if ($testedHost === $masterHost) {
        $mode = 'master';
    } elseif (str_ends_with($testedHost, '.' . $masterHost)) {
        $slug = trim(str_ireplace($masterHost, '', $testedHost), '.');
        $mode = str_contains($slug, '.') ? 'invalid-subdomain' : 'subdomain';
    } else {
        $mode = 'custom-domain';
    }
}

row($mode === 'invalid-subdomain' ? 'fail' : 'info', 'Resolved as', $mode,
    $mode === 'subdomain' ? 'Tenant slug: ' . $slug
        : ($mode === 'invalid-subdomain'
            ? 'Multi-level subdomains are rejected by the SaaS module.'
            : ($mode === 'custom-domain' ? 'Looked up against companies.custom_domain.' : '')));

/* --------------------------------------------------------- 3. the tenant - */

$tenants = [];
$match   = null;
$dbError = '';

$dbHost = env('APP_DB_HOSTNAME_DEFAULT', 'localhost');
$dbUser = env('APP_DB_USERNAME_DEFAULT');
$dbPass = env('APP_DB_PASSWORD_DEFAULT');
$dbName = env('APP_DB_NAME_DEFAULT');

if ($dbName === '' || $dbUser === '') {
    $dbError = 'No database credentials in .env — falling back is not attempted here.';
} else {
    mysqli_report(MYSQLI_REPORT_OFF);
    $link = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
    if (!$link) {
        $dbError = 'Connection failed: ' . mysqli_connect_error();
    } else {
        row('ok', 'Master database', $dbName . ' @ ' . $dbHost);

        $table = null;
        $res = @mysqli_query($link, "SHOW TABLES LIKE '%" . COMPANIES_TABLE_SUFFIX . "'");
        if ($res && ($first = mysqli_fetch_row($res))) {
            $table = $first[0];
        }

        if ($table === null) {
            $dbError = 'Table *' . COMPANIES_TABLE_SUFFIX . ' does not exist — the SaaS module is not installed on this database.';
        } else {
            row('ok', 'Tenants table', $table);

            $res = @mysqli_query(
                $link,
                'SELECT `id`, `slug`, `name`, `status`, `custom_domain`, ' .
                "(CASE WHEN `dsn` IS NULL OR `dsn` = '' THEN 0 ELSE 1 END) AS has_dsn " .
                "FROM `$table` ORDER BY `id` DESC LIMIT 100"
            );
            while ($res && ($tenant = mysqli_fetch_assoc($res))) {
                $tenants[] = $tenant;
                $isMatch = ($mode === 'subdomain' && strcasecmp((string) $tenant['slug'], $slug) === 0)
                    || ($mode === 'custom-domain' && normalise_host((string) $tenant['custom_domain']) === $testedHost);
                if ($isMatch && $match === null) {
                    $match = $tenant;
                }
            }
        }
        mysqli_close($link);
    }
}

if ($dbError !== '') {
    row('fail', 'Master database', $dbError);
} elseif ($mode === 'subdomain' || $mode === 'custom-domain') {
    if ($match === null) {
        row('fail', 'Tenant record', 'no row for "' . ($mode === 'subdomain' ? $slug : $testedHost) . '"',
            'The SaaS module answers this host with its own "Invalid Tenant" page, not an Apache 404.');
    } else {
        row($match['status'] === 'active' ? 'ok' : 'warn', 'Tenant record',
            '#' . $match['id'] . ' ' . $match['name'] . ' — status: ' . $match['status'],
            $match['status'] === 'active' ? '' : 'Only "active" instances serve traffic.');
        row($match['has_dsn'] ? 'ok' : 'fail', 'Tenant DSN', $match['has_dsn'] ? 'stored' : 'EMPTY',
            $match['has_dsn'] ? '' : 'Without a DSN the instance cannot connect to its own database.');
    }
}

/* ------------------------------------------------------------- verdict --- */

$verdict = $sameRoot
    ? 'This request reached the CRM folder, so rewriting and vhost mapping are fine for ' . h($currentHost) .
      '. Work through any red rows below.'
    : 'This request did NOT reach the CRM folder. Point this host\'s DocumentRoot at ' . h($here) . '.';

$counts = ['fail' => 0, 'warn' => 0];
foreach ($rows as $r) {
    if (isset($counts[$r['status']])) {
        $counts[$r['status']]++;
    }
}
?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SaaS subdomain diagnostic</title>
<style>
  :root { color-scheme: light dark; --bg:#fff; --fg:#16181d; --muted:#666; --line:#e3e5e9; --card:#f7f8fa; }
  @media (prefers-color-scheme: dark) { :root { --bg:#14161a; --fg:#e8eaed; --muted:#9aa0a6; --line:#2a2e35; --card:#1c1f25; } }
  body { background:var(--bg); color:var(--fg); font:15px/1.55 ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,sans-serif; margin:0; padding:32px 20px; }
  main { max-width:920px; margin:0 auto; }
  h1 { font-size:20px; margin:0 0 4px; }
  p.sub { color:var(--muted); margin:0 0 24px; }
  .verdict { background:var(--card); border:1px solid var(--line); border-left:4px solid #d93025; padding:14px 16px; border-radius:6px; margin-bottom:24px; }
  .verdict.ok { border-left-color:#188038; }
  table { border-collapse:collapse; width:100%; font-size:14px; }
  th, td { text-align:left; padding:9px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  td.s { width:64px; font-weight:600; }
  .ok { color:#188038; } .fail { color:#d93025; } .warn { color:#b06000; } .info { color:var(--muted); }
  td.v { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; word-break:break-all; }
  .note { color:var(--muted); font-size:13px; display:block; margin-top:3px; font-family:inherit; }
  h2 { font-size:15px; margin:32px 0 10px; }
  code { background:var(--card); padding:1px 5px; border-radius:3px; }
</style>
<main>
  <h1>Tenant subdomain diagnostic</h1>
  <p class="sub">Temporary page — delete <code>saas-debug.php</code> when the subdomains work.</p>

  <div class="verdict<?= $sameRoot ? ' ok' : '' ?>">
    <?= $verdict ?>
    <?php if ($counts['fail'] || $counts['warn']): ?>
      <br><?= $counts['fail'] ?> failing, <?= $counts['warn'] ?> warning check(s).
    <?php endif; ?>
  </div>

  <table>
    <tr><th class="s">State</th><th>Check</th><th>Value</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="s <?= h($r['status']) ?>"><?= h(strtoupper($r['status'])) ?></td>
        <td><?= h($r['label']) ?></td>
        <td class="v"><?= h($r['value']) ?>
          <?php if ($r['note'] !== ''): ?><span class="note"><?= h($r['note']) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($tenants): ?>
    <h2>Instances on this installation (newest 100)</h2>
    <table>
      <tr><th>ID</th><th>Slug</th><th>Expected URL</th><th>Status</th><th>Custom domain</th><th>DSN</th></tr>
      <?php foreach ($tenants as $t): ?>
        <tr>
          <td><?= h((string) $t['id']) ?></td>
          <td class="v"><?= h((string) $t['slug']) ?></td>
          <td class="v"><?= h($masterHost === '' ? '—' : 'https://' . $t['slug'] . '.' . $masterHost . '/') ?></td>
          <td class="<?= $t['status'] === 'active' ? 'ok' : 'warn' ?>"><?= h((string) $t['status']) ?></td>
          <td class="v"><?= h((string) ($t['custom_domain'] ?: '—')) ?></td>
          <td class="<?= $t['has_dsn'] ? 'ok' : 'fail' ?>"><?= $t['has_dsn'] ? 'yes' : 'no' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2>Next probes</h2>
  <ul>
    <li>Rewrite probe — <a href="/__ccx_rewrite_probe">/__ccx_rewrite_probe</a>. A CRM-styled 404 means
        mod_rewrite reached CodeIgniter; Apache's plain “Not Found” means it did not.</li>
    <li>Open this same page on a tenant host, e.g.
        <code>https://&lt;slug&gt;.<?= h($masterHost ?: 'example.com') ?>/saas-debug.php?key=<?= h($expected) ?></code>.
        Apache's own 404 there is the whole answer: that subdomain is not served from this folder.</li>
    <li>Resolve a host without visiting it — append
        <code>&amp;host=&lt;slug&gt;.<?= h($masterHost ?: 'example.com') ?></code> to this URL.</li>
  </ul>
</main>
