<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Brand bar. $nav = true on the landing page (section links + CTA). */
?>
<header class="topbar">
    <div class="container">
        <a class="logo" href="<?php echo duns_public_url(); ?>">
            <?php if ($landing['logo'] !== '') { ?>
                <img src="<?php echo html_escape($landing['logo']); ?>" alt="">
            <?php } else { ?>
                <span class="mark"><i class="fa-solid fa-id-card"></i></span>
            <?php } ?>
            <span><?php echo html_escape($landing['brand']); ?></span>
        </a>
        <?php if (!empty($nav)) { ?>
        <nav class="nav">
            <a href="#pricing">Pricing</a>
            <a href="#how">How it works</a>
            <a href="#documents">Documents</a>
            <a href="#faq">FAQ</a>
        </nav>
        <a href="#order" class="btn btn-primary" data-cta>Get DUNS Now</a>
        <?php } elseif ($landing['wa_link'] !== '') { ?>
        <a href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" class="btn btn-ghost" onclick="dunsTrack('Contact')"><i class="fa-brands fa-whatsapp"></i> Help</a>
        <?php } ?>
    </div>
</header>
