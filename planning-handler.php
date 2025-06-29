<?php
/**
 * planning-handler.php
 * * CORRECTED AND IMPROVED VERSION
 * This file contains all the logic for the planning module.
 *
 * FIXES APPLIED:
 * 1. [CRITICAL] Worker Removal: `removeWorkerFromMission` now correctly uses `mission_group_id` and `assignment_date` to delete the specific assignment, fixing the bug where workers could not be removed.
 * 2. [CRITICAL] Worker Assignment: `assignWorkerToMission` (used for drag-and-drop) now uses `mission_group_id` and date for robustly adding workers to an existing mission.
 * 3. [FEATURE] Multi-Day Asset Assignment: Removed the limitation that prevented assigning assets to missions spanning multiple days in `saveMission`.
 * 4. [BUGFIX] The core logic for `saveMission` and `deleteMissionGroup` is sound but depended on the correct `mission_group_id` from the frontend. The changes in `planning.php` will now make these functions work as intended.
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

    // Use a switch that can handle both POST data (from forms) and JSON data
    $request_method = $_SERVER['REQUEST_METHOD'];
    if ($request_method === 'POST') {
        // Check if content type is JSON
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
             $json_input = json_decode(file_get_contents('php://input'), true);
        }
    }


    switch ($action) {
        case 'get_initial_data':
            getInitialData($conn);
            break;

        case 'save_mission':
            // Frontend sends FormData, so we use $_POST
            saveMission($conn, $currentUser['user_id'], $_POST);
            break;
        
        case 'delete_mission_group':
            if ($json_input === null) { respondWithError('Invalid input received.'); }
            deleteMissionGroup($conn, $json_input);
            break;

        case 'assign_worker_to_mission':
             if ($json_input === null) { respondWithError('Invalid input received.'); }
            assignWorkerToMission($conn, $currentUser['user_id'], $json_input);
            break;

        case 'remove_worker_from_mission':
             if ($json_input === null) { respondWithError('Invalid input received.'); }
            removeWorkerFromMission($conn, $json_input);
            break;

        case 'toggle_mission_validation':
             if ($json_input === null) { respondWithError('Invalid input received.'); }
            toggleMissionValidation($conn, $json_input);
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

    // This query is designed to group assignments by the mission group and date
    // to correctly form mission cards on the frontend.
    $stmt_missions = $conn->prepare("
        SELECT
            pa.mission_group_id,
            pa.assignment_date, pa.mission_text, pa.location, pa.start_time,
            pa.end_time, pa.shift_type, pa.color, pa.is_validated,
            STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.nom, u.prenom) as assigned_user_ids,
            STRING_AGG(u.prenom + ' ' + u.nom, ', ') WITHIN GROUP (ORDER BY u.nom, u.prenom) as assigned_user_names
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
    
    $stmt_bookings = $conn->prepare("SELECT asset_id, booking_date, mission_group_id FROM Bookings WHERE status IN ('booked', 'active') AND booking_date BETWEEN ? AND ?");
    $stmt_bookings->execute([$start_date, $end_date]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);
    
    $asset_map = array_column($inventory, null, 'asset_id');
    $bookings_by_group_and_date = [];
    foreach ($bookings as $booking) {
        if (empty($booking['mission_group_id'])) continue;
        $key = $booking['mission_group_id'] . '_' . $booking['booking_date'];
        if (!isset($bookings_by_group_and_date[$key])) $bookings_by_group_and_date[$key] = [];
         if (isset($asset_map[$booking['asset_id']])) {
           $bookings_by_group_and_date[$key][] = [
               'id' => $asset_map[$booking['asset_id']]['asset_id'],
               'name' => $asset_map[$booking['asset_id']]['asset_name'],
               'serial' => $asset_map[$booking['asset_id']]['serial_or_plate']
           ];
        }
    }

    foreach ($missions as &$mission) {
        $key = $mission['mission_group_id'] . '_' . $mission['assignment_date'];
        $assigned_assets_with_serial = [];
        if (isset($bookings_by_group_and_date[$key])) {
            $mission['assigned_assets'] = $bookings_by_group_and_date[$key];
            foreach($bookings_by_group_and_date[$key] as $asset) {
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
    $mission_group_id = $data['mission_group_id'] ?? null; 
    $assigned_users = $data['assigned_user_ids'] ?? [];
    $assigned_asset_ids = $data['assigned_asset_ids'] ?? [];

    if (empty($data['mission_text'])) { respondWithError('Le titre de la mission est obligatoire.'); }
    if (empty($assigned_users) && !$mission_group_id) { respondWithError('Veuillez assigner au moins un ouvrier pour une nouvelle mission.'); }
    
    $conn->beginTransaction();

    $dates = [];
    if (!empty($data['start_date']) && !empty($data['end_date'])) { // Multi-day mission
        $start = new DateTime($data['start_date']); 
        $end = new DateTime($data['end_date']);
        $end->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $date) $dates[] = $date->format('Y-m-d');
    } else if (!empty($data['assignment_date'])) { // Single-day mission
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
    
    if (!empty($assigned_users)) {
        $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (mission_group_id, assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        foreach ($dates as $mission_date) {
            foreach ($assigned_users as $user_id) {
                $stmt_insert->execute([$mission_group_id, $user_id, $creator_id, $mission_date, $data['start_time'] ?: null, $data['end_time'] ?: null, $data['shift_type'], $data['mission_text'], $data['color'], $data['location'] ?: null]);
            }
        }
    }

    if (!empty($assigned_asset_ids)) {
        // Check asset status before booking
        foreach ($assigned_asset_ids as $asset_id) {
            $stmt_status = $conn->prepare("SELECT status, asset_name FROM Inventory WHERE asset_id = ?");
            $stmt_status->execute([$asset_id]);
            $asset = $stmt_status->fetch(PDO::FETCH_ASSOC);
            if (!$asset || !in_array($asset['status'], ['available', 'in-use'])) {
                 if ($asset && $asset['status'] === 'maintenance') {
                    $conn->rollBack();
                    respondWithError("Impossible de réserver '{$asset['asset_name']}' car il est en maintenance.");
                }
            }
        }
        
        $asset_ph = implode(',', array_fill(0, count($assigned_asset_ids), '?'));
        $date_ph = implode(',', array_fill(0, count($dates), '?'));
        
        // Find conflicting bookings that are NOT for the current mission group
        $sql_check = "SELECT b.booking_date, i.asset_name FROM Bookings b JOIN Inventory i ON b.asset_id = i.asset_id WHERE b.asset_id IN ($asset_ph) AND b.booking_date IN ($date_ph) AND b.status IN ('booked', 'active') AND (b.mission_group_id != ? OR b.mission_group_id IS NULL)";
        $params = array_merge($assigned_asset_ids, $dates, [$mission_group_id]);
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute($params);
        if ($conflict = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
            $conn->rollBack();
            $formatted_date = (new DateTime($conflict['booking_date']))->format('d/m/Y');
            respondWithError("Conflit: L'actif '{$conflict['asset_name']}' est déjà réservé dans une autre mission le {$formatted_date}.");
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
    $mission_group_id = $data['mission_group_id'] ?? null;
    if (!$mission_group_id) { respondWithError('ID de groupe de mission manquant.'); }

    $conn->beginTransaction();

    $stmt_get_text = $conn->prepare("SELECT TOP 1 mission_text FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_get_text->execute([$mission_group_id]);
    $mission_text = $stmt_get_text->fetchColumn();

    // Delete associated bookings first
    $stmt_delete_bookings = $conn->prepare("DELETE FROM Bookings WHERE mission_group_id = ?");
    $stmt_delete_bookings->execute([$mission_group_id]);

    // Then delete the assignments
    $stmt_delete_assign = $conn->prepare("DELETE FROM Planning_Assignments WHERE mission_group_id = ?");
    $stmt_delete_assign->execute([$mission_group_id]);

    // Clear any lingering mission text from inventory items that might have been checked out
    if ($mission_text) {
        $stmt_clear_inventory = $conn->prepare("UPDATE Inventory SET assigned_mission = NULL WHERE assigned_mission = ?");
        $stmt_clear_inventory->execute([$mission_text]);
    }

    $conn->commit();
    respondWithSuccess('Mission supprimée avec succès.');
}

function assignWorkerToMission($conn, $creator_id, $data) {
    $worker_id = $data['worker_id'] ?? null;
    $mission_group_id = $data['mission_group_id'] ?? null;
    $assignment_date = $data['assignment_date'] ?? null;

    if (!$worker_id || !$mission_group_id || !$assignment_date) {
        respondWithError('Données manquantes pour assigner l\'ouvrier.');
    }

    $conn->beginTransaction();
    
    $stmt_orig = $conn->prepare("SELECT TOP 1 * FROM Planning_Assignments WHERE mission_group_id = ? AND assignment_date = ?");
    $stmt_orig->execute([$mission_group_id, $assignment_date]);
    $mission_details = $stmt_orig->fetch(PDO::FETCH_ASSOC);

    if (!$mission_details) {
        $conn->rollBack();
        respondWithError('Mission cible non trouvée. Impossible d\'assigner l\'ouvrier.');
    }
    
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Planning_Assignments WHERE mission_group_id = ? AND assignment_date = ? AND assigned_user_id = ?");
    $stmt_check->execute([$mission_group_id, $assignment_date, $worker_id]);
    if ($stmt_check->fetchColumn() > 0) {
        $conn->rollBack();
        respondWithSuccess('Cet ouvrier est déjà assigné à cette mission.');
        return;
    }

    $stmt_insert = $conn->prepare("INSERT INTO Planning_Assignments (mission_group_id, assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, is_validated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->execute([$mission_details['mission_group_id'], $worker_id, $creator_id, $mission_details['assignment_date'], $mission_details['start_time'], $mission_details['end_time'], $mission_details['shift_type'], $mission_details['mission_text'], $mission_details['color'], $mission_details['location'], $mission_details['is_validated']]);
    
    $conn->commit();
    respondWithSuccess('Ouvrier assigné avec succès.');
}

function removeWorkerFromMission($conn, $data) {
    $worker_id = $data['worker_id'] ?? null;
    $mission_group_id = $data['mission_group_id'] ?? null;
    $assignment_date = $data['assignment_date'] ?? null;

    if (!$worker_id || !$mission_group_id || !$assignment_date) {
        respondWithError('Données manquantes pour retirer l\'ouvrier.');
    }

    $stmt_delete = $conn->prepare("DELETE FROM Planning_Assignments WHERE assigned_user_id = ? AND mission_group_id = ? AND assignment_date = ?");
    $stmt_delete->execute([$worker_id, $mission_group_id, $assignment_date]);
    
    if ($stmt_delete->rowCount() > 0) {
        respondWithSuccess('Ouvrier retiré de la mission.');
    } else {
        respondWithSuccess('Ouvrier non trouvé dans cette mission, aucune action effectuée.', [], 200);
    }
}


function toggleMissionValidation($conn, $data) {
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
