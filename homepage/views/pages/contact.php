<?php
/** @var array $form  ['errors' => [...], 'values' => [...], 'sent' => bool] */
$errors = $form['errors'];
$old    = static fn (string $k): string => e($form['values'][$k] ?? '');

$fields = [
    ['name', 'Full name', 'text', 'name', true],
    ['company', 'Company', 'text', 'organization', false],
    ['email', 'Work email', 'email', 'email', true],
    ['phone', 'Phone', 'tel', 'tel', false],
];

part('page-hero', [
    'crumb' => 'Contact',
    'title' => 'Get a free growth audit',
    'lede'  => "Tell us what you sell, what it costs you to deliver, and where the manual work is. We'll come back with the share rate we'd propose, the automation worth scoping — or a straight no.",
]);
?>

<section class="section">
  <div class="container">
    <div class="split" style="align-items:start">
      <div class="split__body" data-reveal>
        <span class="eyebrow">Talk to us</span>
        <h2>Straight to a human</h2>
        <p>
          No call-centre queue. Reach the team directly by phone or email, or
          send the form and we'll come back within one working day.
        </p>

        <?php part('contact-strip', ['style' => 'grid-template-columns:1fr; margin-top:2rem']) ?>

        <?php part('check-list', ['items' => [
            'The audit is free and there is nothing to sign',
            'We tell you plainly if the model does not fit',
            'Nothing launches before the rate and scope are in writing',
        ]]) ?>

        <p class="muted" style="font-size:.9375rem">
          Already a client?
          <a class="link-arrow" href="<?= APP_LOGIN ?>">Open your reporting dashboard</a>
        </p>
      </div>

      <form class="form" method="post" action="<?= url('contact') ?>" data-reveal>
        <?php if ($form['sent']): ?>
          <p class="field__hint" role="status" style="margin-bottom:1rem">
            Thanks — your request is with the team. We'll come back to you within one working day.
          </p>
        <?php elseif (isset($errors['form'])): ?>
          <p class="field__hint" role="alert" style="margin-bottom:1rem"><?= e($errors['form']) ?></p>
        <?php endif ?>

        <div class="form__row">
          <?php foreach (array_slice($fields, 0, 2) as [$id, $label, $type, $auto, $req]): ?>
            <?php part('field', compact('id', 'label', 'type', 'auto', 'req', 'errors') + ['value' => $old($id)]) ?>
          <?php endforeach ?>
        </div>

        <div class="form__row">
          <?php foreach (array_slice($fields, 2) as [$id, $label, $type, $auto, $req]): ?>
            <?php part('field', compact('id', 'label', 'type', 'auto', 'req', 'errors') + ['value' => $old($id)]) ?>
          <?php endforeach ?>
        </div>

        <?php part('select', [
            'id'      => 'interest',
            'label'   => "What are you after?",
            'options' => INTERESTS,
            'value'   => $form['values']['interest'] ?? '',
        ]) ?>

        <?php part('select', [
            'id'      => 'revenue',
            'label'   => 'Current monthly revenue',
            'options' => REVENUE_BANDS,
            'value'   => $form['values']['revenue'] ?? '',
        ]) ?>

        <div class="field">
          <label for="message">What do you sell, and where does growth get stuck?</label>
          <textarea id="message" name="message" rows="5"
                    placeholder="e.g. D2C skincare, ₹1,200 AOV, roughly 55% gross margin. Meta ads plateaued, lead follow-up is manual on WhatsApp."><?= $old('message') ?></textarea>
          <span class="field__hint">
            <?= isset($errors['message']) ? e($errors['message']) : 'Margins and average order value help most — they decide whether a revenue share can work.' ?>
          </span>
        </div>

        <p style="position:absolute;left:-9999px" aria-hidden="true">
          <label for="website">Leave this empty</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </p>
        <input type="hidden" name="token" value="<?= e(csrf_token()) ?>">

        <button class="btn btn--lg btn--block" type="submit">Get my free growth audit</button>
      </form>
    </div>
  </div>
</section>

<section class="section section--paper">
  <div class="container">
    <?php part('section-head', ['eyebrow' => 'FAQs', 'title' => 'Frequently asked questions']) ?>
    <?php part('faq') ?>
  </div>
</section>
