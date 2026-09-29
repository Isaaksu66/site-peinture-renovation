<?php
/**
 * Template d'en-tête (Header)
 * Cetin Family
 */

require_once __DIR__ . '/db.php';

// Charger les paramètres globaux de l'entreprise
try {
    $settingsStmt = $pdo->query("SELECT * FROM `company_settings` LIMIT 1");
    $company = $settingsStmt->fetch();
    if (!$company) {
        $company = [
            'phone' => '07 60 55 14 44',
            'email' => 'cetinfamily71@gmail.com',
            'address' => '646 Rue Albert Einstein',
            'city' => 'Mâcon',
            'postal_code' => '71000',
            'hours_week' => 'Lundi - Vendredi : 9h00 - 18h00',
            'hours_sat' => 'Samedi : Sur RDV',
            'radius_km' => 50,
            'latitude' => 46.291741,
            'longitude' => 4.817890
        ];
    }
} catch (PDOException $e) {
    $company = [
        'phone' => '07 60 55 14 44',
        'email' => 'cetinfamily71@gmail.com',
        'address' => '646 Rue Albert Einstein',
        'city' => 'Mâcon',
        'postal_code' => '71000',
        'hours_week' => 'Lundi - Vendredi : 9h00 - 18h00',
        'hours_sat' => 'Samedi : Sur RDV',
        'radius_km' => 50,
        'latitude' => 46.291741,
        'longitude' => 4.817890
    ];
}

// Valeurs par défaut pour le référencement SEO
$page_title = isset($page_title) ? $page_title : "Cetin Family | Isolation, Plâtrerie, Peinture à Mâcon";
$page_description = isset($page_description) ? $page_description : "Découvrez les services de plâtrerie, d'isolation et de peinture de Cetin Family à Mâcon (71000) et en Saône-et-Loire. Plaquiste RGE et peintre qualifié.";
$active_page = isset($active_page) ? $active_page : "accueil";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='12' fill='%23161a19'/><text x='50' y='68' font-size='52' font-family='serif' fill='%23c5a880' text-anchor='middle' font-weight='bold'>PÉ</text></svg>">
    
    <!-- Open Graph (Facebook, LinkedIn, WhatsApp) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="Cetin Family">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Feuille de style principale -->
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    
    <!-- Leaflet Map CSS - Inclus uniquement sur la page contact pour de meilleures performances -->
    <?php if ($active_page === 'contact'): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <?php endif; ?>
    
    <!-- Script principal JS -->
    <script src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>" defer></script>
    
    <!-- Leaflet Map JS - Inclus uniquement sur la page contact -->
    <?php if ($active_page === 'contact'): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
    <?php endif; ?>
    
    <!-- Données structurées Schema.org pour le référencement local Google -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "HousePainter",
        "name": "<?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?>",
        "description": "Artisan peintre professionnel spécialisé en peinture intérieure, décoration, ravalement extérieur et pose de revêtements.",
        "url": "<?php echo htmlspecialchars(get_site_base_url() . '/', ENT_QUOTES, 'UTF-8'); ?>",
        "telephone": "<?php echo htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8'); ?>",
        "email": "<?php echo htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8'); ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "<?php echo htmlspecialchars($company['address'], ENT_QUOTES, 'UTF-8'); ?>",
            "addressLocality": "<?php echo htmlspecialchars($company['city'], ENT_QUOTES, 'UTF-8'); ?>",
            "postalCode": "<?php echo htmlspecialchars($company['postal_code'], ENT_QUOTES, 'UTF-8'); ?>",
            "addressCountry": "FR"
        },
        "areaServed": {
            "@type": "GeoCircle",
            "geoMidpoint": {
                "@type": "GeoCoordinates",
                "latitude": <?php echo (float)$company['latitude']; ?>,
                "longitude": <?php echo (float)$company['longitude']; ?>
            },
            "geoRadius": "<?php echo (int)$company['radius_km'] * 1000; ?>"
        },
        "openingHoursSpecification": [
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
                "opens": "08:00",
                "closes": "18:00"
            },
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": "Saturday",
                "opens": "09:00",
                "closes": "12:00"
            }
        ],
        "priceRange": "€€"
    }
    </script>
</head>
<body class="<?php echo isset($body_class) ? htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') : ''; ?>">

    <!-- En-tête / Navigation -->
    <header id="main-header">
        <div class="logo">
            <a href="index.php" class="logo-cetin">
                <img src="assets/images/head-logo.png" alt="<?php echo htmlspecialchars(isset($company['company_name']) ? $company['company_name'] : 'Cetin Family', ENT_QUOTES, 'UTF-8'); ?>" class="logo-img">
            </a>
        </div>
        <nav id="main-nav">
            <ul>
                <li><a href="index.php" class="<?php echo $active_page === 'accueil' ? 'active' : ''; ?>">Accueil</a></li>
                <li><a href="prestations.php" class="<?php echo $active_page === 'prestations' ? 'active' : ''; ?>">Prestations</a></li>
                <li><a href="realisations.php" class="<?php echo $active_page === 'realisations' ? 'active' : ''; ?>">Réalisations</a></li>
                <li><a href="a-propos.php" class="<?php echo $active_page === 'a-propos' ? 'active' : ''; ?>">À Propos</a></li>
                <li><a href="contact.php" class="<?php echo $active_page === 'contact' ? 'active' : ''; ?>">Contact & Devis</a></li>
                <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                <li><a href="admin.php" class="<?php echo $active_page === 'admin' ? 'active' : ''; ?>" style="color: var(--accent-color); font-weight: bold;">Dashboard</a></li>
                <?php endif; ?>
            </ul>
            <!-- Bouton pour le menu mobile -->
            <button id="mobile-menu-toggle" aria-label="Ouvrir le menu de navigation">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
        </nav>
    </header>

    <!-- Barre de Certifications / Labels de confiance (Visible sur toutes les pages) -->
    <section id="certifications-trust-bar">
        <div class="trust-bar-container">
            <img class="partner-logo" src="assets/images/labels/cee.png" alt="Certificats d'Économies d'Énergie (CEE)">
            <img class="partner-logo" src="assets/images/labels/rge.png" alt="RGE Qualibat - Reconnu Garant de l'Environnement">
            <img class="partner-logo" src="assets/images/labels/logo-eco-pret.png" alt="Éco-Prêt à Taux Zéro">
            <img class="partner-logo" src="assets/images/labels/ma-primerenov.png" alt="MaPrimeRénov' - Aides de l'État">
            <img class="partner-logo" src="assets/images/labels/tva-5.5.png" alt="TVA Réduite 5.5% - Travaux de Rénovation">
            <img class="partner-logo" src="assets/images/labels/logo-primesenergie.png" alt="Primes Énergie">
            <img class="partner-logo" src="assets/images/labels/label.png" alt="Label Qualité">
        </div>
    </section>
