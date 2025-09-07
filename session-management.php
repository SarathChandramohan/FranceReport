<?php
// session-management.php (Corrected & Hardened Version)

session_start();

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (isLoggedIn()) {
        return;
    }

    if (isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        
        // --- FIX: Safely parse the cookie ---
        if (count($parts) === 3) {
            list($user_id, $token, $series_identifier) = $parts;

            if ($user_id && $token && $series_identifier) {
                try {
                    $conn = new PDO("sqlsrv:server=tcp:francerecord.database.windows.net,1433;Database=test2", "francerecordloki", "Hesoyam@2025");
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT * FROM UserTokens WHERE user_id = ? AND series_identifier = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$user_id, $series_identifier]);
                    $userToken = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($userToken && hash_equals($userToken['token_hash'], hash('sha256', $token))) {
                        // Token is valid, regenerate session
                        $user = getUserById($conn, $user_id);
                        if ($user) {
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['nom'] = $user['nom'];
                            $_SESSION['prenom'] = $user['prenom'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['logged_in'] = true;
                            $_SESSION['role'] = $user['role'];

                            // Issue a new token for security
                            $new_token = bin2hex(random_bytes(32));
                            $new_token_hash = hash('sha256', $new_token);
                            $sqlUpdate = "UPDATE UserTokens SET token_hash = ? WHERE token_id = ?";
                            $stmtUpdate = $conn->prepare($sqlUpdate);
                            $stmtUpdate->execute([$new_token_hash, $userToken['token_id']]);

                            $cookie_value = $user_id . ':' . $new_token . ':' . $series_identifier;
                            setcookie('remember_me', $cookie_value, time() + (86400 * 30), "/");
                            return;
                        }
                    }
                } catch (Exception $e) {
                    // Log the error, don't show it to the user
                    error_log("requireLogin DB Error: " . $e->getMessage());
                }
            }
        }
        // If cookie is invalid or any step fails, clear it and proceed to logout
        logoutUser();
    }
    
    // If no session and no valid cookie, redirect to login
    header("Location: index.php");
    exit;
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'],
            'nom' => $_SESSION['nom'],
            'prenom' => $_SESSION['prenom'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'] ?? 'User'
        ];
    }
    return null;
}

function logoutUser() {
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 3) {
            list($user_id, $token, $series_identifier) = $parts;
            if ($user_id && $series_identifier) {
                try {
                    $conn = new PDO("sqlsrv:server=tcp:francerecord.database.windows.net,1433;Database=test2", "francerecordloki", "Hesoyam@2025");
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $sql = "DELETE FROM UserTokens WHERE user_id = ? AND series_identifier = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$user_id, $series_identifier]);
                } catch (Exception $e) {
                     error_log("logoutUser DB Error: " . $e->getMessage());
                }
            }
        }
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

function getUserById($conn, $user_id) {
    $sql = "SELECT user_id, nom, prenom, role, email FROM Users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
