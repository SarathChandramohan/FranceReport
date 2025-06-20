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
        case 'add_category': // New action
            addCategory($conn, $currentUser);
            break;
        default:
            throw new Exception("Action non valide ou non spécifiée.");
    }
} catch (PDOException $e) {
    // Catch database-specific errors
    error_log("Database Error in inventory-handler.php: " . $e->getMessage());
    respondWithError("Erreur de base de données.", 500);
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
    // --- TEMPORARY DEBUGGING CODE ---
    $raw_input = file_get_contents('php://input');
    // This line writes the raw request data to your server's error log.
    error_log("Inventory App Debug - Raw Input: " . $raw_input);

    $data = json_decode($raw_input, true);

    // This block checks if the JSON was parsed successfully.
    if (json_last_error() !== JSON_ERROR_NONE) {
        $json_error = json_last_error_msg();
        // This line writes the specific JSON error to your server's error log.
        error_log("Inventory App Debug - JSON Decode Error: " . $json_error);
        // This will send a more specific error message back to the browser.
        throw new Exception("Erreur de décodage JSON: " . $json_error);
    }
    // --- END DEBUGGING CODE ---

    if (!$data || !isset($data['barcode'], $data['asset_name'], $data['asset_type'])) {
        throw new Exception("Données manquantes pour l'ajout de l'actif. L'entrée était vide ou malformée.");
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

// --- NEW FUNCTION ---
function addCategory($conn, $user) {
    // Optional: Restrict this action to admins if needed
    // if ($user['role'] !== 'admin') {
    //     respondWithError("Accès non autorisé.", 403);
    // }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (!$data || empty(trim($data['category_name'])) || empty($data['category_type'])) {
        throw new Exception("Le nom et le type de la catégorie sont obligatoires.");
    }
    $name = trim($data['category_name']);
    $type = $data['category_type'];

    if (!in_array($type, ['tool', 'vehicle'])) {
        throw new Exception("Type de catégorie non valide.");
    }

    // Check for duplicates
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM AssetCategories WHERE category_name = ? AND category_type = ?");
    $stmt_check->execute([$name, $type]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("Une catégorie avec ce nom et ce type existe déjà.");
    }

    // Insertion
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
