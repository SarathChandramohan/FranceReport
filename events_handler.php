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
            // Ensure this is a POST request for creation
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


// Function to fetch events within a date range for FullCalendar
function getEvents($conn) {
    if (!isset($_GET['start']) || !isset($_GET['end'])) {
        echo json_encode(['status' => 'error', 'message' => 'Dates de début et de fin requises par FullCalendar']);
        return;
    }

    try {
        $startDate = new DateTime($_GET['start']);
        $endDate = new DateTime($_GET['end']);
    } catch (Exception $e) {
         echo json_encode(['status' => 'error', 'message' => 'Format de date invalide reçu de FullCalendar.']);
         return;
    }

    $startDateTimeStr = $startDate->format('Y-m-d H:i:s');
    $endDateTimeStr = $endDate->format('Y-m-d H:i:s');

    $query = "
        SELECT
            e.event_id,
            e.title,
            e.description,
            e.start_datetime,
            e.end_datetime,
            e.color,
            e.creator_user_id,
            STUFF((
                SELECT ', ' + CAST(u.user_id AS NVARCHAR(10)) + ':' + u.prenom + ' ' + u.nom
                FROM Event_AssignedUsers eau
                JOIN Users u ON eau.user_id = u.user_id
                WHERE eau.event_id = e.event_id
                ORDER BY u.nom, u.prenom
                FOR XML PATH('')
            ), 1, 2, '') AS assigned_users_info
        FROM
            Events e
        WHERE
            (e.start_datetime < :end_datetime) AND (e.end_datetime > :start_datetime)
        ORDER BY
            e.start_datetime ASC;
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_datetime', $startDateTimeStr, PDO::PARAM_STR);
    $stmt->bindParam(':end_datetime', $endDateTimeStr, PDO::PARAM_STR);

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($results as $row) {
        $startDt = new DateTime($row['start_datetime']);
        $endDt = new DateTime($row['end_datetime']);

        $assignedUsers = [];
        $assignedUserIds = [];
         if (!empty($row['assigned_users_info'])) {
             $usersInfo = explode(', ', $row['assigned_users_info']);
             foreach ($usersInfo as $userInfo) {
                if (strpos($userInfo, ':') !== false) {
                    list($userId, $userName) = explode(':', $userInfo, 2);
                    $userIdInt = (int)$userId;
                    $assignedUsers[] = [
                        'user_id' => $userIdInt,
                        'name' => $userName
                    ];
                    $assignedUserIds[] = $userIdInt;
                }
             }
         }

        $events[] = [
            'id'        => $row['event_id'],
            'title'     => $row['title'],
            'start'     => $startDt->format(DateTime::ATOM),
            'end'       => $endDt->format(DateTime::ATOM),
            'color'     => $row['color'] ?: '#007bff',
            'extendedProps' => [
                'description'   => $row['description'],
                'assigned_users'=> $assignedUsers,
                'assigned_user_ids' => $assignedUserIds,
                'creator_id'    => $row['creator_user_id']
            ]
        ];
    }

    echo json_encode($events);
}


// Function to create a new event with multiple assigned users
function createEvent($conn, $creatorUserId) {
    $requiredFields = ['title', 'start_datetime', 'end_datetime', 'assigned_users'];
    foreach ($requiredFields as $field) {
        if ($field === 'assigned_users') {
            if (!isset($_POST[$field]) || !is_array($_POST[$field]) || empty($_POST[$field])) {
                 echo json_encode(['status' => 'error', 'message' => "Veuillez assigner l'événement à au moins un utilisateur."]);
                 return;
            }
        } elseif (empty($_POST[$field])) {
            echo json_encode(['status' => 'error', 'message' => "Champ manquant: {$field}"]);
            return;
        }
    }

    try {
        $start_datetime = new DateTime($_POST['start_datetime']);
        $end_datetime = new DateTime($_POST['end_datetime']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => "Format de date/heure invalide."]);
        return;
    }


    if ($end_datetime <= $start_datetime) {
         echo json_encode(['status' => 'error', 'message' => "La date/heure de fin doit être postérieure à la date/heure de début."]);
         return;
    }

    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $startStr = $start_datetime->format('Y-m-d H:i:s');
    $endStr = $end_datetime->format('Y-m-d H:i:s');
    $color = !empty($_POST['color']) ? $_POST['color'] : '#007bff';
    $assigned_user_ids = $_POST['assigned_users'];

    $conn->beginTransaction();

    try {
        $sqlEvent = "INSERT INTO Events (title, description, start_datetime, end_datetime, color, creator_user_id)
                     VALUES (:title, :description, :start_datetime, :end_datetime, :color, :creator_user_id)";

        $stmtEvent = $conn->prepare($sqlEvent);

        $stmtEvent->bindParam(':title', $title, PDO::PARAM_STR);
        $stmtEvent->bindParam(':description', $description, PDO::PARAM_STR);
        $stmtEvent->bindParam(':start_datetime', $startStr, PDO::PARAM_STR);
        $stmtEvent->bindParam(':end_datetime', $endStr, PDO::PARAM_STR);
        $stmtEvent->bindParam(':color', $color, PDO::PARAM_STR);
        $stmtEvent->bindParam(':creator_user_id', $creatorUserId, PDO::PARAM_INT);

        $stmtEvent->execute();
        $eventId = $conn->lastInsertId();

        $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (:event_id, :user_id)";
        $stmtAssign = $conn->prepare($sqlAssign);

        foreach ($assigned_user_ids as $userId) {
            if (!empty($userId) && is_numeric($userId)) {
                 $stmtAssign->bindParam(':event_id', $eventId, PDO::PARAM_INT);
                 $stmtAssign->bindParam(':user_id', $userId, PDO::PARAM_INT);
                 $stmtAssign->execute();
            }
        }

        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Événement créé avec succès', 'event_id' => $eventId]);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Create Event Transaction Error: " . $e->getMessage());
         echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la création de l\'événement dans la base de données.']);
    }
}

function updateEvent($conn, $currentUserId) {
    $eventId = $_POST['event_id'];

    $requiredFields = ['title', 'start_datetime', 'end_datetime', 'assigned_users'];
    foreach ($requiredFields as $field) {
        if ($field === 'assigned_users') {
            if (!isset($_POST[$field]) || !is_array($_POST[$field]) || empty($_POST[$field])) {
                 echo json_encode(['status' => 'error', 'message' => "Veuillez assigner l'événement à au moins un utilisateur."]);
                 return;
            }
        } elseif (empty($_POST[$field])) {
            echo json_encode(['status' => 'error', 'message' => "Champ manquant: {$field}"]);
            return;
        }
    }

    try {
        $start_datetime = new DateTime($_POST['start_datetime']);
        $end_datetime = new DateTime($_POST['end_datetime']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => "Format de date/heure invalide."]);
        return;
    }


    if ($end_datetime <= $start_datetime) {
         echo json_encode(['status' => 'error', 'message' => "La date/heure de fin doit être postérieure à la date/heure de début."]);
         return;
    }

    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $startStr = $start_datetime->format('Y-m-d H:i:s');
    $endStr = $end_datetime->format('Y-m-d H:i:s');
    $color = !empty($_POST['color']) ? $_POST['color'] : '#007bff';
    $assigned_user_ids = $_POST['assigned_users'];

    $conn->beginTransaction();

    try {
        $sqlEvent = "UPDATE Events SET 
                        title = :title, 
                        description = :description, 
                        start_datetime = :start_datetime, 
                        end_datetime = :end_datetime, 
                        color = :color 
                     WHERE event_id = :event_id";
        
        $stmtEvent = $conn->prepare($sqlEvent);
        $stmtEvent->bindParam(':title', $title, PDO::PARAM_STR);
        $stmtEvent->bindParam(':description', $description, PDO::PARAM_STR);
        $stmtEvent->bindParam(':start_datetime', $startStr, PDO::PARAM_STR);
        $stmtEvent->bindParam(':end_datetime', $endStr, PDO::PARAM_STR);
        $stmtEvent->bindParam(':color', $color, PDO::PARAM_STR);
        $stmtEvent->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmtEvent->execute();

        $sqlDeleteAssignments = "DELETE FROM Event_AssignedUsers WHERE event_id = :event_id";
        $stmtDelete = $conn->prepare($sqlDeleteAssignments);
        $stmtDelete->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmtDelete->execute();

        $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (:event_id, :user_id)";
        $stmtAssign = $conn->prepare($sqlAssign);

        foreach ($assigned_user_ids as $userId) {
            if (!empty($userId) && is_numeric($userId)) {
                 $stmtAssign->bindParam(':event_id', $eventId, PDO::PARAM_INT);
                 $stmtAssign->bindParam(':user_id', $userId, PDO::PARAM_INT);
                 $stmtAssign->execute();
            }
        }

        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Événement mis à jour avec succès']);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Update Event Transaction Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour de l\'événement.']);
    }
}

function deleteEvent($conn, $currentUserId) {
    $eventId = $_POST['event_id'];
    $conn->beginTransaction();
    try {
        $sqlDeleteAssignments = "DELETE FROM Event_AssignedUsers WHERE event_id = :event_id";
        $stmtDelete = $conn->prepare($sqlDeleteAssignments);
        $stmtDelete->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmtDelete->execute();

        $sqlEvent = "DELETE FROM Events WHERE event_id = :event_id";
        $stmtEvent = $conn->prepare($sqlEvent);
        $stmtEvent->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmtEvent->execute();
        
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Événement supprimé avec succès']);
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Delete Event Transaction Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la suppression de l\'événement.']);
    }
}

function getUsers($conn) {
    $query = "SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $users]);
}

?>
