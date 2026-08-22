<?php part('page-hero', [
    'crumb'   => 'Business Automation',
    'title'   => 'Automation priced on your scope, not per seat',
    'lede'    => 'We map how your business actually runs, write the scope, and quote one fixed price against it. What you pay is decided by the work and the implementation it needs — nothing else.',
    'buttons' => [
        ['Scope my automation', 'contact', 'btn--lg'],
        ['See how scoping works', '#scoping', 'btn--ghost btn--lg'],
    ],
]) ?>

<section class="section" id="scoping">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'How it is priced',
        'title'   => 'Scope first, quote second',
        'lede'    => 'You never get a number before we understand the work. And once the number exists, it is fixed against a document you have read.',
    ]) ?>

    <div class="grid grid--3" data-reveal>
      <article class="card">
        <div class="card__icon"><?= icon('search', 26) ?></div>
        <h3>1 · Discovery, free</h3>
        <p>We sit with the people doing the work and map the processes — every handoff, spreadsheet and WhatsApp thread holding something together. You keep the workflow map whether or not you go ahead.</p>
      </article>
      <article class="card">
        <div class="card__icon"><?= icon('briefcase', 26) ?></div>
        <h3>2 · Scope document</h3>
        <p>Which processes, which integrations, which systems, how many workflows, and what is explicitly out of scope. Written down and agreed before pricing.</p>
      </article>
      <article class="card">
        <div class="card__icon"><?= icon('wallet-out', 26) ?></div>
        <h3>3 · Fixed quote</h3>
        <p>One price for the build, quoted against that document. Optional ongoing support is quoted separately and plainly. If scope changes later, we re-quote the change — we don't slip it onto an invoice.</p>
      </article>
    </div>

    <?php part('notice', ['html' => '<strong>What drives the number:</strong> how many processes are in
        scope, how many systems have to talk to each other, how much custom
        integration work sits between them, how much data has to be migrated,
        and how much training and handover your team needs.']) ?>
  </div>
</section>

<section class="section section--paper" id="services">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'What we build',
        'title'   => 'The processes we take off your team',
        'lede'    => 'Take one area or all of them. Scope is yours to set — the quote follows it.',
    ]) ?>
    <?php part('service-grid', ['items' => AUTOMATION_SERVICES]) ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php part('split', [
        'img'     => 'saas-afi.svg',
        'alt'     => 'A mapped and automated workflow',
        'eyebrow' => 'What you get',
        'title'   => 'A working process, not a login and a documentation link',
        'body'    => 'The deliverable is your business running on automation you can see working on your own data — configured, tested, documented and handed to the people who use it every day.',
        'list'    => [
            'A written workflow map of how the business runs today',
            'Configured systems, not an empty tenant',
            'Integrations wired between the tools you already pay for',
            'SOPs and training for the team that inherits it',
            'Dashboards for the numbers owners actually check',
        ],
        'button'  => ['Book a free discovery', 'contact'],
    ]) ?>
  </div>
</section>

<section class="section section--paper" id="integrations">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Integrations',
        'title'   => 'Connected to the tools you already pay for',
        'lede'    => 'And where no connector exists, we build the API work rather than asking you to change systems.',
    ]) ?>

    <div class="grid grid--3" data-reveal>
      <?php foreach (INTEGRATIONS as [$name, $tag, $logo, $body]): ?>
        <article class="card integration">
          <div class="integration__head">
            <span class="integration__logo"><img src="<?= asset('assets/img/' . $logo) ?>" alt="" width="26" height="26" loading="lazy"></span>
            <h3><?= e($name) ?><span class="integration__tag"><?= e($tag) ?></span></h3>
          </div>
          <p><?= e($body) ?></p>
          <?php part('link-arrow', ['label' => 'Ask about this one', 'href' => 'contact']) ?>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="section" id="process">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Timeline',
        'title'   => 'How a build runs',
        'lede'    => 'In reviewable pieces, on your data, so nothing is discovered at handover.',
    ]) ?>
    <?php part('steps', ['items' => STEPS_AUTOMATION]) ?>
  </div>
</section>

<section class="section section--dark">
  <div class="container">
    <div class="riskfree">
      <div data-reveal>
        <span class="eyebrow">Better together</span>
        <h2>Automation is what stops growth from breaking you</h2>
        <p>
          Paid media that works produces more leads, more orders and more support
          load than the current process was built for. Clients who run both
          engines put the leads we generate straight into automated follow-up,
          billing and support — and see the same revenue in one report.
        </p>
        <?php part('buttons', [
            'items' => [
                ['See the marketing model', 'performance-marketing', 'btn--lime btn--lg'],
                ['Compare both models', 'pricing', 'btn--ghost btn--lg'],
            ],
            'style' => 'margin-top:1.75rem',
        ]) ?>
      </div>
      <figure class="quote-card" data-reveal>
        <blockquote>&ldquo;<?= e(QUOTE['text']) ?>&rdquo;</blockquote>
        <figcaption>
          <span class="avatar" aria-hidden="true"><?= e(initials(QUOTE['name'])) ?></span>
          <span>
            <cite><?= e(QUOTE['name']) ?></cite>
            <span><?= e(QUOTE['role']) ?></span>
          </span>
        </figcaption>
      </figure>
    </div>
  </div>
</section>

<section class="section section--paper">
  <div class="container">
    <?php part('section-head', ['eyebrow' => 'FAQs', 'title' => 'Scoping and pricing, answered']) ?>
    <?php part('faq') ?>
  </div>
</section>

<?php part('cta', [
    'title'   => 'Bring us the process that keeps breaking',
    'lede'    => 'Discovery is free and you keep the workflow map either way. If automating it is not worth the money, we will say so.',
    'buttons' => [
        ['Scope my automation', 'contact', 'btn--lime btn--lg'],
        ['See pricing models', 'pricing', 'btn--ghost btn--lg'],
    ],
]) ?>
