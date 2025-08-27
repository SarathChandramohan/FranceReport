<?php
// notification-manager.php

require_once __DIR__ . '/vendor/autoload.php';
require_once 'db-connection.php'; // Your existing DB connection
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * A centralized function to send push notifications to a group of users based on their role.
 *
 * @param string $role The role of users to notify (e.g., 'admin').
 * @param string $title The title of the notification.
 * @param string $body The body text of the notification.
 * @param string $iconUrl Optional URL to an icon for the notification.
 */
function sendNotificationByRole(string $role, string $title, string $body, string $iconUrl = '/Logo.png') {
    global $conn;

    // --- IMPORTANT: PASTE YOUR VAPID KEYS HERE ---
    $vapidPublicKey = 'BAoBqkEtJlTETgmJptadY56XGZpwxxlvf1R9N1ZYsy8em8FJkriA1HDmqGrpQTwg7OaVY51n7szFW1wv0zcoKjM';
    $vapidPrivateKey = 'rbM28iK7lxqbHouimk8ck3yourM-guMFjWs2RzMb7-k';
    // ---------------------------------------------

    if (empty($vapidPublicKey) || strpos($vapidPublicKey, 'REPLACE') !== false) {
        error_log("VAPID keys are not configured in notification-manager.php");
        return; // Silently fail if keys are not set
    }

    $auth = [
        'VAPID' => [
            'subject' => 'mailto:sarath90941@gmail.com', // Replace with your admin email
            'publicKey' => $vapidPublicKey,
            'privateKey' => $vapidPrivateKey,
        ],
    ];

    try {
        $stmt = $conn->prepare("
            SELECT s.subscription_endpoint, s.subscription_p256dh, s.subscription_auth 
            FROM WebPushSubscriptions s
            JOIN Users u ON s.user_id = u.user_id
            WHERE u.role = ? AND u.status = 'Active'
        ");
        $stmt->execute([$role]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subscriptions)) {
            return; // No one to notify
        }

        $webPush = new WebPush($auth);
        $notificationPayload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $iconUrl,
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['subscription_endpoint'],
                'publicKey' => $sub['subscription_p256dh'],
                'authToken' => $sub['subscription_auth'],
            ]);
            $webPush->queueNotification($subscription, $notificationPayload);
        }

        // Send all queued notifications
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                // If a subscription is expired or invalid, the push service tells us.
                // We should remove it from our database.
                if ($report->isSubscriptionExpired()) {
                    $endpoint = $report->getEndpoint();
                    $stmt_delete = $conn->prepare("DELETE FROM WebPushSubscriptions WHERE subscription_endpoint = ?");
                    $stmt_delete->execute([$endpoint]);
                }
                error_log("Notification failed for endpoint: {$report->getEndpoint()} with reason: {$report->getReason()}");
            }
        }

    } catch (Exception $e) {
        error_log("Error sending push notification: " . $e->getMessage());
    }
}
?>
