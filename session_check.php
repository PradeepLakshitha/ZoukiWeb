<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_session($required_roles = null) {
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

// Optional debugging function
function debug_session() {
    echo '<div class="alert alert-info"><h5>Session Debug:</h5><pre>';
    print_r($_SESSION);
    echo '</pre></div>';
}
?>