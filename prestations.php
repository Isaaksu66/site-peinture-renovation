<?php
/**
 * Page des prestations de services (Prestations)
 * Cetin Family
 */

$page_title = "Nos Prestations | Cetin Family";
$page_description = "Découvrez notre savoir-faire complet : plâtrerie, isolation RGE, pose de placo, cloisonnement, peinture intérieure et revêtements muraux à Mâcon.";
$active_page = "prestations";

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';
?>

<main>
    <!-- En-tête de page -->
    <section class="page-banner">
        <div class="container">
            <h1><?php echo htmlspecialchars(get_site_text('prestations_banner_title', 'Nos Prestations'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(get_site_text('prestations_banner_desc', 'Découvrez notre savoir-faire pour transformer, rénover et embellir votre habitat.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </section>

    <?php
    try {
        $servicesStmt = $pdo->query("SELECT * FROM `services` ORDER BY id ASC");
        $services = $servicesStmt->fetchAll();
    } catch (PDOException $e) {
        $services = [];
    }

    $index = 0;
    foreach ($services as $srv):
        $is_even = ($index % 2 === 0);
        $features = explode("\n", trim($srv['features']));
    ?>
    <section id="<?php echo htmlspecialchars($srv['anchor'], ENT_QUOTES, 'UTF-8'); ?>" class="prestation-detail<?php echo $is_even ? '' : ' alt-bg'; ?>">
        <div class="container">
            <div class="prestation-grid<?php echo $is_even ? '' : ' reverse'; ?>">
                <div class="prestation-text">
                    <h2><?php echo htmlspecialchars($srv['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars($srv['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <ul>
                        <?php foreach ($features as $feat): ?>
                            <?php 
                            $parts = explode(':', $feat, 2);
                            if (count($parts) === 2):
                            ?>
                                <li><strong><?php echo htmlspecialchars(trim($parts[0]), ENT_QUOTES, 'UTF-8'); ?> :</strong><?php echo htmlspecialchars(trim($parts[1]), ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php else: ?>
                                <li><?php echo htmlspecialchars(trim($feat), ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="prestation-img-container">
                    <img src="<?php echo htmlspecialchars($srv['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($srv['title'], ENT_QUOTES, 'UTF-8'); ?>" class="prestation-img" loading="lazy" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </section>
    <?php 
        $index++;
    endforeach;
    ?>

    <!-- Section CTA commune -->
    <section id="cta-action">
        <div class="container">
            <h2><?php echo htmlspecialchars(get_site_text('prestations_cta_title', 'Votre projet nécessite un devis précis ?'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo htmlspecialchars(get_site_text('prestations_cta_desc', 'Nos devis sont gratuits, détaillés et sans engagement. Rencontrons-nous pour étudier votre projet.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="contact.php" class="btn btn-primary">Formuler ma Demande de Devis</a>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
