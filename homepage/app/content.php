<?php
/**
 * Page content as data. Anything that was repeated markup — the commercial
 * models, FAQs, service grids, steps, reviews — is a row in an array here and
 * is rendered once by a partial.
 */
declare(strict_types=1);

/* ==========================================================================
   The two commercial models
   ========================================================================== */

/**
 * Shown on the homepage and the pricing page.
 *
 *   price / period   the headline number and what it is charged against
 *   covers           what the fee already includes — the differentiator
 *   terms            the small print under the button
 *   link             [label, route] to the page that explains the model
 */
const MODELS = [
    [
        'name'     => 'Performance Marketing',
        'badge'    => 'Pay from revenue',
        'featured' => true,
        'price'    => SHARE_RANGE,
        'period'   => 'of the revenue we generate',
        'tagline'  => 'You pay for results, nothing else',
        'summary'  => 'No setup fee. No retainer. No ad budget from you. We fund the whole campaign and take a share of what it produces.',
        'features' => [
            'Ad spend on every channel — funded by us',
            'Media buyers, strategists and analysts — our team',
            'Creative production: video, static, copy',
            'Landing pages, funnels and CRO',
            'Tools and subscriptions we run the account on',
            'Tracking, attribution and monthly reconciliation',
            'Reporting dashboard you can open any day',
        ],
        'terms' => 'Exact rate agreed in writing before launch',
        'cta'   => ['Get a revenue plan', 'contact'],
        'link'  => ['How the revenue share works', 'performance-marketing'],
    ],
    [
        'name'    => 'Business Automation',
        'badge'   => 'Scoped quote',
        'price'   => 'Scope-based',
        'period'  => 'quoted per build',
        'tagline' => 'Priced on your work scope',
        'summary' => 'We map your processes, write the scope, then quote it. One number for the build — no per-seat licence, no surprise line items.',
        'features' => [
            'Process discovery and a written workflow map',
            'Fixed quote against an agreed scope document',
            'CRM, lead routing and follow-up automation',
            'Billing, approvals, HR and support workflows',
            'Integrations with the tools you already pay for',
            'AI agents for chat, ticketing and telecalling',
            'Handover, SOPs and team training included',
        ],
        'terms' => 'Free audit first — the quote follows the scope',
        'cta'   => ['Scope my automation', 'contact'],
        'link'  => ['How scoping works', 'business-automation'],
    ],
    [
        'name'    => 'Growth Partner',
        'badge'   => 'Both engines',
        'price'   => 'Share + scope',
        'period'  => 'one engagement',
        'tagline' => 'Acquisition and operations together',
        'summary' => 'Run both models side by side: we generate the demand on revenue share, and build the automation that stops it leaking on scope.',
        'features' => [
            'Everything in Performance Marketing',
            'Everything in Business Automation',
            'One team across acquisition and operations',
            'Leads flow straight into automated follow-up',
            'Ad data and CRM data in the same reporting',
            'Single point of contact and one review cadence',
            'Automation scope priced with the partnership in mind',
        ],
        'terms' => 'Best fit when demand and delivery both need work',
        'cta'   => ['Talk about a partnership', 'contact'],
        'link'  => ['See how an engagement runs', 'how-it-works'],
    ],
];

/**
 * The ledger that makes the revenue-share model concrete: every cost that
 * usually lands on the client sits on our side of it.
 */
const LEDGER_OURS = [
    'Ad spend across every channel we run',
    'Media buyers, strategists, analysts and account managers',
    'Creative production — video, static, motion, copywriting',
    'Landing pages, funnels and conversion-rate optimisation',
    'Software subscriptions: ad tools, CRM, analytics, automation',
    'Stock assets, licensing and production costs',
    'Tracking, attribution and reporting infrastructure',
    'Testing, iteration and day-to-day campaign management',
];

const LEDGER_YOURS = [
    'Your product or service, and fulfilling the orders we bring',
    'A ' . SHARE_RANGE . ' share of the revenue we generate — agreed up front',
    'Payment gateway and platform fees on your own sales',
    'Access to your ad accounts, analytics, CRM and brand assets',
    'Timely sign-off on offers, creative and landing pages',
];

/* ==========================================================================
   Services
   ========================================================================== */

/** Performance marketing: what we actually run. */
const MARKETING_SERVICES = [
    ['target', 'Paid Media', [
        'Meta Ads — Facebook and Instagram',
        'Google Ads — Search, Performance Max, Shopping',
        'YouTube and video campaigns',
        'LinkedIn for B2B and high-ticket',
        'Native, display and retargeting',
        'Budget allocation across the mix',
    ]],
    ['video', 'Creative Production', [
        'Ad concepts written against the offer',
        'UGC-style and studio video',
        'Static, carousel and motion assets',
        'Copywriting and hook testing',
        'Creative refresh on a fixed cadence',
        'Winning-ad breakdowns in reporting',
    ]],
    ['funnel', 'Funnels & CRO', [
        'Landing pages built for the campaign',
        'Offer and pricing structure',
        'Form, checkout and lead-capture optimisation',
        'A/B testing on page and offer',
        'Speed, mobile and tracking hygiene',
        'Post-click journey mapping',
    ]],
    ['megaphone', 'Lifecycle & Retention', [
        'WhatsApp, SMS and email sequences',
        'Abandoned cart and abandoned lead recovery',
        'Win-back and reactivation campaigns',
        'Upsell and cross-sell journeys',
        'Review and referral prompts',
        'Repeat revenue counted the same way',
    ]],
    ['search', 'Organic & Content', [
        'SEO for the pages that convert',
        'Landing page and category content',
        'Google Business Profile and local search',
        'Social content that supports paid',
        'Marketplace listing optimisation',
        'Content repurposed into ad creative',
    ]],
    ['trending', 'Tracking & Reporting', [
        'Server-side and pixel tracking setup',
        'UTM, coupon and CRM-stage attribution',
        'Live dashboard with revenue by source',
        'Monthly reconciliation against your books',
        'Cohort, LTV and payback reporting',
        'Everything we invoice is traceable to a source',
    ]],
];

/** Business automation: what we build. */
const AUTOMATION_SERVICES = [
    ['users', 'Sales & CRM', [
        'Lead capture from every source into one pipeline',
        'Automatic routing, scoring and owner assignment',
        'Follow-up sequences across call, WhatsApp and email',
        'Quotation and proposal generation',
        'Pipeline stages that match how you actually sell',
        'Re-engagement of cold and lost leads',
    ]],
    ['wallet', 'Billing & Finance', [
        'Invoice generation and delivery',
        'Automatic payment reminders and dunning',
        'Payment gateway reconciliation',
        'Expense capture and approval flows',
        'Recurring and subscription billing',
        'Revenue and receivables dashboards',
    ]],
    ['briefcase', 'HR & Internal Ops', [
        'Attendance, leave and shift workflows',
        'Payroll inputs and payslip distribution',
        'Onboarding and offboarding checklists',
        'Approval chains for spend and documents',
        'Task assignment and SLA tracking',
        'Activity logs and audit trails',
    ]],
    ['chat', 'Support & Service', [
        'Ticketing across email, chat, social and phone',
        'AI first-response and intent routing',
        'AI voice agents for calls and callbacks',
        'Knowledge base and canned resolutions',
        'Appointment booking and reminders',
        'CSAT capture and escalation rules',
    ]],
    ['link', 'Integrations', [
        'Connect the CRM, ad platforms and accounting',
        'Two-way sync so no system is the odd one out',
        'WhatsApp Business API and SMS gateways',
        'Payment gateways and marketplaces',
        'Google Workspace, Sheets and Analytics',
        'Custom API work where an off-the-shelf connector stops',
    ]],
    ['sparkle', 'AI & Reporting', [
        'AI agents for repetitive back-office work',
        'Prompt-driven reports and charts',
        'Document and data extraction',
        'Anomaly alerts on the numbers that matter',
        'Role-based dashboards for owners and managers',
        'Weekly digests pushed to the people who act on them',
    ]],
];

/** Homepage: the three reasons the model works. */
const PILLARS = [
    ['Group-114.svg', 'Our money is at risk first', 'We pay for the ads, the team and the tools before you pay us anything. If the campaign does not produce revenue, we carry that cost — not you.'],
    ['Group-115.svg', 'Priced against your revenue, not our hours', 'A retainer gets paid whether it works or not. A revenue share only grows when your revenue does, so the incentive never points the wrong way.'],
    ['Group-113.svg', 'Demand and delivery, handled together', 'Winning the lead is half of it. We also build the automation that follows up, bills, supports and retains — so growth does not break operations.'],
];

/** Homepage: where the revenue-share model fits, and where it does not. */
const FIT_YES = [
    'You have a proven product with real margin',
    'You can fulfil more orders than you get today',
    'Sales are trackable — online checkout, CRM or booked jobs',
    'You want to grow without funding a marketing budget',
    'You can give us access to accounts and data',
];

const FIT_NO = [
    'Pre-launch with nothing to sell yet',
    'Margins too thin to carry a revenue share',
    'Revenue that cannot be attributed to a source at all',
    'Fulfilment already at capacity',
    'You need day-to-day creative control of every ad',
];

/* ==========================================================================
   Process
   ========================================================================== */

/** How an engagement runs, end to end. Used on how-it-works and the homepage. */
const STEPS_ENGAGEMENT = [
    ['Step one • Free', 'Growth audit', "We look at what you sell, what it costs you to deliver, where your revenue comes from today and what is already tracked. You get a written read on whether the revenue-share model can work for you — including when the answer is no."],
    ['Step two • Free', 'Model and scope', 'For marketing we agree the share rate, the channels, the revenue that counts and how it is measured. For automation we map your processes and write a scope document, which is what the fixed quote is built from. Nothing is signed before both are on paper.'],
    ['Step three', 'Build and launch', 'Tracking and attribution go in first so the numbers are trustworthy from day one. Then creative, funnels and campaigns go live, or the automation gets built module by module with you reviewing each one as it lands.'],
    ['Step four', 'Scale and report', 'You get a live dashboard and a monthly reconciliation. We scale what pays back, kill what does not, and invoice only against revenue that both sides can see in the same report.'],
];

/** The marketing engagement, told at campaign depth. */
const STEPS_MARKETING = [
    ['Week 0', 'Audit and attribution', 'Before a rupee of ad spend, we set up tracking: pixels, server-side events, UTMs, coupon codes or CRM stages — whichever proves where revenue came from in your business. Both sides sign off on what counts as revenue we generated.'],
    ['Week 1–2', 'Offer, creative and funnel', 'We build the landing pages and the first creative batch against your offer, and stand up the lifecycle sequences that catch the traffic that does not convert on the first visit.'],
    ['Week 3–6', 'Launch and find the winners', 'Campaigns go live on our budget. We test angles, audiences and creative hard in this window, and you watch cost-per-acquisition and tracked revenue move on the dashboard as it happens.'],
    ['Month 2 onward', 'Scale and reconcile', 'Budget moves to what pays back. Each month we reconcile tracked revenue against your own books, agree the number, and invoice the share against it.'],
];

/** The automation engagement, told at implementation depth. */
const STEPS_AUTOMATION = [
    ['Phase 1', 'Discovery and workflow map', 'We sit with the people who do the work and map how the business actually runs — every handoff, spreadsheet and message thread holding a process together. The output is a written workflow map, not a slide deck.'],
    ['Phase 2', 'Scope and fixed quote', 'The map becomes a scope document: which processes, which integrations, which systems, what is explicitly out. That document is what the fixed price is quoted against, so scope changes are a conversation rather than an invoice surprise.'],
    ['Phase 3', 'Build and review', 'We build in reviewable pieces. You see each workflow working on your own data before we move to the next, so nothing is discovered at handover.'],
    ['Phase 4', 'Handover and support', 'Training, SOPs and a support line. Your team owns the system; we stay available for tuning and for the next set of processes when you are ready.'],
];

/* ==========================================================================
   Proof and numbers
   ========================================================================== */

/**
 * Structural numbers only — these describe how we charge, not results we
 * claim. Replace or extend with audited client outcomes when you have them.
 */
const STATS_MODEL = [
    ['₹0', 'Setup fee, ever'],
    ['₹0', 'Monthly retainer'],
    ['₹0', 'Ad spend from your pocket'],
    [SHARE_RANGE, 'Of tracked revenue — that is the whole bill'],
];

const STATS_ENGAGEMENT = [
    ['2', 'Free steps before anything is signed'],
    ['1', 'Invoice line — the revenue share'],
    ['30', 'Days notice to end a marketing engagement'],
    ['100%', 'Of what we bill is traceable to a source'],
];

/** Homepage: client logo row. */
const CLIENT_LOGOS = [
    ['iiet-logo-1-removebg-preview.png', 'IIET', 813, 307],
    ['Dr._Care_Logo-removebg-preview.png', 'Dr. Care', 200, 87],
    ['Toot-Logo-1.png', 'Toot', 200, 87],
    ['Autism-Logo-e1753695588960.png', 'Autism', 50, 50],
    ['new-Harvest-helpdesklatest.png', 'Harvest', 552, 195],
    ['new-Quantum-helpdesklatest.png', 'Quantum', 552, 195],
    ['new-Urban-helpdesklatest.png', 'Urban', 552, 195],
    ['new-mrbeat.png', 'Mr Beat', 552, 195],
];

/**
 * PLACEHOLDER COPY — these are not real client quotes.
 * The originals were Elementor filler text and were never attributable.
 * Replace every row with a quote you have written permission to publish,
 * or delete the constant and the review section that renders it.
 * See "Before this goes live" in README.md.
 */
const REVIEWS = [
    ['Client name', 'Role, Company', 'Placeholder — replace with a real client quote about the revenue-share model before launch.'],
    ['Client name', 'Role, Company', 'Placeholder — replace with a real client quote about paid media results before launch.'],
    ['Client name', 'Role, Company', 'Placeholder — replace with a real client quote about an automation build before launch.'],
    ['Client name', 'Role, Company', 'Placeholder — replace with a real client quote about working with the team before launch.'],
];

/** PLACEHOLDER — see the note on REVIEWS. */
const QUOTE = [
    'text' => 'Placeholder pull quote. Replace with a real, attributable client quote before this site goes live.',
    'name' => 'Client name',
    'role' => 'Role, Company',
];

/** Business automation page: platforms we connect to. */
const INTEGRATIONS = [
    ['Slack', 'Internal comms', 'tool-slack.svg', 'Push lead alerts, approvals and SLA breaches into the channel that owns them.'],
    ['Zapier', 'Glue', 'tool-zapier.svg', 'Reach 5,000+ apps when a direct integration is not worth building from scratch.'],
    ['HubSpot', 'CRM', 'tool-hubspot.svg', 'Keep contacts, deals and lifecycle stages in sync in both directions.'],
    ['Stripe', 'Payments', 'tool-stripe.svg', 'Card and subscription payments reconciled straight into your reporting.'],
    ['PayPal', 'Payments', 'tool-paypal.svg', 'Collect against invoices and match receipts automatically.'],
    ['Salesforce', 'CRM', 'tool-salesforce.svg', 'Two-way sync for accounts, opportunities and activity history.'],
];

/* ==========================================================================
   FAQs
   ========================================================================== */

const FAQS = [
    [
        'q' => 'How does the ' . SHARE_RANGE . ' revenue share actually work?',
        'a' => '<p>We agree a percentage before anything launches. Each month we report the revenue our campaigns generated, reconcile it against your own books, and invoice that agreed percentage of the agreed figure. There is no setup fee, no retainer and no minimum spend from you — the share is the entire bill.</p>',
    ],
    [
        'q' => 'What exactly do you pay for?',
        'a' => '<p>Everything it takes to run the campaign:</p>
          <ul>
            <li>Ad spend on every channel we run</li>
            <li>Media buyers, strategists, analysts and account managers</li>
            <li>Creative production — video, static, motion and copy</li>
            <li>Landing pages, funnels and CRO work</li>
            <li>Software subscriptions, tools and stock assets</li>
            <li>Tracking, attribution and reporting infrastructure</li>
          </ul>
          <p style="margin-top:.75rem">You cover your own product, fulfilment and payment gateway fees — and the revenue share.</p>',
    ],
    [
        'q' => 'What decides whether the rate is ' . SHARE_MIN . ' or ' . SHARE_MAX . '?',
        'a' => '<p>Mainly your margin, your average order value and how much of the funnel we take over. High-volume, thinner-margin businesses sit at the lower end; engagements where we own creative, funnel, lifecycle and channel mix end to end sit at the higher end. The number is fixed in writing before launch, not adjusted afterwards.</p>',
    ],
    [
        'q' => 'How do you prove which revenue you generated?',
        'a' => '<p>Attribution is set up before the first campaign goes live, and both sides agree what counts. Depending on the business that means pixel and server-side tracking, dedicated landing pages, unique coupon codes, call tracking numbers, or a CRM source stage. Every month the tracked figure is reconciled against your own sales records — we invoice against the agreed number, not our dashboard alone.</p>',
    ],
    [
        'q' => 'What about refunds, cancellations and returns?',
        'a' => '<p>Only realised revenue counts. Refunded, cancelled, returned and never-collected orders are removed from the figure before the share is calculated, and anything that slips past a monthly cut-off is adjusted on the next invoice.</p>',
    ],
    [
        'q' => 'How is business automation priced?',
        'a' => '<p>On your work scope and the implementation it needs — not per user and not per month. We run a free discovery, write a scope document covering the processes, integrations and systems involved, and quote a fixed price against it. If the scope changes later, we re-quote the change rather than absorbing it quietly or billing it as a surprise.</p>',
    ],
    [
        'q' => 'Do I have to take both services?',
        'a' => '<p>No. Plenty of clients take one. They do work well together — acquisition that fills the pipeline, automation that stops it leaking — and the Growth Partner engagement runs both under one team and one review cadence.</p>',
    ],
    [
        'q' => 'Who owns the ad accounts, creative and data?',
        'a' => '<p>You do. Campaigns run in accounts you own or have full access to, creative produced for you is yours, and your customer data stays yours throughout and after the engagement. The specifics are written into the agreement.</p>',
    ],
    [
        'q' => 'What if it does not work?',
        'a' => "<p>Then you have paid nothing on the marketing side — that is the point of the model, and the reason we audit carefully before taking a client on. Either side can end a marketing engagement on 30 days' written notice, with the share settled on revenue generated up to that date.</p>",
    ],
];

/* ==========================================================================
   Forms
   ========================================================================== */

/** What the enquirer is after — drives routing on our side. */
const INTERESTS = [
    'Performance marketing (revenue share)',
    'Business automation (scoped build)',
    'Both — growth partnership',
    'Not sure yet',
];

/** Monthly revenue bands on the enquiry form. */
const REVENUE_BANDS = [
    'Pre-revenue',
    'Under ₹5 lakh / month',
    '₹5–25 lakh / month',
    '₹25 lakh–1 crore / month',
    'Over ₹1 crore / month',
];

/** Initials for an avatar circle: "Ami Smith" -> "AS", "Khyati" -> "K". */
function initials(string $name): string
{
    $out = '';
    foreach (preg_split('/\s+/', trim($name)) as $word) {
        $out .= mb_strtoupper(mb_substr($word, 0, 1));
    }

    return $out;
}
