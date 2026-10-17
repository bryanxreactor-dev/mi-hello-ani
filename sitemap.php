<?php
// Dynamic sitemap generator
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$base = APP_URL;
$anime = db()->fetchAll("SELECT slug, updated_at FROM anime ORDER BY updated_at DESC");

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= $base ?>/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc><?= $base ?>/browse</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
  <url><loc><?= $base ?>/schedule</loc><changefreq>daily</changefreq><priority>0.7</priority></url>
  <url><loc><?= $base ?>/about</loc><changefreq>monthly</changefreq><priority>0.4</priority></url>
  <url><loc><?= $base ?>/contact</loc><changefreq>monthly</changefreq><priority>0.4</priority></url>
  <url><loc><?= $base ?>/faq</loc><changefreq>monthly</changefreq><priority>0.4</priority></url>
  <url><loc><?= $base ?>/dmca</loc><changefreq>monthly</changefreq><priority>0.3</priority></url>
  <url><loc><?= $base ?>/privacy</loc><changefreq>monthly</changefreq><priority>0.3</priority></url>
  <url><loc><?= $base ?>/terms</loc><changefreq>monthly</changefreq><priority>0.3</priority></url>
<?php foreach($anime as $a): ?>
  <url>
    <loc><?= $base ?>/anime/<?= htmlspecialchars($a['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($a['updated_at'])) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; ?>
</urlset>
