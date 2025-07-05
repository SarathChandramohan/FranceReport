<?php
// technician-handler.php (Final Version)
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
        default:
            json_response('error', 'Action non valide.');
    }
} catch (Exception $e) {
    error_log("Technician handler error: " . $e->getMessage() . " on line " . $e->getLine());
    json_response('error', 'Une erreur interne est survenue: ' . $e->getMessage());
}

function getTechnicianEquipment($conn, $userId) {
    $today = date('Y-m-d');
    
    $sql = "
        SELECT DISTINCT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.barcode,
            i.status, i.assigned_to_user_id,
            b.booking_id, b.mission
        FROM Inventory i
        JOIN Bookings b ON i.asset_id = b.asset_id
        WHERE
            b.booking_date = :today
            AND b.mission_group_id IN (
                SELECT DISTINCT pa.mission_group_id
                FROM Planning_Assignments pa
                WHERE pa.assigned_user_id = :userId AND pa.assignment_date = :today2
            )
        ORDER BY i.asset_name
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':today' => $today, ':userId' => $userId, ':today2' => $today]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response('success', 'Données récupérées.', ['equipment' => $equipment]);
}

function checkoutItem($conn, $userId, $bookingId, $assetId) {
    if (empty($bookingId) || empty($assetId)) throw new Exception("Données de prise manquantes.");

    $conn->beginTransaction();
    try {
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ? AND status = 'available'");
        $updateInvStmt->execute([$userId, $assetId]);
        if ($updateInvStmt->rowCount() == 0) throw new Exception("Cet article n'est plus disponible.");

        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ? AND status = 'booked'");
        $updateBookingStmt->execute([$bookingId]);
        
        $conn->commit();
        json_response('success', 'Article pris avec succès.');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function returnItem($conn, $userId, $assetId) {
    if (empty($assetId)) throw new Exception("Données de retour manquantes.");
    
    $conn->beginTransaction();
    try {
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL WHERE asset_id = ? AND assigned_to_user_id = ?");
        $updateInvStmt->execute([$assetId, $userId]);
        if ($updateInvStmt->rowCount() == 0) throw new Exception("Retour impossible. Cet article n'est pas actuellement sorti à votre nom.");
        
        $today = date('Y-m-d');
        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND status = 'active' AND booking_date <= ?");
        $updateBookingStmt->execute([$assetId, $today]);

        $conn->commit();
        json_response('success', 'Article retourné avec succès.');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function json_response($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}
?>
