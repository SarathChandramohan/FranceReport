<?php
// Set the correct content type for JSON responses
header('Content-Type: application/json');

// It's good practice to prevent caching of this dynamic endpoint
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

require_once 'session-management.php';
require_once 'db-connection.php';

// --- Error Handling Function ---
function send_json_error($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// --- Main Logic ---

// 1. Check if user is logged in
if (!isUserLoggedIn()) {
    send_json_error('Not authenticated', 401);
}

$user = getCurrentUser();
// Use 'user_id' to match your latest schema
$userId = $user['user_id']; 

// 2. Establish database connection
$conn = getDbConnection();
if (!$conn) {
    // We can't use sqlsrv_errors() if the connection itself failed
    send_json_error('Database connection failed.');
}

$notifications = [];
$unreadCount = 0;

// 3. Prepare and execute the query for notifications
$sql = "SELECT TOP 5 notification_id, message, link, created_at FROM Notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
$params = [$userId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // Log the actual DB error for debugging, but send a generic message to the user
    error_log(print_r(sqlsrv_errors(), true));
    send_json_error('Database query failed to fetch notifications.');
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $notifications[] = $row;
}

// 4. Prepare and execute the query for the unread count
$sqlCount = "SELECT COUNT(*) as unread_count FROM Notifications WHERE user_id = ? AND is_read = 0";
$stmtCount = sqlsrv_query($conn, $sqlCount, $params);

if ($stmtCount && ($row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC))) {
    $unreadCount = $row['unread_count'];
} else {
    // If the count query fails, we can still proceed but maybe log it
    error_log("Failed to get notification count for user_id: $userId");
}

// 5. Clean up and send the response
sqlsrv_free_stmt($stmt);
if ($stmtCount) sqlsrv_free_stmt($stmtCount);
sqlsrv_close($conn);

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => (int)$unreadCount // Cast to integer for safety
]);
?>
