<?php
// db-connection.php - Handles database connection to Azure SQL
try {
    $conn = new PDO("sqlsrv:server = tcp:francerecord.database.windows.net,1433; Database = Francerecord", "francerecordloki", "Hesoyam@2025");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e) {
    // Log error and display friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Une erreur de connexion à la base de données s'est produite. Veuillez contacter l'administrateur système.");
}
?>
