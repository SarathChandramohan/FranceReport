<?php
// technician-handler.php (Corrected)
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
        case 'pick_item_by_barcode':
            pickItemByBarcode($conn, $currentUser['user_id'], $_POST['barcode']);
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
    
    // **THIS IS THE CORRECTED QUERY**
    // It now correctly fetches items assigned to the user for today,
    // either directly or through a mission they are part of in Planning.
    $sql = "
        SELECT DISTINCT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate,
            i.status, i.assigned_to_user_id,
            b.booking_id, b.mission
        FROM Inventory i
        JOIN Bookings b ON i.asset_id = b.asset_id
        WHERE
            b.booking_date = :today AND b.status IN ('booked', 'active')
            AND (
                -- Item is booked directly for the user
                b.user_id = :userId
                OR
                -- Item is booked for a mission the user is assigned to in Planning
                b.mission_group_id IN (
                    SELECT pa.mission_group_id
                    FROM Planning_Assignments pa
                    WHERE pa.assigned_user_id = :userId2 AND pa.assignment_date = :today2
                )
            )
        ORDER BY i.asset_name
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':today' => $today,
        ':userId' => $userId,
        ':userId2' => $userId,
        ':today2' => $today
    ]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response('success', 'Données récupérées.', ['equipment' => $equipment]);
}

function checkoutItem($conn, $userId, $bookingId, $assetId) {
    if (empty($bookingId) || empty($assetId)) throw new Exception("Données manquantes.");

    $conn->beginTransaction();
    try {
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ? AND status = 'available'");
        $updateInvStmt->execute([$userId, $assetId]);
        if ($updateInvStmt->rowCount() == 0) throw new Exception("Cet article n'est plus disponible.");

        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ? AND status = 'booked'");
        $updateBookingStmt->execute([$bookingId]);
        
        $conn->commit();
        json_response('success', 'Article marqué comme pris.');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function returnItem($conn, $userId, $assetId) {
    if (empty($assetId)) throw new Exception("ID d'article manquant.");
    
    $conn->beginTransaction();
    try {
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL, assigned_mission = NULL WHERE asset_id = ? AND assigned_to_user_id = ?");
        $updateInvStmt->execute([$assetId, $userId]);
        if ($updateInvStmt->rowCount() == 0) throw new Exception("L'article n'a pas pu être retourné. Il n'est peut-être pas sorti à votre nom.");
        
        $today = date('Y-m-d');
        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND user_id = ? AND status = 'active' AND booking_date = ?");
        $updateBookingStmt->execute([$assetId, $userId, $today]);

        $conn->commit();
        json_response('success', 'Article retourné avec succès.');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function pickItemByBarcode($conn, $userId, $barcode) {
    if (empty($barcode)) throw new Exception("Le code-barres est manquant.");
    $today = date('Y-m-d');
    $conn->beginTransaction();
    try {
        $checkStmt = $conn->prepare("SELECT asset_id, status FROM Inventory WHERE barcode = ? FOR UPDATE");
        $checkStmt->execute([$barcode]);
        $asset = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset) throw new Exception("Aucun article trouvé avec ce code-barres.");
        if ($asset['status'] !== 'available') throw new Exception("Cet article n'est pas disponible pour le moment.");

        $assetId = $asset['asset_id'];

        $bookingCheckStmt = $conn->prepare("SELECT COUNT(*) FROM Bookings WHERE asset_id = ? AND booking_date = ? AND status IN ('booked', 'active')");
        $bookingCheckStmt->execute([$assetId, $today]);
        if ($bookingCheckStmt->fetchColumn() > 0) throw new Exception("Cet article est déjà réservé par quelqu'un d'autre pour aujourd'hui.");

        $bookStmt = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, ?, ?, 'Prise directe via scan', 'active')");
        $bookStmt->execute([$assetId, $userId, $today]);

        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?");
        $updateInvStmt->execute([$userId, $assetId]);

        $conn->commit();
        json_response('success', 'Article pris avec succès et ajouté à votre liste.');
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
