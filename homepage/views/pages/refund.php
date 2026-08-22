<?php part('page-hero', [
    'crumb' => 'Billing & Refunds',
    'title' => 'Billing & Refund Policy',
    'lede'  => 'How revenue share is measured, reconciled and invoiced, how disputes are handled, and how automation project payments and refunds work.',
]) ?>

<section class="section">
  <div class="container prose">

    <?php part('notice', ['html' => '<strong>Draft for review.</strong> This policy describes the billing
        model as published on this site. Have it reviewed by your legal adviser
        and matched to your signed client agreements before go-live.']) ?>

    <h2>1. Performance marketing — what you are billed</h2>
    <p>
      One line item: the agreed percentage — between <?= SHARE_MIN ?> and
      <?= SHARE_MAX ?> — of Attributed Revenue for the billing month. There is no
      setup fee, no retainer, no minimum spend and no separate charge for ad
      spend, personnel, creative, subscriptions or assets. Those costs are ours
      and are already inside the share.
    </p>

    <h2>2. How Attributed Revenue is measured</h2>
    <p>
      The attribution method is agreed in writing before the first campaign goes
      live and recorded in your engagement letter. Depending on the business it
      may combine:
    </p>
    <ul>
      <li>Pixel and server-side conversion tracking</li>
      <li>Dedicated landing pages or campaign-specific URLs</li>
      <li>Unique coupon or promotion codes</li>
      <li>Call-tracking numbers</li>
      <li>A source or origin stage recorded in your CRM</li>
    </ul>
    <p>
      Attributed Revenue is calculated <strong>net</strong>: taxes, refunds,
      cancellations, returns, chargebacks and amounts never collected are
      deducted before the share is applied.
    </p>

    <h2>3. Monthly reconciliation</h2>
    <ol>
      <li>Within five working days of month end we send the tracked figure and the workings behind it.</li>
      <li>You have five working days to check it against your own sales records and confirm or query it.</li>
      <li>Once the figure is agreed, we invoice the share against it. We do not invoice against our dashboard alone.</li>
      <li>Invoices are payable within the period stated in your engagement letter.</li>
    </ol>

    <h2>4. Adjustments and clawbacks</h2>
    <p>
      Where an order is refunded, cancelled, returned or charged back after we
      have already invoiced the share on it, the corresponding amount is credited
      against the next invoice — or refunded to you if no further invoice is due.
      Adjustments run in both directions: revenue that arrives after a cut-off is
      added to the following month.
    </p>

    <h2>5. Disputed figures</h2>
    <p>
      If you dispute part of a figure, pay the undisputed portion and raise the
      rest in writing within the five-day review window. We will share the
      underlying tracking data and work to an agreed number. Unresolved
      differences are escalated as set out in your engagement letter.
    </p>

    <h2>6. If campaigns generate nothing</h2>
    <p>
      Then there is nothing to invoice. Ad spend, personnel and production costs
      already incurred are ours, and are not recoverable from you. That is the
      risk we take in exchange for the share.
    </p>

    <h2>7. Ending a marketing engagement</h2>
    <p>
      Either party may end the engagement on 30 days' written notice. The share
      remains payable on Attributed Revenue generated up to the end of the notice
      period, plus tracked repeat revenue for the tail period stated in your
      engagement letter. No termination fee is charged.
    </p>

    <h2>8. Business automation payments</h2>
    <ul>
      <li>Discovery and the workflow map are free, and you keep the map whether or not you proceed.</li>
      <li>The build fee is fixed against the agreed scope and invoiced at the milestones set out in it.</li>
      <li>Milestone payments become non-refundable once the work for that milestone has been delivered and accepted.</li>
      <li>If you cancel mid-build, you pay for work completed and accepted to that point; anything paid in advance of it is refunded.</li>
      <li>Change requests outside the agreed scope are quoted and approved separately before any work starts.</li>
    </ul>

    <h2>9. What is never refundable</h2>
    <ul>
      <li>Third-party costs you asked us to buy in your name and which have already been spent.</li>
      <li>Automation milestones already delivered and accepted.</li>
      <li>Revenue share correctly invoiced against revenue you received and kept.</li>
    </ul>

    <h2>10. How to raise a billing query or refund request</h2>
    <p>
      Email <a href="mailto:<?= EMAIL ?>"><?= EMAIL ?></a> from the account
      owner's address with your organisation name, the invoice number and what
      you would like corrected. We acknowledge within two working days. Approved
      refunds are returned to the original payment method and typically appear
      within 7–14 working days, depending on your bank.
    </p>

    <h2>11. Contact</h2>
    <p>
      Questions about this policy:
      <a href="mailto:<?= EMAIL ?>"><?= EMAIL ?></a> or <?= PHONE ?>.
    </p>

    <p class="muted" style="margin-top:2rem">Last updated: <?= date('Y') ?></p>
  </div>
</section>
