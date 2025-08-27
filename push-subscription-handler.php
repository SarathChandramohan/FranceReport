<?php
// push-subscription-handler.php

require_once 'session-management.php';
require_once 'db-connection.php';
requireLogin();

header('Content-Type: application/json');
$user = getCurrentUser();
$data = json_decode(file_get_contents('php://input'), true);

if (!$user || !$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit();
}

try {
    switch ($data['action']) {
        case 'subscribe':
            if (!isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid subscription data.']);
                exit();
            }
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
            break;

        case 'unsubscribe':
            if (!isset($data['endpoint'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid unsubscribe data.']);
                exit();
            }
            $endpoint_to_delete = $data['endpoint'];
            $stmt = $conn->prepare("DELETE FROM WebPushSubscriptions WHERE subscription_endpoint = ? AND user_id = ?");
            $stmt->execute([$endpoint_to_delete, $user['user_id']]);
            echo json_encode(['success' => true, 'message' => 'Subscription deleted.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Push Subscription DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
?>
