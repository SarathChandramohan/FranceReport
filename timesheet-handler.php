<?php
require_once 'session-management.php';
requireLogin();

header('Content-Type: application/json');

// --- Database Connection ---
function getDbConnection() {
    // IMPORTANT: Replace with your actual database credentials
    $host = 'localhost';
    $dbname = 'your_database_name';
    $user = 'your_username';
    $password = 'your_password';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // In a real application, you would log this error, not just expose it.
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }
}

// --- Helper Functions ---

/**
 * Sends a JSON response and terminates the script.
 */
function sendJsonResponse($status, $message, $data = null) {
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

/**
 * Calculates the distance between two geographical points (Haversine formula).
 * Returns distance in meters.
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
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
            // Fetches missions assigned to the user for the current day
            $stmt = $pdo->prepare("
                SELECT a.assignment_id, m.name AS mission_text
                FROM mission_assignments a
                JOIN missions m ON a.mission_id = m.mission_id
                WHERE a.user_id = ? AND a.assignment_date = CURDATE()
            ");
            $stmt->execute([$userId]);
            $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Missions loaded.', $missions);
            break;

        case 'check_location_status':
            // Checks if user is within range of any authorized site
            $latitude = $_POST['latitude'] ?? 0;
            $longitude = $_POST['longitude'] ?? 0;
            
            $stmt = $pdo->query("SELECT site_name, latitude, longitude, radius FROM authorized_sites");
            $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $inRange = false;
            $message = "Vous êtes hors de portée de tout site autorisé.";

            foreach ($sites as $site) {
                $distance = calculateDistance($latitude, $longitude, $site['latitude'], $site['longitude']);
                if ($distance <= $site['radius']) {
                    $inRange = true;
                    $message = "Vous êtes à proximité de : " . htmlspecialchars($site['site_name']);
                    break;
                }
            }
            sendJsonResponse('success', 'Location status checked.', ['in_range' => $inRange, 'message' => $message]);
            break;
            
        case 'record_entry':
            // Records a clock-in entry
            $missionId = isset($_POST['mission_id']) && $_POST['mission_id'] !== 'null' ? $_POST['mission_id'] : null;
            $comment = $_POST['comment'] ?? null;
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];

            $stmt = $pdo->prepare("
                INSERT INTO timesheet (user_id, logon_time, logon_latitude, logon_longitude, assignment_id, logon_comment)
                VALUES (?, NOW(), ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $latitude, $longitude, $missionId, $comment]);
            sendJsonResponse('success', 'Entrée enregistrée avec succès.');
            break;

        case 'record_exit':
             // Records a clock-out entry
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];

            $stmt = $pdo->prepare("
                UPDATE timesheet SET logoff_time = NOW(), logoff_latitude = ?, logoff_longitude = ?
                WHERE user_id = ? AND timesheet_date = CURDATE() AND logoff_time IS NULL
            ");
            $stmt->execute([$latitude, $longitude, $userId]);
            sendJsonResponse('success', 'Sortie enregistrée avec succès.');
            break;
            
        case 'add_break':
            // Adds a break to the current day's timesheet
            $breakMinutes = $_POST['break_minutes'] ?? 0;
            $stmt = $pdo->prepare("
                UPDATE timesheet SET break_minutes = break_minutes + ?
                WHERE user_id = ? AND timesheet_date = CURDATE() AND logoff_time IS NULL
            ");
            $stmt->execute([$breakMinutes, $userId]);
            sendJsonResponse('success', 'Pause ajoutée.');
            break;

        case 'get_history':
            // Fetches the user's timesheet history
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(timesheet_date, '%d/%m/%Y') AS date,
                    TIME_FORMAT(logon_time, '%H:%i') AS logon_time,
                    logon_location_name,
                    TIME_FORMAT(logoff_time, '%H:%i') AS logoff_time,
                    logoff_location_name,
                    break_minutes,
                    TIMESTAMPDIFF(MINUTE, logon_time, logoff_time) - COALESCE(break_minutes, 0) as total_minutes
                FROM timesheet 
                WHERE user_id = ? 
                ORDER BY timesheet_date DESC, logon_time DESC
                LIMIT 30
            ");
            $stmt->execute([$userId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($history as &$row) {
                 if ($row['total_minutes'] !== null) {
                    $hours = floor($row['total_minutes'] / 60);
                    $mins = $row['total_minutes'] % 60;
                    $row['duration'] = sprintf('%dh %02dmin', $hours, $mins);
                } else {
                    $row['duration'] = '--';
                }
            }
            sendJsonResponse('success', 'History loaded.', $history);
            break;
            
        case 'get_latest_entry_status':
             // Checks if the user has clocked in or out for the day
            $stmt = $pdo->prepare("
                SELECT 
                    (logon_time IS NOT NULL) as has_entry,
                    (logoff_time IS NOT NULL) as has_exit
                FROM timesheet
                WHERE user_id = ? AND timesheet_date = CURDATE()
                ORDER BY timesheet_id DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$status) {
                $status = ['has_entry' => false, 'has_exit' => false];
            }
            sendJsonResponse('success', 'Status fetched.', $status);
            break;
            
        default:
            sendJsonResponse('error', 'Action non valide.');
            break;
    }
} catch (Exception $e) {
    // Log the error and send a generic error message
    error_log('Timesheet Handler Error: ' . $e->getMessage());
    sendJsonResponse('error', 'Une erreur inattendue est survenue.');
}
