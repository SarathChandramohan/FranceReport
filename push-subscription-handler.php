<?php
// push-subscription-handler.php

require_once 'session-management.php';
require_once 'db-connection.php';
requireLogin();

header('Content-Type: application/json');
$user = getCurrentUser();
$data = json_decode(file_get_contents('php://input'), true);

if (!$user || !$data || !isset($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit();
}

try {
    $endpoint = $data['endpoint'];
    $p256dh = $data['keys']['p256dh'];
    $auth = $data['keys']['auth'];

    // Use MERGE for an efficient "insert or update" operation
    $stmt = $conn->prepare("
        MERGE WebPushSubscriptions AS target
        USING (VALUES (?, ?, ?, ?)) AS source (user_id, endpoint, p256dh, auth)
        ON target.subscription_endpoint = source.endpoint
        WHEN NOT MATCHED THEN
            INSERT (user_id, subscription_endpoint, subscription_p256dh, subscription_auth)
            VALUES (source.user_id, source.endpoint, source.p256dh, source.auth);
    ");
    $stmt->execute([$user['user_id'], $endpoint, $p256dh, $auth]);
    
    echo json_encode(['success' => true, 'message' => 'Subscription saved.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Push Subscription DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
?>
