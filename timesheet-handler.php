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
    default:
        respondWithError('Invalid action specified');
}

/**
 * Records a time entry (either logon or logoff) in the database using Paris time
 *
 * @param int $user_id The user ID
 * @param string $type Either 'logon' or 'logoff'
 */
function recordTimeEntry($user_id, $type) {
    global $conn;

    // Get current time in Paris timezone
    $paris_tz = new DateTimeZone(APP_TIMEZONE);
    $current_paris_time = new DateTime('now', $paris_tz);
    $current_time_for_sql = $current_paris_time->format('Y-m-d H:i:s');
    $current_date_for_sql = $current_paris_time->format('Y-m-d');

    // Get geolocation data
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    $address = isset($_POST['address']) ? $_POST['address'] : null;

    // Sanitize inputs
    $latitude = $latitude !== null ? floatval($latitude) : null;
    $longitude = $longitude !== null ? floatval($longitude) : null;
    $address = $address !== null ? htmlspecialchars($address) : null;

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT timesheet_id, logon_time, logoff_time FROM Timesheet
                                WHERE user_id = ? AND entry_date = ?");
        $stmt->execute([$user_id, $current_date_for_sql]);
        $existing_entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($type === 'logon') {
            if ($existing_entry && $existing_entry['logon_time'] !== null) {
                $conn->rollBack();
                respondWithError("Une entrée a déjà été enregistrée pour aujourd'hui.");
                return;
            }

            if ($existing_entry) {
                 $stmt = $conn->prepare("UPDATE Timesheet
                                        SET logon_time = ?,
                                            logon_latitude = ?,
                                            logon_longitude = ?,
                                            logon_address = ?
                                        WHERE timesheet_id = ?");
                $stmt->execute([
                    $current_time_for_sql,
                    $latitude,
                    $longitude,
                    $address,
                    $existing_entry['timesheet_id']
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO Timesheet
                                        (user_id, entry_date, logon_time,
                                         logon_latitude, logon_longitude, logon_address)
                                        VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    $current_date_for_sql,
                    $current_time_for_sql,
                    $latitude,
                    $longitude,
                    $address
                ]);
            }
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

            $stmt = $conn->prepare("UPDATE Timesheet
                                    SET logoff_time = ?,
                                        logoff_latitude = ?,
                                        logoff_longitude = ?,
                                        logoff_address = ?
                                    WHERE timesheet_id = ?");
            $stmt->execute([
                $current_time_for_sql,
                $latitude,
                $longitude,
                $address,
                $existing_entry['timesheet_id']
            ]);
            $message = "Sortie enregistrée avec succès.";
        }

        $conn->commit();
        respondWithSuccess($message, [
            'timesheet_id' => $existing_entry ? $existing_entry['timesheet_id'] : $conn->lastInsertId(),
            'timestamp' => $current_time_for_sql, // This timestamp is Paris time
            'type' => $type
        ]);

    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Database error: ' . $e->getMessage());
    } catch(Exception $e) { // Catch potential DateTime exceptions
        respondWithError('Time processing error: ' . $e->getMessage());
    }
}

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
 * Gets the timesheet history for a user, assuming times are stored in Paris time
 *
 * @param int $user_id The user ID
 */
function getTimesheetHistory($user_id) {
    global $conn;
    $paris_tz = new DateTimeZone(APP_TIMEZONE); // For creating DateTime objects correctly

    try {
        $stmt = $conn->prepare("SELECT
                                    timesheet_id, entry_date,
                                    logon_time, logon_latitude, logon_longitude, logon_address,
                                    logoff_time, logoff_latitude, logoff_longitude, logoff_address,
                                    break_minutes
                                FROM Timesheet
                                WHERE user_id = ?
                                ORDER BY entry_date DESC, logon_time DESC
                                OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted_history = [];
        foreach ($history as $entry) {
            $duration = '';
            $break_minutes_val = $entry['break_minutes'] ?? 0;

            if ($entry['logon_time'] && $entry['logoff_time']) {
                 // Times are stored in Paris time, create DateTime objects accordingly
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

            // Format times for display (they are already Paris time, just format H:i)
            $logon_time_display = $entry['logon_time'] ? (new DateTime($entry['logon_time'], $paris_tz))->format('H:i') : '--:--';
            $logoff_time_display = $entry['logoff_time'] ? (new DateTime($entry['logoff_time'], $paris_tz))->format('H:i') : '--:--';

            $logon_location = !empty($entry['logon_address']) ? $entry['logon_address'] : 'Non enregistré';
            $logoff_location = !empty($entry['logoff_address']) ? $entry['logoff_address'] : 'Non enregistré';

            // entry_date is stored as Paris date
            $date_obj_for_formatting = new DateTime($entry['entry_date']);
            $formatted_date = $date_obj_for_formatting->format('d/m/Y');

            $formatted_history[] = [
                'id' => $entry['timesheet_id'],
                'date' => $formatted_date,
                'logon_time' => $logon_time_display,
                'logon_location' => $logon_location,
                'logoff_time' => $logoff_time_display,
                'logoff_location' => $logoff_location,
                'logoff_coords' => ['lat' => $entry['logoff_latitude'], 'lon' => $entry['logoff_longitude']],
                'break_minutes' => $break_minutes_val,
                'duration' => $duration
            ];
        }
        respondWithSuccess('History retrieved successfully', $formatted_history);

    } catch(PDOException $e) {
        respondWithError('Database error: ' . $e->getMessage());
    } catch(Exception $e) {
        respondWithError('Time processing error: ' . $e->getMessage());
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
