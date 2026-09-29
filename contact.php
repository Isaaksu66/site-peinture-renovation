<?php
/**
 * Page de Contact et Espace Contact & Devis (Contact)
 * Cetin Family
 */

require_once __DIR__ . '/db.php';

// Récupération des messages flash de session
$success_msg = isset($_SESSION['form_success']) ? $_SESSION['form_success'] : null;
$error_list = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
$inputs = isset($_SESSION['form_inputs']) ? $_SESSION['form_inputs'] : [];

// Nettoyage immédiat de la session pour éviter le réaffichage lors des actualisations
unset($_SESSION['form_success']);
unset($_SESSION['form_errors']);
unset($_SESSION['form_inputs']);

// Traitement du formulaire en méthode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // 1. Validation de la faille CSRF
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = "Session expirée ou requête invalide. Veuillez réessayer.";
    }

    // 2. Récupération et nettoyage des données soumises
    $lastname = trim(isset($_POST['lastname']) ? $_POST['lastname'] : '');
    $firstname = trim(isset($_POST['firstname']) ? $_POST['firstname'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
    $location = trim(isset($_POST['location']) ? $_POST['location'] : '');
    $projectType = trim(isset($_POST['project-type']) ? $_POST['project-type'] : '');
    $surface = isset($_POST['surface']) ? (int)$_POST['surface'] : 0;
    $message = trim(isset($_POST['message']) ? $_POST['message'] : '');
    $consent = isset($_POST['consent']) ? true : false;

    // 3. Validations de champs
    if (empty($lastname)) {
        $errors[] = "Le nom est obligatoire.";
    }
    if (empty($firstname)) {
        $errors[] = "Le prénom est obligatoire.";
    }
    if (empty($email)) {
        $errors[] = "L'adresse e-mail est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse e-mail saisie n'est pas valide.";
    }
    if (empty($phone)) {
        $errors[] = "Le numéro de téléphone est obligatoire.";
    }
    if (empty($location)) {
        $errors[] = "La localisation (ville/code postal) du chantier est obligatoire.";
    }
    if (empty($projectType)) {
        $errors[] = "Le type de projet est requis.";
    }
    if ($surface <= 0) {
        $errors[] = "La surface estimée doit être supérieure à 0 m².";
    }
    if (empty($message)) {
        $errors[] = "La description de votre projet est obligatoire.";
    }
    if (!$consent) {
        $errors[] = "Vous devez consentir au traitement de vos données pour être recontacté.";
    }

    // 4. Traitement en base de données si valide
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO `devis` (lastname, firstname, email, phone, location, project_type, surface, message, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')
            ");
            $stmt->execute([
                $lastname,
                $firstname,
                $email,
                $phone,
                $location,
                $projectType,
                $surface,
                $message
            ]);

            // Envoi de la notification par e-mail
            $to = isset($company['email']) ? $company['email'] : CONTACT_EMAIL;
            $subject = "Nouveau Devis Soumis - Cetin Family";
            
            $mapped_project = $projectType;
            if ($projectType === 'peinture-int') $mapped_project = 'Peinture Intérieure & Décoration';
            elseif ($projectType === 'revetement-mur') $mapped_project = 'Pose de Revêtements Muraux';
            elseif ($projectType === 'ravalement-ext') $mapped_project = 'Peinture Extérieure & Ravalement';
            elseif ($projectType === 'revetement-sol') $mapped_project = 'Pose de Revêtements de Sol';
            elseif ($projectType === 'autre') $mapped_project = 'Autre projet / Plusieurs prestations';

            $email_content = "Une nouvelle demande de devis a été soumise sur le site Cetin Family :\n\n";
            $email_content .= "Client : " . $firstname . " " . $lastname . "\n";
            $email_content .= "Email : " . $email . "\n";
            $email_content .= "Téléphone : " . $phone . "\n";
            $email_content .= "Ville/Code Postal : " . $location . "\n";
            $email_content .= "Type de projet : " . $mapped_project . "\n";
            $email_content .= "Surface estimée : " . $surface . " m²\n\n";
            $email_content .= "Message :\n" . $message . "\n";
            
            $headers = "From: no-reply@cetinfamily71.fr\r\n";
            $headers .= "Reply-To: " . $email . "\r\n";
            $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
            
            // Tentative silencieuse d'envoi d'e-mail (pas d'erreur si le serveur SMTP n'est pas configuré en local)
            @mail($to, $subject, $email_content, $headers);

            $_SESSION['form_success'] = "Votre demande de devis a bien été envoyée ! Un artisan vous recontactera sous 48h pour fixer un rendez-vous.";
        } catch (PDOException $e) {
            $_SESSION['form_errors'] = ["Une erreur technique est survenue lors de l'enregistrement de votre devis. Veuillez nous excuser et réessayer plus tard."];
            $_SESSION['form_inputs'] = $_POST;
        }
    } else {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_inputs'] = $_POST; // Pour préremplir les champs saisis en cas d'erreur
    }

    // Redirection immédiate (POST-redirect-GET pattern)
    header("Location: contact.php");
    exit;
}

$page_title = "Contact & Devis | Cetin Family";
$page_description = "Demandez un devis gratuit pour vos travaux de peinture et décoration. Formulaire de contact en ligne rapide et coordonnées de l'entreprise.";
$active_page = "contact";

require_once __DIR__ . '/header.php';
?>

<main>
    <!-- En-tête de page -->
    <section class="page-banner">
        <div class="container">
            <h1><?php echo htmlspecialchars(get_site_text('contact_banner_title', 'Contact & Demande de Devis'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(get_site_text('contact_banner_desc', 'Une question ? Un projet de rénovation ? Demandez votre estimation gratuite en quelques clics.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </section>

    <!-- Section Contact & Formulaire -->
    <section id="contact-section">
        <div class="container">
            <div class="contact-grid">
                
                <!-- Informations de Contact -->
                <div class="contact-info-block">
                    <h2><?php echo htmlspecialchars(get_site_text('contact_intro_title', 'Nos Coordonnées'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars(get_site_text('contact_intro_desc', 'N\'hésitez pas à nous appeler directement ou à nous envoyer un e-mail pour discuter de vos travaux de peinture.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    
                    <div class="info-list">
                        <div class="info-item">
                            <strong>Téléphone :</strong>
                            <p><a href="tel:<?php echo str_replace(' ', '', $company['phone']); ?>"><?php echo htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8'); ?></a></p>
                        </div>
                        <div class="info-item">
                            <strong>E-mail :</strong>
                            <p><a href="mailto:<?php echo htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8'); ?></a></p>
                        </div>
                        <div class="info-item">
                            <strong>Zone d'intervention :</strong>
                            <p>Toute la région et ses communes limitrophes (dans un rayon de <?php echo (int)$company['radius_km']; ?> km autour de <?php echo htmlspecialchars($company['city'], ENT_QUOTES, 'UTF-8'); ?>).</p>
                        </div>
                        <div class="info-item">
                            <strong>Horaires de secrétariat :</strong>
                            <p><?php echo htmlspecialchars($company['hours_week'], ENT_QUOTES, 'UTF-8'); ?><br><?php echo htmlspecialchars($company['hours_sat'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>

                    <!-- Carte d'intervention interactive Leaflet JS -->
                    <div class="map-container-wrapper">
                        <div id="map"></div>
                    </div>

                    <!-- Configuration dynamique pour Leaflet JS -->
                    <script>
                        window.mapConfig = {
                            company_name: <?php echo json_encode($company['company_name'], JSON_UNESCAPED_UNICODE); ?>,
                            address: <?php echo json_encode($company['address'] . ', ' . $company['postal_code'] . ' ' . $company['city'], JSON_UNESCAPED_UNICODE); ?>,
                            latitude: <?php echo (float)$company['latitude']; ?>,
                            longitude: <?php echo (float)$company['longitude']; ?>,
                            radius: <?php echo (int)$company['radius_km'] * 1000; ?>,
                            city: <?php echo json_encode($company['city'], JSON_UNESCAPED_UNICODE); ?>
                        };
                    </script>
                </div>

                <!-- Formulaire de Devis -->
                <div class="contact-form-block">
                    <h2><?php echo htmlspecialchars(get_site_text('contact_form_title', 'Demander un Devis Gratuit'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars(get_site_text('contact_form_desc', "Remplissez ce formulaire et nous vous recontacterons sous 48 heures pour convenir d'un rendez-vous sur place."), ENT_QUOTES, 'UTF-8'); ?></p>
                    
                    <!-- Affichage des alertes PHP de succès ou erreurs -->
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success">
                            <p><?php echo htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_list)): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach ($error_list as $error): ?>
                                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="contact.php" method="POST" id="quote-form">
                        <!-- Jeton CSRF de sécurité -->
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <!-- Informations personnelles -->
                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="lastname">Nom *</label>
                                <input type="text" id="lastname" name="lastname" required placeholder="Votre nom" 
                                       value="<?php echo isset($inputs['lastname']) ? htmlspecialchars($inputs['lastname'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="firstname">Prénom *</label>
                                <input type="text" id="firstname" name="firstname" required placeholder="Votre prénom"
                                       value="<?php echo isset($inputs['firstname']) ? htmlspecialchars($inputs['firstname'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="email">E-mail *</label>
                                <input type="email" id="email" name="email" required placeholder="Ex: nom@domaine.fr"
                                       value="<?php echo isset($inputs['email']) ? htmlspecialchars($inputs['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Téléphone *</label>
                                <input type="tel" id="phone" name="phone" required placeholder="Ex: 06 12 34 56 78"
                                       value="<?php echo isset($inputs['phone']) ? htmlspecialchars($inputs['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                        </div>

                        <!-- Informations chantier -->
                        <div class="form-group">
                            <label for="location">Ville du chantier / Code Postal *</label>
                            <input type="text" id="location" name="location" required placeholder="Ex: 75000 Paris"
                                   value="<?php echo isset($inputs['location']) ? htmlspecialchars($inputs['location'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="project-type">Type de travaux *</label>
                            <select id="project-type" name="project-type" required>
                                <option value="" disabled <?php echo !isset($inputs['project-type']) ? 'selected' : ''; ?>>-- Sélectionnez une catégorie --</option>
                                <option value="peinture-int" <?php echo (isset($inputs['project-type']) && $inputs['project-type'] === 'peinture-int') ? 'selected' : ''; ?>>Peinture Intérieure & Décoration</option>
                                <option value="revetement-mur" <?php echo (isset($inputs['project-type']) && $inputs['project-type'] === 'revetement-mur') ? 'selected' : ''; ?>>Pose de Revêtements Muraux</option>
                                <option value="ravalement-ext" <?php echo (isset($inputs['project-type']) && $inputs['project-type'] === 'ravalement-ext') ? 'selected' : ''; ?>>Peinture Extérieure & Ravalement</option>
                                <option value="revetement-sol" <?php echo (isset($inputs['project-type']) && $inputs['project-type'] === 'revetement-sol') ? 'selected' : ''; ?>>Pose de Revêtements de Sol</option>
                                <option value="autre" <?php echo (isset($inputs['project-type']) && $inputs['project-type'] === 'autre') ? 'selected' : ''; ?>>Autre projet / Plusieurs prestations</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="surface">Surface estimée à peindre (m² au sol ou murs) *</label>
                            <input type="number" id="surface" name="surface" min="1" required placeholder="Ex: 50"
                                   value="<?php echo isset($inputs['surface']) ? htmlspecialchars($inputs['surface'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="message">Description détaillée de votre projet *</label>
                            <textarea id="message" name="message" rows="5" required placeholder="Décrivez l'état des supports actuels, vos préférences de finitions, couleurs, etc."><?php echo isset($inputs['message']) ? htmlspecialchars($inputs['message'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        </div>

                        <div class="form-group consent-checkbox">
                            <input type="checkbox" id="consent" name="consent" required <?php echo isset($inputs['consent']) ? 'checked' : ''; ?>>
                            <label for="consent">En soumettant ce formulaire, j'accepte que les informations saisies so
                            1ient exploitées pour me recontacter dans le cadre de ma demande de devis. *</label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-submit">Envoyer ma demande</button>
                    </form>
                </div>

            </div>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
