<?php
// planning-handler.php (Corrected version for temporary missions)

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
        case 'save_mission': // This is now for creating AND validating a new mission from the frontend
            saveMission($conn, $currentUser['user_id'], $input);
            break;
        case 'update_mission': // New action to update existing missions
            updateMission($conn, $input);
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
            SELECT *, COUNT(*) OVER (PARTITION BY assigned_user_id, assignment_date) as daily_assignment_count
            FROM Planning_Assignments
            WHERE assignment_date BETWEEN ? AND ?
        )
        SELECT
            MIN(pa.assignment_id) as mission_id, -- A representative ID for the group on that day
            pa.mission_group_id,
            pa.assignment_date, pa.mission_text, pa.comments, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated,
            STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.nom) as assigned_user_ids,
            STRING_AGG(u.prenom + ' ' + u.nom, ', ') WITHIN GROUP (ORDER BY u.nom) as assigned_user_names,
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

function getLeaveTypeName($typeKey) {
    $types = ['cp' => 'Congés Payés', 'rtt' => 'RTT', 'sans-solde' => 'Congé Sans Solde', 'special' => 'Congé Spécial', 'maladie' => 'Arrêt Maladie'];
    return $types[$typeKey] ?? ucfirst($typeKey);
}

function getWorkerStatusForDate($conn, $date) {
    if (!$date) respondWithError('Date non fournie.');
    $stmt_assigned = $conn->prepare("SELECT DISTINCT assigned_user_id FROM Planning_Assignments WHERE assignment_date = ?");
    $stmt_assigned->execute([$date]);
    $assigned_users = $stmt_assigned->fetchAll(PDO::FETCH_COLUMN, 0);
    $stmt_on_leave = $conn->prepare("SELECT user_id, type_conge FROM Conges WHERE ? BETWEEN date_debut AND date_fin AND status = 'approved'");
    $stmt_on_leave->execute([$date]);
    $on_leave_data = $stmt_on_leave->fetchAll(PDO::FETCH_KEY_PAIR);
    $stmt_all_users = $conn->prepare("SELECT user_id FROM Users WHERE status = 'Active'");
    $stmt_all_users->execute();
    $all_users = $stmt_all_users->fetchAll(PDO::FETCH_COLUMN, 0);
    $worker_statuses = [];
    foreach ($all_users as $user_id) {
        $status = 'available'; $leave_type = null;
        if (in_array($user_id, $assigned_users)) { $status = 'assigned'; } 
        elseif (isset($on_leave_data[$user_id])) { $status = 'on_leave'; $leave_type = getLeaveTypeName($on_leave_data[$user_id]); }
        $worker_statuses[] = ['user_id' => $user_id, 'status' => $status, 'leave_type' => $leave_type];
    }
    respondWithSuccess('Statuts des ouvriers récupérés.', $worker_statuses);
}

// Function to CREATE a new mission, saved directly as VALIDATED
function saveMission($conn, $creator_id, $data) {
    $assigned_users = $data['assigned_user_ids'] ?? [];
    $assigned_asset_ids = $data['assigned_asset_ids'] ?? [];
    $comments = $data['comments'] ?? '';
    
    if (empty($data['mission_text'])) respondWithError('Le titre de la mission est obligatoire.');
    if (empty($assigned_users)) respondWithError('Veuillez assigner au moins un ouvrier.');
    
    $dates = [];
    if (!empty($data['start_date']) && !empty($data['end_date'])) {
        $period = new DatePeriod(new DateTime($data['start_date']), new DateInterval('P1D'), (new DateTime($data['end_date']))->modify('+1 day'));
        foreach ($period as $date) $dates[] = $date->format('Y-m-d');
    } elseif (!empty($data['assignment_date'])) {
        $dates[] = $data['assignment_date'];
    }

    if (empty($dates)) {
        respondWithError('La date de la mission est obligatoire.');
    }

    $conn->beginTransaction();

    try {
        if (!empty($assigned_asset_ids) && !empty($dates)) {
            $params = array_merge($assigned_asset_ids, $dates);
            $placeholders_assets = implode(',', array_fill(0, count($assigned_asset_ids), '?'));
            $placeholders_dates = implode(',', array_fill(0, count($dates), '?'));
            $sql_check = "SELECT b.booking_date, i.asset_name FROM Bookings b JOIN Inventory i ON b.asset_id = i.asset_id WHERE b.asset_id IN ($placeholders_assets) AND b.booking_date IN ($placeholders_dates) AND b.status IN ('booked', 'active')";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->execute($params);
            if ($conflict = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
                $conn->rollBack();
                respondWithError("Conflit: L'actif '{$conflict['asset_name']}' est déjà réservé le " . date('d/m/Y', strtotime($conflict['booking_date'])) . ".");
            }
        }

        $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, comments, color, location, is_validated, mission_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt_book = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status, mission_group_id) VALUES (?, NULL, ?, ?, 'booked', ?)");

        $mission_group_id = $conn->query("SELECT NEWID()")->fetchColumn();

        foreach ($dates as $mission_date) {
            foreach ($assigned_users as $user_id) {
                $stmt_insert->execute([$user_id, $creator_id, $mission_date, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['shift_type'], $data['mission_text'], $comments, $data['color'], $data['location'] ?: null, $mission_group_id]);
            }
            if (!empty($assigned_asset_ids)) {
                foreach ($assigned_asset_ids as $asset_id) {
                    $stmt_book->execute([$asset_id, $mission_date, $data['mission_text'], $mission_group_id]);
                }
            }
        }
        $conn->commit();
        respondWithSuccess('Mission enregistrée et validée avec succès.');

    } catch (Exception $e) {
        $conn->rollBack();
        respondWithError('Erreur lors de la création de la mission: ' . $e->getMessage(), 500);
    }
}

// Function to UPDATE an existing mission's details
function updateMission($conn, $data) {
    $mission_group_id = $data['mission_group_id'] ?? null;
    $assigned_asset_ids = $data['assigned_asset_ids'] ?? [];
    $comments = $data['comments'] ?? '';

    if (!$mission_group_id) respondWithError('ID de groupe de mission manquant.');
    if (empty($data['mission_text'])) respondWithError('Le titre de la mission est obligatoire.');

    $conn->beginTransaction();

    try {
        $stmt_get_dates = $conn->prepare("SELECT DISTINCT assignment_date FROM Planning_Assignments WHERE mission_group_id = ?");
        $stmt_get_dates->execute([$mission_group_id]);
        $mission_dates = $stmt_get_dates->fetchAll(PDO::FETCH_COLUMN);

        if (empty($mission_dates)) {
            $conn->rollBack();
            respondWithError("Mission à mettre à jour non trouvée ou sans date assignée.");
        }
        
        // Asset conflict check
        if (!empty($assigned_asset_ids)) {
            $params = array_merge($assigned_asset_ids, $mission_dates, [$mission_group_id]);
            $placeholders_assets = implode(',', array_fill(0, count($assigned_asset_ids), '?'));
            $placeholders_dates = implode(',', array_fill(0, count($mission_dates), '?'));
            $sql_check = "SELECT b.booking_date, i.asset_name FROM Bookings b JOIN Inventory i ON b.asset_id = i.asset_id WHERE b.asset_id IN ($placeholders_assets) AND b.booking_date IN ($placeholders_dates) AND b.mission_group_id != ? AND b.status IN ('booked', 'active')";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->execute($params);
            if ($conflict = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
                $conn->rollBack();
                respondWithError("Conflit: L'actif '{$conflict['asset_name']}' est déjà réservé le " . date('d/m/Y', strtotime($conflict['booking_date'])) . ".");
            }
        }

        // Update main mission details
        $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET mission_text = ?, comments = ?, start_time = ?, end_time = ?, location = ?, shift_type = ?, color = ? WHERE mission_group_id = ?");
        $stmt_update->execute([$data['mission_text'], $comments, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['location'] ?: null, $data['shift_type'], $data['color'], $mission_group_id]);

        // Sync bookings: delete old, insert new
        $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
        $stmt_delete_bookings->execute([$mission_group_id]);

        if (!empty($assigned_asset_ids)) {
            $stmt_book = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status, mission_group_id) VALUES (?, NULL, ?, ?, 'booked', ?)");
            foreach ($mission_dates as $date) {
                foreach ($assigned_asset_ids as $asset_id) {
                    $stmt_book->execute([$asset_id, $date, $data['mission_text'], $mission_group_id]);
                }
            }
        }
        
        $conn->commit();
        respondWithSuccess('Mission mise à jour avec succès.');

    } catch (Exception $e) {
        $conn->rollBack();
        respondWithError('Erreur lors de la mise à jour de la mission: ' . $e->getMessage(), 500);
    }
}


function deleteMissionGroup($conn, $data) {
    $mission_group_id = $data['mission_group_id'];
    if (!$mission_group_id) respondWithError('ID de groupe de mission manquant.');
    $conn->beginTransaction();
    try {
        $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
        $stmt_delete_bookings->execute([$mission_group_id]);
        $stmt_delete_assignments = $conn->prepare("DELETE FROM Planning_Assignments WHERE mission_group_id = ?");
        $stmt_delete_assignments->execute([$mission_group_id]);
        $conn->commit();
        respondWithSuccess('Mission et toutes ses affectations ont été supprimées.');
    } catch (Exception $e) {
        $conn->rollBack();
        respondWithError('Erreur lors de la suppression de la mission: ' . $e->getMessage(), 500);
    }
}

function assignWorkerToMission($conn, $creator_id, $data) {
    $worker_id = $data['worker_id'];
    $mission_group_id = $data['mission_group_id'];
    if (!$worker_id || !$mission_group_id) respondWithError('ID de l\'ouvrier et de la mission requis.');
    $conn->beginTransaction();
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_orig->execute([$mission_group_id]);
    $mission_template = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$mission_template) { $conn->rollBack(); respondWithError('Mission cible non trouvée.'); }
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Planning_Assignments WHERE mission_group_id = ? AND assigned_user_id = ?");
    $stmt_check->execute([$mission_group_id, $worker_id]);
    if ($stmt_check->fetchColumn() > 0) { $conn->rollBack(); respondWithSuccess('Ouvrier déjà assigné.'); return; }
    $stmt_dates = $conn->prepare("SELECT DISTINCT assignment_date FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_dates->execute([$mission_group_id]);
    $mission_dates = $stmt_dates->fetchAll(PDO::FETCH_COLUMN, 0);
    $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, comments, color, location, is_validated, mission_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($mission_dates as $date) {
        $stmt_insert->execute([$worker_id, $creator_id, $date, $mission_template['start_time'], $mission_template['end_time'], $mission_template['shift_type'], $mission_template['mission_text'], $mission_template['comments'], $mission_template['color'], $mission_template['location'], $mission_template['is_validated'], $mission_group_id]);
    }
    $conn->commit();
    respondWithSuccess('Ouvrier assigné.');
}

function removeWorkerFromMission($conn, $data) {
    $worker_id = $data['worker_id'];
    $mission_group_id = $data['mission_group_id'];
    if (!$worker_id || !$mission_group_id) respondWithError('ID de l\'ouvrier et de la mission requis.');
    $conn->beginTransaction();
    try {
        $stmt_delete_worker = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND mission_group_id = ?");
        $stmt_delete_worker->execute([$worker_id, $mission_group_id]);
        $stmt_check_remaining = $conn->prepare("SELECT COUNT(*) FROM Planning_Assignments WHERE mission_group_id = ?");
        $stmt_check_remaining->execute([$mission_group_id]);
        if ($stmt_check_remaining->fetchColumn() == 0) {
            $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
            $stmt_delete_bookings->execute([$mission_group_id]);
        }
        $conn->commit();
        respondWithSuccess('Ouvrier retiré de la mission.');
    } catch (Exception $e) {
        $conn->rollBack();
        respondWithError('Erreur lors du retrait de l\'ouvrier: ' . $e->getMessage(), 500);
    }
}

function toggleMissionValidation($conn, $data) {
    $mission_group_id = $data['mission_group_id'];
    if (!$mission_group_id) respondWithError('ID de groupe de mission manquant.');
    $stmt_orig = $conn->prepare("SELECT TOP 1 is_validated FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_orig->execute([$mission_group_id]);
    $current_status = $stmt_orig->fetchColumn();
    $new_status = $current_status ? 0 : 1;
    $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET is_validated = ? WHERE mission_group_id = ?");
    $stmt_update->execute([$new_status, $mission_group_id]);
    respondWithSuccess('Statut de validation mis à jour.');
}

function validateAllForWeek($conn, $data) {
    if (empty($data['start_date']) || empty($data['end_date'])) respondWithError('Dates de début et de fin requises.');
    $stmt = $conn->prepare("UPDATE Planning_Assignments SET is_validated = 1 WHERE assignment_date BETWEEN ? AND ?");
    $stmt->execute([$data['start_date'], $data['end_date']]);
    respondWithSuccess('Toutes les planifications pour la semaine ont été activées.');
}

?>
