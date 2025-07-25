<?php
// planning-handler.php (Corrected version)

date_default_timezone_set('Europe/Paris');

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
        case 'get_worker_status_for_date':
            getWorkerStatusForDate($conn, $_GET['date']);
            break;
        case 'validate_all_for_week':
            validateAllForWeek($conn, $input);
            break;
        default:
            respondWithError('Action non valide.', 400);
    }
} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}

function validateAllForWeek($conn, $data) {
    if (empty($data['start_date']) || empty($data['end_date'])) {
        respondWithError('Les dates de début et de fin de la semaine sont requises.');
    }

    $stmt = $conn->prepare("UPDATE Planning_Assignments SET is_validated = 1 WHERE assignment_date BETWEEN ? AND ?");
    $stmt->execute([$data['start_date'], $data['end_date']]);

    respondWithSuccess('Toutes les planifications pour la semaine ont été activées.');
}


// --- Functions ---
function getInitialData($conn) {
    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d', strtotime('+6 days'));

    $stmt_users = $conn->prepare("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $stmt_users->execute();
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    $stmt_inventory = $conn->prepare("SELECT asset_id, asset_name, asset_type, status, serial_or_plate FROM Inventory WHERE status != 'maintenance' ORDER BY asset_name");
    $stmt_inventory->execute();
    $inventory = $stmt_inventory->fetchAll(PDO::FETCH_ASSOC);

    $stmt_bookings = $conn->prepare("SELECT asset_id, booking_date, mission, mission_group_id FROM Bookings WHERE status IN ('booked', 'active') AND booking_date BETWEEN ? AND ?");
    $stmt_bookings->execute([$start_date, $end_date]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

    $stmt_missions = $conn->prepare("
        WITH MissionAssignments AS (
            SELECT 
                pa.*, 
                c.type_conge,
                CASE WHEN c.type_conge IS NOT NULL THEN 1 ELSE 0 END as is_on_leave,
                CASE WHEN c.type_conge = 'maladie' OR c.type_conge = 'Arrêt maladie' THEN 1 ELSE 0 END as is_sick_leave,
                COUNT(*) OVER (PARTITION BY pa.assigned_user_id, pa.assignment_date) as daily_assignment_count
            FROM Planning_Assignments pa
            LEFT JOIN Conges c ON pa.assigned_user_id = c.user_id 
                               AND pa.assignment_date BETWEEN c.date_debut AND c.date_fin 
                               AND c.status = 'approved'
            WHERE pa.assignment_date BETWEEN ? AND ?
        )
        SELECT
            MIN(pa.assignment_id) as mission_id,
            pa.mission_group_id,
            pa.assignment_date, pa.mission_text, pa.comments, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated,
            MAX(pa.is_on_leave) as is_on_leave_assignment,
            STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.nom) as assigned_user_ids,
            STRING_AGG(u.prenom + ' ' + u.nom, ', ') WITHIN GROUP (ORDER BY u.nom) as assigned_user_names,
            STRING_AGG(CAST(pa.is_sick_leave AS VARCHAR(1)), ',') WITHIN GROUP (ORDER BY u.nom) as sick_leave_flags,
            STRING_AGG(CAST(pa.is_on_leave AS VARCHAR(1)), ',') WITHIN GROUP (ORDER BY u.nom) as on_leave_flags,
            (SELECT STRING_AGG(CAST(conflict_pa.assigned_user_id AS VARCHAR(10)), ',') 
             FROM MissionAssignments conflict_pa 
             WHERE conflict_pa.assignment_date = pa.assignment_date AND conflict_pa.daily_assignment_count > 1) as conflicting_assignments
        FROM MissionAssignments pa
        JOIN Users u ON pa.assigned_user_id = u.user_id
        GROUP BY
            pa.mission_group_id, pa.assignment_date, pa.mission_text, pa.comments, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated
        ORDER BY pa.assignment_date, pa.start_time
    ");
    $stmt_missions->execute([$start_date, $end_date]);
    $missions = $stmt_missions->fetchAll(PDO::FETCH_ASSOC);

    $asset_map = array_column($inventory, null, 'asset_id');
    $bookings_map = [];
    foreach ($bookings as $booking) {
        $key = $booking['mission_group_id'];
        if (!$key) continue;
        if (!isset($bookings_map[$key])) $bookings_map[$key] = [];
        if (isset($asset_map[$booking['asset_id']])) {
           $bookings_map[$key][] = ['id' => $asset_map[$booking['asset_id']]['asset_id'], 'name' => $asset_map[$booking['asset_id']]['asset_name'], 'serial' => $asset_map[$booking['asset_id']]['serial_or_plate']];
        }
    }

    foreach ($missions as &$mission) {
        $key = $mission['mission_group_id'];
        $assigned_assets_with_serial = [];
        if (isset($bookings_map[$key])) {
            $mission['assigned_assets'] = $bookings_map[$key];
            foreach($bookings_map[$key] as $asset) {
                $assigned_assets_with_serial[] = $asset['serial'] ? "{$asset['name']} ({$asset['serial']})" : $asset['name'];
            }
            $mission['assigned_asset_names'] = implode(', ', $assigned_assets_with_serial);
        } else {
            $mission['assigned_assets'] = [];
            $mission['assigned_asset_names'] = '';
        }
        $mission['conflicting_assignments'] = !empty($mission['conflicting_assignments']) ? array_values(array_unique(explode(',', $mission['conflicting_assignments']))) : [];
    }
    unset($mission);

    respondWithSuccess('Données initiales chargées.', ['staff' => $users, 'missions' => $missions, 'inventory' => $inventory, 'bookings' => $bookings]);
}

// Helper function to get the display name for a leave type key
function getLeaveTypeName($typeKey) {
    $types = [
        'cp' => 'Congés Payés',
        'rtt' => 'RTT',
        'sans-solde' => 'Congé Sans Solde',
        'special' => 'Congé Spécial',
        'maladie' => 'Arrêt Maladie'
    ];
    return $types[$typeKey] ?? ucfirst($typeKey); // Return a user-friendly name
}

function getWorkerStatusForDate($conn, $date) {
    if (!$date) respondWithError('Date not provided.');

    // Get assigned users and their missions
    $stmt_assigned = $conn->prepare("
        SELECT assigned_user_id, STRING_AGG(mission_text, ', ') as missions
        FROM Planning_Assignments
        WHERE assignment_date = ? AND (shift_type IS NULL OR shift_type <> 'repos')
        GROUP BY assigned_user_id
    ");
    $stmt_assigned->execute([$date]);
    $assigned_users_data = $stmt_assigned->fetchAll(PDO::FETCH_KEY_PAIR); // user_id => missions string

    // Get users on leave with leave type
    $stmt_on_leave = $conn->prepare("SELECT user_id, type_conge FROM Conges WHERE ? BETWEEN date_debut AND date_fin AND status = 'approved'");
    $stmt_on_leave->execute([$date]);
    $on_leave_data = $stmt_on_leave->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get all active users
    $stmt_all_users = $conn->prepare("SELECT user_id FROM Users WHERE status = 'Active'");
    $stmt_all_users->execute();
    $all_users = $stmt_all_users->fetchAll(PDO::FETCH_COLUMN, 0);

    $worker_statuses = [];
    foreach ($all_users as $user_id) {
        $status = 'available';
        $leave_type = null;
        $missions = null;

        if (isset($on_leave_data[$user_id])) {
            $leave_type_key = $on_leave_data[$user_id];
            $leave_type = getLeaveTypeName($leave_type_key);
            if ($leave_type_key === 'maladie' || $leave_type_key === 'Arrêt maladie') {
                $status = 'on_sick_leave';
            } else {
                $status = 'on_leave';
            }
        } elseif (isset($assigned_users_data[$user_id])) {
            $status = 'assigned';
            $missions = $assigned_users_data[$user_id];
        }

        $worker_statuses[] = [
            'user_id' => $user_id,
            'status' => $status,
            'leave_type' => $leave_type,
            'missions' => $missions,
        ];
    }
    respondWithSuccess('Worker statuses retrieved.', $worker_statuses);
}

function saveMission($conn, $creator_id, $data) {
    $mission_id = $data['mission_id'] ?? null;
    $assigned_users = $data['assigned_user_ids'] ?? [];
    $assigned_asset_ids = $data['assigned_asset_ids'] ?? [];
    $comments = $data['comments'] ?? '';

    if (empty($data['mission_text'])) respondWithError('Le titre de la mission est obligatoire.');

    $conn->beginTransaction();

    $dates = [];
    if (!empty($data['start_date']) && !empty($data['end_date'])) {
        $period = new DatePeriod(new DateTime($data['start_date']), new DateInterval('P1D'), (new DateTime($data['end_date']))->modify('+1 day'));
        foreach ($period as $date) $dates[] = $date->format('Y-m-d');
    } elseif (!empty($data['assignment_date'])) {
        $dates[] = $data['assignment_date'];
    }

    if (empty($dates) && !$mission_id) {
        $conn->rollBack();
        respondWithError('La date de la mission est obligatoire.');
    }
    
    // Check for asset booking conflicts before making any changes
    if (!empty($assigned_asset_ids) && !empty($dates)) {
        $params = array_merge($assigned_asset_ids, $dates);
        $placeholders_assets = implode(',', array_fill(0, count($assigned_asset_ids), '?'));
        $placeholders_dates = implode(',', array_fill(0, count($dates), '?'));
        
        $sql_check = "SELECT b.booking_date, i.asset_name FROM Bookings b JOIN Inventory i ON b.asset_id = i.asset_id WHERE b.asset_id IN ($placeholders_assets) AND b.booking_date IN ($placeholders_dates) AND b.status IN ('booked', 'active')";
        
        if ($mission_id) {
            $stmt_find_group = $conn->prepare("SELECT mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
            $stmt_find_group->execute([$mission_id]);
            $mission_group_id = $stmt_find_group->fetchColumn();
            if ($mission_group_id) {
                $sql_check .= " AND b.mission_group_id != ?";
                $params[] = $mission_group_id;
            }
        }

        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute($params);
        if ($conflict = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
            $conn->rollBack();
            respondWithError("Conflit: L'actif '{$conflict['asset_name']}' est déjà réservé le " . date('d/m/Y', strtotime($conflict['booking_date'])) . ".");
        }
    }


    if ($mission_id) { // UPDATE existing mission
        $stmt_find_group = $conn->prepare("SELECT mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
        $stmt_find_group->execute([$mission_id]);
        $mission_group_id = $stmt_find_group->fetchColumn();
        if(!$mission_group_id) {
            $conn->rollBack();
            respondWithError("Mission à modifier non trouvée.");
        }
        
        $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET mission_text = ?, comments = ?, start_time = ?, end_time = ?, location = ?, shift_type = ?, color = ? WHERE mission_group_id = ?");
        $stmt_update->execute([$data['mission_text'], $comments, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['location'] ?: null, $data['shift_type'], $data['color'], $mission_group_id]);

        $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
        $stmt_delete_bookings->execute([$mission_group_id]);

        if (!empty($assigned_asset_ids)) {
            $stmt_book = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status, mission_group_id) VALUES (?, NULL, ?, ?, 'booked', ?)");
            $booking_date = $dates[0]; // For a single mission update, there's only one date
            foreach ($assigned_asset_ids as $asset_id) {
                $stmt_book->execute([$asset_id, $booking_date, $data['mission_text'], $mission_group_id]);
            }
        }

    } else { // CREATE new mission
        if (empty($assigned_users)) {
            $conn->rollBack();
            respondWithError('Veuillez assigner au moins un ouvrier.');
        }

        $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, comments, color, location, is_validated, mission_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
        $stmt_book = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status, mission_group_id) VALUES (?, NULL, ?, ?, 'booked', ?)");

        // Loop through each day, create a unique group ID, and then create assignments AND bookings for that day.
        foreach ($dates as $mission_date) {
            // 1. Generate a new, unique ID for this day's group of assignments
            $mission_group_id = $conn->query("SELECT NEWID()")->fetchColumn();

            // 2. Create user assignments for this day
            foreach ($assigned_users as $user_id) {
                $stmt_insert->execute([$user_id, $creator_id, $mission_date, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['shift_type'], $data['mission_text'], $comments, $data['color'], $data['location'] ?: null, $mission_group_id]);
            }

            // 3. Create asset bookings for this day
            if (!empty($assigned_asset_ids)) {
                foreach ($assigned_asset_ids as $asset_id) {
                    $stmt_book->execute([$asset_id, $mission_date, $data['mission_text'], $mission_group_id]);
                }
            }
        }
    }
    
    $conn->commit();
    respondWithSuccess('Mission enregistrée avec succès.');
}


function deleteMissionGroup($conn, $data) {
    $mission_id = $data['mission_id'];
    if (!$mission_id) {
        respondWithError('ID de mission manquant.');
    }

    $conn->beginTransaction();

    try {
        // Find the mission_group_id from the specific assignment_id clicked by the user
        $stmt_find_group = $conn->prepare("SELECT mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
        $stmt_find_group->execute([$mission_id]);
        $mission_group_id = $stmt_find_group->fetchColumn();

        if (!$mission_group_id) {
            $conn->rollBack();
            respondWithError('Mission à supprimer non trouvée.');
        }

        // Delete all bookings associated with this mission group
        $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
        $stmt_delete_bookings->execute([$mission_group_id]);

        // Delete all assignments for this mission group
        $stmt_delete_assignments = $conn->prepare("DELETE FROM Planning_Assignments WHERE mission_group_id = ?");
        $stmt_delete_assignments->execute([$mission_group_id]);

        $conn->commit();
        respondWithSuccess('Mission et toutes ses affectations, y compris les réservations de matériel, ont été supprimées.');

    } catch (Exception $e) {
        $conn->rollBack();
        respondWithError('Erreur lors de la suppression de la mission: ' . $e->getMessage(), 500);
    }
}

function assignWorkerToMission($conn, $creator_id, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id'];
    if (!$worker_id || !$mission_id) respondWithError('Worker ID and Mission ID are required.');

    $conn->beginTransaction();

    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $mission_template = $stmt_orig->fetch(PDO::FETCH_ASSOC);

    if (!$mission_template || !$mission_template['mission_group_id']) {
        $conn->rollBack();
        respondWithError('Mission cible non trouvée ou mal configurée.');
    }
    $mission_group_id = $mission_template['mission_group_id'];

    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Planning_Assignments WHERE mission_group_id = ? AND assigned_user_id = ?");
    $stmt_check->execute([$mission_group_id, $worker_id]);
    if ($stmt_check->fetchColumn() > 0) {
        $conn->rollBack();
        respondWithSuccess('Ouvrier déjà assigné à cette mission.'); 
        return;
    }

    $stmt_dates = $conn->prepare("SELECT DISTINCT assignment_date FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_dates->execute([$mission_group_id]);
    $mission_dates = $stmt_dates->fetchAll(PDO::FETCH_COLUMN, 0);

    if (empty($mission_dates)) {
        $conn->rollBack();
        respondWithError("Impossible de trouver les dates pour cette mission de groupe.");
    }
    
    $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, comments, color, location, is_validated, mission_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($mission_dates as $date) {
        $stmt_insert->execute([$worker_id, $creator_id, $date, $mission_template['start_time'], $mission_template['end_time'], $mission_template['shift_type'], $mission_template['mission_text'], $mission_template['comments'], $mission_template['color'], $mission_template['location'], $mission_template['is_validated'], $mission_group_id]);
    }
    
    $conn->commit();
    respondWithSuccess('Ouvrier assigné à toutes les journées de la mission.');
}

/**
 * Removes a worker from a mission. 
 * If it's the last worker on that mission, the associated bookings are also deleted.
 */
function removeWorkerFromMission($conn, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id']; // This is actually an assignment_id

    if (!$worker_id || !$mission_id) {
        respondWithError('Worker ID and Mission ID are required.');
    }

    $conn->beginTransaction();

    try {
        // 1. Get the mission_group_id from the specific assignment record that was clicked.
        $stmt_get_group = $conn->prepare("SELECT mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
        $stmt_get_group->execute([$mission_id]);
        $mission_group_id = $stmt_get_group->fetchColumn();

        if (!$mission_group_id) {
            $conn->rollBack();
            respondWithError('Mission non trouvée ou mal configurée.');
            return;
        }

        // 2. Delete the specific worker's assignment(s) for this mission group.
        // This is important for multi-day missions where a worker might have multiple assignment rows under one group ID.
        // However, based on current logic, each day has a unique group ID, so this will only delete one record.
        $stmt_delete_worker = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND mission_group_id = ?");
        $stmt_delete_worker->execute([$worker_id, $mission_group_id]);

        // 3. Check if any other assignments (i.e., any other workers) exist for this mission group.
        $stmt_check_remaining = $conn->prepare("SELECT COUNT(*) FROM Planning_Assignments WHERE mission_group_id = ?");
        $stmt_check_remaining->execute([$mission_group_id]);
        $remaining_assignments = $stmt_check_remaining->fetchColumn();

        // 4. If no assignments remain, it was the last worker. Delete the associated bookings.
        if ($remaining_assignments == 0) {
            $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
            $stmt_delete_bookings->execute([$mission_group_id]);
        }

        $conn->commit();
        respondWithSuccess('Ouvrier retiré de la mission. Les réservations ont été mises à jour si nécessaire.');

    } catch (Exception $e) {
        $conn->rollBack();
        respondWithError('Erreur lors du retrait de l\'ouvrier: ' . $e->getMessage(), 500);
    }
}

function toggleMissionValidation($conn, $data) {
    $mission_id = $data['mission_id'];
    $stmt_orig = $conn->prepare("SELECT is_validated, mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$original_mission || !$original_mission['mission_group_id']) respondWithError('Mission non trouvée.');
    $new_status = $original_mission['is_validated'] ? 0 : 1;
    $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET is_validated = ? WHERE mission_group_id = ?");
    $stmt_update->execute([$new_status, $original_mission['mission_group_id']]);
    respondWithSuccess('Statut de validation mis à jour.');
}
?>
