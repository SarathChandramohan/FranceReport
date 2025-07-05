<?php
// technician-handler.php

require_once 'db-connection.php';
require_once 'session-management.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired or not authenticated. Please log in.']);
    exit;
}

$currentUser = getCurrentUser();
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'get_missions':
            getMissionsForTechnician($conn, $currentUser['user_id']);
            break;
        case 'process_scan':
            processScan($conn, $currentUser['user_id'], $_POST);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'An internal server error occurred.']);
}

function getMissionsForTechnician($conn, $userId) {
    // This is a sample implementation. You should customize this query
    // to fetch missions assigned to the logged-in technician for the current day.
    // This query assumes you have a 'Planning_Assignments' table.
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT 
            assignment_id as id, 
            mission_text as title, 
            location, 
            'high' as priority, -- Sample priority
            '4' as duration, -- Sample duration
            '2' as technicians, -- Sample technicians
            start_time as time
        FROM Planning_Assignments 
        WHERE assigned_user_id = ? AND assignment_date = ?
    ");
    $stmt->execute([$userId, $today]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'missions' => $missions]);
}

function processScan($conn, $userId, $data) {
    $barcode = isset($data['barcode']) ? $data['barcode'] : '';
    $scanType = isset($data['scan_type']) ? $data['scan_type'] : ''; // 'checkout' or 'return'
    
    // You should implement your logic here to handle the checkout and return of tools.
    // This will likely involve updating the 'Inventory' and 'Bookings' tables.
    
    // Sample response
    if ($scanType === 'checkout') {
        // Your logic to checkout the item
        echo json_encode(['status' => 'success', 'message' => "Item with barcode {$barcode} checked out."]);
    } elseif ($scanType === 'return') {
        // Your logic to return the item
        echo json_encode(['status' => 'success', 'message' => "Item with barcode {$barcode} returned."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid scan type.']);
    }
}
?>
