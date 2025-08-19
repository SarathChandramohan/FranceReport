<?php
// events_handler.php

// 1. Setup Environment
require_once 'session-management.php';
requireLogin();
require_once 'db-connection.php';
$currentUser = getCurrentUser();
header('Content-Type: application/json');

// 2. Helper Functions for JSON responses
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

// 3. Main Action Router
try {
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        // --- NEW ACTIONS FOR THE REWORKED UI ---
        case 'get_event_dates':
            getEventDates($conn);
            break;
        case 'get_events_for_date':
            getEventsForDate($conn);
            break;

        // --- EXISTING ACTIONS (PRESERVED) ---
        case 'get_events':
            getEvents_FullCalendar($conn);
            break;
        case 'create_event':
            createEvent($conn, $currentUser['user_id']);
            break;
        case 'update_event':
            updateEvent($conn, $currentUser['user_id']);
            break;
        case 'delete_event':
            deleteEvent($conn, $currentUser['user_id']);
            break;
        case 'get_users':
            getUsers($conn);
            break;
        default:
            respondWithError('Action non valide.', 400);
    }
} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}


// --- NEW FUNCTIONS FOR REWORKED UI ---

/**
 * Fetches all dates within a given month that have events.
 */
function getEventDates($conn) {
    $year = $_GET['year'] ?? null;
    $month = $_GET['month'] ?? null;
    if (empty($year) || empty($month)) {
        respondWithError('Année et mois non spécifiés.');
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
 * Fetches event details for a specific date.
 */
function getEventsForDate($conn) {
    $date = $_GET['date'] ?? null;
    if (empty($date)) {
        respondWithError('Date non spécifiée.');
    }

    $sql = "
        SELECT
            e.event_id,
            e.title,
            e.description,
            e.color,
            FORMAT(e.start_datetime, 'HH:mm:ss') as start_time,
            FORMAT(e.end_datetime, 'HH:mm:ss') as end_time,
            (
                SELECT STRING_AGG(u.prenom + ' ' + u.nom, ', ')
                FROM Event_AssignedUsers eau
                JOIN Users u ON eau.user_id = u.user_id
                WHERE eau.event_id = e.event_id
            ) as assigned_user_names
        FROM Events e
        WHERE CAST(e.start_datetime AS DATE) = ?
        ORDER BY e.start_datetime;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$date]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess('Événements du jour récupérés.', $events);
}


// --- EXISTING FUNCTIONS (PRESERVED FOR COMPATIBILITY OR OTHER USES) ---
// (The original create_event, update_event, delete_event, get_users, and getEvents functions remain here)

function getEvents_FullCalendar($conn) {
    if (!isset($_GET['start']) || !isset($_GET['end'])) {
        respondWithError('Dates de début et de fin requises.');
    }
    $startDateTimeStr = (new DateTime($_GET['start']))->format('Y-m-d H:i:s');
    $endDateTimeStr = (new DateTime($_GET['end']))->format('Y-m-d H:i:s');

    $query = "
        SELECT e.event_id, e.title, e.description, e.start_datetime, e.end_datetime, e.color, e.creator_user_id,
               STUFF((SELECT ', ' + CAST(u.user_id AS NVARCHAR(10)) FROM Event_AssignedUsers eau JOIN Users u ON eau.user_id = u.user_id WHERE eau.event_id = e.event_id FOR XML PATH('')), 1, 2, '') AS assigned_user_ids_csv
        FROM Events e
        WHERE (e.start_datetime < :end_datetime) AND (e.end_datetime > :start_datetime)
        ORDER BY e.start_datetime ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':start_datetime' => $startDateTimeStr, ':end_datetime' => $endDateTimeStr]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($results as $row) {
        $events[] = [
            'id'        => $row['event_id'],
            'title'     => $row['title'],
            'start'     => (new DateTime($row['start_datetime']))->format(DateTime::ATOM),
            'end'       => (new DateTime($row['end_datetime']))->format(DateTime::ATOM),
            'color'     => $row['color'] ?: '#007bff',
            'extendedProps' => [
                'description'   => $row['description'],
                'assigned_user_ids' => $row['assigned_user_ids_csv'] ? explode(',', $row['assigned_user_ids_csv']) : [],
                'creator_id'    => $row['creator_user_id']
            ]
        ];
    }
    echo json_encode($events);
    exit;
}

function createEvent($conn, $creatorUserId) {
    // This function remains unchanged...
    $requiredFields = ['title', 'start_datetime', 'end_datetime', 'assigned_users'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || (is_array($_POST[$field]) ? empty($_POST[$field]) : trim($_POST[$field]) === '')) {
            respondWithError("Le champ '{$field}' est requis.");
        }
    }
    
    $start_datetime = new DateTime($_POST['start_datetime']);
    $end_datetime = new DateTime($_POST['end_datetime']);
    if ($end_datetime <= $start_datetime) {
        respondWithError("La date de fin doit être postérieure à la date de début.");
    }

    $conn->beginTransaction();
    $sqlEvent = "INSERT INTO Events (title, description, start_datetime, end_datetime, color, creator_user_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmtEvent = $conn->prepare($sqlEvent);
    $stmtEvent->execute([
        $_POST['title'],
        $_POST['description'] ?? null,
        $start_datetime->format('Y-m-d H:i:s'),
        $end_datetime->format('Y-m-d H:i:s'),
        $_POST['color'] ?? '#007bff',
        $creatorUserId
    ]);
    $eventId = $conn->lastInsertId();

    $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (?, ?)";
    $stmtAssign = $conn->prepare($sqlAssign);
    foreach ($_POST['assigned_users'] as $userId) {
        $stmtAssign->execute([$eventId, $userId]);
    }
    $conn->commit();
    
    respondWithSuccess('Événement créé avec succès.', ['event_id' => $eventId], 201);
}

function updateEvent($conn, $creatorUserId) {
    // This function remains unchanged...
     $requiredFields = ['event_id', 'title', 'start_datetime', 'end_datetime', 'assigned_users'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || (is_array($_POST[$field]) ? empty($_POST[$field]) : trim($_POST[$field]) === '')) {
            respondWithError("Le champ '{$field}' est requis.");
        }
    }

    $start_datetime = new DateTime($_POST['start_datetime']);
    $end_datetime = new DateTime($_POST['end_datetime']);
    if ($end_datetime <= $start_datetime) {
        respondWithError("La date de fin doit être postérieure à la date de début.");
    }
    
    $conn->beginTransaction();

    // Update event details
    $sqlEvent = "UPDATE Events SET title = ?, description = ?, start_datetime = ?, end_datetime = ?, color = ? WHERE event_id = ?";
    $stmtEvent = $conn->prepare($sqlEvent);
    $stmtEvent->execute([
        $_POST['title'],
        $_POST['description'] ?? null,
        $start_datetime->format('Y-m-d H:i:s'),
        $end_datetime->format('Y-m-d H:i:s'),
        $_POST['color'] ?? '#007bff',
        $_POST['event_id']
    ]);

    // Re-assign users
    $stmtDelete = $conn->prepare("DELETE FROM Event_AssignedUsers WHERE event_id = ?");
    $stmtDelete->execute([$_POST['event_id']]);

    $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (?, ?)";
    $stmtAssign = $conn->prepare($sqlAssign);
    foreach ($_POST['assigned_users'] as $userId) {
        $stmtAssign->execute([$_POST['event_id'], $userId]);
    }
    
    $conn->commit();
    respondWithSuccess('Événement mis à jour avec succès.');
}

function deleteEvent($conn, $creatorUserId) {
    // This function remains unchanged...
    $eventId = $_POST['event_id'] ?? null;
    if (!$eventId) {
        respondWithError("ID d'événement manquant.");
    }
    
    $conn->beginTransaction();
    
    // First, delete assignments from the junction table
    $stmtDeleteAssignments = $conn->prepare("DELETE FROM Event_AssignedUsers WHERE event_id = ?");
    $stmtDeleteAssignments->execute([$eventId]);

    // Then, delete the event itself
    $stmtDeleteEvent = $conn->prepare("DELETE FROM Events WHERE event_id = ?");
    $stmtDeleteEvent->execute([$eventId]);
    
    $conn->commit();
    
    respondWithSuccess('Événement supprimé avec succès.');
}

function getUsers($conn) {
    // This function remains unchanged...
    $stmt = $conn->query("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess('Utilisateurs récupérés.', $users);
}
