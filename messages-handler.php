<?php
require_once 'db-connection.php';
require_once 'session-management.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expirée.']);
    exit;
}

$currentUser = getCurrentUser();
$action = isset($_POST['action']) ? $_POST['action'] : '';

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
            getMessageDetails($conn, $currentUser['user_id']);
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

        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            if ($_FILES['attachment']['size'] > 2 * 1024 * 1024) { // 2 MB
                throw new Exception('Le fichier est trop volumineux. La taille maximale est de 2 Mo.');
            }
            $uploadDir = 'uploads/messages/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = uniqid() . '-' . basename($_FILES['attachment']['name']);
            $targetFile = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                throw new Exception('Erreur lors du téléchargement du fichier.');
            }
            $attachmentPath = $targetFile;
        }

        $sql = "INSERT INTO Messages (sender_user_id, recipient_type, subject, content, priority, attachment_path, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
        $params = [$user['user_id'], $_POST['recipient_type'], $_POST['subject'], $_POST['content'], $_POST['priority'], $attachmentPath];
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $messageId = $conn->lastInsertId();

        $recipientIds = [];
        if ($_POST['recipient_type'] === 'individual') {
            $recipientIds = isset($_POST['individual_recipients']) ? $_POST['individual_recipients'] : [];
        } elseif ($_POST['recipient_type'] === 'all_users') {
            $recipientIds = $conn->query("SELECT user_id FROM Users WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
        } elseif (in_array($_POST['recipient_type'], ['rh', 'direction'])) {
            $role = 'admin'; // Assuming admin for both
            $stmtUsers = $conn->prepare("SELECT user_id FROM Users WHERE role = ? AND status = 'Active'");
            $stmtUsers->execute([$role]);
            $recipientIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
        }
        
        if (!empty($recipientIds)) {
            $sqlRecipient = "INSERT INTO Message_Recipients (message_id, recipient_user_id) VALUES (?, ?)";
            $stmtRecipient = $conn->prepare($sqlRecipient);
            foreach (array_unique($recipientIds) as $recipientId) {
                 if ($recipientId != $user['user_id']) { // Do not send to self
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
                COUNT(r.recipient_user_id) as total_recipients,
                SUM(CASE WHEN r.is_read = 1 THEN 1 ELSE 0 END) as read_recipients
            FROM Messages m
            LEFT JOIN Message_Recipients r ON m.message_id = r.message_id
            WHERE m.sender_user_id = ? 
            GROUP BY m.message_id, m.subject, m.recipient_type, m.priority, m.sent_at
            ORDER BY m.sent_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($messages as &$msg) {
        $msg['recipient_display'] = getRecipientDisplayName($msg['recipient_type']);
        $total = (int)$msg['total_recipients'];
        $read = (int)$msg['read_recipients'];
        $msg['read_status'] = "Lu par $read / $total";
        $msg['read_percentage'] = ($total > 0) ? ($read / $total) * 100 : 0;
    }

    echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getReceivedMessages($conn, $userId) {
    $sql = "SELECT m.message_id, m.subject, u.prenom + ' ' + u.nom as sender_name, FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at, r.is_read
            FROM Message_Recipients r
            JOIN Messages m ON r.message_id = m.message_id
            JOIN Users u ON m.sender_user_id = u.user_id
            WHERE r.recipient_user_id = ? ORDER BY m.sent_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getMessageDetails($conn, $userId) {
    $messageId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    if ($messageId <= 0) { throw new Exception('ID de message invalide.'); }

    // Check if user is either sender or recipient
    $stmtCheck = $conn->prepare("
        SELECT 
            (CASE WHEN m.sender_user_id = ? THEN 1 ELSE 0 END) as is_sender,
            (SELECT COUNT(*) FROM Message_Recipients mr WHERE mr.message_id = m.message_id AND mr.recipient_user_id = ?) as is_recipient
        FROM Messages m WHERE m.message_id = ?
    ");
    $stmtCheck->execute([$userId, $userId, $messageId]);
    $auth = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$auth || ($auth['is_sender'] == 0 && $auth['is_recipient'] == 0)) {
        throw new Exception('Accès non autorisé à ce message.');
    }

    // Mark as read if user is a recipient
    if($auth['is_recipient'] > 0) {
        $stmtRead = $conn->prepare("UPDATE Message_Recipients SET is_read = 1, read_at = GETDATE() WHERE message_id = ? AND recipient_user_id = ? AND is_read = 0");
        $stmtRead->execute([$messageId, $userId]);
    }
    
    // Fetch main message details
    $sqlDetails = "SELECT m.subject, m.content, m.attachment_path, u.prenom + ' ' + u.nom as sender_name, FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at
            FROM Messages m JOIN Users u ON m.sender_user_id = u.user_id
            WHERE m.message_id = ?";
    $stmtDetails = $conn->prepare($sqlDetails);
    $stmtDetails->execute([$messageId]);
    $details = $stmtDetails->fetch(PDO::FETCH_ASSOC);

    // Fetch read receipts if the user is the sender
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
    switch($type) {
        case 'rh': return 'Service RH';
        case 'direction': return 'Direction';
        case 'all_users': return 'Tous les utilisateurs';
        case 'individual': return 'Individuel';
        default: return ucfirst($type);
    }
}
?>
