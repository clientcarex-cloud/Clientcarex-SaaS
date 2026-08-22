<?php part('page-hero', [
    'crumb' => 'How It Works',
    'title' => 'Four steps, and the first two cost you nothing',
    'lede'  => "We don't quote before we understand the business, and we don't launch before both sides agree what counts as revenue. Here is exactly how an engagement runs.",
]) ?>

<section class="section">
  <div class="container">
    <?php part('steps', ['items' => STEPS_ENGAGEMENT]) ?>
  </div>
</section>

<section class="section section--dark">
  <div class="container">
    <?php part('stats', ['items' => STATS_ENGAGEMENT]) ?>
  </div>
</section>

<section class="section" id="marketing">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Engine one',
        'title'   => 'Performance marketing, week by week',
        'lede'    => 'Attribution is built before a single rupee of ad spend, because everything we invoice later depends on it.',
    ]) ?>
    <?php part('steps', ['items' => STEPS_MARKETING]) ?>
    <?php part('buttons', [
        'items' => [['See the revenue-share model', 'performance-marketing', 'btn--ghost']],
        'class' => 'btn-row btn-row--center',
        'style' => 'margin-top:2.5rem',
    ]) ?>
  </div>
</section>

<section class="section section--paper" id="automation">
  <div class="container">
    <?php part('section-head', [
        'eyebrow' => 'Engine two',
        'title'   => 'Business automation, phase by phase',
        'lede'    => 'Mapped, scoped, quoted, then built in pieces you review as they land.',
    ]) ?>
    <?php part('steps', ['items' => STEPS_AUTOMATION]) ?>
    <?php part('buttons', [
        'items' => [['See how scoping works', 'business-automation', 'btn--ghost']],
        'class' => 'btn-row btn-row--center',
        'style' => 'margin-top:2.5rem',
    ]) ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php part('split', [
        'img'     => 'saas-ai.svg',
        'alt'     => 'Reporting dashboard showing revenue by source',
        'eyebrow' => 'Reporting',
        'title'   => 'One number, and both of us can see it',
        'body'    => "Every engagement comes with a dashboard you can open any day of the month, showing spend, cost per acquisition and tracked revenue by source. At month end we reconcile it against your own records and agree the figure before anything is invoiced.",
        'list'    => [
            'Live dashboard, not a monthly PDF',
            'Revenue broken down by channel and campaign',
            'Refunds and cancellations removed before the share',
            'Monthly reconciliation against your books',
        ],
        'button'  => ['Read the billing terms', 'refund'],
    ]) ?>
  </div>
</section>

<?php part('promise', [
    'title'   => "The audit is free, and so is being told no",
    'body'    => 'Because we fund the campaign before we earn anything, we only take on businesses the model works for. If your margins or your tracking cannot support a revenue share, we will tell you in the audit rather than three months into a contract.',
    'buttons' => [['Get a free growth audit', 'contact', 'btn--lime btn--lg']],
]) ?>

<?php part('cta', [
    'title'   => 'Start with the audit',
    'lede'    => "Thirty minutes and a look at your numbers is usually enough to tell whether we can help — and we'll say so if we can't.",
    'buttons' => [['Get a free growth audit', 'contact', 'btn--lime btn--lg']],
]) ?>
