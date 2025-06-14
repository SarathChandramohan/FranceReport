<?php
require_once 'db-connection.php';
require_once 'session-management.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expirée.']);
    exit;
}

$currentUser = getCurrentUser();
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');


try {
    switch ($action) {
        case 'send_message':
            sendMessage($conn, $currentUser);
            break;
        case 'get_sent_messages':
            getSentMessages($conn, $currentUser['user_id']);
            break;
        case 'get_received_messages':
            getReceivedMessages($conn, $currentUser['user_id']);
            break;
        case 'get_message_details':
            // Use GET for message_id to simplify AJAX calls
            $messageId = isset($_GET['message_id']) ? $_GET['message_id'] : (isset($_POST['message_id']) ? $_POST['message_id'] : null);
            getMessageDetails($conn, $currentUser['user_id'], $messageId);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }
} catch (Exception $e) {
    error_log("Messages handler error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne est survenue: ' . $e->getMessage()]);
}

function sendMessage($conn, $user) {
    $conn->beginTransaction();
    try {
        if (empty($_POST['recipient_type']) || empty($_POST['subject']) || empty($_POST['content'])) {
            throw new Exception('Tous les champs sont requis.');
        }

        $parentMessageId = isset($_POST['parent_message_id']) && !empty($_POST['parent_message_id']) ? intval($_POST['parent_message_id']) : null;
        $priority = isset($_POST['priority']) && !empty($_POST['priority']) ? $_POST['priority'] : 'normale';
        $recipientType = $_POST['recipient_type'];

        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            // Attachment handling logic... (same as before)
        }

        $sql = "INSERT INTO Messages (sender_user_id, recipient_type, subject, content, priority, attachment_path, parent_message_id, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
        $params = [$user['user_id'], $recipientType, $_POST['subject'], $_POST['content'], $priority, $attachmentPath, $parentMessageId];
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $messageId = $conn->lastInsertId();

        $recipientIds = [];
        if ($recipientType === 'individual') {
            $recipientIds = isset($_POST['individual_recipients']) ? $_POST['individual_recipients'] : [];
        } else {
            // Logic for 'all_users', 'rh', 'direction' (same as before)
        }
        
        if (!empty($recipientIds)) {
            $sqlRecipient = "INSERT INTO Message_Recipients (message_id, recipient_user_id) VALUES (?, ?)";
            $stmtRecipient = $conn->prepare($sqlRecipient);
            foreach (array_unique($recipientIds) as $recipientId) {
                 if ($recipientId != $user['user_id']) {
                    $stmtRecipient->execute([$messageId, $recipientId]);
                }
            }
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Message envoyé avec succès.']);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getSentMessages($conn, $userId) {
    $sql = "SELECT 
                m.message_id, m.subject, m.recipient_type, m.priority, FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at,
                (SELECT COUNT(*) FROM Message_Recipients mr WHERE mr.message_id = m.message_id) as total_recipients,
                (SELECT COUNT(*) FROM Message_Recipients mr WHERE mr.message_id = m.message_id AND mr.is_read = 1) as read_recipients
            FROM Messages m
            WHERE m.sender_user_id = ? 
            ORDER BY m.sent_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($messages as &$msg) {
        $msg['recipient_display'] = getRecipientDisplayName($msg['recipient_type']);
        $total = (int)$msg['total_recipients'];
        $read = (int)$msg['read_recipients'];
        $msg['read_percentage'] = ($total > 0) ? round(($read / $total) * 100) : 0;
    }

    echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getReceivedMessages($conn, $userId) {
    $sql = "SELECT m.message_id, m.subject, m.priority, u.prenom + ' ' + u.nom as sender_name, FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at, r.is_read
            FROM Message_Recipients r
            JOIN Messages m ON r.message_id = m.message_id
            JOIN Users u ON m.sender_user_id = u.user_id
            WHERE r.recipient_user_id = ? ORDER BY m.sent_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getMessageDetails($conn, $userId, $messageId) {
    if (empty($messageId)) { throw new Exception('ID de message invalide.'); }

    $stmtCheck = $conn->prepare("
        SELECT 
            (CASE WHEN m.sender_user_id = ? THEN 1 ELSE 0 END) as is_sender,
            (SELECT COUNT(*) FROM Message_Recipients mr WHERE mr.message_id = m.message_id AND mr.recipient_user_id = ?) as is_recipient
        FROM Messages m WHERE m.message_id = ?");
    $stmtCheck->execute([$userId, $userId, $messageId]);
    $auth = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$auth || ($auth['is_sender'] == 0 && $auth['is_recipient'] == 0)) {
        throw new Exception('Accès non autorisé à ce message.');
    }

    if($auth['is_recipient'] > 0) {
        $stmtRead = $conn->prepare("UPDATE Message_Recipients SET is_read = 1, read_at = GETDATE() WHERE message_id = ? AND recipient_user_id = ? AND is_read = 0");
        $stmtRead->execute([$messageId, $userId]);
    }
    
    $sqlDetails = "SELECT m.subject, m.content, m.attachment_path, m.sender_user_id, u.prenom + ' ' + u.nom as sender_name, FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at
            FROM Messages m JOIN Users u ON m.sender_user_id = u.user_id
            WHERE m.message_id = ?";
    $stmtDetails = $conn->prepare($sqlDetails);
    $stmtDetails->execute([$messageId]);
    $details = $stmtDetails->fetch(PDO::FETCH_ASSOC);

    if($auth['is_sender'] == 1) {
        $sqlReceipts = "SELECT u.prenom + ' ' + u.nom as recipient_name, r.is_read, FORMAT(r.read_at, 'dd/MM/yyyy HH:mm') as read_at
                        FROM Message_Recipients r JOIN Users u ON r.recipient_user_id = u.user_id
                        WHERE r.message_id = ? ORDER BY u.nom";
        $stmtReceipts = $conn->prepare($sqlReceipts);
        $stmtReceipts->execute([$messageId]);
        $details['receipts'] = $stmtReceipts->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['status' => 'success', 'data' => $details]);
}

function getRecipientDisplayName($type) {
    // Same as before
    return $type;
}
?>
