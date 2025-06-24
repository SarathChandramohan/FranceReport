<?php
// planning-handler.php (Corrected and Improved)

require_once 'db-connection.php';
require_once 'session-management.php';

// --- Helper Functions for JSON Response ---
function respondWithSuccess($message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
    exit;
}

function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    // Log the error for debugging
    error_log("Planning Handler Error: " . $message);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// --- Main Logic ---
try {
    requireLogin();
    $currentUser = getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        respondWithError('Accès refusé. Cette section est réservée aux administrateurs.', 403);
    }
    
    global $conn;
    $action = $_REQUEST['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {
        case 'get_initial_data':
            getInitialData($conn);
            break;
        case 'save_mission':
            saveMission($conn, $currentUser['user_id'], $input);
            break;
        case 'delete_mission_group':
            deleteMissionGroup($conn, $input);
            break;
        case 'assign_worker_to_mission':
            assignWorkerToMission($conn, $currentUser['user_id'], $input);
            break;
        case 'remove_worker_from_mission':
            removeWorkerFromMission($conn, $input);
            break;
        case 'toggle_mission_validation':
            toggleMissionValidation($conn, $input);
            break;
        default:
            respondWithError('Action non valide.', 400);
    }
} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}

/**
 * Fetches all necessary data for the initial page load:
 * staff users and assignments for a given date range.
 */
function getInitialData($conn) {
    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d', strtotime('+6 days'));

    // 1. Get Staff Users
    $stmt_users = $conn->prepare("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $stmt_users->execute();
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get Assignments grouped into "Missions"
    $stmt_missions = $conn->prepare("
        SELECT
            MIN(pa.assignment_id) as mission_id, -- A representative ID for the group
            pa.assignment_date,
            pa.mission_text,
            pa.location,
            pa.start_time,
            pa.end_time,
            pa.shift_type,
            pa.color,
            pa.is_validated,
            STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.nom) as assigned_user_ids,
            STRING_AGG(u.prenom + ' ' + u.nom, ', ') WITHIN GROUP (ORDER BY u.nom) as assigned_user_names
        FROM Planning_Assignments pa
        JOIN Users u ON pa.assigned_user_id = u.user_id
        WHERE pa.assignment_date BETWEEN ? AND ?
        GROUP BY
            pa.assignment_date, pa.mission_text, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated
        ORDER BY pa.assignment_date, pa.start_time
    ");
    $stmt_missions->execute([$start_date, $end_date]);
    $missions = $stmt_missions->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess('Données initiales chargées.', [
        'staff' => $users,
        'missions' => $missions
    ]);
}

/**
 * Creates a new mission or updates an existing one for all assigned workers.
 */
function saveMission($conn, $creator_id, $data) {
    $mission_id = $data['mission_id'] ?? null; // This is the representative ID of the group
    $mission_date = $data['assignment_date'];
    $assigned_users = $data['assigned_user_ids'] ?? [];

    // Basic validation
    if (empty($mission_date) || empty($data['mission_text'])) {
        respondWithError('La date et le titre de la mission sont obligatoires.');
    }
    
    $conn->beginTransaction();

    if ($mission_id) { // This is an UPDATE
        // 1. Get the original mission details to identify the group
        $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
        $stmt_orig->execute([$mission_id]);
        $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
        if (!$original_mission) {
            $conn->rollBack();
            respondWithError('Mission à mettre à jour non trouvée.');
        }

        // 2. Update all assignments that belong to this group on that specific date
        $stmt_update = $conn->prepare("
            UPDATE Planning_Assignments SET
                mission_text = ?, start_time = ?, end_time = ?, location = ?,
                shift_type = ?, color = ?
            WHERE
                assignment_date = ? AND mission_text = ? AND shift_type = ?
                AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00')
                AND ISNULL(location, '') = ISNULL(?, '')
        ");
        $stmt_update->execute([
            $data['mission_text'], $data['start_time'] ?: null, $data['end_time'] ?: null, $data['location'] ?: null,
            $data['shift_type'], $data['color'], $mission_date,
            $original_mission['mission_text'], $original_mission['shift_type'],
            $original_mission['start_time'], $original_mission['location']
        ]);

    } else { // This is a CREATE
        if (empty($assigned_users)) {
            respondWithError('Veuillez assigner au moins un ouvrier pour créer une mission.');
        }

        // Insert a new row for each assigned user
        $stmt_insert = $conn->prepare("
            INSERT INTO Planning_Assignments (
                assigned_user_id, creator_user_id, assignment_date, start_time, end_time,
                shift_type, mission_text, color, location, is_validated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        foreach ($assigned_users as $user_id) {
            // REMOVED: The logic that deletes other assignments for the day.
            // $stmt_delete_old = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND assignment_date = ?");
            // $stmt_delete_old->execute([$user_id, $mission_date]);

            // Insert the new assignment
            $stmt_insert->execute([
                $user_id, $creator_id, $mission_date, $data['start_time'] ?: null, $data['end_time'] ?: null,
                $data['shift_type'], $data['mission_text'], $data['color'], $data['location'] ?: null
            ]);
        }
    }
    $conn->commit();
    respondWithSuccess('Mission enregistrée avec succès.');
}

/**
 * Deletes an entire mission group based on its representative ID.
 */
function deleteMissionGroup($conn, $data) {
    $mission_id = $data['mission_id'];
    $conn->beginTransaction();
    
    // Get original details to identify the group
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$original_mission) {
        $conn->rollBack();
        respondWithError('Mission à supprimer non trouvée.');
    }

    // Delete all matching assignments
    $stmt_delete = $conn->prepare("
        DELETE FROM Planning_Assignments
        WHERE
            assignment_date = ? AND mission_text = ? AND shift_type = ?
            AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00')
            AND ISNULL(location, '') = ISNULL(?, '')
    ");
    $stmt_delete->execute([
        $original_mission['assignment_date'], $original_mission['mission_text'], $original_mission['shift_type'],
        $original_mission['start_time'], $original_mission['location']
    ]);

    $conn->commit();
    respondWithSuccess('Mission supprimée.');
}


/**
 * Assigns a worker to an existing mission group.
 */
function assignWorkerToMission($conn, $creator_id, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id']; // Representative ID of the target mission
    $mission_date = $data['assignment_date'];
    
    $conn->beginTransaction();
    
    // REMOVED: The logic that deletes other assignments for the day.
    // $stmt_delete_old = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND assignment_date = ?");
    // $stmt_delete_old->execute([$worker_id, $mission_date]);

    // Get the details of the mission to copy
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $mission_details = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$mission_details) {
        $conn->rollBack();
        respondWithError('Mission cible non trouvée.');
    }

    // Create a new assignment for the worker with the copied details
    $stmt_insert = $conn->prepare("
        INSERT INTO Planning_Assignments (
            assigned_user_id, creator_user_id, assignment_date, start_time, end_time,
            shift_type, mission_text, color, location, is_validated
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_insert->execute([
        $worker_id, $creator_id, $mission_details['assignment_date'], $mission_details['start_time'], $mission_details['end_time'],
        $mission_details['shift_type'], $mission_details['mission_text'], $mission_details['color'], $mission_details['location'], $mission_details['is_validated']
    ]);
    
    $conn->commit();
    respondWithSuccess('Ouvrier assigné avec succès.');
}

/**
 * Removes a single worker from a mission group.
 */
function removeWorkerFromMission($conn, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id']; // Representative ID of the mission group
    
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$original_mission) {
        respondWithError('Mission non trouvée.');
    }

    // Find the specific assignment_id for this user within this group and delete it.
    $stmt_delete = $conn->prepare("
        DELETE FROM Planning_Assignments
        WHERE
            assigned_user_id = ? AND assignment_date = ? AND mission_text = ? AND shift_type = ?
            AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00')
            AND ISNULL(location, '') = ISNULL(?, '')
    ");
    $stmt_delete->execute([
        $worker_id, $original_mission['assignment_date'], $original_mission['mission_text'],
        $original_mission['shift_type'], $original_mission['start_time'], $original_mission['location']
    ]);
    
    respondWithSuccess('Ouvrier retiré de la mission.');
}


/**
 * Toggles the validation status for an entire mission group.
 */
function toggleMissionValidation($conn, $data) {
    $mission_id = $data['mission_id'];
    $conn->beginTransaction();

    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$original_mission) {
        $conn->rollBack();
        respondWithError('Mission non trouvée.');
    }

    $new_status = !$original_mission['is_validated'];

    $stmt_update = $conn->prepare("
        UPDATE Planning_Assignments SET is_validated = ?
        WHERE
            assignment_date = ? AND mission_text = ? AND shift_type = ?
            AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00')
            AND ISNULL(location, '') = ISNULL(?, '')
    ");
    $stmt_update->execute([
        $new_status, $original_mission['assignment_date'], $original_mission['mission_text'],
        $original_mission['shift_type'], $original_mission['start_time'], $original_mission['location']
    ]);

    $conn->commit();
    respondWithSuccess('Statut de validation mis à jour.');
}
