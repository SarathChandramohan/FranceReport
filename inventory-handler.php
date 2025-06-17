<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé.']);
    exit;
}

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
        case 'save_asset':
            saveAsset($conn);
            break;
        case 'delete_asset':
            deleteAsset($conn);
            break;
        case 'get_asset_details':
            getAssetDetails($conn);
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

    $sql = "SELECT * FROM Inventory WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (asset_name LIKE :search OR barcode LIKE :search OR brand LIKE :search OR serial_or_plate LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($type !== 'all') {
        $sql .= " AND asset_type = :type";
        $params[':type'] = $type;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $assets]);
}

function getAssetDetails($conn) {
    $asset_id = $_GET['asset_id'] ?? 0;
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
    
    // Common fields
    $barcode = $_POST['barcode'];
    $asset_type = $_POST['asset_type'];
    $asset_name = $_POST['asset_name'];
    $brand = $_POST['brand'] ?? null;
    $status = $_POST['status'] ?? 'available';
    
    // Type-specific fields
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
        // Update existing asset
        $sql = "UPDATE Inventory SET barcode=?, asset_type=?, asset_name=?, brand=?, serial_or_plate=?, position_or_info=?, status=?, fuel_level=?, last_modified=GETDATE() WHERE asset_id=?";
        $params = [$barcode, $asset_type, $asset_name, $brand, $serial_or_plate, $position_or_info, $status, $fuel_level, $asset_id];
    } else {
        // Insert new asset
        $sql = "INSERT INTO Inventory (barcode, asset_type, asset_name, brand, serial_or_plate, position_or_info, status, fuel_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$barcode, $asset_type, $asset_name, $brand, $serial_or_plate, $position_or_info, $status, $fuel_level];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['status' => 'success', 'message' => 'Actif enregistré avec succès.']);
}

function deleteAsset($conn) {
    $asset_id = $_POST['asset_id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM Inventory WHERE asset_id = :asset_id");
    $stmt->execute([':asset_id' => $asset_id]);

    echo json_encode(['status' => 'success', 'message' => 'Actif supprimé avec succès.']);
}
?>
