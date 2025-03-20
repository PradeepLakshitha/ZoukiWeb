<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_session($required_roles = null) {
    // Log the check to a file
    $log = "Session check at " . date('Y-m-d H:i:s') . "\n";
    $log .= "Session ID: " . session_id() . "\n";
    $log .= "Session data: " . print_r($_SESSION, true) . "\n";
    $log .= "Required roles: " . print_r($required_roles, true) . "\n";
    file_put_contents('session_check.log', $log, FILE_APPEND);

    // Basic login check
    if (!isset($_SESSION['username'])) {
        // Not logged in at all
        $_SESSION['error'] = "Please log in to continue.";
        header("Location: index.php");
        exit();
    }

    // Set logged_in flag for compatibility
    $_SESSION['logged_in'] = true;

    // Optional role-based check
    if ($required_roles !== null) {
        if (!isset($_SESSION['uType']) || !in_array($_SESSION['uType'], (array)$required_roles)) {
            // Logged in but not authorized
            $_SESSION['error'] = "You don't have permission to access this page.";
            header("Location: home.php");
            exit();
        }
    }

    return true;
}
?>