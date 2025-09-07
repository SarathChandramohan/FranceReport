<?php
session_start(); // <-- session_start is at the top.

// --- Core PHP Functions (Unchanged as requested) ---
function connectDB() {
    $connectionInfo = array(
        "UID" => "francerecordloki",
        "pwd" => "Hesoyam@2025",
        "Database" => "test2",
        "LoginTimeout" => 30,
        "Encrypt" => 1,
        "TrustServerCertificate" => 0
    );
    $serverName = "tcp:francerecord.database.windows.net,1433";
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if($conn === false) {
        $errors = sqlsrv_errors();
        $message = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown error during SQL Server connection.';
        throw new Exception("Erreur de connexion SQL Server: " . $message);
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
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return ($row['count'] > 0);
}

function registerUser($conn, $nom, $prenom, $email, $password) {
    $hashedPassword = hashPassword($password);
    $sql = "INSERT INTO Users (nom, prenom, email, role, status, password_hash, date_creation)
            VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
    $params = array($nom, $prenom, $email, "User", "Active", $hashedPassword);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if($stmt === false) {
        error_log("Register error: " . print_r(sqlsrv_errors(), true));
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
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if($row && verifyPassword($password, $row['password_hash'])) {
        return array(
            'user_id' => $row['user_id'],
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'role' => $row['role']
        );
    }
    return false;
}

// --- Form Handling Logic (Remember Me logic modified) ---
$showLogin = true;
$errorMsg = "";
$successMsg = "";

if(isset($_POST['toggleForm'])) {
    $showLogin = ($_POST['toggleForm'] === 'register') ? false : true;
}

if(isset($_POST['register'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if(empty($nom) || empty($prenom) || empty($email) || empty($password)) {
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
                    $showLogin = true;
                } else {
                    $errorMsg = "Erreur lors de l'inscription. Veuillez réessayer.";
                }
            }
            sqlsrv_close($conn);
        } catch (Exception $e) {
            $errorMsg = "Erreur de connexion à la base de données.";
        }
    }
    // To ensure the register form is shown if there's an error
    if (!empty($errorMsg)) {
        $showLogin = false;
    }
}

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
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['email'] = $email;
                $_SESSION['logged_in'] = true;
                $_SESSION['role'] = $user['role'];

                // --- MODIFICATION: "Remember Me" is now always active ---
                // The checkbox is removed, and this logic runs for every successful login.
                $token = bin2hex(random_bytes(32));
                $series_identifier = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                $sql_token = "INSERT INTO UserTokens (user_id, token_hash, series_identifier, expires_at) VALUES (?, ?, ?, ?)";
                $params_token = array($user['user_id'], $token_hash, $series_identifier, $expires_at);
                sqlsrv_query($conn, $sql_token, $params_token);

                $cookie_value = $user['user_id'] . ':' . $token . ':' . $series_identifier;
                setcookie('remember_me', $cookie_value, time() + (86400 * 30), "/"); // 30 day cookie
                // --- END MODIFICATION ---

                // Redirect based on role
                if ($_SESSION['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: timesheet.php");
                }
                exit;
            } else {
                $errorMsg = "Email ou mot de passe incorrect.";
            }
            sqlsrv_close($conn);
        } catch (Exception $e) {
            $errorMsg = "Erreur de connexion à la base de données.";
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
        /* --- Font and Basic Reset --- */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #6A0DAD;
            --primary-hover: #520a86;
            --dark-bg: #111;
            --card-bg: rgba(34, 33, 34, 0.85);
            --input-bg: #2c2c2e;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --border-color: #3a3a3c;
            --error-bg: rgba(255, 59, 48, 0.1);
            --error-border: rgba(255, 59, 48, 0.3);
            --error-text: #ff453a;
            --success-bg: rgba(52, 199, 89, 0.1);
            --success-border: rgba(52, 199, 89, 0.3);
            --success-text: #32d74b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-image: url('Login.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-color: var(--dark-bg);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
        }

        /* --- Loading Screen --- */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--dark-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        .loading-logo {
            width: 150px;
            margin-bottom: 25px;
        }
        .loading-bar {
            width: 220px;
            height: 5px;
            background-color: #333;
            border-radius: 5px;
            overflow: hidden;
        }
        .loading-progress {
            width: 0;
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 5px;
            animation: loading 2.5s ease-in-out forwards;
        }
        @keyframes loading {
            from { width: 0; }
            to { width: 100%; }
        }

        /* --- Main Container & Card --- */
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            max-width: 450px;
            padding: 20px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .container.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .card {
            width: 100%;
            background-color: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            padding: 40px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-section h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .logo-section p {
            color: var(--text-secondary);
            font-size: 16px;
            margin-top: 8px;
        }
        
        /* --- Forms --- */
        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background-color: var(--input-bg);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control::placeholder {
            color: #6c6c70;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 13, 173, 0.3);
            outline: none;
        }

        /* --- Buttons --- */
        .btn-primary {
            background: var(--primary-color);
            color: white;
            width: 100%;
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease;
            margin-top: 10px;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .btn-primary:active {
            transform: scale(0.98);
        }

        /* --- Toggle Link --- */
        .toggle-container {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .btn-link {
            background: none;
            border: none;
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            padding: 5px;
            margin-left: 5px;
            transition: color 0.2s;
        }
        .btn-link:hover {
            color: var(--text-primary);
            text-decoration: underline;
        }

        /* --- Alerts --- */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            animation: fadeIn 0.3s ease;
        }
        .alert-danger {
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }
        .alert-success {
            background-color: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }
        
        .name-group {
            display: flex;
            gap: 15px;
        }
        .name-group .form-group {
            flex: 1;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- Responsive Design --- */
        @media (max-width: 480px) {
            .card {
                padding: 30px 25px;
                border: none;
                background-color: transparent;
                backdrop-filter: none;
                box-shadow: none;
            }
            .name-group {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="loading-screen" id="loading-screen">
        <img src="Logo.png" alt="Logo" class="loading-logo">
        <div class="loading-bar">
            <div class="loading-progress"></div>
        </div>
    </div>

    <main class="container" id="main-content">
        <div class="card">
            <div class="logo-section">
                <h1><?php echo $showLogin ? 'Bienvenue' : 'Créer un Compte'; ?></h1>
                <p>Système de pointage et gestion du personnel</p>
            </div>

            <?php if(!empty($errorMsg)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <?php if(!empty($successMsg)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
            <?php endif; ?>

            <?php if($showLogin): ?>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required placeholder="nom@exemple.com">
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required placeholder="********">
                    </div>
                    <button type="submit" name="login" class="btn-primary">Se connecter</button>
                </form>
                <div class="toggle-container">
                    Pas encore de compte?
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="toggleForm" value="register">
                        <button type="submit" class="btn-link">S'inscrire</button>
                    </form>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <div class="name-group">
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" class="form-control" required value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" class="form-control" required value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe (8+ caractères)</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="register" class="btn-primary">Créer un compte</button>
                </form>
                <div class="toggle-container">
                    Déjà un compte?
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="toggleForm" value="login">
                        <button type="submit" class="btn-link">Se connecter</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const loadingScreen = document.getElementById('loading-screen');
            const mainContent = document.getElementById('main-content');

            const rememberMeCookie = document.cookie.split('; ').find(row => row.startsWith('remember_me='));

            if (rememberMeCookie) {
                // If cookie exists, redirect immediately. The server-side logic in protected
                // pages will handle the actual validation. This is a client-side optimization.
                window.location.href = 'dashboard.php'; 
            } else {
                // If no cookie, show the loading screen, then fade in the login page.
                setTimeout(() => {
                    loadingScreen.style.opacity = '0';
                    // Wait for fade out transition to end before hiding it
                    loadingScreen.addEventListener('transitionend', () => {
                        loadingScreen.style.display = 'none';
                    });
                    
                    // Show and fade in the main content
                    mainContent.classList.add('visible');

                }, 2800); // Slightly longer than the animation to ensure it completes
            }
        });
    </script>
</body>
</html>
