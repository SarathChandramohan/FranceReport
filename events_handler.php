<?php
// 1. Include session management and check login status
require_once 'session-management.php';
requireLogin(); // Ensure user is logged in

// 2. Include database connection
require_once 'db-connection.php'; // Uses your existing PDO connection $conn

// 3. Get current user details
$currentUser = getCurrentUser();
$currentUserId = $currentUser['user_id'];

// 4. Set the content type to JSON
header('Content-Type: application/json');

// 5. Check if action is set
if (!isset($_REQUEST['action'])) { // Use $_REQUEST to handle GET or POST
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
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                 echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
                 exit;
            }
            createEvent($conn, $currentUserId);
            break;
        case 'update_event':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                 echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
                 exit;
            }
            updateEvent($conn, $currentUserId);
            break;
        case 'delete_event':
             if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                 echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
                 exit;
            }
            deleteEvent($conn, $currentUserId);
            break;
        case 'get_users':
            getUsers($conn);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non reconnue']);
            break;
    }
} catch (PDOException $e) {
    error_log("Events Handler DB Error: " . $e->getMessage()); // Log the detailed error
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']); // Inform user of DB error
} catch (Exception $e) {
    error_log("Events Handler Error: " . $e->getMessage()); // Log other errors
    echo json_encode(['status' => 'error', 'message' => 'Une erreur s\'est produite.']);
}


// Function to fetch events
function getEvents($conn) {
    if (!isset($_GET['start']) || !isset($_GET['end'])) {
        echo json_encode(['status' => 'error', 'message' => 'Dates de début et de fin requises']);
        return;
    }

    try {
        $startDate = new DateTime($_GET['start']);
        $endDate = new DateTime($_GET['end']);
    } catch (Exception $e) {
         echo json_encode(['status' => 'error', 'message' => 'Format de date invalide.']);
         return;
    }

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
            // Convert comma-separated string of IDs to an array of integers
            $assignedUserIds = array_map('intval', explode(', ', $row['assigned_user_ids']));
        }
        $events[] = [
            'id'            => $row['event_id'],
            'title'         => $row['title'],
            'start'         => (new DateTime($row['start_datetime']))->format(DateTime::ATOM),
            'end'           => (new DateTime($row['end_datetime']))->format(DateTime::ATOM),
            'color'         => $row['color'] ?: '#2563eb',
            'extendedProps' => [
                'description'       => $row['description'],
                'assigned_user_ids' => $assignedUserIds,
                'creator_id'        => $row['creator_user_id']
            ]
        ];
    }
    echo json_encode($events);
}


// Function to create an event
function createEvent($conn, $creatorUserId) {
    $requiredFields = ['title', 'start_datetime', 'end_datetime', 'assigned_users'];
    foreach ($requiredFields as $field) {
        if ($field === 'assigned_users' && (!isset($_POST[$field]) || !is_array($_POST[$field]) || empty($_POST[$field]))) {
             echo json_encode(['status' => 'error', 'message' => "Veuillez assigner l'événement à au moins un utilisateur."]);
             return;
        } elseif (empty($_POST[$field])) {
            echo json_encode(['status' => 'error', 'message' => "Champ manquant: {$field}"]);
            return;
        }
    }

    $start_datetime = new DateTime($_POST['start_datetime']);
    $end_datetime = new DateTime($_POST['end_datetime']);

    if ($end_datetime <= $start_datetime) {
         echo json_encode(['status' => 'error', 'message' => "La date/heure de fin doit être postérieure à la date/heure de début."]);
         return;
    }

    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $startStr = $start_datetime->format('Y-m-d H:i:s');
    $endStr = $end_datetime->format('Y-m-d H:i:s');
    $color = !empty($_POST['color']) ? $_POST['color'] : '#2563eb';
    $assigned_user_ids = $_POST['assigned_users'];

    $conn->beginTransaction();
    try {
        $sqlEvent = "INSERT INTO Events (title, description, start_datetime, end_datetime, color, creator_user_id)
                     VALUES (:title, :description, :start_datetime, :end_datetime, :color, :creator_user_id)";
        $stmtEvent = $conn->prepare($sqlEvent);
        $stmtEvent->execute([
            ':title' => $title,
            ':description' => $description,
            ':start_datetime' => $startStr,
            ':end_datetime' => $endStr,
            ':color' => $color,
            ':creator_user_id' => $creatorUserId
        ]);
        $eventId = $conn->lastInsertId();

        $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (:event_id, :user_id)";
        $stmtAssign = $conn->prepare($sqlAssign);
        foreach ($assigned_user_ids as $userId) {
            $stmtAssign->execute([':event_id' => $eventId, ':user_id' => $userId]);
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Événement créé avec succès', 'event_id' => $eventId]);
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Create Event Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la création de l\'événement.']);
    }
}

// Function to update an event
function updateEvent($conn, $currentUserId) {
    // Basic validation
    if (empty($_POST['event_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID de l\'événement manquant.']);
        return;
    }
    // You can add more validation here as in createEvent

    $eventId = $_POST['event_id'];
    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $startStr = (new DateTime($_POST['start_datetime']))->format('Y-m-d H:i:s');
    $endStr = (new DateTime($_POST['end_datetime']))->format('Y-m-d H:i:s');
    $color = !empty($_POST['color']) ? $_POST['color'] : '#2563eb';
    $assigned_user_ids = $_POST['assigned_users'];

    $conn->beginTransaction();
    try {
        // Update event details
        $sqlEvent = "UPDATE Events SET title = :title, description = :description, start_datetime = :start, end_datetime = :end, color = :color
                     WHERE event_id = :event_id";
        $stmtEvent = $conn->prepare($sqlEvent);
        $stmtEvent->execute([
            ':title' => $title,
            ':description' => $description,
            ':start' => $startStr,
            ':end' => $endStr,
            ':color' => $color,
            ':event_id' => $eventId
        ]);

        // Resync assigned users: delete old, insert new
        $stmtDelete = $conn->prepare("DELETE FROM Event_AssignedUsers WHERE event_id = :event_id");
        $stmtDelete->execute([':event_id' => $eventId]);

        $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (:event_id, :user_id)";
        $stmtAssign = $conn->prepare($sqlAssign);
        foreach ($assigned_user_ids as $userId) {
            $stmtAssign->execute([':event_id' => $eventId, ':user_id' => $userId]);
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Événement mis à jour avec succès']);

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Update Event Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour de l\'événement.']);
    }
}

// Function to delete an event
function deleteEvent($conn, $currentUserId) {
    if (empty($_POST['event_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID de l\'événement manquant.']);
        return;
    }
    $eventId = $_POST['event_id'];

    $conn->beginTransaction();
    try {
        // First, delete from the linking table
        $stmtDeleteAssign = $conn->prepare("DELETE FROM Event_AssignedUsers WHERE event_id = :event_id");
        $stmtDeleteAssign->execute([':event_id' => $eventId]);

        // Then, delete the event itself
        $stmtDeleteEvent = $conn->prepare("DELETE FROM Events WHERE event_id = :event_id");
        $stmtDeleteEvent->execute([':event_id' => $eventId]);
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Événement supprimé avec succès']);
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Delete Event Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la suppression de l\'événement.']);
    }
}


// Function to get users
function getUsers($conn) {
    $query = "SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $users]);
}
?>
