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
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne est survenue.']);
}

function sendMessage($conn, $user) {
    $conn->beginTransaction();
    try {
        if (empty($_POST['recipient_type']) || empty($_POST['subject']) || empty($_POST['content'])) {
            throw new Exception('Tous les champs sont requis.');
        }

        $recipientType = $_POST['recipient_type'];
        $individualRecipients = isset($_POST['individual_recipients']) ? $_POST['individual_recipients'] : [];
        $priority = isset($_POST['priority']) && !empty($_POST['priority']) ? $_POST['priority'] : 'normale';


        if ($recipientType === 'individual' && empty($individualRecipients)) {
            throw new Exception('Veuillez sélectionner au moins un destinataire individuel.');
        }

        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
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
        $params = [
            $user['user_id'], $recipientType, $_POST['subject'],
            $_POST['content'], $priority, $attachmentPath
        ];
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $messageId = $conn->lastInsertId();

        // Populate recipients table
        $recipientIds = [];
        if ($recipientType === 'individual') {
            $recipientIds = $individualRecipients;
        } elseif ($recipientType === 'all_users') {
            $stmtUsers = $conn->query("SELECT user_id FROM Users WHERE status = 'Active'");
            $recipientIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN, 0);
        } elseif ($recipientType === 'rh' || $recipientType === 'direction') {
            $role = 'admin'; // Assuming admin role for both for now
            $stmtUsers = $conn->prepare("SELECT user_id FROM Users WHERE role = ? AND status = 'Active'");
            $stmtUsers->execute([$role]);
            $recipientIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN, 0);
        }
        
        if (!empty($recipientIds)) {
            $sqlRecipient = "INSERT INTO Message_Recipients (message_id, recipient_user_id) VALUES (?, ?)";
            $stmtRecipient = $conn->prepare($sqlRecipient);
            foreach ($recipientIds as $recipientId) {
                // Avoid sending to self if not intended
                if ($recipientId != $user['user_id']) {
                    $stmtRecipient->execute([$messageId, $recipientId]);
                }
            }
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Message envoyé avec succès.']);

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Send Message Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getSentMessages($conn, $userId) {
    $sql = "SELECT message_id, subject, recipient_type, priority, FORMAT(sent_at, 'dd/MM/yyyy HH:mm') as sent_at FROM Messages 
            WHERE sender_user_id = ? ORDER BY sent_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($messages as &$msg) {
        $msg['recipient_display'] = getRecipientDisplayName($msg['recipient_type']);
        $msg['status'] = 'Envoyé';
    }

    echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getReceivedMessages($conn, $userId) {
    $sql = "SELECT 
                m.message_id, m.subject, m.priority,
                u.prenom + ' ' + u.nom as sender_name,
                FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at,
                r.is_read
            FROM Message_Recipients r
            JOIN Messages m ON r.message_id = m.message_id
            JOIN Users u ON m.sender_user_id = u.user_id
            WHERE r.recipient_user_id = ?
            ORDER BY m.sent_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getMessageDetails($conn, $userId) {
    $messageId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    if ($messageId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID de message invalide.']);
        return;
    }

    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Message_Recipients WHERE message_id = ? AND recipient_user_id = ?");
    $stmtCheck->execute([$messageId, $userId]);
    if ($stmtCheck->fetchColumn() == 0) {
        // Also check if the user is the sender
         $stmtSenderCheck = $conn->prepare("SELECT COUNT(*) FROM Messages WHERE message_id = ? AND sender_user_id = ?");
         $stmtSenderCheck->execute([$messageId, $userId]);
         if($stmtSenderCheck->fetchColumn() == 0){
            echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé à ce message.']);
            return;
         }
    }

    // Mark as read if user is a recipient
    $stmtRead = $conn->prepare("UPDATE Message_Recipients SET is_read = 1, read_at = GETDATE() WHERE message_id = ? AND recipient_user_id = ? AND is_read = 0");
    $stmtRead->execute([$messageId, $userId]);
    
    // Fetch details
    $sql = "SELECT m.subject, m.content, m.attachment_path, m.priority, u.prenom + ' ' + u.nom as sender_name, FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at
            FROM Messages m
            JOIN Users u ON m.sender_user_id = u.user_id
            WHERE m.message_id = ?";
    $stmtDetails = $conn->prepare($sql);
    $stmtDetails->execute([$messageId]);
    $details = $stmtDetails->fetch(PDO::FETCH_ASSOC);

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
