<?php
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
        // Endpoints for multi-day pickup flow
        case 'get_item_availability_for_pickup':
            getItemAvailabilityForPickup($conn, $_POST['barcode']);
            break;
        case 'book_and_pickup_multiple_days':
            bookAndPickupMultipleDays($conn, $currentUser['user_id'], $_POST['asset_id'], $_POST['dates']);
            break;
        default:
            json_response('error', 'Action non valide.');
    }
} catch (Exception $e) {
    error_log("Technician handler error: " . $e->getMessage());
    json_response('error', 'Erreur: ' . $e->getMessage());
}

function getItemAvailabilityForPickup($conn, $barcode) {
    if (empty($barcode)) {
        throw new Exception("Code-barres manquant.");
    }

    $stmt_asset = $conn->prepare("SELECT asset_id, asset_name, status FROM Inventory WHERE barcode = ?");
    $stmt_asset->execute([$barcode]);
    $asset = $stmt_asset->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        throw new Exception("Aucun article trouvé avec ce code-barres.");
    }
    if (!in_array($asset['status'], ['available', 'pending_verification'])) {
        throw new Exception("Cet article n'est pas disponible pour une prise. Statut actuel: " . $asset['status']);
    }

    $stmt_bookings = $conn->prepare("
        SELECT booking_date FROM Bookings 
        WHERE asset_id = ? AND status IN ('booked', 'active') AND booking_date >= ?
    ");
    $stmt_bookings->execute([$asset['asset_id'], date('Y-m-d')]);
    $booked_dates = $stmt_bookings->fetchAll(PDO::FETCH_COLUMN, 0);

    json_response('success', 'Disponibilité récupérée.', ['asset' => $asset, 'booked_dates' => $booked_dates]);
}

function bookAndPickupMultipleDays($conn, $userId, $assetId, $dates) {
    if (empty($assetId) || empty($dates) || !is_array($dates)) {
        throw new Exception("Données de réservation manquantes ou incorrectes.");
    }
    
    $conn->beginTransaction();

    try {
        // CORRECTED: Use SQL Server-compliant locking hint (UPDLOCK, ROWLOCK)
        $checkStmt = $conn->prepare("SELECT status FROM Inventory WITH (UPDLOCK, ROWLOCK) WHERE asset_id = ?");
        $checkStmt->execute([$assetId]);
        $status = $checkStmt->fetchColumn();

        if (!in_array($status, ['available', 'pending_verification'])) {
             throw new Exception("L'article n'est plus disponible.");
        }

        $mission = "Prise directe sur plusieurs jours";
        
        $bookStmt = $conn->prepare("INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, ?, ?, ?, ?)");
        
        $isFirstDay = true;
        foreach($dates as $date) {
            $bookingStatus = $isFirstDay ? 'active' : 'booked';
            $bookStmt->execute([$assetId, $userId, $date, $mission, $bookingStatus]);
            $isFirstDay = false;
        }

        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?");
        $updateInvStmt->execute([$userId, $assetId]);

        $conn->commit();
        json_response('success', "Article pris et réservé avec succès pour les dates sélectionnées.");

    } catch (Exception $e) {
        $conn->rollBack();
        // Provide a more detailed error message for logging
        error_log("Booking failed: " . $e->getMessage());
        throw new Exception("Échec de la réservation de l'article. Erreur: " . $e->getMessage());
    }
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
            Bookings b ON i.asset_id = b.asset_id AND b.booking_date = ? AND b.status = 'booked'
        LEFT JOIN
            Planning_Assignments pa ON b.mission_group_id = pa.mission_group_id AND pa.assignment_date = ?
        WHERE
            (b.booking_date = ? AND (b.user_id = ? OR pa.assigned_user_id = ?))
            OR
            (i.status = 'in-use' AND i.assigned_to_user_id = ?)
        ORDER BY
            i.asset_name;
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$today, $today, $today, $userId, $userId, $userId]);
    
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
    
    if (!in_array($asset['status'], ['available', 'pending_verification'])) {
        $conn->rollBack();
        if ($asset['status'] === 'in-use' && $asset['assigned_to_user_id'] == $userId) {
             throw new Exception("Opération impossible: Vous avez déjà cet article.");
        } else {
             throw new Exception("Opération impossible: Article non disponible. Statut: " . $asset['status']);
        }
    }

    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?");
    $updateInvStmt->execute([$userId, $assetId]);

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
        throw new Exception("Retour impossible. L'article n'est pas sorti à votre nom.");
    }

    $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'pending_verification', last_modified = GETDATE() WHERE asset_id = ?");
    $updateInvStmt->execute([$assetId]);
    
    // This query should now correctly find and update bookings made via the multi-day feature as well
    $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND status = 'active' AND user_id = ?");
    $updateBookingStmt->execute([$assetId, $userId]);
    
    $conn->commit();
    json_response('success', 'Article retourné. En attente de vérification.');
}

function json_response($status, $message, $data = []) {
    http_response_code($status === 'error' ? 400 : 200);
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}
?>
