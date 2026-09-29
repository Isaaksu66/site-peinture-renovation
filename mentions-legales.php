<?php
/**
 * Page des Mentions Légales et RGPD (Mentions Légales)
 * Cetin Family
 */

$page_title = "Mentions Légales & RGPD | Cetin Family";
$page_description = "Consultez les informations légales de Cetin Family et notre charte de protection des données personnelles (RGPD).";
$active_page = "mentions-legales";

require_once __DIR__ . '/header.php';
?>

<main>
    <!-- En-tête de page -->
    <section class="page-banner">
        <div class="container">
            <h1>Mentions Légales & RGPD</h1>
            <p>Conformément à la réglementation française et européenne en vigueur.</p>
        </div>
    </section>

    <!-- Contenu des Mentions Légales -->
    <section id="legal-content" style="padding: 5rem 0;">
        <div class="container" style="max-width: 800px; margin: 0 auto; line-height: 1.8;">
            
            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: var(--primary-color);">1. Présentation du site</h2>
                <p>En vertu de l'article 6 de la loi n° 2004-575 du 21 juin 2004 pour la confiance dans l'économie numérique, il est précisé aux utilisateurs du site <strong><?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?></strong> l'identité des différents intervenants dans le cadre de sa réalisation et de son suivi :</p>
                <ul style="margin-left: 1.5rem; list-style-type: disc; margin-top: 0.5rem; color: var(--text-muted);">
                    <li><strong>Raison sociale :</strong> SASU CETIN FAMILY</li>
                    <li><strong>Siège social :</strong> <?php echo htmlspecialchars($company['address'] . ', ' . $company['postal_code'] . ' ' . $company['city'], ENT_QUOTES, 'UTF-8'); ?>, France</li>
                    <li><strong>Téléphone :</strong> <?php echo htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>E-mail :</strong> <a href="mailto:contact@cetinfamily71.fr" style="color: var(--accent-color);">contact@cetinfamily71.fr</a></li>
                    <li><strong>SIRET :</strong> 830 203 295</li>
                    <li><strong>N° de TVA intracommunautaire :</strong> FR67830203295</li>
                    <li><strong>Directeur de la publication :</strong> CETIN Fatma</li>
                </ul>
            </div>

            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: var(--primary-color);">2. Hébergement</h2>
                <p>Le site est hébergé par un prestataire professionnel conforme aux règles de sécurité en vigueur :</p>
                <ul style="margin-left: 1.5rem; list-style-type: disc; margin-top: 0.5rem; color: var(--text-muted);">
                    <li><strong>Hébergeur :</strong> SAS OVH</li>
                    <li><strong>Siège social :</strong> 2 rue Kellermann, 59100 Roubaix, France</li>
                    <li><strong>RCS Lille Métropole :</strong> 424 761 419</li>
                </ul>
            </div>

            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: var(--primary-color);">3. Propriété intellectuelle et contrefaçons</h2>
                <p><strong><?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?></strong> est propriétaire des droits de propriété intellectuelle ou détient les droits d'usage sur tous les éléments accessibles sur le site, notamment les textes, images, graphismes, logo, icônes, sons, logiciels.</p>
                <p>Toute reproduction, représentation, modification, publication, adaptation de tout ou partie des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite, sauf autorisation écrite préalable de <?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
            </div>

            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: var(--primary-color);">4. Règlement général sur la protection des données (RGPD)</h2>
                <p>CETIN FAMILY s'engage à ce que la collecte et le traitement de vos données, effectués à partir du site www.cetinfamily71.fr soient <strong>conformes au règlement général sur la protection des données (RGPD)</strong>. Chaque formulaire ou téléservice limite la collecte des données personnelles au strict nécessaire (minimisation des données) et indique notamment :</p>
                <ul style="margin-left: 1.5rem; list-style-type: disc; margin-top: 0.5rem; color: var(--text-muted);">
                    <li>quels sont les objectifs du recueil de ces données,</li>
                    <li>si ces données sont obligatoires ou facultatives pour la gestion de votre demande,</li>
                    <li>qui pourra en prendre connaissance (uniquement CETIN FAMILY en principe, sauf précision dans le formulaire lorsqu'une transmission à un tiers est nécessaire à la gestion de votre demande).</li>
                </ul>
                
                <p style="margin-top: 1rem;">Les données personnelles recueillies dans le cadre des services proposés sur www.cetinfamily71.fr sont traitées selon des <strong>protocoles sécurisés</strong> et permettent à CETIN FAMILY de gérer les demandes reçues dans ses applications informatiques.</p>
                <p>Pour toute information ou exercice de vos <strong>droits Informatique et Libertés</strong> sur les traitements de données personnelles gérés par CETIN FAMILY, vous pouvez <strong>contacter CETIN FAMILY</strong> et éventuellement son délégué à la protection des données (DPO), si distingué.</p>
                
                <h3 style="font-size: 1.1rem; margin-top: 1.5rem; margin-bottom: 0.5rem; color: var(--primary-color);">Données collectées via le formulaire</h3>
                <p>Lors de l'utilisation de notre formulaire de contact, nous collectons les données suivantes : Nom, Prénom, Téléphone, E-mail, Ville du chantier, Type de travaux, Surface estimée, et la description de votre projet.</p>
                
                <h3 style="font-size: 1.1rem; margin-top: 1.5rem; margin-bottom: 0.5rem; color: var(--primary-color);">Finalité du traitement</h3>
                <p>Ces données sont uniquement récoltées dans le but de recontacter l'utilisateur pour formuler des devis personnalisés ou répondre à ses interrogations. Aucune donnée n'est cédée à des tiers à des fins commerciales.</p>
                
                <h3 style="font-size: 1.1rem; margin-top: 1.5rem; margin-bottom: 0.5rem; color: var(--primary-color);">Vos droits (Droit d'accès, de rectification et d'opposition)</h3>
                <p>Conformément à la réglementation européenne, vous disposez d'un droit d'accès, de rectification, de mise à jour et de suppression des données qui vous concernent. Vous pouvez exercer ce droit à tout moment en envoyant un e-mail à : <strong><a href="mailto:contact@cetinfamily71.fr" style="color: var(--accent-color);">contact@cetinfamily71.fr</a></strong> ou en envoyant un courrier postal à l'adresse de notre siège social.</p>
            </div>

            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: var(--primary-color);">5. Liens hypertexte</h2>
                <p>Vous êtes libres d'<strong>établir des liens</strong> vers ce site dans la mesure où ils <strong>ne portent pas atteinte</strong> à l'image de son éditeur. Vous êtes autorisés à établir des liens vers les pages du site au format : html, php, xml.</p>
                <p>La pratique du <strong>framing est interdite</strong> de même que toute autre pratique ou technique susceptible d'engendrer une confusion dans l'esprit du public.</p>
                <p>Sont prohibés les liens profonds vers les documents aux formats pdf, Doc, ou rtf. En effet, ce type de document ne permet pas de prendre connaissance des présentes conditions d'utilisation.</p>
                <p>CETIN FAMILY ne contrôle pas les sites en connexion avec le sien, et <strong>ne saurait donc être responsable de leur contenu</strong>. Les risques liés à l'utilisation de ces sites incombent pleinement à l'utilisateur. Il se conformera à leurs conditions d'utilisation.</p>
            </div>

            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: var(--primary-color);">6. Responsabilité</h2>
                <p>CETIN FAMILY décline toute responsabilité pour les difficultés rencontrées lors de l'accès à son site ou pour toute défaillance de communication.</p>
                <p>CETIN FAMILY se réserve la possibilité de <strong>modifier</strong>, d'interrompre temporairement ou de façon permanente, <strong>toute ou une partie du site</strong>, sans préavis. CETIN FAMILY ne pourra en aucun cas être tenu pour responsable à votre encontre ou à l'encontre d'un tiers de toute modification, interruption ou suspension du site.</p>
            </div>

            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: var(--primary-color);">7. Utilisation de cookies</h2>
                <p>La gestion des commandes et l'affichage du site internet nécessite l'utilisation de cookies. Des <strong>informations non personnelles sont enregistrées</strong> par ce système de cookies (fichiers texte utilisés pour reconnaître un utilisateur et ainsi faciliter son utilisation du site). Ceux-ci n'ont aucune signification en dehors de leur utilisation sur le site www.cetinfamily71.fr</p>
            </div>

        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
