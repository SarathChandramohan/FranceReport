<?php
// technician-handler.php (Final Corrected Version)
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

/**
 * Gets all equipment for a technician for the current day.
 * This includes items booked directly by the user AND items booked for missions
 * the user is assigned to in the planning module.
 */
function getTechnicianEquipment($conn, $userId) {
    $today = date('Y-m-d');
    
    // **NEW, ROBUST QUERY**
    // This query uses a subquery with UNION to gather all relevant booking IDs first,
    // ensuring both individual and mission-based bookings are included.
    $sql = "
        SELECT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.barcode,
            i.status, i.assigned_to_user_id,
            b.booking_id, b.mission
        FROM
            Inventory i
        JOIN
            Bookings b ON i.asset_id = b.asset_id
        WHERE
            b.booking_id IN (
                -- Get bookings assigned directly to the user for today
                SELECT booking_id FROM Bookings WHERE user_id = :userId AND booking_date = :today1
                
                UNION
                
                -- Get bookings assigned to a mission the user is part of today
                SELECT booking_id FROM Bookings WHERE mission_group_id IN (
                    SELECT mission_group_id FROM Planning_Assignments WHERE assigned_user_id = :userId2 AND assignment_date = :today2
                ) AND booking_date = :today3
            )
            AND b.status IN ('booked', 'active')
        ORDER BY
            i.asset_name
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':userId' => $userId,
        ':today1' => $today,
        ':userId2' => $userId,
        ':today2' => $today,
        ':today3' => $today
    ]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response('success', 'Données récupérées.', ['equipment' => $equipment]);
}

/**
 * Checks out a specific item for the user.
 */
function checkoutItem($conn, $userId, $bookingId, $assetId) {
    if (empty($bookingId) || empty($assetId)) throw new Exception("Données de prise manquantes.");

    $conn->beginTransaction();
    try {
        // Ensure the item is available before proceeding
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ? AND status = 'available'");
        $updateInvStmt->execute([$userId, $assetId]);
        if ($updateInvStmt->rowCount() == 0) throw new Exception("Action impossible. Cet article n'est plus disponible ou est déjà pris.");

        // Mark the booking as active
        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ? AND status = 'booked'");
        $updateBookingStmt->execute([$bookingId]);
        
        $conn->commit();
        json_response('success', 'Article pris avec succès.');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * Returns a specific item.
 */
function returnItem($conn, $userId, $assetId) {
    if (empty($assetId)) throw new Exception("Données de retour manquantes.");
    
    $conn->beginTransaction();
    try {
        // Ensure the user is returning an item that is actually assigned to them
        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL WHERE asset_id = ? AND assigned_to_user_id = ?");
        $updateInvStmt->execute([$assetId, $userId]);
        if ($updateInvStmt->rowCount() == 0) throw new Exception("Retour impossible. Cet article n'est pas actuellement sorti à votre nom.");
        
        // Find the active booking for this item and complete it
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

/**
 * Helper function to send a JSON response and exit.
 */
function json_response($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}
?>
