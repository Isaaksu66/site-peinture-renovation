<?php
/**
 * Page À Propos (À Propos)
 * Cetin Family
 */

$page_title = "À Propos | Cetin Family";
$page_description = "Découvrez l'histoire de Cetin Family, nos valeurs de plaquiste et peintre RGE à Mâcon (71000), nos qualifications décennales et nos engagements.";
$active_page = "a-propos";

require_once __DIR__ . '/header.php';
?>

<main>
    <!-- En-tête de page -->
    <section class="page-banner">
        <div class="container">
            <h1><?php echo htmlspecialchars(get_site_text('about_banner_title', 'À Propos de Nous'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(get_site_text('about_banner_desc', 'Découvrez notre histoire, nos valeurs d\'artisans et notre passion pour les belles finitions.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </section>

    <!-- Section Histoire & Présentation -->
    <section id="about-intro">
        <div class="container" style="max-width: 800px; text-align: center; padding: 5rem 0 3rem;">
            <h2 style="margin-bottom: 2rem; font-size: 2.2rem;"><?php echo htmlspecialchars(get_site_text('about_intro_title', 'Le mot de l\'artisan'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p style="font-size: 1.1rem; color: var(--text-muted); line-height: 1.8; margin-bottom: 1.5rem;"><?php echo htmlspecialchars(get_site_text('about_intro_p1', 'Créée avec la passion du travail soigné, Cetin Family est le fruit de plusieurs années d\'expérience dans le domaine du second œuvre et de la décoration d\'intérieur.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p style="font-size: 1.1rem; color: var(--text-muted); line-height: 1.8; margin-bottom: 1.5rem;"><?php echo htmlspecialchars(get_site_text('about_intro_p2', 'Notre philosophie repose sur une écoute attentive des besoins de nos clients pour leur proposer des solutions personnalisées qui allient esthétique, durabilité et respect du budget.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p style="font-size: 1.1rem; color: var(--text-muted); line-height: 1.8;"><?php echo htmlspecialchars(get_site_text('about_intro_p3', 'Chaque chantier est traité avec le même degré d\'exigence : une préparation rigoureuse des surfaces (le secret d\'une peinture réussie) et un soin méticuleux apporté aux finitions.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </section>

    <!-- Section Nos Valeurs -->
    <section id="values" class="alt-bg">
        <div class="container">
            <h2><?php echo htmlspecialchars(get_site_text('about_values_title', 'Nos Engagements au Quotidien'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="values-grid">
                <article class="value-card">
                    <h3><?php echo htmlspecialchars(get_site_text('about_val_1_title', '1. Préparation méticuleuse'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars(get_site_text('about_val_1_desc', 'Nous passons le temps nécessaire à poncer, enduire et lisser vos supports. C\'est l\'assurance d\'un rendu parfaitement uniforme et durable.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
                
                <article class="value-card">
                    <h3><?php echo htmlspecialchars(get_site_text('about_val_2_title', '2. Propreté absolue'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars(get_site_text('about_val_2_desc', 'Nous protégeons l\'intégralité de vos espaces de vie (sols, plinthes, meubles) avant de commencer et nettoyons le chantier quotidiennement.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </article>

                <article class="value-card">
                    <h3><?php echo htmlspecialchars(get_site_text('about_val_3_title', '3. Matériaux de qualité'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars(get_site_text('about_val_3_desc', 'Nous sélectionnons rigoureusement nos peintures (écolabellisées, à faible émission de COV, haut pouvoir couvrant) auprès de marques professionnelles reconnues.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            </div>
        </div>
    </section>

    <!-- Section Certifications & Réassurance -->
    <section id="qualifications">
        <div class="container">
            <h2><?php echo htmlspecialchars(get_site_text('about_qualif_title', 'Une entreprise de confiance qualifiée'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="qualifications-flex">
                <div class="qualification-badge">
                    <h4><?php echo htmlspecialchars(get_site_text('about_qualif_1_title', 'Garantie Décennale'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars(get_site_text('about_qualif_1_desc', 'Assurance responsabilité professionnelle couvrant l\'ensemble de nos réalisations extérieures et intérieures.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="qualification-badge">
                    <h4><?php echo htmlspecialchars(get_site_text('about_qualif_2_title', 'Normes Environnementales'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars(get_site_text('about_qualif_2_desc', 'Utilisation prioritaire de peintures certifiées Écolabel Européen pour préserver la qualité de l\'air de votre foyer.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
