<?php
// inventory-handler.php

require_once 'session-management.php';
requireLogin();
require_once 'db-connection.php';

header('Content-Type: application/json');
$currentUser = getCurrentUser();
$action = '';
// Check if the request content type is JSON
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strpos($contentType, 'application/json') !== false) {
    // Attempt to read and decode JSON input
    $jsonInput = file_get_contents('php://input');
    $inputData = json_decode($jsonInput, true);

    // If JSON is successfully decoded and contains 'action', use it
    if (json_last_error() === JSON_ERROR_NONE && isset($inputData['action'])) {
        $action = $inputData['action'];
    }
}

// Fallback to $_REQUEST if action not found in JSON or if not a JSON request
if (empty($action) && isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
}

try {
    switch ($action) {
        // INVENTORY & ASSET ACTIONS
        case 'get_inventory': getInventory($conn); break;
        case 'add_asset': addAsset($conn, $currentUser); break;
        case 'update_asset': updateAsset($conn, $currentUser); break;
        case 'delete_asset': deleteAsset($conn, $currentUser); break;
        case 'update_maintenance_status': updateMaintenanceStatus($conn, $currentUser); break;
        
        // BOOKING & SCANNER ACTIONS
        case 'process_scan': processScan($conn, $currentUser); break;
        case 'book_asset': bookAsset($conn, $currentUser); break;
        case 'get_all_bookings': getAllBookings($conn); break;
        case 'get_asset_availability': getAssetAvailability($conn); break;
        case 'get_asset_history': getAssetHistory($conn); break;
        case 'get_booking_history': getBookingHistory($conn); break; 
        case 'cancel_booking': cancelBooking($conn, $currentUser); break;
        case 'get_missing_items': getMissingItems($conn); break;
        case 'get_items_for_verification': getItemsForVerification($conn, $currentUser); break;
        case 'verify_item_return': verifyItemReturn($conn, $currentUser); break;

        // CATEGORY ACTIONS
        case 'get_categories': getAssetCategories($conn); break;
        case 'add_category': addCategory($conn, $currentUser); break;
        case 'update_category': updateCategory($conn, $currentUser); break;
        case 'delete_category': deleteCategory($conn, $currentUser); break;

        // USER ACTION
        case 'get_users': getUsers($conn); break;

        // REPORT ACTIONS
        case 'report_item': reportItem($conn, $currentUser); break;
        case 'get_reports': getReports($conn, $currentUser); break;
        case 'update_report_status': updateReportStatus($conn, $currentUser); break;
            
        default:
            throw new Exception("Action non valide ou non spécifiée.");
    }
} catch (PDOException $e) {
    error_log("Database Error in inventory-handler.php: " . $e->getMessage());
    respondWithError("Erreur de base de données: " . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("General Error in inventory-handler.php: " . $e->getMessage());
    respondWithError($e->getMessage());
}

function getAssetHistory($conn) {
    $asset_id = isset($_GET['asset_id']) ? intval($_GET['asset_id']) : 0;
    if (!$asset_id) throw new Exception("ID de l'actif manquant.");

    $sql = "SELECT b.booking_date, b.mission, b.status, u.prenom, u.nom, b.created_at as checkout_time,
            (SELECT i.last_modified FROM Inventory i WHERE i.asset_id = b.asset_id) as checkin_time
            FROM Bookings b LEFT JOIN Users u ON b.user_id = u.user_id
            WHERE b.asset_id = ?
            ORDER BY b.booking_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['history' => $history]);
}

function reportItem($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $asset_id = $data['asset_id'] ?? 0;
    $report_type = $data['report_type'] ?? '';
    $comments = $data['comments'] ?? '';

    if (!$asset_id || !$report_type) {
        throw new Exception("Données de rapport manquantes.");
    }

    $sql = "INSERT INTO ToolReports (asset_id, user_id, report_type, comments) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id, $user['user_id'], $report_type, $comments]);

    respondWithSuccess([], "Rapport envoyé avec succès.");
}

function getReports($conn, $user) {
    if ($user['role'] !== 'admin') {
        respondWithError("Accès non autorisé.", 403);
    }

    $sql = "
        SELECT 
            r.*,
            i.asset_name,
            u.prenom,
            u.nom
        FROM ToolReports r
        JOIN Inventory i ON r.asset_id = i.asset_id
        JOIN Users u ON r.user_id = u.user_id
        ORDER BY r.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess(['reports' => $reports]);
}

function updateReportStatus($conn, $user) {
    if ($user['role'] !== 'admin') {
        respondWithError("Accès non autorisé.", 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $report_id = $data['report_id'] ?? 0;
    $status = $data['status'] ?? '';

    if (!$report_id || !$status) {
        throw new Exception("Données de mise à jour de rapport manquantes.");
    }

    $sql = "UPDATE ToolReports SET status = ?, resolved_at = GETDATE() WHERE report_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status, $report_id]);

    respondWithSuccess([], "Statut du rapport mis à jour.");
}


function respondWithSuccess($data = [], $message = "Opération réussie.") {
    echo json_encode(['status' => 'success', 'message' => $message] + $data);
    exit;
}

function respondWithError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function getItemsForVerification($conn, $user) {
     if ($user['role'] !== 'admin') respondWithError("Accès non autorisé.", 403);
    $sql = "
        SELECT 
            i.asset_id, i.asset_name, i.barcode, i.last_modified,
            u.prenom AS returned_by_prenom, u.nom AS returned_by_nom
        FROM Inventory i
        LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id
        WHERE i.status = 'pending_verification'
        ORDER BY i.last_modified ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['items_for_verification' => $items]);
}

function verifyItemReturn($conn, $user) {
    if ($user['role'] !== 'admin') respondWithError("Accès non autorisé.", 403);
    $data = json_decode(file_get_contents('php://input'), true);
    $asset_id = $data['asset_id'] ?? 0;
    if (!$asset_id) throw new Exception("ID de l'actif manquant.");

    $sql = "UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL WHERE asset_id = ? AND status = 'pending_verification'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id]);

    if ($stmt->rowCount() > 0) {
        respondWithSuccess([], "Retour vérifié et l'actif est maintenant disponible.");
    } else {
        throw new Exception("L'actif n'a pas pu être trouvé ou a déjà été vérifié/repris.");
    }
}


function getMissingItems($conn) {
    $sql = "
        SELECT 
            i.asset_name, 
            i.barcode, 
            u.prenom, 
            u.nom, 
            b.booking_date, 
            b.mission
        FROM Inventory i
        JOIN Bookings b ON i.asset_id = b.asset_id
        JOIN Users u ON i.assigned_to_user_id = u.user_id
        WHERE i.status = 'in-use'
        AND b.status = 'active' 
        AND b.booking_date < GETDATE()
        ORDER BY b.booking_date ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $missing_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['missing_items' => $missing_items]);
}

function getBookingHistory($conn) {
    $sql = "
        SELECT 
            b.booking_id, 
            b.created_at as pickedup_date,
            i.last_modified as submitted_date,
            i.asset_name, 
            u.user_id,
            u.prenom, 
            u.nom,
            b.mission
        FROM Bookings b 
        LEFT JOIN Inventory i ON b.asset_id = i.asset_id 
        LEFT JOIN Users u ON b.user_id = u.user_id 
        WHERE b.status IN ('completed', 'cancelled')
        ORDER BY b.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['history' => $history]);
}

function autoCancelPastBookings($conn) {
    $sql = "UPDATE Bookings SET status = 'cancelled' WHERE status = 'booked' AND booking_date < GETDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
}

function getAllBookings($conn) {
    autoCancelPastBookings($conn);
    // Fetch individual bookings
    $sql_individual = "
        SELECT b.booking_id, b.booking_date, b.mission, b.status, a.asset_name, a.barcode, u.prenom, u.nom, b.user_id 
        FROM Bookings b 
        LEFT JOIN Inventory a ON b.asset_id = a.asset_id 
        LEFT JOIN Users u ON b.user_id = u.user_id 
        WHERE b.status IN ('booked', 'active') 
        AND b.user_id IS NOT NULL
        AND b.booking_date >= GETDATE()
        ORDER BY b.booking_date ASC, a.asset_name ASC";
    $stmt_individual = $conn->prepare($sql_individual);
    $stmt_individual->execute();
    $individual_bookings = $stmt_individual->fetchAll(PDO::FETCH_ASSOC);

    // Fetch mission bookings
    $sql_mission = "
        SELECT b.booking_id, b.booking_date, b.mission, b.status, a.asset_name, a.barcode
        FROM Bookings b 
        LEFT JOIN Inventory a ON b.asset_id = a.asset_id 
        WHERE b.status IN ('booked', 'active') 
        AND b.user_id IS NULL
        AND b.booking_date >= GETDATE()
        ORDER BY b.booking_date ASC, b.mission ASC, a.asset_name ASC";
    $stmt_mission = $conn->prepare($sql_mission);
    $stmt_mission->execute();
    $mission_bookings = $stmt_mission->fetchAll(PDO::FETCH_ASSOC);

    $bookings = [
        'individual' => $individual_bookings,
        'mission' => $mission_bookings
    ];

    respondWithSuccess(['bookings' => $bookings]);
}

function cancelBooking($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $booking_id = isset($data['booking_id']) ? $data['booking_id'] : 0;
    if (!$booking_id) throw new Exception("ID de réservation manquant.");
    $stmt_check = $conn->prepare("SELECT user_id FROM Bookings WHERE booking_id = ?");
    $stmt_check->execute([$booking_id]);
    $booking_user_id = $stmt_check->fetchColumn();
    
    if ($user['role'] !== 'admin' && ($booking_user_id === null || $user['user_id'] != $booking_user_id)) {
        respondWithError("Vous n'êtes pas autorisé à annuler cette réservation.", 403);
    }
    $stmt = $conn->prepare("UPDATE Bookings SET status = 'cancelled' WHERE booking_id = ? AND status = 'booked'");
    $stmt->execute([$booking_id]);
    if ($stmt->rowCount() > 0) {
        respondWithSuccess([], "Réservation annulée.");
    } else {
        throw new Exception("Impossible d'annuler. La réservation est peut-être déjà en cours ou annulée.");
    }
}

function processScan($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $barcode = isset($data['barcode']) ? trim($data['barcode']) : '';
    if (empty($barcode)) throw new Exception("Code-barres non fourni.");

    $stmt_asset = $conn->prepare("SELECT * FROM Inventory WHERE barcode = ?");
    $stmt_asset->execute([$barcode]);
    $asset = $stmt_asset->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        respondWithSuccess(['scan_code' => 'asset_not_found', 'barcode' => $barcode], "Cet actif n'existe pas. Voulez-vous l'ajouter?");
    }
    if ($asset['status'] === 'maintenance') {
        throw new Exception("Cet actif est actuellement en maintenance.");
    }

    $today = date('Y-m-d');
    $current_user_id = $user['user_id'];

    // CASE 1: Item is currently IN-USE (must be a return)
    if ($asset['status'] === 'in-use') {
        if ($asset['assigned_to_user_id'] == $current_user_id) {
            $conn->beginTransaction();
            // Complete the active booking
            $stmt_update_booking = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND user_id = ? AND status = 'active'");
            $stmt_update_booking->execute([$asset['asset_id'], $current_user_id]);
            // Set inventory to PENDING VERIFICATION, keeping assigned_to_user_id for tracking
            $stmt_update_inventory = $conn->prepare("UPDATE Inventory SET status = 'pending_verification', last_modified = GETDATE() WHERE asset_id = ?");
            $stmt_update_inventory->execute([$asset['asset_id']]);
            $conn->commit();
            respondWithSuccess(['scan_code' => 'return_success'], "Actif retourné. En attente de vérification.");
        } else {
            $stmt_user = $conn->prepare("SELECT prenom, nom FROM Users WHERE user_id = ?");
            $stmt_user->execute([$asset['assigned_to_user_id']]);
            $assigned_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $user_name = $assigned_user ? $assigned_user['prenom'] . ' ' . $assigned_user['nom'] : 'un autre utilisateur';
            throw new Exception("Cet actif est actuellement utilisé par " . $user_name . ".");
        }
    }

    // CASE 2: Item is AVAILABLE or PENDING VERIFICATION (can be checked out)
    if (in_array($asset['status'], ['available', 'pending_verification'])) {
        $stmt_booking = $conn->prepare("
            SELECT b.*, u.prenom, u.nom
            FROM Bookings b
            LEFT JOIN Users u ON b.user_id = u.user_id
            WHERE b.asset_id = ? AND b.booking_date = ? AND b.status = 'booked'
        ");
        $stmt_booking->execute([$asset['asset_id'], $today]);
        $booking = $stmt_booking->fetch(PDO::FETCH_ASSOC);

        if ($booking) { // A booking exists for today
            $is_authorized = ($booking['user_id'] == $current_user_id) || ($booking['user_id'] === null); // Authorized if it's their booking or a mission booking
            if (!$is_authorized) {
                throw new Exception("Action impossible. L'actif est réservé par " . $booking['prenom'] . " " . $booking['nom'] . " pour aujourd'hui.");
            }
            
            // Authorized, proceed with checkout
            $conn->beginTransaction();
            $stmt_update_booking = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ?");
            $stmt_update_booking->execute([$booking['booking_id']]);
            $stmt_update_inventory = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ?, assigned_mission = ?, last_modified = GETDATE() WHERE asset_id = ?");
            $stmt_update_inventory->execute([$current_user_id, $booking['mission'], $asset['asset_id']]);
            $conn->commit();
            respondWithSuccess(['scan_code' => 'checkout_success', 'asset' => $asset], "Sortie de l'actif enregistrée.");

        } else { // No booking for today, it's a direct pickup
            // This is allowed for both 'available' and 'pending_verification' statuses
            respondWithSuccess(['scan_code' => 'prompt_booking', 'asset' => $asset], "Aucune réservation pour aujourd'hui. Veuillez en créer une.");
        }
    }
}

function getInventory($conn) {
    $sql = "SELECT i.*, ac.category_name, u_assigned.prenom AS assigned_to_prenom, u_assigned.nom AS assigned_to_nom, (SELECT MIN(b.booking_date) FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date > CAST(GETDATE() AS DATE) AND b.status = 'booked') as next_future_booking_date, todays_booking.user_id AS todays_booking_user_id, todays_booking.mission AS todays_booking_mission, u_booking.prenom AS todays_booking_prenom, u_booking.nom AS todays_booking_nom FROM Inventory i LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id LEFT JOIN Users u_assigned ON i.assigned_to_user_id = u_assigned.user_id OUTER APPLY ( SELECT TOP 1 b.user_id, b.mission FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date = CAST(GETDATE() AS DATE) AND b.status = 'booked' ) AS todays_booking LEFT JOIN Users u_booking ON todays_booking.user_id = u_booking.user_id ORDER BY i.asset_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['inventory' => $inventory]);
}

function updateAsset($conn, $user) {
    if ($user['role'] !== 'admin') respondWithError("Accès non autorisé.", 403);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['asset_id'], $data['barcode'], $data['asset_name'], $data['asset_type'])) throw new Exception("Données manquantes pour la mise à jour.");
    $asset_id = $data['asset_id'];
    $barcode = trim($data['barcode']);
    $asset_name = trim($data['asset_name']);
    if (empty($barcode) || empty($asset_name)) throw new Exception("Le code-barres et le nom de l'actif sont obligatoires.");
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Inventory WHERE barcode = ? AND asset_id != ?");
    $stmt_check->execute([$barcode, $asset_id]);
    if ($stmt_check->fetchColumn() > 0) throw new Exception("Ce code-barres est déjà utilisé par un autre actif.");
    $sql = "UPDATE Inventory SET barcode = ?, asset_type = ?, category_id = ?, asset_name = ?, brand = ?, serial_or_plate = ?, position_or_info = ?, fuel_level = ?, last_modified = GETDATE() WHERE asset_id = ?";
    $params = [$barcode, $data['asset_type'], empty($data['category_id']) ? null : $data['category_id'], $asset_name, empty($data['brand']) ? null : trim($data['brand']), empty($data['serial_or_plate']) ? null : trim($data['serial_or_plate']), empty($data['position_or_info']) ? null : trim($data['position_or_info']), empty($data['fuel_level']) ? null : $data['fuel_level'], $asset_id];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $select_stmt = $conn->prepare("SELECT i.*, ac.category_name, u_assigned.prenom AS assigned_to_prenom, u_assigned.nom AS assigned_to_nom, (SELECT MIN(b.booking_date) FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date > CAST(GETDATE() AS DATE) AND b.status = 'booked') as next_future_booking_date, todays_booking.user_id AS todays_booking_user_id, todays_booking.mission AS todays_booking_mission, u_booking.prenom AS todays_booking_prenom, u_booking.nom AS todays_booking_nom FROM Inventory i LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id LEFT JOIN Users u_assigned ON i.assigned_to_user_id = u_assigned.user_id OUTER APPLY ( SELECT TOP 1 b.user_id, b.mission FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date = CAST(GETDATE() AS DATE) AND b.status = 'booked' ) AS todays_booking LEFT JOIN Users u_booking ON todays_booking.user_id = u_booking.user_id WHERE i.asset_id = ?");
    $select_stmt->execute([$asset_id]);
    $updatedAsset = $select_stmt->fetch(PDO::FETCH_ASSOC);
    if ($updatedAsset) respondWithSuccess(['asset' => $updatedAsset], "Actif mis à jour avec succès.");
    else throw new Exception("Échec de la mise à jour de l'actif.");
}

function getAssetCategories($conn) {
    $stmt = $conn->prepare("SELECT category_id, category_name, category_type FROM AssetCategories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['categories' => $categories]);
}

function addCategory($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty(trim($data['category_name'])) || empty($data['category_type'])) throw new Exception("Le nom et le type de la catégorie sont obligatoires.");
    $name = trim($data['category_name']);
    $type = $data['category_type'];
    if (!in_array($type, ['tool', 'vehicle'])) throw new Exception("Type de catégorie non valide.");
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM AssetCategories WHERE category_name = ? AND category_type = ?");
    $stmt_check->execute([$name, $type]);
    if ($stmt_check->fetchColumn() > 0) throw new Exception("Une catégorie avec ce nom et ce type existe déjà.");
    $sql = "INSERT INTO AssetCategories (category_name, category_type) OUTPUT INSERTED.* VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name, $type]);
    $newCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($newCategory) respondWithSuccess(['category' => $newCategory], "Catégorie créée avec succès.");
    else throw new Exception("Échec de la création de la catégorie.");
}

function updateCategory($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['category_id']) || empty(trim($data['category_name']))) throw new Exception("Données de catégorie manquantes.");
    $id = $data['category_id'];
    $name = trim($data['category_name']);
    $stmt_check = $conn->prepare("SELECT category_id, category_type FROM AssetCategories WHERE category_id = ?");
    $stmt_check->execute([$id]);
    $category = $stmt_check->fetch(PDO::FETCH_ASSOC);
    if (!$category) throw new Exception("Catégorie non trouvée.");
    $stmt_dup = $conn->prepare("SELECT COUNT(*) FROM AssetCategories WHERE category_name = ? AND category_type = ? AND category_id != ?");
    $stmt_dup->execute([$name, $category['category_type'], $id]);
    if ($stmt_dup->fetchColumn() > 0) throw new Exception("Une autre catégorie du même type a déjà ce nom.");
    $stmt = $conn->prepare("UPDATE AssetCategories SET category_name = ? WHERE category_id = ?");
    $stmt->execute([$name, $id]);
    respondWithSuccess([], "Catégorie mise à jour avec succès.");
}

function deleteCategory($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['category_id'])) throw new Exception("ID de catégorie manquant.");
    $id = $data['category_id'];
    $stmt = $conn->prepare("DELETE FROM AssetCategories WHERE category_id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) respondWithSuccess([], "Catégorie supprimée. Les actifs associés ne sont plus catégorisés.");
    else throw new Exception("La catégorie à supprimer n'a pas été trouvée.");
}

function bookAsset($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['asset_id'], $data['booking_date']) || empty($data['booking_date'])) throw new Exception("Données de réservation manquantes.");
    $asset_id = $data['asset_id'];
    $booking_date = $data['booking_date'];
    $mission = isset($data['mission']) ? trim($data['mission']) : 'Non spécifiée';
    $user_id = $user['user_id'];
    $stmt_check = $conn->prepare("SELECT booking_id FROM Bookings WHERE asset_id = ? AND booking_date = ? AND status IN ('booked', 'active')");
    $stmt_check->execute([$asset_id, $booking_date]);
    if ($stmt_check->fetch()) throw new Exception("Cet actif est déjà réservé pour cette date.");
    $sql = "INSERT INTO Bookings (asset_id, user_id, booking_date, mission) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id, $user_id, $booking_date, $mission]);
    respondWithSuccess([], "Actif réservé avec succès.");
}

function getAssetAvailability($conn) {
    $asset_id = isset($_GET['asset_id']) ? $_GET['asset_id'] : 0;
    if (!$asset_id) throw new Exception("ID de l'actif manquant.");
    $sql = "SELECT booking_date FROM Bookings WHERE asset_id = ? AND booking_date >= CAST(GETDATE() AS DATE) AND status IN ('booked', 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    respondWithSuccess(['booked_dates' => $dates]);
}

function updateMaintenanceStatus($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $asset_id = $data['asset_id'];
    $status = $data['status'];
    if (!in_array($status, ['maintenance', 'available'])) throw new Exception("Statut non valide.");
    if ($status === 'maintenance') {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Bookings WHERE asset_id = ? AND booking_date >= CAST(GETDATE() AS DATE) AND status IN ('booked', 'active')");
        $stmt_check->execute([$asset_id]);
        if ($stmt_check->fetchColumn() > 0) throw new Exception("Impossible de mettre en maintenance. L'actif a des réservations futures. Veuillez les annuler d'abord.");
    }
    $sql = "UPDATE Inventory SET status = ?, last_modified = GETDATE() WHERE asset_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status, $asset_id]);
    respondWithSuccess([], "Statut de maintenance mis à jour.");
}

function getUsers($conn) {
    $stmt = $conn->prepare("SELECT user_id, prenom, nom FROM Users WHERE status = 'Active' ORDER BY prenom, nom");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['users' => $users]);
}

function addAsset($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['barcode'], $data['asset_name'], $data['asset_type'])) throw new Exception("Données manquantes pour l'ajout de l'actif.");
    $barcode = trim($data['barcode']);
    $asset_name = trim($data['asset_name']);
    if (empty($barcode) || empty($asset_name)) throw new Exception("Le code-barres et le nom de l'actif sont obligatoires.");
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Inventory WHERE barcode = ?");
    $stmt_check->execute([$barcode]);
    if ($stmt_check->fetchColumn() > 0) throw new Exception("Ce code-barres existe déjà dans l'inventaire.");
    $sql = "INSERT INTO Inventory (barcode, asset_type, category_id, asset_name, brand, serial_or_plate, position_or_info, status, fuel_level, date_added, last_modified) OUTPUT INSERTED.asset_id VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?, GETDATE(), GETDATE())";
    $params = [$barcode, $data['asset_type'], empty($data['category_id']) ? null : $data['category_id'], $asset_name, empty($data['brand']) ? null : trim($data['brand']), empty($data['serial_or_plate']) ? null : trim($data['serial_or_plate']), empty($data['position_or_info']) ? null : trim($data['position_or_info']), empty($data['fuel_level']) ? null : $data['fuel_level']];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $newId = $stmt->fetchColumn();
    $select_stmt = $conn->prepare("SELECT i.*, ac.category_name, u_assigned.prenom AS assigned_to_prenom, u_assigned.nom AS assigned_to_nom, (SELECT MIN(b.booking_date) FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date > CAST(GETDATE() AS DATE) AND b.status = 'booked') as next_future_booking_date, todays_booking.user_id AS todays_booking_user_id, todays_booking.mission AS todays_booking_mission, u_booking.prenom AS todays_booking_prenom, u_booking.nom AS todays_booking_nom FROM Inventory i LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id LEFT JOIN Users u_assigned ON i.assigned_to_user_id = u_assigned.user_id OUTER APPLY ( SELECT TOP 1 b.user_id, b.mission FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date = CAST(GETDATE() AS DATE) AND b.status = 'booked' ) AS todays_booking LEFT JOIN Users u_booking ON todays_booking.user_id = u_booking.user_id WHERE i.asset_id = ?");
    $select_stmt->execute([$newId]);
    $newAsset = $select_stmt->fetch(PDO::FETCH_ASSOC);
    if ($newAsset) respondWithSuccess(['asset' => $newAsset], "Actif ajouté avec succès.");
    else throw new Exception("Échec de la création ou de la récupération de l'actif.");
}

function deleteAsset($conn, $user) {
    if ($user['role'] !== 'admin') respondWithError("Accès non autorisé.", 403);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['asset_id'])) throw new Exception("ID de l'actif manquant.");
    $asset_id = $data['asset_id'];
    $conn->beginTransaction();
    try {
        $stmt_bookings = $conn->prepare("DELETE FROM Bookings WHERE asset_id = ?");
        $stmt_bookings->execute([$asset_id]);
        
        $stmt = $conn->prepare("DELETE FROM Inventory WHERE asset_id = ?");
        $stmt->execute([$asset_id]);

        if ($stmt->rowCount() > 0) {
            $conn->commit();
            respondWithSuccess([], "Actif et réservations associées supprimés avec succès.");
        } else {
            throw new Exception("L'actif à supprimer n'a pas été trouvé.");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}
?>
