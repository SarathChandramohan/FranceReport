<?php
/**
 * planning-handler.php
 * * Corrected and refactored version.
 * This version resolves a bug causing an error when saving a mission with assigned assets.
 * The primary fix is clarifying the input handling logic to correctly process both
 * JSON and FormData requests based on the specific action being performed.
 */

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

    // The main routing logic has been refactored to handle input sources correctly.
    switch ($action) {
        case 'get_initial_data':
            getInitialData($conn);
            break;

        case 'save_mission':
            // This action receives data as 'multipart/form-data', so we use $_POST.
            saveMission($conn, $currentUser['user_id'], $_POST);
            break;
        
        case 'delete_mission_group':
        case 'assign_worker_to_mission':
        case 'remove_worker_from_mission':
        case 'toggle_mission_validation':
            // These actions receive data as 'application/json'.
            $json_input = json_decode(file_get_contents('php://input'), true);
            if ($json_input === null) {
                respondWithError('Invalid JSON input received.');
                break;
            }

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

function getInitialData($conn) {
    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d', strtotime('+6 days'));

    $stmt_users = $conn->prepare("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $stmt_users->execute();
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    $stmt_inventory = $conn->prepare("SELECT asset_id, asset_name, asset_type, status, serial_or_plate FROM Inventory WHERE status != 'maintenance' ORDER BY asset_name");
    $stmt_inventory->execute();
    $inventory = $stmt_inventory->fetchAll(PDO::FETCH_ASSOC);

    $stmt_bookings = $conn->prepare("SELECT asset_id, booking_date, mission FROM Bookings WHERE status IN ('booked', 'active') AND booking_date BETWEEN ? AND ?");
    $stmt_bookings->execute([$start_date, $end_date]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

    $stmt_missions = $conn->prepare("
        SELECT
            MIN(pa.assignment_id) as mission_id,
            pa.assignment_date, pa.mission_text, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated,
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

    $asset_map = array_column($inventory, null, 'asset_id');
    $bookings_map = [];
    foreach ($bookings as $booking) {
        $key = $booking['booking_date'] . '||' . $booking['mission'];
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
        $key = $mission['assignment_date'] . '||' . $mission['mission_text'];
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

function saveMission($conn, $creator_id, $data) {
    // PHP automatically creates an array for form fields ending in [].
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
        $conn->rollBack();
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

    if (empty($dates)) {
        $conn->rollBack();
        respondWithError('La date de la mission est manquante ou invalide.');
    }

    if ($mission_id) { // This is an EDIT
        $stmt_orig_find = $conn->prepare("SELECT mission_text, assignment_date, shift_type, start_time, location FROM Planning_Assignments WHERE assignment_id = ?");
        $stmt_orig_find->execute([$mission_id]);
        $orig_props = $stmt_orig_find->fetch(PDO::FETCH_ASSOC);

        if ($orig_props) {
            // Find all dates for this recurring mission group to update them all
            $stmt_all_dates = $conn->prepare("SELECT DISTINCT assignment_date FROM Planning_Assignments WHERE mission_text = ? AND shift_type = ? AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00') AND ISNULL(location, '') = ISNULL(?, '')");
            $stmt_all_dates->execute([$orig_props['mission_text'], $orig_props['shift_type'], $orig_props['start_time'], $orig_props['location']]);
            $old_dates = $stmt_all_dates->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($old_dates)) {
                // Delete old asset bookings before creating new ones
                $placeholders = implode(',', array_fill(0, count($old_dates), '?'));
                $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission = ? AND booking_date IN ($placeholders)");
                $stmt_delete_bookings->execute(array_merge([$orig_props['mission_text']], $old_dates));

                // Update the mission details for all occurrences
                $update_placeholders = implode(',', array_fill(0, count($old_dates), '?'));
                $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET mission_text = ?, start_time = ?, end_time = ?, location = ?, shift_type = ?, color = ? WHERE mission_text = ? AND shift_type = ? AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00') AND ISNULL(location, '') = ISNULL(?, '') AND assignment_date IN ($update_placeholders)");
                $params = [$data['mission_text'], $data['start_time'] ?: null, $data['end_time'] ?: null, $data['location'] ?: null, $data['shift_type'], $data['color'], $orig_props['mission_text'], $orig_props['shift_type'], $orig_props['start_time'], $orig_props['location']];
                $stmt_update->execute(array_merge($params, $old_dates));
            }
        }
    } else { // This is a CREATE
        if (empty($assigned_users)) { $conn->rollBack(); respondWithError('Veuillez assigner au moins un ouvrier.'); }
        
        $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        foreach ($dates as $mission_date) {
            foreach ($assigned_users as $user_id) {
                $stmt_insert->execute([$user_id, $creator_id, $mission_date, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['shift_type'], $data['mission_text'], $data['color'], $data['location'] ?: null]);
            }
        }
    }
    
    // This part runs for both CREATE and EDIT to (re)create asset bookings
    if (!empty($assigned_asset_ids)) {
        // Check for booking conflicts
        $date_ph = implode(',', array_fill(0, count($dates), '?'));
        $asset_ph = implode(',', array_fill(0, count($assigned_asset_ids), '?'));
        $stmt_check = $conn->prepare("SELECT b.booking_date, i.asset_name FROM Bookings b JOIN Inventory i ON b.asset_id = i.asset_id WHERE b.asset_id IN ($asset_ph) AND b.booking_date IN ($date_ph) AND b.status IN ('booked', 'active')");
        $stmt_check->execute(array_merge($assigned_asset_ids, $dates));
        if ($conflict = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
            $conn->rollBack();
            respondWithError("Conflit: L'actif '{$conflict['asset_name']}' est déjà réservé le {$conflict['booking_date']}.");
        }

        // Create new bookings for the assigned assets
        $stmt_book = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, NULL, ?, ?, 'booked')");
        foreach ($dates as $mission_date) {
            foreach ($assigned_asset_ids as $asset_id) {
                $stmt_book->execute([$asset_id, $mission_date, $data['mission_text']]);
            }
        }
    }

    $conn->commit();
    respondWithSuccess('Mission enregistrée avec succès.');
}

function deleteMissionGroup($conn, $data) {
    $mission_id = $data['mission_id'];
    $conn->beginTransaction();
    
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$original_mission) {
        $conn->rollBack();
        respondWithError('Mission à supprimer non trouvée.');
    }

    $stmt_all_dates = $conn->prepare("SELECT DISTINCT assignment_date FROM Planning_Assignments WHERE assignment_date = ? AND mission_text = ? AND shift_type = ? AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00') AND ISNULL(location, '') = ISNULL(?, '')");
    $stmt_all_dates->execute([$original_mission['assignment_date'], $original_mission['mission_text'], $original_mission['shift_type'], $original_mission['start_time'], $original_mission['location']]);
    $all_mission_dates = $stmt_all_dates->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($all_mission_dates)) {
        $placeholders = implode(',', array_fill(0, count($all_mission_dates), '?'));
        $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission = ? AND booking_date IN ($placeholders)");
        $stmt_delete_bookings->execute(array_merge([$original_mission['mission_text']], $all_mission_dates));
    }
    
    $stmt_delete = $conn->prepare("DELETE FROM Planning_Assignments WHERE assignment_date = ? AND mission_text = ? AND shift_type = ? AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00') AND ISNULL(location, '') = ISNULL(?, '')");
    $stmt_delete->execute([$original_mission['assignment_date'], $original_mission['mission_text'], $original_mission['shift_type'], $original_mission['start_time'], $original_mission['location']]);

    $conn->commit();
    respondWithSuccess('Mission supprimée.');
}


function assignWorkerToMission($conn, $creator_id, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id'];
    
    $conn->beginTransaction();
    
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $mission_details = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$mission_details) {
        $conn->rollBack();
        respondWithError('Mission cible non trouvée.');
    }

    $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->execute([$worker_id, $creator_id, $mission_details['assignment_date'], $mission_details['start_time'], $mission_details['end_time'], $mission_details['shift_type'], $mission_details['mission_text'], $mission_details['color'], $mission_details['location'], $mission_details['is_validated']]);
    
    $conn->commit();
    respondWithSuccess('Ouvrier assigné avec succès.');
}

function removeWorkerFromMission($conn, $data) {
    $worker_id = $data['worker_id'];
    $mission_id = $data['mission_id'];
    
    $stmt_orig = $conn->prepare("SELECT * FROM Planning_Assignments WHERE assignment_id = ?");
    $stmt_orig->execute([$mission_id]);
    $original_mission = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if (!$original_mission) {
        respondWithError('Mission non trouvée.');
    }

    $stmt_delete = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND assignment_date = ? AND mission_text = ? AND shift_type = ? AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00') AND ISNULL(location, '') = ISNULL(?, '')");
    $stmt_delete->execute([$worker_id, $original_mission['assignment_date'], $original_mission['mission_text'], $original_mission['shift_type'], $original_mission['start_time'], $original_mission['location']]);
    
    respondWithSuccess('Ouvrier retiré de la mission.');
}

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

    $new_status = $original_mission['is_validated'] ? 0 : 1;

    $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET is_validated = ? WHERE assignment_date = ? AND mission_text = ? AND shift_type = ? AND ISNULL(start_time, '00:00:00') = ISNULL(?, '00:00:00') AND ISNULL(location, '') = ISNULL(?, '')");
    $stmt_update->execute([$new_status, $original_mission['assignment_date'], $original_mission['mission_text'], $original_mission['shift_type'], $original_mission['start_time'], $original_mission['location']]);

    $conn->commit();
    respondWithSuccess('Statut de validation mis à jour.');
}
