<?php part('page-hero', [
    'crumb' => 'Blog',
    'title' => 'Blog & News',
    'lede'  => 'Paid-media teardowns, attribution notes and automation playbooks from the ClientcareX team.',
]) ?>

<section class="section">
  <div class="container">
    <div class="prose" style="max-width:52rem">
      <?php part('notice', ['html' => '<strong>No posts published yet.</strong> Add one by copying a
          <code>.post</code> card into this page — see <code>README.md</code> for
          the snippet.']) ?>

      <h2>Want the next one in your inbox?</h2>
      <p>
        We write about the unglamorous side of growth: what a revenue share
        actually costs an agency to carry, where attribution quietly lies to
        you, and which automations pay for themselves in a quarter.
      </p>
      <?php part('buttons', [
          'items' => [
              ['Get in touch', 'contact', ''],
              ['See how pricing works', 'pricing', 'btn--ghost'],
          ],
          'style' => 'margin-top:1.5rem',
      ]) ?>
    </div>
  </div>
</section>
