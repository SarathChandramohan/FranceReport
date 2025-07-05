<?php
// technician-handler.php (New and Final Version)
require_once 'db-connection.php';
require_once 'session-management.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    json_response('error', 'Session expirée. Veuillez vous reconnecter.');
}

$currentUser = getCurrentUser();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_technician_equipment':
            getTechnicianEquipment($conn, $currentUser['user_id']);
            break;
        case 'checkout_item':
            checkoutItem($conn, $currentUser['user_id'], $_POST['booking_id'], $_POST['asset_id']);
            break;
        case 'return_item':
            returnItem($conn, $currentUser['user_id'], $_POST['asset_id']);
            break;
        case 'pickup_unassigned_item':
            pickupUnassignedItem($conn, $currentUser['user_id'], $_POST['barcode']);
            break;
        default:
            json_response('error', 'Action non valide.');
    }
} catch (Exception $e) {
    error_log("Technician handler error: " . $e->getMessage());
    json_response('error', 'Erreur: ' . $e->getMessage());
}

function getTechnicianEquipment($conn, $userId) {
    $today = date('Y-m-d');
    $sql = "
        SELECT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.barcode,
            i.status, i.assigned_to_user_id, b.booking_id, b.mission
        FROM Inventory i JOIN Bookings b ON i.asset_id = b.asset_id
        WHERE b.booking_id IN (
            SELECT booking_id FROM Bookings WHERE user_id = :userId AND booking_date = :today1
            UNION
            SELECT booking_id FROM Bookings WHERE mission_group_id IN (
                SELECT mission_group_id FROM Planning_Assignments WHERE assigned_user_id = :userId2 AND assignment_date = :today2
            ) AND booking_date = :today3
        ) AND b.status IN ('booked', 'active')
        ORDER BY i.asset_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':userId' => $userId, ':today1' => $today, ':userId2' => $userId, ':today2' => $today, ':today3' => $today]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_response('success', 'Données récupérées.', ['equipment' => $equipment]);
}

function checkoutItem($conn, $userId, $bookingId, $assetId) {
    if (empty($bookingId) || empty($assetId)) throw new Exception("Données de prise manquantes.");

    $conn->beginTransaction();
    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ? AND status = 'available'");
    $updateInvStmt->execute([$userId, $assetId]);
    if ($updateInvStmt->rowCount() == 0) {
        $conn->rollBack();
        throw new Exception("Action impossible. Cet article n'est plus disponible ou est déjà pris.");
    }
    $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ? AND status = 'booked'");
    $updateBookingStmt->execute([$bookingId]);
    $conn->commit();
    json_response('success', 'Article pris avec succès.');
}

function returnItem($conn, $userId, $assetId) {
    if (empty($assetId)) throw new Exception("Données de retour manquantes.");
    
    $conn->beginTransaction();
    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL WHERE asset_id = ? AND assigned_to_user_id = ?");
    $updateInvStmt->execute([$assetId, $userId]);
    if ($updateInvStmt->rowCount() == 0) {
        $conn->rollBack();
        throw new Exception("Retour impossible. Cet article n'est pas actuellement sorti à votre nom.");
    }
    $today = date('Y-m-d');
    $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND status = 'active' AND booking_date <= ?");
    $updateBookingStmt->execute([$assetId, $today]);
    $conn->commit();
    json_response('success', 'Article retourné avec succès.');
}

function pickupUnassignedItem($conn, $userId, $barcode) {
    if (empty($barcode)) throw new Exception("Code-barres manquant.");
    $today = date('Y-m-d');

    $conn->beginTransaction();
    
    $itemStmt = $conn->prepare("SELECT * FROM Inventory WHERE barcode = ? FOR UPDATE");
    $itemStmt->execute([$barcode]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) throw new Exception("Aucun article trouvé avec ce code-barres.");
    if ($item['status'] !== 'available') throw new Exception("Cet article n'est pas disponible actuellement.");
    
    $assetId = $item['asset_id'];
    
    $bookingCheckStmt = $conn->prepare("SELECT COUNT(*) FROM Bookings WHERE asset_id = ? AND booking_date = ? AND status IN ('booked', 'active')");
    $bookingCheckStmt->execute([$assetId, $today]);
    if ($bookingCheckStmt->fetchColumn() > 0) {
        throw new Exception("Cet article est déjà réservé pour une mission aujourd'hui.");
    }

    $bookStmt = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, ?, ?, 'Prise directe', 'active')");
    $bookStmt->execute([$assetId, $userId, $today]);

    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?");
    $updateInvStmt->execute([$userId, $assetId]);

    $conn->commit();
    json_response('success', "Article '{$item['asset_name']}' pris avec succès et ajouté à votre nom.");
}

function json_response($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}
?>
