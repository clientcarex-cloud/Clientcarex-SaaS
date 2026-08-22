<?php
/**
 * One definition per icon. Previously the same check mark was pasted into the
 * markup ~50 times and the same star 20 times; now each shape lives here once
 * and is rendered by icon().
 *
 *   d      path data
 *   fill   true for solid icons (stars, social marks)
 *   sw     default stroke width for outline icons
 *   box    viewBox, when it is not the usual 24x24
 */
declare(strict_types=1);

const ICONS = [
    'check'      => ['d' => 'm20 6-11 11-5-5', 'sw' => '2.5'],
    'plus'       => ['d' => 'M12 5v14M5 12h14', 'sw' => '3', 'cap' => 'round'],
    'arrow'      => ['d' => 'M5 12h14M13 6l6 6-6 6', 'sw' => '2.4'],
    'star'       => ['d' => 'm12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z', 'fill' => true],
    'phone'      => ['d' => 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z'],
    'mail'       => ['d' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>', 'raw' => true],
    'shield'     => ['d' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>', 'raw' => true, 'sw' => '2.2'],
    'info'       => ['d' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/>', 'raw' => true],

    // Shared service-grid headings
    'users'      => ['d' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>', 'raw' => true],
    'chart'      => ['d' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>', 'raw' => true],
    'briefcase'  => ['d' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>', 'raw' => true],
    'chat'       => ['d' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    'bolt'       => ['d' => 'M13 2 3 14h9l-1 8 10-12h-9z'],
    'cog'        => ['d' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.2 4.2l2.9 2.9M16.9 16.9l2.9 2.9M1 12h4M19 12h4M4.2 19.8l2.9-2.9M16.9 7.1l2.9-2.9"/>', 'raw' => true],

    // Performance-marketing service headings
    'target'     => ['d' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4"/>', 'raw' => true],
    'video'      => ['d' => '<rect x="2" y="5" width="14" height="14" rx="3"/><path d="m22 8.5-6 3.5 6 3.5z"/>', 'raw' => true],
    'funnel'     => ['d' => 'M3 4h18l-7 8.2V19l-4 2v-8.8z'],
    'megaphone'  => ['d' => '<path d="M4 10.5v3a1 1 0 0 0 1 1h2l6 4.5v-17L7 6.5H5a1 1 0 0 0-1 1z"/><path d="M17 8.6a5 5 0 0 1 0 6.8"/><path d="M20 5.6a9 9 0 0 1 0 12.8"/>', 'raw' => true],
    'search'     => ['d' => '<circle cx="11" cy="11" r="7"/><path d="m20.5 20.5-4.2-4.2"/>', 'raw' => true],
    'trending'   => ['d' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>', 'raw' => true],

    // Automation service headings
    'wallet'     => ['d' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a1 1 0 0 1 1 1v2"/><path d="M3 7.5V17a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5.5A2.5 2.5 0 0 1 3 7.5z"/><path d="M17.5 13.5h.01"/>', 'raw' => true],
    'link'       => ['d' => '<path d="M10.5 13.5a4.5 4.5 0 0 0 6.8.5l2.5-2.5a4.5 4.5 0 0 0-6.4-6.4l-1.4 1.4"/><path d="M13.5 10.5a4.5 4.5 0 0 0-6.8-.5l-2.5 2.5a4.5 4.5 0 0 0 6.4 6.4l1.4-1.4"/>', 'raw' => true],
    'sparkle'    => ['d' => 'M12 2.5 14.1 9l6.4 2.1-6.4 2.1L12 19.6l-2.1-6.4L3.5 11.1 9.9 9z'],

    // Model / ledger
    'wallet-out' => ['d' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5H10.8a1.8 1.8 0 0 0 0 3.6h2.4a1.8 1.8 0 0 1 0 3.6H9"/><path d="M12 7.6v1.9M12 16.7v1.7"/>', 'raw' => true],
    'clock'      => ['d' => '<circle cx="12" cy="12" r="9"/><path d="M12 6.8V12l3.4 2"/>', 'raw' => true],
    'cross'      => ['d' => 'M6 6l12 12M18 6 6 18', 'sw' => '2.5'],

    // Social
    'linkedin'  => ['d' => 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-.95 1.83-1.95 3.77-1.95C20.4 8.75 21 11 21 14.1V21h-4v-6.1c0-1.45-.03-3.32-2.02-3.32-2.02 0-2.33 1.58-2.33 3.21V21H9z', 'fill' => true],
    'x'         => ['d' => 'M18.9 2H22l-7.1 8.1L23.2 22h-6.6l-5.2-6.8L5.5 22H2.4l7.6-8.7L1.2 2h6.8l4.7 6.2zm-1.1 18h1.7L7.3 3.8H5.5z', 'fill' => true],
    'facebook'  => ['d' => 'M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5H16.7V3.6c-.3-.04-1.3-.13-2.47-.13-2.45 0-4.13 1.5-4.13 4.24V9.9H7.4V13h2.7v8z', 'fill' => true],
    'instagram' => ['d' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.6"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/>', 'raw' => true, 'sw' => '2', 'plain' => true],
];

/**
 * Render an icon.
 *
 * @param string      $name  key in ICONS
 * @param int         $size  width and height in px
 * @param string|null $sw    stroke width override
 */
function icon(string $name, int $size = 16, ?string $sw = null): string
{
    $i = ICONS[$name] ?? null;
    if ($i === null) {
        return '';
    }

    $body = ($i['raw'] ?? false) ? $i['d'] : '<path d="' . $i['d'] . '"/>';

    if ($i['fill'] ?? false) {
        $paint = 'fill="currentColor"';
    } elseif ($i['plain'] ?? false) {
        $paint = 'fill="none" stroke="currentColor" stroke-width="' . ($sw ?? $i['sw'] ?? '2') . '"';
    } else {
        $paint = 'fill="none" stroke="currentColor" stroke-width="' . ($sw ?? $i['sw'] ?? '2')
               . '" stroke-linecap="round" stroke-linejoin="round"';
    }

    return sprintf(
        '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" %2$s aria-hidden="true">%3$s</svg>',
        $size,
        $paint,
        $body
    );
}
