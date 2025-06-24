<?php
// planning-handler.php (Fixed)

require_once 'db-connection.php';
require_once 'session-management.php';

// --- Helper Functions ---
function respondWithSuccess($message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
    exit;
}

function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
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
    $action = $_REQUEST['action'] ?? '';
    $input_data = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'get_staff_users':
            $stmt = $conn->prepare("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
            $stmt->execute();
            respondWithSuccess('Utilisateurs récupérés.', ['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_all_assignments_for_workers':
            $stmt = $conn->prepare("SELECT assignment_date, assigned_user_id, title, shift_type FROM Planning_Assignments ORDER BY assignment_date");
            $stmt->execute();
            respondWithSuccess('Affectations récupérées.', ['assignments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_assignments':
            $start_date = $_GET['start'] ?? date('Y-m-01');
            $end_date = $_GET['end'] ?? date('Y-m-t');
            
            // --- CRITICAL FIX: Corrected query to use `title` and added `comment` (mission_text) ---
            $query_sql = "
                SELECT
                    MIN(pa.assignment_id) as representative_assignment_id,
                    CONVERT(VARCHAR(10), pa.assignment_date, 120) as start_date_group,
                    ISNULL(pa.title, '') as title,
                    pa.start_time,
                    pa.end_time,
                    pa.shift_type,
                    ISNULL(pa.location, '') as location,
                    ISNULL(pa.mission_text, '') as comment,
                    ISNULL(pa.color, '#1877f2') as color,
                    pa.is_validated,
                    STRING_AGG(CONVERT(VARCHAR(10), u.user_id), ',') WITHIN GROUP (ORDER BY u.prenom, u.nom) as user_ids_list,
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
            $mission_id = $_GET['original_mission_id'] ?? 0;
            $stmt = $conn->prepare("SELECT title, start_time, end_time, location, mission_text as comment, shift_type, color FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt->execute([':id' => $mission_id]);
            $mission = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mission) respondWithError('Mission non trouvée.');
            respondWithSuccess('Détails de mission récupérés.', ['mission' => $mission]);
            break;

        case 'save_assignment':
            $conn->beginTransaction();
            if (isset($input_data['is_group_edit']) && $input_data['is_group_edit']) { // Update existing group
                $stmt = $conn->prepare("SELECT title, start_time, end_time, location, mission_text, shift_type, color FROM Planning_Assignments WHERE assignment_id = :id");
                $stmt->execute([':id' => $input_data['original_mission_id']]);
                $original = $stmt->fetch(PDO::FETCH_ASSOC);

                $update_stmt = $conn->prepare("UPDATE Planning_Assignments SET title = :title, start_time = :start_time, end_time = :end_time, location = :location, mission_text = :comment, shift_type = :shift_type, color = :color WHERE assignment_date = :date AND title = :o_title AND ISNULL(start_time, '') = ISNULL(:o_start_time, '') AND ISNULL(end_time, '') = ISNULL(:o_end_time, '') AND ISNULL(location, '') = ISNULL(:o_location, '') AND ISNULL(mission_text, '') = ISNULL(:o_comment, '') AND shift_type = :o_shift_type AND ISNULL(color, '') = ISNULL(:o_color, '')");
                $update_stmt->execute([
                    ':title' => $input_data['title'], ':start_time' => $input_data['start_time'], ':end_time' => $input_data['end_time'], ':location' => $input_data['location'], ':comment' => $input_data['comment'], ':shift_type' => $input_data['shift_type'], ':color' => $input_data['color'],
                    ':date' => $input_data['mission_date'], ':o_title' => $original['title'], ':o_start_time' => $original['start_time'], ':o_end_time' => $original['end_time'], ':o_location' => $original['location'], ':o_comment' => $original['mission_text'], ':o_shift_type' => $original['shift_type'], ':o_color' => $original['color']
                ]);
            } else { // Create new mission
                $assigned_user_ids = $input_data['assigned_user_ids'] ?? [];
                if (empty($assigned_user_ids)) $assigned_user_ids[] = $user_id; // Assign to admin if no one is specified

                $insert_stmt = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, title, start_time, end_time, location, mission_text, shift_type, color, date_creation) VALUES (:uid, :cid, :date, :title, :start, :end, :loc, :comment, :shift, :color, GETDATE())");
                foreach ($assigned_user_ids as $uid) {
                    $insert_stmt->execute([':uid' => $uid, ':cid' => $user_id, ':date' => $input_data['mission_date'], ':title' => $input_data['title'], ':start' => $input_data['start_time'], ':end' => $input_data['end_time'], ':loc' => $input_data['location'], ':comment' => $input_data['comment'], ':shift' => $input_data['shift_type'], ':color' => $input_data['color']]);
                }
            }
            $conn->commit();
            respondWithSuccess('Mission enregistrée avec succès.');
            break;

        case 'delete_assignment':
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT title, start_time, end_time, location, mission_text, shift_type, color FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt->execute([':id' => $input_data['original_mission_id']]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            $delete_stmt = $conn->prepare("DELETE FROM Planning_Assignments WHERE assignment_date = :date AND title = :o_title AND ISNULL(start_time, '') = ISNULL(:o_start_time, '') AND ISNULL(end_time, '') = ISNULL(:o_end_time, '') AND ISNULL(location, '') = ISNULL(:o_location, '') AND ISNULL(mission_text, '') = ISNULL(:o_comment, '') AND shift_type = :o_shift_type AND ISNULL(color, '') = ISNULL(:o_color, '')");
            $delete_stmt->execute([':date' => $input_data['mission_date'], ':o_title' => $original['title'], ':o_start_time' => $original['start_time'], ':o_end_time' => $original['end_time'], ':o_location' => $original['location'], ':o_comment' => $original['mission_text'], ':o_shift_type' => $original['shift_type'], ':o_color' => $original['color']]);
            
            $conn->commit();
            respondWithSuccess('Mission supprimée avec succès.');
            break;

        case 'remove_worker_from_assignment':
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT title, start_time, end_time, location, mission_text, shift_type, color FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt->execute([':id' => $input_data['original_mission_id']]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            $delete_stmt = $conn->prepare("DELETE FROM Planning_Assignments WHERE assignment_date = :date AND assigned_user_id = :worker_id AND title = :o_title AND ISNULL(start_time, '') = ISNULL(:o_start_time, '') AND ISNULL(end_time, '') = ISNULL(:o_end_time, '')");
            $delete_stmt->execute([':date' => $input_data['mission_date'], ':worker_id' => $input_data['worker_id'], ':o_title' => $original['title'], ':o_start_time' => $original['start_time'], ':o_end_time' => $original['end_time']]);
            
            $conn->commit();
            respondWithSuccess('Ouvrier retiré avec succès.');
            break;
        
        case 'assign_worker_to_mission':
            $conn->beginTransaction();
            // Remove any existing assignment for this worker on this day to prevent duplicates
            $stmt_delete_old = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = :worker_id AND assignment_date = :mission_date");
            $stmt_delete_old->execute([':worker_id' => $input_data['worker_id'], ':mission_date' => $input_data['mission_date']]);

            // Get target mission details
            $stmt_mission_details = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt_mission_details->execute([':id' => $input_data['original_mission_id']]);
            $mission_details = $stmt_mission_details->fetch(PDO::FETCH_ASSOC);

            // Create new assignment for the worker by copying details
            $insert_query = "INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, title, start_time, end_time, location, mission_text, shift_type, color, is_validated, date_creation) VALUES (:uid, :cid, :date, :title, :start, :end, :loc, :comment, :shift, :color, :validated, GETDATE())";
            $stmt_insert = $conn->prepare($insert_query);
            $stmt_insert->execute([
                ':uid' => $input_data['worker_id'], ':cid' => $user_id, ':date' => $mission_details['assignment_date'], ':title' => $mission_details['title'], ':start' => $mission_details['start_time'], ':end' => $mission_details['end_time'],
                ':loc' => $mission_details['location'], ':comment' => $mission_details['mission_text'], ':shift' => $mission_details['shift_type'], ':color' => $mission_details['color'], ':validated' => $mission_details['is_validated']
            ]);
            $conn->commit();
            respondWithSuccess('Ouvrier affecté avec succès.');
            break;

        case 'toggle_mission_validation':
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT is_validated, title, start_time, end_time FROM Planning_Assignments WHERE assignment_id = :id");
            $stmt->execute([':id' => $input_data['original_mission_id']]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_status = !$original['is_validated'];

            $update_stmt = $conn->prepare("UPDATE Planning_Assignments SET is_validated = :status WHERE assignment_date = :date AND title = :o_title AND ISNULL(start_time, '') = ISNULL(:o_start_time, '') AND ISNULL(end_time, '') = ISNULL(:o_end_time, '')");
            $update_stmt->execute([':status' => $new_status, ':date' => $input_data['mission_date'], ':o_title' => $original['title'], ':o_start_time' => $original['start_time'], ':o_end_time' => $original['end_time']]);
            $conn->commit();
            respondWithSuccess('Statut de validation mis à jour.');
            break;

        default:
            respondWithError('Action non valide spécifiée.');
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("DB Error in planning-handler: " . $e->getMessage());
    respondWithError('Erreur de base de données: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("General Error in planning-handler: " . $e->getMessage());
    respondWithError('Une erreur interne est survenue.', 500);
}
?>
