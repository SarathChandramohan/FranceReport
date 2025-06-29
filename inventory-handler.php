<?php
// inventory-handler.php

require_once 'session-management.php';
requireLogin();
require_once 'db-connection.php';

header('Content-Type: application/json');
$currentUser = getCurrentUser();
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

try {
    switch ($action) {
        // INVENTORY & ASSET ACTIONS
        case 'get_inventory': getInventory($conn); break;
        case 'add_asset': addAsset($conn, $currentUser); break;
        case 'update_asset': updateAsset($conn, $currentUser); break;
        case 'delete_asset': deleteAsset($conn, $currentUser); break;
        case 'update_maintenance_status': updateMaintenanceStatus($conn, $currentUser); break;
        
        // BOOKING & SCANNER ACTIONS
        case 'process_scan': processScan($conn, $currentUser); break;
        case 'book_asset': bookAsset($conn, $currentUser); break;
        case 'get_all_bookings': getAllBookings($conn); break;
        case 'get_asset_availability': getAssetAvailability($conn); break;
        case 'get_asset_history': getAssetHistory($conn); break;
        case 'cancel_booking': cancelBooking($conn, $currentUser); break;

        // CATEGORY ACTIONS
        case 'get_categories': getAssetCategories($conn); break;
        case 'add_category': addCategory($conn, $currentUser); break;
        case 'update_category': updateCategory($conn, $currentUser); break;
        case 'delete_category': deleteCategory($conn, $currentUser); break;

        // USER ACTION
        case 'get_users': getUsers($conn); break;
            
        default:
            throw new Exception("Action non valide ou non spécifiée.");
    }
} catch (PDOException $e) {
    error_log("Database Error in inventory-handler.php: " . $e->getMessage());
    respondWithError("Erreur de base de données: " . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("General Error in inventory-handler.php: " . $e->getMessage());
    respondWithError($e->getMessage());
}

function respondWithSuccess($data = [], $message = "Opération réussie.") {
    echo json_encode(['status' => 'success', 'message' => $message] + $data);
    exit;
}

function respondWithError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

/**
 * MODIFIED FUNCTION
 * Processes a barcode scan for checking out or returning an asset.
 * Now handles team-based checkouts for missions.
 */
function processScan($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $barcode = isset($data['barcode']) ? trim($data['barcode']) : '';
    if (empty($barcode)) throw new Exception("Code-barres non fourni.");

    $stmt_asset = $conn->prepare("SELECT * FROM Inventory WHERE barcode = ?");
    $stmt_asset->execute([$barcode]);
    $asset = $stmt_asset->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        respondWithSuccess(['scan_code' => 'asset_not_found', 'barcode' => $barcode], "Cet actif n'existe pas. Voulez-vous l'ajouter?");
    }
    if ($asset['status'] === 'maintenance') {
        throw new Exception("Cet actif est actuellement en maintenance.");
    }

    $today = date('Y-m-d');
    $current_user_id = $user['user_id'];

    // --- LOGIC FOR RETURNING AN ASSET ---
    if ($asset['status'] === 'in-use') {
        if ($asset['assigned_to_user_id'] == $current_user_id) {
            $conn->beginTransaction();
            // Update any active booking for this asset to 'completed'
            $stmt_update_booking = $conn->prepare("UPDATE Bookings SET status = 'completed' WHERE asset_id = ? AND status = 'active'");
            $stmt_update_booking->execute([$asset['asset_id']]);
            
            // Make the asset available again
            $stmt_update_inventory = $conn->prepare("UPDATE Inventory SET status = 'available', assigned_to_user_id = NULL, assigned_mission = NULL, last_modified = GETDATE() WHERE asset_id = ?");
            $stmt_update_inventory->execute([$asset['asset_id']]);
            $conn->commit();
            respondWithSuccess(['scan_code' => 'return_success'], "Actif retourné avec succès.");
        } else {
            $stmt_user = $conn->prepare("SELECT prenom, nom FROM Users WHERE user_id = ?");
            $stmt_user->execute([$asset['assigned_to_user_id']]);
            $assigned_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $user_name = $assigned_user ? $assigned_user['prenom'] . ' ' . $assigned_user['nom'] : 'un autre utilisateur';
            throw new Exception("Cet actif est actuellement utilisé par " . $user_name . ".");
        }
    }

    // --- LOGIC FOR CHECKING OUT AN ASSET ---
    if ($asset['status'] === 'available') {
        // Find a booking for today. Use LEFT JOIN on Users as user_id can be NULL for missions.
        $stmt_booking = $conn->prepare("
            SELECT b.*, u.prenom, u.nom
            FROM Bookings b
            LEFT JOIN Users u ON b.user_id = u.user_id
            WHERE b.asset_id = ? AND b.booking_date = ? AND b.status = 'booked'
        ");
        $stmt_booking->execute([$asset['asset_id'], $today]);
        $booking = $stmt_booking->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            $is_authorized = false;
            $mission_text = $booking['mission'];

            // Case A: Personal Booking (user_id is not null)
            if (!empty($booking['user_id'])) {
                if ($booking['user_id'] == $current_user_id) {
                    $is_authorized = true;
                } else {
                    throw new Exception("Action impossible. L'actif est réservé par " . $booking['prenom'] . " " . $booking['nom'] . " pour aujourd'hui.");
                }
            }
            // Case B: Mission/Team Booking (user_id is null)
            else if (!empty($booking['mission_group_id'])) {
                $stmt_check_permission = $conn->prepare("
                    SELECT COUNT(*) FROM Planning_Assignments
                    WHERE mission_group_id = ? AND assigned_user_id = ?
                ");
                $stmt_check_permission->execute([$booking['mission_group_id'], $current_user_id]);
                if ($stmt_check_permission->fetchColumn() > 0) {
                    $is_authorized = true;
                } else {
                     throw new Exception("Vous n'êtes pas assigné à la mission pour laquelle cet outil est réservé.");
                }
            } else {
                 // Fallback for old data or misconfiguration
                 throw new Exception("Cette réservation de mission est mal configurée (ID de groupe manquant).");
            }

            // If authorized, proceed with the checkout
            if ($is_authorized) {
                $conn->beginTransaction();
                
                // Set booking to active
                $stmt_update_booking = $conn->prepare("UPDATE Bookings SET status = 'active' WHERE booking_id = ?");
                $stmt_update_booking->execute([$booking['booking_id']]);

                // Update inventory: set status to 'in-use' and assign to the user who performed the scan
                $stmt_update_inventory = $conn->prepare("
                    UPDATE Inventory SET status = 'in-use', assigned_to_user_id = ?, assigned_mission = ?, last_modified = GETDATE()
                    WHERE asset_id = ?
                ");
                $stmt_update_inventory->execute([$current_user_id, $mission_text, $asset['asset_id']]);
                
                $conn->commit();
                respondWithSuccess(['scan_code' => 'checkout_success', 'asset' => $asset], "Sortie de l'actif enregistrée.");
            }
        } else {
            // No booking found for today, prompt user to create one
            respondWithSuccess(['scan_code' => 'prompt_booking', 'asset' => $asset], "Aucune réservation pour aujourd'hui. Veuillez en créer une.");
        }
    }
}


// --- OTHER UNCHANGED FUNCTIONS ---

function getInventory($conn) {
    $sql = "SELECT i.*, ac.category_name, u_assigned.prenom AS assigned_to_prenom, u_assigned.nom AS assigned_to_nom, (SELECT MIN(b.booking_date) FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date > CAST(GETDATE() AS DATE) AND b.status = 'booked') as next_future_booking_date, todays_booking.user_id AS todays_booking_user_id, todays_booking.mission AS todays_booking_mission, u_booking.prenom AS todays_booking_prenom, u_booking.nom AS todays_booking_nom FROM Inventory i LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id LEFT JOIN Users u_assigned ON i.assigned_to_user_id = u_assigned.user_id OUTER APPLY ( SELECT TOP 1 b.user_id, b.mission FROM Bookings b WHERE b.asset_id = i.asset_id AND b.booking_date = CAST(GETDATE() AS DATE) AND b.status = 'booked' ) AS todays_booking LEFT JOIN Users u_booking ON todays_booking.user_id = u_booking.user_id ORDER BY i.asset_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['inventory' => $inventory]);
}

function updateAsset($conn, $user) {
    if ($user['role'] !== 'admin') respondWithError("Accès non autorisé.", 403);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['asset_id'], $data['barcode'], $data['asset_name'], $data['asset_type'])) throw new Exception("Données manquantes pour la mise à jour.");
    $asset_id = $data['asset_id'];
    $barcode = trim($data['barcode']);
    $asset_name = trim($data['asset_name']);
    if (empty($barcode) || empty($asset_name)) throw new Exception("Le code-barres et le nom de l'actif sont obligatoires.");
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Inventory WHERE barcode = ? AND asset_id != ?");
    $stmt_check->execute([$barcode, $asset_id]);
    if ($stmt_check->fetchColumn() > 0) throw new Exception("Ce code-barres est déjà utilisé par un autre actif.");
    $sql = "UPDATE Inventory SET barcode = ?, asset_type = ?, category_id = ?, asset_name = ?, brand = ?, serial_or_plate = ?, position_or_info = ?, fuel_level = ?, last_modified = GETDATE() WHERE asset_id = ?";
    $params = [$barcode, $data['asset_type'], empty($data['category_id']) ? null : $data['category_id'], $asset_name, empty($data['brand']) ? null : trim($data['brand']), empty($data['serial_or_plate']) ? null : trim($data['serial_or_plate']), empty($data['position_or_info']) ? null : trim($data['position_or_info']), empty($data['fuel_level']) ? null : $data['fuel_level'], $asset_id];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $select_stmt = $conn->prepare("SELECT i.*, ac.category_name, u.prenom AS assigned_to_prenom, u.nom AS assigned_to_nom FROM Inventory i LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id WHERE i.asset_id = ?");
    $select_stmt->execute([$asset_id]);
    $updatedAsset = $select_stmt->fetch(PDO::FETCH_ASSOC);
    if ($updatedAsset) respondWithSuccess(['asset' => $updatedAsset], "Actif mis à jour avec succès.");
    else throw new Exception("Échec de la mise à jour de l'actif.");
}

function getAssetHistory($conn) {
    $asset_id = isset($_GET['asset_id']) ? intval($_GET['asset_id']) : 0;
    if (!$asset_id) throw new Exception("ID de l'actif manquant.");
    $sql = "SELECT b.booking_date, b.mission, b.status, u.prenom, u.nom FROM Bookings b JOIN Users u ON b.user_id = u.user_id WHERE b.asset_id = ? AND b.status IN ('active', 'completed') ORDER BY b.booking_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['history' => $history]);
}

function getAssetCategories($conn) {
    $stmt = $conn->prepare("SELECT category_id, category_name, category_type FROM AssetCategories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['categories' => $categories]);
}

function addCategory($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty(trim($data['category_name'])) || empty($data['category_type'])) throw new Exception("Le nom et le type de la catégorie sont obligatoires.");
    $name = trim($data['category_name']);
    $type = $data['category_type'];
    if (!in_array($type, ['tool', 'vehicle'])) throw new Exception("Type de catégorie non valide.");
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM AssetCategories WHERE category_name = ? AND category_type = ?");
    $stmt_check->execute([$name, $type]);
    if ($stmt_check->fetchColumn() > 0) throw new Exception("Une catégorie avec ce nom et ce type existe déjà.");
    $sql = "INSERT INTO AssetCategories (category_name, category_type) OUTPUT INSERTED.* VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name, $type]);
    $newCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($newCategory) respondWithSuccess(['category' => $newCategory], "Catégorie créée avec succès.");
    else throw new Exception("Échec de la création de la catégorie.");
}

function updateCategory($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['category_id']) || empty(trim($data['category_name']))) throw new Exception("Données de catégorie manquantes.");
    $id = $data['category_id'];
    $name = trim($data['category_name']);
    $stmt_check = $conn->prepare("SELECT category_id, category_type FROM AssetCategories WHERE category_id = ?");
    $stmt_check->execute([$id]);
    $category = $stmt_check->fetch(PDO::FETCH_ASSOC);
    if (!$category) throw new Exception("Catégorie non trouvée.");
    $stmt_dup = $conn->prepare("SELECT COUNT(*) FROM AssetCategories WHERE category_name = ? AND category_type = ? AND category_id != ?");
    $stmt_dup->execute([$name, $category['category_type'], $id]);
    if ($stmt_dup->fetchColumn() > 0) throw new Exception("Une autre catégorie du même type a déjà ce nom.");
    $stmt = $conn->prepare("UPDATE AssetCategories SET category_name = ? WHERE category_id = ?");
    $stmt->execute([$name, $id]);
    respondWithSuccess([], "Catégorie mise à jour avec succès.");
}

function deleteCategory($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['category_id'])) throw new Exception("ID de catégorie manquant.");
    $id = $data['category_id'];
    $stmt = $conn->prepare("DELETE FROM AssetCategories WHERE category_id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) respondWithSuccess([], "Catégorie supprimée. Les actifs associés ne sont plus catégorisés.");
    else throw new Exception("La catégorie à supprimer n'a pas été trouvée.");
}

function bookAsset($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['asset_id'], $data['booking_date']) || empty($data['booking_date'])) throw new Exception("Données de réservation manquantes.");
    $asset_id = $data['asset_id'];
    $booking_date = $data['booking_date'];
    $mission = isset($data['mission']) ? trim($data['mission']) : 'Non spécifiée';
    $user_id = $user['user_id'];
    $stmt_check = $conn->prepare("SELECT booking_id FROM Bookings WHERE asset_id = ? AND booking_date = ? AND status IN ('booked', 'active')");
    $stmt_check->execute([$asset_id, $booking_date]);
    if ($stmt_check->fetch()) throw new Exception("Cet actif est déjà réservé pour cette date.");
    $sql = "INSERT INTO Bookings (asset_id, user_id, booking_date, mission) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id, $user_id, $booking_date, $mission]);
    respondWithSuccess([], "Actif réservé avec succès.");
}

function getAssetAvailability($conn) {
    $asset_id = isset($_GET['asset_id']) ? $_GET['asset_id'] : 0;
    if (!$asset_id) throw new Exception("ID de l'actif manquant.");
    $sql = "SELECT booking_date FROM Bookings WHERE asset_id = ? AND booking_date >= CAST(GETDATE() AS DATE) AND status IN ('booked', 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    respondWithSuccess(['booked_dates' => $dates]);
}

function getAllBookings($conn) {
    $sql = "SELECT b.booking_id, b.booking_date, b.mission, b.status, a.asset_name, a.barcode, u.prenom, u.nom, u.user_id FROM Bookings b JOIN Inventory a ON b.asset_id = a.asset_id JOIN Users u ON b.user_id = u.user_id WHERE b.booking_date >= CAST(GETDATE() AS DATE) AND b.status IN ('booked', 'active') ORDER BY b.booking_date ASC, a.asset_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['bookings' => $bookings]);
}

function cancelBooking($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $booking_id = isset($data['booking_id']) ? $data['booking_id'] : 0;
    if (!$booking_id) throw new Exception("ID de réservation manquant.");
    $stmt_check = $conn->prepare("SELECT user_id FROM Bookings WHERE booking_id = ?");
    $stmt_check->execute([$booking_id]);
    $booking_user_id = $stmt_check->fetchColumn();
    if ($user['role'] !== 'admin' && $user['user_id'] != $booking_user_id) respondWithError("Vous n'êtes pas autorisé à annuler cette réservation.", 403);
    $stmt = $conn->prepare("UPDATE Bookings SET status = 'cancelled' WHERE booking_id = ? AND status = 'booked'");
    $stmt->execute([$booking_id]);
    if ($stmt->rowCount() > 0) respondWithSuccess([], "Réservation annulée.");
    else throw new Exception("Impossible d'annuler cette réservation.");
}

function updateMaintenanceStatus($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $asset_id = $data['asset_id'];
    $status = $data['status'];
    if (!in_array($status, ['maintenance', 'available'])) throw new Exception("Statut non valide.");
    if ($status === 'maintenance') {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Bookings WHERE asset_id = ? AND booking_date >= CAST(GETDATE() AS DATE) AND status IN ('booked', 'active')");
        $stmt_check->execute([$asset_id]);
        if ($stmt_check->fetchColumn() > 0) throw new Exception("Impossible de mettre en maintenance. L'actif a des réservations futures. Veuillez les annuler d'abord.");
    }
    $sql = "UPDATE Inventory SET status = ?, last_modified = GETDATE() WHERE asset_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status, $asset_id]);
    $stmt_updated = $conn->prepare("SELECT * FROM Inventory WHERE asset_id = ?");
    $stmt_updated->execute([$asset_id]);
    $updatedAsset = $stmt_updated->fetch(PDO::FETCH_ASSOC);
    respondWithSuccess(['asset' => $updatedAsset], "Statut de maintenance mis à jour.");
}

function getUsers($conn) {
    $stmt = $conn->prepare("SELECT user_id, prenom, nom FROM Users WHERE status = 'Active' ORDER BY prenom, nom");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respondWithSuccess(['users' => $users]);
}

function addAsset($conn, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['barcode'], $data['asset_name'], $data['asset_type'])) throw new Exception("Données manquantes pour l'ajout de l'actif.");
    $barcode = trim($data['barcode']);
    $asset_name = trim($data['asset_name']);
    if (empty($barcode) || empty($asset_name)) throw new Exception("Le code-barres et le nom de l'actif sont obligatoires.");
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Inventory WHERE barcode = ?");
    $stmt_check->execute([$barcode]);
    if ($stmt_check->fetchColumn() > 0) throw new Exception("Ce code-barres existe déjà dans l'inventaire.");
    $sql = "INSERT INTO Inventory (barcode, asset_type, category_id, asset_name, brand, serial_or_plate, position_or_info, status, fuel_level, date_added, last_modified) VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?, GETDATE(), GETDATE())";
    $params = [$barcode, $data['asset_type'], empty($data['category_id']) ? null : $data['category_id'], $asset_name, empty($data['brand']) ? null : trim($data['brand']), empty($data['serial_or_plate']) ? null : trim($data['serial_or_plate']), empty($data['position_or_info']) ? null : trim($data['position_or_info']), empty($data['fuel_level']) ? null : $data['fuel_level']];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $newId = $conn->lastInsertId();
    $select_stmt = $conn->prepare("SELECT i.*, ac.category_name, u.prenom AS assigned_to_prenom, u.nom AS assigned_to_nom FROM Inventory i LEFT JOIN AssetCategories ac ON i.category_id = ac.category_id LEFT JOIN Users u ON i.assigned_to_user_id = u.user_id WHERE i.asset_id = ?");
    $select_stmt->execute([$newId]);
    $newAsset = $select_stmt->fetch(PDO::FETCH_ASSOC);
    if ($newAsset) respondWithSuccess(['asset' => $newAsset], "Actif ajouté avec succès.");
    else throw new Exception("Échec de la création ou de la récupération de l'actif.");
}

function deleteAsset($conn, $user) {
    if ($user['role'] !== 'admin') respondWithError("Accès non autorisé.", 403);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['asset_id'])) throw new Exception("ID de l'actif manquant.");
    $asset_id = $data['asset_id'];
    $stmt_bookings = $conn->prepare("DELETE FROM Bookings WHERE asset_id = ?");
    $stmt_bookings->execute([$asset_id]);
    $stmt = $conn->prepare("DELETE FROM Inventory WHERE asset_id = ?");
    $stmt->execute([$asset_id]);
    if ($stmt->rowCount() > 0) respondWithSuccess([], "Actif supprimé avec succès.");
    else throw new Exception("L'actif à supprimer n'a pas été trouvé.");
}
?>
