<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Shared birthday banner — rendered on the HR dashboard and on the main CRM
 * dashboard so both surfaces look identical.
 *
 * Expected variables:
 *   $bd_rows    array   birthday rows (staffid, firstname, lastname, department)
 *   $bd_me      int     current staff id (their own card becomes the "you" hero)
 *   $bd_link    bool    link each celebrant to their HR profile (HR users only)
 *   $bd_private bool    HR-only variant (public wishing turned off)
 */

$bd_rows    = isset($bd_rows) ? $bd_rows : [];
$bd_me      = isset($bd_me) ? (int) $bd_me : 0;
$bd_link    = !empty($bd_link);
$bd_private = !empty($bd_private);

if (empty($bd_rows)) {
    return;
}

$bd_others = [];
$bd_self   = null;
foreach ($bd_rows as $bd_row) {
    if (!$bd_private && (int) $bd_row['staffid'] === $bd_me) {
        $bd_self = $bd_row;
    } else {
        $bd_others[] = $bd_row;
    }
}

/**
 * Display name. Plenty of records carry the surname inside the first name too
 * ("ABDUL RASHEED KHAN" + "RASHEED KHAN"), which reads as a stutter — drop the
 * duplicated tail when that happens.
 */
if (!function_exists('hr_bday_name')) {
    function hr_bday_name($row)
    {
        $first = trim($row['firstname']);
        $last  = trim($row['lastname']);
        if ($last === '') {
            return $first;
        }
        if ($first === '') {
            return $last;
        }
        $f = strtolower($first);
        $l = strtolower($last);
        if ($f === $l || substr($f, -strlen($l)) === $l) {
            return $first;
        }

        return $first . ' ' . $last;
    }
}

/** Initials for the avatar fallback. */
if (!function_exists('hr_bday_initials')) {
    function hr_bday_initials($row)
    {
        $parts = preg_split('/\s+/', hr_bday_name($row), -1, PREG_SPLIT_NO_EMPTY);
        $ini   = '';
        foreach ([reset($parts), end($parts)] as $p) {
            if ($p !== false && $p !== null && $p !== '') {
                $ini .= mb_strtoupper(mb_substr($p, 0, 1));
            }
        }

        return $ini !== '' ? mb_substr($ini, 0, 2) : '?';
    }
}

/** Stable per-person avatar colour so the row reads as a group of people. */
if (!function_exists('hr_bday_hue')) {
    function hr_bday_hue($row)
    {
        $palette = [
            ['#f472b6', '#db2777'], ['#818cf8', '#4f46e5'], ['#34d399', '#059669'],
            ['#fbbf24', '#d97706'], ['#22d3ee', '#0891b2'], ['#a78bfa', '#7c3aed'],
            ['#fb7185', '#e11d48'], ['#4ade80', '#16a34a'],
        ];

        return $palette[((int) $row['staffid']) % count($palette)];
    }
}

/** "Four birthdays today" beats "4 birthdays today". */
if (!function_exists('hr_bday_count_word')) {
    function hr_bday_count_word($n)
    {
        $words = [2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
            7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten', ];

        return isset($words[$n]) ? $words[$n] : (string) $n;
    }
}

/** One celebrant card. */
if (!function_exists('hr_bday_card')) {
    function hr_bday_card($row, $link, $muted = false)
    {
        $placeholder = base_url('assets/images/user-placeholder.jpg');
        $img         = staff_profile_image_url((int) $row['staffid'], 'small');
        $colors      = $muted ? ['#94a3b8', '#475569'] : hr_bday_hue($row);
        $dept        = trim($row['department'] ?? '');
        $tag         = $link ? 'a' : 'div';
        $href        = $link ? ' href="' . admin_url('hr/employee/' . (int) $row['staffid']) . '"' : '';

        $avatar = $img !== $placeholder
            ? '<img src="' . $img . '" alt="">'
            : '<span class="hr-bday-ini">' . html_escape(hr_bday_initials($row)) . '</span>';

        return '<' . $tag . $href . ' class="hr-bday-card' . ($muted ? ' is-muted' : '') . '"'
            . ' style="--c1:' . $colors[0] . ';--c2:' . $colors[1] . ';">'
            . '<span class="hr-bday-av">' . $avatar . '<span class="hr-bday-hat">🎉</span></span>'
            . '<span class="hr-bday-card-name">' . html_escape(hr_bday_name($row)) . '</span>'
            . ($dept !== '' ? '<span class="hr-bday-dept">' . html_escape($dept) . '</span>' : '')
            . '</' . $tag . '>';
    }
}

if (!defined('HR_BDAY_BANNER_CSS')) {
    define('HR_BDAY_BANNER_CSS', 1); ?>
    <style>
    .hr-bday{position:relative;overflow:hidden;border-radius:16px;padding:18px 22px;margin-bottom:20px;
        background:linear-gradient(125deg,#fff1f7 0%,#fdf4ff 45%,#eef4ff 100%);
        border:1px solid #fbcfe8;box-shadow:0 6px 24px rgba(219,39,119,.08)}
    .hr-bday.is-private{background:#f8fafc;border-color:#e2e8f0;box-shadow:0 4px 18px rgba(15,23,42,.05)}
    .hr-bday-head{display:flex;align-items:center;gap:16px;position:relative;z-index:2}
    .hr-bday-mark{position:relative;flex-shrink:0;width:54px;height:54px;border-radius:16px;
        display:flex;align-items:center;justify-content:center;font-size:28px;line-height:1;
        background:#fff;box-shadow:0 4px 14px rgba(219,39,119,.18)}
    .hr-bday.is-private .hr-bday-mark{box-shadow:0 4px 14px rgba(15,23,42,.1)}
    .hr-bday-badge{position:absolute;top:-7px;right:-7px;min-width:23px;height:23px;padding:0 6px;
        border-radius:12px;background:#db2777;color:#fff;font-size:12px;font-weight:800;line-height:23px;
        text-align:center;box-shadow:0 2px 6px rgba(219,39,119,.4)}
    .hr-bday.is-private .hr-bday-badge{background:#64748b;box-shadow:none}
    .hr-bday-title{margin:0;font-size:17px;font-weight:800;color:#9d174d;letter-spacing:-.2px}
    .hr-bday.is-private .hr-bday-title{color:#334155;font-size:15px}
    .hr-bday-sub{margin:2px 0 0;font-size:13px;color:#64748b}
    .hr-bday-cards{position:relative;z-index:2;display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
    .hr-bday-card{display:flex;flex-direction:column;align-items:center;gap:5px;width:132px;padding:14px 10px 12px;
        border-radius:14px;background:rgba(255,255,255,.85);border:1px solid rgba(219,39,119,.12);
        text-align:center;text-decoration:none;transition:transform .18s ease,box-shadow .18s ease}
    a.hr-bday-card:hover,a.hr-bday-card:focus{transform:translateY(-3px);box-shadow:0 10px 20px rgba(219,39,119,.14);
        text-decoration:none;border-color:rgba(219,39,119,.3)}
    .hr-bday-card.is-muted{border-color:#e2e8f0;background:#fff}
    a.hr-bday-card.is-muted:hover{box-shadow:0 10px 20px rgba(15,23,42,.1);border-color:#cbd5e1}
    .hr-bday-av{position:relative;width:52px;height:52px;border-radius:50%;display:flex;align-items:center;
        justify-content:center;background:linear-gradient(135deg,var(--c1),var(--c2));
        box-shadow:0 0 0 3px #fff,0 0 0 5px var(--c1);transition:transform .18s ease}
    .hr-bday-av img{width:100%;height:100%;border-radius:50%;object-fit:cover}
    .hr-bday-ini{color:#fff;font-size:17px;font-weight:800;letter-spacing:.5px}
    .hr-bday-hat{position:absolute;bottom:-4px;right:-8px;font-size:14px;opacity:0;transform:scale(.5);
        transition:opacity .18s ease,transform .18s ease}
    .hr-bday-card:hover .hr-bday-av{transform:rotate(-5deg) scale(1.05)}
    .hr-bday-card:hover .hr-bday-hat{opacity:1;transform:scale(1)}
    .hr-bday-card-name{font-size:12.5px;font-weight:700;color:#1e293b;line-height:1.25;word-break:break-word}
    .hr-bday-dept{font-size:10px;font-weight:600;color:#9d174d;background:rgba(219,39,119,.09);
        border-radius:20px;padding:2px 8px;text-transform:uppercase;letter-spacing:.4px;line-height:1.5}
    .hr-bday-card.is-muted .hr-bday-dept{color:#475569;background:#f1f5f9}
    .hr-bday-solo{display:flex;align-items:center;gap:14px}
    .hr-bday-solo .hr-bday-av{width:56px;height:56px}
    .hr-bday-note{position:relative;z-index:2;margin:14px 0 0;font-size:11.5px;color:#64748b}
    /* confetti */
    .hr-bday-confetti{position:absolute;inset:0;z-index:1;pointer-events:none;overflow:hidden}
    .hr-bday-confetti i{position:absolute;top:-14px;width:6px;height:9px;border-radius:2px;opacity:.38;
        animation:hrBdayFall linear infinite}
    @keyframes hrBdayFall{0%{transform:translateY(-16px) rotate(0)}100%{transform:translateY(320px) rotate(420deg)}}
    @media (prefers-reduced-motion:reduce){.hr-bday-confetti{display:none}
        .hr-bday-card,.hr-bday-av,.hr-bday-hat{transition:none}}
    @media (max-width:767px){.hr-bday{padding:16px}.hr-bday-cards{gap:8px}
        .hr-bday-card{width:calc(50% - 4px)}}
    </style>
<?php }

$bd_confetti = '';
if (!$bd_private) {
    $bd_bits = [['#f472b6', 6, 7.5, 0], ['#818cf8', 18, 9, 1.4], ['#fbbf24', 32, 8, .6],
        ['#34d399', 46, 10, 2.1], ['#f472b6', 61, 8.5, 1], ['#a78bfa', 74, 9.5, 2.6],
        ['#fbbf24', 88, 7.5, 1.8], ['#22d3ee', 96, 10.5, .3], ];
    foreach ($bd_bits as $b) {
        $bd_confetti .= '<i style="left:' . $b[1] . '%;background:' . $b[0]
            . ';animation-duration:' . $b[2] . 's;animation-delay:-' . $b[3] . 's;"></i>';
    }
    $bd_confetti = '<div class="hr-bday-confetti">' . $bd_confetti . '</div>';
}
?>

<?php if ($bd_self) { ?>
    <div class="hr-bday">
        <?php echo $bd_confetti; ?>
        <div class="hr-bday-head">
            <div class="hr-bday-mark">🎉</div>
            <div>
                <p class="hr-bday-title">Happy Birthday, <?php echo html_escape($bd_self['firstname']); ?>! 🎂</p>
                <p class="hr-bday-sub">The whole team wishes you a wonderful day ahead. 🥳</p>
            </div>
        </div>
    </div>
<?php } ?>

<?php if (!empty($bd_others)) {
    $bd_n = count($bd_others); ?>
    <div class="hr-bday<?php echo $bd_private ? ' is-private' : ''; ?>">
        <?php echo $bd_confetti; ?>
        <?php if ($bd_n === 1) { ?>
            <div class="hr-bday-head hr-bday-solo">
                <?php echo hr_bday_card($bd_others[0], $bd_link, $bd_private); ?>
                <div>
                    <p class="hr-bday-title">
                        <?php if ($bd_private) { ?>
                            Birthday today <span class="label label-default" style="font-weight:600;">HR only · private</span>
                        <?php } else { ?>
                            It&rsquo;s <?php echo html_escape($bd_others[0]['firstname']); ?>&rsquo;s birthday today! 🎂
                        <?php } ?>
                    </p>
                    <p class="hr-bday-sub">
                        <?php echo $bd_private
                            ? 'Public wishes are turned off, so this is visible to HR only.'
                            : 'One message is all it takes — go make their day. 🎈'; ?>
                    </p>
                </div>
            </div>
        <?php } else { ?>
            <div class="hr-bday-head">
                <div class="hr-bday-mark">🎂<span class="hr-bday-badge"><?php echo $bd_n; ?></span></div>
                <div>
                    <p class="hr-bday-title">
                        <?php if ($bd_private) { ?>
                            <?php echo hr_bday_count_word($bd_n); ?> birthdays today
                            <span class="label label-default" style="font-weight:600;">HR only · private</span>
                        <?php } else { ?>
                            <?php echo hr_bday_count_word($bd_n); ?> birthdays today — what are the odds? 🎉
                        <?php } ?>
                    </p>
                    <p class="hr-bday-sub">
                        <?php echo $bd_private
                            ? 'Public wishes are turned off, so this is visible to HR only.'
                            : 'Say hello to each of them today — a quick wish goes a long way. 🎈'; ?>
                    </p>
                </div>
            </div>
            <div class="hr-bday-cards">
                <?php foreach ($bd_others as $bd_row) {
                    echo hr_bday_card($bd_row, $bd_link, $bd_private);
                } ?>
            </div>
        <?php } ?>
        <?php if ($bd_private) { ?>
            <p class="hr-bday-note"><i class="fa fa-info-circle"></i> Enable <em>Birthday Wishes</em> in HR Settings to share this company-wide.</p>
        <?php } ?>
    </div>
<?php } ?>
