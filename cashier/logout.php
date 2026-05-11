<?php
// pharmacy/logout.php - Securely logs out the pharmacist
require_once '../config/config.php';

/**
 * Perform a clean logout by unsetting the session, 
 * clearing session cookies, and destroying the session data.
 */

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie to prevent session persistence
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Officially destroy the session
session_destroy();

// Redirect the pharmacist back to the module login page
header("Location: login.php");
exit();
