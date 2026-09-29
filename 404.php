<?php
/**
 * Page d'erreur 404 personnalisée
 * Cetin Family
 */

$page_title = "Page introuvable | Cetin Family";
$page_description = "La page que vous recherchez n'existe pas ou a été déplacée.";
$active_page = "";

http_response_code(404);

require_once __DIR__ . '/header.php';
?>

<main>
    <section style="text-align: center; padding: 8rem 5% 6rem;">
        <div class="container" style="max-width: 600px;">
            <h1 style="font-size: 6rem; color: var(--accent-color); margin-bottom: 0.5rem; line-height: 1;">404</h1>
            <h2 style="font-size: 1.8rem; margin-bottom: 1.5rem;">Page introuvable</h2>
            <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.7; margin-bottom: 2.5rem;">La page que vous recherchez n'existe pas, a été déplacée ou est temporairement indisponible.</p>
            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
                <a href="contact.php" class="btn btn-secondary">Nous contacter</a>
            </div>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
