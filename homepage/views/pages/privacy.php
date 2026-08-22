<?php part('page-hero', [
    'crumb' => 'Privacy Policy',
    'title' => 'Privacy Policy',
    'lede'  => 'How Clientcarex AI Private Limited collects, uses and protects your data.',
]) ?>

<section class="section">
  <div class="container prose">

    <h2>1. Who we are</h2>
    <p>
      <?= COMPANY ?> (“ClientcareX”, “we”, “us”) is a performance
      marketing and business automation agency. For any privacy question,
      contact us at <a href="mailto:<?= EMAIL ?>"><?= EMAIL ?></a> or
      <?= PHONE ?>.
    </p>

    <h2>2. Information we collect</h2>
    <ul>
      <li><strong>Enquiry data</strong> — name, work email, phone number, company, what you are interested in and the revenue band you select on our forms.</li>
      <li><strong>Client data</strong> — the records we process on your behalf while running an engagement: leads and enquiries our campaigns generate, sales and refund figures used for reconciliation, and the records held in systems we automate for you.</li>
      <li><strong>Campaign and measurement data</strong> — ad platform metrics, conversion events, click identifiers, coupon and call-tracking references, and CRM source stages used for attribution.</li>
      <li><strong>Website usage data</strong> — pages viewed, device and browser information, and IP address.</li>
      <li><strong>Communications</strong> — messages you send us by email, phone or through forms on this site.</li>
    </ul>

    <h2>3. How we use it</h2>
    <ul>
      <li>To respond to enquiries and prepare growth audits and proposals.</li>
      <li>To plan, run, measure and optimise marketing campaigns on your behalf.</li>
      <li>To calculate and reconcile attributed revenue for invoicing.</li>
      <li>To scope, build, support and hand over automation work.</li>
      <li>To send service notices and, where you have opted in, occasional updates.</li>
      <li>To meet legal, tax and accounting obligations.</li>
    </ul>

    <h2>4. Legal basis</h2>
    <p>
      We process personal data to perform our contract with you, to meet legal
      obligations, on the basis of your consent where required, and for our
      legitimate interest in operating and securing the service.
    </p>

    <h2>5. Sharing</h2>
    <p>
      We do not sell personal data. We share it only with sub-processors that
      help us deliver the engagement — advertising platforms, analytics and
      attribution tools, CRM and automation platforms, hosting, email, SMS and
      WhatsApp providers, and payment processors — under contracts that restrict
      their use of it, and with authorities where the law requires. Where we run
      advertising on your behalf, ad platforms act as independent controllers for
      the data they collect on their own surfaces.
    </p>

    <h2>6. Retention</h2>
    <p>
      Enquiry data is retained for up to 24 months. Client and campaign data is
      retained for as long as the engagement is active, and afterwards only as
      long as needed for reconciliation, legal, accounting or dispute-resolution
      purposes.
    </p>

    <h2>7. Your rights</h2>
    <p>
      Subject to applicable law, you may request access to, correction of, or
      deletion of your personal data, object to certain processing, or ask for a
      portable copy. Where we process data on a client's behalf we will refer
      the request to that client. Write to
      <a href="mailto:<?= EMAIL ?>"><?= EMAIL ?></a> and we will
      respond within the period the law allows.
    </p>

    <h2>8. Security</h2>
    <p>
      We apply access controls, encryption in transit and regular backups, and
      we limit access to client accounts to the people working on your
      engagement. No system is perfectly secure, so please use strong, unique
      credentials and enable multi-factor authentication on any account you
      share with us.
    </p>

    <h2>9. Cookies</h2>
    <p>
      This website uses cookies that are necessary for it to function, and — with
      your consent where required — analytics and advertising cookies that tell
      us which pages and campaigns are working. You can clear or block cookies
      in your browser settings.
    </p>

    <h2>10. Changes</h2>
    <p>
      We will post any update to this policy on this page and revise the date
      below. Material changes will also be notified to active clients by email.
    </p>

    <p class="muted" style="margin-top:2rem">Last updated: <?= date('Y') ?></p>
  </div>
</section>
