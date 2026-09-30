<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<footer>
    <div class="container">
        <div class="cols">
            <div>
                <h4><?php echo html_escape($landing['brand']); ?></h4>
                <p>Fast, hassle-free DUNS number registration for startups and businesses — delivered to your email, backed by a money-back guarantee.</p>
            </div>
            <div>
                <h4>Contact</h4>
                <div class="contact">
                    <?php if ($landing['phone'] !== '') { ?><a href="tel:<?php echo html_escape(preg_replace('/[^\d+]/', '', $landing['phone'])); ?>"><i class="fa-solid fa-phone"></i> <?php echo html_escape($landing['phone']); ?></a><?php } ?>
                    <?php if ($landing['wa_link'] !== '') { ?><a href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><?php } ?>
                    <?php if ($landing['email'] !== '') { ?><a href="mailto:<?php echo html_escape($landing['email']); ?>"><i class="fa-solid fa-envelope"></i> <?php echo html_escape($landing['email']); ?></a><?php } ?>
                </div>
            </div>
            <div>
                <h4>Quick links</h4>
                <div class="contact">
                    <a href="<?php echo duns_public_url(); ?>#pricing">Pricing</a>
                    <a href="<?php echo duns_public_url(); ?>#documents">Required documents</a>
                    <a href="<?php echo duns_public_url(); ?>#faq">FAQ</a>
                    <?php if ($landing['terms_url'] !== '') { ?><a href="<?php echo html_escape($landing['terms_url']); ?>" target="_blank" rel="noopener">Terms &amp; refund policy</a><?php } ?>
                </div>
            </div>
        </div>
        <div class="legal">
            © <?php echo date('Y'); ?> <?php echo html_escape($landing['brand']); ?>. We are an independent registration service provider and are not affiliated with, endorsed by or acting on behalf of Dun &amp; Bradstreet, Google or Apple. D-U-N-S® is a registered trademark of Dun &amp; Bradstreet. Google Play and Apple are trademarks of their respective owners.
        </div>
    </div>
</footer>
