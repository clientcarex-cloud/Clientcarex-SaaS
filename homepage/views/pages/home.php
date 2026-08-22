<section class="hero">
  <div class="container hero__inner">
    <div class="trust-pill">
      <img src="<?= asset('assets/img/users-1.webp') ?>" alt="" width="120" height="32">
      <span class="trust-pill__text">
        <strong class="trust-pill__title">Performance marketing &amp; business automation</strong>
        <span class="trust-pill__sub">Growth funded by us, paid for out of results</span>
      </span>
    </div>

    <h1>We generate the revenue. You pay us a share of it.</h1>

    <p class="hero__lede">
      No setup fee, no retainer, no ad budget out of your pocket. We fund the
      ad spend, the media buyers, the creative and the tools — and charge
      <?= SHARE_RANGE ?> of the revenue we actually generate. Then we automate
      the operations behind it so growth doesn't break the business.
    </p>

    <?php part('buttons', ['items' => [
        ['Get a free growth audit', 'contact', 'btn--lg'],
        ['See how pricing works', 'pricing', 'btn--ghost btn--lg'],
    ]]) ?>

    <p class="hero__note"><?= icon('shield', 16) ?>₹0 setup · ₹0 retainer · we fund the ad spend</p>
  </div>
</section>

<section class="section section--tight logo-cloud">
  <div class="container">
    <h2 class="logo-cloud__title" data-reveal>
      Brands that trusted us to run their growth
    </h2>
    <div class="logo-cloud__grid" data-reveal>
      <?php foreach (CLIENT_LOGOS as [$file, $alt, $w, $h]): ?>
        <img src="<?= asset('assets/img/' . $file) ?>" alt="<?= e($alt) ?>" width="<?= $w ?>" height="<?= $h ?>" loading="lazy">
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="section section--paper" id="services">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Two engines',
        'title'   => 'One agency, two ways to be paid',
        'lede'    => 'Marketing is charged against the revenue it produces. Automation is charged against the scope it takes to build. Nothing in between.',
    ]) ?>

    <?php
    part('split', [
        'img'     => 'saas-ai.svg',
        'alt'     => 'Performance marketing dashboard',
        'eyebrow' => 'Engine one · Revenue share',
        'title'   => 'Performance Marketing',
        'body'    => 'We take over paid media, creative, funnels and lifecycle — and we pay for all of it. Ad spend, manpower, subscriptions, assets: every expense sits on our side of the ledger. You pay ' . SHARE_RANGE . ' of the revenue we generate, and nothing at all if we generate none.',
        'list'    => [
            'Zero setup fee, zero monthly retainer',
            'Ad spend, team, creative and tools funded by us',
            'Charged only against tracked, realised revenue',
        ],
        'button'  => ['How the revenue share works', 'performance-marketing'],
    ]);

    part('split', [
        'flip'    => true,
        'img'     => 'saas-afi.svg',
        'alt'     => 'Automated workflows across a business',
        'eyebrow' => 'Engine two · Scoped build',
        'title'   => 'Business Automation',
        'body'    => 'Leads, follow-up, billing, approvals, HR, support and reporting — mapped, built and handed over. We discover your processes, write the scope, and quote a fixed price against it. No per-seat licence, no open-ended hourly bill.',
        'list'    => [
            'Free discovery and a written workflow map',
            'Fixed quote against an agreed scope document',
            'Built on your data, reviewed piece by piece',
        ],
        'button'  => ['How scoping works', 'business-automation'],
    ]);
    ?>
  </div>
</section>

<section class="section" id="what-we-fund">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'The ledger',
        'title'   => 'Every cost that normally lands on you, lands on us',
        'lede'    => "This is the whole difference. Most agencies bill you a retainer and then spend your money. We spend ours, and only bill once it has turned into your revenue.",
    ]) ?>

    <?php part('ledger', ['cols' => [
        ['ours', 'On us', 'Funded by ClientcareX, included in the revenue share at no extra cost.', LEDGER_OURS],
        ['yours', 'On you', "The short list — and the last line is the only invoice you'll get.", LEDGER_YOURS],
    ]]) ?>
  </div>
</section>

<section class="section section--dark">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'What it costs to start',
        'title'   => 'The numbers before you have made a rupee',
    ]) ?>
    <?php part('stats', ['items' => STATS_MODEL]) ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Why it works',
        'title'   => 'A pricing model that can only pay us if it paid you first',
        'lede'    => 'Three things change the moment the agency carries the cost instead of the client.',
    ]) ?>

    <div class="grid grid--3" data-reveal>
      <?php foreach (PILLARS as [$img, $title, $body]): ?>
        <article class="card">
          <div class="card__icon"><img src="<?= asset('assets/img/' . $img) ?>" alt="" width="30" height="30" loading="lazy"></div>
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="section section--paper" id="channels">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Performance marketing',
        'title'   => 'What we run, on our budget',
        'lede'    => 'Channel mix is decided by what pays back, not by what you bought last year.',
    ]) ?>
    <?php part('service-grid', ['items' => MARKETING_SERVICES]) ?>
    <?php part('buttons', [
        'items' => [['See the full marketing model', 'performance-marketing', 'btn--ghost']],
        'class' => 'btn-row btn-row--center',
        'style' => 'margin-top:2.5rem',
    ]) ?>
  </div>
</section>

<?php part('promise', ['buttons' => [
    ['Get a free growth audit', 'contact', 'btn--lime btn--lg'],
    ['See how an engagement runs', 'how-it-works', 'btn--ghost btn--lg'],
]]) ?>

<section class="section" id="automation">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Business automation',
        'title'   => 'What we build, priced on scope',
        'lede'    => 'Growth exposes every manual process you were tolerating. These are the ones we take off your team first.',
    ]) ?>
    <?php part('service-grid', ['items' => AUTOMATION_SERVICES]) ?>
    <?php part('buttons', [
        'items' => [['See how automation is scoped', 'business-automation', 'btn--ghost']],
        'class' => 'btn-row btn-row--center',
        'style' => 'margin-top:2.5rem',
    ]) ?>
  </div>
</section>

<section class="section section--paper" id="how">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'How it works',
        'title'   => 'Four steps, and the first two are free',
        'lede'    => 'Nothing is signed until the model, the rate and the scope are all on paper.',
    ]) ?>
    <?php part('steps', ['items' => STEPS_ENGAGEMENT]) ?>
  </div>
</section>

<section class="section" id="fit">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Honest fit',
        'title'   => "We turn work down, and here's when",
        'lede'    => 'Because we fund the campaign first, we can only take on businesses the model actually works for. Better to find that out in the audit than three months in.',
    ]) ?>
    <?php part('ledger', ['cols' => [
        ['ours', 'A good fit', null, FIT_YES],
        ['no', 'Not a fit — yet', null, FIT_NO],
    ]]) ?>
  </div>
</section>

<section class="section section--paper" id="pricing">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Pricing',
        'title'   => 'Pick the engine, or take both',
        'lede'    => 'Two commercial models, written plainly. The rate and the scope are always fixed in writing before work starts.',
    ]) ?>
    <?php part('models') ?>
  </div>
</section>

<section class="section" id="reviews">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Reviews',
        'title'   => 'What our clients say',
        'lede'    => 'From the owners who let us put our own money behind their growth.',
    ]) ?>

    <div class="grid grid--4" data-reveal>
      <?php foreach (REVIEWS as [$name, $role, $quote]): ?>
        <article class="review">
          <div class="review__stars" aria-label="5 out of 5 stars"><?= str_repeat(icon('star', 15), 5) ?></div>
          <p><?= e($quote) ?></p>
          <div class="review__person">
            <span class="avatar" aria-hidden="true"><?= e(initials($name)) ?></span>
            <span>
              <span class="review__name"><?= e($name) ?></span>
              <span class="review__role"><?= e($role) ?></span>
            </span>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="section section--paper" id="faq">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'FAQs',
        'title'   => 'The questions everyone asks about revenue share',
        'lede'    => 'How the percentage is set, how revenue is proven, what happens to refunds — and what automation costs.',
    ]) ?>
    <?php part('faq') ?>
    <?php part('buttons', [
        'items' => [['Ask us something else', 'contact', 'btn--ghost']],
        'class' => 'btn-row btn-row--center',
        'style' => 'margin-top:2rem',
    ]) ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php part('contact-strip', ['style' => 'margin-bottom:clamp(2.5rem,5vw,4rem)']) ?>

    <div class="cta" data-reveal>
      <h2>Find out what we'd be willing to fund</h2>
      <p>
        Tell us what you sell and what it costs you to deliver. The audit comes
        back with the share rate we'd propose, the channels we'd run, and a
        straight answer on whether <?= SITE_NAME ?> should take this on at all.
      </p>
      <?php part('buttons', ['items' => [
          ['Get a free growth audit', 'contact', 'btn--lime btn--lg'],
          ['See pricing', 'pricing', 'btn--ghost btn--lg'],
      ], 'class' => 'btn-row btn-row--center']) ?>
    </div>
  </div>
</section>
