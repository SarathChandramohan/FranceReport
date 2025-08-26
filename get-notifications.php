<?php
header('Content-Type: application/json');
require_once 'session-management.php';
require_once 'db-connection.php';

// Ensure the user is logged in
if (!isUserLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    http_response_code(401);
    exit;
}

$user = getCurrentUser();
// Use 'user_id' to match your latest schema
$userId = $user['user_id']; 

$conn = getDbConnection();
$notifications = [];
$unreadCount = 0;

// SQL query to get the 5 most recent unread notifications
$sql = "SELECT TOP 5 notification_id, message, link, created_at FROM Notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
$params = array($userId);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // Log error instead of breaking the page
    // error_log(print_r(sqlsrv_errors(), true));
    echo json_encode(['error' => 'Database query failed.']);
    http_response_code(500);
    exit;
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $notifications[] = $row;
}

// SQL query to get the total count of unread notifications
$sqlCount = "SELECT COUNT(*) as unread_count FROM Notifications WHERE user_id = ? AND is_read = 0";
$stmtCount = sqlsrv_query($conn, $sqlCount, $params);
if ($stmtCount && ($row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC))) {
    $unreadCount = $row['unread_count'];
}


sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);
?>
