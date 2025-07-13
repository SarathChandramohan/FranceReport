<?php
require_once 'db-connection.php';
require_once 'session-management.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Ensure user is logged in
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
        case 'get_item_availability_for_pickup':
            getItemAvailabilityForPickup($conn, $_POST['barcode']);
            break;
        case 'book_and_pickup_range':
            bookAndPickupRange($conn, $currentUser['user_id'], $_POST['asset_id'], $_POST['return_date']);
            break;
        default:
            json_response('error', 'Action non valide ou non spécifiée.');
    }
} catch (PDOException $e) {
    // Catch database-specific errors
    error_log("Database Error in technician-handler: " . $e->getMessage());
    if ($e->getCode() == '23000') {
        json_response('error', 'Erreur de clé unique. Cela indique probablement un conflit de réservation non détecté. Veuillez rafraîchir et réessayer.');
    } else {
        json_response('error', 'Erreur de base de données: ' . $e->getMessage());
    }
} catch (Exception $e) {
    // Catch general application errors
    error_log("General Error in technician-handler: " . $e->getMessage());
    json_response('error', 'Erreur: ' . $e->getMessage());
}

/**
 * Retrieves the availability of an item, respecting the strict unique key on (asset_id, booking_date).
 */
function getItemAvailabilityForPickup($conn, $barcode) {
    if (empty($barcode)) {
        throw new Exception("Le code-barres est manquant.");
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

    $today = date('Y-m-d');

    // **FIXED LOGIC**: Check for ANY booking from today onwards, regardless of status,
    // because the database UNIQUE KEY constraint applies to all rows.
    $stmt_next_booking = $conn->prepare("
        SELECT MIN(booking_date) as next_booking_date
        FROM Bookings
        WHERE asset_id = ? AND booking_date >= ?
    ");
    $stmt_next_booking->execute([$asset['asset_id'], $today]);
    $next_booking_date = $stmt_next_booking->fetchColumn();

    $is_booked_today = ($next_booking_date === $today);

    json_response('success', 'Disponibilité récupérée.', [
        'asset' => $asset,
        'next_booking_date' => $next_booking_date,
        'booked_today' => $is_booked_today
    ]);
}

/**
 * Books an item for a range of dates, with a conflict check that matches the database's strict rules.
 */
function bookAndPickupRange($conn, $userId, $assetId, $returnDateStr) {
    if (empty($assetId) || empty($returnDateStr)) {
        throw new Exception("Données de réservation manquantes (ID article ou date de retour).");
    }

    try {
        $startDate = new DateTime();
        $endDate = new DateTime($returnDateStr);
        $startDate->setTime(0, 0, 0);
        $endDate->setTime(0, 0, 0);
    } catch (Exception $e) {
        throw new Exception("Format de date de retour invalide.");
    }

    if ($startDate > $endDate) {
        throw new Exception("La date de retour doit être aujourd'hui ou une date future.");
    }

    $conn->beginTransaction();

    try {
        $stmt_check_inv = $conn->prepare("SELECT status FROM Inventory WITH (UPDLOCK, ROWLOCK) WHERE asset_id = ?");
        $stmt_check_inv->execute([$assetId]);
        $currentStatus = $stmt_check_inv->fetchColumn();

        if ($currentStatus === false) {
            throw new Exception("L'article avec l'ID $assetId n'existe pas.");
        }
        if (!in_array($currentStatus, ['available', 'pending_verification'])) {
            throw new Exception("L'article n'est plus disponible. Un autre utilisateur l'a probablement pris.");
        }

        // **FIXED LOGIC**: Check for a conflicting booking of ANY status to match the database's UNIQUE KEY constraint.
        $stmt_check_bookings = $conn->prepare("
            SELECT booking_date FROM Bookings
            WHERE asset_id = ? AND booking_date BETWEEN ? AND ?
        ");
        $stmt_check_bookings->execute([$assetId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        $conflictingDates = $stmt_check_bookings->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!empty($conflictingDates)) {
            // This error should now be triggered correctly by the frontend check, but it's here as a final safeguard.
            throw new Exception("Conflit de réservation. L'article a déjà une réservation (de n'importe quel statut) pour le(s) jour(s) : " . implode(', ', $conflictingDates));
        }

        $stmt_insert_booking = $conn->prepare(
            "INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, ?, ?, ?, ?)"
        );
        $mission = "Prise directe par technicien";
        $dateIterator = new DatePeriod($startDate, new DateInterval('P1D'), (clone $endDate)->modify('+1 day'));

        $isFirstDay = true;
        foreach ($dateIterator as $date) {
            $bookingStatus = $isFirstDay ? 'active' : 'booked';
            $stmt_insert_booking->execute([$assetId, $userId, $date->format('Y-m-d'), $mission, $bookingStatus]);
            $isFirstDay = false;
        }

        $stmt_update_inv = $conn->prepare(
            "UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?"
        );
        $stmt_update_inv->execute([$userId, $assetId]);

        $conn->commit();
        json_response('success', "Article pris et réservé avec succès jusqu'au " . $endDate->format('d/m/Y') . ".");

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function getTechnicianEquipment($conn, $userId) {
    $today = date('Y-m-d');
    $sql = "
        SELECT DISTINCT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.barcode,
            i.status, i.assigned_to_user_id,
            b.booking_id, b.mission
        FROM Inventory i
        LEFT JOIN Bookings b ON i.asset_id = b.asset_id AND b.booking_date = ? AND b.status = 'booked'
        LEFT JOIN Planning_Assignments pa ON b.mission_group_id = pa.mission_group_id AND pa.assignment_date = ?
        WHERE
            (b.booking_date = ? AND (b.user_id = ? OR pa.assigned_user_id = ?))
            OR
            (i.status = 'in-use' AND i.assigned_to_user_id = ?)
        ORDER BY i.asset_name;
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$today, $today, $today, $userId, $userId, $userId]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_response('success', 'Données récupérées.', ['equipment' => $equipment]);
}

function checkoutItem($conn, $userId, $bookingId, $assetId) {
    if (empty($assetId)) throw new Exception("Données de prise manquantes.");

    $conn->beginTransaction();
    try {
        $checkStmt = $conn->prepare("SELECT status, assigned_to_user_id FROM Inventory WHERE asset_id = ?");
        $checkStmt->execute([$assetId]);
        $asset = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset) throw new Exception("Article non trouvé.");
        if (!in_array($asset['status'], ['available', 'pending_verification'])) {
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
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function returnItem($conn, $userId, $assetId) {
    if (empty($assetId)) throw new Exception("Données de retour manquantes.");

    $conn->beginTransaction();
    try {
        $checkStmt = $conn->prepare("SELECT status, assigned_to_user_id FROM Inventory WHERE asset_id = ?");
        $checkStmt->execute([$assetId]);
        $asset = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset) throw new Exception("Article non trouvé.");
        if ($asset['status'] !== 'in-use' || $asset['assigned_to_user_id'] != $userId) {
            throw new Exception("Retour impossible. L'article n'est pas sorti à votre nom.");
        }

        $updateInvStmt = $conn->prepare("UPDATE Inventory SET status = 'pending_verification', last_modified = GETDATE() WHERE asset_id = ?");
        $updateInvStmt->execute([$assetId]);

        $updateBookingStmt = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND user_id = ? AND status IN ('active', 'booked')");
        $updateBookingStmt->execute([$assetId, $userId]);

        $conn->commit();
        json_response('success', 'Article retourné. En attente de vérification.');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function json_response($status, $message, $data = []) {
    http_response_code($status === 'error' ? 400 : 200);
    exit(json_encode(['status' => $status, 'message' => $message, 'data' => $data]));
}
?>
