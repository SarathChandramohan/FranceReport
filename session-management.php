<?php
// session-management.php - Secure Session Management

// --- CONFIGURATION ---
// Set the session duration to 2 hours (in seconds)
define('SESSION_LIFETIME', 7200);
// Set the maximum idle time to 30 minutes (in seconds)
define('SESSION_MAX_IDLE_TIME', 1800);

// --- SECURITY BEST PRACTICES ---
// Use strict session mode to prevent session fixation
ini_set('session.use_strict_mode', 1);
// Ensure cookies are sent only over HTTPS
ini_set('session.cookie_secure', 1);
// Prevent JavaScript from accessing the session cookie
ini_set('session.cookie_httponly', 1);
// Prevent session IDs from being passed in URLs
ini_set('session.use_only_cookies', 1);

// --- CUSTOM SESSION START ---
// This function configures and starts the session securely.
function secure_session_start() {
    // Set the session cookie parameters
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Start the session
    session_start();

    // Regenerate the session ID periodically to prevent session hijacking
    if (empty($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } else {
        // Regenerate the session ID every 30 minutes
        if (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// Start the secure session
secure_session_start();

// --- SESSION VALIDATION AND TIMEOUT ---
// This function checks if the session is valid and hasn't expired.
function validate_session() {
    // Check if the user is logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return; // No session to validate
    }

    // 1. Check for total session lifetime expiration
    if (isset($_SESSION['session_start_time']) && (time() - $_SESSION['session_start_time']) > SESSION_LIFETIME) {
        logoutUser("Session has expired due to overall lifetime.");
        return;
    }

    // 2. Check for inactivity timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_MAX_IDLE_TIME) {
        logoutUser("Session has expired due to inactivity.");
        return;
    }

    // 3. User agent validation for added security
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        logoutUser("User agent mismatch. Possible session hijacking attempt.");
        return;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Call this function on each page to check the session
validate_session();

// --- HELPER FUNCTIONS ---

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
            'user_id' => $_SESSION['user_id'] ?? null,
            'nom' => $_SESSION['nom'] ?? '',
            'prenom' => $_SESSION['prenom'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? 'User'
        ];
    }
    return null;
}

// Log out the user
function logoutUser($reason = "Logged out successfully.") {
    // Unset all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session
    session_destroy();
    
    // Redirect to login page with a reason (optional)
    header("Location: index.php?logout_reason=" . urlencode($reason));
    exit;
}

// --- FUNCTION TO CALL AFTER A SUCCESSFUL LOGIN ---
// This should be called from your login script after verifying credentials.
function on_login_success($user) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['nom'] = $user['nom'];
    $_SESSION['prenom'] = $user['prenom'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    // Set session timestamps and security markers
    $_SESSION['session_start_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}

?>
