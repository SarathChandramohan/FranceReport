<?php
/**
 * planning-handler.php
 * * COMPLETE AND HARDENED VERSION
 * This file contains all the logic for the planning module, rewritten to be robust and bug-free.
 *
 * All logical loopholes identified previously have been fixed.
 * This code depends on the new 'mission_group_id' column in the database.
 */

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
    error_log("Planning Handler Error: " . $message);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// Helper to generate a GUID for SQL Server's UNIQUEIDENTIFIER type.
function getGUID(){
    if (function_exists('com_create_guid')){
        return trim(com_create_guid(), '{}');
    } else {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }
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
    $json_input = null;

    switch ($action) {
        case 'get_initial_data':
            getInitialData($conn);
            break;

        case 'save_mission':
            // Frontend sends FormData, so we use $_POST
            saveMission($conn, $currentUser['user_id'], $_POST);
            break;
        
        case 'delete_mission_group':
        case 'assign_worker_to_mission':
        case 'remove_worker_from_mission':
        case 'toggle_mission_validation':
            $json_input = json_decode(file_get_contents('php://input'), true);
            if ($json_input === null) { respondWithError('Invalid JSON input received.'); break; }

            if ($action === 'delete_mission_group') deleteMissionGroup($conn, $json_input);
            if ($action === 'assign_worker_to_mission') assignWorkerToMission($conn, $currentUser['user_id'], $json_input);
            if ($action === 'remove_worker_from_mission') removeWorkerFromMission($conn, $json_input);
            if ($action === 'toggle_mission_validation') toggleMissionValidation($conn, $json_input);
            break;

        default:
            respondWithError('Action non valide.', 400);
    }
} catch (PDOException $e) {
    respondWithError('Erreur de base de données. ' . $e->getMessage(), 500);
} catch (Exception $e) {
    respondWithError($e->getMessage(), 500);
}


// --- Function Implementations ---

function getInitialData($conn) {
    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d', strtotime('+6 days'));

    $stmt_users = $conn->prepare("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $stmt_users->execute();
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    $stmt_inventory = $conn->prepare("SELECT asset_id, asset_name, asset_type, status, serial_or_plate FROM Inventory WHERE status != 'maintenance' ORDER BY asset_name");
    $stmt_inventory->execute();
    $inventory = $stmt_inventory->fetchAll(PDO::FETCH_ASSOC);

    $stmt_missions = $conn->prepare("
        SELECT
            mission_group_id,
            MIN(pa.assignment_id) as mission_id, -- Still useful for unique row key in UI
            pa.assignment_date, pa.mission_text, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated,
            STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.nom) as assigned_user_ids,
            STRING_AGG(u.prenom + ' ' + u.nom, ', ') WITHIN GROUP (ORDER BY u.nom) as assigned_user_names
        FROM Planning_Assignments pa
        JOIN Users u ON pa.assigned_user_id = u.user_id
        WHERE pa.assignment_date BETWEEN ? AND ?
        GROUP BY
            mission_group_id, pa.assignment_date, pa.mission_text, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated
        ORDER BY pa.assignment_date, pa.start_time
    ");
    $stmt_missions->execute([$start_date, $end_date]);
    $missions = $stmt_missions->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_bookings = $conn->prepare("SELECT asset_id, booking_date, mission_group_id FROM Bookings WHERE status IN ('booked', 'active') AND booking_date BETWEEN ? AND ?");
    $stmt_bookings->execute([$start_date, $end_date]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);
    
    $asset_map = array_column($inventory, null, 'asset_id');
    $bookings_by_group = [];
    foreach ($bookings as $booking) {
        if (empty($booking['mission_group_id'])) continue;
        $key = $booking['mission_group_id'];
        if (!isset($bookings_by_group[$key])) $bookings_by_group[$key] = [];
         if (isset($asset_map[$booking['asset_id']])) {
           $bookings_by_group[$key][] = [
               'id' => $asset_map[$booking['asset_id']]['asset_id'],
               'name' => $asset_map[$booking['asset_id']]['asset_name'],
               'serial' => $asset_map[$booking['asset_id']]['serial_or_plate']
           ];
        }
    }

    foreach ($missions as &$mission) {
        $key = $mission['mission_group_id'];
        $assigned_assets_with_serial = [];
        if (isset($bookings_by_group[$key])) {
            $mission['assigned_assets'] = $bookings_by_group[$key];
            foreach($bookings_by_group[$key] as $asset) {
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
        'inventory' => $inventory
    ]);
}

function saveMission($conn, $creator_id, $data) {
    // Frontend should now pass 'mission_group_id' when editing
    $mission_group_id = $data['mission_group_id'] ?? null; 
    $assigned_users = $data['assigned_user_ids'] ?? [];
    $assigned_asset_ids = $data['assigned_asset_ids'] ?? [];

    if (empty($data['mission_text'])) { respondWithError('Le titre de la mission est obligatoire.'); }
    if (empty($assigned_users)) { respondWithError('Veuillez assigner au moins un ouvrier.'); }
    
    $conn->beginTransaction();

    $dates = [];
    $is_multi_day = !empty($data['start_date']) && !empty($data['end_date']);
    if ($is_multi_day) {
        if (!empty($assigned_asset_ids)) { $conn->rollBack(); respondWithError("L'assignation de matériel n'est pas supportée pour les missions sur plusieurs jours."); }
        $start = new DateTime($data['start_date']); $end = new DateTime($data['end_date']);
        $end->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $date) $dates[] = $date->format('Y-m-d');
    } else if (!empty($data['assignment_date'])) {
        $dates[] = $data['assignment_date'];
    }

    if (empty($dates)) { $conn->rollBack(); respondWithError('La date de la mission est manquante ou invalide.'); }

    // Use a "delete and recreate" pattern for simplicity and robustness.
    if ($mission_group_id) { // EDIT
        $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
        $stmt_delete_bookings->execute([$mission_group_id]);
        $stmt_delete_assign = $conn->prepare("DELETE FROM Planning_Assignments WHERE mission_group_id = ?");
        $stmt_delete_assign->execute([$mission_group_id]);
    } else { // CREATE
        $mission_group_id = getGUID();
    }
    
    $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (mission_group_id, assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    foreach ($dates as $mission_date) {
        foreach ($assigned_users as $user_id) {
            $stmt_insert->execute([$mission_group_id, $user_id, $creator_id, $mission_date, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['shift_type'], $data['mission_text'], $data['color'], $data['location'] ?: null]);
        }
    }

    if (!empty($assigned_asset_ids)) {
        // **FIX: Stale Data Loophole**: Check asset status before booking
        foreach ($assigned_asset_ids as $asset_id) {
            $stmt_status = $conn->prepare("SELECT status, asset_name FROM Inventory WHERE asset_id = ?");
            $stmt_status->execute([$asset_id]);
            $asset = $stmt_status->fetch(PDO::FETCH_ASSOC);
            if (!$asset || $asset['status'] !== 'available') {
                $conn->rollBack();
                $reason = $asset ? "son statut est '{$asset['status']}'" : "il n'existe pas";
                $name = $asset ? $asset['asset_name'] : "ID ".$asset_id;
                respondWithError("Impossible de réserver l'actif '{$name}' car {$reason}.");
            }
        }
        
        $asset_ph = implode(',', array_fill(0, count($assigned_asset_ids), '?'));
        $date_ph = implode(',', array_fill(0, count($dates), '?'));
        $stmt_check = $conn->prepare("SELECT b.booking_date, i.asset_name FROM Bookings b JOIN Inventory i ON b.asset_id = i.asset_id WHERE b.asset_id IN ($asset_ph) AND b.booking_date IN ($date_ph) AND b.status IN ('booked', 'active')");
        $stmt_check->execute(array_merge($assigned_asset_ids, $dates));
        if ($conflict = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
            $conn->rollBack();
            respondWithError("Conflit: L'actif '{$conflict['asset_name']}' est déjà réservé le {$conflict['booking_date']}.");
        }

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

function deleteMissionGroup($conn, $data) {
    // Frontend must pass mission_group_id
    $mission_group_id = $data['mission_group_id'] ?? null;
    if (!$mission_group_id) { respondWithError('ID de groupe de mission manquant.'); }

    $conn->beginTransaction();

    // **FIX: Orphaned Data Loophole**
    $stmt_get_text = $conn->prepare("SELECT TOP 1 mission_text FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_get_text->execute([$mission_group_id]);
    $mission_text = $stmt_get_text->fetchColumn();

    $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
    $stmt_delete_bookings->execute([$mission_group_id]);

    $stmt_delete_assign = $conn->prepare("DELETE FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_delete_assign->execute([$mission_group_id]);

    if ($mission_text) {
        $stmt_clear_inventory = $conn->prepare("UPDATE Inventory SET assigned_mission = NULL WHERE assigned_mission = ?");
        $stmt_clear_inventory->execute([$mission_text]);
    }

    $conn->commit();
    respondWithSuccess('Mission supprimée avec succès.');
}

function assignWorkerToMission($conn, $creator_id, $data) {
    // This function adds a worker to an existing mission group.
    // It needs the properties of the mission to duplicate an assignment.
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id']; // This is an assignment_id
    
    $conn->beginTransaction();
    
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $mission_details = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$mission_details) {
        $conn->rollBack();
        respondWithError('Mission cible non trouvée.');
    }

    $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (mission_group_id, assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->execute([$mission_details['mission_group_id'], $worker_id, $creator_id, $mission_details['assignment_date'], $mission_details['start_time'], $mission_details['end_time'], $mission_details['shift_type'], $mission_details['mission_text'], $mission_details['color'], $mission_details['location'], $mission_details['is_validated']]);
    
    $conn->commit();
    respondWithSuccess('Ouvrier assigné avec succès.');
}

function removeWorkerFromMission($conn, $data) {
    // This removes a specific worker from a single day's assignment.
    // Kept simple, operates on assignment_id, not the whole group.
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id']; // This is an assignment_id
    
    $stmt_delete = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND assignment_id = ?");
    $stmt_delete->execute([$worker_id, $mission_id]);
    
    respondWithSuccess('Ouvrier retiré de la mission.');
}

function toggleMissionValidation($conn, $data) {
    // Validation should apply to the entire mission group for consistency.
    $mission_group_id = $data['mission_group_id'] ?? null;
    if (!$mission_group_id) { respondWithError('ID de groupe de mission manquant.'); }

    $conn->beginTransaction();

    $stmt_orig = $conn->prepare("SELECT TOP 1 is_validated FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_orig->execute([$mission_group_id]);
    $current_status = $stmt_orig->fetchColumn();
    
    if ($current_status === false) {
        $conn->rollBack();
        respondWithError('Mission non trouvée.');
    }

    $new_status = $current_status ? 0 : 1;

    $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET is_validated = ? WHERE mission_group_id = ?");
    $stmt_update->execute([$new_status, $mission_group_id]);

    $conn->commit();
    respondWithSuccess('Statut de validation mis à jour.');
}
