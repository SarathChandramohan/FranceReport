<?php
// Step 1: Include the new secure session manager. This replaces session_start().
require_once 'session-management.php';

// Step 2: If user is already logged in, redirect them immediately.
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    if ($currentUser['role'] === 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: timesheet.php");
    }
    exit;
}

// --- DATABASE AND USER AUTHENTICATION FUNCTIONS ---

function connectDB() {
    $connectionInfo = array(
        "UID" => "francerecordloki",
        "pwd" => "Hesoyam@2025",
        "Database" => "Francerecord",
        "LoginTimeout" => 30,
        "Encrypt" => 1,
        "TrustServerCertificate" => 0
    );
    $serverName = "tcp:francerecord.database.windows.net,1433";
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if($conn === false) {
        $errors = sqlsrv_errors();
        $message = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown SQL Server connection error.';
        // In a real app, log this error instead of throwing an exception that reveals details.
        error_log("SQL Server Connection Error: " . $message);
        // For the user, a generic error is safer.
        throw new Exception("Erreur de connexion à la base de données.");
    }
    return $conn;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function userExists($conn, $email) {
    $sql = "SELECT COUNT(*) AS count FROM Users WHERE email = ?";
    $params = array($email);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if($stmt === false) {
        error_log("userExists query failed: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return ($row['count'] > 0);
}

function registerUser($conn, $nom, $prenom, $email, $password) {
    $hashedPassword = hashPassword($password);
    $sql = "INSERT INTO Users (nom, prenom, email, role, status, password_hash, date_creation) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
    $params = array($nom, $prenom, $email, "User", "Active", $hashedPassword);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if($stmt === false) {
        error_log("Register user query failed: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    sqlsrv_free_stmt($stmt);
    return true;
}

function authenticateUser($conn, $email, $password) {
    $sql = "SELECT user_id, nom, prenom, role, password_hash FROM Users WHERE email = ?";
    $params = array($email);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if($stmt === false) {
        error_log("authenticateUser query failed: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if($row && verifyPassword($password, $row['password_hash'])) {
        // Return the full user array, including email
        return array(
            'user_id' => $row['user_id'],
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'role' => $row['role'],
            'email' => $email
        );
    }
    return false;
}


// --- FORM HANDLING LOGIC ---

$showLogin = true;
$errorMsg = "";
$successMsg = "";

// Logic to toggle between login and registration forms
if(isset($_POST['toggleForm'])) {
    $showLogin = ($_POST['toggleForm'] === 'register') ? false : true;
}

// --- REGISTRATION LOGIC ---
if(isset($_POST['register'])) {
    $showLogin = false; // Ensure registration form stays visible on error
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if(empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($confirm_password)) {
        $errorMsg = "Tous les champs sont obligatoires.";
    } elseif(!validateEmail($email)) {
        $errorMsg = "Format d'email invalide.";
    } elseif(strlen($password) < 8) {
        $errorMsg = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif($password !== $confirm_password) {
        $errorMsg = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $conn = connectDB();
            if(userExists($conn, $email)) {
                $errorMsg = "Cette adresse email est déjà utilisée.";
            } else {
                if(registerUser($conn, $nom, $prenom, $email, $password)) {
                    $successMsg = "Compte créé avec succès. Vous pouvez maintenant vous connecter.";
                    $showLogin = true; // Switch to login form after successful registration
                } else {
                    $errorMsg = "Erreur lors de l'inscription. Veuillez réessayer.";
                }
            }
            sqlsrv_close($conn);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage(); // Display the generic error from connectDB
        }
    }
}

// --- LOGIN LOGIC (UPDATED) ---
if(isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if(empty($email) || empty($password)) {
        $errorMsg = "L'email et le mot de passe sont obligatoires.";
    } else {
        try {
            $conn = connectDB();
            $user = authenticateUser($conn, $email, $password);

            if($user) {
                // Step 3: Use the new secure function to initialize the session
                on_login_success($user);

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: timesheet.php");
                }
                exit; // Stop script execution after redirect
            } else {
                $errorMsg = "Email ou mot de passe incorrect.";
            }
            sqlsrv_close($conn);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage(); // Display the generic error from connectDB
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion des Ouvriers</title>
    <style>
        /* Your existing CSS - No changes needed */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body {
            background-image: url('Login.webp');
            background-size: auto;
            background-position: left bottom;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-color: #222122;
            color: #1d1d1f;
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
        }
        .container { max-width: 420px; width: 100%; padding: 25px; }
        .logo-section { text-align: center; margin-bottom: 30px; }
        .logo-section h1 { font-size: 28px; font-weight: 600; color: #ffffff; margin-bottom: 10px; }
        .logo-section p { color: #ffffff; font-size: 16px; margin-bottom: 20px; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); padding: 30px; margin-bottom: 25px; border: 1px solid #e5e5e5; }
        h2 { margin-bottom: 25px; color: #1d1d1f; font-size: 22px; font-weight: 600; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #1d1d1f; }
        .form-control { width: 100%; padding: 12px 15px; font-size: 16px; border: 1px solid #d2d2d7; border-radius: 8px; background-color: #f5f5f7; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus { border-color: #0071e3; box-shadow: 0 0 0 2px rgba(0, 113, 227, 0.2); outline: none; }
        .btn-primary { background-color: #007aff; color: white; width: 100%; padding: 14px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 16px; transition: background-color 0.2s ease-in-out; margin-top: 10px; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-link { background: none; border: none; color: #007aff; text-decoration: none; cursor: pointer; font-weight: 500; font-size: 15px; padding: 5px; }
        .btn-link:hover { text-decoration: underline; }
        .toggle-container { text-align: center; margin-top: 20px; }
        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; }
        .alert-danger { background-color: #ffe5e5; border: 1px solid #ffcccc; color: #d63027; }
        .alert-success { background-color: #e5ffe8; border: 1px solid #ccffcc; color: #2ca048; }
        .alert-info { background-color: #e5f6ff; border: 1px solid #cceeff; color: #007aff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <h1>Gestion des Ouvriers</h1>
            <p>Système de pointage et gestion du personnel</p>
        </div>

        <div class="card">
            <?php if(isset($_GET['logout_reason'])): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_GET['logout_reason']); ?></div>
            <?php endif; ?>
            <?php if(!empty($errorMsg)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <?php if(!empty($successMsg)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
            <?php endif; ?>

            <?php if($showLogin): ?>
                <h2>Connexion</h2>
                <form method="post" action="index.php">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="login" class="btn-primary">Se connecter</button>
                </form>
                <div class="toggle-container">
                    <p>Pas encore de compte?</p>
                    <form method="post" action="index.php">
                        <input type="hidden" name="toggleForm" value="register">
                        <button type="submit" class="btn-link">Créer un compte</button>
                    </form>
                </div>
            <?php else: ?>
                <h2>Créer un compte</h2>
                <form method="post" action="index.php">
                    <div class="form-group"><label for="nom">Nom</label><input type="text" id="nom" name="nom" class="form-control" required value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>"></div>
                    <div class="form-group"><label for="prenom">Prénom</label><input type="text" id="prenom" name="prenom" class="form-control" required value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>"></div>
                    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"></div>
                    <div class="form-group"><label for="password">Mot de passe</label><input type="password" id="password" name="password" class="form-control" required></div>
                    <div class="form-group"><label for="confirm_password">Confirmer le mot de passe</label><input type="password" id="confirm_password" name="confirm_password" class="form-control" required></div>
                    <button type="submit" name="register" class="btn-primary">Créer un compte</button>
                </form>
                <div class="toggle-container">
                    <p>Déjà un compte?</p>
                    <form method="post" action="index.php">
                        <input type="hidden" name="toggleForm" value="login">
                        <button type="submit" class="btn-link">Se connecter</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
