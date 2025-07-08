<?php
// seeplanning-handler.php

// Set the default timezone to ensure consistency
date_default_timezone_set('Europe/Paris');

// Include necessary files for database connection and session management
require_once 'db-connection.php';
require_once 'session-management.php';

// --- Helper Functions for JSON Response ---

/**
 * Sends a success response in JSON format.
 * @param string $message The success message.
 * @param array $data The data payload to send.
 * @param int $statusCode The HTTP status code.
 */
function respondWithSuccess($message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
    exit;
}

/**
 * Sends an error response in JSON format.
 * @param string $message The error message.
 * @param int $statusCode The HTTP status code.
 */
function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    error_log("SeePlanning Handler Error: " . $message);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// --- Main Logic ---
try {
    // Require the user to be logged in. This is the only security check needed for this handler.
    requireLogin();
    $currentUser = getCurrentUser();
    
    global $conn;
    $action = $_REQUEST['action'] ?? '';

    // This handler only supports one action.
    if ($action === 'get_user_planning') {
        $date = $_GET['date'] ?? null;
        getUserPlanning($conn, $currentUser['user_id'], $date);
    } else {
        respondWithError('Action non valide.', 400);
    }

} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}


/**
 * Fetches planning assignments for a specific user on a specific date.
 * @param PDO $conn The database connection object.
 * @param int $userId The ID of the logged-in user.
 * @param string|null $date The date to fetch planning for in 'Y-m-d' format.
 */
function getUserPlanning($conn, $userId, $date) {
    if (empty($date)) {
        respondWithError('Date non spécifiée.');
    }

    // This query first finds the unique mission groups for the user on a given day,
    // then aggregates the details for those missions.
    $sql = "
        WITH UserMissions AS (
            SELECT DISTINCT mission_group_id
            FROM Planning_Assignments
            WHERE assigned_user_id = ? AND assignment_date = ?
        )
        SELECT
            m.mission_group_id,
            m.mission_text,
            m.comments,
            m.location,
            m.start_time,
            m.end_time,
            m.color,
            (
                SELECT STRING_AGG(u.prenom + ' ' + u.nom, ', ')
                FROM Planning_Assignments pa
                JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.mission_group_id = m.mission_group_id
            ) as assigned_user_names,
            (
                SELECT STRING_AGG(i.asset_name, ', ')
                FROM Bookings b
                JOIN Inventory i ON b.asset_id = i.asset_id
                WHERE b.mission_group_id = m.mission_group_id
            ) as assigned_asset_names
        FROM Planning_Assignments m
        WHERE m.mission_group_id IN (SELECT mission_group_id FROM UserMissions)
        GROUP BY m.mission_group_id, m.mission_text, m.comments, m.location, m.start_time, m.end_time, m.color
        ORDER BY m.start_time;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $date]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess('Planning de l\'utilisateur récupéré.', $missions);
}

?>
