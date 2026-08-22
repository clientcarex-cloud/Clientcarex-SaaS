<?php
/**
 * Response emitter plus the full-page cache.
 *
 * A rendered page is minified once, written to disk as plain and pre-gzipped
 * copies, and served straight from there on later requests. Every response
 * carries an ETag, so a returning visitor gets a 304 with no body at all.
 */
declare(strict_types=1);

function cache_dir(): string
{
    return ROOT . '/storage/cache';
}

/**
 * Fingerprint of the current code and content. Any edit under app/ or views/,
 * or a new year for the copyright line, invalidates every cached page.
 */
function app_version(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }

    $newest = 0;
    foreach (['/index.php', '/app/*.php', '/views/*.php', '/views/*/*.php'] as $pattern) {
        foreach (glob(ROOT . $pattern) ?: [] as $file) {
            $newest = max($newest, (int) filemtime($file));
        }
    }

    return $version = substr(sha1($newest . '|' . date('Y')), 0, 10);
}

/** Cache filename stem for a route, without the version or ETag suffix. */
function cache_key(string $route): string
{
    return cache_dir() . '/' . sha1($route === '' ? 'home' : $route);
}

function cache_path(string $route): string
{
    return cache_key($route) . '-' . app_version();
}

/** Serve a cached page if one exists. Returns false when there is nothing stored. */
function cache_serve(string $route): bool
{
    if (!CACHE_ENABLED) {
        return false;
    }

    $hit = glob(cache_path($route) . '-*.html');
    if (!$hit) {
        return false;
    }

    $file = $hit[0];
    $etag = '"' . substr(basename($file, '.html'), -20) . '"';
    $gzip = accepts_gzip() && is_file($file . '.gz');

    emit((string) file_get_contents($gzip ? $file . '.gz' : $file), $etag, 200, $gzip);

    return true;
}

/** Store a rendered page, replacing any older build of the same route. */
function cache_write(string $route, string $html, string $etag): void
{
    if (!CACHE_ENABLED) {
        return;
    }
    if (!is_dir(cache_dir()) && !@mkdir(cache_dir(), 0775, true)) {
        return;
    }

    // Drop earlier builds of this route.
    foreach (glob(cache_key($route) . '-*') ?: [] as $old) {
        @unlink($old);
    }

    $file = cache_path($route) . '-' . trim($etag, '"') . '.html';
    file_put_contents($file, $html, LOCK_EX);
    file_put_contents($file . '.gz', (string) gzencode($html, 9), LOCK_EX);
}

function accepts_gzip(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip');
}

/** Minify, cache if the page allows it, then send. */
function send_page(string $html, string $route, int $status = 200, bool $cacheable = true): void
{
    $html = minify_html($html);
    $etag = '"' . substr(sha1($html), 0, 20) . '"';

    if ($cacheable && $status === 200) {
        cache_write($route, $html, $etag);
    }

    $gzip = accepts_gzip();
    emit($gzip ? (string) gzencode($html, 6) : $html, $etag, $status, $gzip);
}

/** Write the response, answering with 304 when the visitor already has it. */
function emit(string $body, string $etag, int $status = 200, bool $gzip = false, string $type = 'text/html; charset=UTF-8'): void
{
    header('Content-Type: ' . $type);
    header('Vary: Accept-Encoding');
    header('Cache-Control: public, max-age=0, must-revalidate');
    header('ETag: ' . $etag);

    if (str_contains($_SERVER['HTTP_IF_NONE_MATCH'] ?? '', trim($etag, '"'))) {
        http_response_code(304);
        exit;
    }

    http_response_code($status);
    if ($gzip) {
        header('Content-Encoding: gzip');
    }
    header('Content-Length: ' . strlen($body));
    echo $body;
}
