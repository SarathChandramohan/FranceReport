<?php
// timesheet-handler.php - Handles all AJAX requests for timesheet operations

require_once 'db-connection.php';
require_once 'session-management.php';

requireLogin();

$user = getCurrentUser();
$user_id = $user['user_id'];

define('APP_TIMEZONE', 'Europe/Paris');
define('MAX_DISTANCE_METERS', 100);

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch($action) {
    case 'record_entry':
        recordTimeEntry($user_id, 'logon');
        break;
    case 'record_exit':
        recordTimeEntry($user_id, 'logoff');
        break;
    case 'add_break':
        addBreak($user_id);
        break;
    case 'get_history':
        getTimesheetHistory($user_id);
        break;
    case 'get_latest_entry_status':
        getLatestEntryStatus($user_id);
        break;
    case 'check_location_status':
        checkLocationStatus();
        break;
    case 'get_user_assignments':
        getUserAssignments($user_id);
        break;
    default:
        respondWithError('Invalid action specified');
}

function getUserAssignments($user_id) {
    global $conn;
    $today = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
    try {
        $stmt = $conn->prepare("SELECT assignment_id, mission_text FROM Planning_Assignments WHERE assigned_user_id = ? AND assignment_date = ?");
        $stmt->execute([$user_id, $today]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        respondWithSuccess('Assignments retrieved', $assignments);
    } catch (PDOException $e) {
        respondWithError('Database error: ' . $e->getMessage());
    }
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

function findNearestWorkLocation($user_lat, $user_lon) {
    global $conn;
    $stmt = $conn->prepare("SELECT latitude, longitude, location_name FROM WorkLocations WHERE is_active = 1");
    $stmt->execute();
    $work_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($work_locations)) return null;

    $min_distance = PHP_INT_MAX;
    $nearest_location_name = '';
    foreach ($work_locations as $location) {
        $distance = calculateDistance($user_lat, $user_lon, $location['latitude'], $location['longitude']);
        if ($distance < $min_distance) {
            $min_distance = $distance;
            $nearest_location_name = $location['location_name'];
        }
    }
    return ['distance' => round($min_distance), 'name' => $nearest_location_name];
}

function checkLocationStatus() {
    $user_lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $user_lon = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    if ($user_lat === null || $user_lon === null) {
        respondWithError('Coordonnées utilisateur non fournies.');
        return;
    }

    $nearest = findNearestWorkLocation($user_lat, $user_lon);
    if ($nearest === null) {
        respondWithSuccess('Aucun site de travail trouvé.', ['in_range' => false, 'message' => 'Aucun site de travail n\'est configuré.']);
        return;
    }

    $is_in_range = $nearest['distance'] <= MAX_DISTANCE_METERS;
    $message = $is_in_range
        ? "Vous êtes à portée ({$nearest['distance']}m de: {$nearest['name']})."
        : "Vous êtes trop loin ({$nearest['distance']}m). Le pointage est désactivé.";

    respondWithSuccess('Statut de localisation vérifié.', ['in_range' => $is_in_range, 'message' => $message]);
}

function recordTimeEntry($user_id, $type) {
    global $conn;

    $paris_tz = new DateTimeZone(APP_TIMEZONE);
    $current_time_for_sql = (new DateTime('now', $paris_tz))->format('Y-m-d H:i:s');
    $current_date_for_sql = (new DateTime('now', $paris_tz))->format('Y-m-d');

    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if ($latitude === null || $longitude === null) {
        respondWithError("Les coordonnées GPS sont requises pour pointer.");
        return;
    }

    $nearest = findNearestWorkLocation($latitude, $longitude);
    if ($nearest === null) {
        respondWithError("Aucun site de travail configuré. Impossible de pointer.");
        return;
    }
    if ($nearest['distance'] > MAX_DISTANCE_METERS) {
        respondWithError("Pointage refusé. Vous n'êtes pas sur un site de travail autorisé (à {$nearest['distance']}m).");
        return;
    }
    
    $distance_meters = $nearest['distance'];
    $location_name = $nearest['name'];
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : null;
    $logon_comment = isset($_POST['logon_comment']) ? trim($_POST['logon_comment']) : null;

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT timesheet_id, logon_time, logoff_time FROM Timesheet WHERE user_id = ? AND entry_date = ?");
        $stmt->execute([$user_id, $current_date_for_sql]);
        $existing_entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($type === 'logon') {
            if ($existing_entry && $existing_entry['logon_time'] !== null) {
                $conn->rollBack();
                respondWithError("Une entrée a déjà été enregistrée pour aujourd'hui.");
                return;
            }
            $stmt = $conn->prepare("INSERT INTO Timesheet (user_id, entry_date, logon_time, logon_distance_meters, logon_location_name, assignment_id, logon_comment) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $current_date_for_sql, $current_time_for_sql, $distance_meters, $location_name, $assignment_id, $logon_comment]);
            $message = "Entrée enregistrée avec succès.";
        } else if ($type === 'logoff') {
            if (!$existing_entry || $existing_entry['logon_time'] === null) {
                $conn->rollBack();
                respondWithError("Impossible d'enregistrer la sortie sans une entrée préalable.");
                return;
            }
            if ($existing_entry['logoff_time'] !== null) {
                $conn->rollBack();
                respondWithError("Une sortie a déjà été enregistrée pour aujourd'hui.");
                return;
            }
            $stmt = $conn->prepare("UPDATE Timesheet SET logoff_time = ?, logoff_distance_meters = ?, logoff_location_name = ? WHERE timesheet_id = ?");
            $stmt->execute([$current_time_for_sql, $distance_meters, $location_name, $existing_entry['timesheet_id']]);
            $message = "Sortie enregistrée avec succès.";
        }
        $conn->commit();
        respondWithSuccess($message);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        respondWithError('Database error: ' . $e->getMessage());
    }
}

function getTimesheetHistory($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT
                            t.timesheet_id, t.entry_date, t.logon_time, t.logon_location_name,
                            t.logoff_time, t.logoff_location_name, t.break_minutes,
                            t.logon_comment, pa.mission_text
                        FROM Timesheet t
                        LEFT JOIN Planning_Assignments pa ON t.assignment_id = pa.assignment_id
                        WHERE t.user_id = ?
                        ORDER BY t.entry_date DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted_history = [];
        foreach ($history as $entry) {
            $duration = '';
            if ($entry['logon_time'] && $entry['logoff_time']) {
                $logon_dt = new DateTime($entry['logon_time']);
                $logoff_dt = new DateTime($entry['logoff_time']);
                $interval = $logon_dt->diff($logoff_dt);
                $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                $effective_minutes = $total_minutes - ($entry['break_minutes'] ?? 0);
                if ($effective_minutes < 0) $effective_minutes = 0;
                $hours = floor($effective_minutes / 60);
                $minutes = $effective_minutes % 60;
                $duration = sprintf('%dh%02d', $hours, $minutes);
            }

            $formatted_history[] = [
    'date' => (new DateTime($entry['entry_date']))->format('d/m/Y'),
    'logon_time' => $entry['logon_time'] ? (new DateTime($entry['logon_time']))->format('H:i') : '--:--',
    'logon_location_name' => $entry['logon_location_name'] ?? 'N/A',
    'logoff_time' => $entry['logoff_time'] ? (new DateTime($entry['logoff_time']))->format('H:i') : '--:--',
    'logoff_location_name' => $entry['logoff_location_name'] ?? 'N/A',
    'break_minutes' => $entry['break_minutes'] ?? 0,
    'duration' => $duration,
    'mission' => $entry['mission_text'] ?? null,
    'comment' => $entry['logon_comment'] ?? null
];
        }
        respondWithSuccess('History retrieved successfully', $formatted_history);
    } catch (Exception $e) {
        respondWithError('Processing error: ' . $e->getMessage());
    }
}

function addBreak($user_id) {
    global $conn;
    $current_date_for_sql = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
    $break_minutes = isset($_POST['break_minutes']) ? intval($_POST['break_minutes']) : 0;
    if (!in_array($break_minutes, [30, 60])) { respondWithError('Invalid break duration specified.'); return; }
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT timesheet_id FROM Timesheet WHERE user_id = ? AND entry_date = ? AND logon_time IS NOT NULL AND logoff_time IS NULL");
        $stmt->execute([$user_id, $current_date_for_sql]);
        $existing_entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing_entry) { $conn->rollBack(); respondWithError("Impossible d'ajouter une pause. Aucun pointage d'entrée actif trouvé."); return; }
        $stmt = $conn->prepare("UPDATE Timesheet SET break_minutes = ? WHERE timesheet_id = ?");
        $stmt->execute([$break_minutes, $existing_entry['timesheet_id']]);
        $conn->commit();
        respondWithSuccess("Pause de {$break_minutes} minutes ajoutée avec succès.");
    } catch(PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        respondWithError('Database error: ' . $e->getMessage());
    }
}

function getLatestEntryStatus($user_id) {
    global $conn;
    $current_date_for_sql = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
    try {
        $stmt = $conn->prepare("SELECT logon_time, logoff_time FROM Timesheet WHERE user_id = ? AND entry_date = ?");
        $stmt->execute([$user_id, $current_date_for_sql]);
        $latest_entry = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = [
            'has_entry' => $latest_entry && $latest_entry['logon_time'] !== null,
            'has_exit' => $latest_entry && $latest_entry['logoff_time'] !== null
        ];
        respondWithSuccess('Latest entry status retrieved successfully', $status);
    } catch(PDOException $e) {
        respondWithError('Database error: ' . $e->getMessage());
    }
}

function respondWithSuccess($message, $data = []) {
    header('Content-Type: application/json'); echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]); exit;
}
function respondWithError($message) {
    header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $message]); exit;
}
?>
