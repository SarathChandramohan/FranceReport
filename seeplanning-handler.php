<?php
// seeplanning-handler.php (Corrected)

date_default_timezone_set('Europe/Paris');

require_once 'db-connection.php';
require_once 'session-management.php';

function respondWithSuccess($message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
    exit;
}

function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    error_log("SeePlanning Handler Error: " . $message);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

try {
    requireLogin();
    $currentUser = getCurrentUser();
    
    global $conn;
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'get_user_planning':
            $date = $_GET['date'] ?? null;
            getUserPlanning($conn, $currentUser['user_id'], $date);
            break;
            
        // --- THIS IS THE NEW, REQUIRED ACTION ---
        case 'get_mission_dates':
            $year = $_GET['year'] ?? null;
            $month = $_GET['month'] ?? null;
            getMissionDates($conn, $currentUser['user_id'], $year, $month);
            break;
            
        default:
            respondWithError('Action non valide.', 400);
    }

} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}

/**
 * NEW FUNCTION: Fetches all dates within a given month that have validated missions for the user.
 */
function getMissionDates($conn, $userId, $year, $month) {
    if (empty($year) || empty($month)) {
        respondWithError('Année et mois non spécifiés.');
    }

    $sql = "SELECT DISTINCT FORMAT(assignment_date, 'yyyy-MM-dd') as mission_date
            FROM Planning_Assignments
            WHERE assigned_user_id = ? 
              AND YEAR(assignment_date) = ? 
              AND MONTH(assignment_date) = ?
              AND is_validated = 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $year, $month]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    respondWithSuccess('Dates des missions récupérées.', $dates);
}


/**
 * Fetches planning assignments for a specific user on a specific date. (No changes here)
 */
function getUserPlanning($conn, $userId, $date) {
    if (empty($date)) {
        respondWithError('Date non spécifiée.');
    }
    
    // Using a more robust query to correctly group missions and fetch all details.
    $sql = "
        WITH UserMissionGroups AS (
            -- Find the unique mission groups for the user on the given date that are validated
            SELECT DISTINCT mission_group_id
            FROM Planning_Assignments
            WHERE assigned_user_id = ? AND assignment_date = ? AND is_validated = 1
        )
        SELECT
            pa.mission_group_id,
            pa.mission_text,
            pa.comments,
            pa.location,
            pa.start_time,
            pa.end_time,
            pa.color,
            -- Aggregate team member names for the mission group
            (
                SELECT STRING_AGG(u.prenom + ' ' + u.nom, ', ')
                FROM Planning_Assignments pa_team
                JOIN Users u ON pa_team.assigned_user_id = u.user_id
                WHERE pa_team.mission_group_id = pa.mission_group_id
            ) as assigned_user_names,
            -- Aggregate asset names for the mission group
            (
                SELECT STRING_AGG(i.asset_name, ', ')
                FROM Bookings b
                JOIN Inventory i ON b.asset_id = i.asset_id
                WHERE b.mission_group_id = pa.mission_group_id
            ) as assigned_asset_names
        FROM Planning_Assignments pa
        WHERE pa.mission_group_id IN (SELECT mission_group_id FROM UserMissionGroups)
        GROUP BY pa.mission_group_id, pa.mission_text, pa.comments, pa.location, pa.start_time, pa.end_time, pa.color
        ORDER BY pa.start_time;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $date]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess('Planning de l\'utilisateur récupéré.', $missions);
}

?>
