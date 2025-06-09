<?php
// timesheet-handler.php - Handles all AJAX requests for timesheet operations

// Include database connection
require_once 'db-connection.php';
require_once 'session-management.php';

// Ensure user is logged in
requireLogin();

// Get the current user ID
$user = getCurrentUser();
$user_id = $user['user_id'];

// Define the target timezone
define('APP_TIMEZONE', 'Europe/Paris');

// Get the action from the POST request
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Handle different actions
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
    default:
        respondWithError('Invalid action specified');
}

/**
 * Calculates the distance between two points on Earth in meters.
 *
 * @param float $lat1 Latitude of point 1
 * @param float $lon1 Longitude of point 1
 * @param float $lat2 Latitude of point 2
 * @param float $lon2 Longitude of point 2
 * @return float The distance in meters
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // Earth radius in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

/**
 * Finds the nearest work location and the distance to it.
 *
 * @param float $user_lat
 * @param float $user_lon
 * @return array|null ['distance' => float, 'name' => string] or null if no locations found
 */
function findNearestWorkLocation($user_lat, $user_lon) {
    global $conn;
    $stmt = $conn->prepare("SELECT latitude, longitude, location_name FROM WorkLocations WHERE is_active = 1");
    $stmt->execute();
    $work_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($work_locations)) {
        return null;
    }

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

/**
 * Checks the user's distance from the nearest work location.
 */
function checkLocationStatus() {
    $user_lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $user_lon = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if ($user_lat === null || $user_lon === null) {
        respondWithError('Coordonnées de l\'utilisateur non fournies.');
        return;
    }

    $nearest = findNearestWorkLocation($user_lat, $user_lon);

    if ($nearest === null) {
        respondWithSuccess('Aucun site de travail actif trouvé.', ['message' => 'Aucun site de travail n\'est configuré.']);
        return;
    }

    $message = "Vous êtes à {$nearest['distance']}m du site: {$nearest['name']}";
    respondWithSuccess('Distance calculée.', ['message' => $message]);
}


/**
 * Records a time entry and stores the distance from the nearest work site.
 *
 * @param int $user_id The user ID
 * @param string $type Either 'logon' or 'logoff'
 */
function recordTimeEntry($user_id, $type) {
    global $conn;

    $paris_tz = new DateTimeZone(APP_TIMEZONE);
    $current_paris_time = new DateTime('now', $paris_tz);
    $current_time_for_sql = $current_paris_time->format('Y-m-d H:i:s');
    $current_date_for_sql = $current_paris_time->format('Y-m-d');

    // Get geolocation data
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    $address = isset($_POST['address']) ? $_POST['address'] : null;

    $latitude = $latitude !== null ? floatval($latitude) : null;
    $longitude = $longitude !== null ? floatval($longitude) : null;
    $address = $address !== null ? htmlspecialchars($address) : null;

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

            // Calculate distance to nearest work location
            $distance_meters = null;
            if ($latitude !== null && $longitude !== null) {
                $nearest = findNearestWorkLocation($latitude, $longitude);
                if ($nearest !== null) {
                    $distance_meters = $nearest['distance'];
                }
            }

            $stmt = $conn->prepare(
                "INSERT INTO Timesheet (user_id, entry_date, logon_time, logon_latitude, logon_longitude, logon_address, logon_distance_meters)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $user_id,
                $current_date_for_sql,
                $current_time_for_sql,
                $latitude,
                $longitude,
                $address,
                $distance_meters
            ]);

            $message = "Entrée enregistrée avec succès.";

        } else if ($type === 'logoff') {
            if (!$existing_entry || $existing_entry['logon_time'] === null) {
                $conn->rollBack();
                respondWithError("Impossible d'enregistrer la sortie sans une entrée préalable pour aujourd'hui.");
                return;
            }
            if ($existing_entry['logoff_time'] !== null) {
                $conn->rollBack();
                respondWithError("Une sortie a déjà été enregistrée pour aujourd'hui.");
                return;
            }

            $stmt = $conn->prepare("UPDATE Timesheet SET logoff_time = ? WHERE timesheet_id = ?");
            $stmt->execute([$current_time_for_sql, $existing_entry['timesheet_id']]);
            $message = "Sortie enregistrée avec succès.";
        }

        $conn->commit();
        respondWithSuccess($message, [
            'timesheet_id' => $existing_entry ? $existing_entry['timesheet_id'] : $conn->lastInsertId(),
            'timestamp' => $current_time_for_sql,
            'type' => $type
        ]);

    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Database error: ' . $e->getMessage());
    }
}


/**
 * Gets the timesheet history for a user, including distance at logon.
 *
 * @param int $user_id The user ID
 */
function getTimesheetHistory($user_id) {
    global $conn;
    $paris_tz = new DateTimeZone(APP_TIMEZONE);

    try {
        $stmt = $conn->prepare("SELECT
                                    timesheet_id, entry_date,
                                    logon_time, logon_address, logon_distance_meters,
                                    logoff_time, break_minutes
                                FROM Timesheet
                                WHERE user_id = ?
                                ORDER BY entry_date DESC
                                OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted_history = [];
        foreach ($history as $entry) {
            $duration = '';
            $break_minutes_val = $entry['break_minutes'] ?? 0;

            if ($entry['logon_time'] && $entry['logoff_time']) {
                 $logon_datetime_paris = new DateTime($entry['logon_time'], $paris_tz);
                 $logoff_datetime_paris = new DateTime($entry['logoff_time'], $paris_tz);
                 $interval = $logon_datetime_paris->diff($logoff_datetime_paris);
                 $total_minutes_worked = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                 $effective_minutes_worked = $total_minutes_worked - $break_minutes_val;
                 if ($effective_minutes_worked < 0) $effective_minutes_worked = 0;
                 $hours = floor($effective_minutes_worked / 60);
                 $minutes = $effective_minutes_worked % 60;
                 $duration = sprintf('%dh%02d', $hours, $minutes);
            }

            $logon_time_display = $entry['logon_time'] ? (new DateTime($entry['logon_time'], $paris_tz))->format('H:i') : '--:--';
            $logoff_time_display = $entry['logoff_time'] ? (new DateTime($entry['logoff_time'], $paris_tz))->format('H:i') : '--:--';
            $logon_location = !empty($entry['logon_address']) ? $entry['logon_address'] : 'Non enregistré';
            
            $distance_display = '--';
            if ($entry['logon_distance_meters'] !== null) {
                $distance_display = $entry['logon_distance_meters'] > 1000
                    ? round($entry['logon_distance_meters'] / 1000, 2) . ' km'
                    : $entry['logon_distance_meters'] . ' m';
            }


            $formatted_history[] = [
                'id' => $entry['timesheet_id'],
                'date' => (new DateTime($entry['entry_date']))->format('d/m/Y'),
                'logon_time' => $logon_time_display,
                'logon_location' => $logon_location,
                'distance' => $distance_display,
                'logoff_time' => $logoff_time_display,
                'break_minutes' => $break_minutes_val,
                'duration' => $duration
            ];
        }
        respondWithSuccess('History retrieved successfully', $formatted_history);

    } catch(PDOException $e) {
        respondWithError('Database error: ' . $e->getMessage());
    }
}

// --- Other functions (addBreak, getLatestEntryStatus, respondWithSuccess, respondWithError) remain the same as in your original file ---
// Make sure to copy them here if they are not already present.

/**
 * Adds a break to the current day's timesheet entry (Paris time based)
 *
 * @param int $user_id The user ID
 */
function addBreak($user_id) {
    global $conn;

    $paris_tz = new DateTimeZone(APP_TIMEZONE);
    $current_paris_time = new DateTime('now', $paris_tz);
    $current_date_for_sql = $current_paris_time->format('Y-m-d');

    $break_minutes = isset($_POST['break_minutes']) ? intval($_POST['break_minutes']) : 0;

    if (!in_array($break_minutes, [30, 60])) {
        respondWithError('Invalid break duration specified.');
        return;
    }

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT timesheet_id FROM Timesheet
                                WHERE user_id = ? AND entry_date = ? AND logon_time IS NOT NULL AND logoff_time IS NULL");
        $stmt->execute([$user_id, $current_date_for_sql]);
        $existing_entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing_entry) {
            $conn->rollBack();
            respondWithError("Impossible d'ajouter une pause. Aucun pointage d'entrée actif trouvé pour aujourd'hui.");
            return;
        }

        $stmt = $conn->prepare("UPDATE Timesheet SET break_minutes = ? WHERE timesheet_id = ?");
        $stmt->execute([$break_minutes, $existing_entry['timesheet_id']]);
        $conn->commit();
        respondWithSuccess("Pause de {$break_minutes} minutes ajoutée avec succès.");

    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Database error: ' . $e->getMessage());
    }
}

/**
 * Gets the latest timesheet entry status for the current day (Paris time based)
 *
 * @param int $user_id The user ID
 */
function getLatestEntryStatus($user_id) {
    global $conn;
    $paris_tz = new DateTimeZone(APP_TIMEZONE);
    $current_paris_time = new DateTime('now', $paris_tz);
    $current_date_for_sql = $current_paris_time->format('Y-m-d');

    try {
        $stmt = $conn->prepare("SELECT logon_time, logoff_time FROM Timesheet
                                WHERE user_id = ? AND entry_date = ?");
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

/**
 * Sends a success response
 */
function respondWithSuccess($message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
    exit;
}

/**
 * Sends an error response
 */
function respondWithError($message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}
?>
