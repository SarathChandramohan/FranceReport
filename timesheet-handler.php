<?php
// technician-handler.php (Corrected and Reworked)
require_once 'db-connection.php';
require_once 'session-management.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expirée. Veuillez vous reconnecter.']);
    exit;
}

$currentUser = getCurrentUser();
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

try {
    switch ($action) {
        case 'get_technician_data':
            getTechnicianData($conn, $currentUser['user_id']);
            break;
        case 'checkout_items':
            checkoutItems($conn, $currentUser['user_id'], $_POST['items']);
            break;
        case 'return_my_items':
            returnMyItems($conn, $currentUser['user_id']);
            break;
        case 'pick_item_by_barcode':
            pickItemByBarcode($conn, $currentUser['user_id'], $_POST['barcode']);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }
} catch (Exception $e) {
    error_log("Technician handler error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne est survenue: ' . $e->getMessage()]);
}

function getTechnicianData($conn, $userId) {
    $today = date('Y-m-d');
    $data = ['assigned_items' => []];

    // More robust query to get items booked for the technician today.
    // This includes items booked for a mission they are on, or items they booked directly.
    $sqlAssigned = "
        SELECT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.status, i.assigned_to_user_id,
            b.booking_id, b.mission
        FROM Inventory i
        JOIN Bookings b ON i.asset_id = b.asset_id
        WHERE b.booking_date = :today AND b.status IN ('booked', 'active')
          AND (
            b.user_id = :userId
            OR
            b.mission_group_id IN (
                SELECT DISTINCT pa.mission_group_id
                FROM Planning_Assignments pa
                WHERE pa.assigned_user_id = :userId2 AND pa.assignment_date = :today2
            )
          )
        ORDER BY i.asset_name
    ";
    $stmtAssigned = $conn->prepare($sqlAssigned);
    $stmtAssigned->execute([':today' => $today, ':userId' => $userId, ':userId2' => $userId, ':today2' => $today]);
    $data['assigned_items'] = $stmtAssigned->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $data]);
}


function checkoutItems($conn, $userId, $itemsJson) {
    $items = json_decode($itemsJson, true);
    if (empty($items)) throw new Exception("Aucun article sélectionné.");

    $conn->beginTransaction();
    try {
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ? AND status = 'available'");
        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ? AND status = 'booked'");

        foreach ($items as $item) {
            $updateInvStmt->execute([$userId, $item['asset_id']]);
            if ($updateInvStmt->rowCount() == 0) throw new Exception("L'article ID {$item['asset_id']} n'est plus disponible.");
            $updateBookingStmt->execute([$item['booking_id']]);
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => count($items) . ' article(s) marqué(s) comme pris.']);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function returnMyItems($conn, $userId) {
    $conn->beginTransaction();
    try {
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL, assigned_mission = NULL WHERE assigned_to_user_id = ? AND status = 'in-use'");
        $updateInvStmt->execute([$userId]);
        $rowCount = $updateInvStmt->rowCount();

        if ($rowCount > 0) {
            $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE user_id = ? AND status = 'active'");
            $updateBookingStmt->execute([$userId]);
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => $rowCount . ' article(s) retourné(s) avec succès.']);
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
        echo json_encode(['status' => 'success', 'message' => 'Article pris avec succès.']);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}
?>
