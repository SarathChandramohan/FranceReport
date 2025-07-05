<?php
// technician-handler.php
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
        case 'pick_item':
            pickItem($conn, $currentUser['user_id'], $_POST['asset_id']);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }
} catch (Exception $e) {
    error_log("Technician handler error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne est survenue.']);
}

function getTechnicianData($conn, $userId) {
    $today = date('Y-m-d');
    $data = [
        'assigned_items' => [],
        'available_items' => []
    ];

    // 1. Get items booked for the technician's missions today OR directly by the technician
    $sqlAssigned = "
        SELECT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.status, i.assigned_to_user_id,
            b.booking_id, b.mission
        FROM Bookings b
        JOIN Inventory i ON b.asset_id = i.asset_id
        WHERE b.booking_date = ? AND b.status IN ('booked', 'active')
          AND (
            -- Booked directly by the user
            b.user_id = ?
            OR
            -- Booked for a mission the user is assigned to
            b.mission_group_id IN (
                SELECT DISTINCT mission_group_id FROM Planning_Assignments pa
                WHERE pa.assigned_user_id = ? AND pa.assignment_date = ?
            )
          )
        ORDER BY i.asset_name
    ";
    $stmtAssigned = $conn->prepare($sqlAssigned);
    $stmtAssigned->execute([$today, $userId, $userId, $today]);
    $data['assigned_items'] = $stmtAssigned->fetchAll(PDO::FETCH_ASSOC);


    // 2. Get items that are available and have no bookings today
    $sqlAvailable = "
        SELECT asset_id, asset_name, asset_type, serial_or_plate
        FROM Inventory
        WHERE status = 'available'
          AND asset_id NOT IN (
            SELECT asset_id FROM Bookings WHERE booking_date = ? AND status IN ('booked', 'active')
          )
        ORDER BY asset_name
    ";
    $stmtAvailable = $conn->prepare($sqlAvailable);
    $stmtAvailable->execute([$today]);
    $data['available_items'] = $stmtAvailable->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $data]);
}

function checkoutItems($conn, $userId, $itemsJson) {
    $items = json_decode($itemsJson, true);
    if (empty($items)) {
        throw new Exception("Aucun article sélectionné.");
    }

    $conn->beginTransaction();
    try {
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ? AND status = 'available'");
        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ?");

        foreach ($items as $item) {
            // Mark inventory as in-use by the current user
            $updateInvStmt->execute([$userId, $item['asset_id']]);
            if ($updateInvStmt->rowCount() == 0) {
                 throw new Exception("L'article ID {$item['asset_id']} n'est plus disponible.");
            }
            // Mark the booking as active
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
        // Find all items currently checked out by the user
        $getStmt = $conn->prepare("SELECT asset_id FROM Inventory WHERE assigned_to_user_id = ? AND status = 'in-use'");
        $getStmt->execute([$userId]);
        $itemsToReturn = $getStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($itemsToReturn)) {
            echo json_encode(['status' => 'success', 'message' => 'Aucun article à retourner.']);
            $conn->commit();
            return;
        }

        $itemPlaceholders = implode(',', array_fill(0, count($itemsToReturn), '?'));

        // Update Inventory status
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL, assigned_mission = NULL WHERE assigned_to_user_id = ?");
        $updateInvStmt->execute([$userId]);

        // Update related active bookings to 'completed'
        $today = date('Y-m-d');
        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE user_id = ? AND status = 'active' AND asset_id IN ($itemPlaceholders)");
        $params = array_merge([$userId], $itemsToReturn);
        $updateBookingStmt->execute($params);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => $updateInvStmt->rowCount() . ' article(s) retourné(s) avec succès.']);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function pickItem($conn, $userId, $assetId) {
    if (empty($assetId)) {
        throw new Exception("ID d'article manquant.");
    }
    $today = date('Y-m-d');

    $conn->beginTransaction();
    try {
        // Step 1: Lock the row and check if the item is still available
        $checkStmt = $conn->prepare("SELECT status FROM Inventory WHERE asset_id = ? FOR UPDATE");
        $checkStmt->execute([$assetId]);
        $asset = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset || $asset['status'] !== 'available') {
            throw new Exception("Cet article n'est plus disponible.");
        }

        // Step 2: Check for existing bookings on the same day (another check for safety)
        $bookingCheckStmt = $conn->prepare("SELECT COUNT(*) FROM Bookings WHERE asset_id = ? AND booking_date = ? AND status IN ('booked', 'active')");
        $bookingCheckStmt->execute([$assetId, $today]);
        if ($bookingCheckStmt->fetchColumn() > 0) {
            throw new Exception("Cet article a été réservé par quelqu'un d'autre entre-temps.");
        }

        // Step 3: Create a booking for the user for today
        $bookStmt = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, ?, ?, 'Prise directe', 'active')");
        $bookStmt->execute([$assetId, $userId, $today]);
        if ($bookStmt->rowCount() == 0) {
            throw new Exception("Échec de la création de la réservation.");
        }

        // Step 4: Update inventory status to 'in-use'
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?");
        $updateInvStmt->execute([$userId, $assetId]);
        if ($updateInvStmt->rowCount() == 0) {
            throw new Exception("Échec de la mise à jour du statut de l'inventaire.");
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Article pris avec succès.']);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}
?>
