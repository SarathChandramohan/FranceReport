<?php
// inventory_handler.php

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
        default:
            throw new Exception("Action non valide ou non spécifiée.");
    }
} catch (PDOException $e) {
    // Catch database-specific errors
    error_log("Database Error in inventory_handler.php: " . $e->getMessage());
    respondWithError("Erreur de base de données. " . $e->getMessage(), 500);
} catch (Exception $e) {
    // Catch general application errors
    error_log("General Error in inventory_handler.php: " . $e->getMessage());
    respondWithError($e->getMessage());
}


// --- Function Definitions ---

/**
 * Responds with a JSON success message and data.
 */
function respondWithSuccess($data = [], $message = "Opération réussie.") {
    echo json_encode(['status' => 'success', 'message' => $message] + $data);
    exit;
}

/**
 * Responds with a JSON error message.
 */
function respondWithError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

/**
 * Fetches the entire inventory list.
 */
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

/**
 * Fetches all asset categories.
 */
function getAssetCategories($conn) {
    $stmt = $conn->prepare("SELECT category_id, category_name, category_type FROM AssetCategories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['categories' => $categories]);
}

/**
 * Fetches all active users for assignment dropdowns.
 */
function getUsers($conn) {
    $stmt = $conn->prepare("SELECT user_id, prenom, nom FROM Users WHERE status = 'Active' ORDER BY prenom, nom");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['users' => $users]);
}


/**
 * Checks if a barcode already exists in the inventory.
 */
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

/**
 * Adds a new asset to the inventory.
 */
function addAsset($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);

    // --- Validation ---
    if (!$data || !isset($data['barcode'], $data['asset_name'], $data['asset_type'])) {
        throw new Exception("Données manquantes pour l'ajout de l'actif.");
    }
    $barcode = trim($data['barcode']);
    $asset_name = trim($data['asset_name']);
    if (empty($barcode) || empty($asset_name)) {
        throw new Exception("Le code-barres et le nom de l'actif sont obligatoires.");
    }

    // Check for duplicate barcode
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Inventory WHERE barcode = ?");
    $stmt_check->execute([$barcode]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("Ce code-barres existe déjà dans l'inventaire.");
    }
    
    // --- Insertion ---
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
    $asset_id = $conn->lastInsertId();

    // --- Respond with new asset data ---
    $stmt_new = $conn->prepare("SELECT i.*, ac.category_name FROM Inventory i LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id WHERE asset_id = ?");
    $stmt_new->execute([$asset_id]);
    $newAsset = $stmt_new->fetch(PDO::FETCH_ASSOC);

    respondWithSuccess(['asset' => $newAsset], "Actif ajouté avec succès.");
}

/**
 * Updates the status of an asset, including assignment.
 */
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
            SET status = ?, 
                assigned_to_user_id = ?, 
                assigned_mission = ?, 
                last_modified = GETDATE()
            WHERE asset_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status, $assigned_to, $mission, $asset_id]);

    // Fetch the updated asset to send back to the client
    $stmt_updated = $conn->prepare("
        SELECT 
            i.*, 
            ac.category_name,
            u.prenom AS assigned_to_prenom, 
            u.nom AS assigned_to_nom
        FROM Inventory i
        LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id
        LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id
        WHERE i.asset_id = ?");
    $stmt_updated->execute([$asset_id]);
    $updatedAsset = $stmt_updated->fetch(PDO::FETCH_ASSOC);

    respondWithSuccess(['asset' => $updatedAsset], "Statut de l'actif mis à jour.");
}

/**
 * Deletes an asset from the inventory (Admin only).
 */
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
