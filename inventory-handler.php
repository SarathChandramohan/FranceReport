<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// Only admins can perform write operations
$is_admin = $user['role'] === 'admin';

require_once 'db-connection.php';

$action = $_REQUEST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_stats':
            getInventoryStats($conn);
            break;
        case 'get_assets':
            getAssets($conn);
            break;
        case 'get_asset_details':
            if (!$is_admin) throw new Exception('Accès non autorisé.');
            getAssetDetails($conn);
            break;
        case 'save_asset':
            if (!$is_admin) throw new Exception('Accès non autorisé.');
            saveAsset($conn);
            break;
        case 'delete_asset':
            if (!$is_admin) throw new Exception('Accès non autorisé.');
            deleteAsset($conn);
            break;
        case 'get_categories':
            getCategories($conn);
            break;
        case 'save_category':
            if (!$is_admin) throw new Exception('Accès non autorisé.');
            saveCategory($conn);
            break;
        case 'delete_category':
            if (!$is_admin) throw new Exception('Accès non autorisé.');
            deleteCategory($conn);
            break;
        case 'take_vehicle':
            // Any logged-in user can take a vehicle
            takeVehicle($conn, $user['user_id']);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

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
        SELECT i.*, c.category_name, u.full_name as assigned_user
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
    $asset_id = $_GET['asset_id'] ?? 0;
    if (!$asset_id) throw new Exception('ID de l\'actif manquant.');
    
    $stmt = $conn->prepare("SELECT * FROM Inventory WHERE asset_id = :asset_id");
    $stmt->execute([':asset_id' => $asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($asset) {
        echo json_encode(['status' => 'success', 'data' => $asset]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Actif non trouvé.']);
    }
}

function saveAsset($conn) {
    $asset_id = $_POST['asset_id'] ?? 0;
    
    $barcode = $_POST['barcode'];
    $asset_type = $_POST['asset_type'];
    $asset_name = $_POST['asset_name'];
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $brand = $_POST['brand'] ?? null;
    $status = $_POST['status'] ?? 'available';
    
    if ($asset_type === 'tool') {
        $serial_or_plate = $_POST['serial_or_plate_tool'] ?? null;
        $position_or_info = $_POST['position_or_info_tool'] ?? null;
        $fuel_level = null;
    } else { // vehicle
        $serial_or_plate = $_POST['serial_or_plate_vehicle'] ?? null;
        $position_or_info = null;
        $fuel_level = $_POST['fuel_level'] ?? null;
    }
    
    if ($asset_id) {
        $sql = "UPDATE Inventory SET barcode=?, asset_type=?, category_id=?, asset_name=?, brand=?, serial_or_plate=?, position_or_info=?, status=?, fuel_level=?, last_modified=GETDATE() WHERE asset_id=?";
        $params = [$barcode, $asset_type, $category_id, $asset_name, $brand, $serial_or_plate, $position_or_info, $status, $fuel_level, $asset_id];
    } else {
        $sql = "INSERT INTO Inventory (barcode, asset_type, category_id, asset_name, brand, serial_or_plate, position_or_info, status, fuel_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$barcode, $asset_type, $category_id, $asset_name, $brand, $serial_or_plate, $position_or_info, $status, $fuel_level];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['status' => 'success', 'message' => 'Actif enregistré avec succès.']);
}

function deleteAsset($conn) {
    $asset_id = $_POST['asset_id'] ?? 0;
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
    $category_name = $_POST['category_name'];
    $category_type = $_POST['category_type'];
    if (empty($category_name) || empty($category_type)) throw new Exception('Nom et type de catégorie requis.');
    
    $stmt = $conn->prepare("INSERT INTO AssetCategories (category_name, category_type) VALUES (?, ?)");
    $stmt->execute([$category_name, $category_type]);
    echo json_encode(['status' => 'success', 'message' => 'Catégorie ajoutée.']);
}

function deleteCategory($conn) {
    $category_id = $_POST['category_id'];
    if (empty($category_id)) throw new Exception('ID de catégorie manquant.');

    $stmt = $conn->prepare("DELETE FROM AssetCategories WHERE category_id = ?");
    $stmt->execute([$category_id]);
    echo json_encode(['status' => 'success', 'message' => 'Catégorie supprimée.']);
}

function takeVehicle($conn, $userId) {
    $asset_id = $_POST['asset_id'];
    if (empty($asset_id)) throw new Exception('ID de l\'actif manquant.');

    $sql = "UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ? AND asset_type = 'vehicle' AND status = 'available'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $asset_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Véhicule pris avec succès.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Impossible de prendre ce véhicule. Il n\'est peut-être plus disponible.']);
    }
}
?>
