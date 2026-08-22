<?php part('page-hero', [
    'crumb' => 'Pricing',
    'title' => 'Two models. Both written down before we start.',
    'lede'  => 'Performance marketing is charged as ' . SHARE_RANGE . ' of the revenue we generate, with every expense on us. Business automation is quoted as a fixed price against an agreed scope. There is nothing else on the invoice.',
]) ?>

<section class="section">
  <div class="container">
    <?php part('models') ?>

    <p class="pricing-note" style="margin-top:2.5rem">
      No setup fees, no onboarding fees, no platform fees and no per-user licence
      on either model. Prices are exclusive of applicable taxes.
      <?php part('link-arrow', ['label' => 'Ask us to price your case', 'href' => 'contact', 'arrow' => false]) ?>
    </p>
  </div>
</section>

<section class="section section--paper" id="what-we-fund">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Performance marketing',
        'title'   => 'What the ' . SHARE_RANGE . ' already covers',
        'lede'    => 'This is not a management fee charged on top of a budget you fund. It is the entire cost of the engagement.',
    ]) ?>
    <?php part('ledger', ['cols' => [
        ['ours', 'Included in the share', 'Paid for by us, whether or not the campaign works.', LEDGER_OURS],
        ['yours', 'Billed to you', 'The revenue share is the only line item we raise.', LEDGER_YOURS],
    ]]) ?>
  </div>
</section>

<section class="section section--dark">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Before you commit',
        'title'   => 'What the engagement costs you to find out',
    ]) ?>
    <?php part('stats', ['items' => STATS_ENGAGEMENT]) ?>
  </div>
</section>

<section class="section" id="automation-pricing">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Business automation',
        'title'   => 'What moves an automation quote up or down',
        'lede'    => 'Scope-based pricing means the number is built from these, and from nothing else.',
    ]) ?>

    <div class="grid grid--3" data-reveal>
      <article class="card">
        <h3><?= icon('cog', 20) ?> Processes in scope</h3>
        <p>How many workflows we are automating, how many decision branches each carries, and how much of it is exception handling rather than a happy path.</p>
      </article>
      <article class="card">
        <h3><?= icon('link', 20) ?> Systems and integrations</h3>
        <p>How many tools have to talk to each other, whether connectors exist or need building, and how much data has to be migrated and cleaned on the way in.</p>
      </article>
      <article class="card">
        <h3><?= icon('users', 20) ?> Implementation depth</h3>
        <p>How many teams are affected, how much training and SOP work handover needs, and whether you want us on support afterwards or fully handed over.</p>
      </article>
    </div>

    <?php part('notice', ['html' => '<strong>Not priced per user.</strong> Adding people to a workflow we
        have already built does not raise the price. You pay for the build, once.']) ?>
  </div>
</section>

<section class="section section--paper" id="faq">
  <div class="container">
    <?php part('section-head', ['eyebrow' => 'FAQs', 'title' => 'Pricing questions, answered']) ?>
    <?php part('faq') ?>
  </div>
</section>

<?php part('cta', [
    'title'   => 'Not sure which model fits?',
    'lede'    => "Send us what you sell and where the manual work is. We'll come back with the model that fits — and say plainly if the other one would serve you better.",
    'buttons' => [
        ['Get a free growth audit', 'contact', 'btn--lime btn--lg'],
        ['See how an engagement runs', 'how-it-works', 'btn--ghost btn--lg'],
    ],
]) ?>
