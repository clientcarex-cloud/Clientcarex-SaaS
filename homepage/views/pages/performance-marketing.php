<?php part('page-hero', [
    'crumb'   => 'Performance Marketing',
    'title'   => 'You only pay for the revenue we generate',
    'lede'    => 'Every expense a marketing campaign has — ad spend, manpower, subscriptions, creative, assets — is carried by us. Our entire fee is ' . SHARE_RANGE . ' of the revenue we actually produce for you.',
    'buttons' => [
        ['Get a free growth audit', 'contact', 'btn--lg'],
        ['See the ledger', '#what-we-fund', 'btn--ghost btn--lg'],
    ],
]) ?>

<section class="section section--dark">
  <div class="container">
    <?php part('stats', ['items' => STATS_MODEL]) ?>
  </div>
</section>

<section class="section" id="what-we-fund">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'The ledger',
        'title'   => 'What we fund, and the five things we don\'t',
        'lede'    => 'A traditional agency charges you a retainer and then spends your ad budget on top. Under this model there is no retainer and no ad budget — the campaign runs on our money until it produces yours.',
    ]) ?>
    <?php part('ledger', ['cols' => [
        ['ours', 'On us', 'All of it, included in the revenue share. No setup fee, no minimum spend, no pass-through invoices.', LEDGER_OURS],
        ['yours', 'On you', 'That is the complete list.', LEDGER_YOURS],
    ]]) ?>
  </div>
</section>

<section class="section section--paper" id="rate">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'The rate',
        'title'   => 'What decides whether you pay ' . SHARE_MIN . ' or ' . SHARE_MAX,
        'lede'    => 'The percentage is set once, in writing, before the first campaign goes live — and it does not move afterwards.',
    ]) ?>

    <div class="grid grid--3" data-reveal>
      <article class="card">
        <h3>Your margin</h3>
        <p>A revenue share has to sit comfortably inside your gross margin. Thin-margin, high-volume businesses land near <?= SHARE_MIN ?>; healthier margins can carry more of the share and usually want us to spend harder to get it.</p>
      </article>
      <article class="card">
        <h3>Average order value</h3>
        <p>Low-ticket, high-frequency revenue is cheaper for us to service per rupee earned, so it prices toward the bottom of the band. High-ticket, long-consideration sales need more creative, more nurture and more time.</p>
      </article>
      <article class="card">
        <h3>How much we take over</h3>
        <p>Running one channel against an offer you already own sits low. Owning the offer, creative, funnel, lifecycle and full channel mix — where we carry every cost end to end — sits at <?= SHARE_MAX ?>.</p>
      </article>
    </div>

    <?php part('notice', ['html' => '<strong>No hidden second bill.</strong> The share is the whole invoice.
        We do not add a management fee on top of ad spend, we do not mark up
        creative or tooling, and we do not bill for hours.']) ?>
  </div>
</section>

<section class="section" id="attribution">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Attribution',
        'title'   => 'How we prove which revenue was ours',
        'lede'    => "A revenue share is only fair if both sides can see the same number. So attribution is built before the first ad runs, and reconciled against your books every month.",
    ]) ?>

    <div class="grid grid--2" data-reveal>
      <article class="card">
        <h3><?= icon('trending', 20) ?> Tracked at the source</h3>
        <p>
          Pixel and server-side event tracking, dedicated landing pages, unique
          coupon codes, call-tracking numbers or a CRM source stage — whichever
          combination actually proves origin in your business. We agree which
          applies during the audit, and it goes into the agreement.
        </p>
      </article>
      <article class="card">
        <h3><?= icon('shield', 20) ?> Reconciled against your books</h3>
        <p>
          Each month you get the tracked figure and the workings behind it. You
          check it against your own sales records. We invoice the agreed number,
          not whatever our dashboard says on its own — and refunds, cancellations
          and returns come out before the share is calculated.
        </p>
      </article>
    </div>
  </div>
</section>

<section class="section section--paper" id="services">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Scope of work',
        'title'   => 'What we actually run',
        'lede'    => 'The mix is chosen by payback, not by preference. If a channel stops paying, we move the budget — it is our budget.',
    ]) ?>
    <?php part('service-grid', ['items' => MARKETING_SERVICES]) ?>
  </div>
</section>

<section class="section" id="process">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Timeline',
        'title'   => 'From audit to first reconciled invoice',
    ]) ?>
    <?php part('steps', ['items' => STEPS_MARKETING]) ?>
  </div>
</section>

<section class="section section--paper" id="fit">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Honest fit',
        'title'   => 'When this model works, and when it does not',
        'lede'    => 'We put our own capital behind the campaign, so we can only say yes where the numbers support it. The audit tells you which side you are on.',
    ]) ?>
    <?php part('ledger', ['cols' => [
        ['ours', 'A good fit', null, FIT_YES],
        ['no', 'Not a fit — yet', null, FIT_NO],
    ]]) ?>
  </div>
</section>

<?php part('promise', ['buttons' => [
    ['Get a free growth audit', 'contact', 'btn--lime btn--lg'],
    ['Read the billing terms', 'refund', 'btn--ghost btn--lg'],
]]) ?>

<section class="section">
  <div class="container">
    <?php part('section-head', ['eyebrow' => 'FAQs', 'title' => 'Revenue share, answered']) ?>
    <?php part('faq') ?>
  </div>
</section>

<?php part('cta', [
    'title'   => 'Send us your numbers and we will tell you the rate',
    'lede'    => 'What you sell, what it costs you to deliver, and where revenue comes from today. That is enough for us to come back with a share rate, a channel plan — or a straight no.',
    'buttons' => [
        ['Get a free growth audit', 'contact', 'btn--lime btn--lg'],
        ['Compare both models', 'pricing', 'btn--ghost btn--lg'],
    ],
]) ?>
