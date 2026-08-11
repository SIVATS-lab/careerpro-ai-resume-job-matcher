<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Secure Session Annihilation Gateway
 * Version: 2.0.0
 * Architecture: Complete Session Teardown, Cookie Destruction, Safe Routing
 * ============================================================================
 */

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie if it exists
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

// 3. Destroy the session entirely on the server
session_destroy();

// 4. Redirect cleanly back to the authentication gateway
header("Location: login.php?msg=logged_out");
exit;