<?php
/**
 * Configuration globale de l'application
 * Cetin Family
 */

// Démarrage global de la session si non démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Informations de connexion à la base de données (WampServer par défaut)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Par défaut, le mot de passe root de WampServer est vide
define('DB_NAME', 'peinture_elegance');

// Compte administrateur créé au premier lancement (à définir via variables d'environnement)
define('ADMIN_DEFAULT_USER', getenv('ADMIN_DEFAULT_USER') ?: 'admin_demo');
define('ADMIN_DEFAULT_PASSWORD', getenv('ADMIN_DEFAULT_PASSWORD') ?: 'ChangezMoi_2026!');
define('ADMIN_DEFAULT_EMAIL', getenv('ADMIN_DEFAULT_EMAIL') ?: 'admin@example.com');

// Paramètres de l'application
define('SITE_NAME', 'Cetin Family');
define('CONTACT_EMAIL', 'cetinfamily71@gmail.com');

// URL publique du site en production (à adapter si le domaine change)
define('SITE_URL_PRODUCTION', 'https://www.cetinfamily71.fr');

/**
 * Détecte si l'application tourne en environnement local (WampServer)
 * @return bool
 */
function is_dev_environment() {
    if (!isset($_SERVER['HTTP_HOST'])) {
        return false;
    }
    return (bool) preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']);
}

/**
 * Retourne l'URL de base du site (local auto-détecté ou production)
 * @return string
 */
function get_site_base_url() {
    static $base_url = null;
    if ($base_url !== null) {
        return $base_url;
    }

    if (is_dev_environment()) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script_dir === '/') {
            $script_dir = '';
        }
        $base_url = rtrim($protocol . '://' . $_SERVER['HTTP_HOST'] . $script_dir, '/');
    } else {
        $base_url = rtrim(SITE_URL_PRODUCTION, '/');
    }

    return $base_url;
}

// Rapports d'erreurs : affichés en local, journalisés uniquement en production
if (is_dev_environment()) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
}
error_reporting(E_ALL);

/**
 * Génère un jeton CSRF et le stocke en session
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide le jeton CSRF fourni
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Génère le champ HTML hidden pour la protection CSRF
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
?>
