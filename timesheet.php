<?php
echo "Hello from timesheet-wrapper.php!"; // <--- THIS IS LINE 2
// Include session management
require_once 'session-management.php';

// Require login to access this page
requireLogin();
?>

<?php
// 1. Ensure no output before this PHP block
// No spaces, blank lines, or BOM before <?php

// 2. Include session management, which starts the session and defines requireLogin()
require_once 'session-management.php';

// 3. Require login - This will redirect the user to index.php and exit
// if they are not logged in.
requireLogin();

// 4. If the script reaches this point, the user IS logged in.
// Now you can safely output content.

// echo "Hello from timesheet-wrapper.php!"; // You can put the echo here if you want this message to show

// ... rest of your timesheet.php PHP logic (fetching data, etc.) ...

// 5. Start your HTML output
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Timesheet</title>
</head>
<body>
    <h1>Timesheet Page</h1>
    <?php echo "Welcome, " . htmlspecialchars($_SESSION['prenom']) . "!"; // Example using session data ?>
    </body>
</html>
