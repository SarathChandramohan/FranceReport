<?php
// session_check.php - Include this file at the top of each protected page

// Start the session
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}

// Get current user information
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'],
            'nom' => $_SESSION['nom'],
            'prenom' => $_SESSION['prenom'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']  // Default to 'User' if not set
        ];
    }
    return null;
}

// Log out the user
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // If it's desired to kill the session, also delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Implement session timeout
function checkSessionTimeout($maxIdleTime = 900) { // 900 seconds = 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $maxIdleTime) {
        logoutUser();
    }
    $_SESSION['last_activity'] = time(); // Update last activity time
}

// Call this function on each page to check session timeout
checkSessionTimeout();
?>
