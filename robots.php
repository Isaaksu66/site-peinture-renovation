<?php
/**
 * Fichier robots.txt dynamique (SEO)
 * Cetin Family
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$base_url = get_site_base_url();
?>
# Robots.txt - Cetin Family
# Ce fichier guide les moteurs de recherche dans l'indexation du site.

User-agent: *
Allow: /

# Interdire l'indexation de l'espace d'administration et des fichiers techniques
Disallow: /admin.php
Disallow: /logout.php
Disallow: /config.php
Disallow: /db.php
Disallow: /header.php
Disallow: /footer.php

# Emplacement du plan du site
Sitemap: <?php echo $base_url; ?>/sitemap.xml
