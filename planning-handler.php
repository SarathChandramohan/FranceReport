<?php
// planning-handler.php (New Reworked Style)

require_once 'db-connection.php';
require_once 'session-management.php';

// Helper functions for JSON responses
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

// Ensure user is logged in
requireLogin();

$currentUser = getCurrentUser();
$user_id = $currentUser['user_id'];
$user_role = $currentUser['role'];

// Only allow admins to use this planning interface for now
if ($user_role !== 'admin') {
    respondWithError('Accès refusé. Cette section est réservée aux administrateurs.', 403);
}

global $conn;

try {
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $input_data = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'get_staff_users':
            $stmt = $conn->prepare("SELECT user_id, nom, prenom, email, role FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            respondWithSuccess('Utilisateurs récupérés.', ['users' => $users]);
            break;

        case 'get_all_assignments_for_workers':
            // This fetches all assignments regardless of date for client-side worker status
            $stmt = $conn->prepare("SELECT assignment_date, assigned_user_id, mission_text, shift_type FROM Planning_Assignments ORDER BY assignment_date DESC");
            $stmt->execute();
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            respondWithSuccess('Toutes les affectations récupérées pour les ouvriers.', ['assignments' => $assignments]);
            break;

        case 'get_assignments': // Fetches grouped missions for a date range
            $start_date_str = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
            $end_date_str = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');

            // SQL Server query to group assignments into logical "missions"
            $query_sql = "
                SELECT
                       MIN(pa.assignment_id) as representative_assignment_id,
                       CONVERT(VARCHAR(10), pa.assignment_date, 120) as start_date_group,
                       ISNULL(pa.start_time, '') as start_time,
                       ISNULL(pa.end_time, '') as end_time,
                       pa.shift_type,
                       ISNULL(pa.color, '#1877f2') as color,
                       ISNULL(pa.mission_text, '') as title, -- Using 'title' as per React UI
                       ISNULL(pa.location, '') as location,
                       pa.is_validated,
                       COUNT(DISTINCT pa.assigned_user_id) as user_count,
                       STRING_AGG(CONVERT(VARCHAR(10), pa.assigned_user_id), ',') WITHIN GROUP (ORDER BY u.prenom, u.nom) as user_ids_list,
                       STRING_AGG(CONCAT(u.prenom, ' ', u.nom), ', ') WITHIN GROUP (ORDER BY u.prenom, u.nom) as user_names_list
                FROM Planning_Assignments pa
                JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.assignment_date BETWEEN :start_date AND :end_date
                GROUP BY
                       CONVERT(VARCHAR(10), pa.assignment_date, 120),
                       ISNULL(pa.start_time, ''),
                       ISNULL(pa.end_time, ''),
                       pa.shift_type,
                       ISNULL(pa.color, '#1877f2'),
                       ISNULL(pa.mission_text, ''),
                       ISNULL(pa.location, ''),
                       pa.is_validated
                ORDER BY start_date_group, start_time;
            ";

            $stmt = $conn->prepare($query_sql);
            $stmt->bindParam(':start_date', $start_date_str, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $end_date_str, PDO::PARAM_STR);
            $stmt->execute();
            $missions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            respondWithSuccess('Missions récupérées.', ['missions' => $missions_data]);
            break;

        case 'get_mission_details_for_edit': // Get details for a specific mission group to populate form
            $mission_date = $input_data['mission_date'] ?? $_GET['mission_date'];
            $original_mission_id = $input_data['original_mission_id'] ?? $_GET['original_mission_id'];

            if (empty($mission_date) || empty($original_mission_id)) {
                respondWithError('Date de mission et ID original requis.');
            }

            // Fetch one assignment from the group to get common mission details
            $stmt = $conn->prepare("
                SELECT pa.title, pa.start_time, pa.end_time, pa.location, pa.mission_text, pa.shift_type, pa.color, pa.is_validated
                FROM Planning_Assignments pa
                WHERE pa.assignment_id = :original_mission_id AND pa.assignment_date = :mission_date
            ");
            $stmt->bindParam(':original_mission_id', $original_mission_id, PDO::PARAM_INT);
            $stmt->bindParam(':mission_date', $mission_date, PDO::PARAM_STR);
            $stmt->execute();
            $mission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mission) {
                respondWithError('Mission non trouvée pour l\'édition.');
            }

            respondWithSuccess('Détails de mission récupérés.', ['mission' => $mission]);
            break;

        case 'save_assignment': // Create or update mission groups
            $mission_date = $input_data['mission_date'];
            $title = $input_data['title'];
            $start_time = $input_data['start_time'];
            $end_time = $input_data['end_time'];
            $location = $input_data['location'];
            $comment = $input_data['comment'];
            $shift_type = $input_data['shift_type'];
            $color = $input_data['color'];
            $original_mission_id = $input_data['original_mission_id'] ?? null;
            $is_group_edit = isset($input_data['is_group_edit']) ? (bool)$input_data['is_group_edit'] : false;
            $assigned_user_ids = $input_data['assigned_user_ids'] ?? []; // For new missions only

            if (empty($mission_date) || empty($title) || empty($shift_type)) {
                respondWithError('Date, titre et type de service sont obligatoires.');
            }
            if ($shift_type !== 'repos' && (empty($start_time) || empty($end_time))) {
                respondWithError('Heure de début et de fin sont obligatoires pour ce type de service.');
            }

            $conn->beginTransaction();
            try {
                if ($original_mission_id && $is_group_edit) {
                    // Editing an existing mission group
                    // First, get the unique defining characteristics of the original mission
                    $stmt_original = $conn->prepare("
                        SELECT title, start_time, end_time, location, mission_text, shift_type, color
                        FROM Planning_Assignments
                        WHERE assignment_id = :original_mission_id AND assignment_date = :mission_date
                    ");
                    $stmt_original->bindParam(':original_mission_id', $original_mission_id, PDO::PARAM_INT);
                    $stmt_original->bindParam(':mission_date', $mission_date, PDO::PARAM_STR);
                    $stmt_original->execute();
                    $original_mission_details = $stmt_original->fetch(PDO::FETCH_ASSOC);

                    if (!$original_mission_details) {
                        $conn->rollBack();
                        respondWithError('Mission originale non trouvée pour la mise à jour.');
                    }

                    // Update all assignments that match the original mission's characteristics on this date
                    $update_query = "
                        UPDATE Planning_Assignments
                        SET title = :new_title,
                            start_time = :new_start_time,
                            end_time = :new_end_time,
                            location = :new_location,
                            mission_text = :new_mission_text,
                            shift_type = :new_shift_type,
                            color = :new_color
                        WHERE assignment_date = :mission_date
                          AND title = :original_title
                          AND ISNULL(start_time, '') = ISNULL(:original_start_time, '')
                          AND ISNULL(end_time, '') = ISNULL(:original_end_time, '')
                          AND ISNULL(location, '') = ISNULL(:original_location, '')
                          AND ISNULL(mission_text, '') = ISNULL(:original_mission_text, '')
                          AND shift_type = :original_shift_type
                          AND ISNULL(color, '') = ISNULL(:original_color, '');
                    ";
                    $stmt_update = $conn->prepare($update_query);
                    $stmt_update->execute([
                        ':new_title' => $title,
                        ':new_start_time' => $start_time,
                        ':new_end_time' => $end_time,
                        ':new_location' => $location,
                        ':new_mission_text' => $comment,
                        ':new_shift_type' => $shift_type,
                        ':new_color' => $color,
                        ':mission_date' => $mission_date,
                        ':original_title' => $original_mission_details['title'],
                        ':original_start_time' => $original_mission_details['start_time'],
                        ':original_end_time' => $original_mission_details['end_time'],
                        ':original_location' => $original_mission_details['location'],
                        ':original_mission_text' => $original_mission_details['mission_text'],
                        ':original_shift_type' => $original_mission_details['shift_type'],
                        ':original_color' => $original_mission_details['color']
                    ]);
                    respondWithSuccess('Mission mise à jour avec succès pour tous les ouvriers affectés.');

                } else {
                    // Creating a new mission (template for future assignments via drag-drop)
                    // It must be assigned to at least one user to satisfy NOT NULL constraint.
                    // If no specific users are provided by frontend, assign to the admin creating it.
                    if (empty($assigned_user_ids)) {
                        $assigned_user_ids = [$user_id]; // Assign to admin by default
                    }

                    $insert_query = "
                        INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, title, date_creation, is_validated)
                        VALUES (:assigned_user_id, :creator_user_id, :assignment_date, :start_time, :end_time, :shift_type, :mission_text, :color, :location, :title, GETDATE(), 0);
                    ";
                    $stmt_insert = $conn->prepare($insert_query);

                    foreach ($assigned_user_ids as $assigned_uid) {
                        $stmt_insert->execute([
                            ':assigned_user_id' => $assigned_uid,
                            ':creator_user_id' => $user_id,
                            ':assignment_date' => $mission_date,
                            ':start_time' => $start_time,
                            ':end_time' => $end_time,
                            ':shift_type' => $shift_type,
                            ':mission_text' => $comment,
                            ':color' => $color,
                            ':location' => $location,
                            ':title' => $title
                        ]);
                    }
                    respondWithSuccess('Mission créée avec succès.');
                }
                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("DB Error on save_assignment: " . $e->getMessage());
                respondWithError('Erreur de base de données lors de l\'enregistrement de la mission.');
            }
            break;

        case 'delete_assignment': // Delete a mission group or a single assignment
            $mission_date = $input_data['mission_date'];
            $original_mission_id = $input_data['original_mission_id'];
            $is_group_delete = (bool)$input_data['is_group_delete'];

            if (empty($mission_date) || empty($original_mission_id)) {
                respondWithError('Date de mission et ID original requis pour la suppression.');
            }

            $conn->beginTransaction();
            try {
                if ($is_group_delete) {
                    // Get defining characteristics of the mission group
                    $stmt_original = $conn->prepare("
                        SELECT title, start_time, end_time, location, mission_text, shift_type, color
                        FROM Planning_Assignments
                        WHERE assignment_id = :original_mission_id AND assignment_date = :mission_date
                    ");
                    $stmt_original->bindParam(':original_mission_id', $original_mission_id, PDO::PARAM_INT);
                    $stmt_original->bindParam(':mission_date', $mission_date, PDO::PARAM_STR);
                    $stmt_original->execute();
                    $original_mission_details = $stmt_original->fetch(PDO::FETCH_ASSOC);

                    if (!$original_mission_details) {
                        $conn->rollBack();
                        respondWithError('Mission originale non trouvée pour la suppression.');
                    }

                    // Delete all assignments that match the original mission's characteristics on this date
                    $delete_query = "
                        DELETE FROM Planning_Assignments
                        WHERE assignment_date = :mission_date
                          AND title = :original_title
                          AND ISNULL(start_time, '') = ISNULL(:original_start_time, '')
                          AND ISNULL(end_time, '') = ISNULL(:original_end_time, '')
                          AND ISNULL(location, '') = ISNULL(:original_location, '')
                          AND ISNULL(mission_text, '') = ISNULL(:original_mission_text, '')
                          AND shift_type = :original_shift_type
                          AND ISNULL(color, '') = ISNULL(:original_color, '');
                    ";
                    $stmt_delete = $conn->prepare($delete_query);
                    $stmt_delete->execute([
                        ':mission_date' => $mission_date,
                        ':original_title' => $original_mission_details['title'],
                        ':original_start_time' => $original_mission_details['start_time'],
                        ':original_end_time' => $original_mission_details['end_time'],
                        ':original_location' => $original_mission_details['location'],
                        ':original_mission_text' => $original_mission_details['mission_text'],
                        ':original_shift_type' => $original_mission_details['shift_type'],
                        ':original_color' => $original_mission_details['color']
                    ]);
                    respondWithSuccess('Mission et toutes ses affectations associées supprimées avec succès.');

                } else {
                    // Deleting a single assignment (this case might be less used with the new UI)
                    $stmt_delete = $conn->prepare("DELETE FROM Planning_Assignments WHERE assignment_id = :assignment_id");
                    $stmt_delete->bindParam(':assignment_id', $original_mission_id, PDO::PARAM_INT); // Original_mission_id acts as single assignment_id here
                    $stmt_delete->execute();
                    respondWithSuccess('Affectation individuelle supprimée avec succès.');
                }
                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("DB Error on delete_assignment: " . $e->getMessage());
                respondWithError('Erreur de base de données lors de la suppression de la mission.');
            }
            break;

        case 'remove_worker_from_assignment': // Used when clicking 'X' on a worker in a mission card
            $worker_id = $input_data['worker_id'];
            $mission_date = $input_data['mission_date'];
            $original_mission_id = $input_data['original_mission_id'];

            if (empty($worker_id) || empty($mission_date) || empty($original_mission_id)) {
                respondWithError('Données manquantes pour retirer l\'ouvrier.');
            }

            $conn->beginTransaction();
            try {
                // Get the mission's defining characteristics
                $stmt_mission = $conn->prepare("
                    SELECT title, start_time, end_time, location, mission_text, shift_type, color
                    FROM Planning_Assignments
                    WHERE assignment_id = :original_mission_id AND assignment_date = :mission_date
                ");
                $stmt_mission->bindParam(':original_mission_id', $original_mission_id, PDO::PARAM_INT);
                $stmt_mission->bindParam(':mission_date', $mission_date, PDO::PARAM_STR);
                $stmt_mission->execute();
                $mission_details = $stmt_mission->fetch(PDO::FETCH_ASSOC);

                if (!$mission_details) {
                    $conn->rollBack();
                    respondWithError('Mission non trouvée.');
                }

                // Delete the specific assignment for this worker that matches this mission group
                $delete_query = "
                    DELETE FROM Planning_Assignments
                    WHERE assigned_user_id = :worker_id
                      AND assignment_date = :mission_date
                      AND title = :mission_title
                      AND ISNULL(start_time, '') = ISNULL(:start_time, '')
                      AND ISNULL(end_time, '') = ISNULL(:end_time, '')
                      AND ISNULL(location, '') = ISNULL(:location, '')
                      AND ISNULL(mission_text, '') = ISNULL(:mission_text, '')
                      AND shift_type = :shift_type
                      AND ISNULL(color, '') = ISNULL(:color, '');
                ";
                $stmt_delete = $conn->prepare($delete_query);
                $stmt_delete->execute([
                    ':worker_id' => $worker_id,
                    ':mission_date' => $mission_date,
                    ':mission_title' => $mission_details['title'],
                    ':start_time' => $mission_details['start_time'],
                    ':end_time' => $mission_details['end_time'],
                    ':location' => $mission_details['location'],
                    ':mission_text' => $mission_details['mission_text'],
                    ':shift_type' => $mission_details['shift_type'],
                    ':color' => $mission_details['color']
                ]);

                if ($stmt_delete->rowCount() === 0) {
                     $conn->rollBack();
                     respondWithError('Aucune affectation correspondante trouvée pour cet ouvrier et cette mission.');
                }

                $conn->commit();
                respondWithSuccess('Ouvrier retiré de la mission avec succès.');
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("DB Error on remove_worker_from_assignment: " . $e->getMessage());
                respondWithError('Erreur de base de données lors du retrait de l\'ouvrier.');
            }
            break;

        case 'toggle_mission_validation':
            $mission_date = $input_data['mission_date'];
            $original_mission_id = $input_data['original_mission_id'];

            if (empty($mission_date) || empty($original_mission_id)) {
                respondWithError('Date de mission et ID original requis pour la validation.');
            }

            $conn->beginTransaction();
            try {
                // Get current validation status and defining characteristics
                $stmt_mission = $conn->prepare("
                    SELECT title, start_time, end_time, location, mission_text, shift_type, color, is_validated
                    FROM Planning_Assignments
                    WHERE assignment_id = :original_mission_id AND assignment_date = :mission_date
                ");
                $stmt_mission->bindParam(':original_mission_id', $original_mission_id, PDO::PARAM_INT);
                $stmt_mission->bindParam(':mission_date', $mission_date, PDO::PARAM_STR);
                $stmt_mission->execute();
                $mission_details = $stmt_mission->fetch(PDO::FETCH_ASSOC);

                if (!$mission_details) {
                    $conn->rollBack();
                    respondWithError('Mission non trouvée.');
                }

                $new_validation_status = !$mission_details['is_validated']; // Toggle status

                // Update all assignments that match the mission's characteristics on this date
                $update_query = "
                    UPDATE Planning_Assignments
                    SET is_validated = :new_status
                    WHERE assignment_date = :mission_date
                      AND title = :mission_title
                      AND ISNULL(start_time, '') = ISNULL(:start_time, '')
                      AND ISNULL(end_time, '') = ISNULL(:end_time, '')
                      AND ISNULL(location, '') = ISNULL(:location, '')
                      AND ISNULL(mission_text, '') = ISNULL(:mission_text, '')
                      AND shift_type = :shift_type
                      AND ISNULL(color, '') = ISNULL(:color, '');
                ";
                $stmt_update = $conn->prepare($update_query);
                $stmt_update->execute([
                    ':new_status' => $new_validation_status,
                    ':mission_date' => $mission_date,
                    ':mission_title' => $mission_details['title'],
                    ':start_time' => $mission_details['start_time'],
                    ':end_time' => $mission_details['end_time'],
                    ':location' => $mission_details['location'],
                    ':mission_text' => $mission_details['mission_text'],
                    ':shift_type' => $mission_details['shift_type'],
                    ':color' => $mission_details['color']
                ]);

                $conn->commit();
                respondWithSuccess('Statut de validation de la mission mis à jour.');
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("DB Error on toggle_mission_validation: " . $e->getMessage());
                respondWithError('Erreur de base de données lors de la mise à jour du statut de validation.');
            }
            break;

        case 'assign_worker_to_mission': // Handle drag-drop assignment
            $worker_id = $input_data['worker_id'];
            $mission_date = $input_data['mission_date'];
            $original_mission_id = $input_data['original_mission_id']; // This represents the mission group

            if (empty($worker_id) || empty($mission_date) || empty($original_mission_id)) {
                respondWithError('Données manquantes pour l\'affectation de l\'ouvrier à la mission.');
            }

            $conn->beginTransaction();
            try {
                // First, check if the worker is already assigned for this date
                $stmt_check_existing = $conn->prepare("
                    SELECT assignment_id FROM Planning_Assignments
                    WHERE assigned_user_id = :worker_id AND assignment_date = :mission_date
                ");
                $stmt_check_existing->bindParam(':worker_id', $worker_id, PDO::PARAM_INT);
                $stmt_check_existing->bindParam(':mission_date', $mission_date, PDO::PARAM_STR);
                $stmt_check_existing->execute();
                $existing_assignment = $stmt_check_existing->fetch(PDO::FETCH_ASSOC);

                if ($existing_assignment) {
                    // If already assigned, delete the old assignment to move to the new one
                    $stmt_delete_old = $conn->prepare("DELETE FROM Planning_Assignments WHERE assignment_id = :old_assignment_id");
                    $stmt_delete_old->bindParam(':old_assignment_id', $existing_assignment['assignment_id'], PDO::PARAM_INT);
                    $stmt_delete_old->execute();
                }

                // Get the mission's defining characteristics from the original_mission_id
                $stmt_mission_details = $conn->prepare("
                    SELECT title, start_time, end_time, location, mission_text, shift_type, color, is_validated
                    FROM Planning_Assignments
                    WHERE assignment_id = :original_mission_id AND assignment_date = :mission_date
                ");
                $stmt_mission_details->bindParam(':original_mission_id', $original_mission_id, PDO::PARAM_INT);
                $stmt_mission_details->bindParam(':mission_date', $mission_date, PDO::PARAM_STR);
                $stmt_mission_details->execute();
                $mission_details = $stmt_mission_details->fetch(PDO::FETCH_ASSOC);

                if (!$mission_details) {
                    $conn->rollBack();
                    respondWithError('Détails de la mission cible introuvables.');
                }

                // Create a new assignment for the dragged worker with the mission's details
                $insert_query = "
                    INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, title, date_creation, is_validated)
                    VALUES (:assigned_user_id, :creator_user_id, :assignment_date, :start_time, :end_time, :shift_type, :mission_text, :color, :location, :title, GETDATE(), :is_validated);
                ";
                $stmt_insert = $conn->prepare($insert_query);
                $stmt_insert->execute([
                    ':assigned_user_id' => $worker_id,
                    ':creator_user_id' => $user_id, // The admin performing the drag-drop
                    ':assignment_date' => $mission_date,
                    ':start_time' => $mission_details['start_time'],
                    ':end_time' => $mission_details['end_time'],
                    ':shift_type' => $mission_details['shift_type'],
                    ':mission_text' => $mission_details['mission_text'],
                    ':color' => $mission_details['color'],
                    ':location' => $mission_details['location'],
                    ':title' => $mission_details['title'],
                    ':is_validated' => $mission_details['is_validated']
                ]);

                $conn->commit();
                respondWithSuccess('Ouvrier affecté à la mission avec succès.');
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("DB Error on assign_worker_to_mission: " . $e->getMessage());
                respondWithError('Erreur de base de données lors de l\'affectation de l\'ouvrier.');
            }
            break;

        default:
            respondWithError('Action non valide spécifiée.');
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("General Error in planning-handler.php: " . $e->getMessage());
    respondWithError('Une erreur interne du serveur est survenue: ' . $e->getMessage(), 500);
}
?>
