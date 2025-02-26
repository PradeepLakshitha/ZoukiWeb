<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: Log logout event for debugging
file_put_contents('logout_debug.log',
    date('Y-m-d H:i:s') . " - Logout: " . ($_SESSION['username'] ?? 'Unknown user') .
    " (Session ID: " . session_id() . ")\n",
    FILE_APPEND);

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookies
setcookie("username", "", time() - 3600, "/");
setcookie("uType", "", time() - 3600, "/");

// Finally, destroy the session
session_destroy();

// Use a timestamp to prevent caching
header("Location: index.php?logout=" . time());
exit();
?>