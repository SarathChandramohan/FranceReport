<?php
// Ensure this file is included before any output
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();
$is_admin = $user['role'] === 'admin';

// Set content type to JSON for all responses
header('Content-Type: application/json');

// Centralized error handler
function handleError(Exception $e, $code = 500) {
    http_response_code($code);
    error_log("Inventory Handler Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

try {
    require_once 'db-connection.php'; // Establish DB connection
    
    $action = $_REQUEST['action'] ?? '';

    // Route the request to the correct function
    switch ($action) {
        case 'get_stats':
            getInventoryStats($conn);
            break;
        case 'get_assets':
            getAssets($conn);
            break;
        case 'get_asset_details':
            if (!$is_admin) throw new Exception('Accès non autorisé.', 403);
            getAssetDetails($conn);
            break;
        case 'save_asset':
            if (!$is_admin) throw new Exception('Accès non autorisé.', 403);
            saveAsset($conn);
            break;
        case 'delete_asset':
            if (!$is_admin) throw new Exception('Accès non autorisé.', 403);
            deleteAsset($conn);
            break;
        case 'get_categories':
            getCategories($conn);
            break;
        case 'save_category':
            if (!$is_admin) throw new Exception('Accès non autorisé.', 403);
            saveCategory($conn);
            break;
        case 'delete_category':
            if (!$is_admin) throw new Exception('Accès non autorisé.', 403);
            deleteCategory($conn);
            break;
        case 'take_vehicle':
            // Any logged-in user can perform this action, no admin check needed here
            takeVehicle($conn, $user['user_id']);
            break;
        default:
            throw new Exception('Action non valide spécifiée.');
    }
} catch (Exception $e) {
    // Catch any unhandled exceptions from the functions
    handleError($e, $e->getCode() ?: 500);
}

// --- Function Implementations ---

function getInventoryStats($conn) {
    $query = "
        SELECT 
            (SELECT COUNT(*) FROM Inventory) as total,
            (SELECT COUNT(*) FROM Inventory WHERE asset_type = 'tool') as tools,
            (SELECT COUNT(*) FROM Inventory WHERE asset_type = 'vehicle') as vehicles,
            (SELECT COUNT(*) FROM Inventory WHERE status = 'available') as available,
            (SELECT COUNT(*) FROM Inventory WHERE status = 'in-use') as in_use,
            (SELECT COUNT(*) FROM Inventory WHERE status = 'maintenance') as maintenance
    ";
    $stmt = $conn->query($query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $stats]);
}

function getAssets($conn) {
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? 'all';

    $sql = "
        SELECT i.*, c.category_name, u.nom + ' ' + u.prenom as assigned_user
        FROM Inventory i
        LEFT JOIN AssetCategories c ON i.category_id = c.category_id
        LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (i.asset_name LIKE :search OR i.barcode LIKE :search OR i.brand LIKE :search OR i.serial_or_plate LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($type !== 'all') {
        $sql .= " AND i.asset_type = :type";
        $params[':type'] = $type;
    }
    
    $sql .= " ORDER BY i.asset_type, i.asset_name";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $assets]);
}

function getAssetDetails($conn) {
    $asset_id = filter_input(INPUT_GET, 'asset_id', FILTER_VALIDATE_INT);
    if (!$asset_id) throw new Exception('ID de l\'actif invalide ou manquant.');
    
    $stmt = $conn->prepare("SELECT * FROM Inventory WHERE asset_id = :asset_id");
    $stmt->execute([':asset_id' => $asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($asset) {
        echo json_encode(['status' => 'success', 'data' => $asset]);
    } else {
        throw new Exception('Actif non trouvé.', 404);
    }
}

function saveAsset($conn) {
    $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
    
    // Sanitize and validate inputs
    $barcode = trim($_POST['barcode']);
    $asset_type = trim($_POST['asset_type']);
    $asset_name = trim($_POST['asset_name']);
    if (empty($barcode) || empty($asset_type) || empty($asset_name)) {
        throw new Exception("Les champs code-barres, type et nom sont obligatoires.");
    }

    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
    $brand = !empty($_POST['brand']) ? trim($_POST['brand']) : null;
    
    if ($asset_type === 'tool') {
        $serial_or_plate = !empty($_POST['serial_or_plate_tool']) ? trim($_POST['serial_or_plate_tool']) : null;
        $position_or_info = !empty($_POST['position_or_info_tool']) ? trim($_POST['position_or_info_tool']) : null;
        $fuel_level = null;
    } else {
        $serial_or_plate = !empty($_POST['serial_or_plate_vehicle']) ? trim($_POST['serial_or_plate_vehicle']) : null;
        $position_or_info = null;
        $fuel_level = !empty($_POST['fuel_level']) ? trim($_POST['fuel_level']) : null;
    }
    
    if ($asset_id) {
        $sql = "UPDATE Inventory SET barcode=?, asset_type=?, category_id=?, asset_name=?, brand=?, serial_or_plate=?, position_or_info=?, fuel_level=?, last_modified=GETDATE() WHERE asset_id=?";
        $params = [$barcode, $asset_type, $category_id, $asset_name, $brand, $serial_or_plate, $position_or_info, $fuel_level, $asset_id];
    } else {
        $sql = "INSERT INTO Inventory (barcode, asset_type, category_id, asset_name, brand, serial_or_plate, position_or_info, fuel_level, last_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
        $params = [$barcode, $asset_type, $category_id, $asset_name, $brand, $serial_or_plate, $position_or_info, $fuel_level];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['status' => 'success', 'message' => 'Actif enregistré avec succès.']);
}

function deleteAsset($conn) {
    $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
    if (!$asset_id) throw new Exception('ID de l\'actif manquant.');

    $stmt = $conn->prepare("DELETE FROM Inventory WHERE asset_id = :asset_id");
    $stmt->execute([':asset_id' => $asset_id]);

    echo json_encode(['status' => 'success', 'message' => 'Actif supprimé avec succès.']);
}

function getCategories($conn) {
    $stmt = $conn->query("SELECT * FROM AssetCategories ORDER BY category_type, category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $categories]);
}

function saveCategory($conn) {
    $category_name = trim($_POST['category_name'] ?? '');
    $category_type = trim($_POST['category_type'] ?? '');
    if (empty($category_name) || empty($category_type)) {
        throw new Exception('Le nom et le type de la catégorie sont requis.');
    }
    
    $stmt = $conn->prepare("INSERT INTO AssetCategories (category_name, category_type) VALUES (?, ?)");
    $stmt->execute([$category_name, $category_type]);
    echo json_encode(['status' => 'success', 'message' => 'Catégorie ajoutée avec succès.']);
}

function deleteCategory($conn) {
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    if (!$category_id) throw new Exception('ID de catégorie manquant.');

    $stmt = $conn->prepare("DELETE FROM AssetCategories WHERE category_id = ?");
    $stmt->execute([$category_id]);
    echo json_encode(['status' => 'success', 'message' => 'Catégorie supprimée avec succès.']);
}

function takeVehicle($conn, $userId) {
    $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
    if (!$asset_id) throw new Exception('ID de l\'actif manquant.');

    $conn->beginTransaction();
    
    $checkStmt = $conn->prepare("SELECT status FROM Inventory WHERE asset_id = ? AND asset_type = 'vehicle'");
    $checkStmt->execute([$asset_id]);
    $currentStatus = $checkStmt->fetchColumn();

    if($currentStatus !== 'available') {
        $conn->rollBack();
        throw new Exception('Ce véhicule n\'est pas disponible pour le moment.');
    }

    $sql = "UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $asset_id]);
    
    if ($stmt->rowCount() > 0) {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Véhicule pris avec succès.']);
    } else {
        $conn->rollBack();
        throw new Exception('Impossible de prendre ce véhicule.');
    }
}
?>
