<?php
// 1. Include session management and check login status
require_once 'session-management.php';
requireLogin();

// 2. Include database connection
require_once 'db-connection.php';

// 3. Get current user details
$currentUser = getCurrentUser();
$currentUserId = $currentUser['user_id'];

// 4. Set the content type to JSON
header('Content-Type: application/json');

// 5. Check if action is set
if (!isset($_REQUEST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune action spécifiée']);
    exit;
}

// 6. Handle different actions
$action = $_REQUEST['action'];

try {
    switch ($action) {
        case 'get_events':
            getEvents($conn);
            break;
        case 'create_event':
            createEvent($conn, $currentUserId);
            break;
        case 'update_event':
            updateEvent($conn);
            break;
        case 'delete_event':
            deleteEvent($conn);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non reconnue']);
            break;
    }
} catch (PDOException $e) {
    error_log("Events Handler DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
} catch (Exception $e) {
    error_log("Events Handler General Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne s\'est produite.']);
}

function getEvents($conn) {
    if (!isset($_GET['start']) || !isset($_GET['end'])) {
        echo json_encode(['status' => 'error', 'message' => 'Dates de début et de fin requises']);
        return;
    }

    $startDate = new DateTime($_GET['start']);
    $endDate = new DateTime($_GET['end']);
    $startDateTimeStr = $startDate->format('Y-m-d H:i:s');
    $endDateTimeStr = $endDate->format('Y-m-d H:i:s');

    $query = "
        SELECT
            e.event_id, e.title, e.description, e.start_datetime, e.end_datetime, e.color, e.creator_user_id,
            STUFF((
                SELECT ', ' + CAST(u.user_id AS NVARCHAR(10))
                FROM Event_AssignedUsers eau
                JOIN Users u ON eau.user_id = u.user_id
                WHERE eau.event_id = e.event_id
                FOR XML PATH('')
            ), 1, 2, '') AS assigned_user_ids
        FROM Events e
        WHERE (e.start_datetime < :end_datetime) AND (e.end_datetime > :start_datetime)
        ORDER BY e.start_datetime ASC;
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_datetime', $startDateTimeStr, PDO::PARAM_STR);
    $stmt->bindParam(':end_datetime', $endDateTimeStr, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($results as $row) {
        $assignedUserIds = [];
        if (!empty($row['assigned_user_ids'])) {
            $assignedUserIds = array_map('intval', explode(', ', $row['assigned_user_ids']));
        }
        $events[] = [
            'id'            => $row['event_id'],
            'title'         => $row['title'],
            'start'         => (new DateTime($row['start_datetime']))->format(DateTime::ATOM),
            'end'           => (new DateTime($row['end_datetime']))->format(DateTime::ATOM),
            'color'         => $row['color'] ?: '#4f46e5',
            'extendedProps' => [
                'description'       => $row['description'],
                'assigned_user_ids' => $assignedUserIds,
                'creator_id'        => $row['creator_user_id']
            ]
        ];
    }
    echo json_encode($events);
}

function assignEventToAllActiveUsers($conn, $eventId) {
    // Fetch all active user IDs
    $stmtUsers = $conn->query("SELECT user_id FROM Users WHERE status = 'Active'");
    $activeUserIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    // Assign the event to each active user
    $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (:event_id, :user_id)";
    $stmtAssign = $conn->prepare($sqlAssign);
    foreach ($activeUserIds as $userId) {
        $stmtAssign->execute([':event_id' => $eventId, ':user_id' => $userId]);
    }
}


function createEvent($conn, $creatorUserId) {
    if (empty($_POST['title']) || empty($_POST['start_datetime']) || empty($_POST['end_datetime'])) {
        echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs requis.']);
        return;
    }

    $start_datetime = new DateTime($_POST['start_datetime']);
    $end_datetime = new DateTime($_POST['end_datetime']);

    if ($end_datetime <= $start_datetime) {
        echo json_encode(['status' => 'error', 'message' => "La date de fin doit être postérieure à la date de début."]);
        return;
    }

    $conn->beginTransaction();
    $sqlEvent = "INSERT INTO Events (title, description, start_datetime, end_datetime, color, creator_user_id)
                 VALUES (:title, :description, :start_datetime, :end_datetime, :color, :creator_user_id)";
    $stmtEvent = $conn->prepare($sqlEvent);
    $stmtEvent->execute([
        ':title' => trim($_POST['title']),
        ':description' => trim($_POST['description']) ?: null,
        ':start_datetime' => $start_datetime->format('Y-m-d H:i:s'),
        ':end_datetime' => $end_datetime->format('Y-m-d H:i:s'),
        ':color' => $_POST['color'] ?: '#4f46e5',
        ':creator_user_id' => $creatorUserId
    ]);
    $eventId = $conn->lastInsertId();

    // New: Assign the event to all active users
    assignEventToAllActiveUsers($conn, $eventId);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Événement créé avec succès', 'event_id' => $eventId]);
}

function updateEvent($conn) {
    if (empty($_POST['event_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID de l\'événement manquant.']);
        return;
    }
    
    $eventId = $_POST['event_id'];

    $conn->beginTransaction();
    $sqlEvent = "UPDATE Events SET title = :title, description = :description, start_datetime = :start, end_datetime = :end, color = :color WHERE event_id = :event_id";
    $stmtEvent = $conn->prepare($sqlEvent);
    $stmtEvent->execute([
        ':title' => trim($_POST['title']),
        ':description' => trim($_POST['description']) ?: null,
        ':start' => (new DateTime($_POST['start_datetime']))->format('Y-m-d H:i:s'),
        ':end' => (new DateTime($_POST['end_datetime']))->format('Y-m-d H:i:s'),
        ':color' => $_POST['color'] ?: '#4f46e5',
        ':event_id' => $eventId
    ]);

    // First, remove all existing assignments for this event
    $stmtDelete = $conn->prepare("DELETE FROM Event_AssignedUsers WHERE event_id = :event_id");
    $stmtDelete->execute([':event_id' => $eventId]);

    // New: Re-assign the event to all active users
    assignEventToAllActiveUsers($conn, $eventId);
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Événement mis à jour avec succès']);
}

function deleteEvent($conn) {
    if (empty($_POST['event_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID de l\'événement manquant.']);
        return;
    }
    $eventId = $_POST['event_id'];
    $conn->beginTransaction();
    $stmtDeleteAssign = $conn->prepare("DELETE FROM Event_AssignedUsers WHERE event_id = :event_id");
    $stmtDeleteAssign->execute([':event_id' => $eventId]);
    $stmtDeleteEvent = $conn->prepare("DELETE FROM Events WHERE event_id = :event_id");
    $stmtDeleteEvent->execute([':event_id' => $eventId]);
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Événement supprimé avec succès']);
}
?>
