<?php
/**
 * Page des réalisations (Réalisations)
 * Cetin Family
 */

$page_title = "Nos Réalisations | Cetin Family";
$page_description = "Découvrez en images nos chantiers d'isolation, plâtrerie, pose de claco et peinture à Mâcon. L'excellence de notre travail chez nos clients.";
$active_page = "realisations";

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';
?>

<main>
    <!-- En-tête de page -->
    <section class="page-banner">
        <div class="container">
            <h1><?php echo htmlspecialchars(get_site_text('realisations_banner_title', 'Nos Réalisations'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(get_site_text('realisations_banner_desc', 'Découvrez en images la qualité de nos chantiers de peinture et de décoration.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </section>

    <!-- Filtres de la galerie -->
    <section id="gallery-filters">
        <div class="container">
            <ul class="filter-list">
                <li><button class="filter-btn active" data-filter="all">Tout voir</button></li>
                <li><button class="filter-btn" data-filter="interieur">Peinture Intérieure</button></li>
                <li><button class="filter-btn" data-filter="exterieur">Peinture Extérieure</button></li>
                <li><button class="filter-btn" data-filter="decor">Décoration & Revêtements</button></li>
            </ul>
        </div>
    </section>

    <!-- Galerie de projets -->
    <section id="gallery-projects">
        <div class="container">
            <div class="projects-grid">
                
                <?php
                try {
                    $projectsStmt = $pdo->query("SELECT * FROM `projects` ORDER BY id DESC");
                    $projects = $projectsStmt->fetchAll();
                } catch (PDOException $e) {
                    $projects = [];
                }

                if (empty($projects)):
                ?>
                    <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 3rem 0; font-family: var(--font-title); font-size: 1.2rem;">Aucune réalisation enregistrée pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <article class="project-card" data-category="<?php echo htmlspecialchars($proj['category'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="project-img-container">
                                <img src="<?php echo htmlspecialchars($proj['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($proj['title'], ENT_QUOTES, 'UTF-8'); ?>" class="project-img" loading="lazy" onerror="this.style.display='none'">
                            </div>
                            <div class="project-info">
                                <h3><?php echo htmlspecialchars($proj['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="project-tags"><?php echo htmlspecialchars($proj['tags'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="project-desc"><?php echo htmlspecialchars($proj['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- Section CTA commune -->
    <section id="cta-action">
        <div class="container">
            <h2>Ces réalisations vous inspirent ?</h2>
            <p>Chaque projet est unique. Discutons ensemble des finitions et des couleurs adaptées à votre intérieur.</p>
            <a href="contact.php" class="btn btn-primary">Demander une étude gratuite</a>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
