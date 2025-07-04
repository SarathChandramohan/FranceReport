<?php
// planning-handler.php

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
        default:
            respondWithError('Action non valide.', 400);
    }
} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}

/**
 * Fetches all necessary data for the initial page load.
 */
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

    // Use mission_group_id for more reliable grouping
    $stmt_missions = $conn->prepare("
        SELECT
            MIN(pa.assignment_id) as mission_id,
            pa.mission_group_id,
            pa.assignment_date, pa.mission_text, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated,
            STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.nom) as assigned_user_ids,
            STRING_AGG(u.prenom + ' ' + u.nom, ', ') WITHIN GROUP (ORDER BY u.nom) as assigned_user_names
        FROM Planning_Assignments pa
        JOIN Users u ON pa.assigned_user_id = u.user_id
        WHERE pa.assignment_date BETWEEN ? AND ?
        GROUP BY
            pa.mission_group_id, pa.assignment_date, pa.mission_text, pa.location, pa.start_time,
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
           $bookings_map[$key][] = [
               'id' => $asset_map[$booking['asset_id']]['asset_id'],
               'name' => $asset_map[$booking['asset_id']]['asset_name'],
               'serial' => $asset_map[$booking['asset_id']]['serial_or_plate']
           ];
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
    }
    unset($mission);

    respondWithSuccess('Données initiales chargées.', [
        'staff' => $users, 
        'missions' => $missions,
        'inventory' => $inventory,
        'bookings' => $bookings
    ]);
}

/**
 * Creates/updates a mission and handles associated asset bookings for the team.
 */
function saveMission($conn, $creator_id, $data) {
    $mission_id = $data['mission_id'] ?? null;
    $assigned_users = $data['assigned_user_ids'] ?? [];
    $assigned_asset_ids = $data['assigned_asset_ids'] ?? [];

    if (empty($data['mission_text'])) {
        respondWithError('Le titre de la mission est obligatoire.');
    }
    
    $conn->beginTransaction();

    $dates = [];
    $is_multi_day = !empty($data['start_date']) && !empty($data['end_date']);
    if ($is_multi_day && !empty($assigned_asset_ids)) {
        respondWithError("L'assignation de matériel n'est pas supportée pour les missions sur plusieurs jours.");
    }
    
    if ($is_multi_day) {
        $start = new DateTime($data['start_date']);
        $end = new DateTime($data['end_date']);
        $end->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $date) $dates[] = $date->format('Y-m-d');
    } else if (!empty($data['assignment_date'])) {
        $dates[] = $data['assignment_date'];
    }
    if (empty($dates) && !$mission_id) {
        $conn->rollBack();
        respondWithError('La date de la mission est obligatoire.');
    }

    $mission_group_id = null;
    if ($mission_id) {
        $stmt_find_group = $conn->prepare("SELECT mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
        $stmt_find_group->execute([$mission_id]);
        $mission_group_id = $stmt_find_group->fetchColumn();
    }
    
    // If no group ID exists (new mission or old data), generate one.
    if (!$mission_group_id) {
        $mission_group_id = $conn->query("SELECT NEWID()")->fetchColumn();
    }

    // First, clear any existing bookings for this mission group to avoid conflicts
    if ($mission_group_id) {
        $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
        $stmt_delete_bookings->execute([$mission_group_id]);
    }

    // Then, check for conflicts with other missions' bookings
    if (!empty($assigned_asset_ids) && !empty($dates)) {
        $date_ph = implode(',', array_fill(0, count($dates), '?'));
        $asset_ph = implode(',', array_fill(0, count($assigned_asset_ids), '?'));
        
        $sql_check = "SELECT b.booking_date, i.asset_name FROM Bookings b JOIN Inventory i ON b.asset_id = i.asset_id WHERE b.asset_id IN ($asset_ph) AND b.booking_date IN ($date_ph) AND b.status IN ('booked', 'active')";
        $params = array_merge($assigned_asset_ids, $dates);
        
        // When updating, exclude the current mission from the conflict check
        if ($mission_id && $mission_group_id) {
            $sql_check .= " AND (b.mission_group_id IS NULL OR b.mission_group_id != ?)";
            $params[] = $mission_group_id;
        }

        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute($params);
        if ($conflict = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
            $conn->rollBack();
            respondWithError("Conflit: L'actif '{$conflict['asset_name']}' est déjà réservé le {$conflict['booking_date']}.");
        }
    }
    
    if ($mission_id) { // UPDATE existing mission
        $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET mission_text = ?, start_time = ?, end_time = ?, location = ?, shift_type = ?, color = ? WHERE mission_group_id = ?");
        $stmt_update->execute([$data['mission_text'], $data['start_time'] ?: null, $data['end_time'] ?: null, $data['location'] ?: null, $data['shift_type'], $data['color'], $mission_group_id]);
    } else { // CREATE new mission
        if (empty($assigned_users)) { $conn->rollBack(); respondWithError('Veuillez assigner au moins un ouvrier.'); }
        
        $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated, mission_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
        foreach ($dates as $mission_date) {
            foreach ($assigned_users as $user_id) {
                $stmt_insert->execute([$user_id, $creator_id, $mission_date, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['shift_type'], $data['mission_text'], $data['color'], $data['location'] ?: null, $mission_group_id]);
            }
        }
    }

    // Finally, create the new bookings for the mission team (user_id = NULL)
    if (!empty($assigned_asset_ids) && !empty($dates)) {
        $stmt_book = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status, mission_group_id) VALUES (?, NULL, ?, ?, 'booked', ?)");
        foreach ($dates as $mission_date) {
            foreach ($assigned_asset_ids as $asset_id) {
                $stmt_book->execute([$asset_id, $mission_date, $data['mission_text'], $mission_group_id]);
            }
        }
    }

    $conn->commit();
    respondWithSuccess('Mission enregistrée avec succès.');
}

/**
 * Deletes a mission group and its associated asset bookings using mission_group_id.
 */
function deleteMissionGroup($conn, $data) {
    $mission_id = $data['mission_id'];
    if (!$mission_id) respondWithError('ID de mission manquant.');
    
    $conn->beginTransaction();

    $stmt_find_group = $conn->prepare("SELECT mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_find_group->execute([$mission_id]);
    $mission_group_id = $stmt_find_group->fetchColumn();
    
    if (!$mission_group_id) {
        $conn->rollBack();
        respondWithError('Mission à supprimer non trouvée ou mal configurée.');
    }

    // Delete associated bookings
    $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
    $stmt_delete_bookings->execute([$mission_group_id]);

    // Delete assignments
    $stmt_delete_assignments = $conn->prepare("DELETE FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_delete_assignments->execute([$mission_group_id]);

    $conn->commit();
    respondWithSuccess('Mission et toutes ses affectations supprimées.');
}

/**
 * Assigns a worker to an existing mission group.
 */
function assignWorkerToMission($conn, $creator_id, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id'];
    
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $mission_details = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$mission_details || !$mission_details['mission_group_id']) {
        respondWithError('Mission cible non trouvée ou mal configurée.');
    }

    // Check if user is already assigned to this mission on this day
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Planning_Assignments WHERE mission_group_id = ? AND assigned_user_id = ?");
    $stmt_check->execute([$mission_details['mission_group_id'], $worker_id]);
    if ($stmt_check->fetchColumn() > 0) {
        respondWithSuccess('Ouvrier déjà assigné à cette mission.');
        return;
    }

    $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated, mission_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->execute([$worker_id, $creator_id, $mission_details['assignment_date'], $mission_details['start_time'], $mission_details['end_time'], $mission_details['shift_type'], $mission_details['mission_text'], $mission_details['color'], $mission_details['location'], $mission_details['is_validated'], $mission_details['mission_group_id']]);
    
    respondWithSuccess('Ouvrier assigné avec succès.');
}

/**
 * Removes a single worker from a mission group.
 */
function removeWorkerFromMission($conn, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id'];
    
    $stmt_orig = $conn->prepare("SELECT mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $mission_group_id = $stmt_orig->fetchColumn();
    if (!$mission_group_id) {
        respondWithError('Mission non trouvée ou mal configurée.');
    }

    $stmt_delete = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND mission_group_id = ?");
    $stmt_delete->execute([$worker_id, $mission_group_id]);
    
    respondWithSuccess('Ouvrier retiré de la mission.');
}


/**
 * Toggles the validation status for an entire mission group.
 */
function toggleMissionValidation($conn, $data) {
    $mission_id = $data['mission_id'];
    
    $stmt_orig = $conn->prepare("SELECT is_validated, mission_group_id FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$original_mission || !$original_mission['mission_group_id']) {
        respondWithError('Mission non trouvée ou mal configurée.');
    }

    $new_status = $original_mission['is_validated'] ? 0 : 1;
    $mission_group_id = $original_mission['mission_group_id'];

    $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET is_validated = ? WHERE mission_group_id = ?");
    $stmt_update->execute([$new_status, $mission_group_id]);

    respondWithSuccess('Statut de validation mis à jour.');
}
?>
