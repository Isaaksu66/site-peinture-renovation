<?php
/**
 * Template de pied de page (Footer)
 * Peinture Élégance
 */
?>
    <!-- Pied de page -->
    <footer id="main-footer">
        <div class="footer-content">
            <div class="footer-info">
                <h4><?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                <p>Isolation, plâtrerie & peinture.</p>
                <p>Téléphone : <a href="tel:<?php echo str_replace(' ', '', $company['phone']); ?>" style="color: inherit;"><?php echo htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8'); ?></a></p>
                <p>Email : <a href="mailto:<?php echo htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8'); ?>" style="color: inherit;"><?php echo htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8'); ?></a></p>
                <div class="footer-socials">
                    <a href="<?php echo htmlspecialchars($company['facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="social-link">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/cetinfamily_deco" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="social-link">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Liens utiles</h4>
                <ul>
                    <li><a href="a-propos.php">À Propos</a></li>
                    <li><a href="prestations.php">Prestations</a></li>
                    <li><a href="realisations.php">Nos Réalisations</a></li>
                    <li><a href="contact.php">Demande de Devis</a></li>
                </ul>
            </div>
            <div class="footer-hours">
                <h4>Horaires d'ouverture</h4>
                <p><?php echo htmlspecialchars($company['hours_week'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><?php echo htmlspecialchars($company['hours_sat'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?>. Tous droits réservés. | <a href="mentions-legales.php" style="color: inherit; text-decoration: underline;">Mentions Légales & RGPD</a></p>
        </div>
    </footer>
    <!-- Bandeau Cookies RGPD -->
    <div id="cookie-banner" class="cookie-banner">
        <div class="cookie-container">
            <div class="cookie-content">
                <span class="cookie-icon">🍪</span>
                <p><strong>Respect de votre vie privée</strong><br>
                Ce site utilise des cookies nécessaires à son bon fonctionnement et pour mesurer l'audience anonymement. Aucun cookie publicitaire n'est déposé. Pour en savoir plus, consultez nos <a href="mentions-legales.php">Mentions Légales & RGPD</a>.</p>
            </div>
            <div class="cookie-buttons">
                <button id="btn-cookie-refuse" class="btn btn-secondary btn-cookie">Refuser</button>
                <button id="btn-cookie-accept" class="btn btn-primary btn-cookie">Accepter tout</button>
            </div>
        </div>
    </div>
    <!-- Bouton Retour en haut -->
    <button id="scroll-to-top" class="scroll-to-top" aria-label="Retourner en haut de la page">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="arrow-up-icon">
            <polyline points="18 15 12 9 6 15"></polyline>
        </svg>
    </button>

</body>
</html>
