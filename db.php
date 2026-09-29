<?php
/**
 * Connexion à la base de données et initialisation automatique
 * Cetin Family
 */

require_once __DIR__ . '/config.php';

try {
    // Connexion initiale à MySQL sans base de données pour permettre la création si inexistante
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Création de la base de données si nécessaire
    $dbName = DB_NAME;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");

    // Création de la table admins
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Vérifier et ajouter les colonnes de réinitialisation si la table existait déjà
    $adminColumns = $pdo->query("SHOW COLUMNS FROM `admins`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('reset_token', $adminColumns)) {
        $pdo->exec("ALTER TABLE `admins` ADD `reset_token` VARCHAR(100) DEFAULT NULL AFTER `email`");
    }
    if (!in_array('reset_expires', $adminColumns)) {
        $pdo->exec("ALTER TABLE `admins` ADD `reset_expires` DATETIME DEFAULT NULL AFTER `reset_token`");
    }

    // Création de la table devis
    $pdo->exec("CREATE TABLE IF NOT EXISTS `devis` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lastname` VARCHAR(100) NOT NULL,
        `firstname` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `location` VARCHAR(150) NOT NULL,
        `project_type` VARCHAR(50) NOT NULL,
        `surface` INT NOT NULL,
        `message` TEXT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'en_attente',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Création de la table login_attempts pour la sécurité brute-force
    $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
        `ip_address` VARCHAR(45) NOT NULL,
        `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // ==========================================================================
    // CMS : CRÉATION DES TABLES SUPPLÉMENTAIRES
    // ==========================================================================

    // Table company_settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS `company_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_name` VARCHAR(100) NOT NULL DEFAULT 'Cetin Family',
        `phone` VARCHAR(50) NOT NULL,
        `email` VARCHAR(150) NOT NULL,
        `address` VARCHAR(255) NOT NULL,
        `city` VARCHAR(100) NOT NULL,
        `postal_code` VARCHAR(20) NOT NULL,
        `hours_week` VARCHAR(255) NOT NULL,
        `hours_sat` VARCHAR(255) NOT NULL,
        `radius_km` INT NOT NULL DEFAULT 40,
        `latitude` DECIMAL(10, 6) NOT NULL DEFAULT 47.390278,
        `longitude` DECIMAL(10, 6) NOT NULL DEFAULT 0.688889,
        `facebook_url` VARCHAR(255) NOT NULL DEFAULT 'https://facebook.com'
    ) ENGINE=InnoDB");

    // Vérifier et ajouter les colonnes si la table existait déjà
    $columns = $pdo->query("SHOW COLUMNS FROM `company_settings`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('company_name', $columns)) {
        $pdo->exec("ALTER TABLE `company_settings` ADD `company_name` VARCHAR(100) NOT NULL DEFAULT 'Cetin Family' AFTER `id`");
    }
    if (!in_array('facebook_url', $columns)) {
        $pdo->exec("ALTER TABLE `company_settings` ADD `facebook_url` VARCHAR(255) NOT NULL DEFAULT 'https://facebook.com' AFTER `longitude`");
    }

    // Table services (Prestations)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `features` TEXT NOT NULL,
        `image_path` VARCHAR(255) NOT NULL,
        `anchor` VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB");

    // Table projects (Réalisations)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `projects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `tags` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `image_path` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Table site_texts (CMS Textes des pages)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_texts` (
        `text_key` VARCHAR(100) PRIMARY KEY,
        `text_value` TEXT NOT NULL
    ) ENGINE=InnoDB");

    // Table site_analytics (Tracking des visites de la Fiche Visibilité)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_analytics` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ip_hash` VARCHAR(64) NOT NULL,
        `session_id` VARCHAR(100) NOT NULL,
        `page_url` VARCHAR(255) NOT NULL,
        `visit_date` DATE NOT NULL,
        `visited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`visit_date`),
        INDEX (`session_id`),
        INDEX (`ip_hash`)
    ) ENGINE=InnoDB");

    // ==========================================================================
    // PEUPLEMENT INITIAL DES DONNÉES (SEEDING)
    // ==========================================================================

    // 1. Administrateur par défaut
    $checkAdmins = $pdo->query("SELECT COUNT(*) FROM `admins`");
    if ($checkAdmins->fetchColumn() == 0) {
        $defaultUser = ADMIN_DEFAULT_USER;
        $defaultPasswordHash = password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_DEFAULT);
        $defaultEmail = ADMIN_DEFAULT_EMAIL;

        $insertAdmin = $pdo->prepare("INSERT INTO `admins` (username, password, email) VALUES (?, ?, ?)");
        $insertAdmin->execute([$defaultUser, $defaultPasswordHash, $defaultEmail]);
    }

    // 2. Paramètres de l'entreprise
    $checkSettings = $pdo->query("SELECT COUNT(*) FROM `company_settings`");
    if ($checkSettings->fetchColumn() == 0) {
        $insertSettings = $pdo->prepare("INSERT INTO `company_settings` (company_name, phone, email, address, city, postal_code, hours_week, hours_sat, radius_km, latitude, longitude, facebook_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insertSettings->execute([
            'Cetin Family',
            '07 60 55 14 44',
            'contact@cetinfamily71.fr',
            '646 Rue Einstein',
            'Mâcon',
            '71000',
            'Lundi - Vendredi : 09h00 - 12h00 / 14h00 - 17h00',
            'Samedi & Dimanche : Fermé',
            50,
            46.291726,
            4.817687,
            'https://www.facebook.com/FamilyCetin71/'
        ]);
    } else {
        // Mettre à jour le nom si c'était l'ancienne valeur
        $pdo->exec("UPDATE `company_settings` SET `company_name` = 'Cetin Family' WHERE `company_name` = '' OR `company_name` = 'Peinture Élégance' OR `company_name` IS NULL");
        // Corriger l'adresse et les coordonnées GPS si elles sont encore sur les anciennes valeurs
        $pdo->exec("UPDATE `company_settings` SET `address` = '646 Rue Einstein', `latitude` = 46.291726, `longitude` = 4.817687 WHERE `address` LIKE '%Rue Albert Einstein%' OR `address` LIKE '%Rue Einstein%'");
        // Corriger les horaires
        $pdo->exec("UPDATE `company_settings` SET `hours_week` = 'Lundi - Vendredi : 09h00 - 12h00 / 14h00 - 17h00', `hours_sat` = 'Samedi & Dimanche : Fermé' WHERE `hours_week` LIKE '%18h00%' OR `hours_sat` LIKE '%RDV%'");
        // Corriger l'URL Facebook
        $pdo->exec("UPDATE `company_settings` SET `facebook_url` = 'https://www.facebook.com/FamilyCetin71/' WHERE `facebook_url` LIKE '%cetinfamily71%' OR `facebook_url` = 'https://facebook.com'");
        // Corriger l'email de contact
        $pdo->exec("UPDATE `company_settings` SET `email` = 'contact@cetinfamily71.fr' WHERE `email` = 'cetinfamily71@gmail.com'");
    }

    // 3. Prestations (services)
    $checkServices = $pdo->query("SELECT COUNT(*) FROM `services`");
    if ($checkServices->fetchColumn() == 0) {
        $defaultServices = [
            [
                'title' => 'Peinture Intérieure & Décoration',
                'description' => "Nous réalisons l'application de peintures sur tous types de supports (murs, plafonds, boiseries). Nous vous conseillons sur les finitions les plus adaptées à vos pièces de vie :",
                'features' => "Finition mate : Idéale pour masquer les petites imperfections des plafonds et des chambres parentales.\nFinition satinée : Très résistante et lessivable, parfaite pour les pièces d'eau, couloirs et cuisines.\nFinition velours : Le compromis idéal entre mat et satin, offrant un aspect soyeux et chaleureux.\nMurs d'accent : Création de contrastes colorés pour structurer l'espace de vos pièces de vie.",
                'image_path' => 'assets/images/peinture_interieure.jpg',
                'anchor' => 'interieur'
            ],
            [
                'title' => 'Revêtements de Murs',
                'description' => "Au-delà de la peinture traditionnelle, nous habillons vos murs pour leur donner du relief et de la personnalité :",
                'features' => "Papiers peints panoramiques : Parfaits pour créer une fresque unique ou une tête de lit originale.\nToiles de verre & Rénovation : Pour renforcer les murs abîmés ou sujets aux micro-fissures avant peinture.\nEnduits décoratifs : Effets béton ciré, stucs ou enduits à la chaux pour un style texturé haut de gamme.",
                'image_path' => 'assets/images/revetement_mural.jpg',
                'anchor' => 'revetements'
            ],
            [
                'title' => 'Ravalement & Peinture Extérieure',
                'description' => "La peinture extérieure protège vos structures contre l'humidité, les UV et la pollution, tout en valorisant votre patrimoine :",
                'features' => "Peinture de façades : Revêtements semi-épais ou imperméabilisants pour la longévité de vos murs extérieurs.\nBoiseries extérieures : Traitement et peinture/lasure des volets, dessous de toits, portails et bardages.\nPeinture sur métaux : Protection anti-rouille et mise en peinture des grilles, portails et garde-corps.",
                'image_path' => 'assets/images/peinture_exterieure.jpg',
                'anchor' => 'exterieur'
            ],
            [
                'title' => 'Revêtements de Sols',
                'description' => "Pour un projet de rénovation clé en main, nous assurons également la pose de revêtements de sol harmonisés à vos nouvelles peintures :",
                'features' => "Parquets flottants / stratifiés : Pour un rendu chaleureux et facile d'entretien.\nSols souples & Lames PVC : Idéaux pour les pièces d'eau, résistants à l'usure et déclinables en de multiples décors (effet bois, béton, carreaux de ciment).\nMoquettes et dalles : Pour un confort acoustique et thermique optimal.",
                'image_path' => 'assets/images/revetement_sol.jpg',
                'anchor' => 'sols'
            ]
        ];

        $insertService = $pdo->prepare("INSERT INTO `services` (title, description, features, image_path, anchor) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaultServices as $srv) {
            $insertService->execute([
                $srv['title'],
                $srv['description'],
                $srv['features'],
                $srv['image_path'],
                $srv['anchor']
            ]);
        }
    }

    // 4. Réalisations (projets)
    $checkProjects = $pdo->query("SELECT COUNT(*) FROM `projects`");
    if ($checkProjects->fetchColumn() == 0) {
        $defaultProjects = [
            [
                'title' => 'Rénovation de Salon Contemporain',
                'category' => 'interieur',
                'tags' => 'Peinture Intérieure / Velours',
                'description' => "Mise en peinture des murs en blanc velours et création d'un mur d'accent vert sauge pour délimiter le coin salon.",
                'image_path' => 'assets/images/realisation_salon.jpg'
            ],
            [
                'title' => 'Chambre Parentale & Fresque',
                'category' => 'decor',
                'tags' => 'Revêtement Mural / Décoration',
                'description' => "Pose d'un papier peint panoramique à motif forestier en tête de lit et mise en harmonie des autres murs en beige mat.",
                'image_path' => 'assets/images/realisation_chambre.jpg'
            ],
            [
                'title' => 'Ravalement de Maison Traditionnelle',
                'category' => 'exterieur',
                'tags' => 'Peinture Extérieure / Façade',
                'description' => "Nettoyage, traitement anti-mousse et application d'un revêtement d'imperméabilisation ton pierre sur les façades.",
                'image_path' => 'assets/images/realisation_maison.jpg'
            ],
            [
                'title' => 'Cuisine & Crédence',
                'category' => 'interieur',
                'tags' => 'Peinture Intérieure / Satin',
                'description' => "Application d'une laque satinée lessivable ultra-résistante sur les murs de cuisine et rénovation des boiseries.",
                'image_path' => 'assets/images/realisation_cuisine.jpg'
            ],
            [
                'title' => 'Entrée en Béton Ciré',
                'category' => 'decor',
                'tags' => 'Enduit Décoratif / Sol & Mur',
                'description' => "Création d'un enduit décoratif effet béton ciré sur le mur principal de l'entrée pour un rendu industriel chic.",
                'image_path' => 'assets/images/realisation_beton.jpg'
            ],
            [
                'title' => 'Rénovation de Boiseries Extérieures',
                'category' => 'exterieur',
                'tags' => 'Peinture Extérieure / Volets',
                'description' => "Ponçage complet, traitement fongicide et application de deux couches de lasure haute protection sur 8 paires de volets.",
                'image_path' => 'assets/images/realisation_volets.jpg'
            ]
        ];

        $insertProject = $pdo->prepare("INSERT INTO `projects` (title, category, tags, description, image_path) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaultProjects as $proj) {
            $insertProject->execute([
                $proj['title'],
                $proj['category'],
                $proj['tags'],
                $proj['description'],
                $proj['image_path']
            ]);
        }
    }

    // 5. Textes configurables
    $checkTexts = $pdo->query("SELECT COUNT(*) FROM `site_texts`");
    if ($checkTexts->fetchColumn() == 0) {
        $defaultTexts = [
            // Accueil
            'home_hero_title' => 'Donnez des couleurs et du caractère à vos espaces',
            'home_hero_desc' => 'Artisan peintre spécialisé dans la décoration d\'intérieur, la peinture haut de gamme et les revêtements muraux. Travail soigné et finitions d\'excellence.',
            'home_intro_title' => 'Votre projet de peinture entre les mains d\'un expert',
            'home_intro_desc' => 'Basé dans votre région, Cetin Family vous accompagne du conseil couleur à la réalisation de vos travaux de rénovation et de décoration. Nous mettons un point d\'honneur à offrir un chantier propre, des délais respectés et des finitions impeccables.',
            
            'home_commitments_title' => 'Pourquoi choisir Cetin Family ?',
            'home_commitment_1_title' => 'Garantie Décennale',
            'home_commitment_1_desc' => 'Tous nos travaux structurels sont couverts pour votre totale tranquillité d\'esprit.',
            'home_commitment_2_title' => 'Propreté de Chantier',
            'home_commitment_2_desc' => 'Protection minutieuse de vos meubles et sols, et nettoyage complet après intervention.',
            'home_commitment_3_title' => 'Conseil Personnalisé',
            'home_commitment_3_desc' => 'Aide au choix des teintes et des harmonies pour correspondre parfaitement à votre style.',
            
            'home_testimonials_title' => 'Ce que disent nos clients',
            'home_testimonial_1_quote' => 'Un travail d\'une précision incroyable pour notre salon. Le peintre a été de très bon conseil sur le choix des teintes et le chantier a été rendu impeccable chaque soir.',
            'home_testimonial_1_author' => 'Sophie L., Rénovation de salon',
            'home_testimonial_2_quote' => 'Très professionnel, ponctuel et minutieux. Le ravalement de nos volets en bois redonne un coup de jeune à la maison. Je recommande vivement !',
            'home_testimonial_2_author' => 'Jean-Pierre M., Peinture extérieure',
            
            // À Propos
            'about_banner_title' => 'À Propos de Nous',
            'about_banner_desc' => 'Découvrez notre histoire, nos valeurs d\'artisans et notre passion pour les belles finitions.',
            'about_intro_title' => 'Le mot de l\'artisan',
            'about_intro_p1' => 'Créée avec la passion du travail soigné, Cetin Family est le fruit de plusieurs années d\'expérience dans le domaine du second œuvre et de la décoration d\'intérieur.',
            'about_intro_p2' => 'Notre philosophie repose sur une écoute attentive des besoins de nos clients pour leur proposer des solutions personnalisées qui allient esthétique, durabilité et respect du budget.',
            'about_intro_p3' => 'Chaque chantier est traité avec le même degré d\'exigence : une préparation rigoureuse des surfaces (le secret d\'une peinture réussie) et un soin méticuleux apporté aux finitions.',
            
            'about_values_title' => 'Nos Engagements au Quotidien',
            'about_val_1_title' => '1. Préparation méticuleuse',
            'about_val_1_desc' => 'Nous passons le temps nécessaire à poncer, enduire et lisser vos supports. C\'est l\'assurance d\'un rendu parfaitement uniforme et durable.',
            'about_val_2_title' => '2. Propreté absolue',
            'about_val_2_desc' => 'Nous protégeons l\'intégralité de vos espaces de vie (sols, plinthes, meubles) avant de commencer et nettoyons le chantier quotidiennement.',
            'about_val_3_title' => '3. Matériaux de qualité',
            'about_val_3_desc' => 'Nous sélectionnons rigoureusement nos peintures (écolabellisées, à faible émission de COV, haut pouvoir couvrant) auprès de marques professionnelles reconnues.',
            
            'about_qualif_title' => 'Une entreprise de confiance qualifiée',
            'about_qualif_1_title' => 'Garantie Décennale',
            'about_qualif_1_desc' => 'Assurance responsabilité professionnelle couvrant l\'ensemble de nos réalisations extérieures et intérieures.',
            'about_qualif_2_title' => 'Normes Environnementales',
            'about_qualif_2_desc' => 'Utilisation prioritaire de peintures certifiées Écolabel Européen pour préserver la qualité de l\'air de votre foyer.',

            // Prestations
            'prestations_banner_title' => 'Nos Prestations',
            'prestations_banner_desc' => 'Découvrez notre savoir-faire pour transformer, rénover et embellir votre habitat.',
            'prestations_cta_title' => 'Votre projet nécessite un devis précis ?',
            'prestations_cta_desc' => 'Nos devis sont gratuits, détaillés et sans engagement. Rencontrons-nous pour étudier votre projet.',

            // Réalisations
            'realisations_banner_title' => 'Nos Réalisations',
            'realisations_banner_desc' => 'Découvrez en images la qualité de nos chantiers de peinture et de décoration.',

            // Contact
            'contact_banner_title' => 'Contact & Demande de Devis',
            'contact_banner_desc' => 'Une question ? Un projet de rénovation ? Demandez votre estimation gratuite en quelques clics.',
            'contact_intro_title' => 'Nos Coordonnées',
            'contact_intro_desc' => 'N\'hésitez pas à nous appeler directement ou à nous envoyer un e-mail pour discuter de vos travaux de peinture.',
            'contact_form_title' => 'Formulaire de Demande de Devis',
            'contact_form_desc' => 'Remplissez ce formulaire, nous vous répondrons sous 48h ouvrées pour convenir d\'un rendez-vous gratuit.'
        ];

        $insertText = $pdo->prepare("INSERT INTO `site_texts` (text_key, text_value) VALUES (?, ?)");
        foreach ($defaultTexts as $k => $v) {
            $insertText->execute([$k, $v]);
        }
    }

    // 6. Table analytique prête pour le tracking dynamique en temps réel.

} catch (PDOException $e) {
    die("Erreur critique de base de données : " . $e->getMessage());
}

// Cache local pour éviter des requêtes SQL répétées
$site_texts_cache = null;

function get_site_text($key, $default = '') {
    global $pdo, $site_texts_cache;
    if ($site_texts_cache === null) {
        $site_texts_cache = [];
        try {
            $stmt = $pdo->query("SELECT text_key, text_value FROM `site_texts`");
            while ($row = $stmt->fetch()) {
                $site_texts_cache[$row['text_key']] = $row['text_value'];
            }
        } catch (PDOException $e) {
            // Table non encore créée ou autre erreur
        }
    }
    return isset($site_texts_cache[$key]) ? $site_texts_cache[$key] : $default;
}

// Fonction de tracking automatique des visites (GDPR compliant)
function track_visitor_hit() {
    global $pdo;
    
    // Ne pas tracker si l'administrateur est connecté
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return;
    }
    
    // Ne pas tracker si contexte non-HTTP
    if (!isset($_SERVER['REMOTE_ADDR'])) {
        return;
    }
    
    $page = basename($_SERVER['PHP_SELF']);
    $ignored_pages = ['admin.php', 'logout.php', 'db.php', 'config.php', '404.php'];
    
    if (in_array($page, $ignored_pages) || empty($page)) {
        return;
    }
    
    try {
        $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR']);
        
        // Assurer que la session PHP est démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $session_id = session_id();
        $visit_date = date('Y-m-d');
        
        $stmt = $pdo->prepare("INSERT INTO `site_analytics` (ip_hash, session_id, page_url, visit_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ip_hash, $session_id, $page, $visit_date]);
    } catch (Exception $e) {
        // Silencieux
    }
}

// Exécuter le tracking automatique immédiatement lors de l'inclusion
track_visitor_hit();
?>
