<?php
/**
 * Plan du site XML dynamique (SEO)
 * Cetin Family
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

$base_url = get_site_base_url();
$lastmod = date('Y-m-d');

$pages = [
    ['path' => '/index.php',       'changefreq' => 'monthly', 'priority' => '1.0'],
    ['path' => '/prestations.php', 'changefreq' => 'monthly', 'priority' => '0.9'],
    ['path' => '/realisations.php','changefreq' => 'monthly', 'priority' => '0.8'],
    ['path' => '/a-propos.php',    'changefreq' => 'yearly',  'priority' => '0.6'],
    ['path' => '/contact.php',     'changefreq' => 'yearly',  'priority' => '0.9'],
    ['path' => '/mentions-legales.php', 'changefreq' => 'yearly', 'priority' => '0.3'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?>
    <url>
        <loc><?php echo htmlspecialchars($base_url . $page['path'], ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq><?php echo $page['changefreq']; ?></changefreq>
        <priority><?php echo $page['priority']; ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
