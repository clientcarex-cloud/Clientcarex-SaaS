<?php
/**
 * ClientcareX — single front controller.
 *
 * Every request lands here: it resolves a route from the page registry,
 * renders it through one layout, and serves it from the full-page cache
 * whenever possible.
 */
declare(strict_types=1);

// `php -S localhost:4173 index.php` routes every request here, assets included.
if (PHP_SAPI === 'cli-server') {
    $file = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (str_starts_with($file, '/assets/') && is_file(__DIR__ . $file)) {
        return false; // let the built-in server deliver it
    }
}

define('ROOT', __DIR__);
// Install directory, so the site also runs from a sub-folder.
define('BASE', PHP_SAPI === 'cli-server'
    ? ''
    : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

ini_set('zlib.output_compression', '0'); // we compress once, at cache-write time

require ROOT . '/app/config.php';
require ROOT . '/app/helpers.php';
require ROOT . '/app/icons.php';
require ROOT . '/app/content.php';
require ROOT . '/app/response.php';

/** Path portion of the request, with the install directory and slashes trimmed. */
function request_route(): string
{
    $path = rawurldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
    if (BASE !== '' && str_starts_with($path, BASE)) {
        $path = substr($path, strlen(BASE));
    }

    return trim($path, '/');
}

$route = request_route();

/* ---- Redirects: keep the old static URLs working ---------------------- */
if (str_ends_with($route, '.html')) {
    $legacy = substr($route, 0, -5);
    $route  = $legacy === 'index' ? '' : $legacy;
    if (isset(PAGES[$route]) || isset(REDIRECTS[$route])) {
        header('Location: ' . url(REDIRECTS[$route] ?? $route), true, 301);
        exit;
    }
}

// Routes retired when the business model changed — send them to their successor.
if (isset(REDIRECTS[$route])) {
    header('Location: ' . url(REDIRECTS[$route]), true, 301);
    exit;
}
if ($route !== '' && str_ends_with((string) strtok($_SERVER['REQUEST_URI'] ?? '', '?'), '/')) {
    header('Location: ' . url($route), true, 301);
    exit;
}

/* ---- Generated text files -------------------------------------------- */
if ($route === 'sitemap.xml' || $route === 'robots.txt') {
    $body = view('feeds/' . ($route === 'robots.txt' ? 'robots' : 'sitemap'));
    emit($body, '"' . substr(sha1($body), 0, 20) . '"', 200, false,
        $route === 'robots.txt' ? 'text/plain; charset=UTF-8' : 'application/xml; charset=UTF-8');
    exit;
}

/* ---- Page lookup ------------------------------------------------------ */
$page      = PAGES[$route] ?? null;
$status    = $page === null ? 404 : 200;
$cacheable = $page !== null && ($page['cache'] ?? true);

if ($cacheable && $_SERVER['REQUEST_METHOD'] === 'GET' && cache_serve($route)) {
    exit;
}

if ($page === null) {
    $route = '404';
    $page  = PAGES['404'];
}

/* ---- Growth-audit enquiry -------------------------------------------- */
$form = ['errors' => [], 'values' => [], 'sent' => isset($_GET['sent'])];
if ($route === 'contact') {
    require ROOT . '/app/enquiry.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = handle_enquiry() + $form;
    }
}

/* ---- Render ----------------------------------------------------------- */
$html = view('layout', [
    'page'    => $page + ['route' => $route],
    'content' => view('pages/' . $page['view'], ['form' => $form]),
]);

send_page($html, $route, $status, $cacheable);
