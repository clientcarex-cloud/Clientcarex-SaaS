<?php
/** Small render helpers used by every template. */
declare(strict_types=1);

/** Escape for HTML output. */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build an internal URL. Passes through absolute, mail, tel and fragment-only
 * links untouched so link lists can mix the two without special-casing.
 */
function url(string $path = ''): string
{
    if ($path === '' || $path[0] === '#') {
        return BASE . '/' . $path;
    }
    if (preg_match('#^(https?:|mailto:|tel:|//)#', $path)) {
        return $path;
    }
    return BASE . '/' . ltrim($path, '/');
}

/** Asset URL with an mtime cache-buster, so assets can be cached forever. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT . '/' . $path;
    $v    = is_file($file) ? filemtime($file) : 0;

    return BASE . '/' . $path . '?v=' . $v;
}

/** Render a template file to a string. */
function view(string $name, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $name . '.php';

    return (string) ob_get_clean();
}

/** Echo a partial from views/partials/. */
function part(string $name, array $data = []): void
{
    echo view('partials/' . $name, $data);
}

/** `aria-current` marker for the active nav item. */
function active(bool $isCurrent): string
{
    return $isCurrent ? ' aria-current="page"' : '';
}

/** Collapse template whitespace. Content inside pre/textarea/script/style is left alone. */
function minify_html(string $html): string
{
    $parts = preg_split(
        '#(<(?:pre|textarea|script|style)\b[^>]*>.*?</(?:pre|textarea|script|style)>)#is',
        $html,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );

    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            continue; // protected block
        }
        $part = preg_replace('/<!--.*?-->/s', '', $part);
        $parts[$i] = preg_replace('/\s+/', ' ', $part);
    }

    return trim(implode('', $parts));
}
