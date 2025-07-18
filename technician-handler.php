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
        // Existing actions for managing already-assigned equipment
        case 'get_technician_equipment':
            getTechnicianEquipment($conn, $currentUser['user_id']);
            break;
        case 'checkout_item':
            checkoutItem($conn, $currentUser['user_id'], $_POST['booking_id'] ?? null, $_POST['asset_id']);
            break;
        case 'return_item':
            returnItem($conn, $currentUser['user_id'], $_POST['asset_id']);
            break;

        // New flow for picking up an unassigned item
        case 'get_item_availability_for_pickup':
            getItemAvailabilityForPickup($conn, $_POST['barcode']);
            break;
        case 'book_and_pickup_range':
            bookAndPickupRange($conn, $currentUser['user_id'], $_POST['asset_id'], $_POST['return_date'], $_POST['assignment_name']);
            break;
        default:
            json_response('error', 'Action non valide ou non spécifiée.');
    }
} catch (PDOException $e) {
    // Catch database-specific errors
    error_log("Database Error in technician-handler: " . $e->getMessage());
    // Check for unique key violation (SQLSTATE 23000 is for integrity constraint violations)
    if ($e->getCode() == '23000') {
        json_response('error', 'Erreur de base de données: ' . $e->getMessage());
    } else {
        json_response('error', 'Erreur de base de données: ' . $e->getMessage());
    }
} catch (Exception $e) {
    // Catch general application errors
    error_log("General Error in technician-handler: " . $e->getMessage());
    json_response('error', 'Erreur: ' . $e->getMessage());
}

/**
 * Retrieves the availability of a specific item for the pickup flow.
 * Informs the frontend about the next booking date to adjust the calendar.
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
        throw new Exception("Cet article n'est pas disponible. Statut actuel: " . $asset['status']);
    }

    $today = date('Y-m-d');

    // Find the earliest booking date from today onwards.
    $stmt_next_booking = $conn->prepare("
        SELECT MIN(booking_date) as next_booking_date
        FROM Bookings
        WHERE asset_id = ? AND status IN ('booked', 'active') AND booking_date >= ?
    ");
    $stmt_next_booking->execute([$asset['asset_id'], $today]);
    $next_booking_date = $stmt_next_booking->fetchColumn();

    // Specifically check if the item is booked for today.
    $is_booked_today = ($next_booking_date === $today);

    json_response('success', 'Disponibilité récupérée.', [
        'asset' => $asset,
        'next_booking_date' => $next_booking_date, // Can be null if no future bookings
        'booked_today' => $is_booked_today
    ]);
}

/**
 * Books an item for a consecutive range of dates and marks it as picked up.
 * This is the core transactional logic to prevent race conditions.
 */
function bookAndPickupRange($conn, $userId, $assetId, $returnDateStr, $assignmentName) {
    if (empty($assetId) || empty($returnDateStr)) {
        throw new Exception("Données de réservation manquantes (ID article ou date de retour).");
    }

    $assignmentName = !empty(trim($assignmentName)) ? trim($assignmentName) : 'Prise directe par technicien';


    try {
        $startDate = new DateTime(); // Today
        $endDate = new DateTime($returnDateStr);
        $startDate->setTime(0, 0, 0); // Normalize to beginning of day
        $endDate->setTime(0, 0, 0);   // Normalize to beginning of day
    } catch (Exception $e) {
        throw new Exception("Format de date de retour invalide.");
    }

    if ($startDate > $endDate) {
        throw new Exception("La date de retour doit être aujourd'hui ou une date future.");
    }

    $conn->beginTransaction();

    try {
        // Step 1: Lock the inventory item row and verify its status.
        $stmt_check_inv = $conn->prepare("SELECT status FROM Inventory WITH (UPDLOCK, ROWLOCK) WHERE asset_id = ?");
        $stmt_check_inv->execute([$assetId]);
        $currentStatus = $stmt_check_inv->fetchColumn();

        if ($currentStatus === false) {
            throw new Exception("L'article avec l'ID $assetId n'existe pas.");
        }
        if (!in_array($currentStatus, ['available', 'pending_verification'])) {
            throw new Exception("L'article n'est plus disponible. Un autre utilisateur l'a probablement pris. Statut actuel: $currentStatus");
        }

        // Step 2: Within the same transaction, check for booking conflicts in the desired range.
        $stmt_check_bookings = $conn->prepare("
            SELECT booking_date FROM Bookings
            WHERE asset_id = ? AND status IN ('booked', 'active') AND booking_date BETWEEN ? AND ?
        ");
        $stmt_check_bookings->execute([$assetId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        $conflictingDates = $stmt_check_bookings->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!empty($conflictingDates)) {
            throw new Exception("Conflit de réservation. L'article est déjà réservé pour le(s) jour(s) : " . implode(', ', $conflictingDates));
        }

        // Step 3: If no conflicts, insert all the new booking records.
        $stmt_insert_booking = $conn->prepare(
            "INSERT INTO Bookings (asset_id, user_id, booking_date, mission, status) VALUES (?, ?, ?, ?, ?)"
        );
        $dateIterator = new DatePeriod($startDate, new DateInterval('P1D'), (clone $endDate)->modify('+1 day')); // Include end date in iteration

        $isFirstDay = true;
        foreach ($dateIterator as $date) {
            $bookingStatus = $isFirstDay ? 'active' : 'booked';
            $stmt_insert_booking->execute([$assetId, $userId, $date->format('Y-m-d'), $assignmentName, $bookingStatus]);
            $isFirstDay = false;
        }

        // Step 4: Update the inventory item's status to 'in-use'.
        $stmt_update_inv = $conn->prepare(
            "UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ? WHERE asset_id = ?"
        );
        $stmt_update_inv->execute([$userId, $assetId]);

        // Step 5: If everything succeeded, commit the transaction.
        $conn->commit();
        json_response('success', "Article pris et réservé avec succès jusqu'au " . $endDate->format('d/m/Y') . ".");

    } catch (Exception $e) {
        // If any step fails, roll back the entire transaction.
        $conn->rollBack();
        // Re-throw the exception to be caught by the main handler.
        throw $e;
    }
}


/**
 * Retrieves equipment assigned or booked for the technician for today, or currently in their possession.
 */
function getTechnicianEquipment($conn, $userId) {
    $today = date('Y-m-d');
    $sql = "
        -- Part 1: Get items currently IN USE by the technician.
        SELECT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.barcode,
            i.status, i.assigned_to_user_id,
            (SELECT TOP 1 b.booking_id FROM Bookings b WHERE b.asset_id = i.asset_id AND b.user_id = i.assigned_to_user_id AND b.status = 'active') as booking_id,
            (SELECT TOP 1 b.mission FROM Bookings b WHERE b.asset_id = i.asset_id AND b.user_id = i.assigned_to_user_id AND b.status IN ('active', 'booked') ORDER BY b.booking_date) AS mission,
            (SELECT MAX(b.booking_date) FROM Bookings b WHERE b.asset_id = i.asset_id AND b.user_id = i.assigned_to_user_id AND b.status IN ('active', 'booked')) AS return_date,
            1 as is_validated -- Assume in-use items are for validated missions
        FROM Inventory i
        WHERE i.status = 'in-use' AND i.assigned_to_user_id = :user_id_in_use

        UNION ALL

        -- Part 2: Get items BOOKED for today but NOT yet taken.
        -- BUG FIX: Added 'b.status = 'booked'' to prevent showing items that have been returned.
        SELECT DISTINCT
            i.asset_id, i.asset_name, i.asset_type, i.serial_or_plate, i.barcode,
            i.status, i.assigned_to_user_id,
            b.booking_id,
            b.mission,
            b.booking_date AS return_date,
            pa.is_validated
        FROM Inventory i
        JOIN Bookings b ON i.asset_id = b.asset_id
        LEFT JOIN Planning_Assignments pa ON b.mission_group_id = pa.mission_group_id AND pa.assignment_date = :today_pa
        WHERE b.booking_date = :today_b
          AND b.status = 'booked' -- <-- THE FIX IS HERE
          AND (b.user_id = :user_id_booked OR pa.assigned_user_id = :user_id_pa)
          AND (pa.is_validated = 1 OR pa.is_validated IS NULL)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':user_id_in_use' => $userId,
        ':today_pa'       => $today,
        ':today_b'        => $today,
        ':user_id_booked' => $userId,
        ':user_id_pa'     => $userId,
    ]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sort the results in PHP to enforce the display order (overdue items first).
    $today_dt = new DateTime($today);
    $today_dt->setTime(0, 0, 0);

    usort($equipment, function($a, $b) use ($today_dt) {
        $a_return_date = $a['return_date'] ? (new DateTime($a['return_date']))->setTime(0,0,0) : null;
        $b_return_date = $b['return_date'] ? (new DateTime($b['return_date']))->setTime(0,0,0) : null;

        // Check for overdue status only for items 'in-use'.
        $is_a_overdue = ($a['status'] === 'in-use' && $a_return_date && $a_return_date < $today_dt);
        $is_b_overdue = ($b['status'] === 'in-use' && $b_return_date && $b_return_date < $today_dt);

        // Priority 1: Overdue items first.
        if ($is_a_overdue && !$is_b_overdue) return -1;
        if (!$is_a_overdue && $is_b_overdue) return 1;

        // Priority 2: Items to be taken today (status is not 'in-use').
        $a_is_for_today = ($a['status'] !== 'in-use');
        $b_is_for_today = ($b['status'] !== 'in-use');
        if ($a_is_for_today && !$b_is_for_today) return -1;
        if (!$a_is_for_today && $b_is_for_today) return 1;

        // Default sort by asset name.
        return strcmp($a['asset_name'], $b['asset_name']);
    });

    json_response('success', 'Données récupérées.', ['equipment' => $equipment]);
}


/**
 * Marks an item from the daily list as 'in-use'.
 */
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

/**
 * Returns an item, setting its status to 'pending_verification' and completing relevant bookings.
 */
// technician-handler.php

function returnItem($conn, $userId, $assetId) {
    // This function now completes past/current bookings and
    // CANCELS future bookings for the returned item.
    $today = date('Y-m-d');
    $conn->beginTransaction();
    try {
        // Mark past and current bookings as 'completed'
        $updatePastBookingsStmt = $conn->prepare(
            "UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND user_id = ? AND status IN ('active', 'booked') AND booking_date <= ?"
        );
        $updatePastBookingsStmt->execute([$assetId, $userId, $today]);

        // Cancel all future bookings for this item by this user
        $cancelFutureBookingsStmt = $conn->prepare(
            "UPDATE Bookings SET status = 'cancelled' WHERE asset_id = ? AND user_id = ? AND status = 'booked' AND booking_date > ?"
        );
        $cancelFutureBookingsStmt->execute([$assetId, $userId, $today]);

        // Unassign the asset
        $updateAssetStmt = $conn->prepare(
            "UPDATE Assets SET assigned_to = NULL WHERE asset_id = ?"
        );
        $updateAssetStmt->execute([$assetId]);

        $conn->commit();
        return ["message" => "Outil retourné et disponible pour une nouvelle réservation."];
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}
/**
 * Helper function to standardize and send JSON responses, then terminate the script.
 */
function json_response($status, $message, $data = []) {
    http_response_code($status === 'error' ? 400 : 200);
    // Ensure that the script execution stops after sending the response.
    exit(json_encode(['status' => $status, 'message' => $message, 'data' => $data]));
}
?>
