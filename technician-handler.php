<?php
// technician-handler.php

// Ensure all dependencies are correctly loaded
require_once 'db.php'; 
require_once 'C:/Users/sarat/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// It's good practice to wrap the handler logic in a class
class TechnicianHandler {
    private $log;

    public function __construct() {
        // Setup Logger for debugging and error tracking
        $this->log = new Logger('TechnicianHandler');
        $this->log->pushHandler(new StreamHandler('debug.log', Logger::DEBUG));
    }

    /**
     * Handles the scanning of an asset tag.
     * It fetches asset details from the database.
     */
    public function handleScan($p) {
        $data = json_decode($p->request->body, true);
        $assetTag = $data['assetTag'] ?? '';

        if (empty($assetTag)) {
            $p->response->body = json_encode(['status' => 'error', 'message' => 'Asset tag is required.']);
            return;
        }

        $pdo = (new Database())->connect();
        if ($pdo === null) {
            $p->response->body = json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
            return;
        }

        try {
            $sql = "SELECT asset_id, name, status FROM Assets WHERE asset_tag = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$assetTag]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($asset) {
                // Check for current and future bookings for this asset
                $bookingSql = "SELECT booking_date FROM Bookings WHERE asset_id = ? AND booking_date >= CURDATE() AND status IN ('booked', 'active') ORDER BY booking_date ASC";
                $bookingStmt = $pdo->prepare($bookingSql);
                $bookingStmt->execute([$asset['asset_id']]);
                $bookings = $bookingStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $asset['bookings'] = $bookings;
                $p->response->body = json_encode(['status' => 'success', 'asset' => $asset]);
            } else {
                $p->response->body = json_encode(['status' => 'error', 'message' => 'Asset not found.']);
            }
        } catch (Exception $e) {
            $this->log->error("Scan failed: " . $e->getMessage());
            $p->response->body = json_encode(['status' => 'error', 'message' => 'An error occurred during the scan.']);
        }
    }
    
    /**
     * Books an item for multiple days and marks it as picked up ('active').
     * This function now correctly checks for availability across the entire date range.
     */
    public function bookAndPickupMultipleDays($p) {
        $data = json_decode($p->request->body, true);
        $assetId = $data['assetId'] ?? null;
        $dates = $data['dates'] ?? [];

        if (!$assetId || count($dates) < 2) {
            $p->response->body = json_encode(['status' => 'error', 'message' => 'Asset ID and a valid date range are required.']);
            return;
        }

        // Ensure dates are sorted chronologically
        sort($dates);
        $startDate = $dates[0];
        $endDate = end($dates); // Gets the last date from the array

        $pdo = (new Database())->connect();
        if ($pdo === null) {
            $p->response->body = json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
            return;
        }

        try {
            $pdo->beginTransaction();

            // --- START OF CORRECTED LOGIC ---
            // This block checks for any bookings within the entire selected date range.
            $checkRangeSql = "SELECT COUNT(*) FROM Bookings WITH (UPDLOCK) WHERE asset_id = ? AND booking_date >= ? AND booking_date <= ? AND status IN ('booked', 'active')";
            $checkRangeStmt = $pdo->prepare($checkRangeSql);
            $checkRangeStmt->execute([$assetId, $startDate, $endDate]);
            $bookingCount = $checkRangeStmt->fetchColumn();

            if ($bookingCount > 0) {
                // If any booking exists in the range, the item is unavailable.
                $pdo->rollBack();
                $p->response->body = json_encode(['status' => 'error', 'message' => 'L\'article n\'est pas disponible pour toute la période sélectionnée.']);
                return;
            }
            // --- END OF CORRECTED LOGIC ---

            // If no conflicting bookings, proceed to insert the new booking records.
            $insertSql = "INSERT INTO Bookings (asset_id, booking_date, status, booked_by_user_id) VALUES (?, ?, 'active', ?)";
            $insertStmt = $pdo->prepare($insertSql);
            
            // Assuming technician's user ID is stored in the session
            $technicianId = $_SESSION['user_id'] ?? 0; 
            if ($technicianId === 0) {
                 $this->log->warning("User ID not found in session. Using 0 as default.");
            }

            // Create a DatePeriod to iterate through all dates from start to end, inclusive.
            $period = new DatePeriod(
                 new DateTime($startDate),
                 new DateInterval('P1D'),
                 (new DateTime($endDate))->modify('+1 day') // The end date needs to be included in the period.
            );

            foreach ($period as $date) {
                $currentDate = $date->format('Y-m-d');
                $insertStmt->execute([$assetId, $currentDate, $technicianId]);
            }

            // Finally, update the asset's main status to 'in_use'
            $updateAssetSql = "UPDATE Assets SET status = 'in_use' WHERE asset_id = ?";
            $updateAssetStmt = $pdo->prepare($updateAssetSql);
            $updateAssetStmt->execute([$assetId]);

            // If everything is successful, commit the transaction.
            $pdo->commit();
            $p->response->body = json_encode(['status' => 'success', 'message' => 'Article réservé et récupéré avec succès pour la période du ' . $startDate . ' au ' . $endDate]);

        } catch (Exception $e) {
            // If any error occurs, roll back the entire transaction.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->log->error("Booking failed: " . $e->getMessage());
            
            // The specific error message from the database is sent to the client.
            // This is useful for debugging but you might want a more generic message in production.
            $errorMessage = 'Échec de la réservation de l\'article. Erreur: ' . $e->getMessage();
            $p->response->body = json_encode(['status' => 'error', 'message' => $errorMessage]);
        }
    }
    
    // You can add other technician-related functions here in the future
    // For example, a function to handle returning an item.
    
    /*
    public function handleReturn($p) {
        // Logic for returning an asset
    }
    */
}

// This part of the code acts as a simple router.
// It checks the 'action' parameter and calls the corresponding method.
// This is a common pattern for handling multiple operations in a single file.

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$handler = new TechnicianHandler();
$p = new stdClass(); // A generic object to pass around request/response data
$p->request = new stdClass();
$p->response = new stdClass();
$p->request->body = file_get_contents('php://input');

switch ($action) {
    case 'scan':
        $handler->handleScan($p);
        break;
    case 'bookAndPickup':
        $handler->bookAndPickupMultipleDays($p);
        break;
    // Add more cases for other actions like 'return'
    // case 'return':
    //    $handler->handleReturn($p);
    //    break;
    default:
        $p->response->body = json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
        break;
}

// Output the response as JSON
header('Content-Type: application/json');
echo $p->response->body;

?>
