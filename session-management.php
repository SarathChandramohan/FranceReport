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
        // Check for "remember me" cookie
        if (isset($_COOKIE['remember_me'])) {
            $token = $_COOKIE['remember_me'];
            list($user_id, $token, $series_identifier) = explode(':', $token);

            if ($user_id && $token && $series_identifier) {
                // Validate the token against the database
                $db = connectDB(); // You'll need a connectDB function here.
                $sql = "SELECT * FROM UserTokens WHERE user_id = ? AND series_identifier = ?";
                $params = array($user_id, $series_identifier);
                $stmt = sqlsrv_query($db, $sql, $params);
                $userToken = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

                if ($userToken && hash_equals($userToken['token_hash'], hash('sha256', $token))) {
                    // Token is valid, log the user in
                    $user = getUserById($db, $user_id); // You'll need a getUserById function
                    if ($user) {
                        // Log the user in
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['nom'] = $user['nom'];
                        $_SESSION['prenom'] = $user['prenom'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['role'] = $user['role'];

                        // Issue a new token
                        $new_token = bin2hex(random_bytes(32));
                        $new_token_hash = hash('sha256', $new_token);
                        $sql = "UPDATE UserTokens SET token_hash = ? WHERE token_id = ?";
                        $params = array($new_token_hash, $userToken['token_id']);
                        sqlsrv_query($db, $sql, $params);

                        // Set the new cookie
                        $cookie_value = $user_id . ':' . $new_token . ':' . $series_identifier;
                        setcookie('remember_me', $cookie_value, time() + (86400 * 30), "/"); // 30 day cookie

                        return;
                    }
                }
            }
        }

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
    // ---- START: BUG FIX ----
    // Clear the remember_me cookie and database token
    if (isset($_COOKIE['remember_me'])) {
        // Delete the cookie by setting its expiration date in the past
        setcookie('remember_me', '', time() - 3600, '/');

        // Remove the token from the database
        list($user_id, $token, $series_identifier) = explode(':', $_COOKIE['remember_me']);
        if ($user_id && $series_identifier) {
            $db = connectDB();
            $sql = "DELETE FROM UserTokens WHERE user_id = ? AND series_identifier = ?";
            $params = array($user_id, $series_identifier);
            sqlsrv_query($db, $sql, $params);
        }
    }
    // ---- END: BUG FIX ----

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
function checkSessionTimeout($maxIdleTime = 5400) { // 5400 seconds = 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $maxIdleTime) {
        logoutUser();
    }
    $_SESSION['last_activity'] = time(); // Update last activity time
}

// Call this function on each page to check session timeout
checkSessionTimeout();

function getUserById($conn, $user_id) {
    $sql = "SELECT user_id, nom, prenom, role, email FROM Users WHERE user_id = ?";
    $params = array($user_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

?>
