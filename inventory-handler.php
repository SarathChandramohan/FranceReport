<?php
// inventory-handler.php

// Core dependencies
require_once 'session-management.php';
requireLogin();
require_once 'db-connection.php';

// Ensure the response is always JSON
header('Content-Type: application/json');

// Get the current user details for permission checks
$currentUser = getCurrentUser();

// Get the action from the request (works for GET and POST)
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// --- Main Action Router ---
try {
    switch ($action) {
        case 'get_inventory':
            getInventory($conn);
            break;
        case 'get_categories':
            getAssetCategories($conn);
            break;
        case 'get_users':
            getUsers($conn);
            break;
        case 'check_barcode':
            checkBarcodeExists($conn);
            break;
        case 'add_asset':
            addAsset($conn, $currentUser);
            break;
        case 'update_asset_status':
            updateAssetStatus($conn, $currentUser);
            break;
        case 'delete_asset':
            deleteAsset($conn, $currentUser);
            break;
        case 'add_category':
            addCategory($conn, $currentUser);
            break;
        case 'get_asset_bookings':
            getAssetBookings($conn);
            break;
        case 'book_asset':
            bookAsset($conn, $currentUser);
            break;
        case 'get_booking_history':
            getBookingHistory($conn, $currentUser);
            break;
        case 'get_daily_inventory_status':
            getDailyInventoryStatus($conn);
            break;
        default:
            throw new Exception("Action non valide ou non spécifiée.");
    }
} catch (PDOException $e) {
    // Catch database-specific errors
    error_log("Database Error in inventory-handler.php: " . $e->getMessage());
    respondWithError("Erreur de base de données: " . $e->getMessage(), 500);
} catch (Exception $e) {
    // Catch general application errors
    error_log("General Error in inventory-handler.php: " . $e->getMessage());
    respondWithError($e->getMessage());
}

// --- Function Definitions ---

function respondWithSuccess($data = [], $message = "Opération réussie.") {
    echo json_encode(['status' => 'success', 'message' => $message] + $data);
    exit;
}

function respondWithError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function getInventory($conn) {
    $sql = "SELECT 
                i.*, 
                ac.category_name,
                u.prenom AS assigned_to_prenom, 
                u.nom AS assigned_to_nom
            FROM Inventory i
            LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id
            LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id
            ORDER BY i.date_added DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    respondWithSuccess(['inventory' => $inventory]);
}

function getAssetCategories($conn) {
    $stmt = $conn->prepare("SELECT category_id, category_name, category_type FROM AssetCategories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['categories' => $categories]);
}

function getUsers($conn) {
    $stmt = $conn->prepare("SELECT user_id, prenom, nom FROM Users WHERE status = 'Active' ORDER BY prenom, nom");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['users' => $users]);
}

function checkBarcodeExists($conn) {
    $barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
    if (empty($barcode)) {
        throw new Exception("Le code-barres n'a pas été fourni.");
    }

    $stmt = $conn->prepare("SELECT * FROM Inventory WHERE barcode = ?");
    $stmt->execute([$barcode]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($asset) {
        respondWithSuccess(['exists' => true, 'asset' => $asset]);
    } else {
        respondWithSuccess(['exists' => false]);
    }
}

function addAsset($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['barcode'], $data['asset_name'], $data['asset_type'])) {
        throw new Exception("Données manquantes pour l'ajout de l'actif.");
    }
    $barcode = trim($data['barcode']);
    $asset_name = trim($data['asset_name']);
    if (empty($barcode) || empty($asset_name)) {
        throw new Exception("Le code-barres et le nom de l'actif sont obligatoires.");
    }

    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Inventory WHERE barcode = ?");
    $stmt_check->execute([$barcode]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("Ce code-barres existe déjà dans l'inventaire.");
    }
    
    $sql = "INSERT INTO Inventory (barcode, asset_type, category_id, asset_name, brand, serial_or_plate, position_or_info, status, fuel_level, date_added, last_modified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?, GETDATE(), GETDATE())";
            
    $params = [
        $barcode,
        $data['asset_type'],
        empty($data['category_id']) ? null : $data['category_id'],
        $asset_name,
        empty($data['brand']) ? null : trim($data['brand']),
        empty($data['serial_or_plate']) ? null : trim($data['serial_or_plate']),
        empty($data['position_or_info']) ? null : trim($data['position_or_info']),
        empty($data['fuel_level']) ? null : $data['fuel_level']
    ];
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $select_stmt = $conn->prepare("
        SELECT 
            i.*, 
            ac.category_name,
            u.prenom AS assigned_to_prenom, 
            u.nom AS assigned_to_nom
        FROM Inventory i
        LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id
        LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id
        WHERE i.barcode = ?");
    $select_stmt->execute([$barcode]);
    $newAsset = $select_stmt->fetch(PDO::FETCH_ASSOC);

    if ($newAsset) {
         respondWithSuccess(['asset' => $newAsset], "Actif ajouté avec succès.");
    } else {
        throw new Exception("Échec de la création ou de la récupération de l'actif.");
    }
}

function updateAssetStatus($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['asset_id'], $data['status'])) {
        throw new Exception("Données de mise à jour de statut manquantes.");
    }

    $asset_id = $data['asset_id'];
    $status = $data['status'];
    $assigned_to = ($status === 'in-use' && !empty($data['assigned_to_user_id'])) ? $data['assigned_to_user_id'] : null;
    $mission = ($status === 'in-use' && !empty($data['assigned_mission'])) ? trim($data['assigned_mission']) : null;

    $sql = "UPDATE Inventory 
            SET status = ?, assigned_to_user_id = ?, assigned_mission = ?, last_modified = GETDATE()
            WHERE asset_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status, $assigned_to, $mission, $asset_id]);

    $stmt_updated = $conn->prepare("
        SELECT i.*, ac.category_name, u.prenom AS assigned_to_prenom, u.nom AS assigned_to_nom
        FROM Inventory i
        LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id
        LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id
        WHERE i.asset_id = ?");
    $stmt_updated->execute([$asset_id]);
    $updatedAsset = $stmt_updated->fetch(PDO::FETCH_ASSOC);

    respondWithSuccess(['asset' => $updatedAsset], "Statut de l'actif mis à jour.");
}

function deleteAsset($conn, $user) {
    if ($user['role'] !== 'admin') {
        respondWithError("Accès non autorisé. Seuls les administrateurs peuvent supprimer des actifs.", 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['asset_id'])) {
        throw new Exception("ID de l'actif manquant pour la suppression.");
    }
    
    $asset_id = $data['asset_id'];
    
    $stmt = $conn->prepare("DELETE FROM Inventory WHERE asset_id = ?");
    $stmt->execute([$asset_id]);
    
    if ($stmt->rowCount() > 0) {
        respondWithSuccess([], "Actif supprimé avec succès.");
    } else {
        throw new Exception("L'actif à supprimer n'a pas été trouvé.");
    }
}

function addCategory($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty(trim($data['category_name'])) || empty($data['category_type'])) {
        throw new Exception("Le nom et le type de la catégorie sont obligatoires.");
    }
    $name = trim($data['category_name']);
    $type = $data['category_type'];

    if (!in_array($type, ['tool', 'vehicle'])) {
        throw new Exception("Type de catégorie non valide.");
    }

    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM AssetCategories WHERE category_name = ? AND category_type = ?");
    $stmt_check->execute([$name, $type]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("Une catégorie avec ce nom et ce type existe déjà.");
    }

    $sql = "INSERT INTO AssetCategories (category_name, category_type) OUTPUT INSERTED.* VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name, $type]);
    $newCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($newCategory) {
        respondWithSuccess(['category' => $newCategory], "Catégorie créée avec succès.");
    } else {
        throw new Exception("Échec de la création de la catégorie.");
    }
}

function getAssetBookings($conn) {
    $asset_id = isset($_GET['asset_id']) ? intval($_GET['asset_id']) : 0;
    if ($asset_id === 0) {
        throw new Exception("ID d'actif non valide.");
    }

    $sql = "SELECT b.booking_date, u.prenom, u.nom 
            FROM Bookings b
            JOIN Users u ON b.user_id = u.user_id
            WHERE b.asset_id = ? AND b.booking_date >= CAST(GETDATE() AS DATE)
            ORDER BY b.booking_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    respondWithSuccess(['bookings' => $bookings]);
}

function bookAsset($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['asset_id'], $data['booking_date'])) {
        throw new Exception("Données de réservation manquantes.");
    }

    $asset_id = intval($data['asset_id']);
    $booking_date = $data['booking_date'];
    $user_id = $user['user_id'];

    // Check for existing booking on the same day
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Bookings WHERE asset_id = ? AND booking_date = ?");
    $stmt_check->execute([$asset_id, $booking_date]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("Cet article est déjà réservé pour cette date.");
    }

    $conn->beginTransaction();

    try {
        // Insert booking
        $sql_book = "INSERT INTO Bookings (asset_id, user_id, booking_date) VALUES (?, ?, ?)";
        $stmt_book = $conn->prepare($sql_book);
        $stmt_book->execute([$asset_id, $user_id, $booking_date]);
        $booking_id = $conn->lastInsertId();

        // Insert into history
        $sql_history = "INSERT INTO BookingHistory (booking_id, asset_id, user_id, action_type, changed_by_user_id) VALUES (?, ?, ?, 'booked', ?)";
        $stmt_history = $conn->prepare($sql_history);
        $stmt_history->execute([$booking_id, $asset_id, $user_id, $user['user_id']]);

        $conn->commit();
        respondWithSuccess([], "Article réservé avec succès pour le " . date('d/m/Y', strtotime($booking_date)) . ".");

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function getBookingHistory($conn, $user) {
    if ($user['role'] !== 'admin') {
        respondWithError("Accès non autorisé.", 403);
    }

    $asset_id = isset($_GET['asset_id']) ? intval($_GET['asset_id']) : 0;
    if ($asset_id === 0) {
        throw new Exception("ID d'actif non valide.");
    }
    
    $sql = "SELECT h.*, u.prenom, u.nom 
            FROM BookingHistory h
            JOIN Users u ON h.changed_by_user_id = u.user_id
            WHERE h.asset_id = ?
            ORDER BY h.action_timestamp DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    respondWithSuccess(['history' => $history]);
}

function getDailyInventoryStatus($conn) {
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    $sql = "SELECT 
                i.asset_id, i.asset_name, i.asset_type,
                CASE WHEN b.booking_id IS NOT NULL THEN 'booked' ELSE 'available' END as daily_status,
                u.prenom as booked_by_prenom, u.nom as booked_by_nom
            FROM Inventory i
            LEFT JOIN Bookings b ON i.asset_id = b.asset_id AND b.booking_date = ?
            LEFT JOIN Users u ON b.user_id = u.user_id
            ORDER BY i.asset_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$date]);
    $status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondWithSuccess(['status' => $status]);
}
?>
