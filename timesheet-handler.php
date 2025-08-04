<?php
// Enable error reporting for debugging. Should be turned off in production.
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session-management.php';
requireLogin();

header('Content-Type: application/json');

// --- Database Connection ---
function getDbConnection() {
    // IMPORTANT: Replace with your actual MS SQL Server credentials
    $host = 'tcp:francerecord.database.windows.net,1433'; // e.g., 'localhost' or 'server.database.windows.net'
    $dbname = 'Francerecord';
    $user = 'francerecordloki';
    $password = 'Hesoyam@2025';
    
    try {
        // Use the sqlsrv driver for Microsoft SQL Server
        $pdo = new PDO("sqlsrv:server=$host;database=$dbname", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Log the error and send a generic failure message
        error_log('Database Connection Error: ' . $e->getMessage());
        sendJsonResponse('error', 'Erreur de connexion à la base de données.');
    }
}

// --- Helper Functions ---

function sendJsonResponse($status, $message, $data = null) {
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    die(json_encode($response));
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if ($lat1 == null || $lon1 == null || $lat2 == null || $lon2 == null) return PHP_INT_MAX;
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// --- Main Action Handler ---

$action = $_POST['action'] ?? '';
$user = getCurrentUser();
$userId = $user['user_id'];
$pdo = getDbConnection();

try {
    switch ($action) {
        case 'get_user_missions_for_today':
            // SQL Server uses CAST(GETDATE() AS DATE) to get the current date
            $stmt = $pdo->prepare("
                SELECT a.assignment_id, m.name AS mission_text
                FROM mission_assignments a
                JOIN missions m ON a.mission_id = m.mission_id
                WHERE a.user_id = ? AND a.assignment_date = CAST(GETDATE() AS DATE)
            ");
            $stmt->execute([$userId]);
            $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Missions chargées.', $missions);
            break;

        case 'check_location_status':
            $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
            $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
            
            $stmt = $pdo->query("SELECT site_name, latitude, longitude, radius FROM authorized_sites WHERE is_active = 1");
            $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $inRange = false;
            $message = "Hors de portée de tout site autorisé.";

            foreach ($sites as $site) {
                $distance = calculateDistance($latitude, $longitude, $site['latitude'], $site['longitude']);
                if ($distance <= $site['radius']) {
                    $inRange = true;
                    $message = "À proximité de : " . htmlspecialchars($site['site_name']) . " (" . round($distance) . "m)";
                    break;
                }
            }
            sendJsonResponse('success', 'Statut du lieu vérifié.', ['in_range' => $inRange, 'message' => $message]);
            break;
            
        case 'record_entry':
            $missionId = filter_input(INPUT_POST, 'mission_id', FILTER_VALIDATE_INT) ?: null;
            $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
            $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
            $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

            if ($missionId === null && $_POST['mission_id'] !== 'null') $missionId = false; // Handle invalid int

            // SQL Server uses GETDATE() for the current timestamp
            $stmt = $pdo->prepare("
                INSERT INTO timesheet (user_id, timesheet_date, logon_time, logon_latitude, logon_longitude, assignment_id, logon_comment)
                VALUES (?, CAST(GETDATE() AS DATE), GETDATE(), ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $latitude, $longitude, $missionId, $comment]);
            sendJsonResponse('success', 'Entrée enregistrée avec succès.');
            break;

        case 'record_exit':
            $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
            $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

            $stmt = $pdo->prepare("
                UPDATE timesheet SET logoff_time = GETDATE(), logoff_latitude = ?, logoff_longitude = ?
                WHERE user_id = ? AND timesheet_date = CAST(GETDATE() AS DATE) AND logoff_time IS NULL
            ");
            $stmt->execute([$latitude, $longitude, $userId]);
            sendJsonResponse('success', 'Sortie enregistrée avec succès.');
            break;
            
        case 'add_break':
            $breakMinutes = filter_input(INPUT_POST, 'break_minutes', FILTER_VALIDATE_INT);
            if (!$breakMinutes || $breakMinutes <= 0) sendJsonResponse('error', 'Durée de pause invalide.');

            $stmt = $pdo->prepare("
                UPDATE timesheet SET break_minutes = ISNULL(break_minutes, 0) + ?
                WHERE user_id = ? AND timesheet_date = CAST(GETDATE() AS DATE) AND logoff_time IS NULL
            ");
            $stmt->execute([$breakMinutes, $userId]);
            sendJsonResponse('success', 'Pause ajoutée.');
            break;

        case 'get_history':
            // SQL Server uses DATEDIFF for time difference and CONVERT for formatting
            $stmt = $pdo->prepare("
                SELECT TOP 15
                    CONVERT(varchar, timesheet_date, 103) AS date,
                    CONVERT(varchar, logon_time, 108) AS logon_time,
                    CONVERT(varchar, logoff_time, 108) AS logoff_time,
                    ISNULL(break_minutes, 0) as break_minutes,
                    DATEDIFF(minute, logon_time, logoff_time) as total_minutes
                FROM timesheet 
                WHERE user_id = ? 
                ORDER BY timesheet_date DESC, logon_time DESC
            ");
            $stmt->execute([$userId]);
            $history_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $history = [];
            foreach ($history_raw as $row) {
                $duration = '--';
                if ($row['total_minutes'] !== null) {
                    $effective_minutes = $row['total_minutes'] - $row['break_minutes'];
                    if ($effective_minutes < 0) $effective_minutes = 0;
                    $hours = floor($effective_minutes / 60);
                    $mins = $effective_minutes % 60;
                    $duration = sprintf('%dh %02dmin', $hours, $mins);
                }
                $history[] = [
                    'date' => $row['date'],
                    'logon_time' => substr($row['logon_time'], 0, 5),
                    'logoff_time' => $row['logoff_time'] ? substr($row['logoff_time'], 0, 5) : '--',
                    'break_minutes' => $row['break_minutes'] > 0 ? $row['break_minutes'] . ' min' : '--',
                    'duration' => $duration
                ];
            }
            sendJsonResponse('success', 'Historique chargé.', $history);
            break;
            
        case 'get_latest_entry_status':
            $stmt = $pdo->prepare("
                SELECT TOP 1
                    (CASE WHEN logon_time IS NOT NULL THEN 1 ELSE 0 END) as has_entry,
                    (CASE WHEN logoff_time IS NOT NULL THEN 1 ELSE 0 END) as has_exit
                FROM timesheet
                WHERE user_id = ? AND timesheet_date = CAST(GETDATE() AS DATE)
                ORDER BY timesheet_id DESC
            ");
            $stmt->execute([$userId]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$status) {
                $status = ['has_entry' => false, 'has_exit' => false];
            } else {
                // Convert boolean-like integers to actual booleans for JavaScript
                $status['has_entry'] = (bool)$status['has_entry'];
                $status['has_exit'] = (bool)$status['has_exit'];
            }
            sendJsonResponse('success', 'Statut récupéré.', $status);
            break;
            
        default:
            sendJsonResponse('error', 'Action non valide.');
            break;
    }
} catch (Exception $e) {
    error_log('Timesheet Handler Error: ' . $e->getMessage());
    sendJsonResponse('error', 'Une erreur de serveur est survenue. Veuillez contacter l\'administrateur.');
}
