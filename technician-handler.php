<?php
// technician-handler.php
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
            checkoutItem($conn, $currentUser['user_id'], $_POST['booking_id'] ?? null, $_POST['asset_id']);
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
        SELECT DISTINCT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.barcode,
            i.status, i.assigned_to_user_id,
            b.booking_id, b.mission
        FROM
            Inventory i
        LEFT JOIN
            Bookings b ON i.asset_id = b.asset_id AND b.booking_date = :today_b AND b.status IN ('booked', 'active')
        LEFT JOIN
            Planning_Assignments pa ON b.mission_group_id = pa.mission_group_id AND pa.assignment_date = :today_pa
        WHERE
            (b.booking_date = :today_b2 AND b.status = 'booked' AND (b.user_id = :userId1 OR pa.assigned_user_id = :userId2))
            OR
            (i.status = 'in-use' AND i.assigned_to_user_id = :userId3)
        ORDER BY
            i.asset_name;
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':today_b' => $today,
        ':today_pa' => $today,
        ':today_b2' => $today,
        ':userId1' => $userId,
        ':userId2' => $userId,
        ':userId3' => $userId,
    ]);
    
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_response('success', 'Données récupérées.', ['equipment' => $equipment]);
}

function checkoutItem($conn, $userId, $bookingId, $assetId) {
    if (empty($assetId)) {
        throw new Exception("Données de prise manquantes.");
    }

    $conn->beginTransaction();

    $checkStmt = $conn->prepare("SELECT status, assigned_to_user_id FROM Inventory WHERE asset_id = ?");
    $checkStmt->execute([$assetId]);
    $asset = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        $conn->rollBack();
        throw new Exception("Article non trouvé dans l'inventaire.");
    }
    
    // Allow checkout if item is available OR pending verification by an admin
    if (!in_array($asset['status'], ['available', 'pending_verification'])) {
        $conn->rollBack();
        if ($asset['status'] === 'in-use' && $asset['assigned_to_user_id'] == $userId) {
             throw new Exception("Opération impossible: Vous avez déjà cet article en votre possession.");
        } else {
             throw new Exception("Opération impossible: Cet article n'est pas disponible. Statut actuel: " . $asset['status']);
        }
    }

    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?");
    $updateInvStmt->execute([$userId, $assetId]);

    // A booking might not exist if taking an unassigned item that was pending verification
    if ($bookingId) {
        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ? AND status = 'booked'");
        $updateBookingStmt->execute([$bookingId]);
    }
    
    $conn->commit();
    json_response('success', 'Article marqué comme "en cours d\'utilisation".');
}

function returnItem($conn, $userId, $assetId) {
    if (empty($assetId)) {
        throw new Exception("Données de retour manquantes.");
    }
    
    $conn->beginTransaction();

    $checkStmt = $conn->prepare("SELECT status, assigned_to_user_id FROM Inventory WHERE asset_id = ?");
    $checkStmt->execute([$assetId]);
    $asset = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        $conn->rollBack();
        throw new Exception("Article non trouvé.");
    }
    
    if ($asset['status'] !== 'in-use' || $asset['assigned_to_user_id'] != $userId) {
        $conn->rollBack();
        throw new Exception("Retour impossible. Cet article n'est pas actuellement sorti à votre nom.");
    }

    // Instead of making it available, set it to pending verification.
    // The assigned_to_user_id is kept to know who returned it.
    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'pending_verification', last_modified = GETDATE() WHERE asset_id = ?");
    $updateInvStmt->execute([$assetId]);
    
    $today = date('Y-m-d');
    $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND status = 'active' AND user_id = ?");
    $updateBookingStmt->execute([$assetId, $userId]);
    
    $conn->commit();
    json_response('success', 'Article retourné. En attente de vérification par un responsable.');
}

function pickupUnassignedItem($conn, $userId, $barcode) {
    if (empty($barcode)) throw new Exception("Code-barres manquant.");
    $today = date('Y-m-d');

    $conn->beginTransaction();
    
    $itemStmt = $conn->prepare("SELECT * FROM Inventory WHERE barcode = ?");
    $itemStmt->execute([$barcode]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $conn->rollBack();
        throw new Exception("Aucun article trouvé avec ce code-barres.");
    }
    // Allow pickup if item is available OR pending verification
    if (!in_array($item['status'], ['available', 'pending_verification'])) {
        $conn->rollBack();
        throw new Exception("Cet article n'est pas disponible actuellement. Statut: " . $item['status']);
    }
    
    $assetId = $item['asset_id'];
    
    $bookingCheckStmt = $conn->prepare("SELECT COUNT(*) FROM Bookings WHERE asset_id = ? AND booking_date = ? AND status IN ('booked', 'active')");
    $bookingCheckStmt->execute([$assetId, $today]);
    if ($bookingCheckStmt->fetchColumn() > 0) {
        $conn->rollBack();
        throw new Exception("Action impossible. Cet article est déjà réservé pour aujourd'hui. S'il vous est assigné, utilisez le bouton 'Prendre' depuis votre liste de matériel.");
    }

    // Create a new booking for this direct pickup
    $bookStmt = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, ?, ?, 'Prise directe', 'active')");
    $bookStmt->execute([$assetId, $userId, $today]);

    // Update inventory to 'in-use'
    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?");
    $updateInvStmt->execute([$userId, $assetId]);

    $conn->commit();
    json_response('success', "Article '{$item['asset_name']}' pris avec succès et ajouté à votre nom.");
}

function json_response($status, $message, $data = []) {
    if ($status === 'error') {
        http_response_code(400); 
    } else {
        http_response_code(200);
    }
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}
?>
