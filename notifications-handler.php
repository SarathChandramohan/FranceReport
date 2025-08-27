<?php
require_once 'db-connection.php';
require_once 'session-management.php';

requireLogin();
$user = getCurrentUser();
$user_id = $user['user_id'];

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_notifications':
            getNotifications($conn, $user_id);
            break;
        case 'mark_as_read':
            markAllAsRead($conn, $user_id);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non valide']);
    }
} catch (Exception $e) {
    error_log("Notification handler error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne est survenue.']);
}

function getNotifications($conn, $userId) {
    $sql = "SELECT notification_id, message, is_read, link, created_at FROM Notifications WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unread_count_sql = "SELECT COUNT(*) FROM Notifications WHERE user_id = ? AND is_read = 0";
    $unread_count_stmt = $conn->prepare($unread_count_sql);
    $unread_count_stmt->execute([$userId]);
    $unread_count = $unread_count_stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'data' => $notifications, 'unread_count' => $unread_count]);
}

function markAllAsRead($conn, $userId) {
    $sql = "UPDATE Notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    echo json_encode(['status' => 'success', 'message' => 'Notifications marquées comme lues.']);
}
?>
