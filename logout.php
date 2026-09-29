<?php
/**
 * Script de déconnexion Administrateur
 * Cetin Family
 */

require_once __DIR__ . '/config.php';

// Vider le tableau de session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également le cookie de session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Redirection vers l'espace de connexion admin
header("Location: admin.php");
exit;
?>
