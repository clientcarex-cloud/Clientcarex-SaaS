<?php
/**
 * Site-wide configuration and the page registry.
 * Every constant here has exactly one definition — nothing is repeated in a
 * template. Change a value once and it changes everywhere it is rendered.
 */
declare(strict_types=1);

const SITE_NAME  = 'ClientcareX';
const SITE_URL   = 'https://clientcarex.com';
const COMPANY    = 'Clientcarex AI Private Limited';
const PHONE      = '+91 93908 93024';
const PHONE_HREF = '+919390893024';
const EMAIL      = 'care@clientcarex.com';

/** Reporting dashboard for live clients. */
const APP_LOGIN = 'https://clientcarex.com/ccx/authentication/login';

/**
 * The performance-marketing revenue share, written once so the two ends of
 * the band can never drift apart between the pricing table and the copy.
 */
const SHARE_MIN   = '20%';
const SHARE_MAX   = '30%';
const SHARE_RANGE = SHARE_MIN . '–' . SHARE_MAX;

/** Where enquiries are delivered. */
const MAIL_TO = EMAIL;

/** Full-page cache. Turn off while editing templates if you prefer. */
const CACHE_ENABLED = true;

/**
 * Page registry: route => metadata.
 *   view        file under views/pages/
 *   nav         which header link is highlighted ('' = none)
 *   cache       false for pages that vary per request
 *   noindex     keep out of robots and the sitemap
 */
const PAGES = [
    '' => [
        'view'  => 'home',
        'nav'   => 'home',
        'title' => 'ClientcareX — Performance Marketing & Business Automation Agency',
        'description' => 'We fund and run your paid growth — ad spend, media buyers, creative and tools — and take ' . SHARE_RANGE . ' of the revenue we generate. Zero setup, zero retainer. Plus business automation, priced on scope.',
        'priority' => '1.0',
    ],
    'performance-marketing' => [
        'view'  => 'performance-marketing',
        'nav'   => 'marketing',
        'title' => 'Performance Marketing — Pay Only From Revenue We Generate | ClientcareX',
        'description' => 'No setup fee, no retainer, no ad budget from you. We cover ad spend, manpower, subscriptions and creative, and charge ' . SHARE_RANGE . ' of the revenue we produce.',
        'priority' => '0.9',
    ],
    'business-automation' => [
        'view'  => 'business-automation',
        'nav'   => 'automation',
        'title' => 'Business Automation — Built and Priced on Your Scope | ClientcareX',
        'description' => 'CRM, lead routing, follow-up, billing, HR, support and reporting automation. Scoped against your workflows and quoted on implementation — no per-seat licence.',
        'priority' => '0.9',
    ],
    'how-it-works' => [
        'view'  => 'how-it-works',
        'nav'   => 'how',
        'title' => 'How It Works — Audit, Build, Launch, Scale | ClientcareX',
        'description' => 'A free growth audit, an agreed scope and attribution setup, then build and launch. How a ClientcareX engagement runs, on both the marketing and the automation side.',
    ],
    'pricing' => [
        'view'  => 'pricing',
        'nav'   => 'pricing',
        'title' => 'Pricing — Revenue Share or Scoped Quote | ClientcareX',
        'description' => 'Two commercial models: ' . SHARE_RANGE . ' of tracked revenue on performance marketing with every expense on us, or a fixed scoped quote on business automation.',
    ],
    'blog' => [
        'view'  => 'blog',
        'nav'   => 'blog',
        'title' => 'Blog & News — ClientcareX',
        'description' => 'Paid-media teardowns, attribution notes and automation playbooks from the ClientcareX team.',
    ],
    'contact' => [
        'view'  => 'contact',
        'nav'   => '',
        'cache' => false,
        'title' => 'Contact & Free Growth Audit — ClientcareX',
        'description' => 'Talk to the ClientcareX team. Book a free growth audit, ask about the revenue share, or scope an automation build.',
    ],
    'privacy' => [
        'view'  => 'privacy',
        'nav'   => '',
        'title' => 'Privacy Policy — ClientcareX',
        'description' => 'How Clientcarex AI Private Limited collects, uses and protects your data.',
    ],
    'terms' => [
        'view'  => 'terms',
        'nav'   => '',
        'title' => 'Terms & Conditions — ClientcareX',
        'description' => 'The terms governing ClientcareX performance marketing and business automation engagements.',
    ],
    'refund' => [
        'view'  => 'refund',
        'nav'   => '',
        'title' => 'Billing & Refund Policy — ClientcareX',
        'description' => 'How revenue share is measured, reconciled, invoiced and refunded, and how automation project payments work.',
    ],
    '404' => [
        'view'    => '404',
        'nav'     => '',
        'title'   => 'Page not found — ClientcareX',
        'description' => "The page you were looking for doesn't exist.",
        'noindex' => true,
    ],
];

/**
 * Routes retired in the move from ERP product to agency, kept as 301s so old
 * links and search results land on the page that replaced them.
 */
const REDIRECTS = [
    'features'  => 'business-automation',
    'challenge' => 'performance-marketing',
];

/** Header navigation: key => [label, route, hot]. */
const NAV = [
    'home'       => ['Home', '', false],
    'marketing'  => ['Performance Marketing', 'performance-marketing', false],
    'automation' => ['Business Automation', 'business-automation', false],
    'how'        => ['How It Works', 'how-it-works', false],
    'pricing'    => ['Pricing', 'pricing', false],
];

/** Footer link columns: heading => [label, href]. */
const FOOTER_COLUMNS = [
    'What we do' => [
        ['Performance Marketing', 'performance-marketing'],
        ['Business Automation', 'business-automation'],
        ['How It Works', 'how-it-works'],
        ['Pricing Models', 'pricing'],
    ],
    'Company' => [
        ['About Us', 'contact'],
        ['Work With Us', 'contact'],
        ['Blog & News', 'blog'],
        ['Contact Us', 'contact'],
    ],
    'The model' => [
        ['What we fund', 'performance-marketing#what-we-fund'],
        ['How revenue is tracked', 'performance-marketing#attribution'],
        ['Automation scoping', 'business-automation#scoping'],
        ['Common questions', 'pricing#faq'],
    ],
    'Support' => [
        ['Client dashboard', APP_LOGIN],
        ['Help & support', 'contact'],
        ['Referral programme', 'contact'],
        ['Report abuse', 'mailto:' . EMAIL],
    ],
];

/** Footer legal row and social icons. */
const FOOTER_LEGAL = [
    ['Privacy Policy', 'privacy'],
    ['Terms & Conditions', 'terms'],
    ['Billing & Refunds', 'refund'],
];

const SOCIAL = [
    ['linkedin', 'LinkedIn', '#'],
    ['x', 'X', '#'],
    ['facebook', 'Facebook', '#'],
    ['instagram', 'Instagram', '#'],
];
