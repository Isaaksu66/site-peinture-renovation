<?php
/**
 * Page d'accueil (Index)
 * Cetin Family
 */

$page_title = "Cetin Family | Entreprise d'isolation, plâtrerie, peinture à Mâcon";
$page_description = "Découvrez Cetin Family, votre expert en isolation RGE, travaux de plâtrerie, plaquiste et peinture à Mâcon (71000) et en Saône-et-Loire. Devis gratuit.";
$active_page = "accueil";

require_once __DIR__ . '/header.php';
?>

<main>
    <!-- Section Héro (Introduction visuelle) -->
    <section id="hero">
        <div class="hero-content">
            <h1><?php echo htmlspecialchars(get_site_text('home_hero_title', 'Donnez des couleurs et du caractère à vos espaces'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(get_site_text('home_hero_desc', 'Artisan peintre spécialisé dans la décoration d\'intérieur, la peinture haut de gamme et les revêtements muraux. Travail soigné et finitions d\'excellence.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="hero-buttons">
                <a href="contact.php" class="btn btn-primary">Demander un Devis Gratuit</a>
                <a href="prestations.php" class="btn btn-secondary">Découvrir nos Prestations</a>
            </div>
        </div>
    </section>

    <!-- Section Introduction / Qui sommes-nous rapide -->
    <section id="intro-preview">
        <div class="container">
            <h2><?php echo htmlspecialchars(get_site_text('home_intro_title', 'Votre projet de peinture entre les mains d\'un expert'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo nl2br(htmlspecialchars(get_site_text('home_intro_desc', 'Basé dans votre région, Cetin Family vous accompagne du conseil couleur à la réalisation de vos travaux de rénovation et de décoration. Nous mettons un point d\'honneur à offrir un chantier propre, des délais respectés et des finitions impeccables.'), ENT_QUOTES, 'UTF-8')); ?></p>
        </div>
    </section>

    <!-- Section Services phares -->
    <section id="featured-services" class="alt-bg">
        <div class="container">
            <h2>Nos Prestations Phares</h2>
            <div class="services-grid">
                <?php
                try {
                    $servicesStmt = $pdo->query("SELECT * FROM `services` ORDER BY id ASC LIMIT 3");
                    $services_featured = $servicesStmt->fetchAll();
                } catch (PDOException $e) {
                    $services_featured = [];
                }
                foreach ($services_featured as $srv):
                ?>
                <article class="service-card">
                    <h3><?php echo htmlspecialchars($srv['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars(mb_strimwidth($srv['description'], 0, 140, "..."), ENT_QUOTES, 'UTF-8'); ?></p>
                    <a href="prestations.php#<?php echo htmlspecialchars($srv['anchor'], ENT_QUOTES, 'UTF-8'); ?>">En savoir plus &rarr;</a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Section Engagements / Qualité -->
    <section id="commitments">
        <div class="container">
            <h2><?php echo htmlspecialchars(get_site_text('home_commitments_title', 'Pourquoi choisir Cetin Family ?'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="commitments-list">
                <div class="commitment-item">
                    <h4><?php echo htmlspecialchars(get_site_text('home_commitment_1_title', 'Garantie Décennale'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars(get_site_text('home_commitment_1_desc', 'Tous nos travaux structurels sont couverts pour votre totale tranquillité d\'esprit.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="commitment-item">
                    <h4><?php echo htmlspecialchars(get_site_text('home_commitment_2_title', 'Propreté de Chantier'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars(get_site_text('home_commitment_2_desc', 'Protection minutieuse de vos meubles et sols, et nettoyage complet après intervention.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="commitment-item">
                    <h4><?php echo htmlspecialchars(get_site_text('home_commitment_3_title', 'Conseil Personnalisé'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars(get_site_text('home_commitment_3_desc', 'Aide au choix des teintes et des harmonies pour correspondre parfaitement à votre style.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Témoignages / Avis clients -->
    <section id="testimonials" class="alt-bg">
        <div class="container">
            <h2><?php echo htmlspecialchars(get_site_text('home_testimonials_title', 'Ce que disent nos clients'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="testimonials-slider">
                <blockquote class="testimonial">
                    <p>"<?php echo htmlspecialchars(get_site_text('home_testimonial_1_quote', 'Un travail d\'une précision incroyable pour notre salon. Le peintre a été de très bon conseil sur le choix des teintes et le chantier a été rendu impeccable chaque soir.'), ENT_QUOTES, 'UTF-8'); ?>"</p>
                    <cite>— <?php echo htmlspecialchars(get_site_text('home_testimonial_1_author', 'Sophie L., Rénovation de salon'), ENT_QUOTES, 'UTF-8'); ?></cite>
                </blockquote>
                <blockquote class="testimonial">
                    <p>"<?php echo htmlspecialchars(get_site_text('home_testimonial_2_quote', 'Très professionnel, ponctuel et minutieux. Le ravalement de nos volets en bois redonne un coup de jeune à la maison. Je recommande vivement !'), ENT_QUOTES, 'UTF-8'); ?>"</p>
                    <cite>— <?php echo htmlspecialchars(get_site_text('home_testimonial_2_author', 'Jean-Pierre M., Peinture extérieure'), ENT_QUOTES, 'UTF-8'); ?></cite>
                </blockquote>
            </div>
        </div>
    </section>

    <!-- Section Appels à l'action fin de page -->
    <section id="cta-action">
        <div class="container">
            <h2>Prêt à concrétiser votre projet ?</h2>
            <p>Contactez-nous dès aujourd'hui pour obtenir une estimation gratuite et personnalisée de vos travaux.</p>
            <a href="contact.php" class="btn btn-primary">Obtenir mon Devis Gratuit</a>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';?>
