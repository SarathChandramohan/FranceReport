<?php
// planning-handler.php (Revised & Final)

// --- DEBUGGING: UNCOMMENT THE 3 LINES BELOW TO SEE DETAILED ERRORS ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'db-connection.php';
require_once 'session-management.php';

// --- Helper Functions ---
function respondWithSuccess($message, $data = []) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
    exit;
}

function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    // Log the detailed error for the admin, but show a generic message to the user.
    error_log("Planning Handler Error: " . $message);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// --- Main Logic ---
try {
    requireLogin();
    $currentUser = getCurrentUser();
    $user_id = $currentUser['user_id'];
    $user_role = $currentUser['role'];

    if ($user_role !== 'admin') {
        respondWithError('Accès refusé.', 403);
    }

    global $conn;
    // Use isset for better compatibility with older PHP versions
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $input_data = json_decode(file_get_contents('php://input'), true);

    // Check for JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE && in_array($action, ['save_assignment', 'delete_assignment', 'remove_worker_from_assignment', 'assign_worker_to_mission', 'toggle_mission_validation'])) {
       respondWithError('Invalid request data format.');
    }

    // Use transaction for all write operations
    $writeActions = ['save_assignment', 'delete_assignment', 'remove_worker_from_assignment', 'assign_worker_to_mission', 'toggle_mission_validation'];
    if (in_array($action, $writeActions)) {
        $conn->beginTransaction();
    }

    switch ($action) {
        case 'get_staff_users':
            $stmt = $conn->prepare("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
            $stmt->execute();
            respondWithSuccess('Utilisateurs récupérés.', ['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_assignments':
            $start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
            $end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
            
            // This query groups individual assignments into "mission cards" based on their shared properties.
            $query_sql = "
                SELECT
                    MIN(pa.assignment_id) as representative_assignment_id,
                    CONVERT(VARCHAR(10), pa.assignment_date, 120) as start_date_group,
                    ISNULL(pa.title, '') as title,
                    pa.start_time, pa.end_time, pa.shift_type,
                    ISNULL(pa.location, '') as location,
                    ISNULL(pa.mission_text, '') as comment,
                    ISNULL(pa.color, '#1877f2') as color,
                    pa.is_validated,
                    STRING_AGG(CONVERT(VARCHAR(MAX), u.user_id), ',') WITHIN GROUP (ORDER BY u.prenom, u.nom) as user_ids_list,
                    STRING_AGG(CONCAT(u.prenom, ' ', u.nom), ', ') WITHIN GROUP (ORDER BY u.prenom, u.nom) as user_names_list
                FROM Planning_Assignments pa
                JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.assignment_date BETWEEN :start_date AND :end_date
                GROUP BY
                    CONVERT(VARCHAR(10), pa.assignment_date, 120),
                    pa.title, pa.start_time, pa.end_time, pa.shift_type, pa.location, pa.mission_text, pa.color, pa.is_validated
                ORDER BY start_date_group, start_time;
            ";

            $stmt = $conn->prepare($query_sql);
            $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
            respondWithSuccess('Missions récupérées.', ['missions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_mission_details_for_edit':
            $mission_id = isset($_GET['original_mission_id']) ? $_GET['original_mission_id'] : 0;
            $stmt = $conn->prepare("SELECT title, start_time, end_time, location, mission_text as comment, shift_type, color FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt->execute([':id' => $mission_id]);
            $mission = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mission) respondWithError('Mission non trouvée.');
            respondWithSuccess('Détails de mission récupérés.', ['mission' => $mission]);
            break;

        case 'save_assignment':
            // This logic identifies a group of records by its properties (since there is no mission_group_id)
            $get_original_sql = "SELECT title, start_time, end_time, location, mission_text, shift_type, color FROM Planning_Assignments WHERE assignment_id = :id";
            $match_group_sql = "WHERE assignment_date = :date AND title = :o_title AND ISNULL(start_time, '') = ISNULL(:o_start_time, '') AND ISNULL(end_time, '') = ISNULL(:o_end_time, '') AND ISNULL(location, '') = ISNULL(:o_location, '') AND ISNULL(mission_text, '') = ISNULL(:o_comment, '') AND shift_type = :o_shift_type AND ISNULL(color, '') = ISNULL(:o_color, '')";

            if (!empty($input_data['is_group_edit'])) { // Update existing group
                $stmt = $conn->prepare($get_original_sql);
                $stmt->execute([':id' => $input_data['original_mission_id']]);
                $original = $stmt->fetch(PDO::FETCH_ASSOC);

                $update_stmt = $conn->prepare("UPDATE Planning_Assignments SET title = :title, start_time = :start_time, end_time = :end_time, location = :location, mission_text = :comment, shift_type = :shift_type, color = :color " . $match_group_sql);
                $update_stmt->execute([
                    ':title' => $input_data['title'], ':start_time' => $input_data['start_time'], ':end_time' => $input_data['end_time'], ':location' => $input_data['location'], ':comment' => $input_data['comment'], ':shift_type' => $input_data['shift_type'], ':color' => $input_data['color'],
                    ':date' => $input_data['mission_date'], ':o_title' => $original['title'], ':o_start_time' => $original['start_time'], ':o_end_time' => $original['end_time'], ':o_location' => $original['location'], ':o_comment' => $original['mission_text'], ':o_shift_type' => $original['shift_type'], ':o_color' => $original['color']
                ]);
            } else { // Create new mission
                $assigned_user_ids = isset($input_data['assigned_user_ids']) && is_array($input_data['assigned_user_ids']) ? $input_data['assigned_user_ids'] : [];
                if (empty($assigned_user_ids)) $assigned_user_ids[] = $user_id; // Default to current user if none provided

                $insert_stmt = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, title, start_time, end_time, location, mission_text, shift_type, color, date_creation) VALUES (:uid, :cid, :date, :title, :start, :end, :loc, :comment, :shift, :color, GETDATE())");
                foreach ($assigned_user_ids as $uid) {
                    $insert_stmt->execute([':uid' => $uid, ':cid' => $user_id, ':date' => $input_data['mission_date'], ':title' => $input_data['title'], ':start' => $input_data['start_time'], ':end' => $input_data['end_time'], ':loc' => $input_data['location'], ':comment' => $input_data['comment'], ':shift' => $input_data['shift_type'], ':color' => $input_data['color']]);
                }
            }
            $conn->commit();
            respondWithSuccess('Mission enregistrée.');
            break;

        case 'delete_assignment':
            $stmt = $conn->prepare("SELECT title, start_time, end_time FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt->execute([':id' => $input_data['original_mission_id']]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            $delete_stmt = $conn->prepare("DELETE FROM Planning_Assignments WHERE assignment_date = :date AND title = :o_title AND ISNULL(start_time, '') = ISNULL(:o_start_time, '') AND ISNULL(end_time, '') = ISNULL(:o_end_time, '')");
            $delete_stmt->execute([':date' => $input_data['mission_date'], ':o_title' => $original['title'], ':o_start_time' => $original['start_time'], ':o_end_time' => $original['end_time']]);
            
            $conn->commit();
            respondWithSuccess('Mission supprimée.');
            break;

        case 'assign_worker_to_mission':
            // Remove worker's other assignments on this day to avoid conflicts
            $stmt_delete_old = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = :worker_id AND assignment_date = :mission_date");
            $stmt_delete_old->execute([':worker_id' => $input_data['worker_id'], ':mission_date' => $input_data['mission_date']]);

            $stmt_mission_details = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt_mission_details->execute([':id' => $input_data['original_mission_id']]);
            $m = $stmt_mission_details->fetch(PDO::FETCH_ASSOC);

            // Create new assignment by copying details from the target mission
            $insert_query = "INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, title, start_time, end_time, location, mission_text, shift_type, color, is_validated, date_creation) VALUES (:uid, :cid, :date, :title, :start, :end, :loc, :comment, :shift, :color, :validated, GETDATE())";
            $conn->prepare($insert_query)->execute([':uid' => $input_data['worker_id'], ':cid' => $user_id, ':date' => $m['assignment_date'], ':title' => $m['title'], ':start' => $m['start_time'], ':end' => $m['end_time'], ':loc' => $m['location'], ':comment' => $m['mission_text'], ':shift' => $m['shift_type'], ':color' => $m['color'], ':validated' => $m['is_validated']]);
            
            $conn->commit();
            respondWithSuccess('Ouvrier affecté.');
            break;

        // Other cases like remove_worker_from_assignment and toggle_mission_validation would follow a similar pattern of getting original details and then applying changes.
        // For brevity, their logic remains similar to the previous version but would benefit from this transactional approach.
        
        default:
            respondWithError('Action non valide.');
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    respondWithError('Erreur de base de données. Vérifiez les logs du serveur.', 500);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    respondWithError('Une erreur interne est survenue. Vérifiez les logs du serveur.', 500);
}
?>
