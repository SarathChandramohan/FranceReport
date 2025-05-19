<?php
session_start(); // <-- Move session_start to the top before anything else (no space above this line)
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
                // Throw an exception just like PDO would
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

            // 🛠️ Fixed your typo here
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
            // Modified to select role
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
                    'role' => $row['role'] // Added role to the returned array
                );
            }

            return false;
        }

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
                $conn = connectDB();
                if($conn === false) {
                    $errorMsg = "Erreur de connexion à la base de données.";
                } else {
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
                }
            }
        }

        if(isset($_POST['login'])) {
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            if(empty($email) || empty($password)) {
                $errorMsg = "L'email et le mot de passe sont obligatoires.";
            } else {
                $conn = connectDB();
                if($conn === false) {
                    $errorMsg = "Erreur de connexion à la base de données.";
                } else {
                    $user = authenticateUser($conn, $email, $password);
                    if($user) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['nom'] = $user['nom'];
                        $_SESSION['prenom'] = $user['prenom'];
                        $_SESSION['email'] = $email;
                        $_SESSION['logged_in'] = true;
                        $_SESSION['role'] = $user['role']; // Store the user's role in the session

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
        /* Your existing CSS here (no changes) */
        /* --- Apple Inspired Theme --- */
        /* Basic Reset and Font */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }
        body {
    background-image: url('Login.webp'); /* Replace with your image file name */
    background-size: auto; /* Display the image at its natural size */
    background-position: left bottom; /* Align image to the left bottom */
    background-repeat: no-repeat; /* Do not repeat the image */
    background-attachment: fixed; /* Keep the background fixed when scrolling */
    background-color: #222122; /* Keep a fallback background color */
    color: #1d1d1f;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}
        .container {
            width: 100%;
            max-width: 420px;
            padding: 25px;
        }
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-section h1 {
            font-size: 28px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 10px;
        }
        .logo-section p {
            color: #ffffff;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid #e5e5e5;
        }
        h2 {
            margin-bottom: 25px;
            color: #1d1d1f;
            font-size: 22px;
            font-weight: 600;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1d1d1f;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid #d2d2d7;
            border-radius: 8px;
            background-color: #f5f5f7;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #0071e3;
            box-shadow: 0 0 0 2px rgba(0, 113, 227, 0.2);
            outline: none;
        }
        .btn-primary {
            background-color: #007aff;
            color: white;
            width: 100%;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.2s ease-in-out;
            margin-top: 10px;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-link {
            background: none;
            border: none;
            color: #007aff;
            text-decoration: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            padding: 5px;
        }
        .btn-link:hover {
            text-decoration: underline;
        }
        .toggle-container {
            text-align: center;
            margin-top: 20px;
        }
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
        }
        .alert-danger {
            background-color: #ffe5e5;
            border: 1px solid #ffcccc;
            color: #d63027;
        }
        .alert-success {
            background-color: #e5ffe8;
            border: 1px solid #ccffcc;
            color: #2ca048;
        }
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            .card {
                padding: 20px;
            }
            h2 {
                font-size: 20px;
            }
            .form-control {
                padding: 10px 12px;
            }
            .btn-primary {
                padding: 12px 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <h1>Gestion des Ouvriers</h1>
            <p>Système de pointage et gestion du personnel</p>
        </div>




      

        <div class="card">
            <?php if(!empty($errorMsg)): ?>
                <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
            <?php endif; ?>
            <?php if(!empty($successMsg)): ?>
                <div class="alert alert-success"><?php echo $successMsg; ?></div>
            <?php endif; ?>

            <?php if($showLogin): ?>
                <h2>Connexion</h2>
                <form method="post" action="">
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
                    <form method="post" action="">
                        <input type="hidden" name="toggleForm" value="register">
                        <button type="submit" class="btn-link">Créer un compte</button>
                    </form>
                </div>
            <?php else: ?>
                <h2>Créer un compte</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="register" class="btn-primary">Créer un compte</button>
                </form>
                <div class="toggle-container">
                    <p>Déjà un compte?</p>
                    <form method="post" action="">
                        <input type="hidden" name="toggleForm" value="login">
                        <button type="submit" class="btn-link">Se connecter</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
