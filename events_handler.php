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
        case 'get_users':
            // Although users are fetched initially in events.php,
            // keeping this handler might be useful for future dynamic updates.
            getUsers($conn);
            break;
        // Add other actions like update_event, delete_event if needed later
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
    // FullCalendar sends start/end dates like 2025-04-27T00:00:00+05:30 or YYYY-MM-DD
    // Adjust parsing if timezone causes issues, but DateTime usually handles ISO 8601 well.
    if (!isset($_GET['start']) || !isset($_GET['end'])) {
        echo json_encode(['status' => 'error', 'message' => 'Dates de début et de fin requises par FullCalendar']);
        return;
    }

    try {
        // Use DateTime to handle various potential date formats from FullCalendar
        $startDate = new DateTime($_GET['start']);
        $endDate = new DateTime($_GET['end']);
    } catch (Exception $e) {
         echo json_encode(['status' => 'error', 'message' => 'Format de date invalide reçu de FullCalendar.']);
         return;
    }

    // Format dates for SQL Server WHERE clause
    $startDateTimeStr = $startDate->format('Y-m-d H:i:s');
    // For the end date, FullCalendar's 'end' is exclusive, but for overlap checking,
    // we typically want events ending exactly at the start time. Adjust if needed.
    $endDateTimeStr = $endDate->format('Y-m-d H:i:s');

    // Query to fetch events and aggregate assigned users
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
                ORDER BY u.nom, u.prenom -- Add ordering for consistency
                FOR XML PATH('')
            ), 1, 2, '') AS assigned_users_info
        FROM
            Events e
        WHERE
            (e.start_datetime < :end_datetime) AND (e.end_datetime > :start_datetime) -- Standard overlap check
        ORDER BY
            e.start_datetime ASC;
    ";

    $stmt = $conn->prepare($query);
    // Bind parameters using the formatted strings
    $stmt->bindParam(':start_datetime', $startDateTimeStr, PDO::PARAM_STR);
    $stmt->bindParam(':end_datetime', $endDateTimeStr, PDO::PARAM_STR);

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process results into FullCalendar event format
    $events = [];
    foreach ($results as $row) {
        $startDt = new DateTime($row['start_datetime']);
        $endDt = new DateTime($row['end_datetime']);

        // Parse assigned users
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
                    $assignedUserIds[] = $userIdInt; // Collect IDs for extendedProps
                }
             }
         }

        $events[] = [
            'id'        => $row['event_id'], // Use event_id as the FullCalendar event ID
            'title'     => $row['title'],
            'start'     => $startDt->format(DateTime::ATOM), // ISO 8601 for FullCalendar
            'end'       => $endDt->format(DateTime::ATOM),   // ISO 8601 for FullCalendar
            'color'     => $row['color'] ?: '#007bff',      // Provide default color if null
            'extendedProps' => [
                'description'   => $row['description'],
                'assigned_users'=> $assignedUsers, // Array of user objects
                'assigned_user_ids' => $assignedUserIds, // Array of user IDs only
                'creator_id'    => $row['creator_user_id']
                // Add any other custom data needed in the frontend here
            ]
        ];
    }

    // Return the processed events array directly (FullCalendar expects an array)
    echo json_encode($events);
}


// Function to create a new event with multiple assigned users (mostly unchanged)
function createEvent($conn, $creatorUserId) {
    // Basic validation - Add more as needed
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

    // Further validation (e.g., dates)
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

    // Prepare data for insertion
    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $startStr = $start_datetime->format('Y-m-d H:i:s');
    $endStr = $end_datetime->format('Y-m-d H:i:s');
    $color = !empty($_POST['color']) ? $_POST['color'] : '#007bff'; // Default if empty
    $assigned_user_ids = $_POST['assigned_users']; // Array of user IDs

    // Start a transaction
    $conn->beginTransaction();

    try {
        // Insert the event into the Events table
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

        // Insert entries into the Event_AssignedUsers linking table
        $sqlAssign = "INSERT INTO Event_AssignedUsers (event_id, user_id) VALUES (:event_id, :user_id)";
        $stmtAssign = $conn->prepare($sqlAssign);

        foreach ($assigned_user_ids as $userId) {
            if (!empty($userId) && is_numeric($userId)) {
                 $stmtAssign->bindParam(':event_id', $eventId, PDO::PARAM_INT);
                 $stmtAssign->bindParam(':user_id', $userId, PDO::PARAM_INT);
                 $stmtAssign->execute();
            } else {
                 error_log("Invalid user ID provided for event assignment: " . $userId);
                 // Optionally rollback or log and continue
            }
        }

        // Commit the transaction
        $conn->commit();

        // Important: Return the new event ID so the frontend could potentially focus on it
        echo json_encode(['status' => 'success', 'message' => 'Événement créé avec succès', 'event_id' => $eventId]);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Create Event Transaction Error: " . $e->getMessage());
         echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la création de l\'événement dans la base de données.']);
    } catch (Exception $e) {
         $conn->rollBack();
         error_log("Create Event Error: " . $e->getMessage());
         echo json_encode(['status' => 'error', 'message' => 'Une erreur s\'est produite lors de la création de l\'événement.']);
    }
}

// Function to get a list of active users for the dropdown (unchanged)
function getUsers($conn) {
    $query = "SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $users]);
}

?>
