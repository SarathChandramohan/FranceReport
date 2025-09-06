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
            $messageId = isset($_GET['message_id']) ? $_GET['message_id'] : (isset($_POST['message_id']) ? $_POST['message_id'] : null);
            getMessageDetails($conn, $currentUser['user_id'], $messageId);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }
} catch (Exception $e) {
    error_log("Messages handler error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function sendMessage($conn, $user) {
    $conn->beginTransaction();
    try {
        $parentMessageId = isset($_POST['parent_message_id']) && !empty($_POST['parent_message_id']) ? intval($_POST['parent_message_id']) : null;

        if (empty($_POST['subject']) || empty($_POST['content'])) {
            throw new Exception('Le sujet et le contenu du message sont requis.');
        }

        $recipientIds = [];
        $recipientType = $_POST['recipient_type'];

        if ($parentMessageId) {
            // This is a reply. Recipient is the sender of the parent message.
            $stmtSender = $conn->prepare("SELECT sender_user_id FROM Messages WHERE message_id = ?");
            $stmtSender->execute([$parentMessageId]);
            $originalSenderId = $stmtSender->fetchColumn();
            if (!$originalSenderId) {
                throw new Exception("Message original non trouvé pour la réponse.");
            }
            $recipientIds[] = $originalSenderId;
            $recipientType = 'individual'; // Force type for replies
        } else {
            // This is a new message. Validate recipients from the form.
            if (empty($recipientType)) {
                throw new Exception('Veuillez sélectionner un type de destinataire.');
            }
            if ($recipientType === 'individual') {
                if (empty($_POST['individual_recipients'])) {
                    throw new Exception('Veuillez sélectionner au moins un destinataire individuel.');
                }
                $recipientIds = $_POST['individual_recipients'];
            } elseif ($recipientType === 'all_users') {
                $stmtUsers = $conn->prepare("SELECT user_id FROM Users WHERE status = 'Active' AND user_id != ?");
                $stmtUsers->execute([$user['user_id']]);
                $recipientIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
            } elseif (in_array($recipientType, ['rh', 'direction'])) {
                $role = 'admin';
                $stmtUsers = $conn->prepare("SELECT user_id FROM Users WHERE role = ? AND status = 'Active' AND user_id != ?");
                $stmtUsers->execute([$role, $user['user_id']]);
                $recipientIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        
        if (empty($recipientIds)) {
            throw new Exception("Aucun destinataire valide trouvé.");
        }
        
        $priority = isset($_POST['priority']) && !empty($_POST['priority']) ? $_POST['priority'] : 'normale';
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            if ($_FILES['attachment']['size'] > 2 * 1024 * 1024) { throw new Exception('Le fichier est trop volumineux. La taille maximale est de 2 Mo.'); }
            $uploadDir = 'uploads/messages/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = uniqid() . '-' . basename($_FILES['attachment']['name']);
            $targetFile = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) { throw new Exception('Erreur lors du téléchargement du fichier.'); }
            $attachmentPath = $targetFile;
        }

        $sql = "INSERT INTO Messages (sender_user_id, recipient_type, subject, content, priority, attachment_path, parent_message_id, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
        $params = [$user['user_id'], $recipientType, $_POST['subject'], $_POST['content'], $priority, $attachmentPath, $parentMessageId];
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $messageId = $conn->lastInsertId();
        
        $sqlRecipient = "INSERT INTO Message_Recipients (message_id, recipient_user_id) VALUES (?, ?)";
        $stmtRecipient = $conn->prepare($sqlRecipient);
        foreach (array_unique($recipientIds) as $recipientId) {
            $stmtRecipient->execute([$messageId, $recipientId]);
        }
        // Add this code inside the sendMessage function before the commit.
        $notification_title = "Nouveau message de " . $user['prenom'] . ' ' . $user['nom'];
        $notification_body = $_POST['subject'];
        $notification_link = 'messages.php?message_id=' . $messageId;
        
        $insert_notif_sql = "INSERT INTO Notifications (user_id, message, link) VALUES (?, ?, ?)";
        $insert_notif_stmt = $conn->prepare($insert_notif_sql);

        foreach (array_unique($recipientIds) as $recipientId) {
            // Send push notification to the individual recipient
            sendNotificationToUser($recipientId, $notification_title, $notification_body);
            
            // Insert notification into the database
            $insert_notif_stmt->execute([$recipientId, $notification_message, $notification_link]);
        }
// This is the end of the code you need to add.
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
    switch($type) {
        case 'rh': return 'Service RH';
        case 'direction': return 'Direction';
        case 'all_users': return 'Tous les utilisateurs';
        case 'individual': return 'Individuel';
        default: return ucfirst($type);
    }
}
?>
