<?php
/**
 * Espace Administration / Back-office (Admin)
 * Cetin Family
 */

require_once __DIR__ . '/db.php';

// Protection CSRF sur toutes les requêtes POST de l'administration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['admin_error_flash'] = "Session expirée ou requête invalide. Veuillez réessayer.";
        header('Location: admin.php');
        exit;
    }
}

// Messages d'erreur et de succès pour l'admin
$admin_error = null;
$admin_success = null;

// ==========================================================================
// A. TRAITEMENT DU MOT DE PASSE OUBLIÉ (FORGOT PASSWORD)
// ==========================================================================
$simulated_email_body = null;
if (isset($_POST['forgot_submit'])) {
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    if (empty($email)) {
        $admin_error = "Veuillez saisir votre adresse e-mail.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $admin_error = "Veuillez saisir une adresse e-mail valide.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Générer un jeton aléatoire sécurisé
                $token = bin2hex(random_bytes(32));
                // Expire dans 1 heure
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Enregistrer en base de données
                $update = $pdo->prepare("UPDATE `admins` SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $update->execute([$token, $expires, $admin['id']]);
                
                // Construire le lien sécurisé
                $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?reset_token=" . $token;
                
                // Préparer le contenu du mail
                $subject = "Réinitialisation de votre mot de passe - Cetin Family Admin";
                $message = "Bonjour,\n\nVous avez demandé la réinitialisation de votre mot de passe pour l'espace administration de Cetin Family.\n\n";
                $message .= "Veuillez cliquer sur le lien sécurisé ci-dessous pour définir un nouveau mot de passe (valable 1 heure) :\n";
                $message .= $reset_link . "\n\n";
                $message .= "Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail en toute sécurité.\n\nCordialement,\nL'équipe Cetin Family.";
                
                $headers = "From: no-reply@cetinfamily71.fr\r\n";
                $headers .= "Reply-To: no-reply@cetinfamily71.fr\r\n";
                $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
                
                // Envoyer le mail réel
                @mail($email, $subject, $message, $headers);
                
                $admin_success = "Si cette adresse est enregistrée, un e-mail sécurisé vous a été envoyé contenant les instructions de réinitialisation.";
                
                // Pour l'environnement de développement local, on simule l'affichage
                $simulated_email_body = $reset_link;
            } else {
                // Même réponse de succès générique pour éviter d'énumérer les adresses existantes (sécurité contre le user enumeration)
                $admin_success = "Si cette adresse est enregistrée, un e-mail sécurisé vous a été envoyé contenant les instructions de réinitialisation.";
            }
        } catch (PDOException $e) {
            $admin_error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

// ==========================================================================
// B. TRAITEMENT DE LA RÉINITIALISATION EFFECTIVE (RESET PASSWORD)
// ==========================================================================
$valid_reset_admin = null;
$reset_token_param = isset($_GET['reset_token']) ? $_GET['reset_token'] : (isset($_POST['reset_token']) ? $_POST['reset_token'] : '');

if (!empty($reset_token_param)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
        $stmt->execute([$reset_token_param]);
        $valid_reset_admin = $stmt->fetch();
    } catch (PDOException $e) {}
}

if (isset($_POST['reset_submit'])) {
    $new_pwd = $_POST['new_password'];
    $confirm_pwd = $_POST['confirm_password'];
    
    if (!$valid_reset_admin) {
        $admin_error = "Ce jeton de réinitialisation est invalide ou a expiré.";
    } elseif (empty($new_pwd) || empty($confirm_pwd)) {
        $admin_error = "Veuillez remplir tous les champs.";
    } elseif ($new_pwd !== $confirm_pwd) {
        $admin_error = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
    } elseif (strlen($new_pwd) < 8) {
        $admin_error = "Le mot de passe doit faire au moins 8 caractères.";
    } else {
        try {
            $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE `admins` SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $update->execute([$new_hash, $valid_reset_admin['id']]);
            
            $_SESSION['admin_success_flash'] = "Votre mot de passe a été mis à jour avec succès ! Vous pouvez maintenant vous connecter.";
            header("Location: admin.php");
            exit;
        } catch (PDOException $e) {
            $admin_error = "Erreur lors de la mise à jour du mot de passe : " . $e->getMessage();
        }
    }
}

// ==========================================================================
// 1. TRAITEMENT DE LA CONNEXION (LOGIN) & SÉCURISATION BRUTE-FORCE
// ==========================================================================
$ip_address = $_SERVER['REMOTE_ADDR'];

// Nettoyage automatique des anciennes tentatives (plus de 15 minutes)
try {
    $pdo->prepare("DELETE FROM `login_attempts` WHERE attempted_at < NOW() - INTERVAL 15 MINUTE")->execute();
} catch (PDOException $e) {}

if (isset($_POST['login_submit'])) {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $admin_error = "Veuillez remplir tous les champs.";
    } else {
        try {
            // Vérification du nombre de tentatives pour cette IP
            $checkAttempts = $pdo->prepare("SELECT COUNT(*) FROM `login_attempts` WHERE ip_address = ?");
            $checkAttempts->execute([$ip_address]);
            $failed_count = (int)$checkAttempts->fetchColumn();

            if ($failed_count >= 5) {
                $admin_error = "Trop de tentatives infructueuses. Votre adresse IP est temporairement bloquée pendant 15 minutes.";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    // Connexion réussie : vider l'historique des blocages pour cette IP
                    $clearAttempts = $pdo->prepare("DELETE FROM `login_attempts` WHERE ip_address = ?");
                    $clearAttempts->execute([$ip_address]);

                    // Régénération de l'ID de session pour prévenir la fixation de session
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_id'] = $admin['id'];
                    header("Location: admin.php");
                    exit;
                } else {
                    // Connexion échouée : enregistrement de la tentative
                    $logAttempt = $pdo->prepare("INSERT INTO `login_attempts` (ip_address) VALUES (?)");
                    $logAttempt->execute([$ip_address]);
                    
                    $remaining = 5 - ($failed_count + 1);
                    if ($remaining <= 0) {
                        $admin_error = "Trop de tentatives infructueuses. Votre adresse IP est maintenant bloquée pendant 15 minutes.";
                    } else {
                        $admin_error = "Identifiant ou mot de passe incorrect. (Tentatives restantes : $remaining)";
                    }
                }
            }
        } catch (PDOException $e) {
            $admin_error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

// Sécurité : Si non connecté, on affiche le formulaire de connexion
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Libellés lisibles pour le type de projet (défini avant le bloc connecté car utilisé par l'export CSV)
$project_labels = [
    'peinture-int' => 'Peinture Intérieure',
    'revetement-mur' => 'Revêtement Mural',
    'ravalement-ext' => 'Peinture Extérieure',
    'revetement-sol' => 'Revêtement de Sol',
    'autre' => 'Autre / Plusieurs'
];

// ==========================================================================
// 2. ACTIONS DU TABLEAU DE BORD (ACTIONS DISPONIBLES UNIQUEMENT SI CONNECTÉ)
// ==========================================================================
if ($is_logged_in) {

    // Fonction de traitement d'upload d'image sécurisé (CMS)
    function handle_image_upload($file_input_name, $existing_path = '') {
        if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] === UPLOAD_ERR_NO_FILE) {
            return $existing_path;
        }

        $file = $_FILES[$file_input_name];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors du transfert du fichier.");
        }

        // Valider la taille (5 Mo maximum)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("Le fichier est trop volumineux (maximum 5 Mo).");
        }

        // Valider le type MIME via fileinfo
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);

        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception("Type de fichier non autorisé (JPEG, PNG, WEBP et SVG uniquement).");
        }

        // Déterminer l'extension appropriée
        $extension = 'jpg';
        if ($mime_type === 'image/png') $extension = 'png';
        elseif ($mime_type === 'image/webp') $extension = 'webp';
        elseif ($mime_type === 'image/svg+xml') $extension = 'svg';

        // Générer un nom unique sécurisé pour éviter l'écrasement ou l'injection de chemin
        $new_filename = 'upload_' . uniqid() . '.' . $extension;
        $target_dir = __DIR__ . '/assets/images/';
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $target_path = $target_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Nettoyage de l'ancienne image si c'était une image uploadée par l'admin
            if (!empty($existing_path) && strpos($existing_path, 'upload_') !== false) {
                $old_full_path = __DIR__ . '/' . $existing_path;
                if (file_exists($old_full_path)) {
                    @unlink($old_full_path);
                }
            }
            return 'assets/images/' . $new_filename;
        } else {
            throw new Exception("Impossible de sauvegarder l'image sur le serveur.");
        }
    }

    // A. Changement de Statut
    if (isset($_POST['action_status'])) {
        $devis_id = (int)(isset($_POST['devis_id']) ? $_POST['devis_id'] : 0);
        $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';
        $allowed_status = ['en_attente', 'en_cours', 'traite', 'rejete'];

        if ($devis_id > 0 && in_array($new_status, $allowed_status)) {
            try {
                $stmt = $pdo->prepare("UPDATE `devis` SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $devis_id]);
                $_SESSION['admin_success_flash'] = "Statut du devis #$devis_id mis à jour avec succès.";
            } catch (PDOException $e) {
                $_SESSION['admin_error_flash'] = "Erreur de mise à jour : " . $e->getMessage();
            }
        }
        header("Location: admin.php");
        exit;
    }

    // B. Suppression de Devis
    if (isset($_POST['action_delete'])) {
        $devis_id = (int)(isset($_POST['devis_id']) ? $_POST['devis_id'] : 0);

        if ($devis_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `devis` WHERE id = ?");
                $stmt->execute([$devis_id]);
                $_SESSION['admin_success_flash'] = "Le devis #$devis_id a été supprimé.";
            } catch (PDOException $e) {
                $_SESSION['admin_error_flash'] = "Erreur lors de la suppression : " . $e->getMessage();
            }
        }
        header("Location: admin.php");
        exit;
    }

    // C. Modification du Mot de Passe Admin
    if (isset($_POST['password_submit'])) {
        $old_pwd = $_POST['old_password'];
        $new_pwd = $_POST['new_password'];
        $confirm_pwd = $_POST['confirm_password'];

        if (empty($old_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            $_SESSION['admin_error_flash'] = "Tous les champs de mot de passe sont obligatoires.";
        } elseif ($new_pwd !== $confirm_pwd) {
            $_SESSION['admin_error_flash'] = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
        } elseif (strlen($new_pwd) < 8) {
            $_SESSION['admin_error_flash'] = "Le nouveau mot de passe doit faire au moins 8 caractères.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM `admins` WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $adminPwd = $stmt->fetchColumn();

                if ($adminPwd && password_verify($old_pwd, $adminPwd)) {
                    $newHash = password_hash($new_pwd, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE `admins` SET password = ? WHERE id = ?");
                    $update->execute([$newHash, $_SESSION['admin_id']]);
                    $_SESSION['admin_success_flash'] = "Votre mot de passe a bien été modifié.";
                } else {
                    $_SESSION['admin_error_flash'] = "L'ancien mot de passe saisi est incorrect.";
                }
            } catch (PDOException $e) {
                $_SESSION['admin_error_flash'] = "Erreur de base de données : " . $e->getMessage();
            }
        }
        header("Location: admin.php");
        exit;
    }

    // D. Mise à jour des Infos Entreprise (CMS)
    if (isset($_POST['action_update_settings'])) {
        $company_name = trim($_POST['company_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $postal_code = trim($_POST['postal_code']);
        $hours_week = trim($_POST['hours_week']);
        $hours_sat = trim($_POST['hours_sat']);
        $radius_km = (int)$_POST['radius_km'];
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];
        $facebook_url = trim($_POST['facebook_url']);

        if (empty($company_name) || empty($phone) || empty($email) || empty($address) || empty($city) || empty($postal_code)) {
            $_SESSION['admin_error_flash'] = "Tous les champs de contact et le nom de l'entreprise sont obligatoires.";
        } else {
            try {
                $stmt_get_id = $pdo->query("SELECT id FROM `company_settings` LIMIT 1");
                $comp_id = (int)$stmt_get_id->fetchColumn();
                if ($comp_id <= 0) $comp_id = 1;

                $stmt = $pdo->prepare("
                    UPDATE `company_settings` 
                    SET company_name = ?, phone = ?, email = ?, address = ?, city = ?, postal_code = ?, hours_week = ?, hours_sat = ?, radius_km = ?, latitude = ?, longitude = ?, facebook_url = ?
                    WHERE id = ?
                ");
                $stmt->execute([$company_name, $phone, $email, $address, $city, $postal_code, $hours_week, $hours_sat, $radius_km, $latitude, $longitude, $facebook_url, $comp_id]);
                $_SESSION['admin_success_flash'] = "Coordonnées et paramètres mis à jour avec succès.";
            } catch (PDOException $e) {
                $_SESSION['admin_error_flash'] = "Erreur SQL : " . $e->getMessage();
            }
        }
        header("Location: admin.php#entreprise");
        exit;
    }

    // DG. Mise à jour des Textes de Page (CMS)
    if (isset($_POST['action_update_texts'])) {
        if (isset($_POST['texts']) && is_array($_POST['texts'])) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE `site_texts` SET text_value = ? WHERE text_key = ?");
                foreach ($_POST['texts'] as $key => $value) {
                    $stmt->execute([trim($value), $key]);
                }
                $pdo->commit();
                $_SESSION['admin_success_flash'] = "Les textes du site ont été mis à jour avec succès.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['admin_error_flash'] = "Erreur lors de la mise à jour des textes : " . $e->getMessage();
            }
        }
        header("Location: admin.php#contenus");
        exit;
    }

    // E. Gestion des Prestations (Ajout, Modification, Suppression)
    if (isset($_POST['action_add_service'])) {
        $title = trim($_POST['title']);
        $anchor = trim($_POST['anchor']);
        $description = trim($_POST['description']);
        $features = trim($_POST['features']);

        if (empty($title) || empty($anchor) || empty($description)) {
            $_SESSION['admin_error_flash'] = "Le titre, l'ancre et la description sont obligatoires.";
        } else {
            try {
                $image_path = 'assets/images/peinture_interieure.jpg'; // Secours par défaut
                if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $image_path = handle_image_upload('service_image');
                }
                
                $stmt = $pdo->prepare("INSERT INTO `services` (title, description, features, image_path, anchor) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $features, $image_path, $anchor]);
                $_SESSION['admin_success_flash'] = "Nouvelle prestation ajoutée avec succès.";
            } catch (Exception $e) {
                $_SESSION['admin_error_flash'] = "Erreur : " . $e->getMessage();
            }
        }
        header("Location: admin.php#prestations");
        exit;
    }

    if (isset($_POST['action_edit_service'])) {
        $service_id = (int)$_POST['service_id'];
        $title = trim($_POST['title']);
        $anchor = trim($_POST['anchor']);
        $description = trim($_POST['description']);
        $features = trim($_POST['features']);

        if ($service_id <= 0 || empty($title) || empty($anchor) || empty($description)) {
            $_SESSION['admin_error_flash'] = "Données invalides pour la modification.";
        } else {
            try {
                $oldSrv = $pdo->prepare("SELECT image_path FROM `services` WHERE id = ?");
                $oldSrv->execute([$service_id]);
                $old_image = $oldSrv->fetchColumn();

                $image_path = handle_image_upload('service_image', $old_image);

                $stmt = $pdo->prepare("UPDATE `services` SET title = ?, anchor = ?, description = ?, features = ?, image_path = ? WHERE id = ?");
                $stmt->execute([$title, $anchor, $description, $features, $image_path, $service_id]);
                $_SESSION['admin_success_flash'] = "Prestation modifiée avec succès.";
            } catch (Exception $e) {
                $_SESSION['admin_error_flash'] = "Erreur : " . $e->getMessage();
            }
        }
        header("Location: admin.php#prestations");
        exit;
    }

    if (isset($_POST['action_delete_service'])) {
        $service_id = (int)$_POST['service_id'];
        if ($service_id > 0) {
            try {
                $oldSrv = $pdo->prepare("SELECT image_path FROM `services` WHERE id = ?");
                $oldSrv->execute([$service_id]);
                $image_path = $oldSrv->fetchColumn();
                if (!empty($image_path) && strpos($image_path, 'upload_') !== false) {
                    @unlink(__DIR__ . '/' . $image_path);
                }

                $stmt = $pdo->prepare("DELETE FROM `services` WHERE id = ?");
                $stmt->execute([$service_id]);
                $_SESSION['admin_success_flash'] = "Prestation supprimée.";
            } catch (PDOException $e) {
                $_SESSION['admin_error_flash'] = "Erreur de suppression : " . $e->getMessage();
            }
        }
        header("Location: admin.php#prestations");
        exit;
    }

    // F. Gestion des Réalisations (Ajout, Modification, Suppression)
    if (isset($_POST['action_add_project'])) {
        $title = trim($_POST['title']);
        $category = trim($_POST['category']);
        $tags = trim($_POST['tags']);
        $description = trim($_POST['description']);

        if (empty($title) || empty($category) || empty($tags) || empty($description)) {
            $_SESSION['admin_error_flash'] = "Tous les champs du projet sont obligatoires.";
        } else {
            try {
                $image_path = 'assets/images/realisation_salon.jpg';
                if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $image_path = handle_image_upload('project_image');
                }

                $stmt = $pdo->prepare("INSERT INTO `projects` (title, category, tags, description, image_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $category, $tags, $description, $image_path]);
                $_SESSION['admin_success_flash'] = "Nouveau chantier ajouté à la galerie.";
            } catch (Exception $e) {
                $_SESSION['admin_error_flash'] = "Erreur : " . $e->getMessage();
            }
        }
        header("Location: admin.php#realisations");
        exit;
    }

    if (isset($_POST['action_edit_project'])) {
        $project_id = (int)$_POST['project_id'];
        $title = trim($_POST['title']);
        $category = trim($_POST['category']);
        $tags = trim($_POST['tags']);
        $description = trim($_POST['description']);

        if ($project_id <= 0 || empty($title) || empty($category) || empty($tags) || empty($description)) {
            $_SESSION['admin_error_flash'] = "Données invalides pour la modification.";
        } else {
            try {
                $oldProj = $pdo->prepare("SELECT image_path FROM `projects` WHERE id = ?");
                $oldProj->execute([$project_id]);
                $old_image = $oldProj->fetchColumn();

                $image_path = handle_image_upload('project_image', $old_image);

                $stmt = $pdo->prepare("UPDATE `projects` SET title = ?, category = ?, tags = ?, description = ?, image_path = ? WHERE id = ?");
                $stmt->execute([$title, $category, $tags, $description, $image_path, $project_id]);
                $_SESSION['admin_success_flash'] = "Chantier modifié avec succès.";
            } catch (Exception $e) {
                $_SESSION['admin_error_flash'] = "Erreur : " . $e->getMessage();
            }
        }
        header("Location: admin.php#realisations");
        exit;
    }

    if (isset($_POST['action_delete_project'])) {
        $project_id = (int)$_POST['project_id'];
        if ($project_id > 0) {
            try {
                $oldProj = $pdo->prepare("SELECT image_path FROM `projects` WHERE id = ?");
                $oldProj->execute([$project_id]);
                $image_path = $oldProj->fetchColumn();
                if (!empty($image_path) && strpos($image_path, 'upload_') !== false) {
                    @unlink(__DIR__ . '/' . $image_path);
                }

                $stmt = $pdo->prepare("DELETE FROM `projects` WHERE id = ?");
                $stmt->execute([$project_id]);
                $_SESSION['admin_success_flash'] = "Chantier supprimé de la galerie.";
            } catch (PDOException $e) {
                $_SESSION['admin_error_flash'] = "Erreur de suppression : " . $e->getMessage();
            }
        }
        header("Location: admin.php#realisations");
        exit;
    }

    // Récupération des messages flash de session
    if (isset($_SESSION['admin_success_flash'])) {
        $admin_success = $_SESSION['admin_success_flash'];
        unset($_SESSION['admin_success_flash']);
    }
    if (isset($_SESSION['admin_error_flash'])) {
        $admin_error = $_SESSION['admin_error_flash'];
        unset($_SESSION['admin_error_flash']);
    }

    // D. Extraction des statistiques globales
    try {
        $total_devis = $pdo->query("SELECT COUNT(*) FROM `devis`")->fetchColumn();
        $pending_devis = $pdo->query("SELECT COUNT(*) FROM `devis` WHERE status = 'en_attente'")->fetchColumn();
        $treated_devis = $pdo->query("SELECT COUNT(*) FROM `devis` WHERE status = 'traite'")->fetchColumn();
        $avg_raw = $pdo->query("SELECT AVG(surface) FROM `devis`")->fetchColumn();
        $avg_surface = $avg_raw !== null ? round((float)$avg_raw, 1) : 0;
    } catch (PDOException $e) {
        $admin_error = "Erreur de chargement des statistiques : " . $e->getMessage();
    }

    // F. Traitement des statistiques de Visibilité (Fiche Visibilité)
    $stat_page = isset($_GET['sp']) ? (int)$_GET['sp'] : 1;
    if ($stat_page < 1) $stat_page = 1;
    $stat_limit = 10; // 10 lignes par page pour correspondre exactement à l'image
    $stat_offset = ($stat_page - 1) * $stat_limit;
    
    $total_stat_days = 0;
    $analytics_logs = [];
    $rolling_months = [];
    $monthly_data = [];
    $page_total_visits = 0;
    $page_total_visitors = 0;
    $page_total_views = 0;
    $total_stat_pages = 1;
    
    try {
        // Compter le nombre de jours uniques enregistrés
        $total_stat_days = (int)$pdo->query("SELECT COUNT(DISTINCT visit_date) FROM `site_analytics`")->fetchColumn();
        $total_stat_pages = ceil($total_stat_days / $stat_limit);
        if ($total_stat_pages < 1) $total_stat_pages = 1;
        if ($stat_page > $total_stat_pages) $stat_page = $total_stat_pages;
        $stat_offset = ($stat_page - 1) * $stat_limit;
        
        // Récupérer les stats quotidiennes groupées par date (triées par date décroissante)
        $stat_stmt = $pdo->prepare("
            SELECT 
                visit_date AS date,
                COUNT(DISTINCT session_id) AS visits,
                COUNT(DISTINCT ip_hash) AS unique_visitors,
                COUNT(*) AS page_views
            FROM `site_analytics`
            GROUP BY visit_date
            ORDER BY visit_date DESC
            LIMIT ? OFFSET ?
        ");
        $stat_stmt->bindValue(1, $stat_limit, PDO::PARAM_INT);
        $stat_stmt->bindValue(2, $stat_offset, PDO::PARAM_INT);
        $stat_stmt->execute();
        $analytics_logs = $stat_stmt->fetchAll();
        
        // Calculer les totaux de la page courante pour la ligne de Total en bas
        foreach ($analytics_logs as $log) {
            $page_total_visits += $log['visits'];
            $page_total_visitors += $log['unique_visitors'];
            $page_total_views += $log['page_views'];
        }
        
        // Générer le tableau dynamique des 12 derniers mois (mois glissants)
        $months_fr = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
        ];
        
        // On remonte 11 mois en arrière jusqu'au mois en cours (12 mois au total)
        for ($i = 11; $i >= 0; $i--) {
            $time = strtotime("-$i months");
            $m = (int)date('m', $time);
            $y = date('Y', $time);
            $rolling_months[] = [
                'num' => $m,
                'year' => $y,
                'label' => $months_fr[$m],
                'key' => "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT)
            ];
        }
        
        // Initialiser toutes les valeurs mensuelles à 0 par défaut
        foreach ($rolling_months as $rm) {
            $monthly_data[$rm['key']] = 0;
        }
        
        // Remplir avec les vraies données issues de la base
        $month_stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(visit_date, '%Y-%m') AS month_key,
                COUNT(DISTINCT session_id) AS visits
            FROM `site_analytics`
            GROUP BY month_key
        ");
        while ($row = $month_stmt->fetch()) {
            if (isset($monthly_data[$row['month_key']])) {
                $monthly_data[$row['month_key']] = (int)$row['visits'];
            }
        }
        
    } catch (PDOException $e) {
        $admin_error = "Erreur analytique : " . $e->getMessage();
    }

    // E. Gestion de la Recherche et des Filtres de la table devis
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $project_filter = isset($_GET['project_type']) ? $_GET['project_type'] : '';

    $query_base = "SELECT * FROM `devis` WHERE 1=1";
    $query_params = [];

    if ($search !== '') {
        $query_base .= " AND (lastname LIKE ? OR firstname LIKE ? OR location LIKE ? OR message LIKE ?)";
        $search_wildcard = "%$search%";
        $query_params[] = $search_wildcard;
        $query_params[] = $search_wildcard;
        $query_params[] = $search_wildcard;
        $query_params[] = $search_wildcard;
    }

    if ($status_filter !== '') {
        $query_base .= " AND status = ?";
        $query_params[] = $status_filter;
    }

    if ($project_filter !== '') {
        $query_base .= " AND project_type = ?";
        $query_params[] = $project_filter;
    }



    // Calcul du total des résultats filtrés pour la pagination
    try {
        $count_query = str_replace("SELECT * FROM `devis`", "SELECT COUNT(*) FROM `devis`", $query_base);
        $stmt_count = $pdo->prepare($count_query);
        $stmt_count->execute($query_params);
        $total_filtered = (int)$stmt_count->fetchColumn();
    } catch (PDOException $e) {
        $total_filtered = 0;
    }

    // Paramètres de pagination
    $limit = 10; // 10 devis par page
    $total_pages = ceil($total_filtered / $limit);
    if ($total_pages < 1) $total_pages = 1;
    
    $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($page < 1) $page = 1;
    if ($page > $total_pages) $page = $total_pages;
    
    $offset = ($page - 1) * $limit;

    // Requête finale avec pagination
    $query_str = $query_base . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

    try {
        $stmt_devis = $pdo->prepare($query_str);
        $stmt_devis->execute($query_params);
        $devis_list = $stmt_devis->fetchAll();
    } catch (PDOException $e) {
        $admin_error = "Erreur de requêtage des devis : " . $e->getMessage();
        $devis_list = [];
    }

    // Extractions des prestations et chantiers pour l'affichage CMS
    try {
        $services_all = $pdo->query("SELECT * FROM `services` ORDER BY id ASC")->fetchAll();
        $projects_all = $pdo->query("SELECT * FROM `projects` ORDER BY id DESC")->fetchAll();
    } catch (PDOException $e) {
        $services_all = [];
        $projects_all = [];
    }

    // Chargement d'une prestation en cours d'édition (si demandé via GET)
    $edit_service_data = null;
    $edit_service_id = isset($_GET['edit_service']) ? (int)$_GET['edit_service'] : 0;
    if ($edit_service_id > 0) {
        try {
            $srvStmt = $pdo->prepare("SELECT * FROM `services` WHERE id = ?");
            $srvStmt->execute([$edit_service_id]);
            $edit_service_data = $srvStmt->fetch();
        } catch (PDOException $e) {}
    }

    // Chargement d'un chantier en cours d'édition (si demandé via GET)
    $edit_project_data = null;
    $edit_project_id = isset($_GET['edit_project']) ? (int)$_GET['edit_project'] : 0;
    if ($edit_project_id > 0) {
        try {
            $projStmt = $pdo->prepare("SELECT * FROM `projects` WHERE id = ?");
            $projStmt->execute([$edit_project_id]);
            $edit_project_data = $projStmt->fetch();
        } catch (PDOException $e) {}
    }
}


$page_title = $is_logged_in ? "Espace Admin | Cetin Family" : "Connexion Admin | Cetin Family";
$active_page = "admin";
$body_class = $is_logged_in ? "admin-logged-in" : "";

require_once __DIR__ . '/header.php';
?>

<main class="admin-main">
    <div class="container container-admin">
        
        <?php if (!$is_logged_in): ?>
            
            <?php if (!empty($reset_token_param)): ?>
                <!-- FORMULAIRE DE RÉINITIALISATION DU MOT DE PASSE -->
                <section id="admin-login-section">
                    <div class="login-card">
                        <h2>Réinitialisation du mot de passe</h2>
                        <?php if ($valid_reset_admin): ?>
                            <p>Définissez votre nouveau mot de passe administrateur sécurisé.</p>
                            
                            <?php if ($admin_error): ?>
                                <div class="alert alert-error">
                                    <p><?php echo htmlspecialchars($admin_error, ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <form action="admin.php?reset_token=<?php echo htmlspecialchars($reset_token_param, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($reset_token_param, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="form-group">
                                    <label for="new_password">Nouveau mot de passe *</label>
                                    <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Minimum 8 caractères">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirmer le mot de passe *</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirmez le mot de passe">
                                </div>
                                <button type="submit" name="reset_submit" class="btn btn-primary btn-submit">💾 Enregistrer le nouveau mot de passe</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-error" style="margin-top: 1rem;">
                                <p>Ce lien de réinitialisation est invalide ou a expiré (valable 1 heure).</p>
                            </div>
                            <p style="text-align: center; margin-top: 1.5rem;"><a href="admin.php" class="btn btn-secondary" style="text-decoration: none;">Retour à la connexion</a></p>
                        <?php endif; ?>
                    </div>
                </section>

            <?php elseif (isset($_GET['forgot']) || isset($_POST['forgot_submit'])): ?>
                <!-- FORMULAIRE MOT DE PASSE OUBLIÉ -->
                <section id="admin-login-section">
                    <div class="login-card">
                        <h2>Mot de passe oublié</h2>
                        <p>Saisissez votre adresse e-mail d'administrateur pour recevoir un lien de réinitialisation.</p>

                        <?php if ($admin_error): ?>
                            <div class="alert alert-error">
                                <p><?php echo htmlspecialchars($admin_error, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($admin_success): ?>
                            <div class="alert alert-success">
                                <p><?php echo htmlspecialchars($admin_success, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            
                            <?php if ($simulated_email_body): ?>
                                <div class="alert alert-info" style="margin-top: 1rem; border-left: 4px solid var(--accent-color); background: rgba(197, 168, 128, 0.05); color: var(--text-color);">
                                    <p>⚙️ <strong>[Simulateur Local Dev]</strong> L'e-mail a été simulé. Cliquez sur le lien ci-dessous pour tester immédiatement la réinitialisation :</p>
                                    <p style="margin-top: 0.5rem; word-break: break-all;"><a href="<?php echo $simulated_email_body; ?>" style="color: var(--accent-color); text-decoration: underline; font-weight: bold;"><?php echo $simulated_email_body; ?></a></p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <form action="admin.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="form-group">
                                <label for="email">Adresse e-mail administrateur *</label>
                                <input type="email" id="email" name="email" required placeholder="Ex: admin@peinture-elegance.fr">
                            </div>
                            <button type="submit" name="forgot_submit" class="btn btn-primary btn-submit">📨 Envoyer le lien de réinitialisation</button>
                            <p style="text-align: center; margin-top: 1.5rem;"><a href="admin.php" style="color: var(--accent-color); font-size: 0.9rem; text-decoration: underline;">Retour à la connexion</a></p>
                        </form>
                    </div>
                </section>

            <?php else: ?>
                <!-- FORMULAIRE DE CONNEXION ADMIN PAR DÉFAUT -->
                <section id="admin-login-section">
                    <div class="login-card">
                        <h2>Espace Administration</h2>
                        <p>Veuillez vous authentifier pour accéder au tableau de bord.</p>

                        <?php if (isset($_SESSION['admin_success_flash'])): ?>
                            <div class="alert alert-success">
                                <p><?php echo htmlspecialchars($_SESSION['admin_success_flash'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <?php unset($_SESSION['admin_success_flash']); ?>
                        <?php endif; ?>

                        <?php if ($admin_error): ?>
                            <div class="alert alert-error">
                                <p><?php echo htmlspecialchars($admin_error, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php endif; ?>

                        <form action="admin.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="form-group">
                                <label for="username">Identifiant *</label>
                                <input type="text" id="username" name="username" required placeholder="Votre identifiant">
                            </div>
                            <div class="form-group">
                                <label for="password">Mot de passe *</label>
                                <input type="password" id="password" name="password" required placeholder="Mot de passe">
                            </div>
                            <button type="submit" name="login_submit" class="btn btn-primary btn-submit">Se connecter</button>
                            <p style="text-align: center; margin-top: 1.5rem;"><a href="admin.php?forgot=1" style="color: var(--accent-color); font-size: 0.9rem; text-decoration: underline;">Mot de passe oublié ?</a></p>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        
        <?php else: ?>
            
            <!-- CONTENEUR DU DASHBOARD PREMIUM SIDEBAR -->
            </div> <!-- Fin .container-admin (login uniquement) -->
            </main> <!-- Fin .admin-main (login uniquement) -->
            
            <div class="admin-dashboard-wrapper">
                <!-- Sidebar latérale (Menu type Espace Client) -->
                <aside class="admin-sidebar">
                    <div class="sidebar-brand">
                        <span class="brand-icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="sidebar-brand-svg"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 14.7255 3.09032 17.1962 4.85857 19C5.34798 19.4894 5.59269 19.7341 5.62649 19.9866C5.6603 20.239 5.48512 20.5137 5.13476 21.0631C4.78762 21.6074 4.87247 22 5.5 22H12Z"></path><circle cx="7.5" cy="10.5" r="1.5" fill="currentColor"></circle><circle cx="11.5" cy="7.5" r="1.5" fill="currentColor"></circle><circle cx="16.5" cy="9.5" r="1.5" fill="currentColor"></circle><circle cx="15.5" cy="14.5" r="1.5" fill="currentColor"></circle></svg>
                        </span>
                        <h2>CETIN FAMILY</h2>
                    </div>
                    <div class="sidebar-user">
                        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?></div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="user-role">Administrateur</span>
                        </div>
                    </div>
                    
                    <nav class="sidebar-nav">
                        <div class="nav-section-title">CONFIGURATION</div>
                        <ul>
                            <li>
                                <button type="button" class="sidebar-nav-btn admin-tab-btn active" data-tab="devis">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                                    </span>
                                    Tableau de bord
                                </button>
                            </li>
                            <li>
                                <button type="button" class="sidebar-nav-btn admin-tab-btn" data-tab="visibilite">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                                    </span>
                                    Visibilité (Stats)
                                </button>
                            </li>
                            <li>
                                <button type="button" class="sidebar-nav-btn admin-tab-btn" data-tab="entreprise">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    </span>
                                    Coordonnées & Horaires
                                </button>
                            </li>
                            <li>
                                <button type="button" class="sidebar-nav-btn admin-tab-btn" data-tab="securite">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    </span>
                                    Sécurité & Mot de passe
                                </button>
                            </li>
                        </ul>
                        
                        <div class="nav-section-title">MODULES</div>
                        <ul>
                            <li>
                                <button type="button" class="sidebar-nav-btn admin-tab-btn" data-tab="prestations">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                                    </span>
                                    Prestations (Services)
                                </button>
                            </li>
                            <li>
                                <button type="button" class="sidebar-nav-btn admin-tab-btn" data-tab="realisations">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                    </span>
                                    Réalisations (Galerie)
                                </button>
                            </li>
                            <li>
                                <button type="button" class="sidebar-nav-btn admin-tab-btn" data-tab="contenus">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    </span>
                                    Pages & Textes (CMS)
                                </button>
                            </li>
                        </ul>
                    </nav>
                    
                    <div class="sidebar-footer">
                        <a href="logout.php" class="btn-logout">
                            <span class="nav-icon">
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            </span>
                            Quitter l'admin
                        </a>
                    </div>
                </aside>
                
                <!-- Zone de contenu à droite -->
                <main class="admin-content">
                    <!-- Topbar breadcrumbs & actions -->
                    <header class="admin-topbar">
                        <div class="topbar-breadcrumb">
                            <span class="bc-item">Administration</span>
                            <span class="bc-separator">/</span>
                            <span class="bc-item active" id="active-breadcrumb-tab">Tableau de bord</span>
                        </div>
                        <div class="topbar-actions">
                            <a href="index.php" target="_blank" class="btn btn-secondary btn-sm-admin" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; text-decoration: none;">👁️ Voir le Site</a>
                        </div>
                    </header>
                    
                    <div class="admin-tab-container-fluid">
                        <!-- Alertes flash d'administration -->
                        <?php if ($admin_success): ?>
                            <div class="alert alert-success">
                                <p><?php echo htmlspecialchars($admin_success, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($admin_error): ?>
                            <div class="alert alert-error">
                                <p><?php echo htmlspecialchars($admin_error, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="admin-tabs-container">
                    
                    <!-- Onglet 1 : Devis clients -->
                    <div id="devis" class="admin-tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Demandes totales</h3>
                        <span class="stat-number"><?php echo $total_devis; ?></span>
                    </div>
                    <div class="stat-card stat-pending">
                        <h3>En attente</h3>
                        <span class="stat-number"><?php echo $pending_devis; ?></span>
                    </div>
                    <div class="stat-card stat-completed">
                        <h3>Traitées</h3>
                        <span class="stat-number"><?php echo $treated_devis; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3>Surface moyenne</h3>
                        <span class="stat-number"><?php echo $avg_surface; ?> m²</span>
                    </div>
                </div>

                <!-- FILTRES DE RECHERCHE -->
                <div class="admin-filters-card">
                    <h3>Filtrer les demandes</h3>
                    <form action="admin.php" method="GET" class="filters-form">
                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="search">Rechercher (Nom, Ville...)</label>
                                <input type="text" id="search" name="search" placeholder="Ex: Jean, Paris..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="status">Statut</label>
                                <select id="status" name="status">
                                    <option value="">Tous les statuts</option>
                                    <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="en_cours" <?php echo $status_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                    <option value="traite" <?php echo $status_filter === 'traite' ? 'selected' : ''; ?>>Traité</option>
                                    <option value="rejete" <?php echo $status_filter === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="project_type">Type de projet</label>
                                <select id="project_type" name="project_type">
                                    <option value="">Tous les types</option>
                                    <?php foreach ($project_labels as $key => $val): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $project_filter === $key ? 'selected' : ''; ?>><?php echo $val; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filters-buttons" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; width: 100%;">
                            <button type="submit" class="btn btn-secondary">Appliquer les filtres</button>
                            <a href="admin.php" class="btn btn-text">Réinitialiser</a>
                        </div>
                    </form>
                </div>

                <!-- LISTE DES DEMANDES DE DEVIS -->
                <div class="admin-table-card">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Contact</th>
                                    <th>Lieu</th>
                                    <th>Projet</th>
                                    <th>Surface</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th class="actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($devis_list)): ?>
                                    <tr>
                                        <td colspan="9" class="empty-table">Aucune demande de devis ne correspond aux critères.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($devis_list as $devis): ?>
                                        <tr class="status-row-<?php echo $devis['status']; ?>">
                                            <td>#<?php echo $devis['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($devis['lastname'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php echo htmlspecialchars($devis['firstname'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($devis['email'], ENT_QUOTES, 'UTF-8'); ?>" title="Lui envoyer un mail"><?php echo htmlspecialchars($devis['email'], ENT_QUOTES, 'UTF-8'); ?></a><br>
                                                <a href="tel:<?php echo htmlspecialchars($devis['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($devis['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                                            </td>
                                            <td><?php echo htmlspecialchars($devis['location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="project-badge">
                                                    <?php echo isset($project_labels[$devis['project_type']]) ? $project_labels[$devis['project_type']] : $devis['project_type']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $devis['surface']; ?> m²</td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($devis['created_at'])); ?></td>
                                            <td>
                                                <form action="admin.php" method="POST" class="inline-status-form">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="devis_id" value="<?php echo $devis['id']; ?>">
                                                    <select name="new_status" onchange="this.form.submit()" class="status-select select-status-<?php echo $devis['status']; ?>">
                                                        <option value="en_attente" <?php echo $devis['status'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                                        <option value="en_cours" <?php echo $devis['status'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                                        <option value="traite" <?php echo $devis['status'] === 'traite' ? 'selected' : ''; ?>>Traité</option>
                                                        <option value="rejete" <?php echo $devis['status'] === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                                                    </select>
                                                    <input type="hidden" name="action_status" value="1">
                                                </form>
                                            </td>
                                            <td class="actions-cell">
                                                <!-- Bouton de détail -->
                                                <button type="button" class="btn btn-secondary btn-table btn-detail" 
                                                        data-id="<?php echo $devis['id']; ?>"
                                                        data-client="<?php echo htmlspecialchars($devis['firstname'] . ' ' . $devis['lastname'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-email="<?php echo htmlspecialchars($devis['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-phone="<?php echo htmlspecialchars($devis['phone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-location="<?php echo htmlspecialchars($devis['location'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-project="<?php echo isset($project_labels[$devis['project_type']]) ? $project_labels[$devis['project_type']] : $devis['project_type']; ?>"
                                                        data-surface="<?php echo $devis['surface']; ?>"
                                                        data-date="<?php echo date('d/m/Y à H:i', strtotime($devis['created_at'])); ?>"
                                                        data-message="<?php echo htmlspecialchars($devis['message'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-status="<?php echo $devis['status']; ?>">
                                                    Détails
                                                </button>
                                                <!-- Formulaire de suppression -->
                                                <form action="admin.php" method="POST" class="inline-delete-form" onsubmit="return confirm('Confirmer la suppression de cette demande de devis ? Cette action est irréversible.')">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="devis_id" value="<?php echo $devis['id']; ?>">
                                                    <input type="hidden" name="action_delete" value="1">
                                                    <button type="submit" class="btn btn-primary btn-table btn-danger">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container" style="display: flex; justify-content: center; align-items: center; padding: 1.5rem 0; border-top: 1px solid var(--secondary-dark); gap: 0.5rem; margin-top: 1rem;">
                            <!-- Page précédente -->
                            <?php if ($page > 1): ?>
                                <a href="admin.php?p=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&project_type=<?php echo urlencode($project_filter); ?>" class="btn btn-secondary btn-pagination" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center;">&laquo; Précédent</a>
                            <?php else: ?>
                                <span class="btn btn-secondary btn-pagination disabled" style="opacity: 0.5; cursor: not-allowed; padding: 0.4rem 0.8rem; font-size: 0.85rem; display: inline-flex; align-items: center;">&laquo; Précédent</span>
                            <?php endif; ?>

                            <!-- Numéros de page -->
                            <div class="pagination-numbers" style="display: flex; gap: 0.3rem;">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i === $page): ?>
                                        <span class="btn btn-primary btn-pagination active" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background-color: var(--primary-color); border-color: var(--primary-color); color: #fff; cursor: default; pointer-events: none; font-weight: bold;"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="admin.php?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&project_type=<?php echo urlencode($project_filter); ?>" class="btn btn-secondary btn-pagination" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none;"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>

                            <!-- Page suivante -->
                            <?php if ($page < $total_pages): ?>
                                <a href="admin.php?p=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&project_type=<?php echo urlencode($project_filter); ?>" class="btn btn-secondary btn-pagination" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center;">Suivant &raquo;</a>
                            <?php else: ?>
                                <span class="btn btn-secondary btn-pagination disabled" style="opacity: 0.5; cursor: not-allowed; padding: 0.4rem 0.8rem; font-size: 0.85rem; display: inline-flex; align-items: center;">Suivant &raquo;</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    </div> <!-- Fin Onglet 1 : Devis -->

                    <!-- Onglet Visibilité (Stats) -->
                    <div id="visibilite" class="admin-tab-content">
                        <div class="admin-filters-card" style="margin-top: 0; margin-bottom: 2rem;">
                            <div class="admin-card-header">
                                <h3>Fiche Visibilité (Statistiques du site)</h3>
                            </div>

                            <!-- Tableau journalier -->
                            <div class="table-responsive" style="margin-top: 1rem;">
                                <table class="devis-table analytics-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Nombre de visites</th>
                                            <th>Visiteurs différents</th>
                                            <th>Nombres de pages</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($analytics_logs)): ?>
                                            <tr><td colspan="4" style="text-align:center; padding: 2rem; color: var(--text-muted);">Aucune donnée de visite enregistrée pour le moment.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($analytics_logs as $log): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($log['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo (int)$log['visits']; ?></td>
                                                    <td><?php echo (int)$log['unique_visitors']; ?></td>
                                                    <td><?php echo (int)$log['page_views']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="analytics-total-row">
                                                <td><strong>Total</strong></td>
                                                <td><strong><?php echo $page_total_visits; ?></strong></td>
                                                <td><strong><?php echo $page_total_visitors; ?></strong></td>
                                                <td><strong><?php echo $page_total_views; ?></strong></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Stats -->
                            <?php if ($total_stat_pages > 1): ?>
                                <div class="pagination-controls" style="margin-top: 1.5rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php if ($stat_page > 1): ?>
                                        <a href="admin.php?sp=<?php echo $stat_page - 1; ?>#visibilite" class="btn btn-secondary btn-pagination" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none;">&laquo; Précédent</a>
                                    <?php endif; ?>

                                    <div style="display: flex; gap: 0.3rem;">
                                        <?php for ($sp_i = 1; $sp_i <= $total_stat_pages; $sp_i++): ?>
                                            <?php if ($sp_i === $stat_page): ?>
                                                <span class="btn btn-primary btn-pagination" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; cursor: default; pointer-events: none; font-weight: bold;"><?php echo $sp_i; ?></span>
                                            <?php else: ?>
                                                <a href="admin.php?sp=<?php echo $sp_i; ?>#visibilite" class="btn btn-secondary btn-pagination" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none;"><?php echo $sp_i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>

                                    <?php if ($stat_page < $total_stat_pages): ?>
                                        <a href="admin.php?sp=<?php echo $stat_page + 1; ?>#visibilite" class="btn btn-secondary btn-pagination" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none;">suivante &raquo;</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Résumé mensuel glissant (12 derniers mois) -->
                        <div class="admin-filters-card" style="margin-top: 0;">
                            <div class="admin-card-header">
                                <h3>Nombre de visites</h3>
                            </div>
                            <div class="table-responsive" style="margin-top: 1rem;">
                                <table class="devis-table analytics-table analytics-monthly-table">
                                    <thead>
                                        <tr>
                                            <?php
                                            $current_month_key = date('Y-m');
                                            foreach ($rolling_months as $rm): ?>
                                                <th class="<?php echo ($rm['key'] === $current_month_key) ? 'current-month' : ''; ?>"><?php echo htmlspecialchars($rm['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <?php foreach ($rolling_months as $rm): ?>
                                                <td class="<?php echo ($rm['key'] === $current_month_key) ? 'current-month' : ''; ?>"><?php echo isset($monthly_data[$rm['key']]) ? (int)$monthly_data[$rm['key']] : 0; ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div> <!-- Fin Onglet Visibilité -->

                    <!-- Onglet 2 : Coordonnées & Horaires & MDP -->
                    <div id="entreprise" class="admin-tab-content">
                        <div class="admin-filters-card" style="margin-top: 0; margin-bottom: 2.5rem;">
                            <div class="admin-card-header">
                                <h3>Coordonnées & Horaires de l'entreprise</h3>
                            </div>
                            <form action="admin.php" method="POST" class="settings-form" style="display: block;">
                                <?php echo csrf_field(); ?>
                                <div class="form-row-grid">
                                    <div class="form-group" style="grid-column: 1/-1;">
                                        <label for="company_name">Nom de l'entreprise *</label>
                                        <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row-grid">
                                    <div class="form-group">
                                        <label for="phone">Téléphone *</label>
                                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">E-mail de Contact *</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 1.5rem;">
                                    <label for="address">Adresse (Rue) *</label>
                                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($company['address'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="form-row-grid">
                                    <div class="form-group">
                                        <label for="city">Ville *</label>
                                        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($company['city'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="postal_code">Code Postal *</label>
                                        <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($company['postal_code'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row-grid">
                                    <div class="form-group">
                                        <label for="hours_week">Horaires de la semaine (Lun-Ven) *</label>
                                        <input type="text" id="hours_week" name="hours_week" value="<?php echo htmlspecialchars($company['hours_week'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="hours_sat">Horaires du samedi *</label>
                                        <input type="text" id="hours_sat" name="hours_sat" value="<?php echo htmlspecialchars($company['hours_sat'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row-grid">
                                    <div class="form-group">
                                        <label for="radius_km">Rayon d'intervention (km) *</label>
                                        <input type="number" id="radius_km" name="radius_km" value="<?php echo (int)$company['radius_km']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="latitude">Coordonnées GPS - Latitude *</label>
                                        <input type="text" id="latitude" name="latitude" value="<?php echo (float)$company['latitude']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="longitude">Coordonnées GPS - Longitude *</label>
                                        <input type="text" id="longitude" name="longitude" value="<?php echo (float)$company['longitude']; ?>" required>
                                    </div>
                                    <div class="form-group" style="grid-column: 1/-1;">
                                        <label for="facebook_url">Lien de la page Facebook *</label>
                                        <input type="url" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars($company['facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>
                                <button type="submit" name="action_update_settings" class="btn btn-primary">Sauvegarder les modifications</button>
                            </form>
                        </div>
                    </div> <!-- Fin Onglet 2 : Entreprise -->

                    <!-- Onglet 2.5 : Sécurité & Mot de passe -->
                    <div id="securite" class="admin-tab-content">
                        <!-- SECTION CONFIGURATION MOT DE PASSE -->
                        <div class="admin-filters-card" style="margin-top: 0;">
                            <div class="admin-card-header">
                                <h3>Modifier mon mot de passe d'accès</h3>
                            </div>
                            <p style="margin-bottom: 1.5rem; color: var(--text-muted);">Pour garantir la sécurité de votre espace, modifiez régulièrement votre mot de passe d'accès.</p>
                            
                            <!-- Avertissement si le mot de passe est celui par défaut -->
                            <?php 
                            try {
                                $stmt = $pdo->prepare("SELECT password FROM `admins` WHERE id = ?");
                                $stmt->execute([$_SESSION['admin_id']]);
                                $currentPwdHash = $stmt->fetchColumn();
                                if (password_verify(ADMIN_DEFAULT_PASSWORD, $currentPwdHash)):
                            ?>
                                <div class="alert alert-warning" style="margin-bottom: 2rem;">
                                    <p><strong>ATTENTION :</strong> Vous utilisez toujours le mot de passe par défaut. Pour des raisons de sécurité, veuillez le modifier immédiatement.</p>
                                </div>
                            <?php 
                                endif;
                            } catch (PDOException $e) {}
                            ?>

                            <form action="admin.php" method="POST" class="settings-form" style="display: block;">
                                <?php echo csrf_field(); ?>
                                <div class="form-row-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label for="old_password">Ancien mot de passe *</label>
                                        <input type="password" id="old_password" name="old_password" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new_password">Nouveau mot de passe *</label>
                                        <input type="password" id="new_password" name="new_password" required placeholder="Min 8 caractères">
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Confirmer le nouveau MDP *</label>
                                        <input type="password" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <div style="text-align: center; margin-top: 2rem;">
                                    <button type="submit" name="password_submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem;">💾 Modifier mon mot de passe</button>
                                </div>
                            </form>
                        </div>
                    </div> <!-- Fin Onglet Sécurité -->

                    <!-- Onglet 3 : Gestion des Prestations -->
                    <div id="prestations" class="admin-tab-content">
                        <?php if ($edit_service_data): ?>
                            <!-- FORMULAIRE DE MODIFICATION -->
                            <div class="admin-filters-card" style="margin-top: 0;">
                                <div class="admin-card-header">
                                    <h3>Modifier la prestation : <?php echo htmlspecialchars($edit_service_data['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <a href="admin.php#prestations" class="btn btn-secondary">Retour à la liste</a>
                                </div>
                                <form action="admin.php" method="POST" enctype="multipart/form-data" class="settings-form" style="display: block;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="service_id" value="<?php echo $edit_service_data['id']; ?>">
                                    <div class="form-row-grid">
                                        <div class="form-group">
                                            <label for="srv_title_edit">Nom de la prestation *</label>
                                            <input type="text" id="srv_title_edit" name="title" value="<?php echo htmlspecialchars($edit_service_data['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="srv_anchor_edit">Ancre HTML unique (Ex: interieur, sols...) *</label>
                                            <input type="text" id="srv_anchor_edit" name="anchor" value="<?php echo htmlspecialchars($edit_service_data['anchor'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="srv_description_edit">Description détaillée *</label>
                                        <textarea id="srv_description_edit" name="description" rows="4" style="padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars($edit_service_data['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="srv_features_edit">Points clés / Tarifs (Un par ligne) *</label>
                                        <textarea id="srv_features_edit" name="features" rows="5" placeholder="Ex: Finition mate : Masque les défauts" style="padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;"><?php echo htmlspecialchars($edit_service_data['features'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Image d'illustration (Optionnel)</label>
                                        <label class="custom-file-upload">
                                            <input type="file" name="service_image" class="input-file-hidden">
                                             Choisir une nouvelle photo...
                                        </label>
                                        <div class="image-preview-box">
                                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem;">Image actuelle :</p>
                                            <img src="<?php echo htmlspecialchars($edit_service_data['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                        </div>
                                    </div>
                                    <button type="submit" name="action_edit_service" class="btn btn-primary" style="margin-top: 1.5rem;">Sauvegarder les modifications</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- LISTE DES PRESTATIONS -->
                            <div class="admin-table-card" style="margin-top: 0; margin-bottom: 2.5rem;">
                                <div class="admin-card-header" style="padding: 1.5rem 1.5rem 0;">
                                    <h3>Prestations actuelles</h3>
                                </div>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Image</th>
                                                <th>Nom</th>
                                                <th>Ancre</th>
                                                <th>Description</th>
                                                <th class="actions-col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($services_all as $srv): ?>
                                                <tr>
                                                    <td>#<?php echo $srv['id']; ?></td>
                                                    <td>
                                                        <img src="<?php echo htmlspecialchars($srv['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                    </td>
                                                    <td><strong><?php echo htmlspecialchars($srv['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                                    <td><code>#<?php echo htmlspecialchars($srv['anchor'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                                    <td><p style="font-size: 0.85rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;"><?php echo htmlspecialchars($srv['description'], ENT_QUOTES, 'UTF-8'); ?></p></td>
                                                    <td class="actions-cell">
                                                        <a href="admin.php?edit_service=<?php echo $srv['id']; ?>#prestations" class="btn btn-secondary btn-table btn-table-icon">✏️ Modifier</a>
                                                        <form action="admin.php" method="POST" class="inline-delete-form" onsubmit="return confirm('Confirmer la suppression de cette prestation ? Cette action est irréversible.')">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="service_id" value="<?php echo $srv['id']; ?>">
                                                            <input type="hidden" name="action_delete_service" value="1">
                                                            <button type="submit" class="btn btn-primary btn-table btn-danger btn-table-icon">🗑️ Supprimer</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- FORMULAIRE D'AJOUT -->
                            <div class="admin-filters-card">
                                <div class="admin-card-header">
                                    <h3>Ajouter une prestation</h3>
                                </div>
                                <form action="admin.php" method="POST" enctype="multipart/form-data" class="settings-form" style="display: block;">
                                    <?php echo csrf_field(); ?>
                                    <div class="form-row-grid">
                                        <div class="form-group">
                                            <label for="srv_title">Nom de la prestation *</label>
                                            <input type="text" id="srv_title" name="title" required placeholder="Ex: Revêtements Extérieurs">
                                        </div>
                                        <div class="form-group">
                                            <label for="srv_anchor">Ancre HTML unique (Ex: ravalement, sols...) *</label>
                                            <input type="text" id="srv_anchor" name="anchor" required placeholder="Ex: ravalement">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="srv_description">Description détaillée *</label>
                                        <textarea id="srv_description" name="description" rows="4" style="padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required placeholder="Entrez la description de ce service..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="srv_features">Points clés / Tarifs (Un par ligne) *</label>
                                        <textarea id="srv_features" name="features" rows="5" placeholder="Option 1 : Description de l'option 1&#10;Option 2 : Description de l'option 2" style="padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Image d'illustration *</label>
                                        <label class="custom-file-upload">
                                            <input type="file" name="service_image" class="input-file-hidden" required>
                                            📁 Sélectionner une image...
                                        </label>
                                    </div>
                                    <button type="submit" name="action_add_service" class="btn btn-primary btn-sm-success" style="margin-top: 1.5rem;">➕ Créer la prestation</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div> <!-- Fin Onglet 3 : Prestations -->

                    <!-- Onglet 4 : Gestion des Réalisations -->
                    <div id="realisations" class="admin-tab-content">
                        <?php if ($edit_project_data): ?>
                            <!-- FORMULAIRE DE MODIFICATION -->
                            <div class="admin-filters-card" style="margin-top: 0;">
                                <div class="admin-card-header">
                                    <h3>Modifier le chantier : <?php echo htmlspecialchars($edit_project_data['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <a href="admin.php#realisations" class="btn btn-secondary">Retour à la liste</a>
                                </div>
                                <form action="admin.php" method="POST" enctype="multipart/form-data" class="settings-form" style="display: block;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="project_id" value="<?php echo $edit_project_data['id']; ?>">
                                    <div class="form-row-grid">
                                        <div class="form-group">
                                            <label for="proj_title_edit">Nom du projet *</label>
                                            <input type="text" id="proj_title_edit" name="title" value="<?php echo htmlspecialchars($edit_project_data['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="proj_cat_edit">Catégorie *</label>
                                            <select id="proj_cat_edit" name="category" required>
                                                <option value="interieur" <?php echo $edit_project_data['category'] === 'interieur' ? 'selected' : ''; ?>>Peinture Intérieure</option>
                                                <option value="exterieur" <?php echo $edit_project_data['category'] === 'exterieur' ? 'selected' : ''; ?>>Peinture Extérieure</option>
                                                <option value="decor" <?php echo $edit_project_data['category'] === 'decor' ? 'selected' : ''; ?>>Décoration & Revêtements</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row-grid">
                                        <div class="form-group" style="grid-column: 1/-1;">
                                            <label for="proj_tags_edit">Étiquettes descriptives (Ex: Peinture Extérieure / Volets) *</label>
                                            <input type="text" id="proj_tags_edit" name="tags" value="<?php echo htmlspecialchars($edit_project_data['tags'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="proj_desc_edit">Description détaillée *</label>
                                        <textarea id="proj_desc_edit" name="description" rows="4" style="padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars($edit_project_data['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Photo du chantier (Optionnel)</label>
                                        <label class="custom-file-upload">
                                            <input type="file" name="project_image" class="input-file-hidden">
                                            Choisir une nouvelle photo...
                                        </label>
                                        <div class="image-preview-box">
                                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem;">Photo actuelle :</p>
                                            <img src="<?php echo htmlspecialchars($edit_project_data['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                        </div>
                                    </div>
                                    <button type="submit" name="action_edit_project" class="btn btn-primary" style="margin-top: 1.5rem;">Sauvegarder les modifications</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- LISTE DES RÉALISATIONS -->
                            <div class="admin-table-card" style="margin-top: 0; margin-bottom: 2.5rem;">
                                <div class="admin-card-header" style="padding: 1.5rem 1.5rem 0;">
                                    <h3>Chantiers dans la galerie</h3>
                                </div>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Photo</th>
                                                <th>Titre du Projet</th>
                                                <th>Catégorie</th>
                                                <th>Étiquettes</th>
                                                <th class="actions-col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($projects_all)): ?>
                                                <tr>
                                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem 0;">Aucune réalisation enregistrée.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($projects_all as $proj): ?>
                                                    <tr>
                                                        <td>#<?php echo $proj['id']; ?></td>
                                                        <td>
                                                            <img src="<?php echo htmlspecialchars($proj['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                        </td>
                                                        <td><strong><?php echo htmlspecialchars($proj['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                                        <td>
                                                            <?php 
                                                            if ($proj['category'] === 'interieur') echo 'Peinture Intérieure';
                                                            elseif ($proj['category'] === 'exterieur') echo 'Peinture Extérieure';
                                                            elseif ($proj['category'] === 'decor') echo 'Décoration & Revêtements';
                                                            ?>
                                                        </td>
                                                        <td><span class="project-badge"><?php echo htmlspecialchars($proj['tags'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                        <td class="actions-cell">
                                                            <a href="admin.php?edit_project=<?php echo $proj['id']; ?>#realisations" class="btn btn-secondary btn-table btn-table-icon">✏️ Modifier</a>
                                                            <form action="admin.php" method="POST" class="inline-delete-form" onsubmit="return confirm('Confirmer la suppression de cette réalisation ? Cette action est irréversible.')">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="project_id" value="<?php echo $proj['id']; ?>">
                                                                <input type="hidden" name="action_delete_project" value="1">
                                                                <button type="submit" class="btn btn-primary btn-table btn-danger btn-table-icon">🗑️ Supprimer</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- FORMULAIRE D'AJOUT -->
                            <div class="admin-filters-card">
                                <div class="admin-card-header">
                                    <h3>Ajouter un chantier à la galerie</h3>
                                </div>
                                <form action="admin.php" method="POST" enctype="multipart/form-data" class="settings-form" style="display: block;">
                                    <?php echo csrf_field(); ?>
                                    <div class="form-row-grid">
                                        <div class="form-group">
                                            <label for="proj_title">Titre du chantier *</label>
                                            <input type="text" id="proj_title" name="title" required placeholder="Ex: Ravalement Villa d'Exception">
                                        </div>
                                        <div class="form-group">
                                            <label for="proj_category">Catégorie de travaux *</label>
                                            <select id="proj_category" name="category" required>
                                                <option value="interieur">Peinture Intérieure</option>
                                                <option value="exterieur">Peinture Extérieure</option>
                                                <option value="decor">Décoration & Revêtements</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row-grid">
                                        <div class="form-group" style="grid-column: 1/-1;">
                                            <label for="proj_tags">Étiquettes descriptives (Ex: Ravalement / Façade) *</label>
                                            <input type="text" id="proj_tags" name="tags" required placeholder="Ex: Peinture Extérieure / Façade">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="proj_description">Description du chantier *</label>
                                        <textarea id="proj_description" name="description" rows="4" style="padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required placeholder="Décrivez les travaux réalisés..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Photo du chantier *</label>
                                        <label class="custom-file-upload">
                                            <input type="file" name="project_image" class="input-file-hidden" required>
                                            📁 Sélectionner une photo...
                                        </label>
                                    </div>
                                    <button type="submit" name="action_add_project" class="btn btn-primary btn-sm-success" style="margin-top: 1.5rem;">➕ Publier dans la galerie</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div> <!-- Fin Onglet 4 : Réalisations -->

                    <!-- Onglet 5 : Contenus des Pages (CMS Organisé) -->
                    <div id="contenus" class="admin-tab-content">
                        <div class="cms-pages-layout">
                            
                            <!-- Menu latéral de sélection de page (similaire aux captures) -->
                            <aside class="cms-pages-sidebar">
                                <h4>Pages modifiables</h4>
                                <ul>
                                    <li>
                                        <button type="button" class="cms-page-link active" data-subtab="page-accueil">
                                            <span class="page-icon">🏠</span>
                                            Page d'Accueil
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="cms-page-link" data-subtab="page-propos">
                                            <span class="page-icon">🏢</span>
                                            Page À Propos
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="cms-page-link" data-subtab="page-prestations">
                                            <span class="page-icon">🛠️</span>
                                            Page Prestations
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="cms-page-link" data-subtab="page-realisations">
                                            <span class="page-icon">📸</span>
                                            Page Réalisations
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="cms-page-link" data-subtab="page-contact">
                                            <span class="page-icon">✉️</span>
                                            Page Contact & Devis
                                        </button>
                                    </li>
                                </ul>
                            </aside>

                            <!-- Formulaires de modification des pages -->
                            <div class="cms-pages-forms">
                                <form action="admin.php" method="POST" class="settings-form" style="display: block;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_update_texts" value="1">
                                    <input type="hidden" name="tab_active" value="contenus">
                                    
                                    <!-- SOUS-ONGLET 1 : PAGE D'ACCUEIL -->
                                    <div id="page-accueil" class="cms-subtab-content active">
                                        <div class="admin-filters-card" style="margin-top: 0; margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                                            <div class="admin-card-header">
                                                <h3>Page d'Accueil (Textes & Paragraphes)</h3>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_home_hero_title">Titre principal du Héro *</label>
                                                        <input type="text" id="txt_home_hero_title" name="texts[home_hero_title]" value="<?php echo htmlspecialchars(get_site_text('home_hero_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_home_hero_desc">Description du Héro (Sous le titre) *</label>
                                                    <textarea id="txt_home_hero_desc" name="texts[home_hero_desc]" rows="2" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('home_hero_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_home_intro_title">Titre d'introduction *</label>
                                                        <input type="text" id="txt_home_intro_title" name="texts[home_intro_title]" value="<?php echo htmlspecialchars(get_site_text('home_intro_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_home_intro_desc">Description d'introduction *</label>
                                                    <textarea id="txt_home_intro_desc" name="texts[home_intro_desc]" rows="4" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('home_intro_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                
                                                <h4 style="margin-top: 1rem; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Nos Engagements</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_home_commitments_title">Titre global de la section engagements *</label>
                                                        <input type="text" id="txt_home_commitments_title" name="texts[home_commitments_title]" value="<?php echo htmlspecialchars(get_site_text('home_commitments_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-row-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                                    <div class="form-group">
                                                        <label for="txt_home_commitment_1_title">Engagement 1 - Titre *</label>
                                                        <input type="text" id="txt_home_commitment_1_title" name="texts[home_commitment_1_title]" value="<?php echo htmlspecialchars(get_site_text('home_commitment_1_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_home_commitment_1_desc" style="margin-top: 0.5rem;">Engagement 1 - Description *</label>
                                                        <textarea id="txt_home_commitment_1_desc" name="texts[home_commitment_1_desc]" rows="2" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('home_commitment_1_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="txt_home_commitment_2_title">Engagement 2 - Titre *</label>
                                                        <input type="text" id="txt_home_commitment_2_title" name="texts[home_commitment_2_title]" value="<?php echo htmlspecialchars(get_site_text('home_commitment_2_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_home_commitment_2_desc" style="margin-top: 0.5rem;">Engagement 2 - Description *</label>
                                                        <textarea id="txt_home_commitment_2_desc" name="texts[home_commitment_2_desc]" rows="2" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('home_commitment_2_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_home_commitment_3_title">Engagement 3 - Titre *</label>
                                                        <input type="text" id="txt_home_commitment_3_title" name="texts[home_commitment_3_title]" value="<?php echo htmlspecialchars(get_site_text('home_commitment_3_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_home_commitment_3_desc" style="margin-top: 0.5rem;">Engagement 3 - Description *</label>
                                                        <textarea id="txt_home_commitment_3_desc" name="texts[home_commitment_3_desc]" rows="2" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('home_commitment_3_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <h4 style="margin-top: 1rem; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Témoignages Clients</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_home_testimonials_title">Titre global de la section témoignages *</label>
                                                        <input type="text" id="txt_home_testimonials_title" name="texts[home_testimonials_title]" value="<?php echo htmlspecialchars(get_site_text('home_testimonials_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-row-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                                    <div class="form-group">
                                                        <label for="txt_home_testimonial_1_author">Témoignage 1 - Client (Auteur) *</label>
                                                        <input type="text" id="txt_home_testimonial_1_author" name="texts[home_testimonial_1_author]" value="<?php echo htmlspecialchars(get_site_text('home_testimonial_1_author'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_home_testimonial_1_quote" style="margin-top: 0.5rem;">Témoignage 1 - Message/Citation *</label>
                                                        <textarea id="txt_home_testimonial_1_quote" name="texts[home_testimonial_1_quote]" rows="3" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('home_testimonial_1_quote'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="txt_home_testimonial_2_author">Témoignage 2 - Client (Auteur) *</label>
                                                        <input type="text" id="txt_home_testimonial_2_author" name="texts[home_testimonial_2_author]" value="<?php echo htmlspecialchars(get_site_text('home_testimonial_2_author'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_home_testimonial_2_quote" style="margin-top: 0.5rem;">Témoignage 2 - Message/Citation *</label>
                                                        <textarea id="txt_home_testimonial_2_quote" name="texts[home_testimonial_2_quote]" rows="3" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('home_testimonial_2_quote'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- SOUS-ONGLET 2 : PAGE À PROPOS -->
                                    <div id="page-propos" class="cms-subtab-content">
                                        <div class="admin-filters-card" style="margin-top: 0; margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                                            <div class="admin-card-header">
                                                <h3>Page À Propos (Qui Sommes-Nous)</h3>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                                <div class="form-row-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                                    <div class="form-group">
                                                        <label for="txt_about_banner_title">Titre de la bannière *</label>
                                                        <input type="text" id="txt_about_banner_title" name="texts[about_banner_title]" value="<?php echo htmlspecialchars(get_site_text('about_banner_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="txt_about_banner_desc">Description de la bannière *</label>
                                                        <input type="text" id="txt_about_banner_desc" name="texts[about_banner_desc]" value="<?php echo htmlspecialchars(get_site_text('about_banner_desc'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_about_intro_title">Titre principal du mot de l'artisan *</label>
                                                        <input type="text" id="txt_about_intro_title" name="texts[about_intro_title]" value="<?php echo htmlspecialchars(get_site_text('about_intro_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_about_intro_p1">Mot de l'artisan - Paragraphe 1 *</label>
                                                    <textarea id="txt_about_intro_p1" name="texts[about_intro_p1]" rows="3" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_intro_p1'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_about_intro_p2">Mot de l'artisan - Paragraphe 2 *</label>
                                                    <textarea id="txt_about_intro_p2" name="texts[about_intro_p2]" rows="3" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_intro_p2'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_about_intro_p3">Mot de l'artisan - Paragraphe 3 *</label>
                                                    <textarea id="txt_about_intro_p3" name="texts[about_intro_p3]" rows="3" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_intro_p3'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                
                                                <h4 style="margin-top: 1rem; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Nos Engagements au Quotidien</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_about_values_title">Titre de la section engagements *</label>
                                                        <input type="text" id="txt_about_values_title" name="texts[about_values_title]" value="<?php echo htmlspecialchars(get_site_text('about_values_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-row-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                                    <div class="form-group">
                                                        <label for="txt_about_val_1_title">Engagement 1 - Titre *</label>
                                                        <input type="text" id="txt_about_val_1_title" name="texts[about_val_1_title]" value="<?php echo htmlspecialchars(get_site_text('about_val_1_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_about_val_1_desc" style="margin-top: 0.5rem;">Engagement 1 - Description *</label>
                                                        <textarea id="txt_about_val_1_desc" name="texts[about_val_1_desc]" rows="3" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_val_1_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="txt_about_val_2_title">Engagement 2 - Titre *</label>
                                                        <input type="text" id="txt_about_val_2_title" name="texts[about_val_2_title]" value="<?php echo htmlspecialchars(get_site_text('about_val_2_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_about_val_2_desc" style="margin-top: 0.5rem;">Engagement 2 - Description *</label>
                                                        <textarea id="txt_about_val_2_desc" name="texts[about_val_2_desc]" rows="3" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_val_2_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_about_val_3_title">Engagement 3 - Titre *</label>
                                                        <input type="text" id="txt_about_val_3_title" name="texts[about_val_3_title]" value="<?php echo htmlspecialchars(get_site_text('about_val_3_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_about_val_3_desc" style="margin-top: 0.5rem;">Engagement 3 - Description *</label>
                                                        <textarea id="txt_about_val_3_desc" name="texts[about_val_3_desc]" rows="3" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_val_3_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <h4 style="margin-top: 1rem; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Qualifications & Garanties</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_about_qualif_title">Titre de la section qualifications *</label>
                                                        <input type="text" id="txt_about_qualif_title" name="texts[about_qualif_title]" value="<?php echo htmlspecialchars(get_site_text('about_qualif_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-row-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                                    <div class="form-group">
                                                        <label for="txt_about_qualif_1_title">Garantie 1 - Titre *</label>
                                                        <input type="text" id="txt_about_qualif_1_title" name="texts[about_qualif_1_title]" value="<?php echo htmlspecialchars(get_site_text('about_qualif_1_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_about_qualif_1_desc" style="margin-top: 0.5rem;">Garantie 1 - Description *</label>
                                                        <textarea id="txt_about_qualif_1_desc" name="texts[about_qualif_1_desc]" rows="3" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_qualif_1_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="txt_about_qualif_2_title">Garantie 2 - Titre *</label>
                                                        <input type="text" id="txt_about_qualif_2_title" name="texts[about_qualif_2_title]" value="<?php echo htmlspecialchars(get_site_text('about_qualif_2_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                        <label for="txt_about_qualif_2_desc" style="margin-top: 0.5rem;">Garantie 2 - Description *</label>
                                                        <textarea id="txt_about_qualif_2_desc" name="texts[about_qualif_2_desc]" rows="3" style="width: 100%; padding: 0.6rem; font-family: inherit; font-size: 0.9rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('about_qualif_2_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    

                                    <!-- SOUS-ONGLET 3 : PAGE PRESTATIONS -->
                                    <div id="page-prestations" class="cms-subtab-content">
                                        <div class="admin-filters-card" style="margin-top: 0; margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                                            <div class="admin-card-header">
                                                <h3>Page Prestations (Bannière &amp; CTA)</h3>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                                <h4 style="margin-top: 0; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Bannière en-tête de page</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_prestations_banner_title">Titre de la page (H1) *</label>
                                                        <input type="text" id="txt_prestations_banner_title" name="texts[prestations_banner_title]" value="<?php echo htmlspecialchars(get_site_text('prestations_banner_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_prestations_banner_desc">Sous-titre de la bannière *</label>
                                                    <textarea id="txt_prestations_banner_desc" name="texts[prestations_banner_desc]" rows="2" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('prestations_banner_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                <h4 style="margin-top: 0.5rem; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Section Appel à l'action (bas de page)</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_prestations_cta_title">Titre du CTA *</label>
                                                        <input type="text" id="txt_prestations_cta_title" name="texts[prestations_cta_title]" value="<?php echo htmlspecialchars(get_site_text('prestations_cta_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_prestations_cta_desc">Description du CTA *</label>
                                                    <textarea id="txt_prestations_cta_desc" name="texts[prestations_cta_desc]" rows="2" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('prestations_cta_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SOUS-ONGLET 4 : PAGE RÉALISATIONS -->
                                    <div id="page-realisations" class="cms-subtab-content">
                                        <div class="admin-filters-card" style="margin-top: 0; margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                                            <div class="admin-card-header">
                                                <h3>Page Réalisations (Bannière)</h3>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                                <h4 style="margin-top: 0; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Bannière en-tête de page</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_realisations_banner_title">Titre de la page (H1) *</label>
                                                        <input type="text" id="txt_realisations_banner_title" name="texts[realisations_banner_title]" value="<?php echo htmlspecialchars(get_site_text('realisations_banner_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_realisations_banner_desc">Sous-titre de la bannière *</label>
                                                    <textarea id="txt_realisations_banner_desc" name="texts[realisations_banner_desc]" rows="2" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('realisations_banner_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SOUS-ONGLET 5 : PAGE CONTACT -->
                                    <div id="page-contact" class="cms-subtab-content">
                                        <div class="admin-filters-card" style="margin-top: 0; margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                                            <div class="admin-card-header">
                                                <h3>Page Contact &amp; Devis (Bannière &amp; Textes)</h3>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                                <h4 style="margin-top: 0; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Bannière en-tête de page</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_contact_banner_title">Titre de la page (H1) *</label>
                                                        <input type="text" id="txt_contact_banner_title" name="texts[contact_banner_title]" value="<?php echo htmlspecialchars(get_site_text('contact_banner_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_contact_banner_desc">Sous-titre de la bannière *</label>
                                                    <textarea id="txt_contact_banner_desc" name="texts[contact_banner_desc]" rows="2" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('contact_banner_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                <h4 style="margin-top: 0.5rem; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Bloc Coordonnées</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_contact_intro_title">Titre du bloc coordonnées *</label>
                                                        <input type="text" id="txt_contact_intro_title" name="texts[contact_intro_title]" value="<?php echo htmlspecialchars(get_site_text('contact_intro_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_contact_intro_desc">Description sous le titre coordonnées *</label>
                                                    <textarea id="txt_contact_intro_desc" name="texts[contact_intro_desc]" rows="2" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('contact_intro_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                <h4 style="margin-top: 0.5rem; border-bottom: 1px dashed var(--secondary-dark); padding-bottom: 0.5rem; color: var(--accent-color);">Formulaire de Devis</h4>
                                                <div class="form-row-grid">
                                                    <div class="form-group" style="grid-column: 1/-1;">
                                                        <label for="txt_contact_form_title">Titre du formulaire *</label>
                                                        <input type="text" id="txt_contact_form_title" name="texts[contact_form_title]" value="<?php echo htmlspecialchars(get_site_text('contact_form_title'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="txt_contact_form_desc">Description du formulaire *</label>
                                                    <textarea id="txt_contact_form_desc" name="texts[contact_form_desc]" rows="2" style="width: 100%; padding: 0.8rem; font-family: inherit; font-size: 0.95rem; border: 1px solid var(--secondary-dark); border-radius: var(--border-radius-md); outline: none;" required><?php echo htmlspecialchars(get_site_text('contact_form_desc'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="cms-submit-bar">
                                        <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-size: 1rem; width: 100%; max-width: 400px; display: inline-flex; align-items: center; justify-content: center; gap: 0.8rem;">💾 Sauvegarder tous les textes du site</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div> <!-- Fin Onglet 5 : Contenus -->

                        </div> <!-- Fin .admin-tabs-container -->
                    </div> <!-- Fin .admin-tab-container-fluid -->
                </main> <!-- Fin .admin-content -->
            </div> <!-- Fin .admin-dashboard-wrapper -->

            <!-- MODAL DETAIL DE DEVIS (DYNAMIQUEMENT REMPLI PAR JS) -->
            <div id="devis-modal" class="modal">
                <div class="modal-content">
                    <span class="modal-close">&times;</span>
                    <h2 id="modal-title">Demande de devis #</h2>
                    
                    <div class="modal-body-grid">
                        <div class="modal-section-info">
                            <h3>Coordonnées du client</h3>
                            <p><strong>Nom complet :</strong> <span id="modal-client-name"></span></p>
                            <p><strong>E-mail :</strong> <a href="" id="modal-email-link"></a></p>
                            <p><strong>Téléphone :</strong> <a href="" id="modal-phone-link"></a></p>
                            <p><strong>Localisation :</strong> <span id="modal-location"></span></p>
                        </div>
                        <div class="modal-section-project">
                            <h3>Détails du chantier</h3>
                            <p><strong>Type de travaux :</strong> <span id="modal-project-type"></span></p>
                            <p><strong>Surface estimée :</strong> <span id="modal-surface"></span> m²</p>
                            <p><strong>Soumis le :</strong> <span id="modal-date"></span></p>
                            <p><strong>Statut de traitement :</strong> <span id="modal-status" class="status-tag"></span></p>
                        </div>
                    </div>

                    <div class="modal-section-msg">
                        <h3>Description détaillée du projet :</h3>
                        <div class="modal-message-box" id="modal-message"></div>
                    </div>

                    <div class="modal-actions-row" style="display: flex; gap: 1rem; justify-content: flex-end; align-items: center; width: 100%;">
                        <a href="#" id="modal-pdf-btn" target="_blank" class="btn btn-primary" style="background-color: var(--accent-color); border-color: var(--accent-color); color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: var(--border-radius-md); font-weight: 500;">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            Imprimer / PDF
                        </a>
                        <button type="button" class="btn btn-secondary modal-close-btn" style="margin-left: 0;">Fermer la fenêtre</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$is_logged_in): ?>
    </div> <!-- Fin .container-admin (login) -->
</main> <!-- Fin .admin-main (login) -->
        <?php endif; ?>

<?php
require_once __DIR__ . '/footer.php';
?>
