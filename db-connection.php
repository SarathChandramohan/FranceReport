<?php
// db-connection.php - Handles database connection to Azure SQL
try {
    $conn = new PDO("sqlsrv:server = tcp:francerecord.database.windows.net,1433; Database = Francerecord", "francerecordloki", "{your_password_here}");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false);
}
} catch(PDOException $e) {
    // Log error and display friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Une erreur de connexion à la base de données s'est produite. Veuillez contacter l'administrateur système.");
}
?>
