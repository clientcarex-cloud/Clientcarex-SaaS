<?php
/** Generated from the page registry — never edited by hand. */
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach (PAGES as $route => $page): ?>
<?php if ($page['noindex'] ?? false) continue ?>
  <url>
    <loc><?= SITE_URL ?>/<?= $route ?></loc>
    <priority><?= $page['priority'] ?? '0.7' ?></priority>
  </url>
<?php endforeach ?>
</urlset>
