<?php
part('page-hero', [
    'eyebrow' => 'Error 404',
    'title'   => 'That page has moved on',
    'lede'    => "The link you followed doesn't exist any more. Here's the way back to the things people usually come here for.",
    'buttons' => [
        ['Back to home', '', 'btn--lg'],
        ['Contact us', 'contact', 'btn--ghost btn--lg'],
    ],
]);

$routes = [
    ['Performance Marketing', 'We fund the ads, the team and the creative, and charge ' . SHARE_RANGE . ' of the revenue we generate.', 'See the model', 'performance-marketing'],
    ['Business Automation', 'Processes mapped, scoped and built for one fixed price — no per-seat licence.', 'See how scoping works', 'business-automation'],
    ['Pricing', 'Both commercial models side by side, with what each one already covers.', 'Compare models', 'pricing'],
];
?>
<section class="section">
  <div class="container">
    <div class="grid grid--3">
      <?php foreach ($routes as [$title, $body, $label, $href]): ?>
        <article class="card">
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
          <p style="margin-top:1rem"><?php part('link-arrow', ['label' => $label, 'href' => $href]) ?></p>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
