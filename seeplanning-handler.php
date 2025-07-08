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
 * This version uses a more optimized query with Common Table Expressions (CTEs)
 * to avoid correlated subqueries and improve performance.
 *
 * @param PDO $conn The database connection object.
 * @param int $userId The ID of the logged-in user.
 * @param string|null $date The date to fetch planning for in 'Y-m-d' format.
 */
function getUserPlanning($conn, $userId, $date) {
    if (empty($date)) {
        respondWithError('Date non spécifiée.');
    }

    // This optimized query builds the data in steps for clarity and performance.
    $sql = "
        WITH UserMissions AS (
            -- Step 1: Find the mission groups for the current user and date.
            SELECT DISTINCT mission_group_id
            FROM Planning_Assignments
            WHERE assigned_user_id = ? AND assignment_date = ?
        ),
        AggregatedUsers AS (
            -- Step 2: For those missions, get a unique, comma-separated list of team members.
            SELECT
                pa.mission_group_id,
                STRING_AGG(DISTINCT u.prenom + ' ' + u.nom, ', ') as assigned_user_names
            FROM Planning_Assignments pa
            JOIN Users u ON pa.assigned_user_id = u.user_id
            WHERE pa.mission_group_id IN (SELECT mission_group_id FROM UserMissions)
            GROUP BY pa.mission_group_id
        ),
        AggregatedAssets AS (
            -- Step 3: For those missions, get a unique, comma-separated list of assets.
            SELECT
                b.mission_group_id,
                STRING_AGG(DISTINCT i.asset_name, ', ') as assigned_asset_names
            FROM Bookings b
            JOIN Inventory i ON b.asset_id = i.asset_id
            WHERE b.mission_group_id IN (SELECT mission_group_id FROM UserMissions)
            GROUP BY b.mission_group_id
        )
        -- Step 4: Combine the unique mission details with the aggregated lists.
        SELECT
            m.mission_group_id,
            MAX(m.mission_text) as mission_text, -- Using MAX() ensures one value is returned per mission group.
            MAX(m.comments) as comments,
            MAX(m.location) as location,
            m.start_time,
            m.end_time,
            MAX(m.color) as color,
            u.assigned_user_names,
            a.assigned_asset_names
        FROM Planning_Assignments m
        LEFT JOIN AggregatedUsers u ON m.mission_group_id = u.mission_group_id
        LEFT JOIN AggregatedAssets a ON m.mission_group_id = a.mission_group_id
        WHERE m.mission_group_id IN (SELECT mission_group_id FROM UserMissions)
        GROUP BY
            m.mission_group_id,
            m.start_time,
            m.end_time,
            u.assigned_user_names,
            a.assigned_asset_names
        ORDER BY m.start_time;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $date]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess('Planning de l\'utilisateur récupéré.', $missions);
}

?>
