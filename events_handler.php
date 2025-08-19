<?php
// events_handler.php

require_once 'session-management.php';
requireLogin();
require_once 'db-connection.php';
$currentUser = getCurrentUser();
header('Content-Type: application/json');

function respondWithSuccess($message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
    exit;
}

function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    error_log("Events Handler Error: " . $message);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

try {
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'get_event_dates':
            getEventDates($conn);
            break;
        case 'get_events_for_date':
            getEventsForDate($conn);
            break;
        // The other actions (create, update, delete) are preserved from your original file
        // and can be added here if needed for an admin view.
        default:
            respondWithError('Action non valide.', 400);
    }
} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}

/**
 * Fetches distinct dates within a given month that have events.
 * Used to show the dot indicator on the calendar.
 */
function getEventDates($conn) {
    $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
    $month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

    if (!$year || !$month) {
        respondWithError('Année et mois non spécifiés ou invalides.');
    }

    $sql = "SELECT DISTINCT FORMAT(start_datetime, 'yyyy-MM-dd') as event_date
            FROM Events
            WHERE YEAR(start_datetime) = ? AND MONTH(start_datetime) = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$year, $month]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    respondWithSuccess('Dates des événements récupérées.', $dates);
}

/**
 * Fetches full event details for a specific selected date.
 */
function getEventsForDate($conn) {
    $date = $_GET['date'] ?? null;
    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        respondWithError('Date non spécifiée ou format invalide.');
    }

    // This query now formats time as HH:mm to match the design
    $sql = "
        SELECT
            e.event_id,
            e.title,
            e.description,
            e.color,
            FORMAT(e.start_datetime, 'HH:mm') as start_time,
            FORMAT(e.end_datetime, 'HH:mm') as end_time
        FROM Events e
        WHERE CAST(e.start_datetime AS DATE) = ?
        ORDER BY e.start_datetime;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$date]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess('Événements du jour récupérés.', $events);
}

?>
