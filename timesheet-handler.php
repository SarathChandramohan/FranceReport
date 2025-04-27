<?php
// timesheet-handler.php - Handles all AJAX requests for timesheet operations

// Include database connection
require_once 'db-connection.php';
require_once 'session-management.php';

// Ensure user is logged in
requireLogin();

// Get the current user ID
$user = getCurrentUser();
$user_id = $user['id'];

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
    case 'get_history':
        getTimesheetHistory($user_id);
        break;
    default:
        respondWithError('Invalid action specified');
}

/**
 * Records a time entry (either logon or logoff) in the database
 * 
 * @param int $user_id The user ID
 * @param string $type Either 'logon' or 'logoff'
 */
function recordTimeEntry($user_id, $type) {
    global $conn;
    
    // Get current time
    $current_time = date('Y-m-d H:i:s');
    $current_date = date('Y-m-d');
    
    // Get geolocation data
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    $address = isset($_POST['address']) ? $_POST['address'] : null;
    
    // Sanitize inputs
    $latitude = $latitude !== null ? floatval($latitude) : null;
    $longitude = $longitude !== null ? floatval($longitude) : null;
    $address = $address !== null ? htmlspecialchars($address) : null;
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        if ($type === 'logon') {
            // Check if there's already an entry for today
            $stmt = $conn->prepare("SELECT timesheet_id FROM Timesheet 
                                    WHERE user_id = ? AND entry_date = ?");
            $stmt->execute([$user_id, $current_date]);
            $existing_entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_entry) {
                // Update existing entry
                $stmt = $conn->prepare("UPDATE Timesheet 
                                        SET logon_time = ?, 
                                            logon_latitude = ?, 
                                            logon_longitude = ?, 
                                            logon_address = ? 
                                        WHERE timesheet_id = ?");
                $stmt->execute([
                    $current_time, 
                    $latitude, 
                    $longitude, 
                    $address, 
                    $existing_entry['timesheet_id']
                ]);
                
                $timesheet_id = $existing_entry['timesheet_id'];
                $message = "Entrée mise à jour avec succès.";
            } else {
                // Create new entry
                $stmt = $conn->prepare("INSERT INTO Timesheet 
                                        (user_id, entry_date, logon_time, 
                                         logon_latitude, logon_longitude, logon_address) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id, 
                    $current_date, 
                    $current_time, 
                    $latitude, 
                    $longitude, 
                    $address
                ]);
                
                $timesheet_id = $conn->lastInsertId();
                $message = "Entrée enregistrée avec succès.";
            }
        } else if ($type === 'logoff') {
            // Check if there's an entry for today
            $stmt = $conn->prepare("SELECT timesheet_id FROM Timesheet 
                                    WHERE user_id = ? AND entry_date = ?");
            $stmt->execute([$user_id, $current_date]);
            $existing_entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_entry) {
                // Update existing entry with logoff info
                $stmt = $conn->prepare("UPDATE Timesheet 
                                        SET logoff_time = ?, 
                                            logoff_latitude = ?, 
                                            logoff_longitude = ?, 
                                            logoff_address = ? 
                                        WHERE timesheet_id = ?");
                $stmt->execute([
                    $current_time, 
                    $latitude, 
                    $longitude, 
                    $address, 
                    $existing_entry['timesheet_id']
                ]);
                
                $timesheet_id = $existing_entry['timesheet_id'];
                $message = "Sortie enregistrée avec succès.";
            } else {
                // No entry for today, create one with just logoff info
                $stmt = $conn->prepare("INSERT INTO Timesheet 
                                        (user_id, entry_date, logoff_time, 
                                         logoff_latitude, logoff_longitude, logoff_address) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id, 
                    $current_date, 
                    $current_time, 
                    $latitude, 
                    $longitude, 
                    $address
                ]);
                
                $timesheet_id = $conn->lastInsertId();
                $message = "Sortie enregistrée avec succès sans entrée correspondante.";
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        respondWithSuccess($message, [
            'timesheet_id' => $timesheet_id,
            'timestamp' => $current_time,
            'type' => $type
        ]);
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Database error: ' . $e->getMessage());
    }
}

/**
 * Gets the timesheet history for a user
 * 
 * @param int $user_id The user ID
 */
function getTimesheetHistory($user_id) {
    global $conn;
    
    try {
        // Prepare SQL to get last 10 entries
        $stmt = $conn->prepare("SELECT 
                                    timesheet_id,
                                    entry_date,
                                    logon_time,
                                    logon_latitude,
                                    logon_longitude,
                                    logon_address,
                                    logoff_time,
                                    logoff_latitude,
                                    logoff_longitude,
                                    logoff_address,
                                    DATEDIFF(MINUTE, logon_time, logoff_time) as duration_minutes
                                FROM Timesheet
                                WHERE user_id = ?
                                ORDER BY entry_date DESC, logon_time DESC
                                OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY");
        
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data for display
        $formatted_history = [];
        foreach ($history as $entry) {
            // Calculate duration in hours and minutes if both timestamps exist
            $duration = '';
            if ($entry['logon_time'] && $entry['logoff_time']) {
                $minutes = $entry['duration_minutes'];
                if ($minutes > 0) {
                    $hours = floor($minutes / 60);
                    $remaining_minutes = $minutes % 60;
                    $duration = $hours . 'h' . ($remaining_minutes < 10 ? '0' : '') . $remaining_minutes;
                }
            }
            
            // Format timestamps
            $logon_time = $entry['logon_time'] ? date('H:i', strtotime($entry['logon_time'])) : '--:--';
            $logoff_time = $entry['logoff_time'] ? date('H:i', strtotime($entry['logoff_time'])) : '--:--';
            
            // Get location information
            $logon_location = !empty($entry['logon_address']) ? $entry['logon_address'] : 'Non enregistré';
            $logoff_location = !empty($entry['logoff_address']) ? $entry['logoff_address'] : 'Non enregistré';
            
            // Format date for display
            $formatted_date = date('d/m/Y', strtotime($entry['entry_date']));
            
            $formatted_history[] = [
                'id' => $entry['timesheet_id'],
                'date' => $formatted_date,
                'logon_time' => $logon_time,
                'logon_location' => $logon_location,
                'logon_coords' => [
                    'lat' => $entry['logon_latitude'],
                    'lon' => $entry['logon_longitude']
                ],
                'logoff_time' => $logoff_time,
                'logoff_location' => $logoff_location,
                'logoff_coords' => [
                    'lat' => $entry['logoff_latitude'],
                    'lon' => $entry['logoff_longitude']
                ],
                'duration' => $duration
            ];
        }
        
        respondWithSuccess('History retrieved successfully', $formatted_history);
        
    } catch(PDOException $e) {
        respondWithError('Database error: ' . $e->getMessage());
    }
}

/**
 * Sends a success response
 * 
 * @param string $message Success message
 * @param array $data Optional data to include in the response
 */
function respondWithSuccess($message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Sends an error response
 * 
 * @param string $message Error message
 */
function respondWithError($message) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}
?>
